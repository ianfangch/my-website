# IanProject Website

Static B2B product catalogue and project-sourcing website for
[ianproject.com](https://ianproject.com/). Production deploys from GitHub's
`main` branch through Cloudflare Pages.

## Site structure

- `index.html` - homepage
- `products.html` - filterable catalogue index
- `product.html` - non-indexed reusable product-detail template
- `products/` - indexable product landing pages
- `js/catalog-data.js` - catalogue source of truth
- `js/catalog.js` - catalogue filtering and detail rendering
- `img/products/<slug>/` - media grouped by product slug
- `css/` and `js/` - active shared site assets
- `lib/` - current third-party animation and carousel libraries
- `scss/` - Bootstrap/iSTUDIO source styles retained for future theme builds
- `sitemap.xml`, `robots.txt` and `_redirects` - search and hosting controls

## Local preview

Serve the repository root so extensionless links behave like production:

```powershell
python -m http.server 8080
```

Then open <http://localhost:8080/>.

Run the metadata, heading, JSON-LD, sitemap and local-reference checks with:

```powershell
node scripts/validate-site.mjs
```

## Content and SEO maintenance

- Use lowercase kebab-case for page names, product slugs and media folders.
- Keep each product's images inside its matching `img/products/<slug>/` folder.
- Treat `js/catalog-data.js` as the catalogue source of truth; do not duplicate
  product records in additional JavaScript files.
- Keep reusable templates set to `noindex` until real content replaces them.
- Add a product page, catalogue record, media folder, canonical URL, structured
  data and sitemap entry as one change.
- Keep internal links extensionless and ensure canonical and sitemap URLs return
  HTTP 200. Legacy URLs may redirect but must not appear in navigation or sitemap.
- Preserve the B2B enquiry flow through Request a Quote, email or WhatsApp; do
  not add an invented online payment flow.

## Licensing

The site is based on the free iSTUDIO template by HTML Codex and distributed by
ThemeWagon. See `LICENSE.txt` for terms.
