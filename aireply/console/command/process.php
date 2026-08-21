<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\console\command;

use phpbb\config\config;
use phpbb\console\command\command;
use phpbb\language\language;
use phpbb\user;
use salvocortesiano\aireply\queue\job_queue;
use salvocortesiano\aireply\worker\job_worker;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Elabora la coda da riga di comando.
 *
 *     php bin/phpbbcli.php aireply:process
 *     php bin/phpbbcli.php aireply:process --limit=1 --verbose
 *
 * Serve a due cose: far girare la coda a mano durante lo sviluppo senza
 * aspettare il cron, e diagnosticare i problemi vedendo l'esito di ogni job
 * mentre accade invece di ricostruirlo dai log.
 */
class process extends command
{
	/** @var config */
	protected $config;

	/** @var language */
	protected $language;

	/** @var job_queue */
	protected $queue;

	/** @var job_worker */
	protected $worker;

	public function __construct(user $user, config $config, language $language, job_queue $queue, job_worker $worker)
	{
		$this->config = $config;
		$this->language = $language;
		$this->queue = $queue;
		$this->worker = $worker;

		parent::__construct($user);
	}

	protected function configure()
	{
		$this
			->setName('aireply:process')
			->setDescription('Elabora i job in coda di AI Reply.')
			->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Numero massimo di job da elaborare', 5)
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Elabora anche se l\'estensione è disattivata a livello globale');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);

		if (empty($this->config['aireply_enabled']) && !$input->getOption('force'))
		{
			$io->warning('AI Reply è disattivata a livello globale. Usa --force per elaborare comunque.');

			return command::SUCCESS;
		}

		$recovered = $this->queue->recover_stale();

		if ($recovered > 0)
		{
			$io->note(sprintf('%d job rimasti appesi sono stati rimessi in coda.', $recovered));
		}

		$limit = max(1, (int) $input->getOption('limit'));
		$jobs = $this->queue->claim($limit);

		if (empty($jobs))
		{
			$io->success('Nessun job da elaborare.');

			return command::SUCCESS;
		}

		$io->title(sprintf('Elaborazione di %d job', count($jobs)));

		$tally = ['done' => 0, 'failed' => 0, 'retry' => 0, 'skipped' => 0];

		foreach ($jobs as $job)
		{
			$started = microtime(true);

			try
			{
				$outcome = $this->worker->execute($job);
			}
			catch (\Throwable $e)
			{
				$this->queue->fail($job, 'exception', get_class($e) . ': ' . $e->getMessage(), false);
				$outcome = 'failed';

				if ($output->isVerbose())
				{
					$io->text('  ' . $e->getTraceAsString());
				}
			}

			$tally[$outcome] = ($tally[$outcome] ?? 0) + 1;

			$io->text(sprintf(
				'  job #%d  bot %d  post %d  →  <comment>%s</comment>  (%d ms)',
				$job->job_id,
				$job->bot_id,
				$job->post_id,
				$outcome,
				(int) round((microtime(true) - $started) * 1000)
			));
		}

		$io->newLine();
		$io->success(sprintf(
			'Completati: %d · Falliti: %d · Da ritentare: %d · Saltati: %d',
			$tally['done'],
			$tally['failed'],
			$tally['retry'],
			$tally['skipped']
		));

		return command::SUCCESS;
	}
}
