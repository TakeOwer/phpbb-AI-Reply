# AI Reply

**AI-generated replies in selected phpBB forums.**

Someone posts an introduction, and a bot welcomes them with a message that shows it
actually read what they wrote. Someone asks a technical question in the support
section, and gets a first answer while waiting for a human one. Which AI replies,
in which section, and with what character is up to you — forum by forum.

[![phpBB](https://img.shields.io/badge/phpBB-3.3%2B-blue)](https://www.phpbb.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--only-green)](license.txt)

🇬🇧 English · [🇮🇹 Italiano](README.it.md)

---

## Contents

- [At a glance](#at-a-glance)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Getting your API keys](docs/api-keys.md) 🔗
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

> **Don't have a key yet, or is one being rejected?** See
> **[Getting your API keys](docs/api-keys.md)** — it covers obtaining a key from
> both providers and, more usefully, the restriction traps that produce error
> messages pointing in the wrong direction.

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

### Several personalities in one forum

A forum can host more than one bot, each with its own phpBB user, its own system
prompt and its own triggers. Users choose which one to address by mentioning it
by name.

The recommended setup:

| Bot | Triggers | Role |
|---|---|---|
| Main bot | New topics + Mentions | greets and answers by default |
| Extra personalities | **Mentions only** | reply only when called |

A clickable list of the forum's available bots appears above the editor, so nobody
has to remember their names.

> **Watch the cost.** If several bots share a non-mention trigger, one message
> produces several replies and several API calls. *General settings* has a cap on
> how many bots may reply automatically to the same message, set to 1 by default.
> Explicit mentions are never capped: somebody who calls three bots by name expects
> three answers.

Bots never reply to each other. Two personalities with the *Replies* trigger in the
same topic would answer each other indefinitely, at your expense.

### Mentioning the author

When the model writes the name of the person it is replying to, that name becomes a
mention. Nothing is added if the model does not use it: forcing "@Name" in front
would give every reply the same mechanical opening.

The mention format is not hardcoded. AI Reply asks the parser what it accepts, so
it works with **Simple mentions** by paul999 in either of its formats — the
`[smention]` tag of version 2.0 and the older `[mention]` — and falls back to plain
`@Name` when no mention extension is installed. The detection cache invalidates
itself when that extension is enabled or disabled.

Two requirements for notifications to be sent:

- **Simple mentions** must be enabled
- the **bot's user** needs the `u_can_mention` permission — not you, the bot

The ACP tells you which of these is missing. See
[Adapting mention detection](docs/adapting-mention-detection.md) if your board
stores mentions in another format.

### Conversation memory

The bot can read earlier messages in the topic, from 0 (no memory) up to 200. Two
caps work together:

- **Context posts** — the one an administrator thinks in
- **Character cap** — the one that actually protects your spend, and always wins

The second exists because fifty short posts and fifty long posts cost very
differently, and the model bills tokens, not messages. When the cap bites, the
oldest posts are dropped and the log records how many.

### Mentions

The bot answers when called by name. Detection covers the formats used by the most
common mention extensions, matching by user ID where available so that renames and
similar usernames cannot confuse it. If your board stores mentions in a format that
is not recognised, see
[Adapting mention detection](docs/adapting-mention-detection.md).

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

For anything involving keys, restrictions or provider errors, see
**[Getting your API keys](docs/api-keys.md)**, which has an error reference for
both providers.

---

## Known limitations

Listed for honesty, not because they are unfixable.

- **Prices must be entered by hand.** See the note on quotas: hardcoding them would
  mean showing wrong figures with an air of authority.
- **Quoting the bot does not count as a mention.** If a forum has only the
  *Mentions* trigger enabled, a user who quote-replies to the bot gets no answer.
- **No automated test suite.** Every part was verified during development, but
  nothing runs on its own.
- **The notice language** is the board default at the time of publishing, not the
  reader's: the text is stored in the post.
- **Development version.** Not yet submitted to the phpBB Extensions team for
  validation.

---

## Licence and attribution

Copyright © 2026 **Salvo Cortesiano**

Distributed under the **GNU General Public License version 2** (GPL-2.0-only).
See the [license.txt](license.txt) file.

The overall architecture — bot as a real phpBB user, job queue, status shown under
the post, publishing through `submit_post()` with a temporary user switch — is
inspired by [phpbb_ailabs](https://github.com/privet-fun/phpbb_ailabs) by
privet.fun, also GPL-2.0. No file was copied verbatim; where the implementation
pattern coincides the code was rewritten, and the points where AI Reply
deliberately diverges are documented in the comments.

phpBB® is a registered trademark of phpBB Limited. This extension is not affiliated
with phpBB Limited, OpenAI or Google.
