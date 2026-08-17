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

	'AIREPLY_BASE_URL'           => 'Default endpoint',
	'AIREPLY_DEFAULT_MODEL'      => 'Default model',
	'AIREPLY_MODELS_CACHED'      => 'Cached models',
	'AIREPLY_CACHE_STALE'        => 'needs refresh',

	'AIREPLY_REGISTERED_PROVIDERS' => 'Loaded providers',
	'AIREPLY_PLACEHOLDER_INTRO'  => 'Installation successful. This page is temporary: it lists the providers the phpBB container loaded correctly. Real configuration arrives in the next development phase.',

	// --- Bots tab ---------------------------------------------------
	'AIREPLY_BOTS_INTRO'        => 'Configure provider, key and model here. The <strong>Refresh model list</strong> button queries the API directly and shows the models available to your key, marking with ★ those that appeared since the last check.',

	'AIREPLY_API_KEY'           => 'API key',
	'AIREPLY_API_KEY_EXPLAIN'   => 'Recommended: <code>const:NAME</code> with the constant defined in <code>config.php</code>, or <code>env:NAME</code>. A literal value works but is stored in clear text in the database. Leave empty to keep the saved key. If your browser fills the field on its own, <strong>clear it</strong> before saving, or you will store the wrong credential.',

	'AIREPLY_MODEL_MANUAL' => 'or type it manually (takes precedence over the menu):',
	'AIREPLY_ERR_BAD_MODEL_ID' => 'The model identifier "%s" is not valid: letters, digits, dots, hyphens and colons are allowed.',
	'AIREPLY_ERR_METHOD_BLOCKED_HINT' => 'The key works, but it is not authorised to list models. In the Google Cloud Console open <em>APIs &amp; Services › Credentials</em>, select the key and under <em>API restrictions</em> allow the whole <em>Generative Language API</em> instead of individual methods. Alternatively leave it as is: type the model name in the manual field above and the bot will work anyway.',
	'AIREPLY_MODEL'             => 'Model',
	'AIREPLY_REFRESH_MODELS'    => 'Refresh model list',
	'AIREPLY_TEST_CONNECTION'   => 'Test connection',
	'AIREPLY_PRICING_PAGE'      => 'Official pricing ↗',
	'AIREPLY_LAST_CHECK'        => 'last check',
	'AIREPLY_NO_MODELS_YET'     => 'No models cached. Enter your key and press "Refresh model list".',
	'AIREPLY_CACHE_STALE_HINT'  => 'The list is more than 24 hours old; a refresh is advisable.',
	'AIREPLY_NEW_MODELS_BADGE'  => 'New models detected',
	'AIREPLY_REASONING_SHORT'   => 'reasoning',
	'AIREPLY_NO_PRICE_SHORT'    => 'price not set',

	'AIREPLY_TUNING'            => 'Parameters that affect cost',
	'AIREPLY_CONTEXT_MAX_CHARS' => 'Context cap (characters)',
	'AIREPLY_CONTEXT_MAX_CHARS_EXPLAIN' => 'This is what actually protects your spend. The number of context posts is a guideline; this is a hard cap.',
	'AIREPLY_MAX_OUTPUT'        => 'Maximum output tokens',
	'AIREPLY_MAX_OUTPUT_EXPLAIN' => 'On reasoning models this also covers thinking tokens, which are never published but are billed.',
	'AIREPLY_DAILY_CAP'         => 'Daily reply cap',
	'AIREPLY_DAILY_CAP_EXPLAIN' => 'Used to project daily and monthly spend.',

	'AIREPLY_USAGE_SUMMARY'     => 'Consumption',
	'AIREPLY_USAGE_TODAY'       => 'Last 24 hours',
	'AIREPLY_USAGE_MONTH'       => 'Last 30 days',
	'AIREPLY_REPLIES'           => 'replies',
	'AIREPLY_COST_PARTIAL'      => 'Incomplete total: price missing for',
	'AIREPLY_BUDGET_OF'         => 'of a declared budget of',

	'AIREPLY_QUOTA_DISCLAIMER'  => 'Note: neither OpenAI nor Google offers a way to read your remaining credit through the API. The figures above are the exact sum of tokens this extension has used, not your account balance. For the balance, check the provider dashboard.',

	'AIREPLY_SERVICE_BLOCKED' => 'This key is not authorised for the Generative Language API: both listing models and generating are blocked. This is not a per-method restriction, so the manual field will not help until you fix it on the Google side. Original message: %s',
	'AIREPLY_ERR_KEY_SERVICE_BLOCKED' => 'Google replied <code>API_KEY_SERVICE_BLOCKED</code>. This means the list of APIs allowed on this key does not include the Generative Language API. It does not mean the API is disabled on the project: that would be <code>SERVICE_DISABLED</code>. Fix: in the Google Cloud Console, on the key, set <strong>Restrict key</strong> and tick <strong>Generative Language API</strong>. Leaving it unrestricted does not work: since 19 June 2026 Google blocks unrestricted keys.',
	'AIREPLY_ERR_SERVICE_BLOCKED_HINT' => '<strong>How to fix it.</strong> In the <em>Google Cloud Console</em>, on the project the key belongs to:<br>1. <em>APIs &amp; Services › Library</em> → search "Generative Language API" → <strong>Enable</strong>. If it is already enabled, go to step 2.<br>2. <em>APIs &amp; Services › Credentials</em> → open your key → <em>API restrictions</em> → choose <strong>Restrict key</strong> and tick <strong>Generative Language API</strong>. Save.<br><strong>Note:</strong> "Don\'t restrict key" is <em>not</em> a valid alternative. Since 19 June 2026 Google blocks Gemini calls from keys with no API restrictions, and that is what produces the <code>API_KEY_SERVICE_BLOCKED</code> error.<br>3. Check <em>Application restrictions</em> too: if it is set to IP addresses or HTTP referrers, your server will be rejected. For a forum set it to <em>None</em>, or add the server\'s public IP.<br>4. Changes can take a few minutes to propagate: wait and try again.<br><br>Make sure the project is the same one the key belongs to: that is the most common mistake. If the key came from Google AI Studio, the quickest route is to generate a new one there on a project where the Gemini API is active.',
	'AIREPLY_MODELS_BLOCKED_OK' => 'The key works (a test generation with "%s" succeeded), but this key is not authorised to <em>list</em> models, so the menu cannot be populated. This is not a problem: type the model name in the field next to it and the bot will work normally.',
	'AIREPLY_MODELS_BLOCKED_KO' => 'This key is not authorised to list models, and the test generation failed too: %s',
	'AIREPLY_VERSION_LABEL'     => 'Extension version',
	'AIREPLY_MODELS_REFRESHED'  => 'List refreshed: %d models available.',
	'AIREPLY_MODELS_NEW_FOUND'  => 'New (%1$d): %2$s.',
	'AIREPLY_MODELS_REMOVED'    => 'No longer available: %s.',
	'AIREPLY_TEST_OK_LIMITED' => 'Key is valid: a test generation with "%s" succeeded. The model list, however, is not accessible with this key.',
	'AIREPLY_TEST_OK'           => 'Connection successful. %d models accessible with this key.',
	'AIREPLY_TEST_OK_WITH_MODEL' => 'Connection successful and model "%1$s" is available. %2$d models accessible in total.',

	'AIREPLY_ERR_UNKNOWN_PROVIDER' => 'Unrecognised provider.',
	'AIREPLY_ERR_NO_KEY'        => 'Enter an API key first.',
	'AIREPLY_ERR_KEY_UNRESOLVED' => 'The key cannot be resolved: if you used env: or const:, check the name and that the constant is defined in config.php.',
	'AIREPLY_WARN_KEY_FORMAT'   => 'Warning: the key does not have the usual format for this provider. If the test succeeded you can ignore this.',

	'AIREPLY_MODEL_NOTE_ECONOMY_CHAT'      => 'Cheap and fast model, well suited to short forum replies. The sensible choice for a welcome bot: the extra quality of higher tiers rarely shows in a greeting.',
	'AIREPLY_MODEL_NOTE_BALANCED_CHAT'     => 'Mid-tier model. Good writing quality at moderate cost, without the latency of reasoning models.',
	'AIREPLY_MODEL_NOTE_ECONOMY_REASONING' => 'Budget reasoning model. It thinks before answering: more accurate on complex questions, but thinking tokens are billed even though they are never published.',
	'AIREPLY_MODEL_NOTE_BALANCED_REASONING' => 'Mid-tier reasoning model. A good compromise when the forum gets technical questions the bot is expected to actually answer.',
	'AIREPLY_MODEL_NOTE_PREMIUM_REASONING' => 'Flagship model. Far more expensive than the lower tiers and almost always overkill for forum replies: consider whether the difference will be noticeable.',
	'AIREPLY_MODEL_NOTE_BALANCED_THINKING' => 'Flash model with thinking enabled. Fast and cheap, but mind the output token budget: thinking consumes it before any visible text.',
	'AIREPLY_MODEL_NOTE_PREMIUM_THINKING'  => 'Pro model with extended thinking. The most capable of the family and the most expensive; rarely necessary for a forum bot.',
	'AIREPLY_MODEL_NOTE_LEGACY'            => 'Previous-generation model, kept for compatibility. Newer alternatives exist at similar or lower cost.',
	'AIREPLY_MODEL_NOTE_OPEN_MODEL'        => 'Open model served through the same API. Less capable than the main models but often available at reduced or no cost.',
	'AIREPLY_MODEL_NOTE_LATEST_ALIAS' => 'Alias that always points to the latest available Flash-Lite release. The safest choice for a forum bot: cheap, fast, and it does not stop working when Google retires a specific version.',
	'AIREPLY_MODEL_NOTE_UNKNOWN'           => 'This model is not yet catalogued by the extension: it was probably released after this version. It still works; enter the price manually and check its characteristics on the provider site.',

	'AIREPLY_NOTE_REASONING_TOKENS' => 'This model thinks before answering, and thinking tokens count towards the %d output token limit. If the limit is too low the model spends the whole budget reasoning and returns an empty reply. For this family, stay above 2000.',
	'AIREPLY_NOTE_NO_TEMPERATURE'   => 'This model does not accept the "temperature" parameter: the field will be ignored and the value will not affect replies.',
	'AIREPLY_NOTE_CONTEXT_TOO_BIG'  => 'The context cap you set is roughly %1$d tokens, but this model accepts at most %2$d input tokens. Lower the cap, otherwise requests will be rejected.',
	'AIREPLY_NOTE_NO_PRICE'         => 'No price set for this model, so cost estimates are unavailable. You can enter it after checking the <a href="%s" target="_blank" rel="noopener">official pricing page</a>.',
	'AIREPLY_NOTE_SEED_PRICE'       => 'The price shown is an indicative value from %1$s and is not updated automatically. Check it against the <a href="%2$s" target="_blank" rel="noopener">official pricing page</a> and correct it: prices change often.',
	'AIREPLY_NOTE_NEW_MODEL'        => 'This model appeared since the last list refresh. If it is recent, verify its characteristics and price before using it in production.',
	'AIREPLY_NOTE_PREMIUM_TIER'     => 'Flagship tier: for short forum replies the cost/benefit ratio is often unfavourable compared with mid-tier models.',

	'AIREPLY_JS_WORKING'      => 'Please wait…',
	'AIREPLY_JS_FAILED'       => 'Request failed. Check the server connection.',
	'AIREPLY_JS_LIMITS'       => 'Limits (input / output):',
	'AIREPLY_JS_PRICE'        => 'Price per million tokens:',
	'AIREPLY_JS_ESTIMATE'     => 'Spend estimate (worst case)',
	'AIREPLY_JS_PER_REPLY'    => 'per reply:',
	'AIREPLY_JS_PER_DAY'      => 'per day, at the configured cap:',
	'AIREPLY_JS_PER_MONTH'    => 'over 30 days:',
	'AIREPLY_JS_QUOTA'        => 'Consumption and quotas',
	'AIREPLY_JS_SPENT_MONTH'  => 'spent over the last 30 days:',
	'AIREPLY_JS_RATE_LIMIT'   => 'remaining in the current window:',
	'AIREPLY_JS_NO_SNAPSHOT'  => 'no data yet (the provider reports it from the first response)',
	'AIREPLY_JS_QUOTA_ERRORS' => 'Out-of-credit errors in the last 7 days:',
	'AIREPLY_JS_TOKENS'       => 'tokens',
	'AIREPLY_JS_UNKNOWN'      => 'not declared by the API',
	'AIREPLY_JS_RECOMMENDED'  => 'recommended',


	// --- Reply language ------------------------------------------------
	'AIREPLY_REPLY_LANGUAGE'         => 'Reply language',
	'AIREPLY_REPLY_LANGUAGE_EXPLAIN' => 'Models already mirror the writer\'s language on their own; this setting matters when the signal is weak (two-word posts, a bare link, an emoji) or when you want a fixed language regardless. It remains a soft constraint: models usually comply, but smaller ones drift more easily.',
	'AIREPLY_LANG_AUTO'              => 'Match the message (recommended)',
	'AIREPLY_LANG_AUTO_EXPLAIN'      => 'A thread is public: the reply should be readable by everyone following the discussion, not just its author.',
	'AIREPLY_LANG_BOARD'             => 'Always the board language',
	'AIREPLY_LANG_BOARD_EXPLAIN'     => 'Useful on a single-language forum that occasionally receives messages in other languages but wants to answer in its own.',
	'AIREPLY_LANG_CUSTOM'            => 'Fixed language, set manually',
	'AIREPLY_LANG_CUSTOM_EXPLAIN'    => 'Write the language name as you would to a person, e.g. "italiano", "English (UK)" or "espanol". Leave empty to fall back to "Match the message".',

	// --- Bot list -------------------------------------------------------
	'AIREPLY_ADD_BOT'            => 'Add a bot',
	'AIREPLY_EDIT_BOT'           => 'Edit bot',
	'AIREPLY_NO_BOTS_YET'        => 'No bots configured yet. Create one to get started.',
	'AIREPLY_BOT_NOT_FOUND'      => 'Bot not found.',
	'AIREPLY_BOT_CREATED'        => 'Bot "%s" created. Now assign it to one or more forums on the "Forums and triggers" tab.',
	'AIREPLY_BOT_UPDATED'        => 'Bot "%s" updated.',
	'AIREPLY_BOT_DELETED'        => 'Bot deleted, along with its forum assignments.',
	'AIREPLY_CONFIRM_DELETE_BOT' => 'Really delete this bot? Its forum assignments will be removed too. Posts it already published remain.',
	'AIREPLY_BOT_DISABLED_SUFFIX' => 'disabled',
	'AIREPLY_USER_MISSING'       => 'user does not exist',
	'AIREPLY_KEY_MISSING'        => 'key missing',
	'AIREPLY_KEY_CHARS'   => 'characters',
	'AIREPLY_KEY_SUSPECT' => 'Warning: this value does not look like a key for this provider. Check that another credential has not ended up in the field.',
	'AIREPLY_KEY_FORCE'   => 'Save the key even if the format is not recognised (only use this if the provider has introduced a new prefix)',
	'AIREPLY_ERR_KEY_IMPLAUSIBLE' => 'The value entered does not look like a %1$s key: %3$s, %2$d characters.<br><br>OpenAI keys start with <code>sk-</code>, Google keys with <code>AIza</code> or <code>AQ.</code><br><br>Most common cause: the browser password manager filled the field with a saved credential. Clear the field, paste the key by hand and try again. If the provider really has introduced a new format, tick the "Save anyway" box.',

	'AIREPLY_KEYFMT_OPENAI'      => 'OpenAI format',
	'AIREPLY_KEYFMT_GOOGLE_AIZA' => 'classic Google format',
	'AIREPLY_KEYFMT_GOOGLE_AQ'   => 'new Google format (AQ.)',
	'AIREPLY_KEYFMT_UNKNOWN'     => 'unrecognised format',
	'AIREPLY_KEYFMT_EMPTY'       => 'no key',
	'AIREPLY_KEY_CURRENT'        => 'Current key',
	'AIREPLY_WARN_GLOBAL_OFF'    => 'AI Reply is globally disabled: no bot will reply until you enable it under "General settings".',

	'AIREPLY_COL_BOT'      => 'Bot',
	'AIREPLY_COL_USER'     => 'phpBB user',
	'AIREPLY_COL_KEY'      => 'Key',
	'AIREPLY_COL_FORUMS'   => 'Forums',
	'AIREPLY_COL_STATUS'   => 'Enabled',
	'AIREPLY_COL_CREATED'  => 'Date',
	'AIREPLY_COL_FORUM'    => 'Forum',
	'AIREPLY_COL_POSTER'   => 'Author',
	'AIREPLY_COL_TOKENS'   => 'Tokens',
	'AIREPLY_COL_DETAIL'   => 'Detail',
	'AIREPLY_COL_ERROR'    => 'Error',

	// --- Bot form -------------------------------------------------------
	'AIREPLY_IDENTITY'            => 'Identity',
	'AIREPLY_BOT_USER'            => 'phpBB user',
	'AIREPLY_BOT_USER_EXPLAIN'    => 'The name of an existing registered user: the bot will post under this identity, with its avatar and rank. It must have permission to reply in the forums you assign it to. A user can be linked to only one bot. <strong>Do not use your own account:</strong> a bot never replies to its own posts, so assigning it your user means your messages would never get a reply.',
	'AIREPLY_BOT_LABEL'           => 'Internal label',
	'AIREPLY_BOT_LABEL_EXPLAIN'   => 'Only for your own reference on this page. Leave empty to use the username.',
	'AIREPLY_BOT_ENABLED'         => 'Enabled',
	'AIREPLY_PROVIDER_CHOOSE'     => 'Which AI this bot uses',
	'AIREPLY_BASE_URL'            => 'Custom endpoint (advanced)',
	'AIREPLY_BASE_URL_EXPLAIN'    => '<strong>In almost every case, leave this empty.</strong> The grey text in the field is only a reminder of the address that will be used; it is not a value awaiting confirmation. Fill it in only if you go through a proxy or a gateway compatible with the provider API.',

	'AIREPLY_PERSONA'                => 'Persona and format',
	'AIREPLY_SYSTEM_PROMPT'          => 'System prompt',
	'AIREPLY_SYSTEM_PROMPT_EXPLAIN'  => 'Defines who the bot is and how it behaves. Technical instructions about length and formatting are appended automatically, so you can focus on character here. Example: "You are the assistant of a photography enthusiasts\' forum. Welcome new members warmly and ask a relevant question about what they wrote."',
	'AIREPLY_TEMPLATE'               => 'Reply template',
	'AIREPLY_TEMPLATE_EXPLAIN'       => 'Available placeholders: {response} the generated text, {disclosure} the automated-content notice, {model} and {provider}. Leave empty to use "{response}" followed by the notice. The notice is added anyway if you omit it. The author mention, when enabled, is applied to the generated text before the template wraps it.',

	'AIREPLY_CONTEXT_POSTS'          => 'Context posts',
	'AIREPLY_CONTEXT_POSTS_EXPLAIN'  => 'How many earlier posts of the topic to pass to the model. 0 = no memory, every reply is independent. Maximum 200, but the character cap below always takes precedence.',
	'AIREPLY_MAX_POST_CHARS'         => 'Maximum reply length',
	'AIREPLY_MAX_POST_CHARS_EXPLAIN' => 'In characters. Requested from the model and also enforced as a final trim.',
	'AIREPLY_TEMPERATURE'            => 'Temperature',
	'AIREPLY_TEMPERATURE_EXPLAIN'    => 'From 0 (predictable) to 2 (more creative). Reasoning models reject this parameter and it is omitted automatically.',
	'AIREPLY_THINKING_BUDGET'        => 'Thinking budget',
	'AIREPLY_THINKING_BUDGET_EXPLAIN' => 'Gemini only. -1 lets the model decide. A low value reduces cost but can produce empty replies if set too tight.',
	'AIREPLY_TIMEOUT'                => 'Request timeout',
	'AIREPLY_TIMEOUT_EXPLAIN'        => 'Maximum seconds to wait for the API. Reasoning models can take up to a minute.',

	'AIREPLY_ERR_NO_USERNAME'   => 'You must give the name of the phpBB user that will act as the bot.',
	'AIREPLY_ERR_USER_NOT_FOUND' => 'No registered user named "%s".',
	'AIREPLY_ERR_BOT_IS_YOU' => '"%s" is the account you are currently logged in with. A bot never replies to its own posts — that is the safeguard preventing it from answering itself forever — so with this setup your messages would never get a reply, and you would not even see an error: the job would simply never be queued.<br><br>Create a dedicated user under <em>Users and groups › Manage users</em>, for example "Assistant", make sure it can reply in the relevant forums, and use that one.',
	'AIREPLY_ERR_USER_TAKEN'    => 'User "%s" is already linked to another bot.',
	'AIREPLY_ERR_SAVE_FAILED'   => 'Save failed: %s',

	// --- Forums tab ------------------------------------------------------
	'AIREPLY_FORUMS_INTRO'   => 'Assign the bot that should reply in each forum and choose when it steps in. Categories are listed but not configurable: they cannot hold posts.<br><br><strong>The panel above and the table below do different things.</strong> Above you configure <em>several forums at once</em>: pick the ones you want, set bot and rules once, apply to all of them, leaving the others untouched. Below you fine-tune an <em>individual forum</em>, and the Submit button rewrites every row from what the table shows at that moment.',
	'AIREPLY_FORUMS_FOUND' => 'This table lists <strong>every</strong> forum on the board: %d postable forums were found. If you expected more, create them first under "Forums › Manage forums": they appear here automatically.',
	// --- Bulk assignment ---------------------------------------------------
	'AIREPLY_BULK_TITLE'   => 'Quick assignment to several forums',
	'AIREPLY_BULK_EXPLAIN' => 'Apply the same bot and the same rules to every forum you select, in one go. Forums you do not select are left untouched. Use the table below to fine-tune an individual forum.',
	'AIREPLY_BULK_FORUMS'  => 'Forums',
	'AIREPLY_BULK_FORUMS_EXPLAIN' => 'Hold <kbd>Ctrl</kbd> (<kbd>Cmd</kbd> on Mac) to pick more than one, or <kbd>Shift</kbd> for a range. The • marks forums that already have a bot assigned.',
	'AIREPLY_BULK_BOT_EXPLAIN' => 'Choose "Remove assignment" to clear the selected forums.',
	'AIREPLY_BULK_CHOOSE'  => '— choose a bot —',
	'AIREPLY_ERR_NO_BOT_CHOSEN' => 'Pick a bot from the menu before applying. To clear the selected forums, explicitly choose "Remove assignment".',
	'AIREPLY_CONFIRM_BULK_REMOVE' => 'You are about to remove the assignment from the selected forums. Proceed?',
	'AIREPLY_BULK_REMOVE'  => '— Remove assignment —',
	'AIREPLY_BULK_APPLY'   => 'Apply to selected forums',
	'AIREPLY_BULK_APPLIED' => 'Rules applied to %d forums.',
	'AIREPLY_BULK_CLEARED' => 'Assignment removed from %d forums.',
	'AIREPLY_SELECT_ALL'   => 'All',
	'AIREPLY_SELECT_NONE'  => 'None',
	'AIREPLY_SELECT_UNASSIGNED' => 'Only those without a bot',
	'AIREPLY_ERR_NO_FORUM_SELECTED' => 'You have not selected any forum.',
	'AIREPLY_NEEDS_BOT' => 'Pick a bot first: without one, these settings cannot be saved.',
	'AIREPLY_ASSIGNED_BOT'   => 'Assigned bot',
	'AIREPLY_NO_BOT'         => '— no bot —',
	'AIREPLY_TRIGGERS'       => 'When it replies',
	'AIREPLY_LIMITS'         => 'Limits',
	'AIREPLY_FORUMS_SAVED'   => 'Configuration saved: %d forums with an assigned bot.',
	'AIREPLY_NO_BOTS_TITLE'  => 'No bots available',
	'AIREPLY_NO_BOTS_EXPLAIN' => 'Before assigning a bot to a forum you need to create at least one on the "Bots" tab.',

	'AIREPLY_TRIGGER_TOPIC'          => 'New topics',
	'AIREPLY_TRIGGER_TOPIC_EXPLAIN'  => 'The bot replies when somebody opens a discussion. The right setting for an introductions section.',
	'AIREPLY_TRIGGER_REPLY'          => 'Replies',
	'AIREPLY_TRIGGER_REPLY_EXPLAIN'  => 'The bot steps in on every message in the topic. Useful for a support section, but on a busy forum it can become intrusive: that is exactly what the cooldown is for.',
	'AIREPLY_TRIGGER_MENTION'        => 'Mentions',
	'AIREPLY_TRIGGER_MENTION_EXPLAIN' => 'The bot replies when called by name (@botname). Mentions ignore the cooldown: if someone calls the bot explicitly, silencing it would be baffling.',
	'AIREPLY_DELAY_SHORT'            => 'Delay (s)',
	'AIREPLY_DELAY_EXPLAIN'          => 'Seconds to wait before replying. A small delay makes the interaction feel more natural and leaves room for human members to answer first.',
	'AIREPLY_CAP_SHORT'              => 'Max/day',
	'AIREPLY_CAP_EXPLAIN'            => 'Maximum replies per day in this forum. 0 = no limit, not advisable until you have seen real consumption.',
	'AIREPLY_COOLDOWN_SHORT'         => 'Cooldown (s)',
	'AIREPLY_COOLDOWN_EXPLAIN'       => 'Minimum gap between two bot replies in the same topic. Matters most with the "Replies" trigger.',
	'AIREPLY_TRIGGER_HELP_TITLE'     => 'What the columns mean',

	// --- Activity log -----------------------------------------------------
	'AIREPLY_JOBS_INTRO'      => 'Each row is one reply request. When something does not work, this is where you find out why.',
	'AIREPLY_QUEUE_STATE'     => 'Queue state',
	'AIREPLY_QUEUE_COUNTS'    => 'Jobs by status',
	'AIREPLY_STATUS_QUEUED_N'  => 'queued',
	'AIREPLY_STATUS_RUNNING_N' => 'running',
	'AIREPLY_STATUS_DONE_N'    => 'done',
	'AIREPLY_STATUS_FAILED_N'  => 'failed',
	'AIREPLY_STATUS_SKIPPED_N' => 'skipped',
	'AIREPLY_CRON_LAST_RUN'   => 'Worker last run',
	'AIREPLY_CRON_LAST_RUN_EXPLAIN' => 'phpBB\'s cron has no timer of its own: it fires when somebody loads a page. On a quiet board that is what makes replies wait, not the model. For consistent timing set up a system cron; otherwise run "php bin/phpbbcli.php aireply:process" by hand.',
	'AIREPLY_CRON_NEVER'      => 'never',
	'AIREPLY_NO_JOBS'         => 'No jobs recorded.',
	'AIREPLY_VIEW_LOG'        => 'Diagnostics',
	'AIREPLY_VIEW_POST'       => 'Go to post',
	'AIREPLY_CLEAR_JOBS'      => 'Clear finished jobs',
	'AIREPLY_JOBS_CLEARED'    => 'Finished jobs deleted.',
	'AIREPLY_JOB_DETAIL'      => 'Job',
	'AIREPLY_JOB_NOT_FOUND'   => 'Job not found.',
	'AIREPLY_JOB_SUMMARY'     => 'Summary',
	'AIREPLY_JOB_REQUEST'     => 'Text sent',
	'AIREPLY_JOB_RESPONSE'    => 'Text received',
	'AIREPLY_JOB_LOG'         => 'Full diagnostics',

	// --- General settings --------------------------------------------------
	'AIREPLY_SETTINGS_INTRO'  => 'Master switch, queue behaviour and spending limits.',
	'AIREPLY_MASTER_SWITCH'   => 'Master switch',
	'AIREPLY_ENABLED'         => 'AI Reply enabled',
	'AIREPLY_ENABLED_EXPLAIN' => 'Turning this off stops every bot in every forum and queues no new jobs. The fastest way to halt everything.',
	'AIREPLY_QUEUE_SETTINGS'  => 'Queue',
	'AIREPLY_BATCH_SIZE'      => 'Jobs per run',
	'AIREPLY_BATCH_SIZE_EXPLAIN' => 'How many jobs the worker handles per run. High values lengthen each run and risk a PHP timeout.',
	'AIREPLY_CRON_INTERVAL'   => 'Idle interval (seconds)',
	'AIREPLY_CRON_INTERVAL_EXPLAIN' => 'How often the worker runs <strong>when there is nothing to do</strong>, to recover any stuck jobs. When a reply is waiting, the worker starts within 10 seconds regardless of this value: raising it does not slow replies down.',
	'AIREPLY_DIAGNOSTICS'     => 'Diagnostics',
	'AIREPLY_VERBOSE_LOG'     => 'Verbose log',
	'AIREPLY_VERBOSE_LOG_EXPLAIN' => 'Also stores the payloads sent and received, with credentials redacted. Useful for troubleshooting, but logs grow fast: set it back to No when done.',
	'AIREPLY_RETENTION'       => 'Job retention (days)',
	'AIREPLY_RETENTION_EXPLAIN' => 'Finished jobs older than this are deleted automatically. 0 = keep everything.',
	'AIREPLY_BUDGET_SETTINGS' => 'Spending',
	'AIREPLY_MONTHLY_BUDGET'  => 'Declared monthly budget',
	'AIREPLY_MONTHLY_BUDGET_EXPLAIN' => 'A reference threshold you set yourself: it drives the percentage display, it does not block requests. To actually cap spending use the per-forum daily caps. 0 = no budget declared.',
	'AIREPLY_CURRENCY'        => 'Currency',
	'AIREPLY_SPENT_SO_FAR'    => 'Spent over the last 30 days',
	'AIREPLY_SETTINGS_SAVED'  => 'Settings saved.',

	// --- phpBB admin log entries -------------------------------------------
	'LOG_AIREPLY_BOT_SAVED'      => '<strong>AI Reply</strong>: saved bot "%s"',
	'LOG_AIREPLY_FORUMS_SAVED'   => '<strong>AI Reply</strong>: forum assignments updated (%s active)',
	'LOG_AIREPLY_SETTINGS_SAVED' => '<strong>AI Reply</strong>: general settings changed',

	// --- System prompt presets -------------------------------------------
	// Single line each: they end up inside a JavaScript string, and a literal
	// newline would break it.
	'AIREPLY_PRESET_FILL'     => 'Fill with an example:',
	'AIREPLY_PRESET_WELCOME'  => 'Welcoming',
	'AIREPLY_PRESET_SUPPORT'  => 'Support',
	'AIREPLY_PRESET_EXPERT'   => 'Subject expert',
	'AIREPLY_PRESET_CLEAR'    => 'Clear',

	'AIREPLY_PRESET_WELCOME_PROMPT' => 'You are this forum\'s assistant and your job is to welcome people who introduce themselves. Be warm without overdoing it, use the person\'s name if they gave one, and ask a single specific question about something they shared. Do not list forum rules and do not give a generic welcome: two or three sentences showing you actually read the message are worth more than a paragraph of formalities. If the message contains nothing specific, just greet them briefly and invite them to tell you more.',
	'AIREPLY_PRESET_SUPPORT_PROMPT' => 'You are this forum\'s assistant and you help people with technical questions. Answer concretely and verifiably: if the question has enough information, give the solution; if it does not, ask for exactly the detail you need instead of listing every possible cause. When you are unsure, say so plainly, because a confident wrong answer wastes more time than an honest "I do not know". Remember other human members will read this and may correct you: do not present yourself as the final word.',
	'AIREPLY_PRESET_EXPERT_PROMPT'  => 'You are a knowledgeable member of this forum. Only step in to add something useful: a missing detail, a correction, a risk the writer has not considered. Do not summarise the message you are replying to and do not compliment the question. If you have nothing to add beyond what has already been said, say so in one line. Tone: conversational and direct, like an experienced person writing quickly but carefully.',

	// --- Template presets --------------------------------------------------
	'AIREPLY_TPL_PLAIN'  => 'Reply only',
	'AIREPLY_TPL_QUOTE'  => 'Inside a quote',
	'AIREPLY_TPL_SIGNED' => 'With model signature',

	'AIREPLY_LEAVE_EMPTY' => 'leave empty',

	'AIREPLY_JS_BAD_RESPONSE' => 'The server did not reply with JSON:',

	// --- Mentioning the poster --------------------------------------------
	'AIREPLY_MENTION_POSTER'         => 'Mention the message author',
	'AIREPLY_MENTION_POSTER_EXPLAIN' => 'When the model writes the name of the person it is replying to, that name is turned into a mention. Nothing is added if the model does not use it: forcing "@Name" in front would give every reply the same mechanical opening.<br><br><strong>Note:</strong> with mentions on, the user gets a notification for <em>every</em> bot reply. In an introductions section that is what you want; in a support section where the bot steps in on every message, it becomes annoying after three exchanges.',
	'AIREPLY_MENTION_REDETECT'       => 'Detect the format again',
	'AIREPLY_MENTION_DETECTED'       => 'Detection complete. Format in use: <strong>%s</strong>.',

	'AIREPLY_MENTION_NO_EXT'  => 'The <em>Simple mentions</em> extension is not enabled: the name will be written as plain <code>@Name</code>, with no notification and no profile link. It still reads fine.',
	'AIREPLY_MENTION_NO_TAG'  => 'The <em>Simple mentions</em> extension is enabled, but the parser accepts none of its BBCodes. The name will be written as plain <code>@Name</code>. Try "Detect the format again" after checking the extension is configured correctly.',
	'AIREPLY_MENTION_OK'      => 'Detected the <strong>[%s]</strong> format: the mention will also produce a notification and a profile link.',
	'AIREPLY_MENTION_NO_PERM' => '<strong>However the bot\'s user does not have the "Can mention" permission (<code>u_can_mention</code>).</strong> Without it the mention is written but no notification is sent. Grant it under <em>Permissions › User permissions</em>.',

	// --- Several bots per forum --------------------------------------------
	'AIREPLY_NO_BOT_HERE'    => 'No bot in this forum.',
	'AIREPLY_ADD_BOT_HERE'   => '+ add a bot to this forum…',
	'AIREPLY_WARN_MULTI_AUTO' => 'More than one bot replies automatically in this forum: a single message can produce several replies and several API calls. If the extra personalities should only step in when called, leave them the "Mentions" trigger only.',

	'AIREPLY_BULK_MODE'          => 'Apply mode',
	'AIREPLY_BULK_MODE_EXPLAIN'  => 'With several bots per forum "apply" is ambiguous: better to state it than to wipe configurations by accident.',
	'AIREPLY_BULK_MODE_ADD'      => 'Add to the bots already there',
	'AIREPLY_BULK_MODE_REPLACE'  => 'Replace every bot in the selected forums',

	'AIREPLY_MAX_BOTS_PER_POST'  => 'Bots replying to the same message',
	'AIREPLY_MAX_BOTS_PER_POST_EXPLAIN' => 'How many bots may reply automatically to the same message when a forum has more than one assigned. This applies to the "New topics" and "Replies" triggers only: <strong>explicit mentions are never limited</strong>, because somebody who calls three bots by name expects three answers. 0 = no limit, not advisable.',

	// --- Creating the bot's user -------------------------------------------
	'AIREPLY_NEW_USER'        => 'Create a user for the bot',
	'AIREPLY_NEW_USER_LINK'   => 'No user to use? Create one →',
	'AIREPLY_NEW_USER_INTRO'  => 'phpBB does not let you create users from the ACP without a dedicated extension. From here you can create one suited to acting as a bot, without installing anything else.<br><br>The user created is different from a normal one: a random password that is never shown, so nobody can log in as it; private messages disabled, because a bot that piles them up without answering is a promise it cannot keep; excluded from mass email; active immediately, with no confirmation email.',
	'AIREPLY_NEW_USER_NAME_EXPLAIN'  => 'This is the name users will see on posts and use to call it with @name. The same rules as registration apply.',
	'AIREPLY_NEW_USER_EMAIL_EXPLAIN' => 'phpBB requires an address, but the bot will never use it. Leave the field empty and one is generated on the board\'s own domain.',
	'AIREPLY_NEW_USER_GROUP_EXPLAIN' => 'Optional. The user joins the Registered group anyway; here you can add another one, for instance to give it a recognisable rank or colour.',
	'AIREPLY_NEW_USER_NO_GROUP' => '— Registered only —',
	'AIREPLY_NEW_USER_NOTE'   => 'After creation you return to the bot form with the name ready. Forum permissions are granted later, once you have decided where it should reply.',
	'AIREPLY_USER_CREATED'    => 'User "%s" created.',

	'AIREPLY_GRANT_MENTION'   => 'Grant the mention permission',
	'AIREPLY_GRANT_MENTION_EXPLAIN' => 'Gives this user the <code>u_can_mention</code> permission, which Simple mentions requires to send notifications. It is granted to the individual user, not the group: granting it to everyone just to make a bot work would be an unexpected side effect.',
	'AIREPLY_USER_PERM_FAILED' => 'The user was created, but granting permissions failed: %s<br>You can retry with "Grant the missing permissions" from the bot list.',
	'AIREPLY_USER_GRANTED_MENTION' => 'Mention permission granted.',
	'AIREPLY_USER_GRANTED_FORUMS'  => 'Read and reply permissions granted in %d forums.',

	// --- Permission audit ---------------------------------------------------
	'AIREPLY_COL_PERMS'            => 'Permissions',
	'AIREPLY_PERMS_MISSING_MENTION' => 'missing "can mention"',
	'AIREPLY_PERMS_MISSING_FORUMS'  => 'cannot reply in',
	'AIREPLY_PERMS_FIX'            => 'Grant the missing permissions',
	'AIREPLY_PERMS_ALREADY_OK'     => 'Nothing to grant: the bot already has everything it needs.',

	'AIREPLY_ERR_NO_REGISTERED_GROUP' => 'The special "Registered" group does not exist on this board: without it phpBB cannot create users.',
	'AIREPLY_ERR_USER_CREATE_FAILED'  => 'phpBB could not create the user. Check the board error log.',

	// --- Personality builder ------------------------------------------------
	// All texts on a single line: they end up inside JavaScript strings, and a
	// literal newline would break them.
	'AIREPLY_PB_EXPLAIN' => 'Pick a base role and tick the traits you want to add: the prompt is composed below and stays editable by hand. Traits add up, they do not replace each other — you can have a greeter that is <em>also</em> humorous <em>and</em> very concise.',
	'AIREPLY_PB_ROLE'    => 'Base role',
	'AIREPLY_PB_TRAITS'  => 'Traits',
	'AIREPLY_PB_COMPOSE' => 'Compose the prompt',
	'AIREPLY_PB_EDITED'  => 'The prompt has been written or edited by hand: press "Compose the prompt" to replace it with the one generated from the choices above.',

	'AIREPLY_PB_ROLE_FREE'      => '— no role, I will write it —',
	'AIREPLY_PB_ROLE_CLARIFIER' => 'Clarifier',
	'AIREPLY_PB_ROLE_DEVIL'     => 'Devil\'s advocate',
	'AIREPLY_PB_ROLE_ARCHIVIST' => 'Archivist',

	'AIREPLY_PB_ROLE_CLARIFIER_TEXT' => 'Your job is not to solve the problem but to make it solvable by others. Identify the information that is missing and ask for it: one thing only, the most important one. If the question is already complete, say so in one line and add nothing else, because somebody will answer. Do not propose solutions and do not list possible causes.',
	'AIREPLY_PB_ROLE_DEVIL_TEXT'     => 'Only step in to raise the objection nobody has made: a risk not considered, an assumption taken for granted, a case where the proposed solution does not work. One objection only, phrased as a question. You are not hostile, and not contrarian for show: if you have no serious objection, write nothing.',
	'AIREPLY_PB_ROLE_ARCHIVIST_TEXT' => 'When you recognise a question the forum has already covered, say so and suggest what to search for. Do not summarise the answer: the value lies in sending the person to the original discussion, where the later comments and corrections are too. If you recognise nothing similar, write nothing.',

	'AIREPLY_PB_TRAIT_FRIENDLY'  => 'friendly and humorous',
	'AIREPLY_PB_TRAIT_SKEPTIC'   => 'skeptical and analytical',
	'AIREPLY_PB_TRAIT_TECHNICAL' => 'technical and precise',
	'AIREPLY_PB_TRAIT_CONCISE'   => 'very concise',
	'AIREPLY_PB_TRAIT_HUMBLE'    => 'admits uncertainty',
	'AIREPLY_PB_TRAIT_SILENT'    => 'stays quiet when it has nothing to say',

	'AIREPLY_PB_TRAIT_FRIENDLY_TEXT'  => 'Conversational, warm tone, with a touch of humour where it fits. Humour must never replace content: if a joke takes the place of an answer, drop the joke.',
	'AIREPLY_PB_TRAIT_SKEPTIC_TEXT'   => 'Question assumptions rather than accepting them. When something does not add up, say so and explain why in one sentence. Do not be rude: doubting a claim is not doubting the person.',
	'AIREPLY_PB_TRAIT_TECHNICAL_TEXT' => 'Be precise about technical detail: exact names, versions, paths. Prefer one verifiable instruction to a general explanation that reads well but cannot be followed.',
	'AIREPLY_PB_TRAIT_CONCISE_TEXT'   => 'Do not exceed three sentences. If you notice yourself summarising the message you are replying to, thanking someone for the question, or closing with phrases like "I hope this helps", cut that part: it adds nothing.',
	'AIREPLY_PB_TRAIT_HUMBLE_TEXT'    => 'When you are unsure, say so plainly. On a forum, a confident wrong answer wastes more time than an honest "I do not know", because somebody will act on it. Remember other members will read this and may correct you: do not present yourself as the final word.',
	'AIREPLY_PB_TRAIT_SILENT_TEXT'    => 'If you have nothing useful to add beyond what has already been written, say so in a single line or do not reply at all. The value of a forum is people answering each other: a thorough but unnecessary reply removes everyone\'s reason to step in.',

	'AIREPLY_COL_PERSONA' => 'Personality',
	'AIREPLY_NO_PERSONA'  => 'no prompt set',

	'AIREPLY_BOARD_CONTEXT'         => 'The bot knows the forum structure',
	'AIREPLY_BOARD_CONTEXT_EXPLAIN' => 'Adds the board name, the current section and the list of sections to the prompt, so the bot can answer questions like "what is this forum about?" or "where can I just chat?". Without it the model has no way of knowing and <strong>makes things up</strong>.<br><br><strong>The sections listed are those readable by the person who wrote the message</strong>, not those visible to the bot: otherwise anyone could ask for the list and discover the existence of restricted areas.<br><br>Costs a few dozen tokens per call on a small board, up to a couple of thousand on one with many sections. The list stops at 60 entries.',
	'AIREPLY_BOARD_CONTEXT_EMPTY' => 'Nothing to send: the board has no name configured and there are no sections readable with your permissions. With this setting on, the model would receive nothing extra.',
	'AIREPLY_BOARD_CONTEXT_PREVIEW' => 'Show what the model will receive (with your permissions)',

	'ACL_U_AIREPLY_TRIGGER'      => 'Can receive automated replies from AI bots',
]);
