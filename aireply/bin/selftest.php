<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 *
 * Verifica del livello provider SENZA phpBB.
 *
 * Le classi in provider/ non dipendono dal framework (fa eccezione manager.php,
 * che qui non serve), quindi si possono collaudare da riga di comando prima
 * ancora di installare l'estensione. Se qualcosa non funziona, meglio scoprirlo
 * qui che dopo aver cablato listener, coda e ACP.
 *
 * Uso:
 *   php bin/selftest.php openai sk-proj-xxxxx
 *   php bin/selftest.php gemini AIzaSyXXXXX
 *   php bin/selftest.php openai sk-proj-xxxxx gpt-4o-mini
 *
 * Oppure con la chiave nell'ambiente, che è la forma preferibile:
 *   AIREPLY_KEY=sk-proj-xxxxx php bin/selftest.php openai
 *
 * Lo script esegue tre prove in ordine crescente di costo:
 *   1. risoluzione della chiave      (nessuna rete)
 *   2. elenco dei modelli            (una chiamata, zero token)
 *   3. una generazione minima        (pochi token, costo trascurabile)
 */

if (PHP_SAPI !== 'cli')
{
	exit("Questo script si esegue solo da riga di comando.\n");
}

$base = dirname(__DIR__) . '/provider/';

foreach ([
	'ai_message', 'ai_request', 'ai_result', 'model_info', 'provider_exception',
	'http_response', 'http_client', 'key_manager',
	'provider_interface', 'base_provider', 'openai_provider', 'gemini_provider',
] as $class)
{
	require_once $base . $class . '.php';
}

use salvocortesiano\aireply\provider\ai_message;
use salvocortesiano\aireply\provider\ai_request;
use salvocortesiano\aireply\provider\gemini_provider;
use salvocortesiano\aireply\provider\http_client;
use salvocortesiano\aireply\provider\key_manager;
use salvocortesiano\aireply\provider\openai_provider;
use salvocortesiano\aireply\provider\provider_exception;

$provider_id = $argv[1] ?? '';
$raw_key = $argv[2] ?? (getenv('AIREPLY_KEY') ?: '');
$model = $argv[3] ?? '';

if ($provider_id === '' || $raw_key === '')
{
	exit("Uso: php bin/selftest.php <openai|gemini> <chiave> [modello]\n");
}

$http = new http_client();
$keys = new key_manager();

switch ($provider_id)
{
	case 'openai':
		$provider = new openai_provider($http);
		break;

	case 'gemini':
		$provider = new gemini_provider($http);
		break;

	default:
		exit("Provider sconosciuto: {$provider_id}. Valori ammessi: openai, gemini.\n");
}

$line = str_repeat('-', 68);

echo "{$line}\n AI Reply — collaudo provider: {$provider_id}\n{$line}\n\n";

// ---------------------------------------------------------------- prova 1
echo "[1/3] Risoluzione della chiave\n";

$api_key = $keys->resolve($raw_key);

if ($api_key === '')
{
	exit("      ✗ Chiave non risolvibile. Se hai usato env: o const:, verifica il nome.\n");
}

printf("      origine   : %s\n", $keys->get_source($raw_key));
printf("      mascherata: %s\n", $keys->mask($api_key));

if (!$keys->looks_plausible($provider_id, $api_key))
{
	echo "      ⚠ La chiave non ha il formato tipico di questo provider. Continuo lo stesso.\n";
}

echo "      ✓ ok\n\n";

// ---------------------------------------------------------------- prova 2
echo "[2/3] Elenco dei modelli (nessun token consumato)\n";

try
{
	$started = microtime(true);
	$models = $provider->list_models($api_key);
	$elapsed = (int) round((microtime(true) - $started) * 1000);
}
catch (provider_exception $e)
{
	printf("      ✗ [%s] %s\n", $e->get_error_code(), $e->getMessage());
	exit(1);
}

printf("      trovati %d modelli adatti alla chat in %d ms\n", count($models), $elapsed);

foreach (array_slice($models, 0, 12) as $info)
{
	printf(
		"        · %-38s %s%s\n",
		$info->id,
		$info->is_reasoning ? '[ragionamento] ' : '',
		$info->output_limit > 0 ? 'out≤' . $info->output_limit : ''
	);
}

if (count($models) > 12)
{
	printf("        … e altri %d\n", count($models) - 12);
}

echo "      ✓ ok\n\n";

// ---------------------------------------------------------------- prova 3
if ($model === '')
{
	$model = $provider->get_default_model();

	$available = array_map(static function ($info) {
		return $info->id;
	}, $models);

	// Se il predefinito non è disponibile per questo account, ripiega sul primo.
	if (!in_array($model, $available, true) && !empty($available))
	{
		$model = $available[0];
		echo "      ⓘ Il modello predefinito non è disponibile: uso {$model}\n\n";
	}
}

echo "[3/3] Generazione di prova con {$model}\n";

$request = new ai_request();
$request->api_key = $api_key;
$request->model = $model;
$request->system_prompt = 'Sei un assistente di un forum italiano. Rispondi in italiano, in una sola frase breve.';
$request->max_output_tokens = $provider->supports_thinking_budget($model) ? 2048 : 200;
$request->temperature = 0.7;
$request->timeout = 60;
$request->verbose_log = false;

$request->add_message(ai_message::user('Ciao! Presentati in una frase.', 'Salvo'));

printf("      temperatura supportata : %s\n", $provider->supports_temperature($model) ? 'sì' : 'no (parametro omesso)');
printf("      budget di ragionamento : %s\n", $provider->supports_thinking_budget($model) ? 'configurabile' : 'non applicabile');

$result = $provider->generate($request);

if (!$result->success)
{
	printf("      ✗ [%s] %s\n", $result->error_code, $result->error_message);
	printf("        HTTP %d · ritentabile: %s\n", $result->http_status, $result->is_retryable() ? 'sì' : 'no');
	exit(1);
}

printf("      durata      : %d ms\n", $result->duration_ms);
printf("      token       : %d in ingresso, %d in uscita\n", $result->prompt_tokens, $result->completion_tokens);
printf("      terminazione: %s\n", $result->finish_reason !== '' ? $result->finish_reason : 'non indicata');
printf("      risposta    : %s\n", $result->text);

echo "      ✓ ok\n\n{$line}\n Tutte le prove superate.\n{$line}\n";
