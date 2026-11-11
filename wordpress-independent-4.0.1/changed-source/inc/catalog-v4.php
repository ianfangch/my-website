<?php
defined( 'ABSPATH' ) || exit;

function ian4_hidden_categories() {
    $terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'meta_query' => array( array( 'key' => '_ian_hide_catalog', 'value' => '1' ) ), 'fields' => 'ids' ) );
    if ( is_wp_error( $terms ) ) { return array(); }
    foreach ( $terms as $id ) { $children = get_term_children( $id, 'product_cat' ); if ( ! is_wp_error( $children ) ) { $terms = array_merge( $terms, $children ); } }
    return array_unique( array_map( 'absint', $terms ) );
}
function ian4_catalog_args( $args = array() ) {
    if(!function_exists('wc_get_product'))return array('post_type'=>'product','post__in'=>array(0));
    $tax = array( 'relation' => 'AND' ); $hidden = ian4_hidden_categories();
    if ( $hidden ) { $tax[] = array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $hidden, 'operator' => 'NOT IN', 'include_children' => true ); }
    $visibility = wc_get_product_visibility_term_ids(); $visibility_key = ! empty( $args['s'] ) ? 'exclude-from-search' : 'exclude-from-catalog'; $exclude = array_filter( array( $visibility[ $visibility_key ] ?? 0 ) );
    if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) { $exclude[] = $visibility['outofstock'] ?? 0; }
    if ( $exclude ) { $tax[] = array( 'taxonomy' => 'product_visibility', 'field' => 'term_taxonomy_id', 'terms' => $exclude, 'operator' => 'NOT IN' ); }
    return array_merge( array( 'post_type' => 'product', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 12, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ), 'tax_query' => $tax ), $args );
}
function ian4_visible_terms() {
    if ( ! taxonomy_exists( 'product_cat' ) ) { return array(); }
    $terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) ); if ( is_wp_error( $terms ) ) { return array(); }
    $hidden = ian4_hidden_categories(); $visible = array();
    foreach ( $terms as $term ) {
        if ( in_array( $term->term_id, $hidden, true ) ) { continue; }
        $args = ian4_catalog_args( array( 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true ) ); $args['tax_query'][] = array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $term->term_id );
        if ( get_posts( $args ) ) { $visible[] = $term; }
    }
    return $visible;
}
function ian4_products_url() { if ( function_exists( 'ian3_url' ) ) { return ian3_url( 'products' ); } if ( is_page() && has_shortcode( get_post_field( 'post_content', get_queried_object_id() ), 'ian_catalog' ) ) { return get_permalink(); } return wc_get_page_permalink( 'shop' ); }
function ian4_contact_url( $id = 0 ) { $url = function_exists( 'ian3_url' ) ? ian3_url( 'contact' ) : home_url( '/contact/' ); return $id ? add_query_arg( 'product', $id, $url ) : $url; }
function ian4_price_html( $id ) { $p=wc_get_product($id); if(!$p)return ''; return '<div class="catalog-price"><strong>'.esc_html(ian4_price_text(ian4_pricing($p))).'</strong></div>'; }
add_shortcode( 'ian_product_price', function () { return ian4_price_html( get_the_ID() ); } );
add_shortcode( 'ian_product_meta', function () {
    $id = get_the_ID(); $p = wc_get_product( $id ); $terms = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'names' ) );
    return '<p class="text-uppercase text-primary mb-2">' . esc_html( ( is_wp_error( $terms ) ? '' : implode( ', ', $terms ) ) . ( $p && $p->get_sku() ? ' / ' . $p->get_sku() : '' ) ) . '</p>';
} );
function ian4_product_card( $id ) {
    $p = wc_get_product( $id ); if ( ! $p ) { return ''; }
    $terms = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'names' ) );
    return '<article class="catalog-card"><a class="catalog-card-image" href="' . esc_url( get_permalink( $id ) ) . '">' . $p->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy', 'decoding' => 'async' ) ) . '</a><div class="catalog-card-body"><p class="catalog-card-meta">' . esc_html( ( is_wp_error( $terms ) ? '' : implode( ', ', $terms ) ) . ( $p->get_sku() ? ' / ' . $p->get_sku() : '' ) ) . '</p><h3><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $p->get_name() ) . '</a></h3><p>' . esc_html( wp_trim_words( wp_strip_all_tags( $p->get_short_description() ), 25 ) ) . '</p>' . ian4_price_html( $id ) . '<a class="catalog-details" href="' . esc_url( get_permalink( $id ) ) . '">View Details ↗</a></div></article>';
}
add_filter( 'posts_search', function ( $search, $query ) {
    if ( ! $query->get( 'ian4_search' ) || ! $query->get( 's' ) ) { return $search; }
    global $wpdb; $like = '%' . $wpdb->esc_like( $query->get( 's' ) ) . '%';
    return $wpdb->prepare( " AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} ian4_sku WHERE ian4_sku.post_id={$wpdb->posts}.ID AND ian4_sku.meta_key IN ('_sku','_ian4_model') AND ian4_sku.meta_value LIKE %s) OR EXISTS (SELECT 1 FROM {$wpdb->posts} ian4_child INNER JOIN {$wpdb->postmeta} ian4_model ON ian4_model.post_id=ian4_child.ID WHERE ian4_child.post_parent={$wpdb->posts}.ID AND ian4_child.post_type='product_variation' AND ian4_child.post_status='publish' AND ian4_model.meta_key IN ('_sku','_ian4_model') AND ian4_model.meta_value LIKE %s)) AND {$wpdb->posts}.post_password='' ", $like, $like, $like, $like, $like );
}, 10, 2 );
function ian4_query_text( $key ) { return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; }
add_shortcode( 'ian_catalog', function ( $attrs ) {
    if(!function_exists('wc_get_product'))return '<p>Explore product requirements with '.ian3_link('contact','Contact').'.</p>';
    $attrs = shortcode_atts( array( 'category' => '' ), $attrs, 'ian_catalog' ); $fixed = sanitize_title( $attrs['category'] );
    $category = $fixed ?: sanitize_title( ian4_query_text( 'category' ) ); if ( ! $category && is_tax( 'product_cat' ) ) { $category = get_queried_object()->slug; }
    $search = ian4_query_text( 'catalog_search' ); $sort = sanitize_key( ian4_query_text( 'catalog_sort' ) ); $page = max( 1, absint( $_GET['catalog_page'] ?? 1 ) );
    $args = ian4_catalog_args( array( 'paged' => $page, 's' => $search, 'ian4_search' => true ) );
    if ( $category ) { $args['tax_query'][] = array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $category ); }
    if ( 'name' === $sort ) { $args['orderby'] = 'title'; $args['order'] = 'ASC'; }
    if ( in_array( $sort, array( 'price-asc', 'price-desc' ), true ) && get_option( 'ian_catalog_show_prices' ) ) { $args['meta_key'] = '_price'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'price-asc' === $sort ? 'ASC' : 'DESC'; }
    $query = new WP_Query( $args ); $terms = ian4_visible_terms(); $url = ian4_products_url();
    ob_start(); ?>
    <section class="ian-catalog" id="catalog" aria-label="Product catalogue">
    <?php if ( ! $fixed ) : ?><nav class="catalog-filters" aria-label="Product categories"><a href="<?php echo esc_url( $url . '#catalog' ); ?>" class="catalog-filter <?php echo ! $category ? 'active' : ''; ?>">All Products</a><?php foreach ( $terms as $term ) : ?><a class="catalog-filter <?php echo $category === $term->slug ? 'active' : ''; ?>" href="<?php echo esc_url( add_query_arg( ianproject_query_values( array( 'category' => $term->slug, 'catalog_search' => $search ) ), $url ) . '#catalog' ); ?>"><?php echo esc_html( $term->name ); ?></a><?php endforeach; ?></nav><?php endif; ?>
    <form class="catalog-controls" method="get" action="<?php echo esc_url( $url ); ?>#catalog"><?php if ( $category ) : ?><input type="hidden" name="category" value="<?php echo esc_attr( $category ); ?>"><?php endif; ?><div><label for="catalog-search">Search products or model code</label><input type="search" id="catalog-search" name="catalog_search" value="<?php echo esc_attr( $search ); ?>"></div><div><label for="catalog-sort">Sort by</label><select id="catalog-sort" name="catalog_sort"><option value="">Recommended</option><option value="name" <?php selected( $sort, 'name' ); ?>>Name</option><?php if ( get_option( 'ian_catalog_show_prices' ) ) : ?><option value="price-asc" <?php selected( $sort, 'price-asc' ); ?>>Price: low to high</option><option value="price-desc" <?php selected( $sort, 'price-desc' ); ?>>Price: high to low</option><?php endif; ?></select></div><button type="submit">Search</button></form>
    <p class="catalog-count" role="status"><?php echo esc_html( $query->found_posts . ' products' ); ?><?php if ( $search || $category ) : ?> · <a href="<?php echo esc_url( $url . '#catalog' ); ?>">Clear filters</a><?php endif; ?></p><div class="catalog-grid"><?php foreach ( $query->posts as $p ) { echo ian4_product_card( $p->ID ); } ?></div>
    <?php if ( ! $query->found_posts ) : ?><div class="catalog-empty"><h3><?php echo $search || $category ? 'No matching products.' : 'Let’s find the products for your project.'; ?></h3><p>Share your product list or requirements with IanProject.</p><a href="<?php echo esc_url( ian4_contact_url() ); ?>">Discuss Your Requirements ↗</a></div><?php endif; ?>
    <?php if ( $query->max_num_pages > 1 ) : ?><nav class="ian-pagination" aria-label="Product pages"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'catalog_page', '%#%', $url ), 'current' => $page, 'total' => $query->max_num_pages, 'add_args' => ianproject_query_values( array( 'category' => $category, 'catalog_search' => $search, 'catalog_sort' => $sort ) ), 'add_fragment' => '#catalog' ) ) ); ?></nav><?php endif; ?></section>
    <?php return ob_get_clean();
} );
/** Category copy comes from WooCommerce; a short neutral fallback keeps new cards complete. */
function ian4_category_summary( $term ) {
    $description = trim( wp_strip_all_tags( preg_replace( '~</p>\s*<p[^>]*>~i', ' ', $term->description ) ) );
    if ( $description ) { return wp_trim_words( $description, 28 ); }
    $defaults = array(
        'accessories' => 'Storage organisers, cabinet fittings and hardware to complete your furniture and interior products.',
        'cabinetry' => 'Kitchen cabinets, wardrobes and fitted joinery, with materials and configurations reviewed for your project.',
        'cabinetry-joinery' => 'Kitchen cabinets, wardrobes and fitted joinery, with materials and configurations reviewed for your project.',
        'carpet' => 'Carpet tiles and soft flooring options for homes, offices, hospitality and commercial interiors.',
        'carpets' => 'Carpet tiles and soft flooring options for homes, offices, hospitality and commercial interiors.',
        'countertops' => 'Worktops and surface materials for kitchens, bathrooms and interior spaces. Discuss colours, dimensions and finishes.',
        'flooring' => 'Flooring options for residential and commercial spaces. Compare materials, finishes and project requirements.',
        'furniture' => 'Furniture for living, dining, bedrooms and commercial spaces, coordinated around your project requirements.',
        'sinks' => 'Kitchen sinks in a range of sizes and configurations. Confirm installation type and dimensions before quotation.',
    );
    return $defaults[ $term->slug ] ?? sprintf( 'Explore %s options. Share the models, quantities and specifications you need for your project quotation.', $term->name );
}
add_shortcode( 'ian_home_product_categories', function () {
    if ( function_exists( 'ian3_visible' ) && ! ian3_visible( 'products' ) ) { return ''; }
    $terms = ian4_visible_terms(); if ( ! $terms ) { return '<p class="ih-section-intro">Share your product requirements to explore suitable options. <a href="' . esc_url( ian4_contact_url() ) . '">Contact IanProject ↗</a></p>'; }
    $html = '<div class="ih-product-grid">';
    foreach ( $terms as $term ) {
        $image = wp_get_attachment_image( absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) ), 'large', false, array( 'loading' => 'lazy' ) );
        $html .= '<a class="ih-product-card" href="' . esc_url( add_query_arg( 'category', $term->slug, ian4_products_url() ) . '#catalog' ) . '">' . ( $image ?: '<span class="ian-category-placeholder">Explore category</span>' ) . '<div><h3>' . esc_html( $term->name ) . '</h3><p>' . esc_html( ian4_category_summary( $term ) ) . '</p><span class="ih-category-link">Explore products <span aria-hidden="true">↗</span></span></div></a>';
    }
    $html .= '<a class="ih-product-card ih-category-more" href="' . esc_url( ian4_contact_url() ) . '"><p class="ih-more-kicker">MORE PRODUCTS</p><h3>Continuously<br>updated.</h3><p>Looking for something else? Share the product or specification you need.</p><span class="ih-category-link">Tell us what you need <span aria-hidden="true">↗</span></span></a>';
    return $html . '</div>';
} );

