<?php
/**
 * The frame's top: a dark brand band (logo, section links, tools) and the light title area.
 * $section (overview | settings), $title, $description (HTML allowed, escaped by the caller), $actions (HTML from a partial), $app_url.
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;

use MonoRanks\Admin;

$monoranks_sections = array(
	'overview' => array( __( 'Overview', 'monoranks' ), Admin::overview_url() ),
	'settings' => array( __( 'Settings', 'monoranks' ), Admin::settings_url() ),
);
?>
<header class="mr-band">
	<div class="mr-measure mr-masthead">
		<a class="mr-brand" href="<?php echo esc_url( Admin::overview_url() ); ?>"><?php echo Admin::asset( 'logo.svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own SVG ?></a>
		<nav class="mr-nav" aria-label="<?php esc_attr_e( 'MonoRanks sections', 'monoranks' ); ?>">
			<?php foreach ( $monoranks_sections as $monoranks_id => $monoranks_s ) : ?>
				<a href="<?php echo esc_url( $monoranks_s[1] ); ?>"<?php echo $section === $monoranks_id ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $monoranks_s[0] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="mr-tools">
			<a class="mr-tool" href="https://monoranks.com/docs/wordpress/" target="_blank" rel="noopener"><?php esc_html_e( 'Help', 'monoranks' ); ?></a>
			<a class="mr-tool mr-tool-app" href="<?php echo esc_url( $app_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open MonoRanks', 'monoranks' ); ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7M8 7h9v9"/></svg></a>
		</div>
	</div>
</header>
<div class="mr-measure mr-heading">
	<div class="mr-heading-copy">
		<h1 class="h1"><?php echo esc_html( $title ); ?></h1>
		<?php if ( ! empty( $description ) ) : ?><p class="sub mr-heading-desc"><?php echo wp_kses( $description, array( 'span' => array( 'class' => array() ), 'bdi' => array() ) ); ?></p><?php endif; ?>
	</div>
	<?php if ( ! empty( $actions ) ) : ?><div class="row wrap mr-heading-actions"><?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by a partial that escapes ?></div><?php endif; ?>
</div>
