<?php
/**
 * AI image context export and import for Content Sync Manager.
 *
 * @package ContentSyncManager
 */

defined('ABSPATH') || exit;

if (!defined('DCA_TB_AI_IMAGE_CONTEXT_MAX_POSTS')) {
    define('DCA_TB_AI_IMAGE_CONTEXT_MAX_POSTS', 50);
}

if (!defined('DCA_TB_AI_IMAGE_CONTEXT_MAX_MEDIA')) {
    define('DCA_TB_AI_IMAGE_CONTEXT_MAX_MEDIA', 100);
}

if (!defined('DCA_TB_AI_IMAGE_CONTEXT_MAX_CONTEXT_CHARS')) {
    define('DCA_TB_AI_IMAGE_CONTEXT_MAX_CONTEXT_CHARS', 5000);
}

if (!defined('DCA_TB_AI_IMAGE_CONTEXT_PREVIEW_MAX')) {
    define('DCA_TB_AI_IMAGE_CONTEXT_PREVIEW_MAX', 512);
}

if (!defined('DCA_TB_AI_IMAGE_CONTEXT_DETAIL_PREVIEW_MAX')) {
    define('DCA_TB_AI_IMAGE_CONTEXT_DETAIL_PREVIEW_MAX', 1024);
}

if (!defined('DCA_TB_AI_IMAGE_CONTEXT_PREVIEW_TTL')) {
    define('DCA_TB_AI_IMAGE_CONTEXT_PREVIEW_TTL', DAY_IN_SECONDS);
}

if (!defined('DCA_TB_AI_IMAGE_CONTEXT_USAGE_SCAN_MAX_POSTS')) {
    define('DCA_TB_AI_IMAGE_CONTEXT_USAGE_SCAN_MAX_POSTS', 2000);
}

function dca_tb_ai_image_context_screen_is_supported() {
    global $pagenow;

    if ($pagenow === 'upload.php') {
        return true;
    }

    if ($pagenow !== 'edit.php') {
        return false;
    }

    $post_type = function_exists('dca_tb_get_admin_post_type') ? dca_tb_get_admin_post_type() : 'post';

    return function_exists('dca_tb_is_supported_post_type') && dca_tb_is_supported_post_type($post_type);
}

function dca_tb_enqueue_ai_image_context_assets() {
    if (!is_admin() || !dca_tb_ai_image_context_screen_is_supported()) {
        return;
    }

    if (!function_exists('dca_tb_current_user_can_use_manager') || !dca_tb_current_user_can_use_manager()) {
        return;
    }

    wp_enqueue_script(
        'dca-tb-ai-image-context',
        DCA_TB_PLUGIN_URL . 'assets/ai-image-context.js',
        [],
        DCA_TB_VERSION . '-ai-image-3',
        true
    );

    global $pagenow;
    wp_localize_script('dca-tb-ai-image-context', 'dcaTbAiImageContextSettings', [
        'nonce'   => wp_create_nonce('dca_acf_textblock_nonce'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'screen'  => $pagenow === 'upload.php' ? 'media' : 'content',
    ]);
}
add_action('admin_enqueue_scripts', 'dca_tb_enqueue_ai_image_context_assets', 20);

function dca_tb_ai_image_context_clean_excerpt($value, $max_chars = 1000) {
    $value = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $value)));

    if ($value === '') {
        return '';
    }

    $max_chars = max(100, absint($max_chars));

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value) > $max_chars ? rtrim(mb_substr($value, 0, $max_chars - 3)) . '...' : $value;
    }

    return strlen($value) > $max_chars ? rtrim(substr($value, 0, $max_chars - 3)) . '...' : $value;
}

function dca_tb_ai_image_context_page_text($post_id, $max_chars = null) {
    $post_id = absint($post_id);
    $post = get_post($post_id);

    if (!$post) {
        return '';
    }

    $parts = [get_the_title($post_id)];

    if (!empty($post->post_excerpt)) {
        $parts[] = $post->post_excerpt;
    }

    if (!empty($post->post_content)) {
        $parts[] = $post->post_content;
    }

    if (function_exists('dca_tb_get_detected_acf_fields')) {
        foreach (dca_tb_get_detected_acf_fields($post_id) as $field) {
            $type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';

            if (in_array($type, ['image', 'file', 'gallery'], true)) {
                continue;
            }

            $value = $field['value'] ?? '';
            if (is_scalar($value)) {
                $parts[] = (string) $value;
            }
        }
    }

    $limit = $max_chars === null ? DCA_TB_AI_IMAGE_CONTEXT_MAX_CONTEXT_CHARS : absint($max_chars);
    return dca_tb_ai_image_context_clean_excerpt(implode("\n", $parts), max(100, $limit));
}

function dca_tb_ai_image_context_preview_location($create = true) {
    $uploads = wp_upload_dir();

    if (!empty($uploads['error']) || empty($uploads['basedir']) || empty($uploads['baseurl'])) {
        return new WP_Error('dca_ai_preview_uploads', 'De WordPress uploads-map is niet beschikbaar voor een tijdelijke AI-preview.');
    }

    $basedir = wp_normalize_path((string) $uploads['basedir']);
    $dir = trailingslashit($basedir) . 'content-sync-ai-previews';

    if ($create && !is_dir($dir) && !wp_mkdir_p($dir)) {
        return new WP_Error('dca_ai_preview_dir', 'De tijdelijke AI-previewmap kon niet worden gemaakt.');
    }

    return [
        'dir' => $dir,
        'url' => trailingslashit((string) $uploads['baseurl']) . 'content-sync-ai-previews/',
        'basedir' => $basedir,
    ];
}

function dca_tb_ai_image_context_schedule_preview_cleanup() {
    if (!wp_next_scheduled('dca_tb_ai_image_context_cleanup_previews')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'dca_tb_ai_image_context_cleanup_previews');
    }
}

function dca_tb_ai_image_context_cleanup_preview_files($remove_all = false) {
    $location = dca_tb_ai_image_context_preview_location(false);

    if (is_wp_error($location) || !is_dir($location['dir'])) {
        return 0;
    }

    $deleted = 0;
    $threshold = time() - absint(DCA_TB_AI_IMAGE_CONTEXT_PREVIEW_TTL);
    $files = glob(trailingslashit($location['dir']) . 'dca-ai-preview-*');

    if (!is_array($files)) {
        return 0;
    }

    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }

        $mtime = filemtime($file);
        if (!$remove_all && $mtime !== false && $mtime > $threshold) {
            continue;
        }

        wp_delete_file($file);
        if (!file_exists($file)) {
            $deleted++;
        }
    }

    return $deleted;
}

