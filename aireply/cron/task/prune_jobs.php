<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\cron\task;

use phpbb\config\config;
use salvocortesiano\aireply\queue\job_queue;

/**
 * Elimina i job conclusi più vecchi del periodo di conservazione.
 *
 * I log diagnostici sono utili ma voluminosi: su una board attiva la tabella
 * dei job crescerebbe di parecchi megabyte al mese senza questa pulizia.
 */
class prune_jobs extends \phpbb\cron\task\base
{
	/** @var config */
	protected $config;

	/** @var job_queue */
	protected $queue;

	public function __construct(config $config, job_queue $queue)
	{
		$this->config = $config;
		$this->queue = $queue;
	}

	public function run()
	{
		$this->queue->prune((int) $this->config['aireply_job_retention_days']);

		$this->config->set('aireply_prune_last_run', time(), false);
	}

	public function is_runnable()
	{
		return (int) $this->config['aireply_job_retention_days'] > 0;
	}

	public function should_run()
	{
		// Una volta al giorno è più che sufficiente.
		return ((int) ($this->config['aireply_prune_last_run'] ?? 0) + 86400) < time();
	}
}
