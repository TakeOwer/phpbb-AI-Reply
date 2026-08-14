<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\worker;

use phpbb\db\driver\driver_interface;
use phpbb\language\language;
use salvocortesiano\aireply\bot\bot;
use salvocortesiano\aireply\content\text_extractor;
use salvocortesiano\aireply\provider\ai_message;
use salvocortesiano\aireply\queue\job;

/**
 * Ricostruisce la conversazione da passare al modello.
 *
 * Differenza sostanziale rispetto ad AI Labs, che risaliva la catena delle
 * citazioni con una regex e — al primo giro — riscriveva il campo `post_text`
 * del post dell'utente per troncare il contenuto citato. Quel metodo modifica
 * contenuto altrui senza traccia in `post_edit_*` e si rompe appena qualcuno
 * risponde senza citare.
 *
 * Qui si leggono semplicemente gli ultimi N post del topic in ordine
 * cronologico. Più prevedibile, e non tocca i dati degli utenti.
 */
class context_builder
{
	/** @var driver_interface */
	protected $db;

	/** @var text_extractor */
	protected $extractor;

	/** @var language */
	protected $language;

	/** @var string[]|null Espressioni che riconoscono la dichiarazione, costruite una volta sola */
	protected $disclosure_patterns = null;

	/** Lunghezza massima di un singolo post nel contesto, prima del taglio */
	public const MAX_CHARS_PER_POST = 2000;

	public function __construct(driver_interface $db, text_extractor $extractor, language $language)
	{
		$this->db = $db;
		$this->extractor = $extractor;
		$this->language = $language;

		$this->language->add_lang('aireply', 'salvocortesiano/aireply');
	}

	/**
	 * Rimuove la dichiarazione di contenuto generato dai post del bot.
	 *
	 * Ogni risposta del bot termina con "Messaggio generato automaticamente
	 * da un modello di intelligenza artificiale (nome-modello)". Rimandarla
	 * indietro come turno precedente costa token a ogni chiamata e dice al
	 * modello qualcosa che non ha nulla a che vedere con la conversazione:
	 * nel migliore dei casi è rumore, nel peggiore lo induce a commentare la
	 * propria natura invece di rispondere all'utente.
	 */
	protected function strip_disclosure(string $text): string
	{
		if ($this->disclosure_patterns === null)
		{
			$this->disclosure_patterns = $this->build_disclosure_patterns();
		}

		$clean = preg_replace($this->disclosure_patterns, '', $text);

		return trim(is_string($clean) ? $clean : $text);
	}

	/**
	 * Espressioni che riconoscono la dichiarazione nei post già pubblicati.
	 *
	 * Se ne costruisce più di una di proposito. La dichiarazione viene scritta
	 * nel post al momento della pubblicazione, con la lingua che il worker
	 * aveva in quel momento — di norma quella predefinita della board. Ma
	 * l'amministratore può cambiare la lingua della board, oppure il cron può
	 * girare in un contesto diverso da quello in cui i post sono stati scritti:
	 * in quel caso una sola espressione, costruita sulla lingua attiva ora, non
	 * riconoscerebbe le dichiarazioni scritte prima.
	 *
	 * Si aggiunge quindi la versione inglese, che è la lingua di ripiego sempre
	 * presente in un'installazione phpBB. Resta un'euristica: se qualcuno
	 * riscrive la dichiarazione in una terza lingua e poi cambia lingua alla
	 * board, i vecchi post non verranno ripuliti. È rumore nel prompt, non un
	 * malfunzionamento.
	 *
	 * @return string[]
	 */
	protected function build_disclosure_patterns(): array
	{
		$variants = [$this->language->lang('AIREPLY_DISCLOSURE')];

		// Testo inglese predefinito, che resta valido anche se la board parla
		// un'altra lingua.
		$variants[] = 'This message was generated automatically by an artificial intelligence model (%s). It may contain errors.';

		$patterns = [];

		foreach (array_unique($variants) as $raw)
		{
			if (trim($raw) === '' || strpos($raw, '%s') === false)
			{
				continue;
			}

			// %s è il nome del modello: al suo posto un segnaposto non goloso.
			$patterns[] = '/' . str_replace('%s', '.{0,120}?', preg_quote($raw, '/')) . '/u';
		}

		return $patterns;
	}

