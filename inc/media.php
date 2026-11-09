<?php
defined( 'ABSPATH' ) || exit;

/** Copy only local image assets from recognized IanProject themes; never fetch URLs. */
function ianproject_media_source( $url ) {
    if ( ! is_string( $url ) ) { return null; }
    $url = html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $root = wp_parse_url( get_theme_root_uri() );
    $parts = wp_parse_url( $url );
    if ( ! is_array( $parts ) || empty( $parts['path'] ) ) { return null; }
    if ( isset( $parts['host'] ) && strtolower( $parts['host'] ) !== strtolower( $root['host'] ?? '' ) ) { return null; }
    $path = rawurldecode( $parts['path'] );
    $prefix = trailingslashit( $root['path'] ?? '/wp-content/themes' );
    if ( ! str_starts_with( $path, $prefix ) || preg_match( '~(?:^|/)\.\.(?:/|$)|[\\\\\x00]~', $path ) ) { return null; }
    $relative = substr( $path, strlen( $prefix ) );
    $segments = explode( '/', $relative );
    if ( count( $segments ) < 4 || 'assets' !== $segments[1] || ! preg_match( '~\.(?:jpe?g|png|webp|gif|avif)$~i', $path ) ) { return null; }
    $theme = wp_get_theme( $segments[0] );
    if ( ! $theme->exists() || ! str_starts_with( $theme->get( 'Name' ), 'IanProject' ) ) { return null; }
    $base = realpath( get_theme_root() );
    $file = realpath( get_theme_root() . '/' . $relative );
    if ( ! $base || ! $file || ! str_starts_with( $file, $base . DIRECTORY_SEPARATOR ) || ! is_file( $file ) || ! is_readable( $file ) ) {
        return new WP_Error( 'ian_media_missing', '找不到旧主题图片：' . $relative . '。请恢复旧主题文件后重试。' );
    }
    return $file;
}

function ianproject_media_copy( $file, &$created ) {
    static $cache = array();
    $hash = hash_file( 'sha256', $file );
    if ( isset( $cache[ $hash ] ) ) { return $cache[ $hash ]; }
    $existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_ian_media_hash_v2', 'meta_value' => $hash, 'posts_per_page' => 1, 'fields' => 'ids' ) );
    if ( $existing ) {
        $path = get_attached_file( $existing[0] );
        $url = wp_get_attachment_url( $existing[0] );
        if ( $path && is_file( $path ) && $url ) { return $cache[ $hash ] = $url; }
    }
    $upload = wp_upload_bits( 'ian-' . substr( $hash, 0, 12 ) . '-' . sanitize_file_name( basename( $file ) ), null, file_get_contents( $file ) );
    if ( ! empty( $upload['error'] ) ) { return new WP_Error( 'ian_media_write', $upload['error'] ); }
    $mime = wp_check_filetype( $upload['file'] );
    $id = wp_insert_attachment( array( 'post_title' => pathinfo( basename( $file ), PATHINFO_FILENAME ), 'post_status' => 'inherit', 'post_mime_type' => $mime['type'] ), $upload['file'], 0, true );
    if ( is_wp_error( $id ) ) { return $id; }
    $size = wp_getimagesize( $upload['file'] );
    if ( $size ) { wp_update_attachment_metadata( $id, array( 'width' => $size[0], 'height' => $size[1], 'file' => _wp_relative_upload_path( $upload['file'] ), 'sizes' => array(), 'filesize' => filesize( $upload['file'] ) ) ); }
    update_post_meta( $id, '_ian_media_hash_v2', $hash );
    ++$created;
    return $cache[ $hash ] = $upload['url'];
}

function ianproject_media_rewrite( $value, &$created, &$errors ) {
    if ( is_array( $value ) ) {
        foreach ( $value as $key => $item ) { $value[ $key ] = ianproject_media_rewrite( $item, $created, $errors ); }
        return $value;
    }
    if ( ! is_string( $value ) ) { return $value; }
    // Old product metadata keeps token-based cover paths; turn those into portable media too.
    if ( str_starts_with( $value, '{{IAN_ASSET}}/' ) || str_starts_with( $value, 'img/' ) ) {
        $relative = str_replace( '{{IAN_ASSET}}/', '', $value );
        $stylesheet = get_option( 'ian_legacy_stylesheet_v2', get_stylesheet() );
        $legacy = wp_get_theme( $stylesheet );
        $file = $legacy->get_stylesheet_directory() . '/assets/' . $relative;
        if ( is_file( $file ) && preg_match( '~\.(?:jpe?g|png|webp|gif|avif)$~i', $file ) ) {
            $source = ianproject_media_source( trailingslashit( get_theme_root_uri() ) . $stylesheet . '/assets/' . $relative );
            if ( is_string( $source ) ) {
                $result = ianproject_media_copy( $source, $created );
                if ( is_wp_error( $result ) ) { $errors[] = $result->get_error_message(); } else { return $result; }
            }
        }
        // Keep unresolved optional metadata; visible absolute image URLs are checked below.
        return $value;
    }
    return preg_replace_callback( '~(?:https?://[^\s"\'<>\\\\()]+|/[^\s"\'<>\\\\()]*wp-content/themes/[^\s"\'<>\\\\()]+)~i', function ( $match ) use ( &$created, &$errors ) {
        $source = ianproject_media_source( $match[0] );
        if ( is_wp_error( $source ) ) { $errors[] = $source->get_error_message(); return $match[0]; }
        if ( ! is_string( $source ) ) { return $match[0]; }
        $result = ianproject_media_copy( $source, $created );
        if ( is_wp_error( $result ) ) { $errors[] = $result->get_error_message(); return $match[0]; }
        return $result;
    }, $value );
}

