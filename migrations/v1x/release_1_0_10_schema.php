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
 * La colonna dello stato sotto i post diventa annullabile.
 *
 * Era TEXT NOT NULL, e MySQL non ammette un valore predefinito sui TEXT: l'unico
 * a riempirla all'inserimento era l'estensione stessa. Disattivando l'estensione
 * la colonna restava nella tabella ma nessuno la valorizzava piu', e con MySQL
 * in modalita' strict ogni nuovo messaggio falliva con l'errore 1364
 * "Field 'post_aireply_data' doesn't have a default value": il forum smetteva
 * di accettare post.
 *
 * Annullabile, la colonna non dipende piu' dall'estensione: disattivarla non
 * puo' rompere la pubblicazione. Il codice che la legge converte gia' il valore
 * in stringa, quindi NULL equivale a "nessuno stato".
 */
class release_1_0_10_schema extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\salvocortesiano\aireply\migrations\v1x\release_1_0_9_schema'];
	}

	public function effectively_installed()
	{
		return isset($this->config['aireply_version'])
			&& version_compare($this->config['aireply_version'], '1.0.10', '>=');
	}

	public function update_schema()
	{
		return [
			'change_columns' => [
				$this->table_prefix . 'posts' => [
					// null come valore predefinito = colonna che ammette NULL.
					'post_aireply_data' => ['TEXT_UNI', null],
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['aireply_version', '1.0.10']],
		];
	}
}
