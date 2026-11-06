<?php
defined( 'ABSPATH' ) || exit;

function ianproject_manifest() {
    static $manifest;
    if ( null === $manifest ) {
        $file = get_theme_file_path( 'data/content.json' );
        $manifest = is_readable( $file ) ? json_decode( file_get_contents( $file ), true ) : array();
    }
    return is_array( $manifest ) ? $manifest : array();
}

function ianproject_import_records() {
    $manifest = ianproject_manifest(); $records = array();
    foreach ( array( 'pages' => 'page', 'posts' => 'ian_insight', 'products' => 'ian_product', 'drafts' => 'page' ) as $group => $type ) {
        foreach ( $manifest[ $group ] ?? array() as $record ) {
            $record['_type'] = $type;
            if ( 'drafts' === $group ) { $record['status'] = 'draft'; }
            $records[] = $record;
        }
    }
    return $records;
}

function ianproject_import_image( $source, $post_id, $title ) {
    if ( ! is_string( $source ) || ! $source ) { return; }
    $relative = ltrim( str_replace( '{{IAN_ASSET}}/', '', $source ), '/' );
    $map_file = get_theme_file_path( 'assets/asset-map.json' );
    $map = is_readable( $map_file ) ? json_decode( file_get_contents( $map_file ), true ) : array();
    if ( is_array( $map ) && isset( $map[ $relative ] ) ) { $relative = $map[ $relative ]; }
    $base = realpath( get_theme_file_path( 'assets' ) );
    $file = realpath( get_theme_file_path( 'assets/' . $relative ) );
    if ( ! $base || ! $file || 0 !== strpos( $file, $base . DIRECTORY_SEPARATOR ) || ! is_readable( $file ) ) { return; }
    $existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_ian_image_source', 'meta_value' => $relative, 'posts_per_page' => 1, 'fields' => 'ids' ) );
    if ( $existing ) { set_post_thumbnail( $post_id, $existing[0] ); return; }
    $extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
    $upload = wp_upload_bits( sanitize_title( $title ) . '.' . $extension, null, file_get_contents( $file ) );
    if ( ! empty( $upload['error'] ) ) { return; }
    $mime = wp_check_filetype( $upload['file'] );
    $id = wp_insert_attachment( array( 'post_title' => $title, 'post_mime_type' => $mime['type'], 'post_status' => 'inherit' ), $upload['file'], $post_id, true );
    if ( is_wp_error( $id ) ) { return; }
    $size = wp_getimagesize( $upload['file'] );
    if ( $size ) {
        wp_update_attachment_metadata( $id, array( 'width' => $size[0], 'height' => $size[1], 'file' => _wp_relative_upload_path( $upload['file'] ), 'sizes' => array(), 'filesize' => filesize( $upload['file'] ) ) );
    }
    update_post_meta( $id, '_ian_image_source', $relative );
    update_post_meta( $id, '_wp_attachment_image_alt', $title );
    set_post_thumbnail( $post_id, $id );
}

