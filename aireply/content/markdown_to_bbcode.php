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

/**
 * Converte il Markdown prodotto dai modelli in BBCode.
 *
 * I modelli linguistici scrivono in Markdown per impostazione predefinita.
 * Chiederglielo nel prompt di sistema ("rispondi in BBCode") funziona solo a
 * metà: la maggior parte scivola comunque negli asterischi dopo qualche riga.
 * Convertire in uscita è più affidabile che sperare in uscita.
 *
 * L'ordine delle sostituzioni conta: i blocchi di codice si estraggono per
 * primi e si reinseriscono per ultimi, altrimenti gli asterischi al loro
 * interno verrebbero interpretati come grassetto.
 */
class markdown_to_bbcode
{
	/** @var string[] Blocchi di codice messi da parte durante la conversione */
	protected $vault = [];

	public function convert(string $text): string
	{
		$this->vault = [];

		$text = str_replace(["\r\n", "\r"], "\n", $text);

		$text = $this->stash_code_blocks($text);
		$text = $this->convert_headings($text);
		$text = $this->convert_emphasis($text);
		$text = $this->convert_links($text);
		$text = $this->convert_lists($text);
		$text = $this->convert_quotes($text);
		$text = $this->convert_rules($text);
		$text = $this->restore_code_blocks($text);

		$text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

		return trim($text);
	}

	/**
	 * Mette al sicuro i blocchi di codice prima di toccare qualunque altra cosa.
	 */
	protected function stash_code_blocks(string $text): string
	{
		// Blocchi recintati con ``` , con o senza indicazione del linguaggio.
		$text = preg_replace_callback('/```[a-z0-9+#-]*\n(.*?)```/is', function ($m) {
			return $this->stash('[code]' . rtrim($m[1]) . '[/code]');
		}, $text) ?? $text;

		// Codice in linea. phpBB non ha un tag dedicato: si tolgono gli apici
		// inversi e si lascia il testo, che è meno brutto di un [code] in mezzo
		// a una frase.
		$text = preg_replace_callback('/`([^`\n]+)`/', function ($m) {
			return $this->stash($m[1]);
		}, $text) ?? $text;

		return $text;
	}

	protected function stash(string $content): string
	{
		$key = "\x01AIREPLY" . count($this->vault) . "\x02";
		$this->vault[$key] = $content;

		return $key;
	}

	protected function restore_code_blocks(string $text): string
	{
		if (empty($this->vault))
		{
			return $text;
		}

		return str_replace(array_keys($this->vault), array_values($this->vault), $text);
	}

	/**
	 * I titoli Markdown diventano grassetto: phpBB non ha intestazioni e un
	 * [size] grande in mezzo a un post di forum stona.
	 */
	protected function convert_headings(string $text): string
	{
		return preg_replace('/^#{1,6}\s+(.+?)\s*#*$/m', '[b]$1[/b]', $text) ?? $text;
	}

	protected function convert_emphasis(string $text): string
	{
		// Grassetto e corsivo insieme: prima il caso più lungo, altrimenti il
		// grassetto se lo mangia lasciando un asterisco orfano.
		$text = preg_replace('/\*\*\*(?!\s)(.+?)(?<!\s)\*\*\*/s', '[b][i]$1[/i][/b]', $text) ?? $text;
		$text = preg_replace('/\*\*(?!\s)(.+?)(?<!\s)\*\*/s', '[b]$1[/b]', $text) ?? $text;
		$text = preg_replace('/__(?!\s)(.+?)(?<!\s)__/s', '[b]$1[/b]', $text) ?? $text;

		// Corsivo con asterisco singolo: si richiede un confine a sinistra,
		// altrimenti "2*3*4" diventerebbe corsivo.
		$text = preg_replace('/(^|[\s\(\[])\*(?!\s)([^\*\n]+?)(?<!\s)\*(?=$|[\s\)\].,;:!?])/m', '$1[i]$2[/i]', $text) ?? $text;
		$text = preg_replace('/(^|[\s\(\[])_(?!\s)([^_\n]+?)(?<!\s)_(?=$|[\s\)\].,;:!?])/m', '$1[i]$2[/i]', $text) ?? $text;

		// Barrato.
		$text = preg_replace('/~~(?!\s)(.+?)(?<!\s)~~/s', '[s]$1[/s]', $text) ?? $text;

		return $text;
	}

	protected function convert_links(string $text): string
	{
		// Immagini prima dei link: la sintassi differisce per un solo carattere.
		$text = preg_replace('/!\[([^\]]*)\]\((https?:\/\/[^\s\)]+)\)/', '[img]$2[/img]', $text) ?? $text;

		return preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/', '[url=$2]$1[/url]', $text) ?? $text;
	}

	/**
	 * Elenchi puntati e numerati.
	 *
	 * Si lavora riga per riga perché serve sapere dove un elenco inizia e dove
	 * finisce, informazione che una singola espressione regolare non conserva.
	 */
	protected function convert_lists(string $text): string
	{
		$lines = explode("\n", $text);
		$out = [];
		$open = null;

		foreach ($lines as $line)
		{
			if (preg_match('/^\s*[-*+]\s+(.+)$/', $line, $m))
			{
				if ($open !== 'bullet')
				{
					$out[] = $this->close_list($open) . '[list]';
					$open = 'bullet';
				}

				$out[] = '[*]' . trim($m[1]);
				continue;
			}

			if (preg_match('/^\s*\d+[\.\)]\s+(.+)$/', $line, $m))
			{
				if ($open !== 'ordered')
				{
					$out[] = $this->close_list($open) . '[list=1]';
					$open = 'ordered';
				}

				$out[] = '[*]' . trim($m[1]);
				continue;
			}

			// Una riga vuota dentro un elenco non lo chiude: il Markdown
			// distanziato è comunissimo nelle risposte dei modelli.
			if ($open !== null && trim($line) === '')
			{
				$out[] = '';
				continue;
			}

			if ($open !== null)
			{
				$out[] = $this->close_list($open);
				$open = null;
			}

			$out[] = $line;
		}

		if ($open !== null)
		{
			$out[] = $this->close_list($open);
		}

		return implode("\n", $out);
	}

	protected function close_list(?string $open): string
	{
		return ($open === null) ? '' : '[/list]' . "\n";
	}

	protected function convert_quotes(string $text): string
	{
		return preg_replace('/^>\s?(.+)$/m', '[quote]$1[/quote]', $text) ?? $text;
	}

	protected function convert_rules(string $text): string
	{
		return preg_replace('/^\s*(?:---|\*\*\*|___)\s*$/m', '', $text) ?? $text;
	}
}
