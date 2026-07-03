<?php
/**
 * Admin view: Mappings tab.
 * Shows all Icecat -> WooCommerce category mappings with search, add, edit, delete.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Pagination & search
$per_page    = 50;
$current_page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
$search      = sanitize_text_field( $_GET['s'] ?? '' );
$filter      = sanitize_key( $_GET['filter'] ?? 'all' );
$offset      = ( $current_page - 1 ) * $per_page;

$query_args = [
    'per_page' => $per_page,
    'offset'   => $offset,
    'search'   => $search,
];

// Filter: show only unconfigured (no target) or configured
if ( $filter === 'unconfigured' ) {
    $query_args['only_unconfigured'] = true;
} elseif ( $filter === 'configured' ) {
    $query_args['only_configured'] = true;
}

$mappings    = ICM_DB::get_all_mappings( $query_args );
$total_items = ICM_DB::count_mappings( $search, $filter );
$total_pages = max( 1, ceil( $total_items / $per_page ) );

$unconfigured_count = ICM_Admin::count_unconfigured_mappings();

// Edit mode
$editing    = null;
$editing_id = (int) ( $_GET['edit'] ?? 0 );
if ( $editing_id > 0 ) {
    foreach ( $mappings as $m ) {
        if ( (int) $m['id'] === $editing_id ) {
            $editing = $m;
            break;
        }
    }
}

$woo_categories = ICM_Admin::get_woo_categories_dropdown();
?>

<?php if ( $unconfigured_count > 0 && $filter === 'all' && empty( $search ) ) : ?>
    <div class="notice notice-warning inline" style="margin:15px 0; padding:10px 15px;">
        <p>
            <strong>
                <?php
                printf(
                    /* translators: %d: number of mappings without a target category. */
                    esc_html( _n( '%d mapping is missing a target category.', '%d mappings are missing a target category.', $unconfigured_count, 'icecat-category-mapper' ) ),
                    (int) $unconfigured_count
                );
                ?>
            </strong>
            <?php esc_html_e( 'Assign each Icecat category to one of your own WooCommerce categories to enable automatic remapping.', 'icecat-category-mapper' ); ?>
            <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'icm-category-mapper', 'tab' => 'mappings', 'filter' => 'unconfigured' ], admin_url( 'admin.php' ) ) ); ?>">
                <?php esc_html_e( 'Show only unconfigured', 'icecat-category-mapper' ); ?> &raquo;
            </a>
        </p>
    </div>
<?php endif; ?>

<!-- Filter tabs -->
<ul class="subsubsub" style="margin-bottom:10px;">
    <li>
        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'icm-category-mapper', 'tab' => 'mappings' ], admin_url( 'admin.php' ) ) ); ?>"
           class="<?php echo $filter === 'all' ? 'current' : ''; ?>">
            <?php esc_html_e( 'All', 'icecat-category-mapper' ); ?> <span class="count">(<?php echo ICM_DB::count_mappings(); ?>)</span>
        </a> |
    </li>
    <li>
        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'icm-category-mapper', 'tab' => 'mappings', 'filter' => 'configured' ], admin_url( 'admin.php' ) ) ); ?>"
           class="<?php echo $filter === 'configured' ? 'current' : ''; ?>">
            <?php esc_html_e( 'Configured', 'icecat-category-mapper' ); ?> <span class="count">(<?php echo ICM_DB::count_mappings( '', 'configured' ); ?>)</span>
        </a> |
    </li>
    <li>
        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'icm-category-mapper', 'tab' => 'mappings', 'filter' => 'unconfigured' ], admin_url( 'admin.php' ) ) ); ?>"
           class="<?php echo $filter === 'unconfigured' ? 'current' : ''; ?>">
            <?php esc_html_e( 'Missing target', 'icecat-category-mapper' ); ?> <span class="count">(<?php echo $unconfigured_count; ?>)</span>
        </a>
    </li>
</ul>
<div style="clear:both;"></div>

