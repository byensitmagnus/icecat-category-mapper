<?php
/**
 * Admin view: Log tab.
 * Shows a filterable log of all remapping actions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Pagination & filters
$per_page     = 50;
$current_page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
$action_filter = sanitize_key( $_GET['log_action'] ?? '' );
$offset       = ( $current_page - 1 ) * $per_page;

$log_entries = ICM_DB::get_log_entries( [
    'per_page' => $per_page,
    'offset'   => $offset,
    'action'   => $action_filter,
] );
$total_items = ICM_DB::count_log_entries( $action_filter );
$total_pages = ceil( $total_items / $per_page );

// Counts per action
$count_remapped = ICM_DB::count_log_entries( 'remapped' );
$count_unmapped = ICM_DB::count_log_entries( 'unmapped' );
$count_skipped  = ICM_DB::count_log_entries( 'skipped' );
$count_all      = ICM_DB::count_log_entries();
?>

<div class="icm-log-header">
    <!-- Action filter -->
    <div class="icm-log-filters">
        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'icm-category-mapper', 'tab' => 'log' ], admin_url( 'admin.php' ) ) ); ?>"
           class="icm-filter-link <?php echo empty( $action_filter ) ? 'current' : ''; ?>">
            Alle <span class="count">(<?php echo $count_all; ?>)</span>
        </a>
        |
        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'icm-category-mapper', 'tab' => 'log', 'log_action' => 'remapped' ], admin_url( 'admin.php' ) ) ); ?>"
           class="icm-filter-link <?php echo $action_filter === 'remapped' ? 'current' : ''; ?>">
            Omdirigeret <span class="count">(<?php echo $count_remapped; ?>)</span>
        </a>
        |
        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'icm-category-mapper', 'tab' => 'log', 'log_action' => 'unmapped' ], admin_url( 'admin.php' ) ) ); ?>"
           class="icm-filter-link <?php echo $action_filter === 'unmapped' ? 'current' : ''; ?>">
            Ikke-mappede <span class="count">(<?php echo $count_unmapped; ?>)</span>
        </a>
        |
        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'icm-category-mapper', 'tab' => 'log', 'log_action' => 'skipped' ], admin_url( 'admin.php' ) ) ); ?>"
           class="icm-filter-link <?php echo $action_filter === 'skipped' ? 'current' : ''; ?>">
            Sprunget over <span class="count">(<?php echo $count_skipped; ?>)</span>
        </a>
    </div>

    <!-- Clear log -->
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
          class="icm-inline-form" onsubmit="return confirm(icm_admin.i18n.confirm_clear)">
        <?php wp_nonce_field( 'icm_clear_log' ); ?>
        <input type="hidden" name="action" value="icm_clear_log">
        <button type="submit" class="button">Ryd log</button>
    </form>
</div>

<!-- Log table -->
<table class="wp-list-table widefat fixed striped icm-table">
    <thead>
        <tr>
            <th class="icm-col-date">Dato</th>
            <th class="icm-col-product">Produkt</th>
            <th class="icm-col-icecat">Icecat Kategori</th>
            <th class="icm-col-target">Byens IT Kategori</th>
            <th class="icm-col-action">Handling</th>
        </tr>
    </thead>
    <tbody>
        <?php if ( empty( $log_entries ) ) : ?>
            <tr>
                <td colspan="5" class="icm-no-data">Ingen log-poster fundet.</td>
            </tr>
        <?php else : ?>
            <?php foreach ( $log_entries as $entry ) : ?>
                <tr>
                    <td class="icm-col-date">
                        <?php echo esc_html( wp_date( 'd. M Y H:i', strtotime( $entry['created_at'] ) ) ); ?>
                    </td>
                    <td class="icm-col-product">
                        <a href="<?php echo esc_url( get_edit_post_link( $entry['product_id'] ) ); ?>">
                            <?php echo esc_html( $entry['product_title'] ?: '#' . $entry['product_id'] ); ?>
                        </a>
                    </td>
                    <td class="icm-col-icecat">
                        <?php echo esc_html( $entry['icecat_cat_name'] ); ?>
                        <?php if ( $entry['icecat_cat_id'] > 0 ) : ?>
                            <code class="icm-small-id">#<?php echo esc_html( $entry['icecat_cat_id'] ); ?></code>
                        <?php endif; ?>
                    </td>
                    <td class="icm-col-target">
                        <?php if ( ! empty( $entry['target_slug'] ) ) : ?>
                            <span class="icm-target-badge"><?php echo esc_html( $entry['target_slug'] ); ?></span>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="icm-col-action">
                        <?php
                        $action_labels = [
                            'remapped' => '<span class="icm-action-badge icm-action-remapped">Omdirigeret</span>',
                            'unmapped' => '<span class="icm-action-badge icm-action-unmapped">Ikke-mappet</span>',
                            'skipped'  => '<span class="icm-action-badge icm-action-skipped">Sprunget over</span>',
                        ];
                        echo $action_labels[ $entry['action'] ] ?? esc_html( $entry['action'] );
                        ?>
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
