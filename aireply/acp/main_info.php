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

class main_info
{
	public function module()
	{
		return [
			'filename' => '\salvocortesiano\aireply\acp\main_module',
			'title'    => 'ACP_AIREPLY_TITLE',
			'modes'    => [
				'bots' => [
					'title' => 'ACP_AIREPLY_BOTS',
					'auth'  => 'ext_salvocortesiano/aireply && acl_a_board',
					'cat'   => ['ACP_AIREPLY_TITLE'],
				],
				'forums' => [
					'title' => 'ACP_AIREPLY_FORUMS',
					'auth'  => 'ext_salvocortesiano/aireply && acl_a_board',
					'cat'   => ['ACP_AIREPLY_TITLE'],
				],
				'jobs' => [
					'title' => 'ACP_AIREPLY_JOBS',
					'auth'  => 'ext_salvocortesiano/aireply && acl_a_board',
					'cat'   => ['ACP_AIREPLY_TITLE'],
				],
				'settings' => [
					'title' => 'ACP_AIREPLY_SETTINGS',
					'auth'  => 'ext_salvocortesiano/aireply && acl_a_board',
					'cat'   => ['ACP_AIREPLY_TITLE'],
				],
			],
		];
	}
}
