<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\bot;

use phpbb\db\driver\driver_interface;

/**
 * Accesso in lettura alle tabelle dei bot e dei binding.
 *
 * Il listener gira a ogni invio di post: la cache di richiesta evita di
 * ripetere le stesse due query quando più bot insistono sullo stesso forum.
 */
class bot_repository
{
	/** @var driver_interface */
	protected $db;

	/** @var string */
	protected $bots_table;

	/** @var string */
	protected $forums_table;

	/** @var array<int, array{bot: bot, binding: forum_binding}[]> */
	protected $forum_cache = [];

	/** @var int[]|null Elenco degli user_id che appartengono a un bot */
	protected $bot_user_ids = null;

	public function __construct(driver_interface $db, string $bots_table, string $forums_table)
	{
		$this->db = $db;
		$this->bots_table = $bots_table;
		$this->forums_table = $forums_table;
	}

	/**
	 * Bot attivi configurati per un forum, con le rispettive regole.
	 *
	 * @return array<int, array{bot: bot, binding: forum_binding}>
	 */
	public function get_for_forum(int $forum_id): array
	{
		if (isset($this->forum_cache[$forum_id]))
		{
			return $this->forum_cache[$forum_id];
		}

		$sql_array = [
			'SELECT'    => 'b.*, f.forum_id, f.on_new_topic, f.on_reply, f.on_mention, '
						 . 'f.delay_seconds, f.daily_cap, f.cooldown_seconds, f.enabled AS binding_enabled, '
						 . 'u.username, u.user_type',
			'FROM'      => [$this->forums_table => 'f'],
			'LEFT_JOIN' => [
				[
					'FROM' => [$this->bots_table => 'b'],
					'ON'   => 'b.bot_id = f.bot_id',
				],
				[
					'FROM' => [USERS_TABLE => 'u'],
					'ON'   => 'u.user_id = b.user_id',
				],
			],
			'WHERE'     => 'f.forum_id = ' . (int) $forum_id . '
				AND f.enabled = 1
				AND b.enabled = 1',
		];

		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query($sql);

		$entries = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			// Un bot il cui utente phpBB è stato cancellato non deve far
			// esplodere nulla: lo saltiamo e basta.
			if (empty($row['user_id']) || $row['username'] === null)
			{
				continue;
			}

			$row['enabled'] = 1;
			$binding_row = $row;
			$binding_row['enabled'] = $row['binding_enabled'];

			$entries[] = [
				'bot'     => bot::from_row($row),
				'binding' => forum_binding::from_row($binding_row),
			];
		}

		$this->db->sql_freeresult($result);

		$this->forum_cache[$forum_id] = $entries;

		return $entries;
	}

	/**
	 * Un singolo bot per id, senza filtri sullo stato.
	 * Usato dal worker, che lavora su job già accodati.
	 */
	public function get_by_id(int $bot_id): ?bot
	{
		$sql_array = [
			'SELECT'    => 'b.*, u.username',
			'FROM'      => [$this->bots_table => 'b'],
			'LEFT_JOIN' => [
				[
					'FROM' => [USERS_TABLE => 'u'],
					'ON'   => 'u.user_id = b.user_id',
				],
			],
			'WHERE'     => 'b.bot_id = ' . (int) $bot_id,
		];

		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row || $row['username'] === null)
		{
			return null;
		}

		return bot::from_row($row);
	}

	/**
	 * Le regole di un bot in un forum specifico.
	 */
	public function get_binding(int $bot_id, int $forum_id): ?forum_binding
	{
		$sql = 'SELECT * FROM ' . $this->forums_table . '
			WHERE bot_id = ' . (int) $bot_id . '
				AND forum_id = ' . (int) $forum_id;

		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ? forum_binding::from_row($row) : null;
	}

	/**
	 * Tutti gli user_id che appartengono a un bot.
	 *
	 * È la prima linea della protezione anti-loop: se chi ha scritto il post è
	 * un bot, non si accoda nulla. La seconda linea è strutturale — submit_post()
	 * non fa scattare core.posting_modify_submit_post_after — ma su una cosa
	 * che può svuotare un conto corrente due controlli sono meglio di uno.
	 *
	 * @return int[]
	 */
	public function get_bot_user_ids(): array
	{
		if ($this->bot_user_ids !== null)
		{
			return $this->bot_user_ids;
		}

		$sql = 'SELECT user_id FROM ' . $this->bots_table . ' WHERE user_id > 0';
		$result = $this->db->sql_query($sql);

		$ids = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['user_id'];
		}

		$this->db->sql_freeresult($result);

		$this->bot_user_ids = $ids;

		return $ids;
	}

	public function is_bot_user(int $user_id): bool
	{
		return in_array($user_id, $this->get_bot_user_ids(), true);
	}

	/**
	 * Esiste almeno un forum configurato? Consente al listener di uscire
	 * immediatamente sulle board dove l'estensione è attiva ma non configurata.
	 */
	public function has_any_binding(): bool
	{
		$sql = 'SELECT 1 AS present FROM ' . $this->forums_table . ' WHERE enabled = 1';
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return !empty($row);
	}
}
