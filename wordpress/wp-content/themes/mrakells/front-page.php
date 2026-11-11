<?php
/**
 * Главная: первый экран о владельце сайта и последние записи блога.
 * Игровой сервер сюда не выводится — у него своя страница.
 *
 * Текст о себе — содержимое статической главной страницы (Настройки → Чтение).
 * Пока она не назначена, выводится краткое описание сайта.
 */

get_header();

$blog_page = (int) get_option( 'page_for_posts' );
$latest    = new WP_Query(
	array(
		'posts_per_page'      => 5,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
$links     = mrakells_links();
?>

<section class="grid items-center gap-x-12 gap-y-10 pb-12 pt-4 sm:pb-16 sm:pt-8 lg:grid-cols-[1fr_auto]">
	<div class="min-w-0">
		<p class="prompt text-muted">whoami</p>

		<h1 class="pixel-shadow mt-4 whitespace-nowrap font-pixel text-pixel-xl text-bright min-[420px]:text-pixel-2xl sm:text-pixel-4xl xl:text-pixel-5xl">
			<?php bloginfo( 'name' ); ?><span class="ml-[0.14em] inline-block h-[0.82em] w-[0.45em] animate-blink bg-accent align-[-0.09em] shadow-[calc(1em/11)_calc(1em/11)_0_var(--color-shade)] motion-reduce:animate-none" aria-hidden="true"></span>
		</h1>

		<div class="entry-content lede mt-8 max-w-[34em] sm:mt-11">
			<?php
			if ( is_page() ) {
				while ( have_posts() ) {
					the_post();
					the_content();
				}
			} else {
				echo '<p>' . esc_html( get_bloginfo( 'description' ) ) . '</p>';
			}
			?>
		</div>

		<?php if ( $links ) : ?>
			<nav class="mt-9" aria-label="<?php esc_attr_e( 'Links', 'mrakells' ); ?>">
				<ul class="flex flex-wrap gap-3">
					<?php foreach ( $links as $link ) : ?>
						<li>
							<a class="group flex items-center gap-2.5 border border-line bg-panel py-2 pl-2 pr-3.5 hover:border-accent hover:text-accent" href="<?php echo esc_url( $link['url'] ); ?>"<?php echo $link['external'] ? ' rel="me noopener"' : ''; ?><?php echo $link['key'] ? ' data-key="' . esc_attr( $link['key'] ) . '"' : ''; ?>>
								<?php if ( $link['key'] ) : ?>
									<span class="kbd group-hover:border-accent group-hover:text-accent" aria-hidden="true"><?php echo esc_html( $link['key'] ); ?></span>
								<?php endif; ?>
								<?php echo esc_html( $link['title'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
	</div>

	<div class="pixel-plate order-first mr-[11px] size-[132px] lg:order-none lg:mr-[22px] lg:size-[264px]">
		<?php // Под курсором голова превращается в фотографию — см. src/js/face.js. ?>
		<div class="size-full" data-face="<?php echo esc_url( get_theme_file_uri( 'images/me.jpg' ) ); ?>">
			<?php mrakells_avatar( 'size-full', get_bloginfo( 'name' ) ); ?>
		</div>
	</div>
</section>

<div class="dither" aria-hidden="true"></div>

<?php if ( $latest->have_posts() ) : ?>
	<section class="pixel-box mt-12 sm:mt-14">
		<header class="panel-head">
			<h2><span class="text-accent">~/</span><?php echo $blog_page ? esc_html( get_post_field( 'post_name', $blog_page ) ) : 'blog'; ?></h2>

			<?php if ( $blog_page ) : ?>
				<a class="ml-auto shrink-0 text-muted hover:text-accent" href="<?php echo esc_url( get_permalink( $blog_page ) ); ?>"><?php esc_html_e( 'all posts', 'mrakells' ); ?> &rarr;</a>
			<?php endif; ?>
		</header>

		<ul class="p-5">
			<?php
			while ( $latest->have_posts() ) {
				$latest->the_post();
				get_template_part( 'template-parts/post-row' );
			}
			wp_reset_postdata();
			?>
		</ul>
	</section>
<?php endif; ?>

<?php
get_footer();
