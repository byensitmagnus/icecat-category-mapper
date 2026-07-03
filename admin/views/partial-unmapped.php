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
?>

<?php if ( $total_items === 0 ) : ?>
    <div class="icm-empty-state">
        <p>Ingen umappede kategorier endnu.</p>
        <p class="description">
            Naar produkter importeres med Icecat-kategorier der ikke har en mapping,
            vises de her saa du kan tildele dem den rette Byens IT-kategori.
        </p>
    </div>
<?php else : ?>
    <p class="icm-stats">
        <strong><?php echo $total_items; ?></strong> umappede kategorier fundet.
        Brug "Quick-map" knappen for hurtigt at tildele en Byens IT-kategori.
    </p>

    <table class="wp-list-table widefat fixed striped icm-table">
        <thead>
            <tr>
                <th class="icm-col-id">Icecat ID</th>
                <th class="icm-col-name">Icecat Kategori</th>
                <th class="icm-col-count">Antal produkter</th>
                <th class="icm-col-date">Foerst set</th>
                <th class="icm-col-date">Sidst set</th>
                <th class="icm-col-quickmap">Quick-map</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $unmapped as $item ) : ?>
                <tr>
                    <td class="icm-col-id">
                        <?php if ( (int) $item['icecat_cat_id'] > 0 ) : ?>
                            <code><?php echo esc_html( $item['icecat_cat_id'] ); ?></code>
                        <?php else : ?>
                            <span class="icm-unknown">Ukendt</span>
                        <?php endif; ?>
                    </td>
                    <td class="icm-col-name">
                        <strong><?php echo esc_html( $item['icecat_cat_name'] ); ?></strong>
                    </td>
                    <td class="icm-col-count">
                        <?php echo esc_html( $item['product_count'] ); ?>
                    </td>
                    <td class="icm-col-date">
                        <?php echo esc_html( wp_date( 'd. M Y', strtotime( $item['first_seen'] ) ) ); ?>
                    </td>
                    <td class="icm-col-date">
                        <?php echo esc_html( wp_date( 'd. M Y', strtotime( $item['last_seen'] ) ) ); ?>
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
                                <option value="">Vaelg...</option>
                                <?php foreach ( $woo_categories as $slug => $name ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>">
                                        <?php echo esc_html( $name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit" class="button button-small button-primary">Map</button>
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
                    'prev_text' => '&laquo; Forrige',
                    'next_text' => 'Naeste &raquo;',
                ] );
                ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
