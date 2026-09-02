#!/usr/bin/env python3
"""Apply the audited 2026 WordPress best-practice hardening changes."""

from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DOMAIN = "content-sync-manager"


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8", newline="\n")


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected exactly one match, found {count}")
    return text.replace(old, new, 1)


def wrap_literals(text: str, strings: list[str]) -> str:
    for value in strings:
        old = repr(value)
        new = f"__({old}, '{DOMAIN}')"
        if old in text:
            text = text.replace(old, new)
    return text


# Version and public metadata.
bootstrap = read("content-sync-manager.php")
bootstrap = replace_once(bootstrap, " * Version: 1.2.62", " * Version: 1.2.63", "plugin header")
bootstrap = replace_once(
    bootstrap,
    "define('DCA_TB_VERSION', '1.2.62');",
    "define('DCA_TB_VERSION', '1.2.63');",
    "version constant",
)
write("content-sync-manager.php", bootstrap)

runtime_smoke = read("tests/runtime-smoke.php")
runtime_smoke = runtime_smoke.replace("DCA_TB_VERSION === '1.2.62'", "DCA_TB_VERSION === '1.2.63'")
runtime_smoke = runtime_smoke.replace("Installed plugin version is not 1.2.62.", "Installed plugin version is not 1.2.63.")
write("tests/runtime-smoke.php", runtime_smoke)

readme = read("readme.txt")
readme = replace_once(readme, "Tested up to: 7.0", "Tested up to: 7.1", "readme tested up to")
readme = replace_once(readme, "Stable tag: 1.2.62", "Stable tag: 1.2.63", "readme stable tag")
readme_changelog = """= 1.2.63 =
* Compatibility: schone runtimegates toegevoegd voor WordPress 7.1 op PHP 8.3 en PHP 8.5, naast de bestaande minimum- en WooCommerce-matrix.
* Quality: officiële Plugin Check-validatie toegevoegd voor general, security, performance en accessibility checks.
* Standards: WordPress Coding Standards/PHPCS en Composer dependency-audit toegevoegd als vaste CI-gate met gepinde tooling.
* I18n: admin-JavaScript gebruikt nu WordPress wp-i18n en script translations in plaats van losse niet-lokaliseerbare UI-strings.
* Accessibility: regressiechecks bewaken dialogsemantiek, live statusmeldingen, keyboard-focusgedrag en de i18n-laag; echte browser-/screenreadercontrole blijft staging-first.

"""
readme = replace_once(readme, "= 1.2.62 =\n", readme_changelog + "= 1.2.62 =\n", "readme changelog")
write("readme.txt", readme)

readme_md = read("README.md")
readme_md = replace_once(readme_md, "\n1.2.62\n\n## Releaseproces", "\n1.2.63\n\n## Releaseproces", "README version")
readme_md = readme_md.replace("bijvoorbeeld `v1.2.62`", "bijvoorbeeld `v1.2.63`")
old_matrix = "De releasegate test zowel WordPress 6.2.11/PHP 7.4/ACF 6.8.9 als de gezamenlijk ondersteunde ecosystemcombinatie WordPress 7.0.4/PHP 8.3/ACF 6.8.9/WooCommerce 11.0.1. In beide omgevingen wordt de gebouwde ZIP schoon geïnstalleerd en geforceerd bijgewerkt. Daarna worden export, preview, import, importlog en herstel op echte WordPress-content uitgevoerd; de ecosystemmatrix test daarnaast een WooCommerce-product en de AI-media-export/import met fysieke bestandsnaamwijziging. De AI-media hardeningtest controleert bovendien builder/private metadata, gebruikspagina-rechten, JSON round-trip met delimiterteksten en het weigeren van gecropte previewfallbacks."
new_matrix = "De releasegate bewaakt de laagste gedeclareerde combinatie WordPress 6.2.11/PHP 7.4/ACF 6.8.9, de bestaande WooCommerce-baseline WordPress 7.0.4/PHP 8.3/ACF 6.8.9/WooCommerce 11.0.1 en WordPress 7.1 op PHP 8.3 plus PHP 8.5. De WordPress 7.1/PHP 8.3-lane bevat eveneens WooCommerce 11.0.1. In iedere omgeving wordt de gebouwde ZIP schoon geïnstalleerd en geforceerd bijgewerkt. Daarna worden export, preview, import, importlog en herstel op echte WordPress-content uitgevoerd; de WooCommerce-matrices testen daarnaast een product en de AI-media-export/import met fysieke bestandsnaamwijziging. De AI-media hardeningtest controleert bovendien builder/private metadata, gebruikspagina-rechten, JSON round-trip met delimiterteksten en het weigeren van gecropte previewfallbacks. Plugin Check draait aanvullend in de stabiele WordPress 7.0.4/PHP 8.3-lane, terwijl WPCS/PHPCS en Composer audit in de quality gate draaien."
readme_md = replace_once(readme_md, old_matrix, new_matrix, "README runtime matrix")
readme_md_changelog = """### 1.2.63

- Compatibility: WordPress 7.1-runtimecoverage toegevoegd op PHP 8.3 en 8.5 zonder de bestaande minimum- en WooCommerce-baselines te verwijderen.
- Quality: officiële Plugin Check-validatie, WPCS/PHPCS en Composer dependency-audit toegevoegd aan CI.
- I18n: de twee admin-JavaScriptbestanden gebruiken `wp-i18n` en `wp_set_script_translations()` voor gebruikersgerichte UI-tekst.
- Accessibility: bestaande dialog-, live-region- en focuscontracten zijn als regressiegate vastgelegd; browser/screenreader blijft een aparte stagingtest.

"""
readme_md = replace_once(readme_md, "### 1.2.62\n", readme_md_changelog + "### 1.2.62\n", "README changelog")
write("README.md", readme_md)

