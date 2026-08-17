<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\queue;

use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\language\language;
use salvocortesiano\aireply\bot\bot;
use salvocortesiano\aireply\bot\bot_repository;
use salvocortesiano\aireply\bot\forum_binding;

/**
 * Decide se un post merita una risposta.
 *
 * Tutti i controlli stanno qui, in un solo posto, perché vengono eseguiti due
 * volte: all'accodamento (per non sprecare righe in tabella) e prima della
 * chiamata API (perché fra i due momenti possono passare minuti e la
 * configurazione può essere cambiata).
 *
 * Ogni metodo restituisce una stringa vuota se il controllo passa, altrimenti
 * il motivo del rifiuto, che finisce nel log del job.
 */
class gatekeeper
{
	/** Lunghezza minima di un post perché valga la pena rispondere */
	public const MIN_REQUEST_CHARS = 3;

	/** Massimo di job innescabili da uno stesso utente in un'ora */
	public const MAX_JOBS_PER_POSTER_HOUR = 12;

	/** @var config */
	protected $config;

	/** @var language */
	protected $language;

	/** @var auth */
	protected $auth;

	/** @var job_queue */
	protected $queue;

	/** @var bot_repository */
	protected $bots;

	public function __construct(config $config, auth $auth, language $language, job_queue $queue, bot_repository $bots)
	{
		$this->config = $config;
		$this->language = $language;

		// I motivi di blocco finiscono nel registro dell'ACP: vanno tradotti.
		// Il worker può girare da cron, dove core.user_setup non è passato.
		$this->language->add_lang('aireply_log', 'salvocortesiano/aireply');
		$this->auth = $auth;
		$this->queue = $queue;
		$this->bots = $bots;
	}

	/**
	 * L'estensione è operativa in generale?
	 */
	public function is_globally_enabled(): bool
	{
		return !empty($this->config['aireply_enabled']);
	}

	/**
	 * Controlli che dipendono da chi ha scritto, non da quale bot risponde.
	 *
	 * @return string motivo del rifiuto, o stringa vuota
	 */
	public function check_poster(int $poster_id, string $request_text): string
	{
		if (!$this->is_globally_enabled())
		{
			return $this->language->lang('AIREPLY_BLOCK_GLOBAL_OFF');
		}

		/*
		 * Prima linea anti-loop. La seconda è strutturale: il bot pubblica con
		 * submit_post(), che non fa scattare core.posting_modify_submit_post_after,
		 * quindi il listener non vedrà mai il post del bot. Ma su un meccanismo
		 * che spende denaro a ogni giro, un controllo esplicito costa una query
		 * in cache e vale la tranquillità.
		 */
		if ($this->bots->is_bot_user($poster_id))
		{
			return $this->language->lang('AIREPLY_BLOCK_BOT_POST');
		}

		if (!$this->auth->acl_get('u_aireply_trigger'))
		{
			return $this->language->lang('AIREPLY_BLOCK_NO_PERMISSION');
		}

		if (mb_strlen(trim($request_text)) < self::MIN_REQUEST_CHARS)
		{
			return $this->language->lang('AIREPLY_BLOCK_TOO_SHORT');
		}

		if ($this->queue->count_recent_by_poster($poster_id) >= self::MAX_JOBS_PER_POSTER_HOUR)
		{
			return $this->language->lang('AIREPLY_BLOCK_POSTER_RATE');
		}

		return '';
	}

	/**
	 * Controlli che dipendono dalla coppia bot/forum.
	 *
	 * @return string motivo del rifiuto, o stringa vuota
	 */
	public function check_binding(bot $bot, forum_binding $binding, string $trigger, int $post_id, int $topic_id): string
	{
		if (!$bot->enabled)
		{
			return $this->language->lang('AIREPLY_BLOCK_BOT_DISABLED');
		}

		if (!$binding->enabled)
		{
			return $this->language->lang('AIREPLY_BLOCK_BOT_DISABLED_FORUM');
		}

		if (!$binding->accepts($trigger))
		{
			return $this->language->lang('AIREPLY_BLOCK_TRIGGER_OFF', $trigger);
		}

		if ($bot->provider === '' || $bot->model === '')
		{
			return $this->language->lang('AIREPLY_BLOCK_NO_PROVIDER');
		}

		if ($this->queue->exists_for_post($post_id, $bot->bot_id))
		{
			// Succede quando l'utente modifica un post già elaborato.
			return $this->language->lang('AIREPLY_BLOCK_JOB_EXISTS');
		}

		$cap_reason = $this->check_daily_cap($bot, $binding);

		if ($cap_reason !== '')
		{
			return $cap_reason;
		}

		return $this->check_cooldown($bot, $binding, $trigger, $topic_id);
	}

	/**
	 * Tetto giornaliero per bot e forum.
	 */
	public function check_daily_cap(bot $bot, forum_binding $binding): string
	{
		if ($binding->daily_cap <= 0)
		{
			return '';
		}

		$used = $this->queue->count_today($bot->bot_id, $binding->forum_id);

		if ($used >= $binding->daily_cap)
		{
			return $this->language->lang('AIREPLY_BLOCK_DAILY_CAP', $used, $binding->daily_cap);
		}

		return '';
	}

	/**
	 * Intervallo minimo fra due interventi del bot nello stesso topic.
	 *
	 * Serve soprattutto quando `on_reply` è attivo: senza cooldown, due utenti
	 * che conversano si ritroverebbero il bot in mezzo a ogni battuta.
	 *
	 * Una menzione esplicita ignora il cooldown: se qualcuno chiama il bot per
	 * nome, farlo tacere sarebbe scortese oltre che poco comprensibile.
	 */
	public function check_cooldown(bot $bot, forum_binding $binding, string $trigger, int $topic_id): string
	{
		if ($binding->cooldown_seconds <= 0 || $trigger === forum_binding::TRIGGER_MENTION)
		{
			return '';
		}

		$last = $this->queue->last_activity_in_topic($bot->bot_id, $topic_id);

		if ($last === 0)
		{
			return '';
		}

		$elapsed = time() - $last;

		if ($elapsed < $binding->cooldown_seconds)
		{
			return $this->language->lang('AIREPLY_BLOCK_COOLDOWN', $binding->cooldown_seconds - $elapsed);
		}

		return '';
	}

	/**
	 * Ricontrollo prima della chiamata API.
	 *
	 * Fra l'accodamento e l'esecuzione possono passare minuti: nel frattempo
	 * l'admin può aver disattivato il bot, o il tetto giornaliero può essere
	 * stato consumato da altri job della stessa ondata.
	 */
	public function check_before_execution(bot $bot, ?forum_binding $binding): string
	{
		if (!$this->is_globally_enabled())
		{
			return $this->language->lang('AIREPLY_BLOCK_GLOBAL_OFF_LATER');
		}

		if (!$bot->enabled)
		{
			return $this->language->lang('AIREPLY_BLOCK_BOT_DISABLED_LATER');
		}

		if ($binding === null || !$binding->enabled)
		{
			return $this->language->lang('AIREPLY_BLOCK_NO_BINDING');
		}

		return $this->check_daily_cap($bot, $binding);
	}
}
