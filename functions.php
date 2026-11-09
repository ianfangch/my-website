<?php
/** IanProject theme bootstrap. */
defined( 'ABSPATH' ) || exit;

function ianproject_tokens( $text ) {
    static $map;
    if ( null === $map ) {
        $file = get_theme_file_path( 'assets/asset-map.json' );
        $map = is_readable( $file ) ? json_decode( file_get_contents( $file ), true ) : array();
    }
    if ( is_array( $map ) && $map ) { $text = strtr( $text, $map ); }
    return str_replace( array( '{{IAN_ASSET}}', '{{IAN_HOME}}' ), array( untrailingslashit( get_theme_file_uri( 'assets' ) ), untrailingslashit( home_url() ) ), $text );
}

if ( ! function_exists( 'iansf_compatible' ) ) { function iansf_compatible() { return function_exists( 'ianproject_manifest' ); } }
if ( ! function_exists( 'iansf_contacts' ) ) { require_once __DIR__ . '/inc/contacts.php'; }
add_action( 'after_setup_theme', function () { if ( ! function_exists( 'iansf_boot' ) ) { iansf_contacts_boot(); } }, 20 );

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'editor-styles' );
    add_editor_style( array( 'assets/vendor/fonts/fonts.css', 'assets/vendor/fontawesome/css/all.min.css', 'assets/vendor/bootstrap-icons/font/bootstrap-icons.css', 'assets/css/bootstrap.min.css', 'assets/css/style.css', 'assets/css/converted.css', 'assets/css/header-footer.css', 'assets/css/wordpress.css' ) );
} );

add_action( 'wp_enqueue_scripts', function () {
    $base = get_theme_file_uri( 'assets' );
    foreach ( array( 'fonts' => 'vendor/fonts/fonts.css', 'bootstrap' => 'css/bootstrap.min.css', 'icons' => 'vendor/fontawesome/css/all.min.css', 'bootstrap-icons' => 'vendor/bootstrap-icons/font/bootstrap-icons.css', 'owl' => 'lib/owlcarousel/assets/owl.carousel.min.css', 'original' => 'css/style.css', 'converted' => 'css/converted.css', 'header-footer' => 'css/header-footer.css', 'wordpress' => 'css/wordpress.css' ) as $handle => $file ) {
        if ( file_exists( get_theme_file_path( 'assets/' . $file ) ) ) {
            wp_enqueue_style( 'ian-' . $handle, $base . '/' . $file, array(), '2.0.0' );
        }
    }
    wp_enqueue_script( 'ian-bootstrap', $base . '/vendor/bootstrap/bootstrap.bundle.min.js', array(), '5.0.0', true );
    wp_enqueue_script( 'ian-owl', $base . '/lib/owlcarousel/owl.carousel.min.js', array( 'jquery' ), '2.3.4', true );
    wp_enqueue_script( 'ian-theme', $base . '/js/theme.js', array( 'jquery', 'ian-bootstrap', 'ian-owl' ), '2.0.0', true );
    wp_enqueue_script( 'ian-contact', $base . '/js/contact.js', array(), '2.0.0', true );
    $contacts = iansf_contacts();
    wp_localize_script( 'ian-contact', 'IAN_CONTACT', array( 'email' => $contacts['email'], 'whatsapp' => $contacts['whatsapp'], 'whatsappUrl' => iansf_whatsapp_url( $contacts ) ) );
} );

add_action( 'init', function () {
    add_rewrite_rule( '^product/?$', 'index.php?ian_legacy_product=1', 'top' );
    add_rewrite_rule( '^sitemap\.xml$', 'index.php?sitemap=index', 'top' );
    foreach ( ianproject_manifest()['pages'] ?? array() as $page ) {
        if ( 'products' === ( $page['parent'] ?? '' ) ) {
            $slug = sanitize_title( $page['slug'] );
            $imported = ianproject_imported_post( 'products/' . $slug . '.html' );
            $target = $imported ? 'index.php?page_id=' . $imported->ID : 'index.php?pagename=products/' . $slug;
            add_rewrite_rule( '^products/' . preg_quote( $slug, '~' ) . '/?$', $target, 'top' );
        }
    }
    foreach ( array( 'header', 'footer' ) as $part ) {
        $file = get_theme_file_path( 'data/' . $part . '.html' );
        if ( is_readable( $file ) ) {
            register_block_pattern( 'ianproject/' . $part, array( 'title' => 'IanProject ' . ucfirst( $part ), 'inserter' => false, 'content' => ianproject_tokens( file_get_contents( $file ) ) ) );
        }
    }
} );
add_filter( 'query_vars', function ( $vars ) { $vars[] = 'ian_legacy_product'; return $vars; } );

require_once __DIR__ . '/inc/content-types.php';
require_once __DIR__ . '/inc/presentation.php';
require_once __DIR__ . '/inc/upgrade.php';
require_once __DIR__ . '/inc/media.php';
require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/content-repairs.php';
require_once __DIR__ . '/inc/compatibility.php';
require_once __DIR__ . '/inc/preview.php';

// Resolve old static links without hardcoding a production domain.
add_action( 'template_redirect', function () {
    if ( ( get_query_var( 'ian_legacy_product' ) || is_page( 'product' ) ) && isset( $_GET['id'] ) && is_scalar( $_GET['id'] ) ) {
        $product = get_page_by_path( sanitize_title( wp_unslash( $_GET['id'] ) ), OBJECT, 'ian_product' );
        if ( $product ) { wp_safe_redirect( get_permalink( $product ), 301 ); exit; }
    }
    if ( is_404() ) {
        $path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
        if ( is_string( $path ) && preg_match( '~\.html$~', $path ) ) {
            $home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
            if ( $home_path && '/' !== $home_path && 0 === strpos( $path, $home_path ) ) { $path = '/' . substr( $path, strlen( $home_path ) ); }
            if ( '/index.html' === $path ) { wp_safe_redirect( home_url( '/' ), 301 ); exit; }
            $url = home_url( preg_replace( '~\.html$~', '/', $path ) );
            if ( url_to_postid( $url ) ) { wp_safe_redirect( $url, 301 ); exit; }
        }
    }
} );

// Keep SEO plugins in charge when installed, otherwise provide imported descriptions.
add_action( 'wp_head', function () {
    if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) { return; }
    if ( is_singular() ) {
        $description = get_post_meta( get_queried_object_id(), '_ian_description', true );
        if ( is_singular( 'ian_product' ) ) { $description = get_the_excerpt( get_queried_object_id() ); }
        if ( $description ) { echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $description ) ) . '">' . "\n"; }
    }
} );
