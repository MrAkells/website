<?php
/**
 * Головы игроков. Настоящая голова вырезается из скина, который отдаёт Mojang по UUID
 * игрока: лицо 8×8 и поверх него слой головного убора. Готовая картинка весит около
 * сотни байт и встраивается прямо в страницу, поэтому браузер посетителя к Mojang
 * не обращается.
 *
 * Скины запрашивает только фоновое обновление (см. inc/minecraft.php): страница
 * показывает то, что уже лежит в кэше, а до первой загрузки — сгенерированную голову.
 * Кэш привязан к UUID, так что смена ника голову не сбрасывает.
 */

declare( strict_types=1 );

/**
 * Голова игрока: настоящая, если она уже загружена, иначе сгенерированная.
 * Размер по умолчанию — 24px: ровно три экранных пикселя на пиксель скина.
 *
 * @param array{uuid: string, name: string} $player Игрок из ленты или из статуса сервера.
 */
function mrakells_player_head( array $player, string $classes = 'size-6' ): string {
	$heads = get_option( 'mrakells_heads', array() );
	$png   = is_array( $heads ) ? (string) ( $heads[ $player['uuid'] ]['png'] ?? '' ) : '';

	if ( '' === $png ) {
		return mrakells_generated_head( '' !== $player['uuid'] ? $player['uuid'] : $player['name'], $classes );
	}

	return '<img class="pixelated shrink-0 ' . esc_attr( $classes ) . '" src="data:image/png;base64,' . esc_attr( $png ) . '" alt="" width="8" height="8">';
}

/**
 * Загружает головы игроков, которых ещё нет в кэше или которые устарели. Скин живёт
 * сутки; сутки же помнится, что своего скина у игрока нет. За один вызов — не больше
 * нескольких игроков, чтобы не упереться в ограничения Mojang.
 *
 * @param array<int, array{uuid: string, name: string}> $players Игроки из ленты и из статуса сервера.
 */
function mrakells_refresh_heads( array $players, int $limit = 6 ): void {
	if ( ! function_exists( 'imagecreatefromstring' ) ) {
		return;
	}

	$heads   = get_option( 'mrakells_heads', array() );
	$heads   = is_array( $heads ) ? $heads : array();
	$changed = false;

	foreach ( array_unique( array_column( $players, 'uuid' ) ) as $uuid ) {
		$known = $heads[ $uuid ] ?? null;

		// UUID уходит в адрес запроса, поэтому годится только настоящий.
		if ( '' === mrakells_feed_uuid( $uuid ) || ( $known && time() - $known['checked'] < DAY_IN_SECONDS ) ) {
			continue;
		}

		if ( $limit-- <= 0 ) {
			break;
		}

		$png = mrakells_fetch_head( $uuid );

		// null — Mojang не ответил: прежняя голова остаётся, попытка повторится в следующий раз.
		if ( null !== $png ) {
			$heads[ $uuid ] = array(
				'checked' => time(),
				'png'     => $png,
			);
			$changed        = true;
		}
	}

	if ( $changed ) {
		// Кэш не растёт бесконечно: остаются двести игроков, проверенных последними.
		uasort( $heads, static fn ( array $a, array $b ): int => $b['checked'] <=> $a['checked'] );
		update_option( 'mrakells_heads', array_slice( $heads, 0, 200, true ), false );
	}
}

/**
 * Голова одного игрока в base64: UUID → профиль со ссылкой на скин → скин → лицо 8×8.
 * Пустая строка — у игрока нет аккаунта Mojang или своего скина, null — запрос не удался.
 */
function mrakells_fetch_head( string $uuid ): ?string {
	$profile = mrakells_mojang_get( 'https://sessionserver.mojang.com/session/minecraft/profile/' . str_replace( '-', '', $uuid ) );

	if ( null === $profile ) {
		return null;
	}

	$url = '';

	foreach ( (array) ( json_decode( $profile, true )['properties'] ?? array() ) as $property ) {
		if ( 'textures' === ( $property['name'] ?? '' ) ) {
			$textures = json_decode( (string) base64_decode( (string) ( $property['value'] ?? '' ), true ), true );
			$url      = (string) ( $textures['textures']['SKIN']['url'] ?? '' );
		}
	}

	// Скин принимается только с сервера текстур Mojang и запрашивается по https.
	if ( ! preg_match( '#^https?://textures\.minecraft\.net/texture/([0-9a-f]+)$#', $url, $matches ) ) {
		return '';
	}

	$skin = mrakells_mojang_get( 'https://textures.minecraft.net/texture/' . $matches[1] );

	if ( null === $skin ) {
		return null;
	}

	return mrakells_crop_head( $skin ) ?? '';
}

/**
 * Запрос к Mojang. Строка — тело ответа (пустая, если такого профиля нет),
 * null — сеть или сервер подвели и спрашивать стоит ещё раз.
 */
