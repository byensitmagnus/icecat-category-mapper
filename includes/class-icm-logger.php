<?php
/**
 * Logging wrapper for all remapping actions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ICM_Logger {

    /**
     * Log a successful category remap.
     */
    public static function log_remap(
        int $product_id,
        string $product_title,
        int $icecat_cat_id,
        string $icecat_cat_name,
        int $target_term_id,
        string $target_slug,
        string $action = 'remapped'
    ): void {
        ICM_DB::insert_log( [
            'product_id'      => $product_id,
            'product_title'   => $product_title,
            'icecat_cat_id'   => $icecat_cat_id,
            'icecat_cat_name' => $icecat_cat_name,
            'target_term_id'  => $target_term_id,
            'target_slug'     => $target_slug,
            'action'          => $action,
        ] );
    }

    /**
     * Log an unmapped category encounter.
     */
    public static function log_unmapped(
        int $product_id,
        string $product_title,
        int $icecat_cat_id,
        string $icecat_cat_name
    ): void {
        ICM_DB::insert_log( [
            'product_id'      => $product_id,
            'product_title'   => $product_title,
            'icecat_cat_id'   => $icecat_cat_id,
            'icecat_cat_name' => $icecat_cat_name,
            'target_term_id'  => 0,
            'target_slug'     => '',
            'action'          => 'unmapped',
        ] );
    }

    /**
     * Log a skipped product (already has correct category).
     */
    public static function log_skipped(
        int $product_id,
        string $product_title,
        int $icecat_cat_id,
        string $icecat_cat_name,
        string $reason = ''
    ): void {
        ICM_DB::insert_log( [
            'product_id'      => $product_id,
            'product_title'   => $product_title,
            'icecat_cat_id'   => $icecat_cat_id,
            'icecat_cat_name' => $icecat_cat_name,
            'target_term_id'  => 0,
            'target_slug'     => $reason,
            'action'          => 'skipped',
        ] );
    }
}
