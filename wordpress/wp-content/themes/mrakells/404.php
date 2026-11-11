<?php
/**
 * Страница не найдена.
 */

get_header();

$path = mb_strimwidth( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ), 0, 80, '…' );
?>

<div class="py-6 sm:py-12">
	<p class="prompt text-muted">cd <?php echo esc_html( $path ); ?></p>
	<h1 class="pixel-shadow mt-4 font-pixel text-pixel-4xl text-bright sm:text-pixel-5xl">404</h1>
	<p class="mt-8 text-warm">cd: no such file or directory: <?php echo esc_html( $path ); ?></p>
	<p class="mt-8"><a class="link" href="<?php echo esc_url( home_url( '/' ) ); ?>">cd ~</a></p>
</div>

<?php
get_footer();
