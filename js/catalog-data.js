(function () {
    "use strict";

    window.IAN_CATALOG = {
        currency: "USD",
        categories: [
            { id: "cabinetry", name: "Cabinetry" },
            { id: "sinks-faucets", name: "Sinks & Faucets" },
            { id: "countertops", name: "Countertops" },
            { id: "appliances", name: "Appliances" },
            { id: "storage-accessories", name: "Storage Accessories" },
            { id: "hardware-components", name: "Hardware & Components" }
        ],
        products: [
            {
                id: "modular-kitchen-system",
                category: "cabinetry",
                name: "Modular Kitchen System",
                code: "CAB-MKS-001",
                badge: "Made to order",
                image: "img/catalog/full-home-cabinetry.jpg",
                imageAlt: "Representative modular kitchen cabinet system",
                price: null,
                pricePrefix: "From",
                priceUnit: "per set",
                summary: "A coordinated cabinet starting point with selectable layout, finish and hardware options.",
                highlights: ["Layout and drawing support", "Multiple finish options", "Export packing"],
                specifications: {
                    "Configuration": "Base, wall and tall units",
                    "Dimensions": "Confirmed from project drawings",
                    "Finish": "Project selection",
                    "Order type": "Made to order"
                }
            },
            {
                id: "stainless-steel-kitchen-sink",
                category: "sinks-faucets",
                name: "Stainless Steel Kitchen Sink",
                code: "SNK-SS-001",
                badge: "Standard",
                image: "img/catalog/kitchen-sink.jpg",
                imageAlt: "Representative stainless steel kitchen sink",
                price: null,
                pricePrefix: "From",
                priceUnit: "per unit",
                summary: "A practical sink range with multiple bowl, size, mounting and accessory options.",
                highlights: ["Multiple sizes", "Mounting options", "Accessory selection"],
                specifications: {
                    "Material": "Stainless steel grade to be confirmed",
                    "Installation": "Undermount or topmount options",
                    "Accessories": "Available by model",
                    "Packing": "Export packing"
                }
            },
            {
                id: "engineered-quartz-surface",
                category: "countertops",
                name: "Engineered Quartz Surface",
                code: "TOP-QTZ-001",
                badge: "Standard range",
                image: "img/catalog/countertop-detail.jpg",
                imageAlt: "Representative engineered quartz countertop surface",
                price: null,
                pricePrefix: "From",
                priceUnit: "per m²",
                summary: "Quartz surface options prepared to the confirmed colour, thickness, edge and cut-out requirements.",
                highlights: ["Colour selection", "Cut-to-size options", "Edge preparation"],
                specifications: {
                    "Material": "Engineered quartz",
                    "Thickness": "Confirmed by selected range",
                    "Fabrication": "Subject to drawings",
                    "Packing": "Export crate where required"
                }
            },
            {
                id: "built-in-appliance-package",
                category: "appliances",
                name: "Built-in Appliance Package",
                code: "APP-BLT-001",
                badge: "Market specific",
                image: "img/catalog/kitchen-appliances.jpg",
                imageAlt: "Representative built-in kitchen appliance package",
                price: null,
                pricePrefix: "From",
                priceUnit: "per package",
                summary: "A coordinated appliance package selected for cabinet dimensions and destination-market requirements.",
                highlights: ["Dimension coordination", "Voltage and plug check", "Market-specific selection"],
                specifications: {
                    "Package": "Selected by project",
                    "Electrical": "Confirmed for destination market",
                    "Certification": "Confirmed by model and market",
                    "Warranty": "Confirmed in quotation"
                }
            }
        ]
    };
}());
