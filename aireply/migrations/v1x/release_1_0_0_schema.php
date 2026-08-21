<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\migrations\v1x;

class release_1_0_0_schema extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function effectively_installed()
	{
		return isset($this->config['aireply_version'])
			&& version_compare($this->config['aireply_version'], '1.0.0', '>=');
	}

	public function update_schema()
	{
		return [
			'add_tables' => [

				/*
				 * Un bot = un utente phpBB reale + una configurazione di provider.
				 * Le impostazioni per-forum stanno in una tabella separata: è ciò che
				 * permette "OpenAI nel forum A, Gemini nel forum B" con una sola query.
				 */
				$this->table_prefix . 'aireply_bots' => [
					'COLUMNS' => [
						'bot_id'              => ['UINT', null, 'auto_increment'],
						'user_id'             => ['UINT', 0],			// utente phpBB che "è" il bot
						'bot_name'            => ['VCHAR:100', ''],		// etichetta interna per l'ACP
						'provider'            => ['VCHAR:32', ''],		// openai | gemini
						'model'               => ['VCHAR:128', ''],
						'api_key'             => ['VCHAR:255', ''],		// letterale, oppure "env:NOME" / "const:NOME"
						'base_url'            => ['VCHAR:255', ''],		// vuoto = endpoint predefinito del provider
						'system_prompt'       => ['TEXT_UNI', ''],
						// DECIMAL senza suffisso = decimal(5,2). Attenzione: 'DECIMAL:2'
						// darebbe decimal(2,2), cioè massimo 0.99, e la temperatura
						// arriva a 2.0.
						'temperature'         => ['DECIMAL', 1.0],
						'max_output_tokens'   => ['UINT', 800],
						'thinking_budget'     => ['INT:11', -1],		// -1 = lascia decidere al modello (solo Gemini)
						/*
						 * Due tetti sul contesto, e il secondo vince sempre.
						 * context_posts è quello che l'admin ragiona ("ricorda
						 * gli ultimi 50 messaggi"); context_max_chars è quello
						 * che protegge davvero la spesa, perché 50 post lunghi
						 * e 50 post brevi costano in modo molto diverso.
						 */
						'context_posts'       => ['UINT:4', 6],			// 0 = nessuna memoria; max consigliato 100
						'context_max_chars'   => ['UINT', 12000],		// tetto duro: ~3000 token
						'max_post_chars'      => ['UINT', 4000],		// tetto sulla risposta pubblicata
						'reply_template'      => ['TEXT_UNI', ''],
						'request_timeout'     => ['UINT:4', 90],
						'enabled'             => ['BOOL', 0],
					],
					'PRIMARY_KEY' => 'bot_id',
					'KEYS' => [
						'aireply_bot_user' => ['UNIQUE', ['user_id']],
					],
				],

				/*
				 * Quale bot risponde in quale forum, con quali innesco e quali limiti.
				 */
				$this->table_prefix . 'aireply_forums' => [
					'COLUMNS' => [
						'forum_id'          => ['UINT', 0],
						'bot_id'            => ['UINT', 0],
						'on_new_topic'      => ['BOOL', 0],
						'on_reply'          => ['BOOL', 0],
						'on_mention'        => ['BOOL', 0],
						'delay_seconds'     => ['UINT:6', 0],
						'daily_cap'         => ['UINT:6', 50],		// 0 = illimitato
						'cooldown_seconds'  => ['UINT:6', 60],		// intervallo minimo fra due risposte nello stesso topic
						'enabled'           => ['BOOL', 1],
					],
					'PRIMARY_KEY' => ['forum_id', 'bot_id'],
				],

				/*
				 * La coda. Il listener scrive qui e basta; il worker legge da qui.
				 */
				$this->table_prefix . 'aireply_jobs' => [
					'COLUMNS' => [
						'job_id'             => ['UINT', null, 'auto_increment'],
						'bot_id'             => ['UINT', 0],
						'status'             => ['VCHAR:12', 'queued'],	// queued|running|done|failed|skipped
						'attempts'           => ['UINT:4', 0],
						'max_attempts'       => ['UINT:4', 3],
						'created_at'         => ['TIMESTAMP', 0],
						'run_after'          => ['TIMESTAMP', 0],		// abilita delay_seconds
						'locked_until'       => ['TIMESTAMP', 0],		// lock ottimistico anti doppia esecuzione
						'claim_token'        => ['VCHAR:32', ''],		// identifica il worker che ha preso il job
						'finished_at'        => ['TIMESTAMP', 0],
						'trigger_type'       => ['VCHAR:12', ''],		// topic|reply|mention
						'post_id'            => ['UINT', 0],
						'topic_id'           => ['UINT', 0],
						'forum_id'           => ['UINT', 0],
						'poster_id'          => ['UINT', 0],
						'ref'                => ['VCHAR:64', ''],		// token: DA VERIFICARE su ogni endpoint
						'request'            => ['MTEXT_UNI', ''],
						'response'           => ['MTEXT_UNI', ''],
						'prompt_tokens'      => ['UINT', 0],
						'completion_tokens'  => ['UINT', 0],
						'duration_ms'        => ['UINT', 0],
						'error_code'         => ['VCHAR:32', ''],
						'error_message'      => ['VCHAR:255', ''],
						'response_post_id'   => ['UINT', 0],
						'log'                => ['MTEXT_UNI', ''],
					],
					'PRIMARY_KEY' => 'job_id',
					'KEYS' => [
						// L'indice su cui gira la query del worker a ogni ciclo di cron.
						'aireply_job_claim'  => ['INDEX', ['status', 'run_after']],
						'aireply_job_post'   => ['INDEX', ['post_id']],
						'aireply_job_topic'  => ['INDEX', ['topic_id', 'bot_id']],
						'aireply_job_budget' => ['INDEX', ['forum_id', 'created_at']],
					],
				],

				/*
				 * Cache dell'elenco modelli scaricato dalle API.
				 * Evita di richiamare /models a ogni apertura della pagina ACP.
				 */
				$this->table_prefix . 'aireply_models' => [
					'COLUMNS' => [
						'provider'      => ['VCHAR:32', ''],
						'model_id'      => ['VCHAR:128', ''],
						'display_name'  => ['VCHAR:255', ''],
						'input_limit'   => ['UINT', 0],
						'output_limit'  => ['UINT', 0],
						'is_reasoning'  => ['BOOL', 0],
						'fetched_at'    => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => ['provider', 'model_id'],
				],
			],

			'add_columns' => [
				// Stato del job mostrato sotto il post dell'utente.
				$this->table_prefix . 'posts' => [
					'post_aireply_data' => ['TEXT_UNI', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'posts' => ['post_aireply_data'],
			],
			'drop_tables' => [
				$this->table_prefix . 'aireply_bots',
				$this->table_prefix . 'aireply_forums',
				$this->table_prefix . 'aireply_jobs',
				$this->table_prefix . 'aireply_models',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['aireply_version', '1.0.0']],

			// Interruttore globale: consente di fermare tutti i bot con un clic.
			['config.add', ['aireply_enabled', 0]],

			// Quanti job elabora il worker per ciclo di cron.
			['config.add', ['aireply_batch_size', 3]],

			// Ogni quanti secondi il cron task è ammesso a girare.
			['config.add', ['aireply_cron_interval', 60]],

			// Conserva il log JSON diagnostico dei job (0 = solo errori).
			['config.add', ['aireply_verbose_log', 0]],

			// Giorni di conservazione dei job completati; 0 = per sempre.
			['config.add', ['aireply_job_retention_days', 30]],

			// Timestamp dell'ultima esecuzione dei cron task.
			['config.add', ['aireply_cron_last_run', 0, true]],
			['config.add', ['aireply_prune_last_run', 0, true]],

			// Permesso: quali utenti possono innescare una risposta del bot.
			['permission.add', ['u_aireply_trigger']],
			['permission.permission_set', ['REGISTERED', 'u_aireply_trigger', 'group', true]],

			// Moduli ACP.
			['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_AIREPLY_TITLE']],
			['module.add', ['acp', 'ACP_AIREPLY_TITLE', [
				'module_basename' => '\salvocortesiano\aireply\acp\main_module',
				'modes'           => ['bots', 'forums', 'jobs', 'settings'],
			]]],
		];
	}

	public function revert_data()
	{
		return [
			['permission.remove', ['u_aireply_trigger']],
		];
	}
}
