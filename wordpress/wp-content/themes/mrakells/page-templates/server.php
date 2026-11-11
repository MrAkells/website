<?php
/**
 * Template Name: Server status
 *
 * Страница игрового сервера: живой статус, игроки онлайн, карта, последние квесты и команды,
 * ниже — текст страницы. Адрес сервера берётся из произвольного поля server_address,
 * адрес ленты квестов — из поля quests_url, адрес веб-карты — из поля map_url этой страницы.
 */

get_header();

while ( have_posts() ) :
	the_post();

	$address = trim( (string) get_post_meta( get_the_ID(), 'server_address', true ) );
	$server  = array(
		'id'         => get_the_ID(),
		'url'        => (string) get_permalink(),
		'name'       => get_post_field( 'post_name' ),
		'address'    => $address,
		'quests_url' => trim( (string) get_post_meta( get_the_ID(), 'quests_url', true ) ),
		'map_url'    => trim( (string) get_post_meta( get_the_ID(), 'map_url', true ) ),
	);
	?>

	<article>
		<p class="text-muted"><?php echo esc_html( mrakells_path( get_permalink() ) ); ?></p>
		<h1 class="pixel-shadow mt-3 font-pixel text-pixel-lg text-balance text-bright sm:text-pixel-2xl"><?php the_title(); ?></h1>

		<?php // min-w-0 у ячеек: без него строка без переносов распирает сетку шире экрана телефона. ?>
		<div class="mt-10 grid gap-x-7 gap-y-9 lg:grid-cols-12">
			<?php if ( '' !== $address ) : ?>
				<div class="min-w-0 lg:col-span-7" data-server-panel="status" aria-busy="true">
					<p class="pixel-box p-5" data-server-loading><?php esc_html_e( 'Loading live data…', 'mrakells' ); ?></p>
				</div>
				<div class="min-w-0 lg:col-span-5" data-server-panel="players" aria-busy="true"></div>
			<?php endif; ?>

			<?php if ( '' !== $server['map_url'] ) : ?>
				<div class="min-w-0 lg:col-span-12">
					<?php // Карта тяжёлая и постоянно опрашивает сервер, поэтому фрейм существует, только пока блок раскрыт. ?>
					<details class="group pixel-box" data-map>
						<summary class="panel-head cursor-pointer list-none border-b-0 group-open:border-b [&::-webkit-details-marker]:hidden">
							<span class="prompt shrink-0 whitespace-nowrap">
								<span class="link group-open:hidden"><?php esc_html_e( 'open map', 'mrakells' ); ?></span>
								<span class="link hidden group-open:inline"><?php esc_html_e( 'close map', 'mrakells' ); ?></span>
							</span>
							<span class="min-w-0 truncate text-muted group-open:hidden"><?php esc_html_e( '# live map, loads on click', 'mrakells' ); ?></span>
							<a class="ml-auto hidden shrink-0 text-muted hover:text-accent group-open:inline" href="<?php echo esc_url( $server['map_url'] ); ?>" rel="noopener"><?php esc_html_e( 'open in full screen', 'mrakells' ); ?> &nearr;</a>
						</summary>

						<div data-map-frame></div>

						<template>
							<iframe
								class="block aspect-square w-full sm:aspect-video"
								src="<?php echo esc_url( add_query_arg( 'nogui', 'true', $server['map_url'] ) ); ?>"
								title="<?php echo esc_attr( sprintf( /* translators: %s: page title */ __( 'Live map: %s', 'mrakells' ), get_the_title() ) ); ?>"
								referrerpolicy="no-referrer"
								allow="fullscreen"
							></iframe>
						</template>
					</details>

					<script>
						document.querySelectorAll( 'details[data-map]' ).forEach( function ( details ) {
							var holder = details.querySelector( '[data-map-frame]' );
							var template = details.querySelector( 'template' );

							details.addEventListener( 'toggle', function () {
								if ( details.open ) {
									holder.replaceChildren( template.content.cloneNode( true ) );
								} else {
									holder.replaceChildren();
								}
							} );
						} );
					</script>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $server['quests_url'] ) : ?>
				<div class="min-w-0 lg:col-span-7" data-server-panel="quests" hidden></div>
				<div class="min-w-0 lg:col-span-5" data-server-panel="teams" hidden></div>
			<?php endif; ?>
		</div>

		<p class="mt-5 text-[13px] text-warm" data-server-live-message role="status" hidden></p>
		<noscript><p class="mt-5 text-muted"><?php esc_html_e( 'Live data requires JavaScript.', 'mrakells' ); ?></p></noscript>

		<?php if ( '' !== trim( get_the_content() ) ) : ?>
			<section class="mt-14">
				<h2 class="prompt text-muted">cat README.md</h2>

				<div class="entry-content mt-5 max-w-measure">
					<?php the_content(); ?>
				</div>
			</section>
		<?php endif; ?>
	</article>

	<?php
endwhile;

get_footer();