/** Idempotent, bounded import; edited records and slug collisions are never overwritten. */
function ianproject_import_batch( $offset, $limit = 4 ) {
    $all = ianproject_import_records(); $imported = 0; $skipped = 0; $errors = array();
    foreach ( array_slice( $all, $offset, $limit ) as $index => $record ) {
        $source = $record['source'] ?? ( 'products/' . ( $record['id'] ?? $record['slug'] ?? '' ) . '.html' );
        $type = $record['_type'];
        $existing = get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'meta_key' => '_ian_source', 'meta_value' => $source, 'posts_per_page' => 1, 'fields' => 'ids' ) );
        $slug = sanitize_title( $record['slug'] ?? $record['id'] ?? '' );
        $parent = 0;
        if ( ! empty( $record['parent'] ) ) {
            $parent_post = get_page_by_path( sanitize_title( $record['parent'] ), OBJECT, 'page' );
            if ( $parent_post ) { $parent = $parent_post->ID; }
        }
        $path = $parent ? get_page_uri( $parent ) . '/' . $slug : $slug;
        if ( $existing || get_page_by_path( $path, OBJECT, $type ) ) { ++$skipped; continue; }
        $post_id = wp_insert_post( wp_slash( array(
            'post_type' => $type, 'post_status' => 'draft' === ( $record['status'] ?? '' ) ? 'draft' : 'publish',
            'post_title' => $record['title'] ?? $slug, 'post_name' => $slug,
            'post_content' => ianproject_tokens( $record['content'] ?? '' ),
            'post_excerpt' => $record['summary'] ?? $record['description'] ?? '',
            'post_parent' => $parent, 'menu_order' => $offset + $index,
            'comment_status' => 'closed', 'ping_status' => 'closed',
        ) ), true );
        if ( is_wp_error( $post_id ) ) { $errors[] = $source . ': ' . $post_id->get_error_message(); continue; }
        update_post_meta( $post_id, '_ian_source', $source );
        update_post_meta( $post_id, '_ian_description', sanitize_text_field( $record['description'] ?? $record['seoDescription'] ?? $record['summary'] ?? '' ) );
        if ( 'index.html' === $source ) { update_option( 'ian_homepage_id', $post_id ); }
        if ( 'ian_product' === $type ) {
            $data = $record;
            $data['unit'] = $data['unit'] ?? $data['priceUnit'] ?? '';
            unset( $data['content'], $data['_type'] );
            if ( ! empty( $data['image'] ) && ! preg_match( '~^(?:https?:|\{\{)~', $data['image'] ) ) { $data['image'] = '{{IAN_ASSET}}/' . ltrim( $data['image'], '/' ); }
            update_post_meta( $post_id, '_ian_product_data', $data );
            update_post_meta( $post_id, '_ian_price_usd', ianproject_price_value( $data ) );
            if ( ! empty( $record['category'] ) ) { wp_set_object_terms( $post_id, sanitize_title( $record['category'] ), 'ian_product_category' ); }
            // SKU is searchable through WordPress's native search index.
            if ( ! empty( $record['code'] ) ) { update_post_meta( $post_id, '_ian_sku', sanitize_text_field( $record['code'] ) ); }
        }
        $image = $record['featuredImage'] ?? $record['image'] ?? '';
        if ( $image ) { ianproject_import_image( $image, $post_id, $record['title'] ?? $slug ); }
        ++$imported;
    }
    $next = min( count( $all ), $offset + $limit );
    if ( $next >= count( $all ) ) { update_option( 'ian_import_complete', true ); flush_rewrite_rules( false ); }
    return array( 'offset' => $next, 'total' => count( $all ), 'imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'done' => $next >= count( $all ) );
}

add_action( 'admin_menu', function () {
    add_theme_page( 'IanProject 快速设置', 'IanProject 设置', 'manage_options', 'ianproject-setup', 'ianproject_setup_page' );
} );

add_action( 'admin_notices', function () {
    if ( current_user_can( 'manage_options' ) && ! get_option( 'ian_import_complete' ) ) {
        echo '<div class="notice notice-info"><p>IanProject 已启用。<a href="' . esc_url( admin_url( 'themes.php?page=ianproject-setup' ) ) . '">进入快速设置，导入原站页面和产品</a>。</p></div>';
    }
} );

