# IanProject Website

Version 1.0 of the public IanProject website: <https://ianproject.com/>

## Site structure

- `index.html` — homepage
- `products.html` — product category and catalogue framework
- `product.html` — reusable product-detail template
- `custom-solutions.html` — custom project services
- `about.html` — IanProject, markets, factory and supply network
- `quality-process.html` — quality and delivery workflow
- `project-cases.html` / `case-study.html` — case index and reusable detail template
- `insights.html` / `insight-detail.html` — article index and reusable detail template
- `contact.html` / `enquiry.html` — email, WhatsApp and product enquiry
- `privacy.html` / `terms.html` — legal information

## Local preview

Serve this directory with a local static web server and open `index.html`. The production site is deployed from the GitHub `main` branch through Cloudflare Pages.

## Content and maintenance

- Product records are maintained in `js/catalog-data.js`.
- Catalogue rendering and filtering are handled by `js/catalog.js`.
- Shared presentation styles are in `css/style.css`.
- Keep reusable detail templates set to `noindex` until real content replaces the framework copy.
- Update `sitemap.xml` when a new public page is added.

## Licensing

The website is based on the free iSTUDIO template by HTML Codex and distributed by ThemeWagon. Required attribution remains in the website footer. See `LICENSE.txt` for the original template terms.
