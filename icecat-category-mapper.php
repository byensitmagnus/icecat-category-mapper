<?php
/**
 * Plugin Name: Icecat Category Mapper for WooCommerce
 * Plugin URI:  https://github.com/byensitmagnus/icecat-category-mapper
 * Description: Automatically maps Icecat product categories to your own WooCommerce categories. Fully configurable from the admin.
 * Version:     1.3.0
 * Author:      Byens IT
 * Author URI:  https://github.com/byensitmagnus
 * Text Domain: icecat-category-mapper
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'ICM_VERSION', '1.3.0' );
define( 'ICM_PLUGIN_FILE', __FILE__ );
define( 'ICM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ICM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ICM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load all required class files.
 */
function icm_load_dependencies() {
    $includes = ICM_PLUGIN_DIR . 'includes/';
    $admin    = ICM_PLUGIN_DIR . 'admin/';

    require_once $includes . 'class-icm-activator.php';
    require_once $includes . 'class-icm-deactivator.php';
    require_once $includes . 'class-icm-db.php';
    require_once $includes . 'class-icm-defaults.php';
    require_once $includes . 'class-icm-mapper.php';
    require_once $includes . 'class-icm-hooks.php';
    require_once $includes . 'class-icm-icecat-fetcher.php';
    require_once $includes . 'class-icm-logger.php';

    if ( is_admin() ) {
        require_once $admin . 'class-icm-admin.php';
    }
}

/**
 * Plugin activation hook.
 */
function icm_activate() {
    icm_load_dependencies();
    ICM_Activator::activate();

    // Flag for showing welcome notice on first activation
    set_transient( 'icm_just_activated', 1, 60 );
}
register_activation_hook( __FILE__, 'icm_activate' );

/**
 * Plugin deactivation hook.
 */
function icm_deactivate() {
    icm_load_dependencies();
    ICM_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'icm_deactivate' );

/**
 * Initialize the plugin after all plugins are loaded.
 */
function icm_init() {
    // Load translations first so even the "WooCommerce missing" notice is translated
    load_plugin_textdomain(
        'icecat-category-mapper',
        false,
        dirname( ICM_PLUGIN_BASENAME ) . '/languages'
    );

    // Only run if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'icm_woocommerce_missing_notice' );
        return;
    }

    icm_load_dependencies();

    // Check if DB needs update
    $db_version = get_option( 'icm_db_version', '0' );
    if ( version_compare( $db_version, ICM_VERSION, '<' ) ) {
        ICM_Activator::activate();
    }

    // Initialize hooks (product save remapping)
    $hooks = new ICM_Hooks();
    $hooks->register();

    // Initialize admin if in admin context
    if ( is_admin() ) {
        $admin = new ICM_Admin();
        $admin->register();
    }
}
add_action( 'plugins_loaded', 'icm_init' );

/**
 * Display admin notice when WooCommerce is not active.
 */
function icm_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e( 'Icecat Category Mapper', 'icecat-category-mapper' ); ?></strong>
            <?php esc_html_e( 'requires WooCommerce to be installed and activated.', 'icecat-category-mapper' ); ?>
        </p>
    </div>
    <?php
}

/**
 * Add a "Settings" link to the plugin row on the Plugins page.
 */
function icm_plugin_action_links( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=icm-category-mapper' ) ) . '">' .
                     esc_html__( 'Settings', 'icecat-category-mapper' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'icm_plugin_action_links' );

/**
 * Declare HPOS compatibility (High-Performance Order Storage).
 * The plugin NEVER touches orders, so it IS compatible — the declaration removes the yellow
 * "not compatible" banner WooCommerce shows on HPOS shops (default since WC 8.2).
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ICM_PLUGIN_FILE, true );
    }
} );
