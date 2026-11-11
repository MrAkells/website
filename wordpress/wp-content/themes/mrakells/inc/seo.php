<?php
/**
 * Превью ссылок и поисковики: описание страницы, Open Graph (Telegram, соцсети, мессенджеры),
 * Twitter Card и структурированные данные schema.org для Google.
 *
 * hreflang, canonical и карты сайта по языкам — в i18n.php.
 *
 * Картинка превью — миниатюра записи, а если её нет — общая карточка images/og.png (1200×630).
 * Имя владельца для schema.org — «Имя» и «Фамилия» в профиле администратора.
 */

declare( strict_types=1 );

// Миниатюра на самом сайте не выводится: это картинка для превью ссылки на запись.
add_action( 'after_setup_theme', static fn () => add_theme_support( 'post-thumbnails' ) );

/**
 * Короткое описание из HTML: первый абзац с текстом, обрезанный по слову.
 * Google сам укорачивает длинное описание, а Telegram показывает его целиком, поэтому запас до 200 знаков.
 * Заголовки и списки пропускаются — в описании они читаются как склеенный текст.
 */
function mrakells_summary( string $html, int $length = 200 ): string {
	$html = strip_shortcodes( $html );
	$text = '';

	if ( preg_match_all( '#<p[^>]*>(.*?)</p>#is', $html, $paragraphs ) ) {
		foreach ( $paragraphs[1] as $paragraph ) {
			$text = trim( wp_strip_all_tags( $paragraph ) );

			if ( '' !== $text ) {
				break;
			}
		}
	}

	if ( '' === $text ) {
		$text = wp_strip_all_tags( $html );
	}

	$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );

	if ( mb_strlen( $text ) <= $length ) {
		return $text;
	}

	$cut = (string) preg_replace( '/\s+\S*$/u', '', mb_substr( $text, 0, $length ) );

	return rtrim( $cut, ' ,.;:–—-' ) . '…';
}

/**
 * Текст в атрибут: ядро отдаёт заголовки уже с HTML-сущностями (&#8211;, &#8217;).
 */
function mrakells_plain( string $text ): string {
	return trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

/**
 * Текст записи на языке страницы: перевод, а без него — отрывок или текст основного языка.
 */
function mrakells_post_summary( WP_Post $post ): string {
	$translated = mrakells_translation( $post->ID, 'content' );

	if ( '' !== $translated ) {
		return mrakells_summary( $translated );
	}

	return mrakells_summary( '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : $post->post_content );
}

/**
 * Описание текущей страницы для meta description и превью. Пустая строка — описания нет.
 */
function mrakells_description(): string {
	$object = get_queried_object();

	if ( is_singular() && $object instanceof WP_Post ) {
		return mrakells_post_summary( $object );
	}

	if ( is_home() ) {
		$blog = get_post( (int) get_option( 'page_for_posts' ) );
		$text = $blog ? mrakells_post_summary( $blog ) : '';

		/* translators: %s: site name */
		return '' !== $text ? $text : sprintf( __( 'Posts from the %s blog.', 'mrakells' ), get_bloginfo( 'name' ) );
	}

	if ( ( is_category() || is_tag() ) && $object instanceof WP_Term ) {
		$text = mrakells_summary( term_description( $object ) );

		/* translators: 1: tag name, 2: site name */
		return '' !== $text ? $text : sprintf( __( 'Posts tagged #%1$s on %2$s.', 'mrakells' ), $object->name, get_bloginfo( 'name' ) );
	}

	return is_404() ? '' : mrakells_plain( get_bloginfo( 'description' ) );
}

/**
 * Заголовок для превью. Название сайта Telegram и соцсети показывают отдельно (og:site_name),
 * поэтому здесь только название страницы; у главной — полный заголовок вкладки.
 */
function mrakells_share_title(): string {
	$object = get_queried_object();

	if ( is_front_page() ) {
		return mrakells_plain( wp_get_document_title() );
	}

	if ( is_singular() && $object instanceof WP_Post ) {
		return mrakells_plain( get_the_title( $object ) );
	}

	if ( is_home() ) {
		return mrakells_plain( get_the_title( (int) get_option( 'page_for_posts' ) ) );
	}

	if ( $object instanceof WP_Term ) {
		return '#' . mrakells_plain( $object->name );
	}

	return mrakells_plain( wp_get_document_title() );
}

/**
 * Адрес текущей страницы для превью: у записей — канонический, у списков — текущий.
 */
function mrakells_share_url(): string {
	$canonical = is_singular() ? wp_get_canonical_url() : false;

	return $canonical ? $canonical : mrakells_lang_url( mrakells_lang() );
}

/**
 * Картинка превью: миниатюра записи или общая карточка сайта.
 *
 * @return array{url: string, width: int, height: int, alt: string}
 */
function mrakells_share_image(): array {
	$object = get_queried_object();

	if ( is_singular() && $object instanceof WP_Post && has_post_thumbnail( $object ) ) {
		$id    = (int) get_post_thumbnail_id( $object );
		$image = wp_get_attachment_image_src( $id, 'full' );

		if ( $image ) {
			$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );

			return array(
				'url'    => $image[0],
				'width'  => (int) $image[1],
				'height' => (int) $image[2],
				'alt'    => '' !== $alt ? $alt : mrakells_share_title(),
			);
		}
	}

	return array(
		'url'    => get_theme_file_uri( 'images/og.png' ),
		'width'  => 1200,
		'height' => 630,
		'alt'    => get_bloginfo( 'name' ),
	);
}

/**
 * Владелец сайта — первый администратор. Имя — «Имя» и «Фамилия» из профиля,
 * пока они не заполнены — отображаемое имя.
 *
 * @return array{name: string, nick: string}
 */
function mrakells_owner(): array {
	static $owner = null;

	if ( null === $owner ) {
		$users = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
			)
		);
		$user  = $users[0] ?? null;
		$nick  = $user ? $user->display_name : get_bloginfo( 'name' );
		$name  = $user ? trim( $user->first_name . ' ' . $user->last_name ) : '';

		$owner = array(
			'name' => '' !== $name ? $name : $nick,
			'nick' => $nick,
		);
	}

	return $owner;
}

