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
 * Provider per la Gemini API (Google AI Studio, endpoint generateContent).
 *
 * Tre differenze rispetto all'implementazione di AI Labs:
 *
 *  1. La chiave viaggia nell'header `x-goog-api-key`, non nel parametro query
 *     `?key=`. Un parametro query finisce nei log del server, nei proxy e in
 *     qualunque strumento intermedio; è la forma legacy e Google stessa
 *     raccomanda l'header.
 *  2. Il prompt di sistema usa `systemInstruction`, non un finto primo turno
 *     dell'utente: il modello lo tratta diversamente e obbedisce di più.
 *  3. `promptFeedback.blockReason` e `finishReason: SAFETY` producono un errore
 *     parlante invece di una risposta vuota.
 *
 * Nota operativa: dal 19 giugno 2026 Google blocca le chiamate da chiavi prive
 * di restrizioni API. La chiave va limitata alla sola Generative Language API
 * nella console Google Cloud, altrimenti si riceve un 403.
 */
class gemini_provider extends base_provider
{
	public const ID = 'gemini';

	public const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

	/*
	 * Alias, non versione fissa.
	 *
	 * Google chiude i modelli più vecchi ai nuovi account senza rimuoverli da
	 * ListModels: l'elenco li mostra ancora e generateContent risponde 404
	 * "no longer available to new users". Un alias -latest punta sempre a una
	 * versione servita, e non scade.
	 */
	public const DEFAULT_MODEL = 'gemini-flash-lite-latest';

	/** Modelli che non servono a generare testo di conversazione */
	/*
	 * Modelli che supportano generateContent ma non servono a rispondere su un
	 * forum. Senza questo filtro il menù si riempie di voci che confondono e
	 * basta: robotica, ricerca approfondita, uso del computer, generazione
	 * musicale, agenti sperimentali.
	 */
	protected const EXCLUDED_FRAGMENTS = [
		'embedding', 'aqa', 'imagen', 'veo', 'tts', 'image', 'live', 'native-audio',
		'lyria', 'robotics', 'computer-use', 'deep-research', 'antigravity',
		'nano-banana',
	];

	public function get_id(): string
	{
		return self::ID;
	}

