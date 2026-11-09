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

function ianproject_imported_post( $source, $post_type = 'page' ) {
    $posts = get_posts( array( 'post_type' => $post_type, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'meta_key' => '_ian_source', 'meta_value' => $source, 'posts_per_page' => 1, 'orderby' => 'ID', 'order' => 'ASC' ) );
    return $posts ? $posts[0] : null;
}

function ianproject_homepage_id() {
    $id = absint( get_option( 'ian_homepage_id' ) );
    if ( $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) && 'index.html' === get_post_meta( $id, '_ian_source', true ) ) { return $id; }
    $page = ianproject_imported_post( 'index.html' );
    return $page ? $page->ID : 0;
}

function ianproject_set_homepage() {
    $id = ianproject_homepage_id();
    if ( ! $id ) { return new WP_Error( 'ian_home_missing', '尚未找到导入的原站首页。请先点击「导入网站页面」，再设置首页。' ); }
    if ( 'publish' !== get_post_status( $id ) ) { return new WP_Error( 'ian_home_not_published', '原站首页尚未发布。请先在页面编辑器发布该页，再设置首页。' ); }
    update_option( 'ian_homepage_id', $id );
    update_option( 'page_on_front', $id );
    update_option( 'show_on_front', 'page' );
    if ( 'page' !== get_option( 'show_on_front' ) || $id !== absint( get_option( 'page_on_front' ) ) ) { return new WP_Error( 'ian_home_save_failed', '首页设置未保存成功，请稍后重试。' ); }
    do_action( 'litespeed_purge_all' );
    return true;
}

