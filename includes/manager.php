<?php
/**
 * Admin TXT import/export logic for content fields.
 *
 * @package ContentSyncManager
 */

defined('ABSPATH') || exit;

/**
 * Content Sync Manager + USP + Yoast + Media Rename + Compacte Bulkeditor.
 */

if (!defined('DCA_TB_ALLOW_MEDIA_FILE_RENAME')) {
    define('DCA_TB_ALLOW_MEDIA_FILE_RENAME', false);
}

if (!defined('DCA_TB_DELETE_OLD_IMAGE_SIZES_AFTER_RENAME')) {
    define('DCA_TB_DELETE_OLD_IMAGE_SIZES_AFTER_RENAME', false);
}

if (!defined('DCA_TB_AUTO_UNIQUE_RENAMED_FILES')) {
    define('DCA_TB_AUTO_UNIQUE_RENAMED_FILES', true);
}

if (!defined('DCA_TB_IMPORT_DRY_RUN')) {
    define('DCA_TB_IMPORT_DRY_RUN', false);
}

if (!defined('DCA_TB_MAX_IMPORT_PAGES')) {
    define('DCA_TB_MAX_IMPORT_PAGES', 50);
}

if (!defined('DCA_TB_MAX_MEDIA_PER_PAGE')) {
    define('DCA_TB_MAX_MEDIA_PER_PAGE', 25);
}

if (!defined('DCA_TB_MAX_IMPORT_BYTES')) {
    define('DCA_TB_MAX_IMPORT_BYTES', 5242880);
}

function dca_tb_usp_fields() {
    return ['usp_1', 'usp_2', 'usp_3', 'usp_4'];
}

function dca_tb_supported_post_types() {
    return ['page', 'post', 'product'];
}

function dca_tb_is_supported_post_type($post_type) {
    return in_array((string) $post_type, dca_tb_supported_post_types(), true);
}

function dca_tb_get_admin_post_type() {
    global $typenow;

    if (!empty($_GET['post_type'])) {
        return sanitize_key(wp_unslash($_GET['post_type']));
    }

    if (!empty($typenow)) {
        return sanitize_key($typenow);
    }

    return 'post';
}

function dca_tb_post_type_label_single($post_id) {
    $post_type = get_post_type($post_id);

    if ($post_type === 'post') {
        return 'BERICHT';
    }

    if ($post_type === 'product') {
        return 'PRODUCT';
    }

    return 'PAGINA';
}

function dca_tb_post_type_label_plural($post_type) {
    if ($post_type === 'post') {
        return 'berichten';
    }

    if ($post_type === 'product') {
        return 'producten';
    }

    return 'pagina’s';
}

function dca_tb_text($value) {
    if (empty($value) || is_array($value)) return '';
    return trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
}

function dca_tb_marker_count($text, $marker) {
    preg_match_all('/^' . preg_quote($marker, '/') . '\s*$/mi', (string) $text, $m);
    return count($m[0]);
}

function dca_tb_label_marker_count($text, $label) {
    preg_match_all('/^' . preg_quote($label, '/') . '(?:[ \t]*$|[ \t]+.+$)/mi', (string) $text, $m);
    return count($m[0]);
}

function dca_tb_section($text, $start, $ends = []) {
    $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
    $end = empty($ends) ? '\z' : implode('|', array_map(fn($m) => '^' . preg_quote($m, '/') . '\s*$', $ends));
    $pattern = '/^' . preg_quote($start, '/') . '\s*$\n?(.*?)(?=' . $end . ')/ims';
    return preg_match($pattern, $text, $m) ? trim($m[1]) : null;
}

function dca_tb_label($block, $label, $next = []) {
    $block = str_replace(["\r\n", "\r"], "\n", (string) $block);
    $end = empty($next) ? '\z' : '(?:' . implode('|', array_map(fn($m) => '^' . preg_quote($m, '/') . '(?:\s*$|[ \t]+.*$)', $next)) . '|\z)';
    $pattern = '/^' . preg_quote($label, '/') . '[ \t]*(?:\n|([^\n]*)(?:\n|$))(.*?)(?=' . $end . ')/ims';

    if (!preg_match($pattern, $block, $m)) {
        return null;
    }

    $first_line = isset($m[1]) ? trim((string) $m[1]) : '';
    $rest = isset($m[2]) ? trim((string) $m[2]) : '';

    return trim($first_line . ($first_line !== '' && $rest !== '' ? "\n" : '') . $rest);
}

function dca_tb_blocks($text) {
    $parts = preg_split("/\n\s*\n+/", trim(str_replace(["\r\n", "\r"], "\n", (string) $text)));
    return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
}

function dca_tb_clean_html($value) {
    $value = trim((string) $value);
    return $value === '' ? '' : wp_kses($value, wp_kses_allowed_html('post'));
}

function dca_tb_clean_text($value) {
    $value = trim((string) $value);
    return $value === '' ? '' : sanitize_text_field(wp_strip_all_tags($value));
}

function dca_tb_contentblock_end_markers($i) {
    $markers = [];

    if ($i < 3) {
        $markers[] = 'CONTENTBLOK ' . ($i + 1);
    }

    $markers[] = 'USP';
    $markers[] = 'FAQ';
    $markers[] = 'YOAST SEO';
    $markers[] = 'SAMENVATTING';
    $markers[] = 'UITGELICHTE AFBEELDING';
    $markers[] = 'MEDIA';

    return $markers;
}

function dca_tb_mark_updated($post_id) {
    update_post_meta($post_id, '_dca_acf_textblock_updated_at', current_time('timestamp'));
    update_post_meta($post_id, '_dca_acf_textblock_updated_by', get_current_user_id());
}

function dca_tb_add_backup($post_id, $source = 'save') {
    $post_id = absint($post_id);
    $post = get_post($post_id);

    if (!$post || !dca_tb_is_supported_post_type($post->post_type)) {
        return;
    }

    if ($post->post_type === 'page' && !function_exists('get_field')) {
        return;
    }

    $existing = get_post_meta($post_id, '_dca_tb_backups', true);

    if (!is_array($existing)) {
        $existing = [];
    }

    $existing[] = [
        'time'   => current_time('timestamp'),
        'user'   => get_current_user_id(),
        'source' => sanitize_key($source),
        'title'  => get_the_title($post_id),
        'text'   => dca_tb_build_textblock($post_id),
    ];

    if (count($existing) > 20) {
        $existing = array_slice($existing, -20);
    }

    update_post_meta($post_id, '_dca_tb_backups', $existing);
}

function dca_tb_today_start_timestamp() {
    $now_local = current_time('timestamp');
    return strtotime(date('Y-m-d 00:00:00', $now_local));
}

function dca_tb_get_list_status_filter() {
    $status = isset($_GET['dca_tb_status']) ? sanitize_key(wp_unslash($_GET['dca_tb_status'])) : '';

    // Backwards compatibility met de oude toolbar-link.
    $legacy_unupdated = isset($_GET['dca_unupdated']) ? sanitize_text_field(wp_unslash($_GET['dca_unupdated'])) : '';
    if ($status === '' && $legacy_unupdated !== '') {
        $status = 'not_done';
    }

    return in_array($status, ['not_done', 'done_today'], true) ? $status : '';
}

function dca_tb_get_list_template_filter() {
    $template = isset($_GET['dca_tb_template']) ? sanitize_key(wp_unslash($_GET['dca_tb_template'])) : '';

    return in_array($template, ['standard'], true) ? $template : '';
}

function dca_tb_standard_template_meta_query($post_type = '') {
    $blocked_templates = ['elementor_canvas', 'elementor_header_footer'];

    $meta_query = [
        'relation' => 'AND',
        [
            'relation' => 'OR',
            [
                'key'     => '_wp_page_template',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => '_wp_page_template',
                'value'   => '',
                'compare' => '=',
            ],
            [
                'key'     => '_wp_page_template',
                'value'   => 'default',
                'compare' => '=',
            ],
            [
                'key'     => '_wp_page_template',
                'value'   => $blocked_templates,
                'compare' => 'NOT IN',
            ],
        ],
        [
            'relation' => 'OR',
            [
                'key'     => '_elementor_page_template',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => '_elementor_page_template',
                'value'   => '',
                'compare' => '=',
            ],
            [
                'key'     => '_elementor_page_template',
                'value'   => 'default',
                'compare' => '=',
            ],
            [
                'key'     => '_elementor_page_template',
                'value'   => $blocked_templates,
                'compare' => 'NOT IN',
            ],
        ],
    ];

    $meta_query[] = [
        'relation' => 'OR',
        [
            'key'     => '_elementor_page_settings',
            'compare' => 'NOT EXISTS',
        ],
        [
            'key'     => '_elementor_page_settings',
            'value'   => 'elementor_canvas',
            'compare' => 'NOT LIKE',
        ],
    ];

    $meta_query[] = [
        'relation' => 'OR',
        [
            'key'     => '_elementor_page_settings',
            'compare' => 'NOT EXISTS',
        ],
        [
            'key'     => '_elementor_page_settings',
            'value'   => 'elementor_header_footer',
            'compare' => 'NOT LIKE',
        ],
    ];

    if ($post_type === 'page') {
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => '_wp_page_template',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => '_wp_page_template',
                'value'   => '',
                'compare' => '=',
            ],
            [
                'key'     => '_wp_page_template',
                'value'   => 'default',
                'compare' => '=',
            ],
        ];
    }

    return $meta_query;
}

add_action('pre_get_posts', function ($q) {
    if (!is_admin() || !$q->is_main_query()) return;

    global $pagenow;

    $post_type = dca_tb_get_admin_post_type();
    $status = dca_tb_get_list_status_filter();
    $template_filter = dca_tb_get_list_template_filter();

    if ($pagenow !== 'edit.php' || !dca_tb_is_supported_post_type($post_type) || ($status === '' && $template_filter === '')) {
        return;
    }

    $meta_query = (array) $q->get('meta_query');
    $today_start = dca_tb_today_start_timestamp();

    if ($status === 'not_done') {
        // Toon alleen items die vandaag nog niet zijn bijgewerkt/geimporteerd:
        // geen datum, of een datum ouder dan vandaag.
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key'     => '_dca_acf_textblock_updated_at',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => '_dca_acf_textblock_updated_at',
                'value'   => $today_start,
                'compare' => '<',
                'type'    => 'NUMERIC',
            ],
        ];
    }

    if ($status === 'done_today') {
        // Toon alleen items die vandaag al zijn bijgewerkt/geimporteerd.
        $meta_query[] = [
            'key'     => '_dca_acf_textblock_updated_at',
            'value'   => $today_start,
            'compare' => '>=',
            'type'    => 'NUMERIC',
        ];
    }

    if ($template_filter === 'standard') {
        // Toon alleen standaardtemplates voor pagina's, berichten en producten.
        // Elementor Canvas en Elementor Full Width worden op alle ondersteunde post types uitgesloten.
        $meta_query[] = dca_tb_standard_template_meta_query($post_type);
    }

    $q->set('meta_query', $meta_query);
});

add_action('restrict_manage_posts', function ($post_type) {
    if (!dca_tb_is_supported_post_type($post_type) || !dca_tb_current_user_can_use_manager()) {
        return;
    }

    $current = dca_tb_get_list_status_filter();
    $template_current = dca_tb_get_list_template_filter();

    echo '<select name="dca_tb_status" id="dca-tb-status-filter">';
    echo '<option value="">' . esc_html__('Contentblok: alles tonen', 'content-sync-manager') . '</option>';
    echo '<option value="not_done" ' . selected($current, 'not_done', false) . '>' . esc_html__('Contentblok: nog te doen vandaag', 'content-sync-manager') . '</option>';
    echo '<option value="done_today" ' . selected($current, 'done_today', false) . '>' . esc_html__('Contentblok: vandaag bijgewerkt', 'content-sync-manager') . '</option>';
    echo '</select>';

    echo '<select name="dca_tb_template" id="dca-tb-template-filter">';
    echo '<option value="">' . esc_html__('Template: alles tonen', 'content-sync-manager') . '</option>';
    echo '<option value="standard" ' . selected($template_current, 'standard', false) . '>' . esc_html__('Template: standaard, zonder Elementor Canvas/Full Width', 'content-sync-manager') . '</option>';
    echo '</select>';
});

function dca_tb_add_textblock_column($columns) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if ($screen && isset($screen->post_type) && !dca_tb_is_supported_post_type($screen->post_type)) {
        return $columns;
    }

    $new = [];

    foreach ($columns as $key => $label) {
        $new[$key] = $label;

        if ($key === 'title') {
            $new['dca_acf_textblock'] = __('Contentblok', 'content-sync-manager');
        }
    }

    return $new;
}

function dca_tb_render_textblock_column($column, $post_id) {
    if ($column !== 'dca_acf_textblock') return;

    if (!current_user_can('edit_post', $post_id)) {
        echo '<span aria-hidden="true">—</span>';
        return;
    }

    printf(
        '<button type="button" class="button button-small dca-open-acf-textblock" data-post-id="%d">%s</button><br>%s<br>%s',
        absint($post_id),
        esc_html__('Tekst bewerken', 'content-sync-manager'),
        dca_tb_update_badge($post_id),
        dca_tb_content_badge($post_id)
    );
}

