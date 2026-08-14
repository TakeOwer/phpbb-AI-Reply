<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\provider;

use phpbb\config\config;
use phpbb\config\db_text;
use phpbb\db\driver\driver_interface;
use phpbb\di\service_collection;
use phpbb\language\language;

/**
 * Punto d'accesso unico ai provider, più la cache dell'elenco modelli.
 *
 * I provider si registrano da soli con il tag `aireply.provider` in
 * services.yml: questa classe non li conosce per nome.
 */
class manager
{
	/** Dopo quanto tempo l'elenco modelli in cache è considerato stantio */
	public const CACHE_TTL = 86400;

	/** Per quanto tempo un modello resta segnalato come "nuovo" */
	public const NEW_FLAG_TTL = 1209600; // 14 giorni

	/** @var service_collection */
	protected $providers;

	/** @var driver_interface */
	protected $db;

	/** @var config */
	protected $config;

	/** @var db_text */
	protected $config_text;

	/** @var language */
	protected $language;

	/** @var key_manager */
	protected $keys;

	/** @var model_profile */
	protected $profile;

	/** @var string */
	protected $models_table;

	/** @var provider_interface[]|null */
	protected $index = null;

	public function __construct(
		service_collection $providers,
		driver_interface $db,
		config $config,
		db_text $config_text,
		language $language,
		key_manager $keys,
		model_profile $profile,
		string $models_table
	) {
		$this->providers = $providers;
		$this->db = $db;
		$this->config = $config;
		$this->config_text = $config_text;
		$this->language = $language;
		$this->keys = $keys;

		$this->language->add_lang('aireply_log', 'salvocortesiano/aireply');
		$this->profile = $profile;
		$this->models_table = $models_table;
	}

	/**
	 * @return provider_interface[] indicizzati per id
	 */
	public function all(): array
	{
		if ($this->index === null)
		{
			$this->index = [];

			foreach ($this->providers as $provider)
			{
				if ($provider instanceof provider_interface)
				{
					$this->index[$provider->get_id()] = $provider;
				}
			}
		}

		return $this->index;
	}

	public function has(string $id): bool
	{
		return isset($this->all()[$id]);
	}

	/**
	 * @throws provider_exception se l'id non corrisponde a nessun provider
	 */
	public function get(string $id): provider_interface
	{
		$providers = $this->all();

		if (!isset($providers[$id]))
		{
			throw new provider_exception(
				ai_result::ERR_REQUEST,
				$this->language->lang('AIREPLY_ERR_PROVIDER_UNKNOWN', $id)
			);
		}

		return $providers[$id];
	}

	public function get_profile(): model_profile
	{
		return $this->profile;
	}

	/**
	 * Coppie id => chiave di lingua, per i menù a tendina dell'ACP.
	 */
	public function get_choices(): array
	{
		$choices = [];

		foreach ($this->all() as $id => $provider)
		{
			$choices[$id] = $provider->get_label_key();
		}

		return $choices;
	}

	// ------------------------------------------------------------------
	// Cache dei modelli
	// ------------------------------------------------------------------

	/**
	 * @return array[] righe complete della cache, ordinate per id decrescente
	 */
	public function get_cached_models(string $provider_id): array
	{
		$sql = 'SELECT * FROM ' . $this->models_table . "
			WHERE provider = '" . $this->db->sql_escape($provider_id) . "'
			ORDER BY is_recommended DESC, model_id DESC";

		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows ?: [];
	}

