<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\provider;

/**
 * Provider per le API OpenAI (endpoint Chat Completions).
 *
 * Due differenze rispetto all'implementazione di AI Labs, entrambe dovute a
 * cambiamenti dell'API successivi a quel codice:
 *
 *  1. `max_tokens` è deprecato in favore di `max_completion_tokens` e non è
 *     compatibile con i modelli o-series. Usiamo sempre il nome nuovo.
 *  2. I modelli di ragionamento (serie GPT-5, o-series) rifiutano con un 400
 *     i parametri `temperature`, `top_p`, `frequency_penalty` e
 *     `presence_penalty`. Vanno omessi, non semplicemente ignorati.
 */
class openai_provider extends base_provider
{
	public const ID = 'openai';

	public const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

	public const DEFAULT_MODEL = 'gpt-4o-mini';

	/**
	 * Frammenti che identificano modelli non adatti alla chat testuale.
	 * L'endpoint /models restituisce anche embedding, audio, immagini e moderazione.
	 */
	protected const EXCLUDED_FRAGMENTS = [
		'embed', 'tts', 'whisper', 'dall-e', 'moderation', 'audio',
		'realtime', 'image', 'transcribe', 'search', 'computer-use',
		'codex', 'babbage', 'davinci', 'instruct',
	];

	public function get_id(): string
	{
		return self::ID;
	}

	public function get_label_key(): string
	{
		return 'AIREPLY_PROVIDER_OPENAI';
	}

	public function get_default_base_url(): string
	{
		return self::DEFAULT_BASE_URL;
	}

	public function get_default_model(): string
	{
		return self::DEFAULT_MODEL;
	}

	/**
	 * I modelli di ragionamento non accettano `temperature`.
	 *
	 * Il riconoscimento è per prefisso perché non esiste un campo nell'API che
	 * lo dichiari. Se OpenAI introduce una famiglia con un nome diverso, qui va
	 * aggiunta una riga: è il punto di manutenzione noto di questa classe.
	 */
	public function supports_temperature(string $model): bool
	{
		return !$this->is_reasoning_model($model);
	}

