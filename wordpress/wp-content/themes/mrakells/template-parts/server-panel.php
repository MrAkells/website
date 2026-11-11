<?php
/**
 * Панель игрового сервера: онлайн, график за сутки, версия и адрес.
 *
 * @var array{server: array, status: array} $args
 */

$server  = $args['server'];
$status  = $args['status'];
$online  = $status['online'];
$history = mrakells_minecraft_history( $server['address'] );
$peak    = max( 0, ...array_map( 'intval', $history ) );
$checked = sprintf( /* translators: %s: time, HH:MM */ esc_html__( 'checked at %s', 'mrakells' ), mrakells_time( $status['checked'], 'time' ) );

$facts = array_filter(
	array(
		'minecraft'                      => $online ? $status['version'] : '',
		__( 'mods', 'mrakells' )         => $online && $status['mods'] ? (string) $status['mods'] : '',
		__( 'peak, 24 h', 'mrakells' )   => array_filter( $history, 'is_int' ) ? (string) $peak : '',
	),
	// Ноль — тоже значение: пик «0» показывается, пустая строка — нет.
	static fn ( string $value ): bool => '' !== $value
);
?>

<section class="pixel-box">
	<header class="panel-head">
		<span class="dot <?php echo $online ? 'text-ok' : 'text-warm'; ?>" aria-hidden="true"></span>
		<h2 class="min-w-0 truncate"><?php echo esc_html( $server['address'] ); ?></h2>
	</header>

	<div class="p-5">
		<div class="flex flex-wrap items-end gap-x-6 gap-y-5">
			<div>
				<p class="label"><?php esc_html_e( 'players online', 'mrakells' ); ?></p>

				<?php if ( $online ) : ?>
					<p class="mt-3.5 font-pixel text-pixel-3xl text-bright"><?php echo (int) $status['players_online']; ?><span class="text-pixel-lg text-muted">/<?php echo (int) $status['players_max']; ?></span></p>
				<?php else : ?>
					<p class="mt-3.5 font-pixel text-pixel-3xl text-muted">&mdash;</p>
				<?php endif; ?>
			</div>

			<p class="pb-1.5">
				<strong class="block font-medium <?php echo $online ? 'text-ok' : 'text-warm'; ?>"><?php echo $online ? esc_html__( 'online', 'mrakells' ) : esc_html__( 'offline', 'mrakells' ); ?></strong>
				<span class="text-[13px] text-muted"><?php echo $checked; // phpcs:ignore WordPress.Security.EscapeOutput -- перевод экранирован, время собрано функцией. ?></span>
			</p>

			<?php // График: столбец на каждый час, высота кратна «пикселю». Пустой час — точка на базовой линии. ?>
			<div class="flex h-[66px] basis-full items-end justify-between gap-1 sm:ml-auto sm:basis-auto sm:justify-start" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: number of players */ __( 'Players online over the last 24 hours, peak %d', 'mrakells' ), $peak ) ); ?>">
				<?php foreach ( $history as $hour => $players ) : ?>
					<?php
					$height = null === $players || $players < 1 ? 4 : 6 + (int) round( $players / $peak * 15 ) * 4;
					$color  = match ( true ) {
						null === $players => 'bg-line',
						$players < 0      => 'bg-warm',
						23 === $hour      => 'bg-accent',
						default           => 'bg-shade',
					};
					?>
					<i class="w-1.5 sm:w-2 <?php echo esc_attr( $color ); ?>" style="height: <?php echo (int) $height; ?>px"></i>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if ( $online && $status['motd'] ) : ?>
			<p class="mt-5 text-muted"><?php echo implode( ' &middot; ', array_map( 'esc_html', $status['motd'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- строки экранированы. ?></p>
		<?php endif; ?>

		<?php if ( $facts ) : ?>
			<dl class="-mx-5 mt-5 flex border-t border-line">
				<?php foreach ( $facts as $label => $value ) : ?>
					<div class="flex-1 border-l border-line px-5 pt-4 first:border-l-0">
						<dt class="label"><?php echo esc_html( $label ); ?></dt>
						<dd class="mt-2.5 font-pixel text-pixel-sm leading-none"><?php echo esc_html( $value ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		<?php endif; ?>

		<div class="mt-6 flex flex-col gap-3 sm:flex-row">
			<button class="group flex min-w-0 items-center justify-between gap-3 border border-dashed border-edge py-2 pl-4 pr-2.5 text-left hover:border-accent sm:flex-1" type="button" data-copy="<?php echo esc_attr( $server['address'] ); ?>" data-copied="<?php esc_attr_e( 'copied', 'mrakells' ); ?>">
				<span class="truncate select-all"><?php echo esc_html( $server['address'] ); ?></span>
				<span class="kbd shrink-0 group-hover:border-accent group-hover:text-accent" data-copy-label><?php esc_html_e( 'copy', 'mrakells' ); ?></span>
			</button>

			<?php if ( '' !== $server['map_url'] ) : ?>
				<a class="border border-accent bg-accent px-4 py-2 text-center font-semibold text-bg hover:bg-transparent hover:text-accent" href="<?php echo esc_url( $server['map_url'] ); ?>" rel="noopener"><?php esc_html_e( 'live map', 'mrakells' ); ?> &nearr;</a>
			<?php endif; ?>
		</div>
	</div>
</section>