changelog = read("CHANGELOG.md")
changelog_entry = """### 1.2.63
- Compatibility: WordPress 7.1 toegevoegd aan de echte runtime-matrix op PHP 8.3 en 8.5; bestaande minimum- en WooCommerce-baselines blijven behouden.
- Quality: officiële Plugin Check-validatie, WPCS/PHPCS en `composer audit --locked` toegevoegd als CI-gates.
- I18n: gebruikersgerichte admin-JavaScripttekst gebruikt nu WordPress `wp-i18n` en script translations.
- Accessibility: regressiegates bewaken dialogsemantiek, live regions en focusgedrag; volledige WCAG-conformance blijft browser-/screenreaderbewijs vereisen.

"""
changelog = replace_once(changelog, "# Content Sync Manager changelog\n\n", "# Content Sync Manager changelog\n\n" + changelog_entry, "CHANGELOG header")
write("CHANGELOG.md", changelog)

# WordPress-native JavaScript translations.
manager = read("includes/manager.php")
manager = replace_once(
    manager,
    "        [],\n        DCA_TB_VERSION,\n        true\n    );\n\n    wp_add_inline_script(\n        'dca-tb-admin',",
    "        ['wp-i18n'],\n        DCA_TB_VERSION,\n        true\n    );\n\n    wp_set_script_translations('dca-tb-admin', 'content-sync-manager');\n\n    wp_add_inline_script(\n        'dca-tb-admin',",
    "admin script i18n dependency",
)
write("includes/manager.php", manager)

ai_module = read("includes/ai-image-context.php")
ai_module = replace_once(
    ai_module,
    "        [],\n        DCA_TB_VERSION . '-ai-image-3',\n        true\n    );\n\n    global $pagenow;",
    "        ['wp-i18n'],\n        DCA_TB_VERSION . '-ai-image-4',\n        true\n    );\n\n    wp_set_script_translations('dca-tb-ai-image-context', 'content-sync-manager');\n\n    global $pagenow;",
    "AI script i18n dependency",
)
write("includes/ai-image-context.php", ai_module)

admin_js = read("assets/admin.js")
admin_js = replace_once(
    admin_js,
    "    const dcaSettings = window.dcaTbSettings || {};\n",
    "    const dcaSettings = window.dcaTbSettings || {};\n    const { __, sprintf } = window.wp.i18n;\n",
    "admin JS i18n bootstrap",
)