	protected function is_reasoning_model(string $model): bool
	{
		$model = strtolower(trim($model));

		return (bool) preg_match('/^(o\d|gpt-5)/', $model);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generate(ai_request $request): ai_result
	{
		$messages = $this->normalize_messages($request->messages);

		if (empty($messages))
		{
			return ai_result::fail(ai_result::ERR_REQUEST, $this->language->lang('AIREPLY_ERR_NO_MESSAGES'));
		}

		$payload = [
			'model'    => $request->model,
			'messages' => $this->build_messages($request, $messages),
		];

		if ($request->max_output_tokens > 0)
		{
			// Nome corrente del parametro. `max_tokens` è deprecato e i modelli
			// o-series lo rifiutano del tutto.
			$payload['max_completion_tokens'] = (int) $request->max_output_tokens;
		}

		if ($this->supports_temperature($request->model))
		{
			$temperature = $this->clamp_temperature($request->temperature, 0.0, 2.0);

			if ($temperature !== null)
			{
				$payload['temperature'] = $temperature;
			}
		}

		$headers = ['Authorization' => 'Bearer ' . $request->api_key];
		$url = $this->resolve_base_url($request->base_url) . '/chat/completions';

		$started = microtime(true);
		$response = $this->http->request('POST', $url, $headers, $payload, $request->timeout);
		$duration = (int) round((microtime(true) - $started) * 1000);

		if (!$response->is_ok())
		{
			$result = $this->map_http_error($response, $request->api_key);
			$result->duration_ms = $duration;
			$result->rate_limit = $this->extract_rate_limit($response);
			$result->debug = $this->build_debug($request, $response, $headers, $payload);

			return $result;
		}

		$result = $this->parse_success($response, $request);
		$result->duration_ms = $duration;
		$result->http_status = $response->status;
		$result->rate_limit = $this->extract_rate_limit($response);
		$result->debug = $this->build_debug($request, $response, $headers, $payload);

		return $result;
	}

	/**
	 * Costruisce l'array messages[], prompt di sistema incluso.
	 */
	protected function build_messages(ai_request $request, array $messages): array
	{
		$payload = [];

		if (trim($request->system_prompt) !== '')
		{
			// I modelli di ragionamento preferiscono il ruolo `developer`;
			// `system` resta accettato ma è la forma legacy.
			$role = $this->is_reasoning_model($request->model) ? 'developer' : 'system';

			$payload[] = ['role' => $role, 'content' => $request->system_prompt];
		}

		foreach ($messages as $message)
		{
			$payload[] = [
				'role'    => $message->is_assistant() ? 'assistant' : 'user',
				'content' => $message->get_payload_text(),
			];
		}

		return $payload;
	}

	/**
	 * Interpreta una risposta 2xx.
	 */
	protected function parse_success(http_response $response, ai_request $request): ai_result
	{
		$text = $response->get('choices.0.message.content');
		$finish = (string) $response->get('choices.0.finish_reason', '');

		$prompt_tokens = (int) $response->get('usage.prompt_tokens', 0);
		$completion_tokens = (int) $response->get('usage.completion_tokens', 0);

		if (!is_string($text) || trim($text) === '')
		{
			/*
			 * Caso specifico dei modelli di ragionamento: se
			 * max_completion_tokens è troppo basso, tutto il budget viene
			 * consumato dai token di ragionamento e il contenuto visibile
			 * arriva vuoto con finish_reason "length". Senza questo controllo
			 * il bot pubblicherebbe un post vuoto e l'admin non capirebbe perché.
			 */
			if ($finish === 'length' && $this->is_reasoning_model($request->model))
			{
				$result = ai_result::fail(ai_result::ERR_EMPTY, $this->language->lang('AIREPLY_ERR_REASONING_EMPTY'));
			}
			else if ($finish === 'content_filter')
			{
				$result = ai_result::fail(ai_result::ERR_SAFETY, $this->language->lang('AIREPLY_ERR_CONTENT_FILTER'));
			}
			else
			{
				$result = ai_result::fail(ai_result::ERR_EMPTY, $this->language->lang(
					'AIREPLY_ERR_EMPTY_RESPONSE',
					$finish !== '' ? $finish : $this->language->lang('AIREPLY_ERR_FINISH_UNKNOWN')
				));
			}

			$result->finish_reason = $finish;
			$result->prompt_tokens = $prompt_tokens;
			$result->completion_tokens = $completion_tokens;

			return $result;
		}

		$result = ai_result::ok(trim($text));
		$result->finish_reason = $finish;
		$result->prompt_tokens = $prompt_tokens;
		$result->completion_tokens = $completion_tokens;

		return $result;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Elencare i modelli valida la chiave senza consumare token: è il test di
	 * connessione più economico possibile.
	 */
	public function test_connection(string $api_key, string $base_url = '', string $model = ''): ai_result
	{
		try
		{
			$models = $this->list_models($api_key, $base_url);
		}
		catch (provider_exception $e)
		{
			// Stessa logica del provider Gemini: una chiave può essere valida
			// ma non autorizzata all'endpoint /models.
			if ($e->get_error_code() === ai_result::ERR_METHOD_BLOCKED)
			{
				return $this->verify_by_generating($api_key, $base_url, $model, $e->getMessage());
			}

			$result = ai_result::fail($e->get_error_code(), $e->getMessage());
			$result->http_status = $e->get_http_status();

			return $result;
		}

		$result = ai_result::ok('');
		$result->debug = ['models_found' => count($models)];

		if ($model !== '')
		{
			$available = array_map(static function (model_info $info) {
				return $info->id;
			}, $models);

			if (!in_array($model, $available, true))
			{
				return ai_result::fail(ai_result::ERR_MODEL, $this->language->lang('AIREPLY_ERR_MODEL_NOT_AVAILABLE', $model));
			}

			$result->debug['model_verified'] = $model;
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function list_models(string $api_key, string $base_url = ''): array
	{
		$url = $this->resolve_base_url($base_url) . '/models';
		$headers = ['Authorization' => 'Bearer ' . $api_key];

		$response = $this->http->request('GET', $url, $headers, null, 30);

		if (!$response->is_ok())
		{
			$error = $this->map_http_error($response, $api_key);

			throw new provider_exception($error->error_code, $error->error_message, $response->status);
		}

		$data = $response->get('data');

		if (!is_array($data))
		{
			throw new provider_exception(ai_result::ERR_PARSE, $this->language->lang('AIREPLY_ERR_MODELS_PARSE', 'OpenAI'));
		}

		$models = [];

		foreach ($data as $entry)
		{
			if (!isset($entry['id']) || !is_string($entry['id']))
			{
				continue;
			}

			$id = $entry['id'];

			if (!$this->is_chat_model($id))
			{
				continue;
			}

			$info = new model_info($id);
			$info->is_reasoning = $this->is_reasoning_model($id);

			$models[] = $info;
		}

		// Ordine alfabetico inverso: le famiglie più recenti finiscono in cima.
		usort($models, static function (model_info $a, model_info $b) {
			return strnatcmp($b->id, $a->id);
		});

		return $models;
	}

	/**
	 * Verifica la chiave con una generazione minima, quando /models è precluso.
	 */
	protected function verify_by_generating(string $api_key, string $base_url, string $model, string $list_error): ai_result
	{
		$model = ($model !== '') ? $model : $this->get_default_model();

		$payload = [
			'model'                => $model,
			'messages'             => [['role' => 'user', 'content' => 'ping']],
			'max_completion_tokens' => 16,
		];

		$headers = ['Authorization' => 'Bearer ' . $api_key];
		$url = $this->resolve_base_url($base_url) . '/chat/completions';

		$response = $this->http->request('POST', $url, $headers, $payload, 30);

		if (!$response->is_ok())
		{
			$result = $this->map_http_error($response, $api_key);
			$result->debug = ['fallback' => 'chat/completions', 'list_models_error' => $list_error];

			return $result;
		}

		$result = ai_result::ok('');
		$result->http_status = $response->status;
		$result->debug = [
			'models_found'      => 0,
			'verified_by'       => 'chat/completions',
			'list_models_error' => $list_error,
			'model_verified'    => $model,
		];

		return $result;
	}

	/**
	 * L'endpoint /models restituisce tutto il catalogo, non solo i modelli di chat.
	 */
	protected function is_chat_model(string $id): bool
	{
		$id = strtolower($id);

		foreach (self::EXCLUDED_FRAGMENTS as $fragment)
		{
			if (strpos($id, $fragment) !== false)
			{
				return false;
			}
		}

		return (bool) preg_match('/^(gpt-|chatgpt-|o\d)/', $id);
	}
}
