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
 * Esito di una chiamata a un provider.
 *
 * La tassonomia degli errori è deliberatamente piccola e comune ai provider:
 * è ciò che permette al worker di decidere se ritentare senza sapere con chi
 * sta parlando.
 */
class ai_result
{
	/** Chiave assente, non valida o priva dei permessi necessari. Non ritentare. */
	public const ERR_AUTH = 'auth';

	/** Quota o rate limit. Ritentare con backoff. */
	public const ERR_RATE_LIMIT = 'rate_limit';

	/** Modello inesistente o non accessibile con questa chiave. Non ritentare. */
	public const ERR_MODEL = 'model';

	/** Richiesta malformata o parametro non supportato dal modello. Non ritentare. */
	public const ERR_REQUEST = 'request';

	/** Contenuto bloccato dai filtri del provider. Non ritentare. */
	public const ERR_SAFETY = 'safety';

	/** Errore lato provider (5xx) o timeout di rete. Ritentare. */
	public const ERR_TRANSIENT = 'transient';

	/** Risposta ricevuta ma non interpretabile. Non ritentare. */
	public const ERR_PARSE = 'parse';

	/**
	 * La credenziale è valida ma non è autorizzata a questa specifica
	 * operazione. Non ritentare: va cambiata la configurazione della chiave.
	 *
	 * Caso tipico: una chiave Google ristretta a livello di metodo, che
	 * consente generateContent ma non ListModels.
	 */
	public const ERR_METHOD_BLOCKED = 'method_blocked';

	/** Il modello ha esaurito il budget di token senza produrre testo. Non ritentare. */
	public const ERR_EMPTY = 'empty';

	/** @var bool */
	public $success = false;

	/** @var string Testo generato */
	public $text = '';

	/** @var string Motivo di terminazione riportato dal provider */
	public $finish_reason = '';

	/** @var int */
	public $prompt_tokens = 0;

	/** @var int */
	public $completion_tokens = 0;

	/** @var int Durata della chiamata in millisecondi */
	public $duration_ms = 0;

	/** @var string Una delle costanti ERR_* */
	public $error_code = '';

	/** @var string Messaggio leggibile, già privo di credenziali */
	public $error_message = '';

	/** @var int Codice HTTP dell'ultima risposta */
	public $http_status = 0;

	/** @var array Diagnostica redatta, finisce in aireply_jobs.log */
	public $debug = [];

	/** @var int Secondi suggeriti dal provider prima di ritentare (header Retry-After) */
	public $retry_after = 0;

	/**
	 * Limiti di frequenza riportati dagli header dell'ultima risposta.
	 *
	 * Chiavi possibili: limit_requests, remaining_requests, reset_requests,
	 * limit_tokens, remaining_tokens, reset_tokens.
	 *
	 * È l'unica informazione sulle quote che le API espongono: né OpenAI né
	 * Google offrono un endpoint per conoscere il credito residuo. Questo dice
	 * quanto resta nella finestra corrente, non quanti soldi ci sono sul conto.
	 *
	 * @var array
	 */
	public $rate_limit = [];

	public static function ok(string $text): self
	{
		$result = new self();
		$result->success = true;
		$result->text = $text;

		return $result;
	}

	public static function fail(string $code, string $message): self
	{
		$result = new self();
		$result->success = false;
		$result->error_code = $code;
		$result->error_message = $message;

		return $result;
	}

	/**
	 * Vale la pena ritentare questo errore?
	 *
	 * Ritentare un 401 significa solo bruciare tre volte lo stesso fallimento;
	 * ritentare un 429 invece di solito funziona.
	 */
	public function is_retryable(): bool
	{
		return in_array($this->error_code, [self::ERR_RATE_LIMIT, self::ERR_TRANSIENT], true);
	}
}
