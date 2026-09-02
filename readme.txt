=== Icecat Category Mapper for WooCommerce ===
Contributors: byensit
Tags: woocommerce, icecat, categories, product import, mapping
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically maps Icecat product categories to your own WooCommerce categories. Fully configurable from the admin.

== Description ==

If you import products through Icecat-based integrations (e.g. EANrunner, Store Manager, WP All Import), you know the problem: products come in with Icecat's English category names like "Keyboards", "Notebooks", "Gaming Mice" that do not match your shop's category structure.

**Icecat Category Mapper** solves this by automatically converting Icecat categories to your own WooCommerce categories the moment a product is saved.

= Features =

* **Plug and play** — catches all product imports via the `set_object_terms` hook, regardless of which import plugin is used
* **Fully configurable** — you map Icecat categories to your own WooCommerce categories in the admin UI
* **Reference library** — ships with 50+ popular Icecat categories pre-seeded (IDs + bilingual names)
* **Icecat API integration** — fetch the full category list from Open Icecat for free
* **3-tier matching** — Icecat ID in post meta &rarr; exact name &rarr; fuzzy match
* **Protected categories** — mark categories that must never be remapped
* **Fallback behavior** — keep, assign a fallback category, or remove unmapped categories
* **Full logging** — see what was remapped, when, and which categories are missing a mapping
* **Batch recheck** — run all existing products through the mapper
* **Multilingual** — fully translatable (English base, Danish translation included) and supports 11 Icecat languages for fetched category names

= How it works =

1. Install and activate the plugin
2. Go to **WooCommerce &raquo; Icecat Mapper**
3. Assign each Icecat category one of your own WooCommerce categories via the dropdown
4. The plugin now automatically remaps all products imported with those Icecat categories

You can also configure:
- Icecat API credentials (username/password) to fetch new categories
- Protected categories that are never remapped
- Fallback behavior for unmapped categories
- Log retention (number of days)

= Security =

* All database queries use prepared statements
* All admin handlers check nonces and capabilities
* A re-entrancy guard prevents infinite loops
* XMLReader streaming parser for memory-efficient XML parsing

== Installation ==

1. Upload `icecat-category-mapper.zip` via **Plugins &raquo; Add New Plugin &raquo; Upload Plugin**
2. Activate the plugin
3. Go to **WooCommerce &raquo; Icecat Mapper** to configure your mappings

Alternatively, unzip the contents manually into `/wp-content/plugins/` and activate from the **Plugins** page.

== Frequently Asked Questions ==

= Do I need an Icecat subscription? =

No, not for basic functionality. The plugin ships with 50+ popular Icecat categories pre-seeded — you just map them to your WooCommerce categories.

If you want to fetch the full Icecat category list (~5,000+ categories), you can create a free Open Icecat account at icecat.com.

= How does the plugin catch product imports? =

The plugin listens to WordPress' `set_object_terms` action, which fires every time a product category is assigned to a product — regardless of which import plugin is used. That means it works with:

- WooCommerce CSV Importer
- WooCommerce REST API
- WP All Import
- EANrunner
- Any other plugin that uses `wp_set_object_terms()`

= What if I already have incorrectly imported products? =

Go to the **Settings** tab and click **Recheck all products**. It runs all existing products through the mapper and remaps them according to your configuration.

= Can I add my own Icecat categories that are not in the bundle? =

Yes. Go to **Mappings &raquo; Add new mapping** and enter the Icecat category ID and name manually, or fetch them via the Icecat API.

= Is the plugin translatable? =

Yes. The base language is English and a full Danish translation (da_DK) is bundled. A `.pot` template is included in the `languages/` folder for additional translations.

== Screenshots ==

1. Mappings overview with filter and search
2. Add/edit mapping with Icecat search
3. Settings with protected categories and fallback behavior
4. Detailed log of all remappings

== Changelog ==