function dca_tb_ai_image_context_cleanup_previews() {
    dca_tb_ai_image_context_cleanup_preview_files(false);
}
add_action('dca_tb_ai_image_context_cleanup_previews', 'dca_tb_ai_image_context_cleanup_previews');

function dca_tb_ai_image_context_deactivate() {
    wp_clear_scheduled_hook('dca_tb_ai_image_context_cleanup_previews');
    dca_tb_ai_image_context_cleanup_preview_files(true);
}

if (defined('DCA_TB_PLUGIN_FILE')) {
    register_deactivation_hook(DCA_TB_PLUGIN_FILE, 'dca_tb_ai_image_context_deactivate');
}

function dca_tb_ai_image_context_existing_preview($attachment_id, $max_dimension) {
    $best = null;
    $max_dimension = max(1, absint($max_dimension));
    $sizes = array_unique(array_merge(['medium_large', 'medium', 'thumbnail'], get_intermediate_image_sizes()));

    foreach ($sizes as $size) {
        $preview = wp_get_attachment_image_src($attachment_id, $size);
        if (!$preview || empty($preview[0])) {
            continue;
        }

        $width = isset($preview[1]) ? absint($preview[1]) : 0;
        $height = isset($preview[2]) ? absint($preview[2]) : 0;
        $largest = max($width, $height);

        if ($largest < 1 || $largest > $max_dimension) {
            continue;
        }

        if ($best === null || $largest > max($best['width'], $best['height'])) {
            $best = [
                'url' => esc_url_raw($preview[0]),
                'width' => $width,
                'height' => $height,
                'size' => (string) $size,
                'status' => 'existing-resize',
            ];
        }
    }

    return $best;
}

function dca_tb_ai_image_context_generate_preview($attachment_id, $max_dimension) {
    $attachment_id = absint($attachment_id);
    $max_dimension = max(1, absint($max_dimension));
    $source = $attachment_id ? get_attached_file($attachment_id) : '';

    if (!$source || !is_file($source)) {
        return new WP_Error('dca_ai_preview_source', 'Het lokale bronbestand voor de AI-preview ontbreekt.');
    }

    $location = dca_tb_ai_image_context_preview_location(true);
    if (is_wp_error($location)) {
        return $location;
    }

    $source_real = realpath($source);
    $base_real = realpath($location['basedir']);
    if ($source_real === false || $base_real === false) {
        return new WP_Error('dca_ai_preview_path', 'Het lokale pad van de afbeelding kon niet veilig worden vastgesteld.');
    }

    $source_normalized = wp_normalize_path($source_real);
    $base_normalized = trailingslashit(wp_normalize_path($base_real));
    if (strpos($source_normalized, $base_normalized) !== 0) {
        return new WP_Error('dca_ai_preview_outside_uploads', 'De afbeelding staat buiten de WordPress uploads-map.');
    }

    $extension = strtolower((string) pathinfo($source, PATHINFO_EXTENSION));
    if ($extension === '' || !preg_match('/^[a-z0-9]+$/', $extension)) {
        return new WP_Error('dca_ai_preview_extension', 'De afbeelding heeft geen bruikbare bestandsextensie voor een tijdelijke preview.');
    }

    $mtime = filemtime($source);
    $filesize = filesize($source);
    $fingerprint = substr(hash('sha256', $attachment_id . '|' . (string) $mtime . '|' . (string) $filesize . '|' . $max_dimension), 0, 20);
    $filename = sanitize_file_name('dca-ai-preview-' . $attachment_id . '-' . $max_dimension . '-' . $fingerprint . '.' . $extension);
    $target = trailingslashit($location['dir']) . $filename;

    if (!is_file($target)) {
        $editor = wp_get_image_editor($source);
        if (is_wp_error($editor)) {
            return $editor;
        }

        $size = $editor->get_size();
        $width = !empty($size['width']) ? absint($size['width']) : 0;
        $height = !empty($size['height']) ? absint($size['height']) : 0;

        if (max($width, $height) > $max_dimension) {
            $resized = $editor->resize($max_dimension, $max_dimension, false);
            if (is_wp_error($resized)) {
                return $resized;
            }
        }

        $saved = $editor->save($target);
        if (is_wp_error($saved) || empty($saved['path'])) {
            return is_wp_error($saved) ? $saved : new WP_Error('dca_ai_preview_save', 'De tijdelijke AI-preview kon niet worden opgeslagen.');
        }
    }

    $editor = wp_get_image_editor($target);
    if (is_wp_error($editor)) {
        return $editor;
    }

    $size = $editor->get_size();
    dca_tb_ai_image_context_schedule_preview_cleanup();

    return [
        'url' => esc_url_raw($location['url'] . rawurlencode($filename)),
        'width' => !empty($size['width']) ? absint($size['width']) : 0,
        'height' => !empty($size['height']) ? absint($size['height']) : 0,
        'size' => 'generated-' . $max_dimension,
        'status' => 'generated-preview',
    ];
}

function dca_tb_ai_image_context_preview_for_size($attachment_id, $max_dimension) {
    $generated = dca_tb_ai_image_context_generate_preview($attachment_id, $max_dimension);

    if (!is_wp_error($generated)) {
        return $generated;
    }

    $existing = dca_tb_ai_image_context_existing_preview($attachment_id, $max_dimension);
    if ($existing) {
        return $existing;
    }

    $full = wp_get_attachment_image_src($attachment_id, 'full');
    if ($full && !empty($full[0])) {
        $width = isset($full[1]) ? absint($full[1]) : 0;
        $height = isset($full[2]) ? absint($full[2]) : 0;
        if ($width > 0 && $height > 0 && max($width, $height) <= $max_dimension) {
            return [
                'url' => esc_url_raw($full[0]),
                'width' => $width,
                'height' => $height,
                'size' => 'original-small',
                'status' => 'original-small',
            ];
        }
    }

    return [
        'url' => '',
        'width' => 0,
        'height' => 0,
        'size' => '',
        'status' => 'manual-preview-needed',
    ];
}

function dca_tb_ai_image_context_preview($attachment_id) {
    $primary = dca_tb_ai_image_context_preview_for_size($attachment_id, DCA_TB_AI_IMAGE_CONTEXT_PREVIEW_MAX);
    $detail = dca_tb_ai_image_context_preview_for_size($attachment_id, DCA_TB_AI_IMAGE_CONTEXT_DETAIL_PREVIEW_MAX);

    return [
        'url' => $primary['url'],
        'width' => $primary['width'],
        'height' => $primary['height'],
        'size' => $primary['size'],
        'status' => $primary['status'],
        'detail_url' => $detail['url'],
        'detail_width' => $detail['width'],
        'detail_height' => $detail['height'],
        'detail_size' => $detail['size'],
        'detail_status' => $detail['status'],
    ];
}

