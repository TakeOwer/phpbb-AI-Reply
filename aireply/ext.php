<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply;

/**
 * Blocca l'attivazione se l'ambiente non soddisfa i requisiti minimi.
 *
 * Meglio un messaggio chiaro in ACP che un fallimento silenzioso al primo job.
 */
class ext extends \phpbb\extension\base
{
	/** @var string Versione minima di phpBB supportata */
	public const PHPBB_MIN_VERSION = '3.3.0';

	/** @var string Versione minima di PHP supportata */
	public const PHP_MIN_VERSION = '7.4.0';

	/**
	 * {@inheritdoc}
	 */
	public function is_enableable()
	{
		$errors = [];

		/** @var \phpbb\config\config $config */
		$config = $this->container->get('config');

		/** @var \phpbb\language\language $language */
		$language = $this->container->get('language');
		$language->add_lang('aireply_install', 'salvocortesiano/aireply');

		if (phpbb_version_compare($config['version'], self::PHPBB_MIN_VERSION, '<'))
		{
			$errors[] = $language->lang('AIREPLY_REQ_PHPBB', self::PHPBB_MIN_VERSION, $config['version']);
		}

		if (version_compare(PHP_VERSION, self::PHP_MIN_VERSION, '<'))
		{
			$errors[] = $language->lang('AIREPLY_REQ_PHP', self::PHP_MIN_VERSION, PHP_VERSION);
		}

		// Senza cURL non possiamo parlare con nessuna API.
		if (!extension_loaded('curl'))
		{
			$errors[] = $language->lang('AIREPLY_REQ_CURL');
		}

		if (!function_exists('curl_init'))
		{
			$errors[] = $language->lang('AIREPLY_REQ_CURL');
		}

		// json_decode() con profondità e flag: usiamo JSON_THROW_ON_ERROR (PHP 7.3+).
		if (!extension_loaded('json'))
		{
			$errors[] = $language->lang('AIREPLY_REQ_JSON');
		}

		// I job usano token casuali crittograficamente sicuri.
		if (!function_exists('random_bytes'))
		{
			$errors[] = $language->lang('AIREPLY_REQ_RANDOM');
		}

		return empty($errors) ? true : $errors;
	}
}
