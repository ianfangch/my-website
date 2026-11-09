<?php
defined( 'ABSPATH' ) || exit;

/** Normalize a URL path before applying the site's preview boundary. */
function ianproject_preview_path( $path ) {
    if ( ! is_string( $path ) || preg_match( '/[\\\\\x00-\x20\x7f]/', $path ) ) { return null; }
    for ( $i = 0; $i < 5; ++$i ) {
        if ( preg_match( '/%(?:2f|5c)/i', $path ) ) { return null; }
        $decoded = rawurldecode( $path );
        if ( preg_match( '/[\\\\\x00-\x20\x7f?#]/', $decoded ) ) { return null; }
        if ( $decoded === $path ) { break; }
        $path = $decoded;
    }
    if ( preg_match( '/%[0-9a-f]{2}/i', $path ) ) { return null; }
    $segments = array();
    foreach ( explode( '/', $path ) as $segment ) {
        if ( '' === $segment || '.' === $segment ) { continue; }
        if ( '..' === $segment ) { array_pop( $segments ); } else { $segments[] = $segment; }
    }
    return '/' . implode( '/', $segments );
}

function ianproject_preview_current_uri() {
    $uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    if ( '' === $uri || '/' !== $uri[0] || str_starts_with( $uri, '//' ) || preg_match( '/[\\\\\x00-\x20\x7f]/', $uri ) ) { return home_url( '/' ); }
    $home = wp_parse_url( home_url( '/' ) );
    $authority = $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
    return $authority . $uri;
}

/** Only public page destinations inside this WordPress site's exact origin qualify. */
function ianproject_preview_page_destination( $url ) {
    if ( ! is_string( $url ) || $url !== trim( $url ) || preg_match( '/[\\\\\x00-\x20\x7f]/', $url ) ) { return false; }
    $home = wp_parse_url( home_url( '/' ) );
    $parts = wp_parse_url( $url );
    if ( ! is_array( $home ) || empty( $home['host'] ) || ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) { return false; }
    $home_scheme = strtolower( $home['scheme'] ?? 'https' );
    $scheme = strtolower( $parts['scheme'] ?? $home_scheme );
    if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || $scheme !== $home_scheme ) { return false; }
    if ( isset( $parts['scheme'] ) && ! isset( $parts['host'] ) ) { return false; }
    $port = $parts['port'] ?? ( 'https' === $scheme ? 443 : 80 );
    $home_port = $home['port'] ?? ( 'https' === $home_scheme ? 443 : 80 );
    if ( isset( $parts['host'] ) && ( strtolower( $parts['host'] ) !== strtolower( $home['host'] ) || $port !== $home_port ) ) { return false; }
    $path = $parts['path'] ?? '';
    if ( ! isset( $parts['host'] ) && ( '' === $path || '/' !== $path[0] ) ) {
        $current_path = wp_parse_url( ianproject_preview_current_uri(), PHP_URL_PATH ) ?: '/';
        $path = '' === $path ? $current_path : substr( $current_path, 0, strrpos( $current_path, '/' ) + 1 ) . $path;
    }
    $path = ianproject_preview_path( $path ?: '/' );
    $home_path = ianproject_preview_path( $home['path'] ?? '/' );
    if ( null === $path || null === $home_path ) { return false; }
    $boundary = rtrim( $home_path, '/' );
    if ( '' !== $boundary && $path !== $boundary && ! str_starts_with( $path, $boundary . '/' ) ) { return false; }
    if ( preg_match( '~/(?:wp-admin|wp-json|wp-content|wp-includes|feed|rss|rss2|rdf|atom)(?:/|$)~i', $path ) ) { return false; }
    $rest_path = wp_parse_url( rest_url( '/' ), PHP_URL_PATH );
    if ( is_string( $rest_path ) && '/' !== $rest_path ) {
        $rest_path = rtrim( $rest_path, '/' );
        if ( $path === $rest_path || str_starts_with( $path, $rest_path . '/' ) ) { return false; }
    }
    $file = basename( $path );
    if ( preg_match( '/\.[a-z0-9]{1,8}$/i', $file ) && ! preg_match( '/\.(?:html?|php)$/i', $file ) ) { return false; }
    if ( preg_match( '/\.php$/i', $file ) && 'index.php' !== strtolower( $file ) ) { return false; }
    if ( isset( $parts['query'] ) ) {
        if ( str_contains( $parts['query'], ';' ) || preg_match( '/%(?:0[0-9a-f]|1[0-9a-f]|7f)/i', $parts['query'] ) ) { return false; }
        parse_str( $parts['query'], $query );
        foreach ( array( 'rest_route', 'feed', 'sitemap', 'sitemap-stylesheet', 'wc-ajax', 'add-to-cart', 'remove_item', '_wpnonce', 'preview_nonce', 'redirect', 'redirect_to', 'redirect_uri', 'return_url', 'next', 'continue' ) as $excluded ) {
            if ( array_key_exists( $excluded, $query ) ) { return false; }
        }
    }
    return true;
}

