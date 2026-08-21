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
use salvocortesiano\aireply\worker\job_worker;

/**
 * Elabora la coda dei job.
 *
 * Questa è la sostituzione del meccanismo asincrono di AI Labs, che faceva una
 * richiesta HTTP del board verso sé stesso inoltrando i cookie di sessione
 * dell'utente e richiedeva di disattivare la validazione IP di sessione.
 *
 * Attenzione al cron interno di phpBB: si attiva sulle visualizzazioni di
 * pagina, quindi su un forum poco frequentato le risposte possono tardare.
 * Per un comportamento prevedibile serve un cron di sistema che esegua ogni
 * due minuti il comando "cron:run" di phpbbcli (vedi il file LEGGIMI per la
 * riga di crontab completa, che qui non si può riportare perché contiene una
 * sequenza di caratteri che chiuderebbe questo commento), e la disattivazione
 * del cron via page view in ACP.
 */
class process_queue extends \phpbb\cron\task\base
{
	/** @var config */
	protected $config;

	/** @var job_queue */
	protected $queue;

	/** @var job_worker */
	protected $worker;

	public function __construct(config $config, job_queue $queue, job_worker $worker)
	{
		$this->config = $config;
		$this->queue = $queue;
		$this->worker = $worker;
	}

	public function run()
	{
		// Job rimasti appesi perché un worker precedente è morto a metà
		// (timeout PHP, riavvio del server): tornano in coda.
		$this->queue->recover_stale();

		$batch = max(1, (int) $this->config['aireply_batch_size']);
		$jobs = $this->queue->claim($batch);

		foreach ($jobs as $job)
		{
			try
			{
				$this->worker->execute($job);
			}
			catch (\Throwable $e)
			{
				/*
				 * Rete di sicurezza. Un'eccezione non prevista non deve
				 * interrompere il ciclo: gli altri job del lotto hanno diritto
				 * di essere elaborati, e soprattutto il job non deve restare
				 * "running" per sempre.
				 */
				$this->queue->fail(
					$job,
					'exception',
					get_class($e) . ': ' . $e->getMessage(),
					false
				);
			}
		}

		$this->config->set('aireply_cron_last_run', time(), false);
	}

	public function is_runnable()
	{
		return !empty($this->config['aireply_enabled']);
	}

	/**
	 * Pavimento fra due esecuzioni consecutive.
	 *
	 * Non è la frequenza a cui il worker gira, è il minimo intervallo sotto il
	 * quale non scende mai, per non interrogare il database a ogni singola
	 * visualizzazione di pagina su una board affollata.
	 */
	public const MIN_INTERVAL = 10;

	/**
	 * Il worker deve girare adesso?
	 *
	 * Prima la decisione era un timer cieco: "sono passati N secondi?". Su una
	 * board piccola questo significava che una risposta poteva restare in coda
	 * minuti, perché il cron di phpBB non ha un timer proprio — scatta quando
	 * qualcuno carica una pagina — e trovava quasi sempre l'intervallo non
	 * ancora scaduto.
	 *
	 * Ora la domanda è quella giusta: c'è un job pronto? La pagina che l'utente
	 * vede subito dopo aver inviato il post fa scattare il cron, il worker trova
	 * il lavoro e lo esegue. Il timer lungo resta solo per il caso in cui non ci
	 * sia nulla da fare, dove serve unicamente a recuperare i job appesi.
	 *
	 * L'ordine dei controlli conta: il pavimento si verifica per primo perché
	 * non costa nulla, e impedisce che la query venga eseguita più di una volta
	 * ogni dieci secondi.
	 */
	public function should_run()
	{
		$last = (int) $this->config['aireply_cron_last_run'];
		$now = time();

		if (($last + self::MIN_INTERVAL) > $now)
		{
			return false;
		}

		if ($this->queue->has_due_jobs())
		{
			return true;
		}

		// Nessun lavoro pronto: si torna al ritmo lento, che serve solo a
		// rimettere in coda i job rimasti appesi da un worker morto a metà.
		$interval = max(self::MIN_INTERVAL, (int) $this->config['aireply_cron_interval']);

		return ($last + $interval) < $now;
	}
}
