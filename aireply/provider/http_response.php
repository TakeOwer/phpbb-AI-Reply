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

class http_response
{
	/** @var int Codice HTTP; 0 se la richiesta non è mai partita */
	public $status = 0;

	/** @var string Corpo grezzo */
	public $body = '';

	/** @var array|null Corpo decodificato, null se non era JSON valido */
	public $json = null;

	/** @var array Header di risposta, nomi in minuscolo */
	public $headers = [];

	/** @var string Errore di rete o di cURL */
	public $network_error = '';

	/** @var int Codice errore cURL */
	public $curl_errno = 0;

	/** @var int Secondi indicati dall'header Retry-After */
	public $retry_after = 0;

	/** @var int Numero di tentativi effettuati */
	public $attempts = 0;

	/** @var array Traccia dei tentativi per la diagnostica */
	public $trace = [];

	public function is_ok(): bool
	{
		return $this->network_error === '' && $this->status >= 200 && $this->status < 300;
	}

	public function failed_before_sending(): bool
	{
		return $this->network_error !== '';
	}

	/**
	 * Estrae un valore annidato dal JSON con notazione a punti.
	 * Evita catene di isset() lunghe come un giorno senza pane.
	 */
	public function get(string $path, $default = null)
	{
		if (!is_array($this->json))
		{
			return $default;
		}

		$node = $this->json;

		foreach (explode('.', $path) as $segment)
		{
			if (is_array($node) && array_key_exists($segment, $node))
			{
				$node = $node[$segment];
			}
			else
			{
				return $default;
			}
		}

		return $node;
	}
}
