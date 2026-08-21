<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\usage;

use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use salvocortesiano\aireply\provider\manager as provider_manager;
use salvocortesiano\aireply\provider\model_profile;

/**
 * Consumi e stime di costo.
 *
 * ── Perché non mostriamo il credito residuo ───────────────────────────────
 *
 * La domanda naturale è "quanti token mi restano". Non è rispondibile: né
 * OpenAI né Google espongono un endpoint ufficiale per il saldo residuo. Su
 * OpenAI esistono endpoint di utilizzo, ma richiedono chiavi con privilegi
 * amministrativi e non sono la stessa cosa del credito rimanente; Google non
 * offre nulla di analogo per la Gemini API.
 *
 * Quello che si può sapere davvero è tre cose, e sono quelle che mostriamo:
 *
 *  1. quanto abbiamo consumato NOI — la somma dei token dei job, che è esatta
 *     perché la riportano le API a ogni risposta;
 *  2. quanto resta nella finestra di frequenza corrente — dagli header
 *     `x-ratelimit-*`, che dicono richieste e token residui al minuto, non il
 *     saldo del conto;
 *  3. quando il credito è finito davvero — l'errore `insufficient_quota`,
 *     che arriva a posteriori ma è inequivocabile.
 *
 * Il budget mensile è quindi una soglia che l'admin dichiara qui, non un dato
 * letto dal provider. È una scelta consapevole: meglio uno strumento che
 * funziona e lo dichiara, che un numero apparentemente autorevole e sbagliato.
 */
class usage_tracker
{
	/** @var driver_interface */
	protected $db;

	/** @var config */
	protected $config;

	/** @var provider_manager */
	protected $providers;

	/** @var model_profile */
	protected $profile;

	/** @var string */
	protected $jobs_table;

	/** @var string */
	protected $bots_table;

	public function __construct(
		driver_interface $db,
		config $config,
		provider_manager $providers,
		model_profile $profile,
		string $jobs_table,
		string $bots_table
	) {
		$this->db = $db;
		$this->config = $config;
		$this->providers = $providers;
		$this->profile = $profile;
		$this->jobs_table = $jobs_table;
		$this->bots_table = $bots_table;
	}

	/**
	 * Token consumati in una finestra temporale, raggruppati per bot.
	 *
	 * @return array[] righe con bot_id, provider, model, jobs, prompt_tokens,
	 *                 completion_tokens
	 */
	public function get_consumption(int $since): array
	{
		$sql_array = [
			'SELECT'    => 'j.bot_id, b.provider, b.model, '
						 . 'COUNT(j.job_id) AS jobs, '
						 . 'SUM(j.prompt_tokens) AS prompt_tokens, '
						 . 'SUM(j.completion_tokens) AS completion_tokens',
			'FROM'      => [$this->jobs_table => 'j'],
			'LEFT_JOIN' => [
				[
					'FROM' => [$this->bots_table => 'b'],
					'ON'   => 'b.bot_id = j.bot_id',
				],
			],
			'WHERE'     => 'j.created_at >= ' . (int) $since . '
				AND j.prompt_tokens > 0',
			'GROUP_BY'  => 'j.bot_id, b.provider, b.model',
		];

		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows ?: [];
	}

	/**
	 * Riepilogo dei consumi su una finestra, con costo stimato.
	 *
	 * @return array{jobs: int, prompt_tokens: int, completion_tokens: int,
	 *               cost: float, cost_known: bool, unpriced: string[]}
	 */
	public function summarise(int $since): array
	{
		$rows = $this->get_consumption($since);

		$summary = [
			'jobs'              => 0,
			'prompt_tokens'     => 0,
			'completion_tokens' => 0,
			'cost'              => 0.0,
			'cost_known'        => true,
			'unpriced'          => [],
		];

		foreach ($rows as $row)
		{
			$summary['jobs'] += (int) $row['jobs'];
			$summary['prompt_tokens'] += (int) $row['prompt_tokens'];
			$summary['completion_tokens'] += (int) $row['completion_tokens'];

			$price = $this->get_price((string) $row['provider'], (string) $row['model']);

			if ($price === null)
			{
				// Un solo modello senza prezzo rende incerto tutto il totale:
				// dirlo è più utile che presentare una somma parziale come
				// se fosse completa.
				$summary['cost_known'] = false;
				$summary['unpriced'][] = (string) $row['model'];
				continue;
			}

			$summary['cost'] += $this->compute_cost(
				(int) $row['prompt_tokens'],
				(int) $row['completion_tokens'],
				$price['in'],
				$price['out']
			);
		}

		$summary['unpriced'] = array_values(array_unique($summary['unpriced']));

		return $summary;
	}

	public function summarise_today(): array
	{
		return $this->summarise(time() - 86400);
	}

