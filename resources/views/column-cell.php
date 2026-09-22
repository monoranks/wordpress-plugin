<?php
/**
 * The MonoRanks cell in a Posts or Pages row: two score rings; the details (fixes waiting, issues, audit date) sit in the
 * tooltip. $state: unconnected | draft | none | scored, plus the fields Column::cell_data sets.
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="monoranks-cell">
<?php if ( 'unconnected' === $state ) : ?>
	<span class="xs"><a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Connect MonoRanks', 'monoranks' ); ?></a></span>
<?php elseif ( 'draft' === $state ) : ?>
	<span class="xs" title="<?php esc_attr_e( 'Scored after publishing', 'monoranks' ); ?>">&mdash;</span>
<?php elseif ( 'none' === $state ) : ?>
	<div class="row" title="<?php echo esc_attr( $hint ); ?>"><?php echo \MonoRanks\Admin::ring( null, 'sm', __( 'Health', 'monoranks' ) ) . \MonoRanks\Admin::ring( null, 'sm', __( 'AEO', 'monoranks' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?></div>
<?php else : ?>
	<?php $monoranks_link = $fixes_ready > 0 ? $overview_url : $page_url; ?>
	<?php if ( $monoranks_link ) : ?><a class="row" href="<?php echo esc_url( $monoranks_link ); ?>"<?php echo $fixes_ready > 0 ? '' : ' target="_blank" rel="noopener"'; ?> title="<?php echo esc_attr( $line ); ?>"><?php else : ?><div class="row" title="<?php echo esc_attr( $line ); ?>"><?php endif; ?>
		<?php echo \MonoRanks\Admin::ring( $health, 'sm', __( 'Health', 'monoranks' ) ) . \MonoRanks\Admin::ring( $aeo, 'sm', __( 'AEO', 'monoranks' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?>
	<?php echo $monoranks_link ? '</a>' : '</div>'; ?>
<?php endif; ?>
</div>
