<?php
/**
 * MonoRanks → Overview. Data from Overview::data().
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;

use MonoRanks\Admin;

$monoranks_o = $overview;
?>
<div class="wrap monoranks" data-theme="<?php echo esc_attr( $theme ); ?>">
	<div class="row wrap between head">
		<div class="col">
			<h1 class="h1">MonoRanks</h1>
			<p class="sub">
				<?php if ( $monoranks_o ) : ?>
					<?php echo wp_kses_post( sprintf( /* translators: %s: site host */ __( 'Weekly SEO, AEO and GEO audit of %s', 'monoranks' ), '<span class="code">' . esc_html( $host ) . '</span>' ) ); ?>
					<?php if ( $audited ) : ?> · <?php echo esc_html( sprintf( /* translators: %s: relative time */ __( 'last audit %s', 'monoranks' ), $audited ) ); ?><?php endif; ?>
					<?php if ( $monoranks_o['pages_total'] ) : ?> · <?php echo esc_html( sprintf( /* translators: 1: scored, 2: total */ __( '%1$s of %2$s published pages scored', 'monoranks' ), number_format_i18n( $monoranks_o['pages_scored'] ), number_format_i18n( $monoranks_o['pages_total'] ) ) ); ?><?php endif; ?>
				<?php else : ?>
					<?php esc_html_e( 'Weekly SEO, AEO and GEO audits, with the fixes you approve written into WordPress.', 'monoranks' ); ?>
				<?php endif; ?>
			</p>
		</div>
		<div class="row wrap">
			<?php if ( $connected ) : ?>
			<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="inline">
				<?php wp_nonce_field( 'monoranks_send_now' ); ?>
				<input type="hidden" name="action" value="monoranks_send_now"><input type="hidden" name="back" value="overview">
				<button class="btn" type="submit"><?php esc_html_e( 'Sync content now', 'monoranks' ); ?></button>
			</form>
			<?php endif; ?>
			<a class="btn primary" href="<?php echo esc_url( $app_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open in MonoRanks', 'monoranks' ); ?></a>
		</div>
	</div>

	<?php echo Admin::view( 'partials/notice', array( 'notice' => $notice ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?>

	<?php if ( ! $connected || $revoked ) : ?>
		<?php echo Admin::view( 'overview-empty', array( 'kind' => $revoked ? 'revoked' : 'unconnected', 'settings_url' => $settings_url, 'app_url' => $app_url ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?>
	<?php elseif ( ! $monoranks_o ) : ?>
		<?php echo Admin::view( 'overview-empty', array( 'kind' => $status, 'settings_url' => $settings_url, 'app_url' => $app_url, 'last_sent' => $last_sent, 'items' => $items, 'fetched' => $fetched ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?>
	<?php else : ?>

	<div class="tiles">
		<div class="card tile">
			<div class="row between"><span class="label"><?php esc_html_e( 'Health score', 'monoranks' ); ?></span><?php echo Admin::view( 'partials/delta', array( 'n' => $monoranks_o['deltas']['health'], 'unit' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?></div>
			<div class="row" style="gap:14px"><?php echo Admin::ring( $monoranks_o['health'], 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?><div class="col" style="gap:2px"><span class="sm sub"><?php esc_html_e( 'Technical, on-page, links, speed', 'monoranks' ); ?></span><span class="xs mut"><?php echo esc_html( sprintf( /* translators: %s: number of pages */ _n( 'Site average, %s page', 'Site average, %s pages', $monoranks_o['pages_scored'], 'monoranks' ), number_format_i18n( $monoranks_o['pages_scored'] ) ) ); ?></span></div></div>
		</div>
		<div class="card tile">
			<div class="row between"><span class="label"><?php esc_html_e( 'AEO score', 'monoranks' ); ?></span><?php echo Admin::view( 'partials/delta', array( 'n' => $monoranks_o['deltas']['aeo'], 'unit' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?></div>
			<div class="row" style="gap:14px"><?php echo Admin::ring( $monoranks_o['aeo'], 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?><div class="col" style="gap:2px"><span class="sm sub"><?php esc_html_e( 'Answer-first, structure, entities', 'monoranks' ); ?></span><span class="xs mut"><?php esc_html_e( 'How well AI answers can use your pages', 'monoranks' ); ?></span></div></div>
		</div>
		<div class="card tile">
			<div class="row between"><span class="label"><?php esc_html_e( 'Search clicks, 28 days', 'monoranks' ); ?></span><?php if ( $monoranks_o['traffic'] ) : ?><?php echo Admin::view( 'partials/delta', array( 'n' => $monoranks_o['traffic']['delta_pct'], 'unit' => '%' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?><?php endif; ?></div>
			<?php if ( $monoranks_o['traffic'] ) : ?>
				<div class="stat num"><?php echo esc_html( number_format_i18n( $monoranks_o['traffic']['clicks_28d'] ) ); ?></div>
				<?php echo Admin::view( 'partials/sparkline', array( 'series' => $monoranks_o['traffic']['series'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?>
				<span class="xs mut"><?php esc_html_e( 'From Google Search Console, through MonoRanks', 'monoranks' ); ?></span>
			<?php else : ?>
				<div class="stat mut">&mdash;</div>
				<span class="xs mut"><?php esc_html_e( 'Connect Google Search Console in MonoRanks to see clicks here.', 'monoranks' ); ?></span>
			<?php endif; ?>
		</div>
		<div class="card tile">
			<div class="row between"><span class="label"><?php esc_html_e( 'Fixes', 'monoranks' ); ?></span><?php if ( $monoranks_o['fixes']['ready'] ) : ?><span class="badge"><?php echo esc_html( sprintf( /* translators: %s: number */ __( '%s ready', 'monoranks' ), number_format_i18n( $monoranks_o['fixes']['ready'] ) ) ); ?></span><?php endif; ?></div>
			<div class="stat num"><?php echo esc_html( number_format_i18n( $monoranks_o['fixes']['applied_30d'] ) ); ?> <span class="sm sub"><?php esc_html_e( 'applied in 30 days', 'monoranks' ); ?></span></div>
			<div class="row wrap" style="gap:6px">
				<span class="pill"><span class="dot <?php echo $monoranks_o['ai']['bots_rules'] ? 'good' : ''; ?>"></span><?php echo $monoranks_o['ai']['bots_rules'] ? esc_html__( 'AI crawler rules on', 'monoranks' ) : esc_html__( 'No AI crawler rules', 'monoranks' ); ?></span>
				<span class="pill"><span class="dot <?php echo $monoranks_o['ai']['llms_txt'] ? 'good' : ''; ?>"></span><?php echo $monoranks_o['ai']['llms_txt'] ? esc_html__( 'llms.txt published', 'monoranks' ) : esc_html__( 'No llms.txt', 'monoranks' ); ?></span>
			</div>
		</div>
	</div>

	<div class="two">
		<div class="card">
			<div class="cardhd"><h2 class="h2"><?php esc_html_e( 'Pages needing attention', 'monoranks' ); ?></h2><a class="btn sm ghost" href="<?php echo esc_url( $app_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'All pages in MonoRanks', 'monoranks' ); ?></a></div>
			<?php if ( $monoranks_o['attention'] ) : ?>
			<div class="tblwrap" style="padding-top:8px">
				<table class="tbl">
					<thead><tr><th><?php esc_html_e( 'Page', 'monoranks' ); ?></th><th><?php esc_html_e( 'Health', 'monoranks' ); ?></th><th><?php esc_html_e( 'AEO', 'monoranks' ); ?></th><th><?php esc_html_e( 'Biggest issue', 'monoranks' ); ?></th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $monoranks_o['attention'] as $monoranks_row ) : ?>
						<tr>
							<td><div class="col" style="gap:2px"><b><?php echo esc_html( $monoranks_row['title'] ? $monoranks_row['title'] : $monoranks_row['url'] ); ?></b><?php if ( $monoranks_row['url'] ) : ?><span class="code xs"><?php echo esc_html( wp_make_link_relative( $monoranks_row['url'] ) ); ?></span><?php endif; ?></div></td>
							<td><?php echo Admin::ring( $monoranks_row['health'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?></td>
							<td><?php echo Admin::ring( $monoranks_row['aeo'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?></td>
							<td class="sm nowrap"><?php echo esc_html( $monoranks_row['issue'] ); ?></td>
							<td class="r"><?php if ( $monoranks_row['page_url'] ) : ?><a class="btn sm" href="<?php echo esc_url( $monoranks_row['page_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Fix in MonoRanks', 'monoranks' ); ?></a><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php else : ?>
				<div class="empty"><span class="h3"><?php esc_html_e( 'Nothing needs attention', 'monoranks' ); ?></span><span class="sm"><?php esc_html_e( 'Every scored page is above 60. The next audit may change that.', 'monoranks' ); ?></span></div>
			<?php endif; ?>
		</div>

		<div class="card">
			<div class="cardhd">
				<h2 class="h2"><?php esc_html_e( 'Ready to apply', 'monoranks' ); ?><?php if ( $monoranks_o['ready'] ) : ?><span class="badge"><?php echo esc_html( number_format_i18n( count( $monoranks_o['ready'] ) ) ); ?></span><?php endif; ?></h2>
				<?php if ( count( $monoranks_o['ready'] ) > 1 ) : ?>
				<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="inline">
					<?php wp_nonce_field( 'monoranks_apply' ); ?>
					<input type="hidden" name="action" value="monoranks_apply"><input type="hidden" name="fix" value="all">
					<button class="btn sm primary" type="submit"><?php esc_html_e( 'Apply all', 'monoranks' ); ?></button>
				</form>
				<?php endif; ?>
			</div>
			<div class="cardbd col" style="gap:0; padding-top:6px">
				<?php if ( ! $monoranks_o['ready'] ) : ?>
					<div class="empty"><span class="h3"><?php esc_html_e( 'No fixes waiting', 'monoranks' ); ?></span><span class="sm"><?php esc_html_e( 'Fixes you approve in MonoRanks appear here, ready to write into WordPress.', 'monoranks' ); ?></span></div>
				<?php else : ?>
					<?php foreach ( $monoranks_o['ready'] as $monoranks_fix ) : ?>
					<div class="fixrow">
						<span class="badge"><?php echo esc_html( isset( $labels[ $monoranks_fix['field'] ] ) ? $labels[ $monoranks_fix['field'] ] : $monoranks_fix['field'] ); ?></span>
						<div class="val grow">
							<span class="sm"><b><bdi><?php echo esc_html( $monoranks_fix['title'] ? $monoranks_fix['title'] : ( 'redirect' === $monoranks_fix['field'] ? $monoranks_fix['from'] : __( 'Site', 'monoranks' ) ) ); ?></bdi></b></span>
							<?php if ( 'content' === $monoranks_fix['field'] ) : ?>
								<span class="sm mut"><?php esc_html_e( 'Adds one answer-first paragraph at the top. Review the before/after in MonoRanks first.', 'monoranks' ); ?></span>
							<?php else : ?>
								<?php if ( null !== $monoranks_fix['before'] && '' !== $monoranks_fix['before'] ) : ?><s><bdi><?php echo esc_html( wp_html_excerpt( wp_strip_all_tags( $monoranks_fix['before'] ), 140, '…' ) ); ?></bdi></s><?php endif; ?>
								<span class="sm"><bdi><?php echo esc_html( wp_html_excerpt( wp_strip_all_tags( $monoranks_fix['after'] ), 160, '…' ) ); ?></bdi></span>
							<?php endif; ?>
						</div>
						<?php if ( 'content' === $monoranks_fix['field'] && $monoranks_fix['page_url'] ) : ?>
							<a class="btn sm" href="<?php echo esc_url( $monoranks_fix['page_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Review', 'monoranks' ); ?></a>
						<?php else : ?>
							<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="inline">
								<?php wp_nonce_field( 'monoranks_apply' ); ?>
								<input type="hidden" name="action" value="monoranks_apply"><input type="hidden" name="fix" value="<?php echo esc_attr( $monoranks_fix['id'] ); ?>">
								<button class="btn sm" type="submit"><?php esc_html_e( 'Apply', 'monoranks' ); ?></button>
							</form>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
					<div class="xs mut" style="padding-top:12px"><?php esc_html_e( 'Approved in MonoRanks. Each write is logged under Settings and can be undone for 30 days.', 'monoranks' ); ?></div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="card">
		<div class="cardhd"><h2 class="h2"><?php esc_html_e( 'Recent changes', 'monoranks' ); ?></h2><a class="btn sm ghost" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'All changes', 'monoranks' ); ?></a></div>
		<div style="padding-top:8px">
			<?php echo Admin::view( 'partials/change-log', array( 'log' => $log, 'labels' => $labels, 'post_url' => $post_url, 'undo' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?>
		</div>
	</div>

	<?php endif; ?>
</div>
