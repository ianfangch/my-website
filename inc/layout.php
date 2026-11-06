<?php
defined( 'ABSPATH' ) || exit;

/** Correct only known, unchanged conversion fragments; preserve other edits. */
function ianproject_repair_layout_content( $content, $source, &$changes = null ) {
    static $repairs;
    if ( null === $repairs ) {
        $file = get_theme_file_path( 'data/layout-repairs.json' );
        $repairs = is_readable( $file ) ? json_decode( file_get_contents( $file ), true ) : array();
    }
    $changes = 0;
    foreach ( is_array( $repairs ) ? $repairs : array() as $repair ) {
        if ( ( $repair['source'] ?? '' ) !== $source || empty( $repair['old'] ) || ! isset( $repair['new'] ) ) { continue; }
        $content = str_replace( $repair['old'], $repair['new'], $content, $count );
        $changes += $count;
    }
    return $content;
}

function ianproject_migrate_layout() {
    $report = array( 'updated' => array(), 'fragments' => 0, 'errors' => array() );
    $posts = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => -1, 'meta_query' => array( array( 'key' => '_ian_source', 'value' => array( 'about.html', 'custom-solutions.html' ), 'compare' => 'IN' ) ) ) );
    foreach ( $posts as $post ) {
        $source = get_post_meta( $post->ID, '_ian_source', true );
        $content = ianproject_repair_layout_content( $post->post_content, $source, $changes );
        if ( ! $changes || $content === $post->post_content ) { continue; }
        // Keep a backup even when the site's standard revisions are disabled.
        if ( ! metadata_exists( 'post', $post->ID, '_ian_layout_backup_1_0_2' ) ) { add_post_meta( $post->ID, '_ian_layout_backup_1_0_2', wp_slash( $post->post_content ), true ); }
        wp_save_post_revision( $post->ID );
        $result = wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_content' => $content ) ), true );
        if ( is_wp_error( $result ) ) { $report['errors'][] = $result->get_error_message(); continue; }
        $report['updated'][] = $post->ID;
        $report['fragments'] += $changes;
    }
    if ( $report['updated'] ) { do_action( 'litespeed_purge_all' ); }
    return $report;
}

add_action( 'admin_init', function () {
    if ( ! current_user_can( 'manage_options' ) || get_option( 'ian_layout_upgraded_1_0_2' ) ) { return; }
    $report = ianproject_migrate_layout();
    update_option( 'ian_layout_report_1_0_2', $report, false );
    if ( ! $report['errors'] ) {
        update_option( 'ian_layout_upgraded_1_0_2', true, false );
        if ( ! $report['updated'] ) { do_action( 'litespeed_purge_all' ); }
    }
} );
