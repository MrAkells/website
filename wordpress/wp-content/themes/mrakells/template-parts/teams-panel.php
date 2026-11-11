<?php
/**
 * Панель команд и одиночных игроков: состав и продвижение по ступеням основной линейки
 * квестов. Каждая ступень — деление шкалы, закрашенное на долю выполненных в ней квестов.
 *
 * @var array{groups: array<int, array>} $args
 */
?>

<section class="pixel-box">
	<header class="panel-head">
		<h2 class="prompt">cat /etc/group</h2>
		<span class="ml-auto shrink-0 text-muted"><?php esc_html_e( 'tier progress', 'mrakells' ); ?></span>
	</header>

	<ul class="p-5">
		<?php foreach ( $args['groups'] as $group ) : ?>
			<li class="mt-5 border-t border-dashed border-line pt-5 first:mt-0 first:border-t-0 first:pt-0">
				<p class="flex items-center gap-3">
					<?php if ( $group['solo'] ) : ?>
						<?php echo mrakells_player_head( $group['members'][0] ); // phpcs:ignore WordPress.Security.EscapeOutput -- разметка собрана функцией, ника в ней нет. ?>
					<?php endif; ?>

					<b class="min-w-0 truncate font-medium"><?php echo esc_html( $group['name'] ); ?></b>

					<?php if ( $group['solo'] ) : ?>
						<span class="label shrink-0"><?php esc_html_e( 'solo', 'mrakells' ); ?></span>
					<?php endif; ?>

					<span class="ml-auto shrink-0 text-[13px] text-muted">
						<?php if ( '' !== $group['tier'] ) : ?>
							<?php echo esc_html( $group['tier'] ); ?> &middot;
						<?php endif; ?>
						<?php echo (int) $group['done']; ?>/<?php echo (int) $group['total']; ?>
					</span>
				</p>

				<div class="mt-3 flex gap-1" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: completed quests, 2: all quests */ __( '%1$d of %2$d main-line quests completed', 'mrakells' ), $group['done'], $group['total'] ) ); ?>">
					<?php foreach ( $group['tiers'] as $tier ) : ?>
						<?php
						// Начатая ступень видна, даже если в ней выполнен один квест из сотни.
						$share = $tier['total'] ? $tier['done'] / $tier['total'] : 0;
						$width = $tier['done'] ? max( 15, (int) round( $share * 100 ) ) : 0;
						?>
						<i class="h-3 flex-1 bg-raise shadow-[inset_0_0_0_1px_var(--color-line)]" title="<?php echo esc_attr( sprintf( '%s: %d/%d', $tier['label'], $tier['done'], $tier['total'] ) ); ?>">
							<?php if ( $width ) : ?>
								<b class="block h-full bg-accent" style="width: <?php echo (int) $width; ?>%"></b>
							<?php endif; ?>
						</i>
					<?php endforeach; ?>
				</div>

				<?php // У одиночки состава нет: его голова уже стоит рядом с ником. ?>
				<?php if ( ! $group['solo'] ) : ?>
					<ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[13px] text-muted">
						<?php foreach ( $group['members'] as $member ) : ?>
							<li class="flex items-center gap-2">
								<?php echo mrakells_player_head( $member, 'size-4' ); // phpcs:ignore WordPress.Security.EscapeOutput -- разметка собрана функцией, ника в ней нет. ?>
								<?php echo esc_html( $member['name'] ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
