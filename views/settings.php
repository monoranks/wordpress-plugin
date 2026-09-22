<?php
/**
 * MonoRanks → Settings. Data from Settings::data().
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;

use MonoRanks\Admin;
?>
<div class="wrap monoranks mr-frame" data-theme="<?php echo esc_attr( $theme ); ?>">
	<?php echo Admin::view( 'partials/shell-header', array( 'section' => 'settings', 'title' => __( 'Settings', 'monoranks' ), 'description' => esc_html__( 'Connection, what is sent, and every change written into this site.', 'monoranks' ), 'actions' => '', 'app_url' => $app_url ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?>
	<div class="mr-measure mr-main">
	<?php echo Admin::view( 'partials/notice', array( 'notice' => $notice ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?>

	<?php if ( $connected ) : ?>
	<div class="card">
		<div class="cardhd">
			<div class="row"><span class="mark" aria-hidden="true"><?php echo Admin::asset( 'mark.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG ?></span><h2 class="h2"><?php esc_html_e( 'Connection', 'monoranks' ); ?></h2></div>
			<?php if ( $revoked ) : ?>
				<span class="pill"><span class="dot critical"></span><?php esc_html_e( 'Key revoked', 'monoranks' ); ?></span>
			<?php else : ?>
				<span class="pill"><span class="dot good"></span><?php esc_html_e( 'Connected', 'monoranks' ); ?></span>
			<?php endif; ?>
		</div>
		<div class="cardbd col">
			<div class="kv sm">
				<div><?php esc_html_e( 'Status', 'monoranks' ); ?></div>
				<div><?php echo $revoked ? esc_html__( 'MonoRanks no longer accepts this key. Connect again from MonoRanks or paste a new key below.', 'monoranks' ) : esc_html__( 'Sending published content to MonoRanks. Nothing else leaves this site.', 'monoranks' ); ?></div>
				<div><?php esc_html_e( 'Connector key', 'monoranks' ); ?></div>
				<div class="row wrap"><span class="code"><?php echo esc_html( $key_hint ); ?></span><span class="mut"><?php echo esc_html( $key_via ); ?></span></div>
				<div><?php esc_html_e( 'Last sync', 'monoranks' ); ?></div>
				<div class="row wrap">
					<span class="num"><?php echo esc_html( $last_sent ); ?></span>
					<?php if ( $sending ) : ?><span class="badge"><?php echo esc_html( sprintf( /* translators: 1: page, 2: pages */ __( 'sending, batch %1$s of %2$s', 'monoranks' ), number_format_i18n( $sending['page'] ), number_format_i18n( max( 1, $sending['pages'] ) ) ) ); ?></span><?php endif; ?>
					<?php if ( $items ) : ?><span class="badge"><?php echo esc_html( sprintf( /* translators: %s: number */ _n( '%s published item', '%s published items', $items, 'monoranks' ), number_format_i18n( $items ) ) ); ?></span><?php endif; ?>
					<?php if ( $last_error ) : ?><span class="badge warn"><?php echo esc_html( $last_error ); ?></span><?php endif; ?>
				</div>
				<?php if ( $next_audit ) : ?>
				<div><?php esc_html_e( 'Next audit', 'monoranks' ); ?></div>
				<div><?php echo esc_html( $next_audit ); ?></div>
				<?php endif; ?>
				<div><?php esc_html_e( 'Fixes written with', 'monoranks' ); ?></div>
				<div><?php echo wp_kses_post( sprintf( /* translators: %s: profile URL */ __( 'The "MonoRanks" Application Password · <a href="%s">revoke it in your profile</a> to stop writes', 'monoranks' ), esc_url( $profile_url ) ) ); ?></div>
				<div><?php esc_html_e( 'Works with', 'monoranks' ); ?></div>
				<div class="row wrap">
					<?php if ( $seo_plugin ) : ?>
						<span class="badge"><?php echo esc_html( sprintf( /* translators: %s: plugin name */ __( '%s detected', 'monoranks' ), $seo_plugin ) ); ?></span><span class="mut"><?php echo esc_html( sprintf( /* translators: %s: plugin name */ __( 'titles and descriptions go into the fields %s reads', 'monoranks' ), $seo_plugin ) ); ?></span>
					<?php else : ?>
						<span class="badge"><?php esc_html_e( 'No SEO plugin', 'monoranks' ); ?></span><span class="mut"><?php esc_html_e( 'MonoRanks prints the approved title, description, canonical and noindex tags itself', 'monoranks' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<div class="row wrap">
				<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="inline">
					<?php wp_nonce_field( 'monoranks_send_now' ); ?>
					<input type="hidden" name="action" value="monoranks_send_now">
					<button class="btn primary" type="submit"><?php esc_html_e( 'Send content now', 'monoranks' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="inline">
					<?php wp_nonce_field( 'monoranks_disconnect' ); ?>
					<input type="hidden" name="action" value="monoranks_disconnect">
					<button class="btn" type="submit"><?php esc_html_e( 'Disconnect', 'monoranks' ); ?></button>
				</form>
				<span class="sm mut"><?php esc_html_e( 'Disconnect stops sending. Fixes already applied stay.', 'monoranks' ); ?></span>
			</div>
		</div>
	</div>
	<?php else : ?>
	<div class="card">
		<div class="cardhd">
			<div class="row"><span class="mark" aria-hidden="true"><?php echo Admin::asset( 'mark.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG ?></span><h2 class="h2"><?php esc_html_e( 'Connection', 'monoranks' ); ?></h2></div>
			<span class="pill"><span class="dot"></span><?php esc_html_e( 'Not connected', 'monoranks' ); ?></span>
		</div>
		<div class="cardbd col">
			<div class="step">
				<span class="stepn">1</span>
				<div class="col">
					<span class="h3"><?php esc_html_e( 'Connect from MonoRanks', 'monoranks' ); ?></span>
					<span class="sm sub"><?php esc_html_e( 'In MonoRanks open your website → Integrations → WordPress → Connect to WordPress, and allow MonoRanks when WordPress asks. Nothing is sent before you connect.', 'monoranks' ); ?></span>
					<div><a class="btn sm primary" href="<?php echo esc_url( $app_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open MonoRanks', 'monoranks' ); ?></a></div>
				</div>
			</div>
			<div class="step">
				<span class="stepn later">2</span>
				<div class="col grow">
					<span class="h3"><?php esc_html_e( 'Or paste a connector key', 'monoranks' ); ?></span>
					<span class="sm sub"><?php esc_html_e( 'Use this when your host blocks Application Passwords. In MonoRanks: Settings → API and MCP → New credential with the content:write scope, ticked for this website.', 'monoranks' ); ?></span>
					<?php echo Admin::view( 'partials/key-form', array( 'post_url' => $post_url, 'show_address' => $show_address, 'api_base' => $api_base ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( $connected ) : ?>
	<div class="card">
		<div class="cardbd col" style="padding-top:18px">
			<details<?php echo $revoked ? ' open' : ''; ?>>
				<summary><?php esc_html_e( 'Use a different key', 'monoranks' ); ?></summary>
				<div class="col">
					<div class="note"><?php esc_html_e( 'In MonoRanks: Settings → API and MCP → New credential with the content:write scope, ticked for this website. Paste it here. With a key alone MonoRanks receives your content; to apply fixes, also use Connect to WordPress in MonoRanks.', 'monoranks' ); ?></div>
					<?php echo Admin::view( 'partials/key-form', array( 'post_url' => $post_url, 'show_address' => $show_address, 'api_base' => $api_base ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?>
				</div>
			</details>
		</div>
	</div>
	<?php endif; ?>

	<div class="card">
		<div class="cardhd"><h2 class="h2"><?php esc_html_e( 'What is sent', 'monoranks' ); ?></h2></div>
		<div class="cardbd">
			<div class="kv sm">
				<div><?php esc_html_e( 'Sent', 'monoranks' ); ?></div>
				<div><?php esc_html_e( 'Published titles, a 40-word excerpt, dates, authors, categories, tags, SEO fields, image alt text and plugin status.', 'monoranks' ); ?></div>
				<div><?php esc_html_e( 'Read back', 'monoranks' ); ?></div>
				<div><?php esc_html_e( 'This website\'s audit results: scores, issues and the fixes you approved, shown here and in the Posts list.', 'monoranks' ); ?></div>
				<div><?php esc_html_e( 'Never sent', 'monoranks' ); ?></div>
				<div><?php esc_html_e( 'Drafts, private posts, comments, emails, passwords, plugin or theme settings.', 'monoranks' ); ?></div>
				<div><?php esc_html_e( 'Can change', 'monoranks' ); ?></div>
				<div><?php esc_html_e( 'SEO title, meta description, canonical, noindex, image alt text, redirects, one opening paragraph, AI crawler rules, llms.txt. Only after you approve each one.', 'monoranks' ); ?></div>
			</div>
		</div>
	</div>

	<div class="card">
		<div class="cardhd"><h2 class="h2"><?php esc_html_e( 'Recent changes', 'monoranks' ); ?></h2><span class="sm mut"><?php esc_html_e( 'Last 50 · undo for 30 days', 'monoranks' ); ?></span></div>
		<div style="padding-top:8px">
			<?php echo Admin::view( 'partials/change-log', array( 'log' => $log, 'labels' => \MonoRanks\Overview::field_labels(), 'post_url' => $post_url, 'undo' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?>
		</div>
	</div>
	</div>
	<?php echo Admin::view( 'partials/shell-footer', array( 'settings_url' => Admin::settings_url(), 'app_url' => $app_url ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the partial escapes ?>
</div>
