<?php
/**
 * Universal ACF export support for posts, pages, products and terms.
 *
 * @package ContentSyncManager
 */

defined('ABSPATH') || exit;

/**
 * Resolve the canonical ACF storage context for a taxonomy term.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy slug.
 * @return string
 */
function dca_tb_universal_acf_term_context($term_id, $taxonomy) {
    $term_id = absint($term_id);
    $taxonomy = sanitize_key((string) $taxonomy);
    $term = ($term_id && $taxonomy !== '') ? get_term($term_id, $taxonomy) : null;

    if (!$term || is_wp_error($term)) {
        return '';
    }

    if (function_exists('acf_get_valid_post_id')) {
        $context = acf_get_valid_post_id($term);
        if (is_string($context) && $context !== '') {
            return $context;
        }
    }

    // Backwards-compatible ACF taxonomy-term context.
    return $taxonomy . '_' . $term_id;
}

/**
 * Get every exportable ACF field attached to a taxonomy term.
 *
 * Layout-only ACF field types remain excluded by dca_tb_is_exportable_acf_field().
 * Group, repeater and flexible-content values are exported by the existing
 * dca_tb_acf_value_to_text() JSON serializer, so nested values are preserved.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy slug.
 * @return array
 */
function dca_tb_universal_get_detected_term_acf_fields($term_id, $taxonomy) {
    $term_id = absint($term_id);
    $taxonomy = sanitize_key((string) $taxonomy);
    $context = dca_tb_universal_acf_term_context($term_id, $taxonomy);

    if ($context === '' || !function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
        return [];
    }

    $fields = [];
    $seen = [];
    $groups = acf_get_field_groups(['post_id' => $context]);

    if (is_array($groups)) {
        foreach ($groups as $group) {
            $group_fields = acf_get_fields($group);
            if (!is_array($group_fields)) {
                continue;
            }

            foreach ($group_fields as $field) {
                dca_tb_add_detected_acf_field($fields, $seen, $field, $context);
            }
        }
    }

    // get_field_objects() also catches fields present on the term when a group
    // cannot be resolved through the location matcher alone.
    if (function_exists('get_field_objects')) {
        $objects = get_field_objects($context, false, true);

        if (!is_array($objects)) {
            $objects = get_field_objects($context, true, true);
        }

        if (is_array($objects)) {
            foreach ($objects as $field) {
                dca_tb_add_detected_acf_field($fields, $seen, $field, $context);
            }
        }
    }

    return $fields;
}

/**
 * Build the standard ACF VELDEN block for a taxonomy term.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy slug.
 * @return string
 */
function dca_tb_universal_build_term_acf_fields_block($term_id, $taxonomy) {
    $fields = dca_tb_universal_get_detected_term_acf_fields($term_id, $taxonomy);
    $out = ['ACF VELDEN'];

    if (empty($fields)) {
        $out[] = '';
        $out[] = 'Geen ACF-velden gedetecteerd voor deze term.';
        return implode("\n", $out);
    }

    foreach ($fields as $field) {
        $out[] = '';
        $out[] = '--- ACF VELD ---';
        $out[] = 'Naam: ' . $field['name'];
        $out[] = 'Label: ' . $field['label'];
        $out[] = 'Key: ' . $field['key'];
        $out[] = 'Type: ' . $field['type'];
        $out[] = 'Waarde:';
        $out[] = dca_tb_acf_value_to_text($field['value'], $field['type']);
        $out[] = '--- EINDE ACF VELD ---';
    }

    return trim(implode("\n", $out));
}

/**
 * Export one supported post object with ACF fields included for every post type.
 *
 * Pages, products and supported custom post types already emit ACF VELDEN from
 * dca_tb_build_textblock(). Posts did not. For posts the ACF block is prepended
 * so legacy post import parsing cannot accidentally absorb it into CONTENT.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function dca_tb_universal_build_post_textblock($post_id) {
    $post_id = absint($post_id);
    $text = dca_tb_build_textblock($post_id);

    if (!function_exists('get_field_objects') || dca_tb_marker_count($text, 'ACF VELDEN') > 0) {
        return $text;
    }

    return trim(dca_tb_build_acf_fields_block($post_id) . "\n\n" . $text);
}

/**
 * Export one supported taxonomy term with all detected ACF fields.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy slug.
 * @return string
 */
function dca_tb_universal_build_term_textblock($term_id, $taxonomy) {
    $term_id = absint($term_id);
    $taxonomy = sanitize_key((string) $taxonomy);
    $text = dca_tb_build_term_textblock($term_id, $taxonomy);

    if (!function_exists('get_field_objects') || dca_tb_marker_count($text, 'ACF VELDEN') > 0) {
        return $text;
    }

    return trim(dca_tb_universal_build_term_acf_fields_block($term_id, $taxonomy) . "\n\n" . $text);
}

/**
 * Build bulk export using the universal ACF-aware builders.
 *
 * @param array  $object_ids  Post or term IDs.
 * @param string $object_type post or term.
 * @param string $taxonomy    Taxonomy for terms.
 * @return string|WP_Error
 */