function dca_tb_ai_image_context_media_lines($attachment_id) {
    $attachment_id = absint($attachment_id);
    $attachment = get_post($attachment_id);

    if (!$attachment || $attachment->post_type !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
        return [];
    }

    $preview = dca_tb_ai_image_context_preview($attachment_id);
    $metadata = wp_get_attachment_metadata($attachment_id);
    $width = is_array($metadata) && isset($metadata['width']) ? absint($metadata['width']) : 0;
    $height = is_array($metadata) && isset($metadata['height']) ? absint($metadata['height']) : 0;

    return [
        'Huidige URL: ' . esc_url_raw(wp_get_attachment_url($attachment_id)),
        'Afmetingen origineel: ' . $width . 'x' . $height,
        'AI-preview status: ' . $preview['status'],
        'AI-preview URL: ' . $preview['url'],
        'AI-preview afmetingen: ' . $preview['width'] . 'x' . $preview['height'],
        'AI-preview bronformaat: ' . $preview['size'],
        'AI-detailpreview status: ' . $preview['detail_status'],
        'AI-detailpreview URL: ' . $preview['detail_url'],
        'AI-detailpreview afmetingen: ' . $preview['detail_width'] . 'x' . $preview['detail_height'],
        'AI-detailpreview bronformaat: ' . $preview['detail_size'],
        'Huidige bestandsnaam: ' . (function_exists('dca_tb_media_filename') ? dca_tb_media_filename($attachment_id) : ''),
        'Huidige title: ' . dca_tb_ai_image_context_clean_excerpt($attachment->post_title, 1000),
        'Huidige alt: ' . dca_tb_ai_image_context_clean_excerpt(get_post_meta($attachment_id, '_wp_attachment_image_alt', true), 1000),
        'Huidige caption: ' . dca_tb_ai_image_context_clean_excerpt($attachment->post_excerpt, 1500),
        'Huidige description: ' . dca_tb_ai_image_context_clean_excerpt($attachment->post_content, 2000),
        'AI status: [invullen: duidelijk/onzeker/decoratief]',
        'Wat is zichtbaar: [invullen]',
        'Zekerheid: [invullen: hoog/middel/laag]',
        'SEO bestandsnaam zonder extensie: [invullen]',
        'SEO alt: [invullen]',
        'SEO title: [invullen]',
        'SEO caption: [invullen indien nuttig]',
        'SEO description: [invullen indien nuttig]',
    ];
}

function dca_tb_ai_image_context_add_acf_ref(&$refs, $attachment_id, $path, $field_key, $field_type) {
    $attachment_id = absint($attachment_id);
    $path = trim((string) $path);

    if (!$attachment_id || $path === '') {
        return;
    }

    if (!isset($refs[$attachment_id])) {
        $refs[$attachment_id] = [];
    }

    $refs[$attachment_id][$path] = [
        'path' => $path,
        'field_key' => sanitize_key((string) $field_key),
        'field_type' => sanitize_key((string) $field_type),
    ];
}

function dca_tb_ai_image_context_acf_sub_value($row, $sub_field) {
    if (!is_array($row) || !is_array($sub_field)) {
        return null;
    }

    $name = isset($sub_field['name']) ? (string) $sub_field['name'] : '';
    $key = isset($sub_field['key']) ? (string) $sub_field['key'] : '';

    if ($name !== '' && array_key_exists($name, $row)) {
        return $row[$name];
    }

    if ($key !== '' && array_key_exists($key, $row)) {
        return $row[$key];
    }

    return null;
}

function dca_tb_ai_image_context_walk_acf_field($field, $value, $path, &$refs, $depth = 0) {
    if (!is_array($field) || $depth > 20) {
        return;
    }

    $type = isset($field['type']) ? sanitize_key((string) $field['type']) : '';
    $field_key = isset($field['key']) ? sanitize_key((string) $field['key']) : '';
    $sub_fields = !empty($field['sub_fields']) && is_array($field['sub_fields']) ? $field['sub_fields'] : [];

    if (in_array($type, ['image', 'file'], true)) {
        $attachment_id = function_exists('dca_tb_acf_attachment_id_from_value') ? dca_tb_acf_attachment_id_from_value($value) : absint($value);
        dca_tb_ai_image_context_add_acf_ref($refs, $attachment_id, $path, $field_key, $type);
        return;
    }

    if ($type === 'gallery') {
        if (!is_array($value)) {
            return;
        }

        foreach (array_values($value) as $index => $item) {
            $attachment_id = function_exists('dca_tb_acf_attachment_id_from_value') ? dca_tb_acf_attachment_id_from_value($item) : absint($item);
            dca_tb_ai_image_context_add_acf_ref($refs, $attachment_id, $path . '[' . absint($index) . ']', $field_key, $type);
        }
        return;
    }

    if (in_array($type, ['group', 'clone'], true)) {
        if (!is_array($value)) {
            return;
        }

        foreach ($sub_fields as $sub_field) {
            $name = !empty($sub_field['name']) ? sanitize_key((string) $sub_field['name']) : sanitize_key((string) ($sub_field['key'] ?? 'field'));
            if ($name === '') {
                continue;
            }
            dca_tb_ai_image_context_walk_acf_field($sub_field, dca_tb_ai_image_context_acf_sub_value($value, $sub_field), $path . '.' . $name, $refs, $depth + 1);
        }
        return;
    }

    if ($type === 'repeater') {
        if (!is_array($value)) {
            return;
        }

        foreach (array_values($value) as $row_index => $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($sub_fields as $sub_field) {
                $name = !empty($sub_field['name']) ? sanitize_key((string) $sub_field['name']) : sanitize_key((string) ($sub_field['key'] ?? 'field'));
                if ($name === '') {
                    continue;
                }
                dca_tb_ai_image_context_walk_acf_field($sub_field, dca_tb_ai_image_context_acf_sub_value($row, $sub_field), $path . '[' . absint($row_index) . '].' . $name, $refs, $depth + 1);
            }
        }
        return;
    }

    if ($type === 'flexible_content') {
        if (!is_array($value)) {
            return;
        }

        $layouts = !empty($field['layouts']) && is_array($field['layouts']) ? $field['layouts'] : [];
        foreach (array_values($value) as $row_index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $layout_name = !empty($row['acf_fc_layout']) ? sanitize_key((string) $row['acf_fc_layout']) : 'layout';
            $layout_fields = [];
            foreach ($layouts as $layout) {
                if (!is_array($layout) || sanitize_key((string) ($layout['name'] ?? '')) !== $layout_name) {
                    continue;
                }
                $layout_fields = !empty($layout['sub_fields']) && is_array($layout['sub_fields']) ? $layout['sub_fields'] : [];
                break;
            }

            if (!$layout_fields) {
                $layout_fields = $sub_fields;
            }

            foreach ($layout_fields as $sub_field) {
                $name = !empty($sub_field['name']) ? sanitize_key((string) $sub_field['name']) : sanitize_key((string) ($sub_field['key'] ?? 'field'));
                if ($name === '') {
                    continue;
                }

                $child_value = dca_tb_ai_image_context_acf_sub_value($row, $sub_field);
                if ($child_value === null) {
                    continue;
                }

                dca_tb_ai_image_context_walk_acf_field($sub_field, $child_value, $path . '[' . absint($row_index) . ']{' . $layout_name . '}.' . $name, $refs, $depth + 1);
            }
        }
    }
}

