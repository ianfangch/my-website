<?php
defined( 'ABSPATH' ) || exit;

function ianproject_is_imported_native_page() {
    if ( is_admin() || ! is_singular( array( 'page', 'ian_product', 'ian_insight' ) ) ) { return false; }
    $id = get_queried_object_id();
    return (bool) get_post_meta( $id, '_ian_source', true ) && 'builder' !== get_post_meta( $id, '_elementor_edit_mode', true );
}

/** The old IanProject plugin ships unscoped body/heading/button CSS. */
function ianproject_remove_legacy_styles() {
    if ( ! ianproject_is_imported_native_page() ) { return; }
    foreach ( wp_styles()->registered as $handle => $style ) {
        $path = is_string( $style->src ) ? wp_parse_url( $style->src, PHP_URL_PATH ) : null;
        if ( is_string( $path ) && preg_match( '~/plugins/ianproject-site/site\.css$~', $path ) ) { wp_dequeue_style( $handle ); }
    }
}
add_action( 'wp_enqueue_scripts', 'ianproject_remove_legacy_styles', 100 );
add_action( 'wp_print_styles', 'ianproject_remove_legacy_styles', PHP_INT_MAX );

function ianproject_imported_link_map() {
    static $map;
    if ( null !== $map ) { return $map; }
    $map = array();
    $base_path = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
    $posts = get_posts( array( 'post_type' => array( 'page', 'ian_product', 'ian_insight' ), 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_key' => '_ian_source', 'orderby' => 'ID', 'order' => 'DESC' ) );
    foreach ( $posts as $post ) {
        $source = get_post_meta( $post->ID, '_ian_source', true );
        if ( ! is_string( $source ) || ! preg_match( '~\.html$~', $source ) ) { continue; }
        $path = 'index.html' === $source ? '' : preg_replace( '~\.html$~', '', $source );
        $canonical = rtrim( $base_path . '/' . ltrim( $path, '/' ), '/' );
        $map[ $canonical ] = get_permalink( $post );
        $map[ $base_path . '/' . $source ] = $map[ $canonical ];
        if ( 'index.html' === $source ) { $map[ $base_path . '/home' ] = $map[ $canonical ]; }
    }
    return $map;
}

/** Match complete local paths so /products never rewrites product-detail prefixes. */
function ianproject_remap_imported_links( $html ) {
    if ( false === strpos( $html, 'href=' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) { return $html; }
    $map = ianproject_imported_link_map();
    if ( ! $map ) { return $html; }
    $home = wp_parse_url( home_url( '/' ) );
    $base_path = rtrim( $home['path'] ?? '', '/' );
    $processor = new WP_HTML_Tag_Processor( $html );
    while ( $processor->next_tag( 'A' ) ) {
        $url = $processor->get_attribute( 'href' );
        if ( ! is_string( $url ) || '' === $url || '#' === $url[0] ) { continue; }
        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) || ( ! empty( $parts['scheme'] ) && ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) ) { continue; }
        if ( isset( $parts['host'] ) && strtolower( $parts['host'] ) !== strtolower( $home['host'] ?? '' ) ) { continue; }
        $path = $parts['path'] ?? '/';
        if ( '/' !== substr( $path, 0, 1 ) ) { $path = $base_path . '/' . $path; }
        $key = rtrim( $path, '/' );
        if ( ! isset( $map[ $key ] ) ) { continue; }
        $destination = $map[ $key ];
        if ( ! empty( $parts['query'] ) ) { parse_str( $parts['query'], $query ); $destination = add_query_arg( $query, $destination ); }
        if ( isset( $parts['fragment'] ) ) { $destination .= '#' . $parts['fragment']; }
        $processor->set_attribute( 'href', $destination );
    }
    return $processor->get_updated_html();
}

add_filter( 'render_block', function ( $html, $block ) {
    $name = $block['blockName'] ?? '';
    $navigation = in_array( $name, array( 'core/navigation-link', 'core/navigation-submenu' ), true );
    if ( ! $navigation && ( ! ianproject_is_imported_native_page() || ! in_array( $name, array( 'core/paragraph', 'core/heading', 'core/image', 'core/button', 'core/list', 'core/list-item', 'core/table' ), true ) ) ) { return $html; }
    return ianproject_remap_imported_links( $html );
}, 10, 2 );
