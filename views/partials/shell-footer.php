<?php
/**
 * The frame's bottom: a dark service band (logo, a useful link, help) and the publisher credit.
 * $settings_url, $app_url.
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;

use MonoRanks\Admin;
?>
<footer class="mr-band mr-footer">
	<div class="mr-measure mr-service">
		<div class="mr-brand"><?php echo Admin::asset( 'logo.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG ?><span class="mr-version">v<?php echo esc_html( MONORANKS_CONNECTOR_VERSION ); ?></span></div>
		<div class="mr-service-resource">
			<span class="mr-service-label"><?php esc_html_e( 'Your data', 'monoranks' ); ?></span>
			<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'What is sent and what can change', 'monoranks' ); ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7M8 7h9v9"/></svg></a>
		</div>
		<a class="mr-service-help" href="https://monoranks.com/docs/wordpress/" target="_blank" rel="noopener">
			<span><?php esc_html_e( 'Need a hand?', 'monoranks' ); ?></span>
			<strong><?php esc_html_e( 'Help and resources', 'monoranks' ); ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></strong>
		</a>
	</div>
	<div class="mr-measure mr-publisher">
		<span><?php esc_html_e( 'A product by', 'monoranks' ); ?></span>
		<a href="https://veronalabs.com/" target="_blank" rel="noopener" aria-label="VeronaLabs"><?php echo Admin::asset( 'veronalabs.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG ?></a>
	</div>
</footer>
