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
 * Riconosce la famiglia di un modello e ne descrive il comportamento.
 *
 * ── Una premessa onesta sui prezzi ────────────────────────────────────────
 *
 * I listini di OpenAI e Google cambiano più in fretta di quanto un'estensione
 * possa essere rilasciata. Al momento della scrittura, fonti diverse — tutte
 * pubblicate nell'arco di poche settimane — riportano per lo stesso modello
 * prezzi che differiscono anche di sei volte.
 *
 * Cablare un listino nel codice significherebbe quindi mostrare all'admin cifre
 * sbagliate con l'aria di essere autorevoli, che è peggio che non mostrarne.
 *
 * La scelta adottata:
 *   · i prezzi non sono cablati per modello, ma per FAMIGLIA, come valori
 *     puramente indicativi e con una data dichiarata;
 *   · ogni prezzo è modificabile dall'ACP e, una volta toccato dall'admin,
 *     diventa 'manual' e non viene più sovrascritto dagli aggiornamenti;
 *   · finché un prezzo non è confermato, la guida mostra le stime con
 *     l'avvertenza che sono indicative e il link al listino ufficiale.
 *
 * L'unica fonte affidabile resta la pagina di prezzi del provider.
 */
class model_profile
{
	/** Data a cui risalgono i valori indicativi di questo file */
	public const SEED_DATE = '2026-08';

	public const PRICING_URL = [
		openai_provider::ID => 'https://openai.com/api/pricing/',
		gemini_provider::ID => 'https://ai.google.dev/pricing',
	];

	/**
	 * Famiglie riconosciute, dalla più specifica alla più generica.
	 *
	 * `pattern`      espressione regolare sull'id del modello
	 * `tier`         economy | balanced | premium
	 * `seed_in/out`  prezzo indicativo per milione di token; null = sconosciuto
	 * `notes_key`    chiave di lingua della descrizione
	 * `recommended`  buon punto di partenza per un bot di forum
	 */
	protected const FAMILIES = [

		// ── OpenAI ───────────────────────────────────────────────────────
		[
			'provider' => openai_provider::ID,
			'pattern'  => '/^gpt-5.*nano/i',
			'family'   => 'gpt-5-nano',
			'tier'     => 'economy',
			'seed_in'  => 0.20, 'seed_out' => 1.25,
			'notes_key' => 'AIREPLY_MODEL_NOTE_ECONOMY_REASONING',
			'recommended' => true,
		],
		[
			'provider' => openai_provider::ID,
			'pattern'  => '/^gpt-5.*mini/i',
			'family'   => 'gpt-5-mini',
			'tier'     => 'balanced',
			'seed_in'  => 0.75, 'seed_out' => 4.50,
			'notes_key' => 'AIREPLY_MODEL_NOTE_BALANCED_REASONING',
			'recommended' => true,
		],
		[
			'provider' => openai_provider::ID,
			'pattern'  => '/^gpt-5/i',
			'family'   => 'gpt-5',
			'tier'     => 'premium',
			'seed_in'  => null, 'seed_out' => null,
			'notes_key' => 'AIREPLY_MODEL_NOTE_PREMIUM_REASONING',
			'recommended' => false,
		],
		[
			'provider' => openai_provider::ID,
			'pattern'  => '/^o\d/i',
			'family'   => 'o-series',
			'tier'     => 'premium',
			'seed_in'  => null, 'seed_out' => null,
			'notes_key' => 'AIREPLY_MODEL_NOTE_PREMIUM_REASONING',
			'recommended' => false,
		],
		[
			'provider' => openai_provider::ID,
			'pattern'  => '/^gpt-4o-mini|^gpt-4\.1-mini|^gpt-4\.1-nano/i',
			'family'   => 'gpt-4-mini',
			'tier'     => 'economy',
			'seed_in'  => 0.15, 'seed_out' => 0.60,
			'notes_key' => 'AIREPLY_MODEL_NOTE_ECONOMY_CHAT',
			'recommended' => true,
		],
		[
			'provider' => openai_provider::ID,
			'pattern'  => '/^gpt-4/i',
			'family'   => 'gpt-4',
			'tier'     => 'balanced',
			'seed_in'  => null, 'seed_out' => null,
			'notes_key' => 'AIREPLY_MODEL_NOTE_BALANCED_CHAT',
			'recommended' => false,
		],
		[
			'provider' => openai_provider::ID,
			'pattern'  => '/^gpt-3\.5/i',
			'family'   => 'gpt-3.5',
			'tier'     => 'economy',
			'seed_in'  => null, 'seed_out' => null,
			'notes_key' => 'AIREPLY_MODEL_NOTE_LEGACY',
			'recommended' => false,
		],

		// ── Gemini ───────────────────────────────────────────────────────
		[
			// Gli alias -latest hanno la precedenza: non scadono e non
			// richiedono di rimettere mano alla configurazione ogni volta che
			// Google dismette una versione puntuale.
			'provider' => gemini_provider::ID,
			'pattern'  => '/flash-lite-latest/i',
			'family'   => 'gemini-flash-lite-latest',
			'tier'     => 'economy',
			'seed_in'  => null, 'seed_out' => null,
			'notes_key' => 'AIREPLY_MODEL_NOTE_LATEST_ALIAS',
			'recommended' => true,
		],
		[
			'provider' => gemini_provider::ID,
			'pattern'  => '/flash-lite/i',
			'family'   => 'gemini-flash-lite',
			'tier'     => 'economy',
			'seed_in'  => null, 'seed_out' => null,
			'notes_key' => 'AIREPLY_MODEL_NOTE_ECONOMY_CHAT',
			'recommended' => true,
		],
		[
			'provider' => gemini_provider::ID,
			'pattern'  => '/flash/i',
			'family'   => 'gemini-flash',
			'tier'     => 'balanced',
			'seed_in'  => null, 'seed_out' => null,
			'notes_key' => 'AIREPLY_MODEL_NOTE_BALANCED_THINKING',
			'recommended' => true,
		],
		[
			'provider' => gemini_provider::ID,
			'pattern'  => '/pro/i',
			'family'   => 'gemini-pro',
			'tier'     => 'premium',
			'seed_in'  => null, 'seed_out' => null,
			'notes_key' => 'AIREPLY_MODEL_NOTE_PREMIUM_THINKING',
			'recommended' => false,
		],
		[
			'provider' => gemini_provider::ID,
			'pattern'  => '/^gemma/i',
			'family'   => 'gemma',
			'tier'     => 'economy',
			'seed_in'  => null, 'seed_out' => null,
			'notes_key' => 'AIREPLY_MODEL_NOTE_OPEN_MODEL',
			'recommended' => false,
		],
	];

