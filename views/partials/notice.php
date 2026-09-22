<?php
/**
 * The notice after a form action. $notice: array{type: success|error, text: string} or null.
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $notice ) ) {
	return;
}
?>
<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?>"><p><?php echo esc_html( $notice['text'] ); ?></p></div>
