# Adapting mention detection to your board

AI Reply can trigger a bot when somebody **mentions it by name**. Whether that
works depends on which mention extension your board runs and — more importantly —
on what that extension actually stores in the database.

This guide shows how to find out, and what to change if the format is one AI Reply
does not recognise yet.

---

## Formats recognised out of the box

| Stored form | Matched by | Notes |
|---|---|---|
| `<SMENTION u="42">Name</SMENTION>` | user ID | Simple Mentions, parsed |
| `[smention u=42]Name[/smention]` | user ID | Simple Mentions, raw BBCode |
| `<MENTION u="42" …>@Name</MENTION>` | user ID | common `<MENTION>` variant |
| `<MENTION user_id="42">@Name</MENTION>` | user ID | attribute variant |
| `<MENTION id="42">@Name</MENTION>` | user ID | attribute variant |
| `[mention=42]Name[/mention]` | user ID | ID inside the opening tag |
| `[mention u=42]Name[/mention]` | user ID | ID inside the opening tag |
| `[mention]Name[/mention]` | username | no ID available |
| `<MENTION …>@Name</MENTION>` | username | fallback when the ID does not match |
| `@Name` typed by hand | username | works with no extension at all |

**Matching by user ID is always preferred.** It survives renames, is
case-insensitive by nature, and cannot be confused by two users with similar names.
Username matching exists only for formats that do not carry the ID.

If your board uses one of the forms above, there is nothing to do.

---

## Step 1 — Find out what your board really stores

**Do not guess from what you type.** Since phpBB 3.2, posts are stored as XML
produced by the text formatter, not as the BBCode you wrote. A `[mention]` tag in
the editor may be stored as `<MENTION …>`, as `<SMENTION …>`, or left as-is,
depending on how the extension defines its BBCode.

The text AI Reply inspects is exactly this stored form.

Write a test post mentioning any user, then look at it directly:

```sql
SELECT post_id, post_text
FROM phpbb_posts
ORDER BY post_id DESC
LIMIT 1;
```

Replace `phpbb_` with your table prefix. You will see something like:

```xml
<r><MENTION profile="./memberlist.php?mode=viewprofile&amp;u=42" u="42">@Assistant</MENTION> hello</r>
```

Note three things:

1. **The tag name** — `MENTION`, `SMENTION`, or something else
2. **Whether the user ID appears**, and under which attribute name
3. **How the visible name is written** — with or without a leading `@`

That is all you need.

---

## Step 2 — The one file to change

Everything lives in:

```
ext/salvocortesiano/aireply/content/text_extractor.php
```

Two methods matter, and they do different jobs.

### `mentions_user()` — deciding whether the bot was called

This returns `true` when the post mentions the bot. Add your format to the
`$by_id` array if the stored form carries the user ID:

```php
$by_id = [
    '/<?\[?SMENTION[^>\]]*\bu=["\']?' . $id . '\b/i',
    '/<MENTION[^>]*\b(?:u|user_id|id)=["\']?' . $id . '\b/i',
    '/\[mention[\s=]+["\']?(?:u=)?' . $id . '\b/i',

    // ── your format here ──
    '/<YOURTAG[^>]*\bmember=["\']?' . $id . '\b/i',
];
```

`$id` is already cast to an integer, so it is safe to interpolate.

If your format carries only the username, add the pattern below the `$quoted`
line instead, and use `$quoted` — which is the username already escaped for use
in a regular expression:

```php
if (preg_match('/<YOURTAG[^>]*>\s*@?' . $quoted . '\s*<\/YOURTAG>/i', $post_text))
{
    return true;
}
```

### `strip_bbcode()` — cleaning the text sent to the model

This runs on every post before it becomes part of the prompt. Mentions are reduced
to `@Name`, because the numeric ID means nothing to a language model and costs
tokens.

```php
$text = preg_replace('#\[smention[^\]]*\](.*?)\[/smention\]#is', '@$1', $text) ?? $text;
$text = preg_replace('#\[mention[^\]]*\](.*?)\[/mention\]#is', '@$1', $text) ?? $text;

// ── your format here ──
$text = preg_replace('#\[yourtag[^\]]*\](.*?)\[/yourtag\]#is', '@$1', $text) ?? $text;
```

Note that XML tags such as `<MENTION>` do not need a rule here: the generic tag
stripper below these lines removes them and keeps the inner text.

---

## Step 3 — Test it

1. Purge the board cache.
2. In ACP → **AI Reply** → **Forums and triggers**, enable **Mentions** on a test
   forum and disable the other two triggers, so nothing else can fire.
3. Post a message mentioning the bot.
4. Check ACP → **AI Reply** → **Activity log**.

| What you see | What it means |
|---|---|
| A job appears | detection works |
| No job at all | the pattern does not match — go back to Step 1 |
| Job `skipped` with a cooldown or cap message | detection works, something else blocked it |

To be certain the trigger was the mention and not something else, open the job's
**Diagnostics**: the request text shows exactly what was extracted from the post.

---

## A note on quoting

Quoting the bot with the *Reply with quote* button is **not** treated as a mention.
A quote produces a `<QUOTE>` tag, not a mention tag.

If a forum has only the *Mentions* trigger enabled, a user who quote-replies to the
bot will get no answer. Enable *Replies* as well if that matters for your board.

---

## If you extend this

Regular expressions covering more mention extensions are welcome upstream. Please
open an issue or a pull request including:

- the name and link of the mention extension
- a real sample of the stored `post_text`, taken with the SQL query in Step 1
- the phpBB version

The stored sample is the important part — it is the only thing that removes
guesswork.

---

*AI Reply — Copyright © 2026 Salvo Cortesiano — GPL-2.0-only*
