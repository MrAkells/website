<?php
/**
 * Мультиязычность: язык в адресе, переводы содержимого и строк темы, переключатель,
 * hreflang, canonical и карта сайта для каждого языка.
 *
 * Язык — первый сегмент адреса: /uk/…, /ru/…; основной язык живёт в корне сайта.
 * Для WordPress язык выглядит как установка в подкаталог: фильтр home_url добавляет
 * префикс ко всем ссылкам, а ядро само отрезает его от запроса перед разбором адреса.
 *
 * Запись на всех языках одна. Её поля и метаполя — текст на основном языке, перевод
 * лежит рядом в метаполях с суффиксом «:язык»: title:uk, content:uk, у рубрик и меток —
 * name:uk, у полей SCF — {имя поля}:uk. Пока перевода нет, показывается основной язык.
 *
 * В админке версия выбирается вкладками над формой записи или термина (?lang=uk):
 * та же форма показывает и сохраняет перевод, отдельных полей для него нет.
 *
 * Строки шаблонов переводятся словарями languages/{локаль}.l10n.php.
 */

declare( strict_types=1 );

const MRAKELLS_DEFAULT_LANG = 'en';

/**
 * Языки сайта: код в адресе => локаль WordPress и название на самом языке.
 *
 * @return array<string, array{locale: string, name: string}>
 */
function mrakells_languages(): array {
	return array(
		'en' => array(
			'locale' => 'en_US',
			'name'   => 'English',
		),
		'uk' => array(
			'locale' => 'uk',
			'name'   => 'Українська',
		),
		'ru' => array(
			'locale' => 'ru_RU',
			'name'   => 'Русский',
		),
	);
}

/**
 * Запрос, разобранный на язык и путь без языкового префикса.
 *
 * @return array{lang: string, prefixed: bool, path: string}
 */
function mrakells_request(): array {
	static $request = null;

	if ( null === $request ) {
		$base = rtrim( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ), '/' );
		$path = (string) strtok( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), '?' );

		if ( '' !== $base && str_starts_with( $path, $base . '/' ) ) {
			$path = substr( $path, strlen( $base ) );
		}

		$request = array(
			'lang'     => MRAKELLS_DEFAULT_LANG,
			'prefixed' => false,
			'path'     => $path,
		);

		// Публичный endpoint получает язык отдельно: его URL общий для всех версий сайта.
		$route = $_GET['rest_route'] ?? '';
		$lang  = $_GET['lang'] ?? '';
		if ( ( '/wp-json/mrakells/v1/server' === rtrim( $path, '/' ) || ( is_string( $route ) && '/mrakells/v1/server' === rtrim( $route, '/' ) ) ) && is_string( $lang ) && isset( mrakells_languages()[ $lang ] ) ) {
			$request['lang'] = $lang;
		}

		$codes = implode( '|', array_map( 'preg_quote', array_keys( mrakells_languages() ) ) );

		if ( preg_match( '#^/(' . $codes . ')(/.*)?$#', $path, $matches ) ) {
			$request = array(
				'lang'     => $matches[1],
				'prefixed' => true,
				'path'     => $matches[2] ?? '/',
			);
		}
	}

	return $request;
}

/**
 * Язык текущего запроса. В админке, REST и WP-CLI префикса нет, поэтому там всегда основной.
 */
function mrakells_lang(): string {
	return mrakells_request()['lang'];
}

/**
 * Язык версии, которую редактируют в админке: параметр lang формы или её адреса,
 * для ajax-запросов со страницы — параметр lang этой страницы.
 */
function mrakells_edit_lang(): string {
	// phpcs:ignore WordPress.Security.NonceVerification -- язык только выбирает версию, права проверяет сам экран.
	$lang = $_REQUEST['lang'] ?? null;

	if ( null === $lang && wp_doing_ajax() ) {
		parse_str( (string) wp_parse_url( (string) wp_get_referer(), PHP_URL_QUERY ), $query );
		$lang = $query['lang'] ?? null;
	}

	return is_string( $lang ) && isset( mrakells_languages()[ $lang ] ) ? $lang : MRAKELLS_DEFAULT_LANG;
}

