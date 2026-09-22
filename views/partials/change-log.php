<?php
/**
 * Recent changes written by MonoRanks. $log (newest first), $labels (field => label), $post_url, $undo (bool).
 *
 * @package MonoRanks
 */

defined( 'ABSPATH' ) || exit;

$monoranks_total = count( (array) get_option( 'monoranks_change_log', array() ) );
?>
<?php if ( empty( $log ) ) : ?>
	<div class="empty"><span class="h3"><?php esc_html_e( 'No changes yet', 'monoranks' ); ?></span><span class="sm"><?php esc_html_e( 'Fixes you approve in MonoRanks show up here, each with Undo.', 'monoranks' ); ?></span></div>
<?php else : ?>
	<div class="tblwrap">
		<table class="tbl">
			<thead><tr><th><?php esc_html_e( 'When', 'monoranks' ); ?></th><th><?php esc_html_e( 'Who', 'monoranks' ); ?></th><th><?php esc_html_e( 'Field', 'monoranks' ); ?></th><th><?php esc_html_e( 'Where', 'monoranks' ); ?></th><th><?php esc_html_e( 'Before', 'monoranks' ); ?></th><th><?php esc_html_e( 'After', 'monoranks' ); ?></th><?php if ( ! empty( $undo ) ) : ?><th></th><?php endif; ?></tr></thead>
			<tbody>
			<?php foreach ( $log as $monoranks_i => $monoranks_row ) : ?>
				<?php
				$monoranks_field  = isset( $monoranks_row['field'] ) ? (string) $monoranks_row['field'] : '';
				$monoranks_target = isset( $monoranks_row['target'] ) ? (string) $monoranks_row['target'] : '';
				$monoranks_where  = $monoranks_target;
				if ( 0 === strpos( $monoranks_target, 'post:' ) ) {
					$monoranks_where = get_the_title( (int) substr( $monoranks_target, 5 ) );
				} elseif ( 0 === strpos( $monoranks_target, 'attachment:' ) ) {
					/* translators: %d: attachment ID */
					$monoranks_where = sprintf( __( 'Image #%d', 'monoranks' ), (int) substr( $monoranks_target, 11 ) );
				} elseif ( 'site' === $monoranks_target ) {
					$monoranks_where = __( 'Site', 'monoranks' );
				}
				$monoranks_ts = isset( $monoranks_row['at'] ) ? strtotime( $monoranks_row['at'] ) : 0;
				?>
				<tr>
					<td class="sm num"><?php echo $monoranks_ts ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $monoranks_ts ) ) : ''; ?></td>
					<td class="sm"><?php echo esc_html( isset( $monoranks_row['actor'] ) ? $monoranks_row['actor'] : '' ); ?></td>
					<td><span class="badge"><?php echo esc_html( isset( $labels[ $monoranks_field ] ) ? $labels[ $monoranks_field ] : $monoranks_field ); ?></span></td>
					<td class="sm"><div class="cell" title="<?php echo esc_attr( $monoranks_target ); ?>"><?php echo esc_html( $monoranks_where ); ?></div></td>
					<td class="sm mut"><div class="cell" title="<?php echo esc_attr( wp_strip_all_tags( (string) $monoranks_row['previous'] ) ); ?>"><bdi><?php echo '' === (string) $monoranks_row['previous'] ? '&mdash;' : esc_html( wp_strip_all_tags( (string) $monoranks_row['previous'] ) ); ?></bdi></div></td>
					<td class="sm"><div class="cell" title="<?php echo esc_attr( wp_strip_all_tags( (string) $monoranks_row['value'] ) ); ?>"><bdi><?php echo '' === (string) $monoranks_row['value'] ? '&mdash;' : esc_html( wp_strip_all_tags( (string) $monoranks_row['value'] ) ); ?></bdi></div></td>
					<?php if ( ! empty( $undo ) ) : ?>
						<td class="r">
							<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="inline">
								<?php wp_nonce_field( 'monoranks_undo' ); ?>
								<input type="hidden" name="action" value="monoranks_undo">
								<input type="hidden" name="entry" value="<?php echo (int) ( $monoranks_total - 1 - $monoranks_i ); ?>">
								<button class="btn sm ghost" type="submit"><?php esc_html_e( 'Undo', 'monoranks' ); ?></button>
							</form>
						</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
