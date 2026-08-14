<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\event;

use phpbb\auth\auth;
use phpbb\controller\helper;
use phpbb\language\language;
use phpbb\user;
use salvocortesiano\aireply\bot\bot_repository;
use salvocortesiano\aireply\bot\forum_binding;
use salvocortesiano\aireply\content\text_extractor;
use salvocortesiano\aireply\queue\gatekeeper;
use salvocortesiano\aireply\queue\job_queue;
use salvocortesiano\aireply\status\post_status;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Aggancio agli eventi di phpBB.
 *
 * Nota sulla scelta dell'evento, che è la decisione più importante di questo file:
 *
 *   core.posting_modify_submit_post_after  scatta SOLO da posting.php, cioè da un
 *                                          invio tramite interfaccia utente.
 *   core.submit_post_end                   scatta a OGNI chiamata a submit_post(),
 *                                          compresa quella con cui il bot pubblica.
 *
 * Usare il secondo significherebbe che la risposta del bot fa scattare il
 * listener, che accoda un altro job, che genera un'altra risposta: loop infinito
 * al primo post, con fattura annessa. Il primo è l'unico corretto.
 *
 * Regola operativa di questo listener: nessuna chiamata di rete, nessuna
 * elaborazione pesante. Deve restare sotto i 50 ms, perché l'utente sta
 * aspettando che il suo post venga pubblicato.
 */
class listener implements EventSubscriberInterface
{
	/** @var user */
	protected $user;

	/** @var auth */
	protected $auth;

	/** @var language */
	protected $language;

	/** @var helper */
	protected $helper;

	/** @var bot_repository */
	protected $bots;

	/** @var job_queue */
	protected $queue;

	/** @var gatekeeper */
	protected $gate;

	/** @var text_extractor */
	protected $extractor;

	/** @var post_status */
	protected $status;

