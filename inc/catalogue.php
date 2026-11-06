<?php
defined( 'ABSPATH' ) || exit;

function ianproject_query_text( $key ) {
    return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
}

// Extend catalogue keyword search to model numbers without altering other searches.
add_filter( 'posts_search', function ( $search, $query ) {
    if ( ! $query->get( 'ian_catalog_search' ) || ! $query->get( 's' ) ) { return $search; }
    global $wpdb;
    $like = '%' . $wpdb->esc_like( $query->get( 's' ) ) . '%';
    $search = $wpdb->prepare( " AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} ian_sku WHERE ian_sku.post_id = {$wpdb->posts}.ID AND ian_sku.meta_key = '_ian_sku' AND ian_sku.meta_value LIKE %s)) ", $like, $like, $like, $like );
    if ( ! is_user_logged_in() ) { $search .= " AND {$wpdb->posts}.post_password = '' "; }
    return $search;
}, 10, 2 );

add_shortcode( 'ian_catalog', function ( $attributes ) {
    $attributes = shortcode_atts( array( 'category' => '' ), $attributes, 'ian_catalog' );
    $fixed_category = sanitize_title( $attributes['category'] );
    $category = $fixed_category ?: sanitize_title( ianproject_query_text( 'category' ) );
    $search = ianproject_query_text( 'catalog_search' );
    $sort = sanitize_key( ianproject_query_text( 'catalog_sort' ) );
    $current = max( 1, absint( $_GET['catalog_page'] ?? 1 ) );
    $args = array( 'post_type' => 'ian_product', 'post_status' => 'publish', 'posts_per_page' => 24, 'paged' => $current, 's' => $search, 'ian_catalog_search' => true, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
    if ( $category && 'all' !== $category ) { $args['tax_query'] = array( array( 'taxonomy' => 'ian_product_category', 'field' => 'slug', 'terms' => $category ) ); }
    if ( in_array( $sort, array( 'price-asc', 'price-desc' ), true ) ) {
        $args['meta_key'] = '_ian_price_usd'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'price-asc' === $sort ? 'ASC' : 'DESC';
    } elseif ( 'name' === $sort ) { $args['orderby'] = 'title'; $args['order'] = 'ASC'; }
    $query = new WP_Query( $args );
    $terms = get_terms( array( 'taxonomy' => 'ian_product_category', 'hide_empty' => true, 'orderby' => 'term_id' ) );
    $page_url = get_permalink();
    ob_start(); ?>
    <section id="catalog" class="ian-catalog container py-4" aria-label="Product catalogue">
    <?php if ( ! $fixed_category && ! is_wp_error( $terms ) ) : ?>
        <div class="catalog-filters d-flex flex-wrap gap-2 mb-4" aria-label="Product categories">
            <a class="catalog-filter <?php echo ! $category || 'all' === $category ? 'active' : ''; ?>" href="<?php echo esc_url( $page_url . '#catalog' ); ?>">All Products</a>
            <?php foreach ( $terms as $term ) : ?>
                <a class="catalog-filter <?php echo $category === $term->slug ? 'active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'category', $term->slug, $page_url ) . '#catalog' ); ?>" <?php echo $category === $term->slug ? 'aria-current="true"' : ''; ?>><?php echo esc_html( $term->name ); ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
        <form method="get" action="<?php echo esc_url( $page_url ); ?>#catalog" class="row g-3 mb-4 align-items-end">
            <?php if ( $category ) : ?><input type="hidden" name="category" value="<?php echo esc_attr( $category ); ?>"><?php endif; ?>
            <div class="col-md-7"><label class="form-label" for="ian-catalog-search">Search products or model code</label><input class="form-control" type="search" name="catalog_search" id="ian-catalog-search" value="<?php echo esc_attr( $search ); ?>"></div>
            <div class="col-md-3"><label class="form-label" for="ian-catalog-sort">Sort by</label><select class="form-select" name="catalog_sort" id="ian-catalog-sort">
                <?php foreach ( array( '' => 'Recommended', 'name' => 'Name', 'price-asc' => 'Price: low to high', 'price-desc' => 'Price: high to low' ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $sort, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
            </select></div><div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Search</button></div>
        </form>
        <p class="text-muted" role="status"><?php echo esc_html( $query->found_posts . ' products' ); ?></p>
        <div class="catalog-grid">
        <?php while ( $query->have_posts() ) : $query->the_post();
            $data = get_post_meta( get_the_ID(), '_ian_product_data', true );
            $data = is_array( $data ) ? $data : array();
            $image = get_the_post_thumbnail_url( get_the_ID(), 'large' );
            if ( ! $image && ! empty( $data['image'] ) ) { $image = ianproject_tokens( $data['image'] ); }
            $names = wp_get_post_terms( get_the_ID(), 'ian_product_category', array( 'fields' => 'names' ) ); ?>
            <article class="catalog-card">
                <a class="catalog-card-image" href="<?php the_permalink(); ?>"><?php if ( $image ) : ?><img loading="lazy" decoding="async" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>"><?php else : ?><span>View product</span><?php endif; ?></a>
                <div class="catalog-card-body"><p class="catalog-card-meta"><?php echo esc_html( ( ! is_wp_error( $names ) ? implode( ', ', $names ) : '' ) . ( empty( $data['code'] ) ? '' : ' / ' . $data['code'] ) ); ?></p>
                <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3><p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 28 ) ); ?></p>
                <?php echo ianproject_price_html( get_the_ID() ); // Escaped by helper. ?>
                <a class="btn btn-outline-primary mt-3" href="<?php the_permalink(); ?>">View Details</a></div>
            </article>
        <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <?php if ( ! $query->found_posts ) : ?><p class="catalog-empty">No matching products. Try another product name or model code.</p><?php endif; ?>
        <?php if ( $query->max_num_pages > 1 ) : ?>
        <nav class="ian-pagination d-flex flex-wrap gap-2 justify-content-center my-5" aria-label="Catalogue pages">
            <?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'catalog_page', '%#%', $page_url ), 'format' => '', 'current' => $current, 'total' => $query->max_num_pages, 'add_args' => array( 'category' => $category, 'catalog_search' => $search, 'catalog_sort' => $sort ), 'add_fragment' => '#catalog' ) ) ); ?>
        </nav>
        <?php endif; ?>
    </section>
    <?php return ob_get_clean();
} );

add_shortcode( 'ian_quote_form', function () {
    $file = get_theme_file_path( 'data/quote-form.html' );
    return is_readable( $file ) ? file_get_contents( $file ) : '';
} );
