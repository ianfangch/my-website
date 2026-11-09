<?php
defined( 'ABSPATH' ) || exit;

/** One stored setting; original page blocks stay editable and unchanged. */
function iansf_contact_fields() {
    return array(
        'email' => array( 'label' => '通用邮箱', 'type' => 'email', 'default' => 'info@ianproject.com', 'required' => true, 'help' => '全站邮箱展示、邮件按钮、复制邮箱与询价表单统一使用此邮箱。' ),
        'phone' => array( 'label' => '联系电话', 'type' => 'tel', 'default' => '+86 199 5622 9033', 'required' => true, 'help' => '包含国家码；显示格式可保留空格。' ),
        'whatsapp' => array( 'label' => 'WhatsApp 号码', 'type' => 'tel', 'default' => '8619956229033', 'required' => true, 'help' => '包含国家码。页面按钮与询价表单统一使用此号码。' ),
        'whatsapp_url' => array( 'label' => 'WhatsApp 专用链接（可选）', 'type' => 'url', 'default' => '', 'help' => '通常留空，自动使用上面的号码；如填写 wa.me/message/…，全部 WhatsApp 按钮将使用此专用链接。' ),
        'address' => array( 'label' => '地址 / 服务区域', 'type' => 'text', 'default' => 'China · Serving global projects', 'help' => '显示在页脚；这里填写自己的办公地址，不影响案例与行业文章中的其他地址。' ),
        'map_url' => array( 'label' => '地图链接（可选）', 'type' => 'url', 'default' => '', 'help' => '填写后，页脚地址可点击打开地图。' ),
        'linkedin' => array( 'label' => 'LinkedIn', 'type' => 'url', 'default' => 'https://www.linkedin.com/company/ianproject/' ),
        'instagram' => array( 'label' => 'Instagram', 'type' => 'url', 'default' => 'https://www.instagram.com/ian.fang.ch/' ),
        'facebook' => array( 'label' => 'Facebook', 'type' => 'url', 'default' => 'https://www.facebook.com/profile.php?id=61585436573106' ),
        'youtube' => array( 'label' => 'YouTube 频道', 'type' => 'url', 'default' => 'https://www.youtube.com/@ian_fang_ch', 'help' => '修改频道按钮，不替换页面中已经嵌入的具体视频。' ),
        'pinterest' => array( 'label' => 'Pinterest', 'type' => 'url', 'default' => 'https://au.pinterest.com/ianfangch/' ),
        'tiktok' => array( 'label' => 'TikTok', 'type' => 'url', 'default' => 'https://www.tiktok.com/@ian.fang.ch' ),
        'x' => array( 'label' => 'X / Twitter', 'type' => 'url', 'default' => 'https://x.com/ian_fang_ch' ),
    );
}

function iansf_seed_contacts() {
    if ( false !== get_option( 'iansf_contacts', false ) ) { return; }
    $values = array_column( iansf_contact_fields(), 'default' );
    $values = array_combine( array_keys( iansf_contact_fields() ), $values );
    $legacy_email = sanitize_email( get_option( 'ian_sales_email', 'sales@ianproject.com' ) );
    if ( $legacy_email && 'sales@ianproject.com' !== $legacy_email ) { $values['email'] = $legacy_email; }
    $values['whatsapp'] = preg_replace( '/\D/', '', (string) get_option( 'ian_whatsapp', $values['whatsapp'] ) ) ?: $values['whatsapp'];
    add_option( 'iansf_contacts', $values, '', false );
    add_option( 'iansf_contact_aliases', array( 'sales_email' => $legacy_email, 'whatsapp' => $values['whatsapp'] ), '', false );
}

function iansf_contacts() {
    $defaults = array();
    foreach ( iansf_contact_fields() as $key => $field ) { $defaults[ $key ] = $field['default']; }
    return array_merge( $defaults, (array) get_option( 'iansf_contacts', array() ) );
}

