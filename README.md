# phpbb-AI-Reply
AI-generated replies in selected phpBB forums
Someone posts an introduction, and a bot welcomes them with a message that shows it
actually read what they wrote. Someone asks a technical question in the support
section, and gets a first answer while waiting for a human one. Which AI replies,
in which section, and with what character is up to you — forum by forum.

[![phpBB](https://img.shields.io/badge/phpBB-3.3%2B-blue)](https://www.phpbb.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--only-green)](LICENSE)

🇬🇧 English · [🇮🇹 Italiano](README.it.md)

---

## Contents

- [At a glance](#at-a-glance)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Features](#features)
- [How it works](#how-it-works)
- [Security](#security)
- [Cost control](#cost-control)
- [Transparency towards users](#transparency-towards-users)
- [Command line](#command-line)
- [Troubleshooting](#troubleshooting)
- [Known limitations](#known-limitations)
- [Licence and attribution](#licence-and-attribution)

---

## At a glance

| | |
|---|---|
| **Supported providers** | OpenAI (ChatGPT) · Google Gemini |
| **Configuration** | per forum: which bot, when it steps in, under which limits |
| **The bot is** | an ordinary phpBB user, with avatar, rank and profile |
| **Processing** | asynchronous through a queue; the poster never waits |
| **Languages** | English and Italian, complete |
| **Version** | 1.0.2-dev |

---

## Requirements

- **phpBB 3.3.0** or later
- **PHP 7.4** or later
- PHP **cURL** extension enabled
- PHP **json** extension enabled
- An API key from either OpenAI or Google Gemini

The extension checks these on activation and, if something is missing, says exactly
what — rather than failing later in some obscure way.

---

## Installation

1. Copy the `salvocortesiano/` folder into the board's `ext/` directory.
   The final path must be `forum/ext/salvocortesiano/aireply/ext.php`.
2. ACP → **Customise** → **Manage extensions** → enable **AI Reply**.
3. Purge the board cache.

---

## Configuration

### 1. Create the bot's user

ACP → **Users and groups** → **Manage users** → create a dedicated user, for
example `Assistant`. Give it a recognisable avatar and make sure it has permission
to reply in the forums where you will use it.

> **Do not use your own account.** A bot never replies to its own posts — that is
> the safeguard preventing it from answering itself forever — so assigning it your
> user means your messages would never get a reply. The extension refuses this
> configuration and explains why.

### 2. Put the API key in `config.php`

At the bottom of the board's `config.php`:

```php
define('AIREPLY_OPENAI_KEY', 'sk-proj-...');
define('AIREPLY_GEMINI_KEY', 'AIza...');
```

Then, in the ACP key field, write `const:AIREPLY_GEMINI_KEY`.

Three forms are accepted:

| Form | Meaning |
|---|---|
| `const:NAME` | constant defined in `config.php` — **recommended** |
| `env:NAME` | environment variable — **recommended** |
| `sk-...` | literal value, stored in the database |

The literal value works, but sits in clear text in the database. The advantage of
`config.php` is that this file does not end up in the SQL dumps you hand to
technical support or upload to a help forum.

### 3. Create the bot

ACP → **AI Reply** → **Bots** → *Add a bot*.

Pick the provider, press **Refresh model list** to load the models actually
available to your key, and use **Test connection** to verify before saving — it
consumes no tokens.

The system prompt defines the bot's character. Three ready-made examples are one
click away: *Welcoming*, *Support*, *Subject expert*.

### 4. Assign forums

ACP → **AI Reply** → **Forums and triggers**.

You can assign forum by forum in the table, or use **quick assignment** to apply
the same bot and rules to several forums at once, selecting them with `Ctrl`.

Three independent triggers per forum:

| Trigger | When it replies |
|---|---|
| **New topics** | somebody opens a discussion — ideal for introductions |
| **Replies** | every message in the topic — useful for support |
| **Mentions** | only when called by name (`@Assistant`) |

### 5. Enable

ACP → **AI Reply** → **General settings** → turn on the master switch.

### 6. (Optional) System cron

The extension replies within about ten seconds even without a system cron, because
the worker wakes up when there is work ready. For consistent timing even on a board
with no traffic:

```
* * * * * /usr/bin/php /path/to/forum/bin/phpbbcli.php cron:run --quiet
```

---

## Features

### Two AIs, configured separately

OpenAI and Google Gemini each have their own key, model, endpoint and parameters.
You can have Gemini reply in introductions and OpenAI in support.

The provider layer sits behind an interface: adding a third one means writing a
class and registering it, without touching anything else.

### Live model list

The **Refresh model list** button queries the API and shows the models available
**to your key** — not a list hardcoded in the source that goes stale.

- Models that appeared since the last check are marked with `★`
- Models no longer available are reported by name
- Prices you entered by hand are never overwritten by a refresh
- If the list cannot be retrieved (which happens with restricted keys), the model
  can be typed manually and everything keeps working

### Contextual guidance

Selecting a model updates a panel explaining what that model does, what it costs
and what to expect. It recalculates when you change the parameters too.

It includes the warnings that otherwise cost you an afternoon:

- **Reasoning models** — thinking tokens count towards the output limit: if the
  limit is too low, the model spends the whole budget reasoning and returns an
  empty reply
- **Temperature not supported** on some models, and omitted automatically
- **Context larger than the model's window**, with the figure in tokens

### Conversation memory

The bot can read earlier messages in the topic, from 0 (no memory) up to 200. Two
caps work together:

- **Context posts** — the one an administrator thinks in
- **Character cap** — the one that actually protects your spend, and always wins

The second exists because fifty short posts and fifty long posts cost very
differently, and the model bills tokens, not messages. When the cap bites, the
oldest posts are dropped and the log records how many.

### Reply language

Three selectable policies: match the message (recommended), always the board
language, or a fixed language you choose.

### Asynchronous queue

The poster never waits. The listener queues and stops there; a separate worker
makes the API call. With backoff retries on transient errors, and automatic
recovery of jobs left hanging.

### Activity log

Every request is tracked with status, tokens consumed, duration, error and the
provider's full diagnostics — credentials always redacted.

---

## How it works

```
user submits a post
   │
   ├─ the listener checks: forum configured? trigger matching?
   │  author is not a bot? permission present? daily cap?
   │  cooldown respected?
   │
   ├─ queues the job and stops here  ◄── no network call: the poster
   │                                      does not wait
   └─ "about to reply…" appears under the post

the worker (cron or command line)
   │
   ├─ claims ready jobs with a lock against double execution
   ├─ rebuilds the conversation from the topic's last N posts
   ├─ calls the configured provider's API
   ├─ converts the reply from Markdown to BBCode
   └─ publishes as the bot user and updates the badge
```

---

## Security

- **No request from the board to itself**, no session cookies forwarded, no need to
  disable session IP validation
- **Keys never in a URL**: Gemini uses the `x-goog-api-key` header, never the query
  parameter, which would end up in server logs and proxies
- **Keys never logged and never re-printed into HTML**
- **No redirects followed** on API calls: following one would forward credentials
  to a host we did not choose
- **Session token verified** on every ACP action that spends network
- **Dedicated permission** `u_aireply_trigger` to limit who can trigger replies
- **No modification of users' posts** to rebuild context

---

## Cost control

| Control | Effect |
|---|---|
| Daily cap per forum | stops replies once reached |
| Cooldown per topic | minimum gap between two replies |
| Hourly limit per user | stops one person monopolising the bot |
| Context character cap | hard limit on what gets sent |
| Maximum output tokens | limit on the reply |
| Declared monthly budget | reference threshold with percentage used |
| Master switch | stops everything with one click |

### An honest note about quotas

**Neither OpenAI nor Google offers a way to read your remaining credit through the
API.** Anyone claiming otherwise is inventing a number.

What the extension shows is true, and comes from three different sources:

1. **The tokens it has consumed** — an exact sum; the APIs report it on every reply
2. **What remains in the current rate window** — from the `x-ratelimit-*` headers,
   which state the per-minute limit, not the account balance
3. **When credit has run out** — from the `insufficient_quota` error, which arrives
   after the fact but is unambiguous

Model prices are **not hardcoded**: price lists change faster than an extension can
be released. You enter them by hand after checking the official pricing page, with
a direct link in the interface.

---

## Transparency towards users

Every generated reply carries an explicit notice naming the model used. This is
deliberate, and not only out of fairness: the European AI Act sets transparency
obligations for interaction with AI systems and for synthetic content.

The bot also has a visible phpBB profile, so anyone can see who it is.

---

## Command line

Process the queue without waiting for cron — useful for diagnostics:

```bash
php bin/phpbbcli.php aireply:process --limit=1 -v
```

It prints each job's outcome (`done`, `failed`, `retry`, `skipped`) with the time
taken.

---

## Troubleshooting

| Symptom | Most likely cause |
|---|---|
| No badge under the post | master switch off, or no bot assigned to the forum |
| Badge stuck on "about to reply" | cron is not running; try `aireply:process` by hand |
| Job `skipped` | read the message: cooldown, cap reached, or the bot lacks `f_reply` |
| Job `failed` with `auth` | key not resolvable, or missing API restrictions on the key |
| Job `failed` with `method_blocked` | the key is not authorised for that API method |
| Empty reply | maximum output tokens too low on a reasoning model: raise to 2000+ |
| The bot ignores your posts | you assigned your own account to the bot |

The **Activity log** tab holds the full diagnostics of every request. It is the
first place to look.

---

## Known limitations

Listed for honesty, not because they are unfixable.

- **Prices must be entered by hand.** See the note on quotas: hardcoding them would
  mean showing wrong figures with an air of authority.
- **One bot per forum in the interface.** The database schema supports more; the
  configuration tab exposes one.
- **Quoting the bot does not count as a mention.** If a forum has only the
  *Mentions* trigger enabled, a user who quote-replies to the bot gets no answer.
- **The notice language** is the board default at the time of publishing, not the
  reader's: the text is stored in the post.
- **Development version.** Not yet submitted to the phpBB Extensions team for
  validation.

---

## Licence and attribution

Copyright © 2026 **Salvo Cortesiano**

Distributed under the **GNU General Public License version 2** (GPL-2.0-only).
See the [LICENSE](LICENSE) file.

The overall architecture — bot as a real phpBB user, job queue, status shown under
the post, publishing through `submit_post()` with a temporary user switch — is
inspired by [phpbb_ailabs](https://github.com/privet-fun/phpbb_ailabs) by
privet.fun, also GPL-2.0. No file was copied verbatim; where the implementation
pattern coincides the code was rewritten, and the points where AI Reply
deliberately diverges are documented in the comments.

phpBB® is a registered trademark of phpBB Limited. This extension is not affiliated
with phpBB Limited, OpenAI or Google.

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