/**
 * Названия меток записи на языке страницы.
 *
 * @return string[]
 */
function mrakells_post_tags( WP_Post $post ): array {
	$tags = get_the_terms( $post, 'post_tag' );

	return is_array( $tags ) ? array_map( static fn ( WP_Term $tag ): string => $tag->name, $tags ) : array();
}

/**
 * Локаль в формате Open Graph: у украинского в WordPress код без региона.
 */
function mrakells_og_locale( string $lang ): string {
	$locale = mrakells_languages()[ $lang ]['locale'];

	return array( 'uk' => 'uk_UA' )[ $locale ] ?? $locale;
}

/**
 * Структурированные данные страницы: сайт и владелец, запись или страница, хлебные крошки.
 *
 * @return array<int, array<string, mixed>>
 */
function mrakells_schema(): array {
	$object  = get_queried_object();
	$root    = trailingslashit( mrakells_lang_root( mrakells_lang() ) );
	$person  = trailingslashit( mrakells_lang_root( MRAKELLS_DEFAULT_LANG ) ) . '#person';
	$website = $root . '#website';
	$lang    = str_replace( '_', '-', mrakells_og_locale( mrakells_lang() ) );
	$owner   = mrakells_owner();
	$url     = mrakells_share_url();

	$same_as = array_values(
		array_map(
			static fn ( array $link ): string => $link['url'],
			array_filter( mrakells_links(), static fn ( array $link ): bool => $link['external'] )
		)
	);

	$graph = array(
		array_filter(
			array(
				'@type'         => 'Person',
				'@id'           => $person,
				'name'          => $owner['name'],
				'alternateName' => $owner['name'] !== $owner['nick'] ? $owner['nick'] : null,
				'url'           => trailingslashit( mrakells_lang_root( MRAKELLS_DEFAULT_LANG ) ),
				'image'         => get_site_icon_url( 512 ) ? get_site_icon_url( 512 ) : null,
				'sameAs'        => $same_as ? $same_as : null,
			)
		),
		array(
			'@type'       => 'WebSite',
			'@id'         => $website,
			'url'         => $root,
			'name'        => get_bloginfo( 'name' ),
			'description' => mrakells_plain( get_bloginfo( 'description' ) ),
			'inLanguage'  => $lang,
			'publisher'   => array( '@id' => $person ),
		),
	);

	if ( is_singular() && $object instanceof WP_Post && ! is_front_page() ) {
		$is_post = 'post' === $object->post_type;
		$terms   = $is_post ? mrakells_post_tags( $object ) : array();

		$graph[] = array_filter(
			array(
				'@type'            => $is_post ? 'BlogPosting' : 'WebPage',
				'@id'              => $url . '#main',
				'url'              => $is_post ? null : $url,
				'mainEntityOfPage' => $is_post ? $url : null,
				( $is_post ? 'headline' : 'name' ) => mrakells_share_title(),
				'description'      => mrakells_description(),
				'image'            => mrakells_share_image()['url'],
				'inLanguage'       => $lang,
				'datePublished'    => get_post_time( DATE_W3C, true, $object ),
				'dateModified'     => get_post_modified_time( DATE_W3C, true, $object ),
				'author'           => $is_post ? array( '@id' => $person ) : null,
				'publisher'        => $is_post ? array( '@id' => $person ) : null,
				'isPartOf'         => array( '@id' => $website ),
				'keywords'         => $terms ? implode( ', ', $terms ) : null,
			)
		);
	}

	// Хлебные крошки: главная → блог (у записей) → текущая страница.
	if ( ! is_front_page() && ! is_404() ) {
		$crumbs = array(
			array( get_bloginfo( 'name' ), $root ),
		);

		$blog = (int) get_option( 'page_for_posts' );

		if ( $blog && ( is_single() || is_category() || is_tag() ) ) {
			$crumbs[] = array( mrakells_plain( get_the_title( $blog ) ), (string) get_permalink( $blog ) );
		}

		$crumbs[] = array( mrakells_share_title(), $url );

		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array_map(
				static fn ( array $crumb, int $index ): array => array(
					'@type'    => 'ListItem',
					'position' => $index + 1,
					'name'     => $crumb[0],
					'item'     => $crumb[1],
				),
				$crumbs,
				array_keys( $crumbs )
			),
		);
	}

	return $graph;
}

