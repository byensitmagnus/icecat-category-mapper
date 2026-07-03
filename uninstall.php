<?php
/**
 * Uninstall handler.
 * Fires when the plugin is deleted from WordPress.
 * Drops all custom database tables and removes all wp_options.
 */

// Abort if not called by WordPress uninstall mechanism
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables
$tables = [
    $wpdb->prefix . 'icm_category_mappings',
    $wpdb->prefix . 'icm_remap_log',
    $wpdb->prefix . 'icm_unmapped_categories',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Remove all plugin options
$options = [
    'icm_icecat_username',
    'icm_icecat_password',
    'icm_icecat_lang_id',
    'icm_fallback_behavior',
    'icm_fallback_category',
    'icm_auto_remap_enabled',
    'icm_log_retention_days',
    'icm_protected_slugs',
    'icm_categories_cache',
    'icm_categories_cache_time',
    'icm_db_version',
];

// Clean up any transients
delete_transient( 'icm_just_activated' );

foreach ( $options as $option ) {
    delete_option( $option );
}

// Clear any scheduled cron events
wp_clear_scheduled_hook( 'icm_daily_maintenance' );
