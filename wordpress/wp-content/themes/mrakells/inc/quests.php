<?php
/**
 * Лента квестов игрового сервера: последние выполненные квесты, все игроки и команды
 * с их продвижением по книге квестов. Её пишет мод gtnh-telegram на игровом сервере
 * в JSON-файл, сайт забирает этот файл по HTTP.
 *
 * Игроки в ленте названы по UUID, команды — по номеру, а имена идут отдельным списком.
 * Сайт ничего о них не запоминает и собирает картину заново из каждой ленты, поэтому
 * смена ника, переименование команды или переход игрока видны сразу и везде, включая
 * старые записи журнала.
 */

declare( strict_types=1 );

/** Версия формата ленты, которую понимает сайт. */
const MRAKELLS_FEED_VERSION = 3;

/**
 * Лента с кэшем на 30 секунд. Если она недоступна, показывается последняя удачно полученная версия.
 *
 * @return array{
 *     quests: array<int, array{time: int, quest: string, url: string, chapter: string, team: string, players: array<int, array{uuid: string, name: string}>}>,
 *     groups: array<int, array{name: string, solo: bool, members: array<int, array{uuid: string, name: string}>, tiers: array<int, array{label: string, done: int, total: int}>, tier: string, done: int, total: int}>,
 *     players: array<int, array{uuid: string, name: string}>
 * }
 */
function mrakells_quest_feed( string $url, int $limit = 10 ): array {
	$key    = 'mrakells_questfeed_v' . MRAKELLS_FEED_VERSION . '_' . md5( $url );
	$cached = get_transient( $key );

	if ( ! is_array( $cached ) || time() - $cached['fetched'] >= 30 ) {
		$feed   = mrakells_fetch_quest_feed( $url ) ?? ( is_array( $cached ) ? $cached['feed'] : null );
		$cached = array(
			'fetched' => time(),
			'feed'    => $feed ?? array(
				'quests'  => array(),
				'groups'  => array(),
				'players' => array(),
			),
		);

		set_transient( $key, $cached, WEEK_IN_SECONDS );
	}

	$feed           = $cached['feed'];
	$feed['quests'] = array_slice( $feed['quests'], 0, $limit );

	return $feed;
}

/**
 * Забирает и разбирает ленту. Возвращает null, если её не удалось получить или понять.
 */
function mrakells_fetch_quest_feed( string $url ): ?array {
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 3,
			'redirection'         => 2,
			'limit_response_size' => 512 * KB_IN_BYTES,
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	return is_array( $data ) ? mrakells_parse_quest_feed( $data ) : null;
}

/**
 * Превращает ленту в то, что показывают шаблоны: журнал с именами вместо идентификаторов
 * и список команд и одиночных игроков с продвижением по ступеням.
 *
 * @param array<string, mixed> $data Разобранный JSON ленты.
 */
