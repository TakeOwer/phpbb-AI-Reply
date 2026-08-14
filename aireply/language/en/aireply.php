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
	'AIREPLY_STATUS_QUEUED'   => '%s is about to reply…',
	'AIREPLY_STATUS_RUNNING'  => '%s is writing a reply…',
	'AIREPLY_STATUS_DONE'     => '%s replied',
	'AIREPLY_STATUS_FAILED'   => '%s could not reply',
	'AIREPLY_STATUS_SKIPPED'  => '%s did not reply to this message',
	'AIREPLY_STATUS_UNKNOWN'  => '%s',

	'AIREPLY_DISCLOSURE'      => 'This message was generated automatically by an artificial intelligence model (%s). It may contain errors.',

	'ACL_U_AIREPLY_TRIGGER'   => 'Can receive automated replies from AI bots',
]);
