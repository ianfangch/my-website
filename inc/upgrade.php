<?php
defined( 'ABSPATH' ) || exit;

/** Repair-plugin handoff is reversible; saved contacts and products remain in place. */
function ianproject_finish_upgrade( $old_name = '', $old_theme = null ) {
    // WordPress fires this after an authorized theme switch, sometimes on a visitor request.
    if ( $old_theme instanceof WP_Theme && $old_theme->get_stylesheet() !== get_stylesheet() ) {
        update_option( 'ian_legacy_stylesheet_v2', $old_theme->get_stylesheet(), false );
        // Preserve saved header/footer parts when switching to the new theme folder.
        $parts = get_posts( array( 'post_type' => 'wp_template_part', 'post_status' => 'publish', 'posts_per_page' => -1, 'tax_query' => array( array( 'taxonomy' => 'wp_theme', 'field' => 'slug', 'terms' => $old_theme->get_stylesheet() ) ) ) );
        foreach ( $parts as $part ) {
            $existing = get_posts( array( 'post_type' => 'wp_template_part', 'post_name__in' => array( $part->post_name ), 'posts_per_page' => 1, 'tax_query' => array( array( 'taxonomy' => 'wp_theme', 'field' => 'slug', 'terms' => get_stylesheet() ) ) ) );
            if ( $existing ) { continue; }
            $id = wp_insert_post( wp_slash( array( 'post_type' => 'wp_template_part', 'post_status' => 'publish', 'post_name' => $part->post_name, 'post_title' => $part->post_title, 'post_content' => $part->post_content ) ), true );
            if ( is_wp_error( $id ) ) { continue; }
            wp_set_object_terms( $id, get_stylesheet(), 'wp_theme' );
            $areas = wp_get_object_terms( $part->ID, 'wp_template_part_area', array( 'fields' => 'slugs' ) );
            if ( ! is_wp_error( $areas ) && $areas ) { wp_set_object_terms( $id, $areas, 'wp_template_part_area' ); }
            update_post_meta( $id, '_ian_v2_copied_from', $part->ID );
        }
    }
    if ( ! function_exists( 'deactivate_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
    $disabled = array();
    foreach ( (array) get_option( 'active_plugins', array() ) as $plugin ) {
        if ( 'ianproject-site-fixes.php' !== basename( $plugin ) ) { continue; }
        $data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );
        if ( ! str_starts_with( $data['Name'] ?? '', 'IanProject ' ) ) { continue; }
        deactivate_plugins( $plugin, true, false );
        $disabled[] = $plugin;
    }
    update_option( 'ian_v2_repair_plugin_handoff', $disabled, false );
    do_action( 'litespeed_purge_all' );
    flush_rewrite_rules( false );
}
add_action( 'after_switch_theme', 'ianproject_finish_upgrade', 10, 2 );

add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    if ( get_option( 'ian_v2_repair_plugin_handoff' ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>IanProject 2.0 已接管原修复插件，并保留已保存的联系方式。旧修复插件已停用，确认前台正常后可以删除它。产品管理由独立的 IanProject 产品目录插件提供。</p></div>';
    }
} );
