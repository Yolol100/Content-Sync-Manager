<?php
/**
 * Runtime regression checks for persistent AI media indexing and smart selection.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress is not loaded.\n");
    exit(1);
}

$failures = [];
$checks = 0;
$assert = static function ($condition, $message) use (&$failures, &$checks) {
    ++$checks;
    if (!$condition) {
        $failures[] = $message;
    }
};

foreach ([
    'dca_tb_ai_media_index_inventory',
    'dca_tb_ai_media_index_store_context',
    'dca_tb_ai_media_index_context_state',
    'dca_tb_ai_media_index_search',
    'dca_tb_ai_media_index_enrich_export_text',
    'dca_tb_ai_media_index_preview_media_import',
    'dca_tb_ai_media_index_apply_contexts',
] as $function) {
    $assert(function_exists($function), 'Missing runtime function: ' . $function);
}

wp_set_current_user(1);
$assert(current_user_can('manage_options'), 'Runtime user must be an administrator.');

require_once ABSPATH . 'wp-admin/includes/image.php';

$uploads = wp_upload_dir();
$assert(empty($uploads['error']), 'Uploads directory is unavailable.');
$token = strtolower(wp_generate_password(8, false, false));
$filename = 'content-sync-ai-index-' . $token . '.png';
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFElEQVR42mNkYPj/n4GBgYGJAQoAHgQCAfX8lEwAAAAASUVORK5CYII=', true);
$assert($png !== false, 'Unable to decode runtime image fixture.');
$bits = wp_upload_bits($filename, null, $png);
$assert(empty($bits['error']) && !empty($bits['file']), 'Unable to create runtime image fixture.');
$path = $bits['file'];
$filetype = wp_check_filetype($filename, null);
$attachment_id = wp_insert_attachment([
    'post_mime_type' => $filetype['type'],
    'post_title' => 'Rode fiets voorbeeld',
    'post_excerpt' => 'Fiets op straat',
    'post_content' => 'Een rode fiets in een stedelijke omgeving.',
    'post_status' => 'inherit',
], $path, 0, true);
$assert(!is_wp_error($attachment_id) && $attachment_id > 0, 'Unable to create runtime attachment.');

if (!is_wp_error($attachment_id)) {
    $metadata = wp_generate_attachment_metadata($attachment_id, $path);
    $assert(is_array($metadata) && !empty($metadata['width']) && !empty($metadata['height']), 'Attachment metadata generation failed.');
    wp_update_attachment_metadata($attachment_id, $metadata);
    update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Rode fiets');

    $inventory = dca_tb_ai_media_index_inventory($attachment_id);
    $assert(($inventory['mime_type'] ?? '') === 'image/png', 'Inventory MIME type is incorrect.');
    $assert((int) ($inventory['file_size_bytes'] ?? 0) > 0, 'Inventory file size is missing.');
    $assert((int) ($inventory['width'] ?? 0) > 0 && (int) ($inventory['height'] ?? 0) > 0, 'Inventory dimensions are missing.');
    $assert((float) ($inventory['aspect_ratio'] ?? 0) > 0, 'Inventory aspect ratio is missing.');
    $assert(isset($inventory['available_sizes']) && is_array($inventory['available_sizes']), 'Inventory WordPress sizes are missing.');

    $stored = dca_tb_ai_media_index_store_context($attachment_id, [
        'status' => 'clear',
        'summary' => 'Rode fiets op een straat in een stedelijke omgeving.',
        'confidence' => 'high',
        'tags' => ['rode fiets', 'straat', 'stedelijk'],
        'subjects' => ['fiets'],
        'objects' => ['fiets'],
        'colors' => ['rood'],
        'style' => ['fotografie'],
        'composition' => ['centraal onderwerp'],
        'use_cases' => [
            'hero' => 'high',
            'card' => 'high',
            'thumbnail' => 'medium',
        ],
    ]);
    $assert($stored === true, 'AI context was not stored.');

    $state = dca_tb_ai_media_index_context_state($attachment_id);
    $assert(!empty($state['indexed']) && empty($state['stale']), 'Stored AI context must be fresh.');
    $assert(($state['context']['confidence'] ?? '') === 'high', 'Stored confidence is incorrect.');

    $matches = dca_tb_ai_media_index_search('rode fiets stedelijk', ['limit' => 5]);
    $match_ids = array_map(static function ($match) {
        return absint($match['attachment_id'] ?? 0);
    }, $matches);
    $assert(in_array($attachment_id, $match_ids, true), 'Semantic media search did not return the indexed attachment.');

    $page_id = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'Fietsen in de stad',
        'post_content' => 'Een pagina over een rode fiets in een stedelijke straat.',
    ], true);
    $assert(!is_wp_error($page_id) && $page_id > 0, 'Unable to create runtime page for smart selection.');
    if (!is_wp_error($page_id)) {
        $selection = dca_tb_ai_media_index_selection_section([$page_id]);
        $assert(strpos($selection, 'Attachment ID ' . $attachment_id) !== false, 'Smart page selection did not surface the indexed attachment.');
        wp_delete_post($page_id, true);
    }

    $base_export = dca_tb_ai_image_context_build_media_export([$attachment_id]);
    $assert($base_export !== '', 'Base media export failed.');
    $enriched = dca_tb_ai_media_index_enrich_export_text($base_export, 'media', [$attachment_id]);
    $assert(strpos($enriched, '"inventory"') !== false, 'Enriched export is missing inventory data.');
    $assert(strpos($enriched, '"ai"') !== false, 'Enriched export is missing AI context data.');
    $assert(strpos($enriched, '"file_size_bytes"') !== false, 'Enriched export is missing file size.');
    $assert(strpos($enriched, '"aspect_ratio"') !== false, 'Enriched export is missing aspect ratio.');

    $changed = preg_replace_callback(
        '/^MEDIA IMPORT\s*$\n(\{.*?\})\n^EINDE MEDIA IMPORT\s*$/ims',
        static function ($match) {
            $payload = json_decode($match[1], true);
            $payload['ai']['summary'] = 'Blauwe fiets op een straat.';
            $payload['ai']['confidence'] = 'medium';
            $payload['ai']['colors'] = ['blauw'];
            return "MEDIA IMPORT\n" . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\nEINDE MEDIA IMPORT";
        },
        $enriched
    );
    $preview = dca_tb_ai_media_index_preview_media_import($changed);
    $assert(!is_wp_error($preview), 'Enhanced media import preview failed.');
    if (!is_wp_error($preview)) {
        $assert(!empty($preview['items'][0]['ai_context_change']), 'AI-only context change is not detected by preview.');
        $assert((int) ($preview['changes'] ?? 0) >= 1, 'AI-only context change is not counted.');
        $applied = dca_tb_ai_media_index_apply_contexts($preview);
        $assert((int) ($applied['indexed'] ?? 0) === 1, 'Changed AI context was not applied.');
        $context = dca_tb_ai_media_index_get_context($attachment_id);
        $assert(($context['summary'] ?? '') === 'Blauwe fiets op een straat.', 'Updated AI summary was not persisted.');
        $assert(($context['confidence'] ?? '') === 'medium', 'Updated AI confidence was not persisted.');
    }

    clearstatcache(true, $path);
    $mtime = filemtime($path);
    if ($mtime !== false) {
        touch($path, $mtime + 10);
        clearstatcache(true, $path);
        $stale = dca_tb_ai_media_index_context_state($attachment_id);
        $assert(!empty($stale['stale']), 'AI context must become stale when the attachment file changes.');
        $assert(dca_tb_ai_media_index_get_context($attachment_id) === [], 'Stale AI context must not be used for search by default.');
        $assert(dca_tb_ai_media_index_get_context($attachment_id, true) !== [], 'Stale AI context should remain inspectable for diagnostics.');
    }

    wp_delete_attachment($attachment_id, true);
}

if ($failures) {
    fwrite(STDERR, "AI media index runtime audit failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "AI media index runtime audit passed ({$checks} checks).\n");
