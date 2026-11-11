<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/catalog-v4.php';

function ian4_load_cart() {
    if ( ! function_exists( 'WC' ) ) { return false; }
    if ( null === WC()->cart && did_action( 'woocommerce_init' ) ) { wc_load_cart(); }
    if ( WC()->cart ) { WC()->cart->get_cart(); }
    return (bool) WC()->cart;
}
function ian4_cart_state() {
    $state = array( 'items' => array(), 'products' => array(), 'assets' => array() );
    if ( ! ian4_load_cart() ) { return $state; }
    foreach ( WC()->cart->get_cart() as $key => $item ) {
        $p = wc_get_product( $item['product_id'] ); if ( ! $p ) { continue; }
        $pk = 'p' . $p->get_id();
        if ( ! isset( $state['products'][ $pk ] ) ) { $state['products'][ $pk ] = ian4_product_payload( $p, $state['assets'] ); }
        $model = null;
        foreach ( $state['products'][ $pk ]['models'] as $index => $variation ) { if ( $variation['id'] === (int) ( $item['variation_id'] ?? 0 ) ) { $model = $index; break; } }
        $state['items'][] = array( 'key' => $pk, 'model' => $model, 'qty' => (float) $item['quantity'], 'cartKey' => $key );
    }
    return $state;
}
function ian4_quote_product( $product ) { return $product && ( ian4_meta( $product, 'quote_only', false ) || ( $product->is_type( 'variation' ) && ian4_meta( $product->get_parent_id(), 'quote_only', false ) ) ); }
foreach ( array( 'woocommerce_is_purchasable', 'woocommerce_variation_is_purchasable' ) as $hook ) {
    add_filter( $hook, function ( $value, $product ) {
        if ( ! ian4_quote_product( $product ) ) { return $value; }
        $parent = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product;
        return 'publish' === $product->get_status() && $parent && 'publish' === $parent->get_status() && $product->is_in_stock();
    }, 90, 2 );
}
add_filter( 'woocommerce_get_checkout_url', function ( $url ) { return function_exists( 'ian3_url' ) ? add_query_arg( 'cart', '1', ian3_url( 'contact' ) ) : $url; } );
add_action( 'template_redirect', function () { if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url() ) { wp_safe_redirect( add_query_arg( 'cart', '1', ian3_url( 'contact' ) ), 302 ); exit; } }, 4 );
// This site's cart gathers quotations; website checkout must not create orders.
add_action( 'woocommerce_checkout_process', function () { wc_add_notice( 'Please continue to Contact to confirm your quotation. Online checkout is not available.', 'error' ); } );
add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
    if ( 'OPTIONS' !== $request->get_method() && preg_match( '~^/wc/store/v[0-9]+/checkout(?:/|$)~', $request->get_route() ) ) {
        return new WP_Error( 'ian_contact_checkout', 'Please continue to Contact to confirm your quotation.', array( 'status' => 409, 'contact' => add_query_arg( 'cart', '1', ian3_url( 'contact' ) ) ) );
    }
    return $result;
}, 10, 3 );

