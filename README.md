# Content Sync Manager

Admin-only mini-plugin voor TXT export/import van berichten, pagina’s en producten met gedetecteerde ACF-velden, Yoast SEO velden, samenvattingen, uitgelichte afbeeldingen en media-metadata.

## Installatie

1. Upload de pluginmap of ZIP via WordPress.
2. Activeer de plugin bij voorkeur eerst op staging.
3. Open Pagina's, Berichten of Producten in de admin.
4. Gebruik de SEO-toolbar onderaan het overzicht.

## Veilig gebruik

- Test eerst op staging.
- Maak vooraf een database- en uploads-back-up.
- Gebruik altijd eerst `Controleer bestand` voordat je importeert.
- Een import-run wordt server-side geblokkeerd wanneer de TXT-inhoud niet exact overeenkomt met de laatst gecontroleerde preview van dezelfde gebruiker.
- Media hernoemen staat standaard aan via `DCA_TB_ALLOW_MEDIA_FILE_RENAME`; dit is bewust niet aangepast in versie 1.2.26.

## Vereisten

- WordPress 6.2+
- PHP 7.4+
- ACF voor pagina- en productvelden wanneer die via ACF worden beheerd
- Yoast SEO optioneel voor SEO title/meta description

Wanneer ACF niet actief of niet volledig beschikbaar is, toont de plugin in de pagina-/productlijst een admin-waarschuwing. Imports met ACF-velden worden dan server-side geblokkeerd; berichtimports zonder ACF blijven bruikbaar.

## Configuratie

Deze constants kunnen vóór het laden van de plugin worden gezet:

```php
define('DCA_TB_ALLOW_MEDIA_FILE_RENAME', true); // standaard aan; bewust ongewijzigd
define('DCA_TB_MAX_IMPORT_PAGES', 50);
define('DCA_TB_MAX_IMPORT_BYTES', 5242880);
define('DCA_TB_IMPORT_PREVIEW_TTL', 20 * MINUTE_IN_SECONDS);
define('DCA_TB_OVERWRITE_EXISTING_MEDIA', false);
define('DCA_TB_OVERWRITE_EXISTING_TEXT', false);
define('DCA_TB_OVERWRITE_EXISTING_TITLE', false);
```

## ACF-velden

Pagina- en productexport gebruikt dynamische ACF-detectie. De plugin exporteert alleen velden die ACF op het betreffende item detecteert en importeert alleen velden die op het doelitem ook door ACF bestaan. Oude vaste ACF-layouts zoals hoofdtekst/titel_1/usp_1 worden niet meer teruggeschreven.

## Let op bij oude snippets/plugins

Zet oude Code Snippets/WPCode-versies of oude pluginvarianten eerst uit voordat je deze versie activeert. De plugin blokkeert laden wanneer oude functies met dezelfde namen al actief zijn.

## Versie

1.2.26

## Changelog

### 1.2.26

- Server-side import-previewverificatie toegevoegd: bulk/import-run vereist nu een recente preview-hash van exact dezelfde TXT-inhoud.
- Client-side import controleert nu ook de maximale bestandsgrootte voordat het TXT-bestand wordt gelezen.
- ACF-afhankelijkheid zichtbaarder gemaakt met een admin-waarschuwing op pagina-/productlijsten wanneer ACF ontbreekt.
- Textdomain laden toegevoegd en statische PHP-adminmeldingen verder voorbereid op vertaling.
- Importwaarschuwingen en confirmatieteksten aangescherpt voor content-, SEO-, ACF- en mediawijzigingen.
- Media-bestandsnaam hernoemen is bewust niet aangepast en blijft standaard aan.

### 1.2.25

- Media-bestandsnaam wijzigen staat standaard aan via `DCA_TB_ALLOW_MEDIA_FILE_RENAME`.
