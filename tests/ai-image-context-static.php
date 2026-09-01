<?php
/**
 * Static regression checks for the AI image context export module.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$failures, &$checks): void {
    ++$checks;
    if (!$condition) {
        $failures[] = $message;
    }
};

$read = static function (string $path) use ($root, $assert): string {
    $full = $root . '/' . $path;
    $assert(is_file($full), "Missing required file: {$path}");
    if (!is_file($full)) {
        return '';
    }
    $content = file_get_contents($full);
    $assert($content !== false, "Unreadable file: {$path}");
    return $content === false ? '' : $content;
};

$bootstrap = $read('content-sync-manager.php');
$module = $read('includes/ai-image-context.php');
$script = $read('assets/ai-image-context.js');

$assert(strpos($bootstrap, "require_once DCA_TB_PLUGIN_DIR . 'includes/ai-image-context.php';") !== false, 'AI image context module is not loaded by the bootstrap.');
$assert(strpos($module, "wp_ajax_dca_ai_image_context_export") !== false, 'AI image context AJAX route is missing.');
$assert(strpos($module, "dca_tb_require_ajax_access()") !== false, 'AI image context export must reuse the manager AJAX authorization boundary.');
$assert(strpos($module, "\$pagenow === 'upload.php'") !== false, 'AI image context assets must load on the Media Library list screen.');
$assert(strpos($module, "wp_localize_script('dca-tb-ai-image-context', 'dcaTbAiImageContextSettings'") !== false, 'Media Library export must receive its own nonce and AJAX settings.');
$assert(strpos($module, 'dca_tb_ai_image_context_build_media_export') !== false, 'Selected Media Library images need a dedicated export builder.');
$assert(strpos($module, "wp_get_attachment_image_src") !== false, 'AI image context preview must use WordPress attachment image sizes.');
$assert(strpos($module, "manual-preview-needed") !== false, 'Oversized images without a safe preview must fail closed to manual preview.');
$assert(strpos($module, "Gedeeld binnen selectie") !== false, 'Shared attachment signal is missing from page-based export.');
$assert(strpos($module, "Sitebreed gedeeld: onbekend") !== false, 'Site-wide reuse uncertainty must remain explicit.');
$assert(strpos($module, "Status op onzeker") !== false, 'Low-confidence AI fallback instruction is missing.');
$assert(strpos($module, "decoratieve afbeeldingen") !== false, 'Decorative-image alt handling is missing.');
$assert(strpos($module, "IMPORTBLOK VOOR DEZE PAGINA") !== false, 'Import-ready source block is missing from page-based AI export.');
$assert(strpos($script, "dca_ai_image_context_export") !== false, 'Client and server AI export actions do not match.');
$assert(strpos($script, 'input[type="checkbox"][name="post[]"]:checked') !== false, 'Page AI export must use the current WordPress post selection.');
$assert(strpos($script, 'input[type="checkbox"][name="media[]"]:checked') !== false, 'Media AI export must use the current Media Library selection.');
$assert(strpos($script, "formData.append('scope', mediaScreen ? 'media' : 'content')") !== false, 'Client must tell the server whether the selection comes from Media or content.');
$assert(strpos($script, ".tablenav.top .actions.bulkactions") !== false, 'Media export button must be placed next to the Media Library bulk actions.');
$assert(strpos($script, "new Blob([text], { type: 'text/plain;charset=utf-8' })") !== false, 'AI export TXT download implementation is missing.');

if ($failures) {
    fwrite(STDERR, "FAILED ({$checks} checks)\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS ({$checks} checks)\n";
