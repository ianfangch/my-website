<?php
defined( 'ABSPATH' ) || exit;

function ianproject_catalog_register() {
    register_post_type( 'ian_product', array(
        'labels' => array( 'name' => '产品', 'singular_name' => '产品', 'add_new_item' => '添加产品', 'edit_item' => '编辑产品', 'all_items' => '全部产品' ),
        'public' => true, 'show_in_rest' => true, 'has_archive' => false,
        'menu_icon' => 'dashicons-products', 'rewrite' => array( 'slug' => 'products', 'with_front' => false ),
        'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
        'template' => array( array( 'core/group', array( 'className' => 'container py-5', 'layout' => array( 'type' => 'default' ) ), array( array( 'core/post-title', array( 'level' => 1 ) ), array( 'core/post-excerpt', array( 'moreText' => '' ) ), array( 'core/shortcode', array( 'text' => '[ian_product_price]' ) ), array( 'core/image' ), array( 'core/heading', array( 'level' => 2, 'content' => 'Product Description' ) ), array( 'core/paragraph' ) ) ) ),
    ) );
    register_taxonomy( 'ian_product_category', 'ian_product', array(
        'labels' => array( 'name' => '产品分类', 'singular_name' => '产品分类' ),
        'public' => true, 'show_in_rest' => true, 'hierarchical' => true, 'rewrite' => false,
    ) );

}
add_action( 'init', 'ianproject_catalog_register' );

add_action( 'add_meta_boxes', function () {
    add_meta_box( 'ian-product-fields', '产品目录信息', function ( $post ) {
        wp_nonce_field( 'ian_product_save', 'ian_product_nonce' );
        $data = get_post_meta( $post->ID, '_ian_product_data', true );
        $data = is_array( $data ) ? $data : array();
        echo '<p>标题、摘要和特色图片会同步到产品列表。下列价格会同步到列表及详情的价格区块。规格、图库、FAQ 可在上方区块编辑器修改。</p>';
        $fields = array( 'code' => '型号 / SKU', 'price' => '起价（留空表示暂无价格）', 'priceMax' => '价格上限（可空）', 'currency' => '币种（USD / CNY）', 'unit' => '计价单位，例如 per metre', 'pricePrefix' => '价格前缀，例如 From' );
        foreach ( $fields as $key => $label ) {
            echo '<p><label>' . esc_html( $label ) . '<br><input class="widefat" type="text" name="ian_fields[' . esc_attr( $key ) . ']" value="' . esc_attr( $data[ $key ] ?? '' ) . '"></label></p>';
        }
        echo '<p><label><input type="checkbox" name="ian_quote_only" value="1" ' . checked( ! empty( $data['quoteOnly'] ), true, false ) . '> 仅询价，不显示价格</label></p>';
        echo '<p>封面：在右侧“特色图片”中替换。新产品的分类也在右侧选择。</p>';
    }, 'ian_product', 'normal', 'high' );
} );

add_action( 'save_post_ian_product', function ( $post_id ) {
    if ( ! isset( $_POST['ian_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ian_product_nonce'] ) ), 'ian_product_save' ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) { return; }
    $data = get_post_meta( $post_id, '_ian_product_data', true );
    $data = is_array( $data ) ? $data : array();
    $fields = isset( $_POST['ian_fields'] ) && is_array( $_POST['ian_fields'] ) ? wp_unslash( $_POST['ian_fields'] ) : array();
    foreach ( array( 'code', 'unit', 'pricePrefix' ) as $key ) { $data[ $key ] = sanitize_text_field( $fields[ $key ] ?? '' ); }
    $data['currency'] = in_array( $fields['currency'] ?? '', array( 'USD', 'CNY' ), true ) ? $fields['currency'] : 'USD';
    foreach ( array( 'price', 'priceMax' ) as $key ) { $value = $fields[ $key ] ?? ''; $data[ $key ] = is_numeric( $value ) && (float) $value >= 0 ? (float) $value : null; }
    $data['quoteOnly'] = ! empty( $_POST['ian_quote_only'] );
    update_post_meta( $post_id, '_ian_product_data', $data );
    update_post_meta( $post_id, '_ian_sku', $data['code'] ?? '' );
    update_post_meta( $post_id, '_ian_price_usd', ianproject_price_value( $data ) );
} );

function ianproject_price_value( $data ) {
    $price = isset( $data['price'] ) && is_numeric( $data['price'] ) ? (float) $data['price'] : 0;
    return ( $data['currency'] ?? 'USD' ) === 'CNY' ? $price / 6.78 : $price;
}

function ianproject_price_html( $post_id ) {
    $data = get_post_meta( $post_id, '_ian_product_data', true );
    $data = is_array( $data ) ? $data : array();
    if ( ! empty( $data['quoteOnly'] ) ) { return '<div class="catalog-price catalog-price-pending"><strong>Request a Quote</strong></div>'; }
    if ( ! isset( $data['price'] ) || ! is_numeric( $data['price'] ) ) { return '<div class="catalog-price catalog-price-pending"><strong>Price on request</strong></div>'; }
    $value = ianproject_price_value( $data );
    $price = 'USD ' . number_format_i18n( $value, 2 );
    if ( isset( $data['priceMax'] ) && is_numeric( $data['priceMax'] ) && $data['priceMax'] > $data['price'] ) {
        $max = (float) $data['priceMax'] / ( ( $data['currency'] ?? 'USD' ) === 'CNY' ? 6.78 : 1 );
        $price .= ' – ' . number_format_i18n( $max, 2 );
    }
    return '<div class="catalog-price"><span>' . esc_html( $data['pricePrefix'] ?? 'From' ) . '</span><strong>' . esc_html( $price ) . '</strong><small>' . esc_html( $data['unit'] ?? $data['priceUnit'] ?? '' ) . '</small></div>';
}
add_shortcode( 'ian_product_price', function () { return ianproject_price_html( get_the_ID() ); } );
add_shortcode( 'ian_product_meta', function () {
    $terms = wp_get_post_terms( get_the_ID(), 'ian_product_category', array( 'fields' => 'names' ) );
    $data = get_post_meta( get_the_ID(), '_ian_product_data', true );
    return '<p class="text-uppercase text-primary mb-2">' . esc_html( ( ! is_wp_error( $terms ) ? implode( ', ', $terms ) : '' ) . ' / ' . ( $data['code'] ?? '' ) ) . '</p>';
} );
