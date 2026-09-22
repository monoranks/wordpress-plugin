<?php
/**
 * Overview before there is anything to show. $kind: unconnected | revoked | never | none | unsupported | error.
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( 'unconnected' === $kind || 'revoked' === $kind ) : ?>
<div class="card">
	<div class="cardhd">
		<h2 class="h2"><?php echo 'revoked' === $kind ? esc_html__( 'Connect MonoRanks again', 'monoranks' ) : esc_html__( 'Connect MonoRanks', 'monoranks' ); ?></h2>
		<span class="pill"><span class="dot <?php echo 'revoked' === $kind ? 'critical' : ''; ?>"></span><?php echo 'revoked' === $kind ? esc_html__( 'Key revoked', 'monoranks' ) : esc_html__( 'Not connected', 'monoranks' ); ?></span>
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
			<div class="col">
				<span class="h3"><?php esc_html_e( 'Or paste a connector key', 'monoranks' ); ?></span>
				<span class="sm sub"><?php esc_html_e( 'Use this when your host blocks Application Passwords.', 'monoranks' ); ?></span>
				<div><a class="btn sm" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Open settings', 'monoranks' ); ?></a></div>
			</div>
		</div>
		<div class="step">
			<span class="stepn later">3</span>
			<div class="col">
				<span class="h3"><?php esc_html_e( 'See scores here and in your Posts list', 'monoranks' ); ?></span>
				<span class="sm sub"><?php esc_html_e( 'After the first audit this page shows the site\'s health and AEO scores, pages needing attention and the fixes you approved; every post and page gets its scores in the list.', 'monoranks' ); ?></span>
			</div>
		</div>
	</div>
</div>
<?php else : ?>
<div class="card">
	<div class="cardhd"><h2 class="h2"><?php esc_html_e( 'Waiting for the first audit', 'monoranks' ); ?></h2><span class="pill"><span class="dot good"></span><?php esc_html_e( 'Connected', 'monoranks' ); ?></span></div>
	<div class="cardbd col">
		<div class="kv sm">
			<div><?php esc_html_e( 'Content', 'monoranks' ); ?></div>
			<div><?php echo esc_html( $last_sent ? sprintf( /* translators: 1: number of items, 2: relative time */ __( '%1$s published items sent %2$s', 'monoranks' ), number_format_i18n( $items ), $last_sent ) : __( 'Not sent yet. Use "Sync content now" above.', 'monoranks' ) ); ?></div>
			<div><?php esc_html_e( 'Audit results', 'monoranks' ); ?></div>
			<div>
				<?php if ( 'unsupported' === $kind ) : ?>
					<?php esc_html_e( 'This MonoRanks does not share audit results with the plugin yet. Scores stay in MonoRanks for now; the plugin asks again tomorrow.', 'monoranks' ); ?>
				<?php elseif ( 'error' === $kind ) : ?>
					<?php esc_html_e( 'MonoRanks could not be reached for the audit results. The plugin tries again within the hour.', 'monoranks' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'MonoRanks has not scored this site yet. Scores appear here and in the Posts list after the first weekly audit.', 'monoranks' ); ?>
				<?php endif; ?>
				<?php if ( $fetched ) : ?><span class="mut"> · <?php echo esc_html( sprintf( /* translators: %s: relative time */ __( 'checked %s', 'monoranks' ), $fetched ) ); ?></span><?php endif; ?>
			</div>
		</div>
		<div><a class="btn sm" href="<?php echo esc_url( $app_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the audit in MonoRanks', 'monoranks' ); ?></a></div>
	</div>
</div>
<?php endif; ?>
