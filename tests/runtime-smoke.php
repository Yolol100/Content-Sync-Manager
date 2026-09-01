<?php
/**
 * Clean WordPress runtime smoke test, executed through WP-CLI after installing
 * the generated release ZIP.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run this script through WP-CLI eval-file.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

wp_set_current_user(1);
$assert(current_user_can('manage_options'), 'Runtime user must be an administrator.');
$assert(defined('DCA_TB_VERSION') && DCA_TB_VERSION === '1.2.62', 'Installed plugin version is not 1.2.62.');

foreach ([
    'dca_tb_build_bulk_export',
    'dca_tb_bulk_preview',
    'dca_tb_bulk_save',
    'dca_tb_restore_last_import_page_backups',
    'dca_tb_ai_image_context_build_media_export',
    'dca_tb_ai_image_context_preview_media_import',
    'dca_tb_ai_image_context_apply_media_import',
    'dca_tb_ai_image_context_walk_acf_field',
] as $function) {
    $assert(function_exists($function), 'Missing runtime function: ' . $function);
}

foreach ([
    'wp_ajax_dca_get_acf_textblock',
    'wp_ajax_dca_bulk_get_acf_textblocks',
    'wp_ajax_dca_txt_import_preview',
    'wp_ajax_dca_txt_import_run',
    'wp_ajax_dca_restore_last_import_pages',
    'wp_ajax_dca_ai_image_context_export',
    'wp_ajax_dca_ai_image_context_import_preview',
    'wp_ajax_dca_ai_image_context_import_run',
] as $hook) {
    $assert(has_action($hook) !== false, 'Missing authenticated AJAX hook: ' . $hook);
}

$roundtrip = static function (string $post_type, string $field_key = '', string $field_name = '') use ($assert): array {
    $token = 'runtime-' . $post_type . '-' . wp_generate_password(12, false, false);
    $changed = $token . '-changed';
    $post_id = wp_insert_post([
        'post_type'    => $post_type,
        'post_status'  => 'publish',
        'post_title'   => 'Content Sync runtime ' . $post_type,
        'post_content' => $post_type === 'post' ? $token : '',
    ], true);

    $assert(!is_wp_error($post_id) && $post_id > 0, 'Unable to create runtime ' . $post_type . '.');

    if ($field_key !== '') {
        $assert(function_exists('update_field'), 'ACF update_field() is unavailable.');
        update_field($field_key, $token, $post_id);
        $assert((string) get_field($field_name, $post_id) === $token, 'Unable to seed ACF value for ' . $post_type . '.');
    }

    $export = dca_tb_build_bulk_export([$post_id]);
    $assert(!is_wp_error($export), 'Export failed for ' . $post_type . '.');
    $assert(substr_count((string) $export, $token) >= 1, 'Export is missing the seeded value for ' . $post_type . '.');

    $changed_export = str_replace($token, $changed, (string) $export, $replacements);
    $assert($replacements >= 1, 'Unable to prepare changed import for ' . $post_type . '.');

    $preview = dca_tb_bulk_preview($changed_export);
    $assert(!is_wp_error($preview), 'Preview failed for ' . $post_type . '.');
    $assert(isset($preview[0]['status']) && $preview[0]['status'] === 'success', 'Preview is not importable for ' . $post_type . '.');

    $preview_hash = dca_tb_mark_import_previewed($changed_export, $preview);
    $preview_state = get_transient(dca_tb_import_preview_transient_key($preview_hash));
    $assert(is_array($preview_state) && (int) ($preview_state['importable'] ?? 0) === 1, 'Preview binding was not stored for ' . $post_type . '.');
    $assert(!hash_equals($preview_hash, dca_tb_import_preview_hash($changed_export . '-tampered')), 'Preview hash must bind to exact TXT content.');

    $result = dca_tb_bulk_save($changed_export);
    $assert(!is_wp_error($result), 'Import failed for ' . $post_type . '.');
    $assert((int) ($result['imported'] ?? 0) === 1 && (int) ($result['skipped'] ?? 0) === 0, 'Unexpected import result for ' . $post_type . '.');

    if ($field_key !== '') {
        $assert((string) get_field($field_name, $post_id) === $changed, 'Imported ACF value does not match for ' . $post_type . '.');
    } else {
        $assert((string) get_post_field('post_content', $post_id) === $changed, 'Imported post content does not match.');
    }

    $log = dca_tb_get_last_import_log();
    $assert((int) ($log['imported'] ?? 0) === 1, 'Import log is missing the successful ' . $post_type . ' import.');
    $assert(strpos(dca_tb_format_import_log_text($log), 'Items geïmporteerd: 1') !== false, 'Formatted import log is incomplete.');

    $restore = dca_tb_restore_last_import_page_backups();
    $assert(!is_wp_error($restore), 'Restore failed for ' . $post_type . '.');
    $assert((int) ($restore['restored'] ?? 0) === 1 && (int) ($restore['skipped'] ?? 0) === 0, 'Unexpected restore result for ' . $post_type . '.');

    if ($field_key !== '') {
        $assert((string) get_field($field_name, $post_id) === $token, 'ACF rollback did not restore the original ' . $post_type . ' value.');
    } else {
        $assert((string) get_post_field('post_content', $post_id) === $token, 'Post rollback did not restore the original content.');
    }

    wp_delete_post($post_id, true);

    return [
        'post_type' => $post_type,
        'export_bytes' => strlen((string) $export),
        'preview' => $preview[0]['status'],
        'imported' => (int) $result['imported'],
        'restored' => (int) $restore['restored'],
    ];
};

$media_export_import = static function () use ($assert): array {
    $uploads = wp_upload_dir();
    $assert(empty($uploads['error']), 'WordPress uploads directory is unavailable.');

    $token = wp_generate_password(8, false, false);
    $filename = 'content-sync-runtime-media-' . $token . '.png';
    $path = trailingslashit($uploads['path']) . $filename;
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    $assert($png !== false && file_put_contents($path, $png) !== false, 'Unable to create runtime image fixture.');

    $attachment_id = wp_insert_attachment([
        'post_mime_type' => 'image/png',
        'post_title'     => 'Runtime media title',
        'post_excerpt'   => 'Runtime media caption',
        'post_content'   => 'Runtime media description',
        'post_status'    => 'inherit',
    ], $path, 0, true);

    $assert(!is_wp_error($attachment_id) && $attachment_id > 0, 'Unable to create runtime image attachment.');
    update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Runtime media alt');
    wp_update_attachment_metadata($attachment_id, [
        'width'  => 1,
        'height' => 1,
        'file'   => ltrim(str_replace(trailingslashit($uploads['basedir']), '', $path), '/'),
        'sizes'  => [],
    ]);

    $export = dca_tb_ai_image_context_build_media_export([$attachment_id]);
    $assert($export !== '', 'Selected Media Library image export returned no text.');
    $assert(strpos($export, 'AI AFBEELDINGSCONTEXT MEDIA-EXPORT') !== false, 'Media export header is missing.');
    $assert(strpos($export, 'Schema: 3') !== false, 'Media export schema is not current.');
    $assert(strpos($export, 'Attachment ID: ' . $attachment_id) !== false, 'Media export is missing the selected attachment ID.');
    $assert(strpos($export, 'Runtime media title') !== false, 'Media export is missing attachment title metadata.');
    $assert(strpos($export, 'Runtime media alt') !== false, 'Media export is missing attachment alt metadata.');
    $assert(strpos($export, $filename) !== false, 'Media export is missing the selected image filename.');
    $assert(strpos($export, 'MEDIA IMPORT') !== false && strpos($export, 'EINDE MEDIA IMPORT') !== false, 'Media export is missing the round-trip import block.');
    $assert(strpos($export, 'AI-preview URL: ') !== false, 'Media export is missing primary preview context.');
    $assert(strpos($export, 'AI-detailpreview URL: ') !== false, 'Media export is missing detail preview context.');

    $acf_refs = [];
    dca_tb_ai_image_context_walk_acf_field([
        'type' => 'gallery',
        'key' => 'field_runtime_gallery',
    ], [$attachment_id, $attachment_id], 'acf:gallery', $acf_refs);
    $assert(isset($acf_refs[$attachment_id]['acf:gallery[1]']), 'Exact ACF gallery position is missing.');

    $repeater_refs = [];
    dca_tb_ai_image_context_walk_acf_field([
        'type' => 'repeater',
        'key' => 'field_runtime_repeater',
        'sub_fields' => [[
            'type' => 'image',
            'key' => 'field_runtime_repeater_image',
            'name' => 'image',
        ]],
    ], [['image' => $attachment_id]], 'acf:items', $repeater_refs);
    $assert(isset($repeater_refs[$attachment_id]['acf:items[0].image']), 'Exact ACF repeater image path is missing.');

    $flex_refs = [];
    dca_tb_ai_image_context_walk_acf_field([
        'type' => 'flexible_content',
        'key' => 'field_runtime_flex',
        'layouts' => [[
            'name' => 'hero',
            'sub_fields' => [[
                'type' => 'image',
                'key' => 'field_runtime_flex_image',
                'name' => 'image',
            ]],
        ]],
    ], [['acf_fc_layout' => 'hero', 'image' => $attachment_id]], 'acf:flex', $flex_refs);
    $assert(isset($flex_refs[$attachment_id]['acf:flex[0]{hero}.image']), 'Exact ACF Flexible Content image path is missing.');

    $new_name = 'content-sync-runtime-renamed-' . $token;
    $import_text = implode("\n", [
        'MEDIA IMPORT',
        'Attachment ID:',
        (string) $attachment_id,
        'Bestandsnaam:',
        $filename,
        'Nieuwe bestandsnaam:',
        $new_name,
        'Title:',
        'Runtime media title changed',
        'Alt text:',
        'Runtime media alt changed',
        'Caption:',
        'Runtime media caption changed',
        'Description:',
        'Runtime media description changed',
        'EINDE MEDIA IMPORT',
    ]);

    $preview = dca_tb_ai_image_context_preview_media_import($import_text);
    $assert(!is_wp_error($preview), 'AI media import preview failed.');
    $assert((int) ($preview['importable'] ?? 0) === 1, 'AI media import preview is not importable.');
    $assert((int) ($preview['errors'] ?? 0) === 0, 'AI media import preview reported an unexpected error.');
    $assert(!empty($preview['items'][0]['rename_allowed']), 'AI media rename should be allowed for an unused attachment in a complete scan.');

    $result = dca_tb_ai_image_context_apply_media_import($import_text);
    $assert(!is_wp_error($result), 'AI media import failed.');
    $assert((int) ($result['updated'] ?? 0) === 1, 'AI media import did not update the attachment.');
    $assert((int) ($result['renamed'] ?? 0) === 1, 'AI media import did not rename the physical filename.');
    $assert((int) ($result['errors'] ?? 0) === 0, 'AI media import reported an unexpected error.');
    $assert((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true) === 'Runtime media alt changed', 'AI media import did not update alt text.');
    $assert((string) get_post_field('post_title', $attachment_id) === 'Runtime media title changed', 'AI media import did not update media title.');
    $assert((string) wp_basename(get_attached_file($attachment_id)) === $new_name . '.png', 'AI media import did not rename the attached file as expected.');
    $assert(is_file(get_attached_file($attachment_id)), 'Renamed media file is missing from uploads.');

    wp_delete_attachment($attachment_id, true);
    dca_tb_ai_image_context_cleanup_preview_files(true);

    return [
        'attachment' => $attachment_id,
        'export_bytes' => strlen($export),
        'import_updated' => (int) $result['updated'],
        'renamed' => (int) $result['renamed'],
    ];
};

$assert(function_exists('acf_add_local_field_group'), 'ACF is not active.');
$acf_version = defined('ACF_VERSION') ? ACF_VERSION : '';
$assert($acf_version === '6.8.9', 'Unexpected ACF version: ' . $acf_version);

$register_field = static function (string $post_type): array {
    $suffix = str_replace('-', '_', $post_type);
    $field_key = 'field_dca_runtime_' . $suffix;
    $field_name = 'dca_runtime_' . $suffix;
    acf_add_local_field_group([
        'key' => 'group_dca_runtime_' . $suffix,
        'title' => 'Content Sync runtime ' . $post_type,
        'fields' => [[
            'key' => $field_key,
            'label' => 'Runtime value',
            'name' => $field_name,
            'type' => 'text',
        ]],
        'location' => [[[
            'param' => 'post_type',
            'operator' => '==',
            'value' => $post_type,
        ]]],
    ]);
    return [$field_key, $field_name];
};

$evidence = [];
$evidence[] = $roundtrip('post');
[$page_field_key, $page_field_name] = $register_field('page');
$evidence[] = $roundtrip('page', $page_field_key, $page_field_name);
$media_evidence = $media_export_import();

$expect_woocommerce = getenv('DCA_RUNTIME_EXPECT_WOOCOMMERCE') === '1';
if ($expect_woocommerce) {
    $assert(class_exists('WooCommerce'), 'WooCommerce is not active in the ecosystem matrix.');
    $woocommerce_version = defined('WC_VERSION') ? WC_VERSION : '';
    $assert($woocommerce_version === '11.0.1', 'Unexpected WooCommerce version: ' . $woocommerce_version);
    $assert(post_type_exists('product'), 'WooCommerce product post type is unavailable.');
    [$product_field_key, $product_field_name] = $register_field('product');
    $evidence[] = $roundtrip('product', $product_field_key, $product_field_name);
}

$payload = [
    'wordpress' => get_bloginfo('version'),
    'php' => PHP_VERSION,
    'acf' => $acf_version,
    'woocommerce' => defined('WC_VERSION') ? WC_VERSION : null,
    'plugin' => DCA_TB_VERSION,
    'flows' => $evidence,
    'media_export_import' => $media_evidence,
];

if (class_exists('WP_CLI')) {
    WP_CLI::success('Content Sync runtime gate passed: ' . wp_json_encode($payload));
} else {
    echo wp_json_encode($payload) . "\n";
}
