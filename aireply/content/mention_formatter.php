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
use phpbb\extension\manager as extension_manager;
use phpbb\textformatter\parser_interface;
use phpbb\user;

/**
 * Produce il markup per menzionare l'autore di un messaggio.
 *
 * ── Perché il formato non è cablato ───────────────────────────────────────
 *
 * L'estensione "Simple mentions" di paul999 usa due tag diversi a seconda di
 * come è arrivata sulla board:
 *
 *   [smention u=42]Nome[/smention]   registrato a runtime dalla versione 2.0
 *   [mention]Nome[/mention]          registrato in tabella dalla 1.x
 *
 * La migration della 2.0 dichiara esplicitamente di non rimuovere il vecchio
 * BBCode "to keep it working", quindi una board aggiornata ha entrambi, una
 * installazione nuova solo il primo, una ferma alla 1.x solo il secondo.
 *
 * Cablare un tag significherebbe funzionare su una configurazione su tre. Qui
 * si chiede invece al parser cosa accetta davvero, e si memorizza la risposta.
 *
 * ── Il vincolo della pagina di scrittura ──────────────────────────────────
 *
 * Simple mentions disattiva i propri BBCode quando la pagina corrente non è
 * posting.php:
 *
 *     if ($this->user->page['page_name'] != 'posting.' . $this->php_ext)
 *         $disable = true;
 *
 * È una scelta sensata dal suo punto di vista — evita che le menzioni vengano
 * interpretate nelle firme o nei messaggi privati — ma esclude qualunque
 * pubblicazione automatica: il nostro worker gira da cron.php o da phpbbcli.php.
 *
 * Senza aggirare quel controllo, il markup finirebbe nel post come testo
 * letterale, con il BBCode in chiaro e nessuna notifica.
 *
 * La soluzione è `with_posting_page()`: sostituisce temporaneamente il nome
 * della pagina per la sola durata dell'operazione e lo ripristina in ogni caso,
 * eccezioni comprese. È accoppiamento a un dettaglio interno di un'altra
 * estensione, e va detto: se paul999 cambia quel controllo, il rilevamento
 * ricadrà su "plain" e le menzioni diventeranno testo semplice. Nessuna
 * rottura, solo una funzione in meno.
 */
class mention_formatter
{
	/** Tag della versione 2.0, registrato a runtime */
	public const FORMAT_SMENTION = 'smention';

	/** Tag della versione 1.x, registrato nella tabella dei BBCode */
	public const FORMAT_MENTION = 'mention';

	/** Nessuna estensione di menzione utilizzabile: si scrive @Nome */
	public const FORMAT_PLAIN = 'plain';

	/** Estensione da cui provengono i BBCode di menzione */
	public const MENTION_EXTENSION = 'paul999/mention';

	/** @var config */
	protected $config;

	/** @var driver_interface */
	protected $db;

	/** @var extension_manager */
	protected $ext_manager;

	/** @var parser_interface */
	protected $parser;

	/** @var user */
	protected $user;

	/** @var string */
	protected $php_ext;

	public function __construct(
		config $config,
		driver_interface $db,
		extension_manager $ext_manager,
		parser_interface $parser,
		user $user,
		string $php_ext
	) {
		$this->config = $config;
		$this->db = $db;
		$this->ext_manager = $ext_manager;
		$this->parser = $parser;
		$this->user = $user;
		$this->php_ext = $php_ext;
	}

