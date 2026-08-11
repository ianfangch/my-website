(function () {
    "use strict";

    var catalog = window.IAN_CATALOG;
    if (!catalog) return;

    var commercial = {
        "Minimum order": "Confirm with enquiry",
        "Production lead time": "Confirm with quotation"
    };

    function product(config) {
        config.category = "sinks";
        config.badge = "Stainless Steel Sink Series";
        config.pricePrefix = "";
        config.priceUnit = "per unit";
        config.highlights = [];
        config.specifications = commercial;
        config.commercialNote = "Indicative online price range. Final pricing depends on the selected model, quantity and delivery destination.";
        return config;
    }

    catalog.products.unshift(
        product({
            id: "drawn-undermount-single-bowl-sink-series-21-22",
            url: "products/drawn-undermount-single-bowl-sink-series-21-22",
            name: "Drawn Undermount Single-Bowl Sink Series",
            code: "4035T / 4140T / 4540T / 5045T / 6643T / 3238 / 4545 / 5545 / 3634 / 4038 / 4643 / 3330 / 4236",
            image: "img/products/drawn-undermount-single-bowl-sinks/1.jpg",
            images: ["img/products/drawn-undermount-single-bowl-sinks/1.jpg", "img/products/drawn-undermount-single-bowl-sinks/2.jpg", "img/products/drawn-undermount-single-bowl-sinks/3.jpg", "img/products/drawn-undermount-single-bowl-sinks/4.jpg", "img/products/drawn-undermount-single-bowl-sinks/5.jpg", "img/products/drawn-undermount-single-bowl-sinks/6.jpg"],
            imageAlt: "304 stainless steel drawn undermount single-bowl sink model 4035T",
            price: 11,
            priceMax: 47,
            summary: "304 stainless steel one-piece drawn undermount single-bowl sinks in thirteen models, based on catalogue pages 21 and 22.",
            seoDescription: "Drawn 304 stainless steel undermount single-bowl sink series in thirteen models. USD 11-47 per unit.",
            catalogueRows: [
                { image: "img/products/drawn-undermount-single-bowl-sinks/row-1.jpg", imageAlt: "Models 4035T, 4140T, 4540T and 5045T drawn undermount sinks", models: [
                    { code: "4035T", size: "400 x 350 x 190 mm", cutout: "380 x 330 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "4140T", size: "410 x 400 x 210 mm", cutout: "390 x 380 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "4540T", size: "450 x 400 x 210 mm", cutout: "430 x 380 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "5045T", size: "500 x 450 x 210 mm", cutout: "480 x 430 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-undermount-single-bowl-sinks/row-2.jpg", imageAlt: "Model 6643T drawn undermount sink", models: [
                    { code: "6643T", size: "655 x 425 x 220 mm", cutout: "625 x 395 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-undermount-single-bowl-sinks/row-3.jpg", imageAlt: "Model 3238 drawn undermount sink", models: [
                    { code: "3238", size: "320 x 380 x 200 mm", cutout: "290 x 350 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-undermount-single-bowl-sinks/row-4.jpg", imageAlt: "Models 4545 and 5545 drawn undermount sinks", models: [
                    { code: "4545", size: "450 x 450 x 200 mm", cutout: "420 x 420 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "5545", size: "550 x 450 x 200 mm", cutout: "520 x 420 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-undermount-single-bowl-sinks/row-5.jpg", imageAlt: "Models 3634, 4038 and 4643 drawn undermount sinks", models: [
                    { code: "3634", size: "360 x 340 x 200 mm", cutout: "330 x 310 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "4038", size: "400 x 380 x 200 mm", cutout: "370 x 350 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "4643", size: "460 x 430 x 200 mm", cutout: "430 x 400 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-undermount-single-bowl-sinks/row-6.jpg", imageAlt: "Models 3330 and 4236 drawn undermount sinks", models: [
                    { code: "3330", size: "330 x 300 x 200 mm", cutout: "300 x 270 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "4236", size: "420 x 360 x 200 mm", cutout: "390 x 330 mm", material: "304 stainless steel, one-piece drawn" }
                ]}
            ]
        }),
        product({
            id: "drawn-single-bowl-sink-series-17-19",
            url: "products/drawn-single-bowl-sink-series-17-19",
            name: "Drawn Single-Bowl Sink Series",
            code: "7645 / 6845 / 7545 / 5843F / 6544F / 6845F / 5843 / 6245 / 6544 / 6844 / 6045 / 5040 / 5238 / 4835 / 4539 / 4238 / 3833",
            image: "img/products/drawn-single-bowl-sinks/1.jpg",
            images: ["img/products/drawn-single-bowl-sinks/1.jpg", "img/products/drawn-single-bowl-sinks/2.jpg", "img/products/drawn-single-bowl-sinks/3.jpg", "img/products/drawn-single-bowl-sinks/4.jpg", "img/products/drawn-single-bowl-sinks/5.jpg", "img/products/drawn-single-bowl-sinks/6.jpg", "img/products/drawn-single-bowl-sinks/7.jpg"],
            imageAlt: "304 stainless steel drawn single-bowl sink model 7645",
            price: 14,
            priceMax: 54,
            summary: "304 stainless steel one-piece drawn single-bowl sinks in multiple sizes and orientations, based on catalogue pages 17 to 19.",
            seoDescription: "Drawn 304 stainless steel single-bowl sink series from catalogue pages 17-19. USD 14-54 per unit.",
            catalogueRows: [
                { image: "img/products/drawn-single-bowl-sinks/row-1.jpg", imageAlt: "Model 7645 Harbor drawn single-bowl sink", models: [
                    { code: "7645", note: "Harbor", size: "760 x 450 x 220 mm", cutout: "740 x 430 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-single-bowl-sinks/row-2.jpg", imageAlt: "Models 6845 and 7545 Harbor drawn sinks", models: [
                    { code: "6845", note: "Harbor", size: "680 x 450 x 220 mm", cutout: "660 x 430 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "7545", note: "Harbor", size: "750 x 450 x 220 mm", cutout: "730 x 430 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-single-bowl-sinks/row-3.jpg", imageAlt: "Reverse Harbor models 6845 and 7545", models: [
                    { code: "6845", note: "Reverse Harbor", size: "680 x 450 x 220 mm", cutout: "660 x 430 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "7545", note: "Reverse Harbor", size: "750 x 450 x 220 mm", cutout: "730 x 430 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-single-bowl-sinks/row-4.jpg", imageAlt: "Models 5843F, 6544F and 6845F Harbor sinks", models: [
                    { code: "5843F", note: "Harbor", size: "580 x 430 x 220 mm", cutout: "560 x 410 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "6544F", note: "Harbor", size: "650 x 440 x 220 mm", cutout: "630 x 420 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "6845F", note: "Harbor", size: "680 x 450 x 220 mm", cutout: "660 x 430 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-single-bowl-sinks/row-5.jpg", imageAlt: "Models 5843, 6245, 6544 and 6844 Harbor sinks", models: [
                    { code: "5843", note: "Harbor", size: "580 x 430 x 210 mm", cutout: "560 x 410 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "6245", note: "Harbor", size: "620 x 450 x 210 mm", cutout: "600 x 430 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "6544", note: "Harbor", size: "650 x 440 x 220 mm", cutout: "630 x 420 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "6844", note: "Harbor", size: "680 x 440 x 220 mm", cutout: "660 x 420 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-single-bowl-sinks/row-6.jpg", imageAlt: "Models 6045, 5040 and 5238 drawn single-bowl sinks", models: [
                    { code: "6045", size: "600 x 450 x 210 mm", cutout: "580 x 430 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "5040", size: "500 x 400 x 200 mm", cutout: "480 x 380 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "5238", size: "520 x 380 x 210 mm", cutout: "480 x 380 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-single-bowl-sinks/row-7.jpg", imageAlt: "Models 4835, 4539, 4238 and 3833 drawn single-bowl sinks", models: [
                    { code: "4835", size: "480 x 350 x 210 mm", cutout: "460 x 330 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "4539", size: "450 x 390 x 210 mm", cutout: "430 x 370 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "4238", size: "420 x 380 x 200 mm", cutout: "400 x 360 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "3833", size: "380 x 330 x 200 mm", cutout: "360 x 330 mm", material: "304 stainless steel, one-piece drawn" }
                ]}
            ]
        }),
        product({
            id: "drawn-double-bowl-sink-series-14-16",
            url: "products/drawn-double-bowl-sink-series-14-16",
            name: "Drawn Double-Bowl Sink Series",
            code: "8245 / 7843 / 8143 / 7541 / 7241 / 6839 / 6838V / 7339V / 8143V / 7843V / 7540V / 8043 / 8245R / S7241B / S7642B",
            image: "img/products/drawn-double-bowl-sinks/1.jpg",
            images: ["img/products/drawn-double-bowl-sinks/1.jpg", "img/products/drawn-double-bowl-sinks/2.jpg", "img/products/drawn-double-bowl-sinks/3.jpg", "img/products/drawn-double-bowl-sinks/4.jpg", "img/products/drawn-double-bowl-sinks/5.jpg", "img/products/drawn-double-bowl-sinks/6.jpg", "img/products/drawn-double-bowl-sinks/7.jpg"],
            imageAlt: "304 stainless steel drawn double-bowl sink model 8245 with knife rack",
            price: 12,
            priceMax: 49,
            summary: "304 stainless steel one-piece drawn double-bowl sinks in multiple bowl layouts, based on catalogue pages 14 to 16.",
            seoDescription: "Drawn 304 stainless steel double-bowl sink series from catalogue pages 14-16. USD 12-49 per unit.",
            catalogueRows: [
                { image: "img/products/drawn-double-bowl-sinks/row-1.jpg", imageAlt: "Models 8245 and 7843 double-bowl sinks with knife rack", models: [
                    { code: "8245", note: "With knife rack", size: "820 x 450 x 210 mm", cutout: "790 x 420 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "7843", note: "With knife rack", size: "780 x 430 x 210 mm", cutout: "750 x 400 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-double-bowl-sinks/row-2.jpg", imageAlt: "Models 8245, 8143 and 7843 drawn double-bowl sinks", models: [
                    { code: "8245", size: "820 x 450 x 230 mm", cutout: "800 x 430 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "8143", size: "810 x 430 x 230 mm", cutout: "790 x 410 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "7843", size: "780 x 430 x 220 mm", cutout: "760 x 410 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-double-bowl-sinks/row-3.jpg", imageAlt: "Models 7541, 7241 and 6839 drawn double-bowl sinks", models: [
                    { code: "7541", size: "750 x 410 x 220 mm", cutout: "730 x 390 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "7241", size: "720 x 410 x 220 mm", cutout: "700 x 390 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "6839", size: "680 x 390 x 210 mm", cutout: "660 x 370 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-double-bowl-sinks/row-4.jpg", imageAlt: "V-series drawn double-bowl sink models", models: [
                    { code: "6838V", size: "680 x 380 x 210 mm", cutout: "660 x 360 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "7339V", size: "730 x 390 x 210 mm", cutout: "710 x 370 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "8143V", size: "810 x 430 x 220 mm", cutout: "790 x 410 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "7843V", size: "780 x 430 x 220 mm", cutout: "760 x 410 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "7540V", size: "750 x 400 x 220 mm", cutout: "730 x 380 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-double-bowl-sinks/row-5.jpg", imageAlt: "Models 7541 and 8043 drawn double-bowl sinks", models: [
                    { code: "7541", size: "750 x 410 x 220 mm", cutout: "720 x 380 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "8043", size: "800 x 410 x 220 mm", cutout: "770 x 380 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-double-bowl-sinks/row-6.jpg", imageAlt: "Model 8245R drawn double-bowl sink", models: [
                    { code: "8245R", note: "Large half-round bowl", size: "820 x 450 x 220 mm", cutout: "800 x 430 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/drawn-double-bowl-sinks/row-7.jpg", imageAlt: "Models S7241B and S7642B unequal double-bowl sinks", models: [
                    { code: "S7241B", note: "Small left bowl, large right bowl", size: "720 x 410 x 210 mm", cutout: "700 x 390 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "S7642B", note: "Small left bowl, large right bowl", size: "770 x 420 x 215 mm", cutout: "740 x 400 mm", material: "304 stainless steel, one-piece drawn" }
                ]}
            ]
        }),
        product({
            id: "round-stainless-steel-sink-series-13",
            url: "products/round-stainless-steel-sink-series-13",
            name: "Round Stainless Steel Sink Series",
            code: "2828 / 3030 / 3232 / 3434 / 3636 / 4040 / 4242 / 4545",
            image: "img/products/round-stainless-steel-sinks/1.jpg",
            images: ["img/products/round-stainless-steel-sinks/1.jpg", "img/products/round-stainless-steel-sinks/2.jpg", "img/products/round-stainless-steel-sinks/3.jpg"],
            imageAlt: "Round 304 stainless steel drawn sink models 2828 and 3030",
            price: 15,
            priceMax: 55,
            summary: "Round 304 stainless steel one-piece drawn sinks in eight sizes, based on catalogue page 13.",
            seoDescription: "Round 304 stainless steel drawn sink series in eight sizes from 2828 to 4545. USD 15-55 per unit.",
            catalogueRows: [
                { image: "img/products/round-stainless-steel-sinks/row-1.jpg", imageAlt: "Round sink models 2828 and 3030", models: [
                    { code: "2828", note: "Brushed", size: "280 x 280 x 170 mm", cutout: "260 x 260 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "3030", note: "Brushed", size: "300 x 300 x 170 mm", cutout: "280 x 280 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/round-stainless-steel-sinks/row-2.jpg", imageAlt: "Round sink models 3232, 3434 and 3636", models: [
                    { code: "3232", note: "Brushed", size: "320 x 320 x 170 mm", cutout: "300 x 300 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "3434", note: "Brushed", size: "340 x 340 x 180 mm", cutout: "320 x 320 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "3636", note: "Brushed", size: "360 x 360 x 180 mm", cutout: "340 x 340 mm", material: "304 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/round-stainless-steel-sinks/row-3.jpg", imageAlt: "Round sink models 4040, 4242 and 4545", models: [
                    { code: "4040", note: "Brushed", size: "400 x 400 x 180 mm", cutout: "380 x 380 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "4242", note: "Brushed", size: "420 x 420 x 180 mm", cutout: "400 x 400 mm", material: "304 stainless steel, one-piece drawn" },
                    { code: "4545", note: "Brushed", size: "450 x 450 x 180 mm", cutout: "430 x 430 mm", material: "304 stainless steel, one-piece drawn" }
                ]}
            ]
        }),
        product({
            id: "round-nano-black-sink-series-12",
            url: "products/round-nano-black-sink-series-12",
            name: "Round Nano-Black Sink Series",
            code: "2828 / 3030 / 3232 / 3434 / 3636 / 4040 / 4242",
            image: "img/products/round-nano-black-sinks/1.jpg",
            images: ["img/products/round-nano-black-sinks/1.jpg", "img/products/round-nano-black-sinks/2.jpg", "img/products/round-nano-black-sinks/3.jpg"],
            imageAlt: "Round nano-black drawn stainless steel sink models 2828 and 3030",
            price: 10,
            priceMax: 42,
            summary: "Round nano-black 201 stainless steel one-piece drawn sinks in seven sizes, based on catalogue page 12.",
            seoDescription: "Round nano-black 201 stainless steel drawn sink series in seven sizes from 2828 to 4242. USD 10-42 per unit.",
            catalogueRows: [
                { image: "img/products/round-nano-black-sinks/row-1.jpg", imageAlt: "Round nano-black sink models 2828 and 3030", models: [
                    { code: "2828", note: "Nano black", size: "280 x 280 x 170 mm", cutout: "260 x 260 mm", material: "201 stainless steel, one-piece drawn" },
                    { code: "3030", note: "Nano black", size: "300 x 300 x 170 mm", cutout: "280 x 280 mm", material: "201 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/round-nano-black-sinks/row-2.jpg", imageAlt: "Round nano-black sink models 3232, 3434 and 3636", models: [
                    { code: "3232", note: "Nano black", size: "320 x 320 x 170 mm", cutout: "300 x 300 mm", material: "201 stainless steel, one-piece drawn" },
                    { code: "3434", note: "Nano black", size: "340 x 340 x 180 mm", cutout: "320 x 320 mm", material: "201 stainless steel, one-piece drawn" },
                    { code: "3636", note: "Nano black", size: "360 x 360 x 180 mm", cutout: "340 x 340 mm", material: "201 stainless steel, one-piece drawn" }
                ]},
                { image: "img/products/round-nano-black-sinks/row-3.jpg", imageAlt: "Round nano-black sink models 4040 and 4242", models: [
                    { code: "4040", note: "Nano black", size: "400 x 400 x 180 mm", cutout: "380 x 380 mm", material: "201 stainless steel, one-piece drawn" },
                    { code: "4242", note: "Nano black", size: "420 x 420 x 180 mm", cutout: "400 x 400 mm", material: "201 stainless steel, one-piece drawn" }
                ]}
            ]
        })
    );
}());
