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

use phpbb\config\config;
use phpbb\language\language;
use salvocortesiano\aireply\bot\bot;
use salvocortesiano\aireply\bot\bot_repository;
use salvocortesiano\aireply\content\markdown_to_bbcode;
use salvocortesiano\aireply\content\text_extractor;
use salvocortesiano\aireply\provider\ai_request;
use salvocortesiano\aireply\provider\ai_result;
use salvocortesiano\aireply\provider\key_manager;
use salvocortesiano\aireply\provider\manager as provider_manager;
use salvocortesiano\aireply\provider\provider_exception;
use salvocortesiano\aireply\queue\gatekeeper;
use salvocortesiano\aireply\queue\job;
use salvocortesiano\aireply\queue\job_queue;
use salvocortesiano\aireply\status\post_status;

/**
 * Elabora un singolo job: contesto, chiamata al provider, formattazione,
 * pubblicazione, aggiornamento dello stato.
 *
 * Questa classe non conosce né cURL né i formati delle API: parla solo con
 * l'interfaccia dei provider.
 */
class job_worker
{
	/** @var config */
	protected $config;

	/** @var language */
	protected $language;

	/** @var job_queue */
	protected $queue;

	/** @var gatekeeper */
	protected $gate;

	/** @var bot_repository */
	protected $bots;

	/** @var provider_manager */
	protected $providers;

	/** @var key_manager */
	protected $keys;

	/** @var context_builder */
	protected $context;

	/** @var publisher */
	protected $publisher;

	/** @var markdown_to_bbcode */
	protected $markdown;

	/** @var text_extractor */
	protected $extractor;

	/** @var post_status */
	protected $status;

	public function __construct(
		config $config,
		language $language,
		job_queue $queue,
		gatekeeper $gate,
		bot_repository $bots,
		provider_manager $providers,
		key_manager $keys,
		context_builder $context,
		publisher $publisher,
		markdown_to_bbcode $markdown,
		text_extractor $extractor,
		post_status $status
	) {
		$this->config = $config;
		$this->language = $language;

		// Il worker gira anche da cron, dove core.user_setup non è passato:
		// i file di lingua vanno caricati esplicitamente.
		$this->language->add_lang(['aireply', 'aireply_log'], 'salvocortesiano/aireply');
		$this->queue = $queue;
		$this->gate = $gate;
		$this->bots = $bots;
		$this->providers = $providers;
		$this->keys = $keys;
		$this->context = $context;
		$this->publisher = $publisher;
		$this->markdown = $markdown;
		$this->extractor = $extractor;
		$this->status = $status;
	}