function ianproject_setup_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    if ( isset( $_POST['ian_settings_save'] ) ) {
        check_admin_referer( 'ian_settings_save' );
        update_option( 'ian_sales_email', sanitize_email( wp_unslash( $_POST['ian_sales_email'] ?? '' ) ) ?: 'sales@ianproject.com' );
        update_option( 'ian_whatsapp', preg_replace( '/[^0-9]/', '', wp_unslash( $_POST['ian_whatsapp'] ?? '' ) ) ?: '8619956229033' );
        echo '<div class="notice notice-success"><p>询价入口已保存。页眉、页脚和页面中的联系方式请在区块编辑器中修改。</p></div>';
    }
    if ( isset( $_POST['ian_set_home'] ) ) {
        check_admin_referer( 'ian_settings_save' );
        $home_id = absint( get_option( 'ian_homepage_id' ) );
        if ( $home_id && 'publish' === get_post_status( $home_id ) ) {
            update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $home_id );
            echo '<div class="notice notice-success"><p>原站 Home 页面已设为首页。</p></div>';
        }
    }
    ?>
    <div class="wrap" style="max-width:900px"><h1>IanProject 快速设置</h1>
        <p>导入原站页面、126 件产品及行业文章；所有正文均可在 WordPress 区块编辑器修改。重复导入会跳过已有内容，不覆盖你的编辑。原站的示例详情模板会保留为草稿。</p>
        <h2>1. 导入原站内容</h2><p>导入会创建已发布页面和产品，并把产品封面复制到媒体库。已有相同路径会跳过。请保持本页打开至完成。</p>
        <button id="ian-start-import" class="button button-primary">导入页面和产品</button><p id="ian-import-status" aria-live="polite"></p><progress id="ian-import-progress" value="0" max="100" style="width:100%;display:none"></progress>
        <h2>2. 设置首页</h2><p>导入完成后点击。此操作会将“设置 → 阅读”的首页改为原站 Home 页面。</p>
        <form method="post"><?php wp_nonce_field( 'ian_settings_save' ); ?><button class="button" name="ian_set_home" value="1">将原站 Home 设为首页</button></form>
        <h2>3. 询价入口</h2><p>询价表单会打开访客的邮件应用或 WhatsApp，由访客发送；网站不保存提交。需要站内收件时，可将简码区块替换为 Contact Form 7 / WPForms 的表单区块，并配置邮件服务。</p>
        <form method="post"><?php wp_nonce_field( 'ian_settings_save' ); ?>
            <p><label>销售邮箱<br><input type="email" class="regular-text" name="ian_sales_email" value="<?php echo esc_attr( get_option( 'ian_sales_email', 'sales@ianproject.com' ) ); ?>" required></label></p>
            <p><label>WhatsApp 号码（含国家码，仅数字）<br><input type="text" class="regular-text" name="ian_whatsapp" value="<?php echo esc_attr( get_option( 'ian_whatsapp', '8619956229033' ) ); ?>" required></label></p>
            <button class="button" name="ian_settings_save" value="1">保存询价入口</button>
        </form>
        <h2>日常编辑</h2><p>页面 → 首页 / 关于 / 案例等：修改文字、图片、按钮和区块顺序。产品 → 全部产品：编辑详情、价格、摘要和特色图片。行业文章：添加和编辑文章。外观 → 编辑器：编辑页眉、导航、页脚及全站颜色字体。</p>
        <p>产品列表由 <code>[ian_catalog]</code> 简码自动生成；产品价格由 <code>[ian_product_price]</code> 简码读取产品字段。请保留这些简码区块。</p>
    </div>
    <script>
    (()=>{const button=document.getElementById('ian-start-import'),status=document.getElementById('ian-import-status'),progress=document.getElementById('ian-import-progress');button.addEventListener('click',async()=>{button.disabled=true;progress.style.display='block';let offset=0,created=0,skipped=0;try{while(true){status.textContent='正在导入，请保持页面打开… '+offset;const body=new URLSearchParams({action:'ian_import_batch',nonce:<?php echo wp_json_encode( wp_create_nonce( 'ian_import_batch' ) ); ?>,offset:String(offset)});const response=await fetch(ajaxurl,{method:'POST',body,credentials:'same-origin'});const result=await response.json();if(!result.success)throw new Error(result.data?.message||'导入失败');const data=result.data;created+=data.imported;skipped+=data.skipped;offset=data.offset;progress.value=data.total?100*offset/data.total:100;if(data.errors.length)throw new Error(data.errors.join('; '));if(data.done){status.textContent='导入完成：新增 '+created+' 项，跳过已有 '+skipped+' 项。现在可以点击“将原站 Home 设为首页”。';break;}}}catch(error){status.textContent='导入暂停：'+error.message+'。可重试，已有内容会跳过。';}finally{button.disabled=false;}});})();
    </script>
    <?php
}

add_action( 'wp_ajax_ian_import_batch', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => '没有导入权限。' ), 403 ); }
    check_ajax_referer( 'ian_import_batch', 'nonce' );
    $offset = max( 0, absint( $_POST['offset'] ?? 0 ) );
    $manifest = ianproject_manifest();
    if ( ! $manifest ) { wp_send_json_error( array( 'message' => '找不到主题内容文件。' ), 500 ); }
    foreach ( $manifest['categories'] ?? array() as $category ) {
        if ( ! term_exists( $category['id'], 'ian_product_category' ) ) { wp_insert_term( $category['name'], 'ian_product_category', array( 'slug' => $category['id'] ) ); }
    }
    wp_send_json_success( ianproject_import_batch( $offset ) );
} );
