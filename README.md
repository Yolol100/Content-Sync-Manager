# Content Sync Manager — Safe WordPress Content & Media Workflows

> **Portfolio project · WordPress/PHP · ACF · WooCommerce · content import/export · guarded media updates**

Content Sync Manager is an admin-only WordPress plugin for controlled export, review, import and recovery of website content and media. It is built for situations where teams need to update structured WordPress content efficiently without turning bulk editing into an uncontrolled write process.

**Built by:** [Andrew Baeten](https://github.com/Yolol100) · [Portfolio](https://andrewbaeten.nl)

## What problem it solves

Bulk content and media work becomes risky when updates affect ACF fields, WooCommerce products, images or content used in page builders. Content Sync Manager adds preview-before-write checks, capability boundaries, backups/recovery paths and explicit confirmation around higher-risk operations.

## Portfolio snapshot

| Area | What it demonstrates |
| --- | --- |
| WordPress | Posts, pages, products and supported custom post types |
| Structured content | Dynamic ACF field detection and validated import/export |
| WooCommerce | Product content support within controlled WordPress workflows |
| Media | Metadata context, image usage detection and guarded filename changes |
| Safety | Preview binding, validation, permission checks, backups and fail-closed behaviour |
| Delivery | Runtime matrices, Plugin Check, WPCS/PHPCS, Composer audit and deterministic release builds |

For GitHub-based conflict-driven content review, see the separate [Elementorconnector](https://github.com/Yolol100/Elementorconnector) project.

## Installation

1. Upload de pluginmap of ZIP via WordPress.
2. Activeer de plugin bij voorkeur eerst op staging.
3. Open Pagina's, Berichten, Producten, een ondersteund custom post type of Media > Bibliotheek in de admin.
4. Gebruik de Content Sync-toolbar of de AI-afbeeldingsknoppen in de Media Bibliotheek.

## AI-afbeeldingscontext

In Media > Bibliotheek werkt `AI afbeeldingen export` in zowel lijst- als rasterweergave. De export bevat per geselecteerde afbeelding de bestaande media-metadata, WordPress-gebruikslocaties, paginacontext, exacte ACF-paden voor image/gallery/group/repeater/flexible-content waar die beschikbaar zijn, plus tijdelijke niet-gecropte previews van maximaal 512 px en 1024 px.

De tijdelijke previews worden in een aparte uploads-submap gemaakt en automatisch opgeschoond. Wanneer een tijdelijke preview niet kan worden gemaakt, wordt alleen een bestaande WordPress-resize gebruikt als die aantoonbaar niet gecropt is en dezelfde beeldverhouding als het origineel behoudt. Er worden geen externe AI- of trackingcalls vanuit de plugin gedaan.

ChatGPT kan in de export alleen de waarden `new_filename`, `title`, `alt`, `caption` en `description` in de JSON-regel onder `MEDIA IMPORT` aanpassen. JSON voorkomt dat gewone metadataregels zoals `Title:` of `EINDE MEDIA IMPORT` het importformaat kunnen breken. Oude 1.2.62-labelblokken blijven voor backwards compatibility importeerbaar.

Daarna kan hetzelfde TXT-bestand via `AI data importeren` worden gecontroleerd en teruggeschreven. De import vereist dezelfde nonce/capabilitygrens als Content Sync, een preview-hash van exact dezelfde TXT-inhoud en een expliciete bevestiging. Bestandsnaamwijzigingen hergebruiken de bestaande veilige media-renamefunctie. Een fysieke hernoeming wordt fail-closed geblokkeerd als de gebruiksscan onvolledig is, als een vaste media-URL in private/buildermetadata zoals Elementor `_elementor_data` wordt gevonden, als een niet-ondersteunde opslaglocatie wordt gebruikt of als de huidige gebruiker niet iedere betrokken gebruikspagina mag bewerken. Veilige metadatawijzigingen kunnen dan nog wel doorgaan. Interne Content Sync-archiefmeta zoals `_dca_tb_backups` telt bewust niet als actieve gebruikslocatie.

## Veilig gebruik

- Test eerst op staging.
- Maak vooraf een database- en uploads-back-up.
- Gebruik altijd eerst `Controleer bestand` voordat je de normale Content Sync-import uitvoert.
- De AI-media-import voert zelf eerst een server-side controle uit en bindt de uitvoering aan exact dezelfde TXT-inhoud.
- Een import-run wordt server-side geblokkeerd wanneer de TXT-inhoud niet exact overeenkomt met de laatst gecontroleerde preview van dezelfde gebruiker.
- Media hernoemen staat standaard aan via `DCA_TB_ALLOW_MEDIA_FILE_RENAME` en blijft achter de bestaande veiligheidschecks voor extensie, MIME-type, uploads-pad, doelbestand, gebruikslocaties, rechten en back-up.

## Vereisten

- WordPress 6.2+
- PHP 7.4+
- ACF 6.8.9 voor pagina-, product- en custom-post-typevelden wanneer die via ACF worden beheerd
- Voor WooCommerce-producten: WordPress 6.9+ en WooCommerce 11.0.1

De releasegate bewaakt de laagste gedeclareerde combinatie WordPress 6.2.11/PHP 7.4/ACF 6.8.9, de bestaande WooCommerce-baseline WordPress 7.0.4/PHP 8.3/ACF 6.8.9/WooCommerce 11.0.1 en WordPress 7.1 op PHP 8.3 plus PHP 8.5. De WordPress 7.1/PHP 8.3-lane bevat eveneens WooCommerce 11.0.1. In iedere omgeving wordt de gebouwde ZIP schoon geïnstalleerd en geforceerd bijgewerkt. Daarna worden export, preview, import, importlog en herstel op echte WordPress-content uitgevoerd; de WooCommerce-matrices testen daarnaast een product en de AI-media-export/import met fysieke bestandsnaamwijziging. De AI-media hardeningtest controleert bovendien builder/private metadata, gebruikspagina-rechten, JSON round-trip met delimiterteksten en het weigeren van gecropte previewfallbacks. Plugin Check draait aanvullend in de stabiele WordPress 7.0.4/PHP 8.3-lane, terwijl WPCS/PHPCS en Composer audit in de quality gate draaien.

Bekende testbeperking: WooCommerce 11.0.1 schrijft tijdens de WP-CLI-runtime één `_load_textdomain_just_in_time`-notice voor zijn eigen `woocommerce`-tekstdomein. De gate staat alleen die exact herkenbare upstreammelding toe en faalt bij iedere andere notice, warning, fatal of debugregel. De Content Sync-flows zelf moeten zonder eigen debugmelding slagen.

Wanneer ACF niet actief of niet volledig beschikbaar is, toont de plugin in de pagina-/productlijst een admin-waarschuwing. Imports met ACF-velden worden dan server-side geblokkeerd; berichtimports zonder ACF blijven bruikbaar. De Media Bibliotheek-export blijft bruikbaar voor normale WordPress-afbeeldingen, maar exacte ACF-paden zijn dan niet beschikbaar.

## Configuratie

Deze constants kunnen vóór het laden van de plugin worden gezet:

```php
define('DCA_TB_ALLOW_MEDIA_FILE_RENAME', true); // standaard aan
define('DCA_TB_MAX_IMPORT_PAGES', 50);
define('DCA_TB_MAX_IMPORT_BYTES', 5242880);
define('DCA_TB_IMPORT_PREVIEW_TTL', 20 * MINUTE_IN_SECONDS);
define('DCA_TB_OVERWRITE_EXISTING_MEDIA', false);
define('DCA_TB_OVERWRITE_EXISTING_TEXT', true);
define('DCA_TB_OVERWRITE_EXISTING_TITLE', false);
define('DCA_TB_AI_IMAGE_CONTEXT_PREVIEW_MAX', 512);
define('DCA_TB_AI_IMAGE_CONTEXT_DETAIL_PREVIEW_MAX', 1024);
define('DCA_TB_AI_IMAGE_CONTEXT_USAGE_SCAN_MAX_POSTS', 2000);
```

## ACF-velden

Pagina-, product- en custom-post-type-export gebruikt dynamische ACF-detectie. De plugin exporteert alleen velden die ACF op het betreffende item detecteert en importeert alleen velden die op het doelitem ook door ACF bestaan. Oude vaste ACF-layouts zoals hoofdtekst/titel_1/usp_1 worden niet meer teruggeschreven.

Voor AI-afbeeldingscontext wordt aanvullend de raw ACF-structuur doorlopen zodat een afbeelding bijvoorbeeld als `acf:gallery[1]`, `acf:items[0].image` of `acf:flex[0]{hero}.image` kan worden gekoppeld. Deze paden zijn context voor analyse; de bestaande ACF-importcontracten worden er niet door vervangen.

## Let op bij oude snippets/plugins

Zet oude Code Snippets/WPCode-versies of oude pluginvarianten eerst uit voordat je deze versie activeert. De plugin blokkeert laden wanneer oude functies met dezelfde namen al actief zijn.

## Versie

1.2.63

## Releaseproces

1. Laat de quality gate en de geautomatiseerde schone WordPress-runtimegate slagen en test de plugin-ZIP daarna nog op een representatieve staginginstallatie met een back-up.
2. Controleer dat de versie in de pluginheader, `DCA_TB_VERSION` en de `Stable tag` gelijk is.
3. Maak pas daarna de bijpassende tag, bijvoorbeeld `v1.2.63`.
4. De tagworkflow bouwt de ZIP tweemaal, vergelijkt de SHA-256-checksums en maakt een **conceptrelease** met ZIP en checksum.
5. Publiceer het concept pas nadat export, preview, import en herstel in de ondersteunde WordPress/PHP-matrix zijn gecontroleerd en de Media Bibliotheek-UI op staging handmatig is bekeken.

Lokaal kan hetzelfde runtimepakket met Python 3 worden gebouwd:

```shell
python scripts/build_release.py
```

## Changelog

### 1.2.63

- Compatibility: WordPress 7.1-runtimecoverage toegevoegd op PHP 8.3 en 8.5 zonder de bestaande minimum- en WooCommerce-baselines te verwijderen.
- Quality: officiële Plugin Check-validatie, WPCS/PHPCS en Composer dependency-audit toegevoegd aan CI.
- I18n: de twee admin-JavaScriptbestanden gebruiken `wp-i18n` en `wp_set_script_translations()` voor gebruikersgerichte UI-tekst.
- Accessibility: bestaande dialog-, live-region- en focuscontracten zijn als regressiegate vastgelegd; browser/screenreader blijft een aparte stagingtest.

### 1.2.62

- AI Media: exportknoppen werken in Media Bibliotheek lijst- en rasterweergave en lezen alleen de geselecteerde afbeeldingen.
- Preview: tijdelijke 512 px- en 1024 px-previews worden zonder onnodige crop gemaakt, met automatische opschoning en fail-closed fallback; bestaande fallback-resizes moeten niet-gecropt zijn en de originele beeldverhouding behouden.
- Context: export bevat een WordPress-gebruiksscan en exacte ACF-paden voor gallery, group, repeater en flexible content waar beschikbaar; vaste URLs in private/buildermetadata worden als onveilige rename-locatie gemarkeerd.
- Round-trip: `MEDIA IMPORT` gebruikt een collision-safe JSON-regel; oude labelblokken blijven importeerbaar.
- Safety: import vereist exact-preview binding en bevestiging; fysieke hernoeming wordt ook geblokkeerd als niet alle gebruikspagina's door de huidige gebruiker bewerkt mogen worden.
- Quality: regressie- en runtimecoverage omvat AI-media-export/import, fysieke rename, geneste ACF-paden, Elementor/private metadata, permissiegrenzen, delimiterteksten en cropfallbacks.

Zie [CHANGELOG.md](CHANGELOG.md) voor de volledige versiehistorie.

## About the developer

I am **Andrew Baeten**, a Senior WordPress Developer & Web Designer with 10+ years of experience across **90+ WordPress projects**. I currently manage and regularly update **120+ websites and webshops**, including ongoing maintenance, quality checks and WordPress/WooCommerce improvements.

[Portfolio](https://andrewbaeten.nl) · [LinkedIn](https://www.linkedin.com/in/andrew-baeten-305a1478/) · [Email](mailto:info@andrewbaeten.nl)
