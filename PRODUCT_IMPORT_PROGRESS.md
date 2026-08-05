# Product Import Progress

Last updated: 2026-08-05

## Agreed source and publishing rules

- Use `https://www.oulin-oversea.com/` as the only product-data and image source.
- Do not use the Alibaba store for future imports.
- Replace the displayed `OULIN` model prefix with `OL`.
- Publish products in reviewed batches of five.
- Every published product requires six local images, visible source-brand removal, optimized JPG files, edited English copy, accurate specifications, a USD starting price and an enquiry route.

## Published batch 1

1. `OLCG026` — Compact Smart Kitchen with Pull-Out Storage
2. `OLCG025` — Aluminium-Frame Modular Kitchen
3. `OLCG012` — Modern Shaker Kitchen with Smart Storage
4. `OLCG051` — Minimalist Glass-Front Kitchen
5. `OLCG038-1` — High-Gloss Modern Kitchen

Batch 1 contains 30 optimized product images and was published on 2026-08-04 through PR #9.

## Published batch 2

6. `OLCG027-1` — Premium Brown Modular Kitchen with Island
7. `OLCG012-2` — Smart Shaker Kitchen with Integrated Storage
8. `OLCG025-2` — Modern Glass-Front Kitchen with Wine Storage
9. `OLCG018-2` — Blue Melamine Modular Kitchen
10. `OLCG004` — Modern Melamine Kitchen with Integrated Storage

Batch 2 contains 30 optimized product images and was published on 2026-08-04 through PR #10 (merge commit `0516af9c730c5d414a7ab76207348f5431ebc1f0`). All visible OULIN marks were removed, and every product has six descriptive image alt texts, a natural English search title, a search description, price, MOQ, lead time, highlights and detailed specifications.

## Published batch 3

11. `OLCG018-1` — Blue Minimalist Modular Kitchen
12. `OLCG035` — Neutral Modular Kitchen for Home Renovation
13. `OL62749344` — Custom Grey Apartment Kitchen
14. `OLCG022` — Minimalist White and Wood Mini Kitchen
15. `OLCG021` — Grey and Blush Modular Kitchen

Batch 3 contains 30 optimized product images and was published on 2026-08-04 through PR #13 (merge commit `811ec07aad23c7509973df33251a808978b37d22`). All visible source-brand marks were removed, and every product has six descriptive image alt texts, edited English copy, a starting price, MOQ, lead time, highlights, specifications and a dedicated SEO page. `OL62749344` is an internal traceable code based on the source page ID because the source does not publish a manufacturer model for that item.

## Prepared batch 4

16. `OLCG008` — Compact Grey Apartment Kitchen
17. `OLCG014` — Grey Melamine Island Kitchen
18. `OL62749324` — Grey Lacquer Island Kitchen
19. `OLCG018` — Blue and Black Storage Kitchen
20. `OLCG055` — Warm Wood Complete Kitchen with Island

Batch 4 contains 30 optimized, logo-free product images, edited English copy, starting prices, specifications, complete commercial information and dedicated SEO pages. `OL62749324` uses a traceable internal code based on the source page ID because that source item does not publish a manufacturer model. Products 17 and 18 show related layouts but are positioned separately as melamine and lacquer configurations.

## Catalogue and SEO decisions

- Product cards use an approximately 50/50 image-to-copy split.
- Card titles and summaries are limited to two lines.
- Product details use a main image plus six clickable thumbnails.
- Products remain enquiry-based rather than direct online checkout.
- Every published product now has a dedicated crawlable URL under `/products/`.
- Each dedicated page includes unique title and description metadata, a canonical URL, social-sharing metadata, visible HTML product content and Product/Offer JSON-LD in the initial HTML.
- All twenty dedicated product URLs are listed in `sitemap.xml`.

## Next batch after batch 4

- After batch 4 is published, resume with the next source-order Melamine Kitchen Cabinet product after “New arrivals kitchen complete cheap kitchen cabinet”.
- Continue in source-category order and use the same image, data, naming and SEO standard.
- Do not re-import or regenerate the first twenty products unless the user requests changes.
