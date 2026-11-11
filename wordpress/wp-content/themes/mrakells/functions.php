<?php
/**
 * Настройка темы, подключение собранных стилей и вспомогательные функции шаблонов.
 */

declare( strict_types=1 );

require get_theme_file_path( 'inc/i18n.php' );
require get_theme_file_path( 'inc/seo.php' );
require get_theme_file_path( 'inc/minecraft.php' );
require get_theme_file_path( 'inc/heads.php' );
require get_theme_file_path( 'inc/quests.php' );
require get_theme_file_path( 'inc/live.php' );

add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'html5', array( 'style', 'script', 'gallery', 'caption' ) );

		register_nav_menus(
			array(
				'primary' => __( 'Header navigation', 'mrakells' ),
				'links'   => __( 'Links on the front page', 'mrakells' ),
			)
		);
	}
);

/**
 * Манифест Vite: соответствие исходных файлов собранным файлам с хэшем в имени.
 */
function mrakells_manifest(): array {
	static $manifest = null;

	if ( null === $manifest ) {
		$path     = get_theme_file_path( 'dist/.vite/manifest.json' );
		$manifest = is_readable( $path ) ? (array) json_decode( (string) file_get_contents( $path ), true ) : array();
	}

	return $manifest;
}

/**
 * URL собранного файла по пути исходника, например src/css/app.css.
 */
function mrakells_asset_url( string $source ): string {
	$file = mrakells_manifest()[ $source ]['file'] ?? '';

	return $file ? get_theme_file_uri( 'dist/' . $file ) : '';
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$url = mrakells_asset_url( 'src/css/app.css' );

		if ( $url ) {
			// Версия не нужна: хэш содержимого уже в имени файла.
			wp_enqueue_style( 'mrakells', $url, array(), null );
		}

		// Строка ввода, клавиши и мелкие удобства. Без скрипта сайт остаётся полностью рабочим.
		$script = mrakells_asset_url( 'src/js/app.js' );

		if ( $script ) {
			wp_enqueue_script_module( 'mrakells', $script, array(), null );
		}
	}
);

// На странице 404 WordPress помечает блог родителем текущей страницы — вкладка в шапке не должна гореть.
add_filter(
	'nav_menu_css_class',
	static fn ( array $classes ): array => is_404() ? array_diff( $classes, array( 'current_page_parent' ) ) : $classes
);

// Пиксельный шрифт нужен логотипу на первом экране, поэтому загружается заранее.
add_filter(
	'wp_preload_resources',
	static function ( array $resources ): array {
		$url = mrakells_asset_url( 'src/fonts/DepartureMono-Regular.woff2' );

		if ( $url ) {
			$resources[] = array(
				'href'        => $url,
				'as'          => 'font',
				'type'        => 'font/woff2',
				'crossorigin' => 'anonymous',
			);
		}

		return $resources;
	}
);

/**
 * Путь адреса в виде каталога терминала: https://site/blog/post/ → ~/blog/post.
 */
function mrakells_path( string $url ): string {
	$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );

	return '' === $path ? '~' : '~/' . $path;
}

/**
 * Иконка сайта как пиксельный аватар. Берётся оригинал, чтобы браузер масштабировал его без сглаживания.
 */
function mrakells_avatar( string $classes, string $alt = '' ): void {
	$url = wp_get_attachment_image_url( (int) get_option( 'site_icon' ), 'full' );

	if ( ! $url ) {
		return;
	}

	printf(
		'<img class="pixelated %s" src="%s" alt="%s" width="96" height="96" decoding="async">',
		esc_attr( $classes ),
		esc_url( $url ),
		esc_attr( $alt )
	);
}

/**
 * Момент времени в часовом поясе посетителя. Сервер пояса не знает, поэтому отдаёт
 * время по UTC с пометкой, а скрипт на странице заменяет его местным. Без скрипта
 * остаётся честное «UTC».
 *
 * @param string $format Что показывать: time — «18:42», date — «2026-11-06», datetime — оба.
 */
function mrakells_time( int $timestamp, string $format, string $classes = '' ): string {
	$fallback = match ( $format ) {
		'time'  => gmdate( 'H:i', $timestamp ) . ' UTC',
		'date'  => gmdate( 'Y-m-d', $timestamp ),
		default => gmdate( 'Y-m-d H:i', $timestamp ) . ' UTC',
	};

	return sprintf(
		'<time%s datetime="%s" data-local="%s">%s</time>',
		'' !== $classes ? ' class="' . esc_attr( $classes ) . '"' : '',
		esc_attr( gmdate( DATE_W3C, $timestamp ) ),
		esc_attr( $format ),
		esc_html( $fallback )
	);
}

