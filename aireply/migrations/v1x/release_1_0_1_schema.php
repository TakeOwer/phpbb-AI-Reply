<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\migrations\v1x;

/**
 * Aggiunge alla cache dei modelli le informazioni che servono alla guida
 * contestuale dell'ACP: prezzi, data di rilevamento, novità.
 */
class release_1_0_1_schema extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\salvocortesiano\aireply\migrations\v1x\release_1_0_0_schema'];
	}

	public function effectively_installed()
	{
		return isset($this->config['aireply_version'])
			&& version_compare($this->config['aireply_version'], '1.0.1', '>=');
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'aireply_models' => [
					/*
					 * Prezzi per milione di token.
					 *
					 * DECIMAL(12,4) e non un float: con i float le somme di
					 * importi monetari accumulano errori, e qui si moltiplicano
					 * milioni di token per frazioni di centesimo.
					 *
					 * Zero significa "non impostato", non "gratis": la guida
					 * dell'ACP lo distingue e chiede all'admin di compilarlo.
					 */
					'price_in'       => ['DECIMAL:12', 0],		// per 1.000.000 token in ingresso
					'price_out'      => ['DECIMAL:12', 0],		// per 1.000.000 token in uscita
					'price_source'   => ['VCHAR:16', ''],		// '' | seed | manual
					'price_updated'  => ['TIMESTAMP', 0],

					// Rilevamento delle novità fra un aggiornamento e l'altro.
					'first_seen'     => ['TIMESTAMP', 0],
					'is_new'         => ['BOOL', 0],
					'is_recommended' => ['BOOL', 0],

					// Famiglia e chiave di lingua per la descrizione.
					'family'         => ['VCHAR:32', ''],
					'notes_key'      => ['VCHAR:64', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'aireply_models' => [
					'price_in', 'price_out', 'price_source', 'price_updated',
					'first_seen', 'is_new', 'is_recommended', 'family', 'notes_key',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['aireply_version', '1.0.1']],

			// Budget mensile in valuta; 0 = nessun limite di spesa dichiarato.
			['config.add', ['aireply_monthly_budget', 0]],
			['config.add', ['aireply_currency', 'USD']],

			/*
			 * Istantanea degli header di rate limit dell'ultima risposta, per
			 * provider. È l'unica informazione sulle quote residue che le API
			 * espongono davvero: né OpenAI né Google offrono un endpoint per
			 * conoscere il credito rimanente.
			 */
			['config_text.add', ['aireply_ratelimit_snapshot', '{}']],

			// Ultimo aggiornamento riuscito dell'elenco modelli, per provider.
			['config_text.add', ['aireply_models_last_check', '{}']],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['aireply_monthly_budget']],
			['config.remove', ['aireply_currency']],
			['config_text.remove', ['aireply_ratelimit_snapshot']],
			['config_text.remove', ['aireply_models_last_check']],
		];
	}
}
