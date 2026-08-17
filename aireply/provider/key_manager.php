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
 * Risolve il valore memorizzato nel campo `api_key` di un bot.
 *
 * Sono ammesse tre forme:
 *
 *   env:AIREPLY_OPENAI_KEY    → variabile d'ambiente (consigliato)
 *   const:AIREPLY_OPENAI_KEY  → costante definita in config.php (consigliato)
 *   sk-proj-xxxxxxxx          → valore letterale salvato in database
 *
 * Una nota onesta sul terzo caso: nel database la chiave sta in chiaro.
 * Cifrarla non servirebbe a nulla, perché la chiave di cifratura dovrebbe
 * stare sullo stesso server e chi legge il database di solito legge anche i
 * file. Invece di dare una falsa sensazione di sicurezza, l'estensione
 * documenta il limite e rende semplice la strada migliore: mettere la chiave
 * in config.php, che di norma non finisce nei backup condivisi né nei dump SQL
 * che si passano al supporto tecnico.
 */
class key_manager
{
	public const SOURCE_ENV = 'env';
	public const SOURCE_CONST = 'const';
	public const SOURCE_DB = 'db';

	/**
	 * Restituisce la chiave utilizzabile, o stringa vuota se irrisolvibile.
	 */
	public function resolve(string $stored): string
	{
		$stored = trim($stored);

		if ($stored === '')
		{
			return '';
		}

		if (strpos($stored, 'env:') === 0)
		{
			$name = substr($stored, 4);
			$value = getenv($name);

			if ($value === false && isset($_SERVER[$name]))
			{
				$value = $_SERVER[$name];
			}

			return is_string($value) ? trim($value) : '';
		}

		if (strpos($stored, 'const:') === 0)
		{
			$name = substr($stored, 6);

			return defined($name) ? trim((string) constant($name)) : '';
		}

		return $stored;
	}

	/**
	 * Da dove arriva la chiave. Serve all'ACP per spiegarlo all'admin.
	 */
	public function get_source(string $stored): string
	{
		$stored = trim($stored);

		if (strpos($stored, 'env:') === 0)
		{
			return self::SOURCE_ENV;
		}

		if (strpos($stored, 'const:') === 0)
		{
			return self::SOURCE_CONST;
		}

		return self::SOURCE_DB;
	}

	/**
	 * La chiave è configurata e risolvibile?
	 */
	public function is_resolvable(string $stored): bool
	{
		return $this->resolve($stored) !== '';
	}

	/**
	 * Versione mascherata per l'interfaccia.
	 *
	 * I riferimenti env:/const: si mostrano per intero: non sono segreti,
	 * sono puntatori, e vederli aiuta a capire la configurazione.
	 */
	public function mask(string $stored): string
	{
		$stored = trim($stored);

		if ($stored === '')
		{
			return '';
		}

		if ($this->get_source($stored) !== self::SOURCE_DB)
		{
			return $stored;
		}

		$length = strlen($stored);

		if ($length <= 8)
		{
			return str_repeat('•', $length);
		}

		return substr($stored, 0, 4) . str_repeat('•', 12) . substr($stored, -4);
	}

	/**
	 * Descrizione diagnostica della chiave, per l'interfaccia.
	 *
	 * Mostrare lunghezza e formato riconosciuto rende immediatamente evidente
	 * quando nel campo è finito qualcosa che non è una chiave API — per esempio
	 * una password inserita dal gestore password del browser.
	 *
	 * @return array{source: string, length: int, format: string, masked: string}
	 */
	public function describe(string $stored): array
	{
		$key = $this->resolve($stored);

		return [
			'source' => $this->get_source($stored),
			'length' => strlen($key),
			'format' => $this->detect_format($key),
			'masked' => $this->mask($stored),
		];
	}

	/**
	 * Riconosce il formato dal prefisso. I formati noti oggi:
	 *
	 *   sk-      OpenAI
	 *   AIza     Google, formato classico
	 *   AQ.      Google, nuovo formato "auth key"
	 */
	public function detect_format(string $key): string
	{
		if ($key === '')
		{
			return 'empty';
		}

		if (strpos($key, 'sk-') === 0)
		{
			return 'openai';
		}

		if (strpos($key, 'AIza') === 0)
		{
			return 'google-aiza';
		}

		if (strpos($key, 'AQ.') === 0)
		{
			return 'google-aq';
		}

		return 'unknown';
	}

	/**
	 * Controllo di forma, prima di sprecare una chiamata di rete.
	 * Deliberatamente permissivo: i formati delle chiavi cambiano.
	 */
	public function looks_plausible(string $provider_id, string $stored): bool
	{
		$key = $this->resolve($stored);

		if ($key === '')
		{
			return false;
		}

		if (strlen($key) < 20 || preg_match('/\s/', $key))
		{
			return false;
		}

		/*
		 * Il controllo è sul prefisso, non solo sulla lunghezza.
		 *
		 * La versione precedente accettava qualunque stringa lunga e senza
		 * spazi: una password casuale di venti caratteri la superava senza
		 * problemi. È esattamente ciò che succede quando il gestore password
		 * del browser riempie il campo da solo, e il risultato è un "chiave non
		 * valida" che arriva ore dopo dall'API e sembra colpa della chiave.
		 *
		 * I formati riconosciuti oggi:
		 *   OpenAI  sk-...
		 *   Google  AIza... (classico) oppure AQ.Ab8... (nuovo)
		 *
		 * Se un provider introduce un prefisso nuovo il controllo lo rifiuterà:
		 * per questo l'ACP offre una casella per salvare comunque, invece di
		 * bloccare del tutto.
		 */
		$format = $this->detect_format($key);

		if ($provider_id === 'openai')
		{
			return $format === 'openai';
		}

		if ($provider_id === 'gemini')
		{
			return in_array($format, ['google-aiza', 'google-aq'], true);
		}

		return true;
	}
}
