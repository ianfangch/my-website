import { readFileSync, readdirSync, writeFileSync } from "node:fs";
import { resolve } from "node:path";

const root = resolve(import.meta.dirname, "..");
const productsDirectory = resolve(root, "products");
const cataloguePath = resolve(root, "products.html");
const categoryPages = new Set(["kitchen-cabinets.html", "kitchen-sinks.html", "quartz-countertops.html", "flooring.html"]);

const items = readdirSync(productsDirectory)
    .filter((name) => name.endsWith(".html") && !categoryPages.has(name))
    .map((name) => {
        const html = readFileSync(resolve(productsDirectory, name), "utf8");
        const blocks = [...html.matchAll(/<script[^>]+type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi)];
        const product = blocks.map((match) => {
            try { return JSON.parse(match[1]); } catch { return null; }
        }).find((data) => data && data["@type"] === "Product");
        const title = html.match(/<h1(?:\s[^>]*)?>([\s\S]*?)<\/h1>/i)?.[1]
            .replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
        const productName = product?.name || title;
        if (!productName) throw new Error(`Missing product name: products/${name}`);
        return {
            "@type": "ListItem",
            position: 0,
            url: `https://ianproject.com/products/${name.replace(/\.html$/, "")}`,
            name: productName
        };
    })
    .sort((a, b) => a.name.localeCompare(b.name))
    .map((item, index) => ({ ...item, position: index + 1 }));

const schema = {
    "@context": "https://schema.org",
    "@type": "ItemList",
    name: "IanProject Whole-Home Product Catalogue",
    numberOfItems: items.length,
    itemListElement: items
};

const html = readFileSync(cataloguePath, "utf8");
const schemaBlock = /<script type="application\/ld\+json">[\s\S]*?<\/script>/;
if (!schemaBlock.test(html)) throw new Error("Catalogue ItemList JSON-LD block was not found.");
const updated = html.replace(
    schemaBlock,
    `<script type="application/ld+json">\n${JSON.stringify(schema, null, 2)}\n    </script>`
);
writeFileSync(cataloguePath, updated);
console.log(`Updated catalogue ItemList with ${items.length} products.`);
