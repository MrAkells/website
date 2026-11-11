<?php
/** Игроки онлайн; разметка используется публичным live endpoint. */
$status = $args['status'];
$online = $status['online'];
?>
<section class="pixel-box">
	<header class="panel-head">
		<h2 class="prompt">who</h2>

		<?php if ( $online ) : ?>
			<span class="ml-auto text-muted"><?php echo (int) $status['players_online']; ?>/<?php echo (int) $status['players_max']; ?></span>
		<?php endif; ?>
	</header>

	<?php if ( $online && $status['players'] ) : ?>
		<ul class="grid gap-x-6 gap-y-2.5 p-5 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
			<?php foreach ( $status['players'] as $player ) : ?>
				<li class="flex min-w-0 items-center gap-2.5">
					<?php echo mrakells_player_head( $player ); // phpcs:ignore WordPress.Security.EscapeOutput -- разметка собрана функцией, ника в ней нет. ?>
					<span class="truncate"><?php echo esc_html( $player['name'] ); ?></span>
				</li>
			<?php endforeach; ?>

			<?php if ( $status['players_online'] > count( $status['players'] ) ) : ?>
				<li class="text-muted"><?php echo esc_html( sprintf( /* translators: %d: number of players */ __( '... and %d more', 'mrakells' ), $status['players_online'] - count( $status['players'] ) ) ); ?></li>
			<?php endif; ?>
		</ul>
	<?php else : ?>
		<p class="p-5 text-muted"><?php $online ? esc_html_e( 'nobody is online', 'mrakells' ) : esc_html_e( 'the server is not responding', 'mrakells' ); ?></p>
	<?php endif; ?>
</section>
