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
 * Ricerca di discussioni collegate.
 *
 * Nessuna colonna nuova, una sola voce di configurazione, e il valore
 * predefinito è ZERO: la funzione nasce spenta.
 *
 * È deliberato. Interrogare il backend di ricerca è l'unica parte
 * dell'estensione che dipende da componenti di terze parti con firme variabili,
 * quindi chi aggiorna non deve trovarsela addosso senza averla chiesta. Si
 * accende dall'ACP dopo averla provata con il pulsante apposito.
 */
class release_1_0_8_schema extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\salvocortesiano\aireply\migrations\v1x\release_1_0_6_schema'];
	}

	public function effectively_installed()
	{
		return isset($this->config['aireply_search_results']);
	}

	public function update_data()
	{
		return [
			['config.update', ['aireply_version', '1.0.8']],
			['config.add', ['aireply_search_results', 0]],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['aireply_search_results']],
		];
	}
}
