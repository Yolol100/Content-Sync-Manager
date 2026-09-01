# Content Sync Manager changelog

### 1.2.62
- AI Media: exportknoppen werken in Media Bibliotheek lijst- en rasterweergave en lezen alleen de geselecteerde afbeeldingen.
- Preview: tijdelijke 512 px- en 1024 px-previews worden zonder onnodige crop gemaakt, met automatische opschoning en fail-closed fallback.
- Context: export bevat een WordPress-gebruiksscan en exacte ACF-paden voor gallery, group, repeater en flexible content waar beschikbaar.
- Round-trip: `MEDIA IMPORT`-blokken kunnen na AI-bewerking via `AI data importeren` veilig worden gecontroleerd en teruggeschreven naar bestandsnaam, title, alt, caption en description.
- Safety: import vereist exact-preview binding en expliciete bevestiging; fysieke hernoemingen worden geblokkeerd bij een onvolledige scan of gebruik in niet-ondersteunde contenttypes.
- Quality: regressie- en runtimecoverage uitgebreid met AI-media-export/import, fysieke rename en geneste ACF-padcontrole.

### 1.2.61
- Fix: selectie voor TXT-export leest de WordPress `post[]`/`delete_tags[]`-vakjes direct en is niet meer afhankelijk van een `th.check-column`-wrapper.
- Cache: pluginversie verhoogd zodat de aangepaste admin-JavaScript direct met een nieuwe assetversie wordt geladen.
- Quality: regressiecheck aangescherpt voor robuuste selectie van berichten, producten, pagina's en termen.

### 1.2.60
- UI: knop `Selecteer alles` toegevoegd aan de Content Sync-toolbar voor berichten, pagina's, producten en ondersteunde termen.
- Export: `Export selectie .txt` gebruikt direct alle via de lijst aangevinkte items; de nieuwe selectieknop zet ook de WordPress select-all-vakjes consistent aan.
- Quality: regressiechecks toegevoegd voor de selectieknop, de selectie-handler en de bestaande directe bulkexport.

### 1.2.59
- Fix: admin-assets worden voor gebruikers zonder managerrechten geblokkeerd, zodat geen zichtbare maar inactieve exportknoppen ontstaan wanneer de server-side modals terecht ontbreken.
- Packaging: fout geplaatste rootbestanden `admin.js` en `manager.php` verwijderd; de canonieke bestanden blijven in `assets/` en `includes/`.
- Quality: regressietest en GitHub Actions-gate toegevoegd voor PHP-syntax, JavaScript-syntax, exportcontracten, versiepariteit en package-hygiene.

### 1.2.58
- Packagingfix: hoofdpluginbestand hersteld met geldige WordPress-pluginheader, bootstrapconstants en include naar `includes/manager.php`.
- Veiligheidsfix: laden wordt geblokkeerd met adminmelding wanneer een oude snippet of pluginversie met dezelfde functies al actief is.

### 1.2.57
- Yoast metabeschrijving voor categorieën/productcategorieën schoon opgeslagen via termmeta én Yoast taxonomy-meta fallback.
- Kleine opschoning in admin body-class en import/export-resultaatvelden.

### 1.2.56
- Export/import uitgebreid met Yoast metabeschrijving voor berichten, pagina’s, producten, categorieën en productcategorieën.
- Tekstblokbeheer toegevoegd voor categorieën en productcategorieën.

### 1.2.55
- Controlefix: ongebruikte server-side preload-AJAX-route verwijderd zodat de refactorclaim klopt met de code.
- Kleine UI-tekst opgeschoond: selectie-toast is netter en consistenter geformuleerd.
- Overbodige trailing whitespace uit `includes/manager.php` verwijderd.

### 1.2.54
- Admin-JavaScript beperkt gerefactord: betere leesbaarheid, geen extra buildstap en geen parallelle logica.
- Toolbar wordt altijd opnieuw in de canonieke knopvolgorde opgebouwd.
- Import-/bulkpreview gebruikt nu DOM-opbouw in plaats van HTML-stringopbouw met dynamische data.
- Ongebruikte preload-code en overbodige/Engelstalige interne comments opgeschoond.

### 1.2.53
- UI: vaste toolbar in logischere volgorde gezet: selectie-acties, bulkeditor, import, herstel en filter.
- Fix: incomplete bestaande toolbar wordt eerst verwijderd voordat de toolbar opnieuw wordt opgebouwd.
- Fix: adminmodalen kregen ontbrekende screen-reader labels en live statusmeldingen.
- Fix: admin screen/post-type waarden worden veiliger genormaliseerd voordat admin-asset URLs worden opgebouwd.

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
