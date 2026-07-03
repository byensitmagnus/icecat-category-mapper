# Icecat Category Mapper for WooCommerce

Automatisk mapping af Icecat-produktkategorier til dine egne WooCommerce-kategorier. Fuldt konfigurerbar via admin.

Importerer du produkter via Icecat-integrationer (fx EANrunner, Store Manager, WP All Import), kommer produkterne ind med Icecats engelske kategorinavne som "Keyboards", "Notebooks" og "Gaming Mice", der ikke matcher din webshops kategoristruktur. Dette plugin konverterer dem automatisk til dine egne WooCommerce-kategorier i samme øjeblik et produkt gemmes.

## Funktioner

- **Plug-and-play** — fanger alle produkt-imports via `set_object_terms`-hook, uanset hvilken import-plugin der bruges
- **Fuldt konfigurerbar** — map selv Icecat-kategorier til dine egne WooCommerce-kategorier via admin-UI
- **Reference-bibliotek** — leveres med 50+ populære Icecat-kategorier præ-seedet (IDs + bilinguale navne)
- **Icecat API-integration** — hent den fulde kategoriliste fra Open Icecat gratis
- **3-lags matching** — Icecat-ID i post meta → eksakt navn → fuzzy match
- **Beskyttede kategorier** — markér kategorier der aldrig skal remappes
- **Fallback-adfærd** — behold, tildel fallback-kategori, eller fjern umappede kategorier
- **Fuld logging** — se hvad der er remappet, hvornår, og hvilke kategorier der mangler mapping
- **Batch recheck** — kør alle eksisterende produkter igennem mapperen
- **HPOS-kompatibel** — deklareret kompatibilitet med High-Performance Order Storage

## Krav

- WordPress 6.0+
- WooCommerce (kræves aktivt)
- PHP 7.4+

## Installation

1. Download nyeste `icecat-category-mapper-*.zip` under [Releases](../../releases)
2. Upload via **Plugins → Tilføj nyt plugin → Upload plugin**
3. Aktivér pluginet
4. Gå til **WooCommerce → Icecat Mapper** og konfigurér dine mappings

Et Icecat-abonnement er ikke nødvendigt — pluginet leveres med 50+ præ-seedede kategorier. Vil du hente den fulde kategoriliste (~5.000+), kan du oprette en gratis Open Icecat-konto på icecat.com og indtaste credentials under **Indstillinger**.

## Changelog

Se [readme.txt](readme.txt) for fuld changelog.

## Licens

GPL v2 or later — se [LICENSE](LICENSE).
