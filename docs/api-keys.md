# Getting your API keys

AI Reply needs an API key from OpenAI, from Google Gemini, or from both. Obtaining
one takes a few minutes; the traps are in the restrictions applied to the key, and
they produce error messages that point in the wrong direction.

This guide covers what actually goes wrong, not just the happy path.

---

## The rule that saves you an afternoon

**Verify every key with `curl` before touching the forum.**

A key that fails in the extension may be failing for a dozen reasons; a key that
fails in `curl` is failing for exactly one, and the error message is the provider's
own. Do not configure the bot with an unverified key.

```bash
# Google Gemini
curl -s "https://generativelanguage.googleapis.com/v1beta/models" \
  -H "x-goog-api-key: YOUR_KEY"

# OpenAI
curl -s https://api.openai.com/v1/models \
  -H "Authorization: Bearer YOUR_KEY"
```

Both should return a JSON list of models. Anything else means the key is not ready.

`curl.exe` ships with Windows 10 and 11. If you have no shell at all, you can open
`https://generativelanguage.googleapis.com/v1beta/models?key=YOUR_KEY` in a browser
— but use a private window, or a cached response from a previous attempt will
mislead you, and clear your history afterwards since the key ends up in it.

---

## Google Gemini

### Route A — Google AI Studio (recommended)

1. Go to **aistudio.google.com** and sign in
2. **Get API key** → **Create API key**
3. When asked which project to use, choose **Create API key in new project**
4. Copy the key and verify it with the `curl` command above

AI Studio enables the API, sets the restrictions and links the service account for
you. Choosing an existing project is where most problems begin: if that project
does not have the Gemini API enabled, the key is issued but rejected.

### Route B — Google Cloud Console

Use this only if Route A is unavailable to you.

1. **console.cloud.google.com** → make sure the correct project is selected in the
   top bar. This is the single most common mistake.
2. **APIs & Services** → **Library** → search **Gemini API** → **Enable**

   > **Naming trap.** In the Console the API is listed as **Gemini API**. In every
   > error message, and in the API restrictions dropdown, it appears under its
   > service name `generativelanguage.googleapis.com`. Searching for "Generative
   > Language API" in the Library may find nothing. They are the same thing.

3. **APIs & Services** → **Credentials** → **Create credentials** → **API key**
4. Open the key and set:
   - **API restrictions** → **Restrict key** → tick **Gemini API**
   - **Application restrictions** → **None**
5. Save and wait up to 5 minutes for the change to propagate
6. Verify with `curl`

### The restriction traps

**"Restrict key" is mandatory, not optional.** Since June 2026 Google blocks Gemini
calls from keys that have no API restrictions configured. A key set to allow any
API will fail with `API_KEY_SERVICE_BLOCKED`. This is the opposite of the usual
advice, and the opposite of what "leave it unrestricted to make it work" suggests.

**Application restrictions must be None.** A forum calls the API from the server,
not from a browser. If the key is restricted to HTTP referrers or to IP addresses,
your server is rejected. Either set None, or add the server's public IP.

**Method-level restrictions are a thing.** A key can be allowed to call
`generateContent` but not `ListModels`. The bot still works; only the model
dropdown stays empty. AI Reply detects this, falls back to a tiny test generation
to confirm the key is alive, and lets you type the model name by hand.

**Key formats.** Google is migrating from `AIza…` keys to a newer `AQ.…` format.
Both are legitimate and AI Reply accepts both. If an `AQ.` key is rejected by the
REST endpoint — which some accounts report — try generating an `AIza` key from the
Cloud Console instead.

**Retired models.** `ListModels` may list models your account can no longer use.
Calling one returns `404` with *"no longer available to new users"*. Prefer the
alias `gemini-flash-lite-latest`, which always points at a live release and does
not go stale.

### Gemini error reference

