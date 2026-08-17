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

/**
 * Descrive la board al modello, così che possa rispondere a domande su di essa.
 *
 * ── Il problema che risolve ───────────────────────────────────────────────
 *
 * Senza questo, il modello riceve soltanto il prompt di sistema e gli ultimi
 * post del topic: non sa come si chiama la board, quali sezioni esistono, né in
 * quale si trova. Alla domanda «questo forum cosa tratta?» oppure «dove posso
 * fare quattro chiacchiere?» non risponde "non lo so" — inventa, con la
 * sicurezza di sempre. Un bot che indica una sezione inesistente fa più danni
 * di uno che tace.
 *
 * ── Il vincolo che non è negoziabile ──────────────────────────────────────
 *
 * Le sezioni elencate sono quelle che può leggere CHI HA FATTO LA DOMANDA, non
 * quelle che vede il bot.
 *
 * È la differenza fra un aiuto e una fuga di informazioni: se si usasse l'ACL
 * del bot, chiunque potrebbe chiedergli l'elenco delle sezioni e scoprire
 * l'esistenza di aree riservate allo staff. Il bot rivelerebbe per gentilezza
 * ciò che i permessi nascondono.
 */
class board_context
{
	/**
	 * Tetto sulle sezioni elencate.
	 *
	 * Su una board con ottanta forum l'elenco completo costerebbe circa
	 * duemila token a ogni singola chiamata. Il tetto tiene la spesa
	 * prevedibile; oltre, si dice che l'elenco è parziale invece di troncarlo
	 * in silenzio e far credere al modello che sia completo.
	 */
	public const MAX_FORUMS = 60;

	/** Lunghezza massima della descrizione di una sezione */
	public const MAX_DESC_CHARS = 120;

	/** @var config */
	protected $config;

	/** @var driver_interface */
	protected $db;

	/** @var language */
	protected $language;

	/** @var array|null Albero dei forum, letto una volta per richiesta */
	protected $tree = null;

	public function __construct(config $config, driver_interface $db, language $language)
	{
		$this->config = $config;
		$this->db = $db;
		$this->language = $language;

		$this->language->add_lang('aireply_log', 'salvocortesiano/aireply');
	}

	/**
	 * Blocco da accodare al prompt di sistema.
	 *
	 * @param int $poster_id       chi ha scritto il messaggio: determina cosa è visibile
	 * @param int $current_forum_id forum in cui si sta rispondendo
	 */
	public function build(int $poster_id, int $current_forum_id): string
	{
		$righe = [];

		$nome = trim((string) $this->config['sitename']);
		$descrizione = trim(strip_tags((string) $this->config['site_desc']));

		if ($nome !== '')
		{
			$righe[] = $this->language->lang('AIREPLY_CTX_BOARD') . ' ' . $nome
				. ($descrizione !== '' ? ' — ' . $descrizione : '');
		}

		$visibili = $this->readable_forums($poster_id);

		if (isset($visibili[$current_forum_id]))
		{
			$corrente = $visibili[$current_forum_id];

			$righe[] = $this->language->lang('AIREPLY_CTX_CURRENT') . ' ' . $corrente['name']
				. ($corrente['desc'] !== '' ? ' — ' . $corrente['desc'] : '');
		}

		$elenco = $this->format_tree($visibili);

		if ($elenco !== '')
		{
			$righe[] = $this->language->lang('AIREPLY_CTX_SECTIONS') . "\n" . $elenco;
		}

		if (empty($righe))
		{
			return '';
		}

		/*
		 * L'istruzione finale è la parte che rende utile tutto il resto.
		 *
		 * Fornire i dati senza dire al modello di attenersi ad essi non
		 * elimina le invenzioni: le rende solo più credibili, perché mescolate
		 * a informazioni vere.
		 */
		$righe[] = $this->language->lang('AIREPLY_CTX_RULE');

		return implode("\n", $righe);
	}

	/**
	 * Sezioni leggibili da un utente, in ordine di visualizzazione.
	 *
	 * @return array[] forum_id => name, desc, depth, postable
	 */
	protected function readable_forums(int $poster_id): array
	{
		$auth = $this->auth_for($poster_id);
		$consentiti = $auth->acl_getf('f_read', true);

		$risultato = [];
		$contati = 0;

		foreach ($this->load_tree() as $forum_id => $nodo)
		{
			if (!isset($consentiti[$forum_id]))
			{
				continue;
			}

			if ($contati >= self::MAX_FORUMS)
			{
				break;
			}

			$risultato[$forum_id] = $nodo;
			$contati++;
		}

		return $risultato;
	}

	/**
	 * Albero dei forum, letto una volta sola per richiesta.
	 */
	protected function load_tree(): array
	{
		if ($this->tree !== null)
		{
			return $this->tree;
		}

		$sql = 'SELECT forum_id, forum_name, forum_desc, forum_type, parent_id
			FROM ' . FORUMS_TABLE . '
			ORDER BY left_id ASC';

		$result = $this->db->sql_query($sql);

		$profondita = [];
		$this->tree = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$forum_id = (int) $row['forum_id'];
			$parent_id = (int) $row['parent_id'];

			$profondita[$forum_id] = ($parent_id && isset($profondita[$parent_id]))
				? $profondita[$parent_id] + 1
				: 0;

			$this->tree[$forum_id] = [
				'name'     => (string) $row['forum_name'],
				'desc'     => $this->clean_desc((string) $row['forum_desc']),
				'depth'    => $profondita[$forum_id],
				'postable' => ((int) $row['forum_type'] === FORUM_POST),
			];
		}

		$this->db->sql_freeresult($result);

		return $this->tree;
	}

	/**
	 * Rende l'albero in una forma leggibile e compatta.
	 */
	protected function format_tree(array $forums): string
	{
		$righe = [];

		foreach ($forums as $nodo)
		{
			$rientro = str_repeat('  ', min(3, $nodo['depth']));

			// Il puntino distingue le sezioni in cui si può scrivere dalle
			// categorie: dire a qualcuno di postare in una categoria è un
			// consiglio che non può seguire.
			$marcatore = $nodo['postable'] ? '· ' : '';

			$righe[] = $rientro . $marcatore . $nodo['name']
				. ($nodo['desc'] !== '' ? ' — ' . $nodo['desc'] : '');
		}

		return implode("\n", $righe);
	}

	/**
	 * La descrizione di un forum contiene BBCode e HTML: al modello serve testo.
	 */
	protected function clean_desc(string $desc): string
	{
		$desc = strip_tags($desc);
		$desc = preg_replace('#\[/?[a-z0-9\*]+(?:[=:][^\]]*)?\]#i', '', $desc) ?? $desc;
		$desc = html_entity_decode($desc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$desc = trim(preg_replace('/\s+/u', ' ', $desc) ?? $desc);

		if (mb_strlen($desc) > self::MAX_DESC_CHARS)
		{
			$desc = mb_substr($desc, 0, self::MAX_DESC_CHARS) . '…';
		}

		return $desc;
	}

	/**
	 * ACL dell'autore del messaggio, su un'istanza separata.
	 *
	 * Non si tocca l'auth globale: questo servizio gira dentro il worker, dove
	 * alterare i permessi correnti avrebbe conseguenze sulla pubblicazione che
	 * avviene subito dopo.
	 */
	protected function auth_for(int $user_id): \phpbb\auth\auth
	{
		$sql = 'SELECT * FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int) $user_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$auth = new \phpbb\auth\auth();

		if ($row)
		{
			$auth->acl($row);
		}

		return $auth;
	}
}
