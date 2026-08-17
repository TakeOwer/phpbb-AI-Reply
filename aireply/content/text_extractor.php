<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\content;

use phpbb\textformatter\utils_interface;

/**
 * Converte il testo di un post nel testo semplice da mandare al modello.
 *
 * Da phpBB 3.2 il campo `post_text` contiene XML prodotto da s9e/TextFormatter,
 * non BBCode. Passarlo così com'è al modello significa sprecare token in tag e
 * confonderlo; `unparse()` riporta al BBCode sorgente, poi ripuliamo noi.
 */
class text_extractor
{
	/** @var utils_interface */
	protected $utils;

	public function __construct(utils_interface $utils)
	{
		$this->utils = $utils;
	}

	/**
	 * Da post_text (XML o testo semplice) a testo leggibile.
	 *
	 * @param string $post_text  contenuto del campo post_text
	 * @param bool   $drop_quotes rimuove i blocchi [quote] (predefinito: sì)
	 */
	public function to_plain_text(string $post_text, bool $drop_quotes = true): string
	{
		if (trim($post_text) === '')
		{
			return '';
		}

		$text = $post_text;

		if ($drop_quotes)
		{
			/*
			 * Le citazioni si eliminano prima di tutto il resto. In un thread
			 * dove ognuno cita il precedente, tenerle significa mandare al
			 * modello la stessa frase cinque volte: costa e non aggiunge nulla,
			 * perché la cronologia gliela stiamo già passando per conto nostro.
			 */
			try
			{
				$text = $this->utils->remove_bbcode($text, 'quote', 0);
			}
			catch (\Exception $e)
			{
				// Se il testo non è XML valido proseguiamo con la pulizia a regex.
			}
		}

		try
		{
			$text = $this->utils->unparse($text);
		}
		catch (\Exception $e)
		{
			// Post antichi o già in testo semplice: nessuna conversione necessaria.
		}

		return $this->strip_bbcode($text);
	}

	/**
	 * Rimuove i tag BBCode residui conservando il contenuto utile.
	 */
	public function strip_bbcode(string $text): string
	{
		// Le citazioni eventualmente sopravvissute, contenuto compreso.
		$text = preg_replace('#\[quote(?:=[^\]]*)?\].*?\[/quote\]#is', ' ', $text) ?? $text;

		// Gli allegati non hanno testo utile.
		$text = preg_replace('#\[attachment=\d+\].*?\[/attachment\]#is', ' ', $text) ?? $text;

		// [url=http://...]etichetta[/url] → etichetta (http://...)
		$text = preg_replace('#\[url=([^\]]+)\](.*?)\[/url\]#is', '$2 ($1)', $text) ?? $text;
		$text = preg_replace('#\[url\](.*?)\[/url\]#is', '$1', $text) ?? $text;

		// Le immagini diventano un segnaposto: l'URL nudo non dice nulla al modello.
		$text = preg_replace('#\[img\].*?\[/img\]#is', '[immagine]', $text) ?? $text;

		// Le menzioni conservano il nome, che è l'unica informazione utile al
		// modello: l'id numerico non gli dice nulla e costa token.
		$text = preg_replace('#\[smention[^\]]*\](.*?)\[/smention\]#is', '@$1', $text) ?? $text;
		$text = preg_replace('#\[mention[^\]]*\](.*?)\[/mention\]#is', '@$1', $text) ?? $text;

		// Tutti gli altri tag: via il tag, resta il contenuto.
		$text = preg_replace('#\[/?[a-z0-9\*]+(?:[=:][^\]]*)?\]#is', '', $text) ?? $text;

		// Entità HTML e spaziatura.
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
		$text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

		return trim($text);
	}

	/**
	 * Il post menziona questo utente?
	 *
	 * Copre le tre forme che si incontrano su una board reale: il BBCode
	 * dell'estensione Simple Mentions, il vecchio tag [mention] e la scrittura
	 * a mano "@nomeutente".
	 */
	public function mentions_user(string $post_text, int $user_id, string $username): bool
	{
		if ($username === '')
		{
			return false;
		}

		$id = (int) $user_id;

		/*
		 * Corrispondenza per id utente: è la forma affidabile, perché non teme
		 * omonimie, maiuscole o nomi rinominati.
		 *
		 * I formati coperti sono quelli delle estensioni di menzione più
		 * diffuse. Da phpBB 3.2 il testo che arriva qui è l'XML memorizzato,
		 * non il BBCode sorgente, quindi si controllano entrambe le forme: il
		 * tag <MENTION> prodotto dal parser e il BBCode grezzo, che sopravvive
		 * nei post in testo semplice e nelle installazioni dove quel BBCode non
		 * è registrato.
		 */
		$by_id = [
			// Simple Mentions: [smention u=42] · <SMENTION u="42">
			'/<?\[?SMENTION[^>\]]*\bu=["\']?' . $id . '\b/i',

			// Tag <MENTION> con le varianti di attributo più comuni
			'/<MENTION[^>]*\b(?:u|user_id|id)=["\']?' . $id . '\b/i',

			// [mention=42] · [mention="42"] · [mention u=42]
			'/\[mention[\s=]+["\']?(?:u=)?' . $id . '\b/i',
		];

		foreach ($by_id as $pattern)
		{
			if (preg_match($pattern, $post_text))
			{
				return true;
			}
		}

		$quoted = preg_quote($username, '/');

		// Corrispondenza per nome: meno affidabile ma necessaria per i formati
		// che non riportano l'id.
		if (preg_match('/\[mention\]\s*' . $quoted . '\s*\[\/mention\]/i', $post_text))
		{
			return true;
		}

		if (preg_match('/<MENTION[^>]*>\s*@?' . $quoted . '\s*<\/MENTION>/i', $post_text))
		{
			return true;
		}

		// "@nome" scritto a mano. Il confine finale evita che "@Anna" corrisponda
		// anche a un utente di nome "Ann".
		return (bool) preg_match('/(?:^|[\s\(\[>])@' . $quoted . '(?![\w\-])/iu', $post_text);
	}

	/**
	 * Tronca a un numero di caratteri senza spezzare una parola a metà.
	 */
	public function truncate(string $text, int $max_chars, string $suffix = '…'): string
	{
		if ($max_chars <= 0 || mb_strlen($text) <= $max_chars)
		{
			return $text;
		}

		$cut = mb_substr($text, 0, $max_chars);
		$last_space = mb_strrpos($cut, ' ');

		if ($last_space !== false && $last_space > $max_chars * 0.7)
		{
			$cut = mb_substr($cut, 0, $last_space);
		}

		return rtrim($cut) . $suffix;
	}
}