function mrakells_mojang_get( string $url ): ?string {
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 4,
			'redirection'         => 0,
			'limit_response_size' => 64 * KB_IN_BYTES,
		)
	);

	$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

	if ( 200 === $code ) {
		return wp_remote_retrieve_body( $response );
	}

	return in_array( $code, array( 204, 404 ), true ) ? '' : null;
}

/**
 * Вырезает из скина голову: лицо и поверх него слой головного убора. Возвращает PNG 8×8
 * в base64 или null, если это не скин.
 */
function mrakells_crop_head( string $skin ): ?string {
	$image = @imagecreatefromstring( $skin ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- на чужой файл GD отвечает предупреждением.

	if ( ! $image || 64 !== imagesx( $image ) || ! in_array( imagesy( $image ), array( 32, 64 ), true ) ) {
		return null;
	}

	imagepalettetotruecolor( $image );

	// Прозрачен ли пиксель скина: в GD альфа идёт от 0 (непрозрачный) до 127.
	$clear = static fn ( int $x, int $y ): bool => ( ( imagecolorat( $image, $x, $y ) >> 24 ) & 0x7F ) > 63;

	// В скинах старого формата (64×32) слой убора без единого прозрачного пикселя игра
	// считает пустым — иначе у таких скинов голова была бы залита сплошным цветом.
	$hat = true;

	if ( 32 === imagesy( $image ) ) {
		$hat = false;

		for ( $y = 0; $y < 32 && ! $hat; $y++ ) {
			for ( $x = 32; $x < 64 && ! $hat; $x++ ) {
				$hat = $clear( $x, $y );
			}
		}
	}

	$head = imagecreatetruecolor( 8, 8 );

	for ( $y = 0; $y < 8; $y++ ) {
		for ( $x = 0; $x < 8; $x++ ) {
			// Лицо всегда непрозрачное; пиксель убора перекрывает его, если он не прозрачный.
			$over  = $hat && ! $clear( 40 + $x, 8 + $y );
			$color = imagecolorat( $image, ( $over ? 40 : 8 ) + $x, 8 + $y ) & 0xFFFFFF;

			imagesetpixel( $head, $x, $y, $color );
		}
	}

	ob_start();
	imagepng( $head );

	return base64_encode( (string) ob_get_clean() );
}

/**
 * Запасная голова 8×8 в духе скина Minecraft — пока настоящая не загружена или своего
 * скина у игрока нет. Цвета волос, кожи и глаз выводятся из UUID, поэтому у одного
 * игрока она всегда одна и та же.
 */
function mrakells_generated_head( string $seed, string $classes ): string {
	$hash = array_values( (array) unpack( 'C*', md5( $seed, true ) ) );
	$skin = array( '#f5d5a8', '#e8b98a', '#c98f62', '#9a6a45', '#6f4a31' )[ $hash[0] % 5 ];
	$hair = sprintf( 'hsl(%d, %d%%, %d%%)', intdiv( $hash[1] * 360, 256 ), 30 + $hash[2] % 35, 18 + $hash[3] % 22 );
	$eyes = sprintf( 'hsl(%d, 55%%, 48%%)', intdiv( $hash[4] * 360, 256 ) );
	$long = (bool) ( $hash[5] & 1 );
	$face = (bool) ( $hash[5] & 2 );

	// Ряды сверху вниз: h — волосы, s — кожа, w — белок глаза, e — зрачок, n — нос, m — рот.
	$rows = array(
		'hhhhhhhh',
		'hhhhhhhh',
		$face ? 'hhsssshh' : 'hssssssh',
		$long ? 'hssssssh' : 'ssssssss',
		'swessews',
		'sssnnsss',
		$face ? 'shmmmmhs' : 'ssmmmmss',
		$face ? 'shhhhhhs' : 'ssssssss',
	);

	$colors = array(
		'h' => $hair,
		's' => $skin,
		'w' => '#f4f4f4',
		'e' => $eyes,
		'n' => 'rgba(0, 0, 0, 0.14)',
		'm' => 'rgba(0, 0, 0, 0.3)',
	);

	$rects = '<rect width="8" height="8" fill="' . $skin . '"/>';

	foreach ( $rows as $y => $row ) {
		// Соседние клетки одного цвета сливаются в один прямоугольник.
		preg_match_all( '/(.)\1*/', $row, $runs, PREG_OFFSET_CAPTURE );

		foreach ( $runs[0] as $run ) {
			if ( 's' !== $run[0][0] ) {
				$rects .= sprintf( '<rect x="%d" y="%d" width="%d" height="1" fill="%s"/>', $run[1], $y, strlen( $run[0] ), $colors[ $run[0][0] ] );
			}
		}
	}

	return '<svg class="shrink-0 ' . esc_attr( $classes ) . '" viewBox="0 0 8 8" shape-rendering="crispEdges" aria-hidden="true">' . $rects . '</svg>';
}
