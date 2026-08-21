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
 * Menzione dell'autore accesa di serie, e tetto ai bot che rispondono
 * allo stesso messaggio.
 */
class release_1_0_4_schema extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\salvocortesiano\aireply\migrations\v1x\release_1_0_3_schema'];
	}

	public function effectively_installed()
	{
		return isset($this->config['aireply_version'])
			&& version_compare($this->config['aireply_version'], '1.0.4', '>=');
	}

	public function update_schema()
	{
		return [
			'change_columns' => [
				$this->table_prefix . 'aireply_bots' => [
					// Era 0: menzionare l'autore diventa il comportamento normale.
					'mention_poster' => ['BOOL', 1],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'change_columns' => [
				$this->table_prefix . 'aireply_bots' => [
					'mention_poster' => ['BOOL', 0],
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.update', ['aireply_version', '1.0.4']],

			/*
			 * Quanti bot possono rispondere allo stesso messaggio.
			 *
			 * Vale solo per gli innesco automatici (nuovi topic e risposte).
			 * Le menzioni esplicite non sono mai limitate: se un utente chiama
			 * tre bot per nome, si aspetta tre risposte, e negargliele sarebbe
			 * incomprensibile.
			 *
			 * Uno è il valore che riproduce il comportamento odierno, quindi
			 * chi aggiorna non nota alcuna differenza.
			 */
			['config.add', ['aireply_max_bots_per_post', 1]],

			/*
			 * Stato dell'estensione delle menzioni al momento dell'ultimo
			 * rilevamento. Confrontandolo con quello attuale, la cache del
			 * formato si invalida da sola quando Simple mentions viene attivata
			 * o disattivata, senza che nessuno debba ricordarsene.
			 */
			['config.add', ['aireply_mention_ext_seen', 0]],

			// I bot esistenti passano a menzionare, come i nuovi.
			['custom', [[$this, 'enable_mentions_on_existing_bots']]],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['aireply_max_bots_per_post']],
			['config.remove', ['aireply_mention_ext_seen']],
		];
	}

	/**
	 * Accende la menzione sui bot già configurati.
	 *
	 * Cambiare il valore predefinito della colonna non tocca le righe esistenti:
	 * senza questo passaggio, chi aggiorna si ritroverebbe la nuova impostazione
	 * accesa solo sui bot creati da qui in avanti, con un comportamento diverso
	 * fra bot vecchi e nuovi senza capirne il motivo.
	 */
	public function enable_mentions_on_existing_bots()
	{
		$sql = 'UPDATE ' . $this->table_prefix . 'aireply_bots
			SET mention_poster = 1
			WHERE mention_poster = 0';

		$this->db->sql_query($sql);
	}
}
