<?php
/**
 * Plugin deactivation handler.
 * Cleans up scheduled events but preserves data (tables + options).
 * Full cleanup happens in uninstall.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICM_Deactivator {

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate(): void {
        // Remove scheduled cron events
        $timestamp = wp_next_scheduled( 'icm_daily_maintenance' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'icm_daily_maintenance' );
        }
    }
}
