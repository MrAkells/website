<?php
/**
 * Списки записей: блог, рубрики и метки.
 */

global $wp, $wp_query;

get_header();
?>

<div class="max-w-[52rem]">
	<p class="text-muted"><?php echo esc_html( mrakells_path( home_url( $wp->request ) ) ); ?></p>
	<h1 class="pixel-shadow mt-3 font-pixel text-pixel-lg text-balance text-bright sm:text-pixel-2xl"><?php echo esc_html( is_home() ? get_the_title( (int) get_option( 'page_for_posts' ) ) : single_term_title( '', false ) ); ?></h1>

	<section class="pixel-box mt-10">
		<header class="panel-head">
			<h2 class="prompt">ls -t <?php echo esc_html( mrakells_path( home_url( $wp->request ) ) ); ?>/</h2>
			<span class="ml-auto shrink-0 text-muted">total <?php echo (int) $wp_query->found_posts; ?></span>
		</header>

		<?php if ( have_posts() ) : ?>
			<ul class="p-5">
				<?php
				while ( have_posts() ) {
					the_post();
					get_template_part( 'template-parts/post-row' );
				}
				?>
			</ul>
		<?php endif; ?>
	</section>

	<?php
	the_posts_pagination(
		array(
			'mid_size'  => 1,
			'prev_text' => '&larr; ' . esc_html__( 'newer', 'mrakells' ),
			'next_text' => esc_html__( 'older', 'mrakells' ) . ' &rarr;',
		)
	);
	?>
</div>

<?php
get_footer();