add_filter('manage_pages_columns', 'dca_tb_add_textblock_column');
add_filter('manage_posts_columns', 'dca_tb_add_textblock_column');
add_filter('manage_product_posts_columns', 'dca_tb_add_textblock_column');

add_action('manage_pages_custom_column', 'dca_tb_render_textblock_column', 10, 2);
add_action('manage_posts_custom_column', 'dca_tb_render_textblock_column', 10, 2);
add_action('manage_product_posts_custom_column', 'dca_tb_render_textblock_column', 10, 2);

function dca_tb_update_badge($post_id) {
    $time = absint(get_post_meta($post_id, '_dca_acf_textblock_updated_at', true));

    if (!$time) {
        return '<span class="dca-badge dca-badge-not-updated">Geen datum</span>';
    }

    $today_start = dca_tb_today_start_timestamp();
    $class = $time >= $today_start ? 'dca-badge-updated' : 'dca-badge-muted';
    $user_id = absint(get_post_meta($post_id, '_dca_acf_textblock_updated_by', true));
    $user = $user_id ? get_userdata($user_id) : null;
    $title = 'Laatst bijgewerkt op ' . date_i18n('d-m-Y H:i', $time) . ($user ? ' door ' . $user->display_name : '');

    return sprintf(
        '<span class="dca-badge %s" title="%s">%s</span>',
        esc_attr($class),
        esc_attr($title),
        esc_html(date_i18n('d-m-Y', $time))
    );
}

function dca_tb_content_badge($post_id) {
    $post = get_post($post_id);

    if (!$post || !dca_tb_is_supported_post_type($post->post_type)) {
        return '<span class="dca-badge dca-badge-muted">Niet ondersteund</span>';
    }

    $yoast_title = trim((string) get_post_meta($post_id, '_yoast_wpseo_title', true));
    $yoast_desc  = trim((string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true));
    $media_count = count(dca_tb_collect_media_ids($post_id));

    if ($post->post_type === 'post') {
        $has_title   = trim((string) $post->post_title) !== '';
        $has_content = trim((string) $post->post_content) !== '';
        $complete = ($has_title && $has_content && $yoast_title !== '' && $yoast_desc !== '');
        $class = $complete ? 'dca-badge-green' : 'dca-badge-yellow';

        return sprintf(
            '<span class="dca-badge %s">Titel %s / Content %s / Yoast %s / %d media</span>',
            esc_attr($class),
            $has_title ? 'ok' : 'mist',
            $has_content ? 'ok' : 'mist',
            ($yoast_title && $yoast_desc) ? 'ok' : 'mist',
            absint($media_count)
        );
    }

    if (!function_exists('get_field_objects')) {
        return '<span class="dca-badge dca-badge-muted">ACF niet actief</span>';
    }

    $acf_fields = dca_tb_get_detected_acf_fields($post_id);
    $acf_total = count($acf_fields);
    $acf_filled = 0;

    foreach ($acf_fields as $field) {
        $value = $field['value'] ?? '';
        if (is_array($value) || is_object($value)) {
            if (!empty((array) $value)) {
                $acf_filled++;
            }
        } elseif (trim((string) $value) !== '') {
            $acf_filled++;
        }
    }

    $complete = ($acf_total > 0 && $acf_filled === $acf_total && $yoast_title !== '' && $yoast_desc !== '');
    $class = $complete ? 'dca-badge-green' : 'dca-badge-yellow';

    return sprintf(
        '<span class="dca-badge %s">ACF %d/%d / Yoast %s / %d media</span>',
        esc_attr($class),
        absint($acf_filled),
        absint($acf_total),
        ($yoast_title && $yoast_desc) ? 'ok' : 'mist',
        absint($media_count)
    );
}

function dca_tb_array_contains_value($value, $needles) {
    if (is_array($value)) {
        foreach ($value as $item) {
            if (dca_tb_array_contains_value($item, $needles)) {
                return true;
            }
        }

        return false;
    }

    $value = strtolower(trim((string) $value));

    if ($value === '') {
        return false;
    }

    foreach ($needles as $needle) {
        if ($value === strtolower((string) $needle)) {
            return true;
        }
    }

    return false;
}

function dca_tb_has_elementor_builder_content($post_id) {
    $post_id = absint($post_id);

    if (!$post_id) {
        return false;
    }

    $edit_mode = get_post_meta($post_id, '_elementor_edit_mode', true);

    if (is_string($edit_mode) && strtolower(trim($edit_mode)) === 'builder') {
        return true;
    }

    $elementor_data = get_post_meta($post_id, '_elementor_data', true);

    if (is_string($elementor_data)) {
        $elementor_data = trim($elementor_data);
        return $elementor_data !== '' && $elementor_data !== '[]' && $elementor_data !== 'a:0:{}';
    }

    return is_array($elementor_data) && !empty($elementor_data);
}

function dca_tb_has_elementor_nonstandard_layout($post_id) {
    $post_id = absint($post_id);

    if (!$post_id) {
        return false;
    }

    $blocked = [
        'elementor_canvas',
        'elementor_header_footer',
    ];

    // Elementor Canvas / Full Width kan via de normale paginatemplate of via Elementor page settings zijn opgeslagen.
    $wp_template_meta = get_post_meta($post_id, '_wp_page_template', true);

    if (dca_tb_array_contains_value($wp_template_meta, $blocked)) {
        return true;
    }

    $elementor_settings = get_post_meta($post_id, '_elementor_page_settings', true);

    if (is_string($elementor_settings)) {
        $maybe_unserialized = maybe_unserialize($elementor_settings);
        if ($maybe_unserialized !== $elementor_settings) {
            $elementor_settings = $maybe_unserialized;
        }
    }

    if (dca_tb_array_contains_value($elementor_settings, $blocked)) {
        return true;
    }

    foreach (['_elementor_template_type', '_elementor_page_template'] as $meta_key) {
        if (dca_tb_array_contains_value(get_post_meta($post_id, $meta_key, true), $blocked)) {
            return true;
        }
    }

    return false;
}

function dca_tb_template_skip_reason($post_id) {
    $post_id = absint($post_id);
    $post_type = get_post_type($post_id);

    if (!$post_id || !dca_tb_is_supported_post_type($post_type)) {
        return 'Geen geldig bericht, geldige pagina of geldig product.';
    }

    if ($post_type === 'page' && get_page_template_slug($post_id) !== '') {
        return 'Overgeslagen: pagina gebruikt geen standaard WordPress-template.';
    }

    if (dca_tb_has_elementor_nonstandard_layout($post_id)) {
        return 'Overgeslagen: Elementor Canvas of Elementor Full Width is geen standaard template.';
    }

    return '';
}

function dca_tb_is_standard_template($post_id) {
    return dca_tb_template_skip_reason($post_id) === '';
}

function dca_tb_template_badge($post_id) {
    $reason = dca_tb_template_skip_reason($post_id);

    if ($reason === '') {
        return '<span class="dca-badge dca-badge-green">Standaard template</span>';
    }

    return '<span class="dca-badge dca-badge-muted" title="' . esc_attr($reason) . '">Custom template</span>';
}

function dca_tb_media_badge($post_id) {
    $count = count(dca_tb_collect_media_ids($post_id));
    $class = $count > 0 ? 'dca-badge-green' : 'dca-badge-muted';
    return '<span class="dca-badge ' . esc_attr($class) . '">' . absint($count) . ' media</span>';
}


function dca_tb_build_summary_block($post_id) {
    $post = get_post($post_id);

    return trim(implode("\n", [
        'SAMENVATTING',
        '',
        $post ? dca_tb_text($post->post_excerpt) : '',
    ]));
}

function dca_tb_featured_image_end_markers() {
    return ['MEDIA'];
}

function dca_tb_build_featured_image_block($post_id) {
    $thumb_id = get_post_thumbnail_id($post_id);
    $out = ['UITGELICHTE AFBEELDING'];

    array_push(
        $out,
        '',
        'Attachment ID:',
        $thumb_id ? (string) $thumb_id : '',
        '',
        'URL:',
        $thumb_id ? (string) wp_get_attachment_url($thumb_id) : '',
        '',
        'Alt text:',
        $thumb_id ? dca_tb_text(get_post_meta($thumb_id, '_wp_attachment_image_alt', true)) : ''
    );

    return trim(implode("\n", $out));
}

function dca_tb_parse_featured_image_block($textblock) {
    $block = dca_tb_section($textblock, 'UITGELICHTE AFBEELDING', dca_tb_featured_image_end_markers());

    if ($block === null) {
        return null;
    }

    return [
        'attachment_id' => absint(dca_tb_label($block, 'Attachment ID:', ['URL:'])),
        'url'           => dca_tb_label($block, 'URL:', ['Alt text:']),
        'alt'           => dca_tb_label($block, 'Alt text:'),
    ];
}

function dca_tb_validate_featured_image_block($textblock) {
    $block = dca_tb_section($textblock, 'UITGELICHTE AFBEELDING', dca_tb_featured_image_end_markers());

    if ($block === null) {
        return true;
    }

    foreach (['Attachment ID:', 'URL:', 'Alt text:'] as $label) {
        if (dca_tb_label_marker_count($block, $label) !== 1) {
            return new WP_Error('dca_invalid_featured_image', 'Opslaan gestopt: "' . $label . '" ontbreekt of komt meerdere keren voor onder UITGELICHTE AFBEELDING.');
        }
    }

    return true;
}

function dca_tb_apply_featured_image_from_textblock($post_id, $textblock) {
    $validation = dca_tb_validate_featured_image_block($textblock);
    if (is_wp_error($validation)) {
        return $validation;
    }

    $item = dca_tb_parse_featured_image_block($textblock);

    if ($item === null) {
        return true;
    }

    $attachment_id = absint($item['attachment_id'] ?? 0);
    $url = trim((string) ($item['url'] ?? ''));

    if (!$attachment_id && $url !== '') {
        $attachment_id = absint(attachment_url_to_postid($url));
    }

    if (!$attachment_id && $url === '') {
        delete_post_thumbnail($post_id);
        return true;
    }

    if (!$attachment_id || get_post_type($attachment_id) !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
        return new WP_Error('dca_featured_image_invalid', 'Opslaan gestopt: uitgelichte afbeelding is geen geldige WordPress-afbeelding.');
    }

    if (!current_user_can('edit_post', $attachment_id)) {
        return new WP_Error('dca_featured_image_no_permission', 'Geen rechten om deze uitgelichte afbeelding te gebruiken.');
    }

    set_post_thumbnail($post_id, $attachment_id);

    if (array_key_exists('alt', $item) && $item['alt'] !== null) {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', dca_tb_clean_text($item['alt']));
    }

    return true;
}

function dca_tb_yoast_end_markers() {
    return ['SAMENVATTING', 'UITGELICHTE AFBEELDING', 'MEDIA'];
}

function dca_tb_media_end_markers() {
    $markers = [];

    for ($i = 1; $i <= DCA_TB_MAX_MEDIA_PER_PAGE; $i++) {
        $markers[] = 'AFBEELDING ' . $i;
    }

    return $markers;
}

function dca_tb_collect_media_ids_from_value($value, &$ids) {
    if (is_numeric($value)) {
        $id = absint($value);
        if ($id && get_post_type($id) === 'attachment' && wp_attachment_is_image($id)) {
            $ids[$id] = $id;
        }
        return;
    }

    if (is_array($value)) {
        if (!empty($value['ID'])) {
            dca_tb_collect_media_ids_from_value($value['ID'], $ids);
        }
        if (!empty($value['id'])) {
            dca_tb_collect_media_ids_from_value($value['id'], $ids);
        }
        if (!empty($value['url'])) {
            $id = attachment_url_to_postid((string) $value['url']);
            dca_tb_collect_media_ids_from_value($id, $ids);
        }
        foreach ($value as $child) {
            dca_tb_collect_media_ids_from_value($child, $ids);
        }
    }
}

function dca_tb_collect_media_ids($post_id) {
    $post_id = absint($post_id);
    $ids = [];

    $thumb_id = get_post_thumbnail_id($post_id);
    if ($thumb_id && wp_attachment_is_image($thumb_id)) {
        $ids[$thumb_id] = $thumb_id;
    }

    $post = get_post($post_id);
    if ($post && !empty($post->post_content)) {
        if (preg_match_all('/wp-image-([0-9]+)/i', $post->post_content, $m)) {
            foreach ($m[1] as $id) {
                dca_tb_collect_media_ids_from_value($id, $ids);
            }
        }
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $post->post_content, $m)) {
            foreach ($m[1] as $url) {
                dca_tb_collect_media_ids_from_value(attachment_url_to_postid($url), $ids);
            }
        }
    }

    /**
     * ACF media: use detected unformatted field values instead of get_fields()
     * with formatted arrays. This keeps the export tied to fields that actually
     * belong to the edited object and avoids pulling unrelated theme/global
     * images into the MEDIA block.
     */
    if (function_exists('get_field_objects')) {
        foreach (dca_tb_get_detected_acf_fields($post_id) as $field) {
            $type = isset($field['type']) ? sanitize_key($field['type']) : '';
            if (in_array($type, ['image', 'file', 'gallery'], true)) {
                dca_tb_collect_media_ids_from_value($field['value'] ?? '', $ids);
            }
        }
    }

    return array_values($ids);
}

