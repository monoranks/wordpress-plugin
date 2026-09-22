<?php
/**
 * A 28-day sparkline. $series: list of non-negative ints.
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;

$monoranks_s = array_values( array_map( 'intval', (array) $series ) );
if ( count( $monoranks_s ) < 2 ) {
	return;
}
$monoranks_max = max( 1, max( $monoranks_s ) );
$monoranks_n   = count( $monoranks_s );
$monoranks_pts = array();
foreach ( $monoranks_s as $monoranks_i => $monoranks_v ) {
	$monoranks_pts[] = round( 200 * $monoranks_i / ( $monoranks_n - 1 ), 1 ) . ' ' . round( 34 - 30 * $monoranks_v / $monoranks_max, 1 );
}
$monoranks_line = 'M' . implode( ' L', $monoranks_pts );
$monoranks_last = explode( ' ', end( $monoranks_pts ) );
?>
<svg class="spark" viewBox="0 0 200 38" preserveAspectRatio="none" aria-hidden="true">
	<path d="<?php echo esc_attr( $monoranks_line . ' L200 38 L0 38 Z' ); ?>" fill="var(--accentSoft)"/>
	<path d="<?php echo esc_attr( $monoranks_line ); ?>" fill="none" stroke="var(--accent)" stroke-width="1.5"/>
	<circle cx="<?php echo esc_attr( $monoranks_last[0] ); ?>" cy="<?php echo esc_attr( $monoranks_last[1] ); ?>" r="2.5" fill="var(--accent)"/>
</svg>
