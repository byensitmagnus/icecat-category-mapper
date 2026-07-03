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
$protected_slugs  = array_filter( array_map( 'trim', explode( ',', (string) $protected_raw ) ) );
$woo_categories   = ICM_Admin::get_woo_categories_dropdown();
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    <?php wp_nonce_field( 'icm_save_settings' ); ?>
    <input type="hidden" name="action" value="icm_save_settings">

    <!-- Icecat API -->
    <h2>Icecat API</h2>
    <table class="form-table">
        <tr>
            <th><label for="icm_icecat_username">Brugernavn</label></th>
            <td>
                <input type="text" id="icm_icecat_username" name="icm_icecat_username"
                       value="<?php echo esc_attr( $username ); ?>" class="regular-text"
                       autocomplete="off">
                <p class="description">Dit Open Icecat brugernavn.</p>
            </td>
        </tr>
        <tr>
            <th><label for="icm_icecat_password">Adgangskode</label></th>
            <td>
                <input type="password" id="icm_icecat_password" name="icm_icecat_password"
                       value="" class="regular-text" placeholder="<?php echo $username ? '(uaendret)' : ''; ?>"
                       autocomplete="new-password">
                <p class="description">Lad staa tomt for at beholde nuvaerende adgangskode.</p>
            </td>
        </tr>
        <tr>
            <th><label for="icm_icecat_lang_id">Sprog-ID</label></th>
            <td>
                <select id="icm_icecat_lang_id" name="icm_icecat_lang_id">
                    <option value="1" <?php selected( $lang_id, 1 ); ?>>Engelsk (1)</option>
                    <option value="8" <?php selected( $lang_id, 8 ); ?>>Dansk (8)</option>
                    <option value="2" <?php selected( $lang_id, 2 ); ?>>Tysk (2)</option>
                    <option value="3" <?php selected( $lang_id, 3 ); ?>>Fransk (3)</option>
                    <option value="4" <?php selected( $lang_id, 4 ); ?>>Spansk (4)</option>
                    <option value="5" <?php selected( $lang_id, 5 ); ?>>Hollandsk (5)</option>
                    <option value="6" <?php selected( $lang_id, 6 ); ?>>Italiensk (6)</option>
                    <option value="7" <?php selected( $lang_id, 7 ); ?>>Portugisisk (7)</option>
                    <option value="9" <?php selected( $lang_id, 9 ); ?>>Svensk (9)</option>
                    <option value="10" <?php selected( $lang_id, 10 ); ?>>Norsk (10)</option>
                    <option value="11" <?php selected( $lang_id, 11 ); ?>>Finsk (11)</option>
                </select>
                <p class="description">Icecat sprog-ID for kategorinavne der hentes fra API'et.</p>
            </td>
        </tr>
        <tr>
            <th></th>
            <td>
                <button type="button" id="icm-test-connection" class="button">
                    Test forbindelse
                </button>
                <span id="icm-test-result" class="icm-test-result"></span>
            </td>
        </tr>
    </table>

    <!-- Mapping behavior -->
    <h2>Mapping-adfaerd</h2>
    <table class="form-table">
        <tr>
            <th>Auto-remap</th>
            <td>
                <label>
                    <input type="checkbox" name="icm_auto_remap_enabled" value="1"
                        <?php checked( $auto_remap, 1 ); ?>>
                    Aktiver automatisk kategori-remapping ved produktimport
                </label>
                <p class="description">
                    Naar aktiveret, remappes Icecat-kategorier automatisk naar Byens IT-kategorier
                    ved oprettelse og opdatering af produkter.
                </p>
            </td>
        </tr>
        <tr>
            <th>Fallback for umappede</th>
            <td>
                <fieldset>
                    <label>
                        <input type="radio" name="icm_fallback_behavior" value="keep"
                            <?php checked( $fallback, 'keep' ); ?>>
                        <strong>Behold</strong> — Bevar Icecat-kategorien som den er
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="icm_fallback_behavior" value="fallback"
                            <?php checked( $fallback, 'fallback' ); ?>>
                        <strong>Fallback</strong> — Tildel en standard-kategori
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="icm_fallback_behavior" value="remove"
                            <?php checked( $fallback, 'remove' ); ?>>
                        <strong>Fjern</strong> — Fjern den umappede kategori helt
                    </label>
                </fieldset>
            </td>
        </tr>
        <tr id="icm-fallback-cat-row" style="<?php echo $fallback !== 'fallback' ? 'display:none;' : ''; ?>">
            <th><label for="icm_fallback_category">Fallback-kategori</label></th>
            <td>
                <select id="icm_fallback_category" name="icm_fallback_category" class="regular-text">
                    <option value="">— Vaelg kategori —</option>
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

    <!-- Protected categories -->
    <h2>Beskyttede kategorier</h2>
    <p class="description">
        Vaelg de WooCommerce-kategorier som aldrig skal remappes af pluginet.
        Targets du allerede har konfigureret i dine mappings er automatisk beskyttet.
        Brug dette til eksisterende kategorier du vil have i fred.
    </p>
    <table class="form-table">
        <tr>
            <th><label for="icm_protected_slugs">Kategorier</label></th>
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
                    Hold Ctrl/Cmd nede for at vaelge flere. Valgfrit — tomt felt = ingen ekstra beskyttede kategorier.
                </p>
            </td>
        </tr>
    </table>

    <!-- Maintenance -->
    <h2>Vedligeholdelse</h2>
    <table class="form-table">
        <tr>
            <th><label for="icm_log_retention_days">Log-opbevaring</label></th>
            <td>
                <input type="number" id="icm_log_retention_days" name="icm_log_retention_days"
                       value="<?php echo esc_attr( $retention_days ); ?>" class="small-text" min="1" max="365">
                <span>dage</span>
                <p class="description">Log-poster aeldre end dette slettes automatisk dagligt.</p>
            </td>
        </tr>
    </table>

    <?php submit_button( 'Gem indstillinger' ); ?>
</form>

<!-- Batch recheck (separate section) -->
<hr>
<h2>Genkontroller produkter</h2>
<p class="description">
    Korer alle eksisterende produkter igennem mapperen.
    Nyttigt efter tilfoejelse af nye mappings for at rette allerede importerede produkter.
</p>

<div class="icm-batch-recheck">
    <button type="button" id="icm-batch-recheck-btn" class="button button-primary">
        Genkontroller alle produkter
    </button>
    <div id="icm-batch-progress" class="icm-batch-progress" style="display:none;">
        <div class="icm-progress-bar">
            <div class="icm-progress-fill" id="icm-progress-fill"></div>
        </div>
        <p id="icm-batch-status"></p>
    </div>
</div>
