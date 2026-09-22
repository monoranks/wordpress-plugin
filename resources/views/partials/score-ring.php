<?php
/**
 * A score ring. $score (0–100 or null), $size (sm | md | lg), $label, $place (side | under), $tone.
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;

$monoranks_px = 'lg' === $size ? 64 : ( 'md' === $size ? 40 : 30 );
$monoranks_sw = 'lg' === $size ? 5 : ( 'md' === $size ? 4 : 3 );
$monoranks_r  = ( $monoranks_px - $monoranks_sw ) / 2;
$monoranks_c  = 2 * M_PI * $monoranks_r;
$monoranks_mid = $monoranks_px / 2;
/* translators: %s: score out of 100 */
$monoranks_title = null === $score ? __( 'Not scored yet', 'monoranks' ) : \MonoRanks\Admin::digits( sprintf( __( '%s of 100', 'monoranks' ), number_format_i18n( $score ) ) );
?>
<span class="mr-score <?php echo esc_attr( $size . ' ' . $tone . ' ' . ( isset( $place ) ? $place : 'side' ) ); ?>" title="<?php echo esc_attr( ( $label ? $label . ': ' : '' ) . $monoranks_title ); ?>">
	<span class="mr-ring">
		<svg width="<?php echo (int) $monoranks_px; ?>" height="<?php echo (int) $monoranks_px; ?>" viewBox="0 0 <?php echo (int) $monoranks_px; ?> <?php echo (int) $monoranks_px; ?>" aria-hidden="true">
			<?php if ( null === $score ) : ?>
				<circle cx="<?php echo esc_attr( $monoranks_mid ); ?>" cy="<?php echo esc_attr( $monoranks_mid ); ?>" r="<?php echo esc_attr( $monoranks_r ); ?>" fill="none" stroke="currentColor" stroke-width="<?php echo (int) $monoranks_sw; ?>" stroke-dasharray="3 4"/>
			<?php else : ?>
				<circle cx="<?php echo esc_attr( $monoranks_mid ); ?>" cy="<?php echo esc_attr( $monoranks_mid ); ?>" r="<?php echo esc_attr( $monoranks_r ); ?>" fill="none" stroke="var(--grayTrack)" stroke-width="<?php echo (int) $monoranks_sw; ?>"/>
				<circle cx="<?php echo esc_attr( $monoranks_mid ); ?>" cy="<?php echo esc_attr( $monoranks_mid ); ?>" r="<?php echo esc_attr( $monoranks_r ); ?>" fill="none" stroke="currentColor" stroke-width="<?php echo (int) $monoranks_sw; ?>" stroke-linecap="round" stroke-dasharray="<?php echo esc_attr( round( $monoranks_c * $score / 100, 2 ) . ' ' . round( $monoranks_c, 2 ) ); ?>" transform="rotate(-90 <?php echo esc_attr( $monoranks_mid . ' ' . $monoranks_mid ); ?>)"/>
			<?php endif; ?>
		</svg>
		<span><?php echo null === $score ? '&mdash;' : esc_html( \MonoRanks\Admin::n( $score ) ); ?></span>
	</span>
	<?php if ( $label ) : ?><span class="lbl"><?php echo esc_html( $label ); ?></span><?php endif; ?>
</span>
