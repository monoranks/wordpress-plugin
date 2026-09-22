<?php
/**
 * Where the admin app renders. $theme (light | dark), $built (whether assets/build/main.js exists).
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap" style="margin:0">
	<div id="monoranks-admin" data-theme="<?php echo esc_attr( $theme ); ?>">
		<?php if ( ! $built ) : ?>
			<div class="notice notice-error" style="margin:20px"><p><?php echo esc_html__( 'The MonoRanks admin app is not built. Run npm install and npm run build in the plugin folder.', 'monoranks' ); ?></p></div>
		<?php endif; ?>
	</div>
</div>
