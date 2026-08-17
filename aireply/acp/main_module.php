<?php

/**
 *
 * AI Reply. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 Salvo Cortesiano
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace salvocortesiano\aireply\acp;

class main_module
{
	/** @var string */
	public $u_action;

	/** @var string */
	public $tpl_name;

	/** @var string */
	public $page_title;

	public function main($id, $mode)
	{
		global $phpbb_container, $language, $request;

		/*
		 * Nota: i titoli dei moduli nel menù laterale NON vengono da qui, ma
		 * da language/<lang>/info_acp_aireply.php, che phpBB carica da solo
		 * quando costruisce la navigazione. Le stesse stringhe in un file con
		 * un altro nome non verrebbero risolte e nel menù comparirebbero le
		 * chiavi grezze.
		 */
		$language->add_lang(['acp_aireply', 'aireply', 'aireply_log'], 'salvocortesiano/aireply');

		/** @var \salvocortesiano\aireply\controller\acp_controller $controller */
		$controller = $phpbb_container->get('salvocortesiano.aireply.acp_controller');
		$controller->set_action($this->u_action);

		// Le azioni AJAX rispondono in JSON e terminano la richiesta.
		$ajax = $request->variable('aireply_ajax', '');

		if ($ajax !== '')
		{
			$this->handle_ajax($controller, $ajax);
		}

		$this->page_title = 'ACP_AIREPLY_TITLE';

		switch ($mode)
		{
			case 'forums':
				$this->tpl_name = 'acp_aireply_forums';
				$controller->mode_forums();
				break;

			case 'jobs':
				$this->tpl_name = 'acp_aireply_jobs';
				$controller->mode_jobs();
				break;

			case 'settings':
				$this->tpl_name = 'acp_aireply_settings';
				$controller->mode_settings();
				break;

			case 'bots':
			default:
				$this->tpl_name = 'acp_aireply_bots';
				$controller->mode_bots();
		}
	}

	protected function handle_ajax($controller, string $action): void
	{
		global $request, $language;

		// Le azioni AJAX consumano rete o modificano stato: senza verifica del
		// token, la pagina ACP diventerebbe un endpoint che chiunque può far
		// invocare a un amministratore con un link.
		if (!check_link_hash($request->variable('hash', ''), 'aireply_acp'))
		{
			$this->send_json(['success' => false, 'message' => $language->lang('AIREPLY_ERR_BAD_HASH')]);
		}

		switch ($action)
		{
			case 'refresh_models':
				$this->send_json($controller->ajax_refresh_models());
				break;

			case 'test':
				$this->send_json($controller->ajax_test());
				break;

			case 'model_info':
				$this->send_json($controller->ajax_model_info());
				break;

			default:
				$this->send_json(['success' => false, 'message' => $language->lang('AIREPLY_ERR_UNKNOWN_ACTION')]);
		}
	}

	protected function send_json(array $payload): void
	{
		global $db;

		if (!headers_sent())
		{
			header('Content-Type: application/json; charset=UTF-8');
			header('Cache-Control: no-store');
		}

		echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		$db->sql_close();
		exit;
	}
}
