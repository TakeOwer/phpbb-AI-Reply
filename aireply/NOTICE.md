# Attribuzioni

AI Reply è software indipendente. La sua architettura generale — bot come utente
phpBB reale, coda di job, stato del job mostrato sotto il post, pubblicazione
tramite `submit_post()` con cambio temporaneo di utente — è ispirata a
**phpbb_ailabs** di privet.fun (https://github.com/privet-fun/phpbb_ailabs),
rilasciata sotto GNU General Public License versione 2.

AI Reply è distribuita sotto la stessa licenza, GPL-2.0-only.

Nessun file di phpbb_ailabs è stato copiato integralmente. Dove il pattern
implementativo coincide (in particolare il cambio di utente per la
pubblicazione), il codice è stato riscritto e i punti in cui AI Reply si
discosta deliberatamente sono documentati nei commenti.

Differenze principali, tutte volute:

- nessuna richiesta HTTP del board verso sé stesso, nessun inoltro di cookie di
  sessione, nessuna necessità di disattivare la validazione IP di sessione;
- il token `ref` viene verificato su ogni endpoint che può innescare una
  chiamata API a pagamento;
- chiavi API mai trasmesse in un parametro query, mai scritte nei log, mai
  ristampate in HTML;
- nessuna modifica al testo dei post degli utenti per ricostruire il contesto;
- limiti di spesa (tetto giornaliero, cooldown, rate limit) presenti fin dalla
  prima versione.