# Prefer complete sprintf messages over fragment translation for dynamic user-facing strings.
dynamic_admin = {
    "showToast('Content Sync: ' + selectedIds().length + ' geselecteerd');": "showToast(sprintf(__('Content Sync: %d geselecteerd', 'content-sync-manager'), selectedIds().length));",
    "return importable + ' importeerbaar, ' + blocked + ' geblokkeerd.';": "return sprintf(__('%1$d importeerbaar, %2$d geblokkeerd.', 'content-sync-manager'), importable, blocked);",
    "const target = rowItem.target_title ? rowItem.target_title + ' (#' + targetId + ')' : 'Niet gevonden';": "const target = rowItem.target_title ? sprintf(__('%1$s (#%2$d)', 'content-sync-manager'), rowItem.target_title, targetId) : __('Niet gevonden', 'content-sync-manager');",
    "sourceCell.appendChild(document.createTextNode('ID: ' + (rowItem.source_id || '')));": "sourceCell.appendChild(document.createTextNode(sprintf(__('ID: %s', 'content-sync-manager'), rowItem.source_id || '')));",
    "if (ids.length > 50 && !confirm('Je hebt ' + ids.length + ' items geselecteerd. Dit kan zwaar zijn voor de server. Toch doorgaan?')) {": "if (ids.length > 50 && !confirm(sprintf(__('Je hebt %d items geselecteerd. Dit kan zwaar zijn voor de server. Toch doorgaan?', 'content-sync-manager'), ids.length))) {",
    "reject('Bestand is te groot. Maximaal toegestaan: ' + Math.round(Number(dcaSettings.maxImportBytes) / 1024 / 1024) + ' MB.');": "reject(sprintf(__('Bestand is te groot. Maximaal toegestaan: %d MB.', 'content-sync-manager'), Math.round(Number(dcaSettings.maxImportBytes) / 1024 / 1024)));",
}
for old, new in dynamic_admin.items():
    admin_js = replace_once(admin_js, old, new, f"admin dynamic i18n: {old[:32]}")

admin_strings = [
    "Kopieer selectie", "Export selectie .txt", "Bulkeditor", "Selecteer alles", "Deselecteer alles", "Import .txt", "Herstel laatste import", "Filter",
    "Content Sync Manager: admin UI niet volledig geladen. Herlaad de adminpagina.",
    "Je hebt wijzigingen die nog niet zijn opgeslagen. Toch sluiten?",
    "Server gaf geen geldige JSON terug. HTTP ", "lege response", "AJAX-verzoek mislukt. HTTP ", "Lege AJAX-response.", "AJAX-verzoek mislukt: ",
    "Gekopieerd.", "Kopiëren mislukt. Selecteer en kopieer handmatig.", "Opgeslagen", ". Lijst wordt bijgewerkt...",
    "Geen geldige preview ontvangen.", "Controle:", "Bron", "Gekoppelde pagina", "Status", "Onbekend item", "Geen melding ontvangen.",
    "Content ophalen", "Tekst wordt opgehaald...", "Er ging iets mis.", "Geen item geselecteerd.",
    "Weet je zeker dat je dit contentblok wilt opslaan? Er wordt automatisch eerst een back-up gemaakt.",
    "Back-up maken en opslaan...", "Opslaan mislukt.", "Opgeslagen.", "Categorie opgeslagen", "Pagina opgeslagen", "TXT-bestand gedownload.",
    "Content Sync: 0 geselecteerd", "Selecteer eerst één of meerdere items.", "Er staat nog een lokaal concept van de bulkeditor. Wil je dit herstellen?",
    "Plak hier je bulktekst en klik daarna op “Controleer bulktekst”.", "Bulkeditor geopend. Plak je tekst en controleer vóór opslaan.",
    "Contentblokken worden opgehaald...", "Ophalen mislukt.", "Exporteren mislukt.", "Er staat geen tekst om te controleren.", "Controleren...", "Controle mislukt.",
    "Controle gaf geen items terug. Opslaan is geblokkeerd.", "Controleer eerst exact deze bulktekst opnieuw.",
    "Weet je zeker dat je deze gecontroleerde bulk-tekst wilt opslaan? Geldige items kunnen bestaande content, ACF-, SEO-, categorie- en media-data wijzigen. Items met fouten worden overgeslagen. Per geïmporteerd item wordt automatisch eerst een back-up gemaakt.",
    "Back-ups maken en bulk opslaan...", "Bulk opslaan mislukt.", "Bulk opgeslagen.", "Bulk opgeslagen",
    "Weet je zeker dat je de laatste import wilt terugzetten vanuit de automatische pagina-back-ups? Gebruik dit alleen direct na een foutieve import.",
    "Laatste import wordt hersteld...", "Herstellen mislukt.", "Laatste import hersteld",
    "Kies eerst een TXT-bestand.", "Kies een geldig .txt-bestand.", "Bestand kon niet gelezen worden.", "Bestand lezen...", "Bestand controleren...",
    "Controle gaf geen items terug. Importeren is geblokkeerd.", "Controleer eerst exact dit bestand opnieuw.",
    "Weet je zeker dat je dit gecontroleerde TXT-bestand wilt importeren? Geldige items kunnen bestaande content, ACF-, SEO-, categorie- en media-data wijzigen. Items met fouten worden overgeslagen. Per geïmporteerd item wordt automatisch eerst een back-up gemaakt.",
    "Back-ups maken en importeren...", "Import mislukt.", "Import voltooid.", "Import voltooid",
]
admin_js = wrap_literals(admin_js, admin_strings)
write("assets/admin.js", admin_js)

