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

use phpbb\db\driver\driver_interface;
use phpbb\language\language;

/**
 * Coda dei job.
 *
 * Il listener scrive qui e finisce lì il suo lavoro; il worker legge da qui.
 * Nessuna chiamata di rete attraversa questa classe.
 */
class job_queue
{
	/**
	 * Per quanti secondi un job resta riservato al worker che l'ha preso.
	 * Deve superare il timeout più lungo possibile di una chiamata API,
	 * altrimenti un secondo worker potrebbe ripartire su un job ancora vivo.
	 */
	public const LEASE_SECONDS = 300;

	/** @var driver_interface */
	protected $db;

	/** @var language */
	protected $language;

	/** @var string */
	protected $jobs_table;

	public function __construct(driver_interface $db, language $language, string $jobs_table)
	{
		$this->db = $db;
		$this->language = $language;
		$this->jobs_table = $jobs_table;

		$this->language->add_lang('aireply_log', 'salvocortesiano/aireply');
	}

	/**
	 * Accoda un job. Restituisce l'id assegnato.
	 */
	public function enqueue(array $data): int
	{
		$now = time();

		$row = array_merge([
			'status'       => job::STATUS_QUEUED,
			'attempts'     => 0,
			'max_attempts' => 3,
			'created_at'   => $now,
			'run_after'    => $now,
			'locked_until' => 0,
			'claim_token'  => '',
			'ref'          => bin2hex(random_bytes(16)),
			'response'     => '',
			'log'          => '',
			'error_code'   => '',
			'error_message' => '',
		], $data);

		$sql = 'INSERT INTO ' . $this->jobs_table . ' ' . $this->db->sql_build_array('INSERT', $row);
		$this->db->sql_query($sql);

		return (int) $this->db->sql_nextid();
	}

	/**
	 * Prende in carico fino a $limit job pronti.
	 *
	 * Il claim avviene in tre passaggi invece che con un singolo
	 * "UPDATE ... ORDER BY ... LIMIT", che è sintassi MySQL e non funziona su
	 * PostgreSQL. La correttezza è garantita dalla guardia nella WHERE della
	 * UPDATE: se un altro worker ha già preso il job, la riga non corrisponde
	 * più e non viene aggiornata.
	 *
	 * @return job[]
	 */
	public function claim(int $limit): array
	{
		$limit = max(1, min(20, $limit));
		$now = time();

		// 1. Individua i candidati.
		$sql = 'SELECT job_id FROM ' . $this->jobs_table . "
			WHERE status = '" . $this->db->sql_escape(job::STATUS_QUEUED) . "'
				AND run_after <= " . (int) $now . '
				AND locked_until < ' . (int) $now . '
			ORDER BY run_after ASC, job_id ASC';

		$result = $this->db->sql_query_limit($sql, $limit);

		$candidates = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$candidates[] = (int) $row['job_id'];
		}

		$this->db->sql_freeresult($result);

		if (empty($candidates))
		{
			return [];
		}

		// 2. Prova a riservarli. Le condizioni ripetute nella WHERE sono ciò
		//    che rende l'operazione sicura in presenza di worker concorrenti.
		$token = bin2hex(random_bytes(16));

		$set = [
			'status'       => job::STATUS_RUNNING,
			'locked_until' => $now + self::LEASE_SECONDS,
			'claim_token'  => $token,
		];

		$sql = 'UPDATE ' . $this->jobs_table . '
			SET ' . $this->db->sql_build_array('UPDATE', $set) . '
			WHERE ' . $this->db->sql_in_set('job_id', $candidates) . "
				AND status = '" . $this->db->sql_escape(job::STATUS_QUEUED) . "'
				AND locked_until < " . (int) $now;

		$this->db->sql_query($sql);

		if (!$this->db->sql_affectedrows())
		{
			return [];
		}

		// 3. Recupera solo quelli effettivamente nostri.
		$sql = 'SELECT * FROM ' . $this->jobs_table . "
			WHERE claim_token = '" . $this->db->sql_escape($token) . "'
			ORDER BY job_id ASC";

		$result = $this->db->sql_query($sql);

