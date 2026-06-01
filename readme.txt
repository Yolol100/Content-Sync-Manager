=== Content Sync Manager ===
Contributors: webactueel
Tags: admin, acf, yoast, import
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.12
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Admin-only TXT import/export voor gedetecteerde ACF-velden, Yoast SEO en media-metadata.

== Description ==

Deze plugin draait alleen in de WordPress-admin en voert geen externe tracking, externe asset-loading of externe API-calls uit.

Deze private plugin voegt een compacte admin bulkeditor toe voor pagina's, berichten en producten. De plugin is bedoeld voor gecontroleerde staging-imports en detecteert bestaande ACF-velden per pagina/product en werkt met Yoast meta en lokale WordPress-afbeeldingen.

== Installation ==

1. Zet oude Code Snippets/WPCode-versies uit.
2. Upload de pluginmap of ZIP via WordPress.
3. Activeer de plugin op staging.
4. Test eerst export, preview en daarna import.

== Changelog ==

= 1.2.12 =
* Grote gerichte bugfix-release voor admin-functies die stil konden falen.
* Toolbar-opbouw robuuster gemaakt als bestaande markup of conflicterende toolbar aanwezig is.
* Bulk/import-preview blokkeert opslaan als de AJAX-response geen geldige items bevat.
* ACF image/file/gallery-export gebruikt echte enters in plaats van letterlijke \n-tekst.
* ACF-detectie gebruikt nu primair onbewerkte waarden, met fallback naar geformatteerde waarden.
* Resolver voorkomt foutieve titelmatch bij lege titel en blijft backwards-compatible voor ID-only imports.

= 1.2.6 =
* Beperkt admin-UI en filters tot berichten, pagina’s en producten.
* Producten toegevoegd aan import/export.
* Standaardtemplate-filter sluit Elementor Canvas en Elementor Full Width uit.
* Importlogrechten afgestemd op de managerrechten.

= 1.2.4 =
* Extra lijstfilter toegevoegd voor standaard/ACF-pagina's zonder Elementor-builderdata.

= 1.2.3 =
* Verbetering: ACF image/file-velden worden compact en leesbaar geëxporteerd met Attachment ID, URL, alt, titel, caption en description in plaats van volledige ACF image-arrays.
* Verbetering: ACF gallery-velden worden importvriendelijk geëxporteerd als attachment-ID-lijst.
* Fix: import van ACF image/file-velden accepteert de nieuwe nette layout en schrijft het attachment-ID terug naar ACF.

= 1.0.8 =
* Extra 10/10-releasecheck uitgevoerd op foutpaden, admin-toegang en WordPress.org/Plugin Check-aandachtspunten.
* Uitgelichte-afbeelding-sectie wordt nu strikt gevalideerd voordat thumbnail of alt-tekst wordt gewijzigd.
* Admin-modal markup wordt alleen nog geladen voor gebruikers die de manager mogen gebruiken.

= 1.0.7 =
* Diepere release-check uitgevoerd op WordPress.org-richtlijnen, Plugin Check-aandachtspunten, security-boundaries en rollbackgedrag.
* Single-item opslaan verwerkt nu ook media-metadata en media-bestandsnaamwijzigingen.
* Media-rollback hernoemt het fysieke uploadbestand veilig terug wanneer het vorige pad beschikbaar is.
* Uitgelichte-afbeelding-alt wordt alleen aangepast wanneer het Alt text-label aanwezig is.
* Externe Update URI-header verwijderd om WordPress.org-releasehygiëne minder ambigu te maken.

= 1.0.6 =
* Release-check uitgevoerd op syntax, pakketinhoud, admin AJAX-beveiliging en WordPress.org/GitHub-richtlijnen.
* Meta URL-vervanging bij media-hernoemen robuuster gemaakt voor geserialiseerde meta.
* Media-export begrensd op dezelfde limiet als import zodat export/import consistenter blijft.
* Uitgelichte-afbeelding-alt kan nu ook bewust worden leeggemaakt.
* Dubbele interne statusregel verwijderd en LICENSE-bestand toegevoegd.

= 1.0.5 =
* Samenvatting toegevoegd aan TXT-export/import voor berichten en pagina’s.
* Uitgelichte afbeelding expliciet toegevoegd aan TXT-export/import via Attachment ID of URL.

= 1.0.4 =
* Zelfde pluginmap en hoofdpluginbestand behouden zodat uploaden over 1.0.x de oude plugin vervangt.
* Server-side limiet voor export/preload toegevoegd.
* TXT-importgrootte begrensd.
* Text domain consistent gemaakt.
* Striktere uploads-mapcontrole bij media hernoemen.
* Pagina-ID is leidend wanneer ID, URL en titel niet overeenkomen.

= 1.0.2 =
* Striktere pagina-resolutie op URL/titel om verkeerde ID-koppelingen te voorkomen.
* Media-identiteitscontrole op huidige URL en bestandsnaam.
* UTF-8 BOM-tolerantie voor TXT-import.
* Guard tegen dubbele oude snippet/pluginfunctie.

= 1.0.1 =
* Mini-pluginstructuur met losse admin assets.
