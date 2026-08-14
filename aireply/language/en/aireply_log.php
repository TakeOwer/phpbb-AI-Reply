<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 *
 * Diagnostic messages and instructions sent to the model.
 *
 * These strings end up in three places: the `error_message` field of jobs, the
 * ACP log, and — in the case of AIREPLY_SYSTEM_INSTRUCTIONS — the prompt sent
 * to the API. That last one is why this file exists: leaving those
 * instructions hardcoded would mean an English board still sends the model a
 * prompt in the extension author's language.
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

	// --- Technical instructions appended to the bot's system prompt ------
	// They are added to the persona defined by the admin, not a replacement.
	'AIREPLY_SYSTEM_INSTRUCTIONS' => 'You are taking part in a forum discussion. Write a short, relevant reply of no more than %d characters. Do not use tables or headings. Do not repeat the message you are replying to.',

	// Language directive, kept separate because it depends on the policy
	// chosen for the individual bot.
	'AIREPLY_SYSTEM_LANG_AUTO'  => 'Always reply in the same language as the message you are replying to, even if these instructions are written in another language.',
	'AIREPLY_SYSTEM_LANG_FIXED' => 'Always reply in %s, whatever the language of the message you are replying to.',

	// The language's name in that language. Each language pack declares its
	// own: the simplest way to tell the model what to write in, without
	// maintaining a table of ISO codes.
	'AIREPLY_LANGUAGE_ENDONYM'  => 'English',

	// --- Reasons a reply was not queued ----------------------------------
	'AIREPLY_BLOCK_GLOBAL_OFF'         => 'AI Reply is disabled globally.',
	'AIREPLY_BLOCK_BOT_POST'           => 'The post was written by a bot.',
	'AIREPLY_BLOCK_NO_PERMISSION'      => 'The author does not have permission to trigger automated replies.',
	'AIREPLY_BLOCK_TOO_SHORT'          => 'The post is too short to generate a meaningful reply.',
	'AIREPLY_BLOCK_POSTER_RATE'        => 'The author has already triggered too many replies in the last hour.',
	'AIREPLY_BLOCK_BOT_DISABLED'       => 'The bot is disabled.',
	'AIREPLY_BLOCK_BOT_DISABLED_FORUM' => 'The bot is disabled in this forum.',
	'AIREPLY_BLOCK_TRIGGER_OFF'        => 'The bot does not respond to the "%s" trigger in this forum.',
	'AIREPLY_BLOCK_NO_PROVIDER'        => 'The bot has no provider or model configured.',
	'AIREPLY_BLOCK_JOB_EXISTS'         => 'A job already exists for this post.',
	'AIREPLY_BLOCK_DAILY_CAP'          => 'Daily cap reached in this forum (%1$d of %2$d in the last 24 hours).',
	'AIREPLY_BLOCK_COOLDOWN'           => 'Cooldown active in this topic: %d seconds remaining.',
	'AIREPLY_BLOCK_GLOBAL_OFF_LATER'   => 'AI Reply was disabled after the job was queued.',
	'AIREPLY_BLOCK_BOT_DISABLED_LATER' => 'The bot was disabled after the job was queued.',
	'AIREPLY_BLOCK_NO_BINDING'         => 'The bot is no longer configured for this forum.',

	// --- Worker errors ----------------------------------------------------
	'AIREPLY_ERR_WORKER_TIMEOUT'  => 'The worker did not complete the job within the expected time.',
	'AIREPLY_ERR_BOT_GONE'        => 'The bot no longer exists.',
	'AIREPLY_ERR_TOPIC_GONE'      => 'The target topic no longer exists.',
	'AIREPLY_ERR_BOT_USER_GONE'   => 'The user configured for this bot does not exist (user_id %d).',
	'AIREPLY_ERR_NO_REPLY_PERM'   => 'User "%1$s" does not have permission to reply in forum %2$d. Check the permissions in the ACP.',
	'AIREPLY_ERR_NO_KEY_JOB'      => 'API key not configured, or the reference cannot be resolved.',
	'AIREPLY_ERR_NO_CONTENT'      => 'No text content to send to the model.',
	'AIREPLY_ERR_NO_POST_ID'      => 'Publishing did not return a post id.',

	// --- Provider errors --------------------------------------------------
	'AIREPLY_ERR_NO_MESSAGES'         => 'No messages to send to the model.',
	'AIREPLY_ERR_PROVIDER_UNKNOWN'    => 'Provider "%s" is not registered.',
	'AIREPLY_ERR_NO_KEY_RESOLVE'      => 'No API key configured, or the reference cannot be resolved.',
	'AIREPLY_ERR_MODELS_PARSE'        => 'Unexpected response from the %s models endpoint.',
	'AIREPLY_ERR_MODELS_SAVE'         => 'Could not save the model list: %s',
	'AIREPLY_ERR_MODEL_NOT_AVAILABLE' => 'The key is valid, but model "%s" is not available for this account.',

	'AIREPLY_ERR_REASONING_EMPTY' => 'The token budget was consumed by the model\'s reasoning before it produced any text. Increase "Maximum output tokens" or choose a non-reasoning model.',
	'AIREPLY_ERR_CONTENT_FILTER'  => 'Reply blocked by the provider\'s content filters.',
	'AIREPLY_ERR_EMPTY_RESPONSE'  => 'The model returned an empty reply (finish reason: %s).',
	'AIREPLY_ERR_FINISH_UNKNOWN'  => 'not reported',

	'AIREPLY_ERR_GEMINI_BLOCKED'    => 'Gemini blocked the incoming request (reason: %s).',
	'AIREPLY_ERR_GEMINI_SAFETY'     => 'Gemini blocked the reply for content safety reasons.',
	'AIREPLY_ERR_GEMINI_RECITATION' => 'Gemini stopped the reply because it reproduced copyrighted content.',
	'AIREPLY_ERR_GEMINI_REFUSED'    => 'Gemini declined to answer (reason: %s).',
	'AIREPLY_ERR_GEMINI_THINKING'   => 'The model\'s reasoning consumed all %d available tokens without producing text. Increase "Maximum output tokens" or set a lower thinking budget.',
	'AIREPLY_ERR_GEMINI_AQ_KEY' => 'Your key uses the new "AQ." format Google is rolling out to replace "AIza". Many accounts report these keys being rejected by the Gemini REST endpoint: this is not a copy-paste mistake on your side. Try generating an "AIza" key from the Google Cloud Console (APIs & Services › Credentials › Create credentials › API key, then restrict it to the Generative Language API). On some accounts Google no longer allows this, in which case it needs to be raised with Google support.',
'AIREPLY_ERR_MODEL_RETIRED' => 'Model "%1$s" has been closed by Google to new accounts: it still appears in the list but can no longer be used. Change it in the bot\'s "Model" field. Recommended: <code>%2$s</code>, which is an alias and always points to a live version. There is no need to migrate to another API, despite what Google\'s message suggests.',
	'AIREPLY_ERR_MAX_TOKENS'        => 'Output token limit reached before any text was generated.',

	// --- ACP ---------------------------------------------------------------
	'AIREPLY_ERR_BAD_HASH'       => 'Invalid session token. Reload the page and try again.',
	'AIREPLY_ERR_UNKNOWN_ACTION' => 'Unknown action.',
]);