ai_js = read("assets/ai-image-context.js")
ai_js = replace_once(
    ai_js,
    "    const settings = window.dcaTbAiImageContextSettings || window.dcaTbSettings || {};\n",
    "    const settings = window.dcaTbAiImageContextSettings || window.dcaTbSettings || {};\n    const { __, sprintf } = window.wp.i18n;\n",
    "AI JS i18n bootstrap",
)

dynamic_ai = {
    "throw new Error('Geen geldige media-items gevonden om te importeren. Fouten: ' + errors + '.');": "throw new Error(sprintf(__('Geen geldige media-items gevonden om te importeren. Fouten: %d.', 'content-sync-manager'), errors));",
    "const confirmed = window.confirm(\n                        'Controle voltooid. Importabele afbeeldingen: ' + importable\n                        + '. Wijzigingen: ' + changes\n                        + '. Fouten: ' + errors\n                        + '. Veilig geblokkeerde hernoemingen: ' + renameBlocked\n                        + '. Doorgaan met importeren?'\n                    );": "const confirmed = window.confirm(sprintf(\n                        __('Controle voltooid. Importabele afbeeldingen: %1$d. Wijzigingen: %2$d. Fouten: %3$d. Veilig geblokkeerde hernoemingen: %4$d. Doorgaan met importeren?', 'content-sync-manager'),\n                        importable,\n                        changes,\n                        errors,\n                        renameBlocked\n                    ));",
}
for old, new in dynamic_ai.items():
    ai_js = replace_once(ai_js, old, new, f"AI dynamic i18n: {old[:32]}")

ai_strings = [
    "De Content Sync-actie is mislukt.", "Het TXT-bestand kon niet worden gelezen.", "AI afbeeldingen export", "AI data importeren",
    "Selecteer eerst minimaal een afbeelding. Gebruik in de rasterweergave eerst Bulkselectie.", "Selecteer eerst minimaal een pagina, bericht of product.",
    "AI-afbeeldingscontext wordt gemaakt.", "De export bevat geen tekst.", "AI-afbeeldingscontext is geexporteerd.", "AI-afbeeldingscontext exporteren is mislukt.",
    "AI-media-import wordt gecontroleerd.", "AI-media-import geannuleerd.", "AI-media-import is voltooid.", "AI-media-import is mislukt.",
]
ai_js = wrap_literals(ai_js, ai_strings)
write("assets/ai-image-context.js", ai_js)

# Runtime matrix and official Plugin Check.
runtime = read(".github/workflows/runtime-release-gate.yml")
old_matrix_block = """          - label: WordPress 7.0.4 - PHP 8.3 - ACF 6.8.9 - WooCommerce 11.0.1
            wordpress: 7.0.4
            php: '8.3'
            woocommerce: 11.0.1
"""
new_matrix_block = old_matrix_block + """          - label: WordPress 7.1 - PHP 8.3 - ACF 6.8.9 - WooCommerce 11.0.1
            wordpress: 7.1
            php: '8.3'
            woocommerce: 11.0.1
          - label: WordPress 7.1 - PHP 8.5 - ACF 6.8.9
            wordpress: 7.1
            php: '8.5'
            woocommerce: ''
"""
runtime = replace_once(runtime, old_matrix_block, new_matrix_block, "runtime matrix")
runtime = replace_once(
    runtime,
    """          if [[ -n '${{ matrix.woocommerce }}' ]]; then
            php wp-cli.phar plugin install woocommerce \\
              --path=runtime-site \\
              --version='${{ matrix.woocommerce }}' \\
              --activate
          fi
""",
    """          if [[ -n '${{ matrix.woocommerce }}' ]]; then
            php wp-cli.phar plugin install woocommerce \\
              --path=runtime-site \\
              --version='${{ matrix.woocommerce }}' \\
              --activate
          fi
          if [[ '${{ matrix.wordpress }}' == '7.0.4' && '${{ matrix.php }}' == '8.3' ]]; then
            php wp-cli.phar plugin install plugin-check \\
              --path=runtime-site \\
              --version=2.1.0 \\
              --activate
          fi
""",
    "Plugin Check installation",
)
runtime = replace_once(
    runtime,
    """      - name: Exercise export, preview, import and restore
""",
    """      - name: Official Plugin Check
        if: matrix.wordpress == '7.0.4' && matrix.php == '8.3'
        shell: bash
        run: |
          set -euo pipefail
          php wp-cli.phar plugin check content-sync-manager \\
            --path=runtime-site \\
            --require=./wp-content/plugins/plugin-check/cli.php \\
            --categories=general,security,performance,accessibility \\
            --format=table

      - name: Exercise export, preview, import and restore
""",
    "Plugin Check execution",
)
write(".github/workflows/runtime-release-gate.yml", runtime)

