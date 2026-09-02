<?php
/**
 * Admin view: Unmapped categories tab.
 * Shows Icecat categories that have been encountered but have no mapping.
 * Provides quick-map functionality to assign them.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$per_page     = 50;
$current_page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
$offset       = ( $current_page - 1 ) * $per_page;

$unmapped    = ICM_DB::get_unmapped_categories( [
    'per_page' => $per_page,
    'offset'   => $offset,
] );
$total_items = ICM_DB::count_unmapped();
$total_pages = ceil( $total_items / $per_page );

$woo_categories = ICM_Admin::get_woo_categories_dropdown();

$date_format = get_option( 'date_format', 'Y-m-d' );
?>

<?php if ( $total_items === 0 ) : ?>
    <div class="icm-empty-state">
        <p><?php esc_html_e( 'No unmapped categories yet.', 'icecat-category-mapper' ); ?></p>
        <p class="description">
            <?php esc_html_e( 'When products are imported with Icecat categories that have no mapping, they appear here so you can assign them the right WooCommerce category.', 'icecat-category-mapper' ); ?>
        </p>
    </div>
<?php else : ?>
    <p class="icm-stats">
        <?php
        printf(
            /* translators: %s: number of unmapped categories. */
            wp_kses( _n( '<strong>%s</strong> unmapped category found.', '<strong>%s</strong> unmapped categories found.', $total_items, 'icecat-category-mapper' ), [ 'strong' => [] ] ),
            esc_html( number_format_i18n( $total_items ) )
        );
        ?>
        <?php esc_html_e( 'Use the quick-map button to quickly assign a WooCommerce category.', 'icecat-category-mapper' ); ?>
        <?php esc_html_e( 'Er rækken en af butikkens EGNE kategorier (fx Gaming computer, CS2), så klik "Beskyt" — den røres aldrig af remapperen og forsvinder fra listen.', 'icecat-category-mapper' ); ?>
    </p>

    <table class="wp-list-table widefat fixed striped icm-table">
        <thead>
            <tr>
                <th class="icm-col-id"><?php esc_html_e( 'Icecat ID', 'icecat-category-mapper' ); ?></th>
                <th class="icm-col-name"><?php esc_html_e( 'Icecat category', 'icecat-category-mapper' ); ?></th>
                <th class="icm-col-count"><?php esc_html_e( 'Products', 'icecat-category-mapper' ); ?></th>
                <th class="icm-col-date"><?php esc_html_e( 'First seen', 'icecat-category-mapper' ); ?></th>
                <th class="icm-col-date"><?php esc_html_e( 'Last seen', 'icecat-category-mapper' ); ?></th>
                <th class="icm-col-quickmap"><?php esc_html_e( 'Quick-map', 'icecat-category-mapper' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $unmapped as $item ) : ?>
                <tr>
                    <td class="icm-col-id">
                        <?php if ( (int) $item['icecat_cat_id'] > 0 ) : ?>
                            <code><?php echo esc_html( $item['icecat_cat_id'] ); ?></code>
                        <?php else : ?>
                            <span class="icm-unknown"><?php esc_html_e( 'Unknown', 'icecat-category-mapper' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="icm-col-name">
                        <?php
                        // '(no category)' is stored as a locale-independent sentinel — translate for display only
                        $icm_display_name = $item['icecat_cat_name'] === '(no category)'
                            ? __( '(no category)', 'icecat-category-mapper' )
                            : $item['icecat_cat_name'];
                        ?>
                        <strong><?php echo esc_html( $icm_display_name ); ?></strong>
                    </td>
                    <td class="icm-col-count">
                        <?php echo esc_html( $item['product_count'] ); ?>
                    </td>
                    <td class="icm-col-date">
                        <?php echo esc_html( wp_date( $date_format, strtotime( $item['first_seen'] ) ) ); ?>
                    </td>
                    <td class="icm-col-date">
                        <?php echo esc_html( wp_date( $date_format, strtotime( $item['last_seen'] ) ) ); ?>
                    </td>
                    <td class="icm-col-quickmap">
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                              class="icm-quickmap-form">
                            <?php wp_nonce_field( 'icm_quick_map' ); ?>
                            <input type="hidden" name="action" value="icm_quick_map">
                            <input type="hidden" name="unmapped_id" value="<?php echo esc_attr( $item['id'] ); ?>">
                            <input type="hidden" name="icecat_cat_id" value="<?php echo esc_attr( $item['icecat_cat_id'] ); ?>">
                            <input type="hidden" name="icecat_cat_name" value="<?php echo esc_attr( $item['icecat_cat_name'] ); ?>">

                            <select name="woo_term_slug" required class="icm-quickmap-select">
                                <option value=""><?php esc_html_e( 'Select...', 'icecat-category-mapper' ); ?></option>
                                <?php foreach ( $woo_categories as $slug => $name ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>">
                                        <?php echo esc_html( $name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Map', 'icecat-category-mapper' ); ?></button>
                        </form>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                              class="icm-protect-form" style="display:inline-block;margin-left:6px;">
                            <?php wp_nonce_field( 'icm_protect_category' ); ?>
                            <input type="hidden" name="action" value="icm_protect_category">
                            <input type="hidden" name="unmapped_id" value="<?php echo esc_attr( $item['id'] ); ?>">
                            <input type="hidden" name="icecat_cat_name" value="<?php echo esc_attr( $item['icecat_cat_name'] ); ?>">
                            <button type="submit" class="button button-small"
                                    title="<?php esc_attr_e( 'Butikkens egen kategori — beskyt den mod remapping og fjern fra denne liste', 'icecat-category-mapper' ); ?>"><?php esc_html_e( 'Beskyt', 'icecat-category-mapper' ); ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ( $total_pages > 1 ) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links( [
                    'base'      => add_query_arg( 'paged', '%#%' ),
                    'format'    => '',
                    'total'     => $total_pages,
                    'current'   => $current_page,
                    'prev_text' => '&laquo; ' . __( 'Previous', 'icecat-category-mapper' ),
                    'next_text' => __( 'Next', 'icecat-category-mapper' ) . ' &raquo;',
                ] );
                ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