/**
 * Язык, на котором сейчас читается и пишется содержимое: на сайте — язык адреса,
 * в админке — язык редактируемой версии.
 */
function mrakells_content_lang(): string {
	return is_admin() ? mrakells_edit_lang() : mrakells_lang();
}

/**
 * Корень сайта на заданном языке, без завершающего слэша.
 */
function mrakells_lang_root( string $lang ): string {
	$root = untrailingslashit( set_url_scheme( (string) get_option( 'home' ) ) );

	return MRAKELLS_DEFAULT_LANG === $lang ? $root : $root . '/' . $lang;
}

/**
 * Адрес текущей страницы на другом языке.
 */
function mrakells_lang_url( string $lang ): string {
	return mrakells_lang_root( $lang ) . mrakells_request()['path'];
}

/**
 * Перевод поля записи или термина; без указания языка — на язык адреса.
 * Пустая строка — перевода нет.
 */
function mrakells_translation( int $object_id, string $field, string $meta_type = 'post', ?string $lang = null ): string {
	$lang ??= mrakells_lang();

	if ( MRAKELLS_DEFAULT_LANG === $lang || ! $object_id ) {
		return '';
	}

	return trim( (string) get_metadata( $meta_type, $object_id, $field . ':' . $lang, true ) );
}

/**
 * Есть ли у записи версия на заданном языке. Заголовок сохраняется вместе с любой
 * версией, поэтому записи, собранной из одних полей SCF, этого признака тоже хватает.
 */
function mrakells_has_translation( int $post_id, string $lang ): bool {
	return MRAKELLS_DEFAULT_LANG === $lang
		|| '' !== mrakells_translation( $post_id, 'title', 'post', $lang )
		|| '' !== mrakells_translation( $post_id, 'content', 'post', $lang );
}

/* -------------------------------------------------------------------------
 * Язык запроса: локаль и ссылки
 * ---------------------------------------------------------------------- */

// Основной язык доступен только без префикса: /en/blog/ → /blog/.
if ( mrakells_request()['prefixed'] && MRAKELLS_DEFAULT_LANG === mrakells_lang() ) {
	wp_safe_redirect( mrakells_lang_url( MRAKELLS_DEFAULT_LANG ), 301 );
	exit;
}

if ( MRAKELLS_DEFAULT_LANG !== mrakells_lang() ) {
	$mrakells_locale = mrakells_languages()[ mrakells_lang() ]['locale'];

	// Тема подключается после того, как ядро загрузило переводы, поэтому язык переключается
	// на ходу. Без установленного языкового пакета переключение не срабатывает — тогда
	// локаль подменяется фильтром, чтобы <html lang> и словарь темы всё равно были верными.
	if ( ! switch_to_locale( $mrakells_locale ) ) {
		add_filter( 'locale', static fn (): string => $mrakells_locale );
	}

	// home_url() собирает адрес как «корень + / + путь»; префикс языка встаёт между ними.
	add_filter(
		'home_url',
		static function ( string $url, $path ): string {
			$tail = $path && is_string( $path ) ? '/' . ltrim( $path, '/' ) : '';

			return substr( $url, 0, strlen( $url ) - strlen( $tail ) ) . '/' . mrakells_lang() . $tail;
		},
		10,
		2
	);
}

// Строки темы. Обработчик регистрируется раньше остальных в functions.php,
// поэтому словарь уже подключён, когда тема называет свои области меню.
add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'mrakells', get_theme_file_path( 'languages' ) );
	}
);

/* -------------------------------------------------------------------------
 * Переводы содержимого
 * ---------------------------------------------------------------------- */

// Приоритет 1: перевод проходит ту же обработку, что и оригинал (типографика, абзацы, шорткоды).
add_filter(
	'the_title',
	static function ( $title, $post_id = 0 ) {
		$translated = mrakells_translation( (int) $post_id, 'title' );

		return '' !== $translated ? $translated : $title;
	},
	1,
	2
);

// Заголовок во вкладке браузера берётся из записи напрямую, мимо the_title.
add_filter(
	'single_post_title',
	static function ( $title, $post = null ) {
		$translated = $post instanceof WP_Post ? mrakells_translation( $post->ID, 'title' ) : '';

		return '' !== $translated ? $translated : $title;
	},
	1,
	2
);

