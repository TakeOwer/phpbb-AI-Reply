<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\content;

use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\language\language;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Trova discussioni già esistenti collegate alla domanda dell'utente.
 *
 * ── Perché non si costruisce un indice nuovo ──────────────────────────────
 *
 * phpBB un indice sui post ce l'ha già, ed è la parte più costosa del lavoro.
 * Costruirne un secondo — pannello di indicizzazione, barra di progresso, ore
 * di attesa — significherebbe rifarlo da capo per ottenere lo stesso risultato.
 * Qui si interroga quello configurato dall'amministratore, qualunque sia:
 * nativo, MySQL, Postgres, Sphinx o un backend di terze parti.
 *
 * ── Perché due strategie e non una ────────────────────────────────────────
 *
 * phpBB non ha una vera interfaccia comune per i backend di ricerca: c'è una
 * classe base con metodi dalle firme lunghe, che cambiano fra le versioni, e i
 * backend di terze parti le implementano con fedeltà variabile. In più, dalla
 * 3.3.11 le classi sono state spostate in un namespace diverso.
 *
 * Chiamarli e sperare è il modo migliore per scrivere un'estensione che
 * funziona su una board e si rompe sulle altre. Quindi: si prova il backend
 * configurato, e qualunque cosa vada storta — servizio assente, metodo con
 * un'altra firma, eccezione — si ricade su una ricerca nei titoli, che è più
 * grezza ma non dipende da nulla e funziona sempre.
 *
 * ── Perché solo titoli e mai contenuti ────────────────────────────────────
 *
 * Al modello arrivano titolo, sezione, data e link. Mai il testo dei post.
 *
 * Con i titoli il bot dice "c'è una discussione che parla di questo, guardala".
 * Con i contenuti riassumerebbe risposte vecchie con la sicurezza di sempre, e
 * su un forum tecnico una soluzione di tre anni fa ripresentata come attuale fa
 * più danni del silenzio. Il valore sta nel mandare la persona alla discussione
 * originale, dove ci sono anche le correzioni arrivate dopo.
 */
class topic_search
{
	/** Ricerca eseguita dal backend configurato sulla board */
	public const STRATEGY_BACKEND = 'backend';

	/** Ripiego: ricerca nei soli titoli, senza dipendenze */
	public const STRATEGY_TITLES = 'titles';

	/** Nessun risultato utile */
	public const STRATEGY_NONE = 'none';

	/** Tetto assoluto sui risultati, qualunque cosa imposti l'amministratore */
	public const MAX_RESULTS = 10;

	/** Parole più corte di così non vengono cercate */
	public const MIN_WORD_CHARS = 4;

	/** Quante parole chiave usare al massimo */
	public const MAX_KEYWORDS = 6;

	/**
	 * Parole troppo comuni per essere informative.
	 *
	 * Non è un elenco esaustivo né vuole esserlo: serve a evitare che una
	 * domanda come «come faccio a installare Ubuntu» cerchi «come» e «faccio»,
	 * riportando mezzo forum.
	 */
	protected const STOPWORDS = [
		// italiano
		'come', 'cosa', 'quando', 'dove', 'perche', 'perché', 'quale', 'quali',
		'faccio', 'fare', 'posso', 'potete', 'qualcuno', 'grazie', 'ciao',
		'salve', 'aiuto', 'problema', 'problemi', 'sono', 'essere', 'avere',
		'questo', 'questa', 'quello', 'quella', 'sopra', 'sotto', 'anche',
		'ancora', 'sempre', 'molto', 'poco', 'forum', 'sezione', 'messaggio',
		// inglese
		'what', 'when', 'where', 'which', 'about', 'there', 'their', 'would',
		'could', 'should', 'please', 'thanks', 'hello', 'help', 'problem',
		'have', 'been', 'with', 'this', 'that', 'from', 'they', 'some',
		'anyone', 'someone', 'thread', 'topic', 'post',
	];

	/** @var config */
	protected $config;

	/** @var driver_interface */
	protected $db;