function ianproject_import_records() {
    $manifest = ianproject_manifest(); $records = array();
    foreach ( array( 'pages' => 'page', 'posts' => 'ian_insight' ) as $group => $type ) {
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

/** Preserve imported edits and keep unrelated old pages when paths collide. */
function ianproject_import_batch( $offset, $limit = 4 ) {
    $all = ianproject_import_records(); $imported = 0; $skipped = 0; $errors = array();
    foreach ( array_slice( $all, $offset, $limit ) as $index => $record ) {
        $source = $record['source'] ?? ( 'products/' . ( $record['id'] ?? $record['slug'] ?? '' ) . '.html' );
        $type = $record['_type'];
        $existing = ianproject_imported_post( $source, $type );
        if ( $existing ) {
            if ( 'index.html' === $source ) { update_option( 'ian_homepage_id', $existing->ID ); }
            ++$skipped;
            continue;
        }
        $slug = sanitize_title( $record['slug'] ?? $record['id'] ?? '' );
        $parent = 0;
        if ( ! empty( $record['parent'] ) ) {
            $parent_post = ianproject_imported_post( sanitize_title( $record['parent'] ) . '.html' );
            if ( $parent_post ) { $parent = $parent_post->ID; }
        }
        $path = $parent ? get_page_uri( $parent ) . '/' . $slug : $slug;
        if ( get_page_by_path( $path, OBJECT, $type ) ) { $slug = 'ianproject-' . $slug; }
        $post_id = wp_insert_post( wp_slash( array(
            'post_type' => $type, 'post_status' => 'draft' === ( $record['status'] ?? '' ) ? 'draft' : 'publish',
            'post_title' => $record['title'] ?? $slug, 'post_name' => $slug,
            'post_content' => ianproject_tokens( ianproject_repair_category_content( ianproject_repair_layout_content( $record['content'] ?? '', $source ), $source, false ) ),
            'post_excerpt' => $record['summary'] ?? $record['description'] ?? '',
            'post_parent' => $parent, 'menu_order' => $offset + $index,
            'comment_status' => 'closed', 'ping_status' => 'closed',
        ) ), true );
        if ( is_wp_error( $post_id ) ) { $errors[] = $source . ': ' . $post_id->get_error_message(); continue; }
        update_post_meta( $post_id, '_ian_source', $source );
        update_post_meta( $post_id, '_ian_description', sanitize_text_field( $record['description'] ?? $record['seoDescription'] ?? $record['summary'] ?? '' ) );
        if ( 'index.html' === $source ) { update_option( 'ian_homepage_id', $post_id ); }
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
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $home = ianproject_homepage_id();
    if ( ! get_option( 'ian_import_complete' ) || ! $home || 'page' !== get_option( 'show_on_front' ) || $home !== absint( get_option( 'page_on_front' ) ) ) {
        echo '<div class="notice notice-info"><p>IanProject：请确认首页已切换到导入的原站页面。<a href="' . esc_url( admin_url( 'themes.php?page=ianproject-setup' ) ) . '">进入快速设置，查看当前首页并补齐导入</a>。</p></div>';
    }
} );

function ianproject_setup_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    if ( isset( $_POST['ian_set_home'] ) ) {
        check_admin_referer( 'ian_settings_save' );
        $result = ianproject_set_homepage();
        echo is_wp_error( $result ) ? '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>' : '<div class="notice notice-success"><p>首页已切换到导入的原站页面，旧页面仍保留。</p></div>';
    }
    $home_id = ianproject_homepage_id();
    $current_home = 'page' === get_option( 'show_on_front' ) ? get_post( absint( get_option( 'page_on_front' ) ) ) : null;
    $imported_home = $home_id ? get_post( $home_id ) : null;
    ?>
    <div class="wrap" style="max-width:900px"><h1>IanProject 快速设置</h1>
        <p>主题只包含网站页面和行业文章布局，不包含产品数据。产品由独立的 IanProject 产品目录插件管理；您可以从空目录开始重新制作产品，或继续编辑现有产品。页面内容仍可使用原生区块编辑器修改。</p>
        <h2>当前首页检查</h2>
        <p>当前首页：<?php if ( $current_home ) : ?><strong><?php echo esc_html( $current_home->post_title ); ?></strong>（ID <?php echo absint( $current_home->ID ); ?>） <a href="<?php echo esc_url( get_permalink( $current_home ) ); ?>" target="_blank" rel="noopener">查看</a><?php else : ?>最新文章<?php endif; ?></p>
        <p>导入的原站首页：<?php if ( $imported_home ) : ?><strong><?php echo esc_html( $imported_home->post_title ); ?></strong>（ID <?php echo absint( $home_id ); ?>，<?php echo esc_html( $imported_home->post_status ); ?>） <a href="<?php echo esc_url( get_preview_post_link( $home_id ) ); ?>" target="_blank" rel="noopener">预览</a> · <a href="<?php echo esc_url( get_edit_post_link( $home_id ) ); ?>">编辑</a><?php else : ?><strong>尚未导入，请先执行第 1 步。</strong><?php endif; ?></p>
        <?php if ( $imported_home && $current_home && $home_id !== $current_home->ID ) : ?><p><strong>当前仍显示另一个页面，请完成第 2 步切换首页。</strong></p><?php endif; ?>
        <?php $front_template = get_block_template( get_stylesheet() . '//front-page', 'wp_template' ); if ( $front_template && 'custom' === $front_template->source ) : ?><p>检测到站点编辑器保存过自定义首页模板。如果切换页面后仍显示旧正文，请在 <a href="<?php echo esc_url( admin_url( 'site-editor.php' ) ); ?>">外观 → 编辑器</a> 检查「首页」模板是否包含「页面内容」区块；自定义模板会优先于主题默认模板。</p><?php endif; ?>
        <h2>1. 导入网站页面（不含产品）</h2><p>只导入页面和行业文章，不会创建产品、产品分类或产品示例草稿。重复导入保留已编辑的内容，不会自动切换首页。</p>
        <button id="ian-start-import" class="button button-primary">导入网站页面</button><p id="ian-import-status" aria-live="polite"></p><progress id="ian-import-progress" value="0" max="100" style="width:100%;display:none"></progress>
        <h2>2. 设置首页</h2><p>导入完成后点击。此操作会将“设置 → 阅读”的首页改为原站 Home 页面。</p>
        <form method="post"><?php wp_nonce_field( 'ian_settings_save' ); ?><button class="button" name="ian_set_home" value="1">将原站 Home 设为首页</button></form>
        <h2>3. 联系方式</h2><p>全站仅使用一个通用邮箱。联系方式和全部修复均已整合到主题，不需要原来的网站修复插件。</p>
        <p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'options-general.php?page=ianproject-contacts' ) ); ?>">修改全站联系方式</a></p>
        <h2>4. 现有站点升级：迁移旧主题图片</h2>
        <p>首次切换到新版主题后，旧页面或产品可能仍引用旧主题目录中的图片。点击后会逐批复制到媒体库，并更新对应引用。完成且无错误后，再删除旧主题。迁移会保留原始正文备份，不会删除产品。</p>
        <button id="ian-media-start" class="button">迁移并检查旧主题图片</button><p id="ian-media-status" aria-live="polite"></p>
        <h2>日常编辑</h2><p>页面 → 首页 / 关于 / 案例等：修改文字、图片、按钮和区块顺序。产品 → 全部产品：编辑详情、价格、摘要和特色图片。行业文章：添加和编辑文章。外观 → 编辑器：编辑页眉、导航、页脚及全站颜色字体。产品菜单需要单独启用产品目录插件。</p>
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
    wp_send_json_success( ianproject_import_batch( $offset ) );
} );
