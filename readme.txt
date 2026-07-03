=== Icecat Category Mapper for WooCommerce ===
Contributors: icecatmapperteam
Tags: woocommerce, icecat, categories, product import, mapping
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatisk mapping af Icecat produktkategorier til dine egne WooCommerce-kategorier. Fuldt konfigurerbar via admin.

== Description ==

Hvis du importerer produkter via Icecat-integrationer (fx EANrunner, Store Manager, WP All Import) kender du problemet: produkterne kommer ind med Icecats engelske kategorinavne som "Keyboards", "Notebooks", "Gaming Mice", der ikke matcher din webshops kategoristruktur.

**Icecat Category Mapper** loeser det ved automatisk at konvertere Icecat-kategorier til dine egne WooCommerce-kategorier i samme oejeblik et produkt gemmes.

= Funktioner =

* **Plug-and-play** — fanger alle produkt-imports via `set_object_terms`-hook, uanset hvilken import-plugin der bruges
* **Fuldt konfigurerbar** — du mapper selv Icecat-kategorier til dine egne WooCommerce-kategorier via admin-UI
* **Reference-bibliotek** — leveres med 50+ popul&aelig;re Icecat-kategorier pr&aelig;-seedet (IDs + bilingual navne)
* **Icecat API-integration** — hent den fulde kategoriliste fra Open Icecat gratis
* **3-lags matching** — Icecat ID i post meta &rarr; eksakt navn &rarr; fuzzy match
* **Beskyttede kategorier** — markér kategorier der aldrig skal remappes
* **Fallback-adf&aelig;rd** — behold, tildel fallback-kategori, eller fjern umappede kategorier
* **Fuld logging** — se hvad der er remappet, hvorn&aring;r, og hvilke kategorier der mangler mapping
* **Batch recheck** — k&oslash;r alle eksisterende produkter igennem mapperen
* **Multisprog** — understoetter 11 Icecat-sprog (engelsk, dansk, tysk, fransk, spansk, osv.)

= Hvordan det fungerer =

1. Installer og aktiver pluginet
2. G&aring; til **WooCommerce &raquo; Icecat Mapper**
3. Tildel hver Icecat-kategori en af dine egne WooCommerce-kategorier via dropdown
4. Pluginet remapper nu automatisk alle produkter der importeres med disse Icecat-kategorier

Du kan ogs&aring; konfigurere:
- Icecat API-credentials (brugernavn/password) for at hente nye kategorier
- Beskyttede kategorier der aldrig remappes
- Fallback-adfaerd for umappede kategorier
- Log-opbevaring (antal dage)

= Sikkerhed =

* Alle database-queries bruger prepared statements
* Alle admin-handlers checker nonces og capabilities
* Re-entrancy guard forhindrer uendelige loekker
* XMLReader streaming parser for memory-effektiv XML-parsing

== Installation ==

1. Upload `icecat-category-mapper.zip` via **Plugins &raquo; Tilf&oslash;j nyt plugin &raquo; Upload plugin**
2. Aktiver pluginet
3. G&aring; til **WooCommerce &raquo; Icecat Mapper** for at konfigurere mappings

Alternativt kan du unzippe indholdet manuelt i `/wp-content/plugins/` og aktivere via **Plugins**-siden.

== Frequently Asked Questions ==

= Skal jeg have et Icecat-abonnement? =

Nej, for grundl&aelig;ggende funktionalitet er det ikke n&oslash;dvendigt. Pluginet leveres med 50+ popul&aelig;re Icecat-kategorier pr&aelig;-seedet, som du bare skal mappe til dine WooCommerce-kategorier.

Hvis du vil hente den fulde Icecat-kategoriliste (~5.000+ kategorier), kan du oprette en gratis Open Icecat-konto p&aring; icecat.com.

= Hvordan fanger pluginet produkt-imports? =

Pluginet lytter p&aring; WordPress' `set_object_terms`-action, som fyres hver gang en product-category tildeles et produkt — uanset hvilken import-plugin der bruges. Det betyder det fungerer med:

- WooCommerce CSV Importer
- WooCommerce REST API
- WP All Import
- EANrunner
- Alle andre plugins der bruger `wp_set_object_terms()`

= Hvad hvis jeg allerede har produkter importeret forkert? =

G&aring; til **Indstillinger**-tabben og klik p&aring; **Genkontroller alle produkter**. Det k&oslash;rer alle eksisterende produkter igennem mapperen og remapper dem ifoelge din konfiguration.

= Kan jeg tilf&oslash;je egne Icecat-kategorier der ikke er i pakken? =

Ja. G&aring; til **Mappinger &raquo; Tilf&oslash;j ny mapping** og indtast Icecat-kategori-ID og navn manuelt, eller hent dem via Icecat API.

== Screenshots ==

1. Mappings-oversigt med filter og s&oslash;gning
2. Tilfoej/rediger mapping med Icecat-s&oslash;gning
3. Indstillinger med beskyttede kategorier og fallback-adfaerd
4. Detaljeret log over alle remappings

== Changelog ==

= 1.1.0 =
* FIX (blocker): term-navne med HTML-entity (&) — fx "Headphones & Headsets" — matchede aldrig mod mappings; navnet decodes nu foer matching (eksakt + fuzzy)
* FIX (blocker): lookup-cachen noegles nu paa term_id + meta-Icecat-ID, saa produkt #1's mapping ikke genbruges forkert for resten under en bulk-import
* FIX: cron-handleren (log-purge) registreres nu uafhaengigt af auto-remap-indstillingen — loggen vokser ikke laengere uendeligt naar auto-remap slaas fra
* FIX: Strategi 1 (produkt-meta-Icecat-ID) bruges kun naar produktet har én kilde-term — flere kategorier kollapses ikke laengere til samme maal
* FIX: fuzzy-match har nu min-laengde-guard (>= 4 tegn) + deterministisk ORDER BY CHAR_LENGTH — faerre vilkaarlige false-positives
* FIX: WooCommerce default-kategori ("Ukategoriseret") beskyttes altid — produkter uden andre kategorier mister ikke deres kategori ved fallback=remove
* NEW: HPOS-kompatibilitet deklareret (fjerner WooCommerce's "ikke kompatibel"-banner)
* NEW: kombineret "Headphones & Headsets"-seed-raekke (det navn Icecat/EANrunner faktisk leverer)
* IMPROVE: idempotent seeding — nye default-kategorier propageres ved version-upgrade, admins konfigurerede maal bevares
* IMPROVE: capability-check paa ajax_search_icecat; produkter helt uden kategori registreres i "Ikke-mappede"; eksplicit autoload=false paa kategori-cachen

= 1.0.0 =
* Foerste release
* Pre-seedet med 50+ popul&aelig;re Icecat-kategorier
* Fuld admin-UI med 4 tabs (Mappinger, Ikke-mappede, Indstillinger, Log)
* Icecat API-integration med streaming XML-parser
* Batch recheck af eksisterende produkter
* Beskyttede kategorier konfigurerbar via settings
* Dansk UI med engelsk fallback

== Upgrade Notice ==

= 1.0.0 =
Foerste release.
