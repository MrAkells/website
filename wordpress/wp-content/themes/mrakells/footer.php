<?php
/**
 * Низ страницы: строка ввода команд и закреплённая строка состояния.
 */

$path = mrakells_path( home_url( $GLOBALS['wp']->request ) );
?>
	</main>

	<?php // Строка ввода работает на скрипте и без него не показывается. Вывод команд — в журнале над ней. ?>
	<section class="border border-line bg-panel" aria-label="<?php esc_attr_e( 'Command line', 'mrakells' ); ?>" data-shell hidden>
		<div class="hidden max-h-72 overflow-y-auto whitespace-pre-wrap border-b border-line px-5 py-3 text-[13px] leading-6 [&:not(:empty)]:block" role="log" data-shell-log></div>

		<form class="flex items-center gap-3 px-5 py-4" data-shell-form>
			<label class="shrink-0 text-accent" for="shell-input"><?php echo esc_html( $path ); ?> $</label>
			<input class="min-w-0 flex-1 bg-transparent text-base caret-accent outline-none placeholder:text-dim" id="shell-input" type="text" placeholder="help" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="go" data-shell-input>

			<span class="hidden shrink-0 items-center gap-2.5 text-[13px] text-muted lg:flex" aria-hidden="true">
				<span class="kbd normal-case">help</span>
				<span class="kbd normal-case">ls</span>
				<span class="kbd normal-case">cd blog</span>
				<span class="kbd normal-case">theme amber</span>
			</span>
		</form>

		<script type="application/json" data-shell-data><?php echo wp_json_encode( mrakells_shell_data(), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
	</section>
</div>

<footer class="fixed inset-x-0 bottom-0 z-10 flex h-[33px] items-stretch whitespace-nowrap border-t border-line bg-bg text-[13px] leading-8">
	<span class="bg-accent px-3.5 font-bold text-bg" aria-hidden="true" data-mode>NORMAL</span>
	<span class="truncate bg-raise px-3.5"><?php echo esc_html( $path ); ?></span>

	<?php // Подсказки по клавишам имеют смысл только со скриптом и на устройствах с клавиатурой. ?>
	<span class="hidden gap-5 px-3.5 text-muted md:flex" aria-hidden="true" data-keys hidden>
		<span><b class="font-medium text-fg">/</b> <?php esc_html_e( 'command', 'mrakells' ); ?></span>
		<span><b class="font-medium text-fg">0-9</b> <?php esc_html_e( 'tabs', 'mrakells' ); ?></span>
		<span><b class="font-medium text-fg">?</b> <?php esc_html_e( 'help', 'mrakells' ); ?></span>
	</span>

	<span class="flex-1"></span>

	<span class="hidden border-l border-line px-3.5 text-muted sm:block"><?php echo mrakells_time( time(), 'time' ); // phpcs:ignore WordPress.Security.EscapeOutput -- разметка собрана функцией. ?></span>
	<span class="hidden border-l border-line px-3.5 text-muted sm:block">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></span>
</footer>

<?php wp_footer(); ?>
</body>
</html>