function iansf_whatsapp_url( $values = null ) {
    $values = $values ?? iansf_contacts();
    return $values['whatsapp_url'] ?: 'https://wa.me/' . $values['whatsapp'];
}

function iansf_contacts_boot() {
    iansf_seed_contacts();
    // Keep the theme's existing form configuration and any third-party readers in sync.
    add_filter( 'pre_option_ian_sales_email', function () { return iansf_contacts()['email']; } );
    add_filter( 'pre_option_ian_whatsapp', function () { return iansf_contacts()['whatsapp']; } );
    add_action( 'template_redirect', 'iansf_start_contacts_output', 0 );
    add_action( 'wp_enqueue_scripts', function () {
        if ( ! wp_script_is( 'ian-contact', 'enqueued' ) ) { return; }
        $values = iansf_contacts();
        wp_dequeue_script( 'ian-contact' );
        wp_deregister_script( 'ian-contact' );
        wp_enqueue_script( 'ian-contact', plugins_url( 'assets/contact.js', dirname( __DIR__ ) . '/ianproject-site-fixes.php' ), array(), '1.0.3', true );
        wp_localize_script( 'ian-contact', 'IAN_CONTACT', array( 'email' => $values['email'], 'whatsapp' => $values['whatsapp'], 'whatsappUrl' => iansf_whatsapp_url( $values ) ) );
    }, 100 );
}

function iansf_sanitize_contacts( $input ) {
    $old = iansf_contacts();
    if ( ! is_array( $input ) ) { return $old; }
    $result = $old;
    foreach ( iansf_contact_fields() as $key => $field ) {
        if ( ! array_key_exists( $key, $input ) || ! is_scalar( $input[ $key ] ) ) { continue; }
        $raw = trim( (string) $input[ $key ] );
        $valid = true;
        if ( 'email' === $field['type'] ) {
            $value = sanitize_email( $raw );
            $valid = (bool) is_email( $raw );
        } elseif ( 'tel' === $field['type'] ) {
            $digits = preg_replace( '/\D/', '', $raw );
            $valid = (bool) preg_match( '/^[1-9][0-9]{6,14}$/', $digits ) && ! preg_match( '/[^0-9+() .-]/', $raw );
            $value = 'whatsapp' === $key ? $digits : sanitize_text_field( $raw );
        } elseif ( 'url' === $field['type'] ) {
            $parts = wp_parse_url( $raw );
            $valid = '' === $raw || ( is_array( $parts ) && in_array( strtolower( $parts['scheme'] ?? '' ), array( 'https', 'http' ), true ) && ! empty( $parts['host'] ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) );
            if ( $valid && 'whatsapp_url' === $key && '' !== $raw ) {
                $valid = in_array( strtolower( $parts['host'] ), array( 'wa.me', 'api.whatsapp.com', 'www.whatsapp.com', 'whatsapp.com' ), true );
            }
            $value = esc_url_raw( $raw, array( 'https', 'http' ) );
            if ( '' !== $raw && '' === $value ) { $valid = false; }
        } else {
            $value = sanitize_text_field( $raw );
        }
        if ( ! $valid || ( ! empty( $field['required'] ) && '' === $value ) ) {
            add_settings_error( 'iansf_contacts', $key, $field['label'] . '格式不正确，已保留原值。', 'error' );
            continue;
        }
        $result[ $key ] = $value;
    }
    return $result;
}

add_action( 'admin_init', function () {
    register_setting( 'iansf_contacts_group', 'iansf_contacts', array( 'type' => 'array', 'sanitize_callback' => 'iansf_sanitize_contacts', 'show_in_rest' => false ) );
    // Old quick-setup bookmarks now lead to the same authoritative contact settings.
    if ( iansf_compatible() && current_user_can( 'manage_options' ) && isset( $_POST['ian_settings_save'] ) && isset( $_GET['page'] ) && 'ianproject-setup' === $_GET['page'] ) {
        check_admin_referer( 'ian_settings_save' );
        wp_safe_redirect( admin_url( 'options-general.php?page=ianproject-contacts' ) );
        exit;
    }
} );

