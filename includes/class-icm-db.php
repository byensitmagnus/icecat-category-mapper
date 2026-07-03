<?php
/**
 * Database abstraction layer for all ICM tables.
 * Centralizes all SQL queries: mappings, log, unmapped categories.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICM_DB {

    /* ───────────────────────────────────────────────
     *  Table name helpers
     * ─────────────────────────────────────────────── */

    public static function mappings_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'icm_category_mappings';
    }

    public static function log_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'icm_remap_log';
    }

    public static function unmapped_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'icm_unmapped_categories';
    }

    /* ───────────────────────────────────────────────
     *  MAPPINGS — CRUD
     * ─────────────────────────────────────────────── */

    /**
     * Lookup mapping by Icecat category ID (primary method).
     */
    public static function get_mapping_by_icecat_id( int $icecat_cat_id ): ?array {
        global $wpdb;
        $table = self::mappings_table();

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE icecat_cat_id = %d", $icecat_cat_id ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Lookup mapping by Icecat category name (exact, case-insensitive).
     * Checks both English and Danish names.
     */
    public static function get_mapping_by_icecat_name( string $name ): ?array {
        global $wpdb;
        $table = self::mappings_table();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE LOWER(icecat_cat_name) = LOWER(%s)
                    OR LOWER(icecat_cat_name_da) = LOWER(%s)
                 LIMIT 1",
                $name,
                $name
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Fuzzy lookup mapping by Icecat category name (LIKE match).
     */
    public static function get_mapping_by_fuzzy_name( string $name ): ?array {
        global $wpdb;

        // Skip fuzzy matching for short names: a substring match on 1-3 characters produces
        // arbitrary false positives (a wrong fuzzy hit remaps to the wrong category with NO
        // warning — worse than landing in "Unmapped"). Exact match (Strategy 2) covers short names.
        if ( mb_strlen( trim( $name ) ) < 4 ) {
            return null;
        }

        $table = self::mappings_table();
        $like  = '%' . $wpdb->esc_like( $name ) . '%';

        // ORDER BY CHAR_LENGTH ASC → the SHORTEST (most specific) stored name containing the
        // query wins deterministically. Without ORDER BY, the LIMIT 1 winner was effectively arbitrary (DB order).
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE icecat_cat_name LIKE %s
                    OR icecat_cat_name_da LIKE %s
                 ORDER BY CHAR_LENGTH( icecat_cat_name ) ASC
                 LIMIT 1",
                $like,
                $like
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Get all mappings with optional pagination, search, and configured-status filter.
     */
    public static function get_all_mappings( array $args = [] ): array {
        global $wpdb;
        $table = self::mappings_table();

        $defaults = [
            'per_page'          => 50,
            'offset'            => 0,
            'search'            => '',
            'orderby'           => 'icecat_cat_name',
            'order'             => 'ASC',
            'only_configured'   => false,
            'only_unconfigured' => false,
        ];
        $args = wp_parse_args( $args, $defaults );

        $where_parts = [];
        $params      = [];

        if ( ! empty( $args['search'] ) ) {
            $like          = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where_parts[] = "(icecat_cat_name LIKE %s OR icecat_cat_name_da LIKE %s OR woo_term_slug LIKE %s)";
            $params[]      = $like;
            $params[]      = $like;
            $params[]      = $like;
        }

        if ( ! empty( $args['only_configured'] ) ) {
            $where_parts[] = "woo_term_slug != ''";
        }
        if ( ! empty( $args['only_unconfigured'] ) ) {
            $where_parts[] = "woo_term_slug = ''";
        }

        $where = ! empty( $where_parts ) ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';

        // Whitelist orderby
        $allowed_orderby = [ 'id', 'icecat_cat_id', 'icecat_cat_name', 'woo_term_slug', 'is_default', 'created_at' ];
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'icecat_cat_name';
        $order   = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

        $sql      = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = (int) $args['per_page'];
        $params[] = (int) $args['offset'];

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: [];
    }

    /**
     * Count total mappings (with optional search filter and configured-status filter).
     */
    public static function count_mappings( string $search = '', string $filter = 'all' ): int {
        global $wpdb;
        $table = self::mappings_table();

        $where_parts = [];
        $params      = [];

        if ( ! empty( $search ) ) {
            $like          = '%' . $wpdb->esc_like( $search ) . '%';
            $where_parts[] = "(icecat_cat_name LIKE %s OR icecat_cat_name_da LIKE %s OR woo_term_slug LIKE %s)";
            $params[]      = $like;
            $params[]      = $like;
            $params[]      = $like;
        }

        if ( $filter === 'configured' ) {
            $where_parts[] = "woo_term_slug != ''";
        } elseif ( $filter === 'unconfigured' ) {
            $where_parts[] = "woo_term_slug = ''";
        }

        $where = ! empty( $where_parts ) ? 'WHERE ' . implode( ' AND ', $where_parts ) : '';
        $sql   = "SELECT COUNT(*) FROM {$table} {$where}";

        if ( ! empty( $params ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * Insert a new mapping.
     *
     * @return int|false Insert ID or false on failure.
     */
    public static function insert_mapping( array $data ) {
        global $wpdb;

        $result = $wpdb->insert(
            self::mappings_table(),
            [
                'icecat_cat_id'      => (int) $data['icecat_cat_id'],
                'icecat_cat_name'    => sanitize_text_field( $data['icecat_cat_name'] ?? '' ),
                'icecat_cat_name_da' => sanitize_text_field( $data['icecat_cat_name_da'] ?? '' ),
                'woo_term_id'        => (int) ( $data['woo_term_id'] ?? 0 ),
                'woo_term_slug'      => sanitize_title( $data['woo_term_slug'] ?? '' ),
                'is_default'         => (int) ( $data['is_default'] ?? 0 ),
            ],
            [ '%d', '%s', '%s', '%d', '%s', '%d' ]
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update an existing mapping.
     */
    public static function update_mapping( int $id, array $data ): bool {
        global $wpdb;

        $update = [];
        $format = [];

        if ( isset( $data['icecat_cat_id'] ) ) {
            $update['icecat_cat_id'] = (int) $data['icecat_cat_id'];
            $format[] = '%d';
        }
        if ( isset( $data['icecat_cat_name'] ) ) {
            $update['icecat_cat_name'] = sanitize_text_field( $data['icecat_cat_name'] );
            $format[] = '%s';
        }
        if ( isset( $data['icecat_cat_name_da'] ) ) {
            $update['icecat_cat_name_da'] = sanitize_text_field( $data['icecat_cat_name_da'] );
            $format[] = '%s';
        }
        if ( isset( $data['woo_term_id'] ) ) {
            $update['woo_term_id'] = (int) $data['woo_term_id'];
            $format[] = '%d';
        }
        if ( isset( $data['woo_term_slug'] ) ) {
            $update['woo_term_slug'] = sanitize_title( $data['woo_term_slug'] );
            $format[] = '%s';
        }

        if ( empty( $update ) ) {
            return false;
        }

        $result = $wpdb->update(
            self::mappings_table(),
            $update,
            [ 'id' => $id ],
            $format,
            [ '%d' ]
        );

        return $result !== false;
    }

    /**
     * Delete a mapping by ID.
     */
    public static function delete_mapping( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( self::mappings_table(), [ 'id' => $id ], [ '%d' ] );
    }

    /**
     * Delete all mappings (used before reseeding defaults).
     */
    public static function delete_all_mappings(): bool {
        global $wpdb;
        return $wpdb->query( "TRUNCATE TABLE " . self::mappings_table() ) !== false;
    }

    /* ───────────────────────────────────────────────
     *  UNMAPPED CATEGORIES
     * ─────────────────────────────────────────────── */

    /**
     * Record an unmapped Icecat category that was encountered.
     */
    public static function record_unmapped( int $icecat_cat_id, string $name ): void {
        global $wpdb;
        $table = self::unmapped_table();

        // Try to update existing record first
        $exists = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE icecat_cat_id = %d", $icecat_cat_id )
        );

        if ( $exists ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET last_seen = NOW(), product_count = product_count + 1 WHERE icecat_cat_id = %d",
                    $icecat_cat_id
                )
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    'icecat_cat_id'   => $icecat_cat_id,
                    'icecat_cat_name' => sanitize_text_field( $name ),
                    'product_count'   => 1,
                ],
                [ '%d', '%s', '%d' ]
            );
        }
    }

    /**
     * Record unmapped by name only (when we don't have an Icecat ID).
     */
    public static function record_unmapped_by_name( string $name ): void {
        global $wpdb;
        $table = self::unmapped_table();

        $exists = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE icecat_cat_name = %s", $name )
        );

        if ( $exists ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET last_seen = NOW(), product_count = product_count + 1 WHERE icecat_cat_name = %s",
                    $name
                )
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    'icecat_cat_id'   => 0,
                    'icecat_cat_name' => sanitize_text_field( $name ),
                    'product_count'   => 1,
                ],
                [ '%d', '%s', '%d' ]
            );
        }
    }

    /**
     * Get all unmapped categories with pagination.
     */
    public static function get_unmapped_categories( array $args = [] ): array {
        global $wpdb;
        $table = self::unmapped_table();

        $defaults = [
            'per_page' => 50,
            'offset'   => 0,
            'orderby'  => 'product_count',
            'order'    => 'DESC',
        ];
        $args = wp_parse_args( $args, $defaults );

        $allowed_orderby = [ 'id', 'icecat_cat_id', 'icecat_cat_name', 'product_count', 'first_seen', 'last_seen' ];
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'product_count';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                (int) $args['per_page'],
                (int) $args['offset']
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Count unmapped categories.
     */
    public static function count_unmapped(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::unmapped_table() );
    }

    /**
     * Remove an unmapped entry (typically after mapping it).
     */
    public static function delete_unmapped( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( self::unmapped_table(), [ 'id' => $id ], [ '%d' ] );
    }

    /* ───────────────────────────────────────────────
     *  LOG
     * ─────────────────────────────────────────────── */

    /**
     * Insert a log entry.
     */
    public static function insert_log( array $data ): void {
        global $wpdb;

        $wpdb->insert(
            self::log_table(),
            [
                'product_id'      => (int) $data['product_id'],
                'product_title'   => sanitize_text_field( $data['product_title'] ?? '' ),
                'icecat_cat_id'   => (int) ( $data['icecat_cat_id'] ?? 0 ),
                'icecat_cat_name' => sanitize_text_field( $data['icecat_cat_name'] ?? '' ),
                'target_term_id'  => (int) ( $data['target_term_id'] ?? 0 ),
                'target_slug'     => sanitize_title( $data['target_slug'] ?? '' ),
                'action'          => sanitize_key( $data['action'] ?? 'remapped' ),
            ],
            [ '%d', '%s', '%d', '%s', '%d', '%s', '%s' ]
        );
    }

    /**
     * Get log entries with pagination and optional filters.
     */
    public static function get_log_entries( array $args = [] ): array {
        global $wpdb;
        $table = self::log_table();

        $defaults = [
            'per_page' => 50,
            'offset'   => 0,
            'action'   => '',
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        ];
        $args = wp_parse_args( $args, $defaults );

        $where  = '';
        $params = [];

        if ( ! empty( $args['action'] ) ) {
            $where    = 'WHERE action = %s';
            $params[] = sanitize_key( $args['action'] );
        }

        $allowed_orderby = [ 'id', 'product_id', 'icecat_cat_name', 'target_slug', 'action', 'created_at' ];
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $sql      = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = (int) $args['per_page'];
        $params[] = (int) $args['offset'];

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: [];
    }

    /**
     * Count total log entries (with optional action filter).
     */
    public static function count_log_entries( string $action = '' ): int {
        global $wpdb;
        $table = self::log_table();

        if ( ! empty( $action ) ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE action = %s", $action )
            );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    /**
     * Purge log entries older than X days.
     *
     * @return int Number of rows deleted.
     */
    public static function purge_old_logs( int $days ): int {
        global $wpdb;
        $table = self::log_table();

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    /**
     * Clear all log entries.
     */
    public static function clear_log(): bool {
        global $wpdb;
        return $wpdb->query( "TRUNCATE TABLE " . self::log_table() ) !== false;
    }
}
