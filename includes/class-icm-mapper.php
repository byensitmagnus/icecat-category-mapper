<?php
/**
 * Core mapping engine.
 * Looks up Icecat categories and remaps products to the admin-configured WooCommerce categories.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICM_Mapper {

    /**
     * Static cache of protected slugs for the current request.
     *
     * @var string[]|null
     */
    private static ?array $protected_slugs_cache = null;

    /**
     * Static cache of mapping lookups to avoid repeated DB queries in bulk operations.
     *
     * @var array<string, array|null>
     */
    private static array $lookup_cache = [];

    /**
     * Remap a product's categories from Icecat to the admin's configured targets.
     *
     * @param int $product_id WooCommerce product post ID.
     * @return array Summary: [ 'remapped' => [...], 'unmapped' => [...], 'skipped' => [...] ]
     */
    public static function remap_product_categories( int $product_id ): array {
        $result = [
            'remapped' => [],
            'unmapped' => [],
            'skipped'  => [],
        ];

        // Get current product_cat terms
        $terms = wp_get_object_terms( $product_id, 'product_cat' );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            // Products without any category (e.g. barcode-title drafts in an import batch) would
            // otherwise vanish from all reporting — record them so they can be actioned under "Unmapped".
            if ( ! is_wp_error( $terms ) ) {
                ICM_DB::record_unmapped_by_name( '(no category)' ); // Stable sentinel — translated at render time only
            }
            return $result;
        }

        $product_title   = get_the_title( $product_id );
        $protected_slugs = self::get_protected_slugs();

        // Count non-protected (= source) terms. Strategy 1 (product meta Icecat ID) is only
        // unambiguous with exactly one source term; with more we fall back to per-term name matching.
        $non_protected = 0;
        foreach ( $terms as $t ) {
            if ( ! in_array( $t->slug, $protected_slugs, true ) ) { $non_protected++; }
        }
        $use_meta_strategy = ( $non_protected <= 1 );

        $terms_to_remove = [];
        $terms_to_add    = [];

        foreach ( $terms as $term ) {
            // Skip protected categories (user-configured + auto-derived from mapping targets)
            if ( in_array( $term->slug, $protected_slugs, true ) ) {
                // Heal a missing parent chain on already-remapped products: their target term
                // (e.g. Headsets) is protected and skipped, so without this they would never
                // receive their ancestors (e.g. "Gaming tilbehoer") on a recheck.
                foreach ( get_ancestors( $term->term_id, 'product_cat' ) as $ancestor_id ) {
                    $terms_to_add[] = (int) $ancestor_id;
                }
                $result['skipped'][] = $term->slug;
                continue;
            }

            // Try to find a mapping with a configured target
            $mapping = self::find_mapping_for_term( $term, $product_id, $use_meta_strategy );

            if ( $mapping && ! empty( $mapping['woo_term_slug'] ) ) {
                // Found a configured mapping — remap
                $target_slug = $mapping['woo_term_slug'];
                $target_term = get_term_by( 'slug', $target_slug, 'product_cat' );

                if ( $target_term ) {
                    $terms_to_remove[] = $term->term_id;
                    $terms_to_add[]    = $target_term->term_id;

                    // Also assign the FULL parent chain (e.g. Headsets -> also "Gaming tilbehoer").
                    // Mirrors how the shop's existing products are categorized (child + parent);
                    // without this the product is missing from parent-category widgets/counts.
                    foreach ( get_ancestors( $target_term->term_id, 'product_cat' ) as $ancestor_id ) {
                        $terms_to_add[] = (int) $ancestor_id;
                    }

                    ICM_Logger::log_remap(
                        $product_id,
                        $product_title,
                        (int) $mapping['icecat_cat_id'],
                        $term->name,
                        $target_term->term_id,
                        $target_slug
                    );

                    $result['remapped'][] = [
                        'from' => $term->name,
                        'to'   => $target_slug,
                    ];
                } else {
                    // Target slug doesn't exist as a WooCommerce term — log and skip
                    ICM_Logger::log_unmapped(
                        $product_id,
                        $product_title,
                        (int) $mapping['icecat_cat_id'],
                        $term->name
                    );
                    $result['unmapped'][] = $term->name;
                }
            } else {
                // Mapping missing, or exists with an empty target (admin hasn't configured it yet).
                $rule_slug = self::handle_unmapped( $product_id, $product_title, $term, (int) ( $mapping['icecat_cat_id'] ?? 0 ) );
                if ( $rule_slug ) {
                    $result['remapped'][] = [ 'from' => $term->name, 'to' => $rule_slug ];
                } else {
                    $result['unmapped'][] = $term->name;
                }
            }
        }

        // Apply changes
        if ( ! empty( $terms_to_remove ) || ! empty( $terms_to_add ) ) {
            self::apply_term_changes( $product_id, $terms_to_remove, $terms_to_add );
        }

        return $result;
    }

    /**
     * Find a mapping for a given WooCommerce term using tiered matching:
     *   1. Post meta Icecat category ID
     *   2. Exact name match (English or Danish)
     *   3. Fuzzy name match (LIKE)
     */
    private static function find_mapping_for_term( WP_Term $term, int $product_id, bool $use_meta_strategy = true ): ?array {
        // Normalize the term name BEFORE matching: Icecat/EANrunner terms contain literal
        // HTML entities (e.g. "Headphones &amp; Headsets") that would otherwise NEVER match the
        // seed/mappings ("Headphones & Headsets") — neither exact nor fuzzy. Without this, the whole
        // point fails silently for all "&" categories (headsets are a core category).
        $name = html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $name = trim( preg_replace( '/\s+/', ' ', $name ) );

        // Strategy 1 (meta Icecat ID) is only UNAMBIGUOUS when the product has exactly one source
        // term. With multiple terms, the same product meta ID would match them ALL to the same target → collapse.
        $icecat_id = $use_meta_strategy ? (int) self::get_icecat_id_from_meta( $product_id ) : 0;

        // The cache key MUST include the meta ID. Otherwise (key on term_id only), product #1's
        // ID-based mapping would incorrectly be reused for ALL subsequent products with the same
        // term in a bulk import — silent mis-mapping on the primary/most precise path.
        $cache_key = 'term_' . $term->term_id . '_ic_' . $icecat_id;
        if ( array_key_exists( $cache_key, self::$lookup_cache ) ) {
            return self::$lookup_cache[ $cache_key ];
        }

        $mapping = null;

        // Strategy 1: Icecat ID from post meta
        if ( $icecat_id ) {
            $mapping = ICM_DB::get_mapping_by_icecat_id( $icecat_id );
        }

        // Strategy 2: Exact name match (decoded name)
        if ( ! $mapping ) {
            $mapping = ICM_DB::get_mapping_by_icecat_name( $name );
        }

        // Strategy 3: Fuzzy name match (decoded name)
        if ( ! $mapping ) {
            $mapping = ICM_DB::get_mapping_by_fuzzy_name( $name );
        }

        self::$lookup_cache[ $cache_key ] = $mapping;

        return $mapping;
    }

    /**
     * Try to get an Icecat category ID from product post meta.
     * EANrunner or other import plugins might store this.
     */
    private static function get_icecat_id_from_meta( int $product_id ): ?int {
        $meta_keys = apply_filters( 'icm_icecat_meta_keys', [
            '_icecat_category_id',
            '_icecat_catid',
            'icecat_category_id',
            '_eanrunner_icecat_cat',
        ] );

        foreach ( $meta_keys as $key ) {
            $value = get_post_meta( $product_id, $key, true );
            if ( $value && is_numeric( $value ) ) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * Handle an unmapped category based on admin settings.
     */
    private static function handle_unmapped( int $product_id, string $product_title, WP_Term $term, int $icecat_id ): ?string {
        // Decode the term name so the unmapped table + log are not polluted with HTML entities
        // (one logical category = one row: "Headphones & Headsets", not "...&amp; Headsets").
        $term_name = trim( preg_replace( '/\s+/', ' ', html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );

        // Title rules first: catch-all Icecat categories ("Other", "Not Categorized",
        // "Computer Components") can never be mapped per category — only the product
        // title tells a mouse from a fan. Admin-configured regex → target slug.
        $rule_slug = self::match_title_rule( $product_title );
        if ( $rule_slug ) {
            $target = get_term_by( 'slug', $rule_slug, 'product_cat' );
            if ( $target ) {
                $add_ids = array_merge( [ (int) $target->term_id ], array_map( 'intval', get_ancestors( $target->term_id, 'product_cat' ) ) );
                self::apply_term_changes( $product_id, [ $term->term_id ], $add_ids );
                ICM_Logger::log_remap( $product_id, $product_title, $icecat_id, $term_name, (int) $target->term_id, $rule_slug, 'title_rule' );
                return $rule_slug;
            }
        }

        // Record the unmapped category
        if ( $icecat_id > 0 ) {
            ICM_DB::record_unmapped( $icecat_id, $term_name );
        } else {
            // Try to find the Icecat ID from post meta
            $meta_id = self::get_icecat_id_from_meta( $product_id );
            if ( $meta_id ) {
                ICM_DB::record_unmapped( $meta_id, $term_name );
                $icecat_id = $meta_id;
            } else {
                ICM_DB::record_unmapped_by_name( $term_name );
            }
        }

        // Log it
        ICM_Logger::log_unmapped( $product_id, $product_title, $icecat_id, $term_name );

        // Apply fallback behavior
        $fallback = get_option( 'icm_fallback_behavior', 'keep' );

        if ( $fallback === 'fallback' ) {
            $fallback_slug = get_option( 'icm_fallback_category', '' );
            if ( $fallback_slug ) {
                $fallback_term = get_term_by( 'slug', $fallback_slug, 'product_cat' );
                if ( $fallback_term ) {
                    wp_remove_object_terms( $product_id, $term->term_id, 'product_cat' );
                    // Include the parent chain — same pattern as the remap path.
                    $fallback_ids = array_merge(
                        [ (int) $fallback_term->term_id ],
                        array_map( 'intval', get_ancestors( $fallback_term->term_id, 'product_cat' ) )
                    );
                    wp_set_object_terms( $product_id, $fallback_ids, 'product_cat', true );
                }
            }
        } elseif ( $fallback === 'remove' ) {
            wp_remove_object_terms( $product_id, $term->term_id, 'product_cat' );
        } elseif ( $fallback === 'draft' ) {
            self::draft_product( $product_id, $product_title, $icecat_id, $term_name );
        }
        // 'keep' = do nothing, leave the Icecat term as-is
        return null;
    }

    /**
     * Fallback 'draft': hide the product until the admin maps its category and runs a recheck.
     * Direct DB write on purpose — wp_update_post() would fire save_post inside the import's
     * own save cycle (and re-enter every product hook), and WooCommerce's data store may
     * still be mid-save when set_object_terms fires.
     */
    private static function draft_product( int $product_id, string $product_title, int $icecat_id, string $term_name ): void {
        global $wpdb;
        if ( get_post_status( $product_id ) !== 'publish' ) {
            return;
        }
        $wpdb->update( $wpdb->posts, [ 'post_status' => 'draft' ], [ 'ID' => $product_id ], [ '%s' ], [ '%d' ] );
        clean_post_cache( $product_id );
        if ( function_exists( 'wc_delete_product_transients' ) ) {
            wc_delete_product_transients( $product_id );
        }
        ICM_Logger::log_remap( $product_id, $product_title, $icecat_id, $term_name, 0, '', 'drafted' );
    }

    /**
     * Match a product title against the admin's title rules (Settings → Title rules).
     * One rule per line: `woo-slug | regex` — first match wins, case-insensitive, Unicode.
     *
     * @return string|null Target slug or null.
     */
    public static function match_title_rule( string $title ): ?string {
        $title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        foreach ( self::parse_title_rules( (string) get_option( 'icm_title_rules', '' ) ) as $rule ) {
            if ( @preg_match( '/' . str_replace( '/', '\/', $rule[1] ) . '/iu', $title ) === 1 ) {
                return $rule[0];
            }
        }
        return null;
    }

    /**
     * Parse the raw textarea into [ [slug, regex], ... ]. Blank lines and `#` comments are ignored.
     *
     * @return array<int, array{0:string,1:string}>
     */
    public static function parse_title_rules( string $raw ): array {
        $rules = [];
        foreach ( preg_split( '/\R/', $raw ) as $line ) {
            $line = trim( $line );
            if ( $line === '' || $line[0] === '#' || strpos( $line, '|' ) === false ) {
                continue;
            }
            [ $slug, $regex ] = array_map( 'trim', explode( '|', $line, 2 ) );
            if ( $slug !== '' && $regex !== '' ) {
                $rules[] = [ sanitize_title( $slug ), $regex ];
            }
        }
        return $rules;
    }

    /**
     * Apply term changes: remove Icecat terms and add target terms.
     */
    private static function apply_term_changes( int $product_id, array $remove_ids, array $add_ids ): void {
        foreach ( $remove_ids as $term_id ) {
            wp_remove_object_terms( $product_id, $term_id, 'product_cat' );
        }

        if ( ! empty( $add_ids ) ) {
            $add_ids = array_unique( $add_ids );
            wp_set_object_terms( $product_id, $add_ids, 'product_cat', true );
        }
    }

    /**
     * Get cached protected slugs.
     *
     * @return string[]
     */
    private static function get_protected_slugs(): array {
        if ( self::$protected_slugs_cache === null ) {
            self::$protected_slugs_cache = ICM_Defaults::get_protected_slugs();
        }
        return self::$protected_slugs_cache;
    }

    /**
     * Clear the static lookup cache.
     */
    public static function clear_cache(): void {
        self::$lookup_cache          = [];
        self::$protected_slugs_cache = null;
    }
}
