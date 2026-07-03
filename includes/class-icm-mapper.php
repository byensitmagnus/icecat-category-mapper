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
            // Produkter helt uden kategori (fx stregkode-titel-kladder i import-batchen) ville
            // ellers forsvinde fra al rapportering — registrér dem så de kan aktioneres i "Ikke-mappede".
            if ( ! is_wp_error( $terms ) ) {
                ICM_DB::record_unmapped_by_name( '(ingen kategori)' );
            }
            return $result;
        }

        $product_title   = get_the_title( $product_id );
        $protected_slugs = self::get_protected_slugs();

        // Tæl ikke-beskyttede (= kilde-)termer. Strategi 1 (produkt-meta-Icecat-ID) er kun entydig
        // ved præcis én kilde-term; ved flere falder vi tilbage på navne-match pr. term.
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
            } elseif ( $mapping ) {
                // Mapping exists but target is empty — admin hasn't configured this yet
                self::handle_unmapped( $product_id, $product_title, $term, (int) $mapping['icecat_cat_id'] );
                $result['unmapped'][] = $term->name;
            } else {
                // No mapping exists at all
                self::handle_unmapped( $product_id, $product_title, $term, 0 );
                $result['unmapped'][] = $term->name;
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
        // Normalisér term-navnet FØR matching: Icecat/EANrunner-termer indeholder literal
        // HTML-entities (fx "Headphones &amp; Headsets") som ellers ALDRIG matcher seed/mappings
        // ("Headphones & Headsets") — hverken eksakt eller fuzzy. Uden dette fejler hele formålet
        // stille for alle "&"-kategorier (headsets er en kernekategori).
        $name = html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $name = trim( preg_replace( '/\s+/', ' ', $name ) );

        // Strategi 1 (meta-Icecat-ID) er kun ENTYDIG når produktet har præcis én kilde-term.
        // Med flere termer ville samme produkt-meta-ID matche dem ALLE til samme mål → kollaps.
        $icecat_id = $use_meta_strategy ? (int) self::get_icecat_id_from_meta( $product_id ) : 0;

        // Cache-nøglen SKAL inkludere meta-ID'et. Ellers (nøgle kun på term_id) genbruges
        // produkt #1's ID-baserede mapping forkert for ALLE efterfølgende produkter med samme
        // term i en bulk-import — stille fejl-mapping på den primære/mest præcise vej.
        $cache_key = 'term_' . $term->term_id . '_ic_' . $icecat_id;
        if ( array_key_exists( $cache_key, self::$lookup_cache ) ) {
            return self::$lookup_cache[ $cache_key ];
        }

        $mapping = null;

        // Strategi 1: Icecat-ID i post-meta
        if ( $icecat_id ) {
            $mapping = ICM_DB::get_mapping_by_icecat_id( $icecat_id );
        }

        // Strategi 2: Eksakt navne-match (decoded navn)
        if ( ! $mapping ) {
            $mapping = ICM_DB::get_mapping_by_icecat_name( $name );
        }

        // Strategi 3: Fuzzy navne-match (decoded navn)
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
    private static function handle_unmapped( int $product_id, string $product_title, WP_Term $term, int $icecat_id ): void {
        // Decode term-navnet så unmapped-tabellen + loggen ikke forurenes med HTML-entities
        // (én logisk kategori = én række: "Headphones & Headsets", ikke "...&amp; Headsets").
        $term_name = trim( preg_replace( '/\s+/', ' ', html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );

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
                    wp_set_object_terms( $product_id, [ $fallback_term->term_id ], 'product_cat', true );
                }
            }
        } elseif ( $fallback === 'remove' ) {
            wp_remove_object_terms( $product_id, $term->term_id, 'product_cat' );
        }
        // 'keep' = do nothing, leave the Icecat term as-is
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
