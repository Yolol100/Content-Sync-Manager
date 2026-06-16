=== Content Sync Manager ===
Contributors: webactueel
Tags: admin, acf, yoast, import
Requires at least: 6.2
Tested up to: 6.2
Requires PHP: 7.4
Stable tag: 1.2.34
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

= 1.2.34 =
* Verbetering: ACF-export detecteert velden nu dynamisch via de actieve ACF-veldgroepen van de pagina of het product, niet via vaste veldnamen.
* Verbetering: waarden worden per veldkey opgehaald en import matcht eerst op veldkey, daarna op veldnaam. Hierdoor werken tekst_1/2/3/4, titels, minititels, FAQ-velden en andere ACF-tekstvelden wanneer ze op de site bestaan.

= 1.2.32 =
* Fix: ACF-export laadt nu de echte veldwaarden mee voor tekst_1/2/3/4, titelvelden, mintitels, FAQ-velden en andere ACF-velden.
* Behoud: lege importwaarden wissen bestaande ACF-tekstvelden niet.

= 1.2.31 =
* Admin filterbalk opnieuw opgebouwd zodat paginering niet meer over de filterknoppen valt.


= 1.2.28 =
* Hotfix: lege ACF-tekstvelden uit een export overschrijven bestaande teksten niet meer. Samenvatting en gevulde Yoast/ACF-tekstwaarden worden nog wel bijgewerkt.
* Hotfix: dubbele UITGELICHTE AFBEELDING-sectie uit pagina-export verwijderd.
* Herstelknop toegevoegd om de laatste import via automatische pagina-back-ups terug te zetten.

= 1.2.26 =
* Server-side import-previewverificatie toegevoegd: import-run vereist nu een recente preview-hash van exact dezelfde TXT-inhoud.
* Client-side bestandsgroottecontrole toegevoegd voor TXT-imports.
* ACF-afhankelijkheid zichtbaarder gemaakt met een admin-waarschuwing op pagina-/productlijsten wanneer ACF ontbreekt.
* Textdomain laden toegevoegd en importwaarschuwingen/confirmatieteksten aangescherpt.
* Media-bestandsnaam hernoemen is bewust niet aangepast en blijft standaard aan.

= 1.2.25 =
* Media-bestandsnaam wijzigen staat nu standaard aan via DCA_TB_ALLOW_MEDIA_FILE_RENAME. Bestaande veiligheidschecks voor extensie, MIME-type, uploads-pad, rechten, doelbestand en backups blijven actief.

= 1.2.24 =
* Hotfix: ontbrekende ACF-helperfuncties teruggezet zodat AJAX-export/import weer geldige JSON kan teruggeven.
* Featured-imageblok uitgebreid met bestandsnaam, nieuwe bestandsnaam, title, alt, caption en description.
* MEDIA-blok toont nu de bron per afbeelding, zoals featured_image, post_content of acf:veldnaam.
* ACF-media-detectie loopt nu recursief door image, file, gallery, group, repeater en flexible_content velden die ACF op dat moment detecteert.
* Media-metadata-update gecentraliseerd zodat title, alt, caption en description consistent worden opgeslagen.

= 1.2.21 =
* Na import, bulk opslaan of single save blijft de adminlijst op dezelfde filterstand staan.
* Automatische redirect naar Contentblok: nog te doen vandaag verwijderd.

= 1.2.20 =
* Packaging: pluginbestanden staan nu in de map `content-sync-manager` voor voorspelbaarder overschrijven bij upload.
* Cache-busting: pluginversie verhoogd zodat aangepaste admin-JavaScript niet door browsercache blijft hangen.

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
