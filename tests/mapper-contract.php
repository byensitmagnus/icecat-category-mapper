<?php
/**
 * Runnable contract for the mapper. Run on a real WordPress site (staging!) with:
 *
 *   wp eval-file wp-content/plugins/icecat-category-mapper/tests/mapper-contract.php
 *
 * Creates temporary categories + products, asserts the mapping decisions, and deletes
 * everything it created. Exits non-zero on the first failing assertion.
 *
 * What it proves (the four defects fixed in 1.4.0):
 *   1. "Other" no longer fuzzy-matches "Motherboards" (substring hit that filed mice under motherboards).
 *   2. A whole-word fuzzy hit still works when every matching mapping agrees on the target.
 *   3. A title rule rescues a product in a catch-all category and assigns the full parent chain.
 *   4. Ancestors of mapping targets are protected (never re-evaluated / logged as unmapped).
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'ICM_Mapper' ) ) {
    fwrite( STDERR, "Run via wp eval-file with the plugin active.\n" );
    exit( 1 );
}

$created_terms = [];
$created_posts = [];
$failures      = 0;

$term = static function ( string $name, string $slug, int $parent = 0 ) use ( &$created_terms ): int {
    $existing = get_term_by( 'slug', $slug, 'product_cat' );
    if ( $existing ) {
        return (int) $existing->term_id;
    }
    $r = wp_insert_term( $name, 'product_cat', [ 'slug' => $slug, 'parent' => $parent ] );
    if ( is_wp_error( $r ) ) {
        throw new RuntimeException( $r->get_error_message() );
    }
    $created_terms[] = (int) $r['term_id'];
    return (int) $r['term_id'];
};
$product = static function ( string $title, array $term_ids ) use ( &$created_posts ): int {
    $id = wp_insert_post( [ 'post_title' => $title, 'post_type' => 'product', 'post_status' => 'draft' ] );
    $created_posts[] = $id;
    ICM_Hooks::set_remapping( true ); // assign source terms WITHOUT triggering the live hook
    wp_set_object_terms( $id, $term_ids, 'product_cat' );
    ICM_Hooks::set_remapping( false );
    return $id;
};
$slugs_of = static function ( int $post_id ): array {
    $s = wp_list_pluck( wp_get_object_terms( $post_id, 'product_cat' ), 'slug' );
    sort( $s );
    return $s;
};
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$failures ): void {
    $failures += $ok ? 0 : 1;
    echo ( $ok ? 'PASS' : 'FAIL' ) . '  ' . $label . ( $detail !== '' ? '  [' . $detail . ']' : '' ) . "\n";
};

// ── Fixture: an isolated mini-shop so the test never depends on the site's real mappings ──
$parent_id = $term( 'ICM Test Parent', 'icm-test-parent' );
$mb_id     = $term( 'ICM Test Motherboards', 'icm-test-mb', $parent_id );
$mice_id   = $term( 'ICM Test Mice', 'icm-test-mice', $parent_id );
$mon_id    = $term( 'ICM Test Monitors', 'icm-test-mon', $parent_id );
$other_id  = $term( 'ICM Zother', 'icm-other' );          // substring of 'ICM Zotherboards' — the real "Other"/"Motherboards" trap
$mons_src  = $term( 'Zonitors', 'icm-monitors' );        // whole word inside 'ICM Computer Zonitors' + 'ICM Gaming Zonitors'

$mapping_ids = [];
foreach ( [
    [ 9990001, 'ICM Zotherboards',          'icm-test-mb' ],
    [ 9990002, 'ICM Computer Zonitors',     'icm-test-mon' ],
    [ 9990003, 'ICM Gaming Zonitors',       'icm-test-mon' ],
    [ 9990004, 'ICM Case Fans',             '' ],
] as $m ) {
    $mapping_ids[] = ICM_DB::insert_mapping( [ 'icecat_cat_id' => $m[0], 'icecat_cat_name' => $m[1], 'woo_term_slug' => $m[2], 'woo_term_id' => 0 ] );
}
$saved_rules = get_option( 'icm_title_rules', '' );
update_option( 'icm_title_rules', "# test rules\nicm-test-mice | \\bmouse\\b|\\boptisk\\b.*\\b(kablet|tr(å|aa)dl(ø|oe)s)\\b\n" );
$saved_fallback = get_option( 'icm_fallback_behavior', 'keep' );
update_option( 'icm_fallback_behavior', 'keep' );
ICM_Mapper::clear_cache();

try {
    // 1. "ICM Zother" is a substring of "ICM Zotherboards" → must NOT map. No title rule matches → stays unmapped.
    $fan = $product( 'Arctic P12 PWM 120 mm case fan', [ $other_id ] );
    ICM_Mapper::remap_product_categories( $fan );
    $check( '"Other" no longer fuzzy-matches "Motherboards"', $slugs_of( $fan ) === [ 'icm-other' ], implode( ',', $slugs_of( $fan ) ) );

    // 2. "Zonitors" whole-word matches two mappings that agree on the target → maps.
    $mon = $product( 'LG 24U41YA-B 24 IPS 1920 x 1080', [ $mons_src ] );
    ICM_Mapper::remap_product_categories( $mon );
    $check( 'Whole-word fuzzy hit with one agreed target maps', $slugs_of( $mon ) === [ 'icm-test-mon', 'icm-test-parent' ], implode( ',', $slugs_of( $mon ) ) );

    // 3. Title rule rescues a mouse filed under "Other" and assigns the parent chain.
    $mouse = $product( 'Razer Viper V4 Pro Optisk Trådløs Kablet Sort', [ $other_id ] );
    $res   = ICM_Mapper::remap_product_categories( $mouse );
    $check( 'Title rule: Other + mouse title → mice + parent', $slugs_of( $mouse ) === [ 'icm-test-mice', 'icm-test-parent' ], implode( ',', $slugs_of( $mouse ) ) );
    $check( 'Title rule reported as remapped, not unmapped', ! empty( $res['remapped'] ) && empty( $res['unmapped'] ) );

    // 4. Parent of a target is protected → a product that only carries it is skipped, not "unmapped".
    ICM_Mapper::clear_cache();
    $bare = $product( 'Bare parent product', [ $parent_id ] );
    $res  = ICM_Mapper::remap_product_categories( $bare );
    $check( 'Ancestor of a target is protected (skipped, not unmapped)', $res['skipped'] === [ 'icm-test-parent' ] && empty( $res['unmapped'] ), wp_json_encode( $res ) );

    // 5. Ambiguous whole-word hit (two different targets) → unmapped, never a guess.
    $mapping_ids[] = ICM_DB::insert_mapping( [ 'icecat_cat_id' => 9990005, 'icecat_cat_name' => 'ICM Zonitors Pro', 'woo_term_slug' => 'icm-test-mb', 'woo_term_id' => 0 ] );
    ICM_Mapper::clear_cache();
    $amb = $product( 'Something with no title signal', [ $mons_src ] );
    $res = ICM_Mapper::remap_product_categories( $amb );
    $check( 'Ambiguous fuzzy hit (two targets) stays unmapped', $slugs_of( $amb ) === [ 'icm-monitors' ] && ! empty( $res['unmapped'] ), implode( ',', $slugs_of( $amb ) ) );

    // 6. Fallback 'draft': unmapped + no title rule → product hidden as draft, category kept, logged as 'drafted'.
    update_option( 'icm_fallback_behavior', 'draft' );
    $hidden = $product( 'Arctic P14 PWM PST 140 mm case fan', [ $other_id ] );
    wp_update_post( [ 'ID' => $hidden, 'post_status' => 'publish' ] );
    ICM_Mapper::remap_product_categories( $hidden );
    $logged = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . ICM_DB::log_table() . " WHERE product_id = %d AND action = 'drafted'", $hidden ) );
    $check( 'Fallback draft: unmapped product becomes draft + logged', get_post_status( $hidden ) === 'draft' && $logged === 1 && $slugs_of( $hidden ) === [ 'icm-other' ], get_post_status( $hidden ) . ' logged=' . $logged );
} finally {
    // ── Cleanup: everything this script created, nothing else ──
    update_option( 'icm_title_rules', $saved_rules );
    update_option( 'icm_fallback_behavior', $saved_fallback );
    foreach ( $mapping_ids as $mid ) {
        if ( $mid ) {
            ICM_DB::delete_mapping( (int) $mid );
        }
    }
    global $wpdb;
    $wpdb->query( "DELETE FROM " . ICM_DB::unmapped_table() . " WHERE icecat_cat_name LIKE 'ICM %' OR icecat_cat_name = 'Zonitors'" );
    foreach ( $created_posts as $pid ) {
        $wpdb->delete( ICM_DB::log_table(), [ 'product_id' => $pid ] );
        wp_delete_post( $pid, true );
    }
    foreach ( array_reverse( $created_terms ) as $tid ) {
        wp_delete_term( $tid, 'product_cat' );
    }
    ICM_Mapper::clear_cache();
}

echo $failures === 0 ? "ALL PASS\n" : "$failures FAILED\n";
exit( $failures === 0 ? 0 : 1 );
