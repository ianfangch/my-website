<?php
defined( 'ABSPATH' ) || exit;

function ian3_roles() {
    return array( 'home' => 'Home', 'products' => 'Products', 'project' => 'Project', 'insights' => 'Insights', 'about' => 'IanProject', 'factory' => 'Factory', 'showroom' => 'Showroom', 'services' => 'Services', 'contact' => 'Contact', 'privacy' => 'Privacy Policy', 'terms' => 'Terms of Use' );
}
function ian3_id( $role ) { $map = (array) get_option( 'ian3_pages', array() ); return absint( $map[ $role ] ?? 0 ); }
function ian3_url( $role ) { $id = ian3_id( $role ); return $id && get_post( $id ) ? get_permalink( $id ) : home_url( '/' . ( 'home' === $role ? '' : $role . '/' ) ); }
function ian3_visible( $role ) {
    $options = (array) get_option( 'ian3_visibility', array() );
    return ! ( isset( $options[ $role ] ) && ! $options[ $role ] ) && 'publish' === get_post_status( ian3_id( $role ) );
}
function ian3_about_items() {
    $items = array();
    foreach ( array( 'about', 'factory', 'showroom', 'services' ) as $key ) { if ( ian3_visible( $key ) ) { $items[ $key ] = ian3_roles()[ $key ]; } }
    return $items;
}
function ian3_current_role() {
    $id = get_queried_object_id();
    foreach ( (array) get_option( 'ian3_pages', array() ) as $role => $page ) { if ( $id === (int) $page && is_page() ) { return $role; } }
    if ( is_singular( 'post' ) ) { return has_category( 'project', $id ) ? 'project' : 'insights'; }
    if ( is_singular( 'product' ) || is_tax( 'product_cat' ) || is_post_type_archive( 'product' ) ) { return 'products'; }
    if ( is_page() ) { $parent = wp_get_post_parent_id( $id ); foreach ( array( 'factory', 'showroom' ) as $key ) { if ( $parent === ian3_id( $key ) ) { return $key; } } }
    return '';
}
function ian3_link( $role, $label = '', $class = '' ) {
    if ( ! ian3_visible( $role ) ) { return ''; }
    return '<a href="' . esc_url( ian3_url( $role ) ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ?: ian3_roles()[ $role ] ) . '</a>';
}
function ian3_header() {
    $current = ian3_current_role(); $about = ian3_about_items();
    ob_start(); ?>
    <a class="ian-skip-link screen-reader-text" href="#ian-content">Skip to content</a><header class="preview-header ian-site-header" id="top"><div class="preview-nav-shell">
    <?php if ( has_custom_logo() ) { echo get_custom_logo(); } else { ?><a class="preview-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="IanProject Home">IAN<span>PROJECT</span></a><?php } ?>
    <button class="preview-menu-toggle" type="button" aria-label="Open navigation" aria-controls="preview-menu" aria-expanded="false">☰</button>
    <nav class="preview-menu" id="preview-menu" aria-label="Main navigation">
    <?php foreach ( array( 'home', 'products', 'project', 'insights' ) as $role ) : if ( ! ian3_visible( $role ) ) { continue; } ?>
      <?php $terms = 'products' === $role && function_exists( 'ian4_visible_terms' ) ? ian4_visible_terms() : array(); if ( $terms ) : ?>
      <div class="preview-about ian-dropdown"><div class="preview-about-top"><a href="<?php echo esc_url( ian3_url( $role ) ); ?>" <?php echo $current === $role ? 'aria-current="page"' : ''; ?>>Products</a><button class="ian-dropdown-toggle preview-about-toggle" type="button" aria-label="Open Products submenu" aria-controls="products-submenu" aria-expanded="false"><span class="ian-nav-chevron" aria-hidden="true"></span></button></div><div class="preview-about-menu" id="products-submenu" hidden><a href="<?php echo esc_url( ian3_url( 'products' ) ); ?>">All Products</a><?php foreach ( $terms as $term ) : ?><a href="<?php echo esc_url( add_query_arg( 'category', $term->slug, ian3_url( 'products' ) ) . '#catalog' ); ?>"><?php echo esc_html( $term->name ); ?></a><?php endforeach; ?></div></div>
      <?php else : ?><a href="<?php echo esc_url( ian3_url( $role ) ); ?>" <?php echo $current === $role ? 'aria-current="page"' : ''; ?>><?php echo esc_html( ian3_roles()[ $role ] ); ?></a><?php endif; ?>
    <?php endforeach; ?>
    <?php if ( $about ) : $root = isset( $about['about'] ) ? 'about' : array_key_first( $about ); ?>
    <div class="preview-about ian-dropdown"><div class="preview-about-top"><a href="<?php echo esc_url( ian3_url( $root ) ); ?>" <?php echo isset( $about[ $current ] ) ? 'aria-current="true"' : ''; ?>>About</a><?php if ( count( $about ) > 1 ) : ?><button class="ian-dropdown-toggle preview-about-toggle" type="button" aria-label="Open About submenu" aria-controls="about-submenu" aria-expanded="false"><span class="ian-nav-chevron" aria-hidden="true"></span></button><?php endif; ?></div><?php if ( count( $about ) > 1 ) : ?><div class="preview-about-menu" id="about-submenu" hidden><?php foreach ( $about as $role => $label ) : ?><a href="<?php echo esc_url( ian3_url( $role ) ); ?>" <?php echo $current === $role ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a><?php endforeach; ?></div><?php endif; ?></div>
    <?php endif; ?>
    <?php if ( ian3_visible( 'contact' ) ) : ?><a href="<?php echo esc_url( ian3_url( 'contact' ) ); ?>" <?php echo 'contact' === $current ? 'aria-current="page"' : ''; ?>>Contact</a><?php endif; ?>
    <?php if(function_exists('wc_get_cart_url')): ?><a class="header-cart" data-open-cart href="<?php echo esc_url(wc_get_cart_url()); ?>"><span>Cart</span><b id="cart-count"><?php echo esc_html(function_exists('WC')&&WC()->cart?count(WC()->cart->get_cart()):0); ?></b></a><?php endif; ?>
    </nav></div></header><div id="ian-content" tabindex="-1"></div>
    <?php return ob_get_clean();
}
function ian3_footer() {
    $contact = iansf_contacts();
    ob_start(); ?>
    <footer class="preview-footer ian-global-footer"><div class="preview-footer-shell"><div class="preview-footer-grid">
    <div><h2>IANPROJECT</h2><p>Home products, custom furniture and interior solutions for international residential and commercial projects.</p></div>
    <div><h3>Get In Touch</h3><p><?php echo esc_html( $contact['address'] ); ?></p><p><a href="<?php echo esc_url( 'mailto:' . $contact['email'] ); ?>"><?php echo esc_html( $contact['email'] ); ?></a></p></div>
    <div><h3>Explore</h3><ul><?php foreach ( array( 'products', 'project', 'insights', 'about', 'contact' ) as $key ) { $link = ian3_link( $key, 'about' === $key ? 'About' : '' ); if ( $link ) { echo '<li>' . $link . '</li>'; } } ?></ul></div>
    <div><h3>Company &amp; Support</h3><ul><?php foreach ( array( 'factory', 'showroom', 'services', 'privacy', 'terms' ) as $key ) { $link = ian3_link( $key ); if ( $link ) { echo '<li>' . $link . '</li>'; } } ?></ul></div>
    </div><div class="preview-copyright"><p>© <?php echo esc_html( wp_date( 'Y' ) ); ?> IanProject. All Rights Reserved.</p><p>Template design by <a href="https://htmlcodex.com" rel="noopener" target="_blank">HTML Codex</a>.</p></div></div></footer>
    <?php return ob_get_clean();
}
add_shortcode( 'ian_site_header', 'ian3_header' );
add_shortcode( 'ian_site_footer', 'ian3_footer' );
// All theme templates, including saved Site Editor parts, share one authoritative shell.
add_filter( 'pre_render_block', function ( $pre, $block ) {
    if ( 'core/template-part' !== ( $block['blockName'] ?? '' ) ) { return $pre; }
    $slug = $block['attrs']['slug'] ?? '';
    if ( 'header' === $slug ) { return ian3_header(); }
    if ( 'footer' === $slug ) { return ian3_footer(); }
    return $pre;
}, 10, 2 );
add_shortcode( 'ian_about_nav', function () {
    $items = ian3_about_items(); if ( count( $items ) < 2 ) { return ''; }
    $html = '<nav class="ia-subnav" aria-label="About pages"><div class="ip-shell">';
    foreach ( $items as $key => $label ) { $html .= '<a href="' . esc_url( ian3_url( $key ) ) . '"' . ( ian3_current_role() === $key ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>'; }
    return $html . '</div></nav>';
} );

function ian3_remap_links( $html ) {
    if ( ! str_contains( $html, 'href=' ) ) { return $html; }
    $aliases = array( '' => 'home', 'home' => 'home', 'index.html' => 'home', 'products' => 'products', 'project' => 'project', 'project-cases' => 'project', 'insights' => 'insights', 'about' => 'about', 'factory' => 'factory', 'showroom' => 'showroom', 'services' => 'services', 'contact' => 'contact', 'privacy' => 'privacy', 'terms' => 'terms' );
    $processor = new WP_HTML_Tag_Processor( $html ); $host = wp_parse_url( home_url(), PHP_URL_HOST ); $base = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
    while ( $processor->next_tag( 'A' ) ) {
        $href = $processor->get_attribute( 'href' ); if ( ! is_string( $href ) || str_starts_with( $href, '#' ) ) { continue; }
        $parts = wp_parse_url( $href ); if ( ! is_array( $parts ) || ! in_array( $parts['scheme'] ?? 'https', array( 'http', 'https' ), true ) || ( ! empty( $parts['host'] ) && ! in_array( $parts['host'], array( $host, 'ianproject.com', 'www.ianproject.com' ), true ) ) ) { continue; }
        $path = trim( $parts['path'] ?? '', '/' ); if ( $base && str_starts_with( '/' . $path, $base . '/' ) ) { $path = substr( '/' . $path, strlen( $base ) + 1 ); }
        $path = preg_replace( '/\.html$/', '', $path );
        if ( isset( $aliases[ $path ] ) ) {
            $key = $aliases[ $path ]; $url = ian3_url( $key );
            if ( ! ian3_visible( $key ) && ! in_array( $key, array( 'home', 'contact' ), true ) ) { $processor->set_attribute( 'hidden', true ); }
            if ( ! empty( $parts['query'] ) ) { parse_str( $parts['query'], $args ); $url = add_query_arg( ianproject_query_values( $args ), $url ); }
            if ( isset( $parts['fragment'] ) ) { $url .= '#' . $parts['fragment']; }
            $processor->set_attribute( 'href', $url );
        }
    }
    return $processor->get_updated_html();
}
add_filter( 'render_block', function ( $html, $block ) {
    if ( is_admin() ) { return $html; }
    $classes = $block['attrs']['className'] ?? '';
    if ( str_contains( $classes, 'ia-owner-section' ) || str_contains( $classes, 'ia-draft-slot' ) ) { return ''; }
    if ( is_singular( 'post' ) && get_post_meta( get_the_ID(), '_ian_v3_legacy_insight', true ) && str_contains( $classes, 'article-hero' ) ) { return ''; }
    if ( str_contains( $classes, 'ia-explore-card' ) ) {
        foreach ( array( 'factory', 'showroom', 'services' ) as $key ) { if ( str_contains( $html, '/' . $key ) && ! ian3_visible( $key ) ) { return ''; } }
    }
    return ian3_remap_links( $html );
}, 25, 2 );

function ian3_profile_parent( $id ) { $parent = wp_get_post_parent_id( $id ); foreach ( array( 'factory', 'showroom' ) as $kind ) { if ( $parent && $parent === ian3_id( $kind ) ) { return $kind; } } return ''; }
function ian3_profile_fields() { return array( 'country' => '国家 / 地区', 'city' => '城市', 'address' => '地址', 'categories' => '主要产品 / 展示类别' ); }
add_action( 'init', function () {
    foreach ( array_keys( ian3_profile_fields() ) as $field ) { register_post_meta( 'page', '_ian_profile_' . $field, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => function ( $allowed, $key, $id ) { return current_user_can( 'edit_post', $id ); } ) ); }
} );
add_action( 'add_meta_boxes_page', function ( $post ) {
    if ( ! ian3_profile_parent( $post->ID ) ) { return; }
    add_meta_box( 'ian-profile', '工厂 / 展厅资料', function ( $post ) {
        wp_nonce_field( 'ian_profile', 'ian_profile_nonce' );
        foreach ( ian3_profile_fields() as $field => $label ) { echo '<p><label>' . esc_html( $label ) . '<input class="widefat" name="ian_profile[' . esc_attr( $field ) . ']" value="' . esc_attr( get_post_meta( $post->ID, '_ian_profile_' . $field, true ) ) . '"></label></p>'; }
        echo '<p>在正文添加图片画廊、视频和介绍；特色图片用作目录封面。未完成时保存为草稿，发布后才会进入目录。</p>';
    }, 'page', 'side' );
} );
add_action( 'save_post_page', function ( $id ) {
    if ( ! isset( $_POST['ian_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ian_profile_nonce'] ) ), 'ian_profile' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) ) { return; }
    $values = isset( $_POST['ian_profile'] ) && is_array( $_POST['ian_profile'] ) ? wp_unslash( $_POST['ian_profile'] ) : array();
    foreach ( ian3_profile_fields() as $field => $label ) { update_post_meta( $id, '_ian_profile_' . $field, sanitize_text_field( $values[ $field ] ?? '' ) ); }
} );
add_shortcode( 'ian_profile_facts', function () {
    $html = '';
    foreach ( array( 'country' => 'Country / Region', 'city' => 'City', 'address' => 'Address', 'categories' => 'Product categories' ) as $field => $label ) { $value = get_post_meta( get_the_ID(), '_ian_profile_' . $field, true ); if ( $value ) { $html .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>'; } }
    return $html ? '<dl class="ia-profile-facts">' . $html . '</dl>' : '';
} );
add_shortcode( 'ian_profiles', function ( $attrs ) {
    $kind = 'showroom' === ( $attrs['kind'] ?? '' ) ? 'showroom' : 'factory'; $parent = ian3_id( $kind );
    if ( ! $parent ) { return ''; }
    $all = get_posts( array( 'post_type' => 'page', 'post_parent' => $parent, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ); if ( ! $all ) { return ''; }
    $countries = array(); foreach ( $all as $id ) { $value = get_post_meta( $id, '_ian_profile_country', true ); if ( $value ) { $countries[ $value ] = $value; } } natcasesort( $countries );
    $country = ian3_query_text( 'profile_country' ); $page = max( 1, absint( $_GET['profile_page'] ?? 1 ) );
    $args = array( 'post_type' => 'page', 'post_parent' => $parent, 'post_status' => 'publish', 'posts_per_page' => 9, 'paged' => $page, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
    if ( $country ) { $args['meta_query'] = array( array( 'key' => '_ian_profile_country', 'value' => $country ) ); }
    $query = new WP_Query( $args ); $url = get_permalink( $parent );
    ob_start(); ?>
    <section class="ia-pale"><div class="ip-shell ia-section"><div class="ia-section-head"><div><span class="ip-kicker"><?php echo esc_html( ucfirst( $kind ) ); ?> directory</span><h2><?php echo 'factory' === $kind ? 'Explore the factories' : 'Explore showrooms by region'; ?></h2></div></div>
    <?php if ( $countries ) : ?><form class="ian-directory-filter" method="get" action="<?php echo esc_url( $url ); ?>"><label for="profile-country-<?php echo esc_attr( $kind ); ?>">Country / Region</label><select name="profile_country" id="profile-country-<?php echo esc_attr( $kind ); ?>"><option value="">All regions</option><?php foreach ( $countries as $value ) : ?><option <?php selected( $country, $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select><button class="ip-button" type="submit">Filter</button></form><?php endif; ?>
    <div class="ia-directory-grid"><?php foreach ( $query->posts as $post ) : ?><a class="ia-directory-card" href="<?php echo esc_url( get_permalink( $post ) ); ?>"><div class="ia-directory-cover"><?php echo get_the_post_thumbnail( $post, 'large', array( 'loading' => 'lazy' ) ) ?: '<span>View profile</span>'; ?></div><div class="ia-directory-body"><span class="ip-kicker"><?php echo esc_html( implode( ' · ', array_filter( array( get_post_meta( $post->ID, '_ian_profile_country', true ), get_post_meta( $post->ID, '_ian_profile_city', true ) ) ) ) ); ?></span><h3><?php echo esc_html( $post->post_title ); ?></h3><p><?php echo esc_html( wp_trim_words( $post->post_excerpt ?: wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 24 ) ); ?></p></div></a><?php endforeach; ?></div>
    <?php if ( ! $query->found_posts ) { echo '<p>No matching profiles. Try another region.</p>'; } echo ian3_pagination( $query, $page, 'profile_page', $url ); ?>
    </div></section><?php return ob_get_clean();
} );

function ian3_query_text( $key ) { return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; }
function ian3_pagination( $query, $current, $key, $url ) {
    if ( $query->max_num_pages < 2 ) { return ''; }
    return '<nav class="ip-pagination" aria-label="Results pages">' . paginate_links( array( 'base' => add_query_arg( $key, '%#%', $url ), 'format' => '', 'current' => $current, 'total' => $query->max_num_pages, 'type' => 'plain', 'add_args' => ianproject_query_values( array_filter( array( 'topic' => ian3_query_text( 'topic' ), 'article_search' => ian3_query_text( 'article_search' ), 'profile_country' => ian3_query_text( 'profile_country' ) ), 'strlen' ) ) ) ) . '</nav>';
}

// Unstyled owner-created pages still receive a single accessible page heading.
add_shortcode( 'ian_page_heading', function () {
    if ( ! is_page() ) { return ''; }
    $content = get_post_field( 'post_content', get_queried_object_id() );
    if ( preg_match( '/<h1\b/i', $content ) || has_block( 'core/post-title', $content ) ) { return ''; }
    return '<div class="ip-shell ia-section"><h1>' . esc_html( get_the_title() ) . '</h1></div>';
} );
add_shortcode( 'ian_catalog_heading', function () {
    return '<section class="ip-collection-head"><div class="ip-shell"><h1>' . esc_html( is_tax() ? single_term_title( '', false ) : 'Products' ) . '</h1></div></section>';
} );
