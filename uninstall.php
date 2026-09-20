<?php
/**
 * Removes everything the connector stored when the plugin is deleted from WordPress (wordpress.org requirement).
 * Deactivating keeps the settings; deleting removes them. Changes already written to posts stay as they are.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array( 'monoranks', 'monoranks_connection', 'monoranks_sync', 'monoranks_redirects', 'monoranks_change_log', 'monoranks_ai_bots', 'monoranks_llms_txt' ) as $option ) {
	delete_option( $option );
}
wp_clear_scheduled_hook( 'monoranks_daily_sync' );
wp_clear_scheduled_hook( 'monoranks_sync_step' );
delete_transient( 'monoranks_sync_lock' );
