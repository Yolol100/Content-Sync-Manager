<?php
/**
 * Clean WordPress runtime smoke test, executed through WP-CLI after installing
 * the generated release ZIP.
 */

declare(strict_types=1);

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
$assert(defined('DCA_TB_VERSION') && DCA_TB_VERSION === '1.2.61', 'Installed plugin version is not 1.2.61.');

foreach ([
    'dca_tb_build_bulk_export',
    'dca_tb_bulk_preview',
    'dca_tb_bulk_save',
    'dca_tb_restore_last_import_page_backups',
] as $function) {
    $assert(function_exists($function), 'Missing runtime function: ' . $function);
}

foreach ([
    'wp_ajax_dca_get_acf_textblock',
    'wp_ajax_dca_bulk_get_acf_textblocks',
    'wp_ajax_dca_txt_import_preview',
    'wp_ajax_dca_txt_import_run',
    'wp_ajax_dca_restore_last_import_pages',
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
];

if (class_exists('WP_CLI')) {
    WP_CLI::success('Content Sync runtime gate passed: ' . wp_json_encode($payload));
} else {
    echo wp_json_encode($payload) . "\n";
}
