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

use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\language\language;
use phpbb\passwords\manager as passwords_manager;
use phpbb\user;

/**
 * Crea l'utente phpBB che farà da bot.
 *
 * Senza questo, l'amministratore deve creare l'utente a mano — e phpBB, di
 * serie, non offre alcun modo di farlo dall'ACP: serve un'estensione apposita.
 * Chiedere di installarne una per poter usare la nostra è una richiesta
 * irragionevole.
 *
 * L'utente creato è deliberatamente diverso da un utente normale:
 *
 *  · password casuale e mai mostrata — il bot non deve poter accedere, e
 *    nessuno deve poter accedere come lui;
 *  · messaggi privati disattivati — un bot che non risponde ai messaggi
 *    privati e li accumula senza che nessuno li legga è una promessa non
 *    mantenuta verso gli utenti;
 *  · escluso dalle email di massa;
 *  · attivo subito, senza email di attivazione da confermare.
 */
class user_creator
{
	/** @var config */
	protected $config;

	/** @var driver_interface */
	protected $db;

	/** @var language */
	protected $language;

	/** @var passwords_manager */
	protected $passwords;

	/** @var user */
	protected $user;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	public function __construct(
		config $config,
		driver_interface $db,
		language $language,
		passwords_manager $passwords,
		user $user,
		string $root_path,
		string $php_ext
	) {
		$this->config = $config;
		$this->db = $db;
		$this->language = $language;
		$this->passwords = $passwords;
		$this->user = $user;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Crea l'utente.
	 *
	 * @return array{user_id: int, errors: string[]}
	 */
	public function create(string $username, string $email, int $group_id = 0): array
	{
		$this->load_functions();

		$errors = $this->validate($username, $email);

		if (!empty($errors))
		{
			return ['user_id' => 0, 'errors' => $errors];
		}

		$registered = $this->get_registered_group_id();

		if ($registered === 0)
		{
			return ['user_id' => 0, 'errors' => [$this->language->lang('AIREPLY_ERR_NO_REGISTERED_GROUP')]];
		}

		$row = [
			'username'             => $username,

			/*
			 * Password casuale di 40 caratteri, generata e subito dimenticata.
			 *
			 * Il bot non accede mai: pubblica attraverso submit_post() con un
			 * cambio di identità interno, senza passare da una sessione. Una
			 * password che nessuno conosce è quindi la configurazione più
			 * sicura possibile, non un ostacolo.
			 */
			'user_password'        => $this->passwords->hash(bin2hex(random_bytes(20))),

			'user_email'           => $email,
			'group_id'             => $registered,
			'user_type'            => USER_NORMAL,
			'user_lang'            => (string) $this->config['default_lang'],
			'user_timezone'        => (string) $this->config['board_timezone'],
			'user_dateformat'      => (string) $this->config['default_dateformat'],
			'user_style'           => (int) $this->config['default_style'],
			'user_regdate'         => time(),
			'user_ip'              => '',
			'user_actkey'          => '',
			'user_inactive_reason' => 0,
			'user_inactive_time'   => 0,

			// Un bot che accumula messaggi privati senza rispondere sarebbe
			// una promessa non mantenuta verso chi glieli scrive.
			'user_allow_pm'        => 0,
			'user_allow_massemail' => 0,
		];

		$user_id = (int) user_add($row);

		if ($user_id === 0)
		{
			return ['user_id' => 0, 'errors' => [$this->language->lang('AIREPLY_ERR_USER_CREATE_FAILED')]];
		}

		if ($group_id > 0 && $group_id !== $registered)
		{
			group_user_add($group_id, [$user_id]);
		}

		return ['user_id' => $user_id, 'errors' => []];
	}

	/**
	 * Controlli sul nome e sull'email, con i messaggi di phpBB.
	 *
	 * Si usa validate_data del core invece di regole nostre: così il nome
	 * rispetta le stesse regole della registrazione — lunghezza, caratteri
	 * consentiti, nomi vietati, duplicati — e i messaggi d'errore sono quelli
	 * che l'amministratore già conosce.
	 *
	 * @return string[]
	 */
	protected function validate(string $username, string $email): array
	{
		/*
		 * validate_data() restituisce chiavi di lingua del core — USERNAME_TAKEN,
		 * EMAIL_TAKEN, TOO_SHORT_USERNAME e simili — che vivono in ucp.php.
		 * L'ACP non carica quel file, quindi senza questa riga l'amministratore
		 * si vede sputare in faccia le chiavi grezze invece dei messaggi.
		 */
		$this->language->add_lang('ucp');

		$errors = validate_data(
			['username' => $username, 'email' => $email],
			[
				'username' => [
					['string', false, (int) $this->config['min_name_chars'], (int) $this->config['max_name_chars']],
					['username', ''],
				],
				'email' => [
					['string', false, 6, 60],
					['user_email'],
				],
			]
		);

		return array_map([$this->language, 'lang'], $errors);
	}

	protected function get_registered_group_id(): int
	{
		$sql = 'SELECT group_id FROM ' . GROUPS_TABLE . "
			WHERE group_name = 'REGISTERED'
				AND group_type = " . GROUP_SPECIAL;

		$result = $this->db->sql_query($sql);
		$group_id = (int) $this->db->sql_fetchfield('group_id');
		$this->db->sql_freeresult($result);

		return $group_id;
	}

	/**
	 * Gruppi selezionabili, per il menù del form.
	 *
	 * @return array[] group_id, name
	 */
	public function get_groups(): array
	{
		$sql = 'SELECT group_id, group_name, group_type
			FROM ' . GROUPS_TABLE . '
			ORDER BY group_type DESC, group_name ASC';

		$result = $this->db->sql_query($sql);

		$groups = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$name = ((int) $row['group_type'] === GROUP_SPECIAL)
				? $this->language->lang('G_' . $row['group_name'])
				: $row['group_name'];

			$groups[] = ['group_id' => (int) $row['group_id'], 'name' => $name];
		}

		$this->db->sql_freeresult($result);

		return $groups;
	}

	/**
	 * Indirizzo suggerito per il nuovo utente.
	 *
	 * phpBB pretende un'email e la vuole unica quando il riutilizzo è
	 * disattivato. Il bot non la userà mai, ma dev'essere sintatticamente
	 * valida e appartenere a un dominio che esiste: si costruisce quindi sul
	 * dominio della board stessa, non su un dominio inventato che i controlli
	 * DNS rifiuterebbero.
	 */
	public function suggest_email(string $username): string
	{
		$host = parse_url(generate_board_url(), PHP_URL_HOST);

		if (!$host)
		{
			$host = 'example.com';
		}

		$slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $username));
		$slug = trim($slug, '-');

		if ($slug === '')
		{
			$slug = 'bot';
		}

		return 'aireply-' . $slug . '@' . $host;
	}

	protected function load_functions(): void
	{
		if (!function_exists('user_add'))
		{
			include $this->root_path . 'includes/functions_user.' . $this->php_ext;
		}

		if (!function_exists('validate_data'))
		{
			include $this->root_path . 'includes/functions_user.' . $this->php_ext;
		}
	}
}
