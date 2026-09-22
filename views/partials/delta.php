<?php
/**
 * "+3 this week" / "−2 this week". $n (int or null), $unit ('' or '%').
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;

if ( null === $n ) {
	return;
}
$monoranks_dir = $n > 0 ? 'up' : ( $n < 0 ? 'down' : '' );
$monoranks_txt = ( $n > 0 ? '+' : ( $n < 0 ? '−' : '' ) ) . number_format_i18n( abs( $n ) ) . $unit;
?>
<span class="delta <?php echo esc_attr( $monoranks_dir ); ?>"><?php echo wp_kses( sprintf( /* translators: %s: signed number */ __( '%s this week', 'monoranks' ), '<bdi>' . esc_html( $monoranks_txt ) . '</bdi>' ), array( 'bdi' => array() ) ); ?></span>
