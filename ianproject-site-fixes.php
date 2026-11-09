<?php
/**
 * Plugin Name: IanProject 网站修复与联系方式
 * Description: IanProject 分类配图、私密预览导航修复与全站联系方式集中管理。
 * Version: 1.0.3
 * Requires at least: 6.7
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/contacts.php';

function iansf_compatible() {
    return function_exists( 'ianproject_manifest' ) && function_exists( 'ianproject_tokens' );
}

function iansf_boot() {
    static $booted = false;
    if ( $booted || ! iansf_compatible() ) { return; }
    $booted = true;
    if ( ! function_exists( 'ianproject_preview_token' ) ) { require_once __DIR__ . '/includes/preview.php'; }
    if ( ! function_exists( 'ianproject_repair_category_content' ) ) { require_once __DIR__ . '/includes/content-repairs.php'; }
    add_action( 'init', 'iansf_footer_pattern', 20 );
    iansf_contacts_boot();
}
add_action( 'after_setup_theme', 'iansf_boot', 20 );
if ( did_action( 'after_setup_theme' ) ) { iansf_boot(); }

function iansf_footer_pattern() {
    $registry = WP_Block_Patterns_Registry::get_instance();
    $pattern = $registry->get_registered( 'ianproject/footer' );
    if ( ! $pattern || empty( $pattern['content'] ) ) { return; }
    $content = ianproject_repair_category_content( $pattern['content'], 'footer' );
    if ( $content === $pattern['content'] ) { return; }
    unset( $pattern['name'] );
    $pattern['content'] = $content;
    unregister_block_pattern( 'ianproject/footer' );
    register_block_pattern( 'ianproject/footer', $pattern );
}

function iansf_activate() {
    if ( ! iansf_compatible() ) { wp_die( '请先启用之前安装的 IanProject WordPress 主题，再启用此插件。' ); }
    iansf_boot();
    iansf_seed_contacts();
    iansf_footer_pattern();
    $report = ianproject_migrate_category_content();
    update_option( 'ian_content_report_1_0_3', $report, false );
    if ( ! $report['errors'] ) { update_option( 'ian_content_upgraded_1_0_3', true, false ); }
    do_action( 'litespeed_purge_all' );
}
register_activation_hook( __FILE__, 'iansf_activate' );
register_deactivation_hook( __FILE__, function () { do_action( 'litespeed_purge_all' ); } );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=ianproject-contacts' ) ) . '">修改联系方式</a>' );
    return $links;
} );

add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    if ( ! iansf_compatible() ) {
        echo '<div class="notice notice-warning"><p>IanProject 修复插件需要 IanProject 主题；当前主题下不会改动页面。</p></div>';
    } elseif ( isset( $_GET['activate'] ) || ( isset( $_GET['page'] ) && 'ianproject-setup' === $_GET['page'] ) ) {
        echo '<div class="notice notice-info"><p>邮箱、电话、WhatsApp、地址与社交链接已集中管理：<a href="' . esc_url( admin_url( 'options-general.php?page=ianproject-contacts' ) ) . '">设置 → IanProject 联系方式</a>。保存一次即可同步全站。</p></div>';
    }
    $report = get_option( 'ian_content_report_1_0_3', array() );
    if ( ! empty( $report['errors'] ) ) {
        echo '<div class="notice notice-error"><p>部分分类内容修复尚未完成：' . esc_html( implode( '；', $report['errors'] ) ) . '</p></div>';
    }
} );
