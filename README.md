# Content Sync Manager

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
- ACF voor pagina-, product- en custom-post-typevelden wanneer die via ACF worden beheerd

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

1.2.52

## Changelog

### 1.2.52
- Wijziging: knop `Export SEO-problemen`, de bijbehorende AJAX-route en de SEO-problemenexport naar `Yoast.txt` verwijderd.
- Fix: admin-JavaScript verwacht deze knop niet meer, zodat de toolbar blijft laden zonder SEO-exportknop.

### 1.2.51
- UI-fix: filterrij en paginering gebruiken nu dezelfde volledige rijbreedte, zodat de rechterkant niet meer verspringt door tablenav-uitlijning.

### 1.2.50
- UI-fix: rechterzijde van de top-tablenav strakker uitgelijnd; filterknop en paginering gebruiken vaste control-hoogtes en dezelfde rechterrand.

### 1.2.49

### 1.2.48

- Behoud: normale contentexport en contentimport blijven gescheiden van SEO-meta.

### 1.2.47

- Fix: via `dca_tb_supported_post_types` toegevoegde custom post types krijgen nu dezelfde Contentblok-kolom als pagina’s, berichten en producten.
- Fix: contentexport voegt het post type toe aan de TXT-header en import accepteert dynamische itemlabels, zodat ondersteunde custom post types niet naar pagina’s terugvallen.

### 1.2.46

- UI-fix: filterrij gebruikt de volledige beschikbare breedte en de tekst “van 2” in de paginering krijgt extra tussenruimte.

### 1.2.45

- UI-fix: paginering blijft onder de filterrij en staat rechts uitgelijnd, zonder overlap met de filterknop.
- Technisch: top-tablenav gebruikt een twee-regelige flex-layout: filters boven, itemtelling/paginering onder rechts.

### 1.2.44

- UI-fix: paginering rechts uitgelijnd onder filters.

### 1.2.43

- UI-fix: itemtelling en paginering verder uitgelijnd; pagingtekst verticaal gecentreerd.

### 1.2.42

- UI-fix: lijstfilters gebruiken geen horizontale scrollbar meer.

### 1.2.41

- UI-fix: bulkacties, filters en paginering in de overzichtslijst worden op desktop compacter weergegeven.

### 1.2.40

- UI: label `Content Sync:` uit de vaste admin-toolbar verwijderd; de export/importknoppen blijven ongewijzigd.
- UI: single-item modal toont nu direct de paginatitel zonder `Content Sync:`-prefix.

### 1.2.39

- Wijziging: knop en rapport hernoemd naar `Export SEO-meta & scores`, zodat duidelijk is dat het om opgeslagen SEO-meta en score-snapshots gaat.
- Fix: ongebruikte SEO-importwrite-logica verwijderd uit de actieve workflow; contentimport schrijft geen Yoast/Rank Math postmeta.
- Verbetering: ondersteunde post types zijn uitbreidbaar via filter `dca_tb_supported_post_types`.
- Verbetering: SEO-export bevat nu expliciet post type en titel per item en meldt wanneer geen SEO-provider/meta gevonden is.

### 1.2.38
- Wijziging: normale contentexport bevat standaard geen `SEO META`-blok meer.
- Wijziging: normale contentimport schrijft standaard geen Yoast/Rank Math SEO-meta meer terug.
- Behoud: oude TXT-bestanden met `SEO META` of `YOAST SEO` worden nog wel gevalideerd zodat dubbele/ongeldige secties zichtbaar blijven.

### 1.2.37
- Feature: nieuw `SEO META`-blok voor Yoast en Rank Math met provider, SEO title, meta description, focus keyphrase, canonical, robots, social velden en score-snapshots.
- Backwards compatibility: oude exports met `YOAST SEO` blijven importeerbaar.
- Import: SEO-velden worden naar de actieve provider gemapt; bij Yoast én Rank Math tegelijk wordt de import geblokkeerd tenzij de exportprovider eenduidig is.

### 1.2.36
- Fix: importmatch op ID accepteert een leeg URL-veld niet meer wanneer de titel afwijkt.
- Packaging: release-ZIP gebruikt opnieuw de runtime-map `content-sync-manager`.

### 1.2.35
- Media-rename aangescherpt: vul bij `Nieuwe bestandsnaam:` alleen de nieuwe naam in, zonder extensie. De bestaande extensie blijft behouden.
- URL-vervangingen na media-hernoemen nemen nu ook gegenereerde afbeeldingsformaten mee wanneer WordPress nieuwe metadata genereert.


## 1.2.34
- Releasehygiëne: runtime-map, README-versie en request-inputverwerking aangescherpt.
- ACF-detectie werkt via actieve veldgroepen per pagina/product en blijft veldkey-first importeren.

### 1.2.26

- Server-side import-previewverificatie toegevoegd: bulk/import-run vereist nu een recente preview-hash van exact dezelfde TXT-inhoud.
- Client-side import controleert nu ook de maximale bestandsgrootte voordat het TXT-bestand wordt gelezen.
- ACF-afhankelijkheid zichtbaarder gemaakt met een admin-waarschuwing op pagina-/productlijsten wanneer ACF ontbreekt.
- Textdomain laden toegevoegd en statische PHP-adminmeldingen verder voorbereid op vertaling.
- Importwaarschuwingen en confirmatieteksten aangescherpt voor content-, SEO-, ACF- en mediawijzigingen.
- Media-bestandsnaam hernoemen is bewust niet aangepast en blijft standaard aan.

### 1.2.25

- Media-bestandsnaam wijzigen staat standaard aan via `DCA_TB_ALLOW_MEDIA_FILE_RENAME`.


## 1.2.28 hotfix

Lege ACF-tekstwaarden uit een TXT-export worden niet meer als overschrijfwaarde opgeslagen. Dit voorkomt dat bestaande ACF-teksten leeg raken wanneer alleen samenvattingen zijn aangepast. De toolbar bevat daarnaast een herstelactie voor de laatste import op basis van automatische back-ups.


## 1.2.32

- Fix: ACF-export gebruikt weer geladen veldwaarden, zodat tekstvelden, titels, minititels en FAQ-velden niet leeg in de TXT-export komen.
- Lege importwaarden blijven beschermd en overschrijven bestaande tekst niet.

## 1.2.31
- Admin filterbalk opnieuw opgebouwd zodat paginering niet over filters valt.
