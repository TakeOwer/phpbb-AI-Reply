<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 *
 * Variante "dai del tu" dell'italiano.
 *
 * phpBB cerca le traduzioni di un'estensione nella cartella che corrisponde
 * esattamente alla lingua dell'utente. Una board che usa il pacchetto it_x_tu
 * non troverebbe nulla in language/it/ e ricadrebbe sull'inglese, dando il
 * risultato straniante di un pannello per metà tradotto: le voci del core in
 * italiano e quelle dell'estensione in inglese.
 *
 * Questo file rimanda a quello italiano invece di duplicarne il contenuto: due
 * copie dello stesso testo divergono al primo ritocco.
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

include __DIR__ . '/../it/aireply_install.php';
