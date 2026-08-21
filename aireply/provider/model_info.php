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
 * Un modello disponibile, come restituito dall'endpoint /models del provider.
 */
class model_info
{
	/** @var string Identificatore da inviare all'API */
	public $id = '';

	/** @var string Nome leggibile, se il provider lo fornisce */
	public $display_name = '';

	/** @var int Limite di token in ingresso; 0 = sconosciuto */
	public $input_limit = 0;

	/** @var int Limite di token in uscita; 0 = sconosciuto */
	public $output_limit = 0;

	/** @var bool Modello di ragionamento, con i vincoli sui parametri che ne derivano */
	public $is_reasoning = false;

	public function __construct(string $id, string $display_name = '')
	{
		$this->id = $id;
		$this->display_name = ($display_name !== '') ? $display_name : $id;
	}

	public function to_row(string $provider): array
	{
		return [
			'provider'     => $provider,
			'model_id'     => $this->id,
			'display_name' => $this->display_name,
			'input_limit'  => (int) $this->input_limit,
			'output_limit' => (int) $this->output_limit,
			'is_reasoning' => (int) $this->is_reasoning,
			'fetched_at'   => time(),
		];
	}
}
