<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 *
 * Etichette dei moduli ACP.
 *
 * IMPORTANTE — il nome di questo file non è arbitrario: phpBB carica
 * automaticamente i file che iniziano per `info_acp_` quando costruisce il
 * menù dell'ACP. Le stesse identiche stringhe messe in un file con un altro
 * nome non vengono risolte, e nel menù compaiono le chiavi grezze
 * (ACP_AIREPLY_BOTS invece di "Bot").
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
	'ACP_AIREPLY_BOTS'     => 'Bot',
	'ACP_AIREPLY_FORUMS'   => 'Forum e innesco',
	'ACP_AIREPLY_JOBS'     => 'Registro attività',
	'ACP_AIREPLY_SETTINGS' => 'Impostazioni generali',
]);
