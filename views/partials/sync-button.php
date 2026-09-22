<?php
/**
 * The "Send content now" form button. $post_url, $back (overview | settings), $label.
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;
?>
<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="inline">
	<?php wp_nonce_field( 'monoranks_send_now' ); ?>
	<input type="hidden" name="action" value="monoranks_send_now"><input type="hidden" name="back" value="<?php echo esc_attr( $back ); ?>">
	<button class="btn primary" type="submit"><?php echo esc_html( $label ); ?></button>
</form>
