<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\controller;

use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\language\language;
use phpbb\log\log;
use phpbb\request\request;
use phpbb\template\template;
use phpbb\user;
use salvocortesiano\aireply\bot\bot;
use salvocortesiano\aireply\bot\permission_manager;
use salvocortesiano\aireply\bot\user_creator;
use salvocortesiano\aireply\content\board_context;
use salvocortesiano\aireply\content\mention_formatter;
use salvocortesiano\aireply\provider\ai_result;
use salvocortesiano\aireply\provider\key_manager;
use salvocortesiano\aireply\provider\manager as provider_manager;
use salvocortesiano\aireply\provider\provider_exception;
use salvocortesiano\aireply\usage\usage_tracker;

/**
 * Le quattro schede dell'ACP.
 *
 *   bots      creazione e modifica dei bot, con modelli e test di connessione
 *   forums    quale bot risponde in quale forum e con quali innesco
 *   jobs      registro delle attività con diagnostica
 *   settings  interruttore generale, coda, conservazione, budget
 */
class acp_controller
{
	/** @var config */
	protected $config;

	/** @var driver_interface */
	protected $db;

	/** @var language */
	protected $language;

	/** @var log */
	protected $log;

	/** @var request */
	protected $request;

	/** @var template */
	protected $template;

	/** @var user */
	protected $user;

	/** @var provider_manager */
	protected $providers;

	/** @var key_manager */
	protected $keys;

	/** @var usage_tracker */
	protected $usage;

	/** @var mention_formatter */
	protected $mentions;

	/** @var board_context */
	protected $board;

	/** @var user_creator */
	protected $creator;

	/** @var permission_manager */
	protected $permissions;

	/** @var string */
	protected $bots_table;

	/** @var string */
	protected $forums_table;

	/** @var string */
	protected $jobs_table;

	/** @var string */
	protected $u_action = '';

	public function __construct(
		config $config,
		driver_interface $db,
		language $language,
		log $log,
		request $request,
		template $template,
		user $user,
		provider_manager $providers,
		key_manager $keys,
		usage_tracker $usage,
		mention_formatter $mentions,
		board_context $board,
		user_creator $creator,
		permission_manager $permissions,
		string $bots_table,
		string $forums_table,
		string $jobs_table
	) {
		$this->config = $config;
		$this->db = $db;
		$this->language = $language;
		$this->log = $log;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->providers = $providers;
		$this->keys = $keys;
		$this->usage = $usage;
		$this->mentions = $mentions;
		$this->board = $board;
		$this->creator = $creator;
		$this->permissions = $permissions;
		$this->bots_table = $bots_table;
		$this->forums_table = $forums_table;
		$this->jobs_table = $jobs_table;
	}

	public function set_action(string $u_action): void
	{
		$this->u_action = $u_action;
	}

	// ==================================================================
	// Scheda: Bot
	// ==================================================================

	public function mode_bots(): void
	{
		$action = $this->request->variable('action', '');
		$bot_id = $this->request->variable('bot_id', 0);

		switch ($action)
		{
			case 'add':
			case 'edit':
				$this->render_bot_form($action === 'edit' ? $bot_id : 0);
				return;

			case 'save':
				$this->save_bot();
				return;

			case 'delete':
				$this->delete_bot($bot_id);
				return;

			case 'toggle':
				$this->toggle_bot($bot_id);
				return;

			case 'detect_mentions':
				$this->redetect_mentions();
				return;

			case 'newuser':
				$this->render_user_form();
				return;

			case 'createuser':
				$this->create_bot_user();
				return;

			case 'fixperms':
				$this->fix_permissions($bot_id);
				return;
		}

		$this->render_bot_list();
	}

	protected function render_bot_list(): void
	{
		$sql_array = [
			'SELECT'    => 'b.*, u.username, u.user_colour',
			'FROM'      => [$this->bots_table => 'b'],
			'LEFT_JOIN' => [[
				'FROM' => [USERS_TABLE => 'u'],
				'ON'   => 'u.user_id = b.user_id',
			]],
			'ORDER_BY'  => 'b.bot_name ASC',
		];

		$result = $this->db->sql_query($this->db->sql_build_query('SELECT', $sql_array));

		$count = 0;

		while ($row = $this->db->sql_fetchrow($result))
		{
			$count++;

			// Quanti forum usano questo bot: se zero, il bot non risponderà
			// mai a nulla, ed è bene che si veda a colpo d'occhio.
			$sql = 'SELECT COUNT(forum_id) AS total FROM ' . $this->forums_table . '
				WHERE bot_id = ' . (int) $row['bot_id'] . ' AND enabled = 1';
			$sub = $this->db->sql_query($sql);
			$forums = (int) $this->db->sql_fetchfield('total');
			$this->db->sql_freeresult($sub);

			/*
			 * Stato dei permessi, calcolato riga per riga.
			 *
			 * Costa qualche query in più su una pagina che si apre di rado, ed
			 * evita il problema peggiore che questa estensione può avere: un bot
			 * configurato bene che non pubblica nulla e non dice perché, perché
			 * gli manca f_reply.
			 */
			$forum_ids = $this->permissions->get_bot_forums((int) $row['bot_id'], $this->forums_table);
			$mention_needed = !empty($row['mention_poster']) && $this->mentions->is_extension_enabled();

			$audit = ($row['user_id'] > 0)
				? $this->permissions->audit((int) $row['user_id'], $forum_ids, $mention_needed)
				: ['mention' => true, 'forums' => [], 'mention_needed' => false];

			$perms_ok = $audit['mention'] && empty($audit['forums']);

			$this->template->assign_block_vars('bots', [
				'PERMS_OK'        => $perms_ok,
				'PERMS_NO_MENTION' => !$audit['mention'],
				'PERMS_FORUMS'    => count($audit['forums']),
				'U_FIXPERMS'      => $this->u_action . '&amp;action=fixperms&amp;bot_id=' . (int) $row['bot_id']
					. '&amp;hash=' . generate_link_hash('aireply_acp'),

				'BOT_ID'      => (int) $row['bot_id'],
				'NAME'        => $row['bot_name'] !== '' ? $row['bot_name'] : $row['username'],

				/*
				 * Le prime righe del prompt di sistema.
				 *
				 * Nessuna colonna nuova e nessuna descrizione da inventare: si
				 * mostra ciò che l'amministratore ha scritto. Con due bot
				 * assegnati allo stesso forum, questa è l'unica cosa che dice a
				 * colpo d'occhio quale accoglie e quale fa l'esperto.
				 */
				'PERSONA'     => $this->summarise_prompt((string) $row['system_prompt']),
				'USERNAME'    => $row['username'] ?? '',
				'USER_MISSING' => empty($row['username']),
				'PROVIDER'    => $this->provider_label((string) $row['provider']),
				'MODEL'       => $row['model'],
				'KEY_SOURCE'  => $this->keys->get_source((string) $row['api_key']),
				'KEY_OK'      => $this->keys->is_resolvable((string) $row['api_key']),
				'ENABLED'     => (bool) $row['enabled'],
				'FORUM_COUNT' => $forums,
				'U_EDIT'      => $this->u_action . '&amp;action=edit&amp;bot_id=' . (int) $row['bot_id'],
				'U_TOGGLE'    => $this->u_action . '&amp;action=toggle&amp;bot_id=' . (int) $row['bot_id'] . '&amp;hash=' . generate_link_hash('aireply_acp'),
				'U_DELETE'    => $this->u_action . '&amp;action=delete&amp;bot_id=' . (int) $row['bot_id'] . '&amp;hash=' . generate_link_hash('aireply_acp'),
			]);
		}

		$this->db->sql_freeresult($result);

		$today = $this->usage->summarise_today();
		$month = $this->usage->summarise_month();

		$this->template->assign_vars([
			'S_BOT_LIST'           => true,
			'AIREPLY_BOT_COUNT'    => $count,
			'AIREPLY_GLOBAL_ON'    => !empty($this->config['aireply_enabled']),
			'U_ADD_BOT'            => $this->u_action . '&amp;action=add',
			'U_NEW_USER'           => $this->u_action . '&amp;action=newuser',
			'AIREPLY_TODAY_JOBS'   => $today['jobs'],
			'AIREPLY_TODAY_TOKENS' => $today['prompt_tokens'] + $today['completion_tokens'],
			'AIREPLY_TODAY_COST'   => $this->usage->format_cost($today['cost']),
			'AIREPLY_MONTH_JOBS'   => $month['jobs'],
			'AIREPLY_MONTH_TOKENS' => $month['prompt_tokens'] + $month['completion_tokens'],
			'AIREPLY_MONTH_COST'   => $this->usage->format_cost($month['cost']),
			'AIREPLY_COST_KNOWN'   => $month['cost_known'],
			'AIREPLY_UNPRICED'     => implode(', ', $month['unpriced']),
			'AIREPLY_VERSION'      => (string) $this->config['aireply_version'],
		]);
	}

