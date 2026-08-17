<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\provider;

/**
 * Un turno della conversazione, in forma neutra rispetto al provider.
 *
 * La traduzione verso il formato specifico (messages[] per OpenAI,
 * contents[] per Gemini) è responsabilità del singolo provider.
 */
class ai_message
{
	public const ROLE_USER = 'user';
	public const ROLE_ASSISTANT = 'assistant';

	/** @var string */
	public $role;

	/** @var string */
	public $text;

	/** @var string Nome dell'autore umano, usato solo per contestualizzare il prompt */
	public $author;

	public function __construct(string $role, string $text, string $author = '')
	{
		$this->role = ($role === self::ROLE_ASSISTANT) ? self::ROLE_ASSISTANT : self::ROLE_USER;
		$this->text = $text;
		$this->author = $author;
	}

	public static function user(string $text, string $author = ''): self
	{
		return new self(self::ROLE_USER, $text, $author);
	}

	public static function assistant(string $text): self
	{
		return new self(self::ROLE_ASSISTANT, $text);
	}

	public function is_assistant(): bool
	{
		return $this->role === self::ROLE_ASSISTANT;
	}

	/**
	 * Testo effettivo da inviare al modello.
	 *
	 * Prefissare il nome dell'autore aiuta il modello a distinguere gli
	 * interlocutori in un thread con più partecipanti; senza questo, in un
	 * topic affollato tende a rispondere alla persona sbagliata.
	 */
	public function get_payload_text(): string
	{
		if ($this->role === self::ROLE_USER && $this->author !== '')
		{
			return $this->author . ': ' . $this->text;
		}

		return $this->text;
	}
}