/**
 * Ссылки из меню «Ссылки на главной». Каждой достаётся клавиша — первая свободная
 * латинская буква названия; по ней ссылка открывается с клавиатуры.
 *
 * @return array<int, array{title: string, url: string, key: string, external: bool}>
 */
function mrakells_links(): array {
	$menu  = get_nav_menu_locations()['links'] ?? 0;
	$items = $menu ? wp_get_nav_menu_items( $menu ) : array();
	$host  = wp_parse_url( home_url(), PHP_URL_HOST );
	$links = array();
	$taken = array();

	foreach ( $items ? $items : array() as $item ) {
		$key = '';

		foreach ( str_split( strtolower( (string) preg_replace( '/[^a-z]/i', '', $item->title ) ) ) as $letter ) {
			if ( '' !== $letter && ! isset( $taken[ $letter ] ) ) {
				$key             = $letter;
				$taken[ $letter ] = true;
				break;
			}
		}

		$item_host = wp_parse_url( $item->url, PHP_URL_HOST );

		$links[] = array(
			'title'    => $item->title,
			'url'      => $item->url,
			'key'      => $key,
			'external' => $item_host && $item_host !== $host,
		);
	}

	return $links;
}

/**
 * Время чтения записи в минутах: 200 слов в минуту, считается текст на языке страницы.
 */
function mrakells_reading_time( WP_Post $post ): int {
	$translated = mrakells_translation( $post->ID, 'content' );
	$text       = wp_strip_all_tags( strip_shortcodes( '' !== $translated ? $translated : $post->post_content ) );

	return max( 1, (int) ceil( preg_match_all( '/\S+/u', $text ) / 200 ) );
}

/**
 * Данные для строки ввода: куда можно перейти, что открыть и что ответить на «ping».
 */
function mrakells_shell_data(): array {
	$places = array();
	$menu   = get_nav_menu_locations()['primary'] ?? 0;

	foreach ( $menu ? (array) wp_get_nav_menu_items( $menu ) : array() as $item ) {
		$places[] = array(
			'name' => mrakells_path( $item->url ),
			'url'  => $item->url,
		);
	}

	foreach ( get_posts( array( 'numberposts' => 30 ) ) as $post ) {
		$places[] = array(
			'name' => mrakells_path( (string) get_permalink( $post ) ),
			'url'  => (string) get_permalink( $post ),
		);
	}

	$langs = array();

	foreach ( array_keys( mrakells_languages() ) as $lang ) {
		$langs[ $lang ] = mrakells_lang_url( $lang );
	}

	$server = mrakells_server();

	return array(
		'home'   => home_url( '/' ),
		'places' => $places,
		'links'  => array_map(
			static fn ( array $link ): array => array(
				'name' => $link['title'],
				'url'  => $link['url'],
			),
			mrakells_links()
		),
		'langs'  => $langs,
		'server' => $server ? array(
			'address' => $server['address'],
			'url'     => $server['url'],
			'endpoint' => add_query_arg( array( 'rest_route' => '/mrakells/v1/server', 'lang' => mrakells_lang() ), trailingslashit( mrakells_lang_root( MRAKELLS_DEFAULT_LANG ) ) ),
			'error'    => __( 'Live data could not be refreshed. Retrying…', 'mrakells' ),
		) : null,
		'help'   => array(
			'cd'    => __( 'go to a section or a post, e.g. cd blog', 'mrakells' ),
			'ls'    => __( 'list everything you can go to', 'mrakells' ),
			'open'  => __( 'open a link, e.g. open github', 'mrakells' ),
			'ping'  => __( 'game server status', 'mrakells' ),
			'theme' => __( 'accent colour: cyan, amber or green', 'mrakells' ),
			'fx'    => __( 'cursor trail in the background: on or off', 'mrakells' ),
			/* translators: %s: language codes */
			'lang'  => sprintf( __( 'switch language: %s', 'mrakells' ), implode( ', ', array_keys( mrakells_languages() ) ) ),
			'clear' => __( 'clear the output', 'mrakells' ),
		),
	);
}
