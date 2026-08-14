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
 * Wrapper cURL per le API dei provider.
 *
 * Differenze deliberate rispetto al GenericCurl di AI Labs:
 *  - i retryCodes hanno un valore predefinito sensato invece di restare vuoti;
 *  - il backoff è esponenziale e rispetta l'header Retry-After;
 *  - CURLOPT_FOLLOWLOCATION è disattivato: seguire un redirect significherebbe
 *    inoltrare l'header Authorization a un host che non abbiamo scelto noi;
 *  - le credenziali sono redatte prima di finire in qualunque log.
 */
class http_client
{
	/** @var int Timeout di connessione, distinto da quello complessivo */
	public const CONNECT_TIMEOUT = 15;

	/** @var int[] Codici HTTP per cui ritentare ha senso */
	public const RETRY_STATUS = [408, 409, 425, 429, 500, 502, 503, 504];

	/** @var string[] Header il cui valore non deve mai comparire in un log */
	public const SECRET_HEADERS = ['authorization', 'x-goog-api-key', 'api-key', 'x-api-key'];

	/** @var int */
	protected $max_attempts = 3;

	/** @var int Millisecondi di attesa base per il backoff */
	protected $backoff_base_ms = 1000;

	/** @var array Traccia dei tentativi, per la diagnostica */
	protected $trace = [];

	public function set_max_attempts(int $attempts): self
	{
		$this->max_attempts = max(1, min(5, $attempts));
		return $this;
	}

	/**
	 * Esegue una richiesta JSON con retry automatico.
	 *
	 * @param string     $method  GET o POST
	 * @param string     $url     URL assoluto https
	 * @param array      $headers ['Header-Name' => 'value']
	 * @param array|null $body    Corpo da codificare in JSON, o null
	 * @param int        $timeout Timeout complessivo in secondi
	 */
	public function request(string $method, string $url, array $headers = [], ?array $body = null, int $timeout = 90): http_response
	{
		$this->trace = [];

		if (!preg_match('#^https://#i', $url))
		{
			$response = new http_response();
			$response->network_error = 'Solo URL https sono ammessi.';
			return $response;
		}

		$payload = null;

		if ($body !== null)
		{
			$payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

			if ($payload === false)
			{
				$response = new http_response();
				$response->network_error = 'Impossibile codificare il corpo della richiesta in JSON: ' . json_last_error_msg();
				return $response;
			}
		}

		$header_lines = ['Content-Type: application/json', 'Accept: application/json'];

		foreach ($headers as $name => $value)
		{
			$header_lines[] = $name . ': ' . $value;
		}

		$attempt = 0;
		$response = new http_response();

		do
		{
			$attempt++;
			$started = microtime(true);

			$response = $this->execute($method, $url, $header_lines, $payload, $timeout);
			$response->attempts = $attempt;

			$this->trace[] = [
				'attempt'     => $attempt,
				'status'      => $response->status,
				'duration_ms' => (int) round((microtime(true) - $started) * 1000),
				'error'       => $response->network_error,
			];

			if (!$this->should_retry($response) || $attempt >= $this->max_attempts)
			{
				break;
			}

			$this->sleep_before_retry($attempt, $response->retry_after);
		}
		while (true);

		$response->trace = $this->trace;

		return $response;
	}

	/**
	 * Un singolo tentativo, senza logica di retry.
	 */
	protected function execute(string $method, string $url, array $header_lines, ?string $payload, int $timeout): http_response
	{
		$response = new http_response();
		$raw_headers = [];

		$curl = curl_init();

		$options = [
			CURLOPT_URL             => $url,
			CURLOPT_RETURNTRANSFER  => true,
			CURLOPT_CUSTOMREQUEST   => $method,
			CURLOPT_HTTPHEADER      => $header_lines,
			CURLOPT_TIMEOUT         => max(5, $timeout),
			CURLOPT_CONNECTTIMEOUT  => self::CONNECT_TIMEOUT,
			// Non seguire i redirect: manderemmo l'header Authorization altrove.
			CURLOPT_FOLLOWLOCATION  => false,
			CURLOPT_SSL_VERIFYPEER  => true,
			CURLOPT_SSL_VERIFYHOST  => 2,
			CURLOPT_ENCODING        => '',
			CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
			CURLOPT_HEADERFUNCTION  => static function ($ch, $header) use (&$raw_headers) {
				$parts = explode(':', $header, 2);

				if (count($parts) === 2)
				{
					$raw_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
				}

				return strlen($header);
			},
		];

		if ($payload !== null)
		{
			$options[CURLOPT_POSTFIELDS] = $payload;
		}

		curl_setopt_array($curl, $options);

		$raw_body = curl_exec($curl);
		$errno = curl_errno($curl);

		if ($errno !== 0)
		{
			$response->network_error = curl_error($curl);
			$response->curl_errno = $errno;
			curl_close($curl);

			return $response;
		}

		$response->status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$response->body = is_string($raw_body) ? $raw_body : '';
		$response->headers = $raw_headers;

		curl_close($curl);

		if (isset($raw_headers['retry-after']))
		{
			$response->retry_after = $this->parse_retry_after($raw_headers['retry-after']);
		}

		if ($response->body !== '')
		{
			$decoded = json_decode($response->body, true);
			$response->json = (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
		}

		return $response;
	}

	protected function should_retry(http_response $response): bool
	{
		if ($response->network_error !== '')
		{
			// Un timeout o un DNS che non risolve possono essere transitori.
			return true;
		}

		return in_array($response->status, self::RETRY_STATUS, true);
	}

	/**
	 * Backoff esponenziale con jitter, ma se il provider ci ha detto quanto
	 * aspettare diamo retta a lui.
	 */
	protected function sleep_before_retry(int $attempt, int $retry_after): void
	{
		if ($retry_after > 0)
		{
			sleep(min($retry_after, 30));
			return;
		}

		$delay_ms = $this->backoff_base_ms * (2 ** ($attempt - 1));
		$delay_ms += random_int(0, 250);

		usleep(min($delay_ms, 15000) * 1000);
	}

	/**
	 * Retry-After può essere un numero di secondi oppure una data HTTP.
	 */
	protected function parse_retry_after(string $value): int
	{
		$value = trim($value);

		if (is_numeric($value))
		{
			return max(0, (int) $value);
		}

		$timestamp = strtotime($value);

		return ($timestamp !== false) ? max(0, $timestamp - time()) : 0;
	}

	/**
	 * Sostituisce i valori degli header sensibili prima di scriverli in un log.
	 */
	public static function redact_headers(array $headers): array
	{
		$safe = [];

		foreach ($headers as $name => $value)
		{
			$safe[$name] = in_array(strtolower($name), self::SECRET_HEADERS, true)
				? '[redatto]'
				: $value;
		}

		return $safe;
	}

	/**
	 * Rimuove eventuali chiavi API finite in un URL o in un messaggio di errore.
	 * Rete di sicurezza: noi le chiavi nell'URL non ce le mettiamo mai.
	 */
	public static function redact_text(string $text, string ...$secrets): string
	{
		foreach ($secrets as $secret)
		{
			if ($secret !== '' && strlen($secret) > 8)
			{
				$text = str_replace($secret, '[redatto]', $text);
			}
		}

		return preg_replace('/([?&](?:key|api_key|access_token)=)[^&\s]+/i', '$1[redatto]', $text);
	}
}
