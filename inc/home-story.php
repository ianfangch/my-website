<?php
defined( 'ABSPATH' ) || exit;

/** Only meaningful quote text enables a reserved review card on the front end. */
function ianproject_home_quote_text( $block ) {
    $text = '';
    foreach ( $block['innerBlocks'] ?? array() as $child ) {
        if ( 'core/paragraph' === ( $child['blockName'] ?? '' ) ) { $text .= wp_strip_all_tags( $child['innerHTML'] ?? '' ); }
        $text .= ianproject_home_quote_text( $child );
    }
    return trim( preg_replace( '/[\s\x{00A0}\x{200B}]+/u', ' ', $text ) );
}
function ianproject_home_has_review( $block ) {
    if ( 'core/quote' === ( $block['blockName'] ?? '' ) && ianproject_home_quote_text( $block ) ) { return true; }
    foreach ( $block['innerBlocks'] ?? array() as $child ) { if ( ianproject_home_has_review( $child ) ) { return true; } }
    return false;
}
add_filter( 'render_block', function ( $html, $block ) {
    if ( is_admin() ) { return $html; }
    $classes = preg_split( '/\s+/', $block['attrs']['className'] ?? '' );
    if ( in_array( 'ih-review-card', $classes, true ) && ! ianproject_home_quote_text( $block ) ) { return ''; }
    if ( in_array( 'ih-reviews', $classes, true ) ) {
        if ( ! ianproject_home_has_review( $block ) ) { return ''; }
        $p = new WP_HTML_Tag_Processor( $html );
        if ( $p->next_tag() ) { $p->remove_class( 'ih-review-pending' ); }
        return $p->get_updated_html();
    }
    return $html;
}, 35, 2 );

function ianproject_home_story_draft() {
    $id = absint( get_option( 'ian_home_story_draft_id' ) );
    if ( $id && get_post( $id ) && ! in_array( get_post_status( $id ), array( 'trash', 'auto-draft' ), true ) && 'story-v1' === get_post_meta( $id, '_ian_home_variant', true ) ) { return $id; }
    $file = get_theme_file_path( 'data/home-story.html' );
    if ( ! is_readable( $file ) ) { return new WP_Error( 'ian_home_source', '找不到新版首页内容。' ); }
    $id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_name' => 'ianproject-home-story', 'post_title' => 'IanProject Home — 新版首页', 'post_content' => ianproject_tokens( file_get_contents( $file ) ), 'comment_status' => 'closed', 'ping_status' => 'closed' ) ), true );
    if ( is_wp_error( $id ) ) { return $id; }
    update_post_meta( $id, '_ian_home_variant', 'story-v1' );
    update_post_meta( $id, '_ian_source', 'home-story.html' );
    update_post_meta( $id, '_ian_description', 'China-based home product sourcing, custom solutions and coordinated project supply — from quotation to delivery.' );
    update_option( 'ian_home_story_draft_id', $id, false );
    return $id;
}