# Composer/WPCS quality gate. Keep the existing protected job names untouched.
quality = read(".github/workflows/quality.yml")
quality_addition = """

  wordpress-best-practices:
    runs-on: ubuntu-24.04
    timeout-minutes: 10

    steps:
      - name: Checkout
        uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
        with:
          persist-credentials: false

      - name: Setup PHP
        uses: shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240 # 2.37.2
        with:
          php-version: '8.3'
          coverage: none
          tools: composer:v2

      - name: Validate locked tooling
        shell: bash
        run: |
          set -euo pipefail
          composer validate --strict --no-interaction
          composer install --no-interaction --prefer-dist --no-progress
          composer audit --locked --format=json

      - name: WordPress Coding Standards
        shell: bash
        run: |
          set -euo pipefail
          vendor/bin/phpcs --standard=phpcs.xml.dist
"""
if "wordpress-best-practices:" in quality:
    raise SystemExit("quality workflow already contains wordpress-best-practices")
quality = quality.rstrip() + quality_addition + "\n"
write(".github/workflows/quality.yml", quality)

composer_json = {
    "name": "webactueel/content-sync-manager",
    "description": "Development tooling for Content Sync Manager.",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {"php": ">=7.4"},
    "require-dev": {
        "dealerdirect/phpcodesniffer-composer-installer": "1.2.1",
        "wp-coding-standards/wpcs": "3.4.1",
    },
    "config": {
        "allow-plugins": {"dealerdirect/phpcodesniffer-composer-installer": True},
        "sort-packages": True,
    },
}
write("composer.json", json.dumps(composer_json, indent=4, ensure_ascii=False) + "\n")

phpcs = """<?xml version=\"1.0\"?>
<ruleset name=\"Content Sync Manager\">
    <description>Release-critical WordPress security and API coding-standard checks.</description>
    <file>content-sync-manager.php</file>
    <file>uninstall.php</file>
    <file>includes</file>
    <arg name=\"extensions\" value=\"php\"/>
    <arg name=\"colors\"/>
    <arg value=\"sp\"/>

    <rule ref=\"WordPress.Security.NonceVerification\"/>
    <rule ref=\"WordPress.Security.ValidatedSanitizedInput\"/>
    <rule ref=\"WordPress.Security.EscapeOutput\"/>
    <rule ref=\"WordPress.DB.PreparedSQL\"/>
    <rule ref=\"WordPress.WP.Capabilities\"/>
    <rule ref=\"WordPress.WP.EnqueuedResources\"/>
    <rule ref=\"WordPress.NamingConventions.PrefixAllGlobals\"/>
</ruleset>
"""
write("phpcs.xml.dist", phpcs)

gitignore = read(".gitignore")
if "/vendor/" not in gitignore:
    gitignore = "/vendor/\n" + gitignore
write(".gitignore", gitignore)

