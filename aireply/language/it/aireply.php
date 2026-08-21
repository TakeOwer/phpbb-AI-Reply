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
	// Badge di stato sotto il post dell'utente
	'AIREPLY_STATUS_QUEUED'   => '%s sta per rispondere…',
	'AIREPLY_STATUS_RUNNING'  => '%s sta scrivendo una risposta…',
	'AIREPLY_STATUS_DONE'     => '%s ha risposto',
	'AIREPLY_STATUS_FAILED'   => '%s non è riuscito a rispondere',
	'AIREPLY_STATUS_SKIPPED'  => '%s non ha risposto a questo messaggio',
	'AIREPLY_STATUS_UNKNOWN'  => '%s',

	// Dichiarazione di contenuto generato automaticamente.
	// Sostituirla con qualcosa di meno esplicito è tecnicamente possibile, ma
	// l'AI Act europeo prevede obblighi di trasparenza per i contenuti sintetici
	// e gli utenti hanno diritto di sapere con chi stanno parlando.
	'AIREPLY_DISCLOSURE'      => 'Messaggio generato automaticamente da un modello di intelligenza artificiale (%s). Può contenere errori.',

	'AIREPLY_AVAILABLE_BOTS_HINT' => 'Assistenti che puoi chiamare in questa sezione:',

	// Permesso
	'ACL_U_AIREPLY_TRIGGER'   => 'Può ricevere risposte automatiche dai bot IA',
]);
