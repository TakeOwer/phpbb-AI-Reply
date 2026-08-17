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
 * Errore sollevato dai provider per operazioni diverse dalla generazione
 * (elenco modelli, test di connessione), dove non ha senso restituire un
 * ai_result vuoto.
 *
 * Il codice è una delle costanti ai_result::ERR_*, così il chiamante può
 * decidere se ritentare senza conoscere il provider.
 */
class provider_exception extends \RuntimeException
{
	/** @var string */
	protected $error_code;

	/** @var int */
	protected $http_status;

	public function __construct(string $error_code, string $message, int $http_status = 0)
	{
		parent::__construct($message);

		$this->error_code = $error_code;
		$this->http_status = $http_status;
	}

	public function get_error_code(): string
	{
		return $this->error_code;
	}

	public function get_http_status(): int
	{
		return $this->http_status;
	}
}