add_filter(
	'the_content',
	static function ( $content ) {
		$post = get_post();

		if ( ! $post || ! in_the_loop() || post_password_required( $post ) ) {
			return $content;
		}

		$translated = mrakells_translation( $post->ID, 'content' );

		return '' !== $translated ? $translated : $content;
	},
	1
);

add_filter(
	'get_term',
	static function ( $term ) {
		if ( $term instanceof WP_Term ) {
			$translated = mrakells_translation( $term->term_id, 'name', 'term' );

			if ( '' !== $translated ) {
				$term->name = $translated;
			}
		}

		return $term;
	}
);

/* -------------------------------------------------------------------------
 * Поля SCF
 *
 * Все чтения и записи значений SCF проходят через эти три фильтра. На неосновном языке
 * обращение к полю X повторяется с именем «X:язык» — так переводится любое поле, включая
 * вложенные, и в группы полей ничего добавлять не нужно. Имя с двоеточием — уже перевод.
 * ---------------------------------------------------------------------- */

add_filter(
	'acf/pre_load_metadata',
	static function ( $pre, $post_id, $name, $hidden ) {
		$lang = mrakells_content_lang();

		if ( MRAKELLS_DEFAULT_LANG === $lang || str_contains( (string) $name, ':' ) ) {
			return $pre;
		}

		// null — перевода нет, и SCF сам прочитает значение основного языка.
		return acf_get_metadata( $post_id, $name . ':' . $lang, $hidden ) ?? $pre;
	},
	10,
	4
);

add_filter(
	'acf/pre_update_metadata',
	static function ( $pre, $post_id, $name, $value, $hidden ) {
		$lang = mrakells_content_lang();

		if ( MRAKELLS_DEFAULT_LANG === $lang || str_contains( (string) $name, ':' ) ) {
			return $pre;
		}

		return acf_update_metadata( $post_id, $name . ':' . $lang, $value, $hidden );
	},
	10,
	5
);

add_filter(
	'acf/pre_delete_metadata',
	static function ( $pre, $post_id, $name, $hidden ) {
		$lang = mrakells_content_lang();

		if ( MRAKELLS_DEFAULT_LANG === $lang || str_contains( (string) $name, ':' ) ) {
			return $pre;
		}

		return acf_delete_metadata( $post_id, $name . ':' . $lang, $hidden );
	},
	10,
	4
);

/* -------------------------------------------------------------------------
 * Поисковики: hreflang, canonical, sitemap
 * ---------------------------------------------------------------------- */

/**
 * Языки, на которых у текущей страницы есть собственный текст.
 * У записей и страниц — только переведённые, у списков — все.
 *
 * @return string[]
 */
function mrakells_page_languages(): array {
	$languages = array_keys( mrakells_languages() );

	if ( ! is_singular() ) {
		return $languages;
	}

	$post_id = get_queried_object_id();

	return array_values( array_filter( $languages, static fn ( string $lang ): bool => mrakells_has_translation( $post_id, $lang ) ) );
}

add_action(
	'wp_head',
	static function (): void {
		if ( is_404() ) {
			return;
		}

		foreach ( mrakells_page_languages() as $lang ) {
			printf( '<link rel="alternate" hreflang="%s" href="%s">' . "\n", esc_attr( $lang ), esc_url( mrakells_lang_url( $lang ) ) );
		}

		printf( '<link rel="alternate" hreflang="x-default" href="%s">' . "\n", esc_url( mrakells_lang_url( MRAKELLS_DEFAULT_LANG ) ) );
	},
	5
);

// Страница без перевода показывает текст основного языка — каноническим остаётся его адрес.
add_filter(
	'get_canonical_url',
	static function ( $url, $post ) {
		$root = mrakells_lang_root( mrakells_lang() );

		if ( $post instanceof WP_Post && ! mrakells_has_translation( $post->ID, mrakells_lang() ) && str_starts_with( (string) $url, $root ) ) {
			return mrakells_lang_root( MRAKELLS_DEFAULT_LANG ) . substr( (string) $url, strlen( $root ) );
		}

		return $url;
	},
	10,
	2
);

