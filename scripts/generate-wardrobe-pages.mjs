import fs from "node:fs";
import path from "node:path";

const root = path.resolve(import.meta.dirname, "..");
const template = fs.readFileSync(path.join(root, "products", "white-concrete-dining-kitchen.html"), "utf8");
const oldSummary = "A compact white concrete-look kitchen with a linear work zone, warm timber niches and an attached dining table for efficient apartments.";
const oldDescription = "White concrete-look dining kitchen cabinets with timber niches and quartz worktop. Model OLCG034, from USD 351 per metre.";
const oldHighlights = ["White concrete-look cabinet fronts", "Warm timber open storage niches", "Attached dining table for compact layouts"];

const products = [
  ["dark-glass-walk-in-wardrobe", "Dark Glass Walk-In Wardrobe", "OLQW001", 203, "A dark timber walk-in wardrobe with glass-front storage, open shelving, a central drawer island and an integrated window seat.", "Dark glass walk-in wardrobe with central island, open shelving and custom storage. Model OLQW001, from USD 203 per square metre.", "Dark glass walk-in wardrobe with central storage island", ["Glass-front wardrobe storage", "Central drawer island and window seat", "Custom shelving, hanging and drawer layout"], "glass-door-wardrobe-system"],
  ["light-oak-open-walk-in-wardrobe", "Light Oak Open Walk-In Wardrobe", "OLQW013", 203, "A warm light-oak walk-in wardrobe with open hanging zones, illuminated shelving, drawers and flexible corner storage.", "Light oak open walk-in wardrobe with illuminated shelving, drawers and custom corner storage. Model OLQW013, from USD 203 per square metre.", "Light oak open walk-in wardrobe with illuminated storage", ["Warm light-oak open storage", "Integrated LED shelf lighting", "Flexible hanging, drawer and shoe zones"], "wardrobe-living-room-cabinet-system"],
  ["black-glass-wardrobe-blush-drawers", "Black Glass Wardrobe with Blush Drawers", "OL62749318", 268, "A contemporary black-framed glass wardrobe combining illuminated hanging sections, marble-look back panels and blush drawer accents.", "Black glass bedroom wardrobe with marble-look panels, lighting and blush drawers. Model OL62749318, from USD 268 per square metre.", "Black glass wardrobe with marble panels and blush drawers", ["Black-framed smoked glass doors", "Marble-look illuminated back panels", "Blush drawer accents and custom storage"], "glass-wardrobe-closet-system"],
  ["ivory-glass-wardrobe-dressing-table", "Ivory Glass Wardrobe with Dressing Table", "OLQW015-4", 433, "An elegant ivory timber wardrobe with framed glass doors, illuminated upper cabinets and a built-in dressing table for coordinated bedrooms.", "Ivory glass-door bedroom wardrobe with built-in dressing table and illuminated storage. Model OLQW015-4, from USD 433 per square metre.", "Ivory glass wardrobe with integrated dressing table", ["Ivory framed glass wardrobe doors", "Integrated dressing table and mirror", "Illuminated upper display storage"], "sliding-door-wardrobe-dressing-table"],
  ["black-glass-marble-panel-wardrobe", "Black Glass Marble-Panel Wardrobe", "OLQW018", 268, "A sleek black glass wardrobe with illuminated marble-look panels, open display shelves and blush drawer fronts for modern bedrooms.", "Black glass wardrobe with illuminated marble-look panels and blush drawers. Model OLQW018, from USD 268 per square metre.", "Black glass wardrobe with illuminated marble-look panels", ["Full-height black glass framing", "Illuminated marble-look feature panels", "Blush drawers and open display shelves"], "modern-glass-door-bedroom-wardrobe"],
];

for (const [id, name, code, price, summary, description, alt, highlights, folder] of products) {
  let html = template
    .replaceAll("white-concrete-dining-kitchen", id)
    .replaceAll("White Concrete Dining Kitchen", name)
    .replaceAll(oldSummary, summary)
    .replaceAll(oldDescription, description)
    .replaceAll("OLCG034", code)
    .replaceAll("White concrete-look kitchen with attached dining table", alt)
    .replaceAll("img/products/" + id, "img/products/" + folder)
    .replaceAll("Modular Kitchen Cabinets", "Custom Wardrobes")
    .replaceAll("USD 351.00", `USD ${price.toFixed(2)}`)
    .replaceAll('content="351"', `content="${price}"`)
    .replaceAll('"price":351', `"price":${price}`)
    .replaceAll("per metre", "per square metre");
  for (let i = 0; i < 3; i += 1) html = html.replaceAll(oldHighlights[i], highlights[i]);
  fs.writeFileSync(path.join(root, "products", `${id}.html`), html);
  console.log(`Generated products/${id}.html`);
}