	public function summarise_month(): array
	{
		return $this->summarise(time() - (30 * 86400));
	}

	/**
	 * Stato del budget mensile dichiarato dall'admin.
	 *
	 * @return array{budget: float, spent: float, percent: int, known: bool}
	 */
	public function get_budget_status(): array
	{
		$budget = (float) $this->config['aireply_monthly_budget'];
		$month = $this->summarise_month();

		$percent = ($budget > 0)
			? (int) min(999, round(($month['cost'] / $budget) * 100))
			: 0;

		return [
			'budget'  => $budget,
			'spent'   => $month['cost'],
			'percent' => $percent,
			'known'   => $month['cost_known'],
		];
	}

	/**
	 * Prezzo di un modello, o null se non impostato.
	 *
	 * @return array{in: float, out: float, source: string}|null
	 */
	public function get_price(string $provider_id, string $model_id): ?array
	{
		if ($provider_id === '' || $model_id === '')
		{
			return null;
		}

		$row = $this->providers->get_cached_model($provider_id, $model_id);

		if ($row === null)
		{
			return null;
		}

		$in = (float) $row['price_in'];
		$out = (float) $row['price_out'];

		// Zero significa "non impostato", non "gratuito".
		if ($in <= 0 && $out <= 0)
		{
			return null;
		}

		return ['in' => $in, 'out' => $out, 'source' => (string) $row['price_source']];
	}

	/**
	 * Costo di una quantità di token, dati i prezzi per milione.
	 */
	public function compute_cost(int $prompt_tokens, int $completion_tokens, float $price_in, float $price_out): float
	{
		return (($prompt_tokens / 1000000) * $price_in)
			+ (($completion_tokens / 1000000) * $price_out);
	}

	/**
	 * Stima del costo di UNA risposta con le impostazioni correnti del bot.
	 *
	 * È deliberatamente una stima per eccesso: si assume che il contesto
	 * riempia interamente `context_max_chars` e che il modello usi tutti i
	 * token in uscita concessi. Nella pratica costerà meno, ma per decidere se
	 * un modello è sostenibile serve il caso peggiore, non quello medio.
	 *
	 * @return array{tokens_in: int, tokens_out: int, cost: ?float,
	 *               cost_day: ?float, cost_month: ?float}
	 */
	public function estimate_per_reply(string $provider_id, string $model_id, int $context_max_chars, int $max_output_tokens, int $daily_cap): array
	{
		$tokens_in = $this->profile->chars_to_tokens($context_max_chars);
		$tokens_out = max(0, $max_output_tokens);

		$estimate = [
			'tokens_in'  => $tokens_in,
			'tokens_out' => $tokens_out,
			'cost'       => null,
			'cost_day'   => null,
			'cost_month' => null,
		];

		$price = $this->get_price($provider_id, $model_id);

		if ($price === null)
		{
			return $estimate;
		}

		$per_reply = $this->compute_cost($tokens_in, $tokens_out, $price['in'], $price['out']);

		$estimate['cost'] = $per_reply;

		if ($daily_cap > 0)
		{
			$estimate['cost_day'] = $per_reply * $daily_cap;
			$estimate['cost_month'] = $per_reply * $daily_cap * 30;
		}

		return $estimate;
	}

	/**
	 * Quante volte il credito del provider è risultato esaurito di recente.
	 *
	 * È il segnale a posteriori più affidabile che abbiamo: l'API restituisce
	 * `insufficient_quota` quando il conto è a secco, ed è diverso da un
	 * normale superamento del limite di frequenza.
	 */
	public function count_quota_errors(int $since): int
	{
		$sql = 'SELECT COUNT(job_id) AS total FROM ' . $this->jobs_table . '
			WHERE created_at >= ' . (int) $since . "
				AND (error_message " . $this->db->sql_like_expression($this->db->get_any_char() . 'insufficient_quota' . $this->db->get_any_char()) . "
					OR error_message " . $this->db->sql_like_expression($this->db->get_any_char() . 'quota' . $this->db->get_any_char()) . ')';

		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		return $total;
	}

	public function get_currency(): string
	{
		$currency = (string) $this->config['aireply_currency'];

		return $currency !== '' ? $currency : 'USD';
	}

	/**
	 * Formatta un importo con la valuta configurata.
	 * Gli importi sotto il centesimo si mostrano con più decimali: una risposta
	 * può costare 0,0004 e arrotondarla a 0,00 la farebbe sembrare gratuita.
	 */
	public function format_cost(?float $amount): string
	{
		if ($amount === null)
		{
			return '—';
		}

		$decimals = ($amount > 0 && $amount < 0.01) ? 5 : 2;

		return number_format($amount, $decimals, ',', '.') . ' ' . $this->get_currency();
	}
}