// У каждого языка своя карта сайта: /wp-sitemap.xml, /uk/wp-sitemap.xml, /ru/wp-sitemap.xml.
// В карту языка попадают только записи с переводом на него.
add_filter(
	'wp_sitemaps_posts_query_args',
	static function ( array $args ): array {
		if ( MRAKELLS_DEFAULT_LANG !== mrakells_lang() ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'OR',
				array(
					'key'     => 'content:' . mrakells_lang(),
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'key'     => 'title:' . mrakells_lang(),
					'value'   => '',
					'compare' => '!=',
				),
			);
		}

		return $args;
	}
);

// robots.txt один на весь сайт; ядро вписывает в него карту основного языка, остальные добавляются здесь.
add_filter(
	'robots_txt',
	static function ( string $output, $is_public ): string {
		if ( $is_public ) {
			foreach ( array_keys( mrakells_languages() ) as $lang ) {
				if ( mrakells_lang() !== $lang ) {
					$output .= 'Sitemap: ' . esc_url( mrakells_lang_root( $lang ) . '/wp-sitemap.xml' ) . "\n";
				}
			}
		}

		return $output;
	},
	20,
	2
);

/* -------------------------------------------------------------------------
 * Переключатель языка
 * ---------------------------------------------------------------------- */

function mrakells_language_switcher(): void {
	echo '<nav aria-label="' . esc_attr__( 'Language', 'mrakells' ) . '"><ul class="lang-nav">';

	foreach ( mrakells_languages() as $lang => $language ) {
		printf(
			'<li><a href="%s" lang="%s" hreflang="%s" title="%s"%s>%s</a></li>',
			esc_url( mrakells_lang_url( $lang ) ),
			esc_attr( $lang ),
			esc_attr( $lang ),
			esc_attr( $language['name'] ),
			mrakells_lang() === $lang ? ' aria-current="true"' : '',
			esc_html( $lang )
		);
	}

	echo '</ul></nav>';
}

/* -------------------------------------------------------------------------
 * Админка: вкладки языков над формой записи и термина
 * ---------------------------------------------------------------------- */

/**
 * Вкладки версий и скрытое поле, которое сообщает сохранению язык формы.
 *
 * @param string   $url        Адрес формы на основном языке.
 * @param string[] $translated Языки, на которых версия уже есть.
 * @param string   $object     Что редактируется: post или term.
 * @param int      $object_id  ID записи или термина.
 */
function mrakells_edit_tabs( string $url, array $translated, string $object, int $object_id ): void {
	$current = mrakells_edit_lang();

	echo '<input type="hidden" name="lang" value="' . esc_attr( $current ) . '">';
	echo '<nav class="nav-tab-wrapper" style="margin: 12px 0" aria-label="' . esc_attr__( 'Language', 'mrakells' ) . '">';

	foreach ( mrakells_languages() as $lang => $language ) {
		printf(
			'<a class="nav-tab%s" href="%s">%s%s</a>',
			$current === $lang ? ' nav-tab-active' : '',
			esc_url( MRAKELLS_DEFAULT_LANG === $lang ? $url : add_query_arg( 'lang', $lang, $url ) ),
			in_array( $lang, $translated, true ) ? '' : '+ ',
			esc_html( $language['name'] )
		);
	}

	echo '</nav>';

	if ( MRAKELLS_DEFAULT_LANG === $current ) {
		return;
	}

	if ( ! in_array( $current, $translated, true ) ) {
		echo '<p class="description">' . esc_html__( 'No translation yet: the form shows the text in the default language. Saving creates the translation.', 'mrakells' ) . '</p>';

		return;
	}

	$delete = wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'mrakells_delete_translation',
				'object' => $object,
				'id'     => $object_id,
				'lang'   => $current,
			),
			admin_url( 'admin-post.php' )
		),
		"mrakells_delete_translation_{$object}_{$object_id}_{$current}"
	);

	printf(
		'<p class="description"><a class="submitdelete" style="color: #b32d2e" href="%s" onclick="return confirm( %s )">%s</a></p>',
		esc_url( $delete ),
		esc_attr( (string) wp_json_encode( __( 'Delete this translation? The text in other languages stays.', 'mrakells' ) ) ),
		esc_html__( 'Delete translation', 'mrakells' )
	);
}

