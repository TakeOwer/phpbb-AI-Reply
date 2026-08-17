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
 * Il bot può conoscere la struttura della board.
 *
 * Predefinita accesa: senza, alla domanda «questo forum cosa tratta?» il
 * modello inventa. Un comportamento peggiore di quello nuovo, quindi non c'è
 * ragione di lasciarlo attivo per chi aggiorna.
 */
class release_1_0_5_schema extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\salvocortesiano\aireply\migrations\v1x\release_1_0_4_schema'];
	}

	public function effectively_installed()
	{
		return isset($this->config['aireply_version'])
			&& version_compare($this->config['aireply_version'], '1.0.5', '>=');
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'aireply_bots' => [
					'board_context' => ['BOOL', 1],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'aireply_bots' => ['board_context'],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['aireply_version', '1.0.5']],
		];
	}
}
