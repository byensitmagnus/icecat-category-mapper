<?php
/**
 * Admin view: Settings tab.
 * Icecat credentials, fallback behavior, maintenance tools.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$username         = get_option( 'icm_icecat_username', '' );
$lang_id          = (int) get_option( 'icm_icecat_lang_id', 1 );
$auto_remap       = (int) get_option( 'icm_auto_remap_enabled', 1 );
$fallback         = get_option( 'icm_fallback_behavior', 'keep' );
$fallback_cat     = get_option( 'icm_fallback_category', '' );
$retention_days   = (int) get_option( 'icm_log_retention_days', 90 );
$protected_raw    = get_option( 'icm_protected_slugs', '' );
$title_rules      = (string) get_option( 'icm_title_rules', '' );
$protected_slugs  = array_filter( array_map( 'trim', explode( ',', (string) $protected_raw ) ) );
$woo_categories   = ICM_Admin::get_woo_categories_dropdown();

$icecat_languages = [
    1  => __( 'English (1)', 'icecat-category-mapper' ),
    8  => __( 'Danish (8)', 'icecat-category-mapper' ),
    2  => __( 'German (2)', 'icecat-category-mapper' ),
    3  => __( 'French (3)', 'icecat-category-mapper' ),
    4  => __( 'Spanish (4)', 'icecat-category-mapper' ),
    5  => __( 'Dutch (5)', 'icecat-category-mapper' ),
    6  => __( 'Italian (6)', 'icecat-category-mapper' ),
    7  => __( 'Portuguese (7)', 'icecat-category-mapper' ),
    9  => __( 'Swedish (9)', 'icecat-category-mapper' ),
    10 => __( 'Norwegian (10)', 'icecat-category-mapper' ),
    11 => __( 'Finnish (11)', 'icecat-category-mapper' ),
];
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'icm_save_settings' ); ?>
    <input type="hidden" name="action" value="icm_save_settings">

    <!-- Icecat API -->
    <h2><?php esc_html_e( 'Icecat API', 'icecat-category-mapper' ); ?></h2>
    <table class="form-table">
        <tr>
            <th><label for="icm_icecat_username"><?php esc_html_e( 'Username', 'icecat-category-mapper' ); ?></label></th>
            <td>
                <input type="text" id="icm_icecat_username" name="icm_icecat_username"
                       value="<?php echo esc_attr( $username ); ?>" class="regular-text"
                       autocomplete="off">
                <p class="description"><?php esc_html_e( 'Your Open Icecat username.', 'icecat-category-mapper' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="icm_icecat_password"><?php esc_html_e( 'Password', 'icecat-category-mapper' ); ?></label></th>
            <td>
                <input type="password" id="icm_icecat_password" name="icm_icecat_password"
                       value="" class="regular-text" placeholder="<?php echo $username ? esc_attr__( '(unchanged)', 'icecat-category-mapper' ) : ''; ?>"
                       autocomplete="new-password">
                <p class="description"><?php esc_html_e( 'Leave empty to keep the current password.', 'icecat-category-mapper' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="icm_icecat_lang_id"><?php esc_html_e( 'Language ID', 'icecat-category-mapper' ); ?></label></th>
            <td>
                <select id="icm_icecat_lang_id" name="icm_icecat_lang_id">
                    <?php foreach ( $icecat_languages as $id => $label ) : ?>
                        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $lang_id, $id ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e( 'Icecat language ID for category names fetched from the API.', 'icecat-category-mapper' ); ?></p>
            </td>
        </tr>
        <tr>
            <th></th>
            <td>
                <button type="button" id="icm-test-connection" class="button">
                    <?php esc_html_e( 'Test connection', 'icecat-category-mapper' ); ?>
                </button>
                <span id="icm-test-result" class="icm-test-result"></span>
            </td>
        </tr>
    </table>

    <!-- Mapping behavior -->
    <h2><?php esc_html_e( 'Mapping behavior', 'icecat-category-mapper' ); ?></h2>
    <table class="form-table">
        <tr>
            <th><?php esc_html_e( 'Auto-remap', 'icecat-category-mapper' ); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="icm_auto_remap_enabled" value="1"
                        <?php checked( $auto_remap, 1 ); ?>>
                    <?php esc_html_e( 'Enable automatic category remapping on product import', 'icecat-category-mapper' ); ?>
                </label>
                <p class="description">
                    <?php esc_html_e( 'When enabled, Icecat categories are automatically remapped to your WooCommerce categories when products are created or updated.', 'icecat-category-mapper' ); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Fallback for unmapped', 'icecat-category-mapper' ); ?></th>
            <td>
                <fieldset>
                    <label>
                        <input type="radio" name="icm_fallback_behavior" value="keep"
                            <?php checked( $fallback, 'keep' ); ?>>
                        <strong><?php esc_html_e( 'Keep', 'icecat-category-mapper' ); ?></strong> — <?php esc_html_e( 'Keep the Icecat category as is', 'icecat-category-mapper' ); ?>
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="icm_fallback_behavior" value="fallback"
                            <?php checked( $fallback, 'fallback' ); ?>>
                        <strong><?php esc_html_e( 'Fallback', 'icecat-category-mapper' ); ?></strong> — <?php esc_html_e( 'Assign a default category', 'icecat-category-mapper' ); ?>
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="icm_fallback_behavior" value="remove"
                            <?php checked( $fallback, 'remove' ); ?>>
                        <strong><?php esc_html_e( 'Remove', 'icecat-category-mapper' ); ?></strong> — <?php esc_html_e( 'Remove the unmapped category entirely', 'icecat-category-mapper' ); ?>
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="icm_fallback_behavior" value="draft"
                            <?php checked( $fallback, 'draft' ); ?>>
                        <strong><?php esc_html_e( 'Draft', 'icecat-category-mapper' ); ?></strong> — <?php esc_html_e( 'Set the product to draft so it never shows in the shop until you map the category and run a recheck', 'icecat-category-mapper' ); ?>
                    </label>
                </fieldset>
            </td>
        </tr>
        <tr id="icm-fallback-cat-row" style="<?php echo $fallback !== 'fallback' ? 'display:none;' : ''; ?>">
            <th><label for="icm_fallback_category"><?php esc_html_e( 'Fallback category', 'icecat-category-mapper' ); ?></label></th>
            <td>
                <select id="icm_fallback_category" name="icm_fallback_category" class="regular-text">
                    <option value=""><?php esc_html_e( '— Select category —', 'icecat-category-mapper' ); ?></option>
                    <?php foreach ( $woo_categories as $slug => $name ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>"
                            <?php selected( $fallback_cat, $slug ); ?>>
                            <?php echo esc_html( $name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>

    <!-- Title rules -->
    <h2><?php esc_html_e( 'Title rules', 'icecat-category-mapper' ); ?></h2>
    <p class="description">
        <?php esc_html_e( 'Used only when a category has no mapping — e.g. the Icecat catch-all "Other" or "Not Categorized", where only the product title tells a mouse from a fan. One rule per line: WooCommerce slug, a pipe, then a regular expression (case-insensitive). First match wins, so put specific rules (mouse pad) before general ones (mouse).', 'icecat-category-mapper' ); ?>
    </p>
    <table class="form-table">
        <tr>
            <th><label for="icm_title_rules"><?php esc_html_e( 'Rules', 'icecat-category-mapper' ); ?></label></th>
            <td>
                <textarea id="icm_title_rules" name="icm_title_rules" rows="10" class="large-text code"
                          placeholder="mouse-pads | mouse\s?pad&#10;mice | \bmouse\b"><?php echo esc_textarea( $title_rules ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Lines starting with # are comments. Invalid regex lines are skipped.', 'icecat-category-mapper' ); ?></p>
            </td>
        </tr>
    </table>

    <!-- Protected categories -->
    <h2><?php esc_html_e( 'Protected categories', 'icecat-category-mapper' ); ?></h2>
    <p class="description">
        <?php esc_html_e( 'Choose the WooCommerce categories that should never be remapped by the plugin. Targets you have already configured in your mappings are protected automatically. Use this for existing categories you want left alone.', 'icecat-category-mapper' ); ?>
    </p>
    <table class="form-table">
        <tr>
            <th><label for="icm_protected_slugs"><?php esc_html_e( 'Categories', 'icecat-category-mapper' ); ?></label></th>
            <td>
                <select id="icm_protected_slugs" name="icm_protected_slugs[]" multiple
                        class="icm-multi-select" size="10" style="min-width:400px;">
                    <?php foreach ( $woo_categories as $slug => $name ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>"
                            <?php echo in_array( $slug, $protected_slugs, true ) ? 'selected' : ''; ?>>
                            <?php echo esc_html( $name ); ?> (<?php echo esc_html( $slug ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php esc_html_e( 'Hold Ctrl/Cmd to select multiple. Optional — leave empty for no extra protected categories.', 'icecat-category-mapper' ); ?>
                </p>
            </td>
        </tr>
    </table>

    <!-- Maintenance -->
    <h2><?php esc_html_e( 'Maintenance', 'icecat-category-mapper' ); ?></h2>
    <table class="form-table">
        <tr>
            <th><label for="icm_log_retention_days"><?php esc_html_e( 'Log retention', 'icecat-category-mapper' ); ?></label></th>
            <td>
                <input type="number" id="icm_log_retention_days" name="icm_log_retention_days"
                       value="<?php echo esc_attr( $retention_days ); ?>" class="small-text" min="1" max="365">
                <span><?php esc_html_e( 'days', 'icecat-category-mapper' ); ?></span>
                <p class="description"><?php esc_html_e( 'Log entries older than this are automatically deleted daily.', 'icecat-category-mapper' ); ?></p>
            </td>
        </tr>
    </table>

    <?php submit_button( __( 'Save settings', 'icecat-category-mapper' ) ); ?>
</form>

<!-- Batch recheck (separate section) -->
<hr>
<h2><?php esc_html_e( 'Recheck products', 'icecat-category-mapper' ); ?></h2>
<p class="description">
    <?php esc_html_e( 'Runs all existing products through the mapper. Useful after adding new mappings to fix already-imported products.', 'icecat-category-mapper' ); ?>
</p>

<div class="icm-batch-recheck">
    <button type="button" id="icm-batch-recheck-btn" class="button button-primary">
        <?php esc_html_e( 'Recheck all products', 'icecat-category-mapper' ); ?>
    </button>
    <div id="icm-batch-progress" class="icm-batch-progress" style="display:none;">
        <div class="icm-progress-bar">
            <div class="icm-progress-fill" id="icm-progress-fill"></div>
        </div>
        <p id="icm-batch-status"></p>
    </div>
</div>
