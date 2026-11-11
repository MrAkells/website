<?php
/**
 * Статус Minecraft-сервера по протоколу Server List Ping — тот же запрос,
 * который делает игра, когда обновляет список серверов.
 */

declare( strict_types=1 );

/**
 * Статус сервера с кэшем на 15 секунд. Неудачная попытка тоже кэшируется,
 * чтобы недоступный сервер не тормозил каждую загрузку страницы.
 *
 * @return array{online: bool, checked: int, motd?: string[], version?: string, players_online?: int, players_max?: int, players?: array<int, array{uuid: string, name: string}>, mods?: int}
 */
function mrakells_minecraft_status( string $address ): array {
	$key    = 'mrakells_mc_' . md5( $address );
	$cached = get_transient( $key );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$status            = mrakells_minecraft_ping( $address ) ?? array( 'online' => false );
	$status['checked'] = time();

	set_transient( $key, $status, 15 );
	update_option( 'mrakells_mc_last_' . md5( $address ), $status, false );
	mrakells_minecraft_record( $address, $status );

	return $status;
}

/**
 * Последний известный статус без похода в сеть — для команды «ping» в строке ввода,
 * которая есть на каждой странице и не должна ждать игровой сервер. Сам запрос делается, только если статус
 * ещё ни разу не получали или он старше десяти минут (значит, cron не работает).
 *
 * @return array{online: bool, checked: int, motd?: string[], version?: string, players_online?: int, players_max?: int, players?: array<int, array{uuid: string, name: string}>, mods?: int}
 */
function mrakells_minecraft_snapshot( string $address ): array {
	$cached = get_transient( 'mrakells_mc_' . md5( $address ) );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$last = get_option( 'mrakells_mc_last_' . md5( $address ) );

	if ( is_array( $last ) && time() - (int) ( $last['checked'] ?? 0 ) < 10 * MINUTE_IN_SECONDS ) {
		return $last;
	}

	return mrakells_minecraft_status( $address );
}

/**
 * Дописывает замер в историю онлайна: время и число игроков, −1 — сервер недоступен.
 * Замеры не чаще раза в четыре минуты, хранятся чуть больше суток.
 */
function mrakells_minecraft_record( string $address, array $status ): void {
	$key     = 'mrakells_mc_history_' . md5( $address );
	$history = get_option( $key, array() );
	$history = is_array( $history ) ? $history : array();
	$last    = end( $history );
	$now     = (int) $status['checked'];

	if ( is_array( $last ) && $now - (int) $last[0] < 4 * MINUTE_IN_SECONDS ) {
		return;
	}

	$history[] = array( $now, $status['online'] ? (int) ( $status['players_online'] ?? 0 ) : -1 );
	$history   = array_values(
		array_filter(
			$history,
			static fn ( $sample ): bool => is_array( $sample ) && $now - (int) $sample[0] <= 25 * HOUR_IN_SECONDS
		)
	);

	update_option( $key, $history, false );
}

/**
 * Онлайн за последние сутки по часам, от старых к новым: наибольшее число игроков
 * за час, −1 — сервер весь час был недоступен, null — замеров за этот час нет.
 *
 * @return array<int, int|null>
 */
function mrakells_minecraft_history( string $address ): array {
	$history = get_option( 'mrakells_mc_history_' . md5( $address ), array() );
	$hours   = array_fill( 0, 24, null );
	$now     = time();

	foreach ( is_array( $history ) ? $history : array() as $sample ) {
		$age = is_array( $sample ) ? $now - (int) $sample[0] : -1;

		if ( $age < 0 || $age >= DAY_IN_SECONDS ) {
			continue;
		}

		$hour           = 23 - intdiv( $age, HOUR_IN_SECONDS );
		$hours[ $hour ] = max( $hours[ $hour ] ?? -1, (int) $sample[1] );
	}

	return $hours;
}

/**
 * Страница игрового сервера и её настройки. Если таких страниц несколько, берётся первая.
 *
 * @return array{id: int, url: string, name: string, address: string, quests_url: string, map_url: string}|null
 */
function mrakells_server(): ?array {
	static $server = false;

	if ( false === $server ) {
		$server = null;
		$pages  = get_pages(
			array(
				'meta_key'   => '_wp_page_template',
				'meta_value' => 'page-templates/server.php',
				'number'     => 1,
			)
		);

		$page    = $pages ? $pages[0] : null;
		$address = $page ? trim( (string) get_post_meta( $page->ID, 'server_address', true ) ) : '';

		if ( '' !== $address ) {
			$server = array(
				'id'         => $page->ID,
				'url'        => (string) get_permalink( $page ),
				'name'       => $page->post_name,
				'address'    => $address,
				'quests_url' => trim( (string) get_post_meta( $page->ID, 'quests_url', true ) ),
				'map_url'    => trim( (string) get_post_meta( $page->ID, 'map_url', true ) ),
			);
		}
	}

	return $server;
}

/* -------------------------------------------------------------------------
 * Фоновое обновление: статус, лента квестов и головы игроков забираются раз
 * в пять минут, чтобы история онлайна копилась и без посетителей на странице сервера
 * ---------------------------------------------------------------------- */