/** Use an explicit request parameter only; never store a preview credential. */
function ianproject_preview_token() {
    if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! isset( $_GET['woo-share'] ) || ! is_scalar( $_GET['woo-share'] ) ) { return ''; }
    $token = (string) $_GET['woo-share'];
    if ( ! preg_match( '/\A[A-Za-z0-9_-]{16,128}\z/D', $token ) || ! ianproject_preview_page_destination( ianproject_preview_current_uri() ) ) { return ''; }
    return $token;
}

/** Existing preview parameters, including other keys, are preserved without replacement. */
function ianproject_preview_url( $url, $token = null ) {
    $token = null === $token ? ianproject_preview_token() : $token;
    if ( ! is_string( $token ) || ! preg_match( '/\A[A-Za-z0-9_-]{16,128}\z/D', $token ) || ! is_string( $url ) || '' === $url || '#' === $url[0] || ! ianproject_preview_page_destination( $url ) ) { return $url; }
    $parts = wp_parse_url( $url );
    foreach ( explode( '&', $parts['query'] ?? '' ) as $pair ) {
        $name = rawurldecode( explode( '=', $pair, 2 )[0] );
        if ( 'woo-share' === $name || str_starts_with( $name, 'woo-share[' ) ) { return $url; }
    }
    // An absolute destination cannot be redirected off-site by an HTML <base> tag.
    $absolute = WP_Http::make_absolute_url( $url, ianproject_preview_current_uri() );
    if ( ! ianproject_preview_page_destination( $absolute ) ) { return $url; }
    $url = $absolute;
    $fragment_at = strpos( $url, '#' );
    $fragment = false === $fragment_at ? '' : substr( $url, $fragment_at );
    $base = false === $fragment_at ? $url : substr( $url, 0, $fragment_at );
    $separator = false === strpos( $base, '?' ) ? '?' : ( str_ends_with( $base, '?' ) || str_ends_with( $base, '&' ) ? '' : '&' );
    return $base . $separator . 'woo-share=' . rawurlencode( $token ) . $fragment;
}

function ianproject_preview_form_action_allowed( $action, $token ) {
    $action = null === $action || '' === $action ? ianproject_preview_current_uri() : $action;
    if ( ! is_string( $action ) || ! ianproject_preview_page_destination( $action ) ) { return false; }
    $parts = wp_parse_url( $action );
    foreach ( explode( '&', $parts['query'] ?? '' ) as $pair ) {
        $pair = explode( '=', $pair, 2 );
        $name = rawurldecode( $pair[0] );
        if ( str_starts_with( $name, 'woo-share[' ) || ( 'woo-share' === $name && rawurldecode( $pair[1] ?? '' ) !== $token ) ) { return false; }
    }
    return true;
}