function dca_tb_ai_image_context_acf_refs($post_id) {
    $refs = [];

    if (!function_exists('get_field_objects')) {
        return $refs;
    }

    $fields = get_field_objects($post_id, false, true, false);
    if (!is_array($fields)) {
        return $refs;
    }

    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $name = !empty($field['name']) ? sanitize_key((string) $field['name']) : sanitize_key((string) ($field['key'] ?? 'field'));
        if ($name === '') {
            continue;
        }

        dca_tb_ai_image_context_walk_acf_field($field, $field['value'] ?? null, 'acf:' . $name, $refs, 0);
    }

    return $refs;
}

function dca_tb_ai_image_context_post_media_refs($post_id) {
    $refs = function_exists('dca_tb_collect_media_refs') ? dca_tb_collect_media_refs($post_id) : [];
    $exact = dca_tb_ai_image_context_acf_refs($post_id);

    foreach ($exact as $attachment_id => $paths) {
        $attachment_id = absint($attachment_id);
        if (!$attachment_id) {
            continue;
        }

        if (!isset($refs[$attachment_id]) || !is_array($refs[$attachment_id])) {
            $refs[$attachment_id] = ['sources' => []];
        }

        if (!isset($refs[$attachment_id]['sources']) || !is_array($refs[$attachment_id]['sources'])) {
            $refs[$attachment_id]['sources'] = [];
        }

        $refs[$attachment_id]['acf_paths'] = array_values($paths);
        foreach ($paths as $path_data) {
            $path = isset($path_data['path']) ? (string) $path_data['path'] : '';
            if ($path !== '' && !in_array($path, $refs[$attachment_id]['sources'], true)) {
                $refs[$attachment_id]['sources'][] = $path;
            }
        }
    }

    return $refs;
}

function dca_tb_ai_image_context_usage_post_types() {
    $post_types = get_post_types([], 'names');
    $excluded = [
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_font_face',
        'wp_font_family',
    ];

    return array_values(array_diff(array_map('sanitize_key', $post_types), $excluded));
}

function dca_tb_ai_image_context_site_usage_scan($attachment_ids) {
    $attachment_ids = array_values(array_unique(array_filter(array_map('absint', (array) $attachment_ids))));
    $targets = array_fill_keys($attachment_ids, true);
    $usage = [];
    foreach ($attachment_ids as $attachment_id) {
        $usage[$attachment_id] = [];
    }

    if (!$attachment_ids) {
        return [
            'complete' => true,
            'scanned_posts' => 0,
            'limit' => DCA_TB_AI_IMAGE_CONTEXT_USAGE_SCAN_MAX_POSTS,
            'post_types' => [],
            'usage' => $usage,
        ];
    }

    $post_types = dca_tb_ai_image_context_usage_post_types();
    $limit = max(1, absint(DCA_TB_AI_IMAGE_CONTEXT_USAGE_SCAN_MAX_POSTS));
    $query = new WP_Query([
        'post_type' => $post_types,
        'post_status' => 'any',
        'posts_per_page' => $limit + 1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
    ]);

    $post_ids = array_map('absint', (array) $query->posts);
    $complete = count($post_ids) <= $limit;
    $post_ids = array_slice($post_ids, 0, $limit);

    foreach ($post_ids as $post_id) {
        $refs = dca_tb_ai_image_context_post_media_refs($post_id);
        if (!$refs) {
            continue;
        }

        foreach ($refs as $attachment_id => $ref) {
            $attachment_id = absint($attachment_id);
            if (!$attachment_id || !isset($targets[$attachment_id])) {
                continue;
            }

            $post = get_post($post_id);
            if (!$post) {
                continue;
            }

            $sources = !empty($ref['sources']) && is_array($ref['sources']) ? array_values(array_unique(array_map('strval', $ref['sources']))) : [];
            $paths = !empty($ref['acf_paths']) && is_array($ref['acf_paths']) ? array_values($ref['acf_paths']) : [];
            $supported = function_exists('dca_tb_is_supported_post_type') && dca_tb_is_supported_post_type($post->post_type);

            $usage[$attachment_id][] = [
                'post_id' => $post_id,
                'post_type' => sanitize_key((string) $post->post_type),
                'title' => dca_tb_ai_image_context_clean_excerpt(get_the_title($post_id), 300),
                'url' => esc_url_raw(get_permalink($post_id)),
                'sources' => $sources,
                'acf_paths' => $paths,
                'context' => dca_tb_ai_image_context_page_text($post_id, 1400),
                'supported' => $supported,
            ];
        }
    }

    return [
        'complete' => $complete,
        'scanned_posts' => count($post_ids),
        'limit' => $limit,
        'post_types' => $post_types,
        'usage' => $usage,
    ];
}

