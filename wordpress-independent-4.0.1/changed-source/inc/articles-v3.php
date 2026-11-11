<?php
defined( 'ABSPATH' ) || exit;

function ian3_article_kind( $id ) { return has_category( 'project', $id ) ? 'project' : 'insights'; }
function ian3_article_fields() { return array( 'region' => '地区分组（例如 Asia / Europe）', 'location' => '国家 / 城市', 'project_type' => '项目类型', 'scope' => '产品范围', 'materials' => '材料' ); }
add_action( 'init', function () {
    foreach ( array_keys( ian3_article_fields() ) as $key ) { register_post_meta( 'post', '_ian_' . $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => function ( $allowed, $key, $id ) { return current_user_can( 'edit_post', $id ); } ) ); }
    register_post_meta( 'post', '_ian_insight_topic', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => function ( $allowed, $key, $id ) { return current_user_can( 'edit_post', $id ); } ) );
    // Legacy records stay registered for migration and rollback, with no second article menu.
    register_post_type( 'ian_insight', array( 'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'rewrite' => false, 'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ) ) );
} );
add_action( 'add_meta_boxes_post', function () {
    add_meta_box( 'ian-article-fields', 'IanProject 文章 / 项目资料', function ( $post ) {
        wp_nonce_field( 'ian_article_fields', 'ian_article_nonce' );
        echo '<p>选择“Project”分类发布案例，选择“Insights”分类发布行业文章。列表会自动更新。案例信息可选填；不要填写未确认的数据。</p>';
        foreach ( ian3_article_fields() as $key => $label ) { echo '<p><label>' . esc_html( $label ) . '<input class="widefat" name="ian_article[' . esc_attr( $key ) . ']" value="' . esc_attr( get_post_meta( $post->ID, '_ian_' . $key, true ) ) . '"></label></p>'; }
        echo '<p><label>行业文章主题<input class="widefat" name="ian_article[insight_topic]" value="' . esc_attr( get_post_meta( $post->ID, '_ian_insight_topic', true ) ) . '" placeholder="Materials &amp; finishes / Shipping / Hardware"></label></p>';
    }, 'post', 'side' );
} );
add_action( 'save_post_post', function ( $id ) {
    if ( ! isset( $_POST['ian_article_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ian_article_nonce'] ) ), 'ian_article_fields' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) ) { return; }
    $values = isset( $_POST['ian_article'] ) && is_array( $_POST['ian_article'] ) ? wp_unslash( $_POST['ian_article'] ) : array();
    foreach ( array_merge( array_keys( ian3_article_fields() ), array( 'insight_topic' ) ) as $key ) { update_post_meta( $id, '_ian_' . $key, sanitize_text_field( $values[ $key ] ?? '' ) ); }
} );

