<?php
defined( 'ABSPATH' ) || exit;
add_action( 'init', function () {
    register_post_type( 'ian_insight', array(
        'labels' => array( 'name' => '行业文章', 'singular_name' => '行业文章', 'add_new_item' => '添加文章', 'edit_item' => '编辑文章' ),
        'public' => true, 'show_in_rest' => true, 'has_archive' => false, 'menu_icon' => 'dashicons-welcome-write-blog',
        'rewrite' => array( 'slug' => 'insights', 'with_front' => false ),
        'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
        'template' => array( array( 'core/group', array( 'className' => 'container py-5', 'layout' => array( 'type' => 'default' ) ), array( array( 'core/post-title', array( 'level' => 1 ) ), array( 'core/paragraph' ) ) ) ),
    ) );
} );
