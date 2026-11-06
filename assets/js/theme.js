(function () {
    'use strict';
    function ready() {
        if (document.body.classList.contains('wp-admin')) return;
        var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (window.jQuery && window.jQuery.fn.owlCarousel) {
            window.jQuery('.header-carousel').each(function () {
                if (!window.jQuery(this).hasClass('owl-loaded')) {
                    window.jQuery(this).owlCarousel({ autoplay: !reducedMotion, autoplayHoverPause: true, smartSpeed: reducedMotion ? 0 : 650, loop: true, dots: true, items: 1 });
                }
            });
        }
        // Images remain core Image blocks; this enhances the full gallery with a zoom dialog.
        var galleryImages = document.querySelectorAll('.product-direct-image img, .product-detail-image img');
        if (galleryImages.length && 'HTMLDialogElement' in window) {
            var dialog = document.createElement('dialog');
            dialog.className = 'ian-image-dialog';
            dialog.setAttribute('aria-label', 'Product image preview');
            var close = document.createElement('button');
            close.type = 'button';
            close.textContent = 'Close \u00d7';
            close.addEventListener('click', function () { dialog.close(); });
            var image = document.createElement('img');
            image.src = galleryImages[0].currentSrc || galleryImages[0].src;
            dialog.appendChild(close);
            dialog.appendChild(image);
            document.body.appendChild(dialog);
            dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
            galleryImages.forEach(function (item) {
                if (item.closest('a')) return;
                item.classList.add('ian-product-gallery-link');
                item.tabIndex = 0;
                item.setAttribute('role', 'button');
                item.setAttribute('aria-label', 'Enlarge: ' + (item.alt || 'product image'));
                function show() { image.src = item.currentSrc || item.src; image.alt = item.alt; dialog.showModal(); }
                item.addEventListener('click', show);
                item.addEventListener('keydown', function (event) { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); show(); } });
            });
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready); else ready();
})();
