<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\worker;

use phpbb\auth\auth;
use phpbb\db\driver\driver_interface;
use phpbb\language\language;
use phpbb\user;
use salvocortesiano\aireply\bot\bot;
use salvocortesiano\aireply\queue\job;

/**
 * Pubblica la risposta come utente bot.
 *
 * Il meccanismo — sostituire temporaneamente $user->data, ricalcolare l'ACL,
 * chiamare submit_post(), ripristinare — è lo stesso di AI Labs, perché è quello
 * corretto e non ne esiste uno più pulito in phpBB 3.3. Le differenze sono nella
 * robustezza: il ripristino avviene in un blocco finally, così un'eccezione a
 * metà non lascia la sessione dell'utente con l'identità del bot.
 *
 * Nota importante: submit_post() non fa scattare core.posting_modify_submit_post_after,
 * quindi la pubblicazione della risposta non riattiva il listener. È la ragione
 * strutturale per cui non esiste un loop.
 */
class publisher
{
	/** @var driver_interface */
	protected $db;

	/** @var user */
	protected $user;

	/** @var language */
	protected $language;

	/** @var auth */
	protected $auth;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	public function __construct(driver_interface $db, user $user, auth $auth, language $language, string $root_path, string $php_ext)
	{
		$this->db = $db;
		$this->language = $language;
		$this->language->add_lang('aireply_log', 'salvocortesiano/aireply');
		$this->user = $user;
		$this->auth = $auth;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * @param  string $message testo già in BBCode
	 * @return int    id del post creato, 0 in caso di fallimento
	 * @throws \RuntimeException
	 */
	public function publish(job $j, bot $b, string $message): int
	{
		$topic = $this->fetch_topic_context($j);

		if ($topic === null)
		{
			throw new \RuntimeException($this->language->lang('AIREPLY_ERR_TOPIC_GONE'));
		}

		if (!function_exists('submit_post'))
		{
			include $this->root_path . 'includes/functions_posting.' . $this->php_ext;
		}

		$uid = $bitfield = $options = '';
		$flags = OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS;

		$stored = $message;
		generate_text_for_storage($stored, $uid, $bitfield, $options, true, true, true);

		/*
		 * generate_text_for_storage() non sempre valorizza $uid: succede quando
		 * il testo non contiene BBCode da codificare. Senza uid il post si salva
		 * ma il BBCode non viene interpretato in visualizzazione.
		 */
		if ($uid === '')
		{
			$parser = new \parse_message($message);
			$parser->parse(true, true, true);
			$uid = $parser->bbcode_uid;
		}

		$subject = $this->build_subject((string) $topic['topic_title']);

		$data = [
			'forum_id'          => $j->forum_id,
			'topic_id'          => $j->topic_id,
			'icon_id'           => false,
			'poster_id'         => $b->user_id,

			'enable_bbcode'     => true,
			'enable_smilies'    => true,
			'enable_urls'       => true,
			'enable_sig'        => true,

			'message'           => $stored,
			'message_md5'       => md5($stored),
			'post_checksum'     => md5($stored),

			'bbcode_bitfield'   => $bitfield,
			'bbcode_uid'        => $uid,

			'post_edit_locked'  => 0,
			'topic_title'       => $topic['topic_title'],
			'notify_set'        => false,
			'notify'            => true,
			'post_time'         => 0,
			'forum_name'        => $topic['forum_name'],
			'enable_indexing'   => true,
			'force_approved_state' => true,
		];

		$original_user_id = (int) $this->user->data['user_id'];
		$original_data = $this->user->data;

		try
		{
			$this->switch_to($b->user_id);

			$poll = [];
			submit_post('reply', $subject, $b->username, POST_NORMAL, $poll, $data);
		}
		finally
		{
			// Il ripristino deve avvenire anche se submit_post() esplode:
			// lasciare la sessione dell'utente con l'identità del bot sarebbe
			// il tipo di bug che non si scopre subito e fa danni seri.
			$this->user->data = $original_data;
			$this->auth->acl($this->user->data);

			unset($original_data);
		}

		unset($original_user_id);

		return (int) ($data['post_id'] ?? 0);
	}

	/**
	 * Assume l'identità del bot per la durata della pubblicazione.
	 */
	protected function switch_to(int $user_id): void
	{
		if ((int) $this->user->data['user_id'] === $user_id)
		{
			return;
		}

		$sql = 'SELECT * FROM ' . USERS_TABLE . '
			WHERE user_id = ' . (int) $user_id;

		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			throw new \RuntimeException($this->language->lang('AIREPLY_ERR_BOT_USER_GONE', $user_id));
		}

		$row['is_registered'] = true;

		$this->user->data = array_merge($this->user->data, $row);
		$this->auth->acl($this->user->data);
	}

	/**
	 * Titolo e nome forum servono a submit_post() per notifiche ed elenchi.
	 */
	protected function fetch_topic_context(job $j): ?array
	{
		$sql_array = [
			'SELECT'    => 't.topic_title, t.topic_id, f.forum_name',
			'FROM'      => [TOPICS_TABLE => 't'],
			'LEFT_JOIN' => [
				[
					'FROM' => [FORUMS_TABLE => 'f'],
					'ON'   => 'f.forum_id = t.forum_id',
				],
			],
			'WHERE'     => 't.topic_id = ' . (int) $j->topic_id,
		];

		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: null;
	}

	protected function build_subject(string $topic_title): string
	{
		$title = censor_text($topic_title);

		return (strpos($title, 'Re: ') === 0) ? $title : 'Re: ' . $title;
	}

	/**
	 * Il bot ha i permessi per pubblicare in questo forum?
	 *
	 * Se manca `f_reply`, submit_post() fallisce in silenzio e il job risulta
	 * completato senza che compaia nessun post: un caso particolarmente
	 * fastidioso da diagnosticare, quindi lo verifichiamo prima.
	 */
	public function can_post(bot $b, int $forum_id): bool
	{
		$original_data = $this->user->data;

		try
		{
			$this->switch_to($b->user_id);

			return (bool) $this->auth->acl_get('f_reply', $forum_id);
		}
		catch (\RuntimeException $e)
		{
			return false;
		}
		finally
		{
			$this->user->data = $original_data;
			$this->auth->acl($this->user->data);
		}
	}
}
