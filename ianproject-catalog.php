<?php
/**
 * Plugin Name: IanProject 产品目录
 * Description: 独立管理产品、分类、型号、价格与目录筛选。不附带示例产品，保留已有 IanProject 产品。
 * Version: 2.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */
defined( 'ABSPATH' ) || exit;

function ianproject_catalog_boot() {
    // Existing 1.x themes continue to run during the transition to the separate module.
    if ( ! function_exists( 'ianproject_price_html' ) ) { require_once __DIR__ . '/includes/content-types.php'; }
    if ( ! function_exists( 'ianproject_query_text' ) ) { require_once __DIR__ . '/includes/catalogue.php'; }
}
add_action( 'after_setup_theme', 'ianproject_catalog_boot', 30 );
if ( did_action( 'after_setup_theme' ) ) { ianproject_catalog_boot(); }

function ianproject_catalog_image( $image ) {
    if ( ! is_string( $image ) || str_contains( $image, '{{' ) ) { return ''; }
    return esc_url_raw( $image );
}

add_action( 'wp_enqueue_scripts', function () {
    if ( is_singular( 'ian_product' ) || is_page() ) {
        wp_enqueue_style( 'ian-catalog-module', plugins_url( 'assets/catalog.css', __FILE__ ), array(), '2.0.0' );
    }
} );

register_activation_hook( __FILE__, function () {
    ianproject_catalog_boot();
    if ( function_exists( 'ianproject_catalog_register' ) ) { ianproject_catalog_register(); }
    flush_rewrite_rules( false );
} );
register_deactivation_hook( __FILE__, function () { flush_rewrite_rules( false ); } );

add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['post_type'] ) || 'ian_product' !== $_GET['post_type'] ) { return; }
    echo '<div class="notice notice-info"><p>产品数据独立保存在数据库和媒体库中。启用插件不会导入旧产品，也不会清空现有产品；您可以添加自己的分类、封面、图库和规格。停用或删除插件也不会删除产品数据。</p></div>';
} );