function dca_tb_ai_image_context_usage_lines($scan, $attachment_id) {
    $attachment_id = absint($attachment_id);
    $entries = isset($scan['usage'][$attachment_id]) && is_array($scan['usage'][$attachment_id]) ? $scan['usage'][$attachment_id] : [];
    $complete = !empty($scan['complete']);
    $lines = [
        'WordPress gebruiksscan: ' . ($complete ? 'compleet' : 'gedeeltelijk'),
        'Gescande contentitems: ' . absint($scan['scanned_posts'] ?? 0),
        'Gebruikslocaties gevonden: ' . count($entries),
        'Gedeeld over meerdere contentitems: ' . (count($entries) > 1 ? 'ja' : 'nee'),
    ];

    $max_entries = 20;
    foreach (array_slice($entries, 0, $max_entries) as $index => $entry) {
        $number = $index + 1;
        $lines[] = 'Gebruik ' . $number . ': Post ID ' . absint($entry['post_id'] ?? 0) . ' | ' . sanitize_key((string) ($entry['post_type'] ?? '')) . ' | ' . dca_tb_ai_image_context_clean_excerpt($entry['title'] ?? '', 300);
        $lines[] = 'Gebruik ' . $number . ' URL: ' . esc_url_raw($entry['url'] ?? '');
        $lines[] = 'Gebruik ' . $number . ' bron: ' . implode(', ', array_map('dca_tb_clean_text', (array) ($entry['sources'] ?? [])));
        $lines[] = 'Gebruik ' . $number . ' context: ' . dca_tb_ai_image_context_clean_excerpt($entry['context'] ?? '', 1400);

        foreach ((array) ($entry['acf_paths'] ?? []) as $path_data) {
            if (!is_array($path_data) || empty($path_data['path'])) {
                continue;
            }
            $lines[] = 'Gebruik ' . $number . ' exact ACF-pad: ' . dca_tb_clean_text($path_data['path']) . ' | field key ' . sanitize_key((string) ($path_data['field_key'] ?? '')) . ' | type ' . sanitize_key((string) ($path_data['field_type'] ?? ''));
        }
    }

    if (count($entries) > $max_entries) {
        $lines[] = 'Extra gebruikslocaties niet uitgeschreven: ' . (count($entries) - $max_entries) . '.';
    }

    if (!$complete) {
        $lines[] = 'Let op: de gebruiksscan bereikte de veiligheidslimiet. Een bestandsnaamwijziging wordt bij import geblokkeerd totdat de scan compleet is.';
    }

    return $lines;
}

function dca_tb_ai_image_context_media_import_block($attachment_id) {
    $attachment_id = absint($attachment_id);
    $attachment = get_post($attachment_id);

    if (!$attachment || $attachment->post_type !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
        return '';
    }

    return trim(implode("\n", [
        'MEDIA IMPORT',
        'Attachment ID:',
        (string) $attachment_id,
        'Bestandsnaam:',
        function_exists('dca_tb_media_filename') ? dca_tb_media_filename($attachment_id) : '',
        'Nieuwe bestandsnaam:',
        '',
        'Title:',
        dca_tb_text($attachment->post_title),
        'Alt text:',
        dca_tb_text(get_post_meta($attachment_id, '_wp_attachment_image_alt', true)),
        'Caption:',
        dca_tb_text($attachment->post_excerpt),
        'Description:',
        dca_tb_text($attachment->post_content),
        'EINDE MEDIA IMPORT',
    ]));
}

function dca_tb_ai_image_context_instruction_block() {
    return implode("\n", [
        'INSTRUCTIE VOOR CHATGPT',
        'Analyseer per afbeelding eerst metadata, exacte ACF-locatie en beschikbare paginacontext.',
        'Gebruik eerst de AI-preview van maximaal 512 px. Gebruik de detailpreview van maximaal 1024 px alleen wanneer details anders onvoldoende zichtbaar zijn.',
        'De preview is een tijdelijke, verkleinde WordPress-kopie zonder onnodige crop; gebruik niet automatisch het volledige origineel.',
        'Benoem alleen wat visueel betrouwbaar is vastgesteld. Gok niet op personen, locaties, merken of eigenschappen.',
        'Is de afbeelding ook met preview niet duidelijk genoeg, zet Status op onzeker en laat bestaande metadata ongemoeid.',
        'Combineer wat zichtbaar is met de functie van de afbeelding binnen de pagina wanneer die context beschikbaar is.',
        'Voor puur decoratieve afbeeldingen mag de geadviseerde alt-tekst leeg zijn.',
        'Bij een gedeelde Attachment ID moet metadata bruikbaar blijven voor alle gemelde pagina\'s; maak geen te paginaspecifieke gedeelde metadata.',
        'Maak waar voldoende zeker een SEO-bestandsnaam zonder extensie, alt-tekst, titel, caption en description.',
        'Wijzig in MEDIA IMPORT uitsluitend Nieuwe bestandsnaam, Title, Alt text, Caption en Description.',
        'Wijzig nooit Attachment ID of Bestandsnaam. Laat het MEDIA IMPORT- en EINDE MEDIA IMPORT-label staan zodat het bestand veilig kan worden teruggeimporteerd.',
        'Bij pagina-export blijft ook het normale Content Sync-importblok beschikbaar voor de bestaande pagina-importflow.',
    ]);
}

function dca_tb_ai_image_context_build_export($post_ids) {
    $post_ids = array_values(array_unique(array_filter(array_map('absint', (array) $post_ids))));
    $post_ids = array_slice($post_ids, 0, DCA_TB_AI_IMAGE_CONTEXT_MAX_POSTS);
    $valid_posts = [];
    $refs_by_post = [];
    $attachment_ids = [];

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post || !function_exists('dca_tb_is_supported_post_type') || !dca_tb_is_supported_post_type($post->post_type)) {
            continue;
        }
        if (function_exists('dca_tb_can_edit_post') && !dca_tb_can_edit_post($post_id)) {
            continue;
        }

        $refs = dca_tb_ai_image_context_post_media_refs($post_id);
        $valid_posts[$post_id] = $post;
        $refs_by_post[$post_id] = $refs;
        $attachment_ids = array_merge($attachment_ids, array_map('absint', array_keys($refs)));
    }

    $scan = dca_tb_ai_image_context_site_usage_scan($attachment_ids);
    $sections = [
        'AI AFBEELDINGSCONTEXT EXPORT',
        'Schema: 3',
        'Gegenereerd: ' . current_time('mysql'),
        '',
        dca_tb_ai_image_context_instruction_block(),
    ];

    foreach ($valid_posts as $post_id => $post) {
        $refs = $refs_by_post[$post_id];
        $sections[] = '';
        $sections[] = '============================================================';
        $sections[] = 'ITEM';
        $sections[] = 'Post ID: ' . $post_id;
        $sections[] = 'Post type: ' . sanitize_key($post->post_type);
        $sections[] = 'Titel: ' . dca_tb_ai_image_context_clean_excerpt(get_the_title($post_id), 500);
        $sections[] = 'URL: ' . esc_url_raw(get_permalink($post_id));
        $sections[] = 'Paginacontext: ' . dca_tb_ai_image_context_page_text($post_id);

        if (!$refs) {
            $sections[] = 'Afbeeldingen: geen lokale WordPress-afbeeldingen gevonden.';
        }

        $index = 1;
        foreach ($refs as $attachment_id => $ref) {
            $attachment_id = absint($attachment_id);
            $media_lines = dca_tb_ai_image_context_media_lines($attachment_id);
            if (!$media_lines) {
                continue;
            }

            $sources = !empty($ref['sources']) && is_array($ref['sources']) ? implode(', ', array_map('dca_tb_clean_text', $ref['sources'])) : '';
            $sections[] = '';
            $sections[] = 'AFBEELDINGSCONTEXT ' . $index;
            $sections[] = 'Attachment ID: ' . $attachment_id;
            $sections[] = 'Bron/ACF-veld: ' . $sources;

            foreach ((array) ($ref['acf_paths'] ?? []) as $path_data) {
                if (!is_array($path_data) || empty($path_data['path'])) {
                    continue;
                }
                $sections[] = 'Exact ACF-pad: ' . dca_tb_clean_text($path_data['path']) . ' | field key ' . sanitize_key((string) ($path_data['field_key'] ?? '')) . ' | type ' . sanitize_key((string) ($path_data['field_type'] ?? ''));
            }

            array_push($sections, ...dca_tb_ai_image_context_usage_lines($scan, $attachment_id));
            array_push($sections, ...$media_lines);
            $sections[] = '';
            $sections[] = dca_tb_ai_image_context_media_import_block($attachment_id);
            $index++;
        }

        if (function_exists('dca_tb_build_textblock')) {
            $sections[] = '';
            $sections[] = 'IMPORTBLOK VOOR DEZE PAGINA';
            $sections[] = 'Gebruik dit blok als basis voor de normale pagina-import. Verwijder deze labelregel uit het uiteindelijke pagina-importbestand.';
            $sections[] = dca_tb_build_textblock($post_id);
        }
    }

    return trim(implode("\n", $sections));
}

