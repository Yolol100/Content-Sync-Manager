# Content Sync Manager

> **Portfoliostatus:** Actief ondersteunend · zelfstandig WordPress contentproduct

**Rol in het portfolio:** Content Sync Manager is een zelfstandige admin-only WordPress-plugin voor gecontroleerde TXT-export, preview, import en herstel. Voor GitHub-gebaseerde, conflictgestuurde contentreview is [Elementorconnector](https://github.com/Yolol100/Elementorconnector) het aparte bridgeproduct; beide producten behouden hun eigen veiligheids- en releasegrenzen.

Admin-only mini-plugin voor TXT export/import van berichten, pagina’s, producten en ondersteunde custom post types met gedetecteerde ACF-velden, samenvattingen, uitgelichte afbeeldingen en media-metadata.

## Installatie

1. Upload de pluginmap of ZIP via WordPress.
2. Activeer de plugin bij voorkeur eerst op staging.
3. Open Pagina's, Berichten, Producten of een ondersteund custom post type in de admin.
4. Gebruik de Content Sync-toolbar onderaan het overzicht.

## Veilig gebruik

- Test eerst op staging.
- Maak vooraf een database- en uploads-back-up.
- Gebruik altijd eerst `Controleer bestand` voordat je importeert.
- Een import-run wordt server-side geblokkeerd wanneer de TXT-inhoud niet exact overeenkomt met de laatst gecontroleerde preview van dezelfde gebruiker.
- Media hernoemen staat standaard aan via `DCA_TB_ALLOW_MEDIA_FILE_RENAME`; dit is bewust niet aangepast in versie 1.2.37.

## Vereisten

- WordPress 6.2+
- PHP 7.4+
- ACF 6.8.9 voor pagina-, product- en custom-post-typevelden wanneer die via ACF worden beheerd
- Voor WooCommerce-producten: WordPress 6.9+ en WooCommerce 11.0.1

De releasegate test zowel WordPress 6.2.11/PHP 7.4/ACF 6.8.9 als de gezamenlijk ondersteunde ecosystemcombinatie WordPress 7.0.4/PHP 8.3/ACF 6.8.9/WooCommerce 11.0.1. In beide omgevingen wordt de gebouwde ZIP schoon geïnstalleerd en geforceerd bijgewerkt. Daarna worden export, preview, import, importlog en herstel op echte WordPress-content uitgevoerd; de ecosystemmatrix test daarnaast een WooCommerce-product.

Wanneer ACF niet actief of niet volledig beschikbaar is, toont de plugin in de pagina-/productlijst een admin-waarschuwing. Imports met ACF-velden worden dan server-side geblokkeerd; berichtimports zonder ACF blijven bruikbaar.

## Configuratie

Deze constants kunnen vóór het laden van de plugin worden gezet:

```php
define('DCA_TB_ALLOW_MEDIA_FILE_RENAME', true); // standaard aan; bewust ongewijzigd
define('DCA_TB_MAX_IMPORT_PAGES', 50);
define('DCA_TB_MAX_IMPORT_BYTES', 5242880);
define('DCA_TB_IMPORT_PREVIEW_TTL', 20 * MINUTE_IN_SECONDS);
define('DCA_TB_OVERWRITE_EXISTING_MEDIA', false);
define('DCA_TB_OVERWRITE_EXISTING_TEXT', true);
define('DCA_TB_OVERWRITE_EXISTING_TITLE', false);
```

## ACF-velden

Pagina-, product- en custom-post-type-export gebruikt dynamische ACF-detectie. De plugin exporteert alleen velden die ACF op het betreffende item detecteert en importeert alleen velden die op het doelitem ook door ACF bestaan. Oude vaste ACF-layouts zoals hoofdtekst/titel_1/usp_1 worden niet meer teruggeschreven.

## Let op bij oude snippets/plugins

Zet oude Code Snippets/WPCode-versies of oude pluginvarianten eerst uit voordat je deze versie activeert. De plugin blokkeert laden wanneer oude functies met dezelfde namen al actief zijn.

## Versie

1.2.61

## Releaseproces

1. Laat de quality gate en de geautomatiseerde schone WordPress-runtimegate slagen en test de plugin-ZIP daarna nog op een representatieve staginginstallatie met een back-up.
2. Controleer dat de versie in de pluginheader, `DCA_TB_VERSION` en de `Stable tag` gelijk is.
3. Maak pas daarna de bijpassende tag, bijvoorbeeld `v1.2.61`.
4. De tagworkflow bouwt de ZIP tweemaal, vergelijkt de SHA-256-checksums en maakt een **conceptrelease** met ZIP en checksum.
5. Publiceer het concept pas nadat export, preview, import en herstel in de ondersteunde WordPress/PHP-matrix zijn gecontroleerd.

Lokaal kan hetzelfde runtimepakket met Python 3 worden gebouwd:

```shell
python scripts/build_release.py
```

## Changelog

### 1.2.61

- Fix: selectie voor TXT-export leest de WordPress `post[]`/`delete_tags[]`-vakjes direct en is niet meer afhankelijk van een `th.check-column`-wrapper.
- Cache: pluginversie verhoogd zodat de aangepaste admin-JavaScript direct met een nieuwe assetversie wordt geladen.
- Quality: regressiecheck aangescherpt voor robuuste selectie van berichten, producten, pagina's en termen.

Zie [CHANGELOG.md](CHANGELOG.md) voor de volledige versiehistorie.