function ian4_cart_request( $data ) {
    if ( ! ian4_load_cart() ) { return new WP_Error( 'ian_cart', 'The cart is unavailable. Please contact us.' ); }
    $op = sanitize_key( $data['op'] ?? 'get' );
    if ( in_array( $op, array( 'update', 'remove' ), true ) ) {
        $key = sanitize_text_field( $data['key'] ?? '' ); $item = WC()->cart->get_cart_item( $key );
        if ( ! $item ) { return new WP_Error( 'ian_cart_item', 'This item is no longer in your cart. Refresh and try again.' ); }
        if ( 'remove' === $op ) { WC()->cart->remove_cart_item( $key ); }
        else {
            $p = wc_get_product( $item['product_id'] ); $qty = ian4_quantity( $p, $data['qty'] ?? 1 );
            if ( ! $item['data']->has_enough_stock( $qty ) ) { return new WP_Error( 'ian_stock', 'Please contact us to confirm this quantity.' ); }
            WC()->cart->set_quantity( $key, $qty );
        }
    } elseif ( in_array( $op, array( 'add', 'contact' ), true ) ) {
        $id = absint( $data['product'] ?? 0 ); $variation = absint( $data['variation'] ?? 0 ); $p = wc_get_product( $id );
        if ( ! $p || 'publish' !== $p->get_status() || $p->is_type( 'variation' ) ) { return new WP_Error( 'ian_product', 'This product is unavailable.' ); }
        $attrs = array();
        if ( $p->is_type( 'variable' ) ) {
            $v = wc_get_product( $variation );
            if ( ! $v || ! $v->is_type( 'variation' ) || $v->get_parent_id() !== $id || 'publish' !== $v->get_status() || ! $v->variation_is_active() ) { return new WP_Error( 'ian_variation', 'Please select an available configuration.' ); }
            $attrs = $v->get_variation_attributes();
        } elseif ( ! $p->is_type( 'simple' ) ) { return new WP_Error( 'ian_type', 'Please contact us about this product.' ); }
        $qty = ian4_quantity( $p, $data['qty'] ?? 1 );
        $existing = '';
        if ( 'contact' === $op ) { foreach ( WC()->cart->get_cart() as $key => $item ) { if ( (int) $item['product_id'] === $id && (int) ( $item['variation_id'] ?? 0 ) === $variation ) { $existing = $key; break; } } }
        if ( $existing ) {
            $item = WC()->cart->get_cart_item( $existing );
            if ( ! $item['data']->has_enough_stock( $qty ) ) { return new WP_Error( 'ian_stock', 'Please contact us to confirm this quantity.' ); }
            WC()->cart->set_quantity( $existing, $qty );
        } elseif ( ! WC()->cart->add_to_cart( $id, $qty, $variation, $attrs ) ) {
            $notices = wc_get_notices( 'error' ); wc_clear_notices();
            return new WP_Error( 'ian_add_cart', $notices ? wp_strip_all_tags( $notices[0]['notice'] ) : 'Unable to add this configuration. Please contact us.' );
        }
    } elseif ( 'get' !== $op ) { return new WP_Error( 'ian_cart_action', 'Unsupported cart action.' ); }
    WC()->cart->calculate_totals(); WC()->cart->set_session();
    return ian4_cart_state();
}
function ian4_quantity( $product, $value ) { $step = 'metre' === ian4_meta( $product, 'unit', 'unit' ) ? 0.1 : 1; return max( $step, round( (float) $value / $step ) * $step ); }
foreach ( array( 'wp_ajax_ian4_cart', 'wp_ajax_nopriv_ian4_cart' ) as $hook ) {
    add_action( $hook, function () {
        check_ajax_referer( 'ian4_cart', 'nonce' ); $input = array();
        foreach ( array( 'op', 'key', 'qty', 'product', 'variation' ) as $name ) { if ( isset( $_POST[ $name ] ) && is_scalar( $_POST[ $name ] ) ) { $input[ $name ] = wp_unslash( $_POST[ $name ] ); } }
        $r = ian4_cart_request( $input ); if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ), 400 ); } wp_send_json_success( $r );
    } );
}
function ian4_product_view() {
    if ( ! function_exists( 'wc_get_product' ) || ! ( $p = wc_get_product( get_queried_object_id() ) ) ) { return ''; }
    $state = ian4_cart_state(); $product = ian4_product_payload( $p, $state['assets'] ); $state['products'][ $product['key'] ] = $product;
    $html = file_get_contents( get_theme_file_path( 'parts/product-v4.html' ) );
    $html = str_replace( array( '%%TITLE%%', '%%SUMMARY%%', '%%CATEGORY%%', '%%DESCRIPTION%%', '%%PRODUCTS_URL%%' ), array( esc_html( $product['name'] ), esc_html( $product['summary'] ), esc_html( $product['category'] ), apply_filters( 'the_content', $p->get_description() ), esc_url(ian3_url('products')) ), $html );
    $data = array( 'data' => array( 'products' => $state['products'], 'assets' => $state['assets'] ), 'key' => $product['key'], 'state' => $state, 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ian4_cart' ), 'contact' => add_query_arg( 'cart', '1', ian3_url( 'contact' ) ) );
    wp_enqueue_script( 'ian-commerce', get_theme_file_uri( 'assets/js/commerce-v4.js' ), array(), IANPROJECT_VERSION, true );
    return '<div class="ian-commerce">' . $html . '<p class="shell" id="commerce-status" role="alert"></p><script type="application/json" id="ian4-product-data">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script><noscript><p class="shell">' . ian3_link( 'contact', 'Contact us about this product', 'button' ) . '</p></noscript></div>';
}
function ian4_cart_summary_text() {
    $state = ian4_cart_state(); $rows = array();
    foreach ( $state['items'] as $i ) {
        $p = $state['products'][ $i['key'] ]; $m = null !== $i['model'] ? $p['models'][ $i['model'] ] : null; $price = $m['pricing'] ?? $p['pricing'];
        $rows[] = 'Product: ' . $p['name'] . "\nConfiguration: " . ( $m['label'] ?? $p['code'] ) . "\nQuantity: " . $i['qty'] . ' ' . $p['pricing']['quantityUnit'] . "\nReference amount: " . ian4_price_text( $price, $i['qty'] );
    }
    return implode( "\n\n", $rows );
}
function ian4_cart_view() {
    $state = ian4_cart_state(); $html = '<div class="ian-commerce"><main class="shell section"><h1>Your project cart</h1><p>Review the selected configurations, then continue to Contact to confirm specifications, final pricing and delivery.</p>' . ( function_exists( 'wc_print_notices' ) ? wc_print_notices( true ) : '' );
    if ( ! $state['items'] ) { return $html . '<p class="cart-empty">Your cart is empty.</p>' . ian3_link( 'products', 'Explore products', 'button' ) . '</main></div>'; }
    $html .= '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ian4_cart_update">'.wp_nonce_field('ian4_cart_update','ian4_cart_nonce',true,false).'<div class="native-cart-scroll"><table class="native-cart-table"><thead><tr><th>Product</th><th>Configuration</th><th>Quantity</th><th>Reference amount</th><th></th></tr></thead><tbody>';
    foreach ( $state['items'] as $i ) { $p = $state['products'][ $i['key'] ]; $m = null !== $i['model'] ? $p['models'][ $i['model'] ] : null; $html .= '<tr><td><a href="' . esc_url( get_permalink( $p['id'] ) ) . '">' . esc_html( $p['name'] ) . '</a></td><td>' . esc_html( $m['label'] ?? $p['code'] ) . '</td><td><input class="cart-quantity" type="number" name="cart_qty['.esc_attr($i['cartKey']).']" min="'.esc_attr($p['pricing']['step']).'" step="'.esc_attr($p['pricing']['step']).'" value="'.esc_attr($i['qty']).'"> ' . esc_html($p['pricing']['quantityUnit']) . '</td><td>' . esc_html( ian4_price_text( $m['pricing'] ?? $p['pricing'], $i['qty'] ) ) . '</td><td><button class="view-cart-link" name="remove" value="'.esc_attr($i['cartKey']).'">Remove</button></td></tr>'; }
    return $html . '</tbody></table></div><button class="button button-outline" name="update" style="margin-top:20px">Update quantities</button></form><div class="native-cart-actions"><a class="button" href="' . esc_url( add_query_arg( 'cart', '1', ian3_url( 'contact' ) ) ) . '">Continue to Contact</a>' . ian3_link( 'products', 'Continue browsing', 'text-link' ) . '</div><p>Shipping, taxes and final customisation charges are confirmed in the quotation. No online payment is collected.</p></main></div>';
}
add_filter( 'the_content', function ( $content ) { return function_exists( 'is_cart' ) && is_cart() && get_the_ID() === (int)get_option('woocommerce_cart_page_id') ? ian4_cart_view() : $content; }, 100 );
add_action( 'wp_enqueue_scripts', function () { if ( function_exists( 'WC' ) ) { wp_enqueue_style( 'ian-commerce', get_theme_file_uri( 'assets/css/commerce-v4.css' ), array( 'ian-typography' ), IANPROJECT_VERSION ); } }, 70 );
add_action( 'init', function () {
    add_shortcode( 'ian_wc_product', 'ian4_product_view' );
    add_shortcode( 'ian_product_price', function () { return function_exists( 'wc_get_product' ) ? ian4_price_html( get_the_ID() ) : ''; } );
}, 1000 );

foreach(array('admin_post_ian4_cart_update','admin_post_nopriv_ian4_cart_update') as $hook){add_action($hook,function(){check_admin_referer('ian4_cart_update','ian4_cart_nonce');if(isset($_POST['remove'])&&is_scalar($_POST['remove'])){$r=ian4_cart_request(array('op'=>'remove','key'=>sanitize_text_field(wp_unslash($_POST['remove']))));}else{foreach((array)($_POST['cart_qty']??array()) as $key=>$qty){if(!is_scalar($qty))continue;$r=ian4_cart_request(array('op'=>'update','key'=>sanitize_text_field($key),'qty'=>wp_unslash($qty)));if(is_wp_error($r))break;}}if(isset($r)&&is_wp_error($r))wc_add_notice($r->get_error_message(),'error');wp_safe_redirect(wc_get_cart_url());exit;});}

add_filter('pre_do_shortcode_tag',function($value,$tag){return 'ian_page_heading'===$tag&&function_exists('is_cart')&&is_cart()?'':$value;},10,2);
