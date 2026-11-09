<?php
defined( 'ABSPATH' ) || exit;

/** Match untouched native blocks only; explicit token mode supports future imports. */
function ianproject_repair_category_content( $content, $source, $resolved = true, &$changes = null ) {
    static $repairs;
    if ( null === $repairs ) {
        $file = get_theme_file_path( 'data/content-repairs.json' );
        $repairs = is_readable( $file ) ? json_decode( file_get_contents( $file ), true ) : array();
    }
    $changes = 0;
    foreach ( is_array( $repairs ) ? $repairs : array() as $repair ) {
        if ( ( $repair['source'] ?? '' ) !== $source || empty( $repair['old'] ) || ! isset( $repair['new'] ) ) { continue; }
        $old = $resolved ? ianproject_tokens( $repair['old'] ) : $repair['old'];
        $new = $resolved ? ianproject_tokens( $repair['new'] ) : $repair['new'];
        $content = str_replace( $old, $new, $content, $count );
        $changes += $count;
        if ( $resolved && get_option( 'ian_legacy_stylesheet_v2' ) ) {
            $legacy_base = trailingslashit( get_theme_root_uri() ) . get_option( 'ian_legacy_stylesheet_v2' ) . '/assets';
            $legacy_old = str_replace( untrailingslashit( get_theme_file_uri( 'assets' ) ), $legacy_base, $old );
            if ( $legacy_old !== $old ) {
                $content = str_replace( $legacy_old, $new, $content, $legacy_count );
                $changes += $legacy_count;
            }
        }
    }
    return $content;
}

function ianproject_migrate_category_content() {
    $report = array( 'updated' => array(), 'fragments' => 0, 'errors' => array() );
    $targets = array();
    $pages = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => -1, 'meta_query' => array( array( 'key' => '_ian_source', 'value' => array( 'index.html', 'products.html' ), 'compare' => 'IN' ) ) ) );
    foreach ( $pages as $page ) { $targets[ $page->ID ] = array( $page, get_post_meta( $page->ID, '_ian_source', true ) ); }
    foreach ( get_block_templates( array( 'slug__in' => array( 'footer' ) ), 'wp_template_part' ) as $part ) {
        if ( 'custom' !== $part->source || empty( $part->wp_id ) || $part->theme !== get_stylesheet() ) { continue; }
        $post = get_post( $part->wp_id );
        if ( $post && 'wp_template_part' === $post->post_type ) { $targets[ $post->ID ] = array( $post, 'footer' ); }
    }
    foreach ( $targets as list( $post, $source ) ) {
        $content = ianproject_repair_category_content( $post->post_content, $source, true, $changes );
        if ( ! $changes || $content === $post->post_content ) { continue; }
        if ( ! metadata_exists( 'post', $post->ID, '_ian_content_backup_1_0_3' ) ) { add_post_meta( $post->ID, '_ian_content_backup_1_0_3', wp_slash( $post->post_content ), true ); }
        wp_save_post_revision( $post->ID );
        $result = wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_content' => $content ) ), true );
        if ( is_wp_error( $result ) ) { $report['errors'][] = $result->get_error_message(); continue; }
        $report['updated'][] = $post->ID;
        $report['fragments'] += $changes;
    }
    return $report;
}

add_action( 'admin_init', function () {
    if ( ! current_user_can( 'manage_options' ) || get_option( 'ian_content_upgraded_1_0_3' ) ) { return; }
    $report = ianproject_migrate_category_content();
    update_option( 'ian_content_report_1_0_3', $report, false );
    if ( ! $report['errors'] ) {
        update_option( 'ian_content_upgraded_1_0_3', true, false );
        do_action( 'litespeed_purge_all' );
    }
} );
