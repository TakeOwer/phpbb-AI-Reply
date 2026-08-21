# AI Reply

**Risposte automatiche generate da intelligenza artificiale nei forum phpBB.**

Un utente scrive nella sezione presentazioni, e un bot lo accoglie con un messaggio
che dimostra di aver letto davvero ciò che ha scritto. Un utente pone una domanda
tecnica nella sezione assistenza, e riceve una prima risposta mentre aspetta quella
di un umano. Quale intelligenza artificiale risponde, in quale sezione e con quale
carattere lo decidi tu, forum per forum.

[![phpBB](https://img.shields.io/badge/phpBB-3.3%2B-blue)](https://www.phpbb.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--only-green)](license.txt)

[🇬🇧 English](README.md) · 🇮🇹 Italiano

---

## Indice

- [In breve](#in-breve)
- [Requisiti](#requisiti)
- [Installazione](#installazione)
- [Configurazione](#configurazione)
- [Ottenere le chiavi API](docs/api-keys.md) 🔗
- [Funzionalità](#funzionalità)
- [Come funziona](#come-funziona)
- [Sicurezza](#sicurezza)
- [Controllo della spesa](#controllo-della-spesa)
- [Trasparenza verso gli utenti](#trasparenza-verso-gli-utenti)
- [Riga di comando](#riga-di-comando)
- [Risoluzione dei problemi](#risoluzione-dei-problemi)
- [Limitazioni note](#limitazioni-note)
- [Licenza e attribuzioni](#licenza-e-attribuzioni)

---

## In breve

| | |
|---|---|
| **Provider supportati** | OpenAI (ChatGPT) · Google Gemini |
| **Configurazione** | per forum: quale bot, quando interviene, con quali limiti |
| **Il bot è** | un normale utente phpBB, con avatar, rank e profilo |
| **Elaborazione** | asincrona tramite coda; l'utente non attende mai |
| **Lingue** | italiano e inglese, complete |
| **Versione** | 1.0.2-dev |

---

## Requisiti

- **phpBB 3.3.0** o superiore
- **PHP 7.4** o superiore
- Estensione PHP **cURL** attiva
- Estensione PHP **json** attiva
- Una chiave API di OpenAI oppure di Google Gemini

L'estensione verifica questi requisiti all'attivazione e, se qualcosa manca, lo dice
esattamente invece di fallire più avanti in modo oscuro.

---

## Installazione

1. Copia la cartella `salvocortesiano/` dentro la directory `ext/` del forum.
   Il percorso finale deve essere `forum/ext/salvocortesiano/aireply/ext.php`.
2. ACP → **Personalizza** → **Gestisci estensioni** → attiva **AI Reply**.
3. Svuota la cache del forum.

---

## Configurazione

### 1. Crea l'utente del bot

ACP → **Utenti e gruppi** → **Gestisci utenti** → crea un utente dedicato, per
esempio `Assistente`. Dagli un avatar riconoscibile e verifica che abbia il
permesso di rispondere nei forum in cui lo userai.

> **Non usare il tuo account.** Un bot non risponde mai ai propri post — è la
> protezione che gli impedisce di rispondersi da solo all'infinito — quindi
> assegnandogli il tuo utente i tuoi messaggi non riceverebbero mai risposta.
> L'estensione rifiuta questa configurazione e spiega perché.

### 2. Metti la chiave API in `config.php`

> **Non hai ancora una chiave, o te ne viene rifiutata una?** Vedi
> **[Getting your API keys](docs/api-keys.md)** (in inglese): copre l'ottenimento
> della chiave da entrambi i provider e, cosa più utile, le trappole delle
> restrizioni che producono messaggi d'errore fuorvianti.

In fondo al `config.php` del forum:

```php
define('AIREPLY_OPENAI_KEY', 'sk-proj-...');
define('AIREPLY_GEMINI_KEY', 'AIza...');
```

Poi, nel campo chiave dell'ACP, scrivi `const:AIREPLY_GEMINI_KEY`.

Sono accettate tre forme:

| Forma | Significato |
|---|---|
| `const:NOME` | costante definita in `config.php` — **consigliato** |
| `env:NOME` | variabile d'ambiente — **consigliato** |
| `sk-...` | valore letterale, salvato in database |

Il valore letterale funziona, ma resta in chiaro nel database. Il vantaggio di
`config.php` è che quel file non finisce nei dump SQL che si passano al supporto
tecnico o si caricano su un forum di assistenza.

### 3. Crea il bot

ACP → **AI Reply** → **Bot** → *Aggiungi un bot*.

Scegli il provider, premi **Aggiorna elenco modelli** per caricare i modelli
realmente disponibili per la tua chiave, e usa **Testa connessione** per
verificare prima di salvare — non consuma token.

Il prompt di sistema definisce il carattere del bot. Tre esempi pronti si
inseriscono con un clic: *Accoglienza*, *Assistenza*, *Esperto del settore*.

### 4. Assegna i forum

ACP → **AI Reply** → **Forum e innesco**.

Puoi assegnare forum a forum nella tabella, oppure usare l'**assegnazione rapida**
per applicare lo stesso bot e le stesse regole a più forum in un colpo solo,
selezionandoli con `Ctrl`.

Tre innesco indipendenti per ogni forum:

| Innesco | Quando risponde |
|---|---|
| **Nuovi topic** | qualcuno apre una discussione — ideale per le presentazioni |
| **Risposte** | ogni messaggio del topic — utile per l'assistenza |
| **Menzioni** | solo se chiamato per nome (`@Assistente`) |

### 5. Attiva

ACP → **AI Reply** → **Impostazioni generali** → attiva l'interruttore principale.

### 6. (Facoltativo) Cron di sistema

L'estensione risponde entro una decina di secondi anche senza cron di sistema,
perché il worker si attiva quando c'è lavoro pronto. Per tempi costanti anche su
una board senza traffico:

```
* * * * * /usr/bin/php /percorso/forum/bin/phpbbcli.php cron:run --quiet
```

---

## Funzionalità

### Due intelligenze artificiali, configurabili separatamente

OpenAI e Google Gemini hanno ciascuno chiave, modello, endpoint e parametri
propri. Puoi far rispondere Gemini nelle presentazioni e OpenAI nell'assistenza.

Il livello provider è astratto dietro un'interfaccia: aggiungerne un terzo
significa scrivere una classe e registrarla, senza toccare il resto.

### Elenco modelli dal vivo

Il pulsante **Aggiorna elenco modelli** interroga l'API e mostra i modelli
disponibili **per la tua chiave**, non un elenco cablato nel codice che invecchia.

- I modelli comparsi dall'ultimo controllo sono contrassegnati con `★`
- Quelli non più disponibili vengono segnalati per nome
- I prezzi inseriti a mano non vengono mai sovrascritti da un aggiornamento
- Se l'elenco non è ottenibile (capita con chiavi ristrette), il modello si può
  scrivere a mano e tutto continua a funzionare

### Guida contestuale

Selezionando un modello, un pannello si aggiorna spiegando cosa fa quel modello,
quanto costa e cosa aspettarsi. Si ricalcola anche quando cambi i parametri.

Comprende le avvertenze che fanno perdere un pomeriggio se nessuno te le dice:

- **Modelli che ragionano** — i token di pensiero rientrano nel limite di uscita:
  se il limite è basso, il modello consuma tutto il budget ragionando e restituisce
  una risposta vuota
- **Parametro temperatura non supportato** su alcuni modelli, che viene omesso
  automaticamente
- **Contesto più grande della finestra del modello**, con il calcolo in token

### Più personalità nello stesso forum

Un forum può ospitare più bot, ciascuno con il proprio utente phpBB, il proprio
prompt di sistema e i propri innesco. Gli utenti scelgono a chi rivolgersi
chiamandolo per nome.

La configurazione consigliata:

| Bot | Innesco | Ruolo |
|---|---|---|
| Bot principale | Nuovi topic + Menzioni | accoglie e risponde di default |
| Personalità aggiuntive | **solo Menzioni** | rispondono solo se chiamate |

Sopra l'editor compare un elenco cliccabile dei bot disponibili in quel forum,
così nessuno deve ricordarne i nomi.

> **Attenzione ai costi.** Se più bot condividono un innesco diverso dalle
> menzioni, un solo messaggio produce più risposte e più chiamate API. Nelle
> *Impostazioni generali* c'è un tetto ai bot che possono rispondere
> automaticamente allo stesso messaggio, predefinito a 1. Le menzioni esplicite
> non sono mai limitate: chi chiama tre bot per nome si aspetta tre risposte.

I bot non si rispondono mai fra loro. Due personalità con l'innesco *Risposte*
nello stesso topic si risponderebbero all'infinito, a tue spese.

### Menzione dell'autore

Quando il modello scrive il nome della persona a cui sta rispondendo, quel nome
diventa una menzione. Non viene aggiunto nulla se il modello non lo usa:
anteporre «@Nome» a forza darebbe a ogni risposta lo stesso incipit meccanico.

Il formato non è cablato. AI Reply chiede al parser cosa accetta, quindi funziona
con **Simple mentions** di paul999 in entrambi i suoi formati — il tag
`[smention]` della versione 2.0 e il vecchio `[mention]` — e ricade su `@Nome` in
chiaro quando nessuna estensione di menzione è installata. La cache del
rilevamento si invalida da sola quando quell'estensione viene attivata o
disattivata.

Perché le notifiche partano servono due cose:

- **Simple mentions** attiva
- l'**utente del bot** deve avere il permesso `u_can_mention` — il bot, non tu

L'ACP ti dice quale delle due manca. Vedi
[Adapting mention detection](docs/adapting-mention-detection.md) se la tua board
memorizza le menzioni in un altro formato.

### Memoria della conversazione

Il bot può leggere i messaggi precedenti del topic, da 0 (nessuna memoria) fino a
200. Due tetti lavorano insieme:

- **Post di contesto** — quello che ragiona l'amministratore
- **Tetto in caratteri** — quello che protegge davvero la spesa, e vince sempre

Il secondo esiste perché cinquanta post brevi e cinquanta post lunghi costano in
modo molto diverso, e il modello fattura i token, non i messaggi. Quando il tetto
scatta vengono scartati i post più vecchi, e il registro annota quanti.

### Menzioni

Il bot risponde quando viene chiamato per nome. Il rilevamento copre i formati
delle estensioni di menzione più diffuse, con corrispondenza sull'id utente dove
disponibile, così che rinomine e nomi simili non possano confonderlo. Se la tua
board memorizza le menzioni in un formato non riconosciuto, vedi
[Adapting mention detection](docs/adapting-mention-detection.md) (in inglese).

### Lingua della risposta

Tre politiche selezionabili: rispecchia il messaggio (consigliata), sempre la
lingua della board, oppure una lingua fissa scelta a mano.

### Coda asincrona

L'utente non attende mai. Il listener accoda e finisce lì; un worker separato
esegue la chiamata all'API. Con ritentativi a intervalli crescenti sugli errori
transitori, e recupero automatico dei job rimasti appesi.

### Registro attività

Ogni richiesta è tracciata con stato, token consumati, durata, errore e
diagnostica completa del provider — con le credenziali sempre redatte.

---

## Come funziona

```
utente invia un post
   │
   ├─ il listener verifica: forum configurato? innesco corrispondente?
   │  autore non è un bot? permesso presente? tetto giornaliero?
   │  cooldown rispettato?
   │
   ├─ accoda il job e finisce qui  ◄── nessuna chiamata di rete: l'utente
   │                                    non aspetta
   └─ sotto il post compare "sta per rispondere…"

il worker (cron o riga di comando)
   │
   ├─ prende in carico i job pronti con un lock anti doppia esecuzione
   ├─ ricostruisce la conversazione dagli ultimi N post del topic
   ├─ chiama l'API del provider configurato
   ├─ converte la risposta da Markdown a BBCode
   └─ pubblica come utente bot e aggiorna il badge
```

---

## Sicurezza

- **Nessuna richiesta del board verso sé stesso**, nessun inoltro di cookie di
  sessione, nessuna necessità di disattivare la validazione IP di sessione
- **Chiavi mai in un URL**: Gemini usa l'header `x-goog-api-key`, mai il parametro
  query, che finirebbe nei log del server e nei proxy
- **Chiavi mai nei log e mai ristampate in HTML**
- **Nessun redirect seguito** nelle chiamate API: seguirlo significherebbe
  inoltrare le credenziali a un host non scelto da noi
- **Token di sessione verificato** su ogni azione dell'ACP che consuma rete
- **Permesso dedicato** `u_aireply_trigger` per limitare chi può innescare risposte
- **Nessuna modifica ai post degli utenti** per ricostruire il contesto

---

## Controllo della spesa

| Strumento | Effetto |
|---|---|
| Tetto giornaliero per forum | ferma le risposte al raggiungimento |
| Cooldown per topic | intervallo minimo fra due interventi |
| Limite orario per utente | impedisce a una persona di monopolizzare il bot |
| Tetto del contesto in caratteri | limite duro su quanto viene inviato |
| Token massimi in uscita | limite sulla risposta |
| Budget mensile dichiarato | soglia di riferimento con percentuale consumata |
| Interruttore generale | ferma tutto con un clic |

### Una nota onesta sulle quote

**Né OpenAI né Google offrono un modo per leggere il credito residuo tramite API.**
Chi dice il contrario sta inventando un numero.

Quello che l'estensione mostra è vero e viene da tre fonti diverse:

1. **I token che ha consumato** — somma esatta, le API la riportano a ogni risposta
2. **Il residuo nella finestra di frequenza corrente** — dagli header
   `x-ratelimit-*`, che dicono il limite al minuto, non il saldo del conto
3. **Quando il credito è finito** — dall'errore `insufficient_quota`, che arriva a
   posteriori ma è inequivocabile

I prezzi dei modelli **non sono cablati nel codice**: i listini cambiano più in
fretta di quanto un'estensione possa essere rilasciata. Si inseriscono a mano dopo
averli verificati sul listino ufficiale, con un collegamento diretto nell'interfaccia.

---

## Trasparenza verso gli utenti

Ogni risposta generata porta una dichiarazione esplicita che indica il modello
usato. È voluto, e non solo per correttezza: il regolamento europeo sull'IA
prevede obblighi di trasparenza per l'interazione con sistemi di IA e per i
contenuti sintetici.

Il bot ha inoltre un profilo phpBB visibile, quindi chiunque può vedere chi è.

---

## Riga di comando

Elabora la coda senza aspettare il cron — utile per la diagnostica:

```bash
php bin/phpbbcli.php aireply:process --limit=1 -v
```

Stampa l'esito di ogni job (`done`, `failed`, `retry`, `skipped`) con il tempo
impiegato.

---

## Risoluzione dei problemi

| Sintomo | Causa più probabile |
|---|---|
| Nessun badge sotto il post | interruttore generale spento, o nessun bot assegnato al forum |
| Badge fermo su "sta per rispondere" | il cron non gira; prova `aireply:process` a mano |
| Job `skipped` | leggi il messaggio: cooldown, tetto raggiunto, o permesso `f_reply` mancante al bot |
| Job `failed` con `auth` | chiave non risolvibile, o restrizioni API mancanti sulla chiave |
| Job `failed` con `method_blocked` | la chiave non è autorizzata a quel metodo dell'API |
| Risposta vuota | token massimi in uscita troppo bassi su un modello che ragiona: alza a 2000+ |
| Il bot non risponde ai tuoi post | hai assegnato al bot il tuo stesso account |

La scheda **Registro attività** contiene la diagnostica completa di ogni richiesta.
È il primo posto dove guardare.

Per tutto ciò che riguarda chiavi, restrizioni ed errori dei provider, vedi
**[Getting your API keys](docs/api-keys.md)**, che contiene una tabella degli
errori per entrambi.

---

## Limitazioni note

Elencate per onestà, non perché siano irrisolvibili.

- **I prezzi vanno inseriti a mano.** Vedi la nota sulle quote: cablarli
  significherebbe mostrare cifre sbagliate con l'aria di essere autorevoli.
- **Citare il bot non conta come menzione.** Se in un forum è attivo solo l'innesco
  *Menzioni*, un utente che risponde citando il bot non riceve risposta.
- **Nessuna suite di test automatici.** Ogni parte è stata verificata durante lo
  sviluppo, ma non c'è nulla che giri da solo.
- **La lingua della dichiarazione** è quella predefinita della board al momento
  della pubblicazione, non quella di chi legge: il testo è salvato nel post.
- **Versione di sviluppo.** Non ancora sottoposta alla validazione del team
  Extensions di phpBB.

---

## Licenza e attribuzioni

Copyright © 2026 **Salvo Cortesiano**

Distribuito sotto **GNU General Public License versione 2** (GPL-2.0-only).
Vedi il file [license.txt](license.txt).

L'architettura generale — bot come utente phpBB reale, coda di job, stato mostrato
sotto il post, pubblicazione tramite `submit_post()` con cambio temporaneo di
utente — è ispirata a [phpbb_ailabs](https://github.com/privet-fun/phpbb_ailabs)
di privet.fun, anch'essa GPL-2.0. Nessun file è stato copiato integralmente; dove
il pattern implementativo coincide, il codice è stato riscritto e i punti in cui
AI Reply si discosta deliberatamente sono documentati nei commenti.

phpBB® è un marchio registrato di phpBB Limited. Questa estensione non è affiliata
a phpBB Limited, OpenAI o Google.
