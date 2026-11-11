<?php
/**
 * Plugin Name: Debloat
 * Description: Убирает из WordPress всё, что не нужно сайту на собственной классической теме: Gutenberg, комментарии, пинги, XML-RPC, RSS, поиск, архивы авторов и дат, анонимный REST API и служебный мусор в разметке.
 * Version:     2.0.0
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * XML-RPC
 * ---------------------------------------------------------------------- */

// xmlrpc.php объявляет константу до загрузки ядра, поэтому запрос обрывается сразу.
if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
	http_response_code( 403 );
	exit;
}

/* -------------------------------------------------------------------------
 * <head> и HTTP-заголовки
 * ---------------------------------------------------------------------- */

remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
remove_action( 'wp_head', 'feed_links', 2 );
remove_action( 'wp_head', 'feed_links_extra', 3 );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 4 );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );

remove_action( 'template_redirect', 'rest_output_link_header', 11 );
remove_action( 'template_redirect', 'wp_shortlink_header', 11 );

/* -------------------------------------------------------------------------
 * Emoji
 * ---------------------------------------------------------------------- */

remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
add_filter( 'tiny_mce_plugins', static fn ( array $plugins ): array => array_diff( $plugins, array( 'wpemoji' ) ) );

/* -------------------------------------------------------------------------
 * Gutenberg
 * ---------------------------------------------------------------------- */

// Классический редактор для всех типов записей и классические виджеты.
add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );
add_filter( 'use_widgets_block_editor', '__return_false' );

// Паттерны блоков: встроенные и с wordpress.org.
add_action( 'after_setup_theme', static fn () => remove_theme_support( 'core-block-patterns' ) );
add_filter( 'should_load_remote_block_patterns', '__return_false' );

// Стили отдельных блоков по требованию. Вместе с ними уходит буферизация всей страницы,
// которую ядро включает классическим темам, чтобы поднять эти стили в <head>.
remove_action( 'wp_default_styles', 'wp_load_classic_theme_block_styles_on_demand', 0 );

// Общая библиотека блоков, правки для классических тем, global styles и стили block supports.
remove_action( 'wp_enqueue_scripts', 'wp_common_block_scripts_and_styles' );
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles' );
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_stored_styles' );
remove_action( 'wp_footer', 'wp_enqueue_stored_styles', 1 );

/* -------------------------------------------------------------------------
 * RSS, поиск, архивы авторов и дат, embed-шаблоны
 * ---------------------------------------------------------------------- */

add_action(
	'template_redirect',
	static function (): void {
		if ( is_feed() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}

		if ( is_search() || is_author() || is_date() || is_embed() ) {
			global $wp_query;

			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	},
	1
);

// Архивы авторов отключены, поэтому в sitemap им не место.
add_filter(
	'wp_sitemaps_add_provider',
	static fn ( $provider, string $name ) => 'users' === $name ? false : $provider,
	10,
	2
);

/* -------------------------------------------------------------------------
 * REST API — только для авторизованных
 * ---------------------------------------------------------------------- */

// Приоритет 101: после проверки cookie + nonce (100) и application passwords (90).
add_filter(
	'rest_authentication_errors',
	static function ( $result ) {
		if ( is_wp_error( $result ) || is_user_logged_in() ) {
			return $result;
		}

		// Единственное публичное исключение: чтение статуса настроенного игрового сервера.
		global $wp;
		$route = $wp->query_vars['rest_route'] ?? '';
		if ( is_string( $route ) && '/mrakells/v1/server' === rtrim( $route, '/' ) && in_array( $_SERVER['REQUEST_METHOD'] ?? '', array( 'GET', 'HEAD' ), true ) ) {
			return $result;
		}

		return new WP_Error(
			'rest_login_required',
			'Authentication required.',
			array( 'status' => 401 )
		);
	},
	101
);

/* -------------------------------------------------------------------------
 * Комментарии, трекбэки, пингбэки
 * ---------------------------------------------------------------------- */

add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );

add_action(
	'init',
	static function (): void {
		foreach ( get_post_types() as $post_type ) {
			remove_post_type_support( $post_type, 'comments' );
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	},
	100
);

// Счётчики комментариев в админке без запроса к базе.
add_filter(
	'wp_count_comments',
	static fn (): stdClass => (object) array(
		'approved'       => 0,
		'moderated'      => 0,
		'spam'           => 0,
		'trash'          => 0,
		'post-trashed'   => 0,
		'total_comments' => 0,
		'all'            => 0,
	)
);

// При публикации не ставить метки _pingme/_encloseme и не планировать cron-задачу do_pings.
remove_action( 'publish_post', '_publish_post_hook', 5 );
remove_action( 'do_pings', 'do_all_pings', 10 );

/* -------------------------------------------------------------------------
 * jQuery Migrate
 * ---------------------------------------------------------------------- */

// Только на фронте: в админке его могут ждать плагины.
add_action(
	'wp_default_scripts',
	static function ( WP_Scripts $scripts ): void {
		if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
			$scripts->registered['jquery']->deps = array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) );
		}
	}
);

/* -------------------------------------------------------------------------
 * Админка
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function (): void {
		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
		remove_submenu_page( 'themes.php', 'site-editor.php?p=/pattern' );
	}
);

add_action(
	'admin_init',
	static function (): void {
		global $pagenow;

		if ( in_array( $pagenow, array( 'edit-comments.php', 'comment.php', 'options-discussion.php' ), true ) ) {
			wp_safe_redirect( admin_url() );
			exit;
		}

		// Эти хуки регистрирует wp-admin/includes/admin-filters.php, раньше admin_init их ещё нет.
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
	}
);

add_action(
	'wp_dashboard_setup',
	static function (): void {
		// «Новости и мероприятия WordPress» ходят на внешние серверы при каждом открытии консоли.
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
	}
);

add_action(
	'admin_bar_menu',
	static function ( WP_Admin_Bar $admin_bar ): void {
		$admin_bar->remove_node( 'wp-logo' );
		$admin_bar->remove_node( 'comments' );
		$admin_bar->remove_node( 'search' );
	},
	999
);
