<?php
/**
 * Строка записи в списке: дата, заголовок, время чтения и рубрики.
 */

$categories = wp_list_pluck( (array) get_the_category(), 'name' );
$minutes    = sprintf( /* translators: %d: minutes */ __( '%d min', 'mrakells' ), mrakells_reading_time( get_post() ) );
?>
<li class="grid gap-x-4 gap-y-0.5 border-t border-dashed border-line py-3.5 first:border-t-0 first:pt-0 last:pb-0 sm:grid-cols-[6rem_1fr]">
	<?php echo mrakells_time( (int) get_post_timestamp(), 'date', 'pt-0.5 text-[13px] text-muted' ); // phpcs:ignore WordPress.Security.EscapeOutput -- разметка собрана функцией. ?>

	<div class="min-w-0">
		<a class="font-medium text-bright hover:text-accent" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		<p class="text-[13px] text-muted"><?php echo esc_html( implode( ' · ', array_merge( array( $minutes ), $categories ) ) ); ?></p>
	</div>
</li>
