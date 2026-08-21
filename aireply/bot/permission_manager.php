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
 * Verifica e concede al bot i permessi che gli servono per lavorare.
 *
 * ── Perché serve ──────────────────────────────────────────────────────────
 *
 * Un bot senza `f_reply` nel forum assegnato fallisce in modo silenzioso:
 * submit_post() non pubblica nulla e nessun errore raggiunge l'amministratore.
 * Un bot senza `u_can_mention` scrive la menzione ma la notifica non parte.
 *
 * Sono due permessi che si dimenticano facilmente, perché appartengono al bot
 * e non a chi lo configura, e la loro assenza non produce un messaggio ma un
 * silenzio. Da qui la scelta di controllarli e concederli dalla stessa pagina
 * in cui il bot viene creato.
 *
 * ── Perché a livello utente e non di gruppo ───────────────────────────────
 *
 * I permessi vengono assegnati al singolo utente e non al gruppo Registrati:
 * concedere `u_can_mention` a tutti per far funzionare un bot sarebbe un
 * effetto collaterale che nessuno si aspetta da questa pagina.
 */
class permission_manager
{
	/** Permesso richiesto da Simple mentions per inviare le notifiche */
	public const MENTION_PERMISSION = 'u_can_mention';

	/** Permessi minimi per pubblicare una risposta in un forum */
	public const FORUM_PERMISSIONS = ['f_read', 'f_post', 'f_reply'];

	/** @var driver_interface */
	protected $db;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * @var \auth_admin|null
	 *
	 * Namespace globale, non \phpbb\auth\auth_admin: è una delle poche classi
	 * di phpBB rimaste fuori dai namespace, definita in includes/acp/auth.php
	 * come `class auth_admin extends \phpbb\auth\auth`.
	 */
	protected $auth_admin = null;

	public function __construct(driver_interface $db, string $root_path, string $php_ext)
	{
		$this->db = $db;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Cosa manca a questo bot per funzionare.
	 *
	 * @param  int[] $forum_ids forum in cui il bot è assegnato
	 * @return array{mention: bool, forums: int[], mention_needed: bool}
	 *         `forums` elenca i forum in cui manca almeno un permesso
	 */
	public function audit(int $user_id, array $forum_ids, bool $mention_needed): array
	{
		$auth = $this->auth_for($user_id);

		$missing_forums = [];

		foreach ($forum_ids as $forum_id)
		{
			foreach (self::FORUM_PERMISSIONS as $option)
			{
				if (!$auth->acl_get($option, $forum_id))
				{
					$missing_forums[] = (int) $forum_id;
					break;
				}
			}
		}

		return [
			'mention'        => !$mention_needed || (bool) $auth->acl_get(self::MENTION_PERMISSION),
			'mention_needed' => $mention_needed,
			'forums'         => $missing_forums,
		];
	}

	/**
	 * Concede i permessi mancanti.
	 *
	 * @param  int[] $forum_ids
	 * @return array{granted_mention: bool, granted_forums: int[]}
	 */
	public function grant(int $user_id, array $forum_ids, bool $mention_needed): array
	{
		$this->load_admin();

		$audit = $this->audit($user_id, $forum_ids, $mention_needed);

		$granted_mention = false;

		if ($mention_needed && !$audit['mention'] && $this->permission_exists(self::MENTION_PERMISSION))
		{
			// forum_id 0 = permesso globale, non legato a un forum.
			$this->auth_admin->acl_set('user', 0, $user_id, [self::MENTION_PERMISSION => ACL_YES]);
			$granted_mention = true;
		}

		foreach ($audit['forums'] as $forum_id)
		{
			$settings = [];

			foreach (self::FORUM_PERMISSIONS as $option)
			{
				$settings[$option] = ACL_YES;
			}

			$this->auth_admin->acl_set('user', (int) $forum_id, $user_id, $settings);
		}

		return [
			'granted_mention' => $granted_mention,
			'granted_forums'  => $audit['forums'],
		];
	}

	/**
	 * Il permesso esiste su questa board?
	 *
	 * `u_can_mention` appartiene a Simple mentions: se quell'estensione non è
	 * installata, il permesso non esiste e tentare di assegnarlo produrrebbe una
	 * riga inutile nella tabella delle ACL.
	 */
	public function permission_exists(string $option): bool
	{
		$sql = 'SELECT auth_option_id FROM ' . ACL_OPTIONS_TABLE . "
			WHERE auth_option = '" . $this->db->sql_escape($option) . "'";

		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return !empty($row);
	}

	/**
	 * Forum in cui questo bot è assegnato.
	 *
	 * @return int[]
	 */
	public function get_bot_forums(int $bot_id, string $forums_table): array
	{
		$sql = 'SELECT forum_id FROM ' . $forums_table . '
			WHERE bot_id = ' . (int) $bot_id . '
				AND enabled = 1';

		$result = $this->db->sql_query($sql);

		$ids = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['forum_id'];
		}

		$this->db->sql_freeresult($result);

		return $ids;
	}

	/**
	 * Istanza di auth con l'ACL di quell'utente.
	 *
	 * Deliberatamente separata da quella globale: questo servizio viene usato
	 * dall'ACP, dove alterare l'ACL corrente avrebbe conseguenze ben oltre il
	 * controllo che interessa.
	 */
	protected function auth_for(int $user_id): \phpbb\auth\auth
	{
		$sql = 'SELECT * FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int) $user_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$auth = new \phpbb\auth\auth();

		if ($row)
		{
			$auth->acl($row);
		}

		return $auth;
	}

	protected function load_admin(): void
	{
		if ($this->auth_admin !== null)
		{
			return;
		}

		if (!class_exists('auth_admin'))
		{
			include $this->root_path . 'includes/acp/auth.' . $this->php_ext;
		}

		if (!class_exists('auth_admin'))
		{
			// Se anche dopo l'include la classe non c'è, meglio dirlo che
			// morire con un fatale che l'amministratore vede come errore 500.
			throw new \RuntimeException('auth_admin non disponibile: impossibile assegnare i permessi.');
		}

		$this->auth_admin = new \auth_admin();
	}
}