	/** @var language */
	protected $language;

	/** @var ContainerInterface */
	protected $container;

	/** @var string Strategia usata nell'ultima ricerca, per la diagnostica */
	protected $last_strategy = self::STRATEGY_NONE;

	/** @var string[] Parole chiave dell'ultima ricerca, per la diagnostica */
	protected $last_keywords = [];

	/** @var string Motivo dell'eventuale ripiego, per la diagnostica */
	protected $last_note = '';

	public function __construct(config $config, driver_interface $db, language $language, ContainerInterface $container)
	{
		$this->config = $config;
		$this->db = $db;
		$this->language = $language;
		$this->container = $container;

		$this->language->add_lang('aireply_log', 'salvocortesiano/aireply');
	}

	/**
	 * Blocco da accodare al prompt, oppure stringa vuota.
	 *
	 * @param string $question        testo del messaggio a cui si risponde
	 * @param int    $poster_id       chi ha scritto: determina cosa è visibile
	 * @param int    $exclude_topic_id discussione corrente, da non riproporre
	 */
	public function build(string $question, int $poster_id, int $exclude_topic_id = 0): string
	{
		$quanti = (int) ($this->config['aireply_search_results'] ?? 0);

		if ($quanti <= 0)
		{
			return '';
		}

		$risultati = $this->find($question, $poster_id, $exclude_topic_id, min($quanti, self::MAX_RESULTS));

		if (empty($risultati))
		{
			return '';
		}

		$righe = [$this->language->lang('AIREPLY_SEARCH_HEADER')];

		foreach ($risultati as $r)
		{
			$righe[] = '  · ' . $r['title']
				. ' [' . $r['forum'] . ', ' . $r['date'] . ']'
				. "\n    " . $r['url'];
		}

		$righe[] = $this->language->lang('AIREPLY_SEARCH_RULE');

		return implode("\n", $righe);
	}

	/**
	 * Esegue la ricerca e restituisce i risultati normalizzati.
	 *
	 * @return array[] title, forum, date, url, topic_id
	 */
	public function find(string $question, int $poster_id, int $exclude_topic_id, int $limit): array
	{
		$this->last_strategy = self::STRATEGY_NONE;
		$this->last_note = '';

		$parole = $this->extract_keywords($question);
		$this->last_keywords = $parole;

		if (empty($parole))
		{
			$this->last_note = $this->language->lang('AIREPLY_SEARCH_NO_KEYWORDS');
			return [];
		}

		$vietati = $this->forbidden_forums($poster_id);

		$ids = $this->search_with_backend($parole, $vietati, $limit);

		if ($ids === null)
		{
			$ids = $this->search_titles($parole, $vietati, $limit);
			$this->last_strategy = self::STRATEGY_TITLES;
		}
		else
		{
			$this->last_strategy = self::STRATEGY_BACKEND;
		}

		$ids = array_values(array_filter($ids, static function ($id) use ($exclude_topic_id) {
			return (int) $id !== $exclude_topic_id;
		}));

		if (empty($ids))
		{
			return [];
		}

		return $this->describe_topics(array_slice($ids, 0, $limit));
	}