function dca_tb_media_filename($attachment_id) {
    $file = get_attached_file($attachment_id, true);
    return $file ? wp_basename($file) : '';
}

function dca_tb_build_media_block($post_id) {
    $ids = dca_tb_collect_media_ids($post_id);
    $total_ids = count($ids);

    if ($total_ids > DCA_TB_MAX_MEDIA_PER_PAGE) {
        $ids = array_slice($ids, 0, DCA_TB_MAX_MEDIA_PER_PAGE);
    }

    $out = ['MEDIA'];

    if (!$ids) {
        $out[] = '';
        $out[] = 'Geen lokale WordPress-afbeeldingen gevonden voor deze pagina.';
        return trim(implode("\n", $out));
    }

    if ($total_ids > DCA_TB_MAX_MEDIA_PER_PAGE) {
        $out[] = '';
        $out[] = 'Let op: alleen de eerste ' . absint(DCA_TB_MAX_MEDIA_PER_PAGE) . ' lokale WordPress-afbeeldingen zijn geëxporteerd. Extra media blijven ongemoeid.';
    }

    $i = 1;
    foreach ($ids as $attachment_id) {
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
            continue;
        }

        array_push(
            $out,
            '',
            'AFBEELDING ' . $i,
            'Attachment ID:',
            (string) $attachment_id,
            'Huidige URL:',
            (string) wp_get_attachment_url($attachment_id),
            'Bestandsnaam:',
            dca_tb_media_filename($attachment_id),
            'Nieuwe bestandsnaam:',
            '',
            'Title:',
            dca_tb_text($attachment->post_title),
            'Alt text:',
            dca_tb_text(get_post_meta($attachment_id, '_wp_attachment_image_alt', true)),
            'Caption:',
            dca_tb_text($attachment->post_excerpt),
            'Description:',
            dca_tb_text($attachment->post_content)
        );
        $i++;
    }

    return trim(implode("\n", $out));
}