	/**
	 * Una singola riga di cache, o null se il modello non è noto.
	 */
	public function get_cached_model(string $provider_id, string $model_id): ?array
	{
		$sql = 'SELECT * FROM ' . $this->models_table . "
			WHERE provider = '" . $this->db->sql_escape($provider_id) . "'
				AND model_id = '" . $this->db->sql_escape($model_id) . "'";

		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	public function cache_is_stale(string $provider_id): bool
	{
		return (time() - $this->get_last_check($provider_id)) > self::CACHE_TTL;
	}

	public function get_last_check(string $provider_id): int
	{
		$data = json_decode((string) $this->config_text->get('aireply_models_last_check'), true);

		return is_array($data) ? (int) ($data[$provider_id] ?? 0) : 0;
	}

	protected function set_last_check(string $provider_id): void
	{
		$data = json_decode((string) $this->config_text->get('aireply_models_last_check'), true);
		$data = is_array($data) ? $data : [];
		$data[$provider_id] = time();

		$this->config_text->set('aireply_models_last_check', (string) json_encode($data));
	}

	/**
	 * Interroga l'API e riconcilia la cache.
	 *
	 * "Riconcilia" e non "sostituisce": i prezzi inseriti a mano dall'admin e la
	 * data di primo avvistamento devono sopravvivere all'aggiornamento,
	 * altrimenti ogni refresh cancellerebbe il lavoro di configurazione.
	 *
	 * @return array{total: int, added: string[], removed: string[]}
	 * @throws provider_exception
	 */
	public function refresh_models(string $provider_id, string $stored_key, string $base_url = ''): array
	{
		$provider = $this->get($provider_id);
		$api_key = $this->keys->resolve($stored_key);

		if ($api_key === '')
		{
			throw new provider_exception(
				ai_result::ERR_AUTH,
				$this->language->lang('AIREPLY_ERR_NO_KEY_RESOLVE')
			);
		}

		$fetched = $provider->list_models($api_key, $base_url);

		// Stato precedente, indicizzato per id.
		$existing = [];

		foreach ($this->get_cached_models($provider_id) as $row)
		{
			$existing[$row['model_id']] = $row;
		}

		$now = time();
		$first_run = empty($existing);

		$rows = [];
		$added = [];
		$seen = [];

		foreach ($fetched as $model)
		{
			$seen[] = $model->id;

			$profile = $this->profile->describe($provider_id, $model->id);
			$previous = $existing[$model->id] ?? null;

			$is_new = false;

			if ($previous === null)
			{
				// Al primo aggiornamento tutto è "nuovo": segnalarlo sarebbe
				// rumore. La novità ha senso solo rispetto a uno stato noto.
				$is_new = !$first_run;

				if ($is_new)
				{
					$added[] = $model->id;
				}
			}
			else
			{
				// Il contrassegno di novità scade da solo.
				$is_new = !empty($previous['is_new'])
					&& ($now - (int) $previous['first_seen']) < self::NEW_FLAG_TTL;
			}

			// I prezzi impostati a mano non si toccano mai.
			$price_source = (string) ($previous['price_source'] ?? '');
			$price_in = (float) ($previous['price_in'] ?? 0);
			$price_out = (float) ($previous['price_out'] ?? 0);
			$price_updated = (int) ($previous['price_updated'] ?? 0);

			if ($price_source !== 'manual')
			{
				if ($profile['seed_in'] !== null && $profile['seed_out'] !== null)
				{
					$price_in = (float) $profile['seed_in'];
					$price_out = (float) $profile['seed_out'];
					$price_source = 'seed';
					$price_updated = $price_updated ?: $now;
				}
				else
				{
					$price_in = 0;
					$price_out = 0;
					$price_source = '';
					$price_updated = 0;
				}
			}

			$rows[] = [
				'provider'       => $provider_id,
				'model_id'       => $model->id,
				'display_name'   => $model->display_name,
				'input_limit'    => (int) $model->input_limit,
				'output_limit'   => (int) $model->output_limit,
				'is_reasoning'   => (int) $model->is_reasoning,
				'fetched_at'     => $now,
				'first_seen'     => (int) ($previous['first_seen'] ?? $now),
				'is_new'         => (int) $is_new,
				'is_recommended' => (int) $profile['recommended'],
				'family'         => $profile['family'],
				'notes_key'      => $profile['notes_key'],
				'price_in'       => $price_in,
				'price_out'      => $price_out,
				'price_source'   => $price_source,
				'price_updated'  => $price_updated,
			];
		}

		$removed = array_values(array_diff(array_keys($existing), $seen));

		$this->db->sql_transaction('begin');

		try
		{
			$sql = 'DELETE FROM ' . $this->models_table . "
				WHERE provider = '" . $this->db->sql_escape($provider_id) . "'";
			$this->db->sql_query($sql);

			if (!empty($rows))
			{
				$this->db->sql_multi_insert($this->models_table, $rows);
			}

			$this->db->sql_transaction('commit');
		}
		catch (\Exception $e)
		{
			$this->db->sql_transaction('rollback');

			throw new provider_exception(
				ai_result::ERR_PARSE,
				$this->language->lang('AIREPLY_ERR_MODELS_SAVE', $e->getMessage())
			);
		}

		$this->set_last_check($provider_id);

		return [
			'total'   => count($rows),
			'added'   => $added,
			'removed' => $removed,
		];
	}

	/**
	 * Imposta a mano il prezzo di un modello. Da qui in avanti gli
	 * aggiornamenti non lo sovrascrivono più.
	 */
	public function set_price(string $provider_id, string $model_id, float $price_in, float $price_out): void
	{
		$set = [
			'price_in'      => $price_in,
			'price_out'     => $price_out,
			'price_source'  => 'manual',
			'price_updated' => time(),
		];

		$sql = 'UPDATE ' . $this->models_table . '
			SET ' . $this->db->sql_build_array('UPDATE', $set) . "
			WHERE provider = '" . $this->db->sql_escape($provider_id) . "'
				AND model_id = '" . $this->db->sql_escape($model_id) . "'";

		$this->db->sql_query($sql);
	}

	/**
	 * Modelli comparsi dall'ultimo aggiornamento.
	 *
	 * @return array[] righe complete
	 */
	public function get_new_models(string $provider_id = ''): array
	{
		$where = 'is_new = 1';

		if ($provider_id !== '')
		{
			$where .= " AND provider = '" . $this->db->sql_escape($provider_id) . "'";
		}

		$sql = 'SELECT * FROM ' . $this->models_table . '
			WHERE ' . $where . '
			ORDER BY first_seen DESC, model_id DESC';

		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows ?: [];
	}

	/**
	 * L'admin ha preso atto delle novità.
	 */
	public function dismiss_new_flags(string $provider_id = ''): void
	{
		$sql = 'UPDATE ' . $this->models_table . ' SET is_new = 0 WHERE is_new = 1';

		if ($provider_id !== '')
		{
			$sql .= " AND provider = '" . $this->db->sql_escape($provider_id) . "'";
		}

		$this->db->sql_query($sql);
	}

	/**
	 * Modelli per il menù. Se la cache è vuota si offre almeno il predefinito,
	 * così il campo non resta mai senza opzioni.
	 */
	public function get_model_choices(string $provider_id): array
	{
		$rows = $this->get_cached_models($provider_id);

		if (empty($rows))
		{
			if (!$this->has($provider_id))
			{
				return [];
			}

			$default = $this->get($provider_id)->get_default_model();

			return [$default => $default];
		}

		$choices = [];

		foreach ($rows as $row)
		{
			$label = $row['display_name'] !== '' ? $row['display_name'] : $row['model_id'];

			if ($label !== $row['model_id'])
			{
				$label .= ' (' . $row['model_id'] . ')';
			}

			if (!empty($row['is_new']))
			{
				$label = '★ ' . $label;
			}

			$choices[$row['model_id']] = $label;
		}

		return $choices;
	}

	// ------------------------------------------------------------------
	// Istantanea dei limiti di frequenza
	// ------------------------------------------------------------------

	/**
	 * Registra i limiti riportati dall'ultima risposta del provider.
	 *
	 * Questa è l'unica informazione sulle quote che le API espongono davvero:
	 * né OpenAI né Google offrono un endpoint per conoscere il credito residuo.
	 */
	public function store_rate_limit(string $provider_id, array $snapshot): void
	{
		if (empty($snapshot))
		{
			return;
		}

		$data = json_decode((string) $this->config_text->get('aireply_ratelimit_snapshot'), true);
		$data = is_array($data) ? $data : [];

		$snapshot['recorded_at'] = time();
		$data[$provider_id] = $snapshot;

		$this->config_text->set('aireply_ratelimit_snapshot', (string) json_encode($data));
	}

	public function get_rate_limit(string $provider_id): array
	{
		$data = json_decode((string) $this->config_text->get('aireply_ratelimit_snapshot'), true);

		return (is_array($data) && isset($data[$provider_id]) && is_array($data[$provider_id]))
			? $data[$provider_id]
			: [];
	}
}