	/**
	 * Interroga il backend di ricerca configurato sulla board.
	 *
	 * @return int[]|null id delle discussioni, oppure null se il backend non è
	 *                    utilizzabile — nel qual caso il chiamante ripiega
	 */
	protected function search_with_backend(array $parole, array $vietati, int $limit): ?array
	{
		$tipo = (string) ($this->config['search_type'] ?? '');

		if ($tipo === '')
		{
			$this->last_note = $this->language->lang('AIREPLY_SEARCH_NO_BACKEND');
			return null;
		}

		try
		{
			if (!$this->container->has($tipo))
			{
				$this->last_note = $this->language->lang('AIREPLY_SEARCH_BACKEND_MISSING', $tipo);
				return null;
			}

			$backend = $this->container->get($tipo);

			if (!method_exists($backend, 'keyword_search') || !method_exists($backend, 'split_keywords'))
			{
				$this->last_note = $this->language->lang('AIREPLY_SEARCH_BACKEND_INCOMPATIBLE', $tipo);
				return null;
			}

			$termini = implode(' ', $parole);

			if ($backend->split_keywords($termini, 'all') === false)
			{
				$this->last_note = $this->language->lang('AIREPLY_SEARCH_KEYWORDS_REJECTED');
				return null;
			}

			$id_ary = [];
			$start = 0;

			/*
			 * Si cerca fra i TITOLI delle discussioni, non fra i testi.
			 *
			 * È la ricerca meno costosa per il backend e restituisce già
			 * l'identificativo della discussione, che è l'unica cosa che ci
			 * serve: del contenuto non facciamo nulla di proposito.
			 */
			$backend->keyword_search(
				'all',                  // tipo di ricerca
				'titleonly',            // solo titoli
				'all',                  // tutti i termini
				['t.topic_last_post_time DESC'],
				't',                    // ordinamento per discussione
				'd',
				0,                      // nessun limite di data
				$vietati,
				ITEM_APPROVED,
				0,                      // nessuna discussione specifica
				[],
				'',
				$id_ary,
				$start,
				$limit
			);

			return array_map('intval', (array) $id_ary);
		}
		catch (\Throwable $e)
		{
			/*
			 * Qualunque cosa sia andata storta, si ripiega.
			 *
			 * Le firme di keyword_search cambiano fra le versioni di phpBB e i
			 * backend di terze parti le implementano con fedeltà variabile: un
			 * ArgumentCountError qui è un'eventualità prevista, non un
			 * imprevisto, e non deve impedire al bot di rispondere.
			 */
			$this->last_note = $this->language->lang('AIREPLY_SEARCH_BACKEND_FAILED', $e->getMessage());
			return null;
		}
	}

	/**
	 * Ripiego: cerca le parole nei titoli delle discussioni.
	 *
	 * Non dipende da alcun backend. Per trovare discussioni collegate i titoli
	 * sono un segnale meno debole di quanto sembri: chi apre una discussione il
	 * titolo lo scrive pensando a chi cercherà.
	 *
	 * @return int[]
	 */
	protected function search_titles(array $parole, array $vietati, int $limit): array
	{
		$condizioni = [];

		foreach ($parole as $parola)
		{
			$condizioni[] = 'LOWER(t.topic_title) ' . $this->db->sql_like_expression(
				$this->db->get_any_char() . utf8_strtolower($parola) . $this->db->get_any_char()
			);
		}

		if (empty($condizioni))
		{
			return [];
		}

		$sql = 'SELECT t.topic_id
			FROM ' . TOPICS_TABLE . ' t
			WHERE t.topic_visibility = ' . ITEM_APPROVED . '
				AND t.topic_status <> ' . ITEM_MOVED . '
				AND (' . implode(' OR ', $condizioni) . ')';

		if (!empty($vietati))
		{
			$sql .= ' AND ' . $this->db->sql_in_set('t.forum_id', array_map('intval', $vietati), true);
		}

		$sql .= ' ORDER BY t.topic_last_post_time DESC';

		$result = $this->db->sql_query_limit($sql, $limit);

		$ids = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['topic_id'];
		}

		$this->db->sql_freeresult($result);

