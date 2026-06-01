# Content Sync Manager

Admin-only mini-plugin voor TXT export/import van berichten, pagina’s en producten met gedetecteerde ACF-velden, Yoast SEO velden, samenvattingen, uitgelichte afbeeldingen en media-metadata.

## Installatie

1. Upload de map `content-sync-manager` naar `/wp-content/plugins/`.
2. Activeer de plugin in WordPress.
3. Open Pagina's, Berichten of Producten in de admin.
4. Gebruik de SEO-toolbar onderaan het overzicht.

## Veilig gebruik

- Test eerst op staging.
- Maak vooraf een database- en uploads-back-up.
- Gebruik altijd eerst `Controleer bestand` voordat je importeert.
- Media hernoemen staat standaard uit via `DCA_TB_ALLOW_MEDIA_FILE_RENAME`; zet dit alleen bewust aan op staging of na acceptatie.

## Vereisten

- WordPress 6.2+
- PHP 7.4+
- ACF voor pagina- en productvelden wanneer die via ACF worden beheerd
- Yoast SEO optioneel voor SEO title/meta description


## Versie

1.2.12

## ACF-velden

Pagina-export gebruikt dynamische ACF-detectie. De plugin exporteert alleen velden die ACF op de betreffende pagina detecteert en importeert alleen velden die op de doelpagina ook door ACF bestaan. Oude vaste ACF-layouts zoals hoofdtekst/titel_1/usp_1 worden niet meer teruggeschreven.


## Let op bij hernoemen

Deze versie gebruikt de nieuwe pluginmap `content-sync-manager` en het nieuwe hoofdpluginbestand `content-sync-manager.php`. Zet de oude plugin `dca-acf-tekstblok-manager` eerst uit voordat je deze versie activeert, zodat er geen dubbele managerfuncties actief zijn.


## 1.2.21

- Pluginbestanden verpakt in de map `content-sync-manager` voor voorspelbaarder overschrijven.
- Versie verhoogd voor cache-busting van aangepaste admin-JavaScript.

## 1.2.21

- Bestaande gekoppelde media blijft standaard behouden tijdens import.
- Nieuwe constante `DCA_TB_OVERWRITE_EXISTING_MEDIA` toegevoegd, standaard `false`.

## 1.2.21

- Bestaande titels, tekstvelden, samenvattingen en Yoast-teksten worden standaard niet overschreven.
- Nieuwe constants: `DCA_TB_OVERWRITE_EXISTING_TEXT` en `DCA_TB_OVERWRITE_EXISTING_TITLE`, beide standaard `false`.
- Import vult standaard alleen lege tekstvelden aan.
