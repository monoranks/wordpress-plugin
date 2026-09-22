<?php
/**
 * The MonoRanks cell in a Posts or Pages row: two score rings, and a card that appears on hover or keyboard focus with the
 * details (scores with a word, fixes waiting, open issues, audit dates, links). $state: unconnected | draft | none | scored,
 * plus the fields Column::cell_data sets. No JavaScript: the card is CSS.
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;

use MonoRanks\Admin;
?>
<div class="monoranks-cell">
<?php if ( 'unconnected' === $state ) : ?>
	<span class="xs"><a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Connect MonoRanks', 'monoranks' ); ?></a></span>
<?php elseif ( 'draft' === $state ) : ?>
	<span class="row" tabindex="0"><span class="xs">&mdash;</span></span>
	<div class="mr-tip" role="tooltip">
		<div class="t"><bdi><?php echo esc_html( $title ); ?></bdi></div>
		<div class="empty"><?php esc_html_e( 'Drafts are not sent to MonoRanks. This page gets its scores after the first audit that follows publishing.', 'monoranks' ); ?></div>
	</div>
<?php elseif ( 'none' === $state ) : ?>
	<span class="row" tabindex="0" aria-label="<?php echo esc_attr( $hint ); ?>"><?php echo Admin::ring( null, 'sm', __( 'Health', 'monoranks' ) ) . Admin::ring( null, 'sm', __( 'AEO', 'monoranks' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?></span>
	<div class="mr-tip" role="tooltip">
		<div class="t"><bdi><?php echo esc_html( $title ); ?></bdi></div>
		<div class="rings">
			<span class="rk"><?php echo Admin::ring( null, 'md' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?><span class="k"><b><?php esc_html_e( 'Health', 'monoranks' ); ?></b><span><?php echo esc_html( Admin::tone_word( null ) ); ?></span></span></span>
			<span class="rk"><?php echo Admin::ring( null, 'md' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?><span class="k"><b><?php esc_html_e( 'AEO', 'monoranks' ); ?></b><span><?php echo esc_html( Admin::tone_word( null ) ); ?></span></span></span>
		</div>
		<div class="kv">
			<div><?php esc_html_e( 'Status', 'monoranks' ); ?></div><div><?php esc_html_e( 'Not audited yet', 'monoranks' ); ?></div>
			<?php if ( $next_in ) : ?><div><?php esc_html_e( 'Next audit', 'monoranks' ); ?></div><div><?php echo esc_html( $next_in ); ?></div><?php endif; ?>
			<div><?php esc_html_e( 'Published', 'monoranks' ); ?></div><div><?php echo esc_html( $published ); ?></div>
		</div>
		<div class="foot"><span class="empty"><?php esc_html_e( 'Scores arrive after the next audit', 'monoranks' ); ?></span><a href="<?php echo esc_url( $app_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open MonoRanks', 'monoranks' ); ?> &#8599;</a></div>
	</div>
<?php else : ?>
	<span class="row" tabindex="0" aria-label="<?php echo esc_attr( $line ); ?>"><?php echo Admin::ring( $health, 'sm', __( 'Health', 'monoranks' ) ) . Admin::ring( $aeo, 'sm', __( 'AEO', 'monoranks' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?></span>
	<div class="mr-tip" role="tooltip">
		<div class="t"><bdi><?php echo esc_html( $title ); ?></bdi></div>
		<div class="rings">
			<span class="rk"><?php echo Admin::ring( $health, 'md' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?><span class="k"><b><?php esc_html_e( 'Health', 'monoranks' ); ?></b><span><?php echo esc_html( Admin::tone_word( $health ) ); ?></span></span></span>
			<span class="rk"><?php echo Admin::ring( $aeo, 'md' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?><span class="k"><b><?php esc_html_e( 'AEO', 'monoranks' ); ?></b><span><?php echo esc_html( Admin::tone_word( $aeo ) ); ?></span></span></span>
		</div>
		<div class="kv">
			<div><?php esc_html_e( 'Fixes', 'monoranks' ); ?></div>
			<?php if ( $fixes_ready > 0 ) : ?>
				<div class="critical"><?php echo esc_html( sprintf( /* translators: %s: number */ _n( '%s ready to apply', '%s ready to apply', $fixes_ready, 'monoranks' ), Admin::n( $fixes_ready ) ) ); ?></div>
			<?php else : ?>
				<div class="good"><?php esc_html_e( 'Nothing waiting', 'monoranks' ); ?></div>
			<?php endif; ?>
			<div><?php esc_html_e( 'Issues', 'monoranks' ); ?></div>
			<?php if ( $open_issues > 0 ) : ?>
				<div><?php echo esc_html( sprintf( /* translators: %s: number */ _n( '%s open', '%s open', $open_issues, 'monoranks' ), Admin::n( $open_issues ) ) ); ?></div>
			<?php else : ?>
				<div class="good"><?php esc_html_e( 'No open issues', 'monoranks' ); ?></div>
			<?php endif; ?>
			<?php if ( $audited ) : ?><div><?php esc_html_e( 'Audited', 'monoranks' ); ?></div><div><?php echo esc_html( $audited ); ?></div><?php endif; ?>
			<?php if ( $next_in ) : ?><div><?php esc_html_e( 'Next audit', 'monoranks' ); ?></div><div><?php echo esc_html( $next_in ); ?></div><?php endif; ?>
		</div>
		<div class="foot">
			<?php if ( $fixes_ready > 0 ) : ?><a href="<?php echo esc_url( $overview_url ); ?>"><?php esc_html_e( 'Apply fixes', 'monoranks' ); ?></a><?php else : ?><span></span><?php endif; ?>
			<?php if ( $page_url ) : ?><a href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open in MonoRanks', 'monoranks' ); ?> &#8599;</a><?php endif; ?>
		</div>
	</div>
<?php endif; ?>
</div>