		return $ids;
	}

	/**
	 * Titolo, sezione, data e collegamento delle discussioni trovate.
	 */
	protected function describe_topics(array $topic_ids): array
	{
		if (empty($topic_ids))
		{
			return [];
		}

		$sql = 'SELECT t.topic_id, t.topic_title, t.topic_last_post_time, f.forum_name
			FROM ' . TOPICS_TABLE . ' t
			LEFT JOIN ' . FORUMS_TABLE . ' f ON f.forum_id = t.forum_id
			WHERE ' . $this->db->sql_in_set('t.topic_id', $topic_ids) . '
			ORDER BY t.topic_last_post_time DESC';

		$result = $this->db->sql_query($sql);

		$out = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$out[] = [
				'topic_id' => (int) $row['topic_id'],
				'title'    => censor_text((string) $row['topic_title']),
				'forum'    => (string) ($row['forum_name'] ?? ''),
				// Anno-mese, non testuale: la data serve al modello per capire
				// se la discussione è vecchia, e non deve dipendere dalla lingua
				// caricata nel worker.
				'date'     => date('Y-m', (int) $row['topic_last_post_time']),
				'url'      => generate_board_url() . '/viewtopic.php?t=' . (int) $row['topic_id'],
			];
		}

		$this->db->sql_freeresult($result);

		return $out;
	}

	/**
	 * Sezioni che l'autore della domanda NON può leggere.
	 *
	 * È la stessa regola del contesto della board: si usa l'ACL di chi ha
	 * scritto, mai quella del bot. Con quella del bot, chiunque potrebbe
	 * ottenere per interposta persona l'elenco delle discussioni in aree
	 * riservate — una fuga di informazioni per gentilezza.
	 *
	 * @return int[]
	 */
	protected function forbidden_forums(int $poster_id): array
	{
		$sql = 'SELECT * FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int) $poster_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$auth = new \phpbb\auth\auth();

		if ($row)
		{
			$auth->acl($row);
		}

		$consentiti = $auth->acl_getf('f_read', true);

		$vietati = [];

		$sql = 'SELECT forum_id FROM ' . FORUMS_TABLE;
		$result = $this->db->sql_query($sql);

		while ($f = $this->db->sql_fetchrow($result))
		{
			$forum_id = (int) $f['forum_id'];

			if (!isset($consentiti[$forum_id]))
			{
				$vietati[] = $forum_id;
			}
		}

		$this->db->sql_freeresult($result);

		return $vietati;
	}

	/**
	 * Estrae le parole da cercare dalla domanda.
	 *
	 * Una domanda in linguaggio naturale non è una query: «Ciao @Gemini, come
	 * installo ubuntu su un pc windows?» deve diventare «installo ubuntu
	 * windows». Vanno tolti le menzioni, la punteggiatura, le parole troppo
	 * corte e quelle troppo comuni per essere informative.
	 *
	 * @return string[]
	 */
	public function extract_keywords(string $question): array
	{
		$testo = $question;

		// Menzioni e BBCode residuo: non sono contenuto della domanda.
		$testo = preg_replace('/\[[^\]]*\]/', ' ', $testo) ?? $testo;
		$testo = preg_replace('/(?:^|\s)@\S+/u', ' ', $testo) ?? $testo;
		$testo = preg_replace('/https?:\/\/\S+/i', ' ', $testo) ?? $testo;

		$testo = utf8_strtolower($testo);

		$pezzi = preg_split('/[^\p{L}\p{N}]+/u', $testo, -1, PREG_SPLIT_NO_EMPTY) ?: [];

		// La board ha una lunghezza minima configurata: cercare parole più
		// corte restituisce zero risultati senza spiegazioni.
		$minimo = max(self::MIN_WORD_CHARS, (int) ($this->config['fulltext_native_min_chars'] ?? 0));

		$parole = [];

		foreach ($pezzi as $parola)
		{
			if (utf8_strlen($parola) < $minimo)
			{
				continue;
			}

			if (in_array($parola, self::STOPWORDS, true))
			{
				continue;
			}

			if (isset($parole[$parola]))
			{
				continue;
			}

			$parole[$parola] = true;

			if (count($parole) >= self::MAX_KEYWORDS)
			{
				break;
			}
		}

		return array_keys($parole);
	}

	/** Strategia usata nell'ultima ricerca. */
	public function get_last_strategy(): string
	{
		return $this->last_strategy;
	}

	/** Parole chiave dell'ultima ricerca. */
	public function get_last_keywords(): array
	{
		return $this->last_keywords;
	}

	/** Motivo dell'eventuale ripiego. */
	public function get_last_note(): string
	{
		return $this->last_note;
	}
}
