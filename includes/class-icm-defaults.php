<?php
/**
 * Default Icecat category reference library.
 *
 * This data is SEEDED into the mappings table on activation with EMPTY targets —
 * the admin must configure each target via the admin UI to match their own
 * WooCommerce category structure.
 *
 * Icecat category IDs are sourced from Icecat's public taxonomy at
 * https://data.icecat.biz/export/freexml/refs/CategoriesList.xml.gz
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICM_Defaults {

    /**
     * A library of common/popular Icecat categories used for seeding the mappings table.
     * Each entry has an Icecat ID and bilingual names (English + Danish reference names;
     * the *_da column holds the name in whatever language is configured under Settings).
     * The admin configures the target WooCommerce category via the admin UI.
     *
     * @return array[]
     */
    public static function get_icecat_category_library(): array {
        return [
            // ── Computers & Laptops ──
            [ 'icecat_cat_id' => 12,    'icecat_cat_name' => 'Desktops',                 'icecat_cat_name_da' => 'Stationære computere' ],
            [ 'icecat_cat_id' => 151,   'icecat_cat_name' => 'Notebooks',                'icecat_cat_name_da' => 'Bærbare computere' ],
            [ 'icecat_cat_id' => 3,     'icecat_cat_name' => 'Workstations',             'icecat_cat_name_da' => 'Workstations' ],
            [ 'icecat_cat_id' => 2562,  'icecat_cat_name' => 'Gaming PCs',               'icecat_cat_name_da' => 'Gaming-computere' ],
            [ 'icecat_cat_id' => 2563,  'icecat_cat_name' => 'Gaming Laptops',           'icecat_cat_name_da' => 'Gaming-bærbare' ],
            [ 'icecat_cat_id' => 690,   'icecat_cat_name' => 'Tablets',                  'icecat_cat_name_da' => 'Tablets' ],
            [ 'icecat_cat_id' => 1475,  'icecat_cat_name' => 'Chromebooks',              'icecat_cat_name_da' => 'Chromebooks' ],

            // ── Input Devices ──
            [ 'icecat_cat_id' => 194,   'icecat_cat_name' => 'Keyboards',                'icecat_cat_name_da' => 'Tastaturer' ],
            [ 'icecat_cat_id' => 2558,  'icecat_cat_name' => 'Gaming Keyboards',         'icecat_cat_name_da' => 'Gaming-tastaturer' ],
            [ 'icecat_cat_id' => 4742,  'icecat_cat_name' => 'Keyboard Accessories',     'icecat_cat_name_da' => 'Tastatur-tilbehør' ],
            [ 'icecat_cat_id' => 193,   'icecat_cat_name' => 'Mice',                     'icecat_cat_name_da' => 'Computermus' ],
            [ 'icecat_cat_id' => 2559,  'icecat_cat_name' => 'Gaming Mice',              'icecat_cat_name_da' => 'Gaming-mus' ],
            [ 'icecat_cat_id' => 900,   'icecat_cat_name' => 'Mouse Pads',               'icecat_cat_name_da' => 'Musemåtter' ],

            // ── Audio ──
            [ 'icecat_cat_id' => 123,   'icecat_cat_name' => 'Headphones',               'icecat_cat_name_da' => 'Hovedtelefoner' ],
            [ 'icecat_cat_id' => 124,   'icecat_cat_name' => 'Headsets',                 'icecat_cat_name_da' => 'Headsets' ],
            // EANrunner/Icecat often delivers a single COMBINED name — added so name matching works
            // out of the box. The ID is an internal placeholder ID (only used for Strategy 1/meta);
            // matching happens on the name. Verify the real Icecat ID if you rely on the meta-ID path.
            [ 'icecat_cat_id' => 990124, 'icecat_cat_name' => 'Headphones & Headsets',    'icecat_cat_name_da' => 'Hovedtelefoner & Headsets' ],
            [ 'icecat_cat_id' => 2560,  'icecat_cat_name' => 'Gaming Headsets',          'icecat_cat_name_da' => 'Gaming-headsets' ],
            [ 'icecat_cat_id' => 788,   'icecat_cat_name' => 'Speakers',                 'icecat_cat_name_da' => 'Højtalere' ],
            [ 'icecat_cat_id' => 981,   'icecat_cat_name' => 'Microphones',              'icecat_cat_name_da' => 'Mikrofoner' ],

            // ── Displays ──
            [ 'icecat_cat_id' => 895,   'icecat_cat_name' => 'Computer Monitors',        'icecat_cat_name_da' => 'Computerskærme' ],
            [ 'icecat_cat_id' => 2561,  'icecat_cat_name' => 'Gaming Monitors',          'icecat_cat_name_da' => 'Gaming-skærme' ],
            [ 'icecat_cat_id' => 948,   'icecat_cat_name' => 'LED Displays',             'icecat_cat_name_da' => 'LED-skærme' ],
            [ 'icecat_cat_id' => 1504,  'icecat_cat_name' => 'Televisions',              'icecat_cat_name_da' => 'Fjernsyn' ],
            [ 'icecat_cat_id' => 1469,  'icecat_cat_name' => 'Projectors',               'icecat_cat_name_da' => 'Projektorer' ],

            // ── Components ──
            [ 'icecat_cat_id' => 671,   'icecat_cat_name' => 'Processors',               'icecat_cat_name_da' => 'Processorer' ],
            [ 'icecat_cat_id' => 672,   'icecat_cat_name' => 'Graphics Cards',           'icecat_cat_name_da' => 'Grafikkort' ],
            [ 'icecat_cat_id' => 673,   'icecat_cat_name' => 'Motherboards',             'icecat_cat_name_da' => 'Bundkort' ],
            [ 'icecat_cat_id' => 674,   'icecat_cat_name' => 'Memory',                   'icecat_cat_name_da' => 'RAM' ],
            [ 'icecat_cat_id' => 675,   'icecat_cat_name' => 'Power Supplies',           'icecat_cat_name_da' => 'Strømforsyninger' ],
            [ 'icecat_cat_id' => 676,   'icecat_cat_name' => 'PC Cases',                 'icecat_cat_name_da' => 'Computer-kabinetter' ],
            [ 'icecat_cat_id' => 1004,  'icecat_cat_name' => 'CPU Coolers',              'icecat_cat_name_da' => 'Processor-kølere' ],
            [ 'icecat_cat_id' => 1006,  'icecat_cat_name' => 'Case Fans',                'icecat_cat_name_da' => 'Kabinetblæsere' ],

            // ── Storage ──
            [ 'icecat_cat_id' => 689,   'icecat_cat_name' => 'Internal Hard Drives',     'icecat_cat_name_da' => 'Interne harddiske' ],
            [ 'icecat_cat_id' => 1021,  'icecat_cat_name' => 'Internal SSDs',            'icecat_cat_name_da' => 'Interne SSDer' ],
            [ 'icecat_cat_id' => 822,   'icecat_cat_name' => 'External Hard Drives',     'icecat_cat_name_da' => 'Eksterne harddiske' ],
            [ 'icecat_cat_id' => 1022,  'icecat_cat_name' => 'External SSDs',            'icecat_cat_name_da' => 'Eksterne SSDer' ],
            [ 'icecat_cat_id' => 801,   'icecat_cat_name' => 'USB Flash Drives',         'icecat_cat_name_da' => 'USB-nøgler' ],
            [ 'icecat_cat_id' => 1517,  'icecat_cat_name' => 'Memory Cards',             'icecat_cat_name_da' => 'Hukommelseskort' ],

            // ── Networking ──
            [ 'icecat_cat_id' => 220,   'icecat_cat_name' => 'Routers',                  'icecat_cat_name_da' => 'Routere' ],
            [ 'icecat_cat_id' => 221,   'icecat_cat_name' => 'Network Switches',         'icecat_cat_name_da' => 'Netværks-switches' ],
            [ 'icecat_cat_id' => 222,   'icecat_cat_name' => 'Wireless Access Points',   'icecat_cat_name_da' => 'Wireless Access Points' ],
            [ 'icecat_cat_id' => 1118,  'icecat_cat_name' => 'Network Adapters',         'icecat_cat_name_da' => 'Netværkskort' ],

            // ── Peripherals ──
            [ 'icecat_cat_id' => 685,   'icecat_cat_name' => 'Webcams',                  'icecat_cat_name_da' => 'Webcams' ],
            [ 'icecat_cat_id' => 652,   'icecat_cat_name' => 'USB Hubs',                 'icecat_cat_name_da' => 'USB-hubs' ],
            [ 'icecat_cat_id' => 943,   'icecat_cat_name' => 'Gaming Controllers',       'icecat_cat_name_da' => 'Spil-controllere' ],
            [ 'icecat_cat_id' => 110,   'icecat_cat_name' => 'Printers',                 'icecat_cat_name_da' => 'Printere' ],
            [ 'icecat_cat_id' => 1458,  'icecat_cat_name' => 'Multifunction Printers',   'icecat_cat_name_da' => 'Multifunktionsprintere' ],
            [ 'icecat_cat_id' => 112,   'icecat_cat_name' => 'Scanners',                 'icecat_cat_name_da' => 'Scannere' ],

            // ── Accessories ──
            [ 'icecat_cat_id' => 702,   'icecat_cat_name' => 'Computer Accessories',     'icecat_cat_name_da' => 'Computertilbehør' ],
            [ 'icecat_cat_id' => 826,   'icecat_cat_name' => 'Gaming Accessories',       'icecat_cat_name_da' => 'Gaming-tilbehør' ],
            [ 'icecat_cat_id' => 1453,  'icecat_cat_name' => 'Notebook Bags',            'icecat_cat_name_da' => 'Computertasker' ],
            [ 'icecat_cat_id' => 2001,  'icecat_cat_name' => 'Cables',                   'icecat_cat_name_da' => 'Kabler' ],
            [ 'icecat_cat_id' => 2002,  'icecat_cat_name' => 'Adapters',                 'icecat_cat_name_da' => 'Adaptere' ],
            [ 'icecat_cat_id' => 1012,  'icecat_cat_name' => 'Surge Protectors',         'icecat_cat_name_da' => 'Strømskinner' ],

            // ── Mobile ──
            [ 'icecat_cat_id' => 1452,  'icecat_cat_name' => 'Smartphones',              'icecat_cat_name_da' => 'Smartphones' ],
            [ 'icecat_cat_id' => 1464,  'icecat_cat_name' => 'Mobile Phone Cases',       'icecat_cat_name_da' => 'Mobilcovers' ],
            [ 'icecat_cat_id' => 2055,  'icecat_cat_name' => 'Power Banks',              'icecat_cat_name_da' => 'Power banks' ],
            [ 'icecat_cat_id' => 1473,  'icecat_cat_name' => 'Smartwatches',             'icecat_cat_name_da' => 'Smartwatches' ],
        ];
    }

    /**
     * Alias for backwards compatibility (older versions used get_default_mappings).
     */
    public static function get_default_mappings(): array {
        return self::get_icecat_category_library();
    }

    /**
     * Get the list of category slugs that should never be remapped.
     * Combines user-configured protected slugs + all mapping targets.
     *
     * @return string[]
     */
    public static function get_protected_slugs(): array {
        // User-configured protected slugs from settings
        $user_slugs_raw = get_option( 'icm_protected_slugs', '' );
        $user_slugs     = array_filter( array_map( 'trim', explode( ',', (string) $user_slugs_raw ) ) );

        // All mapping target slugs (auto-protected, since they're known-good destinations)
        global $wpdb;
        $table = ICM_DB::mappings_table();
        $target_slugs = $wpdb->get_col(
            "SELECT DISTINCT woo_term_slug FROM {$table} WHERE woo_term_slug != ''"
        );

        // The WooCommerce default category ("Uncategorized") is ALWAYS protected: otherwise
        // products that ONLY have the default category would get it stripped with
        // fallback='remove' and end up without any product_cat (disappearing from archives/feeds).
        $default_slugs  = [];
        $default_cat_id = (int) get_option( 'default_product_cat', 0 );
        if ( $default_cat_id ) {
            $default_term = get_term( $default_cat_id, 'product_cat' );
            if ( $default_term && ! is_wp_error( $default_term ) ) {
                $default_slugs[] = $default_term->slug;
            }
        }

        return array_values( array_unique( array_merge( $user_slugs, (array) $target_slugs, $default_slugs ) ) );
    }
}