add_action( 'update_option_iansf_contacts', function () { do_action( 'litespeed_purge_all' ); }, 10, 0 );
add_action( 'admin_menu', function () {
    add_options_page( 'IanProject 联系方式', 'IanProject 联系方式', 'manage_options', 'ianproject-contacts', 'iansf_contacts_page' );
} );

function iansf_contacts_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $values = iansf_contacts();
    echo '<div class="wrap" style="max-width:950px"><h1>IanProject 联系方式</h1><p>在这里修改，点击一次“保存并同步全站”。页头、页脚、联系页、产品询价、复制邮箱和社交按钮同步使用这些设置。</p><p>社交链接留空即可隐藏对应入口。请保持此插件启用。</p>';
    settings_errors( 'iansf_contacts' );
    echo '<form method="post" action="options.php">';
    settings_fields( 'iansf_contacts_group' );
    echo '<table class="form-table" role="presentation">';
    foreach ( iansf_contact_fields() as $key => $field ) {
        echo '<tr><th scope="row"><label for="iansf-' . esc_attr( $key ) . '">' . esc_html( $field['label'] ) . '</label></th><td><input class="regular-text" id="iansf-' . esc_attr( $key ) . '" type="' . esc_attr( $field['type'] ) . '" name="iansf_contacts[' . esc_attr( $key ) . ']" value="' . esc_attr( $values[ $key ] ) . '"' . ( ! empty( $field['required'] ) ? ' required' : '' ) . '>';
        if ( ! empty( $field['help'] ) ) { echo '<p class="description">' . esc_html( $field['help'] ) . '</p>'; }
        echo '</td></tr>';
    }
    echo '</table>';
    submit_button( '保存并同步全站' );
    echo '</form><p>新增页面也可使用简码，例如 <code>[ian_contact field="email"]</code> 或 <code>[ian_contact field="whatsapp" label="WhatsApp"]</code>。</p></div>';
}