	/**
	 * @return string esito: done | failed | retry | skipped
	 */
	public function execute(job $j): string
	{
		$started = microtime(true);
		$log = ['started_at' => date('c')];

		$b = $this->bots->get_by_id($j->bot_id);

		if ($b === null)
		{
			$this->finish_skipped($j, $this->language->lang('AIREPLY_ERR_BOT_GONE'));
			return 'skipped';
		}

		$this->status->update_entry($j->post_id, $j->bot_id, ['status' => post_status::STATUS_RUNNING]);

		// Ricontrollo: fra accodamento ed esecuzione possono essere passati minuti.
		$binding = $this->bots->get_binding($j->bot_id, $j->forum_id);
		$blocker = $this->gate->check_before_execution($b, $binding);

		if ($blocker !== '')
		{
			$this->finish_skipped($j, $blocker);
			return 'skipped';
		}

		if (!$this->publisher->can_post($b, $j->forum_id))
		{
			// Senza f_reply submit_post() fallirebbe in silenzio: meglio dirlo.
			$this->finish_skipped($j, $this->language->lang('AIREPLY_ERR_NO_REPLY_PERM', $b->username, $j->forum_id));
			return 'skipped';
		}

		$api_key = $this->keys->resolve($b->api_key);

		if ($api_key === '')
		{
			$this->finish_failed($j, ai_result::ERR_AUTH, $this->language->lang('AIREPLY_ERR_NO_KEY_JOB'), false, $log);
			return 'failed';
		}

		try
		{
			$provider = $this->providers->get($b->provider);
		}
		catch (provider_exception $e)
		{
			$this->finish_failed($j, ai_result::ERR_REQUEST, $e->getMessage(), false, $log);
			return 'failed';
		}

		// --- contesto ---------------------------------------------------
		$built = $this->context->build($j, $b);
		$log['context'] = $built['stats'];

		if (empty($built['messages']))
		{
			$this->finish_skipped($j, $this->language->lang('AIREPLY_ERR_NO_CONTENT'));
			return 'skipped';
		}

		// --- richiesta --------------------------------------------------
		$request = new ai_request();
		$request->api_key = $api_key;
		$request->base_url = $b->base_url;
		$request->model = $b->model;
		$request->system_prompt = $this->build_system_prompt($b);
		$request->messages = $built['messages'];
		$request->max_output_tokens = $b->max_output_tokens;
		$request->thinking_budget = $b->thinking_budget;
		$request->timeout = $b->request_timeout;
		$request->verbose_log = !empty($this->config['aireply_verbose_log']);

		if ($provider->supports_temperature($b->model))
		{
			$request->temperature = $b->temperature;
		}

		$result = $provider->generate($request);

		// I limiti di frequenza si registrano sempre, anche quando la chiamata
		// fallisce: proprio in caso di 429 sono l'informazione più utile.
		if (!empty($result->rate_limit))
		{
			$this->providers->store_rate_limit($b->provider, $result->rate_limit);
		}

		$log['provider'] = $b->provider;
		$log['model'] = $b->model;
		$log['duration_ms'] = $result->duration_ms;
		$log['finish_reason'] = $result->finish_reason;
		$log['debug'] = $result->debug;

		if (!$result->success)
		{
			$retrying = $this->finish_failed(
				$j,
				$result->error_code,
				$result->error_message,
				$result->is_retryable(),
				$log,
				$result
			);

			return $retrying ? 'retry' : 'failed';
		}

		// --- formattazione e pubblicazione ------------------------------
		$message = $this->format_reply($b, $result->text);

		try
		{
			$response_post_id = $this->publisher->publish($j, $b, $message);
		}
		catch (\Throwable $e)
		{
			$log['publish_exception'] = $e->getMessage();

			// La chiamata API è già stata pagata: non ha senso rifarla.
			// Se il fallimento è transitorio, il retry ripartirà dal contesto.
			$this->finish_failed($j, 'publish', $e->getMessage(), false, $log, $result);

			return 'failed';
		}

		if ($response_post_id === 0)
		{
			$this->finish_failed($j, 'publish', $this->language->lang('AIREPLY_ERR_NO_POST_ID'), false, $log, $result);
			return 'failed';
		}

		$log['response_post_id'] = $response_post_id;
		$log['total_ms'] = (int) round((microtime(true) - $started) * 1000);

		$this->queue->complete($j, [
			'response'          => $this->extractor->truncate($result->text, 60000, ''),
			'prompt_tokens'     => $result->prompt_tokens,
			'completion_tokens' => $result->completion_tokens,
			'duration_ms'       => $result->duration_ms,
			'response_post_id'  => $response_post_id,
			'error_code'        => '',
			'error_message'     => '',
			'log'               => $this->encode_log($log),
		]);

		$this->status->update_entry($j->post_id, $j->bot_id, [
			'status'           => post_status::STATUS_DONE,
			'response_post_id' => $response_post_id,
		]);

		return 'done';
	}

	/**
	 * Prompt di sistema effettivo: quello dell'admin più le istruzioni tecniche
	 * di formattazione.
	 *
	 * Le istruzioni si aggiungono in coda e non sostituiscono nulla: la
	 * personalità del bot resta interamente nelle mani di chi lo configura.
	 */
	protected function build_system_prompt(bot $b): string
	{
		$parts = [];

		if (trim($b->system_prompt) !== '')
		{
			$parts[] = trim($b->system_prompt);
		}

		/*
		 * Le istruzioni tecniche vengono dal file di lingua e non sono cablate.
		 *
		 * Non è una questione di capacità del modello: i modelli sono
		 * multilingui e rispecchiano da soli la lingua di chi scrive. Il punto
		 * è che un prompt di sistema in una lingua esercita una trazione verso
		 * quella lingua, e vince quando il segnale del messaggio è debole —
		 * post di due parole, un link, un'emoji, un thread misto. I modelli
		 * economici, che sono poi quelli che si usano davvero, sono i più
		 * sensibili. Il risultato non sarebbe "sbaglia sempre", ma la cosa
		 * peggiore: sbaglia ogni tanto.
		 */
		$parts[] = $this->language->lang('AIREPLY_SYSTEM_INSTRUCTIONS', $b->max_post_chars);
		$parts[] = $this->build_language_directive($b);

		return implode("\n\n", $parts);
	}