	/**
	 * L'estensione delle menzioni è installata e attiva?
	 */
	public function is_extension_enabled(): bool
	{
		try
		{
			return $this->ext_manager->is_enabled(self::MENTION_EXTENSION);
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	/**
	 * Formato in uso, dalla cache. Rileva al primo utilizzo.
	 *
	 * La cache si invalida da sola quando l'estensione delle menzioni viene
	 * attivata o disattivata. Senza questo controllo, chi installa Simple
	 * mentions *dopo* AI Reply si ritroverebbe il valore "plain" congelato:
	 * le menzioni continuerebbero a uscire come testo semplice e non ci sarebbe
	 * modo di capire perché, se non premendo un pulsante di cui nessuno sospetta
	 * l'esistenza.
	 */
	public function get_format(): string
	{
		$cached = (string) ($this->config['aireply_mention_format'] ?? '');
		$was_enabled = (bool) ($this->config['aireply_mention_ext_seen'] ?? false);
		$is_enabled = $this->is_extension_enabled();

		if ($cached === '' || $was_enabled !== $is_enabled)
		{
			return $this->detect();
		}

		return $cached;
	}

	/**
	 * Interroga il parser e memorizza il risultato.
	 *
	 * Non si controlla la versione dell'estensione né la tabella dei BBCode:
	 * si prova a interpretare un frammento e si guarda cosa esce. È l'unico
	 * metodo che resta valido anche su board configurate a mano.
	 */
	public function detect(): string
	{
		$format = self::FORMAT_PLAIN;

		if ($this->is_extension_enabled())
		{
			$probes = [
				self::FORMAT_SMENTION => '[smention u=1]probe[/smention]',
				self::FORMAT_MENTION  => '[mention]probe[/mention]',
			];

			foreach ($probes as $candidate => $probe)
			{
				if ($this->probe_parses($probe, $candidate))
				{
					$format = $candidate;
					break;
				}
			}
		}

		$this->config->set('aireply_mention_format', $format);

		// Si registra anche lo stato dell'estensione al momento del
		// rilevamento: è il confronto che permette l'invalidazione automatica.
		$this->config->set('aireply_mention_ext_seen', $this->is_extension_enabled() ? 1 : 0);

		return $format;
	}

	/**
	 * Il parser produce davvero un elemento per questo tag?
	 *
	 * Se il BBCode non è definito o è disattivato, s9e lascia il testo com'è e
	 * nell'XML non compare nessun elemento con quel nome.
	 */
	protected function probe_parses(string $probe, string $tag): bool
	{
		$xml = $this->with_posting_page(function () use ($probe) {
			try
			{
				return $this->parser->parse($probe);
			}
			catch (\Exception $e)
			{
				return '';
			}
		});

		return stripos((string) $xml, '<' . strtoupper($tag)) !== false;
	}

	/**
	 * Esegue il codice facendo credere di essere sulla pagina di scrittura.
	 *
	 * Vedi la nota in testa alla classe. Il ripristino avviene anche in caso di
	 * eccezione: lasciare la pagina falsata influenzerebbe tutto ciò che viene
	 * dopo nella stessa richiesta.
	 *
	 * @return mixed il valore restituito dalla funzione
	 */
	public function with_posting_page(callable $fn)
	{
		$page = $this->user->page ?? [];
		$original = $page['page_name'] ?? null;

		$page['page_name'] = 'posting.' . $this->php_ext;
		$this->user->page = $page;

		try
		{
			return $fn();
		}
		finally
		{
			$page = $this->user->page;

			if ($original === null)
			{
				unset($page['page_name']);
			}
			else
			{
				$page['page_name'] = $original;
			}

			$this->user->page = $page;
		}
	}

	/**
	 * Markup per menzionare un utente, nel formato disponibile su questa board.
	 */
	public function build(int $user_id, string $username): string
	{
		switch ($this->get_format())
		{
			case self::FORMAT_SMENTION:
				return '[smention u=' . (int) $user_id . ']' . $username . '[/smention]';

			case self::FORMAT_MENTION:
				return '[mention]' . $username . '[/mention]';
		}

		// Senza estensione la menzione resta leggibile, semplicemente non
		// produce né notifica né collegamento al profilo.
		return '@' . $username;
	}

	/**
	 * Sostituisce la prima occorrenza del nome con la menzione.
	 *
	 * Si agisce sul testo già generato invece di chiedere al modello di
	 * produrre il markup: il modello non conosce lo user_id e non ha modo di
	 * saperlo. I prompt predefiniti gli chiedono già di usare il nome della
	 * persona, quindi nella pratica il nome c'è quasi sempre.
	 *
	 * Se il nome non compare, non si aggiunge nulla: anteporre "@Nome," a forza
	 * darebbe a ogni risposta lo stesso incipit meccanico.
	 */
	public function linkify(string $bbcode_text, int $user_id, string $username): string
	{
		$username = trim($username);

		if ($username === '' || $user_id <= 0)
		{
			return $bbcode_text;
		}

		// I blocchi di codice si mettono al riparo: un nome utente che compare
		// dentro un esempio di codice non va trasformato in menzione.
		$vault = [];
		$text = preg_replace_callback('#\[code\].*?\[/code\]#is', static function ($m) use (&$vault) {
			$key = "\x01AIRM" . count($vault) . "\x02";
			$vault[$key] = $m[0];
			return $key;
		}, $bbcode_text) ?? $bbcode_text;

		$quoted = preg_quote($username, '#');

		/*
		 * Il confine a sinistra esclude "=" e "[", così un nome che compare
		 * dentro un attributo — [url=https://x/Founder] — non viene toccato.
		 * A destra si escludono i caratteri di parola, perché "Founder" non
		 * deve corrispondere dentro "Founders".
		 */
		$pattern = '#(^|[^\w=\[/@])' . $quoted . '(?![\w])#u';

		$replacement = $this->build($user_id, $username);

		$done = false;

		$text = preg_replace_callback($pattern, static function ($m) use ($replacement, &$done) {
			if ($done)
			{
				return $m[0];
			}

			$done = true;

			return $m[1] . $replacement;
		}, $text, 1) ?? $text;

		if (!empty($vault))
		{
			$text = str_replace(array_keys($vault), array_values($vault), $text);
		}

		return $text;
	}

	/**
	 * L'utente ha il permesso di menzionare?
	 *
	 * Simple mentions richiede `u_can_mention` per estrarre le menzioni e
	 * inviare le notifiche. Al momento della pubblicazione l'ACL in vigore è
	 * quella del bot, quindi è il bot a doverlo avere — non l'amministratore.
	 *
	 * Si usa un'istanza di auth separata invece di toccare quella globale:
	 * questo metodo viene chiamato anche dall'ACP, dove alterare l'ACL corrente
	 * avrebbe conseguenze ben oltre il controllo che ci interessa.
	 */
	public function user_can_mention(int $user_id): bool
	{
		if ($user_id <= 0)
		{
			return false;
		}

		$sql = 'SELECT * FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int) $user_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return false;
		}

		$auth = new \phpbb\auth\auth();
		$auth->acl($row);

		return (bool) $auth->acl_get('u_can_mention');
	}
}
