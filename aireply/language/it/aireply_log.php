<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 *
 * Messaggi diagnostici e istruzioni inviate al modello.
 *
 * Questi testi finiscono in tre posti: il campo `error_message` dei job, il
 * registro dell'ACP e — nel caso di AIREPLY_SYSTEM_INSTRUCTIONS — il prompt
 * inviato all'API. Quest'ultima è la ragione per cui il file esiste: lasciare
 * quelle istruzioni cablate in italiano significherebbe che su una board
 * inglese il modello riceve comunque un prompt in italiano.
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

	// --- Istruzioni tecniche accodate al prompt di sistema del bot ------
	// Si aggiungono alla personalità definita dall'admin, non la sostituiscono.
	'AIREPLY_SYSTEM_INSTRUCTIONS' => 'Stai partecipando a una discussione su un forum. Scrivi una risposta breve e pertinente, senza superare %d caratteri. Non usare tabelle né titoli. Non ripetere il messaggio a cui stai rispondendo.',

	// Direttiva sulla lingua, tenuta separata perché dipende dalla politica
	// scelta per il singolo bot.
	'AIREPLY_SYSTEM_LANG_AUTO'  => 'Rispondi sempre nella stessa lingua del messaggio a cui stai rispondendo, anche se queste istruzioni sono scritte in un\'altra lingua.',
	'AIREPLY_SYSTEM_LANG_FIXED' => 'Rispondi sempre in %s, qualunque sia la lingua del messaggio a cui stai rispondendo.',

	// Nome della lingua nella lingua stessa. Ogni file di lingua dichiara il
	// proprio: è il modo più semplice per dire al modello in che lingua
	// scrivere senza mantenere una tabella di codici ISO.
	'AIREPLY_LANGUAGE_ENDONYM'  => 'italiano',

	// --- Motivi per cui una risposta non viene accodata ------------------
	'AIREPLY_BLOCK_GLOBAL_OFF'         => 'AI Reply è disattivata a livello globale.',
	'AIREPLY_BLOCK_BOT_POST'           => 'Il post è stato scritto da un bot.',
	'AIREPLY_BLOCK_NO_PERMISSION'      => 'L\'autore non ha il permesso di innescare risposte automatiche.',
	'AIREPLY_BLOCK_TOO_SHORT'          => 'Il post è troppo breve per generare una risposta sensata.',
	'AIREPLY_BLOCK_POSTER_RATE'        => 'L\'autore ha già innescato troppe risposte nell\'ultima ora.',
	'AIREPLY_BLOCK_BOT_DISABLED'       => 'Il bot è disattivato.',
	'AIREPLY_BLOCK_BOT_DISABLED_FORUM' => 'Il bot è disattivato in questo forum.',
	'AIREPLY_BLOCK_TRIGGER_OFF'        => 'Il bot non risponde all\'innesco «%s» in questo forum.',
	'AIREPLY_BLOCK_NO_PROVIDER'        => 'Il bot non ha provider o modello configurati.',
	'AIREPLY_BLOCK_JOB_EXISTS'         => 'Esiste già un job per questo post.',
	'AIREPLY_BLOCK_DAILY_CAP'          => 'Tetto giornaliero raggiunto in questo forum (%1$d su %2$d nelle ultime 24 ore).',
	'AIREPLY_BLOCK_COOLDOWN'           => 'Cooldown attivo in questo topic: mancano %d secondi.',
	'AIREPLY_BLOCK_GLOBAL_OFF_LATER'   => 'AI Reply è stata disattivata dopo l\'accodamento del job.',
	'AIREPLY_BLOCK_BOT_DISABLED_LATER' => 'Il bot è stato disattivato dopo l\'accodamento del job.',
	'AIREPLY_BLOCK_NO_BINDING'         => 'Il bot non è più configurato per questo forum.',

	// --- Errori del worker ----------------------------------------------
	'AIREPLY_ERR_WORKER_TIMEOUT'  => 'Il worker non ha completato il job entro il tempo previsto.',
	'AIREPLY_ERR_BOT_GONE'        => 'Il bot non esiste più.',
	'AIREPLY_ERR_TOPIC_GONE'      => 'Il topic di destinazione non esiste più.',
	'AIREPLY_ERR_BOT_USER_GONE'   => 'L\'utente configurato per il bot non esiste (user_id %d).',
	'AIREPLY_ERR_NO_REPLY_PERM'   => 'L\'utente «%1$s» non ha il permesso di rispondere nel forum %2$d. Controlla i permessi in ACP.',
	'AIREPLY_ERR_NO_KEY_JOB'      => 'Chiave API non configurata o riferimento non risolvibile.',
	'AIREPLY_ERR_NO_CONTENT'      => 'Nessun contenuto testuale da inviare al modello.',
	'AIREPLY_ERR_NO_POST_ID'      => 'La pubblicazione non ha restituito un id di post.',

	// --- Errori dei provider --------------------------------------------
	'AIREPLY_ERR_NO_MESSAGES'         => 'Nessun messaggio da inviare al modello.',
	'AIREPLY_ERR_PROVIDER_UNKNOWN'    => 'Provider «%s» non registrato.',
	'AIREPLY_ERR_NO_KEY_RESOLVE'      => 'Nessuna chiave API configurata, o riferimento non risolvibile.',
	'AIREPLY_ERR_MODELS_PARSE'        => 'Risposta inattesa dall\'endpoint dei modelli di %s.',
	'AIREPLY_ERR_MODELS_SAVE'         => 'Impossibile salvare l\'elenco modelli: %s',
	'AIREPLY_ERR_MODEL_NOT_AVAILABLE' => 'La chiave è valida, ma il modello «%s» non è disponibile per questo account.',

	'AIREPLY_ERR_REASONING_EMPTY' => 'Il budget di token è stato esaurito dal ragionamento del modello prima che producesse testo. Aumenta «Token massimi in uscita» oppure scegli un modello non di ragionamento.',
	'AIREPLY_ERR_CONTENT_FILTER'  => 'Risposta bloccata dai filtri di contenuto del provider.',
	'AIREPLY_ERR_EMPTY_RESPONSE'  => 'Il modello ha restituito una risposta vuota (motivo di terminazione: %s).',
	'AIREPLY_ERR_FINISH_UNKNOWN'  => 'non indicato',

	'AIREPLY_ERR_GEMINI_BLOCKED'    => 'Gemini ha bloccato la richiesta in ingresso (motivo: %s).',
	'AIREPLY_ERR_GEMINI_SAFETY'     => 'Gemini ha bloccato la risposta per motivi di sicurezza dei contenuti.',
	'AIREPLY_ERR_GEMINI_RECITATION' => 'Gemini ha interrotto la risposta perché riproduceva contenuto protetto da copyright.',
	'AIREPLY_ERR_GEMINI_REFUSED'    => 'Gemini ha rifiutato di rispondere (motivo: %s).',
	'AIREPLY_ERR_GEMINI_THINKING'   => 'Il ragionamento del modello ha consumato tutti i %d token disponibili senza produrre testo. Aumenta «Token massimi in uscita» oppure imposta un budget di ragionamento più basso.',
	'AIREPLY_ERR_GEMINI_AQ_KEY' => 'La tua chiave usa il nuovo formato «AQ.» che Google sta introducendo al posto di «AIza». Molti account riportano che queste chiavi vengono rifiutate dall\'endpoint REST di Gemini: non è un tuo errore di copia-incolla. Prova a generare una chiave nel formato «AIza» dalla Google Cloud Console (API e servizi › Credenziali › Crea credenziali › Chiave API, poi limitala alla Generative Language API). Su alcuni account Google non lo consente più: in quel caso il problema va segnalato al supporto Google.',
