<?php
/**
 * Admin view: Mappings tab.
 * Shows all Icecat -> Byens IT category mappings with search, add, edit, delete.
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
            <strong><?php echo esc_html( $unconfigured_count ); ?> mappinger mangler target-kategori.</strong>
            Tildel hver Icecat-kategori en af dine egne WooCommerce-kategorier for at aktivere automatisk remapping.
            <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'icm-category-mapper', 'tab' => 'mappings', 'filter' => 'unconfigured' ], admin_url( 'admin.php' ) ) ); ?>">
                Vis kun umappede &raquo;
            </a>
        </p>
    </div>
<?php endif; ?>

<!-- Filter tabs -->
<ul class="subsubsub" style="margin-bottom:10px;">
    <li>
        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'icm-category-mapper', 'tab' => 'mappings' ], admin_url( 'admin.php' ) ) ); ?>"
           class="<?php echo $filter === 'all' ? 'current' : ''; ?>">
            Alle <span class="count">(<?php echo ICM_DB::count_mappings(); ?>)</span>
        </a> |
    </li>
    <li>
        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'icm-category-mapper', 'tab' => 'mappings', 'filter' => 'configured' ], admin_url( 'admin.php' ) ) ); ?>"
           class="<?php echo $filter === 'configured' ? 'current' : ''; ?>">
            Konfigurerede <span class="count">(<?php echo ICM_DB::count_mappings( '', 'configured' ); ?>)</span>
        </a> |
    </li>
    <li>
        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'icm-category-mapper', 'tab' => 'mappings', 'filter' => 'unconfigured' ], admin_url( 'admin.php' ) ) ); ?>"
           class="<?php echo $filter === 'unconfigured' ? 'current' : ''; ?>">
            Mangler target <span class="count">(<?php echo $unconfigured_count; ?>)</span>
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
                   placeholder="Soeg i mappinger..." class="icm-search-input">
            <button type="submit" class="button">Soeg</button>
        </form>

        <div class="icm-actions-buttons">
            <button type="button" id="icm-toggle-add-form" class="button button-primary">
                + Tilfoej ny mapping
            </button>

            <button type="button" id="icm-fetch-icecat" class="button">
                Hent Icecat kategorier
            </button>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                  class="icm-inline-form" onsubmit="return confirm(icm_admin.i18n.confirm_reset)">
                <?php wp_nonce_field( 'icm_reset_defaults' ); ?>
                <input type="hidden" name="action" value="icm_reset_defaults">
                <button type="submit" class="button">Nulstil standarder</button>
            </form>
        </div>
    </div>

    <div class="icm-cache-info">
        <?php
        $cache_time = ICM_Icecat_Fetcher::get_cache_time_formatted();
        $cached     = ICM_Icecat_Fetcher::get_cached_categories();
        ?>
        <span class="icm-cache-status" id="icm-cache-status">
            Icecat cache: <?php echo esc_html( $cache_time ); ?>
            <?php if ( ! empty( $cached ) ) : ?>
                (<?php echo number_format( count( $cached ) ); ?> kategorier)
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
    Viser <strong><?php echo count( $mappings ); ?></strong> af
    <strong><?php echo $total_items; ?></strong> mappinger.
</p>

<!-- Mappings table -->
<table class="wp-list-table widefat fixed striped icm-table">
    <thead>
        <tr>
            <th class="icm-col-id">Icecat ID</th>
            <th class="icm-col-name">Icecat Kategori</th>
            <th class="icm-col-name-da">Dansk navn</th>
            <th class="icm-col-target">Byens IT Kategori</th>
            <th class="icm-col-default">Standard</th>
            <th class="icm-col-actions">Handlinger</th>
        </tr>
    </thead>
    <tbody>
        <?php if ( empty( $mappings ) ) : ?>
            <tr>
                <td colspan="6" class="icm-no-data">Ingen mappinger fundet.</td>
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
                            <span class="icm-target-empty">— mangler target —</span>
                        <?php endif; ?>
                    </td>
                    <td class="icm-col-default">
                        <?php if ( (int) $mapping['is_default'] ) : ?>
                            <span class="icm-default-badge" title="Standard mapping">&#10003;</span>
                        <?php endif; ?>
                    </td>
                    <td class="icm-col-actions">
                        <a href="<?php echo esc_url( add_query_arg( [
                            'page' => 'icm-category-mapper',
                            'tab'  => 'mappings',
                            'edit' => $mapping['id'],
                        ], admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
                            Rediger
                        </a>
                        <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [
                            'action'     => 'icm_delete_mapping',
                            'mapping_id' => $mapping['id'],
                        ], admin_url( 'admin-post.php' ) ), 'icm_delete_mapping' ) ); ?>"
                           class="button button-small icm-delete-btn"
                           onclick="return confirm(icm_admin.i18n.confirm_delete)">
                            Slet
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
                'prev_text' => '&laquo; Forrige',
                'next_text' => 'Naeste &raquo;',
            ] );
            ?>
        </div>
    </div>
<?php endif; ?>
