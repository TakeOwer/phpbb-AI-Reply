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

use phpbb\language\language;

/**
 * Logica comune ai provider: normalizzazione della cronologia, mappatura
 * degli errori HTTP, costruzione del log diagnostico.
 *
 * Questo è ciò che in AI Labs non esisteva: chatgpt.php e gemini.php
 * duplicavano quasi per intero lo stesso metodo process().
 */
abstract class base_provider implements provider_interface
{
	/** @var http_client */
	protected $http;

	/** @var language */
	protected $language;

	public function __construct(http_client $http, language $language)
	{
		$this->http = $http;
		$this->language = $language;

		// I messaggi d'errore finiscono nel registro dei job, che l'admin legge
		// nella propria lingua.
		$this->language->add_lang('aireply_log', 'salvocortesiano/aireply');
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_temperature(string $model): bool
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports_thinking_budget(string $model): bool
	{
		return false;
	}

	/**
	 * Endpoint effettivo: quello del bot se valorizzato, altrimenti il predefinito.
	 */
	protected function resolve_base_url(string $base_url): string
	{
		$base_url = trim($base_url);

		return rtrim($base_url !== '' ? $base_url : $this->get_default_base_url(), '/');
	}

	/**
	 * Normalizza la cronologia prima di consegnarla al provider.
	 *
	 * Tre invarianti che entrambe le API si aspettano, e che una conversazione
	 * reale di forum viola regolarmente:
	 *  - il primo turno deve essere dell'utente;
	 *  - non devono esserci due turni consecutivi dello stesso ruolo;
	 *  - i messaggi vuoti vanno eliminati.
	 *
	 * Gemini in particolare risponde 400 se il primo turno è del modello.
	 *
	 * @param  ai_message[] $messages
	 * @return ai_message[]
	 */
	protected function normalize_messages(array $messages): array
	{
		$clean = [];

		foreach ($messages as $message)
		{
			if (!$message instanceof ai_message)
			{
				continue;
			}

			if (trim($message->get_payload_text()) === '')
			{
				continue;
			}

			$clean[] = $message;
		}

		// Elimina i turni dell'assistente in testa: senza una domanda che li
		// preceda non hanno significato e fanno fallire la richiesta.
		while (!empty($clean) && $clean[0]->is_assistant())
		{
			array_shift($clean);
		}

		// Fonde i turni consecutivi dello stesso ruolo.
		$merged = [];

		foreach ($clean as $message)
		{
			$last = end($merged);

			if ($last !== false && $last->role === $message->role)
			{
				$last->text = $last->get_payload_text() . "\n\n" . $message->get_payload_text();
				$last->author = '';
				continue;
			}

			$merged[] = new ai_message($message->role, $message->get_payload_text());
		}

		return $merged;
	}

	/**
	 * Traduce un errore HTTP nella tassonomia comune.
	 *
	 * Il messaggio parte dal corpo della risposta quando c'è: "Incorrect API key
	 * provided" è infinitamente più utile di "errore 401" per chi legge il log.
	 */
	protected function map_http_error(http_response $response, string $api_key): ai_result
	{
		if ($response->failed_before_sending())
		{
			$result = ai_result::fail(
				ai_result::ERR_TRANSIENT,
				http_client::redact_text($response->network_error, $api_key)
			);
			$result->http_status = 0;

			return $result;
		}

		$detail = $this->extract_error_message($response);
		$detail = http_client::redact_text($detail, $api_key);

		switch (true)
		{
			// Va controllato prima del 403 generico: un blocco per metodo
			// arriva proprio con quel codice, ma il rimedio è tutt'altro.
			case $this->is_method_blocked($response):
				$code = ai_result::ERR_METHOD_BLOCKED;
				break;

			case $response->status === 401 || $response->status === 403:
				$code = ai_result::ERR_AUTH;
				break;

			case $response->status === 404:
				$code = ai_result::ERR_MODEL;
				break;

			case $response->status === 429:
				$code = ai_result::ERR_RATE_LIMIT;
				break;

			case $response->status >= 500:
				$code = ai_result::ERR_TRANSIENT;
				break;

			case $response->status === 400:
				// Un 400 che parla di modello è quasi sempre un id sbagliato.
				$code = (stripos($detail, 'model') !== false) ? ai_result::ERR_MODEL : ai_result::ERR_REQUEST;
				break;

			default:
				$code = ai_result::ERR_REQUEST;
		}

		$result = ai_result::fail($code, $detail !== '' ? $detail : 'HTTP ' . $response->status);
		$result->http_status = $response->status;
		$result->retry_after = $response->retry_after;

		return $result;
	}

	/**
	 * Entrambi i provider annidano il messaggio d'errore in `error.message`,
	 * ma non sempre; il fallback sul corpo grezzo troncato evita log muti.
	 */
	protected function extract_error_message(http_response $response): string
	{
		$message = $response->get('error.message');

		if (is_string($message) && $message !== '')
		{
			return $message;
		}

		$message = $response->get('message');

		if (is_string($message) && $message !== '')
		{
			return $message;
		}

		if ($response->body !== '')
		{
			return mb_substr(trim($response->body), 0, 300);
		}

		return '';
	}

	/**
	 * Diagnostica da salvare in aireply_jobs.log.
	 * Non contiene mai credenziali: gli header passano da redact_headers().
	 */
	protected function build_debug(ai_request $request, http_response $response, array $sent_headers, ?array $payload): array
	{
		$debug = [
			'provider'    => $this->get_id(),
			'model'       => $request->model,
			'http_status' => $response->status,
			'attempts'    => $response->attempts,
			'trace'       => $response->trace,
			'headers'     => http_client::redact_headers($sent_headers),
		];

		if ($request->verbose_log)
		{
			$debug['request_payload'] = $payload;
			$debug['response_body'] = mb_substr($response->body, 0, 8000);
		}

		return $debug;
	}

	/**
	 * La credenziale è valida ma bloccata su questa specifica operazione?
	 *
	 * Le chiavi Google possono essere ristrette per metodo: una chiave creata
	 * da AI Studio spesso consente generateContent ma non ListModels. Il
	 * risultato è una chiave perfettamente funzionante che fallisce il test
	 * dell'elenco modelli, e senza questa distinzione l'admin conclude che la
	 * chiave sia sbagliata e ne genera altre dieci identiche.
	 */
	protected function is_method_blocked(http_response $response): bool
	{
		$parts = [
			(string) $response->get('error.message', ''),
			(string) $response->get('error.status', ''),
			// OpenAI usa `type` e `code` dove Google usa `status` e `details`.
			(string) $response->get('error.type', ''),
			(string) $response->get('error.code', ''),
		];

		$details = $response->get('error.details');

		if (is_array($details))
		{
			foreach ($details as $detail)
			{
				if (isset($detail['reason']) && is_string($detail['reason']))
				{
					$parts[] = $detail['reason'];
				}
			}
		}

		$haystack = implode(' ', $parts);

		/*
		 * I due provider dicono la stessa cosa in modi molto diversi.
		 *
		 * Google, chiave ristretta per metodo:
		 *   "Requests to this API ... method ...ListModels are blocked"
		 *   reason: API_KEY_SERVICE_BLOCKED
		 *
		 * OpenAI, chiave di progetto con ambiti limitati:
		 *   "You have insufficient permissions for this operation.
		 *    Missing scopes: model.read"
		 *   type: insufficient_permissions
		 *
		 * Cercare solo le formulazioni di Google, come faceva la versione
		 * precedente, significava che una chiave OpenAI ristretta veniva
		 * classificata come non valida e il ripiego non scattava mai.
		 */
		$needles = [
			// Google
			'API_KEY_SERVICE_BLOCKED',
			'SERVICE_DISABLED',
			'API_KEY_HTTP_REFERRER_BLOCKED',
			'API_KEY_IP_ADDRESS_BLOCKED',
			'are blocked',
			'is blocked',
			'PERMISSION_DENIED',

			// OpenAI
			'insufficient_permissions',
			'insufficient permissions',
			'Missing scopes',
			'model.read',
			'unsupported_endpoint',
		];

		foreach ($needles as $needle)
		{
			if (stripos($haystack, $needle) !== false)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Estrae i limiti di frequenza dagli header di risposta.
	 *
	 * OpenAI li invia su ogni chiamata a /chat/completions con il prefisso
	 * `x-ratelimit-`; Google usa nomi diversi e non sempre li invia. Il metodo
	 * normalizza entrambi e restituisce un array vuoto quando non c'è nulla,
	 * senza inventare valori.
	 */
	protected function extract_rate_limit(http_response $response): array
	{
		$map = [
			'x-ratelimit-limit-requests'     => 'limit_requests',
			'x-ratelimit-remaining-requests' => 'remaining_requests',
			'x-ratelimit-reset-requests'     => 'reset_requests',
			'x-ratelimit-limit-tokens'       => 'limit_tokens',
			'x-ratelimit-remaining-tokens'   => 'remaining_tokens',
			'x-ratelimit-reset-tokens'       => 'reset_tokens',
		];

		$snapshot = [];

		foreach ($map as $header => $key)
		{
			if (isset($response->headers[$header]) && $response->headers[$header] !== '')
			{
				$snapshot[$key] = $response->headers[$header];
			}
		}

		return $snapshot;
	}

	/**
	 * Tronca la temperatura nell'intervallo accettato dal provider.
	 */
	protected function clamp_temperature(?float $value, float $min, float $max): ?float
	{
		if ($value === null)
		{
			return null;
		}

		return max($min, min($max, $value));
	}
}
