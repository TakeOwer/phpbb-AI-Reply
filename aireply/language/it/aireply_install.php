<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'AIREPLY_REQ_PHPBB'  => 'AI Reply richiede phpBB %1$s o superiore. Versione installata: %2$s.',
	'AIREPLY_REQ_PHP'    => 'AI Reply richiede PHP %1$s o superiore. Versione installata: %2$s.',
	'AIREPLY_REQ_CURL'   => 'AI Reply richiede l\'estensione PHP <strong>cURL</strong>, che non risulta attiva. Contatta il tuo provider di hosting.',
	'AIREPLY_REQ_JSON'   => 'AI Reply richiede l\'estensione PHP <strong>json</strong>, che non risulta attiva.',
	'AIREPLY_REQ_RANDOM' => 'AI Reply richiede la funzione <strong>random_bytes()</strong>, che non risulta disponibile.',
]);