/** Add one hidden parameter to complete, local GET forms; leave POST/client-only forms alone. */
function ianproject_preview_get_forms( $html, $token ) {
    if ( false === stripos( $html, '<form' ) ) { return $html; }
    $unsafe_ids = array();
    $scan = new WP_HTML_Tag_Processor( $html );
    while ( $scan->next_tag() ) {
        $form = $scan->get_attribute( 'form' );
        if ( ! is_string( $form ) || '' === $form ) { continue; }
        $override = $scan->get_attribute( 'formaction' );
        $method = $scan->get_attribute( 'formmethod' );
        $name = $scan->get_attribute( 'name' );
        if ( null !== $override || null !== $method || ( is_string( $name ) && ( 'woo-share' === $name || str_starts_with( $name, 'woo-share[' ) ) ) ) { $unsafe_ids[ $form ] = true; }
    }
    $processor = new WP_HTML_Tag_Processor( $html );
    $marker = wp_generate_uuid4();
    $form = null;
    while ( $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
        $tag = $processor->get_tag();
        if ( 'FORM' === $tag ) {
            if ( ! $processor->is_tag_closer() ) {
                if ( null !== $form ) { ++$form['depth']; $form['allowed'] = false; continue; }
                $method = $processor->get_attribute( 'method' );
                $id = $processor->get_attribute( 'id' );
                $allowed = ( null === $method || '' === $method || 'get' === strtolower( (string) $method ) ) && 'quote-builder-form' !== $id && ! isset( $unsafe_ids[ (string) $id ] ) && ianproject_preview_form_action_allowed( $processor->get_attribute( 'action' ), $token );
                $form = array( 'allowed' => $allowed, 'field' => false, 'depth' => 1 );
                $processor->set_bookmark( 'ian-preview-form' );
            } elseif ( null !== $form && 0 === --$form['depth'] ) {
                if ( $form['allowed'] && ! $form['field'] ) {
                    $processor->set_bookmark( 'ian-preview-form-end' );
                    $processor->seek( 'ian-preview-form' );
                    $action = $processor->get_attribute( 'action' );
                    $action = null === $action || '' === $action ? ianproject_preview_current_uri() : $action;
                    $processor->set_attribute( 'action', WP_Http::make_absolute_url( $action, ianproject_preview_current_uri() ) );
                    $processor->set_attribute( 'data-ian-preview-slot', $marker );
                    $processor->seek( 'ian-preview-form-end' );
                    $processor->release_bookmark( 'ian-preview-form-end' );
                }
                $processor->release_bookmark( 'ian-preview-form' );
                $form = null;
            }
            continue;
        }
        if ( null === $form || $processor->is_tag_closer() ) { continue; }
        $name = $processor->get_attribute( 'name' );
        if ( is_string( $name ) && ( 'woo-share' === $name || str_starts_with( $name, 'woo-share[' ) ) ) { $form['field'] = true; }
        $override = $processor->get_attribute( 'formaction' );
        $method = $processor->get_attribute( 'formmethod' );
        // Special submit overrides are left alone rather than risk forwarding access.
        if ( null !== $override || null !== $method ) { $form['allowed'] = false; }
    }
    $html = $processor->get_updated_html();
    return preg_replace_callback( '~<form\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~i', function ( $match ) use ( $marker, $token ) {
        $opening = new WP_HTML_Tag_Processor( $match[0] );
        if ( ! $opening->next_tag( 'FORM' ) || $marker !== $opening->get_attribute( 'data-ian-preview-slot' ) ) { return $match[0]; }
        $opening->remove_attribute( 'data-ian-preview-slot' );
        return $opening->get_updated_html() . '<input type="hidden" name="woo-share" value="' . esc_attr( $token ) . '">';
    }, $html );
}

function ianproject_preserve_preview_html( $html ) {
    $token = ianproject_preview_token();
    if ( ! $token || ! is_string( $html ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) { return $html; }
    $processor = new WP_HTML_Tag_Processor( $html );
    while ( $processor->next_tag( 'A' ) ) {
        $url = $processor->get_attribute( 'href' );
        if ( is_string( $url ) ) {
            $updated = ianproject_preview_url( $url, $token );
            if ( $updated !== $url ) { $processor->set_attribute( 'href', $updated ); }
        }
    }
    return ianproject_preview_get_forms( $processor->get_updated_html(), $token );
}
add_filter( 'render_block', 'ianproject_preserve_preview_html', 20 );
add_filter( 'the_content', 'ianproject_preserve_preview_html', 30 );
add_filter( 'wp_nav_menu_items', 'ianproject_preserve_preview_html', 20 );
add_filter( 'get_search_form', 'ianproject_preserve_preview_html', 20 );
add_filter( 'wp_redirect', 'ianproject_preview_url', 20 );

function ianproject_preview_no_cache() {
    if ( ! ianproject_preview_token() ) { return; }
    if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
    do_action( 'litespeed_control_set_nocache', 'IanProject private preview' );
}
add_action( 'init', 'ianproject_preview_no_cache', 0 );
add_filter( 'wp_headers', function ( $headers ) {
    if ( ! ianproject_preview_token() ) { return $headers; }
    $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate, private';
    $headers['Pragma'] = 'no-cache';
    $headers['Expires'] = 'Wed, 11 Jan 1984 05:00:00 GMT';
    // Cross-origin providers receive the origin, never the private query string.
    // Analytics scripts that explicitly read page_location need their own controls.
    $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';
    $headers['X-Robots-Tag'] = 'noindex, nofollow';
    return $headers;
} );
