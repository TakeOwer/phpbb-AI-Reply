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
 * Risposta immediata.
 *
 * Spenta di serie: cambia il momento in cui il lavoro viene svolto e chi
 * aggiorna non deve trovarselo addosso senza averlo scelto.
 */
class release_1_0_9_schema extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\salvocortesiano\aireply\migrations\v1x\release_1_0_8_schema'];
	}

	public function effectively_installed()
	{
		return isset($this->config['aireply_instant']);
	}

	public function update_data()
	{
		return [
			['config.update', ['aireply_version', '1.0.9']],
			['config.add', ['aireply_instant', 0]],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['aireply_instant']],
		];
	}
}
