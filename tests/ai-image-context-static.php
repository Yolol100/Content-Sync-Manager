<?php
/**
 * Static regression checks for the AI image context export/import module.
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
$assert(strpos($module, "wp_ajax_dca_ai_image_context_export") !== false, 'AI image context export AJAX route is missing.');
$assert(strpos($module, "wp_ajax_dca_ai_image_context_import_preview") !== false, 'AI image import preview AJAX route is missing.');
$assert(strpos($module, "wp_ajax_dca_ai_image_context_import_run") !== false, 'AI image import run AJAX route is missing.');
$assert(strpos($module, "dca_tb_require_ajax_access()") !== false, 'AI image actions must reuse the manager AJAX authorization boundary.');
$assert(strpos($module, "dca_tb_require_matching_import_preview(\$text)") !== false, 'AI image import must bind execution to the exact previewed TXT.');
$assert(strpos($module, "dca_tb_require_destructive_confirmation()") !== false, 'AI image import must require an explicit destructive confirmation.');
$assert(strpos($module, "\$pagenow === 'upload.php'") !== false, 'AI image context assets must load on the Media Library screen.');
$assert(strpos($module, "wp_localize_script('dca-tb-ai-image-context', 'dcaTbAiImageContextSettings'") !== false, 'Media Library export must receive its own nonce and AJAX settings.');
$assert(strpos($module, 'DCA_TB_AI_IMAGE_CONTEXT_PREVIEW_MAX') !== false && strpos($module, '512') !== false, 'Primary AI preview limit is missing.');
$assert(strpos($module, 'DCA_TB_AI_IMAGE_CONTEXT_DETAIL_PREVIEW_MAX') !== false && strpos($module, '1024') !== false, 'Detail AI preview limit is missing.');
$assert(strpos($module, 'wp_get_image_editor') !== false, 'AI image context must generate a real downsized preview when needed.');
$assert(strpos($module, 'content-sync-ai-previews') !== false, 'Temporary preview directory is missing.');
$assert(strpos($module, "wp_schedule_event") !== false && strpos($module, "dca_tb_ai_image_context_cleanup_previews") !== false, 'Temporary preview cleanup schedule is missing.');
$assert(strpos($module, "manual-preview-needed") !== false, 'Preview generation must fail closed when no safe preview is available.');
$assert(strpos($module, 'dca_tb_ai_image_context_dimensions_preserve_ratio') !== false, 'Existing preview fallback must verify source aspect ratio.');
$assert(strpos($module, 'wp_get_registered_image_subsizes') !== false, 'Existing preview fallback must inspect registered crop settings.');
$assert(strpos($module, 'dca_tb_ai_image_context_walk_acf_field') !== false, 'Exact recursive ACF path walker is missing.');
$assert(strpos($module, "'gallery'") !== false && strpos($module, "'repeater'") !== false && strpos($module, "'flexible_content'") !== false, 'Nested ACF media types are not covered.');
$assert(strpos($module, "field['layouts']") !== false, 'Flexible Content layout-specific subfields are not handled.');
$assert(strpos($module, "acf_fc_layout") !== false, 'Flexible Content layout name is missing from exact ACF paths.');
$assert(strpos($module, 'dca_tb_ai_image_context_site_usage_scan') !== false, 'WordPress-wide media usage scan is missing.');
$assert(strpos($module, 'DCA_TB_AI_IMAGE_CONTEXT_USAGE_SCAN_MAX_POSTS') !== false, 'Usage scan safety limit is missing.');
$assert(strpos($module, 'dca_tb_ai_image_context_private_meta_url_refs') !== false, 'Private builder metadata must be scanned before physical media renames.');
$assert(strpos($module, "\$source = 'meta:' . \$meta_key;") !== false, 'Private metadata usage must be surfaced in usage sources.');
$assert(strpos($module, 'dca_tb_ai_image_context_media_import_block') !== false, 'Round-trip MEDIA IMPORT block is missing.');
$assert(strpos($module, 'EINDE MEDIA IMPORT') !== false, 'MEDIA IMPORT end marker is missing.');
$assert(strpos($module, "'schema' => 2") !== false && strpos($module, 'wp_json_encode') !== false, 'MEDIA IMPORT must use collision-safe JSON payloads.');
$assert(strpos($module, 'json_decode') !== false, 'MEDIA IMPORT must parse collision-safe JSON payloads.');
$assert(strpos($module, 'dca_tb_ai_image_context_preview_media_import') !== false, 'AI media import preview validator is missing.');
$assert(strpos($module, 'dca_tb_ai_image_context_apply_media_import') !== false, 'AI media import executor is missing.');
$assert(strpos($module, 'dca_tb_update_attachment_from_media_item') !== false, 'AI media import must reuse the canonical media update/rename function.');
$assert(strpos($module, 'dca_tb_replace_media_urls_on_page') !== false, 'Safe URL replacement after a media rename is missing.');
$assert(strpos($module, 'dca_tb_ai_image_context_rename_blocker') !== false, 'Filename rename must share one fail-closed usage/permission blocker.');
$assert(strpos($module, "current_user_can('edit_post'") !== false, 'Filename rename must verify edit permission on every usage post before rewriting it.');
$assert(strpos($module, 'onveilige of niet-ondersteunde opslaglocatie') !== false, 'Filename rename must fail closed for builder/private metadata usage.');
$assert(strpos($module, 'Status op onzeker') !== false, 'Low-confidence AI fallback instruction is missing.');
$assert(strpos($module, 'decoratieve afbeeldingen') !== false, 'Decorative-image alt handling is missing.');
$assert(strpos($module, 'wp_remote_') === false, 'AI image context must not silently send images or metadata to an external service.');

$assert(strpos($script, "dca_ai_image_context_export") !== false, 'Client and server export actions do not match.');
$assert(strpos($script, "dca_ai_image_context_import_preview") !== false, 'Client media import preview action is missing.');
$assert(strpos($script, "dca_ai_image_context_import_run") !== false, 'Client media import run action is missing.');
$assert(strpos($script, 'input[type="checkbox"][name="post[]"]:checked') !== false, 'Page AI export must use the current WordPress post selection.');
$assert(strpos($script, 'input[type="checkbox"][name="media[]"]:checked') !== false, 'Media list AI export must use the current Media Library selection.');
$assert(strpos($script, '.attachment.selected[data-id]') !== false, 'Media grid AI export must read selected grid attachments.');
$assert(strpos($script, "state.get('selection')") !== false, 'Media grid AI export must also support the WordPress media selection model.');
$assert(strpos($script, '.select-mode-toggle-button') !== false, 'Media grid controls must be placed beside the WordPress Bulk Select toggle.');
$assert(strpos($script, 'MutationObserver') !== false, 'Media grid controls must survive WordPress toolbar re-renders.');
$assert(strpos($script, 'dca-ai-image-context-controls-grid media-button') !== false, 'Media grid controls must stay visible in WordPress select mode.');
$assert(strpos($script, 'AI data importeren') !== false, 'Media Library AI import button is missing.');
$assert(strpos($script, "accept = '.txt,text/plain'") !== false, 'AI media import must accept TXT files only.');
$assert(strpos($script, "preview_hash") !== false && strpos($script, "destructive_confirm") !== false, 'Client import must submit exact-preview binding and destructive confirmation.');
$assert(strpos($script, "new Blob([text], { type: 'text/plain;charset=utf-8' })") !== false, 'AI export TXT download implementation is missing.');

if ($failures) {
    fwrite(STDERR, "FAILED ({$checks} checks)\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS ({$checks} checks)\n";
