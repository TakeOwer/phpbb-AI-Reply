<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\bot;

/**
 * Configurazione di un bot, caricata da aireply_bots.
 *
 * Oggetto di sola lettura: chi lo riceve non deve poterlo modificare per
 * sbaglio a metà elaborazione.
 */
class bot
{
	/** Tetto di sicurezza sul numero di post di contesto, indipendente dall'ACP. */
	public const MAX_CONTEXT_POSTS = 200;

	/** Rispecchia la lingua del messaggio a cui risponde. */
	public const LANG_AUTO = 'auto';

	/** Usa sempre la lingua predefinita della board. */
	public const LANG_BOARD = 'board';

	/** Usa sempre la lingua indicata a mano dall'amministratore. */
	public const LANG_CUSTOM = 'custom';

	/** @var int */
	public $bot_id = 0;

	/** @var int Utente phpBB che "è" il bot */
	public $user_id = 0;

	/** @var string Nome utente phpBB, caricato con una join */
	public $username = '';

	/** @var string Etichetta interna per l'ACP */
	public $bot_name = '';

	/** @var string */
	public $provider = '';

	/** @var string */
	public $model = '';

	/** @var string Valore grezzo: può essere "env:NOME", "const:NOME" o la chiave */
	public $api_key = '';

	/** @var string */
	public $base_url = '';

	/** @var string */
	public $system_prompt = '';

	/** @var float */
	public $temperature = 1.0;

	/** @var int */
	public $max_output_tokens = 800;

	/** @var int */
	public $thinking_budget = -1;

	/** @var int */
	public $context_posts = 6;

	/** @var int */
	public $context_max_chars = 12000;

	/** @var int */
	public $max_post_chars = 4000;

	/** @var string */
	public $reply_template = '';

	/** @var string auto | board | custom */
	public $reply_language = self::LANG_AUTO;

	/** @var string Nome della lingua, usato solo con LANG_CUSTOM */
	public $reply_language_custom = '';

	/** @var bool Menziona l'autore del messaggio a cui risponde */
	public $mention_poster = false;

	/** @var bool Riceve la descrizione della board nel prompt di sistema */
	public $board_context = true;

	/**
	 * @var string Scelte del costruttore di personalità, es. "welcome|friendly,concise"
	 *
	 * Serve solo all'interfaccia: il prompt inviato al modello è system_prompt.
	 */
	public $persona_recipe = '';

	/** @var int */
	public $request_timeout = 90;

	/** @var bool */
	public $enabled = false;

	public static function from_row(array $row): self
	{
		$bot = new self();

		$bot->bot_id            = (int) $row['bot_id'];
		$bot->user_id           = (int) $row['user_id'];
		$bot->username          = (string) ($row['username'] ?? '');
		$bot->bot_name          = (string) $row['bot_name'];
		$bot->provider          = (string) $row['provider'];
		$bot->model             = (string) $row['model'];
		$bot->api_key           = (string) $row['api_key'];
		$bot->base_url          = (string) $row['base_url'];
		$bot->system_prompt     = (string) $row['system_prompt'];
		$bot->temperature       = (float) $row['temperature'];
		$bot->max_output_tokens = (int) $row['max_output_tokens'];
		$bot->thinking_budget   = (int) $row['thinking_budget'];
		$bot->context_max_chars = (int) $row['context_max_chars'];
		$bot->max_post_chars    = (int) $row['max_post_chars'];
		$bot->reply_template    = (string) $row['reply_template'];
		$bot->reply_language    = (string) ($row['reply_language'] ?? self::LANG_AUTO);
		$bot->reply_language_custom = (string) ($row['reply_language_custom'] ?? '');
		$bot->mention_poster    = (bool) ($row['mention_poster'] ?? 0);
		$bot->board_context     = (bool) ($row['board_context'] ?? 1);
		$bot->persona_recipe    = (string) ($row['persona_recipe'] ?? '');
		$bot->request_timeout   = (int) $row['request_timeout'];
		$bot->enabled           = (bool) $row['enabled'];

		// Tetto di sicurezza: un valore assurdo in database non deve poter
		// generare una richiesta da mezzo milione di token.
		$bot->context_posts = max(0, min(self::MAX_CONTEXT_POSTS, (int) $row['context_posts']));

		return $bot;
	}

	/**
	 * Il bot ha memoria della conversazione?
	 */
	public function has_memory(): bool
	{
		return $this->context_posts > 0;
	}

	/**
	 * Politica di lingua effettiva.
	 *
	 * Se è impostato 'custom' ma il campo del nome è vuoto, si ricade su
	 * 'auto': meglio il comportamento predefinito che un'istruzione monca.
	 */
	public function get_reply_language(): string
	{
		if ($this->reply_language === self::LANG_CUSTOM && trim($this->reply_language_custom) === '')
		{
			return self::LANG_AUTO;
		}

		return in_array($this->reply_language, [self::LANG_AUTO, self::LANG_BOARD, self::LANG_CUSTOM], true)
			? $this->reply_language
			: self::LANG_AUTO;
	}

	/**
	 * Template effettivo. Se l'admin non ne ha impostato uno, la risposta è il
	 * testo nudo più la dichiarazione di generazione automatica.
	 */
	public function get_template(): string
	{
		$template = trim($this->reply_template);

		return ($template !== '') ? $template : "{response}\n\n{disclosure}";
	}
}