		$jobs = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$jobs[] = job::from_row($row);
		}

		$this->db->sql_freeresult($result);

		// L'incremento dei tentativi avviene qui, non a fine elaborazione: se
		// il processo muore a metà (timeout PHP, riavvio del server), il
		// tentativo deve risultare comunque consumato. Altrimenti un job che
		// fa sempre andare in timeout il worker verrebbe ritentato per sempre.
		if (!empty($jobs))
		{
			$ids = array_map(static function (job $j) {
				return $j->job_id;
			}, $jobs);

			$sql = 'UPDATE ' . $this->jobs_table . '
				SET attempts = attempts + 1
				WHERE ' . $this->db->sql_in_set('job_id', $ids);
			$this->db->sql_query($sql);

			foreach ($jobs as $j)
			{
				$j->attempts++;
			}
		}

		return $jobs;
	}

	/**
	 * Rimette in coda i job rimasti appesi in stato "running" oltre la scadenza
	 * del lock: succede se il worker è morto, per timeout PHP o riavvio.
	 *
	 * @return int quanti job sono stati recuperati
	 */
	public function recover_stale(): int
	{
		$now = time();

		// Chi ha ancora tentativi torna in coda; gli altri vanno in errore,
		// altrimenti resterebbero "running" per sempre.
		$sql = 'UPDATE ' . $this->jobs_table . "
			SET status = '" . $this->db->sql_escape(job::STATUS_QUEUED) . "',
				locked_until = 0,
				claim_token = ''
			WHERE status = '" . $this->db->sql_escape(job::STATUS_RUNNING) . "'
				AND locked_until > 0
				AND locked_until < " . (int) $now . '
				AND attempts < max_attempts';

		$this->db->sql_query($sql);
		$recovered = (int) $this->db->sql_affectedrows();

		$sql = 'UPDATE ' . $this->jobs_table . "
			SET status = '" . $this->db->sql_escape(job::STATUS_FAILED) . "',
				locked_until = 0,
				claim_token = '',
				error_code = 'timeout',
				error_message = '" . $this->db->sql_escape($this->language->lang('AIREPLY_ERR_WORKER_TIMEOUT')) . "',
				finished_at = " . (int) $now . "
			WHERE status = '" . $this->db->sql_escape(job::STATUS_RUNNING) . "'
				AND locked_until > 0
				AND locked_until < " . (int) $now . '
				AND attempts >= max_attempts';

		$this->db->sql_query($sql);

		return $recovered;
	}

	/**
	 * Job completato con successo.
	 */
	public function complete(job $j, array $set): void
	{
		$this->update($j->job_id, array_merge($set, [
			'status'       => job::STATUS_DONE,
			'finished_at'  => time(),
			'locked_until' => 0,
			'claim_token'  => '',
		]));
	}

	/**
	 * Job fallito. Se l'errore è ritentabile e restano tentativi, torna in coda
	 * con un ritardo crescente; altrimenti si ferma qui.
	 */
	public function fail(job $j, string $error_code, string $error_message, bool $retryable, array $extra = [], int $retry_after = 0): bool
	{
		$will_retry = $retryable && $j->has_attempts_left();

		if ($will_retry)
		{
			// Backoff: 1 min, 5 min, 15 min. Ritentare subito un rate limit
			// significa soltanto incassare un secondo rate limit.
			$delays = [60, 300, 900];
			$delay = $retry_after > 0
				? min($retry_after, 900)
				: ($delays[min($j->attempts - 1, count($delays) - 1)] ?? 900);

			$set = array_merge($extra, [
				'status'        => job::STATUS_QUEUED,
				'run_after'     => time() + $delay,
				'locked_until'  => 0,
				'claim_token'   => '',
				'error_code'    => $error_code,
				'error_message' => $this->truncate($error_message),
			]);
		}
		else
		{
			$set = array_merge($extra, [
				'status'        => job::STATUS_FAILED,
				'finished_at'   => time(),
				'locked_until'  => 0,
				'claim_token'   => '',
				'error_code'    => $error_code,
				'error_message' => $this->truncate($error_message),
			]);
		}

		$this->update($j->job_id, $set);

		return $will_retry;
	}

	/**
	 * Job scartato senza tentare: il contesto è cambiato fra accodamento ed
	 * esecuzione (post cancellato, bot disattivato, budget esaurito).
	 */
	public function skip(job $j, string $reason): void
	{
		$this->update($j->job_id, [
			'status'        => job::STATUS_SKIPPED,
			'finished_at'   => time(),
			'locked_until'  => 0,
			'claim_token'   => '',
			'error_code'    => 'skipped',
			'error_message' => $this->truncate($reason),
		]);
	}

	public function update(int $job_id, array $set): void
	{
		$sql = 'UPDATE ' . $this->jobs_table . '
			SET ' . $this->db->sql_build_array('UPDATE', $set) . '
			WHERE job_id = ' . (int) $job_id;

		$this->db->sql_query($sql);
	}

	/**
	 * C'è almeno un job pronto per essere eseguito in questo istante?
	 *
	 * Interroga lo stesso indice usato da claim(), quindi costa quanto una
	 * ricerca su chiave. Serve al cron task per decidere se vale la pena
	 * girare, invece di affidarsi a un timer cieco.
	 */
	public function has_due_jobs(): bool
	{
		$now = time();

		$sql = 'SELECT 1 AS present FROM ' . $this->jobs_table . "
			WHERE status = '" . $this->db->sql_escape(job::STATUS_QUEUED) . "'
				AND run_after <= " . (int) $now . '
				AND locked_until < ' . (int) $now;

		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return !empty($row);
	}

	/**
	 * Esiste già un job per questa coppia post/bot?
	 * Evita il doppio accodamento quando l'utente modifica il proprio post.
	 */
	public function exists_for_post(int $post_id, int $bot_id): bool
	{
		$sql = 'SELECT 1 AS present FROM ' . $this->jobs_table . '
			WHERE post_id = ' . (int) $post_id . '
				AND bot_id = ' . (int) $bot_id;

		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return !empty($row);
	}

	/**
	 * Quante risposte ha già prodotto questo bot oggi in questo forum?
	 * I job ancora in coda contano: altrimenti un'ondata di post
	 * sfonderebbe il tetto prima che il worker faccia in tempo a girare.
	 */
	public function count_today(int $bot_id, int $forum_id): int
	{
		$since = time() - 86400;

		$sql = 'SELECT COUNT(job_id) AS total FROM ' . $this->jobs_table . '
			WHERE bot_id = ' . (int) $bot_id . '
				AND forum_id = ' . (int) $forum_id . '
				AND created_at >= ' . (int) $since . "
				AND status <> '" . $this->db->sql_escape(job::STATUS_SKIPPED) . "'";

		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $total;
	}

	/**
	 * Timestamp dell'ultimo job accodato da questo bot in questo topic.
	 * Base del cooldown.
	 */
	public function last_activity_in_topic(int $bot_id, int $topic_id): int
	{
		$sql = 'SELECT MAX(created_at) AS last_at FROM ' . $this->jobs_table . '
			WHERE bot_id = ' . (int) $bot_id . '
				AND topic_id = ' . (int) $topic_id . "
				AND status <> '" . $this->db->sql_escape(job::STATUS_SKIPPED) . "'";

		$result = $this->db->sql_query($sql);
		$last = (int) $this->db->sql_fetchfield('last_at');
		$this->db->sql_freeresult($result);

		return $last;
	}

	/**
	 * Quanti job ha innescato un utente nell'ultima ora, su tutta la board.
	 * Impedisce che una singola persona monopolizzi i bot.
	 */
	public function count_recent_by_poster(int $poster_id, int $window_seconds = 3600): int
	{
		$sql = 'SELECT COUNT(job_id) AS total FROM ' . $this->jobs_table . '
			WHERE poster_id = ' . (int) $poster_id . '
				AND created_at >= ' . (time() - $window_seconds);

		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $total;
	}

	/**
	 * Elimina i job conclusi più vecchi di N giorni.
	 */
	public function prune(int $days): int
	{
		if ($days <= 0)
		{
			return 0;
		}

		$sql = 'DELETE FROM ' . $this->jobs_table . '
			WHERE finished_at > 0
				AND finished_at < ' . (time() - ($days * 86400)) . '
				AND ' . $this->db->sql_in_set('status', [job::STATUS_DONE, job::STATUS_FAILED, job::STATUS_SKIPPED]);

		$this->db->sql_query($sql);

		return (int) $this->db->sql_affectedrows();
	}

	protected function truncate(string $text): string
	{
		return mb_substr(trim($text), 0, 250);
	}
}
