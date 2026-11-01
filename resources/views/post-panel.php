<?php
/**
 * The contents of the MonoRanks box in the post editor (PostPanel::data). Loaded after the editor through REST.
 * $state: unconnected | draft | ok.
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;

use MonoRanks\Admin;
use MonoRanks\Column;

$monoranks_arrow = '<span class="mr-arrow" aria-hidden="true">&#8599;</span>';
?>
<?php if ( 'unconnected' === $state ) : ?>
	<p class="mr-panel-note"><a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Connect MonoRanks', 'monoranks' ); ?></a></p>
<?php elseif ( 'draft' === $state ) : ?>
	<p class="mr-panel-note"><?php esc_html_e( 'Scores appear after you publish.', 'monoranks' ); ?></p>
<?php else : ?>
	<?php
	// Only the page's scores, one row of Google numbers and the way into MonoRanks; the rest lives in MonoRanks.
	$monoranks_row = $values && is_array( $values['row'] ) && 'ok' === $values['search'] ? $values['row'] : null;
	?>
	<div class="mr-panel-section">
		<?php if ( $score && ( null !== $score['health'] || null !== $score['aeo'] ) ) : ?>
			<div class="mr-panel-rings"><?php echo Admin::ring( $score['health'], 'md', __( 'Health', 'monoranks' ), 'under' ) . Admin::ring( $score['aeo'], 'md', __( 'AI answers', 'monoranks' ), 'under' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?></div>
			<p class="mr-panel-note"><?php echo esc_html( Column::cell_line( $score ) ); ?></p>
		<?php else : ?>
			<p class="mr-panel-note"><?php esc_html_e( 'Not checked yet.', 'monoranks' ); ?></p>
		<?php endif; ?>
		<?php if ( $monoranks_row ) : ?>
			<dl class="mr-panel-tiles" title="<?php esc_attr_e( 'Google, 28 days', 'monoranks' ); ?>">
				<div><dt><?php esc_html_e( 'Clicks', 'monoranks' ); ?></dt><dd><?php echo esc_html( Admin::n( null !== $monoranks_row['clicks'] ? $monoranks_row['clicks'] : 0 ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Shown', 'monoranks' ); ?></dt><dd><?php echo esc_html( Admin::n( null !== $monoranks_row['impressions'] ? $monoranks_row['impressions'] : 0 ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Place', 'monoranks' ); ?></dt><dd><?php echo null !== $monoranks_row['position'] ? esc_html( Admin::digits( number_format_i18n( $monoranks_row['position'], 1 ) ) ) : '&mdash;'; ?></dd></div>
			</dl>
		<?php endif; ?>
	</div>

	<?php if ( $page_url ) : ?>
		<p class="mr-panel-foot"><a class="mr-btn" href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open in MonoRanks', 'monoranks' ); ?> <?php echo $monoranks_arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup ?><span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'monoranks' ); ?></span></a></p>
	<?php endif; ?>
<?php endif; ?>