/**
 * Язык формы, которая сейчас сохраняется; пустая строка — основной язык или не форма с вкладками.
 */
function mrakells_saved_lang(): string {
	// phpcs:ignore WordPress.Security.NonceVerification -- nonce формы проверяет сам экран до сохранения.
	$lang = $_POST['lang'] ?? '';

	return is_string( $lang ) && isset( mrakells_languages()[ $lang ] ) && MRAKELLS_DEFAULT_LANG !== $lang ? $lang : '';
}

if ( is_admin() ) {

	/* --- Записи ---------------------------------------------------------- */

	add_action(
		'edit_form_top',
		static function ( WP_Post $post ): void {
			// Версии появляются у сохранённой записи: сначала она создаётся на основном языке.
			if ( ! is_post_type_viewable( $post->post_type ) || 'auto-draft' === $post->post_status ) {
				return;
			}

			mrakells_edit_tabs(
				(string) get_edit_post_link( $post, 'raw' ),
				array_values( array_filter( array_keys( mrakells_languages() ), static fn ( string $lang ): bool => mrakells_has_translation( $post->ID, $lang ) ) ),
				'post',
				$post->ID
			);

			// Предпросмотр и автосохранение работают с основными полями записи, поэтому у перевода их нет.
			if ( MRAKELLS_DEFAULT_LANG !== mrakells_edit_lang() ) {
				echo '<style>#preview-action { display: none; }</style>';
			}
		}
	);

	add_action(
		'admin_enqueue_scripts',
		static function (): void {
			if ( MRAKELLS_DEFAULT_LANG !== mrakells_edit_lang() ) {
				wp_dequeue_script( 'autosave' );
			}
		},
		100
	);

	// Заголовок и текст в форме: перевод, а пока его нет — основной язык как заготовка.
	foreach ( array( 'title', 'content' ) as $mrakells_field ) {
		add_filter(
			$mrakells_field . '_edit_pre',
			static function ( $value, $post_id ) use ( $mrakells_field ) {
				$translated = mrakells_translation( (int) $post_id, $mrakells_field, 'post', mrakells_edit_lang() );

				return '' !== $translated ? $translated : $value;
			},
			10,
			2
		);
	}

	// Сохранение перевода: заголовок и текст уходят в метаполя, сама запись остаётся прежней.
	add_filter(
		'wp_insert_post_data',
		static function ( array $data, array $postarr ): array {
			$lang = mrakells_saved_lang();
			$id   = (int) ( $postarr['ID'] ?? 0 );

			// Только сама запись из формы: ревизии и пункты меню, которые сохраняются тем же запросом, идут мимо.
			// phpcs:ignore WordPress.Security.NonceVerification
			if ( '' === $lang || ! $id || (int) ( $_POST['post_ID'] ?? 0 ) !== $id ) {
				return $data;
			}

			$original = get_post( $id );

			if ( ! $original ) {
				return $data;
			}

			foreach ( array( 'title', 'content' ) as $field ) {
				// $data здесь ещё со слэшами — update_post_meta() ждёт именно такие значения.
				update_post_meta( $id, $field . ':' . $lang, $data[ 'post_' . $field ] );

				$data[ 'post_' . $field ] = wp_slash( $original->{'post_' . $field} );
			}

			return $data;
		},
		10,
		2
	);

	// После сохранения форма открывается на том же языке.
	add_filter(
		'redirect_post_location',
		static fn ( string $location ): string => '' !== mrakells_saved_lang() ? add_query_arg( 'lang', mrakells_saved_lang(), $location ) : $location
	);

	// «Посмотреть страницу» из формы перевода ведёт на страницу этого языка.
	foreach ( array( 'post_link', 'page_link', 'post_type_link' ) as $mrakells_hook ) {
		add_filter(
			$mrakells_hook,
			static function ( $url ) {
				$root = mrakells_lang_root( MRAKELLS_DEFAULT_LANG );
				$lang = mrakells_edit_lang();

				return MRAKELLS_DEFAULT_LANG !== $lang && str_starts_with( (string) $url, $root )
					? mrakells_lang_root( $lang ) . substr( (string) $url, strlen( $root ) )
					: $url;
			}
		);
	}

	/* --- Список записей: колонка со ссылками на версии ------------------- */

	$mrakells_column = static function ( array $columns, string $post_type = 'page' ): array {
		if ( is_post_type_viewable( $post_type ) ) {
			$columns['translations'] = __( 'Translations' );
		}

		return $columns;
	};

	$mrakells_cell = static function ( string $column, int $post_id ): void {
		if ( 'translations' !== $column ) {
			return;
		}

		foreach ( mrakells_languages() as $lang => $language ) {
			if ( MRAKELLS_DEFAULT_LANG !== $lang ) {
				printf(
					'<a href="%s" title="%s">%s%s</a> ',
					esc_url( add_query_arg( 'lang', $lang, (string) get_edit_post_link( $post_id, 'raw' ) ) ),
					esc_attr( $language['name'] ),
					mrakells_has_translation( $post_id, $lang ) ? '' : '+',
					esc_html( $lang )
				);
			}
		}
	};

	add_filter( 'manage_posts_columns', $mrakells_column, 10, 2 );
	add_filter( 'manage_pages_columns', $mrakells_column );
	add_action( 'manage_posts_custom_column', $mrakells_cell, 10, 2 );
	add_action( 'manage_pages_custom_column', $mrakells_cell, 10, 2 );

	/* --- Рубрики и метки ------------------------------------------------- */

	add_action(
		'admin_init',
		static function (): void {
			foreach ( get_taxonomies( array( 'public' => true ) ) as $taxonomy ) {
				add_action(
					$taxonomy . '_term_edit_form_top',
					static function ( WP_Term $term ): void {
						mrakells_edit_tabs(
							(string) get_edit_term_link( $term ),
							array_values( array_filter( array_keys( mrakells_languages() ), static fn ( string $lang ): bool => MRAKELLS_DEFAULT_LANG === $lang || '' !== mrakells_translation( $term->term_id, 'name', 'term', $lang ) ) ),
							'term',
							$term->term_id
						);
					}
				);
			}
		}
	);

	add_filter(
		'edit_term_name',
		static function ( $value, $term_id ) {
			$translated = mrakells_translation( (int) $term_id, 'name', 'term', mrakells_edit_lang() );

			return '' !== $translated ? $translated : $value;
		},
		10,
		2
	);

	add_filter(
		'wp_update_term_data',
		static function ( array $data, int $term_id, string $taxonomy ): array {
			$lang = mrakells_saved_lang();

			// phpcs:ignore WordPress.Security.NonceVerification
			if ( '' === $lang || (int) ( $_POST['tag_ID'] ?? 0 ) !== $term_id ) {
				return $data;
			}

			$original = get_term_field( 'name', $term_id, $taxonomy, 'raw' );

			if ( is_string( $original ) ) {
				update_term_meta( $term_id, 'name:' . $lang, wp_slash( $data['name'] ) );

				$data['name'] = $original;
			}

			return $data;
		},
		10,
		3
	);

	/* --- Удаление версии ------------------------------------------------- */

	add_action(
		'admin_post_mrakells_delete_translation',
		static function (): void {
			$object = 'term' === ( $_GET['object'] ?? '' ) ? 'term' : 'post';
			$id     = (int) ( $_GET['id'] ?? 0 );
			$lang   = mrakells_edit_lang();

			check_admin_referer( "mrakells_delete_translation_{$object}_{$id}_{$lang}" );

			if ( MRAKELLS_DEFAULT_LANG === $lang || ! current_user_can( 'edit_' . $object, $id ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to edit this item.' ), 403 );
			}

			// Версия — это все метаполя с суффиксом языка, включая значения полей SCF.
			foreach ( array_keys( (array) get_metadata( $object, $id ) ) as $key ) {
				if ( str_ends_with( (string) $key, ':' . $lang ) ) {
					delete_metadata( $object, $id, (string) $key );
				}
			}

			wp_safe_redirect( (string) ( 'term' === $object ? get_edit_term_link( $id ) : get_edit_post_link( $id, 'raw' ) ) );
			exit;
		}
	);
}
