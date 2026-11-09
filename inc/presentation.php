<?php
defined( 'ABSPATH' ) || exit;

add_shortcode( 'ian_quote_form', function () {
    return file_get_contents( get_theme_file_path( 'data/quote-form.html' ) );
} );

// Theme-only installations have useful public output without product functionality.
add_action( 'init', function () {
    if ( ! shortcode_exists( 'ian_catalog' ) ) {
        add_shortcode( 'ian_catalog', function () {
            $contact = ianproject_imported_post( 'contact.html' );
            $url = $contact ? get_permalink( $contact ) : home_url( '/contact/' );
            return '<section id="catalog" class="container py-5"><p>Our product selection is being updated.</p><p><a class="btn btn-primary" href="' . esc_url( $url ) . '">Discuss Your Requirements</a></p></section>';
        } );
    }
    if ( ! shortcode_exists( 'ian_product_price' ) ) { add_shortcode( 'ian_product_price', '__return_empty_string' ); }
    if ( ! shortcode_exists( 'ian_product_meta' ) ) { add_shortcode( 'ian_product_meta', '__return_empty_string' ); }
}, 99 );