function mrakells_parse_quest_feed( array $data ): ?array {
	if ( MRAKELLS_FEED_VERSION !== ( $data['version'] ?? null ) ) {
		return null;
	}

	$list = static fn ( string $field ): array => array_values( array_filter( (array) ( $data[ $field ] ?? array() ), 'is_array' ) );

	// Ступени — главы основной линейки («Tier 1 - LV») по возрастанию номера.
	$tiers = array();

	foreach ( $list( 'chapters' ) as $index => $entry ) {
		$chapter = mrakells_chapter( mrakells_feed_text( $entry['name'] ?? '', 100 ) );

		if ( null !== $chapter['rank'] ) {
			$tiers[] = array(
				'index' => $index,
				'rank'  => $chapter['rank'],
				'label' => $chapter['label'],
				'total' => max( 0, (int) ( $entry['quests'] ?? 0 ) ),
			);
		}
	}

	usort( $tiers, static fn ( array $a, array $b ): int => $a['rank'] <=> $b['rank'] );

	// Продвижение по ступеням из счётчиков «выполнено в главе», которые лента даёт на каждую главу.
	$progress = static function ( array $entry ) use ( $tiers ): array {
		$done   = (array) ( $entry['done'] ?? array() );
		$result = array(
			'tiers' => array(),
			'tier'  => '',
			'done'  => 0,
			'total' => 0,
		);

		foreach ( $tiers as $tier ) {
			$count             = min( $tier['total'], max( 0, (int) ( $done[ $tier['index'] ] ?? 0 ) ) );
			$result['tiers'][] = array(
				'label' => $tier['label'],
				'done'  => $count,
				'total' => $tier['total'],
			);
			$result['done']   += $count;
			$result['total']  += $tier['total'];

			// Достигнутая ступень — старшая, в которой выполнен хотя бы один квест.
			if ( $count > 0 ) {
				$result['tier'] = $tier['label'];
			}
		}

		return $result;
	};

	// Игроки по UUID: имя для показа и исходная запись со счётчиками.
	$names   = array();
	$entries = array();

	foreach ( $list( 'players' ) as $entry ) {
		$uuid = mrakells_feed_uuid( $entry['uuid'] ?? '' );
		$name = mrakells_feed_text( $entry['name'] ?? '', 40 );

		if ( '' !== $uuid && '' !== $name ) {
			$names[ $uuid ]   = $name;
			$entries[ $uuid ] = $entry;
		}
	}

	$person = static fn ( string $uuid ): array => array(
		'uuid' => $uuid,
		'name' => $names[ $uuid ],
	);

	// Из списка UUID — только известные игроки, уже с именами.
	$people = static function ( $uuids ) use ( $names, $person ): array {
		$known = array_filter( array_map( 'mrakells_feed_uuid', (array) $uuids ), static fn ( string $uuid ): bool => isset( $names[ $uuid ] ) );

		return array_map( $person, array_values( array_unique( $known ) ) );
	};

	$teams  = array();
	$teamed = array();
	$groups = array();

	foreach ( array_slice( $list( 'teams' ), 0, 100 ) as $entry ) {
		$name    = mrakells_feed_text( $entry['name'] ?? '', 40 );
		$members = $people( $entry['members'] ?? array() );

		if ( '' === $name || ! $members ) {
			continue;
		}

		$teams[ (int) ( $entry['id'] ?? -1 ) ] = $name;
		$teamed                               += array_flip( array_column( $members, 'uuid' ) );
		$groups[]                              = array(
			'name'    => $name,
			'solo'    => false,
			'members' => $members,
		) + $progress( $entry );
	}

	// Кто не состоит в команде — играет один и показан отдельной строкой.
	foreach ( array_diff_key( $entries, $teamed ) as $uuid => $entry ) {
		$groups[] = array(
			'name'    => $names[ $uuid ],
			'solo'    => true,
			'members' => array( $person( $uuid ) ),
		) + $progress( $entry );
	}

	// Сначала те, кто продвинулся дальше: по числу начатых ступеней, затем по числу квестов.
	$reached = static fn ( array $group ): int => count( array_filter( array_column( $group['tiers'], 'done' ) ) );

	usort(
		$groups,
		static fn ( array $a, array $b ): int => array( $reached( $b ), $b['done'] ) <=> array( $reached( $a ), $a['done'] )
	);

	$quests = array();

	foreach ( array_slice( $list( 'quests' ), 0, 200 ) as $entry ) {
		$credited = $people( $entry['players'] ?? array() );

		if ( ! $credited || ! is_string( $entry['quest'] ?? null ) ) {
			continue;
		}

		$quests[] = array(
			'time'    => (int) ( $entry['time'] ?? 0 ),
			'quest'   => mrakells_feed_text( $entry['quest'], 200 ),
			'url'     => esc_url_raw( (string) ( $entry['questUrl'] ?? '' ), array( 'http', 'https' ) ),
			'chapter' => mrakells_feed_text( $entry['chapter'] ?? '', 100 ),
			// Команда, в которой квест был выполнен; если её больше нет, запись остаётся без команды.
			'team'    => isset( $entry['team'] ) ? ( $teams[ (int) $entry['team'] ] ?? '' ) : '',
			'players' => $credited,
		);
	}

	return array(
		'quests'  => $quests,
		'groups'  => $groups,
		'players' => array_map( $person, array_keys( $names ) ),
	);
}

/**
 * Глава квеста, разобранная на ступень: «Tier 1 - LV» → ступень 1, название «LV».
 * У глав вне основной линейки («Multiblock Goals») ступени нет.
 *
 * @return array{rank: float|null, label: string}
 */
function mrakells_chapter( string $chapter ): array {
	if ( preg_match( '/^Tier\s+(\d+(?:\.\d+)?)\s*-\s*(.+)$/u', $chapter, $matches ) ) {
		return array(
			'rank'  => (float) $matches[1],
			'label' => $matches[2],
		);
	}

	return array(
		'rank'  => null,
		'label' => $chapter,
	);
}

/**
 * Строка из ленты: только текст, ограниченной длины.
 *
 * @param mixed $value Значение из JSON.
 */
function mrakells_feed_text( $value, int $max_length ): string {
	return is_string( $value ) ? mb_strimwidth( sanitize_text_field( $value ), 0, $max_length, '…' ) : '';
}

/**
 * UUID игрока из ленты в нижнем регистре; пустая строка, если это не UUID.
 *
 * @param mixed $value Значение из JSON.
 */
function mrakells_feed_uuid( $value ): string {
	$uuid = is_string( $value ) ? strtolower( $value ) : '';

	return preg_match( '/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/', $uuid ) ? $uuid : '';
}