function dca_tb_universal_build_bulk_export($object_ids, $object_type = 'post', $taxonomy = '') {
    $out = [];
    $object_type = sanitize_key((string) $object_type);
    $taxonomy = sanitize_key((string) $taxonomy);

    foreach ((array) $object_ids as $object_id) {
        $object_id = absint($object_id);

        if ($object_type === 'term') {
            $term = $object_id ? get_term($object_id, $taxonomy) : null;

            if (!$term || is_wp_error($term) || !dca_tb_is_supported_taxonomy($taxonomy) || !dca_tb_can_edit_term($object_id, $taxonomy)) {
                continue;
            }

            $term_link = get_term_link($term, $taxonomy);
            array_push(
                $out,
                str_repeat('=', 80),
                dca_tb_taxonomy_label_single($taxonomy) . ': ' . $term->name,
                'URL: ' . (!is_wp_error($term_link) ? $term_link : ''),
                'ID: ' . $object_id,
                'Object type: term',
                'Taxonomy: ' . $taxonomy,
                str_repeat('=', 80),
                '',
                dca_tb_universal_build_term_textblock($object_id, $taxonomy),
                '',
                ''
            );
            continue;
        }

        $post = $object_id ? get_post($object_id) : null;

        if (!$post || !dca_tb_is_supported_post_type($post->post_type) || !current_user_can('edit_post', $object_id)) {
            continue;
        }

        if ($post->post_type === 'page' && dca_tb_template_skip_reason($object_id) !== '') {
            continue;
        }

        array_push(
            $out,
            str_repeat('=', 80),
            dca_tb_post_type_label_single($object_id) . ': ' . get_the_title($object_id),
            'URL: ' . get_permalink($object_id),
            'ID: ' . $object_id,
            'Object type: post',
            'Post type: ' . $post->post_type,
            str_repeat('=', 80),
            '',
            dca_tb_universal_build_post_textblock($object_id),
            '',
            ''
        );
    }

    if (!empty($out)) {
        return trim(implode("\n", $out));
    }

    if ($object_type === 'term') {
        return new WP_Error('dca_no_terms', 'Er zijn geen geldige categorieën of productcategorieën geselecteerd.');
    }

    return new WP_Error('dca_no_pages', 'Er zijn geen geldige berichten, pagina’s of producten geselecteerd.');
}

/**
 * Replacement single-item export route with universal ACF coverage.
 *
 * @return void
 */
function dca_tb_universal_ajax_get_acf_textblock() {
    dca_tb_require_ajax_access();
    $object_type = dca_tb_get_request_object_type();

    if ($object_type === 'term') {
        $term_id = dca_tb_post_int('term_id');
        $taxonomy = sanitize_key(dca_tb_post_text('taxonomy'));
        $term = get_term($term_id, $taxonomy);

        if (!dca_tb_can_edit_term($term_id, $taxonomy) || !$term || is_wp_error($term)) {
            wp_send_json_error(['message' => 'Geen toegang tot deze categorie.']);
        }

        $term_link = get_term_link($term, $taxonomy);
        wp_send_json_success([
            'title'    => $term->name,
            'text'     => dca_tb_universal_build_term_textblock($term_id, $taxonomy),
            'view_url' => !is_wp_error($term_link) ? $term_link : '',
        ]);
    }

    $post_id = dca_tb_post_int('post_id');

    if (!dca_tb_can_edit_post($post_id)) {
        wp_send_json_error(['message' => 'Geen toegang tot deze pagina.']);
    }

    wp_send_json_success([
        'title'    => get_the_title($post_id),
        'text'     => dca_tb_universal_build_post_textblock($post_id),
        'view_url' => get_permalink($post_id),
    ]);
}

/**
 * Replacement bulk export route with universal ACF coverage.
 *
 * @return void
 */
function dca_tb_universal_ajax_bulk_get_acf_textblocks() {
    dca_tb_require_ajax_access();
    $object_type = dca_tb_get_request_object_type();
    $taxonomy = sanitize_key(dca_tb_post_text('taxonomy'));
    $object_ids = dca_tb_post_id_list('object_ids');

    if (!$object_ids) {
        $object_ids = dca_tb_post_id_list('post_ids');
    }

    if (!$object_ids) {
        wp_send_json_error(['message' => 'Selecteer eerst één of meerdere items.']);
    }

    if (count($object_ids) > DCA_TB_MAX_IMPORT_PAGES) {
        wp_send_json_error(['message' => 'Export bevat ' . count($object_ids) . ' items. Maximaal toegestaan: ' . absint(DCA_TB_MAX_IMPORT_PAGES) . '.']);
    }

    $text = dca_tb_universal_build_bulk_export($object_ids, $object_type, $taxonomy);

    if (is_wp_error($text)) {
        wp_send_json_error(['message' => $text->get_error_message()]);
    }

    wp_send_json_success([
        'text'     => $text,
        'filename' => 'content-sync-' . date_i18n('Y-m-d-H-i', current_time('timestamp')) . '.txt',
    ]);
}

// Replace only the two read-only export routes. Save/import/restore handlers remain
// on the existing guarded code paths, so this change cannot widen write access.
remove_all_actions('wp_ajax_dca_get_acf_textblock');
remove_all_actions('wp_ajax_dca_bulk_get_acf_textblocks');
add_action('wp_ajax_dca_get_acf_textblock', 'dca_tb_universal_ajax_get_acf_textblock');
add_action('wp_ajax_dca_bulk_get_acf_textblocks', 'dca_tb_universal_ajax_bulk_get_acf_textblocks');