function dca_tb_ai_image_context_build_media_export($attachment_ids) {
    $attachment_ids = array_values(array_unique(array_filter(array_map('absint', (array) $attachment_ids))));
    $attachment_ids = array_slice($attachment_ids, 0, DCA_TB_AI_IMAGE_CONTEXT_MAX_MEDIA);
    $scan = dca_tb_ai_image_context_site_usage_scan($attachment_ids);
    $sections = [
        'AI AFBEELDINGSCONTEXT MEDIA-EXPORT',
        'Schema: 3',
        'Gegenereerd: ' . current_time('mysql'),
        '',
        dca_tb_ai_image_context_instruction_block(),
    ];
    $exported = 0;

    foreach ($attachment_ids as $attachment_id) {
        $attachment = get_post($attachment_id);
        $media_lines = dca_tb_ai_image_context_media_lines($attachment_id);

        if (!$attachment || !$media_lines || !current_user_can('edit_post', $attachment_id)) {
            continue;
        }

        $parent_id = absint($attachment->post_parent);
        $parent = $parent_id ? get_post($parent_id) : null;

        $sections[] = '';
        $sections[] = '============================================================';
        $sections[] = 'MEDIA ITEM ' . ($exported + 1);
        $sections[] = 'Attachment ID: ' . $attachment_id;
        $sections[] = 'Bron: geselecteerd in WordPress Media Library';
        $sections[] = 'Upload-parent Post ID: ' . ($parent ? $parent_id : '');
        $sections[] = 'Upload-parent titel: ' . ($parent ? dca_tb_ai_image_context_clean_excerpt(get_the_title($parent_id), 500) : '');
        $sections[] = 'Upload-parent URL: ' . ($parent ? esc_url_raw(get_permalink($parent_id)) : '');
        $sections[] = 'Upload-parent context: ' . ($parent ? dca_tb_ai_image_context_page_text($parent_id) : '');
        array_push($sections, ...dca_tb_ai_image_context_usage_lines($scan, $attachment_id));
        array_push($sections, ...$media_lines);
        $sections[] = '';
        $sections[] = dca_tb_ai_image_context_media_import_block($attachment_id);
        $exported++;
    }

    return $exported > 0 ? trim(implode("\n", $sections)) : '';
}

function dca_tb_ai_image_context_parse_media_import($text) {
    $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
    preg_match_all('/^MEDIA IMPORT\s*$\n(.*?)^EINDE MEDIA IMPORT\s*$/ims', $text, $matches);
    $blocks = isset($matches[1]) && is_array($matches[1]) ? $matches[1] : [];

    if (!$blocks) {
        return new WP_Error('dca_ai_media_import_missing', 'Geen MEDIA IMPORT-blokken gevonden. Gebruik een AI-media-export van Content Sync Manager.');
    }

    if (count($blocks) > DCA_TB_AI_IMAGE_CONTEXT_MAX_MEDIA) {
        return new WP_Error('dca_ai_media_import_limit', 'Het bestand bevat meer media-items dan per import zijn toegestaan.');
    }

    $items = [];
    $seen = [];
    $labels = ['Attachment ID:', 'Bestandsnaam:', 'Nieuwe bestandsnaam:', 'Title:', 'Alt text:', 'Caption:', 'Description:'];

    foreach ($blocks as $block) {
        foreach ($labels as $label) {
            if (dca_tb_label_marker_count($block, $label) !== 1) {
                return new WP_Error('dca_ai_media_import_label', 'MEDIA IMPORT is ongeldig: "' . $label . '" ontbreekt of komt meerdere keren voor.');
            }
        }

        $item = [
            'attachment_id' => absint(dca_tb_label($block, 'Attachment ID:', ['Bestandsnaam:'])),
            'filename' => dca_tb_label($block, 'Bestandsnaam:', ['Nieuwe bestandsnaam:']),
            'new_filename' => dca_tb_label($block, 'Nieuwe bestandsnaam:', ['Title:']),
            'title' => dca_tb_label($block, 'Title:', ['Alt text:']),
            'alt' => dca_tb_label($block, 'Alt text:', ['Caption:']),
            'caption' => dca_tb_label($block, 'Caption:', ['Description:']),
            'description' => dca_tb_label($block, 'Description:'),
        ];

        $attachment_id = absint($item['attachment_id']);
        if (!$attachment_id) {
            return new WP_Error('dca_ai_media_import_id', 'MEDIA IMPORT bevat een ongeldig Attachment ID.');
        }
        if (isset($seen[$attachment_id])) {
            return new WP_Error('dca_ai_media_import_duplicate', 'Attachment ID #' . $attachment_id . ' komt meerdere keren voor in MEDIA IMPORT.');
        }

        $seen[$attachment_id] = true;
        $items[] = $item;
    }

    return $items;
}

