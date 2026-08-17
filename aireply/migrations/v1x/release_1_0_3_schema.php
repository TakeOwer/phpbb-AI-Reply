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
 * Menzione dell'autore nelle risposte del bot.
 *
 * Il valore predefinito è spento di proposito: chi aggiorna non deve trovarsi
 * un comportamento nuovo — e delle notifiche in più per i propri utenti — senza
 * averlo chiesto.
 */
class release_1_0_3_schema extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\salvocortesiano\aireply\migrations\v1x\release_1_0_2_schema'];
	}

	public function effectively_installed()
	{
		return isset($this->config['aireply_version'])
			&& version_compare($this->config['aireply_version'], '1.0.3', '>=');
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'aireply_bots' => [
					'mention_poster' => ['BOOL', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'aireply_bots' => ['mention_poster'],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['aireply_version', '1.0.3']],

			/*
			 * Formato di menzione rilevato: smention | mention | plain.
			 * Vuoto significa "non ancora rilevato": il servizio lo determina
			 * al primo utilizzo interrogando il parser.
			 */
			['config.add', ['aireply_mention_format', '']],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['aireply_mention_format']],
		];
	}
}