function ian3_article_card( $id, $kind = '' ) {
    $kind = $kind ?: ian3_article_kind( $id ); $project = 'project' === $kind;
    $image = get_the_post_thumbnail( $id, 'large', array( 'loading' => 'lazy', 'decoding' => 'async' ) );
    $meta = get_post_meta( $id, $project ? '_ian_location' : '_ian_insight_topic', true );
    $meta = $meta ?: ( $project ? 'Project reference' : 'Industry insights' );
    $html = '<article class="ip-card' . ( $project ? '' : ' ip-insight-card' ) . '"><a class="ip-card-link" href="' . esc_url( get_permalink( $id ) ) . '"><div class="ip-card-image">' . ( $image ?: '<span class="ian-no-image">View ' . ( $project ? 'project' : 'guide' ) . '</span>' ) . '</div><span class="ip-card-meta">' . esc_html( $meta ) . '</span><h3>' . esc_html( get_the_title( $id ) ) . '</h3>';
    if ( $project ) {
        $facts = '';
        foreach ( array( 'scope' => 'Products', 'materials' => 'Materials' ) as $field => $label ) {
            $value = get_post_meta( $id, '_ian_' . $field, true );
            if ( $value ) { $facts .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>'; }
        }
        if ( $facts ) { $html .= '<dl class="ip-card-scope">' . $facts . '</dl>'; }
    }
    else { $html .= '<p>' . esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt( $id ) ), 30 ) ) . '</p><div class="ip-card-foot"><span>' . esc_html( max( 2, (int) ceil( str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $id ) ) ) / 220 ) ) ) . ' min read</span><span>Read the guide ↗</span></div>'; }
    return $html . '</a></article>';
}
add_shortcode( 'ian_articles', function ( $attrs ) {
    $kind = 'project' === ( $attrs['type'] ?? '' ) ? 'project' : 'insights';
    $field = 'project' === $kind ? '_ian_region' : '_ian_insight_topic';
    $all = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'category_name' => $kind, 'posts_per_page' => -1, 'fields' => 'ids' ) );
    $filters = array(); foreach ( $all as $id ) { $value = get_post_meta( $id, $field, true ); if ( $value ) { $filters[ $value ] = $value; } } natcasesort( $filters );
    $category = ian3_query_text( 'topic' ); $search = ian3_query_text( 'article_search' ); $page = max( 1, absint( $_GET['article_page'] ?? 1 ) );
    $args = array( 'post_type' => 'post', 'post_status' => 'publish', 'category_name' => $kind, 'posts_per_page' => 9, 'paged' => $page, 's' => $search, 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ) );
    if ( $category ) { $args['meta_query'] = array( array( 'key' => $field, 'value' => $category ) ); }
    $query = new WP_Query( $args ); $url = get_permalink();
    ob_start(); ?>
    <div class="ian-article-directory"><div class="ip-filter-bar"><nav class="ip-filters" aria-label="<?php echo 'project' === $kind ? 'Project regions' : 'Article topics'; ?>"><a class="ip-filter <?php echo ! $category ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'article_search', rawurlencode( $search ), $url ) ); ?>">All <?php echo 'project' === $kind ? 'projects' : 'insights'; ?></a><?php foreach ( $filters as $value ) : ?><a class="ip-filter <?php echo $category === $value ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( ianproject_query_values( array( 'topic' => $value, 'article_search' => $search ) ), $url ) ); ?>" <?php echo $category === $value ? 'aria-current="true"' : ''; ?>><?php echo esc_html( $value ); ?></a><?php endforeach; ?></nav>
    <form class="ip-search" method="get" action="<?php echo esc_url( $url ); ?>"><?php if ( $category ) : ?><input type="hidden" name="topic" value="<?php echo esc_attr( $category ); ?>"><?php endif; ?><label class="screen-reader-text" for="ian-article-search">Search <?php echo esc_html( $kind ); ?></label><input id="ian-article-search" type="search" name="article_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Search <?php echo 'project' === $kind ? 'projects, materials…' : 'guides, topics…'; ?>"><button type="submit" aria-label="Search">⌕</button></form></div>
    <div class="ip-results-row"><span role="status"><?php echo esc_html( $query->found_posts . ( 'project' === $kind ? ' project references' : ' guides' ) ); ?></span><?php if ( $category || $search ) : ?><a class="ip-clear" href="<?php echo esc_url( $url ); ?>">Clear filters ×</a><?php endif; ?></div><div class="ip-collection-grid"><?php foreach ( $query->posts as $item ) { echo ian3_article_card( $item->ID, $kind ); } ?></div>
    <?php if ( ! $query->found_posts ) : ?><div class="ip-empty"><h3>No matching results.</h3><p>Try another search or explore all <?php echo esc_html( $kind ); ?>.</p></div><?php endif; echo ian3_pagination( $query, $page, 'article_page', $url ); ?>
    </div><?php return ob_get_clean();
} );

add_shortcode( 'ian_home_projects', function () {
    if ( ! ian3_visible( 'project' ) ) { return ''; }
    $items = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'category_name' => 'project', 'posts_per_page' => 3, 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ) ) );
    if ( ! $items ) { return ''; }
    return '<div class="ip-collection-grid ian-home-project-grid">' . implode( '', array_map( fn( $p ) => ian3_article_card( $p->ID, 'project' ), $items ) ) . '</div>';
} );