function dca_tb_ai_image_context_media_import_changes($attachment_id, $item) {
    $attachment = get_post($attachment_id);
    if (!$attachment) {
        return 0;
    }

    $changes = 0;
    $current_filename = function_exists('dca_tb_media_filename') ? dca_tb_media_filename($attachment_id) : '';
    $new_filename = trim((string) ($item['new_filename'] ?? ''));

    if ($new_filename !== '') {
        $prepared = function_exists('dca_tb_prepare_new_media_filename') ? dca_tb_prepare_new_media_filename($current_filename, $new_filename) : $new_filename;
        if (!is_wp_error($prepared) && $prepared !== $current_filename) {
            $changes++;
        }
    }

    if (dca_tb_clean_text($item['title'] ?? '') !== dca_tb_clean_text($attachment->post_title)) {
        $changes++;
    }
    if (dca_tb_clean_text($item['alt'] ?? '') !== dca_tb_clean_text(get_post_meta($attachment_id, '_wp_attachment_image_alt', true))) {
        $changes++;
    }
    if (dca_tb_clean_text($item['caption'] ?? '') !== dca_tb_clean_text($attachment->post_excerpt)) {
        $changes++;
    }
    if (dca_tb_clean_html($item['description'] ?? '') !== dca_tb_clean_html($attachment->post_content)) {
        $changes++;
    }

    return $changes;
}

function dca_tb_ai_image_context_preview_media_import($text) {
    $items = dca_tb_ai_image_context_parse_media_import($text);
    if (is_wp_error($items)) {
        return $items;
    }

    $attachment_ids = array_map(static function ($item) {
        return absint($item['attachment_id'] ?? 0);
    }, $items);
    $scan = dca_tb_ai_image_context_site_usage_scan($attachment_ids);
    $preview = [];
    $importable = 0;
    $changes_total = 0;
    $errors = 0;
    $rename_blocked = 0;

    foreach ($items as $item) {
        $attachment_id = absint($item['attachment_id'] ?? 0);
        $attachment = get_post($attachment_id);
        $status = 'success';
        $message = 'Klaar voor import.';
        $rename_allowed = true;
        $rename_requested = trim((string) ($item['new_filename'] ?? '')) !== '';

        if (!$attachment || $attachment->post_type !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
            $status = 'error';
            $message = 'Attachment ontbreekt of is geen afbeelding.';
        } elseif (!current_user_can('edit_post', $attachment_id)) {
            $status = 'error';
            $message = 'Geen rechten om deze afbeelding te wijzigen.';
        } else {
            $current_filename = function_exists('dca_tb_media_filename') ? dca_tb_media_filename($attachment_id) : '';
            if ((string) ($item['filename'] ?? '') !== $current_filename) {
                $status = 'error';
                $message = 'Bestandsnaam is gewijzigd sinds export; maak een nieuwe export voordat je importeert.';
            }

            if ($status !== 'error' && $rename_requested) {
                $prepared = function_exists('dca_tb_prepare_new_media_filename') ? dca_tb_prepare_new_media_filename($current_filename, $item['new_filename']) : $item['new_filename'];
                if (is_wp_error($prepared)) {
                    $status = 'error';
                    $message = $prepared->get_error_message();
                } else {
                    $usage_entries = isset($scan['usage'][$attachment_id]) ? (array) $scan['usage'][$attachment_id] : [];
                    $unsupported_usage = false;
                    foreach ($usage_entries as $entry) {
                        if (empty($entry['supported'])) {
                            $unsupported_usage = true;
                            break;
                        }
                    }

                    if (empty($scan['complete']) || $unsupported_usage) {
                        $rename_allowed = false;
                        $rename_blocked++;
                        $status = 'partial';
                        $message = empty($scan['complete'])
                            ? 'Metadata kan worden bijgewerkt, maar bestandsnaam hernoemen is geblokkeerd omdat de WordPress gebruiksscan niet compleet is.'
                            : 'Metadata kan worden bijgewerkt, maar bestandsnaam hernoemen is geblokkeerd omdat de afbeelding ook in een niet-ondersteund contenttype wordt gebruikt.';
                    }
                }
            }
        }

        $changes = $status === 'error' ? 0 : dca_tb_ai_image_context_media_import_changes($attachment_id, $item);
        if ($status === 'error') {
            $errors++;
        } else {
            $importable++;
            $changes_total += $changes;
        }

        $preview[] = [
            'attachment_id' => $attachment_id,
            'status' => $status,
            'message' => $message,
            'changes' => $changes,
            'rename_requested' => $rename_requested,
            'rename_allowed' => $rename_allowed,
        ];
    }

    return [
        'items' => $preview,
        'parsed_items' => $items,
        'usage_scan' => $scan,
        'importable' => $importable,
        'changes' => $changes_total,
        'errors' => $errors,
        'rename_blocked' => $rename_blocked,
    ];
}

function dca_tb_ai_image_context_apply_media_import($text) {
    $preview = dca_tb_ai_image_context_preview_media_import($text);
    if (is_wp_error($preview)) {
        return $preview;
    }

    $preview_by_id = [];
    foreach ($preview['items'] as $row) {
        $preview_by_id[absint($row['attachment_id'] ?? 0)] = $row;
    }

    $result = [
        'updated' => 0,
        'renamed' => 0,
        'skipped' => 0,
        'errors' => 0,
        'url_replaces' => 0,
        'rename_blocked' => 0,
        'messages' => [],
    ];

    foreach ($preview['parsed_items'] as $item) {
        $attachment_id = absint($item['attachment_id'] ?? 0);
        $row = $preview_by_id[$attachment_id] ?? null;

        if (!$row || ($row['status'] ?? '') === 'error') {
            $result['errors']++;
            $result['messages'][] = 'Attachment #' . $attachment_id . ' overgeslagen: ' . (string) ($row['message'] ?? 'ongeldige import.');
            continue;
        }

        if (empty($row['changes'])) {
            $result['skipped']++;
            continue;
        }

        $rename_requested = !empty($row['rename_requested']);
        $rename_allowed = !empty($row['rename_allowed']);
        $write_item = $item;
        if ($rename_requested && !$rename_allowed) {
            $write_item['new_filename'] = '';
            $result['rename_blocked']++;
        }

        $usage_entries = isset($preview['usage_scan']['usage'][$attachment_id]) ? (array) $preview['usage_scan']['usage'][$attachment_id] : [];
        if ($rename_requested && $rename_allowed && function_exists('dca_tb_add_backup')) {
            foreach ($usage_entries as $entry) {
                if (!empty($entry['supported']) && !empty($entry['post_id'])) {
                    dca_tb_add_backup(absint($entry['post_id']), 'ai-media-import');
                }
            }
        }

        $replace_pairs = [];
        $updated = dca_tb_update_attachment_from_media_item($attachment_id, $write_item, $replace_pairs, 'ai-media-import', false);
        if (is_wp_error($updated)) {
            $result['errors']++;
            $result['messages'][] = 'Attachment #' . $attachment_id . ' overgeslagen: ' . $updated->get_error_message();
            continue;
        }

        $result['updated']++;
        if (!empty($updated['renamed'])) {
            $result['renamed']++;
        }

        if (!empty($replace_pairs) && $rename_allowed && function_exists('dca_tb_replace_media_urls_on_page')) {
            foreach ($usage_entries as $entry) {
                if (!empty($entry['supported']) && !empty($entry['post_id'])) {
                    $result['url_replaces'] += absint(dca_tb_replace_media_urls_on_page(absint($entry['post_id']), $replace_pairs));
                }
            }
        }
    }

    return $result;
}

