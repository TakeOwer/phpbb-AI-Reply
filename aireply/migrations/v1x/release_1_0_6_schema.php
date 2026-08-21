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
 * Memorizza le scelte del costruttore di personalità.
 *
 * Il prompt di sistema resta la verità — è quello che viene inviato al modello,
 * e l'amministratore può riscriverlo a mano quando vuole. Ma senza ricordare
 * come è stato composto, riaprendo il bot le caselle risultano tutte vuote e non
 * si ha idea di quale ruolo e quali tratti fossero stati scelti.
 *
 * Il campo contiene una ricetta compatta, per esempio:
 *
 *     welcome|friendly,concise,silent
 */
class release_1_0_6_schema extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\salvocortesiano\aireply\migrations\v1x\release_1_0_5_schema'];
	}

	public function effectively_installed()
	{
		return isset($this->config['aireply_version'])
			&& version_compare($this->config['aireply_version'], '1.0.6', '>=');
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'aireply_bots' => [
					'persona_recipe' => ['VCHAR:255', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'aireply_bots' => ['persona_recipe'],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['aireply_version', '1.0.6']],
		];
	}
}