add_filter(
	'cron_schedules',
	static function ( array $schedules ): array {
		$schedules['mrakells_5min'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => 'Every 5 minutes',
		);

		return $schedules;
	}
);

add_action(
	'init',
	static function (): void {
		if ( ! wp_next_scheduled( 'mrakells_refresh_server' ) ) {
			wp_schedule_event( time(), 'mrakells_5min', 'mrakells_refresh_server' );
		}
	}
);

add_action(
	'mrakells_refresh_server',
	static function (): void {
		$server = mrakells_server();

		if ( ! $server ) {
			return;
		}

		$status  = mrakells_minecraft_status( $server['address'] );
		$players = $status['players'] ?? array();

		if ( '' !== $server['quests_url'] ) {
			$players = array_merge( $players, mrakells_quest_feed( $server['quests_url'] )['players'] );
		}

		mrakells_refresh_heads( $players );
	}
);

add_action( 'switch_theme', static fn () => wp_clear_scheduled_hook( 'mrakells_refresh_server' ) );

/**
 * Один запрос статуса. Адрес — «host» или «host:port»; без порта учитывается
 * SRV-запись _minecraft._tcp, как это делает клиент игры.
 */
function mrakells_minecraft_ping( string $address ): ?array {
	$parts = explode( ':', trim( $address ), 2 );
	$host  = $parts[0];
	$port  = isset( $parts[1] ) ? (int) $parts[1] : 0;

	if ( '' === $host ) {
		return null;
	}

	if ( ! $port ) {
		$port = 25565;
		$srv  = @dns_get_record( '_minecraft._tcp.' . $host, DNS_SRV ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( ! empty( $srv[0]['target'] ) ) {
			$host = (string) $srv[0]['target'];
			$port = (int) $srv[0]['port'];
		}
	}

	$socket = @stream_socket_client( "tcp://{$host}:{$port}", $errno, $error, 2 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	if ( ! $socket ) {
		return null;
	}

	stream_set_timeout( $socket, 2 );

	$varint = static function ( int $number ): string {
		$bytes = '';

		do {
			$byte     = $number & 0x7F;
			$number >>= 7;
			$bytes   .= chr( $byte | ( $number ? 0x80 : 0 ) );
		} while ( $number );

		return $bytes;
	};

	$read_varint = static function () use ( $socket ): int {
		$number = 0;

		for ( $i = 0; $i < 5; $i++ ) {
			$char = fread( $socket, 1 );

			if ( false === $char || '' === $char ) {
				return -1;
			}

			$byte    = ord( $char );
			$number |= ( $byte & 0x7F ) << ( 7 * $i );

			if ( ! ( $byte & 0x80 ) ) {
				return $number;
			}
		}

		return -1;
	};

	// Handshake (следующее состояние — status) и сразу за ним пустой запрос статуса.
	$handshake = $varint( 0 ) . $varint( 5 ) . $varint( strlen( $host ) ) . $host . pack( 'n', $port ) . $varint( 1 );
	fwrite( $socket, $varint( strlen( $handshake ) ) . $handshake . $varint( 1 ) . $varint( 0 ) );

	$read_varint(); // Длина пакета.
	$read_varint(); // Идентификатор пакета.
	$length = $read_varint();
	$json   = '';

	// Ответ модового сервера содержит список всех модов, поэтому читается частями.
	while ( $length > 0 && $length <= 2 * MB_IN_BYTES && strlen( $json ) < $length ) {
		$chunk = fread( $socket, min( 8192, $length - strlen( $json ) ) );

		if ( false === $chunk || '' === $chunk ) {
			break;
		}

		$json .= $chunk;
	}

	fclose( $socket );

	$data = json_decode( $json, true );

	if ( ! is_array( $data ) ) {
		return null;
	}

	// MOTD бывает строкой или деревом компонентов; коды оформления «§x» убираются.
	$motd = $data['description'] ?? '';

	if ( is_array( $motd ) ) {
		$text = (string) ( $motd['text'] ?? '' );

		foreach ( (array) ( $motd['extra'] ?? array() ) as $part ) {
			$text .= is_array( $part ) ? (string) ( $part['text'] ?? '' ) : (string) $part;
		}

		$motd = $text;
	}

	$motd    = (string) preg_replace( '/§./u', '', (string) $motd );
	$players = array();

	// Сервер называет игроков онлайн вместе с UUID — по нему для них находится голова.
	foreach ( (array) ( $data['players']['sample'] ?? array() ) as $player ) {
		if ( is_array( $player ) && isset( $player['name'] ) && is_string( $player['name'] ) ) {
			$players[] = array(
				'uuid' => mrakells_feed_uuid( $player['id'] ?? '' ),
				'name' => mrakells_feed_text( $player['name'], 40 ),
			);
		}
	}

	return array(
		'online'         => true,
		'motd'           => array_values( array_filter( array_map( 'trim', explode( "\n", $motd ) ) ) ),
		'version'        => (string) ( $data['version']['name'] ?? '' ),
		'players_online' => (int) ( $data['players']['online'] ?? 0 ),
		'players_max'    => (int) ( $data['players']['max'] ?? 0 ),
		'players'        => $players,
		'mods'           => count( (array) ( $data['modinfo']['modList'] ?? array() ) ),
	);
}