function ianproject_activate_home_story( $id ) {
    if ( 'story-v1' !== get_post_meta( $id, '_ian_home_variant', true ) || 'page' !== get_post_type( $id ) ) { return new WP_Error( 'ian_home_invalid', '请选择由本工具创建的首页草稿。' ); }
    if ( absint( get_option( 'page_on_front' ) ) === $id && 'page' === get_option( 'show_on_front' ) ) { return true; }
    $old_id = absint( get_option( 'page_on_front' ) );
    $backup = array( 'page_on_front' => $old_id, 'show_on_front' => get_option( 'show_on_front' ), 'ian_homepage_id' => get_option( 'ian_homepage_id' ), 'old_source' => $old_id ? get_post_meta( $old_id, '_ian_source', true ) : '', 'new_id' => $id );
    $result = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
    if ( is_wp_error( $result ) ) { return $result; }
    update_option( 'ian_home_story_previous', $backup, false );
    if ( $old_id && 'index.html' === $backup['old_source'] ) { update_post_meta( $old_id, '_ian_source', 'archived-home-' . $old_id ); }
    update_post_meta( $id, '_ian_source', 'index.html' );
    update_option( 'ian_homepage_id', $id );
    update_option( 'page_on_front', $id );
    update_option( 'show_on_front', 'page' );
    do_action( 'litespeed_purge_all' );
    return true;
}
function ianproject_restore_home_story() {
    $backup = get_option( 'ian_home_story_previous' );
    if ( ! is_array( $backup ) || ! isset( $backup['page_on_front'], $backup['new_id'] ) ) { return new WP_Error( 'ian_home_backup', '没有可恢复的首页设置。' ); }
    $old_id = absint( $backup['page_on_front'] );
    if ( $old_id && ! get_post( $old_id ) ) { return new WP_Error( 'ian_home_backup_missing', '原首页已不存在，无法恢复。' ); }
    if ( $old_id ) { update_post_meta( $old_id, '_ian_source', $backup['old_source'] ); }
    update_post_meta( $backup['new_id'], '_ian_source', 'home-story.html' );
    update_option( 'page_on_front', $old_id );
    update_option( 'show_on_front', $backup['show_on_front'] );
    update_option( 'ian_homepage_id', $backup['ian_homepage_id'] );
    delete_option( 'ian_home_story_previous' );
    do_action( 'litespeed_purge_all' );
    return true;
}

add_action( 'admin_menu', function () { add_theme_page( 'Home 首页改版', 'Home 首页改版', 'manage_options', 'ianproject-home-story', 'ianproject_home_story_admin' ); } );
function ianproject_home_story_admin() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    if ( isset( $_POST['ian_home_story_action'] ) ) {
        check_admin_referer( 'ian_home_story' );
        $action = sanitize_key( wp_unslash( $_POST['ian_home_story_action'] ) );
        if ( 'create' === $action ) { $result = ianproject_home_story_draft(); }
        elseif ( 'activate' === $action ) { $result = ianproject_activate_home_story( absint( get_option( 'ian_home_story_draft_id' ) ) ); }
        elseif ( 'restore' === $action ) { $result = ianproject_restore_home_story(); }
        else { $result = new WP_Error( 'ian_home_action', '未知操作。' ); }
        echo '<div class="notice ' . ( is_wp_error( $result ) ? 'notice-error' : 'notice-success' ) . '"><p>' . ( is_wp_error( $result ) ? esc_html( $result->get_error_message() ) : '操作完成。' ) . '</p></div>';
    }
    $id = absint( get_option( 'ian_home_story_draft_id' ) );
    echo '<div class="wrap" style="max-width:900px"><h1>Home 首页改版</h1><p>新版按四部分组织：我们是谁 → 提供什么 → 报价到交付 → 项目参考与服务体验。本页只操作首页，不迁移文章或产品，也不改导航和其他页面。</p><p>先创建草稿并预览，确认后再设为首页。创建草稿不会切换当前首页；重复点击会保留已经编辑的草稿。</p><form method="post">';
    wp_nonce_field( 'ian_home_story' );
    echo '<button class="button button-primary" name="ian_home_story_action" value="create">创建 / 打开新版首页草稿</button>';
    if ( $id && get_post( $id ) ) {
        echo '<p><a class="button" href="' . esc_url( get_edit_post_link( $id ) ) . '">编辑新版首页</a> <a class="button" target="_blank" rel="noopener" href="' . esc_url( get_preview_post_link( $id ) ) . '">预览新版首页</a></p><button class="button" name="ian_home_story_action" value="activate">发布并设为首页</button>';
    }
    if ( get_option( 'ian_home_story_previous' ) ) { echo ' <button class="button" name="ian_home_story_action" value="restore">恢复之前的首页</button>'; }
    echo '</form><h2>填写客户评价</h2><p>在首页编辑器打开“列表视图”，找到“客户评价｜填入真实内容后自动显示”，其中预留三条评价。填写评价正文及客户称呼，然后保存。空评价自动隐藏；整个区域没有内容时也自动隐藏。</p><p>实力数字可直接编辑四个数字区块。全站邮箱与 WhatsApp 继续在“设置 → IanProject 联系方式”中统一修改。</p></div>';
}