	/**
	 * Profilo di un modello.
	 *
	 * @return array{family: string, tier: string, notes_key: string,
	 *               seed_in: ?float, seed_out: ?float, recommended: bool}
	 */
	public function describe(string $provider_id, string $model_id): array
	{
		foreach (self::FAMILIES as $entry)
		{
			if ($entry['provider'] !== $provider_id)
			{
				continue;
			}

			if (preg_match($entry['pattern'], $model_id))
			{
				return [
					'family'      => $entry['family'],
					'tier'        => $entry['tier'],
					'notes_key'   => $entry['notes_key'],
					'seed_in'     => $entry['seed_in'],
					'seed_out'    => $entry['seed_out'],
					'recommended' => $entry['recommended'],
				];
			}
		}

		// Modello mai visto prima: nessuna presunzione, nessun prezzo inventato.
		return [
			'family'      => 'unknown',
			'tier'        => 'unknown',
			'notes_key'   => 'AIREPLY_MODEL_NOTE_UNKNOWN',
			'seed_in'     => null,
			'seed_out'    => null,
			'recommended' => false,
		];
	}

	public function get_pricing_url(string $provider_id): string
	{
		return self::PRICING_URL[$provider_id] ?? '';
	}

	/**
	 * Conversione approssimativa da caratteri a token.
	 *
	 * Il rapporto reale dipende dalla lingua e dal tokenizzatore: circa 4
	 * caratteri per token in inglese, un po' meno in italiano per via degli
	 * accenti e delle parole più lunghe. Il valore serve solo a dare un ordine
	 * di grandezza, e la guida dell'ACP lo dichiara come tale.
	 */
	public const CHARS_PER_TOKEN = 3.6;

	public function chars_to_tokens(int $chars): int
	{
		return (int) ceil($chars / self::CHARS_PER_TOKEN);
	}
}