| Error | Meaning | Fix |
|---|---|---|
| `API_KEY_SERVICE_BLOCKED` | the key's allowed-API list does not include Gemini | Credentials → key → Restrict key → tick Gemini API |
| `SERVICE_DISABLED` | the API is not enabled on the project | Library → Gemini API → Enable |
| `API key not valid` | wrong or malformed key | check for stray spaces; regenerate |
| `403` on `ListModels` only | method-level restriction | harmless; type the model name manually |
| `404 no longer available` | retired model | switch to `gemini-flash-lite-latest` |
| `429` | rate limit | wait; AI Reply retries with backoff |

---

## OpenAI

1. Go to **platform.openai.com** and sign in
2. **Settings** → **Billing** → add a payment method and some credit

   > API access is **not** included with a ChatGPT Plus subscription, and there is
   > no free tier for new accounts. Without credit, every call returns
   > `insufficient_quota`.

3. **API keys** → **Create new secret key**
4. Copy it immediately — it is shown only once
5. Verify with `curl`

### Project key scopes

OpenAI project keys can be created with restricted permissions. Listing models
requires the `model.read` scope. Without it you get:

> You have insufficient permissions for this operation. Missing scopes: model.read

As with Gemini, the bot still works — only the model dropdown stays empty. AI Reply
detects this and lets you enter the model by hand.

### Model parameter notes

Reasoning models (GPT-5 family, o-series) reject the `temperature` parameter. AI
Reply omits it automatically and tells you in the interface.

On the same models, thinking tokens count towards the output limit. Setting
**Maximum output tokens** too low makes the model spend its whole budget reasoning
and return an empty reply. Stay above 2000.

### OpenAI error reference

| Error | Meaning | Fix |
|---|---|---|
| `invalid_api_key` | wrong or revoked key | regenerate |
| `insufficient_quota` | no credit on the account | add credit under Billing |
| `insufficient_permissions` | project key lacks a scope | recreate with `model.read`, or type the model manually |
| `model_not_found` | wrong model ID, or not available to your account | refresh the model list |
| `429` | rate limit | wait; AI Reply retries with backoff |

---

## Storing the key

Once `curl` succeeds, add the key to the bottom of the board's `config.php`:

```php
define('AIREPLY_OPENAI_KEY', 'sk-proj-xxxxxxxx');
define('AIREPLY_GEMINI_KEY', 'AIzaSyxxxxxxxx');
```

Then, in ACP → **AI Reply** → **Bots**, write in the key field:

```
const:AIREPLY_GEMINI_KEY
```

Three forms are accepted:

| Form | Where the key lives |
|---|---|
| `const:NAME` | `config.php` — **recommended** |
| `env:NAME` | environment variable — **recommended** |
| `sk-…` | database, in clear text |

The literal value works. The reason to avoid it is that `config.php` does not end
up in the SQL dumps you hand to technical support or upload to a help forum, and
the field then contains nothing for a browser password manager to overwrite.

---

## If a key stops working

Check in this order — it goes from most to least likely:

1. **Did anything change on the provider side?** Restrictions, billing, project.
2. **Verify with `curl`.** If it fails there, the forum is not involved.
3. **Check ACP → AI Reply → Activity log.** Open a failed job's *Diagnostics*: it
   shows the HTTP status, the provider's own message and how AI Reply classified
   it, with credentials redacted.
4. **Check the key in the interface.** Under the key field, AI Reply shows the
   length and the detected format. If it reads *unrecognised format*, something
   other than your key is stored — a browser password manager filling the field is
   the usual culprit.

---

## Security

- Never paste an API key into a forum post, a chat, or an issue report. Treat any
  key you have shared as compromised and revoke it.
- Keys in `config.php` are excluded from database backups; keys in the database are
  not.
- Set a spending limit in the provider's own console as well. The caps in AI Reply
  protect against runaway usage by the extension, not against anything else that
  might use the same key.

---

*AI Reply — Copyright © 2026 Salvo Cortesiano — GPL-2.0-only*
