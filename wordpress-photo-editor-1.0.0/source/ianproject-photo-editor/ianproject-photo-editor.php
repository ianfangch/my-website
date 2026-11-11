<?php
/**
 * Plugin Name: IanProject 图片编辑器
 * Description: 从媒体库或电脑选图，裁剪、去白边、调整尺寸、转换格式和命名，另存为新图片。
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.0
 * Author: IanProject
 * License: GPL-2.0-or-later
 */
defined( 'ABSPATH' ) || exit;
add_action( 'admin_menu', function () {
    add_media_page( 'IanProject 图片编辑器', 'IanProject 图片编辑器', 'upload_files', 'ianproject-photo-editor', 'ianpe_page' );
} );
function ianpe_page() {
    if ( ! current_user_can( 'upload_files' ) ) { return; }
    echo '<div class="wrap ianpe-admin">';
    // Static UI shipped with this plugin. No user-controlled HTML is included.
    readfile( __DIR__ . '/assets/editor.html' );
    echo '</div>';
}
function ianpe_edit_url( $id ) { return add_query_arg( array( 'page' => 'ianproject-photo-editor', 'source' => absint( $id ) ), admin_url( 'upload.php' ) ); }
add_filter( 'media_row_actions', function ( $actions, $post ) {
    if ( current_user_can( 'upload_files' ) && in_array( get_post_mime_type( $post->ID ), array_values( ianpe_mimes() ), true ) ) { $actions['ianpe'] = '<a href="' . esc_url( ianpe_edit_url( $post->ID ) ) . '" target="_blank" rel="noopener">裁剪并另存新图</a>'; }
    return $actions;
}, 10, 2 );
add_filter( 'attachment_fields_to_edit', function ( $fields, $post ) {
    if ( current_user_can( 'upload_files' ) && in_array( get_post_mime_type( $post->ID ), array_values( ianpe_mimes() ), true ) ) { $fields['ianpe'] = array( 'label' => 'IanProject', 'input' => 'html', 'html' => '<a class="button" href="' . esc_url( ianpe_edit_url( $post->ID ) ) . '" target="_blank" rel="noopener">裁剪并另存新图 ↗</a>' ); }
    return $fields;
}, 10, 2 );
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( 'media_page_ianproject-photo-editor' !== $hook ) { return; }
    wp_enqueue_media();
    wp_enqueue_style( 'ianpe', plugins_url( 'assets/editor.css', __FILE__ ), array(), '1.0.0' );
    wp_enqueue_script( 'ianpe', plugins_url( 'assets/editor.js', __FILE__ ), array( 'media-views' ), '1.0.0', true );
    wp_localize_script( 'ianpe', 'IANPE_CONFIG', array( 'wordpress' => true, 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ianpe' ), 'maxUpload' => min( wp_max_upload_size(), 20 * MB_IN_BYTES ), 'initialAttachment' => is_scalar( $_GET['source'] ?? '' ) ? absint( $_GET['source'] ?? 0 ) : 0 ) );
} );
function ianpe_access() {
    if ( ! current_user_can( 'upload_files' ) ) { wp_send_json_error( array( 'message' => '没有上传图片的权限。' ), 403 ); }
    check_ajax_referer( 'ianpe', 'nonce' );
}
function ianpe_mimes() { return array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ); }
add_action( 'wp_ajax_ianpe_source', function () {
    ianpe_access();
    $id = absint( $_POST['id'] ?? 0 );
    if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'read_post', $id ) || ! in_array( get_post_mime_type( $id ), array_values( ianpe_mimes() ), true ) ) { wp_send_json_error( array( 'message' => '请选择 JPEG、PNG 或 WebP 图片。' ), 400 ); }
    $path = wp_get_original_image_path( $id ) ?: get_attached_file( $id );
    wp_send_json_success( array( 'id' => $id, 'url' => wp_get_original_image_url( $id ) ?: wp_get_attachment_url( $id ), 'name' => basename( $path ?: 'image' ), 'title' => get_the_title( $id ), 'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ), 'type' => get_post_mime_type( $id ) ) );
} );
/** Validates the generated image before WordPress handles a new upload. */
function ianpe_validate_image( $file ) {
    if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $file['tmp_name'] ) ) { return new WP_Error( 'ianpe_upload', '没有收到完整的图片文件。' ); }
    if ( (int) ( $file['size'] ?? 0 ) <= 0 || (int) $file['size'] > min( wp_max_upload_size(), 20 * MB_IN_BYTES ) ) { return new WP_Error( 'ianpe_size', '图片超过本站允许的上传大小。' ); }
    $mime = wp_get_image_mime( $file['tmp_name'] );
    $size = wp_getimagesize( $file['tmp_name'] );
    if ( ! in_array( $mime, array_values( ianpe_mimes() ), true ) || ! $size || $size[0] < 16 || $size[1] < 16 || $size[0] > 8192 || $size[1] > 8192 || $size[0] * $size[1] > 24000000 ) { return new WP_Error( 'ianpe_image', '图片格式或尺寸不支持；仅接收 JPEG / PNG / WebP，最长边 8192，最多 2400 万像素。' ); }
    return array( 'mime' => $mime, 'width' => $size[0], 'height' => $size[1], 'extension' => array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' )[ $mime ] );
}
add_action( 'wp_ajax_ianpe_save', function () {
    ianpe_access();
    $file = $_FILES['image'] ?? null;
    if ( ! $file || ! is_uploaded_file( $file['tmp_name'] ?? '' ) ) { wp_send_json_error( array( 'message' => '请重新导出并上传图片。' ), 400 ); }
    $valid = ianpe_validate_image( $file );
    if ( is_wp_error( $valid ) ) { wp_send_json_error( array( 'message' => $valid->get_error_message() ), 400 ); }
    $base = pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_FILENAME );
    $_FILES['image']['name'] = ( $base ?: 'ianproject-image' ) . '.' . $valid['extension'];
    $_FILES['image']['type'] = $valid['mime'];
    $title = isset( $_POST['title'] ) && is_scalar( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : $base;
    $alt = isset( $_POST['alt'] ) && is_scalar( $_POST['alt'] ) ? sanitize_text_field( wp_unslash( $_POST['alt'] ) ) : '';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    // Preserve the explicitly chosen output dimensions for this upload only.
    add_filter( 'big_image_size_threshold', '__return_false' );
    try { $id = media_handle_upload( 'image', 0, array( 'post_title' => $title ?: $base ), array( 'test_form' => false, 'mimes' => ianpe_mimes() ) ); }
    finally { remove_filter( 'big_image_size_threshold', '__return_false' ); }
    if ( is_wp_error( $id ) ) { wp_send_json_error( array( 'message' => $id->get_error_message() ), 400 ); }
    update_post_meta( $id, '_wp_attachment_image_alt', $alt );
    $source = absint( $_POST['source'] ?? 0 );
    if ( 'attachment' === get_post_type( $source ) && current_user_can( 'read_post', $source ) ) { update_post_meta( $id, '_ianpe_source_attachment', $source ); }
    update_post_meta( $id, '_ianpe_version', '1.0.0' );
    wp_send_json_success( array( 'id' => $id, 'url' => wp_get_attachment_url( $id ), 'edit' => get_edit_post_link( $id, 'raw' ), 'name' => basename( get_attached_file( $id ) ), 'width' => $valid['width'], 'height' => $valid['height'] ) );
} );
