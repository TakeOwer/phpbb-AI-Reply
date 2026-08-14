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
	'AIREPLY_REQ_PHPBB'  => 'AI Reply requires phpBB %1$s or higher. Installed version: %2$s.',
	'AIREPLY_REQ_PHP'    => 'AI Reply requires PHP %1$s or higher. Installed version: %2$s.',
	'AIREPLY_REQ_CURL'   => 'AI Reply requires the PHP <strong>cURL</strong> extension, which is not enabled. Please contact your hosting provider.',
	'AIREPLY_REQ_JSON'   => 'AI Reply requires the PHP <strong>json</strong> extension, which is not enabled.',
	'AIREPLY_REQ_RANDOM' => 'AI Reply requires the <strong>random_bytes()</strong> function, which is not available.',
]);
