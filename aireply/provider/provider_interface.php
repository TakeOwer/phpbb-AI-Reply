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
 * Contratto che ogni provider IA deve rispettare.
 *
 * Aggiungere un provider in futuro significa scrivere una classe che
 * implementa questa interfaccia e aggiungere tre righe in services.yml:
 * nessun'altra parte dell'estensione va toccata.
 */
interface provider_interface
{
	/**
	 * Identificatore stabile, salvato in aireply_bots.provider.
	 * Non tradurre e non cambiare mai dopo il rilascio.
	 */
	public function get_id(): string;

	/**
	 * Chiave di lingua per il nome mostrato in ACP.
	 */
	public function get_label_key(): string;

	/**
	 * Endpoint predefinito, usato quando il bot non ne specifica uno.
	 */
	public function get_default_base_url(): string;

	/**
	 * Modello suggerito per una nuova configurazione.
	 */
	public function get_default_model(): string;

	/**
	 * Genera una risposta.
	 */
	public function generate(ai_request $request): ai_result;

	/**
	 * Verifica credenziali ed eventualmente il modello, senza generare testo.
	 *
	 * Deve essere a costo zero o quasi: l'admin lo premerà spesso.
	 */
	public function test_connection(string $api_key, string $base_url = '', string $model = ''): ai_result;

	/**
	 * Elenco dei modelli disponibili per questa chiave, adatti alla chat.
	 *
	 * @return model_info[]
	 * @throws provider_exception se la chiamata fallisce
	 */
	public function list_models(string $api_key, string $base_url = ''): array;

	/**
	 * Il modello accetta il parametro `temperature`?
	 *
	 * I modelli di ragionamento di OpenAI lo rifiutano con un 400: l'ACP usa
	 * questo metodo per disabilitare il campo invece di far scoprire l'errore
	 * al primo post di un utente.
	 */
	public function supports_temperature(string $model): bool;

	/**
	 * Il modello espone un budget di ragionamento configurabile?
	 */
	public function supports_thinking_budget(string $model): bool;
}
