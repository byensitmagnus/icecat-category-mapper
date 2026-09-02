# Icecat Category Mapper for WooCommerce

Automatically maps Icecat product categories to your own WooCommerce categories. Fully configurable from the admin — works on any WordPress + WooCommerce shop.

If you import products through Icecat-based integrations (e.g. EANrunner, Store Manager, WP All Import), products come in with Icecat's English category names like "Keyboards", "Notebooks" and "Gaming Mice" that do not match your shop's category structure. This plugin converts them to your own WooCommerce categories automatically the moment a product is saved.

## Features

- **Plug and play** — catches all product imports via the `set_object_terms` hook, regardless of which import plugin is used
- **Fully configurable** — you map Icecat categories to your own WooCommerce categories in the admin UI (WooCommerce → Icecat Mapper)
- **Reference library** — ships with 50+ popular Icecat categories pre-seeded (IDs + bilingual names)
- **Icecat API integration** — fetch the full category list (~5,000+ categories) from Open Icecat for free
- **3-tier matching** — Icecat ID in post meta → exact name → whole-word fuzzy match (only when every matching mapping agrees on the target; never a guess)
- **Title rules** — `slug | regex` lines applied when a category has no mapping, so products in Icecat's catch-all "Other" / "Not Categorized" are filed by their title
- **Protected categories** — mark categories that must never be remapped
- **Fallback behavior** — keep, assign a fallback category, remove the category, or set the product to draft (never show unmapped product types to customers)
- **Full logging** — see what was remapped, when, and which categories are missing a mapping
- **Batch recheck** — run all existing products through the mapper
- **Translatable** — English base language, complete Danish translation (da_DK) bundled, `.pot` template included for other languages
- **HPOS compatible** — declared compatibility with High-Performance Order Storage

## Testing

On a staging site with the plugin active:

```
wp eval-file wp-content/plugins/icecat-category-mapper/tests/mapper-contract.php
```

Creates temporary categories/products, asserts the mapping decisions, cleans up, and exits non-zero on failure.

## Testing

On a staging site with the plugin active:

```
wp eval-file wp-content/plugins/icecat-category-mapper/tests/mapper-contract.php
```

Creates temporary categories/products, asserts the mapping decisions, cleans up, and exits non-zero on failure.

## Testing

On a staging site with the plugin active:

```
wp eval-file wp-content/plugins/icecat-category-mapper/tests/mapper-contract.php
```

Creates temporary categories/products, asserts the mapping decisions, cleans up, and exits non-zero on failure.

## Requirements

- WordPress 6.0+
- WooCommerce (must be active)
- PHP 7.4+

## Installation

1. Download the latest `icecat-category-mapper-*.zip` from [Releases](../../releases)
2. Upload via **Plugins → Add New Plugin → Upload Plugin**
3. Activate the plugin
4. Go to **WooCommerce → Icecat Mapper** and configure your mappings

No Icecat subscription is required — the plugin ships with 50+ pre-seeded categories. To fetch the full category list, create a free Open Icecat account at icecat.com and enter your credentials under **Settings**.

## Translations

The base language is English. A full Danish translation is bundled and loads automatically when the site language is set to `da_DK`. To add another language, translate `languages/icecat-category-mapper.pot` and place the compiled `.mo` file in the `languages/` folder (or use a tool like Loco Translate).

## Changelog

See [readme.txt](readme.txt) for the full changelog.

## License

GPL v2 or later — see [LICENSE](LICENSE).