add_action(
	'wp_head',
	static function (): void {
		if ( is_404() ) {
			return;
		}

		$object      = get_queried_object();
		$description = mrakells_description();
		$image       = mrakells_share_image();
		$is_post     = is_single() && $object instanceof WP_Post;

		$meta = array(
			array( 'name', 'description', $description ),
			array( 'property', 'og:type', $is_post ? 'article' : 'website' ),
			array( 'property', 'og:site_name', get_bloginfo( 'name' ) ),
			array( 'property', 'og:title', mrakells_share_title() ),
			array( 'property', 'og:description', $description ),
			array( 'property', 'og:url', mrakells_share_url() ),
			array( 'property', 'og:locale', mrakells_og_locale( mrakells_lang() ) ),
		);

		foreach ( mrakells_page_languages() as $lang ) {
			if ( mrakells_lang() !== $lang ) {
				$meta[] = array( 'property', 'og:locale:alternate', mrakells_og_locale( $lang ) );
			}
		}

		$meta[] = array( 'property', 'og:image', $image['url'] );
		$meta[] = array( 'property', 'og:image:width', (string) $image['width'] );
		$meta[] = array( 'property', 'og:image:height', (string) $image['height'] );
		$meta[] = array( 'property', 'og:image:alt', $image['alt'] );

		if ( $is_post ) {
			$meta[] = array( 'property', 'article:published_time', (string) get_post_time( DATE_W3C, true, $object ) );
			$meta[] = array( 'property', 'article:modified_time', (string) get_post_modified_time( DATE_W3C, true, $object ) );

			foreach ( mrakells_post_tags( $object ) as $tag ) {
				$meta[] = array( 'property', 'article:tag', $tag );
			}
		}

		// Заголовок, описание и картинку X берёт из Open Graph; здесь только крупный формат карточки.
		$meta[] = array( 'name', 'twitter:card', 'summary_large_image' );

		foreach ( $meta as [ $attribute, $key, $value ] ) {
			if ( '' !== $value ) {
				printf( '<meta %s="%s" content="%s">' . "\n", $attribute, esc_attr( $key ), esc_attr( $value ) );
			}
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => mrakells_schema(),
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
			)
		);
	},
	6
);
