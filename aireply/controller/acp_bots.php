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
use phpbb\request\request;
use phpbb\template\template;
use phpbb\user;
use salvocortesiano\aireply\provider\key_manager;
use salvocortesiano\aireply\provider\manager as provider_manager;
use salvocortesiano\aireply\provider\provider_exception;
use salvocortesiano\aireply\usage\usage_tracker;

/**
 * Scheda "Bot" dell'ACP.
 *
 * Tre azioni AJAX alimentano la pagina senza ricaricarla:
 *   refresh_models  interroga l'API e riconcilia la cache
 *   test            verifica chiave e modello senza consumare token
 *   model_info      restituisce la guida contestuale del modello scelto
 */
class acp_bots
{
	/** @var config */
	protected $config;

	/** @var driver_interface */
	protected $db;

	/** @var language */
	protected $language;

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

	/** @var string */
	protected $bots_table;

	/** @var string */
	protected $u_action;

	public function __construct(
		config $config,
		driver_interface $db,
		language $language,
		request $request,
		template $template,
		user $user,
		provider_manager $providers,
		key_manager $keys,
		usage_tracker $usage,
		string $bots_table
	) {
		$this->config = $config;
		$this->db = $db;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->providers = $providers;
		$this->keys = $keys;
		$this->usage = $usage;
		$this->bots_table = $bots_table;
	}

	public function set_action(string $u_action): void
	{
		$this->u_action = $u_action;
	}

	// ------------------------------------------------------------------
	// Azioni AJAX
	// ------------------------------------------------------------------

	/**
	 * Interroga l'API del provider e aggiorna la cache dei modelli.
	 *
	 * La chiave usata è quella inserita nel form, non quella salvata: così si
	 * possono elencare i modelli di una chiave nuova prima di salvarla.
	 */
	public function ajax_refresh_models(): array
	{
		$provider_id = $this->request->variable('provider', '');
		$stored_key = $this->resolve_form_key();
		$base_url = $this->request->variable('base_url', '');

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
			$outcome = $this->providers->refresh_models($provider_id, $stored_key, $base_url);
		}
		catch (provider_exception $e)
		{
			return $this->error($e->getMessage(), $e->get_error_code());
		}

		$message = $this->language->lang('AIREPLY_MODELS_REFRESHED', $outcome['total']);

		if (!empty($outcome['added']))
		{
			$message .= ' ' . $this->language->lang(
				'AIREPLY_MODELS_NEW_FOUND',
				count($outcome['added']),
				implode(', ', array_slice($outcome['added'], 0, 5))
			);
		}

		if (!empty($outcome['removed']))
		{
			$message .= ' ' . $this->language->lang(
				'AIREPLY_MODELS_REMOVED',
				implode(', ', array_slice($outcome['removed'], 0, 5))
			);
		}

