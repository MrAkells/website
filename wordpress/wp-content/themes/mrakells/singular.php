<?php
/**
 * Запись или страница: текст в левой колонке, справа — панель со сведениями о ней.
 * Сетка split одна на все текстовые страницы, поэтому их ширина совпадает с остальным сайтом.
 */

get_header();

while ( have_posts() ) :
	the_post();

	$terms = array_filter(
		array_merge( (array) get_the_category(), (array) get_the_tags() ),
		static fn ( $term ): bool => $term instanceof WP_Term
	);
	?>

	<article class="split">
		<div class="min-w-0">
			<p class="text-muted"><?php echo esc_html( mrakells_path( get_permalink() ) ); ?></p>
			<h1 class="pixel-shadow mt-3 font-pixel text-pixel-lg text-balance text-bright sm:text-pixel-xl"><?php the_title(); ?></h1>

			<div class="entry-content mt-9">
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>

			<?php
			if ( is_single() ) {
				the_post_navigation(
					array(
						'prev_text' => '&larr; %title',
						'next_text' => '%title &rarr;',
					)
				);
			}
			?>
		</div>

		<aside class="lg:pt-9">
			<section class="pixel-box lg:sticky lg:top-6">
				<header class="panel-head">
					<h2 class="prompt truncate">stat <?php echo esc_html( get_post_field( 'post_name' ) ); ?></h2>
				</header>

				<dl class="grid grid-cols-[auto_1fr] items-baseline gap-x-5 gap-y-3 p-5">
					<?php // У страницы важна только свежесть, у записи — дата публикации. ?>
					<?php if ( is_single() ) : ?>
						<dt class="label"><?php esc_html_e( 'published', 'mrakells' ); ?></dt>
						<dd><?php echo mrakells_time( (int) get_post_timestamp(), 'date' ); // phpcs:ignore WordPress.Security.EscapeOutput -- разметка собрана функцией. ?></dd>
					<?php endif; ?>

					<?php // «Обновлено» у записи показывается, если её меняли хотя бы через сутки после публикации. ?>
					<?php if ( ! is_single() || get_post_timestamp( null, 'modified' ) - get_post_timestamp() >= DAY_IN_SECONDS ) : ?>
						<dt class="label"><?php esc_html_e( 'updated', 'mrakells' ); ?></dt>
						<dd><?php echo mrakells_time( (int) get_post_timestamp( null, 'modified' ), 'date' ); // phpcs:ignore WordPress.Security.EscapeOutput -- разметка собрана функцией. ?></dd>
					<?php endif; ?>

					<dt class="label"><?php esc_html_e( 'reading', 'mrakells' ); ?></dt>
					<dd><?php echo esc_html( sprintf( /* translators: %d: minutes */ __( '%d min', 'mrakells' ), mrakells_reading_time( get_post() ) ) ); ?></dd>

					<?php if ( $terms ) : ?>
						<dt class="label"><?php esc_html_e( 'tags', 'mrakells' ); ?></dt>
						<dd class="flex flex-wrap gap-x-3 gap-y-1">
							<?php foreach ( $terms as $term ) : ?>
								<a class="text-warm hover:text-accent" href="<?php echo esc_url( get_term_link( $term ) ); ?>">#<?php echo esc_html( $term->name ); ?></a>
							<?php endforeach; ?>
						</dd>
					<?php endif; ?>
				</dl>
			</section>
		</aside>
	</article>

	<?php
endwhile;

get_footer();