= 1.4.0 =
* FIX: Fuzzy name matching is now whole-word and unambiguous. The old substring match turned Icecat's catch-all "Other" into "M-other-boards" and filed 14 gaming mice under motherboards. Names that match several mappings with different targets now land in "Unmapped" instead of a silent wrong category.
* FIX: Ancestors of mapping targets (e.g. a "Components" parent) are protected automatically — no more re-evaluation and "unmapped" log noise on every product save.
* NEW: Title rules (Settings) — `slug | regex` lines applied when a category has no mapping (Icecat "Other", "Not Categorized", …), so the product title decides. Logged with action "title_rule".
* NEW: Title rules may target `draft` to hide a product type entirely (e.g. `draft | \blaptop\b|thinkpad`).
* NEW: Fallback "Draft" — products in a category with no mapping (and no title rule) are set to draft, so an import can never put unwanted product types (case fans, laptops, …) in front of customers. Logged with action "drafted".
* NEW: `tests/mapper-contract.php` — run with `wp eval-file` to prove the mapper on a real site.

= 1.3.0 =
* NEW: Remapping now also assigns the FULL parent chain (e.g. Headsets -> also "Gaming tilbehoer") on both the remap and fallback paths — matches how the shop's existing products are categorized
* NEW: "Beskyt" button on the Unmapped tab — one click protects the shop's own categories (Gaming computer, CS2, ...) from the remapper and removes them from the list
* NOTE: Configuring a mapping does NOT remap existing products by itself — run "Recheck all products" (Settings) afterwards; future imports are remapped instantly

= 1.2.0 =
* NEW: Full internationalization — all user-facing strings (PHP + JavaScript) now use WordPress i18n with the `icecat-category-mapper` text domain
* NEW: English base language so the plugin works on any WordPress site; complete Danish translation (da_DK) bundled, plus a `.pot` template for other languages
* CHANGE: Shop-specific "Byens IT" labels replaced with generic "WooCommerce category"
* CHANGE: "Danish name" field renamed to "Localized name" — it holds the category name in whatever Icecat language is configured under Settings
* CHANGE: Log/date columns now follow the site's date format setting instead of a hardcoded Danish format
* CHANGE: Danish reference names in the seed library now use proper Danish characters (æ/ø/å)
* FIX: Translations are loaded before the "requires WooCommerce" notice so it is shown in the site language
* FIX: XML parser language fallback aligned with the activation default (English)

= 1.1.0 =
* FIX (blocker): term names containing an HTML entity (&) — e.g. "Headphones & Headsets" — never matched mappings; the name is now decoded before matching (exact + fuzzy)
* FIX (blocker): the lookup cache is now keyed on term_id + meta Icecat ID, so product #1's mapping is no longer wrongly reused for the rest of a bulk import
* FIX: the cron handler (log purge) is registered independently of the auto-remap setting — the log no longer grows forever when auto-remap is off
* FIX: Strategy 1 (product meta Icecat ID) is only used when the product has a single source term — multiple categories no longer collapse into the same target
* FIX: fuzzy matching now has a minimum-length guard (>= 4 characters) + deterministic ORDER BY CHAR_LENGTH — fewer arbitrary false positives
* FIX: the WooCommerce default category ("Uncategorized") is always protected — products with no other categories no longer lose their category with fallback=remove
* NEW: HPOS compatibility declared (removes WooCommerce's "not compatible" banner)
* NEW: combined "Headphones & Headsets" seed row (the name Icecat/EANrunner actually delivers)
* IMPROVE: idempotent seeding — new default categories propagate on version upgrades, the admin's configured targets are preserved
* IMPROVE: capability check on ajax_search_icecat; products with no category at all are recorded under "Unmapped"; explicit autoload=false on the category cache

= 1.0.0 =
* First release
* Pre-seeded with 50+ popular Icecat categories
* Full admin UI with 4 tabs (Mappings, Unmapped, Settings, Log)
* Icecat API integration with streaming XML parser
* Batch recheck of existing products
* Protected categories configurable via settings

== Upgrade Notice ==

= 1.2.0 =
The admin UI base language is now English with a bundled Danish translation. Danish shops: set the site language to da_DK (Settings &raquo; General) to keep the Danish UI.
