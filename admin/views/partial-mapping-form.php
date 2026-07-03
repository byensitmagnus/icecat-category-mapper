<?php
/**
 * Admin partial: Add/Edit mapping form.
 * Used in both the mappings tab and quick-map from unmapped tab.
 *
 * Variables available from parent:
 *   $editing        - mapping array if editing, null if adding
 *   $woo_categories - array of slug => name for dropdown
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_edit = ! empty( $editing );
?>

<div class="icm-form-card">
    <h3><?php echo $is_edit ? 'Rediger mapping' : 'Tilfoej ny mapping'; ?></h3>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'icm_save_mapping' ); ?>
        <input type="hidden" name="action" value="icm_save_mapping">

        <?php if ( $is_edit ) : ?>
            <input type="hidden" name="mapping_id" value="<?php echo esc_attr( $editing['id'] ); ?>">
        <?php endif; ?>

        <table class="form-table icm-form-table">
            <tr>
                <th><label for="icecat_cat_id">Icecat Kategori-ID</label></th>
                <td>
                    <input type="number" id="icecat_cat_id" name="icecat_cat_id"
                           value="<?php echo esc_attr( $is_edit ? $editing['icecat_cat_id'] : '' ); ?>"
                           class="regular-text" min="0" required>
                    <p class="description">Icecat's numeriske kategori-ID.</p>
                </td>
            </tr>
            <tr>
                <th><label for="icecat_cat_name">Icecat Kategori (engelsk)</label></th>
                <td>
                    <input type="text" id="icecat_cat_name" name="icecat_cat_name"
                           value="<?php echo esc_attr( $is_edit ? $editing['icecat_cat_name'] : '' ); ?>"
                           class="regular-text" required>
                    <button type="button" id="icm-search-icecat-btn" class="button button-small">
                        Soeg i Icecat
                    </button>
                    <div id="icm-icecat-search-results" class="icm-search-dropdown" style="display:none;"></div>
                </td>
            </tr>
            <tr>
                <th><label for="icecat_cat_name_da">Dansk navn</label></th>
                <td>
                    <input type="text" id="icecat_cat_name_da" name="icecat_cat_name_da"
                           value="<?php echo esc_attr( $is_edit ? $editing['icecat_cat_name_da'] : '' ); ?>"
                           class="regular-text">
                    <p class="description">Valgfrit. Bruges ogsaa til matching.</p>
                </td>
            </tr>
            <tr>
                <th><label for="woo_term_slug">Byens IT Kategori</label></th>
                <td>
                    <select id="woo_term_slug" name="woo_term_slug" class="regular-text" required>
                        <option value="">— Vaelg kategori —</option>
                        <?php foreach ( $woo_categories as $slug => $name ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>"
                                <?php selected( $is_edit ? $editing['woo_term_slug'] : '', $slug ); ?>>
                                <?php echo esc_html( $name ); ?> (<?php echo esc_html( $slug ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php echo $is_edit ? 'Opdater mapping' : 'Tilfoej mapping'; ?>
            </button>
            <?php if ( $is_edit ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=icm-category-mapper&tab=mappings' ) ); ?>"
                   class="button">Annuller</a>
            <?php else : ?>
                <button type="button" id="icm-cancel-add" class="button">Annuller</button>
            <?php endif; ?>
        </p>
    </form>
</div>
