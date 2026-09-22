<?php
/**
 * The MonoRanks cell in a Posts or Pages row. $state: unconnected | draft | none | scored, plus the fields Column::cell_data sets.
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="monoranks-cell" data-theme="<?php echo esc_attr( \MonoRanks\Admin::theme() ); ?>">
<?php if ( 'unconnected' === $state ) : ?>
	<span class="xs"><a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Connect MonoRanks', 'monoranks' ); ?></a></span>
<?php elseif ( 'draft' === $state ) : ?>
	<span class="xs"><?php esc_html_e( 'Scored after publishing', 'monoranks' ); ?></span>
<?php elseif ( 'none' === $state ) : ?>
	<div class="row"><?php echo \MonoRanks\Admin::ring( null, 'sm', __( 'Health', 'monoranks' ) ); ?><?php echo \MonoRanks\Admin::ring( null, 'sm', __( 'AEO', 'monoranks' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?></div>
	<span class="xs"><?php esc_html_e( 'Not audited yet', 'monoranks' ); ?><?php if ( $next_audit ) : ?> · <?php echo esc_html( sprintf( /* translators: %s: relative time such as "5 days" */ __( 'next audit in %s', 'monoranks' ), human_time_diff( time(), max( time() + MINUTE_IN_SECONDS, (int) strtotime( $next_audit ) ) ) ) ); ?><?php endif; ?></span>
<?php else : ?>
	<div class="row"><?php echo \MonoRanks\Admin::ring( $health, 'sm', __( 'Health', 'monoranks' ) ); ?><?php echo \MonoRanks\Admin::ring( $aeo, 'sm', __( 'AEO', 'monoranks' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?></div>
	<span class="xs">
		<?php if ( $fixes_ready > 0 ) : ?>
			<a href="<?php echo esc_url( $overview_url ); ?>"><?php echo esc_html( $line ); ?></a>
		<?php elseif ( $page_url ) : ?>
			<a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $line ); ?></a>
		<?php else : ?>
			<?php echo esc_html( $line ); ?>
		<?php endif; ?>
	</span>
<?php endif; ?>
</div>
