<?php
/**
 * The connector key form. $post_url, $show_address (development sites only), $api_base.
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;
?>
<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="col">
	<?php wp_nonce_field( 'monoranks_connect_key' ); ?>
	<input type="hidden" name="action" value="monoranks_connect_key">
	<div class="field">
		<label class="label" for="monoranks-key"><?php esc_html_e( 'Connector key', 'monoranks' ); ?></label>
		<input class="input" id="monoranks-key" name="key" type="password" autocomplete="off" placeholder="mr_ws_… or mr_site_…" required>
	</div>
	<?php if ( $show_address ) : ?>
	<div class="field">
		<label class="label" for="monoranks-address"><?php esc_html_e( 'MonoRanks address (development sites only)', 'monoranks' ); ?></label>
		<input class="input" id="monoranks-address" name="api_base" type="url" value="<?php echo esc_attr( $api_base ); ?>">
	</div>
	<?php endif; ?>
	<div class="row"><button class="btn primary" type="submit"><?php esc_html_e( 'Connect', 'monoranks' ); ?></button></div>
</form>