	/**
	 * @return array{messages: ai_message[], stats: array}
	 */
	public function build(job $j, bot $b): array
	{
		$stats = [
			'context_posts_requested' => $b->context_posts,
			'context_posts_used'      => 0,
			'context_posts_dropped'   => 0,
			'context_chars'           => 0,
			'truncated_by_chars'      => false,
		];

		$messages = [];

		if ($b->has_memory())
		{
			$history = $this->fetch_history($j, $b, $stats);
			$messages = $history;
		}

		// Il post che ha innescato il job è sempre l'ultimo messaggio, anche
		// quando la memoria è disattivata: è la domanda a cui rispondere.
		$trigger_text = $this->extractor->truncate(trim($j->request), self::MAX_CHARS_PER_POST);

		if ($trigger_text !== '')
		{
			$already_included = false;

			// Con la memoria attiva il post di innesco è già dentro la
			// cronologia: aggiungerlo di nuovo lo farebbe leggere due volte.
			foreach ($messages as $message)
			{
				if (!$message->is_assistant() && mb_substr($message->text, 0, 120) === mb_substr($trigger_text, 0, 120))
				{
					$already_included = true;
					break;
				}
			}

			if (!$already_included)
			{
				$messages[] = ai_message::user($trigger_text, $this->get_poster_name($j->poster_id));
			}
		}

		$stats['context_chars'] = array_reduce($messages, static function ($carry, ai_message $m) {
			return $carry + mb_strlen($m->get_payload_text());
		}, 0);

		return ['messages' => $messages, 'stats' => $stats];
	}

	/**
	 * Ultimi N post del topic, dal più vecchio al più recente.
	 *
	 * @return ai_message[]
	 */
	protected function fetch_history(job $j, bot $b, array &$stats): array
	{
		/*
		 * Si interroga in ordine decrescente e si inverte dopo: prendere i
		 * "più recenti N" in ordine crescente richiederebbe un OFFSET calcolato
		 * sul totale, con una query in più e una condizione di gara se nel
		 * frattempo arriva un post nuovo.
		 */
		$sql_array = [
			'SELECT'    => 'p.post_id, p.poster_id, p.post_text, p.post_time, p.post_username, u.username',
			'FROM'      => [POSTS_TABLE => 'p'],
			'LEFT_JOIN' => [
				[
					'FROM' => [USERS_TABLE => 'u'],
					'ON'   => 'u.user_id = p.poster_id',
				],
			],
			'WHERE'     => 'p.topic_id = ' . (int) $j->topic_id . '
				AND p.post_visibility = ' . ITEM_APPROVED . '
				AND p.post_id <= ' . (int) $j->post_id,
			'ORDER_BY'  => 'p.post_time DESC, p.post_id DESC',
		];

		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query_limit($sql, $b->context_posts);

		$rows = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}

		$this->db->sql_freeresult($result);

		$rows = array_reverse($rows);

		$bot_user_id = $b->user_id;
		$messages = [];

		foreach ($rows as $row)
		{
			$text = $this->extractor->to_plain_text((string) $row['post_text']);
			$text = $this->extractor->truncate($text, self::MAX_CHARS_PER_POST);

			if ($text === '')
			{
				continue;
			}

			if ((int) $row['poster_id'] === $bot_user_id)
			{
				$text = $this->strip_disclosure($text);

				// Se dopo la pulizia non resta nulla, il post del bot conteneva
				// solo il contorno: passarlo come turno vuoto non aiuterebbe.
				if ($text === '')
				{
					continue;
				}

				$messages[] = ai_message::assistant($text);
			}
			else
			{
				$author = (string) ($row['username'] ?: $row['post_username'] ?: '');
				$messages[] = ai_message::user($text, $author);
			}
		}

		$stats['context_posts_used'] = count($messages);

		/*
		 * Il tetto in caratteri è quello che protegge davvero la spesa.
		 * Cinquanta post brevi e cinquanta post lunghi costano in modo molto
		 * diverso, e il modello conta i token, non i messaggi. Si tagliano i
		 * più vecchi, perché sono quelli meno rilevanti per la risposta.
		 */
		if ($b->context_max_chars > 0)
		{
			$total = array_reduce($messages, static function ($carry, ai_message $m) {
				return $carry + mb_strlen($m->get_payload_text());
			}, 0);

			while ($total > $b->context_max_chars && count($messages) > 1)
			{
				$removed = array_shift($messages);
				$total -= mb_strlen($removed->get_payload_text());

				$stats['context_posts_dropped']++;
				$stats['truncated_by_chars'] = true;
			}

			$stats['context_posts_used'] = count($messages);
		}

		return $messages;
	}

	protected function get_poster_name(int $poster_id): string
	{
		if ($poster_id === 0)
		{
			return '';
		}

		$sql = 'SELECT username FROM ' . USERS_TABLE . '
			WHERE user_id = ' . (int) $poster_id;

		$result = $this->db->sql_query($sql);
		$username = (string) $this->db->sql_fetchfield('username');
		$this->db->sql_freeresult($result);

		return $username;
	}
}
