<?php
/**
 * Removes everything the connector stored when the plugin is deleted from WordPress (wordpress.org requirement).
 * Deactivating keeps the settings; deleting removes them. Changes already written to posts stay as they are.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// The same list the "Delete all MonoRanks data" button uses (MonoRanks\Actions::delete_everything).
foreach ( array( 'monoranks', 'monoranks_connection', 'monoranks_sync', 'monoranks_redirects', 'monoranks_change_log', 'monoranks_ai_bots', 'monoranks_llms_txt', 'monoranks_insights', 'monoranks_version' ) as $monoranks_option ) {
	delete_option( $monoranks_option );
}
wp_clear_scheduled_hook( 'monoranks_daily_sync' );
wp_clear_scheduled_hook( 'monoranks_sync_step' );
wp_clear_scheduled_hook( 'monoranks_refresh' );
delete_post_meta_by_key( '_monoranks_score' );
delete_post_meta_by_key( '_monoranks_health' );
delete_transient( 'monoranks_sync_lock' );
delete_transient( 'monoranks_refreshing' );
