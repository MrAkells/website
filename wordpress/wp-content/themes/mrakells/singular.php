<?php
/**
 * Запись или страница. У записи справа — панель со сведениями о ней,
 * страница выводится одной колонкой шириной как список блога.
 */

get_header();

while ( have_posts() ) :
	the_post();

	$terms = array_filter(
		array_merge( (array) get_the_category(), (array) get_the_tags() ),
		static fn ( $term ): bool => $term instanceof WP_Term
	);
	?>

	<?php // Две колонки нужны только записи с панелью; страница — одна колонка шириной как список блога. ?>
	<article class="<?php echo is_single() ? 'grid gap-x-14 gap-y-10 lg:grid-cols-[minmax(0,44rem)_minmax(0,1fr)]' : 'max-w-[52rem]'; ?>">
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

		<?php if ( is_single() ) : ?>
			<aside class="lg:pt-9">
				<section class="pixel-box lg:sticky lg:top-6">
					<header class="panel-head">
						<h2 class="prompt truncate">stat <?php echo esc_html( get_post_field( 'post_name' ) ); ?></h2>
					</header>

					<dl class="grid grid-cols-[auto_1fr] items-baseline gap-x-5 gap-y-3 p-5">
						<dt class="label"><?php esc_html_e( 'published', 'mrakells' ); ?></dt>
						<dd><?php echo mrakells_time( (int) get_post_timestamp(), 'date' ); // phpcs:ignore WordPress.Security.EscapeOutput -- разметка собрана функцией. ?></dd>

						<?php // «Обновлено» показывается, если запись меняли хотя бы через сутки после публикации. ?>
						<?php if ( get_post_timestamp( null, 'modified' ) - get_post_timestamp() >= DAY_IN_SECONDS ) : ?>
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
		<?php endif; ?>
	</article>

	<?php
endwhile;

get_footer();