	public function __construct(
		user $user,
		auth $auth,
		language $language,
		helper $helper,
		bot_repository $bots,
		job_queue $queue,
		gatekeeper $gate,
		text_extractor $extractor,
		post_status $status
	) {
		$this->user = $user;
		$this->auth = $auth;
		$this->language = $language;
		$this->helper = $helper;
		$this->bots = $bots;
		$this->queue = $queue;
		$this->gate = $gate;
		$this->extractor = $extractor;
		$this->status = $status;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'                        => 'load_language',
			'core.posting_modify_submit_post_after'  => 'enqueue_replies',
			'core.submit_post_modify_sql_data'       => 'init_post_column',
			'core.viewtopic_post_rowset_data'        => 'fetch_post_status',
			'core.viewtopic_modify_post_row'         => 'render_post_status',
			'core.permissions'                       => 'add_permission',
		];
	}

	public function load_language($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'salvocortesiano/aireply',
			'lang_set' => 'aireply',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	public function add_permission($event)
	{
		$permissions = $event['permissions'];
		$permissions['u_aireply_trigger'] = ['lang' => 'ACL_U_AIREPLY_TRIGGER', 'cat' => 'post'];
		$event['permissions'] = $permissions;
	}

	/**
	 * Garantisce che la colonna non resti NULL sui post nuovi.
	 */
	public function init_post_column($event)
	{
		if (!in_array($event['post_mode'], ['post', 'reply', 'quote'], true))
		{
			return;
		}

		$sql_data = $event['sql_data'];

		if (empty($sql_data[POSTS_TABLE]['sql']['post_aireply_data']))
		{
			$sql_data[POSTS_TABLE]['sql']['post_aireply_data'] = '';
			$event['sql_data'] = $sql_data;
		}
	}

	/**
	 * Il cuore del listener: decide quali bot devono rispondere e accoda i job.
	 */
	public function enqueue_replies($event)
	{
		$mode = (string) $event['mode'];

		// La modifica di un post non genera nuove risposte: sarebbe un modo
		// banale per far girare il bot all'infinito sullo stesso messaggio.
		if (!in_array($mode, ['post', 'reply', 'quote'], true))
		{
			return;
		}

		if (!$this->gate->is_globally_enabled())
		{
			return;
		}

		$data = $event['data'];

		$forum_id = (int) $event['forum_id'];
		$post_id = (int) ($data['post_id'] ?? 0);
		$topic_id = (int) ($data['topic_id'] ?? 0);

		if ($forum_id === 0 || $post_id === 0)
		{
			return;
		}

		// Uscita rapida: sulle board dove l'estensione è attiva ma non ancora
		// configurata, questo è tutto ciò che viene eseguito.
		$candidates = $this->bots->get_for_forum($forum_id);

		if (empty($candidates))
		{
			return;
		}

		$raw_text = (string) ($data['message'] ?? '');
		$request = $this->extractor->to_plain_text($raw_text);

		$poster_id = (int) $this->user->data['user_id'];

		if ($this->gate->check_poster($poster_id, $request) !== '')
		{
			return;
		}

		$trigger_base = ($mode === 'post') ? forum_binding::TRIGGER_TOPIC : forum_binding::TRIGGER_REPLY;

		$queued = [];

		foreach ($candidates as $candidate)
		{
			/** @var \salvocortesiano\aireply\bot\bot $bot */
			$bot = $candidate['bot'];
			/** @var forum_binding $binding */
			$binding = $candidate['binding'];

			// Un bot non risponde a sé stesso né agli altri bot.
			if ($bot->user_id === $poster_id)
			{
				continue;
			}

			$trigger = $this->resolve_trigger($trigger_base, $binding, $raw_text, $bot->user_id, $bot->username);

			if ($trigger === '')
			{
				continue;
			}

			if ($this->gate->check_binding($bot, $binding, $trigger, $post_id, $topic_id) !== '')
			{
				continue;
			}

			$job_id = $this->queue->enqueue([
				'bot_id'       => $bot->bot_id,
				'trigger_type' => $trigger,
				'post_id'      => $post_id,
				'topic_id'     => $topic_id,
				'forum_id'     => $forum_id,
				'poster_id'    => $poster_id,
				'run_after'    => time() + $binding->delay_seconds,
				'request'      => $this->extractor->truncate($request, 60000),
			]);

			$queued[] = [
				'job_id'   => $job_id,
				'bot_id'   => $bot->bot_id,
				'bot_name' => $bot->username,
				'status'   => 'queued',
			];
		}

		if (!empty($queued))
		{
			// Il badge "sta pensando…" sotto il post dell'utente. Senza questo,
			// l'attesa sembra un malfunzionamento.
			$this->status->write($post_id, $queued);
		}
	}

	/**
	 * Quale innesco vale per questo bot?
	 *
	 * La menzione ha la precedenza: se qualcuno chiama il bot per nome, quella
	 * è l'intenzione esplicita, e come tale ignora il cooldown.
	 */
	protected function resolve_trigger(string $base, forum_binding $binding, string $raw_text, int $bot_user_id, string $bot_username): string
	{
		if ($binding->on_mention && $this->extractor->mentions_user($raw_text, $bot_user_id, $bot_username))
		{
			return forum_binding::TRIGGER_MENTION;
		}

		return $binding->accepts($base) ? $base : '';
	}

	/**
	 * Porta la colonna nel rowset di viewtopic.
	 */
	public function fetch_post_status($event)
	{
		$rowset_data = $event['rowset_data'];
		$rowset_data['post_aireply_data'] = $event['row']['post_aireply_data'] ?? '';
		$event['rowset_data'] = $rowset_data;
	}

	/**
	 * Passa lo stato al template del post.
	 */
	public function render_post_status($event)
	{
		$row = $event['row'];

		if (empty($row['post_aireply_data']))
		{
			return;
		}

		$entries = $this->status->read((string) $row['post_aireply_data']);

		if (empty($entries))
		{
			return;
		}

		$blocks = [];

		foreach ($entries as $entry)
		{
			$block = [
				'BOT_NAME' => $entry['bot_name'] ?? '',
				'STATUS'   => $entry['status'] ?? '',
				'TEXT'     => $this->language->lang($this->status->get_lang_key($entry['status'] ?? ''), $entry['bot_name'] ?? ''),
				'U_REPLY'  => '',
			];

			if (!empty($entry['response_post_id']))
			{
				$block['U_REPLY'] = append_sid(
					generate_board_url() . '/viewtopic.' . $this->get_php_ext(),
					'p=' . (int) $entry['response_post_id']
				) . '#p' . (int) $entry['response_post_id'];
			}

			$blocks[] = $block;
		}

		$post_row = $event['post_row'];
		$post_row['AIREPLY_STATUS'] = $blocks;
		$event['post_row'] = $post_row;
	}

	protected function get_php_ext(): string
	{
		global $phpEx;

		return $phpEx ?: 'php';
	}
}
