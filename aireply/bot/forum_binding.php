<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\bot;

/**
 * Regole con cui un bot opera in uno specifico forum.
 *
 * Tenere queste impostazioni fuori dalla riga del bot è ciò che permette
 * "Gemini nel forum Presentazioni, OpenAI nel forum Assistenza" con una sola
 * query invece di una scansione di stringhe JSON.
 */
class forum_binding
{
	public const TRIGGER_TOPIC = 'topic';
	public const TRIGGER_REPLY = 'reply';
	public const TRIGGER_MENTION = 'mention';

	/** @var int */
	public $forum_id = 0;

	/** @var int */
	public $bot_id = 0;

	/** @var bool Risponde all'apertura di un nuovo topic */
	public $on_new_topic = false;

	/** @var bool Risponde a ogni risposta nel topic */
	public $on_reply = false;

	/** @var bool Risponde quando viene menzionato */
	public $on_mention = false;

	/** @var int Ritardo prima di rispondere, in secondi */
	public $delay_seconds = 0;

	/** @var int Massimo di risposte al giorno in questo forum; 0 = illimitato */
	public $daily_cap = 50;

	/** @var int Intervallo minimo fra due risposte nello stesso topic */
	public $cooldown_seconds = 60;

	/** @var bool */
	public $enabled = true;

	public static function from_row(array $row): self
	{
		$binding = new self();

		$binding->forum_id         = (int) $row['forum_id'];
		$binding->bot_id           = (int) $row['bot_id'];
		$binding->on_new_topic     = (bool) $row['on_new_topic'];
		$binding->on_reply         = (bool) $row['on_reply'];
		$binding->on_mention       = (bool) $row['on_mention'];
		$binding->delay_seconds    = max(0, (int) $row['delay_seconds']);
		$binding->daily_cap        = max(0, (int) $row['daily_cap']);
		$binding->cooldown_seconds = max(0, (int) $row['cooldown_seconds']);
		$binding->enabled          = (bool) $row['enabled'];

		return $binding;
	}

	/**
	 * Questo innesco è attivo per questo forum?
	 */
	public function accepts(string $trigger): bool
	{
		switch ($trigger)
		{
			case self::TRIGGER_TOPIC:
				return $this->on_new_topic;

			case self::TRIGGER_REPLY:
				return $this->on_reply;

			case self::TRIGGER_MENTION:
				return $this->on_mention;
		}

		return false;
	}

	public function reacts_to_anything(): bool
	{
		return $this->on_new_topic || $this->on_reply || $this->on_mention;
	}
}
