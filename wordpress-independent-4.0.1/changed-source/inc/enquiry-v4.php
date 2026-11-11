<?php
defined( 'ABSPATH' ) || exit;
/** Prepare a message for the visitor's own email / WhatsApp. No server mail. */
function ian4_enquiry_form() {
    $requirements = '';
    if ( ! empty( $_GET['cart'] ) ) { $requirements = ian4_cart_summary_text(); }
    if ( ! $requirements && isset( $_GET['product'] ) && is_scalar( $_GET['product'] ) ) { $id = absint( $_GET['product'] ); if ( 'publish' === get_post_status( $id ) && 'product' === get_post_type( $id ) ) { $requirements = 'Product: ' . get_the_title( $id ) . "\nModel: " . get_post_meta( $id, '_sku', true ) . "\nQuantity:\nRequirements:"; } }
    $contacts = iansf_contacts();
    wp_enqueue_script( 'ian-enquiry', get_theme_file_uri( 'assets/js/enquiry-v4.js' ), array(), IANPROJECT_VERSION, true );
    wp_localize_script( 'ian-enquiry', 'IAN_ENQUIRY', array( 'email' => sanitize_email( $contacts['email'] ), 'whatsapp' => iansf_whatsapp_url( $contacts ) ) );
    ob_start(); ?>
    <form id="quote-builder-form" action="<?php echo esc_url( 'mailto:' . sanitize_email( $contacts['email'] ) ); ?>" method="post" enctype="text/plain">
    <div class="row g-4">
    <div class="col-md-6"><label class="form-label" for="request-name">Name *</label><input class="form-control" id="request-name" name="name" maxlength="120" autocomplete="name" required></div>
    <div class="col-md-6"><label class="form-label" for="request-website">Company website <span class="text-muted small">(optional)</span></label><input class="form-control" id="request-website" name="website" inputmode="url" maxlength="240" autocomplete="url" placeholder="yourcompany.com"></div>
    <div class="col-md-6"><label class="form-label" for="request-country">Country *</label><input class="form-control" id="request-country" name="country" autocomplete="country-name" maxlength="100" required></div>
    <div class="col-md-6"><label class="form-label" for="request-terms">Trade terms *</label><select class="form-select" id="request-terms" name="terms" required><option value="">Select trade terms</option><?php foreach ( array( 'EXW', 'FOB', 'CIF', 'DDP' ) as $term ) { echo '<option>' . esc_html( $term ) . '</option>'; } ?></select></div>
    <div class="col-12"><label class="form-label" for="request-requirements">Requirements *</label><textarea class="form-control" id="request-requirements" name="requirements" rows="9" maxlength="12000" required><?php echo esc_textarea( $requirements ); ?></textarea><p class="small text-muted mt-2">Include product names, models, quantities and custom requirements. Attach drawings and a bill of materials in your email or WhatsApp conversation.</p></div>
    </div>
    <div class="d-flex flex-wrap gap-3 mt-4"><button class="btn btn-primary px-4 py-3" id="request-email" type="button" disabled>Send by email</button><button class="btn btn-outline-primary px-4 py-3" id="request-whatsapp" type="button" disabled>Send via WhatsApp</button><button class="btn btn-outline-primary px-4 py-3" id="request-copy" type="button" disabled>Copy request</button></div>
    <p class="small text-muted mt-3">Your email app or WhatsApp will open with your request. Review it and press Send there. This form does not automatically send or store your contact details on the website.</p>
    <p id="request-feedback" role="status" aria-live="polite" class="small mt-2"></p>
    <div id="request-manual" hidden></div>
    <details id="request-preview-wrap" class="mt-3" hidden><summary>Review / copy your request</summary><label class="visually-hidden" for="request-preview">Prepared request</label><textarea class="form-control mt-2" id="request-preview" rows="12" readonly></textarea></details>
    <noscript><p>Please email <a href="<?php echo esc_url( 'mailto:' . sanitize_email( $contacts['email'] ) ); ?>"><?php echo esc_html( $contacts['email'] ); ?></a> or <a href="<?php echo esc_url( iansf_whatsapp_url( $contacts ) ); ?>" target="_blank" rel="noopener">open WhatsApp</a> to send your project information.</p></noscript>
    </form>
    <?php return ob_get_clean();
}
add_action( 'init', function () { add_shortcode( 'ian_quote_form', 'ian4_enquiry_form' ); }, 1001 );
// A stale cached 4.0.0 form must never silently send a server email.
foreach ( array( 'admin_post_ian4_enquiry', 'admin_post_nopriv_ian4_enquiry' ) as $hook ) {
    add_action( $hook, function () { wp_die( 'Please refresh the Contact page, then send your request using your email app or WhatsApp.', 'Contact page updated', array( 'response' => 400 ) ); } );
}
