<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\status;

use phpbb\db\driver\driver_interface;

/**
 * Legge e scrive `phpbb_posts.post_aireply_data`.
 *
 * Contiene un array JSON con lo stato dei bot invocati su quel post, ed è ciò
 * che permette di mostrare "sta pensando…" sotto il messaggio dell'utente.
 * Senza questo riscontro, i secondi di attesa sembrano un malfunzionamento.
 *
 * Differenza rispetto ad AI Labs: lì il campo veniva accresciuto per
 * concatenazione SQL a ogni cambio di stato, accumulando voci duplicate che poi
 * andavano deduplicate in lettura. Qui è sempre un JSON valido, riscritto per
 * intero, con una voce per bot.
 */
class post_status
{
	public const STATUS_QUEUED = 'queued';
	public const STATUS_RUNNING = 'running';
	public const STATUS_DONE = 'done';
	public const STATUS_FAILED = 'failed';
	public const STATUS_SKIPPED = 'skipped';

	/** @var driver_interface */
	protected $db;

	public function __construct(driver_interface $db)
	{
		$this->db = $db;
	}

	/**
	 * Sostituisce l'intero contenuto della colonna.
	 *
	 * @param array[] $entries voci con bot_id, bot_name, status, job_id
	 */
	public function write(int $post_id, array $entries): void
	{
		$payload = $this->encode($entries);

		$sql = 'UPDATE ' . POSTS_TABLE . "
			SET post_aireply_data = '" . $this->db->sql_escape($payload) . "'
			WHERE post_id = " . (int) $post_id;

		$this->db->sql_query($sql);
	}

	/**
	 * Aggiorna la voce di un singolo bot lasciando intatte le altre.
	 *
	 * Un post può aver innescato due bot diversi: quando il primo finisce, il
	 * secondo deve continuare a risultare in elaborazione.
	 */
	public function update_entry(int $post_id, int $bot_id, array $changes): void
	{
		$entries = $this->read($this->fetch_raw($post_id));

		$found = false;

		foreach ($entries as $index => $entry)
		{
			if ((int) ($entry['bot_id'] ?? 0) === $bot_id)
			{
				$entries[$index] = array_merge($entry, $changes);
				$found = true;
				break;
			}
		}

		if (!$found)
		{
			$entries[] = array_merge(['bot_id' => $bot_id], $changes);
		}

		$this->write($post_id, $entries);
	}

	/**
	 * @return array[] voci decodificate; array vuoto se il campo è vuoto o corrotto
	 */
	public function read(string $raw): array
	{
		$raw = trim($raw);

		if ($raw === '')
		{
			return [];
		}

		$decoded = json_decode($raw, true);

		if (!is_array($decoded))
		{
			return [];
		}

		$entries = [];

		foreach ($decoded as $entry)
		{
			if (is_array($entry) && isset($entry['status']))
			{
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	protected function fetch_raw(int $post_id): string
	{
		$sql = 'SELECT post_aireply_data FROM ' . POSTS_TABLE . '
			WHERE post_id = ' . (int) $post_id;

		$result = $this->db->sql_query($sql);
		$raw = (string) $this->db->sql_fetchfield('post_aireply_data');
		$this->db->sql_freeresult($result);

		return $raw;
	}

	protected function encode(array $entries): string
	{
		$payload = json_encode(array_values($entries), JSON_UNESCAPED_UNICODE);

		if ($payload === false)
		{
			return '';
		}

		// La colonna è TEXT_UNI: se per qualche motivo il contenuto eccede,
		// meglio nessun badge che una colonna troncata a metà JSON.
		return (strlen($payload) > 60000) ? '' : $payload;
	}

	/**
	 * Chiave di lingua corrispondente a uno stato.
	 */
	public function get_lang_key(string $status): string
	{
		switch ($status)
		{
			case self::STATUS_QUEUED:
				return 'AIREPLY_STATUS_QUEUED';

			case self::STATUS_RUNNING:
				return 'AIREPLY_STATUS_RUNNING';

			case self::STATUS_DONE:
				return 'AIREPLY_STATUS_DONE';

			case self::STATUS_FAILED:
				return 'AIREPLY_STATUS_FAILED';

			case self::STATUS_SKIPPED:
				return 'AIREPLY_STATUS_SKIPPED';
		}

		return 'AIREPLY_STATUS_UNKNOWN';
	}
}
