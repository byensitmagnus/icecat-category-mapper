<?php
/**
 * Admin page controller.
 * Registers menu, handles routing between tabs, processes form submissions, and AJAX handlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICM_Admin {

    /**
     * Register admin hooks.
     */
    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_notices', [ $this, 'maybe_show_welcome_notice' ] );

        // Form handlers
        add_action( 'admin_post_icm_save_mapping', [ $this, 'handle_save_mapping' ] );
        add_action( 'admin_post_icm_delete_mapping', [ $this, 'handle_delete_mapping' ] );
        add_action( 'admin_post_icm_save_settings', [ $this, 'handle_save_settings' ] );
        add_action( 'admin_post_icm_reset_defaults', [ $this, 'handle_reset_defaults' ] );
        add_action( 'admin_post_icm_clear_log', [ $this, 'handle_clear_log' ] );
        add_action( 'admin_post_icm_quick_map', [ $this, 'handle_quick_map' ] );
        add_action( 'admin_post_icm_protect_category', [ $this, 'handle_protect_category' ] );

        // AJAX handlers
        add_action( 'wp_ajax_icm_fetch_icecat', [ $this, 'ajax_fetch_icecat' ] );
        add_action( 'wp_ajax_icm_search_icecat', [ $this, 'ajax_search_icecat' ] );
        add_action( 'wp_ajax_icm_test_connection', [ $this, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_icm_batch_recheck', [ $this, 'ajax_batch_recheck' ] );
    }

    /**
     * Show a welcome notice after first activation.
     */
    public function maybe_show_welcome_notice(): void {
        if ( ! get_transient( 'icm_just_activated' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        delete_transient( 'icm_just_activated' );

        $url = admin_url( 'admin.php?page=icm-category-mapper' );
        ?>
        <div class="notice notice-info is-dismissible">
            <h3 style="margin-top:10px;"><?php esc_html_e( 'Thank you for installing Icecat Category Mapper!', 'icecat-category-mapper' ); ?></h3>
            <p>
                <?php
                printf(
                    /* translators: %s: link to the plugin admin page. */
                    esc_html__( 'The plugin has created a reference list of popular Icecat categories. Next step: Go to %s and assign each Icecat category to one of your own WooCommerce categories.', 'icecat-category-mapper' ),
                    '<a href="' . esc_url( $url ) . '"><strong>' . esc_html__( 'WooCommerce » Icecat Mapper', 'icecat-category-mapper' ) . '</strong></a>'
                );
                ?>
            </p>
            <p>
                <a href="<?php echo esc_url( $url ); ?>" class="button button-primary"><?php esc_html_e( 'Start setup', 'icecat-category-mapper' ); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Count mappings that don't have a target configured yet.
     */
    public static function count_unconfigured_mappings(): int {
        global $wpdb;
        $table = ICM_DB::mappings_table();
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE woo_term_slug = ''" );
    }

    /**
     * Add submenu page under WooCommerce.
     */
    public function add_menu_page(): void {
        add_submenu_page(
            'woocommerce',
            __( 'Icecat Category Mapper', 'icecat-category-mapper' ),
            __( 'Icecat Mapper', 'icecat-category-mapper' ),
            'manage_woocommerce',
            'icm-category-mapper',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Enqueue admin CSS and JS.
     */
    public function enqueue_assets( string $hook ): void {
        if ( $hook !== 'woocommerce_page_icm-category-mapper' ) {
            return;
        }

        wp_enqueue_style(
            'icm-admin-css',
            ICM_PLUGIN_URL . 'assets/css/icm-admin.css',
            [],
            ICM_VERSION
        );

        wp_enqueue_script(
            'icm-admin-js',
            ICM_PLUGIN_URL . 'assets/js/icm-admin.js',
            [ 'jquery' ],
            ICM_VERSION,
            true
        );

        wp_localize_script( 'icm-admin-js', 'icm_admin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'icm_admin_nonce' ),
            'i18n'     => [
                'confirm_delete'  => __( 'Are you sure you want to delete this mapping?', 'icecat-category-mapper' ),
                'confirm_reset'   => __( 'Are you sure? This deletes all mappings and re-inserts the defaults.', 'icecat-category-mapper' ),
                'confirm_clear'   => __( 'Are you sure you want to clear the entire log?', 'icecat-category-mapper' ),
                'fetching'        => __( 'Fetching Icecat categories...', 'icecat-category-mapper' ),
                'fetch_done'      => __( 'Icecat categories fetched!', 'icecat-category-mapper' ),
                'fetch_error'     => __( 'Error fetching categories.', 'icecat-category-mapper' ),
                'fetch_button'    => __( 'Fetch Icecat categories', 'icecat-category-mapper' ),
                /* translators: 1: date/time the cache was fetched, 2: number of cached categories. */
                'cache_status'    => __( 'Icecat cache: %1$s (%2$s categories)', 'icecat-category-mapper' ),
                'no_results'      => __( 'No results', 'icecat-category-mapper' ),
                'testing'         => __( 'Testing connection...', 'icecat-category-mapper' ),
                'test_ok'         => __( 'Connection OK!', 'icecat-category-mapper' ),
                'test_fail'       => __( 'Connection error.', 'icecat-category-mapper' ),
                'rechecking'      => __( 'Rechecking products...', 'icecat-category-mapper' ),
                'recheck_done'    => __( 'Recheck finished!', 'icecat-category-mapper' ),
                'batch_confirm'   => __( 'Are you sure? This rechecks the categories of all products.', 'icecat-category-mapper' ),
                'error_prefix'    => __( 'Error:', 'icecat-category-mapper' ),
                'unknown_error'   => __( 'Unknown error', 'icecat-category-mapper' ),
                /* translators: 1: products processed, 2: total products, 3: remapped count, 4: unmapped count. */
                'batch_status'    => __( '%1$s of %2$s products processed. %3$s remapped, %4$s unmapped.', 'icecat-category-mapper' ),
                'batch_done'      => __( 'Done!', 'icecat-category-mapper' ),
                'ajax_error'      => __( 'AJAX error. Please try again.', 'icecat-category-mapper' ),
            ],
        ] );
    }

    /**
     * Render the admin page (tab router).
     */
    public function render_page(): void {
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'mappings';

        // Admin notice messages
        if ( isset( $_GET['icm_msg'] ) ) {
            $this->show_admin_message( sanitize_key( $_GET['icm_msg'] ) );
        }

        ?>
        <div class="wrap icm-wrap">
            <h1><?php esc_html_e( 'Icecat Category Mapper', 'icecat-category-mapper' ); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url( $this->tab_url( 'mappings' ) ); ?>"
                   class="nav-tab <?php echo $current_tab === 'mappings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Mappings', 'icecat-category-mapper' ); ?>
                </a>
                <a href="<?php echo esc_url( $this->tab_url( 'unmapped' ) ); ?>"
                   class="nav-tab <?php echo $current_tab === 'unmapped' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Unmapped', 'icecat-category-mapper' ); ?>
                    <?php
                    $unmapped_count = ICM_DB::count_unmapped();
                    if ( $unmapped_count > 0 ) :
                        ?>
                        <span class="icm-badge"><?php echo esc_html( $unmapped_count ); ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo esc_url( $this->tab_url( 'settings' ) ); ?>"
                   class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Settings', 'icecat-category-mapper' ); ?>
                </a>
                <a href="<?php echo esc_url( $this->tab_url( 'log' ) ); ?>"
                   class="nav-tab <?php echo $current_tab === 'log' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Log', 'icecat-category-mapper' ); ?>
                </a>
            </nav>

            <div class="icm-tab-content">
                <?php
                switch ( $current_tab ) {
                    case 'unmapped':
                        include ICM_PLUGIN_DIR . 'admin/views/partial-unmapped.php';
                        break;
                    case 'settings':
                        include ICM_PLUGIN_DIR . 'admin/views/page-settings.php';
                        break;
                    case 'log':
                        include ICM_PLUGIN_DIR . 'admin/views/page-log.php';
                        break;
                    default:
                        include ICM_PLUGIN_DIR . 'admin/views/page-mappings.php';
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /* ───────────────────────────────────────────────
     *  Form Handlers
     * ─────────────────────────────────────────────── */

    /**
     * Handle add/edit mapping form submission.
     */
    public function handle_save_mapping(): void {
        check_admin_referer( 'icm_save_mapping' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'icecat-category-mapper' ) );
        }

        $mapping_id        = (int) ( $_POST['mapping_id'] ?? 0 );
        $icecat_cat_id     = (int) ( $_POST['icecat_cat_id'] ?? 0 );
        $icecat_cat_name   = sanitize_text_field( $_POST['icecat_cat_name'] ?? '' );
        $icecat_cat_name_da = sanitize_text_field( $_POST['icecat_cat_name_da'] ?? '' );
        $woo_term_slug     = sanitize_title( $_POST['woo_term_slug'] ?? '' );

        // Resolve WooCommerce term
        $woo_term    = get_term_by( 'slug', $woo_term_slug, 'product_cat' );
        $woo_term_id = $woo_term ? $woo_term->term_id : 0;

        $data = [
            'icecat_cat_id'      => $icecat_cat_id,
            'icecat_cat_name'    => $icecat_cat_name,
            'icecat_cat_name_da' => $icecat_cat_name_da,
            'woo_term_id'        => $woo_term_id,
            'woo_term_slug'      => $woo_term_slug,
        ];

        if ( $mapping_id > 0 ) {
            ICM_DB::update_mapping( $mapping_id, $data );
            $msg = 'mapping_updated';
        } else {
            $data['is_default'] = 0;
            ICM_DB::insert_mapping( $data );
            $msg = 'mapping_added';
        }

        wp_safe_redirect( add_query_arg( 'icm_msg', $msg, $this->tab_url( 'mappings' ) ) );
        exit;
    }

    /**
     * Handle delete mapping.
     */
    public function handle_delete_mapping(): void {
        check_admin_referer( 'icm_delete_mapping' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'icecat-category-mapper' ) );
        }

        $mapping_id = (int) ( $_GET['mapping_id'] ?? 0 );
        if ( $mapping_id > 0 ) {
            ICM_DB::delete_mapping( $mapping_id );
        }

        wp_safe_redirect( add_query_arg( 'icm_msg', 'mapping_deleted', $this->tab_url( 'mappings' ) ) );
        exit;
    }

    /**
     * Handle settings form submission.
     */
    public function handle_save_settings(): void {
        check_admin_referer( 'icm_save_settings' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'icecat-category-mapper' ) );
        }

        update_option( 'icm_icecat_username', sanitize_text_field( $_POST['icm_icecat_username'] ?? '' ) );

        // Only update password if a new one was provided
        $new_password = $_POST['icm_icecat_password'] ?? '';
        if ( ! empty( $new_password ) ) {
            update_option( 'icm_icecat_password', sanitize_text_field( $new_password ) );
        }

        update_option( 'icm_icecat_lang_id', (int) ( $_POST['icm_icecat_lang_id'] ?? 1 ) );
        update_option( 'icm_auto_remap_enabled', isset( $_POST['icm_auto_remap_enabled'] ) ? 1 : 0 );
        update_option( 'icm_fallback_behavior', sanitize_key( $_POST['icm_fallback_behavior'] ?? 'keep' ) );
        update_option( 'icm_fallback_category', sanitize_title( $_POST['icm_fallback_category'] ?? '' ) );
        update_option( 'icm_log_retention_days', max( 1, (int) ( $_POST['icm_log_retention_days'] ?? 90 ) ) );

        // Title rules — free text, one `slug | regex` per line. Only strip tags; regex needs its punctuation.
        $rules_raw = wp_kses( wp_unslash( $_POST['icm_title_rules'] ?? '' ), [] );
        update_option( 'icm_title_rules', trim( str_replace( "\r", '', $rules_raw ) ) );

        // Protected slugs — multi-select saved as comma-separated list
        $protected = $_POST['icm_protected_slugs'] ?? [];
        if ( is_array( $protected ) ) {
            $protected = array_map( 'sanitize_title', $protected );
            $protected = array_filter( $protected );
            update_option( 'icm_protected_slugs', implode( ',', $protected ) );
        }

        wp_safe_redirect( add_query_arg( 'icm_msg', 'settings_saved', $this->tab_url( 'settings' ) ) );
        exit;
    }

    /**
     * Handle reset to defaults.
     */
    public function handle_reset_defaults(): void {
        check_admin_referer( 'icm_reset_defaults' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'icecat-category-mapper' ) );
        }

        ICM_DB::delete_all_mappings();
        // Re-seed defaults
        ICM_Activator::activate();

        wp_safe_redirect( add_query_arg( 'icm_msg', 'defaults_reset', $this->tab_url( 'mappings' ) ) );
        exit;
    }

    /**
     * Handle clear log.
     */
    public function handle_clear_log(): void {
        check_admin_referer( 'icm_clear_log' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'icecat-category-mapper' ) );
        }

        ICM_DB::clear_log();

        wp_safe_redirect( add_query_arg( 'icm_msg', 'log_cleared', $this->tab_url( 'log' ) ) );
        exit;
    }

    /**
     * Handle quick-map from unmapped tab.
     */
    public function handle_quick_map(): void {
        check_admin_referer( 'icm_quick_map' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to do this.', 'icecat-category-mapper' ) );
        }

        $unmapped_id     = (int) ( $_POST['unmapped_id'] ?? 0 );
        $icecat_cat_id   = (int) ( $_POST['icecat_cat_id'] ?? 0 );
        $icecat_cat_name = sanitize_text_field( $_POST['icecat_cat_name'] ?? '' );
        $woo_term_slug   = sanitize_title( $_POST['woo_term_slug'] ?? '' );

        if ( ! empty( $woo_term_slug ) ) {
            $woo_term = get_term_by( 'slug', $woo_term_slug, 'product_cat' );

            ICM_DB::insert_mapping( [
                'icecat_cat_id'      => $icecat_cat_id,
                'icecat_cat_name'    => $icecat_cat_name,
                'icecat_cat_name_da' => '',
                'woo_term_id'        => $woo_term ? $woo_term->term_id : 0,
                'woo_term_slug'      => $woo_term_slug,
                'is_default'         => 0,
            ] );

            // Remove from unmapped table
            if ( $unmapped_id > 0 ) {
                ICM_DB::delete_unmapped( $unmapped_id );
            }
        }

        wp_safe_redirect( add_query_arg( 'icm_msg', 'mapping_added', $this->tab_url( 'unmapped' ) ) );
        exit;
    }

    /**
     * Handle "Beskyt" from the unmapped tab.
     *
     * The shop's OWN categories (e.g. Gaming computer, CS2) show up as noise in the
     * unmapped list because they are not mapping targets. Protect = add the slug to
     * icm_protected_slugs (never touched by the remapper) + remove the row.
     */
    public function handle_protect_category(): void {
        check_admin_referer( 'icm_protect_category' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Ingen adgang.', 'icecat-category-mapper' ) );
        }

        $unmapped_id = (int) ( $_POST['unmapped_id'] ?? 0 );
        $cat_name    = sanitize_text_field( wp_unslash( $_POST['icecat_cat_name'] ?? '' ) );

        $term = get_term_by( 'name', $cat_name, 'product_cat' );
        if ( $term instanceof WP_Term ) {
            $raw   = (string) get_option( 'icm_protected_slugs', '' );
            $slugs = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
            if ( ! in_array( $term->slug, $slugs, true ) ) {
                $slugs[] = $term->slug;
                update_option( 'icm_protected_slugs', implode( ',', $slugs ) );
            }
        }
        // Remove the row either way (if no WC category matches the name, it is pure noise).
        if ( $unmapped_id > 0 ) {
            ICM_DB::delete_unmapped( $unmapped_id );
        }

        wp_safe_redirect( add_query_arg( 'icm_msg', 'category_protected', $this->tab_url( 'unmapped' ) ) );
        exit;
    }

    /* ───────────────────────────────────────────────
     *  AJAX Handlers
     * ─────────────────────────────────────────────── */

    /**
     * AJAX: Fetch Icecat categories from API.
     */
    public function ajax_fetch_icecat(): void {
        check_ajax_referer( 'icm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'You do not have permission to do this.', 'icecat-category-mapper' ) );
        }

        $result = ICM_Icecat_Fetcher::fetch_categories();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( [
            'count'      => count( $result ),
            'cache_time' => ICM_Icecat_Fetcher::get_cache_time_formatted(),
        ] );
    }

    /**
     * AJAX: Search cached Icecat categories.
     */
    public function ajax_search_icecat(): void {
        check_ajax_referer( 'icm_admin_nonce', 'nonce' );

        // Capability check (the three other AJAX handlers have it; it was missing here).
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'You do not have permission to do this.', 'icecat-category-mapper' ) );
        }

        $query   = sanitize_text_field( $_POST['query'] ?? '' );
        $results = ICM_Icecat_Fetcher::search_categories( $query, 20 );

        wp_send_json_success( $results );
    }

    /**
     * AJAX: Test Icecat connection.
     */
    public function ajax_test_connection(): void {
        check_ajax_referer( 'icm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'You do not have permission to do this.', 'icecat-category-mapper' ) );
        }

        $result = ICM_Icecat_Fetcher::test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( __( 'Connection OK!', 'icecat-category-mapper' ) );
    }

    /**
     * AJAX: Batch recheck all products.
     */
    public function ajax_batch_recheck(): void {
        check_ajax_referer( 'icm_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'You do not have permission to do this.', 'icecat-category-mapper' ) );
        }

        $offset   = (int) ( $_POST['offset'] ?? 0 );
        $per_batch = 50;

        global $wpdb;
        $product_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish','draft','private')
             ORDER BY ID ASC LIMIT %d OFFSET %d",
            $per_batch,
            $offset
        ) );

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish','draft','private')"
        );

        $remapped = 0;
        $unmapped = 0;

        // Temporarily set remapping flag to avoid double-processing from hooks
        ICM_Hooks::set_remapping( true );

        foreach ( $product_ids as $product_id ) {
            $result = ICM_Mapper::remap_product_categories( (int) $product_id );
            $remapped += count( $result['remapped'] );
            $unmapped += count( $result['unmapped'] );
        }

        ICM_Hooks::set_remapping( false );
        ICM_Mapper::clear_cache();

        $processed = $offset + count( $product_ids );
        $has_more  = $processed < $total;

        wp_send_json_success( [
            'processed' => $processed,
            'total'     => $total,
            'remapped'  => $remapped,
            'unmapped'  => $unmapped,
            'has_more'  => $has_more,
            'next_offset' => $processed,
        ] );
    }

    /* ───────────────────────────────────────────────
     *  Helpers
     * ─────────────────────────────────────────────── */

    /**
     * Build a tab URL.
     */
    private function tab_url( string $tab ): string {
        return admin_url( 'admin.php?page=icm-category-mapper&tab=' . $tab );
    }

    /**
     * Show an admin notice based on message code.
     */
    private function show_admin_message( string $msg_code ): void {
        $messages = [
            'mapping_added'   => [ 'success', __( 'Mapping added.', 'icecat-category-mapper' ) ],
            'mapping_updated' => [ 'success', __( 'Mapping updated.', 'icecat-category-mapper' ) ],
            'mapping_deleted' => [ 'success', __( 'Mapping deleted.', 'icecat-category-mapper' ) ],
            'settings_saved'  => [ 'success', __( 'Settings saved.', 'icecat-category-mapper' ) ],
            'defaults_reset'  => [ 'success', __( 'Default mappings restored.', 'icecat-category-mapper' ) ],
            'log_cleared'     => [ 'success', __( 'Log cleared.', 'icecat-category-mapper' ) ],
            'category_protected' => [ 'success', __( 'Kategori beskyttet — den røres aldrig af remapperen og vises ikke længere her.', 'icecat-category-mapper' ) ],
        ];

        if ( isset( $messages[ $msg_code ] ) ) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr( $messages[ $msg_code ][0] ),
                esc_html( $messages[ $msg_code ][1] )
            );
        }
    }

    /**
     * Get all WooCommerce product categories as slug => name pairs.
     * Used in dropdown selectors across admin views.
     *
     * @return array<string, string>
     */
    public static function get_woo_categories_dropdown(): array {
        $terms = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ] );

        if ( is_wp_error( $terms ) ) {
            return [];
        }

        $options = [];
        foreach ( $terms as $term ) {
            $prefix = '';
            if ( $term->parent > 0 ) {
                $parent = get_term( $term->parent, 'product_cat' );
                if ( $parent && ! is_wp_error( $parent ) ) {
                    $prefix = $parent->name . ' > ';
                }
            }
            $options[ $term->slug ] = $prefix . $term->name;
        }

        return $options;
    }
}