add_action( 'product_cat_add_form_fields', function () { wp_nonce_field( 'ian4_term', 'ian4_term_nonce' ); echo '<div class="form-field"><label><input type="checkbox" name="ian4_hide" value="1"> 从 IanProject 目录隐藏此分类及子分类</label><p>不删除产品，仅隐藏目录、筛选、菜单及首页入口。</p></div>'; } );
add_action( 'product_cat_edit_form_fields', function ( $term ) { ?><tr class="form-field"><th>IanProject 目录显示</th><td><?php wp_nonce_field( 'ian4_term', 'ian4_term_nonce' ); ?><label><input type="checkbox" name="ian4_hide" value="1" <?php checked( get_term_meta( $term->term_id, '_ian_hide_catalog', true ), '1' ); ?>>从目录隐藏此分类及子分类</label><p class="description">原产品链接仍可访问；完全不公开请将产品设为草稿。</p></td></tr><?php } );
function ian4_save_term( $id ) {
    if ( ! current_user_can( 'manage_product_terms' ) || ! isset( $_POST['ian4_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ian4_term_nonce'] ) ), 'ian4_term' ) ) { return; }
    update_term_meta( $id, '_ian_hide_catalog', isset( $_POST['ian4_hide'] ) ? '1' : '0' ); do_action( 'litespeed_purge_all' );
}
add_action( 'created_product_cat', 'ian4_save_term' ); add_action( 'edited_product_cat', 'ian4_save_term' );
