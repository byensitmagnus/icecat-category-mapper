<?php
/**
 * Handles plugin activation: creates DB tables and seeds Icecat reference data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICM_Activator {

    /**
     * Run on plugin activation and DB version upgrade.
     */
    public static function activate(): void {
        self::create_tables();
        self::seed_icecat_library();
        self::set_default_options();

        update_option( 'icm_db_version', ICM_VERSION );

        // Schedule daily maintenance cron
        if ( ! wp_next_scheduled( 'icm_daily_maintenance' ) ) {
            wp_schedule_event( time(), 'daily', 'icm_daily_maintenance' );
        }
    }

    /**
     * Create all custom database tables.
     */
    private static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ── Mappings table ──
        $mappings_table = ICM_DB::mappings_table();
        $sql_mappings = "CREATE TABLE {$mappings_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            icecat_cat_id INT(11) UNSIGNED NOT NULL DEFAULT 0,
            icecat_cat_name VARCHAR(255) NOT NULL DEFAULT '',
            icecat_cat_name_da VARCHAR(255) NOT NULL DEFAULT '',
            woo_term_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            woo_term_slug VARCHAR(200) NOT NULL DEFAULT '',
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY icecat_cat_id (icecat_cat_id),
            KEY woo_term_id (woo_term_id),
            KEY icecat_cat_name (icecat_cat_name)
        ) {$charset_collate};";

        dbDelta( $sql_mappings );

        // ── Remap log table ──
        $log_table = ICM_DB::log_table();
        $sql_log = "CREATE TABLE {$log_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            product_title VARCHAR(255) NOT NULL DEFAULT '',
            icecat_cat_id INT(11) UNSIGNED NOT NULL DEFAULT 0,
            icecat_cat_name VARCHAR(255) NOT NULL DEFAULT '',
            target_term_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            target_slug VARCHAR(200) NOT NULL DEFAULT '',
            action VARCHAR(20) NOT NULL DEFAULT 'remapped',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $sql_log );

        // ── Unmapped categories table ──
        $unmapped_table = ICM_DB::unmapped_table();
        $sql_unmapped = "CREATE TABLE {$unmapped_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            icecat_cat_id INT(11) UNSIGNED NOT NULL DEFAULT 0,
            icecat_cat_name VARCHAR(255) NOT NULL DEFAULT '',
            first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            product_count INT(11) UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY icecat_cat_id (icecat_cat_id),
            KEY icecat_cat_name (icecat_cat_name)
        ) {$charset_collate};";

        dbDelta( $sql_unmapped );
    }

    /**
     * Seed the mappings table with the Icecat reference library
     * (IDs and names only — targets are EMPTY and must be configured by admin).
     */
    private static function seed_icecat_library(): void {
        $library = ICM_Defaults::get_icecat_category_library();

        foreach ( $library as $entry ) {
            // Idempotent: indsæt kun rækker hvis Icecat-ID'et ikke allerede findes. Så nye
            // default-kategorier propageres ved version-upgrade (ikke kun frisk install), OG
            // admins konfigurerede mål (woo_term_slug) på eksisterende rækker bevares urørt.
            if ( ICM_DB::get_mapping_by_icecat_id( (int) $entry['icecat_cat_id'] ) ) {
                continue;
            }
            ICM_DB::insert_mapping( [
                'icecat_cat_id'      => $entry['icecat_cat_id'],
                'icecat_cat_name'    => $entry['icecat_cat_name'],
                'icecat_cat_name_da' => $entry['icecat_cat_name_da'] ?? '',
                'woo_term_id'        => 0,     // No target — user configures this
                'woo_term_slug'      => '',    // No target — user configures this
                'is_default'         => 1,     // Marks it as shipped default data
            ] );
        }
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options(): void {
        add_option( 'icm_icecat_username', '' );
        add_option( 'icm_icecat_password', '' );
        add_option( 'icm_icecat_lang_id', 1 ); // 1 = English (default), admin can change
        add_option( 'icm_fallback_behavior', 'keep' );
        add_option( 'icm_fallback_category', '' );
        add_option( 'icm_auto_remap_enabled', 1 );
        add_option( 'icm_log_retention_days', 90 );
        add_option( 'icm_protected_slugs', '' ); // Comma-separated slugs the admin never wants remapped
        add_option( 'icm_categories_cache', '', '', 'no' ); // autoload = no
        add_option( 'icm_categories_cache_time', 0 );
    }
}
