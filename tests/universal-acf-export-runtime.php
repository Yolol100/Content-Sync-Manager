<?php
/**
 * Runtime regression test for universal ACF export coverage and bulk item limit.
 * Run through WP-CLI eval-file after the release ZIP is installed.
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
$assert(function_exists('dca_tb_universal_build_bulk_export'), 'Universal ACF export builder is missing.');
$assert(function_exists('dca_tb_universal_build_term_textblock'), 'Universal term ACF export builder is missing.');
$assert(defined('DCA_TB_MAX_IMPORT_PAGES') && DCA_TB_MAX_IMPORT_PAGES === PHP_INT_MAX, 'The 50-item bulk limit is still active.');
$assert(function_exists('acf_add_local_field_group') && function_exists('update_field'), 'ACF is not active.');

$fields = [];
for ($i = 1; $i <= 64; $i++) {
    $name = $i <= 4 ? 'portfolio_stat_' . $i . '_label' : 'dca_universal_field_' . $i;
    $fields[] = [
        'key'   => 'field_dca_universal_post_' . $i,
        'label' => 'Universal field ' . $i,
        'name'  => $name,
        'type'  => 'text',
    ];
}

acf_add_local_field_group([
    'key'      => 'group_dca_universal_post',
    'title'    => 'Universal ACF post export',
    'fields'   => $fields,
    'location' => [[[
        'param'    => 'post_type',
        'operator' => '==',
        'value'    => 'post',
    ]]],
]);

$post_id = wp_insert_post([
    'post_type'    => 'post',
    'post_status'  => 'publish',
    'post_title'   => 'Universal ACF export runtime post',
    'post_content' => 'Runtime post content',
], true);
$assert(!is_wp_error($post_id) && $post_id > 0, 'Unable to create runtime post.');

foreach ($fields as $index => $field) {
    $value = 'universal-post-value-' . ($index + 1);
    update_field($field['key'], $value, $post_id);
}

$post_export = dca_tb_universal_build_bulk_export([$post_id], 'post');
$assert(!is_wp_error($post_export), 'Universal post export failed.');
$assert(strpos($post_export, 'ACF VELDEN') !== false, 'Post export is missing the ACF VELDEN block.');
$assert(substr_count($post_export, '--- ACF VELD ---') >= 64, 'Post export did not include all 64 ACF value fields.');
for ($i = 1; $i <= 4; $i++) {
    $assert(strpos($post_export, 'Naam: portfolio_stat_' . $i . '_label') !== false, 'Post export is missing portfolio_stat_' . $i . '_label.');
    $assert(strpos($post_export, 'universal-post-value-' . $i) !== false, 'Post export is missing the value for portfolio_stat_' . $i . '_label.');
}
$assert(strpos($post_export, 'Naam: dca_universal_field_64') !== false, 'Post export stopped before the final dynamically registered ACF field.');
$assert(strpos($post_export, 'universal-post-value-64') !== false, 'Post export is missing the final dynamically registered ACF value.');

acf_add_local_field_group([
    'key'   => 'group_dca_universal_category',
    'title' => 'Universal ACF category export',
    'fields' => [[
        'key'   => 'field_dca_universal_category_label',
        'label' => 'Category result label',
        'name'  => 'portfolio_category_label',
        'type'  => 'text',
    ]],
    'location' => [[[
        'param'    => 'taxonomy',
        'operator' => '==',
        'value'    => 'category',
    ]]],
]);

$term = wp_insert_term('Universal ACF runtime category', 'category');
$assert(!is_wp_error($term) && !empty($term['term_id']), 'Unable to create runtime category.');
$term_id = (int) $term['term_id'];
$term_context = dca_tb_universal_acf_term_context($term_id, 'category');
$assert($term_context !== '', 'Unable to resolve the ACF term context.');
update_field('field_dca_universal_category_label', 'universal-category-value', $term_context);

$term_export = dca_tb_universal_build_bulk_export([$term_id], 'term', 'category');
$assert(!is_wp_error($term_export), 'Universal category export failed.');
$assert(strpos($term_export, 'ACF VELDEN') !== false, 'Category export is missing the ACF VELDEN block.');
$assert(strpos($term_export, 'Naam: portfolio_category_label') !== false, 'Category export is missing the ACF field name.');
$assert(strpos($term_export, 'universal-category-value') !== false, 'Category export is missing the ACF field value.');

$assert(has_action('wp_ajax_dca_get_acf_textblock', 'dca_tb_universal_ajax_get_acf_textblock') !== false, 'Single-item AJAX export is not using the universal ACF route.');
$assert(has_action('wp_ajax_dca_bulk_get_acf_textblocks', 'dca_tb_universal_ajax_bulk_get_acf_textblocks') !== false, 'Bulk AJAX export is not using the universal ACF route.');

wp_delete_post($post_id, true);
wp_delete_term($term_id, 'category');

echo wp_json_encode([
    'post_acf_fields' => substr_count($post_export, '--- ACF VELD ---'),
    'category_acf_exported' => true,
    'bulk_limit' => DCA_TB_MAX_IMPORT_PAGES,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