	/**
	 * Istruzione esplicita sulla lingua della risposta.
	 *
	 * Le tre politiche non sono equivalenti e la scelta dipende dalla board:
	 *
	 *  auto   rispecchia il messaggio. Giusto quasi sempre, perché un thread è
	 *         pubblico: la risposta deve essere leggibile da chi legge la
	 *         discussione, non solo da chi l'ha scritta.
	 *  board  lingua fissa della board. Utile su forum monolingui che ogni
	 *         tanto ricevono messaggi in altre lingue e vogliono comunque
	 *         rispondere nella propria.
	 *  custom lingua fissa scelta a mano, per i casi che le prime due non
	 *         coprono (sezione dedicata a una lingua diversa dalla board).
	 *
	 * Resta un vincolo morbido: il modello di norma obbedisce, ma non è una
	 * garanzia, e i modelli più piccoli sbandano più facilmente.
	 */
	protected function build_language_directive(bot $b): string
	{
		switch ($b->get_reply_language())
		{
			case bot::LANG_BOARD:
				// Endonimo dichiarato dal file di lingua caricato, che in
				// contesto cron è quello predefinito della board.
				return $this->language->lang(
					'AIREPLY_SYSTEM_LANG_FIXED',
					$this->language->lang('AIREPLY_LANGUAGE_ENDONYM')
				);

			case bot::LANG_CUSTOM:
				return $this->language->lang(
					'AIREPLY_SYSTEM_LANG_FIXED',
					trim($b->reply_language_custom)
				);
		}

		return $this->language->lang('AIREPLY_SYSTEM_LANG_AUTO');
	}

	/**
	 * Da testo del modello a BBCode pronto per la pubblicazione.
	 */
	protected function format_reply(bot $b, string $text): string
	{
		$body = $this->markdown->convert($text);
		$body = $this->extractor->truncate($body, $b->max_post_chars);

		$disclosure = '[size=85][i]' . $this->language->lang('AIREPLY_DISCLOSURE', $b->model) . '[/i][/size]';

		$template = $b->get_template();

		/*
		 * Il testo generato è l'unica cosa che non può mancare.
		 *
		 * Se il template non contiene {response} — perché l'admin l'ha
		 * cancellato, o ha incollato un template pensato per altro — la
		 * sostituzione non ha dove inserire la risposta e il post esce vuoto,
		 * con il solo contorno. Il job risulta completato, l'API viene pagata,
		 * e nel registro non compare nessun errore: il modo peggiore possibile
		 * di fallire.
		 *
		 * La risposta va quindi anteposta, non lasciata cadere.
		 */
		if (strpos($template, '{response}') === false)
		{
			$template = '{response}' . "\n\n" . $template;
		}

		$message = str_replace(
			['{response}', '{disclosure}', '{model}', '{provider}'],
			[$body, $disclosure, $b->model, $b->provider],
			$template
		);

		// Stessa logica per la dichiarazione di contenuto generato: su una board
		// europea la trasparenza sui contenuti sintetici non è facoltativa.
		if (strpos($template, '{disclosure}') === false)
		{
			$message .= "\n\n" . $disclosure;
		}

		return trim($message);
	}

	protected function finish_skipped(job $j, string $reason): void
	{
		$this->queue->skip($j, $reason);

		$this->status->update_entry($j->post_id, $j->bot_id, [
			'status' => post_status::STATUS_SKIPPED,
		]);
	}

	/**
	 * @return bool true se il job tornerà in coda
	 */
	protected function finish_failed(job $j, string $code, string $message, bool $retryable, array $log, ?ai_result $result = null): bool
	{
		$log['error'] = ['code' => $code, 'message' => $message];

		$extra = ['log' => $this->encode_log($log)];

		if ($result !== null)
		{
			$extra['prompt_tokens'] = $result->prompt_tokens;
			$extra['completion_tokens'] = $result->completion_tokens;
			$extra['duration_ms'] = $result->duration_ms;
		}

		$will_retry = $this->queue->fail(
			$j,
			$code,
			$message,
			$retryable,
			$extra,
			$result !== null ? $result->retry_after : 0
		);

		$this->status->update_entry($j->post_id, $j->bot_id, [
			'status' => $will_retry ? post_status::STATUS_QUEUED : post_status::STATUS_FAILED,
		]);

		return $will_retry;
	}

	protected function encode_log(array $log): string
	{
		$encoded = json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

		if ($encoded === false)
		{
			return '{"error":"log non codificabile"}';
		}

		return mb_substr($encoded, 0, 60000);
	}
}
