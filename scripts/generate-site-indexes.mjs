import { readFileSync, readdirSync, writeFileSync } from "node:fs";
import { join, resolve } from "node:path";

const root = resolve(import.meta.dirname, "..");
const productsDirectory = join(root, "products");
const categoryPages = new Set(["kitchen-cabinets.html", "kitchen-sinks.html", "quartz-countertops.html", "flooring.html"]);

function htmlFiles(directory = root) {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        if (entry.name === ".git" || entry.name === "node_modules") return [];
        const path = join(directory, entry.name);
        return entry.isDirectory() ? htmlFiles(path) : (entry.name.endsWith(".html") ? [path] : []);
    });
}

function canonical(html) {
    return html.match(/<link\s+[^>]*rel=["']canonical["'][^>]*href=["']([^"']+)["']/i)?.[1] ||
        html.match(/<link\s+[^>]*href=["']([^"']+)["'][^>]*rel=["']canonical["']/i)?.[1];
}

const productItems = readdirSync(productsDirectory)
    .filter((name) => name.endsWith(".html") && !categoryPages.has(name))
    .map((name) => {
        const html = readFileSync(join(productsDirectory, name), "utf8");
        const productSchema = [...html.matchAll(/<script[^>]+type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi)]
            .map((match) => { try { return JSON.parse(match[1]); } catch { return null; } })
            .find((data) => data?.["@type"] === "Product");
        const title = html.match(/<h1(?:\s[^>]*)?>([\s\S]*?)<\/h1>/i)?.[1].replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
        const nameValue = productSchema?.name || title;
        if (!nameValue) throw new Error(`Missing product name: products/${name}`);
        return { "@type": "ListItem", position: 0, url: `https://ianproject.com/products/${name.replace(/\.html$/, "")}`, name: nameValue };
    })
    .sort((a, b) => a.name.localeCompare(b.name))
    .map((item, index) => ({ ...item, position: index + 1 }));

const cataloguePath = join(root, "products.html");
const catalogue = readFileSync(cataloguePath, "utf8");
const schema = { "@context": "https://schema.org", "@type": "ItemList", name: "IanProject Whole-Home Product Catalogue", numberOfItems: productItems.length, itemListElement: productItems };
const schemaBlock = /<script type="application\/ld\+json">[\s\S]*?<\/script>/;
if (!schemaBlock.test(catalogue)) throw new Error("Catalogue ItemList JSON-LD block was not found.");
writeFileSync(cataloguePath, catalogue.replace(schemaBlock, `<script type="application/ld+json">\n${JSON.stringify(schema, null, 2)}\n    </script>`));

const urls = [...new Set(htmlFiles().map((path) => {
    const html = readFileSync(path, "utf8");
    if (/<meta[^>]+name=["']robots["'][^>]+noindex/i.test(html)) return null;
    return canonical(html);
}).filter((url) => url?.startsWith("https://ianproject.com/")))].sort((a, b) => {
    if (a === "https://ianproject.com/") return -1;
    if (b === "https://ianproject.com/") return 1;
    return a.localeCompare(b);
});
const sitemap = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.map((url) => `  <url><loc>${url}</loc></url>`).join("\n")}\n</urlset>\n`;
writeFileSync(join(root, "sitemap.xml"), sitemap);
console.log(`Generated ${productItems.length} products in ItemList and ${urls.length} sitemap URLs.`);
