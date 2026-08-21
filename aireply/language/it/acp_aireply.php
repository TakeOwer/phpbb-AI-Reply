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

	'AIREPLY_PROVIDER'           => 'Provider',
	'AIREPLY_PROVIDER_OPENAI'    => 'OpenAI (ChatGPT)',
	'AIREPLY_PROVIDER_GEMINI'    => 'Google Gemini',

	'AIREPLY_BASE_URL'           => 'Endpoint predefinito',
	'AIREPLY_DEFAULT_MODEL'      => 'Modello predefinito',
	'AIREPLY_MODELS_CACHED'      => 'Modelli in cache',
	'AIREPLY_CACHE_STALE'        => 'da aggiornare',

	'AIREPLY_REGISTERED_PROVIDERS' => 'Provider caricati',
	'AIREPLY_PLACEHOLDER_INTRO'  => 'Installazione riuscita. Questa pagina è provvisoria: elenca i provider che il container di phpBB ha caricato correttamente. La configurazione vera e propria arriverà con la fase successiva dello sviluppo.',


	// --- Scheda Bot -------------------------------------------------
	'AIREPLY_BOTS_INTRO'        => 'Configura qui provider, chiave e modello. Il pulsante <strong>Aggiorna elenco modelli</strong> interroga direttamente l\'API e ti mostra i modelli disponibili per la tua chiave, contrassegnando con ★ quelli comparsi dall\'ultimo controllo.',

	'AIREPLY_API_KEY'           => 'Chiave API',
	'AIREPLY_API_KEY_EXPLAIN'   => 'Consigliato: <code>const:NOME</code> con la costante definita in <code>config.php</code>, oppure <code>env:NOME</code>. Il valore letterale funziona ma resta in chiaro nel database. Lascia vuoto per non modificare la chiave già salvata. Se il browser riempie il campo da solo, <strong>cancellalo</strong> prima di salvare: salveresti la credenziale sbagliata.',

	'AIREPLY_MODEL_MANUAL' => 'oppure scrivilo a mano (ha la precedenza sul menù):',
	'AIREPLY_ERR_BAD_MODEL_ID' => 'L\'identificatore di modello «%s» non è valido: sono ammessi lettere, cifre, punti, trattini e due punti.',
	'AIREPLY_ERR_METHOD_BLOCKED_HINT' => 'La chiave funziona, ma non è autorizzata a elencare i modelli. Nella Google Cloud Console apri <em>API e servizi › Credenziali</em>, seleziona la chiave e in <em>Restrizioni API</em> consenti l\'intera <em>Generative Language API</em> invece dei singoli metodi. In alternativa lascia pure così: scrivi il nome del modello nel campo manuale qui sopra e il bot funzionerà lo stesso.',
	'AIREPLY_MODEL'             => 'Modello',
	'AIREPLY_REFRESH_MODELS'    => 'Aggiorna elenco modelli',
	'AIREPLY_TEST_CONNECTION'   => 'Testa connessione',
	'AIREPLY_PRICING_PAGE'      => 'Listino ufficiale ↗',
	'AIREPLY_LAST_CHECK'        => 'ultimo controllo',
	'AIREPLY_NO_MODELS_YET'     => 'Nessun modello in cache. Inserisci la chiave e premi «Aggiorna elenco modelli».',
	'AIREPLY_CACHE_STALE_HINT'  => 'L\'elenco ha più di 24 ore: conviene aggiornarlo.',
	'AIREPLY_NEW_MODELS_BADGE'  => 'Nuovi modelli rilevati',
	'AIREPLY_REASONING_SHORT'   => 'ragionamento',
	'AIREPLY_NO_PRICE_SHORT'    => 'prezzo non impostato',

	'AIREPLY_TUNING'            => 'Parametri che influenzano il costo',
	'AIREPLY_CONTEXT_MAX_CHARS' => 'Tetto del contesto (caratteri)',
	'AIREPLY_CONTEXT_MAX_CHARS_EXPLAIN' => 'È il limite che protegge davvero la spesa. Il numero di post di contesto è un\'indicazione; questo è un tetto duro.',
	'AIREPLY_MAX_OUTPUT'        => 'Token massimi in uscita',
	'AIREPLY_MAX_OUTPUT_EXPLAIN' => 'Sui modelli di ragionamento include anche i token di pensiero, che non vengono pubblicati ma si pagano.',
	'AIREPLY_DAILY_CAP'         => 'Tetto giornaliero di risposte',
	'AIREPLY_DAILY_CAP_EXPLAIN' => 'Serve a calcolare la proiezione di spesa giornaliera e mensile.',

	// --- Consumi ----------------------------------------------------
	'AIREPLY_USAGE_SUMMARY'     => 'Consumi',
	'AIREPLY_USAGE_TODAY'       => 'Ultime 24 ore',
	'AIREPLY_USAGE_MONTH'       => 'Ultimi 30 giorni',
	'AIREPLY_REPLIES'           => 'risposte',
	'AIREPLY_COST_PARTIAL'      => 'Totale incompleto: manca il prezzo per',
	'AIREPLY_BUDGET_OF'         => 'su un budget dichiarato di',

	'AIREPLY_QUOTA_DISCLAIMER'  => 'Nota: né OpenAI né Google offrono un modo per conoscere il credito residuo tramite API. I consumi qui sopra sono la somma esatta dei token che questa estensione ha usato, non il saldo del tuo account. Per il saldo devi consultare la dashboard del provider.',

	// --- Esiti dei test ---------------------------------------------
	'AIREPLY_SERVICE_BLOCKED' => 'Questa chiave non è autorizzata alla Generative Language API: sono bloccati sia l\'elenco dei modelli sia la generazione. Non è una restrizione sui singoli metodi, quindi il campo manuale non serve a nulla finché non risolvi lato Google. Messaggio originale: %s',
	'AIREPLY_ERR_KEY_SERVICE_BLOCKED' => 'Google ha risposto <code>API_KEY_SERVICE_BLOCKED</code>. Significa che l\'elenco delle API consentite su questa chiave non include la Generative Language API. Non significa che l\'API sia disabilitata sul progetto: quello sarebbe <code>SERVICE_DISABLED</code>. Rimedio: nella Google Cloud Console, sulla chiave, imposta <strong>Limita chiave</strong> e spunta <strong>Generative Language API</strong>. Lasciarla senza restrizioni non funziona: dal 19 giugno 2026 Google blocca le chiavi non ristrette.',
	'AIREPLY_ERR_SERVICE_BLOCKED_HINT' => '<strong>Come risolvere.</strong> Nella <em>Google Cloud Console</em>, sul progetto a cui appartiene la chiave:<br>1. <em>API e servizi › Libreria</em> → cerca «Generative Language API» → <strong>Abilita</strong>. Se risulta già abilitata, passa al punto 2.<br>2. <em>API e servizi › Credenziali</em> → apri la tua chiave → sezione <em>Restrizioni API</em> → scegli <strong>Limita chiave</strong> e spunta <strong>Generative Language API</strong>. Salva.<br><strong>Attenzione:</strong> «Non limitare la chiave» <em>non</em> è un\'alternativa valida. Dal 19 giugno 2026 Google blocca le chiamate a Gemini proprio dalle chiavi prive di restrizioni API, ed è questo che produce l\'errore <code>API_KEY_SERVICE_BLOCKED</code>.<br>3. Controlla anche <em>Restrizioni applicazione</em>: se è impostata su indirizzi IP o referrer HTTP, il tuo server verrà rifiutato. Per un forum imposta <em>Nessuna</em> oppure aggiungi l\'IP pubblico del server.<br>4. Le modifiche possono impiegare qualche minuto a propagarsi: attendi e riprova.<br><br>Verifica che il progetto sia lo stesso della chiave: è l\'errore più frequente. Se la chiave viene da Google AI Studio, la strada più rapida è generarne una nuova da lì su un progetto in cui la Gemini API è attiva.',
	'AIREPLY_MODELS_BLOCKED_OK' => 'La chiave funziona (una generazione di prova con «%s» è riuscita), ma questa chiave non è autorizzata a <em>elencare</em> i modelli, quindi il menù non può essere popolato. Non è un problema: scrivi il nome del modello nel campo qui accanto e il bot funzionerà normalmente.',
	'AIREPLY_MODELS_BLOCKED_KO' => 'Questa chiave non è autorizzata a elencare i modelli, e anche la generazione di prova è fallita: %s',
	'AIREPLY_VERSION_LABEL'     => 'Versione dell\'estensione',
	'AIREPLY_MODELS_REFRESHED'  => 'Elenco aggiornato: %d modelli disponibili.',
	'AIREPLY_MODELS_NEW_FOUND'  => 'Novità (%1$d): %2$s.',
	'AIREPLY_MODELS_REMOVED'    => 'Non più disponibili: %s.',
	'AIREPLY_TEST_OK_LIMITED' => 'Chiave valida: una generazione di prova con «%s» è andata a buon fine. L\'elenco dei modelli però non è accessibile con questa chiave.',
	'AIREPLY_TEST_OK'           => 'Connessione riuscita. %d modelli accessibili con questa chiave.',
	'AIREPLY_TEST_OK_WITH_MODEL' => 'Connessione riuscita e modello «%1$s» disponibile. %2$d modelli accessibili in totale.',

	'AIREPLY_ERR_UNKNOWN_PROVIDER' => 'Provider non riconosciuto.',
	'AIREPLY_ERR_NO_KEY'        => 'Inserisci prima una chiave API.',
	'AIREPLY_ERR_KEY_UNRESOLVED' => 'La chiave non è risolvibile: se hai usato env: o const:, verifica che il nome sia corretto e che la costante sia definita in config.php.',
	'AIREPLY_WARN_KEY_FORMAT'   => 'Attenzione: la chiave non ha il formato tipico di questo provider. Se il test è riuscito puoi ignorare l\'avviso.',

	// --- Descrizioni dei modelli ------------------------------------
	'AIREPLY_MODEL_NOTE_ECONOMY_CHAT'      => 'Modello economico e veloce, adatto a risposte brevi di forum. È la scelta sensata per un bot di accoglienza: la qualità in più dei modelli superiori raramente si nota in un messaggio di benvenuto.',
	'AIREPLY_MODEL_NOTE_BALANCED_CHAT'     => 'Modello di fascia intermedia. Buona qualità di scrittura a un costo contenuto, senza i tempi di attesa dei modelli di ragionamento.',
	'AIREPLY_MODEL_NOTE_ECONOMY_REASONING' => 'Modello di ragionamento economico. Ragiona prima di rispondere: più accurato sulle domande complesse, ma i token di pensiero si pagano anche se non vengono pubblicati.',
	'AIREPLY_MODEL_NOTE_BALANCED_REASONING' => 'Modello di ragionamento di fascia intermedia. Ottimo compromesso quando il forum riceve domande tecniche a cui il bot deve rispondere davvero.',
	'AIREPLY_MODEL_NOTE_PREMIUM_REASONING' => 'Modello di punta. Costa molto più dei modelli minori e per le risposte di un forum è quasi sempre sovradimensionato: valuta se la differenza si noterà davvero.',
	'AIREPLY_MODEL_NOTE_BALANCED_THINKING' => 'Modello Flash con ragionamento attivo. Veloce ed economico, ma attenzione al budget di token in uscita: il ragionamento lo consuma prima del testo visibile.',
	'AIREPLY_MODEL_NOTE_PREMIUM_THINKING'  => 'Modello Pro con ragionamento esteso. Il più capace della famiglia e il più costoso; per un bot di forum è raramente necessario.',
	'AIREPLY_MODEL_NOTE_LEGACY'            => 'Modello di generazione precedente, mantenuto per compatibilità. Esistono alternative più recenti a costo simile o inferiore.',
	'AIREPLY_MODEL_NOTE_OPEN_MODEL'        => 'Modello aperto servito tramite la stessa API. Meno capace dei modelli principali ma spesso disponibile a costo ridotto o nullo.',
	'AIREPLY_MODEL_NOTE_LATEST_ALIAS' => 'Alias che punta sempre all\'ultima versione Flash-Lite disponibile. È la scelta più sicura per un bot di forum: economico, veloce, e non smette di funzionare quando Google dismette una versione puntuale.',
	'AIREPLY_MODEL_NOTE_UNKNOWN'           => 'Modello non ancora catalogato da questa estensione: probabilmente è uscito dopo il rilascio. Funziona comunque; il prezzo va inserito a mano e conviene verificarne le caratteristiche sul sito del provider.',

	// --- Avvertenze contestuali -------------------------------------
	'AIREPLY_NOTE_REASONING_TOKENS' => 'Questo modello ragiona prima di rispondere, e i token di pensiero rientrano nel limite di %d token in uscita. Se il limite è troppo basso il modello consuma tutto il budget ragionando e restituisce una risposta vuota. Per questa famiglia conviene stare sopra i 2000.',
	'AIREPLY_NOTE_NO_TEMPERATURE'   => 'Questo modello non accetta il parametro «temperatura»: il campo verrà ignorato e il valore non influirà sulle risposte.',
	'AIREPLY_NOTE_CONTEXT_TOO_BIG'  => 'Il tetto del contesto impostato corrisponde a circa %1$d token, ma questo modello ne accetta al massimo %2$d in ingresso. Riduci il tetto, altrimenti le richieste verranno rifiutate.',
	'AIREPLY_NOTE_NO_PRICE'         => 'Prezzo non impostato per questo modello, quindi le stime di costo non sono disponibili. Puoi inserirlo dopo averlo verificato sul <a href="%s" target="_blank" rel="noopener">listino ufficiale</a>.',
	'AIREPLY_NOTE_SEED_PRICE'       => 'Il prezzo mostrato è un valore indicativo risalente a %1$s e non viene aggiornato automaticamente. Verificalo sul <a href="%2$s" target="_blank" rel="noopener">listino ufficiale</a> e correggilo: i listini cambiano spesso.',
	'AIREPLY_NOTE_NEW_MODEL'        => 'Questo modello è comparso dall\'ultimo aggiornamento dell\'elenco. Se è recente, verifica caratteristiche e prezzo prima di metterlo in produzione.',
	'AIREPLY_NOTE_PREMIUM_TIER'     => 'Fascia di punta: per le risposte brevi di un forum il rapporto fra costo e beneficio è spesso sfavorevole rispetto ai modelli intermedi.',

	// --- Stringhe usate dal JavaScript ------------------------------
	'AIREPLY_JS_WORKING'      => 'Attendi…',
	'AIREPLY_JS_FAILED'       => 'Richiesta non riuscita. Controlla la connessione del server.',
	'AIREPLY_JS_LIMITS'       => 'Limiti (ingresso / uscita):',
	'AIREPLY_JS_PRICE'        => 'Prezzo per milione di token:',
	'AIREPLY_JS_ESTIMATE'     => 'Stima di spesa (caso peggiore)',
	'AIREPLY_JS_PER_REPLY'    => 'per risposta:',
	'AIREPLY_JS_PER_DAY'      => 'al giorno, al tetto impostato:',
	'AIREPLY_JS_PER_MONTH'    => 'su 30 giorni:',
	'AIREPLY_JS_QUOTA'        => 'Consumi e quote',
	'AIREPLY_JS_SPENT_MONTH'  => 'speso negli ultimi 30 giorni:',
	'AIREPLY_JS_RATE_LIMIT'   => 'residuo nella finestra corrente:',
	'AIREPLY_JS_NO_SNAPSHOT'  => 'ancora nessun dato (il provider lo comunica dalla prima risposta)',
	'AIREPLY_JS_QUOTA_ERRORS' => 'Errori di credito esaurito negli ultimi 7 giorni:',
	'AIREPLY_JS_TOKENS'       => 'token',
	'AIREPLY_JS_UNKNOWN'      => 'non dichiarati dall\'API',
	'AIREPLY_JS_RECOMMENDED'  => 'consigliato',

	// Permesso

	// --- Lingua della risposta ---------------------------------------
	'AIREPLY_REPLY_LANGUAGE'         => 'Lingua della risposta',
	'AIREPLY_REPLY_LANGUAGE_EXPLAIN' => 'I modelli rispecchiano già da soli la lingua di chi scrive; questa impostazione serve nei casi in cui il segnale è debole (post di due parole, un link, un\'emoji) o quando vuoi comunque una lingua fissa. Resta un vincolo morbido: il modello di norma obbedisce, ma i modelli più piccoli sbandano più facilmente.',
	'AIREPLY_LANG_AUTO'              => 'Come il messaggio (consigliato)',
	'AIREPLY_LANG_AUTO_EXPLAIN'      => 'Un thread è pubblico: la risposta deve essere leggibile da chi legge la discussione, non solo da chi l\'ha scritta.',
	'AIREPLY_LANG_BOARD'             => 'Sempre nella lingua della board',
	'AIREPLY_LANG_BOARD_EXPLAIN'     => 'Utile su un forum monolingue che ogni tanto riceve messaggi in altre lingue e vuole comunque rispondere nella propria.',
	'AIREPLY_LANG_CUSTOM'            => 'Lingua fissa indicata a mano',
	'AIREPLY_LANG_CUSTOM_EXPLAIN'    => 'Scrivi il nome della lingua come lo scriveresti a una persona, per esempio «italiano», «English (UK)» o «español». Se lasci il campo vuoto si torna a «Come il messaggio».',

	// --- Elenco bot ----------------------------------------------------
	'AIREPLY_ADD_BOT'            => 'Aggiungi un bot',
	'AIREPLY_EDIT_BOT'           => 'Modifica bot',
	'AIREPLY_NO_BOTS_YET'        => 'Nessun bot configurato. Creane uno per cominciare.',
	'AIREPLY_BOT_NOT_FOUND'      => 'Bot non trovato.',
	'AIREPLY_BOT_CREATED'        => 'Bot «%s» creato. Ora assegnalo a uno o più forum nella scheda «Forum e innesco».',
	'AIREPLY_BOT_UPDATED'        => 'Bot «%s» aggiornato.',
	'AIREPLY_BOT_DELETED'        => 'Bot eliminato, insieme alle sue assegnazioni ai forum.',
	'AIREPLY_CONFIRM_DELETE_BOT' => 'Vuoi davvero eliminare questo bot? Verranno rimosse anche le sue assegnazioni ai forum. I post già pubblicati restano.',
	'AIREPLY_BOT_DISABLED_SUFFIX' => 'disattivato',
	'AIREPLY_USER_MISSING'       => 'utente inesistente',
	'AIREPLY_KEY_MISSING'        => 'chiave assente',
	'AIREPLY_KEY_CHARS'   => 'caratteri',
	'AIREPLY_KEY_SUSPECT' => 'Attenzione: questo valore non ha il formato di una chiave di questo provider. Controlla che non sia finita nel campo un\'altra credenziale.',
	'AIREPLY_KEY_FORCE'   => 'Salva la chiave anche se non ha un formato riconosciuto (usalo solo se il provider ha introdotto un prefisso nuovo)',
	'AIREPLY_ERR_KEY_IMPLAUSIBLE' => 'Il valore inserito non ha il formato di una chiave %1$s: %3$s, %2$d caratteri.<br><br>Le chiavi OpenAI iniziano con <code>sk-</code>, quelle Google con <code>AIza</code> oppure <code>AQ.</code><br><br>Causa più frequente: il gestore password del browser ha riempito il campo da solo con una credenziale salvata. Cancella il contenuto del campo, incolla la chiave a mano e riprova. Se il provider ha davvero introdotto un formato nuovo, spunta la casella «Salva comunque».',

	'AIREPLY_KEYFMT_OPENAI'      => 'formato OpenAI',
	'AIREPLY_KEYFMT_GOOGLE_AIZA' => 'formato Google classico',
	'AIREPLY_KEYFMT_GOOGLE_AQ'   => 'formato Google nuovo (AQ.)',
	'AIREPLY_KEYFMT_UNKNOWN'     => 'formato non riconosciuto',
	'AIREPLY_KEYFMT_EMPTY'       => 'nessuna chiave',
	'AIREPLY_KEY_CURRENT'        => 'Chiave attuale',
	'AIREPLY_WARN_GLOBAL_OFF'    => 'AI Reply è disattivata a livello globale: nessun bot risponderà finché non la attivi nelle «Impostazioni generali».',

	'AIREPLY_COL_BOT'      => 'Bot',
	'AIREPLY_COL_USER'     => 'Utente phpBB',
	'AIREPLY_COL_KEY'      => 'Chiave',
	'AIREPLY_COL_FORUMS'   => 'Forum',
	'AIREPLY_COL_STATUS'   => 'Attivo',
	'AIREPLY_COL_CREATED'  => 'Data',
	'AIREPLY_COL_FORUM'    => 'Forum',
	'AIREPLY_COL_POSTER'   => 'Autore',
	'AIREPLY_COL_TOKENS'   => 'Token',
	'AIREPLY_COL_DETAIL'   => 'Dettaglio',
	'AIREPLY_COL_ERROR'    => 'Errore',

	// --- Form del bot --------------------------------------------------
	'AIREPLY_IDENTITY'            => 'Identità',
	'AIREPLY_BOT_USER'            => 'Utente phpBB',
	'AIREPLY_BOT_USER_EXPLAIN'    => 'Nome di un utente registrato esistente: il bot pubblicherà con questa identità, con il suo avatar e il suo rank. Deve avere il permesso di rispondere nei forum in cui lo assegnerai. Un utente può essere associato a un solo bot. <strong>Non usare il tuo account:</strong> un bot non risponde mai ai propri post, quindi assegnandogli il tuo utente i tuoi messaggi non riceverebbero mai risposta.',
	'AIREPLY_BOT_LABEL'           => 'Etichetta interna',
	'AIREPLY_BOT_LABEL_EXPLAIN'   => 'Serve solo a te per riconoscerlo in questa pagina. Se lo lasci vuoto viene usato il nome utente.',
	'AIREPLY_BOT_ENABLED'         => 'Attivo',
	'AIREPLY_PROVIDER_CHOOSE'     => 'Quale IA usa questo bot',
	'AIREPLY_BASE_URL'            => 'Endpoint personalizzato (avanzato)',
	'AIREPLY_BASE_URL_EXPLAIN'    => '<strong>Nella quasi totalità dei casi va lasciato vuoto.</strong> Il testo grigio che vedi nel campo è solo un promemoria dell\'indirizzo che verrà usato: non è un valore da confermare. Compilalo solo se passi da un proxy o da un gateway compatibile con l\'API del provider.',

	'AIREPLY_PERSONA'                => 'Personalità e formato',
	'AIREPLY_SYSTEM_PROMPT'          => 'Prompt di sistema',
	'AIREPLY_SYSTEM_PROMPT_EXPLAIN'  => 'Definisce chi è il bot e come si comporta. Le istruzioni tecniche su lunghezza e formattazione vengono aggiunte automaticamente in coda, quindi qui puoi concentrarti sul carattere. Esempio: «Sei l\'assistente di un forum di appassionati di fotografia. Accogli i nuovi iscritti con calore e fai una domanda pertinente su ciò che hanno scritto.»',
	'AIREPLY_TEMPLATE'               => 'Template della risposta',
	'AIREPLY_TEMPLATE_EXPLAIN'       => 'Segnaposto disponibili: {response} il testo generato, {disclosure} la dichiarazione di contenuto automatico, {model} e {provider}. Se lo lasci vuoto viene usato «{response}» seguito dalla dichiarazione. La dichiarazione viene comunque aggiunta se non la includi. La menzione dell\'autore, se attiva, viene applicata al testo generato prima che il template lo avvolga.',

	'AIREPLY_CONTEXT_POSTS'          => 'Post di contesto',
	'AIREPLY_CONTEXT_POSTS_EXPLAIN'  => 'Quanti messaggi precedenti del topic passare al modello. 0 = nessuna memoria, ogni risposta è indipendente. Massimo 200, ma il tetto in caratteri qui sotto ha comunque la precedenza.',
	'AIREPLY_MAX_POST_CHARS'         => 'Lunghezza massima della risposta',
	'AIREPLY_MAX_POST_CHARS_EXPLAIN' => 'In caratteri. Viene chiesto al modello e applicato comunque come taglio finale.',
	'AIREPLY_TEMPERATURE'            => 'Temperatura',
	'AIREPLY_TEMPERATURE_EXPLAIN'    => 'Da 0 (risposte prevedibili) a 2 (più creative). Sui modelli di ragionamento il parametro non è accettato e viene omesso automaticamente.',
	'AIREPLY_THINKING_BUDGET'        => 'Budget di ragionamento',
	'AIREPLY_THINKING_BUDGET_EXPLAIN' => 'Solo Gemini. -1 lascia decidere al modello. Un valore basso riduce il costo ma può produrre risposte vuote se troppo stretto.',
	'AIREPLY_TIMEOUT'                => 'Timeout della richiesta',
	'AIREPLY_TIMEOUT_EXPLAIN'        => 'Secondi di attesa massima per la risposta dell\'API. I modelli di ragionamento possono richiedere anche un minuto.',

	'AIREPLY_ERR_NO_USERNAME'   => 'Devi indicare il nome dell\'utente phpBB che farà da bot.',
	'AIREPLY_ERR_USER_NOT_FOUND' => 'Nessun utente registrato con il nome «%s».',
	'AIREPLY_ERR_BOT_IS_YOU' => '«%s» è l\'account con cui sei collegato adesso. Un bot non risponde mai ai propri post — è la protezione che impedisce al bot di rispondersi da solo all\'infinito — quindi con questa configurazione i tuoi messaggi non riceverebbero mai risposta, e non vedresti nemmeno un errore: il job non verrebbe proprio accodato.<br><br>Crea un utente dedicato in <em>Utenti e gruppi › Gestisci utenti</em>, per esempio «Assistente», assicurati che possa rispondere nei forum interessati, e usa quello.',
	'AIREPLY_ERR_USER_TAKEN'    => 'L\'utente «%s» è già associato a un altro bot.',
	'AIREPLY_ERR_SAVE_FAILED'   => 'Salvataggio non riuscito: %s',

	// --- Scheda Forum ---------------------------------------------------
	'AIREPLY_FORUMS_INTRO'   => 'Assegna a ogni forum il bot che deve rispondere e scegli quando deve intervenire. Le categorie sono elencate ma non configurabili: non possono contenere post.<br><br><strong>Il pannello in alto e la tabella in basso fanno cose diverse.</strong> In alto configuri <em>più forum insieme</em>: scegli quelli che ti interessano, imposti bot e regole una volta sola e le applichi a tutti, lasciando intatti gli altri. In basso rifinisci il <em>singolo forum</em>, e il pulsante Invia riscrive tutte le righe in base a ciò che la tabella mostra in quel momento.',
	'AIREPLY_FORUMS_FOUND' => 'Questa tabella elenca <strong>tutti</strong> i forum della board: ne sono stati trovati %d in cui è possibile scrivere. Se te ne aspettavi di più, creali prima in «Forum › Gestisci i forum»: qui compaiono automaticamente.',
	// --- Assegnazione rapida a più forum ---------------------------------
	'AIREPLY_BULK_TITLE'   => 'Assegnazione rapida a più forum',
	'AIREPLY_BULK_EXPLAIN' => 'Applica lo stesso bot e le stesse regole a tutti i forum che selezioni in un colpo solo. I forum non selezionati restano come sono. Per rifinire il singolo forum usa la tabella qui sotto.',
	'AIREPLY_BULK_FORUMS'  => 'Forum',
	'AIREPLY_BULK_FORUMS_EXPLAIN' => 'Tieni premuto <kbd>Ctrl</kbd> (<kbd>Cmd</kbd> su Mac) per selezionarne più di uno, oppure <kbd>Maiusc</kbd> per un intervallo. Il pallino • segnala i forum che hanno già un bot assegnato.',
	'AIREPLY_BULK_BOT_EXPLAIN' => 'Scegli «Rimuovi assegnazione» per liberare i forum selezionati.',
	'AIREPLY_BULK_CHOOSE'  => '— scegli un bot —',
	'AIREPLY_ERR_NO_BOT_CHOSEN' => 'Scegli un bot dal menù prima di applicare. Se vuoi liberare i forum selezionati, scegli esplicitamente «Rimuovi assegnazione».',
	'AIREPLY_CONFIRM_BULK_REMOVE' => 'Stai per rimuovere l\'assegnazione dai forum selezionati. Procedere?',
	'AIREPLY_BULK_REMOVE'  => '— Rimuovi assegnazione —',
	'AIREPLY_BULK_APPLY'   => 'Applica ai forum selezionati',
	'AIREPLY_BULK_APPLIED' => 'Regole applicate a %d forum.',
	'AIREPLY_BULK_CLEARED' => 'Assegnazione rimossa da %d forum.',
	'AIREPLY_SELECT_ALL'   => 'Tutti',
	'AIREPLY_SELECT_NONE'  => 'Nessuno',
	'AIREPLY_SELECT_UNASSIGNED' => 'Solo quelli senza bot',
	'AIREPLY_ERR_NO_FORUM_SELECTED' => 'Non hai selezionato nessun forum.',
	'AIREPLY_NEEDS_BOT' => 'Scegli prima un bot: senza, queste impostazioni non possono essere salvate.',
	'AIREPLY_ASSIGNED_BOT'   => 'Bot assegnato',
	'AIREPLY_NO_BOT'         => '— nessun bot —',
	'AIREPLY_TRIGGERS'       => 'Quando risponde',
	'AIREPLY_LIMITS'         => 'Limiti',
	'AIREPLY_FORUMS_SAVED'   => 'Configurazione salvata: %d forum con un bot assegnato.',
	'AIREPLY_NO_BOTS_TITLE'  => 'Nessun bot disponibile',
	'AIREPLY_NO_BOTS_EXPLAIN' => 'Prima di assegnare un bot a un forum devi crearne almeno uno nella scheda «Bot».',

	'AIREPLY_TRIGGER_TOPIC'          => 'Nuovi topic',
	'AIREPLY_TRIGGER_TOPIC_EXPLAIN'  => 'Il bot risponde quando qualcuno apre una discussione. È l\'impostazione giusta per una sezione presentazioni.',
	'AIREPLY_TRIGGER_REPLY'          => 'Risposte',
	'AIREPLY_TRIGGER_REPLY_EXPLAIN'  => 'Il bot interviene a ogni messaggio del topic. Utile per una sezione di assistenza, ma su un forum vivace può diventare invadente: il cooldown serve proprio a questo.',
	'AIREPLY_TRIGGER_MENTION'        => 'Menzioni',
	'AIREPLY_TRIGGER_MENTION_EXPLAIN' => 'Il bot risponde quando viene chiamato per nome (@nomebot). Le menzioni ignorano il cooldown: se qualcuno chiama il bot esplicitamente, farlo tacere sarebbe incomprensibile.',
	'AIREPLY_DELAY_SHORT'            => 'Ritardo (s)',
	'AIREPLY_DELAY_EXPLAIN'          => 'Secondi di attesa prima di rispondere. Un piccolo ritardo rende l\'interazione più naturale e lascia spazio agli utenti umani per rispondere per primi.',
	'AIREPLY_CAP_SHORT'              => 'Max/giorno',
	'AIREPLY_CAP_EXPLAIN'            => 'Massimo di risposte al giorno in questo forum. 0 = nessun limite, sconsigliato finché non hai visto i consumi reali.',
	'AIREPLY_COOLDOWN_SHORT'         => 'Cooldown (s)',
	'AIREPLY_COOLDOWN_EXPLAIN'       => 'Intervallo minimo fra due interventi del bot nello stesso topic. Serve soprattutto con l\'innesco «Risposte».',
	'AIREPLY_TRIGGER_HELP_TITLE'     => 'Cosa significano le colonne',

	// --- Registro attività ----------------------------------------------
	'AIREPLY_JOBS_INTRO'      => 'Ogni riga è una richiesta di risposta. Se qualcosa non funziona, è qui che si capisce perché.',
	'AIREPLY_QUEUE_STATE'     => 'Stato della coda',
	'AIREPLY_QUEUE_COUNTS'    => 'Job per stato',
	'AIREPLY_STATUS_QUEUED_N'  => 'in coda',
	'AIREPLY_STATUS_RUNNING_N' => 'in corso',
	'AIREPLY_STATUS_DONE_N'    => 'completati',
	'AIREPLY_STATUS_FAILED_N'  => 'falliti',
	'AIREPLY_STATUS_SKIPPED_N' => 'saltati',
	'AIREPLY_CRON_LAST_RUN'   => 'Ultima esecuzione del worker',
	'AIREPLY_CRON_LAST_RUN_EXPLAIN' => 'Il cron di phpBB non ha un timer proprio: scatta quando qualcuno carica una pagina. Su una board poco frequentata questo è ciò che fa attendere le risposte, non il modello. Per tempi costanti configura un cron di sistema; in alternativa lancia «php bin/phpbbcli.php aireply:process» a mano.',
	'AIREPLY_CRON_NEVER'      => 'mai',
	'AIREPLY_NO_JOBS'         => 'Nessun job registrato.',
	'AIREPLY_VIEW_LOG'        => 'Diagnostica',
	'AIREPLY_VIEW_POST'       => 'Vai al post',
	'AIREPLY_CLEAR_JOBS'      => 'Svuota i job conclusi',
	'AIREPLY_JOBS_CLEARED'    => 'Job conclusi eliminati.',
	'AIREPLY_JOB_DETAIL'      => 'Job',
	'AIREPLY_JOB_NOT_FOUND'   => 'Job non trovato.',
	'AIREPLY_JOB_SUMMARY'     => 'Riepilogo',
	'AIREPLY_JOB_REQUEST'     => 'Testo inviato',
	'AIREPLY_JOB_RESPONSE'    => 'Testo ricevuto',
	'AIREPLY_JOB_LOG'         => 'Diagnostica completa',

	// --- Impostazioni generali ------------------------------------------
	'AIREPLY_SETTINGS_INTRO'  => 'Interruttore generale, comportamento della coda e limiti di spesa.',
	'AIREPLY_MASTER_SWITCH'   => 'Interruttore generale',
	'AIREPLY_ENABLED'         => 'AI Reply attiva',
	'AIREPLY_ENABLED_EXPLAIN' => 'Disattivandola nessun bot risponde più, in nessun forum, e nessun nuovo job viene accodato. È il modo più rapido per fermare tutto.',
	'AIREPLY_QUEUE_SETTINGS'  => 'Coda',
	'AIREPLY_BATCH_SIZE'      => 'Job per ciclo',
	'AIREPLY_BATCH_SIZE_EXPLAIN' => 'Quanti job elabora il worker a ogni esecuzione. Valori alti allungano la durata del singolo ciclo e rischiano il timeout PHP.',
	'AIREPLY_CRON_INTERVAL'   => 'Intervallo a riposo (secondi)',
	'AIREPLY_CRON_INTERVAL_EXPLAIN' => 'Ogni quanto il worker gira <strong>quando non c\\'è nulla da fare</strong>, per recuperare eventuali job rimasti appesi. Se invece c\\'è una risposta in attesa, il worker parte comunque entro 10 secondi indipendentemente da questo valore: alzarlo non rallenta le risposte.',
	'AIREPLY_DIAGNOSTICS'     => 'Diagnostica',
	'AIREPLY_VERBOSE_LOG'     => 'Log dettagliato',
	'AIREPLY_VERBOSE_LOG_EXPLAIN' => 'Salva anche i payload inviati e ricevuti, con le credenziali redatte. Utile per capire un problema, ma i log crescono in fretta: riportalo a No quando hai finito.',
	'AIREPLY_RETENTION'       => 'Conservazione dei job (giorni)',
	'AIREPLY_RETENTION_EXPLAIN' => 'I job conclusi più vecchi vengono eliminati automaticamente. 0 = conserva tutto.',
	'AIREPLY_BUDGET_SETTINGS' => 'Spesa',
	'AIREPLY_MONTHLY_BUDGET'  => 'Budget mensile dichiarato',
	'AIREPLY_MONTHLY_BUDGET_EXPLAIN' => 'Una soglia di riferimento che imposti tu: serve a mostrare la percentuale consumata, non a bloccare le richieste. Per fermare davvero la spesa usa i tetti giornalieri per forum. 0 = nessun budget dichiarato.',
	'AIREPLY_CURRENCY'        => 'Valuta',
	'AIREPLY_SPENT_SO_FAR'    => 'Speso negli ultimi 30 giorni',
	'AIREPLY_SETTINGS_SAVED'  => 'Impostazioni salvate.',

	// --- Voci del registro amministratore di phpBB -----------------------
	'LOG_AIREPLY_BOT_SAVED'      => '<strong>AI Reply</strong>: salvato il bot «%s»',
	'LOG_AIREPLY_FORUMS_SAVED'   => '<strong>AI Reply</strong>: aggiornate le assegnazioni ai forum (%s attive)',
	'LOG_AIREPLY_SETTINGS_SAVED' => '<strong>AI Reply</strong>: impostazioni generali modificate',

	// --- Preset del prompt di sistema -----------------------------------
	// Su una riga sola: finiscono in una stringa JavaScript, e un a capo
	// letterale la spezzerebbe.
	'AIREPLY_PRESET_FILL'     => 'Riempi con un esempio:',
	'AIREPLY_PRESET_WELCOME'  => 'Accoglienza',
	'AIREPLY_PRESET_SUPPORT'  => 'Assistenza',
	'AIREPLY_PRESET_EXPERT'   => 'Esperto del settore',
	'AIREPLY_PRESET_CLEAR'    => 'Svuota',

	'AIREPLY_PRESET_WELCOME_PROMPT' => 'Sei l\'assistente di questo forum e ti occupi di accogliere chi si presenta. Saluta con calore ma senza esagerare, chiama la persona per nome se lo ha scritto, e fai una sola domanda specifica su qualcosa che ha raccontato di sé. Non elencare le regole del forum e non dare il benvenuto in modo generico: due o tre frasi che dimostrino di aver letto davvero il messaggio valgono più di un paragrafo di formule. Se il messaggio non contiene nulla di specifico, limitati a un saluto breve e a un invito a raccontare qualcosa di più.',
	'AIREPLY_PRESET_SUPPORT_PROMPT' => 'Sei l\'assistente di questo forum e aiuti chi pone domande tecniche. Rispondi in modo concreto e verificabile: se la domanda contiene abbastanza informazioni, dai la soluzione; se ne mancano, chiedi esattamente il dato che ti serve invece di elencare tutte le ipotesi possibili. Quando non sei sicuro dillo apertamente, perché una risposta sbagliata detta con sicurezza fa perdere più tempo di un "non lo so". Ricorda che altri utenti umani leggeranno e potranno correggerti: non presentarti come l\'ultima parola.',
	'AIREPLY_PRESET_EXPERT_PROMPT'  => 'Sei un membro competente di questo forum. Intervieni solo per aggiungere qualcosa di utile: un dettaglio che manca, una precisazione, un rischio che chi ha scritto non ha considerato. Non riassumere il messaggio a cui rispondi e non complimentarti per la domanda. Se non hai nulla da aggiungere oltre a ciò che è già stato detto, dillo in una riga. Tono: colloquiale e diretto, come una persona esperta che scrive di fretta ma con cura.',

	// --- Preset del template --------------------------------------------
	'AIREPLY_TPL_PLAIN'  => 'Solo risposta',
	'AIREPLY_TPL_QUOTE'  => 'Dentro una citazione',
	'AIREPLY_TPL_SIGNED' => 'Con firma del modello',

	'AIREPLY_LEAVE_EMPTY' => 'lascia vuoto',

	'AIREPLY_JS_BAD_RESPONSE' => 'Il server non ha risposto in JSON:',

	// --- Menzione dell'autore -------------------------------------------
	'AIREPLY_MENTION_POSTER'         => 'Menziona l\'autore del messaggio',
	'AIREPLY_MENTION_POSTER_EXPLAIN' => 'Quando il modello scrive il nome della persona a cui sta rispondendo, quel nome viene trasformato in una menzione. Non viene aggiunto nulla se il modello non lo usa: anteporre «@Nome» a forza darebbe a ogni risposta lo stesso incipit meccanico.<br><br><strong>Attenzione:</strong> con le menzioni attive l\'utente riceve una notifica a <em>ogni</em> risposta del bot. In una sezione presentazioni è quello che vuoi; in una di assistenza dove il bot interviene su ogni messaggio, dopo tre scambi diventa fastidioso.',
	'AIREPLY_MENTION_REDETECT'       => 'Rileva di nuovo il formato',
	'AIREPLY_MENTION_DETECTED'       => 'Rilevamento eseguito. Formato in uso: <strong>%s</strong>.',

	'AIREPLY_MENTION_NO_EXT'  => 'L\'estensione <em>Simple mentions</em> non risulta attiva: il nome verrà scritto come <code>@Nome</code> in chiaro, senza notifica né collegamento al profilo. Si legge comunque bene.',
	'AIREPLY_MENTION_NO_TAG'  => 'L\'estensione <em>Simple mentions</em> è attiva, ma il parser non accetta nessuno dei suoi BBCode. Il nome verrà scritto come <code>@Nome</code> in chiaro. Prova a premere «Rileva di nuovo il formato» dopo aver verificato che l\'estensione sia configurata correttamente.',
	'AIREPLY_MENTION_OK'      => 'Rilevato il formato <strong>[%s]</strong>: la menzione produrrà anche la notifica e il collegamento al profilo.',
	'AIREPLY_MENTION_NO_PERM' => '<strong>Però l\'utente del bot non ha il permesso «Può menzionare» (<code>u_can_mention</code>).</strong> Senza quel permesso la menzione viene scritta ma la notifica non parte. Assegnalo in <em>Permessi › Permessi utente</em>.',

	// --- Più bot per forum ------------------------------------------------
	'AIREPLY_NO_BOT_HERE'    => 'Nessun bot in questo forum.',
	'AIREPLY_ADD_BOT_HERE'   => '+ aggiungi un bot a questo forum…',
	'AIREPLY_WARN_MULTI_AUTO' => 'Più di un bot risponde in automatico in questo forum: ogni messaggio può generare più risposte e più chiamate API. Se le personalità aggiuntive devono intervenire solo quando chiamate, lascia loro il solo innesco «Menzioni».',

	'AIREPLY_BULK_MODE'          => 'Modo di applicazione',
	'AIREPLY_BULK_MODE_EXPLAIN'  => 'Con più bot per forum «applica» è ambiguo: meglio dirlo esplicitamente che cancellare configurazioni per sbaglio.',
	'AIREPLY_BULK_MODE_ADD'      => 'Aggiungi ai bot già presenti',
	'AIREPLY_BULK_MODE_REPLACE'  => 'Sostituisci tutti i bot dei forum selezionati',

	'AIREPLY_MAX_BOTS_PER_POST'  => 'Bot che rispondono allo stesso messaggio',
	'AIREPLY_MAX_BOTS_PER_POST_EXPLAIN' => 'Quanti bot possono rispondere automaticamente allo stesso messaggio, quando in un forum ne è assegnato più di uno. Vale solo per gli innesco «Nuovi topic» e «Risposte»: <strong>le menzioni esplicite non sono mai limitate</strong>, perché chi chiama tre bot per nome si aspetta tre risposte. 0 = nessun limite, sconsigliato.',

	// --- Creazione dell'utente del bot ------------------------------------
	'AIREPLY_NEW_USER'        => 'Crea un utente per il bot',
	'AIREPLY_NEW_USER_LINK'   => 'Non hai un utente da usare? Creane uno →',
	'AIREPLY_NEW_USER_INTRO'  => 'phpBB non permette di creare utenti dall\'ACP senza un\'estensione apposita. Da qui puoi crearne uno adatto a fare da bot, senza installare nient\'altro.<br><br>L\'utente che verrà creato è diverso da uno normale: password casuale e mai mostrata, così nessuno può accedere come lui; messaggi privati disattivati, perché un bot che li accumula senza rispondere è una promessa non mantenuta; escluso dalle email di massa; attivo subito, senza email di conferma.',
	'AIREPLY_NEW_USER_NAME_EXPLAIN'  => 'È il nome che gli utenti vedranno nei post e useranno per chiamarlo con @nome. Valgono le stesse regole della registrazione.',
	'AIREPLY_NEW_USER_EMAIL_EXPLAIN' => 'phpBB richiede un indirizzo, ma il bot non lo userà mai. Se lasci il campo vuoto ne viene generato uno sul dominio della board.',
	'AIREPLY_NEW_USER_GROUP_EXPLAIN' => 'Facoltativo. L\'utente entra comunque nel gruppo Registrati; qui puoi aggiungerne un altro, per esempio per dargli un rank o un colore riconoscibile.',
	'AIREPLY_NEW_USER_NO_GROUP' => '— solo Registrati —',
	'AIREPLY_NEW_USER_NOTE'   => 'Dopo la creazione torni al form del bot con il nome già pronto. I permessi sui forum si assegnano dopo, quando avrai deciso in quali sezioni farlo rispondere.',
	'AIREPLY_USER_CREATED'    => 'Utente «%s» creato.',

	'AIREPLY_GRANT_MENTION'   => 'Concedi il permesso di menzionare',
	'AIREPLY_GRANT_MENTION_EXPLAIN' => 'Assegna a questo utente il permesso <code>u_can_mention</code>, che Simple mentions richiede per inviare le notifiche. Viene assegnato al singolo utente e non al gruppo: concederlo a tutti per far funzionare un bot sarebbe un effetto collaterale inatteso.',
	'AIREPLY_USER_PERM_FAILED' => 'L\\'utente è stato creato, ma l\\'assegnazione dei permessi non è riuscita: %s<br>Puoi riprovare con «Concedi i permessi mancanti» dall\\'elenco dei bot.',
	'AIREPLY_USER_GRANTED_MENTION' => 'Permesso di menzionare concesso.',
	'AIREPLY_USER_GRANTED_FORUMS'  => 'Permessi di lettura e risposta concessi in %d forum.',

	// --- Verifica dei permessi ---------------------------------------------
	'AIREPLY_COL_PERMS'            => 'Permessi',
	'AIREPLY_PERMS_MISSING_MENTION' => 'manca «può menzionare»',
	'AIREPLY_PERMS_MISSING_FORUMS'  => 'non può rispondere in',
	'AIREPLY_PERMS_FIX'            => 'Concedi i permessi mancanti',
	'AIREPLY_PERMS_ALREADY_OK'     => 'Nessun permesso da concedere: il bot ha già tutto ciò che gli serve.',

	'AIREPLY_ERR_NO_REGISTERED_GROUP' => 'Il gruppo speciale «Registrati» non esiste su questa board: senza, phpBB non può creare utenti.',
	'AIREPLY_ERR_USER_CREATE_FAILED'  => 'phpBB non è riuscito a creare l\'utente. Controlla il registro degli errori del forum.',

	// --- Costruttore di personalità ---------------------------------------
	// Tutti i testi su una riga sola: finiscono in stringhe JavaScript, e un
	// a capo letterale le spezzerebbe.
	'AIREPLY_PB_EXPLAIN' => 'Scegli un ruolo di base e spunta i tratti che vuoi aggiungere: il prompt si compone qui sotto e resta modificabile a mano. I tratti si sommano, non si sostituiscono — puoi avere un accogliente <em>e</em> spiritoso <em>e</em> molto conciso.',
	'AIREPLY_PB_ROLE'    => 'Ruolo di base',
	'AIREPLY_PB_TRAITS'  => 'Tratti',
	'AIREPLY_PB_COMPOSE' => 'Componi il prompt',
	'AIREPLY_PB_EDITED'  => 'Il prompt è stato scritto o modificato a mano: premi «Componi il prompt» per sostituirlo con quello generato dalle scelte qui sopra.',

	'AIREPLY_PB_ROLE_FREE'      => '— nessun ruolo, scrivo io —',
	'AIREPLY_PB_ROLE_CLARIFIER' => 'Chiarificatore',
	'AIREPLY_PB_ROLE_DEVIL'     => 'Avvocato del diavolo',
	'AIREPLY_PB_ROLE_ARCHIVIST' => 'Archivista',

	'AIREPLY_PB_ROLE_CLARIFIER_TEXT' => 'Il tuo compito non è risolvere il problema ma renderlo risolvibile da altri. Individua l\'informazione che manca e chiedila: una sola, la più importante. Se la domanda è già completa dillo in una riga e non aggiungere altro, perché qualcuno risponderà. Non proporre soluzioni e non elencare le cause possibili.',
	'AIREPLY_PB_ROLE_DEVIL_TEXT'     => 'Intervieni solo per sollevare l\'obiezione che nessuno ha fatto: un rischio non considerato, un presupposto dato per scontato, un caso in cui la soluzione proposta non funziona. Una sola obiezione, formulata come domanda. Non sei ostile né polemico per posa: se non hai un\'obiezione seria, non scrivere nulla.',
	'AIREPLY_PB_ROLE_ARCHIVIST_TEXT' => 'Quando riconosci una domanda già trattata sul forum, dillo e indica che cosa cercare. Non riassumere la risposta: il valore sta nel mandare la persona alla discussione originale, dove ci sono anche i commenti e le correzioni arrivate dopo. Se non riconosci nulla di simile, non scrivere nulla.',

	'AIREPLY_PB_TRAIT_FRIENDLY'  => 'amichevole e spiritoso',
	'AIREPLY_PB_TRAIT_SKEPTIC'   => 'scettico e analitico',
	'AIREPLY_PB_TRAIT_TECHNICAL' => 'tecnico e preciso',
	'AIREPLY_PB_TRAIT_CONCISE'   => 'molto conciso',
	'AIREPLY_PB_TRAIT_HUMBLE'    => 'ammette l\'incertezza',
	'AIREPLY_PB_TRAIT_SILENT'    => 'tace se non ha nulla da dire',

	'AIREPLY_PB_TRAIT_FRIENDLY_TEXT'  => 'Tono colloquiale e caldo, con un tocco di ironia dove ci sta. L\'ironia però non deve mai sostituire il contenuto: se una battuta occupa il posto di una risposta, togli la battuta.',
	'AIREPLY_PB_TRAIT_SKEPTIC_TEXT'   => 'Metti in discussione i presupposti invece di accettarli. Quando qualcosa non torna, dillo e spiega perché in una frase. Non essere scortese: dubitare di un\'affermazione non significa dubitare della persona.',
	'AIREPLY_PB_TRAIT_TECHNICAL_TEXT' => 'Sii preciso sui dettagli tecnici: nomi esatti, versioni, percorsi. Preferisci un\'indicazione verificabile a una spiegazione generale che suona bene ma non si può seguire.',
	'AIREPLY_PB_TRAIT_CONCISE_TEXT'   => 'Non superare le tre frasi. Se ti accorgi di stare riassumendo il messaggio a cui rispondi, di ringraziare per la domanda o di chiudere con formule come «spero di esserti stato utile», togli quella parte: non aggiunge nulla.',
	'AIREPLY_PB_TRAIT_HUMBLE_TEXT'    => 'Quando non sei sicuro dillo apertamente. Su un forum una risposta sbagliata detta con sicurezza fa perdere più tempo di un «non lo so», perché qualcuno la seguirà. Ricorda che altri utenti leggeranno e potranno correggerti: non presentarti come l\'ultima parola.',
	'AIREPLY_PB_TRAIT_SILENT_TEXT'    => 'Se non hai nulla di utile da aggiungere rispetto a quanto già scritto, dillo in una riga sola oppure non rispondere affatto. Il valore di un forum sono le persone che si rispondono fra loro: una risposta esauriente e superflua toglie a tutti il motivo di intervenire.',

	'AIREPLY_COL_PERSONA' => 'Personalità',
	'AIREPLY_NO_PERSONA'  => 'nessun prompt impostato',

	'AIREPLY_BOARD_CONTEXT'         => 'Il bot conosce la struttura del forum',
	'AIREPLY_BOARD_CONTEXT_EXPLAIN' => 'Aggiunge al prompt il nome della board, la sezione corrente e l\'elenco delle sezioni, così il bot può rispondere a domande come «questo forum cosa tratta?» o «dove posso fare quattro chiacchiere?». Senza, il modello non ha modo di saperlo e <strong>inventa</strong>.<br><br><strong>Le sezioni elencate sono quelle leggibili da chi ha scritto il messaggio</strong>, non quelle visibili al bot: altrimenti chiunque potrebbe chiedergli l\'elenco e scoprire l\'esistenza di aree riservate.<br><br>Costa qualche decina di token per chiamata su una board piccola, fino a un paio di migliaia su una con molte sezioni. L\'elenco si ferma a 60 voci.',
	'AIREPLY_BOARD_CONTEXT_EMPTY' => 'Nessuna descrizione da inviare: la board non ha un nome configurato e non ci sono sezioni leggibili con i tuoi permessi. Con questa impostazione accesa il modello non riceverebbe nulla di aggiuntivo.',
	'AIREPLY_BOARD_CONTEXT_PREVIEW' => 'Mostra cosa riceverà il modello (con i tuoi permessi)',

	// --- Ricerca di discussioni collegate ---------------------------------
	'AIREPLY_SEARCH_RESULTS'         => 'Discussioni collegate da cercare',
	'AIREPLY_SEARCH_RESULTS_EXPLAIN' => 'Quando qualcuno scrive, l\'estensione cerca nell\'indice gia\' presente sulla board le discussioni collegate e ne passa al modello <strong>titolo, sezione, data e collegamento</strong>. Mai il contenuto dei messaggi: con i titoli il bot puo\' dire «c\'e\' una discussione che ne parla, guardala»; con i contenuti riassumerebbe risposte vecchie con la sicurezza di sempre.<br><br>Non viene costruito alcun indice nuovo e non c\'e\' nulla da attendere: si interroga quello che l\'amministratore ha gia\' configurato. Se quel backend non risponde, si ripiega su una ricerca nei titoli che non dipende da nulla.<br><br><strong>0 disattiva la funzione, ed e\' il valore predefinito.</strong> Prima di accenderla usa la prova qui sotto: e\' l\'unica parte dell\'estensione che dipende da componenti di terze parti, e conviene vedere cosa fa sulla tua board prima che finisca in una risposta vera.',
	'AIREPLY_SEARCH_PROBE'           => 'Prova la ricerca',
	'AIREPLY_SEARCH_PROBE_EXPLAIN'   => 'Scrivi una domanda come la scriverebbe un utente e premi il pulsante: vedrai quale strategia viene usata, quali parole chiave vengono estratte e quali discussioni verrebbero passate al modello. Non consuma token e non chiama alcuna API.',
	'AIREPLY_SEARCH_PROBE_RUN'       => 'Prova',
	'AIREPLY_SEARCH_PROBE_STRATEGY'  => 'Strategia usata:',
	'AIREPLY_SEARCH_PROBE_KEYWORDS'  => 'Parole cercate:',
	'AIREPLY_SEARCH_PROBE_NOTE'      => 'Nota:',
	'AIREPLY_SEARCH_PROBE_EMPTY'     => 'Nessuna discussione trovata. Con questa domanda il modello non riceverebbe nulla di aggiuntivo.',
	'AIREPLY_SEARCH_PROBE_ERROR'     => 'La ricerca ha sollevato un errore: %s',

	'AIREPLY_SEARCH_STRATEGY_BACKEND' => 'il motore di ricerca configurato sulla board',
	'AIREPLY_SEARCH_STRATEGY_TITLES'  => 'ripiego sui titoli delle discussioni',
	'AIREPLY_SEARCH_STRATEGY_NONE'    => 'nessuna ricerca eseguita',

	'AIREPLY_SEARCH_NO_KEYWORDS'      => 'nessuna parola utile nella domanda dopo aver tolto menzioni, punteggiatura e parole troppo comuni',
	'AIREPLY_SEARCH_NO_BACKEND'       => 'nessun motore di ricerca configurato sulla board',
	'AIREPLY_SEARCH_BACKEND_MISSING'  => 'il servizio «%s» non esiste su questa installazione',
	'AIREPLY_SEARCH_BACKEND_INCOMPATIBLE' => 'il backend «%s» non espone i metodi attesi',
	'AIREPLY_SEARCH_KEYWORDS_REJECTED' => 'il motore di ricerca ha rifiutato le parole chiave, probabilmente perche\' troppo corte per la sua configurazione',
	'AIREPLY_SEARCH_BACKEND_FAILED'   => 'il motore di ricerca ha sollevato un errore (%s)',

	'ACL_U_AIREPLY_TRIGGER'      => 'Può ricevere risposte automatiche dai bot IA',
]);
