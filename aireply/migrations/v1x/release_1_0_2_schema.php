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
 * Rende esplicita la politica di lingua della risposta.
 *
 * Prima era una decisione implicita nel prompt: "rispondi nella lingua del
 * messaggio". Funziona bene su una board monolingue, ma su una board mista è
 * una scelta fra tre comportamenti diversi, e non è detto che il primo sia
 * quello giusto per tutti.
 */
class release_1_0_2_schema extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\salvocortesiano\aireply\migrations\v1x\release_1_0_1_schema'];
	}

	public function effectively_installed()
	{
		return isset($this->config['aireply_version'])
			&& version_compare($this->config['aireply_version'], '1.0.2', '>=');
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'aireply_bots' => [
					// auto | board | custom
					'reply_language'        => ['VCHAR:16', 'auto'],
					// Usato solo con 'custom': il nome della lingua come lo
					// capisce un modello, es. "italiano" o "English (UK)".
					'reply_language_custom' => ['VCHAR:64', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'aireply_bots' => [
					'reply_language',
					'reply_language_custom',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['aireply_version', '1.0.2']],
		];
	}
}