'AIREPLY_ERR_MODEL_RETIRED' => 'Il modello «%1$s» è stato chiuso da Google ai nuovi account: compare ancora nell\'elenco ma non è più utilizzabile. Cambialo nel campo «Modello» del bot. Consigliato: <code>%2$s</code>, che è un alias e punta sempre a una versione attiva. Non serve migrare ad altre API, come suggerisce il messaggio di Google.',
	'AIREPLY_ERR_MAX_TOKENS'        => 'Limite di token in uscita raggiunto prima della generazione del testo.',

	// --- Descrizione della board inviata al modello ------------------------
	'AIREPLY_CTX_BOARD'   => 'Forum:',
	'AIREPLY_CTX_CURRENT' => 'Sezione in cui stai rispondendo:',
	'AIREPLY_CTX_SECTIONS' => 'Sezioni del forum visibili a chi ti ha scritto:',
	'AIREPLY_CTX_RULE'    => 'Sulla struttura del forum usa soltanto le informazioni qui sopra. Se qualcosa non è elencato, di\' che non lo sai invece di supporlo: indicare una sezione che non esiste è peggio che ammettere di non saperlo. Le sezioni contrassegnate con · sono quelle in cui si può scrivere; le altre sono categorie che le contengono.',

	// --- Ricerca di discussioni collegate ---------------------------------
	'AIREPLY_SEARCH_HEADER' => 'Discussioni gia\' presenti sul forum che sembrano collegate alla domanda:',
	'AIREPLY_SEARCH_RULE'   => 'Di queste discussioni conosci soltanto il titolo, la sezione e la data: non sai cosa contengono. Indicale solo se sono davvero pertinenti a quanto ti e\' stato chiesto, con il loro collegamento, e non riassumerle. Se una discussione e\' vecchia dillo, perche\' potrebbe contenere informazioni superate. Se nessuna e\' pertinente, ignora questo elenco e non nominarlo.',

	// --- ACP -------------------------------------------------------------
	'AIREPLY_ERR_BAD_HASH'       => 'Token di sessione non valido. Ricarica la pagina e riprova.',
	'AIREPLY_ERR_UNKNOWN_ACTION' => 'Azione sconosciuta.',
]);
