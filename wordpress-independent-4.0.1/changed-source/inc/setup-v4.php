<?php
defined( 'ABSPATH' ) || exit;
function ian3_manifest() { static $data; if ( null === $data ) { $data = json_decode( file_get_contents( get_theme_file_path( 'data/release.json' ) ), true ); } return is_array( $data ) ? $data : array(); }
function ianproject_homepage_id() { return ian3_id( 'home' ); }
function ianproject_imported_post( $source, $type = 'page' ) { $posts = get_posts( array( 'post_type' => $type, 'meta_key' => '_ian_source', 'meta_value' => $source, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => 1 ) ); return $posts[0] ?? null; }
function ian4_starter_pages() {
    $map = (array) get_option( 'ian3_pages', array() );
    foreach ( ian3_manifest()['pages'] ?? array() as $record ) {
        if ( ! empty( $map[ $record['role'] ] ) && get_post( $map[ $record['role'] ] ) ) { continue; }
        $existing = ianproject_imported_post( $record['source'] );
        if ( $existing ) { $map[ $record['role'] ] = $existing->ID; continue; }
        $slug = get_page_by_path( $record['slug'], OBJECT, 'page' ) ? 'ianproject-' . $record['slug'] : $record['slug'];
        $id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $record['title'], 'post_content' => ianproject_tokens( $record['content'] ), 'post_excerpt' => $record['description'] ?? '', 'comment_status' => 'closed' ) ), true );
        if ( is_wp_error( $id ) ) { return $id; }
        update_post_meta( $id, '_ian_source', $record['source'] ); update_post_meta( $id, '_ian4_native', '4.0.0' ); $map[ $record['role'] ] = $id;
    }
    update_option( 'ian3_pages', $map, false );
    foreach ( array( 'project' => 'Project', 'insights' => 'Insights' ) as $slug => $name ) { if ( ! get_term_by( 'slug', $slug, 'category' ) ) { wp_insert_term( $name, 'category', array( 'slug' => $slug ) ); } }
    if ( class_exists( 'WooCommerce' ) ) {
        if ( ! get_option( 'woocommerce_cart_page_id' ) ) { $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Cart', 'post_name' => 'project-cart', 'post_content' => '<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->' ) ); update_option( 'woocommerce_cart_page_id', $id ); }
        if ( ! get_option( 'woocommerce_shop_page_id' ) && isset( $map['products'] ) ) { update_option( 'woocommerce_shop_page_id', $map['products'] ); }
    }
    return $map;
}
function ian3_create_profile( $kind, $seed = false ) {
    $kind = 'showroom' === $kind ? 'showroom' : 'factory'; $parent = ian3_id( $kind );
    if ( ! $parent ) { return new WP_Error( 'ian_parent', '请先创建基础页面。' ); }
    foreach ( ian3_manifest()['patterns'] ?? array() as $pattern ) { if ( $pattern['slug'] === $kind . '-profile' ) { return wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_parent' => $parent, 'post_title' => ucfirst( $kind ) . ' — 待填写', 'post_content' => ianproject_tokens( $pattern['content'] ) ) ), true ); } }
    return new WP_Error( 'ian_pattern', '未找到资料模板。' );
}
add_action( 'admin_post_ian3_add_profile', function () { if ( ! current_user_can( 'edit_pages' ) ) { wp_die( '没有权限', 403 ); } check_admin_referer( 'ian3_profile' ); $id = ian3_create_profile( sanitize_key( $_GET['kind'] ?? '' ) ); if ( is_wp_error( $id ) ) { wp_die( esc_html( $id->get_error_message() ) ); } wp_safe_redirect( get_edit_post_link( $id, 'raw' ) ); exit; } );
add_action( 'admin_menu', function () { add_theme_page( 'IanProject 设置', 'IanProject 设置', 'manage_options', 'ianproject-setup', 'ian4_settings' ); } );
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $legacy = array_intersect( (array) get_option( 'active_plugins', array() ), array( 'ianproject-catalog/ianproject-catalog.php', 'ianproject-site/ianproject-site.php' ) );
    if ( $legacy ) { echo '<div class="notice notice-warning"><p>IanProject 4.0：旧目录或修复插件仍在运行，可能覆盖新主题的商品、购物车和样式。请先用独立迁移工具完成转换，验收时停用旧插件；WooCommerce 保持启用。</p></div>'; }
} );
function ian4_settings() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    if ( isset( $_POST['ian4_starter'] ) ) { check_admin_referer( 'ian4_setup' ); $r = ian4_starter_pages(); echo '<div class="notice ' . ( is_wp_error( $r ) ? 'notice-error' : 'notice-success' ) . '"><p>' . esc_html( is_wp_error( $r ) ? $r->get_error_message() : '基础页面已准备好；已有页面和商品未覆盖，首页不会自动切换。' ) . '</p></div>'; }
    if ( isset( $_POST['ian4_save'] ) ) {
        check_admin_referer( 'ian4_setup' ); $map = array(); $visibility = array();
        foreach ( ian3_roles() as $role => $label ) { $id = absint( $_POST['ian4_pages'][ $role ] ?? 0 ); if ( $id && 'page' === get_post_type( $id ) ) { $map[ $role ] = $id; } $visibility[ $role ] = isset( $_POST['ian4_visible'][ $role ] ) ? 1 : 0; }
        update_option( 'ian3_pages', $map, false ); update_option( 'ian3_visibility', $visibility, false );
        if ( isset( $_POST['ian4_front'] ) && ! empty( $map['home'] ) && 'publish' === get_post_status( $map['home'] ) ) { update_option( 'page_on_front', $map['home'] ); update_option( 'show_on_front', 'page' ); }
        do_action( 'litespeed_purge_all' ); echo '<div class="notice notice-success"><p>页面映射和入口设置已保存。</p></div>';
    }
    $pages = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
    $map = (array) get_option( 'ian3_pages', array() ); $visibility = (array) get_option( 'ian3_visibility', array() );
    echo '<div class="wrap" style="max-width:1100px"><h1>IanProject 4.0</h1><p>主题包含日常编辑、统一模板、分类显示、规格图片联动、购物车和基础询价。旧资料迁移与批量工具是独立可选插件。</p><h2>基础页面</h2><p>只补建缺少的页面，不导入示例商品、行业文章或案例，不覆盖已编辑页面。</p><form method="post">'; wp_nonce_field( 'ian4_setup' ); echo '<button class="button button-primary" name="ian4_starter">创建缺少的基础页面</button></form><h2>页面和导航入口</h2><form method="post">'; wp_nonce_field( 'ian4_setup' ); echo '<table class="form-table">';
    foreach ( ian3_roles() as $role => $label ) { echo '<tr><th>' . esc_html( $label ) . '</th><td><select name="ian4_pages[' . esc_attr( $role ) . ']"><option value="0">未指定</option>'; foreach ( $pages as $page ) { echo '<option value="' . esc_attr( $page->ID ) . '" ' . selected( $map[ $role ] ?? 0, $page->ID, false ) . '>' . esc_html( $page->post_title . ' — ' . $page->post_status ) . '</option>'; } echo '</select> <label><input type="checkbox" name="ian4_visible[' . esc_attr( $role ) . ']" ' . checked( ! isset( $visibility[ $role ] ) || $visibility[ $role ], true, false ) . '>显示入口</label></td></tr>'; }
    echo '</table><p><label><input type="checkbox" name="ian4_front">同时将所选 Home 设为网站首页</label></p><button name="ian4_save" class="button button-primary">保存设置</button></form><h2>日常编辑</h2><p>页面使用 WordPress 原生编辑器；文章使用 Project / Insights 分类；产品、价格、规格及图库使用 WooCommerce。包装图和附加规格字段位于商品的“IanProject 资料”标签及各可变规格中。</p><p><a class="button" href="' . esc_url( admin_url( 'options-general.php?page=ianproject-contacts' ) ) . '">统一联系方式</a></p><p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ian3_add_profile&kind=factory' ), 'ian3_profile' ) ) . '">新增工厂资料草稿</a> <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ian3_add_profile&kind=showroom' ), 'ian3_profile' ) ) . '">新增展厅资料草稿</a></p><h2>旧资料迁移</h2><p>请单独安装 IanProject 旧资料迁移工具，先扫描再转换。主题本身不会扫描或自动改写旧资料。</p></div>';
}
// Keep published static-site links useful after the formal domain is moved.
add_action( 'template_redirect', function () {
    if ( is_admin() || is_preview() || ! is_404() ) { return; }
    $path = trim( rawurldecode( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) ), '/' );
    $base = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
    if ( $base && str_starts_with( $path, $base . '/' ) ) { $path = substr( $path, strlen( $base ) + 1 ); }
    if ( ! preg_match( '~^(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+\.html$~', $path ) ) { return; }
    $aliases = array( 'index.html' => 'home', 'about.html' => 'about', 'contact.html' => 'contact', 'products.html' => 'products', 'project-cases.html' => 'project', 'insights.html' => 'insights', 'privacy.html' => 'privacy', 'terms.html' => 'terms' );
    $id = isset( $aliases[ $path ] ) ? ian3_id( $aliases[ $path ] ) : 0;
    if ( ! $id || 'publish' !== get_post_status( $id ) ) {
        $matches = get_posts( array( 'post_type' => array( 'page', 'post', 'product' ), 'post_status' => 'publish', 'posts_per_page' => 1, 'meta_key' => '_ian_source', 'meta_value' => $path, 'fields' => 'ids' ) );
        $id = $matches[0] ?? 0;
    }
    if ( $id && 'publish' === get_post_status( $id ) ) { wp_safe_redirect( get_permalink( $id ), 301 ); exit; }
}, 2 );
add_action( 'template_redirect', function () {
    if ( is_admin() || is_preview() || ! is_404() ) { return; }
    $path = '/' . trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ), '/' );
    $routes = (array) get_option( 'ian4_redirects', array() ); $id = absint( $routes[ $path ] ?? 0 );
    if ( $id && 'publish' === get_post_status( $id ) ) { wp_safe_redirect( get_permalink( $id ), 301 ); exit; }
}, 3 );