	public function get_label_key(): string
	{
		return 'AIREPLY_PROVIDER_GEMINI';
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
	 * Da Gemini 2.5 in poi i modelli ragionano prima di rispondere e il budget
	 * è configurabile.
	 */
	public function supports_thinking_budget(string $model): bool
	{
		return (bool) preg_match('/gemini-([2-9]\.[5-9]|[3-9])/i', $model);
	}

	/**
	 * Invia una richiesta provando gli schemi di autenticazione accettati.
	 *
	 * ── Perché serve un tentativo doppio ─────────────────────────────────
	 *
	 * Google sta sostituendo le chiavi "AIza..." con un nuovo formato
	 * "AQ.Ab8..." (le cosiddette auth key). Il passaggio è in corso e il
	 * comportamento è disomogeneo: molti account riportano che le chiavi AQ.
	 * vengono rifiutate dall'endpoint REST nativo con 401
	 * ACCESS_TOKEN_TYPE_UNSUPPORTED oppure con "API key not valid", sia
	 * passandole nell'header x-goog-api-key sia nel parametro query.
	 *
	 * Il messaggio d'errore in alcuni casi dice "Expected OAuth 2 access
	 * token", il che suggerisce che il server si aspetti quella credenziale in
	 * forma di Bearer. Non è documentato e non funziona ovunque, ma è un
	 * secondo tentativo che costa nulla e che su alcuni account risolve.
	 *
	 * L'ordine è deliberato: x-goog-api-key resta il metodo primario, perché è
	 * quello ufficiale e l'unico che funziona con le chiavi AIza.
	 *
	 * @param  string[] $attempted riceve l'elenco degli schemi provati
	 */
	protected function send(string $method, string $url, string $api_key, ?array $payload, int $timeout, array &$attempted = [], array &$headers_used = []): http_response
	{
		$schemes = [['x-goog-api-key' => $api_key]];

		if ($this->is_auth_key($api_key))
		{
			$schemes[] = ['Authorization' => 'Bearer ' . $api_key];
		}

		$response = null;

		foreach ($schemes as $index => $headers)
		{
			$attempted[] = array_key_first($headers);
			$headers_used = $headers;

			$response = $this->http->request($method, $url, $headers, $payload, $timeout);

			if ($response->is_ok())
			{
				return $response;
			}

			// Si ritenta solo su un rifiuto della credenziale: un 429 o un 500
			// non migliorano cambiando header, e riprovare sprecherebbe tempo.
			if (!$this->is_credential_rejection($response) || $index === count($schemes) - 1)
			{
				return $response;
			}
		}

		return $response;
	}

	/**
	 * La chiave è nel nuovo formato "auth key" di Google?
	 */
	protected function is_auth_key(string $api_key): bool
	{
		return strpos($api_key, 'AQ.') === 0;
	}

	/**
	 * La risposta è un rifiuto della credenziale, non un altro tipo di errore?
	 */
	protected function is_credential_rejection(http_response $response): bool
	{
		if (!in_array($response->status, [400, 401, 403], true))
		{
			return false;
		}

		$message = (string) $response->get('error.message', '');
		$status = (string) $response->get('error.status', '');

		foreach (['API key not valid', 'ACCESS_TOKEN_TYPE_UNSUPPORTED', 'UNAUTHENTICATED', 'invalid authentication'] as $needle)
		{
			if (stripos($message . ' ' . $status, $needle) !== false)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Errore parlante quando una chiave AQ. viene rifiutata.
	 *
	 * Senza questo, l'admin legge "chiave non valida", conclude di aver
	 * sbagliato a copiarla e ne genera altre dieci identiche.
	 */
	/**
	 * Un 404 "no longer available to new users" ha un rimedio preciso e
	 * immediato, ma il messaggio di Google non lo dice: rimanda alla
	 * migrazione verso un'altra API, che qui non serve. Basta cambiare modello.
	 */
	protected function explain_retired_model(ai_result $result, string $model): ai_result
	{
		if (stripos($result->error_message, 'no longer available') === false)
		{
			return $result;
		}

		$result->error_message = $this->language->lang('AIREPLY_ERR_MODEL_RETIRED', $model, self::DEFAULT_MODEL)
			. ' — ' . $result->error_message;

		return $result;
	}

	protected function explain_credential_rejection(ai_result $result, string $api_key): ai_result
	{
		if (!$this->is_auth_key($api_key))
		{
			return $result;
		}

		$result->error_message = $this->language->lang('AIREPLY_ERR_GEMINI_AQ_KEY') . ' — ' . $result->error_message;

		return $result;
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

		$payload = ['contents' => $this->build_contents($messages)];

		if (trim($request->system_prompt) !== '')
		{
			$payload['systemInstruction'] = [
				'parts' => [['text' => $request->system_prompt]],
			];
		}

		$generation_config = [];

		$temperature = $this->clamp_temperature($request->temperature, 0.0, 2.0);

		if ($temperature !== null)
		{
			$generation_config['temperature'] = $temperature;
		}

		if ($request->max_output_tokens > 0)
		{
			$generation_config['maxOutputTokens'] = (int) $request->max_output_tokens;
		}

		if ($request->thinking_budget >= 0 && $this->supports_thinking_budget($request->model))
		{
			$generation_config['thinkingConfig'] = ['thinkingBudget' => (int) $request->thinking_budget];
		}

		if (!empty($generation_config))
		{
			$payload['generationConfig'] = $generation_config;
		}

		$url = sprintf(
			'%s/models/%s:generateContent',
			$this->resolve_base_url($request->base_url),
			rawurlencode($this->strip_models_prefix($request->model))
		);

		$attempted = [];
		$headers = [];

		$started = microtime(true);
		$response = $this->send('POST', $url, $request->api_key, $payload, $request->timeout, $attempted, $headers);
		$duration = (int) round((microtime(true) - $started) * 1000);

		if (!$response->is_ok())
		{
			$result = $this->map_http_error($response, $request->api_key);
			$result = $this->explain_credential_rejection($result, $request->api_key);
			$result = $this->explain_retired_model($result, $request->model);
			$result->duration_ms = $duration;
			$result->rate_limit = $this->extract_rate_limit($response);
			$result->debug = $this->build_debug($request, $response, $headers, $payload);
			$result->debug['auth_schemes_tried'] = $attempted;

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
	 * Traduce i messaggi neutri nel formato contents[] di Gemini.
	 * Il ruolo dell'assistente qui si chiama "model".
	 */
	protected function build_contents(array $messages): array
	{
		$contents = [];

		foreach ($messages as $message)
		{
			$contents[] = [
				'role'  => $message->is_assistant() ? 'model' : 'user',
				'parts' => [['text' => $message->get_payload_text()]],
			];
		}

		return $contents;
	}

	/**
	 * Interpreta una risposta 2xx.
	 *
	 * Attenzione: un 200 da Gemini non significa che ci sia del testo. Il
	 * blocco per motivi di sicurezza arriva con codice 200 e candidates vuoto.
	 */
	protected function parse_success(http_response $response, ai_request $request): ai_result
	{
		$prompt_tokens = (int) $response->get('usageMetadata.promptTokenCount', 0);
		$completion_tokens = (int) $response->get('usageMetadata.candidatesTokenCount', 0);
		$thoughts_tokens = (int) $response->get('usageMetadata.thoughtsTokenCount', 0);

		// Il prompt stesso è stato rifiutato prima ancora della generazione.
		$block_reason = $response->get('promptFeedback.blockReason');

		if (is_string($block_reason) && $block_reason !== '')
		{
			$result = ai_result::fail(ai_result::ERR_SAFETY, $this->language->lang('AIREPLY_ERR_GEMINI_BLOCKED', $block_reason));
			$result->prompt_tokens = $prompt_tokens;

			return $result;
		}

		$finish = (string) $response->get('candidates.0.finishReason', '');
		$parts = $response->get('candidates.0.content.parts');

		$text = '';

		if (is_array($parts))
		{
			// Una risposta può arrivare spezzata in più parti: vanno concatenate,
			// altrimenti si perde tutto tranne la prima.
			foreach ($parts as $part)
			{
				if (isset($part['text']) && is_string($part['text']))
				{
					$text .= $part['text'];
				}
			}
		}

		if (trim($text) === '')
		{
			$result = $this->explain_empty_response($finish, $thoughts_tokens, $request);
			$result->finish_reason = $finish;
			$result->prompt_tokens = $prompt_tokens;
			$result->completion_tokens = $completion_tokens;

			return $result;
		}

		$result = ai_result::ok(trim($text));
		$result->finish_reason = $finish;
		$result->prompt_tokens = $prompt_tokens;

		// I token di ragionamento sono fatturati come output: contarli evita
		// che il budget giornaliero risulti sistematicamente sottostimato.
		$result->completion_tokens = $completion_tokens + $thoughts_tokens;

		return $result;
	}

	/**
	 * Perché la risposta è vuota? Ogni caso ha un rimedio diverso e vale la
	 * pena dirlo all'admin invece di scrivere "errore, controlla i log".
	 */
	protected function explain_empty_response(string $finish, int $thoughts_tokens, ai_request $request): ai_result
	{
		switch (strtoupper($finish))
		{
			case 'SAFETY':
				return ai_result::fail(ai_result::ERR_SAFETY, $this->language->lang('AIREPLY_ERR_GEMINI_SAFETY'));

			case 'RECITATION':
				return ai_result::fail(ai_result::ERR_SAFETY, $this->language->lang('AIREPLY_ERR_GEMINI_RECITATION'));

			case 'PROHIBITED_CONTENT':
			case 'BLOCKLIST':
			case 'SPII':
				return ai_result::fail(ai_result::ERR_SAFETY, $this->language->lang('AIREPLY_ERR_GEMINI_REFUSED', $finish));

			case 'MAX_TOKENS':
				if ($thoughts_tokens > 0)
				{
					/*
					 * Il tranello dei modelli 2.5 e successivi: il ragionamento
					 * consuma maxOutputTokens prima che venga prodotto testo
					 * visibile. Con maxOutputTokens basso si ottiene sempre una
					 * risposta vuota, e la causa non è affatto evidente.
					 */
					return ai_result::fail(ai_result::ERR_EMPTY, $this->language->lang(
						'AIREPLY_ERR_GEMINI_THINKING',
						(int) $request->max_output_tokens
					));
				}

				return ai_result::fail(ai_result::ERR_EMPTY, $this->language->lang('AIREPLY_ERR_MAX_TOKENS'));

			default:
				return ai_result::fail(ai_result::ERR_EMPTY, $this->language->lang(
					'AIREPLY_ERR_EMPTY_RESPONSE',
					$finish !== '' ? $finish : $this->language->lang('AIREPLY_ERR_FINISH_UNKNOWN')
				));
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection(string $api_key, string $base_url = '', string $model = ''): ai_result
	{
		try
		{
			$models = $this->list_models($api_key, $base_url);
		}
		catch (provider_exception $e)
		{
			/*
			 * Se l'elenco modelli è bloccato ma la chiave è valida, il test non
			 * deve dichiarare fallimento: molte chiavi Google sono ristrette a
			 * livello di metodo e consentono generateContent ma non ListModels.
			 * Si verifica la chiave con una generazione minima, che è ciò che
			 * il bot userà davvero.
			 */
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
			$wanted = $this->strip_models_prefix($model);

			$available = array_map(static function (model_info $info) {
				return $info->id;
			}, $models);

			if (!in_array($wanted, $available, true))
			{
				return ai_result::fail(ai_result::ERR_MODEL, $this->language->lang('AIREPLY_ERR_MODEL_NOT_AVAILABLE', $model));
			}

			$result->debug['model_verified'] = $wanted;
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function list_models(string $api_key, string $base_url = ''): array
	{
		$url = $this->resolve_base_url($base_url) . '/models?pageSize=200';

		$attempted = [];
		$headers = [];

		$response = $this->send('GET', $url, $api_key, null, 30, $attempted, $headers);

		if (!$response->is_ok())
		{
			$error = $this->map_http_error($response, $api_key);
			$error = $this->explain_credential_rejection($error, $api_key);

			throw new provider_exception($error->error_code, $error->error_message, $response->status);
		}

		$data = $response->get('models');

		if (!is_array($data))
		{
			throw new provider_exception(ai_result::ERR_PARSE, $this->language->lang('AIREPLY_ERR_MODELS_PARSE', 'Gemini'));
		}

		$models = [];

		foreach ($data as $entry)
		{
			if (!isset($entry['name']) || !is_string($entry['name']))
			{
				continue;
			}

			$id = $this->strip_models_prefix($entry['name']);

			// Solo i modelli che supportano davvero generateContent.
			$methods = $entry['supportedGenerationMethods'] ?? [];

			if (!is_array($methods) || !in_array('generateContent', $methods, true))
			{
				continue;
			}

			if (!$this->is_chat_model($id))
			{
				continue;
			}

			$info = new model_info($id, (string) ($entry['displayName'] ?? ''));
			$info->input_limit = (int) ($entry['inputTokenLimit'] ?? 0);
			$info->output_limit = (int) ($entry['outputTokenLimit'] ?? 0);
			$info->is_reasoning = $this->supports_thinking_budget($id);

			$models[] = $info;
		}

		usort($models, static function (model_info $a, model_info $b) {
			return strnatcmp($b->id, $a->id);
		});

		return $models;
	}

	/**
	 * Verifica la chiave con una generazione minima.
	 *
	 * Costa pochissimi token ed è la prova più diretta che esista: fa
	 * esattamente ciò che farà il bot in produzione. Un HTTP 200 basta a
	 * dichiarare la chiave funzionante, anche se il testo restituito è vuoto
	 * (con i modelli che ragionano e un budget minimo è normale).
	 */
	protected function verify_by_generating(string $api_key, string $base_url, string $model, string $list_error): ai_result
	{
		$model = ($model !== '') ? $model : $this->get_default_model();

		$url = sprintf(
			'%s/models/%s:generateContent',
			$this->resolve_base_url($base_url),
			rawurlencode($this->strip_models_prefix($model))
		);

		$payload = [
			'contents'         => [['role' => 'user', 'parts' => [['text' => 'ping']]]],
			'generationConfig' => ['maxOutputTokens' => 16],
		];

		$attempted = [];
		$headers = [];

		$response = $this->send('POST', $url, $api_key, $payload, 30, $attempted, $headers);

		if (!$response->is_ok())
		{
			$result = $this->map_http_error($response, $api_key);
			$result = $this->explain_credential_rejection($result, $api_key);
			$result->debug = ['fallback' => 'generateContent', 'list_models_error' => $list_error];

			return $result;
		}

		$result = ai_result::ok('');
		$result->http_status = $response->status;
		$result->debug = [
			'models_found'     => 0,
			'verified_by'      => 'generateContent',
			'list_models_error' => $list_error,
			'model_verified'   => $model,
		];

		return $result;
	}

	/**
	 * L'API restituisce "models/gemini-2.5-flash"; noi lavoriamo con l'id nudo.
	 */
	protected function strip_models_prefix(string $name): string
	{
		return (strpos($name, 'models/') === 0) ? substr($name, 7) : $name;
	}

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

		return true;
	}
}
