<?php
/** IanProject 4.0 — native content and WooCommerce presentation; migration is optional. */
defined( 'ABSPATH' ) || exit;
define( 'IANPROJECT_VERSION', '4.0.1' );
function ianproject_tokens( $text ) { return str_replace( array( '{{IAN_ASSET}}', '{{IAN_HOME}}' ), array( untrailingslashit( get_theme_file_uri( 'assets' ) ), untrailingslashit( home_url() ) ), $text ); }
function ianproject_query_values( $values ) { return map_deep( $values, static fn( $value ) => rawurlencode( (string) $value ) ); }
function iansf_compatible() { return true; }
require_once __DIR__ . '/inc/contacts.php';

require_once __DIR__ . '/inc/site-v3.php';
require_once __DIR__ . '/inc/articles-v3.php';
require_once __DIR__ . '/inc/setup-v4.php';

require_once __DIR__ . '/inc/reviews-v3.php';
require_once __DIR__ . '/inc/woo-display.php';
require_once __DIR__ . '/inc/product-data-v4.php';
require_once __DIR__ . '/inc/commerce-v4.php';
require_once __DIR__ . '/inc/enquiry-v4.php';
require_once __DIR__ . '/inc/contact-actions.php';
require_once __DIR__ . '/inc/preview.php';
add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' ); add_theme_support( 'post-thumbnails' ); add_theme_support( 'responsive-embeds' ); add_theme_support( 'editor-styles' ); add_theme_support( 'woocommerce' ); add_theme_support( 'custom-logo', array( 'height' => 70, 'width' => 240, 'flex-width' => true, 'flex-height' => true ) );
    iansf_contacts_boot();
    add_editor_style( array( 'assets/vendor/fonts/fonts.css', 'assets/css/bootstrap.min.css', 'assets/css/style.css', 'assets/css/converted.css', 'assets/css/wordpress.css', 'assets/css/home-story.css', 'assets/css/site-design.css', 'assets/css/site-about.css', 'assets/css/release-inline.css', 'assets/css/block-adapters.css', 'assets/css/typography.css' ) );
} );
add_action( 'after_switch_theme', function ( $name, $theme ) { if ( $theme instanceof WP_Theme && $theme->get_stylesheet() !== get_stylesheet() ) { update_option( 'ian_legacy_stylesheet_v2', $theme->get_stylesheet(), false ); } flush_rewrite_rules( false ); }, 10, 2 );
add_action( 'wp_enqueue_scripts', function () {
    $base = get_theme_file_uri( 'assets' );
    foreach ( array( 'fonts' => 'vendor/fonts/fonts.css', 'bootstrap' => 'css/bootstrap.min.css', 'icons' => 'vendor/fontawesome/css/all.min.css', 'original' => 'css/style.css', 'converted' => 'css/converted.css', 'wordpress' => 'css/wordpress.css', 'shell' => 'css/site-shell.css', 'home' => 'css/home-story.css', 'design' => 'css/site-design.css', 'about' => 'css/site-about.css', 'inline' => 'css/release-inline.css', 'adapters' => 'css/block-adapters.css', 'catalog-base' => 'css/catalog-base.css' ) as $handle => $file ) { wp_enqueue_style( 'ian-' . $handle, $base . '/' . $file, array(), IANPROJECT_VERSION ); }
    wp_enqueue_script( 'ian-site', $base . '/js/site-v3.js', array(), IANPROJECT_VERSION, true );
    wp_enqueue_script( 'ian-bootstrap', $base . '/vendor/bootstrap/bootstrap.bundle.min.js', array(), '5.0.0', true );

}, 20 );
add_action( 'init', function () {
    if ( ! shortcode_exists( 'ian_catalog' ) ) { add_shortcode( 'ian_catalog', 'ian3_basic_catalogue' ); }
    if ( ! shortcode_exists( 'ian_wc_product' ) ) { add_shortcode( 'ian_wc_product', 'ian3_product_detail' ); }
    if ( ! shortcode_exists( 'ian_home_product_categories' ) ) { add_shortcode( 'ian_home_product_categories', function () { return '<p class="ih-section-intro">Share your product requirements to explore suitable options. ' . ian3_link( 'contact', 'Contact IanProject ↗' ) . '</p>'; } ); }
    foreach ( ian3_manifest()['patterns'] ?? array() as $p ) { register_block_pattern( 'ianproject/' . $p['slug'], array( 'title' => $p['title'], 'categories' => array( 'pages' ), 'content' => ianproject_tokens( $p['content'] ) ) ); }
}, 99 );
add_filter( 'body_class', function ( $classes ) { if ( 'contact' !== ian3_current_role() ) { $classes[] = 'ian-modern'; } return $classes; } );
add_action( 'wp_head', function () {
    if ( ! is_singular() ) { return; } $id = get_queried_object_id(); $description = get_post_meta( $id, '_ian_description', true ) ?: get_post_field( 'post_excerpt', $id );
    if ( $description ) { echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $description ) ) . '">'; }
    echo '<meta property="og:title" content="' . esc_attr( get_the_title( $id ) ) . '"><meta property="og:url" content="' . esc_url( get_permalink( $id ) ) . '">';
    $image = get_the_post_thumbnail_url( $id, 'large' ); if ( $image ) { echo '<meta property="og:image" content="' . esc_url( $image ) . '">'; }
}, 3 );
add_action( 'wp_head', function () { if ( ! has_site_icon() && is_file( get_theme_file_path( 'assets/img/favicon.svg' ) ) ) { echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( get_theme_file_uri( 'assets/img/favicon.svg' ) ) . '">'; } }, 2 );
// Dequeue the old, unscoped repair stylesheet without affecting unrelated plugins.
add_action( 'wp_enqueue_scripts', function () { foreach ( wp_styles()->registered as $handle => $style ) { if ( is_string( $style->src ) && preg_match( '~/plugins/ianproject-site/site\.css(?:\?|$)~', $style->src ) ) { wp_dequeue_style( $handle ); } } }, 100 );

add_action( 'wp_enqueue_scripts', function () { wp_enqueue_style( 'ian-typography', get_theme_file_uri( 'assets/css/typography.css' ), array( 'ian-adapters' ), IANPROJECT_VERSION ); }, 60 );

add_action( 'wp_enqueue_scripts', function () { wp_enqueue_style( 'ian-project-alignment', get_theme_file_uri( 'assets/css/project-alignment.css' ), array( 'ian-typography' ), IANPROJECT_VERSION ); }, 61 );
