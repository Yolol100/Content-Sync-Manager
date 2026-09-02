<?php
/**
 * Static regression checks for the persistent AI media index module.
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
$module = $read('includes/ai-media-index.php');
$quality = $read('.github/workflows/quality.yml');
$runtime = $read('.github/workflows/runtime-release-gate.yml');

$assert(strpos($bootstrap, "require_once DCA_TB_PLUGIN_DIR . 'includes/ai-media-index.php';") !== false, 'AI media index module is not loaded by the bootstrap.');
$assert(strpos($module, "'_dca_ai_media_context'") !== false, 'Persistent AI media context meta key is missing.');
$assert(strpos($module, 'dca_tb_ai_media_index_attachment_fingerprint') !== false, 'AI media context is not bound to an attachment fingerprint.');
$assert(strpos($module, "'file_size_bytes'") !== false, 'Media inventory is missing file size.');
$assert(strpos($module, "'mime_type'") !== false, 'Media inventory is missing MIME type.');
$assert(strpos($module, "'aspect_ratio'") !== false, 'Media inventory is missing aspect ratio.');
$assert(strpos($module, "'available_sizes'") !== false, 'Media inventory is missing WordPress image sizes.');
$assert(strpos($module, "'summary'") !== false && strpos($module, "'confidence'") !== false && strpos($module, "'tags'") !== false, 'Semantic AI fields are incomplete.');
$assert(strpos($module, "'people'") !== false && strpos($module, "'objects'") !== false && strpos($module, "'products'") !== false && strpos($module, "'logos'") !== false, 'Visual entity fields are incomplete.');
$assert(strpos($module, "'locations'") !== false && strpos($module, "'screenshots'") !== false && strpos($module, "'visible_text'") !== false, 'Location, screenshot or visible-text context is missing.');
$assert(strpos($module, "'colors'") !== false && strpos($module, "'style'") !== false && strpos($module, "'composition'") !== false, 'Visual style fields are incomplete.');
$assert(strpos($module, "'use_cases'") !== false && strpos($module, "'hero'") !== false && strpos($module, "'background'") !== false, 'Use-case suitability mapping is missing.');
$assert(strpos($module, 'dca_tb_ai_media_index_search') !== false, 'Searchable media index is missing.');
$assert(strpos($module, 'SLIMME MEDIASELECTIE VOOR POST #') !== false, 'Page-context smart media selection output is missing.');
$assert(strpos($module, 'dca_tb_ai_media_index_enrich_import_blocks') !== false, 'Structured export enrichment is missing.');
$assert(strpos($module, "dca_tb_require_ajax_access()") !== false, 'Enhanced AJAX handlers must reuse the manager authorization boundary.');
$assert(strpos($module, 'dca_tb_require_matching_import_preview($text)') !== false, 'Enhanced import must retain exact preview binding.');
$assert(strpos($module, 'dca_tb_require_destructive_confirmation()') !== false, 'Enhanced import must retain destructive confirmation.');
$assert(strpos($module, "remove_action('wp_ajax_dca_ai_image_context_export'") !== false, 'Original export callback is not explicitly replaced.');
$assert(strpos($module, "remove_action('wp_ajax_dca_ai_image_context_import_preview'") !== false, 'Original preview callback is not explicitly replaced.');
$assert(strpos($module, "remove_action('wp_ajax_dca_ai_image_context_import_run'") !== false, 'Original import callback is not explicitly replaced.');
$assert(strpos($module, 'wp_remote_') === false && strpos($module, 'curl_') === false, 'AI media index must not add external provider calls.');
$assert(strpos($quality, 'php tests/ai-media-index-static.php') !== false, 'Quality workflow does not run the AI media index static regression test.');
$assert(strpos($runtime, 'tests/ai-media-index-runtime.php') !== false, 'Runtime workflow does not run the AI media index runtime regression test.');

if ($failures) {
    fwrite(STDERR, "AI media index static audit failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "AI media index static audit passed ({$checks} checks).\n");
