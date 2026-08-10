(function () {
    "use strict";

    var catalog = window.IAN_CATALOG;
    if (!catalog) return;

    var storageKey = "ianproject-enquiry";

    function escapeHtml(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function getCategory(id) {
        return catalog.categories.find(function (category) { return category.id === id; });
    }

    function getProduct(id) {
        return catalog.products.find(function (product) { return product.id === id; });
    }

    function productUrl(product) {
        return product.url || ("/product?id=" + encodeURIComponent(product.id));
    }

    function bindProductBackLink() {
        document.querySelectorAll("[data-product-back]").forEach(function (link) {
            if (link.getAttribute("data-product-back-bound") === "true") return;
            link.setAttribute("data-product-back-bound", "true");
            link.addEventListener("click", function (event) {
                if (!document.referrer || window.history.length < 2) return;
                try {
                    var referrer = new URL(document.referrer);
                    var referrerPath = referrer.pathname.replace(/\.html$/, "").replace(/\/$/, "") || "/";
                    if (referrer.origin === window.location.origin && referrerPath === "/products") {
                        event.preventDefault();
                        window.history.back();
                    }
                } catch (error) {
                    // The normal link remains available if the referrer cannot be parsed.
                }
            });
        });
    }

    function getEnquiry() {
        try {
            var parsed = JSON.parse(localStorage.getItem(storageKey) || "[]");
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function saveEnquiry(items) {
        localStorage.setItem(storageKey, JSON.stringify(items));
        updateEnquiryCount();
    }

    function priceMarkup(product, compact) {
        if (typeof product.price !== "number") {
            return '<div class="catalog-price catalog-price-pending"><strong>Price to be added</strong>' +
                (compact ? "" : "<small>Real USD price required before publishing</small>") + "</div>";
        }

        return '<div class="catalog-price"><span>' + escapeHtml(product.pricePrefix || "") + '</span>' +
            '<strong>' + escapeHtml(catalog.currency) + " " +
            product.price.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) +
            '</strong><small>' + escapeHtml(product.priceUnit || "") + "</small></div>";
    }

    function addToEnquiry(productId) {
        var items = getEnquiry();
        var existing = items.find(function (item) { return item.id === productId; });
        if (existing) {
            existing.quantity += 1;
        } else {
            items.push({ id: productId, quantity: 1 });
        }
        saveEnquiry(items);
        showNotice("Added to enquiry list");
    }

    function showNotice(message) {
        var notice = document.getElementById("catalog-notice");
        if (!notice) {
            notice = document.createElement("div");
            notice.id = "catalog-notice";
            notice.className = "catalog-notice";
            notice.setAttribute("role", "status");
            document.body.appendChild(notice);
        }
        notice.textContent = message;
        notice.classList.add("show");
        window.setTimeout(function () { notice.classList.remove("show"); }, 1800);
    }

    function updateEnquiryCount() {
        var count = getEnquiry().reduce(function (total, item) { return total + item.quantity; }, 0);
        document.querySelectorAll("[data-enquiry-count]").forEach(function (element) {
            element.textContent = count;
            element.hidden = count === 0;
        });
    }

    function productCard(product) {
        var category = getCategory(product.category);
        return '<article class="catalog-card">' +
            '<a class="catalog-card-image" href="' + escapeHtml(productUrl(product)) + '">' +
            '<img src="' + escapeHtml(product.image) + '" alt="' + escapeHtml(product.imageAlt) + '" loading="lazy" width="1254" height="1254">' +
            '<span class="badge bg-primary">' + escapeHtml(product.badge) + "</span></a>" +
            '<div class="catalog-card-body"><p class="catalog-card-meta">' +
            escapeHtml(category ? category.name : product.category) + " / " + escapeHtml(product.code) + "</p>" +
            '<h3><a href="' + escapeHtml(productUrl(product)) + '">' + escapeHtml(product.name) + "</a></h3>" +
            "<p>" + escapeHtml(product.summary) + "</p>" +
            priceMarkup(product, true) +
            '<div class="d-flex flex-wrap gap-2 mt-4">' +
            '<a class="btn btn-outline-primary" href="' + escapeHtml(productUrl(product)) + '">View Details</a>' +
            '<button class="btn btn-primary" type="button" data-add-product="' + escapeHtml(product.id) + '">Add to Enquiry</button>' +
            "</div></div></article>";
    }

    function renderCatalog() {
        var grid = document.getElementById("catalog-grid");
        if (!grid) return;

        var params = new URLSearchParams(window.location.search);
        var activeCategory = params.get("category") || "all";
        var search = "";
        var sort = "featured";
        var searchInput = document.getElementById("catalog-search");
        var sortInput = document.getElementById("catalog-sort");
        var filterContainer = document.getElementById("catalog-filters");

        filterContainer.innerHTML = [{ id: "all", name: "All Products" }].concat(catalog.categories)
            .map(function (category) {
                return '<button class="catalog-filter' + (category.id === activeCategory ? " active" : "") +
                    '" type="button" data-category="' + escapeHtml(category.id) + '">' +
                    escapeHtml(category.name) + "</button>";
            }).join("");

        function draw() {
            var products = catalog.products.filter(function (product) {
                var categoryMatch = activeCategory === "all" || product.category === activeCategory;
                var haystack = (product.name + " " + product.code + " " + product.summary).toLowerCase();
                return categoryMatch && haystack.indexOf(search) !== -1;
            });

            if (sort === "price-low") {
                products.sort(function (a, b) {
                    return (typeof a.price === "number" ? a.price : Number.MAX_VALUE) -
                        (typeof b.price === "number" ? b.price : Number.MAX_VALUE);
                });
            } else if (sort === "price-high") {
                products.sort(function (a, b) {
                    return (typeof b.price === "number" ? b.price : -1) -
                        (typeof a.price === "number" ? a.price : -1);
                });
            } else if (sort === "name") {
                products.sort(function (a, b) { return a.name.localeCompare(b.name); });
            }

            document.getElementById("catalog-result-count").textContent =
                products.length + (products.length === 1 ? " product" : " products");
            grid.innerHTML = products.length ? products.map(productCard).join("") :
                '<div class="catalog-empty"><h3>No products in this category yet.</h3>' +
                '<p>The category is ready for future products. Send us the first product images, codes and prices to publish them.</p></div>';
            bindAddButtons();
        }

        filterContainer.addEventListener("click", function (event) {
            var button = event.target.closest("[data-category]");
            if (!button) return;
            activeCategory = button.getAttribute("data-category");
            filterContainer.querySelectorAll(".catalog-filter").forEach(function (item) {
                item.classList.toggle("active", item === button);
            });
            var url = new URL(window.location.href);
            if (activeCategory === "all") url.searchParams.delete("category");
            else url.searchParams.set("category", activeCategory);
            window.history.replaceState({}, "", url.pathname + url.search + "#catalog");
            draw();
        });

        searchInput.addEventListener("input", function () {
            search = searchInput.value.trim().toLowerCase();
            draw();
        });
        sortInput.addEventListener("change", function () {
            sort = sortInput.value;
            draw();
        });
        draw();
    }

    function renderProductDetail() {
        var target = document.getElementById("product-detail");
        if (!target) return;
        var id = new URLSearchParams(window.location.search).get("id") || target.getAttribute("data-product-id");
        var product = getProduct(id);
        if (!product) {
            target.innerHTML = '<div class="catalog-empty"><h1>Product not found</h1>' +
                '<p>This product may have been moved or is not yet published.</p>' +
                '<a class="btn btn-primary" href="/products#catalog">Browse Products</a></div>';
            return;
        }

        var category = getCategory(product.category);
        var isSink = product.category === "sinks";
        var isCabinetry = product.category === "cabinetry";
        var isCountertop = product.category === "countertops";
        target.classList.toggle("countertop-detail", isCountertop);
        var displaySpecifications = {};
        Object.keys(product.specifications).forEach(function (key) {
            if (key === "Minimum order" || key === "Production lead time") return;
            displaySpecifications[key] = product.specifications[key];
            if (isCabinetry && key === "Door finish" && !product.specifications["Standard hinges"]) {
                displaySpecifications["Standard hinges"] = "Blum";
            }
        });
        if (isCabinetry && !displaySpecifications["Standard hinges"]) displaySpecifications["Standard hinges"] = "Blum";
        if (!displaySpecifications["Country of origin"]) displaySpecifications["Country of origin"] = "China";
        var specs = Object.keys(displaySpecifications).map(function (key) {
            return "<tr><th>" + escapeHtml(key) + "</th><td>" +
                escapeHtml(displaySpecifications[key]) + "</td></tr>";
        }).join("");
        var highlights = product.highlights.map(function (item) {
            return '<li><i class="fa fa-check text-primary me-2"></i>' + escapeHtml(item) + "</li>";
        }).join("");
        var gallery = Array.isArray(product.images) && product.images.length ? product.images : [product.image];
        var galleryMarkup = '<div class="product-gallery"><div class="product-detail-image">' +
            '<img id="product-main-image" src="' + escapeHtml(gallery[0]) + '" alt="' +
            escapeHtml(product.imageAlt) + '" width="1254" height="1254" fetchpriority="high"></div></div>';
        var detailGallery = isCountertop ? gallery : gallery.slice(1);
        var directGalleryMarkup = detailGallery.length ?
            '<section class="col-12 mt-5 product-image-section" aria-labelledby="product-gallery-title">' +
            '<h2 id="product-gallery-title" class="mb-4">' + (isCountertop ? "Surface and Application" : "Product Gallery") + '</h2>' +
            '<div class="product-direct-gallery">' + detailGallery.map(function (image, index) {
                var altIndex = isCountertop ? index : index + 1;
                var imageAlt = product.imageAlts && product.imageAlts[altIndex] ? product.imageAlts[altIndex] : product.imageAlt;
                return '<div class="product-direct-image"><img src="' + escapeHtml(image) + '" alt="' + escapeHtml(imageAlt) +
                    '" loading="lazy" width="1254" height="1254"></div>';
            }).join("") + "</div></section>" : "";
        var descriptionMarkup = Array.isArray(product.descriptionParagraphs) && product.descriptionParagraphs.length ?
            '<section class="col-12 mt-5 product-info-section"><h2 class="mb-4">Product Description</h2>' +
            product.descriptionParagraphs.map(function (paragraph) {
                return '<p class="product-description-copy">' + escapeHtml(paragraph) + '</p>';
            }).join("") + '</section>' : '';
        var faqMarkup = Array.isArray(product.faq) && product.faq.length ?
            '<section class="col-12 mt-5 product-info-section"><h2 class="mb-4">Frequently Asked Questions</h2>' +
            '<div class="product-faq">' + product.faq.map(function (item) {
                return '<div class="product-faq-item"><h3>' + escapeHtml(item.question) + '</h3><p>' + escapeHtml(item.answer) + '</p></div>';
            }).join("") + '</div></section>' : '';
        var contactMarkup = isCountertop ?
            '<section class="col-12 mt-5 product-contact-panel"><h2>Contact Us for Detailed Information</h2>' +
            '<p>Contact us for pricing, samples, current slab availability, fabrication options, drawings and delivery arrangements.</p>' +
            '<div class="d-flex flex-wrap gap-3"><a class="btn btn-light px-4 py-3" href="mailto:sales@ianproject.com?subject=Quartz%20Countertop%20Enquiry">Email Us</a>' +
            '<a class="btn btn-outline-light px-4 py-3" href="https://wa.me/message/A4AOHGMZ6DB6A1" target="_blank" rel="noopener">WhatsApp</a></div></section>' : '';
        var standardCustomisationOptions = [
            "Cabinet dimensions and layout",
            "Door colours and finishes",
            "Countertop colours and thicknesses",
            "Handles and hardware",
            "Internal storage accessories",
            "Sink and appliance integration",
            "Alternative materials according to model and project requirements",
            "Other requirements — contact us"
        ];
        var sinkCustomisationOptions = [
            "Sink dimensions and bowl depth",
            "Stainless steel grade and material thickness",
            "Surface finish and colour",
            "Top-mount, undermount or flush-mount configuration",
            "Faucet, rinser and accessory-hole layout",
            "Drain and overflow configuration",
            "Workstation boards, racks, colanders and baskets",
            "Other requirements — contact us"
        ];
        var customisationOptions = Array.isArray(product.customisationOptions) && product.customisationOptions.length ?
            product.customisationOptions : (isSink ? sinkCustomisationOptions : standardCustomisationOptions);
        var customisationNote = product.customisationNote ||
            (isSink ?
                "Final sink dimensions, cut-out details, finish and included accessories are confirmed in the approved quotation before production." :
                "Final specifications are confirmed through drawings, material samples and the approved quotation before production.");
        var indicativePrice = (product.pricePrefix ? product.pricePrefix + " " : "") + catalog.currency + " " +
            product.price.toLocaleString("en-US", { maximumFractionDigits: 2 }) + " " + (product.priceUnit || "");
        var standardCommercialInformation = {
            "Indicative price": indicativePrice.trim(),
            "Minimum order": "1 metre",
            "Currency": "USD",
            "Payment method": "T/T only",
            "Payment terms": "30% deposit upon order confirmation and 70% balance before shipment",
            "Production lead time": "Approximately 30–40 days after confirmation of drawings, materials and deposit",
            "Packaging": "Flat-pack or assembled export packaging according to project requirements",
            "Trade terms": "EXW, FOB and CIF",
            "DDP delivery": "Available for selected destinations, subject to the final delivery address and local import requirements",
            "Port of loading": "Ningbo, China",
            "Warranty": "One year",
            "Factory inspection": "Buyers or their appointed representatives may inspect the goods at the factory by appointment",
            "Video inspection": "Remote video inspection is available before shipment"
        };
        var sinkCommercialInformation = {
            "Indicative price": indicativePrice.trim(),
            "Minimum order": product.specifications["Minimum order"] || "Confirm with enquiry",
            "Currency": "USD",
            "Payment method": "T/T only",
            "Payment terms": "30% deposit upon order confirmation and 70% balance before shipment",
            "Production lead time": product.specifications["Production lead time"] || "Confirm with enquiry",
            "Packaging": "Protective export packaging according to sink configuration and order quantity",
            "Trade terms": "EXW, FOB and CIF",
            "Port of loading": "Confirmed with the final quotation",
            "Factory inspection": "Buyers or their appointed representatives may inspect the goods by appointment",
            "Video inspection": "Remote video inspection is available before shipment"
        };
        var commercialInformation = product.commercialInformation || (isSink ? sinkCommercialInformation : standardCommercialInformation);
        var commercialNote = product.commercialNote ||
            (isSink ?
                "Prices shown are indicative starting prices. Final sink pricing depends on size, material thickness, finish, accessory set, order quantity and delivery destination." :
                "Prices shown on the website are indicative starting prices. Final pricing depends on dimensions, materials, hardware, accessories, order quantity and delivery destination.");
        var customisationMarkup = !isCountertop && customisationOptions.length ?
            '<section class="col-12 mt-5 product-info-section"><h2 class="mb-4">Customisation Options</h2>' +
            '<ul class="product-option-list list-unstyled">' + customisationOptions.map(function (option) {
                return '<li><i class="fa fa-check text-primary" aria-hidden="true"></i><span>' +
                    escapeHtml(option) + "</span></li>";
            }).join("") + "</ul>" + '<p class="product-info-note">' +
                escapeHtml(customisationNote) + "</p></section>" : "";
        var commercialMarkup = !isCountertop && commercialInformation ?
            '<section class="col-12 mt-5 product-info-section"><h2 class="mb-4">Commercial &amp; Delivery Information</h2>' +
            '<div class="table-responsive"><table class="table product-commercial-table"><tbody>' +
            Object.keys(commercialInformation).map(function (key) {
                return "<tr><th>" + escapeHtml(key) + "</th><td>" +
                    escapeHtml(commercialInformation[key]) + "</td></tr>";
            }).join("") + "</tbody></table></div>" +
                '<p class="product-info-note">' + escapeHtml(commercialNote) + "</p></section>" : "";

        document.title = product.name + " | IanProject";
        var descriptionMeta = document.querySelector('meta[name="description"]');
        if (descriptionMeta) descriptionMeta.setAttribute("content", product.seoDescription || product.summary);
        target.innerHTML = '<div class="product-detail-actions mb-4">' +
            '<a class="btn btn-outline-primary px-3 py-2" href="/products#catalog" data-product-back>' +
            '<i class="fa fa-arrow-left me-2" aria-hidden="true"></i>Back to Products</a></div>' +
            '<div class="row g-5 align-items-start">' +
            '<div class="col-lg-6">' + galleryMarkup + '</div>' +
            '<div class="col-lg-6"><p class="text-uppercase text-primary mb-2">' +
            escapeHtml(category ? category.name : product.category) + " / " + escapeHtml(product.code) + "</p>" +
            "<h1>" + escapeHtml(product.name) + "</h1><p class=\"mb-4\">" + escapeHtml(product.summary) + "</p>" +
            priceMarkup(product, false) +
            '<p class="catalog-price-note">Displayed prices exclude freight, duties, installation and project-specific changes unless stated otherwise.</p>' +
            '<ul class="product-highlights list-unstyled my-4">' + highlights + "</ul>" +
            '<div class="d-flex flex-wrap gap-3"><button class="btn btn-primary px-4 py-3" type="button" data-add-product="' +
            escapeHtml(product.id) + '">Add to Enquiry</button><a class="btn btn-outline-dark px-4 py-3" href="/enquiry">View Enquiry List</a></div></div>' +
            directGalleryMarkup +
            descriptionMarkup +
            '<div class="col-12 mt-5"><h2 class="mb-4">Key Specifications</h2>' +
            '<div class="table-responsive"><table class="table product-spec-table"><tbody>' + specs + "</tbody></table></div></div>" +
            faqMarkup + contactMarkup + customisationMarkup + commercialMarkup + "</div>";
        bindProductBackLink();
        bindAddButtons();
    }

    function renderEnquiry() {
        var target = document.getElementById("enquiry-list");
        if (!target) return;

        function draw() {
            var items = getEnquiry();
            if (!items.length) {
                target.innerHTML = '<div class="catalog-empty"><h2>Your enquiry list is empty.</h2>' +
                    '<p>Add products from the catalogue, then return here to send one combined request.</p>' +
                    '<a class="btn btn-primary" href="/products#catalog">Browse Products</a></div>';
                document.getElementById("send-enquiry-email").classList.add("disabled");
                return;
            }

            target.innerHTML = items.map(function (item) {
                var product = getProduct(item.id);
                if (!product) return "";
                return '<div class="enquiry-item"><img src="' + escapeHtml(product.image) + '" alt="">' +
                    '<div class="enquiry-item-main"><p>' + escapeHtml(product.code) + '</p><h3>' +
                    escapeHtml(product.name) + "</h3>" + priceMarkup(product, true) + "</div>" +
                    '<div class="enquiry-quantity"><label for="qty-' + escapeHtml(product.id) + '">Quantity</label>' +
                    '<input id="qty-' + escapeHtml(product.id) + '" type="number" min="1" value="' + item.quantity +
                    '" data-quantity-product="' + escapeHtml(product.id) + '"></div>' +
                    '<button class="btn btn-link text-danger" type="button" data-remove-product="' +
                    escapeHtml(product.id) + '">Remove</button></div>';
            }).join("");

            var lines = items.map(function (item) {
                var product = getProduct(item.id);
                return product ? "- " + product.code + " | " + product.name + " | Quantity: " + item.quantity : "";
            }).filter(Boolean);
            var body = "Hello IanProject team,\n\nPlease quote the following products:\n\n" +
                lines.join("\n") + "\n\nCompany:\nCountry:\nDelivery destination:\nRequired date:\n\nThank you.";
            var email = document.getElementById("send-enquiry-email");
            email.href = "mailto:sales@ianproject.com?subject=" +
                encodeURIComponent("Product Enquiry - IanProject") + "&body=" + encodeURIComponent(body);
            email.classList.remove("disabled");
        }

        target.addEventListener("change", function (event) {
            var input = event.target.closest("[data-quantity-product]");
            if (!input) return;
            var items = getEnquiry();
            var item = items.find(function (candidate) { return candidate.id === input.getAttribute("data-quantity-product"); });
            if (item) item.quantity = Math.max(1, parseInt(input.value, 10) || 1);
            saveEnquiry(items);
            draw();
        });
        target.addEventListener("click", function (event) {
            var button = event.target.closest("[data-remove-product]");
            if (!button) return;
            saveEnquiry(getEnquiry().filter(function (item) {
                return item.id !== button.getAttribute("data-remove-product");
            }));
            draw();
        });
        draw();
    }

    function bindAddButtons() {
        document.querySelectorAll("[data-add-product]").forEach(function (button) {
            if (button.dataset.bound) return;
            button.dataset.bound = "true";
            button.addEventListener("click", function () {
                addToEnquiry(button.getAttribute("data-add-product"));
            });
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        updateEnquiryCount();
        renderCatalog();
        renderProductDetail();
        renderEnquiry();
        bindAddButtons();
    });
}());