function dca_tb_strip_media_block($textblock) {
    $media_pos = preg_match('/^MEDIA\s*$/mi', (string) $textblock, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : false;
    if ($media_pos === false) {
        return trim((string) $textblock);
    }
    return trim(substr((string) $textblock, 0, $media_pos));
}

function dca_tb_normalize_compare_url_path($url) {
    $url = trim((string) $url);

    if ($url === '') {
        return '';
    }

    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    $path = rawurldecode($path);
    $path = preg_replace('#/+#', '/', $path);

    return untrailingslashit(strtolower($path));
}

function dca_tb_media_identity_error($item, $attachment_id) {
    $attachment_id = absint($attachment_id);

    if (!$attachment_id) {
        return new WP_Error('dca_media_missing_id', 'Attachment ID ontbreekt.');
    }

    $expected_url = isset($item['current_url']) ? trim((string) $item['current_url']) : '';
    $actual_url = (string) wp_get_attachment_url($attachment_id);

    if ($expected_url !== '') {
        $expected_path = dca_tb_normalize_compare_url_path($expected_url);
        $actual_path = dca_tb_normalize_compare_url_path($actual_url);

        if ($expected_path !== '' && $actual_path !== '' && $expected_path !== $actual_path) {
            return new WP_Error('dca_media_url_mismatch', 'Attachment #' . $attachment_id . ' komt niet overeen met de huidige URL in het importbestand.');
        }
    }

    $expected_filename = isset($item['filename']) ? trim((string) $item['filename']) : '';
    $actual_filename = dca_tb_media_filename($attachment_id);

    if ($expected_filename !== '' && $actual_filename !== '' && strtolower($expected_filename) !== strtolower($actual_filename)) {
        return new WP_Error('dca_media_filename_mismatch', 'Attachment #' . $attachment_id . ' komt niet overeen met de huidige bestandsnaam in het importbestand.');
    }

    return true;
}

function dca_tb_parse_media_blocks($textblock) {
    $media = dca_tb_section($textblock, 'MEDIA');
    if ($media === null || stripos($media, 'AFBEELDING') === false) {
        return [];
    }

    $items = [];
    $pattern = '/^AFBEELDING\s+\d+\s*$\n?(.*?)(?=^AFBEELDING\s+\d+\s*$|\z)/ims';
    if (!preg_match_all($pattern, $media, $matches, PREG_SET_ORDER)) {
        return [];
    }

    foreach ($matches as $m) {
        $block = trim($m[1]);
        $items[] = [
            'attachment_id' => absint(dca_tb_label($block, 'Attachment ID:', ['Huidige URL:'])),
            'current_url'   => dca_tb_label($block, 'Huidige URL:', ['Bestandsnaam:']),
            'filename'      => dca_tb_label($block, 'Bestandsnaam:', ['Nieuwe bestandsnaam:']),
            'new_filename'  => dca_tb_label($block, 'Nieuwe bestandsnaam:', ['Title:']),
            'title'         => dca_tb_label($block, 'Title:', ['Alt text:']),
            'alt'           => dca_tb_label($block, 'Alt text:', ['Caption:']),
            'caption'       => dca_tb_label($block, 'Caption:', ['Description:']),
            'description'   => dca_tb_label($block, 'Description:'),
        ];
    }

    return $items;
}

function dca_tb_add_media_backup($attachment_id, $source = 'media-import') {
    $attachment_id = absint($attachment_id);
    $attachment = get_post($attachment_id);
    if (!$attachment || $attachment->post_type !== 'attachment') {
        return;
    }

    $existing = get_post_meta($attachment_id, '_dca_tb_media_backups', true);
    if (!is_array($existing)) {
        $existing = [];
    }

    $existing[] = [
        'time'          => current_time('timestamp'),
        'user'          => get_current_user_id(),
        'source'        => sanitize_key($source),
        'filename'      => dca_tb_media_filename($attachment_id),
        'attached_file' => get_post_meta($attachment_id, '_wp_attached_file', true),
        'guid'          => $attachment->guid,
        'alt'           => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
        'title'         => $attachment->post_title,
        'caption'       => $attachment->post_excerpt,
        'description'   => $attachment->post_content,
        'metadata'      => wp_get_attachment_metadata($attachment_id),
    ];

    if (count($existing) > 20) {
        $existing = array_slice($existing, -20);
    }

    update_post_meta($attachment_id, '_dca_tb_media_backups', $existing);
}

function dca_tb_prepare_new_media_filename($old_filename, $requested) {
    $old_filename = wp_basename((string) $old_filename);
    $requested = trim((string) $requested);

    if ($requested === '') {
        return '';
    }

    $old_ext_raw = (string) pathinfo($old_filename, PATHINFO_EXTENSION);
    $old_ext = strtolower($old_ext_raw);
    $safe = sanitize_file_name($requested);

    if ($safe === '') {
        return new WP_Error('dca_media_bad_filename', 'Nieuwe bestandsnaam is leeg of ongeldig na opschonen.');
    }

    $requested_ext = strtolower((string) pathinfo($safe, PATHINFO_EXTENSION));

    if ($requested_ext !== '' && $old_ext !== '' && $requested_ext !== $old_ext) {
        return new WP_Error('dca_media_ext_change', 'Bestandstype wijzigen is niet toegestaan. Alleen de naam mag wijzigen; de extensie moet .' . $old_ext . ' blijven.');
    }

    $name_only = $requested_ext !== '' ? pathinfo($safe, PATHINFO_FILENAME) : $safe;
    $name_only = sanitize_file_name($name_only);
    $name_only = trim($name_only, '.-_ ');

    if ($name_only === '') {
        return new WP_Error('dca_media_bad_filename', 'Nieuwe bestandsnaam bevat geen geldige naam vóór de extensie.');
    }

    return $old_ext_raw !== '' ? $name_only . '.' . $old_ext_raw : $name_only;
}

function dca_tb_validate_renamed_filetype($old_file, $new_filename) {
    if (!function_exists('wp_check_filetype_and_ext')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $check = wp_check_filetype_and_ext($old_file, $new_filename);

    if (empty($check['ext']) || empty($check['type'])) {
        return new WP_Error('dca_media_invalid_filetype', 'Bestandstype of extensie is ongeldig volgens WordPress-bestandscontrole.');
    }

    if (strpos((string) $check['type'], 'image/') !== 0) {
        return new WP_Error('dca_media_not_image_type', 'Alleen echte afbeelding-bestandstypen mogen worden hernoemd.');
    }

    $old_ext = strtolower((string) pathinfo($old_file, PATHINFO_EXTENSION));
    $new_ext = strtolower((string) pathinfo($new_filename, PATHINFO_EXTENSION));

    if ($old_ext !== '' && $new_ext !== $old_ext) {
        return new WP_Error('dca_media_ext_change', 'Bestandstype wijzigen is niet toegestaan. Alleen de naam vóór .' . $old_ext . ' mag wijzigen.');
    }

    return true;
}

function dca_tb_rename_attachment_file($attachment_id, $requested_filename, &$replace_pairs) {
    $attachment_id = absint($attachment_id);

    if (!DCA_TB_ALLOW_MEDIA_FILE_RENAME || trim((string) $requested_filename) === '') {
        return ['renamed' => false, 'message' => 'Geen bestandsnaamwijziging.'];
    }

    if (!current_user_can('edit_post', $attachment_id)) {
        return new WP_Error('dca_media_no_permission', 'Geen rechten om attachment #' . $attachment_id . ' te wijzigen.');
    }

    if (!wp_attachment_is_image($attachment_id)) {
        return new WP_Error('dca_media_not_image', 'Attachment #' . $attachment_id . ' is geen afbeelding.');
    }

    $old_file = get_attached_file($attachment_id, true);
    if (!$old_file || !file_exists($old_file)) {
        return new WP_Error('dca_media_file_missing', 'Origineel bestand bestaat niet voor attachment #' . $attachment_id . '.');
    }

    $uploads = wp_upload_dir();
    $base = realpath($uploads['basedir']);
    $old_real = realpath($old_file);

    if (!$base || !$old_real) {
        return new WP_Error('dca_media_outside_uploads', 'Bestand staat niet veilig binnen de uploads-map.');
    }

    $base_path = trailingslashit(wp_normalize_path($base));
    $old_path = wp_normalize_path($old_real);

    if (strpos($old_path, $base_path) !== 0) {
        return new WP_Error('dca_media_outside_uploads', 'Bestand staat niet veilig binnen de uploads-map.');
    }

    $new_filename = dca_tb_prepare_new_media_filename(wp_basename($old_file), $requested_filename);
    if (is_wp_error($new_filename)) {
        return $new_filename;
    }

    if ($new_filename === wp_basename($old_file)) {
        return ['renamed' => false, 'message' => 'Nieuwe bestandsnaam is gelijk aan huidige bestandsnaam.'];
    }

    $type_check = dca_tb_validate_renamed_filetype($old_file, $new_filename);
    if (is_wp_error($type_check)) {
        return $type_check;
    }

    $dir = dirname($old_file);
    $new_file = trailingslashit($dir) . $new_filename;

    if (file_exists($new_file)) {
        if (DCA_TB_AUTO_UNIQUE_RENAMED_FILES) {
            $unique_filename = wp_unique_filename($dir, $new_filename);
            $unique_ext = strtolower((string) pathinfo($unique_filename, PATHINFO_EXTENSION));
            $old_ext = strtolower((string) pathinfo($old_file, PATHINFO_EXTENSION));

            if ($old_ext !== '' && $unique_ext !== $old_ext) {
                return new WP_Error('dca_media_unique_ext_change', 'Automatische unieke bestandsnaam zou de extensie wijzigen. Hernoemen overgeslagen.');
            }

            $new_filename = $unique_filename;
            $new_file = trailingslashit($dir) . $new_filename;
        } else {
            return new WP_Error('dca_media_target_exists', 'Doelbestand bestaat al: ' . $new_filename . '.');
        }
    }

    $old_url = wp_get_attachment_url($attachment_id);
    $relative = ltrim(str_replace(wp_normalize_path($uploads['basedir']), '', wp_normalize_path($new_file)), '/');

    if (DCA_TB_IMPORT_DRY_RUN) {
        return ['renamed' => true, 'message' => 'Dry-run: bestandsnaam zou worden hernoemd naar ' . $new_filename . '.'];
    }

    dca_tb_add_media_backup($attachment_id, 'rename');

    if (!rename($old_file, $new_file)) {
        return new WP_Error('dca_media_rename_failed', 'Fysiek hernoemen mislukt voor attachment #' . $attachment_id . '.');
    }

    clearstatcache(true, $old_file);
    clearstatcache(true, $new_file);

    update_post_meta($attachment_id, '_wp_attached_file', $relative);

    $new_url = trailingslashit($uploads['baseurl']) . str_replace('%2F', '/', rawurlencode($relative));
    $new_url = str_replace('%2F', '/', $new_url);

    $attachment = get_post($attachment_id);
    if ($attachment && $old_url && $attachment->guid === $old_url) {
        wp_update_post([
            'ID'   => $attachment_id,
            'guid' => esc_url_raw($new_url),
        ]);
    }

    if (!function_exists('wp_generate_attachment_metadata')) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $metadata = wp_generate_attachment_metadata($attachment_id, $new_file);
    if (!is_wp_error($metadata) && !empty($metadata)) {
        wp_update_attachment_metadata($attachment_id, $metadata);
    }

    if ($old_url && $new_url && $old_url !== $new_url) {
        $replace_pairs[$old_url] = $new_url;
    }

    return ['renamed' => true, 'message' => 'Bestandsnaam hernoemd naar ' . $new_filename . '.'];
}

function dca_tb_recursive_url_replace($value, $replace_pairs, &$count) {
    if (is_string($value)) {
        $new = str_replace(array_keys($replace_pairs), array_values($replace_pairs), $value, $local_count);
        $count += (int) $local_count;
        return $new;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            $value[$k] = dca_tb_recursive_url_replace($v, $replace_pairs, $count);
        }
    }

    return $value;
}

function dca_tb_replace_media_urls_on_page($post_id, $replace_pairs) {
    $post_id = absint($post_id);
    if (!$post_id || empty($replace_pairs)) {
        return 0;
    }

    $count = 0;
    $post = get_post($post_id);
    if ($post && is_string($post->post_content)) {
        $new_content = str_replace(array_keys($replace_pairs), array_values($replace_pairs), $post->post_content, $content_count);
        if ($new_content !== $post->post_content) {
            wp_update_post(['ID' => $post_id, 'post_content' => $new_content]);
            $count += (int) $content_count;
        }
    }

    $all_meta = get_post_meta($post_id);
    foreach ($all_meta as $key => $values) {
        if (strpos((string) $key, '_') === 0) {
            continue;
        }
        foreach ($values as $index => $raw) {
            $value = maybe_unserialize($raw);
            $local_count = 0;
            $new_value = dca_tb_recursive_url_replace($value, $replace_pairs, $local_count);
            if ($local_count > 0) {
                update_post_meta($post_id, $key, $new_value, $value);
                $count += $local_count;
            }
        }
    }

    return $count;
}

function dca_tb_preview_media_changes($textblock) {
    $items = dca_tb_parse_media_blocks($textblock);
    $summary = [
        'found'   => count($items),
        'updates' => 0,
        'renames' => 0,
        'errors'  => 0,
        'messages'=> [],
    ];

    if (count($items) > DCA_TB_MAX_MEDIA_PER_PAGE) {
        $summary['errors']++;
        $summary['messages'][] = 'Media fout: maximaal ' . absint(DCA_TB_MAX_MEDIA_PER_PAGE) . ' media-items per pagina toegestaan.';
        return $summary;
    }

    foreach ($items as $item) {
        $id = absint($item['attachment_id'] ?? 0);
        if (!$id || get_post_type($id) !== 'attachment' || !wp_attachment_is_image($id)) {
            $summary['errors']++;
            $summary['messages'][] = 'Media fout: attachment ontbreekt of is geen afbeelding.';
            continue;
        }

        $identity = dca_tb_media_identity_error($item, $id);
        if (is_wp_error($identity)) {
            $summary['errors']++;
            $summary['messages'][] = 'Media fout #' . $id . ': ' . $identity->get_error_message();
            continue;
        }

        if (!current_user_can('edit_post', $id)) {
            $summary['errors']++;
            $summary['messages'][] = 'Media fout: geen rechten voor attachment #' . $id . '.';
            continue;
        }
        $summary['updates']++;
        if (trim((string) ($item['new_filename'] ?? '')) !== '') {
            if (!DCA_TB_ALLOW_MEDIA_FILE_RENAME) {
                $summary['messages'][] = 'Attachment #' . $id . ': bestandsnaamwijziging genegeerd omdat media hernoemen uit staat.';
                continue;
            }

            $prepared = dca_tb_prepare_new_media_filename(dca_tb_media_filename($id), $item['new_filename']);
            if (is_wp_error($prepared)) {
                $summary['errors']++;
                $summary['messages'][] = 'Media fout #' . $id . ': ' . $prepared->get_error_message();
            } else {
                $old_file = get_attached_file($id, true);
                $type_check = $old_file ? dca_tb_validate_renamed_filetype($old_file, $prepared) : new WP_Error('dca_media_file_missing', 'Origineel bestand bestaat niet.');
                if (is_wp_error($type_check)) {
                    $summary['errors']++;
                    $summary['messages'][] = 'Media fout #' . $id . ': ' . $type_check->get_error_message();
                } else {
                    $target_file = trailingslashit(dirname($old_file)) . $prepared;
                    $summary['renames']++;
                    if (file_exists($target_file) && DCA_TB_AUTO_UNIQUE_RENAMED_FILES) {
                        $summary['messages'][] = 'Attachment #' . $id . ' wordt hernoemd met automatische unieke naam omdat doelbestand bestaat.';
                    } elseif (file_exists($target_file)) {
                        $summary['errors']++;
                        $summary['messages'][] = 'Media fout #' . $id . ': doelbestand bestaat al.';
                    } else {
                        $summary['messages'][] = 'Attachment #' . $id . ' wordt hernoemd naar ' . $prepared . '.';
                    }
                }
            }
        }
    }

    return $summary;
}

function dca_tb_save_media_items($post_id, $textblock) {
    $items = dca_tb_parse_media_blocks($textblock);
    $result = [
        'media_updated' => 0,
        'renamed'       => 0,
        'media_errors'  => 0,
        'url_replaces'  => 0,
        'messages'      => [],
    ];

    if (!$items) {
        return $result;
    }

    if (count($items) > DCA_TB_MAX_MEDIA_PER_PAGE) {
        $result['media_errors']++;
        $result['messages'][] = 'Media overgeslagen: maximaal ' . absint(DCA_TB_MAX_MEDIA_PER_PAGE) . ' media-items per pagina toegestaan.';
        return $result;
    }

    $replace_pairs = [];

    foreach ($items as $item) {
        $id = absint($item['attachment_id'] ?? 0);

        if (!$id || get_post_type($id) !== 'attachment' || !wp_attachment_is_image($id)) {
            $result['media_errors']++;
            $result['messages'][] = 'Media overgeslagen: attachment ontbreekt of is geen afbeelding.';
            continue;
        }

        $identity = dca_tb_media_identity_error($item, $id);
        if (is_wp_error($identity)) {
            $result['media_errors']++;
            $result['messages'][] = 'Media overgeslagen voor attachment #' . $id . ': ' . $identity->get_error_message();
            continue;
        }

        if (!current_user_can('edit_post', $id)) {
            $result['media_errors']++;
            $result['messages'][] = 'Media overgeslagen: geen rechten voor attachment #' . $id . '.';
            continue;
        }

        if (!DCA_TB_IMPORT_DRY_RUN) {
            dca_tb_add_media_backup($id, 'media-import');
        }

        $rename = dca_tb_rename_attachment_file($id, (string) ($item['new_filename'] ?? ''), $replace_pairs);
        if (is_wp_error($rename)) {
            $result['media_errors']++;
            $result['messages'][] = 'Bestandsnaam niet gewijzigd voor attachment #' . $id . ': ' . $rename->get_error_message();
        } elseif (!empty($rename['renamed'])) {
            $result['renamed']++;
        }

        $post_update = ['ID' => $id];
        if ($item['title'] !== null) {
            $post_update['post_title'] = dca_tb_clean_text($item['title']);
        }
        if ($item['caption'] !== null) {
            $post_update['post_excerpt'] = dca_tb_clean_text($item['caption']);
        }
        if ($item['description'] !== null) {
            $post_update['post_content'] = dca_tb_clean_html($item['description']);
        }

        if (!DCA_TB_IMPORT_DRY_RUN) {
            if (count($post_update) > 1) {
                $updated = wp_update_post($post_update, true);
                if (is_wp_error($updated)) {
                    $result['media_errors']++;
                    $result['messages'][] = 'Media velden niet opgeslagen voor attachment #' . $id . ': ' . $updated->get_error_message();
                    continue;
                }
            }

            if ($item['alt'] !== null) {
                update_post_meta($id, '_wp_attachment_image_alt', dca_tb_clean_text($item['alt']));
            }
        }

        $result['media_updated']++;
    }

    if (!empty($replace_pairs) && !DCA_TB_IMPORT_DRY_RUN) {
        $result['url_replaces'] = dca_tb_replace_media_urls_on_page($post_id, $replace_pairs);
    }

    return $result;
}


function dca_tb_acf_export_end_markers() {
    return ['YOAST SEO', 'SAMENVATTING', 'UITGELICHTE AFBEELDING', 'MEDIA'];
}

function dca_tb_acf_attachment_id_from_value($value) {
    if (is_numeric($value)) {
        return absint($value);
    }

    if (is_array($value)) {
        foreach (['ID', 'id'] as $key) {
            if (isset($value[$key]) && is_numeric($value[$key])) {
                return absint($value[$key]);
            }
        }
    }

    return 0;
}

function dca_tb_acf_attachment_to_text($value) {
    $attachment_id = dca_tb_acf_attachment_id_from_value($value);

    if (!$attachment_id && is_string($value) && function_exists('attachment_url_to_postid')) {
        $attachment_id = absint(attachment_url_to_postid(esc_url_raw($value)));
    }

    if (!$attachment_id) {
        return "Geen afbeelding/bestand geselecteerd.\nAttachment ID:\n0";
    }

    $url = '';
    if (is_string($value) && preg_match('#^https?://#i', $value)) {
        $url = esc_url_raw($value);
    } elseif (is_array($value) && !empty($value['url'])) {
        $url = esc_url_raw($value['url']);
    } else {
        $url = wp_get_attachment_url($attachment_id);
    }

    $out = [
        'Attachment ID:',
        (string) $attachment_id,
        '',
        'URL:',
        $url ? $url : '',
    ];

    if (wp_attachment_is_image($attachment_id)) {
        $out[] = '';
        $out[] = 'Alt text:';
        $out[] = dca_tb_text(get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
    }

    return rtrim(implode("\n", $out));
}

function dca_tb_acf_gallery_to_text($value) {
    if (!is_array($value)) {
        return '';
    }

    $ids = [];

    foreach ($value as $item) {
        $id = dca_tb_acf_attachment_id_from_value($item);
        if ($id) {
            $ids[] = $id;
        }
    }

    if (!$ids) {
        return "Geen afbeeldingen geselecteerd.\nAttachment IDs:\n";
    }

    return "Attachment IDs:\n" . implode(', ', array_map('absint', array_unique($ids)));
}

function dca_tb_acf_value_to_text($value, $type = '') {
    $type = sanitize_key($type);

    if (in_array($type, ['image', 'file'], true)) {
        return dca_tb_acf_attachment_to_text($value);
    }

    if ($type === 'gallery') {
        return dca_tb_acf_gallery_to_text($value);
    }

    if (is_array($value) || is_object($value)) {
        $json = wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json ? $json : '';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return dca_tb_text($value);
}

function dca_tb_get_detected_acf_fields($post_id) {
    $post_id = absint($post_id);

    if (!$post_id || !function_exists('get_field_objects')) {
        return [];
    }

    $objects = get_field_objects($post_id, false, false);

    if (!is_array($objects)) {
        $objects = get_field_objects($post_id, false, true);
    }

    if (!is_array($objects)) {
        return [];
    }

    $fields = [];

    foreach ($objects as $field) {
        if (!is_array($field)) {
            continue;
        }

        $name = isset($field['name']) ? sanitize_key($field['name']) : '';
        $key = isset($field['key']) ? sanitize_key($field['key']) : '';

        if ($name === '') {
            continue;
        }

        $fields[] = [
            'name'  => $name,
            'key'   => $key,
            'label' => isset($field['label']) ? dca_tb_clean_text($field['label']) : $name,
            'type'  => isset($field['type']) ? sanitize_key($field['type']) : '',
            'value' => $field['value'] ?? '',
        ];
    }

    return $fields;
}

function dca_tb_build_acf_fields_block($post_id) {
    $fields = dca_tb_get_detected_acf_fields($post_id);
    $out = ['ACF VELDEN'];

    if (empty($fields)) {
        $out[] = '';
        $out[] = 'Geen ACF-velden gedetecteerd voor deze pagina.';
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

function dca_tb_parse_acf_fields_block($textblock) {
    $section = dca_tb_section($textblock, 'ACF VELDEN', dca_tb_acf_export_end_markers());

    if ($section === null) {
        return null;
    }

    preg_match_all('/^--- ACF VELD ---\s*\n(.*?)^--- EINDE ACF VELD ---\s*$/ims', $section, $matches);
    $items = [];

    foreach ($matches[1] as $raw) {
        $name = dca_tb_label($raw, 'Naam:', ['Label:', 'Key:', 'Type:', 'Waarde:']);
        $label = dca_tb_label($raw, 'Label:', ['Key:', 'Type:', 'Waarde:']);
        $key = dca_tb_label($raw, 'Key:', ['Type:', 'Waarde:']);
        $type = dca_tb_label($raw, 'Type:', ['Waarde:']);
        $value = dca_tb_label($raw, 'Waarde:');

        $name = sanitize_key((string) $name);
        $key = sanitize_key((string) $key);

        if ($name === '') {
            continue;
        }

        $items[] = [
            'name'  => $name,
            'label' => dca_tb_clean_text((string) $label),
            'key'   => $key,
            'type'  => sanitize_key((string) $type),
            'value' => $value === null ? '' : $value,
        ];
    }

    return $items;
}

function dca_tb_clean_acf_import_value($value, $type) {
    $value = trim((string) $value);
    $type = sanitize_key($type);

    if (in_array($type, ['image', 'file'], true)) {
        $attachment_id = absint(dca_tb_label($value, 'Attachment ID:', ['URL:', 'Alt text:', 'Title:', 'Caption:', 'Description:']));

        if (!$attachment_id && is_numeric($value)) {
            $attachment_id = absint($value);
        }

        if (!$attachment_id) {
            $url = dca_tb_label($value, 'URL:', ['Alt text:', 'Title:', 'Caption:', 'Description:']);
            if ($url && function_exists('attachment_url_to_postid')) {
                $attachment_id = absint(attachment_url_to_postid(esc_url_raw($url)));
            }
        }

        return $attachment_id;
    }

    if ($type === 'gallery') {
        preg_match_all('/\d+/', $value, $matches);
        return array_values(array_unique(array_map('absint', $matches[0] ?? [])));
    }

    if ($value !== '' && in_array(substr($value, 0, 1), ['{', '['], true)) {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
    }

    if (in_array($type, ['wysiwyg', 'textarea', 'oembed'], true)) {
        return dca_tb_clean_html($value);
    }

    if (in_array($type, ['number', 'range'], true)) {
        return is_numeric($value) ? $value + 0 : '';
    }

    if (in_array($type, ['true_false'], true)) {
        return in_array(strtolower($value), ['1', 'true', 'ja', 'yes', 'on'], true) ? 1 : 0;
    }

    return dca_tb_clean_text($value);
}

function dca_tb_validate_dynamic_acf_textblock($textblock) {
    if (dca_tb_marker_count($textblock, 'ACF VELDEN') !== 1) {
        return new WP_Error('dca_invalid_acf_fields', 'Opslaan gestopt: de kop "ACF VELDEN" ontbreekt of komt meerdere keren voor.');
    }

    foreach (['YOAST SEO', 'MEDIA', 'SAMENVATTING', 'UITGELICHTE AFBEELDING'] as $marker) {
        if (dca_tb_marker_count($textblock, $marker) > 1) {
            return new WP_Error('dca_duplicate_marker', 'Opslaan gestopt: de kop "' . $marker . '" komt meerdere keren voor.');
        }
    }

    $featured_validation = dca_tb_validate_featured_image_block($textblock);
    if (is_wp_error($featured_validation)) {
        return $featured_validation;
    }

    $fields = dca_tb_parse_acf_fields_block($textblock);

    if (!is_array($fields)) {
        return new WP_Error('dca_invalid_acf_fields', 'Opslaan gestopt: ACF VELDEN kon niet exact gelezen worden.');
    }

    return true;
}

function dca_tb_save_dynamic_acf_fields($post_id, $textblock) {
    $items = dca_tb_parse_acf_fields_block($textblock);

    if (!is_array($items)) {
        return new WP_Error('dca_invalid_acf_fields', 'Opslaan gestopt: ACF VELDEN kon niet exact gelezen worden.');
    }

    $detected = dca_tb_get_detected_acf_fields($post_id);
    $by_name = [];
    $by_key = [];

    foreach ($detected as $field) {
        $by_name[$field['name']] = $field;
        if (!empty($field['key'])) {
            $by_key[$field['key']] = $field;
        }
    }

    foreach ($items as $item) {
        $field = null;

        if (!empty($item['key']) && isset($by_key[$item['key']])) {
            $field = $by_key[$item['key']];
        } elseif (isset($by_name[$item['name']])) {
            $field = $by_name[$item['name']];
        }

        if (!$field) {
            continue;
        }

        $value = dca_tb_clean_acf_import_value($item['value'], $field['type']);
        $selector = !empty($field['key']) ? $field['key'] : $field['name'];

        update_field($selector, $value, $post_id);
    }

    return true;
}

function dca_tb_build_textblock($post_id) {
    $post = get_post($post_id);

    if (!$post || !dca_tb_is_supported_post_type($post->post_type)) {
        return 'Geen geldig bericht, geldige pagina of geldig product gevonden.';
    }

    /**
     * Berichten: alleen WordPress titel, content, Yoast en media.
     */
    if ($post->post_type === 'post') {
        $out = [
            'TITEL',
            '',
            dca_tb_text($post->post_title),
            '',
            'CONTENT',
            '',
            dca_tb_text($post->post_content),
            '',
            'YOAST SEO',
            '',
            'SEO title:',
            '',
            dca_tb_text(get_post_meta($post_id, '_yoast_wpseo_title', true)),
            '',
            'Meta description:',
            '',
            dca_tb_text(get_post_meta($post_id, '_yoast_wpseo_metadesc', true)),
            '',
            dca_tb_build_summary_block($post_id),
            '',
            dca_tb_build_featured_image_block($post_id),
            '',
            dca_tb_build_media_block($post_id),
        ];

        return trim(implode("\n", $out));
    }

    /**
     * Pagina's: exporteer alleen de ACF-velden die ACF voor dit object detecteert.
     */
    if (!function_exists('get_field_objects')) {
        return 'ACF is niet actief of get_field_objects() is niet beschikbaar.';
    }

    $out = [
        dca_tb_build_acf_fields_block($post_id),
        '',
        'YOAST SEO',
        '',
        'SEO title:',
        '',
        dca_tb_text(get_post_meta($post_id, '_yoast_wpseo_title', true)),
        '',
        'Meta description:',
        '',
        dca_tb_text(get_post_meta($post_id, '_yoast_wpseo_metadesc', true)),
        '',
        dca_tb_build_summary_block($post_id),
        '',
        dca_tb_build_featured_image_block($post_id),
        '',
        dca_tb_build_media_block($post_id),
    ];

    return trim(implode("\n", $out));
}

function dca_tb_validate_textblock($textblock) {
    foreach (['HOOFDTEKST', 'CONTENTBLOK 1', 'CONTENTBLOK 2', 'CONTENTBLOK 3', 'FAQ'] as $marker) {
        $count = dca_tb_marker_count($textblock, $marker);

        if ($count === 0) {
            return new WP_Error('dca_missing_marker', 'Opslaan gestopt: de kop "' . $marker . '" ontbreekt of staat niet op een eigen regel.');
        }

        if ($count > 1) {
            return new WP_Error('dca_duplicate_marker', 'Opslaan gestopt: de kop "' . $marker . '" komt meerdere keren voor.');
        }
    }

    if (dca_tb_marker_count($textblock, 'USP') > 1) {
        return new WP_Error('dca_duplicate_usp', 'Opslaan gestopt: de kop "USP" komt meerdere keren voor.');
    }

    if (dca_tb_marker_count($textblock, 'YOAST SEO') > 1) {
        return new WP_Error('dca_duplicate_yoast', 'Opslaan gestopt: de kop "YOAST SEO" komt meerdere keren voor.');
    }

    if (dca_tb_marker_count($textblock, 'MEDIA') > 1) {
        return new WP_Error('dca_duplicate_media', 'Opslaan gestopt: de kop "MEDIA" komt meerdere keren voor.');
    }

    if (dca_tb_marker_count($textblock, 'SAMENVATTING') > 1) {
        return new WP_Error('dca_duplicate_summary', 'Opslaan gestopt: de kop "SAMENVATTING" komt meerdere keren voor.');
    }

    if (dca_tb_marker_count($textblock, 'UITGELICHTE AFBEELDING') > 1) {
        return new WP_Error('dca_duplicate_featured_image', 'Opslaan gestopt: de kop "UITGELICHTE AFBEELDING" komt meerdere keren voor.');
    }

    $featured_validation = dca_tb_validate_featured_image_block($textblock);
    if (is_wp_error($featured_validation)) {
        return $featured_validation;
    }

    for ($i = 1; $i <= 3; $i++) {
        $block = dca_tb_section($textblock, 'CONTENTBLOK ' . $i, dca_tb_contentblock_end_markers($i));

        if ($block === null) {
            return new WP_Error('dca_invalid_block', 'Opslaan gestopt: CONTENTBLOK ' . $i . ' kon niet exact gelezen worden.');
        }

        if (dca_tb_label_marker_count($block, 'Titel:') !== 1) {
            return new WP_Error('dca_invalid_title', 'Opslaan gestopt: "Titel:" ontbreekt of komt meerdere keren voor in CONTENTBLOK ' . $i . '.');
        }

        if (dca_tb_label_marker_count($block, 'Tekst:') !== 1) {
            return new WP_Error('dca_invalid_text', 'Opslaan gestopt: "Tekst:" ontbreekt of komt meerdere keren voor in CONTENTBLOK ' . $i . '.');
        }

        if (dca_tb_label_marker_count($block, 'Minititel:') > 1) {
            return new WP_Error('dca_invalid_mini', 'Opslaan gestopt: "Minititel:" komt meerdere keren voor in CONTENTBLOK ' . $i . '.');
        }
    }

    $faq = dca_tb_section($textblock, 'FAQ', ['YOAST SEO', 'SAMENVATTING', 'UITGELICHTE AFBEELDING', 'MEDIA']);

    if ($faq === null) {
        return new WP_Error('dca_invalid_faq', 'Opslaan gestopt: FAQ kon niet exact gelezen worden.');
    }

    if (count(dca_tb_blocks($faq)) !== 8) {
        return new WP_Error('dca_invalid_faq_count', 'Opslaan gestopt: onder FAQ moeten exact 8 blokken staan: vraag, antwoord, vraag, antwoord, vraag, antwoord, vraag, antwoord.');
    }

    return true;
}

function dca_tb_validate_post_textblock($textblock) {
    foreach (['TITEL', 'CONTENT', 'YOAST SEO'] as $marker) {
        $count = dca_tb_marker_count($textblock, $marker);

        if ($count === 0) {
            return new WP_Error('dca_missing_marker', 'Opslaan gestopt: de kop "' . $marker . '" ontbreekt of staat niet op een eigen regel.');
        }

        if ($count > 1) {
            return new WP_Error('dca_duplicate_marker', 'Opslaan gestopt: de kop "' . $marker . '" komt meerdere keren voor.');
        }
    }

    if (dca_tb_marker_count($textblock, 'MEDIA') > 1) {
        return new WP_Error('dca_duplicate_media', 'Opslaan gestopt: de kop "MEDIA" komt meerdere keren voor.');
    }

    if (dca_tb_marker_count($textblock, 'SAMENVATTING') > 1) {
        return new WP_Error('dca_duplicate_summary', 'Opslaan gestopt: de kop "SAMENVATTING" komt meerdere keren voor.');
    }

    if (dca_tb_marker_count($textblock, 'UITGELICHTE AFBEELDING') > 1) {
        return new WP_Error('dca_duplicate_featured_image', 'Opslaan gestopt: de kop "UITGELICHTE AFBEELDING" komt meerdere keren voor.');
    }

    $featured_validation = dca_tb_validate_featured_image_block($textblock);
    if (is_wp_error($featured_validation)) {
        return $featured_validation;
    }

    $yoast = dca_tb_section($textblock, 'YOAST SEO', dca_tb_yoast_end_markers());

    if ($yoast === null) {
        return new WP_Error('dca_invalid_yoast', 'Opslaan gestopt: YOAST SEO kon niet exact gelezen worden.');
    }

    if (dca_tb_label_marker_count($yoast, 'SEO title:') !== 1) {
        return new WP_Error('dca_invalid_yoast_title', 'Opslaan gestopt: "SEO title:" ontbreekt of komt meerdere keren voor.');
    }

    if (dca_tb_label_marker_count($yoast, 'Meta description:') !== 1) {
        return new WP_Error('dca_invalid_yoast_desc', 'Opslaan gestopt: "Meta description:" ontbreekt of komt meerdere keren voor.');
    }

    return true;
}

function dca_tb_update_acf_value($field_name, $value, $post_id) {
    $field_name = sanitize_key($field_name);
    $post_id = absint($post_id);

    if ($field_name === '' || !$post_id) {
        return false;
    }

    $updated = false;

    if (function_exists('update_field')) {
        $acf_result = update_field($field_name, $value, $post_id);
        $updated = $updated || (bool) $acf_result;
    }

    // Fallback: update by meta key as well. This fixes cases where ACF cannot resolve
    // a selector by field name because the field reference meta is missing or stale.
    $meta_result = update_post_meta($post_id, $field_name, $value);
    $updated = $updated || (bool) $meta_result;

    return $updated;
}

function dca_tb_save_to_fields($post_id, $textblock, $source = 'save') {
    $post_id = absint($post_id);
    $post = get_post($post_id);

    if (!$post || !dca_tb_is_supported_post_type($post->post_type)) {
        return new WP_Error('dca_invalid_post', 'Geen geldig bericht, geldige pagina of geldig product gevonden.');
    }

    $textblock = trim(str_replace(["\r\n", "\r"], "\n", (string) $textblock));

    /**
     * Berichten: opslaan naar post_title, post_content en Yoast.
     */
    if ($post->post_type === 'post') {
        $validation = dca_tb_validate_post_textblock($textblock);

        if (is_wp_error($validation)) {
            return $validation;
        }

        if (DCA_TB_IMPORT_DRY_RUN) {
            return true;
        }

        dca_tb_add_backup($post_id, $source);

        $title   = dca_tb_section($textblock, 'TITEL', ['CONTENT']);
        $content = dca_tb_section($textblock, 'CONTENT', ['YOAST SEO']);
        $yoast   = dca_tb_section($textblock, 'YOAST SEO', dca_tb_yoast_end_markers());

        $seo_title = dca_tb_label($yoast, 'SEO title:', ['Meta description:']);
        $meta_desc = dca_tb_label($yoast, 'Meta description:');

        $post_update = [
            'ID'           => $post_id,
            'post_title'   => dca_tb_clean_text($title),
            'post_content' => dca_tb_clean_html($content),
        ];

        $summary = dca_tb_section($textblock, 'SAMENVATTING', ['UITGELICHTE AFBEELDING', 'MEDIA']);
        if ($summary !== null) {
            $post_update['post_excerpt'] = dca_tb_clean_text($summary);
        }

        $updated = wp_update_post($post_update, true);

        if (is_wp_error($updated)) {
            return $updated;
        }

        if ($seo_title !== null) {
            update_post_meta($post_id, '_yoast_wpseo_title', dca_tb_clean_text($seo_title));
        }

        if ($meta_desc !== null) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', dca_tb_clean_text($meta_desc));
        }

        $featured = dca_tb_apply_featured_image_from_textblock($post_id, $textblock);
        if (is_wp_error($featured)) {
            return $featured;
        }

        dca_tb_mark_updated($post_id);
        clean_post_cache($post_id);

        return true;
    }

    /**
     * Pagina's: dynamische ACF-logica. Alleen velden verwerken die ACF op
     * deze doelpagina detecteert en die ook in de export staan.
     */
    if (!function_exists('update_field') || !function_exists('get_field_objects')) {
        return new WP_Error('dca_acf_missing', 'ACF is niet actief of update_field()/get_field_objects() is niet beschikbaar.');
    }

    if (dca_tb_marker_count($textblock, 'ACF VELDEN') !== 1) {
        return new WP_Error('dca_missing_acf_fields', 'Opslaan gestopt: pagina-import accepteert alleen exports met de kop "ACF VELDEN". Oude vaste ACF-layouts worden niet meer geïmporteerd.');
    }

    $validation = dca_tb_validate_dynamic_acf_textblock($textblock);

    if (is_wp_error($validation)) {
        return $validation;
    }

    if (DCA_TB_IMPORT_DRY_RUN) {
        return true;
    }

    dca_tb_add_backup($post_id, $source);

    $acf_save = dca_tb_save_dynamic_acf_fields($post_id, $textblock);
    if (is_wp_error($acf_save)) {
        return $acf_save;
    }

    if (dca_tb_marker_count($textblock, 'YOAST SEO') === 1) {
        $yoast = dca_tb_section($textblock, 'YOAST SEO', dca_tb_yoast_end_markers());
        $seo_title = dca_tb_label($yoast, 'SEO title:', ['Meta description:']);
        $meta_desc = dca_tb_label($yoast, 'Meta description:');

        if ($seo_title !== null) {
            update_post_meta($post_id, '_yoast_wpseo_title', dca_tb_clean_text($seo_title));
        }

        if ($meta_desc !== null) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', dca_tb_clean_text($meta_desc));
        }
    }

    $summary = dca_tb_section($textblock, 'SAMENVATTING', ['UITGELICHTE AFBEELDING', 'MEDIA']);
    if ($summary !== null) {
        $updated_excerpt = wp_update_post([
            'ID'           => $post_id,
            'post_excerpt' => dca_tb_clean_text($summary),
        ], true);

        if (is_wp_error($updated_excerpt)) {
            return $updated_excerpt;
        }
    }

    $featured = dca_tb_apply_featured_image_from_textblock($post_id, $textblock);
    if (is_wp_error($featured)) {
        return $featured;
    }

    dca_tb_mark_updated($post_id);
    clean_post_cache($post_id);

    return true;

}

function dca_tb_build_bulk_export($post_ids) {
    $out = [];

    foreach ($post_ids as $post_id) {
        $post_id = absint($post_id);
        $post = $post_id ? get_post($post_id) : null;

        if (!$post || !dca_tb_is_supported_post_type($post->post_type) || !current_user_can('edit_post', $post_id)) {
            continue;
        }

        if (dca_tb_template_skip_reason($post_id) !== '') {
            continue;
        }

        array_push(
            $out,
            str_repeat('=', 80),
            dca_tb_post_type_label_single($post_id) . ': ' . get_the_title($post_id),
            'URL: ' . get_permalink($post_id),
            'ID: ' . $post_id,
            str_repeat('=', 80),
            '',
            dca_tb_build_textblock($post_id),
            '',
            ''
        );
    }

    return empty($out)
        ? new WP_Error('dca_no_pages', 'Er zijn geen geldige berichten, pagina’s of producten geselecteerd.')
        : trim(implode("\n", $out));
}

function dca_tb_normalize_title_for_match($value) {
    $value = wp_strip_all_tags(html_entity_decode((string) $value, ENT_QUOTES, get_bloginfo('charset')));
    $value = preg_replace('/\s+/u', ' ', trim($value));

    return strtolower($value);
}

function dca_tb_title_matches_post($title, $post_id) {
    $title = trim((string) $title);

    if ($title === '') {
        return false;
    }

    return dca_tb_normalize_title_for_match($title) === dca_tb_normalize_title_for_match(get_the_title($post_id));
}

function dca_tb_url_matches_post($url, $post_id) {
    $url = trim((string) $url);

    if ($url === '') {
        return true;
    }

    $source_path = dca_tb_normalize_compare_url_path($url);
    $target_path = dca_tb_normalize_compare_url_path(get_permalink($post_id));

    if ($source_path === '' || $target_path === '') {
        return false;
    }

    return $source_path === $target_path;
}


function dca_tb_import_label_to_post_type($label) {
    $label = strtoupper(trim((string) $label));

    if ($label === 'BERICHT') {
        return 'post';
    }

    if ($label === 'PRODUCT') {
        return 'product';
    }

    return 'page';
}

function dca_tb_resolve_page($page_id, $url, $title, $expected_post_type = '') {
    $page_id = absint($page_id);
    $url = trim((string) $url);
    $title = trim((string) $title);
    $expected_post_type = sanitize_key($expected_post_type);

    if ($expected_post_type !== '' && !dca_tb_is_supported_post_type($expected_post_type)) {
        return 0;
    }

    $allowed_post_types = $expected_post_type !== '' ? [$expected_post_type] : dca_tb_supported_post_types();

    // Prefer the exported ID when it still points to the same item. If the ID now
    // points to another item, do not fail immediately: on staging/live copies IDs
    // can drift while the URL or exact title still identifies the correct target.
    if ($page_id && ($post = get_post($page_id)) && in_array($post->post_type, $allowed_post_types, true)) {
        $url_matches = dca_tb_url_matches_post($url, $page_id);
        $title_matches = dca_tb_title_matches_post($title, $page_id);

        if (($url === '' && $title === '') || $url_matches || $title_matches) {
            return $page_id;
        }
    }

    if ($url !== '') {
        $url_id = url_to_postid($url);

        if ($url_id && ($post = get_post($url_id)) && in_array($post->post_type, $allowed_post_types, true)) {
            return absint($url_id);
        }

        $source_path = dca_tb_normalize_compare_url_path($url);

        if ($source_path !== '') {
            $q = new WP_Query([
                'post_type'      => $allowed_post_types,
                'post_status'    => 'any',
                'posts_per_page' => 20,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);

            foreach ((array) $q->posts as $candidate_id) {
                if (dca_tb_normalize_compare_url_path(get_permalink($candidate_id)) === $source_path) {
                    return absint($candidate_id);
                }
            }
        }
    }

    if ($title !== '') {
        $q = new WP_Query([
            'post_type'      => $allowed_post_types,
            'post_status'    => 'any',
            'title'          => $title,
            'posts_per_page' => 2,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        if (count((array) $q->posts) === 1 && !empty($q->posts[0])) {
            return absint($q->posts[0]);
        }
    }

    return 0;
}

function dca_tb_validate_import_size($txt) {
    $bytes = strlen((string) $txt);

    if ($bytes > DCA_TB_MAX_IMPORT_BYTES) {
        return new WP_Error('dca_import_too_large', 'Het TXT-bestand is te groot. Maximaal toegestaan: ' . size_format(DCA_TB_MAX_IMPORT_BYTES) . '.');
    }

    return true;
}

function dca_tb_sanitize_post_id_list($post_ids) {
    if (!is_array($post_ids)) {
        return [];
    }

    $post_ids = array_values(array_unique(array_filter(array_map('absint', $post_ids))));

    return $post_ids;
}

function dca_tb_parse_bulk_file($txt) {
    $size_check = dca_tb_validate_import_size($txt);

    if (is_wp_error($size_check)) {
        return $size_check;
    }

    $txt = preg_replace('/^\xEF\xBB\xBF/', '', (string) $txt);
    $txt = trim(str_replace(["\r\n", "\r"], "\n", $txt));

    if ($txt === '') {
        return new WP_Error('dca_empty_import', 'Het TXT-bestand is leeg.');
    }

    $items = [];

    // Keep the export format compatible, but make the import/check more tolerant:
    // - labels may contain extra spaces around the colon;
    // - blank lines around separators are allowed;
    // - matching is case-insensitive.
    $pattern = '/^={10,}[^\S\n]*\n\s*(PAGINA|BERICHT|PRODUCT)\s*:\s*(.*?)\n\s*URL\s*:\s*(.*?)\n\s*ID\s*:\s*(\d+)\s*\n={10,}[^\S\n]*(?:\n)+(.*?)(?=^={10,}[^\S\n]*\n\s*(?:PAGINA|BERICHT|PRODUCT)\s*:|\z)/ims';

    if (preg_match_all($pattern, $txt, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $items[] = [
                'source_type'  => dca_tb_import_label_to_post_type($m[1]),
                'source_title' => trim($m[2]),
                'source_url'   => trim($m[3]),
                'source_id'    => absint($m[4]),
                'textblock'    => trim($m[5]),
            ];
        }
    }

    return empty($items)
        ? new WP_Error('dca_invalid_import_format', 'Geen geldige blokken gevonden. Gebruik tekst met PAGINA/BERICHT/PRODUCT, URL en ID uit een Content Sync-export.')
        : $items;
}

function dca_tb_bulk_preview($txt) {
    $items = dca_tb_parse_bulk_file($txt);

    if (is_wp_error($items)) {
        return $items;
    }

    if (count($items) > DCA_TB_MAX_IMPORT_PAGES) {
        return new WP_Error('dca_too_many_import_pages', 'Import bevat ' . count($items) . ' items. Maximaal toegestaan: ' . absint(DCA_TB_MAX_IMPORT_PAGES) . '.');
    }

    $preview = [];

    foreach ($items as $i => $item) {
        $target = dca_tb_resolve_page($item['source_id'], $item['source_url'], $item['source_title'], $item['source_type'] ?? '');
        $status = 'error';
        $message = '';
        $media = dca_tb_preview_media_changes($item['textblock']);

        if (!$target) {
            $message = 'Overgeslagen: geen bijpassend bericht, pagina of product gevonden.';
        } elseif (!current_user_can('edit_post', $target)) {
            $message = 'Overgeslagen: geen rechten om dit item te bewerken.';
        } else {
            $target_post = get_post($target);
            $template_reason = $target_post ? dca_tb_template_skip_reason($target) : '';

            if ($template_reason !== '') {
                $message = $template_reason;
            } else {
                if ($target_post && $target_post->post_type === 'post') {
                    $validation = dca_tb_validate_post_textblock($item['textblock']);
                } else {
                    $validation = dca_tb_validate_dynamic_acf_textblock($item['textblock']);
                }

                if (is_wp_error($validation)) {
                    $message = 'Overgeslagen: ' . $validation->get_error_message();
                } else {
                    $status = $media['errors'] > 0 ? 'partial' : 'success';
                    $message = 'Klaar om op te slaan. Media: ' . absint($media['found']) . ' gevonden, ' . absint($media['renames']) . ' bestandsnamen te hernoemen';
                    if ($media['errors'] > 0) {
                        $message .= ', ' . absint($media['errors']) . ' mediafout(en). Tekst wordt wel geïmporteerd.';
                    }
                    $message .= '.';
                }
            }
        }

        $preview[] = [
            'index'          => $i,
            'source_title'   => $item['source_title'],
            'source_id'      => $item['source_id'],
            'target_post_id' => $target,
            'target_title'   => $target ? get_the_title($target) : '',
            'status'         => $status,
            'message'        => $message,
            'media_found'    => absint($media['found']),
            'media_renames'  => absint($media['renames']),
            'media_errors'   => absint($media['errors']),
        ];
    }

    return $preview;
}

function dca_tb_bulk_save($txt) {
    $items = dca_tb_parse_bulk_file($txt);

    if (is_wp_error($items)) {
        return $items;
    }

    if (count($items) > DCA_TB_MAX_IMPORT_PAGES) {
        return new WP_Error('dca_too_many_import_pages', 'Import bevat ' . count($items) . ' items. Maximaal toegestaan: ' . absint(DCA_TB_MAX_IMPORT_PAGES) . '.');
    }

    $imported = 0;
    $skipped  = 0;
    $media_updated = 0;
    $renamed = 0;
    $media_errors = 0;
    $url_replaces = 0;
    $results  = [];

    foreach ($items as $i => $item) {
        $target = dca_tb_resolve_page($item['source_id'], $item['source_url'], $item['source_title'], $item['source_type'] ?? '');
        $title  = $item['source_title'] ?: 'Blok ' . ($i + 1);

        if (!$target) {
            $skipped++;
            $results[] = [
                'index'          => $i,
                'source_title'   => $title,
                'source_id'      => $item['source_id'],
                'target_post_id' => 0,
                'target_title'   => '',
                'status'         => 'skipped',
                'message'        => 'Overgeslagen: geen bijpassend bericht, pagina of product gevonden.',
            ];
            continue;
        }

        if (!current_user_can('edit_post', $target)) {
            $skipped++;
            $results[] = [
                'index'          => $i,
                'source_title'   => $title,
                'source_id'      => $item['source_id'],
                'target_post_id' => $target,
                'target_title'   => get_the_title($target),
                'status'         => 'skipped',
                'message'        => 'Overgeslagen: geen rechten om dit item te bewerken.',
            ];
            continue;
        }


        $target_post = get_post($target);
        $template_reason = $target_post ? dca_tb_template_skip_reason($target) : '';

        if ($template_reason !== '') {
            $skipped++;
            $results[] = [
                'index'          => $i,
                'source_title'   => $title,
                'source_id'      => $item['source_id'],
                'target_post_id' => $target,
                'target_title'   => get_the_title($target),
                'status'         => 'skipped',
                'message'        => $template_reason,
            ];
            continue;
        }

        $validation = ($target_post && $target_post->post_type === 'post')
            ? dca_tb_validate_post_textblock($item['textblock'])
            : dca_tb_validate_dynamic_acf_textblock($item['textblock']);

        if (is_wp_error($validation)) {
            $skipped++;
            $results[] = [
                'index'          => $i,
                'source_title'   => $title,
                'source_id'      => $item['source_id'],
                'target_post_id' => $target,
                'target_title'   => get_the_title($target),
                'status'         => 'skipped',
                'message'        => 'Overgeslagen: ' . $validation->get_error_message(),
            ];
            continue;
        }

        $save = dca_tb_save_to_fields($target, $item['textblock'], 'bulk');

        if (is_wp_error($save)) {
            $skipped++;
            $results[] = [
                'index'          => $i,
                'source_title'   => $title,
                'source_id'      => $item['source_id'],
                'target_post_id' => $target,
                'target_title'   => get_the_title($target),
                'status'         => 'skipped',
                'message'        => 'Overgeslagen: ' . $save->get_error_message(),
            ];
            continue;
        }

        $media_result = dca_tb_save_media_items($target, $item['textblock']);
        $media_updated += absint($media_result['media_updated']);
        $renamed       += absint($media_result['renamed']);
        $media_errors  += absint($media_result['media_errors']);
        $url_replaces  += absint($media_result['url_replaces']);

        $imported++;
        $status = $media_result['media_errors'] > 0 ? 'partial' : 'success';
        $message = (DCA_TB_IMPORT_DRY_RUN ? 'Dry-run: zou importeren. ' : 'Geïmporteerd. ') . 'Media bijgewerkt: ' . absint($media_result['media_updated']) . ', bestandsnamen hernoemd: ' . absint($media_result['renamed']) . ', URL-vervangingen: ' . absint($media_result['url_replaces']) . '.';
        if ($media_result['media_errors'] > 0) {
            $message .= ' Mediafouten: ' . absint($media_result['media_errors']) . '. ' . implode(' ', array_slice($media_result['messages'], 0, 3));
        }

        $results[] = [
            'index'          => $i,
            'source_title'   => $title,
            'source_id'      => $item['source_id'],
            'target_post_id' => $target,
            'target_title'   => get_the_title($target),
            'status'         => $status,
            'message'        => $message,
        ];
    }

    $summary = [
        'imported'      => $imported,
        'skipped'       => $skipped,
        'media_updated' => $media_updated,
        'renamed'       => $renamed,
        'media_errors'  => $media_errors,
        'url_replaces'  => $url_replaces,
        'errors'        => $skipped + $media_errors,
        'results'       => $results,
    ];

    dca_tb_store_import_log($summary);

    return $summary;
}

function dca_tb_store_import_log($summary) {
    $summary = is_array($summary) ? $summary : [];
    $summary['time'] = current_time('timestamp');
    $summary['user'] = get_current_user_id();
    $summary['dry_run'] = (bool) DCA_TB_IMPORT_DRY_RUN;
    update_option('_dca_tb_last_import_log', $summary, false);
    return $summary;
}

function dca_tb_get_last_import_log() {
    $log = get_option('_dca_tb_last_import_log', []);
    return is_array($log) ? $log : [];
}

function dca_tb_format_import_log_text($log = null) {
    $log = is_array($log) ? $log : dca_tb_get_last_import_log();
    if (!$log) {
        return 'Geen importlog gevonden.';
    }

    $lines = [];
    $lines[] = 'DCA TXT importlog';
    $lines[] = 'Datum: ' . (!empty($log['time']) ? date_i18n('d-m-Y H:i:s', absint($log['time'])) : '-');
    $lines[] = 'Gebruiker ID: ' . absint($log['user'] ?? 0);
    $lines[] = 'Dry-run: ' . (!empty($log['dry_run']) ? 'ja' : 'nee');
    $lines[] = str_repeat('-', 60);
    $lines[] = 'Items geïmporteerd: ' . absint($log['imported'] ?? 0);
    $lines[] = 'Items overgeslagen: ' . absint($log['skipped'] ?? 0);
    $lines[] = 'Media-items bijgewerkt: ' . absint($log['media_updated'] ?? 0);
    $lines[] = 'Bestandsnamen hernoemd: ' . absint($log['renamed'] ?? 0);
    $lines[] = 'Mediafouten: ' . absint($log['media_errors'] ?? 0);
    $lines[] = 'URL-vervangingen: ' . absint($log['url_replaces'] ?? 0);
    $lines[] = str_repeat('-', 60);

    foreach (($log['results'] ?? []) as $row) {
        $lines[] = '[' . strtoupper((string) ($row['status'] ?? 'info')) . '] ' . (string) ($row['source_title'] ?? '-') . ' -> ' . (string) ($row['target_title'] ?? '-') . ' (#' . absint($row['target_post_id'] ?? 0) . ')';
        $lines[] = (string) ($row['message'] ?? '');
        $lines[] = '';
    }

    return trim(implode("\n", $lines));
}

function dca_tb_restore_last_page_backup($post_id) {
    $post_id = absint($post_id);
    if (!dca_tb_can_edit_post($post_id)) {
        return new WP_Error('dca_restore_no_permission', 'Geen rechten om deze pagina of dit bericht te herstellen.');
    }

    $post_type = get_post_type($post_id);
    if ($post_type === 'page' && !function_exists('update_field')) {
        return new WP_Error('dca_restore_acf_missing', 'ACF is niet actief of update_field() is niet beschikbaar.');
    }

    $backups = get_post_meta($post_id, '_dca_tb_backups', true);
    if (!is_array($backups) || empty($backups)) {
        return new WP_Error('dca_restore_no_backup', 'Geen back-up gevonden.');
    }

    $last = end($backups);
    $text = isset($last['text']) ? (string) $last['text'] : '';
    if ($text === '') {
        return new WP_Error('dca_restore_empty_backup', 'Laatste back-up bevat geen tekst.');
    }

    $old_dry_run = defined('DCA_TB_IMPORT_DRY_RUN') && DCA_TB_IMPORT_DRY_RUN;
    if ($old_dry_run) {
        return new WP_Error('dca_restore_dry_run', 'Rollback is niet beschikbaar zolang dry-run actief is.');
    }

    return dca_tb_save_to_fields($post_id, $text, 'rollback');
}

function dca_tb_normalize_upload_relative_path($relative_path) {
    $relative_path = wp_normalize_path((string) $relative_path);
    $relative_path = ltrim($relative_path, '/\\');

    if ($relative_path === '' || strpos($relative_path, '..') !== false) {
        return '';
    }

    return $relative_path;
}

function dca_tb_restore_attachment_file_path($attachment_id, $target_relative) {
    $attachment_id = absint($attachment_id);
    $target_relative = dca_tb_normalize_upload_relative_path($target_relative);

    if (!$attachment_id || $target_relative === '') {
        return true;
    }

    $uploads = wp_upload_dir();
    $base = realpath($uploads['basedir']);
    $current_file = get_attached_file($attachment_id, true);
    $current_real = $current_file ? realpath($current_file) : false;
    $target_file = trailingslashit($uploads['basedir']) . $target_relative;
    $target_dir = dirname($target_file);
    $target_dir_real = realpath($target_dir);

    if (!$base || !$current_real || !$target_dir_real) {
        return new WP_Error('dca_media_restore_path_missing', 'Media-rollback gestopt: huidig of doelpad kon niet veilig worden gelezen.');
    }

    $base_path = trailingslashit(wp_normalize_path($base));
    $current_path = wp_normalize_path($current_real);
    $target_dir_path = trailingslashit(wp_normalize_path($target_dir_real));
    $target_path = wp_normalize_path($target_file);

    if (strpos($current_path, $base_path) !== 0 || strpos($target_dir_path, $base_path) !== 0 || strpos($target_path, $base_path) !== 0) {
        return new WP_Error('dca_media_restore_outside_uploads', 'Media-rollback gestopt: bestand staat niet veilig binnen uploads.');
    }

    if (wp_normalize_path($current_file) === $target_path) {
        return true;
    }

    if (file_exists($target_file)) {
        return new WP_Error('dca_media_restore_target_exists', 'Media-rollback gestopt: doelbestand bestaat al.');
    }

    if (!rename($current_file, $target_file)) {
        return new WP_Error('dca_media_restore_rename_failed', 'Media-rollback gestopt: fysiek terug hernoemen is mislukt.');
    }

    clearstatcache(true, $current_file);
    clearstatcache(true, $target_file);

    return true;
}

function dca_tb_restore_last_media_backup($attachment_id) {
    $attachment_id = absint($attachment_id);
    if (!$attachment_id || get_post_type($attachment_id) !== 'attachment' || !current_user_can('edit_post', $attachment_id)) {
        return new WP_Error('dca_media_restore_no_permission', 'Geen rechten om deze media te herstellen.');
    }
    if (DCA_TB_IMPORT_DRY_RUN) {
        return new WP_Error('dca_media_restore_dry_run', 'Media-rollback is niet beschikbaar zolang dry-run actief is.');
    }

    $backups = get_post_meta($attachment_id, '_dca_tb_media_backups', true);
    if (!is_array($backups) || empty($backups)) {
        return new WP_Error('dca_media_restore_no_backup', 'Geen media-backup gevonden.');
    }

    $last = end($backups);

    if (!empty($last['attached_file'])) {
        $file_restore = dca_tb_restore_attachment_file_path($attachment_id, $last['attached_file']);
        if (is_wp_error($file_restore)) {
            return $file_restore;
        }
    }

    $updated = wp_update_post([
        'ID'           => $attachment_id,
        'post_title'   => dca_tb_clean_text($last['title'] ?? ''),
        'post_excerpt' => dca_tb_clean_text($last['caption'] ?? ''),
        'post_content' => dca_tb_clean_html($last['description'] ?? ''),
        'guid'         => esc_url_raw($last['guid'] ?? ''),
    ], true);

    if (is_wp_error($updated)) {
        return $updated;
    }

    update_post_meta($attachment_id, '_wp_attachment_image_alt', dca_tb_clean_text($last['alt'] ?? ''));
    if (!empty($last['attached_file'])) {
        update_post_meta($attachment_id, '_wp_attached_file', dca_tb_normalize_upload_relative_path($last['attached_file']));
    }
    if (!empty($last['metadata']) && is_array($last['metadata'])) {
        wp_update_attachment_metadata($attachment_id, $last['metadata']);
    }

    clean_post_cache($attachment_id);
    return true;
}


function dca_tb_can_edit_post($post_id) {
    $post_id = absint($post_id);
    $post = $post_id ? get_post($post_id) : null;

    return $post && dca_tb_is_supported_post_type($post->post_type) && current_user_can('edit_post', $post_id);
}

function dca_tb_current_user_can_use_manager() {
    return current_user_can('edit_posts') || current_user_can('edit_pages') || current_user_can('edit_products');
}

function dca_tb_require_manager_access() {
    if (!dca_tb_current_user_can_use_manager()) {
        wp_send_json_error(['message' => 'Geen rechten om de Content Sync Manager te gebruiken.'], 403);
    }
}


function dca_tb_request_has_destructive_confirmation() {
    return isset($_POST['destructive_confirm']) && hash_equals('1', (string) wp_unslash($_POST['destructive_confirm']));
}

function dca_tb_require_destructive_confirmation() {
    if (DCA_TB_IMPORT_DRY_RUN) {
        return;
    }

    if (!dca_tb_request_has_destructive_confirmation()) {
        wp_send_json_error(['message' => 'Opslaan of importeren is geblokkeerd: bevestig de destructieve actie opnieuw.'], 403);
    }
}

function dca_tb_require_ajax_access() {
    if (!check_ajax_referer('dca_acf_textblock_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Beveiligingscontrole verlopen of ongeldig. Herlaad de adminpagina en probeer opnieuw.'], 403);
    }

    dca_tb_require_manager_access();
}

add_action('wp_ajax_dca_get_acf_textblock', function () {
    dca_tb_require_ajax_access();

    $post_id = absint($_POST['post_id'] ?? 0);

    if (!dca_tb_can_edit_post($post_id)) {
        wp_send_json_error(['message' => 'Geen toegang tot deze pagina.']);
    }

    wp_send_json_success([
        'title'    => get_the_title($post_id),
        'text'     => dca_tb_build_textblock($post_id),
        'view_url' => get_permalink($post_id),
    ]);
});

add_action('wp_ajax_dca_preload_acf_textblocks', function () {
    dca_tb_require_ajax_access();

    $post_ids = isset($_POST['post_ids']) && is_array($_POST['post_ids'])
        ? dca_tb_sanitize_post_id_list($_POST['post_ids'])
        : [];

    if (count($post_ids) > DCA_TB_MAX_IMPORT_PAGES) {
        $post_ids = array_slice($post_ids, 0, DCA_TB_MAX_IMPORT_PAGES);
    }

    $items = [];

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);

        if ($post && dca_tb_is_supported_post_type($post->post_type) && current_user_can('edit_post', $post_id)) {
            $items[$post_id] = [
                'title'    => get_the_title($post_id),
                'text'     => dca_tb_build_textblock($post_id),
                'view_url' => get_permalink($post_id),
            ];
        }
    }

    wp_send_json_success(['items' => $items]);
});

add_action('wp_ajax_dca_save_acf_textblock', function () {
    dca_tb_require_ajax_access();
    dca_tb_require_destructive_confirmation();

    $post_id = absint($_POST['post_id'] ?? 0);
    $text = isset($_POST['textblock']) ? wp_unslash($_POST['textblock']) : '';

    if (!dca_tb_can_edit_post($post_id)) {
        wp_send_json_error(['message' => 'Geen toegang tot deze pagina.']);
    }

    $save = dca_tb_save_to_fields($post_id, $text, 'single');

    if (is_wp_error($save)) {
        wp_send_json_error(['message' => $save->get_error_message()]);
    }

    $media_result = dca_tb_save_media_items($post_id, $text);
    $message = 'Opgeslagen. Back-up is automatisch gemaakt.';

    if ($media_result['media_updated'] > 0 || $media_result['renamed'] > 0 || $media_result['media_errors'] > 0) {
        $message .= ' Media bijgewerkt: ' . absint($media_result['media_updated']) . ', bestandsnamen hernoemd: ' . absint($media_result['renamed']) . ', mediafouten: ' . absint($media_result['media_errors']) . '.';
    }

    wp_send_json_success(['message' => $message]);
});

add_action('wp_ajax_dca_bulk_get_acf_textblocks', function () {
    dca_tb_require_ajax_access();

    $post_ids = isset($_POST['post_ids']) && is_array($_POST['post_ids'])
        ? dca_tb_sanitize_post_id_list($_POST['post_ids'])
        : [];

    if (!$post_ids) {
        wp_send_json_error(['message' => 'Selecteer eerst één of meerdere items.']);
    }

    if (count($post_ids) > DCA_TB_MAX_IMPORT_PAGES) {
        wp_send_json_error(['message' => 'Export bevat ' . count($post_ids) . ' items. Maximaal toegestaan: ' . absint(DCA_TB_MAX_IMPORT_PAGES) . '.']);
    }

    $text = dca_tb_build_bulk_export($post_ids);

    if (is_wp_error($text)) {
        wp_send_json_error(['message' => $text->get_error_message()]);
    }

    wp_send_json_success([
        'text'     => $text,
        'filename' => 'content-sync-' . date_i18n('Y-m-d-H-i', current_time('timestamp')) . '.txt',
    ]);
});

add_action('wp_ajax_dca_txt_import_preview', function () {
    dca_tb_require_ajax_access();

    $preview = dca_tb_bulk_preview(isset($_POST['txt_content']) ? wp_unslash($_POST['txt_content']) : '');

    if (is_wp_error($preview)) {
        wp_send_json_error(['message' => $preview->get_error_message()]);
    }

    wp_send_json_success(['items' => $preview]);
});

add_action('wp_ajax_dca_txt_import_run', function () {
    dca_tb_require_ajax_access();
    dca_tb_require_destructive_confirmation();

    $result = dca_tb_bulk_save(isset($_POST['txt_content']) ? wp_unslash($_POST['txt_content']) : '');

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success([
        'message'  => 'Import voltooid: ' . absint($result['imported']) . ' items geïmporteerd, ' . absint($result['skipped']) . ' items overgeslagen, ' . absint($result['media_updated']) . ' media-items bijgewerkt, ' . absint($result['renamed']) . ' bestandsnamen hernoemd, ' . absint($result['media_errors']) . ' mediafouten. URL-vervangingen: ' . absint($result['url_replaces']) . '.',
        'imported' => absint($result['imported']),
        'skipped'  => absint($result['skipped']),
        'media_updated' => absint($result['media_updated']),
        'renamed' => absint($result['renamed']),
        'media_errors' => absint($result['media_errors']),
        'items'    => $result['results'],
    ]);
});


add_action('wp_ajax_dca_get_last_import_log', function () {
    dca_tb_require_ajax_access();

    wp_send_json_success([
        'log'      => dca_tb_get_last_import_log(),
        'text'     => dca_tb_format_import_log_text(),
        'filename' => 'dca-importlog-' . date_i18n('Y-m-d-H-i', current_time('timestamp')) . '.txt',
    ]);
});

add_action('wp_ajax_dca_restore_last_page_backup', function () {
    dca_tb_require_ajax_access();
    dca_tb_require_destructive_confirmation();

    $post_id = absint($_POST['post_id'] ?? 0);
    $restore = dca_tb_restore_last_page_backup($post_id);

    if (is_wp_error($restore)) {
        wp_send_json_error(['message' => $restore->get_error_message()]);
    }

    wp_send_json_success(['message' => 'Laatste pagina-backup is hersteld.']);
});

add_action('wp_ajax_dca_restore_last_media_backup', function () {
    dca_tb_require_ajax_access();
    dca_tb_require_destructive_confirmation();

    $attachment_id = absint($_POST['attachment_id'] ?? 0);
    $restore = dca_tb_restore_last_media_backup($attachment_id);

    if (is_wp_error($restore)) {
        wp_send_json_error(['message' => $restore->get_error_message()]);
    }

    wp_send_json_success(['message' => 'Laatste media-backup is hersteld.']);
});


function dca_tb_should_load_admin_ui($hook_suffix = '') {
    if ($hook_suffix !== '' && $hook_suffix !== 'edit.php') {
        return false;
    }

    $screen = get_current_screen();

    return $screen
        && $screen->base === 'edit'
        && dca_tb_is_supported_post_type($screen->post_type);
}

function dca_tb_get_admin_asset_settings() {
    $screen = get_current_screen();
    $status_filter = dca_tb_get_list_status_filter();
    $template_filter = dca_tb_get_list_template_filter();
    $base_url = ($screen && $screen->post_type === 'post')
        ? admin_url('edit.php')
        : admin_url('edit.php?post_type=' . ($screen ? $screen->post_type : 'page'));

    if ($template_filter !== '' && $screen && dca_tb_is_supported_post_type($screen->post_type)) {
        $base_url = add_query_arg('dca_tb_template', $template_filter, $base_url);
    }

    $not_done_url = add_query_arg('dca_tb_status', 'not_done', $base_url);
    $filter_url = $status_filter === 'not_done' ? $base_url : $not_done_url;

    return [
        'nonce'       => wp_create_nonce('dca_acf_textblock_nonce'),
        'filterUrl'   => esc_url_raw($filter_url),
        'notDoneUrl'  => esc_url_raw($not_done_url),
        'filterLabel' => $status_filter === 'not_done' ? 'Toon alles' : 'Verberg vandaag bijgewerkt',
        'ajaxUrl'     => admin_url('admin-ajax.php'),
    ];
}

function dca_tb_enqueue_admin_assets($hook_suffix) {
    if (!dca_tb_should_load_admin_ui($hook_suffix)) {
        return;
    }

    wp_enqueue_style(
        'dca-tb-admin',
        DCA_TB_PLUGIN_URL . 'assets/admin.css',
        [],
        DCA_TB_VERSION
    );

    wp_enqueue_script(
        'dca-tb-admin',
        DCA_TB_PLUGIN_URL . 'assets/admin.js',
        [],
        DCA_TB_VERSION,
        true
    );

    wp_add_inline_script(
        'dca-tb-admin',
        'window.dcaTbSettings = ' . wp_json_encode(dca_tb_get_admin_asset_settings()) . ';',
        'before'
    );
}
add_action('admin_enqueue_scripts', 'dca_tb_enqueue_admin_assets');

add_action('admin_footer-edit.php', function () {
    $screen = get_current_screen();
    
    if (
        !$screen ||
        $screen->base !== 'edit' ||
        !dca_tb_is_supported_post_type($screen->post_type) ||
        !dca_tb_current_user_can_use_manager()
    ) {
        return;
    }
    

    $html = <<<'HTML'
    <div class="dca-modal" id="dca-single-modal">
            <div class="dca-box">
                <div class="dca-head">
                    <h2 id="dca-single-title">Content Sync</h2>
                    <button type="button" class="button dca-close-single">Sluiten</button>
                </div>
                <div class="dca-content">
                    <textarea class="dca-textarea" id="dca-single-output"></textarea>
                    <div class="dca-actions">
                        <button type="button" class="button button-primary" id="dca-single-save">Opslaan</button>
                        <button type="button" class="button" id="dca-single-copy">Kopieer tekst</button>
                        <button type="button" class="button" id="dca-single-download">Download .txt</button>
                        <a class="button" id="dca-single-view" href="#" target="_blank" rel="noopener">Open voorkant</a>
                        <span class="dca-status" id="dca-single-status"></span>
                    </div>
                </div>
            </div>
        </div>
    
        <div class="dca-modal" id="dca-bulk-modal">
            <div class="dca-box">
                <div class="dca-head">
                    <h2>Bulkeditor</h2>
                    <button type="button" class="button dca-close-bulk">Sluiten</button>
                </div>
                <div class="dca-content">
                    <textarea class="dca-textarea" id="dca-bulk-output"></textarea>
                    <div class="dca-actions">
                        <button type="button" class="button" id="dca-bulk-check">Controleer bulktekst</button>
                        <button type="button" class="button button-primary" id="dca-bulk-save" disabled>Bulk opslaan</button>
                        <button type="button" class="button" id="dca-bulk-copy">Kopieer alles</button>
                        <button type="button" class="button" id="dca-bulk-download">Download .txt</button>
                        <span class="dca-status" id="dca-bulk-status"></span>
                    </div>
                    <div class="dca-preview" id="dca-bulk-preview"></div>
                </div>
            </div>
        </div>
    
        <div class="dca-modal" id="dca-import-modal">
            <div class="dca-box">
                <div class="dca-head">
                    <h2>TXT importeren</h2>
                    <button type="button" class="button dca-close-import">Sluiten</button>
                </div>
                <div class="dca-content">
                    <p class="dca-warning">Gebruik een TXT-bestand dat via “Exporteer als .txt” is gemaakt. Berichten, pagina’s en producten met een standaardtemplate worden verwerkt; Elementor Canvas en Elementor Full Width worden overgeslagen. Media kan alt, title, caption, description en fysieke bestandsnaam wijzigen.</p>
                    <input type="file" id="dca-import-file" accept=".txt,text/plain">
                    <div class="dca-actions">
                        <button type="button" class="button" id="dca-import-preview">Controleer bestand</button>
                        <button type="button" class="button button-primary" id="dca-import-run" disabled>Importeer en overschrijf</button>
                        <span class="dca-status" id="dca-import-status"></span>
                    </div>
                    <div class="dca-preview" id="dca-import-preview-box"></div>
                </div>
            </div>
        </div>
    
        <div class="dca-toast" id="dca-toast"></div>
    HTML;
    // Static admin markup only; no user-supplied values are concatenated here.
    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
});
