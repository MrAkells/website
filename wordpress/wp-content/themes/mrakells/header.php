<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<?php // Акцент, выбранный командой «theme», применяется до первой отрисовки, чтобы цвет не мигал. ?>
<script>try { var t = localStorage.getItem( 'mrakells-theme' ); if ( t ) { document.documentElement.dataset.theme = t; } } catch ( e ) {}</script>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-20 focus:bg-bg focus:px-3 focus:py-1" href="#content"><?php esc_html_e( 'Skip to content', 'mrakells' ); ?></a>

<?php // Нижний отступ — место под закреплённую строку состояния. relative — чтобы содержимое лежало над холстом со следом курсора. ?>
<div class="relative mx-auto max-w-[76rem] px-5 pb-20 sm:px-8">
	<?php // На телефоне шапка в две строки: логотип с языками и под ними вкладки; с планшета — в одну. ?>
	<header class="flex flex-wrap items-center gap-x-7 gap-y-3 border-b border-line py-4">
		<a class="flex items-center gap-3 hover:text-accent" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php mrakells_avatar( 'size-[22px]' ); ?>
			<span class="font-pixel text-pixel-sm leading-none"><?php bloginfo( 'name' ); ?></span>
		</a>

		<?php if ( has_nav_menu( 'primary' ) ) : ?>
			<?php // Вкладки на узком экране занимают всю ширину до краёв и прокручиваются вбок, если не помещаются. ?>
			<nav class="scrollbar-none order-2 -mx-5 basis-[calc(100%+2.5rem)] overflow-x-auto px-5 md:order-none md:mx-0 md:basis-auto md:overflow-visible md:px-0" aria-label="<?php esc_attr_e( 'Main', 'mrakells' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'site-nav',
						'depth'          => 1,
					)
				);
				?>
			</nav>
		<?php endif; ?>

		<div class="order-1 ml-auto flex items-center gap-5 md:order-none">
			<?php // Кнопка появляется вместе со строкой ввода, когда загрузился скрипт. ?>
			<button class="hidden w-56 items-center justify-between gap-3 border border-line bg-panel py-1 pl-3 pr-2 text-left text-muted hover:border-edge lg:flex" type="button" data-shell-focus hidden>
				<span><?php esc_html_e( 'jump to…', 'mrakells' ); ?></span>
				<span class="kbd" aria-hidden="true">/</span>
			</button>

			<?php mrakells_language_switcher(); ?>
		</div>
	</header>

	<main id="content" class="py-10 sm:py-14">
