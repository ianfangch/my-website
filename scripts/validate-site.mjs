import { existsSync, readFileSync, readdirSync } from "node:fs";
import { dirname, join, relative, resolve } from "node:path";

const root = resolve(import.meta.dirname, "..");
const htmlFiles = [];

function walk(directory) {
    for (const entry of readdirSync(directory, { withFileTypes: true })) {
        if (entry.name === ".git" || entry.name === "node_modules") continue;
        const path = join(directory, entry.name);
        if (entry.isDirectory()) walk(path);
        else if (entry.name.endsWith(".html")) htmlFiles.push(path);
    }
}

function count(content, pattern) {
    return [...content.matchAll(pattern)].length;
}

function localTarget(page, reference, hasBase) {
    const clean = reference.split(/[?#]/, 1)[0];
    if (!clean || /^(?:https?:|mailto:|tel:|javascript:|data:|\/\/)/i.test(clean)) return null;
    const base = clean.startsWith("/") || hasBase ? root : dirname(page);
    const target = resolve(base, clean.replace(/^\//, ""));
    if (existsSync(target)) return target;
    if (existsSync(`${target}.html`)) return `${target}.html`;
    if (existsSync(join(target, "index.html"))) return join(target, "index.html");
    return target;
}

walk(root);
const errors = [];
const sitemap = readFileSync(join(root, "sitemap.xml"), "utf8");
const sitemapUrls = new Set([...sitemap.matchAll(/<loc>(.*?)<\/loc>/g)].map((match) => match[1]));

for (const page of htmlFiles) {
    const content = readFileSync(page, "utf8");
    const name = relative(root, page).replaceAll("\\", "/");
    const noindex = /<meta[^>]+name=["']robots["'][^>]+noindex/i.test(content);

    if (count(content, /<title\b/gi) !== 1) errors.push(`${name}: expected one title`);
    if (!noindex) {
        if (count(content, /<meta\s+[^>]*name=["']description["']/gi) !== 1) errors.push(`${name}: expected one meta description`);
        if (count(content, /<link\s+[^>]*rel=["']canonical["']/gi) !== 1) errors.push(`${name}: expected one canonical`);
        if (count(content, /<h1(?:\s|>)/gi) !== 1) errors.push(`${name}: expected one H1`);
    }

    for (const match of content.matchAll(/<script[^>]+type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi)) {
        try { JSON.parse(match[1]); } catch (error) { errors.push(`${name}: invalid JSON-LD (${error.message})`); }
    }

    const hasBase = /<base\s+[^>]*href=["']\/["']/i.test(content);
    for (const match of content.matchAll(/\b(?:src|href)=["']([^"']+)["']/gi)) {
        const target = localTarget(page, match[1], hasBase);
        if (target && !existsSync(target)) errors.push(`${name}: missing local reference ${match[1]}`);
    }
}

for (const url of sitemapUrls) {
    const pathname = new URL(url).pathname;
    const target = pathname === "/" ? join(root, "index.html") : join(root, `${pathname.slice(1)}.html`);
    if (!existsSync(target)) errors.push(`sitemap: missing page for ${url}`);
}

if (errors.length) {
    console.error(errors.join("\n"));
    console.error(`\nValidation failed with ${errors.length} error(s).`);
    process.exit(1);
}

console.log(`Validated ${htmlFiles.length} HTML files and ${sitemapUrls.size} sitemap URLs.`);