function ian3_heading_ids( $html, &$toc ) {
    $toc = array(); $used = array();
    return preg_replace_callback( '~<h2\b([^>]*)>(.*?)</h2>~is', function ( $match ) use ( &$toc, &$used ) {
        $label = trim( wp_strip_all_tags( $match[2] ) ); if ( ! $label ) { return $match[0]; }
        $existing = preg_match( '~\bid=["\']([^"\']+)["\']~i', $match[1], $id );
        $anchor = $existing ? $id[1] : ( sanitize_title( $label ) ?: 'section' ); $base = $anchor; $count = 1;
        while ( isset( $used[ $anchor ] ) ) { $anchor = $base . '-' . ++$count; } $used[ $anchor ] = true; $toc[] = array( $anchor, $label );
        return $existing ? $match[0] : '<h2' . $match[1] . ' id="' . esc_attr( $anchor ) . '">' . $match[2] . '</h2>';
    }, $html );
}
add_shortcode( 'ian_article_single', function () {
    $id = get_queried_object_id(); if ( 'post' !== get_post_type( $id ) ) { return ''; }
    $kind = ian3_article_kind( $id ); $project = 'project' === $kind;
    $body = apply_filters( 'the_content', get_post_field( 'post_content', $id ) ); $toc = array(); $body = ian3_heading_ids( $body, $toc );
    $head = '<main class="ip-page"><section class="ip-detail-head"><div class="ip-shell"><nav class="ip-breadcrumb" aria-label="Breadcrumb"><a href="' . esc_url( home_url( '/' ) ) . '">Home</a><span>/</span><a href="' . esc_url( ian3_url( $kind ) ) . '">' . ( $project ? 'Project' : 'Insights' ) . '</a></nav><div class="ip-detail-title"><span class="ip-kicker">' . esc_html( $project ? ( get_post_meta( $id, '_ian_location', true ) ?: 'Project reference' ) : ( get_post_meta( $id, '_ian_insight_topic', true ) ?: 'Industry insights' ) ) . '</span><h1>' . esc_html( get_the_title( $id ) ) . '</h1><p>' . esc_html( get_the_excerpt( $id ) ) . '</p></div></div></section>';
    $image = get_the_post_thumbnail( $id, 'full', array( 'loading' => 'eager', 'decoding' => 'async' ) );
    if ( $project ) {
        $facts = ''; foreach ( array( 'location' => 'Location', 'project_type' => 'Type', 'scope' => 'Scope', 'materials' => 'Materials' ) as $key => $label ) { $v = get_post_meta( $id, '_ian_' . $key, true ); if ( $v ) { $facts .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>'; } }
        $head .= '<div class="ip-shell ip-case-layout"><article class="ip-case-body">' . ( $image ? '<figure class="ian-case-cover">' . $image . '</figure>' : '' ) . $body . '</article><aside class="ip-case-sidebar"><span class="ip-kicker">At a glance</span><h2>Reference details</h2><dl class="ip-case-facts">' . $facts . '</dl>' . ian3_link( 'contact', 'Discuss Your Project', 'ip-button' ) . '</aside></div>';
    } else {
        $nav = ''; foreach ( $toc as $row ) { $nav .= '<li><a href="#' . esc_attr( $row[0] ) . '">' . esc_html( $row[1] ) . '</a></li>'; }
        $head .= '<div class="ip-shell ip-reading-layout"><aside class="ip-reading-aside"><details class="ip-toc" open><summary>On this page</summary><ol>' . $nav . '</ol></details><p>Turning research into a product brief?</p>' . ian3_link( 'contact', 'Discuss Your Project ↗', 'ip-inline-link' ) . '</aside><div class="ip-reading-content">' . ( $image ? '<figure class="ip-reading-cover">' . $image . '</figure>' : '' ) . '<div class="ip-article">' . $body . '</div><a class="ip-return-link" href="' . esc_url( ian3_url( 'insights' ) ) . '">← Back to all insights</a></div></div>';
    }
    $related = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'category_name' => $kind, 'posts_per_page' => 3, 'post__not_in' => array( $id ), 'orderby' => 'date', 'order' => 'DESC' ) );
    if ( $related ) { $head .= '<section class="ip-related"><div class="ip-shell"><div class="ip-project-intro"><h2>' . ( $project ? 'More project references' : 'More useful insights' ) . '</h2><a class="ip-inline-link" href="' . esc_url( ian3_url( $kind ) ) . '">View all →</a></div><div class="ip-collection-grid">' . implode( '', array_map( fn( $p ) => ian3_article_card( $p->ID, $kind ), $related ) ) . '</div></div></section>'; }
    return $head . '</main>';
} );

add_shortcode( 'ian_native_archive', function () {
    global $wp_query;
    $title = is_search() ? 'Search results' : ( is_category() ? single_cat_title( '', false ) : 'Articles' );
    $html = '<main class="ip-page"><section class="ip-collection-head"><div class="ip-shell ip-page-title"><h1>' . esc_html( $title ) . '</h1></div></section><div class="ip-shell ip-archive"><div class="ip-collection-grid">';
    foreach ( $wp_query->posts as $p ) { if ( 'post' === $p->post_type ) { $html .= ian3_article_card( $p->ID ); } else { $html .= '<article class="ip-card"><h2><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( $p->post_title ) . '</a></h2><p>' . esc_html( wp_trim_words( wp_strip_all_tags( strip_shortcodes( $p->post_content ) ), 25 ) ) . '</p></article>'; } }
    if ( ! $wp_query->post_count ) { $html .= '<p>No matching content.</p>'; }
    return $html . '</div>' . get_the_posts_pagination() . '</div></main>';
} );
