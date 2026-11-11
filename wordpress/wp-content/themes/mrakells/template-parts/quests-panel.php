<?php
/**
 * Панель последних выполненных квестов.
 *
 * @var array{quests: array<int, array>} $args
 */

$quests = $args['quests'];
?>

<section class="pixel-box">
	<header class="panel-head">
		<h2 class="prompt">tail -n <?php echo count( $quests ); ?> quests.log</h2>
	</header>

	<ul>
		<?php foreach ( $quests as $index => $quest ) : ?>
			<?php $chapter = mrakells_chapter( $quest['chapter'] ); ?>

			<?php // Самая свежая запись подсвечена: полоса слева и затухающая подложка. ?>
			<li class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-line px-5 py-3 first:border-t-0 <?php echo 0 === $index ? 'bg-linear-to-r from-accent/7 to-transparent to-60% shadow-[inset_4px_0_0_var(--color-accent)]' : ''; ?>">
				<span class="flex shrink-0 gap-1">
					<?php
					foreach ( array_slice( $quest['players'], 0, 3 ) as $player ) {
						echo mrakells_player_head( $player ); // phpcs:ignore WordPress.Security.EscapeOutput -- разметка собрана функцией, ника в ней нет.
					}
					?>
				</span>

				<div class="min-w-48 flex-1">
					<p class="text-bright">
						<?php if ( $quest['url'] ) : ?>
							<a class="hover:text-accent hover:underline hover:decoration-dashed hover:underline-offset-4" href="<?php echo esc_url( $quest['url'] ); ?>" rel="noopener"><?php echo esc_html( $quest['quest'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $quest['quest'] ); ?>
						<?php endif; ?>
					</p>

					<p class="text-[13px] text-muted">
						<span class="text-warm"><?php echo esc_html( implode( ', ', array_column( $quest['players'], 'name' ) ) ); ?></span>
						<?php if ( $quest['team'] ) : ?>
							@<?php echo esc_html( $quest['team'] ); ?>
						<?php endif; ?>
						&middot;
						<?php echo mrakells_time( $quest['time'], 'datetime', 'whitespace-nowrap' ); // phpcs:ignore WordPress.Security.EscapeOutput -- разметка собрана функцией. ?>
					</p>
				</div>

				<?php if ( '' !== $chapter['label'] ) : ?>
					<span class="chip max-w-full shrink-0 truncate <?php echo null === $chapter['rank'] ? 'text-muted' : 'text-warm'; ?>" title="<?php echo esc_attr( $quest['chapter'] ); ?>"><?php echo esc_html( $chapter['label'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
