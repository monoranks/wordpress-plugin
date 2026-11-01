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
use MonoRanks\PageValues;

$monoranks_arrow = '<span class="mr-arrow" aria-hidden="true">&#8599;</span>';
$monoranks_scope = static function ( $url ) {
	return esc_html__( 'No access yet.', 'monoranks' ) . ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Allow', 'monoranks' ) . '</a>';
};
?>
<?php if ( 'unconnected' === $state ) : ?>
	<p class="mr-panel-note"><a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Connect MonoRanks', 'monoranks' ); ?></a></p>
<?php elseif ( 'draft' === $state ) : ?>
	<p class="mr-panel-note"><?php esc_html_e( 'Scores appear after you publish.', 'monoranks' ); ?></p>
<?php else : ?>
	<?php
	$monoranks_row  = $values && is_array( $values['row'] ) ? $values['row'] : null;
	$monoranks_lost = $values && is_array( $values['lost'] ) ? $values['lost'] : null;
	?>
	<div class="mr-panel-section">
		<?php if ( $score && ( null !== $score['health'] || null !== $score['aeo'] ) ) : ?>
			<div class="mr-panel-rings"><?php echo Admin::ring( $score['health'], 'md', __( 'Health', 'monoranks' ), 'under' ) . Admin::ring( $score['aeo'], 'md', __( 'AI answers', 'monoranks' ), 'under' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?></div>
			<p class="mr-panel-note"><?php echo esc_html( Column::cell_line( $score ) ); ?></p>
		<?php else : ?>
			<p class="mr-panel-note"><?php esc_html_e( 'Not checked yet.', 'monoranks' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="mr-panel-section">
		<h3 class="mr-panel-h"><?php esc_html_e( 'Google, 28 days', 'monoranks' ); ?></h3>
		<?php if ( ! $values ) : ?>
			<p class="mr-panel-note"><?php esc_html_e( 'Coming with the next refresh.', 'monoranks' ); ?></p>
		<?php elseif ( 'no_access' === $values['search'] && 'ok' !== $values['analytics'] ) : ?>
			<p class="mr-panel-note"><?php echo $monoranks_scope( $scope_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure ?></p>
		<?php elseif ( 'ok' !== $values['search'] ) : ?>
			<p class="mr-panel-note"><?php esc_html_e( 'Connect Search Console in MonoRanks.', 'monoranks' ); ?></p>
		<?php else : ?>
			<dl class="mr-panel-tiles">
				<div><dt><?php esc_html_e( 'Clicks', 'monoranks' ); ?></dt><dd><?php echo esc_html( Admin::n( $monoranks_row && null !== $monoranks_row['clicks'] ? $monoranks_row['clicks'] : 0 ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Shown', 'monoranks' ); ?></dt><dd><?php echo esc_html( Admin::n( $monoranks_row && null !== $monoranks_row['impressions'] ? $monoranks_row['impressions'] : 0 ) ); ?></dd></div>
				<?php if ( $monoranks_row && null !== $monoranks_row['position'] ) : ?>
					<div><dt><?php esc_html_e( 'Place', 'monoranks' ); ?></dt><dd><?php echo esc_html( Admin::digits( number_format_i18n( $monoranks_row['position'], 1 ) ) ); ?></dd></div>
				<?php endif; ?>
			</dl>
			<?php if ( 'ok' === $queries['state'] ) : ?>
				<p class="mr-panel-sub"><?php esc_html_e( 'Top searches', 'monoranks' ); ?></p>
				<ul class="mr-panel-list">
					<?php foreach ( $queries['rows'] as $monoranks_q ) : ?>
						<li><bdi><?php echo esc_html( $monoranks_q['query'] ); ?></bdi> <span class="mr-panel-mute"><?php echo esc_html( sprintf( /* translators: %s: number of clicks */ _n( '%s click', '%s clicks', $monoranks_q['clicks'], 'monoranks' ), Admin::n( $monoranks_q['clicks'] ) ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<?php if ( $values ) : ?>
		<div class="mr-panel-section">
			<h3 class="mr-panel-h"><?php esc_html_e( 'Visits, 28 days', 'monoranks' ); ?></h3>
			<?php if ( 'ok' === $values['analytics'] ) : ?>
				<dl class="mr-panel-tiles">
					<div><dt><?php esc_html_e( 'Visits', 'monoranks' ); ?></dt><dd><?php echo esc_html( Admin::n( $monoranks_row && isset( $monoranks_row['sessions'] ) ? $monoranks_row['sessions'] : 0 ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Goals', 'monoranks' ); ?></dt><dd><?php echo esc_html( Admin::n( $monoranks_row && isset( $monoranks_row['key_events'] ) ? $monoranks_row['key_events'] : 0 ) ); ?></dd></div>
					<div><dt><?php esc_html_e( 'Sales', 'monoranks' ); ?></dt><dd><?php echo esc_html( PageValues::money( $monoranks_row && isset( $monoranks_row['revenue'] ) ? $monoranks_row['revenue'] : 0, $values['currency'] ) ); ?></dd></div>
				</dl>
			<?php elseif ( 'no_access' === $values['analytics'] ) : ?>
				<p class="mr-panel-note"><?php echo $monoranks_scope( $scope_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure ?></p>
			<?php elseif ( 'not_connected' === $values['analytics'] ) : ?>
				<p class="mr-panel-note"><a href="<?php echo esc_url( $analytics_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Connect Analytics', 'monoranks' ); ?> <?php echo $monoranks_arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup ?></a></p>
			<?php else : ?>
				<p class="mr-panel-note"><?php esc_html_e( 'Not available right now.', 'monoranks' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $monoranks_lost ) : ?>
		<div class="mr-panel-section">
			<h3 class="mr-panel-h" title="<?php esc_attr_e( 'These sites used to link to this page. Fix the address if it moved, or ask them to update the link.', 'monoranks' ); ?>"><?php echo esc_html( sprintf( /* translators: %s: number of links */ _n( '%s lost link to win back', '%s lost links to win back', $monoranks_lost['count'], 'monoranks' ), Admin::n( $monoranks_lost['count'] ) ) ); ?></h3>
			<ul class="mr-panel-list">
				<?php foreach ( $monoranks_lost['top'] as $monoranks_l ) : ?>
					<li><?php if ( $monoranks_l['link'] ) : ?><a href="<?php echo esc_url( $monoranks_l['link'] ); ?>" target="_blank" rel="noopener"><bdi><?php echo esc_html( $monoranks_l['domain'] ); ?></bdi></a><?php else : ?><bdi><?php echo esc_html( $monoranks_l['domain'] ); ?></bdi><?php endif; ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="mr-panel-section">
		<h3 class="mr-panel-h"><?php esc_html_e( 'Last change', 'monoranks' ); ?></h3>
		<?php if ( 'ok' === $history['state'] ) : ?>
			<p class="mr-panel-note" title="<?php echo esc_attr( Admin::ago( $history['at'] ) ); ?>"><?php echo esc_html( Admin::ago_short( $history['at'] ) ); ?></p>
			<ul class="mr-panel-list">
				<?php foreach ( $history['changes'] as $monoranks_c ) : ?>
					<li><?php echo esc_html( $monoranks_c ); ?></li>
				<?php endforeach; ?>
				<?php if ( $history['more'] > 0 ) : ?>
					<li class="mr-panel-mute"><?php echo esc_html( sprintf( /* translators: %s: number of changes */ _n( 'and %s more', 'and %s more', $history['more'], 'monoranks' ), Admin::n( $history['more'] ) ) ); ?></li>
				<?php endif; ?>
			</ul>
		<?php elseif ( 'unchanged' === $history['state'] ) : ?>
			<p class="mr-panel-note"><?php esc_html_e( 'No change lately.', 'monoranks' ); ?></p>
		<?php elseif ( 'empty' === $history['state'] ) : ?>
			<p class="mr-panel-note"><?php esc_html_e( 'No history yet.', 'monoranks' ); ?></p>
		<?php else : ?>
			<p class="mr-panel-note"><?php esc_html_e( 'Not available right now.', 'monoranks' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $page_url ) : ?>
		<p class="mr-panel-foot"><a class="mr-btn" href="<?php echo esc_url( $page_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open in MonoRanks', 'monoranks' ); ?> <?php echo $monoranks_arrow; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup ?><span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'monoranks' ); ?></span></a></p>
	<?php endif; ?>
<?php endif; ?>
