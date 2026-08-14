<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 *
 * ACP module labels.
 *
 * IMPORTANT — the name of this file is not arbitrary: phpBB automatically
 * loads files starting with `info_acp_` when building the ACP menu. The exact
 * same strings placed in a differently named file are never resolved, and the
 * menu shows raw keys (ACP_AIREPLY_BOTS instead of "Bots").
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
	'ACP_AIREPLY_TITLE'    => 'AI Reply',
	'ACP_AIREPLY_BOTS'     => 'Bots',
	'ACP_AIREPLY_FORUMS'   => 'Forums and triggers',
	'ACP_AIREPLY_JOBS'     => 'Activity log',
	'ACP_AIREPLY_SETTINGS' => 'General settings',
]);
