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

class job
{
	public const STATUS_QUEUED = 'queued';
	public const STATUS_RUNNING = 'running';
	public const STATUS_DONE = 'done';
	public const STATUS_FAILED = 'failed';
	public const STATUS_SKIPPED = 'skipped';

	/** @var int */
	public $job_id = 0;

	/** @var int */
	public $bot_id = 0;

	/** @var string */
	public $status = self::STATUS_QUEUED;

	/** @var int */
	public $attempts = 0;

	/** @var int */
	public $max_attempts = 3;

	/** @var int */
	public $created_at = 0;

	/** @var int */
	public $run_after = 0;

	/** @var string topic|reply|mention */
	public $trigger_type = '';

	/** @var int Post che ha innescato il job */
	public $post_id = 0;

	/** @var int */
	public $topic_id = 0;

	/** @var int */
	public $forum_id = 0;

	/** @var int Autore del post che ha innescato il job */
	public $poster_id = 0;

	/** @var string Token da verificare su ogni endpoint che innesca una chiamata a pagamento */
	public $ref = '';

	/** @var string */
	public $claim_token = '';

	/** @var string Testo del post, già ripulito dal BBCode */
	public $request = '';

	public static function from_row(array $row): self
	{
		$job = new self();

		$job->job_id       = (int) $row['job_id'];
		$job->bot_id       = (int) $row['bot_id'];
		$job->status       = (string) $row['status'];
		$job->attempts     = (int) $row['attempts'];
		$job->max_attempts = (int) $row['max_attempts'];
		$job->created_at   = (int) $row['created_at'];
		$job->run_after    = (int) $row['run_after'];
		$job->trigger_type = (string) $row['trigger_type'];
		$job->post_id      = (int) $row['post_id'];
		$job->topic_id     = (int) $row['topic_id'];
		$job->forum_id     = (int) $row['forum_id'];
		$job->poster_id    = (int) $row['poster_id'];
		$job->ref          = (string) $row['ref'];
		$job->claim_token  = (string) ($row['claim_token'] ?? '');
		$job->request      = (string) $row['request'];

		return $job;
	}

	/**
	 * Ha senso rimettere in coda questo job dopo un fallimento?
	 */
	public function has_attempts_left(): bool
	{
		return $this->attempts < $this->max_attempts;
	}
}