# Regression assertions for the new compatibility/quality/i18n contracts.
static = read("tests/static-audit.php")
static = replace_once(
    static,
    "$readmeMd = $read('README.md');\n",
    "$readmeMd = $read('README.md');\n$composerJson = $read('composer.json');\n$read('composer.lock');\n$phpcsConfig = $read('phpcs.xml.dist');\n",
    "static tooling reads",
)
static = replace_once(
    static,
    "$assert(strpos($adminJs, \"['dca-select-all', 'Selecteer alles', 'button']\") !== false, 'Select-all toolbar action is missing.');",
    "$assert(strpos($adminJs, \"['dca-select-all', __('Selecteer alles', 'content-sync-manager'), 'button']\") !== false, 'Select-all toolbar action is missing or is not localized.');",
    "localized select-all assertion",
)
static = replace_once(
    static,
    "$assert(strpos($runtimeWorkflow, 'WordPress 6.2.11') !== false && strpos($runtimeWorkflow, 'WordPress 7.0.4') !== false, 'Runtime workflow must cover the minimum and supported ecosystem WordPress matrices.');",
    "$assert(strpos($runtimeWorkflow, 'WordPress 6.2.11') !== false && strpos($runtimeWorkflow, 'WordPress 7.0.4') !== false && strpos($runtimeWorkflow, 'WordPress 7.1 - PHP 8.3') !== false && strpos($runtimeWorkflow, 'WordPress 7.1 - PHP 8.5') !== false, 'Runtime workflow must cover the minimum, existing ecosystem and WordPress 7.1 matrices.');\n$assert(strpos($runtimeWorkflow, '--version=2.1.0') !== false && strpos($runtimeWorkflow, 'plugin check content-sync-manager') !== false, 'Official Plugin Check gate is missing or unpinned.');\n$assert(strpos($workflow, 'wordpress-best-practices:') !== false && strpos($workflow, 'composer audit --locked') !== false && strpos($workflow, 'vendor/bin/phpcs --standard=phpcs.xml.dist') !== false, 'WPCS/Composer best-practice gate is missing.');\n$assert(strpos($composerJson, '\"wp-coding-standards/wpcs\": \"3.4.1\"') !== false && strpos($composerJson, '\"dealerdirect/phpcodesniffer-composer-installer\": \"1.2.1\"') !== false, 'WPCS tooling versions are not pinned to the audited versions.');\n$assert(strpos($phpcsConfig, 'WordPress.Security.NonceVerification') !== false && strpos($phpcsConfig, 'WordPress.Security.EscapeOutput') !== false, 'Release-critical WPCS rules are missing.');\n$assert(strpos($manager, \"['wp-i18n']\") !== false && strpos($manager, \"wp_set_script_translations('dca-tb-admin', 'content-sync-manager')\") !== false, 'Admin JavaScript must load through WordPress i18n.');\n$assert(strpos($adminJs, 'window.wp.i18n') !== false && strpos($adminJs, \"__('Kopieer selectie', 'content-sync-manager')\") !== false, 'Admin JavaScript user-facing strings must use wp-i18n.');\n$assert(strpos($manager, 'role=\"dialog\"') !== false && strpos($manager, 'aria-live=\"polite\"') !== false && strpos($adminJs, \"event.key === 'Tab'\") !== false && strpos($adminJs, 'lastFocusedBeforeModal.focus()') !== false, 'Dialog semantics, live status and keyboard focus regression contract is incomplete.');",
    "static best-practice assertions",
)
write("tests/static-audit.php", static)

ai_static = read("tests/ai-image-context-static.php")
ai_static = replace_once(
    ai_static,
    "$assert(strpos($module, \"wp_localize_script('dca-tb-ai-image-context', 'dcaTbAiImageContextSettings'\") !== false, 'Media Library export must receive its own nonce and AJAX settings.');",
    "$assert(strpos($module, \"wp_localize_script('dca-tb-ai-image-context', 'dcaTbAiImageContextSettings'\") !== false, 'Media Library export must receive its own nonce and AJAX settings.');\n$assert(strpos($module, \"['wp-i18n']\") !== false && strpos($module, \"wp_set_script_translations('dca-tb-ai-image-context', 'content-sync-manager')\") !== false, 'AI Media JavaScript must load through WordPress i18n.');",
    "AI static PHP i18n assertion",
)
ai_static = replace_once(
    ai_static,
    "$assert(strpos($script, 'AI data importeren') !== false, 'Media Library AI import button is missing.');",
    "$assert(strpos($script, \"__('AI data importeren', 'content-sync-manager')\") !== false && strpos($script, 'window.wp.i18n') !== false, 'Media Library AI import button must use WordPress i18n.');",
    "AI static JS i18n assertion",
)
write("tests/ai-image-context-static.php", ai_static)

print("Applied 2026 WordPress best-practice hardening patch.")