function dca_tb_ai_image_context_validate_import_text($text) {
    if (function_exists('dca_tb_validate_import_size')) {
        return dca_tb_validate_import_size($text);
    }

    if (strlen((string) $text) > 5242880) {
        return new WP_Error('dca_ai_media_import_size', 'Het importbestand is te groot.');
    }

    return true;
}

function dca_tb_ajax_ai_image_context_export() {
    if (!function_exists('dca_tb_require_ajax_access')) {
        wp_send_json_error(['message' => 'Content Sync Manager is niet volledig geladen.'], 500);
    }

    dca_tb_require_ajax_access();

    $scope = isset($_POST['scope']) ? sanitize_key(wp_unslash($_POST['scope'])) : 'content';
    $raw_ids = isset($_POST['ids']) ? wp_unslash($_POST['ids']) : [];
    if (!is_array($raw_ids)) {
        wp_send_json_error(['message' => 'Ongeldige selectie.'], 400);
    }

    $ids = array_values(array_unique(array_filter(array_map('absint', $raw_ids))));

    if ($scope === 'media') {
        if (!$ids) {
            wp_send_json_error(['message' => 'Selecteer minimaal een afbeelding in Media.'], 400);
        }

        if (count($ids) > DCA_TB_AI_IMAGE_CONTEXT_MAX_MEDIA) {
            wp_send_json_error(['message' => 'Selecteer maximaal ' . DCA_TB_AI_IMAGE_CONTEXT_MAX_MEDIA . ' afbeeldingen per AI-context-export.'], 400);
        }

        $text = dca_tb_ai_image_context_build_media_export($ids);
        if ($text === '') {
            wp_send_json_error(['message' => 'De selectie bevat geen exporteerbare lokale WordPress-afbeeldingen.'], 422);
        }

        wp_send_json_success([
            'text' => $text,
            'filename' => 'content-sync-ai-media-' . current_time('Y-m-d-His') . '.txt',
        ]);
    }

    if (!$ids) {
        wp_send_json_error(['message' => 'Selecteer minimaal een pagina, bericht of product.'], 400);
    }

    if (count($ids) > DCA_TB_AI_IMAGE_CONTEXT_MAX_POSTS) {
        wp_send_json_error(['message' => 'Selecteer maximaal ' . DCA_TB_AI_IMAGE_CONTEXT_MAX_POSTS . ' items per AI-context-export.'], 400);
    }

    $text = dca_tb_ai_image_context_build_export($ids);
    if ($text === '') {
        wp_send_json_error(['message' => 'Voor deze selectie kon geen AI-afbeeldingscontext worden gemaakt.'], 422);
    }

    wp_send_json_success([
        'text' => $text,
        'filename' => 'content-sync-ai-images-' . current_time('Y-m-d-His') . '.txt',
    ]);
}
add_action('wp_ajax_dca_ai_image_context_export', 'dca_tb_ajax_ai_image_context_export');

function dca_tb_ajax_ai_image_context_import_preview() {
    if (!function_exists('dca_tb_require_ajax_access')) {
        wp_send_json_error(['message' => 'Content Sync Manager is niet volledig geladen.'], 500);
    }

    dca_tb_require_ajax_access();
    $text = isset($_POST['text']) ? wp_unslash((string) $_POST['text']) : '';
    $size_check = dca_tb_ai_image_context_validate_import_text($text);
    if (is_wp_error($size_check)) {
        wp_send_json_error(['message' => $size_check->get_error_message()], 400);
    }

    $preview = dca_tb_ai_image_context_preview_media_import($text);
    if (is_wp_error($preview)) {
        wp_send_json_error(['message' => $preview->get_error_message()], 422);
    }

    $preview_hash = dca_tb_mark_import_previewed($text, $preview['items']);
    wp_send_json_success([
        'preview_hash' => $preview_hash,
        'importable' => absint($preview['importable']),
        'changes' => absint($preview['changes']),
        'errors' => absint($preview['errors']),
        'rename_blocked' => absint($preview['rename_blocked']),
        'items' => $preview['items'],
    ]);
}
add_action('wp_ajax_dca_ai_image_context_import_preview', 'dca_tb_ajax_ai_image_context_import_preview');

function dca_tb_ajax_ai_image_context_import_run() {
    if (!function_exists('dca_tb_require_ajax_access')) {
        wp_send_json_error(['message' => 'Content Sync Manager is niet volledig geladen.'], 500);
    }

    dca_tb_require_ajax_access();
    $text = isset($_POST['text']) ? wp_unslash((string) $_POST['text']) : '';
    $size_check = dca_tb_ai_image_context_validate_import_text($text);
    if (is_wp_error($size_check)) {
        wp_send_json_error(['message' => $size_check->get_error_message()], 400);
    }

    dca_tb_require_matching_import_preview($text);
    dca_tb_require_destructive_confirmation();

    $result = dca_tb_ai_image_context_apply_media_import($text);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 422);
    }

    wp_send_json_success([
        'message' => 'AI-media-import voltooid: ' . absint($result['updated']) . ' media-items bijgewerkt, ' . absint($result['renamed']) . ' bestandsnamen hernoemd, ' . absint($result['rename_blocked']) . ' hernoemingen veilig geblokkeerd, ' . absint($result['errors']) . ' fouten.',
        'updated' => absint($result['updated']),
        'renamed' => absint($result['renamed']),
        'skipped' => absint($result['skipped']),
        'errors' => absint($result['errors']),
        'url_replaces' => absint($result['url_replaces']),
        'rename_blocked' => absint($result['rename_blocked']),
        'messages' => $result['messages'],
    ]);
}
add_action('wp_ajax_dca_ai_image_context_import_run', 'dca_tb_ajax_ai_image_context_import_run');