		return [
			'success' => true,
			'message' => $message,
			'models'  => $this->build_model_list($provider_id),
			'added'   => $outcome['added'],
			'removed' => $outcome['removed'],
		];
	}

	/**
	 * Verifica chiave e modello. Non consuma token: elenca i modelli.
	 */
	public function ajax_test(): array
	{
		$provider_id = $this->request->variable('provider', '');
		$stored_key = $this->resolve_form_key();
		$base_url = $this->request->variable('base_url', '');
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

		if (!$this->keys->looks_plausible($provider_id, $api_key))
		{
			// Avvertenza, non blocco: i formati delle chiavi cambiano e non
			// vogliamo rifiutare una chiave valida per un controllo di forma.
			$warning = $this->language->lang('AIREPLY_WARN_KEY_FORMAT');
		}

		$provider = $this->providers->get($provider_id);
		$result = $provider->test_connection($api_key, $base_url, $model);

		if (!$result->success)
		{
			return $this->error($result->error_message, $result->error_code);
		}

		$found = (int) ($result->debug['models_found'] ?? 0);

		$message = ($model !== '')
			? $this->language->lang('AIREPLY_TEST_OK_WITH_MODEL', $model, $found)
			: $this->language->lang('AIREPLY_TEST_OK', $found);

		return [
			'success' => true,
			'message' => $message,
			'warning' => $warning ?? '',
			'source'  => $this->keys->get_source($stored_key),
		];
	}

	/**
	 * Guida contestuale: tutto ciò che si può dire onestamente su un modello.
	 */
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

		// Impostazioni correnti del form: la stima deve riflettere ciò che
		// l'admin sta configurando in questo momento, non i valori salvati.
		$context_chars = $this->request->variable('context_max_chars', 12000);
		$max_output = $this->request->variable('max_output_tokens', 800);
		$daily_cap = $this->request->variable('daily_cap', 0);

		$estimate = $this->usage->estimate_per_reply(
			$provider_id,
			$model_id,
			$context_chars,
			$max_output,
			$daily_cap
		);

		$price = $this->usage->get_price($provider_id, $model_id);
		$rate_limit = $this->providers->get_rate_limit($provider_id);
		$month = $this->usage->summarise_month();
		$budget = $this->usage->get_budget_status();

		$is_reasoning = $row ? !empty($row['is_reasoning']) : false;

		$notes = [];

		// Avvertenze specifiche, quelle che fanno perdere un pomeriggio se
		// nessuno te le dice prima.
		if ($is_reasoning || $provider->supports_thinking_budget($model_id))
		{
			$notes[] = [
				'level' => 'warning',
				'text'  => $this->language->lang('AIREPLY_NOTE_REASONING_TOKENS', $max_output),
			];
		}

		if (!$provider->supports_temperature($model_id))
		{
			$notes[] = [
				'level' => 'info',
				'text'  => $this->language->lang('AIREPLY_NOTE_NO_TEMPERATURE'),
			];
		}

		if ($row && (int) $row['input_limit'] > 0)
		{
			$needed = $profile->chars_to_tokens($context_chars);

			if ($needed > (int) $row['input_limit'])
			{
				$notes[] = [
					'level' => 'error',
					'text'  => $this->language->lang(
						'AIREPLY_NOTE_CONTEXT_TOO_BIG',
						$needed,
						(int) $row['input_limit']
					),
				];
			}
		}

		if ($price === null)
		{
			$notes[] = [
				'level' => 'warning',
				'text'  => $this->language->lang('AIREPLY_NOTE_NO_PRICE', $profile->get_pricing_url($provider_id)),
			];
		}
		else if ($price['source'] === 'seed')
		{
			$notes[] = [
				'level' => 'info',
				'text'  => $this->language->lang(
					'AIREPLY_NOTE_SEED_PRICE',
					\salvocortesiano\aireply\provider\model_profile::SEED_DATE,
					$profile->get_pricing_url($provider_id)
				),
			];
		}

		if (!empty($row['is_new']))
		{
			$notes[] = [
				'level' => 'info',
				'text'  => $this->language->lang('AIREPLY_NOTE_NEW_MODEL'),
			];
		}

		if ($described['tier'] === 'premium')
		{
			$notes[] = [
				'level' => 'warning',
				'text'  => $this->language->lang('AIREPLY_NOTE_PREMIUM_TIER'),
			];
		}

		return [
			'success' => true,
			'model'   => $model_id,
			'title'   => $row && $row['display_name'] !== '' ? $row['display_name'] : $model_id,
			'summary' => $this->language->lang($described['notes_key']),
			'tier'    => $described['tier'],
			'family'  => $described['family'],
			'recommended' => (bool) $described['recommended'],

			'limits' => [
				'input'  => $row ? (int) $row['input_limit'] : 0,
				'output' => $row ? (int) $row['output_limit'] : 0,
			],

			'price' => $price === null ? null : [
				'in'     => $price['in'],
				'out'    => $price['out'],
				'source' => $price['source'],
				'in_fmt'  => $this->usage->format_cost($price['in']),
				'out_fmt' => $this->usage->format_cost($price['out']),
			],
			'pricing_url' => $profile->get_pricing_url($provider_id),

			'estimate' => [
				'tokens_in'  => $estimate['tokens_in'],
				'tokens_out' => $estimate['tokens_out'],
				'reply'      => $this->usage->format_cost($estimate['cost']),
				'day'        => $this->usage->format_cost($estimate['cost_day']),
				'month'      => $this->usage->format_cost($estimate['cost_month']),
				'known'      => $estimate['cost'] !== null,
			],

			// Quote: quello che si può dire davvero, con l'avvertenza.
			'quota' => [
				'rate_limit'   => $rate_limit,
				'has_snapshot' => !empty($rate_limit),
				'spent_month'  => $this->usage->format_cost($month['cost']),
				'spent_known'  => $month['cost_known'],
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

	// ------------------------------------------------------------------
	// Supporto
	// ------------------------------------------------------------------

	/**
	 * Chiave da usare per le azioni AJAX.
	 *
	 * Il campo del form arriva vuoto quando l'admin non l'ha toccato — è di
	 * tipo password e non ristampiamo mai il valore salvato. In quel caso si
	 * recupera dal database il valore del bot che si sta modificando.
	 */
	protected function resolve_form_key(): string
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

		$sql = 'SELECT api_key FROM ' . $this->bots_table . '
			WHERE bot_id = ' . (int) $bot_id;

		$result = $this->db->sql_query($sql);
		$key = (string) $this->db->sql_fetchfield('api_key');
		$this->db->sql_freeresult($result);

		return $key;
	}

	/**
	 * Elenco modelli in forma adatta al menù a tendina lato client.
	 */
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
				'input'       => (int) $row['input_limit'],
				'output'      => (int) $row['output_limit'],
			];
		}

		return $list;
	}

	protected function error(string $message, string $code = ''): array
	{
		return [
			'success' => false,
			'message' => $message,
			'code'    => $code,
		];
	}
}
