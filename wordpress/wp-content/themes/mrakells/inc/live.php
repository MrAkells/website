<?php
/**
 * Живые игровые данные отдельно от HTML-кэша. Endpoint читает только сервер,
 * настроенный в опубликованной странице, и не принимает адреса от посетителей.
 */

declare( strict_types=1 );

add_action(
	'rest_api_init',
	static function (): void {
		register_rest_route(
			'mrakells/v1',
			'/server',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => 'mrakells_live_server',
				'args'                => array(
					'panels' => array( 'type' => 'boolean', 'default' => false ),
					'lang'   => array( 'type' => 'string', 'enum' => array_keys( mrakells_languages() ), 'default' => MRAKELLS_DEFAULT_LANG ),
				),
			)
		);
	}
);

/** Рендер готовой панели с тем же экранированием и переводами, что у страницы. */
function mrakells_live_panel( string $name, array $args ): string {
	ob_start();
	get_template_part( 'template-parts/' . $name . '-panel', null, $args );
	return (string) ob_get_clean();
}

/** Статус для команды ping; по запросу — панели страницы сервера. */
function mrakells_live_server( WP_REST_Request $request ) {
	$server = mrakells_server();

	if ( ! $server ) {
		return new WP_Error( 'server_not_configured', 'No public game server configured.', array( 'status' => 404 ) );
	}

	$status = mrakells_minecraft_status( $server['address'] );
	$data   = array(
		'server' => array(
			'address' => $server['address'],
			'online'  => $status['online'],
			'players' => (int) ( $status['players_online'] ?? 0 ),
			'max'     => (int) ( $status['players_max'] ?? 0 ),
			'checked' => $status['checked'],
		),
		'refresh_after' => 30,
	);

	if ( $request['panels'] ) {
		$feed   = '' !== $server['quests_url'] ? mrakells_quest_feed( $server['quests_url'] ) : null;
		$quests = $feed['quests'] ?? array();
		$groups = $feed['groups'] ?? array();
		$data['panels'] = array(
			'status'  => mrakells_live_panel( 'server', array( 'server' => $server, 'status' => $status ) ),
			'players' => mrakells_live_panel( 'players', array( 'status' => $status ) ),
			'quests'  => $quests ? mrakells_live_panel( 'quests', array( 'quests' => $quests ) ) : '',
			'teams'   => $groups ? mrakells_live_panel( 'teams', array( 'groups' => $groups ) ) : '',
		);
	}

	$response = new WP_REST_Response( $data );
	$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
	$response->header( 'Pragma', 'no-cache' );
	return $response;
}