function ianproject_migrate_media_batch( $offset, $limit = 1 ) {
    $query = new WP_Query( array( 'post_type' => array( 'page', 'ian_product', 'ian_insight', 'wp_template', 'wp_template_part' ), 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => max( 1, min( 3, $limit ) ), 'offset' => max( 0, $offset ), 'orderby' => 'ID', 'order' => 'ASC' ) );
    $report = array( 'offset' => $offset + count( $query->posts ), 'total' => (int) $query->found_posts, 'updated' => 0, 'files' => 0, 'errors' => array() );
    foreach ( $query->posts as $post ) {
        $errors = array();
        $old_data = get_post_meta( $post->ID, '_ian_product_data', true );
        $content = ianproject_media_rewrite( $post->post_content, $report['files'], $errors );
        $data = ianproject_media_rewrite( $old_data, $report['files'], $errors );
        if ( $errors ) { $report['errors'] = array_merge( $report['errors'], $errors ); continue; }
        if ( $content === $post->post_content && $data === $old_data ) { continue; }
        if ( ! metadata_exists( 'post', $post->ID, '_ian_media_backup_2_0_0' ) ) {
            add_post_meta( $post->ID, '_ian_media_backup_2_0_0', wp_slash( array( 'content' => $post->post_content, 'product_data' => $old_data ) ), true );
        }
        if ( $content !== $post->post_content ) {
            wp_save_post_revision( $post->ID );
            $result = wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_content' => $content ) ), true );
            if ( is_wp_error( $result ) ) { $report['errors'][] = $result->get_error_message(); continue; }
        }
        if ( $data !== $old_data ) { update_post_meta( $post->ID, '_ian_product_data', wp_slash( $data ) ); }
        ++$report['updated'];
    }
    $report['done'] = $report['offset'] >= $report['total'];
    if ( $report['updated'] ) { do_action( 'litespeed_purge_all' ); }
    return $report;
}

add_action( 'wp_ajax_ian_migrate_media_v2', function () {
    if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => '没有迁移权限。' ), 403 ); }
    check_ajax_referer( 'ian_media_v2', 'nonce' );
    wp_send_json_success( ianproject_migrate_media_batch( absint( $_POST['offset'] ?? 0 ) ) );
} );

add_action( 'admin_footer', function () {
    if ( ! current_user_can( 'manage_options' ) || 'ianproject-setup' !== ( $_GET['page'] ?? '' ) ) { return; }
    ?>
    <script>
    (()=>{const button=document.getElementById('ian-media-start'),status=document.getElementById('ian-media-status');if(!button)return;button.addEventListener('click',async()=>{button.disabled=true;let offset=0,files=0,updated=0;try{while(true){status.textContent='正在迁移图片，请保持页面打开… 已检查 '+offset+' 项';const body=new URLSearchParams({action:'ian_migrate_media_v2',nonce:<?php echo wp_json_encode( wp_create_nonce( 'ian_media_v2' ) ); ?>,offset:String(offset)});const response=await fetch(ajaxurl,{method:'POST',body,credentials:'same-origin'});const result=await response.json();if(!result.success)throw new Error(result.data?.message||'迁移失败');const data=result.data;files+=data.files;updated+=data.updated;if(data.errors.length)throw new Error(data.errors.join('；'));offset=data.offset;if(data.done){status.textContent='迁移与检查完成：更新 '+updated+' 项，新增媒体 '+files+' 张。可以检查前台图片，再删除旧主题。';break;}}}catch(error){status.textContent='迁移尚未完成：'+error.message+' 请保留旧主题，修复后可重试；已迁移的图片不会重复创建。';}finally{button.disabled=false;}});})();
    </script>
    <?php
} );