<div class="icm-mappings-header">
    <div class="icm-actions-row">
        <!-- Search -->
        <form method="get" class="icm-search-form">
            <input type="hidden" name="page" value="icm-category-mapper">
            <input type="hidden" name="tab" value="mappings">
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
                   placeholder="<?php esc_attr_e( 'Search mappings...', 'icecat-category-mapper' ); ?>" class="icm-search-input">
            <button type="submit" class="button"><?php esc_html_e( 'Search', 'icecat-category-mapper' ); ?></button>
        </form>

        <div class="icm-actions-buttons">
            <button type="button" id="icm-toggle-add-form" class="button button-primary">
                <?php esc_html_e( '+ Add new mapping', 'icecat-category-mapper' ); ?>
            </button>

            <button type="button" id="icm-fetch-icecat" class="button">
                <?php esc_html_e( 'Fetch Icecat categories', 'icecat-category-mapper' ); ?>
            </button>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                  class="icm-inline-form" onsubmit="return confirm(icm_admin.i18n.confirm_reset)">
                <?php wp_nonce_field( 'icm_reset_defaults' ); ?>
                <input type="hidden" name="action" value="icm_reset_defaults">
                <button type="submit" class="button"><?php esc_html_e( 'Reset defaults', 'icecat-category-mapper' ); ?></button>
            </form>
        </div>
    </div>

    <div class="icm-cache-info">
        <?php
        $cache_time = ICM_Icecat_Fetcher::get_cache_time_formatted();
        $cached     = ICM_Icecat_Fetcher::get_cached_categories();
        ?>
        <span class="icm-cache-status" id="icm-cache-status">
            <?php esc_html_e( 'Icecat cache:', 'icecat-category-mapper' ); ?> <?php echo esc_html( $cache_time ); ?>
            <?php if ( ! empty( $cached ) ) : ?>
                (<?php
                printf(
                    /* translators: %s: number of cached Icecat categories. */
                    esc_html( _n( '%s category', '%s categories', count( $cached ), 'icecat-category-mapper' ) ),
                    esc_html( number_format_i18n( count( $cached ) ) )
                );
                ?>)
            <?php endif; ?>
        </span>
    </div>
</div>

<!-- Add/Edit form -->
<div id="icm-mapping-form-wrapper" class="icm-form-wrapper" style="<?php echo $editing ? '' : 'display:none;'; ?>">
    <?php include ICM_PLUGIN_DIR . 'admin/views/partial-mapping-form.php'; ?>
</div>

<!-- Stats -->
<p class="icm-stats">
    <?php
    printf(
        /* translators: 1: number of mappings shown, 2: total number of mappings. */
        wp_kses( __( 'Showing <strong>%1$s</strong> of <strong>%2$s</strong> mappings.', 'icecat-category-mapper' ), [ 'strong' => [] ] ),
        esc_html( number_format_i18n( count( $mappings ) ) ),
        esc_html( number_format_i18n( $total_items ) )
    );
    ?>
</p>

<!-- Mappings table -->
<table class="wp-list-table widefat fixed striped icm-table">
    <thead>
        <tr>
            <th class="icm-col-id"><?php esc_html_e( 'Icecat ID', 'icecat-category-mapper' ); ?></th>
            <th class="icm-col-name"><?php esc_html_e( 'Icecat category', 'icecat-category-mapper' ); ?></th>
            <th class="icm-col-name-da"><?php esc_html_e( 'Localized name', 'icecat-category-mapper' ); ?></th>
            <th class="icm-col-target"><?php esc_html_e( 'WooCommerce category', 'icecat-category-mapper' ); ?></th>
            <th class="icm-col-default"><?php esc_html_e( 'Default', 'icecat-category-mapper' ); ?></th>
            <th class="icm-col-actions"><?php esc_html_e( 'Actions', 'icecat-category-mapper' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if ( empty( $mappings ) ) : ?>
            <tr>
                <td colspan="6" class="icm-no-data"><?php esc_html_e( 'No mappings found.', 'icecat-category-mapper' ); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ( $mappings as $mapping ) : ?>
                <tr class="<?php echo $editing_id === (int) $mapping['id'] ? 'icm-editing-row' : ''; ?>">
                    <td class="icm-col-id">
                        <code><?php echo esc_html( $mapping['icecat_cat_id'] ); ?></code>
                    </td>
                    <td class="icm-col-name">
                        <?php echo esc_html( $mapping['icecat_cat_name'] ); ?>
                    </td>
                    <td class="icm-col-name-da">
                        <?php echo esc_html( $mapping['icecat_cat_name_da'] ?: '—' ); ?>
                    </td>
                    <td class="icm-col-target">
                        <?php if ( ! empty( $mapping['woo_term_slug'] ) ) : ?>
                            <span class="icm-target-badge">
                                <?php echo esc_html( $mapping['woo_term_slug'] ); ?>
                            </span>
                        <?php else : ?>
                            <span class="icm-target-empty"><?php esc_html_e( '— no target —', 'icecat-category-mapper' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="icm-col-default">
                        <?php if ( (int) $mapping['is_default'] ) : ?>
                            <span class="icm-default-badge" title="<?php esc_attr_e( 'Default mapping', 'icecat-category-mapper' ); ?>">&#10003;</span>
                        <?php endif; ?>
                    </td>
                    <td class="icm-col-actions">
                        <a href="<?php echo esc_url( add_query_arg( [
                            'page' => 'icm-category-mapper',
                            'tab'  => 'mappings',
                            'edit' => $mapping['id'],
                        ], admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
                            <?php esc_html_e( 'Edit', 'icecat-category-mapper' ); ?>
                        </a>
                        <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [
                            'action'     => 'icm_delete_mapping',
                            'mapping_id' => $mapping['id'],
                        ], admin_url( 'admin-post.php' ) ), 'icm_delete_mapping' ) ); ?>"
                           class="button button-small icm-delete-btn"
                           onclick="return confirm(icm_admin.i18n.confirm_delete)">
                            <?php esc_html_e( 'Delete', 'icecat-category-mapper' ); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
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