	protected function render_bot_form(int $bot_id): void
	{
		add_form_key('aireply_bot');

		$data = $this->default_bot_data();

		if ($bot_id > 0)
		{
			$sql_array = [
				'SELECT'    => 'b.*, u.username',
				'FROM'      => [$this->bots_table => 'b'],
				'LEFT_JOIN' => [[
					'FROM' => [USERS_TABLE => 'u'],
					'ON'   => 'u.user_id = b.user_id',
				]],
				'WHERE'     => 'b.bot_id = ' . (int) $bot_id,
			];

			$result = $this->db->sql_query($this->db->sql_build_query('SELECT', $sql_array));
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if (!$row)
			{
				trigger_error($this->language->lang('AIREPLY_BOT_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$data = array_merge($data, $row);
		}

		foreach ($this->providers->all() as $provider_id => $provider)
		{
			$models = [];

			foreach ($this->providers->get_cached_models($provider_id) as $m)
			{
				$models[] = [
					'ID'          => $m['model_id'],
					'LABEL'       => $m['display_name'] !== '' ? $m['display_name'] : $m['model_id'],
					'SELECTED'    => ($data['provider'] === $provider_id && $data['model'] === $m['model_id']),
					'IS_NEW'      => !empty($m['is_new']),
					'RECOMMENDED' => !empty($m['is_recommended']),
					'REASONING'   => !empty($m['is_reasoning']),
					'PRICE_SET'   => ((float) $m['price_in'] > 0 || (float) $m['price_out'] > 0),
				];
			}

			$this->template->assign_block_vars('providers', [
				'ID'            => $provider_id,
				'NAME'          => $this->language->lang($provider->get_label_key()),
				'SELECTED'      => ($data['provider'] === $provider_id),
				'DEFAULT_MODEL' => $provider->get_default_model(),
				'BASE_URL'      => $provider->get_default_base_url(),
				'PRICING_URL'   => $this->providers->get_profile()->get_pricing_url($provider_id),
				'MODEL_COUNT'   => count($models),
				'NEW_COUNT'     => count($this->providers->get_new_models($provider_id)),
				'LAST_CHECK'    => $this->providers->get_last_check($provider_id) > 0
					? $this->user->format_date($this->providers->get_last_check($provider_id)) : '',
				'IS_STALE'      => $this->providers->cache_is_stale($provider_id),
			]);

			foreach ($models as $m)
			{
				$this->template->assign_block_vars('providers.models', $m);
			}
		}

		$this->template->assign_vars([
			'S_BOT_FORM'      => true,
			'S_EDIT'          => $bot_id > 0,
			'AIREPLY_VERSION' => (string) $this->config['aireply_version'],
			'AIREPLY_HASH'    => generate_link_hash('aireply_acp'),

			'BOT_ID'          => $bot_id,
			'BOT_NAME'        => $data['bot_name'],
			'BOT_USERNAME'    => ($data['username'] ?? '') !== ''
				? $data['username']
				: $this->request->variable('prefill', '', true),
			'BOT_PROVIDER'    => $data['provider'],
			'BOT_MODEL'       => $data['model'],

			// Se il modello configurato non compare nella cache va mostrato nel
			// campo manuale, altrimenti sparirebbe alla prima riapertura del form.
			'BOT_MODEL_UNLISTED' => ($data['model'] !== '' && $data['provider'] !== ''
				&& $this->providers->get_cached_model((string) $data['provider'], (string) $data['model']) === null),
			'BOT_KEY_MASKED'  => $this->keys->mask((string) $data['api_key']),
			'BOT_KEY_SOURCE'  => $this->keys->get_source((string) $data['api_key']),
			'BOT_KEY_LENGTH'  => $this->keys->describe((string) $data['api_key'])['length'],
			'BOT_KEY_FORMAT'  => $this->language->lang(
				'AIREPLY_KEYFMT_' . strtoupper(str_replace('-', '_', $this->keys->describe((string) $data['api_key'])['format']))
			),
			'BOT_KEY_SUSPECT' => ($data['api_key'] !== '' && !$this->keys->looks_plausible((string) $data['provider'], (string) $data['api_key'])),
			'BOT_BASE_URL'    => $data['base_url'],
			'BOT_PROMPT'      => $data['system_prompt'],
			'BOT_TEMPERATURE' => (float) $data['temperature'],
			'BOT_MAX_OUTPUT'  => (int) $data['max_output_tokens'],
			'BOT_THINKING'    => (int) $data['thinking_budget'],
			'BOT_CTX_POSTS'   => (int) $data['context_posts'],
			'BOT_CTX_CHARS'   => (int) $data['context_max_chars'],
			'BOT_MAX_POST'    => (int) $data['max_post_chars'],
			'BOT_TEMPLATE'    => $data['reply_template'],
			'BOT_TIMEOUT'     => (int) $data['request_timeout'],
			'BOT_ENABLED'     => (bool) $data['enabled'],
			'BOT_LANG_MODE'   => $data['reply_language'],
			'BOT_LANG_CUSTOM' => $data['reply_language_custom'],
			'BOT_MENTION'     => (bool) $data['mention_poster'],
			'BOT_BOARD_CTX'   => (bool) ($data['board_context'] ?? 1),
			'BOT_RECIPE'      => (string) ($data['persona_recipe'] ?? ''),

			// Anteprima di ciò che il modello riceverà davvero: un blocco di
			// testo descritto a parole si valuta molto peggio di uno che si vede.
			'BOARD_CTX_PREVIEW' => $this->board->build((int) $this->user->data['user_id'], 0),

			// Stato del rilevamento: l'amministratore deve sapere cosa
			// succederà davvero, non solo che l'opzione esiste.
			'MENTION_FORMAT'  => $this->mentions->get_format(),
			'MENTION_EXT_ON'  => $this->mentions->is_extension_enabled(),
			'MENTION_STATUS'  => $this->describe_mention_status((int) $data['user_id']),
			'U_DETECT_MENTIONS' => $this->u_action . '&amp;action=detect_mentions&amp;bot_id=' . (int) $bot_id
				. '&amp;hash=' . generate_link_hash('aireply_acp'),

			'U_BACK'          => $this->u_action,
			'U_NEW_USER'      => $this->u_action . '&amp;action=newuser',
			'U_SAVE'          => $this->u_action . '&amp;action=save&amp;bot_id=' . (int) $bot_id,

			/*
			 * URL per le chiamate AJAX, deliberatamente NON codificato per HTML.
			 *
			 * $this->u_action contiene "&amp;", che è corretto in un attributo
			 * href ma non dentro un blocco <script>: lì la stringa non viene
			 * decodificata, quindi fetch() chiamerebbe "?i=...&amp;mode=bots" e
			 * i parametri diventerebbero "amp;mode", "amp;action". phpBB
			 * riceverebbe una richiesta senza mode, risponderebbe con una
			 * pagina HTML di errore e il JSON non si potrebbe interpretare.
			 */
			'U_AJAX'          => html_entity_decode($this->u_action, ENT_QUOTES),
		]);
	}

	protected function default_bot_data(): array
	{
		return [
			'bot_id'                => 0,
			'user_id'               => 0,
			'username'              => '',
			'bot_name'              => '',
			'provider'              => '',
			'model'                 => '',
			'api_key'               => '',
			'base_url'              => '',
			// Un bot nuovo parte con un prompt funzionante, non con un campo
			// vuoto: la pagina bianca è il punto in cui ci si arena.
			'system_prompt'         => $this->language->lang('AIREPLY_PRESET_WELCOME_PROMPT'),
			'temperature'           => 1.0,
			/*
			 * 2048 e non 800.
			 *
			 * È un tetto, non una spesa: si paga solo ciò che viene davvero
			 * generato. Ma quasi tutti i modelli Gemini attuali ragionano prima
			 * di rispondere, e i token di pensiero rientrano in questo limite:
			 * con 800 il ragionamento li esaurisce e arriva una risposta vuota.
			 * Un valore basso non fa risparmiare, fa fallire.
			 */
			'max_output_tokens'     => 2048,
			'thinking_budget'       => -1,
			'context_posts'         => 6,
			'context_max_chars'     => 12000,
			'max_post_chars'        => 1500,
			'reply_template'        => "{response}\n\n{disclosure}",
			'request_timeout'       => 90,
			'reply_language'        => bot::LANG_AUTO,
			'reply_language_custom' => '',
			'mention_poster'        => 0,
			'board_context'         => 1,
			'persona_recipe'        => '',
			'enabled'               => 0,
		];
	}

	protected function save_bot(): void
	{
		if (!check_form_key('aireply_bot'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$bot_id = $this->request->variable('bot_id', 0);
		$username = trim($this->request->variable('bot_username', '', true));

		if ($username === '')
		{
			trigger_error($this->language->lang('AIREPLY_ERR_NO_USERNAME') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$user_id = $this->find_user_id($username);

		if ($user_id === 0)
		{
			trigger_error($this->language->lang('AIREPLY_ERR_USER_NOT_FOUND', $username) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		// Un utente phpBB può essere associato a un solo bot: la tabella ha un
		// indice univoco, ma un messaggio chiaro è meglio di un errore SQL.
		$sql = 'SELECT bot_id FROM ' . $this->bots_table . '
			WHERE user_id = ' . (int) $user_id . '
				AND bot_id <> ' . (int) $bot_id;
		$result = $this->db->sql_query_limit($sql, 1);
		$clash = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($clash)
		{
			trigger_error($this->language->lang('AIREPLY_ERR_USER_TAKEN', $username) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		/*
		 * Il bot non risponde mai ai propri post: è la protezione anti-loop.
		 *
		 * Assegnare al bot lo stesso account con cui si amministra il forum è
		 * quindi un vicolo cieco silenzioso: si scrive un messaggio di prova,
		 * non succede nulla, e nel registro non compare niente perché il job
		 * non viene nemmeno accodato. È un errore facilissimo da fare in fase
		 * di prova e impossibile da diagnosticare senza saperlo.
		 */
		if ($user_id === (int) $this->user->data['user_id'])
		{
			trigger_error(
				$this->language->lang('AIREPLY_ERR_BOT_IS_YOU', $username) . adm_back_link($this->u_action),
				E_USER_WARNING
			);
		}

		$provider = $this->request->variable('provider', '');

		if (!$this->providers->has($provider))
		{
			trigger_error($this->language->lang('AIREPLY_ERR_UNKNOWN_PROVIDER') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		/*
		 * Il campo manuale ha la precedenza sul menù.
		 *
		 * Serve quando l'elenco modelli non è ottenibile: le chiavi Google
		 * possono essere ristrette per metodo e consentire generateContent ma
		 * non ListModels. In quel caso il menù resta vuoto per sempre e senza
		 * un campo libero il bot non sarebbe configurabile affatto.
		 */
		$model = trim($this->request->variable('model_custom_' . $provider, ''));

		if ($model === '')
		{
			$model = trim($this->request->variable('model_' . $provider, ''));
		}

		if ($model === '')
		{
			$model = $this->providers->get($provider)->get_default_model();
		}

		// Gli id dei modelli sono alfanumerici con punti, trattini e due punti.
		if (!preg_match('/^[A-Za-z0-9._:\/-]{2,128}$/', $model))
		{
			trigger_error($this->language->lang('AIREPLY_ERR_BAD_MODEL_ID', $model) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$data = [
			'user_id'               => $user_id,
			'bot_name'              => $this->request->variable('bot_name', '', true),
			'provider'              => $provider,
			'model'                 => $model,
			'base_url'              => trim($this->request->variable('base_url_' . $provider, '')),
			'system_prompt'         => $this->request->variable('system_prompt', '', true),
			'temperature'           => max(0.0, min(2.0, (float) $this->request->variable('temperature', 1.0))),
			'max_output_tokens'     => max(50, $this->request->variable('max_output_tokens', 800)),
			'thinking_budget'       => $this->request->variable('thinking_budget', -1),
			'context_posts'         => max(0, min(bot::MAX_CONTEXT_POSTS, $this->request->variable('context_posts', 6))),
			'context_max_chars'     => max(0, $this->request->variable('context_max_chars', 12000)),
			'max_post_chars'        => max(200, $this->request->variable('max_post_chars', 1500)),
			'reply_template'        => $this->request->variable('reply_template', '', true),
			'request_timeout'       => max(10, min(300, $this->request->variable('request_timeout', 90))),
			'reply_language'        => $this->request->variable('reply_language', bot::LANG_AUTO),
			'reply_language_custom' => $this->request->variable('reply_language_custom', '', true),
			'mention_poster'        => $this->request->variable('mention_poster', 0) ? 1 : 0,
			'board_context'         => $this->request->variable('board_context', 0) ? 1 : 0,

			/*
			 * La ricetta del costruttore di personalità.
			 *
			 * Non è la fonte di verità — quella resta il prompt, che l'admin può
			 * riscrivere a mano — ma senza salvarla, riaprendo il bot le caselle
			 * risultano vuote e non si sa più come era stato composto.
			 */
			'persona_recipe'        => $this->sanitise_recipe($this->request->variable('persona_recipe', '')),
			'enabled'               => $this->request->variable('enabled', 0) ? 1 : 0,
		];

		if ($data['bot_name'] === '')
		{
			$data['bot_name'] = $username;
		}

		/*
		 * La chiave si aggiorna solo se il campo è stato compilato. Il campo è
		 * di tipo password e non ristampa mai il valore salvato: se lo
		 * sovrascrivessimo con la stringa vuota, ogni modifica di un altro
		 * parametro cancellerebbe la chiave.
		 */
		$new_key = trim($this->request->variable('api_key_' . $provider, '', true));

		if ($new_key !== '')
		{
			/*
			 * Validazione di forma prima di salvare.
			 *
			 * Senza questo controllo, qualunque cosa finisca nel campo viene
			 * scritta in database e l'unico sintomo è un "chiave non valida"
			 * che arriva molto dopo, dall'API, e sembra colpa della chiave.
			 * È esattamente ciò che succede quando il gestore password del
			 * browser riempie il campo da solo.
			 */
			if (!$this->keys->looks_plausible($provider, $new_key) && !$this->request->variable('api_key_force', 0))
			{
				$described = $this->keys->describe($new_key);

				trigger_error(
					$this->language->lang(
						'AIREPLY_ERR_KEY_IMPLAUSIBLE',
						$this->provider_label($provider),
						$described['length'],
						$this->keys->mask($new_key)
					) . adm_back_link($this->u_action),
					E_USER_WARNING
				);
			}

			$data['api_key'] = $new_key;
		}

		if ($bot_id > 0)
		{
			$sql = 'UPDATE ' . $this->bots_table . '
				SET ' . $this->db->sql_build_array('UPDATE', $data) . '
				WHERE bot_id = ' . (int) $bot_id;
			$this->db->sql_query($sql);

			$message = 'AIREPLY_BOT_UPDATED';
		}
		else
		{
			if (!isset($data['api_key']))
			{
				$data['api_key'] = '';
			}

			$sql = 'INSERT INTO ' . $this->bots_table . ' ' . $this->db->sql_build_array('INSERT', $data);
			$this->db->sql_query($sql);
			$bot_id = (int) $this->db->sql_nextid();

			$message = 'AIREPLY_BOT_CREATED';
		}

		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_AIREPLY_BOT_SAVED', time(), [$data['bot_name']]);

		trigger_error($this->language->lang($message, $data['bot_name']) . adm_back_link($this->u_action));
	}

	protected function delete_bot(int $bot_id): void
	{
		if (!check_link_hash($this->request->variable('hash', ''), 'aireply_acp'))
		{
			trigger_error($this->language->lang('AIREPLY_ERR_BAD_HASH') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		if (confirm_box(true))
		{
			// I binding vanno via con il bot, altrimenti resterebbero righe
			// orfane che puntano a un bot inesistente.
			$this->db->sql_query('DELETE FROM ' . $this->forums_table . ' WHERE bot_id = ' . (int) $bot_id);
			$this->db->sql_query('DELETE FROM ' . $this->bots_table . ' WHERE bot_id = ' . (int) $bot_id);

			trigger_error($this->language->lang('AIREPLY_BOT_DELETED') . adm_back_link($this->u_action));
		}

		confirm_box(false, $this->language->lang('AIREPLY_CONFIRM_DELETE_BOT'), build_hidden_fields([
			'action' => 'delete',
			'bot_id' => $bot_id,
			'hash'   => $this->request->variable('hash', ''),
		]));

		redirect($this->u_action);
	}

	protected function toggle_bot(int $bot_id): void
	{
		if (!check_link_hash($this->request->variable('hash', ''), 'aireply_acp'))
		{
			trigger_error($this->language->lang('AIREPLY_ERR_BAD_HASH') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$sql = 'UPDATE ' . $this->bots_table . '
			SET enabled = 1 - enabled
			WHERE bot_id = ' . (int) $bot_id;
		$this->db->sql_query($sql);

		redirect($this->u_action);
	}

	/**
	 * Descrive a parole cosa produrrà la menzione su questa board.
	 *
	 * Tre informazioni distinte, che vanno tenute separate perché hanno rimedi
	 * diversi: l'estensione è presente? quale tag accetta? il bot ha il
	 * permesso di menzionare?
	 */
	protected function describe_mention_status(int $bot_user_id): string
	{
		if (!$this->mentions->is_extension_enabled())
		{
			return $this->language->lang('AIREPLY_MENTION_NO_EXT');
		}

		$format = $this->mentions->get_format();

		if ($format === mention_formatter::FORMAT_PLAIN)
		{
			return $this->language->lang('AIREPLY_MENTION_NO_TAG');
		}

		$status = $this->language->lang('AIREPLY_MENTION_OK', $format);

		if ($bot_user_id > 0 && !$this->mentions->user_can_mention($bot_user_id))
		{
			$status .= '<br>' . $this->language->lang('AIREPLY_MENTION_NO_PERM');
		}

		return $status;
	}

	/**
	 * Rilancia il rilevamento del formato, dopo un cambio di estensioni.
	 */
	protected function redetect_mentions(): void
	{
		if (!check_link_hash($this->request->variable('hash', ''), 'aireply_acp'))
		{
			trigger_error($this->language->lang('AIREPLY_ERR_BAD_HASH') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$format = $this->mentions->detect();
		$bot_id = $this->request->variable('bot_id', 0);

		$back = $this->u_action . ($bot_id > 0 ? '&amp;action=edit&amp;bot_id=' . $bot_id : '');

		trigger_error($this->language->lang('AIREPLY_MENTION_DETECTED', $format) . adm_back_link($back));
	}

	// ------------------------------------------------------------------
	// Creazione dell'utente del bot
	// ------------------------------------------------------------------

	/**
	 * Form per creare un utente phpBB da usare come bot.
	 */
	protected function render_user_form(): void
	{
		add_form_key('aireply_newuser');

		$suggerito = $this->request->variable('username', '', true);

		foreach ($this->creator->get_groups() as $g)
		{
			$this->template->assign_block_vars('groups', [
				'ID'   => $g['group_id'],
				'NAME' => $g['name'],
			]);
		}

		$this->template->assign_vars([
			'S_NEW_USER'      => true,
			'NEWUSER_NAME'    => $suggerito,
			'NEWUSER_EMAIL'   => $suggerito !== '' ? $this->creator->suggest_email($suggerito) : '',
			'MENTION_USEFUL'  => $this->mentions->is_extension_enabled(),
			'U_BACK'          => $this->u_action,
			'U_CREATE_USER'   => $this->u_action . '&amp;action=createuser',
		]);
	}

	/**
	 * Crea l'utente e, se richiesto, gli concede i permessi.
	 */
	protected function create_bot_user(): void
	{
		if (!check_form_key('aireply_newuser'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$username = trim($this->request->variable('username', '', true));
		$email = trim($this->request->variable('email', ''));
		$group_id = $this->request->variable('group_id', 0);

		if ($email === '' && $username !== '')
		{
			$email = $this->creator->suggest_email($username);
		}

		$outcome = $this->creator->create($username, $email, $group_id);

		if (!empty($outcome['errors']))
		{
			trigger_error(
				implode('<br>', $outcome['errors']) . adm_back_link($this->u_action . '&amp;action=newuser'),
				E_USER_WARNING
			);
		}

		$user_id = (int) $outcome['user_id'];
		$note = '';

		/*
		 * Il permesso di menzionare si concede subito, se ha senso.
		 *
		 * Quelli sui forum no: al momento della creazione il bot non è ancora
		 * assegnato a nessun forum, quindi non c'è nulla su cui agire. Si
		 * concedono con "Verifica permessi" dopo l'assegnazione, ed è anche
		 * l'ordine in cui l'amministratore lavora naturalmente.
		 */
		if ($this->request->variable('grant_mention', 0) && $this->mentions->is_extension_enabled())
		{
			/*
			 * L'assegnazione del permesso non deve poter annullare la creazione.
			 *
			 * L'utente a questo punto esiste già: se qualcosa va storto qui e
			 * l'errore risale come fatale, l'amministratore vede una pagina
			 * bianca e non sa che l'utente è stato creato lo stesso. Se ne
			 * accorge solo al secondo tentativo, quando gli viene detto che il
			 * nome è già in uso — che è esattamente il modo peggiore di
			 * scoprirlo.
			 */
			try
			{
				$granted = $this->permissions->grant($user_id, [], true);

				if (!empty($granted['granted_mention']))
				{
					$note = ' ' . $this->language->lang('AIREPLY_USER_GRANTED_MENTION');
				}
			}
			catch (\Throwable $e)
			{
				$note = ' ' . $this->language->lang('AIREPLY_USER_PERM_FAILED', $e->getMessage());
			}
		}

		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_USER_ADDED', time(), [$username]);

		/*
		 * Si torna al form del bot con il nome già compilato.
		 *
		 * Chi crea un utente lo sta facendo per assegnarlo a un bot: farglielo
		 * riscrivere a mano subito dopo sarebbe una piccola scortesia gratuita.
		 */
		trigger_error(
			$this->language->lang('AIREPLY_USER_CREATED', $username) . $note
				. adm_back_link($this->u_action . '&amp;action=add&amp;prefill=' . urlencode($username))
		);
	}

	/**
	 * Concede al bot i permessi che gli mancano.
	 */
	protected function fix_permissions(int $bot_id): void
	{
		if (!check_link_hash($this->request->variable('hash', ''), 'aireply_acp'))
		{
			trigger_error($this->language->lang('AIREPLY_ERR_BAD_HASH') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$b = $this->load_bot($bot_id);

		if ($b === null)
		{
			trigger_error($this->language->lang('AIREPLY_BOT_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$forums = $this->permissions->get_bot_forums($bot_id, $this->forums_table);
		$mention_needed = !empty($b['mention_poster']) && $this->mentions->is_extension_enabled();

		try
		{
			$granted = $this->permissions->grant((int) $b['user_id'], $forums, $mention_needed);
		}
		catch (\Throwable $e)
		{
			trigger_error(
				$this->language->lang('AIREPLY_USER_PERM_FAILED', $e->getMessage()) . adm_back_link($this->u_action),
				E_USER_WARNING
			);
		}

		$parts = [];

		if (!empty($granted['granted_mention']))
		{
			$parts[] = $this->language->lang('AIREPLY_USER_GRANTED_MENTION');
		}

		if (!empty($granted['granted_forums']))
		{
			$parts[] = $this->language->lang('AIREPLY_USER_GRANTED_FORUMS', count($granted['granted_forums']));
		}

		$message = empty($parts)
			? $this->language->lang('AIREPLY_PERMS_ALREADY_OK')
			: implode('<br>', $parts);

		trigger_error($message . adm_back_link($this->u_action));
	}

	/**
	 * Riga grezza di un bot, senza costruire l'oggetto.
	 */
	protected function load_bot(int $bot_id): ?array
	{
		$sql = 'SELECT * FROM ' . $this->bots_table . ' WHERE bot_id = ' . (int) $bot_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	protected function find_user_id(string $username): int
	{
		$sql = 'SELECT user_id FROM ' . USERS_TABLE . "
			WHERE username_clean = '" . $this->db->sql_escape(utf8_clean_string($username)) . "'";

		$result = $this->db->sql_query($sql);
		$user_id = (int) $this->db->sql_fetchfield('user_id');
		$this->db->sql_freeresult($result);

		return $user_id;
	}

	/**
	 * Estratto leggibile del prompt di sistema.
	 *
	 * Il taglio avviene sull'ultimo spazio utile e non a metà parola: un
	 * riassunto che finisce con "acco…" comunica meno di uno che finisce con
	 * "accogliere chi si…".
	 */
	/**
	 * Ripulisce la ricetta prima di salvarla.
	 *
	 * Arriva da un campo nascosto compilato dal JavaScript, quindi va trattata
	 * come qualunque altro dato inviato dal client: si accettano solo lettere,
	 * cifre, la barra verticale che separa ruolo e tratti, e la virgola.
	 */
	protected function sanitise_recipe(string $recipe): string
	{
		$recipe = trim($recipe);

		if ($recipe === '' || !preg_match('/^[a-z0-9_]*\|[a-z0-9_,]*$/', $recipe))
		{
			return '';
		}

		return mb_substr($recipe, 0, 250);
	}

	protected function summarise_prompt(string $prompt): string
	{
		$prompt = trim(preg_replace('/\s+/u', ' ', $prompt) ?? $prompt);

		if ($prompt === '')
		{
			return '';
		}

		if (mb_strlen($prompt) <= 140)
		{
			return $prompt;
		}

		$cut = mb_substr($prompt, 0, 140);
		$space = mb_strrpos($cut, ' ');

		if ($space !== false && $space > 90)
		{
			$cut = mb_substr($cut, 0, $space);
		}

		return rtrim($cut) . '…';
	}

	protected function provider_label(string $provider_id): string
	{
		return $this->providers->has($provider_id)
			? $this->language->lang($this->providers->get($provider_id)->get_label_key())
			: $provider_id;
	}

	// ==================================================================
	// Scheda: Forum
	// ==================================================================

	public function mode_forums(): void
	{
		add_form_key('aireply_forums');

		/*
		 * Le due azioni non escono più dalla pagina con trigger_error().
		 *
		 * Con adm_back_link() l'amministratore finisce su una schermata di
		 * conferma e torna a un modulo azzerato: la selezione dei forum, il bot
		 * e i limiti appena impostati spariscono. Su un'operazione che si
		 * ripete più volte di seguito è esasperante. Qui si esegue, si torna a
		 * disegnare la stessa pagina con i valori appena usati e si mostra la
		 * conferma in cima.
		 */
		$notice = '';
		$applied = [];
		$posted = null;

		if ($this->request->is_set_post('submit'))
		{
			$notice = $this->save_forums();
		}

		if ($this->request->is_set_post('bulk_submit'))
		{
			$outcome = $this->bulk_assign();
			$notice = $outcome['message'];
			$applied = $outcome['applied'];
			$posted = $outcome['settings'];
		}

		$bots = $this->load_bot_options();

		if (empty($bots))
		{
			// Senza bot questa pagina non ha nulla da mostrare: meglio dirlo
			// che presentare una tabella di menù vuoti.
			$this->template->assign_var('S_NO_BOTS', true);
		}

		$bindings = $this->load_bindings();
		$bot_labels = $this->load_bot_labels();

		/*
		 * Rendering a due livelli: una riga per forum, e sotto una riga per
		 * ogni bot assegnato.
		 *
		 * Ogni binding conserva i propri innesco e limiti: è ciò che permette
		 * al bot principale di accogliere i nuovi iscritti e alle personalità
		 * aggiuntive di restare in attesa di essere chiamate per nome.
		 */
		$forum_count = 0;

		foreach ($this->load_forum_tree() as $node)
		{
			$forum_id = $node['forum_id'];

			if (!$node['postable'])
			{
				$this->template->assign_block_vars('forums', [
					'IS_CATEGORY' => true,
					'FORUM_ID'    => $forum_id,
					'NAME'        => $node['name'],
					'INDENT'      => $node['depth'],
				]);
				continue;
			}

			$forum_count++;
			$assigned = $bindings[$forum_id] ?? [];

			// Quanti bot rispondono in automatico: serve all'avviso sui costi.
			$auto = 0;

			foreach ($assigned as $row)
			{
				if (!empty($row['on_new_topic']) || !empty($row['on_reply']))
				{
					$auto++;
				}
			}

			$this->template->assign_block_vars('forums', [
				'IS_CATEGORY'  => false,
				'FORUM_ID'     => $forum_id,
				'NAME'         => $node['name'],
				'INDENT'       => $node['depth'],
				'BOT_COUNT'    => count($assigned),
				'AUTO_COUNT'   => $auto,
				'WARN_MULTI'   => ($auto > 1),
			]);

			foreach ($assigned as $row)
			{
				$bot_id = (int) $row['bot_id'];

				$this->template->assign_block_vars('forums.assigned', [
					'BOT_ID'       => $bot_id,
					'NAME'         => $bot_labels[$bot_id] ?? ('#' . $bot_id),
					'ON_NEW_TOPIC' => (bool) $row['on_new_topic'],
					'ON_REPLY'     => (bool) $row['on_reply'],
					'ON_MENTION'   => (bool) $row['on_mention'],
					'DELAY'        => (int) $row['delay_seconds'],
					'DAILY_CAP'    => (int) $row['daily_cap'],
					'COOLDOWN'     => (int) $row['cooldown_seconds'],
				]);
			}

			// Menù per aggiungere un bot non ancora presente in questo forum.
			foreach ($bots as $b)
			{
				if (isset($assigned[$b['bot_id']]))
				{
					continue;
				}

				$this->template->assign_block_vars('forums.addable', [
					'ID'   => $b['bot_id'],
					'NAME' => $b['label'],
				]);
			}
		}

		// Lista a selezione multipla per l'assegnazione rapida.
		// Riusa l'albero già calcolato: una seconda query darebbe gli stessi
		// dati e una seconda occasione di divergere.
		foreach ($this->load_forum_tree() as $node)
		{
			if (!$node['postable'])
			{
				continue;
			}

			$this->template->assign_block_vars('bulk_forums', [
				'ID'       => $node['forum_id'],
				'NAME'     => str_repeat('   ', $node['depth']) . $node['name'],
				'ASSIGNED' => isset($bindings[$node['forum_id']]),
				// Resta selezionato ciò su cui si è appena agito: quasi sempre
				// il passo successivo riguarda gli stessi forum.
				'SELECTED' => in_array($node['forum_id'], $applied, true),
			]);
		}

		foreach ($bots as $b)
		{
			$this->template->assign_block_vars('bulk_bots', [
				'ID'       => $b['bot_id'],
				'NAME'     => $b['label'],
				'SELECTED' => ($posted !== null && (int) $posted['bot_id'] === (int) $b['bot_id']),
			]);
		}

		$this->template->assign_vars([
			'AIREPLY_NOTICE'    => $notice,

			// Il pannello ricorda l'ultima configurazione usata.
			'BULK_ON_TOPIC'     => ($posted === null) ? true  : (bool) $posted['on_new_topic'],
			'BULK_ON_REPLY'     => ($posted === null) ? false : (bool) $posted['on_reply'],
			'BULK_ON_MENTION'   => ($posted === null) ? true  : (bool) $posted['on_mention'],
			'BULK_DELAY'        => ($posted === null) ? 20  : (int) $posted['delay'],
			'BULK_CAP'          => ($posted === null) ? 30  : (int) $posted['cap'],
			'BULK_COOLDOWN'     => ($posted === null) ? 120 : (int) $posted['cooldown'],

			'S_FORUM_LIST'      => true,
			'AIREPLY_FORUMS'    => $forum_count,

			// Conteggio esplicito: su una board con pochi forum, una tabella
			// di una riga sola sembra un elenco che non si è caricato.
			'L_AIREPLY_FORUMS_FOUND' => $this->language->lang('AIREPLY_FORUMS_FOUND', $forum_count),
			'AIREPLY_GLOBAL_ON' => !empty($this->config['aireply_enabled']),
			'U_ACTION'          => $this->u_action,
		]);
	}

	/**
	 * Albero dei forum in ordine di visualizzazione, con la profondità.
	 *
	 * @return array[] forum_id, name, depth, postable
	 */
	protected function load_forum_tree(): array
	{
		$sql = 'SELECT forum_id, forum_name, forum_type, parent_id, left_id
			FROM ' . FORUMS_TABLE . '
			ORDER BY left_id ASC';

		$result = $this->db->sql_query($sql);

		$depth = [];
		$tree = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$forum_id = (int) $row['forum_id'];
			$parent_id = (int) $row['parent_id'];

			$depth[$forum_id] = ($parent_id && isset($depth[$parent_id])) ? $depth[$parent_id] + 1 : 0;

			$tree[] = [
				'forum_id' => $forum_id,
				'name'     => (string) $row['forum_name'],
				'depth'    => $depth[$forum_id],
				// Categorie e collegamenti non possono contenere post.
				'postable' => ((int) $row['forum_type'] === FORUM_POST),
			];
		}

		$this->db->sql_freeresult($result);

		return $tree;
	}

	/**
	 * Assegnazione rapida: applica lo stesso bot e le stesse regole a tutti i
	 * forum selezionati, senza toccare gli altri.
	 *
	 * La tabella sottostante resta il posto dove rifinire forum per forum;
	 * questo serve a partire in fretta su una board con molte sezioni.
	 */
	/**
	 * @return array{message: string, applied: int[], settings: array|null}
	 */
	protected function bulk_assign(): array
	{
		if (!check_form_key('aireply_forums'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$forum_ids = array_map('intval', $this->request->variable('bulk_forums', [0]));
		$forum_ids = array_values(array_filter($forum_ids));

		$bot_id = $this->request->variable('bulk_bot', -1);

		$settings = [
			'bot_id'       => $bot_id,
			'on_new_topic' => $this->request->variable('bulk_on_new_topic', 0),
			'on_reply'     => $this->request->variable('bulk_on_reply', 0),
			'on_mention'   => $this->request->variable('bulk_on_mention', 0),
			'delay'        => $this->request->variable('bulk_delay', 20),
			'cap'          => $this->request->variable('bulk_cap', 30),
			'cooldown'     => $this->request->variable('bulk_cooldown', 120),
		];

		if (empty($forum_ids))
		{
			return [
				'message'  => $this->language->lang('AIREPLY_ERR_NO_FORUM_SELECTED'),
				'applied'  => [],
				'settings' => $settings,
			];
		}

		/*
		 * -1 è il segnaposto "non ho ancora scelto".
		 *
		 * Prima la prima voce del menù era «Rimuovi assegnazione» con valore 0,
		 * quindi era anche quella preselezionata: bastava premere Applica senza
		 * toccare il menù per cancellare le assegnazioni dei forum scelti.
		 * Un'azione distruttiva non deve mai essere l'impostazione predefinita.
		 */
		if ($bot_id === -1)
		{
			return [
				'message'  => $this->language->lang('AIREPLY_ERR_NO_BOT_CHOSEN'),
				'applied'  => $forum_ids,
				'settings' => $settings,
			];
		}

		// Zero significa rimuovere ogni assegnazione: è una scelta esplicita.
		if ($bot_id === 0)
		{
			$sql = 'DELETE FROM ' . $this->forums_table . '
				WHERE ' . $this->db->sql_in_set('forum_id', $forum_ids);
			$this->db->sql_query($sql);

			return [
				'message'  => $this->language->lang('AIREPLY_BULK_CLEARED', count($forum_ids)),
				'applied'  => $forum_ids,
				'settings' => $settings,
			];
		}

		/*
		 * Con più bot per forum, "applica" diventa ambiguo: il bot si affianca
		 * a quelli già presenti o li sostituisce? Chiederlo esplicitamente è
		 * l'unico modo per non far cancellare configurazioni per sbaglio.
		 */
		$replace = (bool) $this->request->variable('bulk_replace', 0);
		$settings['replace'] = $replace ? 1 : 0;

		$topic = $settings['on_new_topic'] ? 1 : 0;
		$reply = $settings['on_reply'] ? 1 : 0;
		$mention = $settings['on_mention'] ? 1 : 0;

		// Un binding senza innesco non farebbe mai nulla.
		if (!$topic && !$reply && !$mention)
		{
			$mention = 1;
		}

		$rows = [];

		foreach ($forum_ids as $forum_id)
		{
			$rows[] = [
				'forum_id'         => $forum_id,
				'bot_id'           => $bot_id,
				'on_new_topic'     => $topic,
				'on_reply'         => $reply,
				'on_mention'       => $mention,
				'delay_seconds'    => max(0, (int) $settings['delay']),
				'daily_cap'        => max(0, (int) $settings['cap']),
				'cooldown_seconds' => max(0, (int) $settings['cooldown']),
				'enabled'          => 1,
			];
		}

		$this->db->sql_transaction('begin');

		try
		{
			if ($replace)
			{
				// Sostituisci: via tutti i bot dai forum selezionati.
				$sql = 'DELETE FROM ' . $this->forums_table . '
					WHERE ' . $this->db->sql_in_set('forum_id', $forum_ids);
			}
			else
			{
				// Aggiungi: si toglie solo questo bot, per riscriverlo con le
				// impostazioni nuove senza violare la chiave primaria.
				$sql = 'DELETE FROM ' . $this->forums_table . '
					WHERE bot_id = ' . (int) $bot_id . '
						AND ' . $this->db->sql_in_set('forum_id', $forum_ids);
			}

			$this->db->sql_query($sql);
			$this->db->sql_multi_insert($this->forums_table, $rows);

			$this->db->sql_transaction('commit');
		}
		catch (\Exception $e)
		{
			$this->db->sql_transaction('rollback');

			trigger_error($this->language->lang('AIREPLY_ERR_SAVE_FAILED', $e->getMessage()) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_AIREPLY_FORUMS_SAVED', time(), [count($rows)]);

		return [
			'message'  => $this->language->lang('AIREPLY_BULK_APPLIED', count($rows)),
			'applied'  => $forum_ids,
			'settings' => $settings,
		];
	}

	/**
	 * @return array[] bot_id, label
	 */
	protected function load_bot_options(): array
	{
		$sql_array = [
			'SELECT'    => 'b.bot_id, b.bot_name, b.provider, b.model, b.enabled, u.username',
			'FROM'      => [$this->bots_table => 'b'],
			'LEFT_JOIN' => [[
				'FROM' => [USERS_TABLE => 'u'],
				'ON'   => 'u.user_id = b.user_id',
			]],
			'ORDER_BY'  => 'b.bot_name ASC',
		];

		$result = $this->db->sql_query($this->db->sql_build_query('SELECT', $sql_array));

		$bots = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$label = ($row['bot_name'] !== '' ? $row['bot_name'] : $row['username'])
				. ' — ' . $this->provider_label((string) $row['provider'])
				. ' / ' . $row['model'];

			if (empty($row['enabled']))
			{
				$label .= ' (' . $this->language->lang('AIREPLY_BOT_DISABLED_SUFFIX') . ')';
			}

			$bots[] = ['bot_id' => (int) $row['bot_id'], 'label' => $label];
		}

		$this->db->sql_freeresult($result);

		return $bots;
	}

	/**
	 * Binding indicizzati per forum e, dentro, per bot.
	 *
	 * Prima era un binding per forum: la struttura piatta era il vero limite
	 * che impediva di assegnarne più d'uno, anche se il database lo permetteva
	 * da sempre grazie alla chiave primaria composta.
	 *
	 * @return array[] $bindings[forum_id][bot_id] = riga
	 */
	protected function load_bindings(): array
	{
		$result = $this->db->sql_query('SELECT * FROM ' . $this->forums_table . ' ORDER BY forum_id ASC, bot_id ASC');

		$bindings = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$bindings[(int) $row['forum_id']][(int) $row['bot_id']] = $row;
		}

		$this->db->sql_freeresult($result);

		return $bindings;
	}

	/**
	 * Etichette dei bot indicizzate per id, per la tabella dei forum.
	 */
	protected function load_bot_labels(): array
	{
		$labels = [];

		foreach ($this->load_bot_options() as $b)
		{
			$labels[(int) $b['bot_id']] = $b['label'];
		}

		return $labels;
	}

	protected function save_forums(): string
	{
		if (!check_form_key('aireply_forums'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		/*
		 * I campi sono indicizzati per coppia: on_reply[forum_id][bot_id].
		 *
		 * Prima erano indicizzati per solo forum, ed era quello — non il
		 * database — a imporre un bot per forum.
		 *
		 * `known` elenca i binding che il modulo aveva davanti quando è stato
		 * disegnato. Serve a distinguere "questo binding non c'è più perché
		 * l'admin l'ha rimosso" da "non c'è perché nel frattempo qualcun altro
		 * l'ha aggiunto": senza, due amministratori al lavoro insieme si
		 * cancellerebbero il lavoro a vicenda.
		 */
		$known    = $this->request->variable('known', [0 => [0 => 0]]);
		$remove   = $this->request->variable('remove', [0 => [0 => 0]]);
		$on_topic = $this->request->variable('on_new_topic', [0 => [0 => 0]]);
		$on_reply = $this->request->variable('on_reply', [0 => [0 => 0]]);
		$on_ment  = $this->request->variable('on_mention', [0 => [0 => 0]]);
		$delay    = $this->request->variable('delay', [0 => [0 => 0]]);
		$cap      = $this->request->variable('cap', [0 => [0 => 0]]);
		$cooldown = $this->request->variable('cooldown', [0 => [0 => 0]]);
		$add_bot  = $this->request->variable('add_bot', [0 => 0]);

		$rows = [];
		$seen = [];

		foreach ($known as $forum_id => $bot_ids)
		{
			$forum_id = (int) $forum_id;

			if ($forum_id <= 0 || !is_array($bot_ids))
			{
				continue;
			}

			foreach ($bot_ids as $bot_id)
			{
				$bot_id = (int) $bot_id;

				if ($bot_id <= 0 || !empty($remove[$forum_id][$bot_id]))
				{
					continue;
				}

				$rows[] = $this->build_binding_row($forum_id, $bot_id, [
					'topic'    => !empty($on_topic[$forum_id][$bot_id]),
					'reply'    => !empty($on_reply[$forum_id][$bot_id]),
					'mention'  => !empty($on_ment[$forum_id][$bot_id]),
					'delay'    => (int) ($delay[$forum_id][$bot_id] ?? 20),
					'cap'      => (int) ($cap[$forum_id][$bot_id] ?? 30),
					'cooldown' => (int) ($cooldown[$forum_id][$bot_id] ?? 120),
				]);

				$seen[$forum_id][$bot_id] = true;
			}
		}

		// Bot aggiunti dal menù in fondo alla riga del forum.
		foreach ($add_bot as $forum_id => $bot_id)
		{
			$forum_id = (int) $forum_id;
			$bot_id = (int) $bot_id;

			if ($forum_id <= 0 || $bot_id <= 0 || isset($seen[$forum_id][$bot_id]))
			{
				continue;
			}

			// Un bot appena aggiunto parte mention-only: è la scelta che non
			// sorprende nessuno e non moltiplica le risposte automatiche.
			$rows[] = $this->build_binding_row($forum_id, $bot_id, [
				'topic'    => false,
				'reply'    => false,
				'mention'  => true,
				'delay'    => 20,
				'cap'      => 30,
				'cooldown' => 120,
			]);

			$seen[$forum_id][$bot_id] = true;
		}

		$this->db->sql_transaction('begin');

		try
		{
			$this->db->sql_query('DELETE FROM ' . $this->forums_table);

			if (!empty($rows))
			{
				$this->db->sql_multi_insert($this->forums_table, $rows);
			}

			$this->db->sql_transaction('commit');
		}
		catch (\Exception $e)
		{
			$this->db->sql_transaction('rollback');

			trigger_error($this->language->lang('AIREPLY_ERR_SAVE_FAILED', $e->getMessage()) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_AIREPLY_FORUMS_SAVED', time(), [count($rows)]);

		return $this->language->lang('AIREPLY_FORUMS_SAVED', count($rows));
	}

	/**
	 * Costruisce una riga di binding normalizzata.
	 */
	protected function build_binding_row(int $forum_id, int $bot_id, array $opts): array
	{
		$topic = !empty($opts['topic']);
		$reply = !empty($opts['reply']);
		$mention = !empty($opts['mention']);

		// Un binding senza nessun innesco non farebbe mai nulla: resterebbe in
		// tabella a occupare spazio e a confondere chi lo rilegge.
		if (!$topic && !$reply && !$mention)
		{
			$mention = true;
		}

		return [
			'forum_id'         => $forum_id,
			'bot_id'           => $bot_id,
			'on_new_topic'     => (int) $topic,
			'on_reply'         => (int) $reply,
			'on_mention'       => (int) $mention,
			'delay_seconds'    => max(0, (int) $opts['delay']),
			'daily_cap'        => max(0, (int) $opts['cap']),
			'cooldown_seconds' => max(0, (int) $opts['cooldown']),
			'enabled'          => 1,
		];
	}

	// ==================================================================
	// Scheda: Registro attività
	// ==================================================================

	public function mode_jobs(): void
	{
		$job_id = $this->request->variable('job_id', 0);

		if ($job_id > 0)
		{
			$this->render_job_detail($job_id);
			return;
		}

		if ($this->request->variable('action', '') === 'clear' && check_link_hash($this->request->variable('hash', ''), 'aireply_acp'))
		{
			$this->db->sql_query('DELETE FROM ' . $this->jobs_table . " WHERE status IN ('done', 'failed', 'skipped')");
			trigger_error($this->language->lang('AIREPLY_JOBS_CLEARED') . adm_back_link($this->u_action));
		}

		$sql_array = [
			'SELECT'    => 'j.*, b.bot_name, b.provider, b.model, f.forum_name, u.username',
			'FROM'      => [$this->jobs_table => 'j'],
			'LEFT_JOIN' => [
				['FROM' => [$this->bots_table => 'b'], 'ON' => 'b.bot_id = j.bot_id'],
				['FROM' => [FORUMS_TABLE => 'f'], 'ON' => 'f.forum_id = j.forum_id'],
				['FROM' => [USERS_TABLE => 'u'], 'ON' => 'u.user_id = j.poster_id'],
			],
			'ORDER_BY'  => 'j.job_id DESC',
		];

		$result = $this->db->sql_query_limit($this->db->sql_build_query('SELECT', $sql_array), 60);

		$count = 0;

		while ($row = $this->db->sql_fetchrow($result))
		{
			$count++;

			$this->template->assign_block_vars('jobs', [
				'JOB_ID'    => (int) $row['job_id'],
				'CREATED'   => $this->user->format_date((int) $row['created_at']),
				'STATUS'    => $row['status'],
				'BOT'       => $row['bot_name'] ?? ('#' . (int) $row['bot_id']),
				'MODEL'     => $row['model'] ?? '',
				'FORUM'     => $row['forum_name'] ?? '',
				'POSTER'    => $row['username'] ?? '',
				'TRIGGER'   => $row['trigger_type'],
				'ATTEMPTS'  => (int) $row['attempts'] . '/' . (int) $row['max_attempts'],
				'TOKENS'    => (int) $row['prompt_tokens'] + (int) $row['completion_tokens'],
				'DURATION'  => (int) $row['duration_ms'],
				'ERROR'     => $row['error_message'],
				'U_DETAIL'  => $this->u_action . '&amp;job_id=' . (int) $row['job_id'],
				'U_POST'    => (int) $row['response_post_id'] > 0
					? append_sid(generate_board_url() . '/viewtopic.php', 'p=' . (int) $row['response_post_id']) . '#p' . (int) $row['response_post_id']
					: '',
			]);
		}

		$this->db->sql_freeresult($result);

		// Conteggio per stato: dà subito il polso della situazione.
		$sql = 'SELECT status, COUNT(job_id) AS total FROM ' . $this->jobs_table . ' GROUP BY status';
		$result = $this->db->sql_query($sql);

		$totals = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$totals[$row['status']] = (int) $row['total'];
		}

		$this->db->sql_freeresult($result);

		$this->template->assign_vars([
			'S_JOB_LIST'        => true,
			'AIREPLY_JOB_COUNT' => $count,
			'AIREPLY_Q_QUEUED'  => $totals['queued'] ?? 0,
			'AIREPLY_Q_RUNNING' => $totals['running'] ?? 0,
			'AIREPLY_Q_DONE'    => $totals['done'] ?? 0,
			'AIREPLY_Q_FAILED'  => $totals['failed'] ?? 0,
			'AIREPLY_Q_SKIPPED' => $totals['skipped'] ?? 0,
			'AIREPLY_CRON_LAST' => (int) $this->config['aireply_cron_last_run'] > 0
				? $this->user->format_date((int) $this->config['aireply_cron_last_run'])
				: $this->language->lang('AIREPLY_CRON_NEVER'),
			'U_CLEAR'           => $this->u_action . '&amp;action=clear&amp;hash=' . generate_link_hash('aireply_acp'),
		]);
	}

	protected function render_job_detail(int $job_id): void
	{
		$sql = 'SELECT * FROM ' . $this->jobs_table . ' WHERE job_id = ' . (int) $job_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			trigger_error($this->language->lang('AIREPLY_JOB_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$log = json_decode((string) $row['log'], true);

		$this->template->assign_vars([
			'S_JOB_DETAIL'  => true,
			'JOB_ID'        => (int) $row['job_id'],
			'JOB_STATUS'    => $row['status'],
			'JOB_CREATED'   => $this->user->format_date((int) $row['created_at']),
			'JOB_ERROR'     => $row['error_message'],
			'JOB_REQUEST'   => $row['request'],
			'JOB_RESPONSE'  => $row['response'],
			'JOB_LOG'       => is_array($log)
				? json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
				: (string) $row['log'],
			'U_BACK'        => $this->u_action,
		]);
	}

	// ==================================================================
	// Scheda: Impostazioni
	// ==================================================================

	public function mode_settings(): void
	{
		add_form_key('aireply_settings');

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key('aireply_settings'))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$this->config->set('aireply_enabled', $this->request->variable('aireply_enabled', 0) ? 1 : 0);
			$this->config->set('aireply_batch_size', max(1, min(20, $this->request->variable('aireply_batch_size', 3))));
			$this->config->set('aireply_max_bots_per_post', max(0, min(10, $this->request->variable('aireply_max_bots_per_post', 1))));
			$this->config->set('aireply_cron_interval', max(30, $this->request->variable('aireply_cron_interval', 60)));
			$this->config->set('aireply_verbose_log', $this->request->variable('aireply_verbose_log', 0) ? 1 : 0);
			$this->config->set('aireply_job_retention_days', max(0, $this->request->variable('aireply_job_retention_days', 30)));
			$this->config->set('aireply_monthly_budget', max(0, (float) $this->request->variable('aireply_monthly_budget', 0.0)));
			$this->config->set('aireply_currency', $this->request->variable('aireply_currency', 'USD'));

			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_AIREPLY_SETTINGS_SAVED', time());

			trigger_error($this->language->lang('AIREPLY_SETTINGS_SAVED') . adm_back_link($this->u_action));
		}

		$budget = $this->usage->get_budget_status();

		$this->template->assign_vars([
			'S_SETTINGS'          => true,
			'AIREPLY_ENABLED'     => !empty($this->config['aireply_enabled']),
			'AIREPLY_BATCH'       => (int) $this->config['aireply_batch_size'],
			'AIREPLY_MAX_BOTS'    => (int) ($this->config['aireply_max_bots_per_post'] ?? 1),
			'AIREPLY_INTERVAL'    => (int) $this->config['aireply_cron_interval'],
			'AIREPLY_VERBOSE'     => !empty($this->config['aireply_verbose_log']),
			'AIREPLY_RETENTION'   => (int) $this->config['aireply_job_retention_days'],
			'AIREPLY_BUDGET_RAW'  => (float) $this->config['aireply_monthly_budget'],
			'AIREPLY_CURRENCY'    => $this->usage->get_currency(),
			'AIREPLY_SPENT'       => $this->usage->format_cost($budget['spent']),
			'AIREPLY_BUDGET_PCT'  => $budget['percent'],
			'AIREPLY_CRON_LAST'   => (int) $this->config['aireply_cron_last_run'] > 0
				? $this->user->format_date((int) $this->config['aireply_cron_last_run'])
				: $this->language->lang('AIREPLY_CRON_NEVER'),
			'U_ACTION'            => $this->u_action,
		]);
	}

	// ==================================================================
	// Azioni AJAX
	// ==================================================================

	public function ajax_refresh_models(): array
	{
		$provider_id = $this->request->variable('provider', '');
		$stored_key = $this->resolve_form_key($provider_id);

		if (!$this->providers->has($provider_id))
		{
			return $this->error($this->language->lang('AIREPLY_ERR_UNKNOWN_PROVIDER'));
		}

		if ($stored_key === '')
		{
			return $this->error($this->language->lang('AIREPLY_ERR_NO_KEY'));
		}

		try
		{
			$outcome = $this->providers->refresh_models($provider_id, $stored_key, $this->request->variable('base_url', ''));
		}
		catch (provider_exception $e)
		{
			/*
			 * L'elenco modelli è precluso, ma la chiave può essere perfettamente
			 * funzionante: succede con le chiavi ristrette per metodo.
			 *
			 * Rovesciare addosso all'admin il messaggio grezzo del provider lo
			 * lascia in un vicolo cieco: sembra un errore fatale, mentre in
			 * realtà basta scrivere il nome del modello a mano. Qui verifichiamo
			 * la chiave con una generazione minima e lo diciamo esplicitamente.
			 */
			if ($e->get_error_code() === ai_result::ERR_METHOD_BLOCKED)
			{
				return $this->explain_blocked_model_list($provider_id, $stored_key, $e->getMessage());
			}

			return $this->error($e->getMessage(), $e->get_error_code(), $e->get_http_status());
		}

		$message = $this->language->lang('AIREPLY_MODELS_REFRESHED', $outcome['total']);

		if (!empty($outcome['added']))
		{
			$message .= ' ' . $this->language->lang('AIREPLY_MODELS_NEW_FOUND', count($outcome['added']), implode(', ', array_slice($outcome['added'], 0, 5)));
		}

		if (!empty($outcome['removed']))
		{
			$message .= ' ' . $this->language->lang('AIREPLY_MODELS_REMOVED', implode(', ', array_slice($outcome['removed'], 0, 5)));
		}

		return [
			'success' => true,
			'message' => $message,
			'models'  => $this->build_model_list($provider_id),
		];
	}

	public function ajax_test(): array
	{
		$provider_id = $this->request->variable('provider', '');
		$stored_key = $this->resolve_form_key($provider_id);
		$model = $this->request->variable('model', '');

		if (!$this->providers->has($provider_id))
		{
			return $this->error($this->language->lang('AIREPLY_ERR_UNKNOWN_PROVIDER'));
		}

		$api_key = $this->keys->resolve($stored_key);

		if ($api_key === '')
		{
			return $this->error($this->language->lang('AIREPLY_ERR_KEY_UNRESOLVED'));
		}

		$warning = $this->keys->looks_plausible($provider_id, $api_key)
			? ''
			: $this->language->lang('AIREPLY_WARN_KEY_FORMAT');

		$result = $this->providers->get($provider_id)->test_connection($api_key, $this->request->variable('base_url', ''), $model);

		if (!$result->success)
		{
			$error = $this->error($result->error_message, $result->error_code, $result->http_status);

			// Un 403 di servizio bloccato ha un rimedio preciso: dirlo qui
			// evita il giro di ricerche a cui costringerebbe il solo messaggio
			// del provider.
			if ($result->error_code === ai_result::ERR_METHOD_BLOCKED)
			{
				$error['warning'] = $this->language->lang('AIREPLY_ERR_SERVICE_BLOCKED_HINT');
			}

			return $error;
		}

		$found = (int) ($result->debug['models_found'] ?? 0);

		/*
		 * Se la verifica è passata dal ripiego, l'elenco modelli non è
		 * disponibile: dichiararlo "tutto a posto" senza spiegare perché il
		 * menù resta vuoto genererebbe la segnalazione successiva.
		 */
		if (!empty($result->debug['verified_by']))
		{
			return [
				'success' => true,
				'message' => $this->language->lang('AIREPLY_TEST_OK_LIMITED', (string) $result->debug['model_verified']),
				'warning' => $this->language->lang('AIREPLY_ERR_METHOD_BLOCKED_HINT'),
			];
		}

		return [
			'success' => true,
			'message' => $model !== ''
				? $this->language->lang('AIREPLY_TEST_OK_WITH_MODEL', $model, $found)
				: $this->language->lang('AIREPLY_TEST_OK', $found),
			'warning' => $warning,
		];
	}

	public function ajax_model_info(): array
	{
		$provider_id = $this->request->variable('provider', '');
		$model_id = $this->request->variable('model', '');

		if (!$this->providers->has($provider_id) || $model_id === '')
		{
			return $this->error($this->language->lang('AIREPLY_ERR_UNKNOWN_PROVIDER'));
		}

		$provider = $this->providers->get($provider_id);
		$profile = $this->providers->get_profile();
		$row = $this->providers->get_cached_model($provider_id, $model_id);
		$described = $profile->describe($provider_id, $model_id);

		$context_chars = $this->request->variable('context_max_chars', 12000);
		$max_output = $this->request->variable('max_output_tokens', 800);
		$daily_cap = $this->request->variable('daily_cap', 0);

		$estimate = $this->usage->estimate_per_reply($provider_id, $model_id, $context_chars, $max_output, $daily_cap);
		$price = $this->usage->get_price($provider_id, $model_id);
		$month = $this->usage->summarise_month();
		$budget = $this->usage->get_budget_status();

		$notes = [];

		if (($row && !empty($row['is_reasoning'])) || $provider->supports_thinking_budget($model_id))
		{
			$notes[] = ['level' => 'warning', 'text' => $this->language->lang('AIREPLY_NOTE_REASONING_TOKENS', $max_output)];
		}

		if (!$provider->supports_temperature($model_id))
		{
			$notes[] = ['level' => 'info', 'text' => $this->language->lang('AIREPLY_NOTE_NO_TEMPERATURE')];
		}

		if ($row && (int) $row['input_limit'] > 0)
		{
			$needed = $profile->chars_to_tokens($context_chars);

			if ($needed > (int) $row['input_limit'])
			{
				$notes[] = ['level' => 'error', 'text' => $this->language->lang('AIREPLY_NOTE_CONTEXT_TOO_BIG', $needed, (int) $row['input_limit'])];
			}
		}

		if ($price === null)
		{
			$notes[] = ['level' => 'warning', 'text' => $this->language->lang('AIREPLY_NOTE_NO_PRICE', $profile->get_pricing_url($provider_id))];
		}
		else if ($price['source'] === 'seed')
		{
			$notes[] = ['level' => 'info', 'text' => $this->language->lang('AIREPLY_NOTE_SEED_PRICE', \salvocortesiano\aireply\provider\model_profile::SEED_DATE, $profile->get_pricing_url($provider_id))];
		}

		if (!empty($row['is_new']))
		{
			$notes[] = ['level' => 'info', 'text' => $this->language->lang('AIREPLY_NOTE_NEW_MODEL')];
		}

		if ($described['tier'] === 'premium')
		{
			$notes[] = ['level' => 'warning', 'text' => $this->language->lang('AIREPLY_NOTE_PREMIUM_TIER')];
		}

		return [
			'success'     => true,
			'model'       => $model_id,
			'title'       => ($row && $row['display_name'] !== '') ? $row['display_name'] : $model_id,
			'summary'     => $this->language->lang($described['notes_key']),
			'tier'        => $described['tier'],
			'recommended' => (bool) $described['recommended'],
			'limits'      => [
				'input'  => $row ? (int) $row['input_limit'] : 0,
				'output' => $row ? (int) $row['output_limit'] : 0,
			],
			'price' => $price === null ? null : [
				'in_fmt'  => $this->usage->format_cost($price['in']),
				'out_fmt' => $this->usage->format_cost($price['out']),
				'source'  => $price['source'],
			],
			'estimate' => [
				'tokens_in'  => $estimate['tokens_in'],
				'tokens_out' => $estimate['tokens_out'],
				'reply'      => $this->usage->format_cost($estimate['cost']),
				'day'        => $this->usage->format_cost($estimate['cost_day']),
				'month'      => $this->usage->format_cost($estimate['cost_month']),
				'known'      => $estimate['cost'] !== null,
			],
			'quota' => [
				'rate_limit'   => $this->providers->get_rate_limit($provider_id),
				'has_snapshot' => !empty($this->providers->get_rate_limit($provider_id)),
				'spent_month'  => $this->usage->format_cost($month['cost']),
				'tokens_month' => $month['prompt_tokens'] + $month['completion_tokens'],
				'jobs_month'   => $month['jobs'],
				'budget'       => $budget['budget'] > 0 ? $this->usage->format_cost($budget['budget']) : '',
				'budget_pct'   => $budget['percent'],
				'quota_errors' => $this->usage->count_quota_errors(time() - (7 * 86400)),
				'disclaimer'   => $this->language->lang('AIREPLY_QUOTA_DISCLAIMER'),
			],
			'notes' => $notes,
		];
	}

	/**
	 * L'elenco modelli è bloccato: verifica se almeno la generazione funziona
	 * e restituisce un messaggio che dice cosa fare, non solo cosa è andato male.
	 */
	protected function explain_blocked_model_list(string $provider_id, string $stored_key, string $raw_error): array
	{
		$api_key = $this->keys->resolve($stored_key);
		$model = trim($this->request->variable('model', ''));

		if ($model === '')
		{
			$model = $this->providers->get($provider_id)->get_default_model();
		}

		$check = $this->providers->get($provider_id)->test_connection($api_key, $this->request->variable('base_url', ''), $model);

		if ($check->success)
		{
			// La chiave funziona: il problema è solo l'elenco.
			return [
				'success' => false,
				'blocked' => true,
				'code'    => ai_result::ERR_METHOD_BLOCKED,
				'message' => $this->language->lang('AIREPLY_MODELS_BLOCKED_OK', $model),
				'hint'    => $this->language->lang('AIREPLY_ERR_METHOD_BLOCKED_HINT'),
			];
		}

		/*
		 * Nemmeno la generazione passa.
		 *
		 * Se anche questa è bloccata allo stesso modo, non si tratta di una
		 * restrizione su singoli metodi: la chiave è esclusa dall'intero
		 * servizio, oppure l'API non è abilitata sul progetto. Il rimedio è
		 * completamente diverso, e dire "la chiave funziona" in questo caso
		 * manderebbe l'amministratore a cercare nel posto sbagliato.
		 */
		$fully_blocked = ($check->error_code === ai_result::ERR_METHOD_BLOCKED);

		return [
			'success' => false,
			'blocked' => true,
			'code'    => $check->error_code,
			'message' => $this->language->lang(
				$fully_blocked ? 'AIREPLY_SERVICE_BLOCKED' : 'AIREPLY_MODELS_BLOCKED_KO',
				$check->error_message
			),
			'hint'    => $this->language->lang(
				$fully_blocked ? 'AIREPLY_ERR_SERVICE_BLOCKED_HINT' : 'AIREPLY_ERR_METHOD_BLOCKED_HINT'
			),

			// API_KEY_SERVICE_BLOCKED e SERVICE_DISABLED si assomigliano ma
			// hanno rimedi opposti: il primo è una restrizione mancante sulla
			// chiave, il secondo un'API non abilitata sul progetto.
			'extra'   => (stripos($check->error_message, 'API_KEY_SERVICE_BLOCKED') !== false
				|| stripos($raw_error, 'API_KEY_SERVICE_BLOCKED') !== false)
				? $this->language->lang('AIREPLY_ERR_KEY_SERVICE_BLOCKED')
				: '',
			'http_status' => $check->http_status,
			'diagnostic'  => $check->error_code . ' / HTTP ' . $check->http_status,
			'version'     => (string) $this->config['aireply_version'],
		];
	}

	/**
	 * Chiave da usare per le azioni AJAX.
	 *
	 * Il campo del form arriva vuoto quando l'admin non l'ha toccato: in quel
	 * caso si recupera quella salvata sul bot in modifica, così il test
	 * funziona anche senza reinserire la chiave.
	 */
	protected function resolve_form_key(string $provider_id): string
	{
		$from_form = trim($this->request->variable('api_key', '', true));

		if ($from_form !== '')
		{
			return $from_form;
		}

		$bot_id = $this->request->variable('bot_id', 0);

		if ($bot_id === 0)
		{
			return '';
		}

		$sql = 'SELECT api_key, provider FROM ' . $this->bots_table . ' WHERE bot_id = ' . (int) $bot_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row || ($provider_id !== '' && $row['provider'] !== $provider_id))
		{
			// La chiave salvata appartiene a un altro provider: usarla
			// significherebbe mandare una chiave OpenAI a Google.
			return '';
		}

		return (string) $row['api_key'];
	}

	protected function build_model_list(string $provider_id): array
	{
		$list = [];

		foreach ($this->providers->get_cached_models($provider_id) as $row)
		{
			$list[] = [
				'id'          => $row['model_id'],
				'label'       => $row['display_name'] !== '' ? $row['display_name'] : $row['model_id'],
				'reasoning'   => !empty($row['is_reasoning']),
				'recommended' => !empty($row['is_recommended']),
				'is_new'      => !empty($row['is_new']),
			];
		}

		return $list;
	}

	/**
	 * Errore AJAX con la diagnostica in chiaro.
	 *
	 * Il codice interno e lo stato HTTP vengono mostrati nell'interfaccia di
	 * proposito: senza, l'unica cosa che si vede è il messaggio del provider,
	 * e non c'è modo di capire se l'estensione lo abbia classificato bene.
	 * Con "[method_blocked / HTTP 403]" in coda al messaggio, si vede subito
	 * se il ripiego doveva scattare e non è scattato.
	 */
	protected function error(string $message, string $code = '', int $http_status = 0): array
	{
		$diagnostic = [];

		if ($code !== '')
		{
			$diagnostic[] = $code;
		}

		if ($http_status > 0)
		{
			$diagnostic[] = 'HTTP ' . $http_status;
		}

		return [
			'success'     => false,
			'message'     => $message,
			'code'        => $code,
			'http_status' => $http_status,
			'diagnostic'  => empty($diagnostic) ? '' : implode(' / ', $diagnostic),
			'version'     => (string) $this->config['aireply_version'],
		];
	}
}
