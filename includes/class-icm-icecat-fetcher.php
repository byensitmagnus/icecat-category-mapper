<?php
/**
 * Icecat category fetcher.
 * Downloads and parses CategoriesList.xml.gz from Open Icecat API.
 * Uses XMLReader for memory-efficient streaming parse of the large XML file.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICM_Icecat_Fetcher {

    /**
     * Icecat categories list URL.
     */
    const CATEGORIES_URL = 'https://data.icecat.biz/export/freexml/refs/CategoriesList.xml.gz';

    /**
     * Cache duration in seconds (7 days).
     */
    const CACHE_DURATION = 604800;

    /**
     * Fetch categories from Icecat API.
     *
     * @return array|WP_Error Array of categories or WP_Error on failure.
     */
    public static function fetch_categories() {
        $username = get_option( 'icm_icecat_username', '' );
        $password = get_option( 'icm_icecat_password', '' );

        if ( empty( $username ) || empty( $password ) ) {
            return new WP_Error(
                'icm_no_credentials',
                __( 'Icecat username and password are required. Enter them under Settings.', 'icecat-category-mapper' )
            );
        }

        // Download the gzipped XML
        $response = wp_remote_get( self::CATEGORIES_URL, [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'icm_fetch_failed',
                __( 'Could not fetch Icecat categories:', 'icecat-category-mapper' ) . ' ' . $response->get_error_message()
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            return new WP_Error(
                'icm_fetch_http_error',
                sprintf( /* translators: %d: HTTP status code. */ __( 'The Icecat API returned HTTP %d. Check your credentials.', 'icecat-category-mapper' ), $status_code )
            );
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return new WP_Error( 'icm_empty_response', __( 'Empty response from the Icecat API.', 'icecat-category-mapper' ) );
        }

        // Decompress gzip
        $xml_string = @gzdecode( $body );
        if ( $xml_string === false ) {
            // Maybe it wasn't gzipped (some configurations return raw XML)
            $xml_string = $body;
        }

        // Parse the XML
        $categories = self::parse_categories_xml( $xml_string );

        if ( is_wp_error( $categories ) ) {
            return $categories;
        }

        // Cache the result. The 3rd arg is $autoload (bool) — pass an explicit false, otherwise
        // the (potentially MB-sized) Icecat taxonomy would be autoloaded on EVERY request if the
        // option is ever deleted and recreated here ('' + 'no' was a no-op/ignored 4th arg).
        update_option( 'icm_categories_cache', maybe_serialize( $categories ), false );
        update_option( 'icm_categories_cache_time', time() );

        return $categories;
    }

    /**
     * Parse the CategoriesList XML using streaming XMLReader.
     *
     * @param string $xml_string Raw XML content.
     * @return array|WP_Error Array of parsed categories.
     */
    private static function parse_categories_xml( string $xml_string ) {
        $lang_id    = (int) get_option( 'icm_icecat_lang_id', 1 ); // Default: English (matches the activation default)
        $categories = [];

        // Suppress XML errors and handle them manually
        $use_errors = libxml_use_internal_errors( true );

        $reader = new XMLReader();
        $result = $reader->XML( $xml_string );

        if ( ! $result ) {
            libxml_use_internal_errors( $use_errors );
            return new WP_Error( 'icm_xml_parse_error', __( 'Could not parse the Icecat XML.', 'icecat-category-mapper' ) );
        }

        while ( $reader->read() ) {
            if ( $reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'Category' ) {
                continue;
            }

            $cat_id = (int) $reader->getAttribute( 'ID' );
            if ( $cat_id <= 0 ) {
                continue;
            }

            // Read the inner XML of this Category element
            $inner_xml = $reader->readOuterXml();

            // Use SimpleXML to parse just this node
            $node = @simplexml_load_string( $inner_xml );
            if ( ! $node ) {
                continue;
            }

            $name_en = '';
            $name_da = ''; // Name in the configured Icecat language (stored in the *_da column)
            $parent_id = 0;

            // Extract names by language
            if ( isset( $node->Name ) ) {
                foreach ( $node->Name as $name_node ) {
                    $lid = (int) $name_node['langid'];
                    $val = (string) $name_node['Value'];

                    if ( $lid === 1 ) {
                        $name_en = $val;  // English
                    }
                    if ( $lid === $lang_id ) {
                        $name_da = $val;  // Configured language
                    }
                }
            }

            // Extract parent category
            if ( isset( $node->ParentCategory ) ) {
                $parent_id = (int) $node->ParentCategory['ID'];
            }

            $categories[] = [
                'id'        => $cat_id,
                'name_en'   => $name_en,
                'name_da'   => $name_da,
                'parent_id' => $parent_id,
            ];
        }

        $reader->close();
        libxml_use_internal_errors( $use_errors );

        if ( empty( $categories ) ) {
            return new WP_Error( 'icm_no_categories', __( 'No categories found in the Icecat XML.', 'icecat-category-mapper' ) );
        }

        return $categories;
    }

    /**
     * Get cached categories.
     *
     * @return array Cached categories array (empty if no cache).
     */
    public static function get_cached_categories(): array {
        $cache = get_option( 'icm_categories_cache', '' );
        if ( empty( $cache ) ) {
            return [];
        }

        $data = maybe_unserialize( $cache );
        return is_array( $data ) ? $data : [];
    }

    /**
     * Check if the cache is fresh.
     *
     * @return bool
     */
    public static function is_cache_fresh(): bool {
        $cache_time = (int) get_option( 'icm_categories_cache_time', 0 );
        return ( time() - $cache_time ) < self::CACHE_DURATION;
    }

    /**
     * Get the cache timestamp as a formatted string.
     *
     * @return string
     */
    public static function get_cache_time_formatted(): string {
        $cache_time = (int) get_option( 'icm_categories_cache_time', 0 );
        if ( $cache_time === 0 ) {
            return __( 'Never fetched', 'icecat-category-mapper' );
        }

        return wp_date( get_option( 'date_format', 'Y-m-d' ) . ' H:i', $cache_time );
    }

    /**
     * Search cached categories by name.
     *
     * @param string $query Search query.
     * @param int    $limit Max results.
     * @return array Matching categories.
     */
    public static function search_categories( string $query, int $limit = 20 ): array {
        $categories = self::get_cached_categories();
        if ( empty( $categories ) || empty( $query ) ) {
            return [];
        }

        $query   = strtolower( $query );
        $matches = [];

        foreach ( $categories as $cat ) {
            if (
                stripos( $cat['name_en'], $query ) !== false ||
                stripos( $cat['name_da'], $query ) !== false ||
                (string) $cat['id'] === $query
            ) {
                $matches[] = $cat;
                if ( count( $matches ) >= $limit ) {
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * Test connection to Icecat API.
     *
     * @return true|WP_Error
     */
    public static function test_connection() {
        $username = get_option( 'icm_icecat_username', '' );
        $password = get_option( 'icm_icecat_password', '' );

        if ( empty( $username ) || empty( $password ) ) {
            return new WP_Error( 'icm_no_credentials', __( 'Username and password are missing.', 'icecat-category-mapper' ) );
        }

        // Use a lightweight request to test credentials (HEAD request)
        $response = wp_remote_head( self::CATEGORIES_URL, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'icm_connection_failed', __( 'Connection error:', 'icecat-category-mapper' ) . ' ' . $response->get_error_message() );
        }

        $status = wp_remote_retrieve_response_code( $response );

        if ( $status === 200 ) {
            return true;
        } elseif ( $status === 401 ) {
            return new WP_Error( 'icm_auth_failed', __( 'Invalid credentials (HTTP 401).', 'icecat-category-mapper' ) );
        }

        return new WP_Error( 'icm_unexpected_status', sprintf( /* translators: %d: HTTP status code. */ __( 'Unexpected HTTP status: %d', 'icecat-category-mapper' ), $status ) );
    }
}