/** Update only the site's known contact destinations, leaving other companies intact. */
function iansf_contact_destination( $url, $values ) {
    $aliases = (array) get_option( 'iansf_contact_aliases', array() );
    $parts = wp_parse_url( $url );
    if ( ! is_array( $parts ) ) { return null; }
    $scheme = strtolower( $parts['scheme'] ?? '' );
    if ( 'mailto' === $scheme ) {
        $email = rawurldecode( $parts['path'] ?? '' );
        $key = in_array( strtolower( $email ), array_map( 'strtolower', array( 'sales@ianproject.com', 'info@ianproject.com', $aliases['sales_email'] ?? '' ) ), true ) ? 'email' : null;
        if ( $key ) { return array( $key, 'mailto:' . $values[ $key ] . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ) . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' ) ); }
    }
    if ( 'tel' === $scheme && '8619956229033' === preg_replace( '/\D/', '', $parts['path'] ?? '' ) ) { return array( 'phone', 'tel:+' . preg_replace( '/\D/', '', $values['phone'] ) ); }
    if ( ! in_array( $scheme, array( 'https', 'http', '' ), true ) || empty( $parts['host'] ) ) { return null; }
    $host = strtolower( $parts['host'] );
    $path = rtrim( $parts['path'] ?? '', '/' );
    if ( 'wa.me' === $host && in_array( $path, array( '/message/A4AOHGMZ6DB6A1', '/8619956229033', '/' . ( $aliases['whatsapp'] ?? '8619956229033' ) ), true ) ) {
        $target = iansf_whatsapp_url( $values );
        if ( isset( $parts['query'] ) ) {
            wp_parse_str( $parts['query'], $query );
            if ( isset( $query['text'] ) && is_scalar( $query['text'] ) ) { $target = add_query_arg( 'text', $query['text'], $target ); }
        }
        return array( 'whatsapp', $target );
    }
    $key = null;
    if ( in_array( $host, array( 'www.linkedin.com', 'linkedin.com' ), true ) && '/company/ianproject' === $path ) { $key = 'linkedin'; }
    if ( in_array( $host, array( 'www.instagram.com', 'instagram.com' ), true ) && '/ian.fang.ch' === $path ) { $key = 'instagram'; }
    if ( in_array( $host, array( 'www.facebook.com', 'facebook.com' ), true ) && '/profile.php' === $path ) {
        wp_parse_str( $parts['query'] ?? '', $query );
        if ( '61585436573106' === ( $query['id'] ?? '' ) ) { $key = 'facebook'; }
    }
    if ( in_array( $host, array( 'www.youtube.com', 'youtube.com' ), true ) && '/@ian_fang_ch' === $path ) { $key = 'youtube'; }
    if ( in_array( $host, array( 'pinterest.com', 'www.pinterest.com', 'au.pinterest.com' ), true ) && '/ianfangch' === $path ) { $key = 'pinterest'; }
    if ( in_array( $host, array( 'www.tiktok.com', 'tiktok.com' ), true ) && '/@ian.fang.ch' === $path ) { $key = 'tiktok'; }
    if ( in_array( $host, array( 'x.com', 'www.x.com', 'twitter.com', 'www.twitter.com' ), true ) && '/ian_fang_ch' === $path ) { $key = 'x'; }
    return $key ? array( $key, $values[ $key ] ) : null;
}

function iansf_start_contacts_output() {
    if ( is_admin() || is_feed() || is_trackback() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) { return; }
    // One pass over the finished HTML also covers shortcode and Elementor output.
    ob_start( 'iansf_render_contacts' );
}

function iansf_render_contacts( $html ) {
    if ( ! is_string( $html ) || ! preg_match( '~<(?:!doctype\s+html|html|body)\b~i', $html ) ) { return $html; }
    $values = iansf_contacts();
    $aliases = (array) get_option( 'iansf_contact_aliases', array() );
    $text_map = array(
        'sales@ianproject.com' => $values['email'],
        'info@ianproject.com' => $values['email'],
        'Message +86 199 5622 9033' => 'Message +' . $values['whatsapp'],
        '+86 199 5622 9033' => $values['phone'],
        '+8619956229033' => $values['phone'],
    );
    if ( ! empty( $aliases['sales_email'] ) && ! isset( $text_map[ $aliases['sales_email'] ] ) ) { $text_map[ $aliases['sales_email'] ] = $values['email']; }
    // Only the exact original address line becomes the site's address/map slot.
    $address = esc_html( $values['address'] );
    if ( $address && $values['map_url'] ) { $address = '<a href="' . esc_url( $values['map_url'] ) . '" target="_blank" rel="noopener noreferrer">' . $address . '</a>'; }
    $html = preg_replace_callback( '~(<p\b[^>]*>)China · Serving global projects</p>~', function ( $match ) use ( $address ) {
        $opening = new WP_HTML_Tag_Processor( $match[1] );
        $opening->next_tag( 'P' );
        $opening->set_attribute( 'data-ian-contact', 'address' );
        return $opening->get_updated_html() . $address . '</p>';
    }, $html );
    // Rebuild the original footer social row so cleared fields leave no stray separators.
    $html = preg_replace_callback( '~<p\b[^>]*class="[^"<>]*\bian-footer-social\b[^"<>]*"[^>]*>(.*?)</p>~s', function ( $match ) use ( $values ) {
        $scan = new WP_HTML_Tag_Processor( $match[1] );
        $keys = array();
        while ( $scan->next_tag( 'A' ) ) {
            $url = $scan->get_attribute( 'href' );
            $contact = is_string( $url ) ? iansf_contact_destination( $url, $values ) : null;
            if ( ! $contact ) { return $match[0]; }
            $keys[] = $contact[0];
        }
        if ( $keys !== array( 'whatsapp', 'linkedin', 'instagram', 'facebook' ) ) { return $match[0]; }
        $links = array();
        foreach ( array( 'whatsapp' => 'WhatsApp', 'linkedin' => 'LinkedIn', 'instagram' => 'Instagram', 'facebook' => 'Facebook' ) as $key => $label ) {
            $url = 'whatsapp' === $key ? iansf_whatsapp_url( $values ) : $values[ $key ];
            if ( $url ) { $links[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>'; }
        }
        return '<p class="ian-footer-social" data-ian-contact="social">' . implode( ' · ', $links ) . '</p>';
    }, $html );
    $processor = new WP_HTML_Tag_Processor( $html );
    $skip_tag = null;
    $skip_depth = 0;
    while ( $processor->next_token() ) {
        if ( '#text' === $processor->get_token_type() ) {
            if ( ! $skip_tag ) {
                $text = $processor->get_modifiable_text();
                $new = strtr( $text, $text_map );
                if ( $new !== $text ) { $processor->set_modifiable_text( $new ); }
            }
            continue;
        }
        if ( '#tag' !== $processor->get_token_type() ) { continue; }
        $tag = $processor->get_tag();
        if ( $skip_tag ) {
            if ( $tag === $skip_tag ) { $skip_depth += $processor->is_tag_closer() ? -1 : 1; if ( 0 === $skip_depth ) { $skip_tag = null; } }
            continue;
        }
        if ( $processor->is_tag_closer() ) { continue; }
        // Raw-text elements are atomic tokens in the WordPress HTML API.
        if ( in_array( $tag, array( 'SCRIPT', 'STYLE', 'TEXTAREA', 'TITLE' ), true ) ) { continue; }
        if ( in_array( $tag, array( 'PRE', 'CODE' ), true ) || is_string( $processor->get_attribute( 'data-ian-contact' ) ) ) {
            $skip_tag = $tag;
            $skip_depth = 1;
            continue;
        }
        if ( 'A' === $tag ) {
            $url = $processor->get_attribute( 'href' );
            $updated = is_string( $url ) ? iansf_contact_destination( $url, $values ) : null;
            if ( $updated ) {
                if ( '' === $updated[1] ) { $processor->remove_attribute( 'href' ); $processor->set_attribute( 'hidden', true ); $processor->set_attribute( 'aria-hidden', 'true' ); $processor->set_attribute( 'style', 'display:none!important' ); }
                else { $processor->set_attribute( 'href', $updated[1] ); }
            }
        }
        foreach ( array( 'data-email', 'data-whatsapp' ) as $attribute ) {
            if ( null !== $processor->get_attribute( $attribute ) ) { $processor->set_attribute( $attribute, 'data-email' === $attribute ? $values['email'] : $values['whatsapp'] ); }
        }
    }
    return $processor->get_updated_html();
}

add_shortcode( 'ian_contact', function ( $atts ) {
    $atts = shortcode_atts( array( 'field' => 'email', 'label' => '', 'link' => 'yes' ), $atts, 'ian_contact' );
    $values = iansf_contacts();
    $key = in_array( $atts['field'], array( 'sales_email', 'info_email' ), true ) ? 'email' : $atts['field'];
    if ( ! isset( $values[ $key ] ) || '' === $values[ $key ] ) { return ''; }
    $label = $atts['label'] ?: $values[ $key ];
    $url = '';
    if ( 'email' === $key ) { $url = 'mailto:' . $values[ $key ]; }
    elseif ( 'phone' === $key ) { $url = 'tel:+' . preg_replace( '/\D/', '', $values['phone'] ); }
    elseif ( 'whatsapp' === $key ) { $url = iansf_whatsapp_url( $values ); }
    elseif ( 'address' === $key ) { $url = $values['map_url']; }
    elseif ( 'url' === ( iansf_contact_fields()[ $key ]['type'] ?? '' ) ) { $url = $values[ $key ]; }
    $out = esc_html( $label );
    if ( $url && 'no' !== $atts['link'] ) { $out = '<a href="' . esc_url( $url ) . '">' . $out . '</a>'; }
    return '<span data-ian-contact="' . esc_attr( $key ) . '">' . $out . '</span>';
} );
