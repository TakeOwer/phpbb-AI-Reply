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
 * Tutto ciò che serve per una generazione, in forma neutra.
 *
 * I provider sono servizi condivisi e senza stato: le credenziali viaggiano
 * qui dentro, non nel costruttore. Così due bot con chiavi diverse possono
 * usare la stessa istanza del provider.
 */
class ai_request
{
	/** @var string Chiave già risolta da key_manager (mai il riferimento "env:...") */
	public $api_key = '';

	/** @var string Endpoint base; vuoto = predefinito del provider */
	public $base_url = '';

	/** @var string */
	public $model = '';

	/** @var string Istruzione di sistema, ovvero la "personalità" del bot */
	public $system_prompt = '';

	/** @var ai_message[] Cronologia in ordine cronologico; l'ultimo è il messaggio a cui rispondere */
	public $messages = [];

	/** @var float|null null = non inviare il parametro */
	public $temperature = null;

	/** @var int|null */
	public $max_output_tokens = null;

	/** @var int Budget di ragionamento per Gemini; -1 = decide il modello */
	public $thinking_budget = -1;

	/** @var int Timeout complessivo in secondi */
	public $timeout = 90;

	/** @var bool Se true, il log conserva anche i payload completi (redatti) */
	public $verbose_log = false;

	public function add_message(ai_message $message): self
	{
		$this->messages[] = $message;
		return $this;
	}

	/**
	 * Somma dei caratteri di tutti i messaggi, prompt di sistema incluso.
	 * Serve per il tetto `context_max_chars` prima ancora di spendere una chiamata.
	 */
	public function estimate_chars(): int
	{
		$total = mb_strlen($this->system_prompt);

		foreach ($this->messages as $message)
		{
			$total += mb_strlen($message->get_payload_text());
		}

		return $total;
	}
}
