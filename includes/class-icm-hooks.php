<?php
/**
 * Registers all WordPress and WooCommerce hooks for automatic category remapping.
 *
 * The key hook is `set_object_terms` which fires whenever wp_set_object_terms() is called,
 * catching imports from EANrunner and any other plugin that assigns product categories.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICM_Hooks {

    /**
     * Re-entrancy guard — prevents infinite loops when we call wp_set_object_terms()
     * inside the set_object_terms handler.
     *
     * @var bool
     */
    private static bool $is_remapping = false;

    /**
     * Register all hooks.
     */
    public function register(): void {
        // ── Daily maintenance cron — registreres ALTID ──
        // Cron-eventet schedules ved aktivering og af-schedules kun ved deaktivering. Lå handleren
        // efter early-returnet nedenfor, ville log-purgen stoppe stille når auto-remap slås fra
        // (legitimt fx efter en engangs-import) → log-tabellen vokser uendeligt på et live site.
        add_action( 'icm_daily_maintenance', [ $this, 'daily_maintenance' ] );

        // Selve remappingen er gated på auto-remap-indstillingen.
        if ( ! get_option( 'icm_auto_remap_enabled', 1 ) ) {
            return;
        }

        // ── Primary hook: catches ALL term assignments ──
        add_action( 'set_object_terms', [ $this, 'on_set_object_terms' ], 100, 6 );

        // ── WooCommerce-specific hooks for belt-and-suspenders coverage ──
        add_action( 'save_post_product', [ $this, 'on_product_save' ], 100, 3 );
        add_action( 'woocommerce_rest_insert_product_object', [ $this, 'on_rest_product_save' ], 100, 3 );
        add_action( 'woocommerce_product_import_inserted_product_object', [ $this, 'on_csv_import' ], 100, 2 );

        // ── WP All Import support ──
        add_action( 'pmxi_saved_post', [ $this, 'on_wpallimport_save' ], 100, 1 );
    }

    /**
     * Fires when wp_set_object_terms() is called from ANY source.
     * This is the most important hook — it catches EANrunner and everything else.
     *
     * @param int    $object_id  Post ID.
     * @param array  $terms      Array of term slugs/IDs/names.
     * @param array  $tt_ids     Array of term_taxonomy_ids.
     * @param string $taxonomy   Taxonomy slug.
     * @param bool   $append     Whether terms were appended.
     * @param array  $old_tt_ids Previous term_taxonomy_ids.
     */
    public function on_set_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ): void {
        // Only care about product categories
        if ( $taxonomy !== 'product_cat' ) {
            return;
        }

        // Re-entrancy guard
        if ( self::$is_remapping ) {
            return;
        }

        // Only process WooCommerce products
        if ( get_post_type( $object_id ) !== 'product' ) {
            return;
        }

        // Perform the remapping
        self::$is_remapping = true;

        try {
            ICM_Mapper::remap_product_categories( (int) $object_id );
        } finally {
            self::$is_remapping = false;
        }
    }

    /**
     * Fires on standard WooCommerce product save (admin editor, quick edit, etc).
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an update.
     */
    public function on_product_save( $post_id, $post, $update ): void {
        // Skip autosaves and revisions
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // The set_object_terms hook should have already caught this,
        // but we run it again as a safety net for edge cases.
        if ( self::$is_remapping ) {
            return;
        }

        self::$is_remapping = true;

        try {
            ICM_Mapper::remap_product_categories( (int) $post_id );
        } finally {
            self::$is_remapping = false;
        }
    }

    /**
     * Fires when a product is created/updated via the WooCommerce REST API.
     *
     * @param \WC_Product      $product  Product object.
     * @param \WP_REST_Request $request  Request object.
     * @param bool             $creating Whether creating a new product.
     */
    public function on_rest_product_save( $product, $request, $creating ): void {
        if ( self::$is_remapping ) {
            return;
        }

        self::$is_remapping = true;

        try {
            ICM_Mapper::remap_product_categories( $product->get_id() );
        } finally {
            self::$is_remapping = false;
        }
    }

    /**
     * Fires after a product is imported via WooCommerce CSV importer.
     *
     * @param \WC_Product $product Product object.
     * @param array       $data    Raw CSV data.
     */
    public function on_csv_import( $product, $data ): void {
        if ( self::$is_remapping ) {
            return;
        }

        self::$is_remapping = true;

        try {
            ICM_Mapper::remap_product_categories( $product->get_id() );
        } finally {
            self::$is_remapping = false;
        }
    }

    /**
     * Fires after a product is saved via WP All Import.
     *
     * @param int $post_id Post ID.
     */
    public function on_wpallimport_save( $post_id ): void {
        if ( get_post_type( $post_id ) !== 'product' ) {
            return;
        }

        if ( self::$is_remapping ) {
            return;
        }

        self::$is_remapping = true;

        try {
            ICM_Mapper::remap_product_categories( (int) $post_id );
        } finally {
            self::$is_remapping = false;
        }
    }

    /**
     * Daily maintenance: purge old log entries.
     */
    public function daily_maintenance(): void {
        $retention_days = (int) get_option( 'icm_log_retention_days', 90 );
        if ( $retention_days > 0 ) {
            ICM_DB::purge_old_logs( $retention_days );
        }
    }

    /**
     * Check if remapping is currently in progress (for external use).
     */
    public static function is_remapping(): bool {
        return self::$is_remapping;
    }

    /**
     * Manually set remapping flag (used by batch recheck).
     */
    public static function set_remapping( bool $value ): void {
        self::$is_remapping = $value;
    }
}
