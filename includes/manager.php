<?php
/**
 * Adminlogica voor TXT-import en -export van contentvelden.
 *
 * @package ContentSyncManager
 */

defined('ABSPATH') || exit;

if (!defined('DCA_TB_ALLOW_MEDIA_FILE_RENAME')) {
    define('DCA_TB_ALLOW_MEDIA_FILE_RENAME', true);
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

if (!defined('DCA_TB_OVERWRITE_EXISTING_MEDIA')) {
    define('DCA_TB_OVERWRITE_EXISTING_MEDIA', false);
}

if (!defined('DCA_TB_OVERWRITE_EXISTING_TEXT')) {
    define('DCA_TB_OVERWRITE_EXISTING_TEXT', true);
}

if (!defined('DCA_TB_ALLOW_EMPTY_TEXT_OVERWRITE')) {
    define('DCA_TB_ALLOW_EMPTY_TEXT_OVERWRITE', false);
}

if (!defined('DCA_TB_OVERWRITE_EXISTING_TITLE')) {
    define('DCA_TB_OVERWRITE_EXISTING_TITLE', false);
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

if (!defined('DCA_TB_IMPORT_PREVIEW_TTL')) {
    define('DCA_TB_IMPORT_PREVIEW_TTL', 20 * MINUTE_IN_SECONDS);
}

if (!defined('DCA_TB_LIGHT_ADMIN_LIST')) {
    define('DCA_TB_LIGHT_ADMIN_LIST', true);
}

function dca_tb_usp_fields() {
    return ['usp_1', 'usp_2', 'usp_3', 'usp_4'];
}

function dca_tb_supported_post_types() {
    $defaults = ['page', 'post'];

    if (post_type_exists('product')) {
        $defaults[] = 'product';
    }

    $post_types = apply_filters('dca_tb_supported_post_types', $defaults);

    if (!is_array($post_types)) {
        $post_types = $defaults;
    }

    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types))));

    return array_values(array_filter($post_types, static function ($post_type) use ($defaults) {
        return in_array($post_type, $defaults, true) || post_type_exists($post_type);
    }));
}

function dca_tb_is_supported_post_type($post_type) {
    return in_array(sanitize_key((string) $post_type), dca_tb_supported_post_types(), true);
}

function dca_tb_supported_taxonomies() {
    $defaults = ['category'];

    if (taxonomy_exists('product_cat')) {
        $defaults[] = 'product_cat';
    }

    $taxonomies = apply_filters('dca_tb_supported_taxonomies', $defaults);

    if (!is_array($taxonomies)) {
        $taxonomies = $defaults;
    }

    $taxonomies = array_values(array_unique(array_filter(array_map('sanitize_key', $taxonomies))));

    return array_values(array_filter($taxonomies, static function ($taxonomy) use ($defaults) {
        return in_array($taxonomy, $defaults, true) || taxonomy_exists($taxonomy);
    }));
}

function dca_tb_is_supported_taxonomy($taxonomy) {
    return in_array(sanitize_key((string) $taxonomy), dca_tb_supported_taxonomies(), true);
}

function dca_tb_get_admin_taxonomy() {
    if (!empty($_GET['taxonomy'])) {
        return sanitize_key(wp_unslash($_GET['taxonomy']));
    }

    return '';
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

    if ($post_type === 'page') {
        return 'PAGINA';
    }

    $object = $post_type ? get_post_type_object($post_type) : null;
    $label = $object && !empty($object->labels->singular_name) ? $object->labels->singular_name : (string) $post_type;

    return strtoupper(dca_tb_clean_text($label));
}

function dca_tb_post_type_label_plural($post_type) {
    if ($post_type === 'post') {
        return 'berichten';
    }

    if ($post_type === 'product') {
        return 'producten';
    }

    if ($post_type === 'page') {
        return 'pagina’s';
    }

    $object = $post_type ? get_post_type_object($post_type) : null;

    return $object && !empty($object->labels->name) ? strtolower(dca_tb_clean_text($object->labels->name)) : 'items';
}

function dca_tb_taxonomy_label_single($taxonomy) {
    $taxonomy = sanitize_key((string) $taxonomy);

    if ($taxonomy === 'category') {
        return 'CATEGORIE';
    }

    if ($taxonomy === 'product_cat') {
        return 'PRODUCTCATEGORIE';
    }

    $object = $taxonomy ? get_taxonomy($taxonomy) : null;
    $label = $object && !empty($object->labels->singular_name) ? $object->labels->singular_name : $taxonomy;

    return strtoupper(dca_tb_clean_text($label));
}

function dca_tb_taxonomy_label_plural($taxonomy) {
    $taxonomy = sanitize_key((string) $taxonomy);

    if ($taxonomy === 'category') {
        return 'categorieën';
    }

    if ($taxonomy === 'product_cat') {
        return 'productcategorieën';
    }

    $object = $taxonomy ? get_taxonomy($taxonomy) : null;

    return $object && !empty($object->labels->name) ? strtolower(dca_tb_clean_text($object->labels->name)) : 'termen';
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
    $markers[] = 'SEO META';
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

function dca_tb_apply_standard_template_filter_where($where, $query) {
    if (!is_admin() || !$query->is_main_query() || !$query->get('dca_tb_standard_template_filter')) {
        return $where;
    }

    global $wpdb;

    $post_type = sanitize_key((string) $query->get('dca_tb_standard_template_filter'));
    $blocked = ['elementor_canvas', 'elementor_header_footer'];
    $blocked_sql = "'" . implode("','", array_map('esc_sql', $blocked)) . "'";

    // Houd de templatefilter bewust licht. Geen LIKE op geserialiseerde Elementor settings,
    // omdat dat grote adminlijsten kan laten vastlopen. We sluiten alleen expliciete template-meta uit.
    if ($post_type === 'page') {
        $where .= " AND NOT EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} dca_tpl_page_custom
            WHERE dca_tpl_page_custom.post_id = {$wpdb->posts}.ID
            AND dca_tpl_page_custom.meta_key = '_wp_page_template'
            AND dca_tpl_page_custom.meta_value NOT IN ('', 'default')
        )";
    }

    $where .= " AND NOT EXISTS (
        SELECT 1 FROM {$wpdb->postmeta} dca_tpl_el_blocked
        WHERE dca_tpl_el_blocked.post_id = {$wpdb->posts}.ID
        AND dca_tpl_el_blocked.meta_key IN ('_elementor_page_template', '_elementor_template_type')
        AND dca_tpl_el_blocked.meta_value IN ({$blocked_sql})
    )";

    return $where;
}
add_filter('posts_where', 'dca_tb_apply_standard_template_filter_where', 10, 2);

add_action('pre_get_posts', function ($q) {
    if (!is_admin() || !$q->is_main_query()) return;

    global $pagenow;

    $post_type = dca_tb_get_admin_post_type();
    $status = dca_tb_get_list_status_filter();
    $template_filter = dca_tb_get_list_template_filter();

    if ($pagenow !== 'edit.php' || !dca_tb_is_supported_post_type($post_type) || ($status === '' && $template_filter === '')) {
        return;
    }

    $meta_query = $q->get('meta_query');
    $meta_query = is_array($meta_query) ? $meta_query : [];
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
        // Toon alleen standaardtemplates zonder zware WP_Meta_Query JOINs.
        $q->set('dca_tb_standard_template_filter', $post_type);
    }

    if (!empty($meta_query)) {
        $q->set('meta_query', $meta_query);
    }
});

add_action('restrict_manage_posts', function ($post_type) {
    if (!dca_tb_is_supported_post_type($post_type) || !dca_tb_current_user_can_use_manager()) {
        return;
    }

    $current = dca_tb_get_list_status_filter();
    $template_current = dca_tb_get_list_template_filter();

    echo '<select name="dca_tb_status" id="dca-tb-status-filter">';
    echo '<option value="">' . esc_html__('Contentblok: alles', 'content-sync-manager') . '</option>';
    echo '<option value="not_done" ' . selected($current, 'not_done', false) . '>' . esc_html__('Nog te doen vandaag', 'content-sync-manager') . '</option>';
    echo '<option value="done_today" ' . selected($current, 'done_today', false) . '>' . esc_html__('Vandaag bijgewerkt', 'content-sync-manager') . '</option>';
    echo '</select>';

    echo '<select name="dca_tb_template" id="dca-tb-template-filter">';
    echo '<option value="">' . esc_html__('Template: alles', 'content-sync-manager') . '</option>';
    echo '<option value="standard" ' . selected($template_current, 'standard', false) . '>' . esc_html__('Standaard template', 'content-sync-manager') . '</option>';
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

function dca_tb_register_textblock_columns() {
    static $registered = false;

    if ($registered || !is_admin()) {
        return;
    }

    $registered = true;

    foreach (dca_tb_supported_post_types() as $post_type) {
        if ($post_type === 'page') {
            add_filter('manage_pages_columns', 'dca_tb_add_textblock_column');
            add_action('manage_pages_custom_column', 'dca_tb_render_textblock_column', 10, 2);
            continue;
        }

        if ($post_type === 'post') {
            add_filter('manage_posts_columns', 'dca_tb_add_textblock_column');
            add_action('manage_posts_custom_column', 'dca_tb_render_textblock_column', 10, 2);
            continue;
        }

        add_filter('manage_' . $post_type . '_posts_columns', 'dca_tb_add_textblock_column');
        add_action('manage_' . $post_type . '_posts_custom_column', 'dca_tb_render_textblock_column', 10, 2);
    }
}
add_action('admin_init', 'dca_tb_register_textblock_columns');

function dca_tb_add_term_textblock_column($columns) {
    $new = [];

    foreach ($columns as $key => $label) {
        $new[$key] = $label;

        if ($key === 'name') {
            $new['dca_acf_textblock'] = __('Content Sync', 'content-sync-manager');
        }
    }

    if (!isset($new['dca_acf_textblock'])) {
        $new['dca_acf_textblock'] = __('Content Sync', 'content-sync-manager');
    }

    return $new;
}

function dca_tb_render_term_textblock_column($content, $column, $term_id) {
    if ($column !== 'dca_acf_textblock') {
        return $content;
    }

    $taxonomy = dca_tb_get_admin_taxonomy();

    if (!dca_tb_can_edit_term($term_id, $taxonomy)) {
        return '<span aria-hidden="true">—</span>';
    }

    return sprintf(
        '<button type="button" class="button button-small dca-open-acf-textblock" data-object-type="term" data-term-id="%d" data-taxonomy="%s">%s</button><br>%s',
        absint($term_id),
        esc_attr($taxonomy),
        esc_html__('Tekst bewerken', 'content-sync-manager'),
        dca_tb_term_update_badge($term_id, $taxonomy)
    );
}

function dca_tb_register_term_textblock_columns() {
    static $registered = false;

    if ($registered || !is_admin()) {
        return;
    }

    $registered = true;

    foreach (dca_tb_supported_taxonomies() as $taxonomy) {
        add_filter('manage_edit-' . $taxonomy . '_columns', 'dca_tb_add_term_textblock_column');
        add_filter('manage_' . $taxonomy . '_custom_column', 'dca_tb_render_term_textblock_column', 10, 3);
    }
}
add_action('admin_init', 'dca_tb_register_term_textblock_columns');

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

function dca_tb_term_update_badge($term_id, $taxonomy) {
    $term_id = absint($term_id);
    $taxonomy = sanitize_key((string) $taxonomy);

    if (!$term_id || !dca_tb_is_supported_taxonomy($taxonomy)) {
        return '<span class="dca-badge dca-badge-muted">Niet ondersteund</span>';
    }

    $time = absint(get_term_meta($term_id, '_dca_acf_textblock_updated_at', true));

    if (!$time) {
        return '<span class="dca-badge dca-badge-not-updated">Geen datum</span>';
    }

    $today_start = dca_tb_today_start_timestamp();
    $class = $time >= $today_start ? 'dca-badge-updated' : 'dca-badge-muted';
    $user_id = absint(get_term_meta($term_id, '_dca_acf_textblock_updated_by', true));
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

    if (DCA_TB_LIGHT_ADMIN_LIST) {
        // Voorkom zware ACF/media/Elementor-detectie per rij in de adminlijst.
        return '<span class="dca-badge dca-badge-muted">Snelle lijstweergave</span>';
    }

    $media_count = count(dca_tb_collect_media_ids($post_id));

    if ($post->post_type === 'post') {
        $has_title   = trim((string) $post->post_title) !== '';
        $has_content = trim((string) $post->post_content) !== '';
        $complete = ($has_title && $has_content);
        $class = $complete ? 'dca-badge-green' : 'dca-badge-yellow';

        return sprintf(
            '<span class="dca-badge %s">Titel %s / Content %s / %d media</span>',
            esc_attr($class),
            $has_title ? 'ok' : 'mist',
            $has_content ? 'ok' : 'mist',
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

    $complete = ($acf_total > 0 && $acf_filled === $acf_total);
    $class = $complete ? 'dca-badge-green' : 'dca-badge-yellow';

    return sprintf(
        '<span class="dca-badge %s">ACF %d/%d / %d media</span>',
        esc_attr($class),
        absint($acf_filled),
        absint($acf_total),
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
    $attachment = $thumb_id ? get_post($thumb_id) : null;
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
        'Bestandsnaam:',
        $thumb_id ? dca_tb_media_filename($thumb_id) : '',
        'Nieuwe bestandsnaam:',
        '',
        'Title:',
        $attachment ? dca_tb_text($attachment->post_title) : '',
        'Alt text:',
        $thumb_id ? dca_tb_text(get_post_meta($thumb_id, '_wp_attachment_image_alt', true)) : '',
        'Caption:',
        $attachment ? dca_tb_text($attachment->post_excerpt) : '',
        'Description:',
        $attachment ? dca_tb_text($attachment->post_content) : ''
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
        'url'           => dca_tb_label($block, 'URL:', ['Bestandsnaam:', 'Alt text:']),
        'filename'      => dca_tb_label($block, 'Bestandsnaam:', ['Nieuwe bestandsnaam:', 'Title:', 'Alt text:']),
        'new_filename'  => dca_tb_label($block, 'Nieuwe bestandsnaam:', ['Title:', 'Alt text:']),
        'title'         => dca_tb_label($block, 'Title:', ['Alt text:']),
        'alt'           => dca_tb_label($block, 'Alt text:', ['Caption:']),
        'caption'       => dca_tb_label($block, 'Caption:', ['Description:']),
        'description'   => dca_tb_label($block, 'Description:'),
    ];
}

function dca_tb_validate_featured_image_block($textblock) {
    $block = dca_tb_section($textblock, 'UITGELICHTE AFBEELDING', dca_tb_featured_image_end_markers());

    if ($block === null) {
        return true;
    }

    foreach (['Attachment ID:', 'URL:', 'Bestandsnaam:', 'Nieuwe bestandsnaam:', 'Title:', 'Alt text:', 'Caption:', 'Description:'] as $label) {
        if (dca_tb_label_marker_count($block, $label) !== 1) {
            return new WP_Error('dca_invalid_featured_image', 'Opslaan gestopt: "' . $label . '" ontbreekt of komt meerdere keren voor onder UITGELICHTE AFBEELDING.');
        }
    }

    return true;
}

function dca_tb_featured_image_has_media_item($attachment_id, $textblock) {
    $attachment_id = absint($attachment_id);
    if (!$attachment_id) {
        return false;
    }

    foreach (dca_tb_parse_media_blocks($textblock) as $item) {
        if (absint($item['attachment_id'] ?? 0) === $attachment_id) {
            return true;
        }
    }

    return false;
}

function dca_tb_apply_featured_image_from_textblock($post_id, $textblock) {
    $current_thumbnail_id = absint(get_post_thumbnail_id($post_id));
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
        if (!DCA_TB_OVERWRITE_EXISTING_MEDIA && $current_thumbnail_id) {
            return true;
        }
        delete_post_thumbnail($post_id);
        return true;
    }

    if (!DCA_TB_OVERWRITE_EXISTING_MEDIA && $current_thumbnail_id && $current_thumbnail_id !== $attachment_id) {
        return true;
    }

    if (!$attachment_id || get_post_type($attachment_id) !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
        return new WP_Error('dca_featured_image_invalid', 'Opslaan gestopt: uitgelichte afbeelding is geen geldige WordPress-afbeelding.');
    }

    if (!current_user_can('edit_post', $attachment_id)) {
        return new WP_Error('dca_featured_image_no_permission', 'Geen rechten om deze uitgelichte afbeelding te gebruiken.');
    }

    set_post_thumbnail($post_id, $attachment_id);

    // Metadata staat normaal ook in het MEDIA-blok. Als dat blok ontbreekt of
    // deze attachment daar niet in voorkomt, verwerkt het featured-imageblok de
    // metadata zelf zodat featured images zelfstandig te beheren blijven.
    if (!dca_tb_featured_image_has_media_item($attachment_id, $textblock)) {
        $replace_pairs = [];
        $media_item = [
            'attachment_id' => $attachment_id,
            'current_url'   => $url,
            'filename'      => $item['filename'] ?? '',
            'new_filename'  => $item['new_filename'] ?? '',
            'title'         => $item['title'] ?? null,
            'alt'           => $item['alt'] ?? null,
            'caption'       => $item['caption'] ?? null,
            'description'   => $item['description'] ?? null,
        ];
        $updated = dca_tb_update_attachment_from_media_item($attachment_id, $media_item, $replace_pairs, 'featured-image-import');
        if (is_wp_error($updated)) {
            return $updated;
        }
        if (!empty($replace_pairs) && !DCA_TB_IMPORT_DRY_RUN) {
            dca_tb_replace_media_urls_on_page($post_id, $replace_pairs);
        }
    }

    return true;
}

function dca_tb_seo_section_markers() {
    return ['SEO META', 'YOAST SEO'];
}

function dca_tb_seo_end_markers() {
    return ['SAMENVATTING', 'UITGELICHTE AFBEELDING', 'MEDIA'];
}

function dca_tb_post_content_end_markers() {
    return array_merge(dca_tb_seo_section_markers(), dca_tb_seo_end_markers());
}

function dca_tb_validate_seo_meta_block($textblock, $required = false) {
    $seo_meta_count = dca_tb_marker_count($textblock, 'SEO META');
    $yoast_count = dca_tb_marker_count($textblock, 'YOAST SEO');

    if ($seo_meta_count > 1) {
        return new WP_Error('dca_duplicate_seo_meta', 'Opslaan gestopt: de kop "SEO META" komt meerdere keren voor. verwijder dubbele SEO-secties uit het TXT-bestand.');
    }

    if ($yoast_count > 1) {
        return new WP_Error('dca_duplicate_yoast', 'Opslaan gestopt: de kop "YOAST SEO" komt meerdere keren voor. verwijder dubbele SEO-secties uit het TXT-bestand.');
    }

    if ($seo_meta_count && $yoast_count) {
        return new WP_Error('dca_duplicate_seo_sections', 'Opslaan gestopt: gebruik SEO META of YOAST SEO, niet beide tegelijk. SEO-meta kan niet veilig worden bepaald.');
    }

    if ($required && !$seo_meta_count && !$yoast_count) {
        return new WP_Error('dca_missing_seo_meta', 'Opslaan gestopt: de kop "SEO META" of "YOAST SEO" ontbreekt.');
    }

    return true;
}

function dca_tb_extract_yoast_meta_description($textblock) {
    $section = null;

    foreach (dca_tb_seo_section_markers() as $marker) {
        $section = dca_tb_section($textblock, $marker, dca_tb_seo_end_markers());

        if ($section !== null) {
            break;
        }
    }

    if ($section === null) {
        return null;
    }

    foreach (['Yoast metabeschrijving:', 'Metabeschrijving:', 'Meta description:'] as $label) {
        $value = dca_tb_label($section, $label, ['Yoast metabeschrijving:', 'Metabeschrijving:', 'Meta description:']);

        if ($value !== null) {
            return dca_tb_clean_text($value);
        }
    }

    return dca_tb_clean_text($section);
}

function dca_tb_get_yoast_taxonomy_meta_description($term_id, $taxonomy) {
    $term_id = absint($term_id);
    $taxonomy = sanitize_key((string) $taxonomy);

    if (!$term_id || !dca_tb_is_supported_taxonomy($taxonomy)) {
        return '';
    }

    $term_meta_value = get_term_meta($term_id, '_yoast_wpseo_metadesc', true);

    if (dca_tb_has_existing_content_value($term_meta_value)) {
        return dca_tb_text($term_meta_value);
    }

    $taxonomy_meta = get_option('wpseo_taxonomy_meta', []);

    if (!is_array($taxonomy_meta)) {
        return '';
    }

    $legacy_value = $taxonomy_meta[$taxonomy][$term_id]['wpseo_desc'] ?? '';

    return dca_tb_text($legacy_value);
}

function dca_tb_update_yoast_taxonomy_meta_description($term_id, $taxonomy, $description) {
    $term_id = absint($term_id);
    $taxonomy = sanitize_key((string) $taxonomy);
    $description = dca_tb_clean_text($description);

    if (!$term_id || !dca_tb_is_supported_taxonomy($taxonomy)) {
        return false;
    }

    update_term_meta($term_id, '_yoast_wpseo_metadesc', $description);

    $taxonomy_meta = get_option('wpseo_taxonomy_meta', []);

    if (!is_array($taxonomy_meta)) {
        $taxonomy_meta = [];
    }

    if (!isset($taxonomy_meta[$taxonomy]) || !is_array($taxonomy_meta[$taxonomy])) {
        $taxonomy_meta[$taxonomy] = [];
    }

    if (!isset($taxonomy_meta[$taxonomy][$term_id]) || !is_array($taxonomy_meta[$taxonomy][$term_id])) {
        $taxonomy_meta[$taxonomy][$term_id] = [];
    }

    $taxonomy_meta[$taxonomy][$term_id]['wpseo_desc'] = $description;

    return update_option('wpseo_taxonomy_meta', $taxonomy_meta, false);
}

function dca_tb_get_yoast_meta_description($object_id, $object_type = 'post', $taxonomy = '') {
    $object_id = absint($object_id);
    $object_type = sanitize_key((string) $object_type);
    $taxonomy = sanitize_key((string) $taxonomy);

    if (!$object_id) {
        return '';
    }

    if ($object_type === 'term' && dca_tb_is_supported_taxonomy($taxonomy)) {
        return dca_tb_get_yoast_taxonomy_meta_description($object_id, $taxonomy);
    }

    return dca_tb_text(get_post_meta($object_id, '_yoast_wpseo_metadesc', true));
}

function dca_tb_build_yoast_meta_block($object_id, $object_type = 'post', $taxonomy = '') {
    return trim(implode("\n", [
        'SEO META',
        '',
        'Yoast metabeschrijving:',
        dca_tb_get_yoast_meta_description($object_id, $object_type, $taxonomy),
    ]));
}

function dca_tb_save_yoast_meta_from_textblock($object_id, $textblock, $object_type = 'post', $taxonomy = '') {
    $description = dca_tb_extract_yoast_meta_description($textblock);

    if ($description === null || !dca_tb_has_importable_text_value($description)) {
        return true;
    }

    $object_id = absint($object_id);
    $object_type = sanitize_key((string) $object_type);
    $taxonomy = sanitize_key((string) $taxonomy);

    if (!$object_id) {
        return new WP_Error('dca_yoast_invalid_object', 'SEO-meta kon niet worden opgeslagen: ongeldig object.');
    }

    if ($object_type === 'term') {
        if (!dca_tb_can_edit_term($object_id, $taxonomy)) {
            return new WP_Error('dca_yoast_term_no_permission', 'Geen rechten om de Yoast metabeschrijving van deze term te wijzigen.');
        }

        dca_tb_update_yoast_taxonomy_meta_description($object_id, $taxonomy, $description);
        return true;
    }

    if (!dca_tb_can_edit_post($object_id)) {
        return new WP_Error('dca_yoast_post_no_permission', 'Geen rechten om de Yoast metabeschrijving van dit item te wijzigen.');
    }

    update_post_meta($object_id, '_yoast_wpseo_metadesc', $description);
    return true;
}

function dca_tb_term_content_end_markers() {
    return dca_tb_seo_section_markers();
}

function dca_tb_build_term_textblock($term_id, $taxonomy) {
    $term_id = absint($term_id);
    $taxonomy = sanitize_key((string) $taxonomy);
    $term = get_term($term_id, $taxonomy);

    if (!$term || is_wp_error($term) || !dca_tb_is_supported_taxonomy($taxonomy)) {
        return 'Geen geldige categorie of productcategorie gevonden.';
    }

    return trim(implode("\n", [
        'NAAM',
        '',
        dca_tb_text($term->name),
        '',
        'BESCHRIJVING',
        '',
        dca_tb_text($term->description),
        '',
        dca_tb_build_yoast_meta_block($term_id, 'term', $taxonomy),
    ]));
}

function dca_tb_validate_term_textblock($textblock) {
    foreach (['NAAM', 'BESCHRIJVING'] as $marker) {
        $count = dca_tb_marker_count($textblock, $marker);

        if ($count === 0) {
            return new WP_Error('dca_missing_term_marker', 'Opslaan gestopt: de kop "' . $marker . '" ontbreekt of staat niet op een eigen regel.');
        }

        if ($count > 1) {
            return new WP_Error('dca_duplicate_term_marker', 'Opslaan gestopt: de kop "' . $marker . '" komt meerdere keren voor.');
        }
    }

    return dca_tb_validate_seo_meta_block($textblock, false);
}

function dca_tb_mark_term_updated($term_id, $taxonomy) {
    $term_id = absint($term_id);
    $taxonomy = sanitize_key((string) $taxonomy);

    if (!$term_id || !dca_tb_is_supported_taxonomy($taxonomy)) {
        return;
    }

    update_term_meta($term_id, '_dca_acf_textblock_updated_at', current_time('timestamp'));
    update_term_meta($term_id, '_dca_acf_textblock_updated_by', get_current_user_id());
}

function dca_tb_add_term_backup($term_id, $taxonomy, $source = 'save') {
    $term_id = absint($term_id);
    $taxonomy = sanitize_key((string) $taxonomy);
    $term = get_term($term_id, $taxonomy);

    if (!$term || is_wp_error($term) || !dca_tb_is_supported_taxonomy($taxonomy)) {
        return;
    }

    $existing = get_term_meta($term_id, '_dca_tb_backups', true);

    if (!is_array($existing)) {
        $existing = [];
    }

    $existing[] = [
        'time'     => current_time('timestamp'),
        'user'     => get_current_user_id(),
        'source'   => sanitize_key($source),
        'taxonomy' => $taxonomy,
        'title'    => $term->name,
        'text'     => dca_tb_build_term_textblock($term_id, $taxonomy),
    ];

    if (count($existing) > 20) {
        $existing = array_slice($existing, -20);
    }

    update_term_meta($term_id, '_dca_tb_backups', $existing);
}

function dca_tb_save_term_to_fields($term_id, $taxonomy, $textblock, $source = 'save') {
    $term_id = absint($term_id);
    $taxonomy = sanitize_key((string) $taxonomy);
    $term = get_term($term_id, $taxonomy);

    if (!$term || is_wp_error($term) || !dca_tb_is_supported_taxonomy($taxonomy)) {
        return new WP_Error('dca_invalid_term', 'Geen geldige categorie of productcategorie gevonden.');
    }

    if (!dca_tb_can_edit_term($term_id, $taxonomy)) {
        return new WP_Error('dca_term_no_permission', 'Geen rechten om deze categorie te bewerken.');
    }

    $textblock = trim(str_replace(["\r\n", "\r"], "\n", (string) $textblock));
    $validation = dca_tb_validate_term_textblock($textblock);

    if (is_wp_error($validation)) {
        return $validation;
    }

    if (DCA_TB_IMPORT_DRY_RUN) {
        return true;
    }

    dca_tb_add_term_backup($term_id, $taxonomy, $source);

    $name = dca_tb_section($textblock, 'NAAM', ['BESCHRIJVING']);
    $description = dca_tb_section($textblock, 'BESCHRIJVING', dca_tb_term_content_end_markers());
    $args = [];

    if (dca_tb_has_importable_text_value($name)) {
        $args['name'] = dca_tb_clean_text($name);
    }

    if ($description !== null && dca_tb_has_importable_text_value($description)) {
        $args['description'] = dca_tb_clean_html($description);
    }

    if (!empty($args)) {
        $updated = wp_update_term($term_id, $taxonomy, $args);

        if (is_wp_error($updated)) {
            return $updated;
        }
    }

    $seo_save = dca_tb_save_yoast_meta_from_textblock($term_id, $textblock, 'term', $taxonomy);
    if (is_wp_error($seo_save)) {
        return $seo_save;
    }

    dca_tb_mark_term_updated($term_id, $taxonomy);
    clean_term_cache($term_id, $taxonomy);

    return true;
}

function dca_tb_media_end_markers() {
    $markers = [];

    for ($i = 1; $i <= DCA_TB_MAX_MEDIA_PER_PAGE; $i++) {
        $markers[] = 'AFBEELDING ' . $i;
    }

    return $markers;
}

function dca_tb_register_media_ref(&$refs, $attachment_id, $source) {
    $attachment_id = absint($attachment_id);
    $source = trim(dca_tb_clean_text((string) $source));

    if (!$attachment_id || get_post_type($attachment_id) !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
        return;
    }

    if (!isset($refs[$attachment_id])) {
        $refs[$attachment_id] = [
            'id'      => $attachment_id,
            'sources' => [],
        ];
    }

    if ($source !== '' && !in_array($source, $refs[$attachment_id]['sources'], true)) {
        $refs[$attachment_id]['sources'][] = $source;
    }
}

function dca_tb_collect_media_refs_from_value($value, &$refs, $source, $depth = 0) {
    if ($depth > 15) {
        return;
    }

    if (is_numeric($value)) {
        dca_tb_register_media_ref($refs, absint($value), $source);
        return;
    }

    if (is_string($value)) {
        $value = trim($value);
        if ($value !== '' && preg_match('#^https?://#i', $value)) {
            $id = function_exists('attachment_url_to_postid') ? absint(attachment_url_to_postid(esc_url_raw($value))) : 0;
            dca_tb_register_media_ref($refs, $id, $source);
        }
        return;
    }

    if (!is_array($value)) {
        return;
    }

    foreach (['ID', 'id', 'attachment_id', 'image_id'] as $key) {
        if (isset($value[$key])) {
            dca_tb_collect_media_refs_from_value($value[$key], $refs, $source, $depth + 1);
        }
    }

    foreach (['url', 'src'] as $key) {
        if (!empty($value[$key]) && is_string($value[$key])) {
            dca_tb_collect_media_refs_from_value($value[$key], $refs, $source, $depth + 1);
        }
    }

    foreach ($value as $key => $child) {
        $child_source = $source;
        if (is_string($key) && $key !== '') {
            $safe_key = sanitize_key($key);
            if ($safe_key !== '' && !in_array($safe_key, ['id', 'ID', 'url', 'src'], true)) {
                $child_source .= ' > ' . $safe_key;
            }
        }
        dca_tb_collect_media_refs_from_value($child, $refs, $child_source, $depth + 1);
    }
}

function dca_tb_collect_media_refs($post_id) {
    $post_id = absint($post_id);
    $refs = [];

    $thumb_id = get_post_thumbnail_id($post_id);
    if ($thumb_id) {
        dca_tb_register_media_ref($refs, $thumb_id, 'featured_image');
    }

    $post = get_post($post_id);
    if ($post && !empty($post->post_content)) {
        if (preg_match_all('/wp-image-([0-9]+)/i', $post->post_content, $m)) {
            foreach ($m[1] as $id) {
                dca_tb_collect_media_refs_from_value($id, $refs, 'post_content');
            }
        }
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $post->post_content, $m)) {
            foreach ($m[1] as $url) {
                dca_tb_collect_media_refs_from_value($url, $refs, 'post_content');
            }
        }
    }

    // Loop alleen door ACF-velden die ACF op dit object detecteert.
    if (function_exists('get_field_objects')) {
        foreach (dca_tb_get_detected_acf_fields($post_id) as $field) {
            $type = isset($field['type']) ? sanitize_key($field['type']) : '';
            if (!in_array($type, ['image', 'file', 'gallery', 'group', 'repeater', 'flexible_content'], true)) {
                continue;
            }
            $field_name = isset($field['name']) ? sanitize_key($field['name']) : '';
            $source = $field_name !== '' ? 'acf:' . $field_name : 'acf';
            dca_tb_collect_media_refs_from_value($field['value'] ?? '', $refs, $source);
        }
    }

    return $refs;
}

function dca_tb_collect_media_ids_from_value($value, &$ids) {
    $refs = [];
    dca_tb_collect_media_refs_from_value($value, $refs, 'legacy');
    foreach ($refs as $id => $ref) {
        $ids[$id] = $id;
    }
}

function dca_tb_collect_media_ids($post_id) {
    return array_values(array_map('absint', array_keys(dca_tb_collect_media_refs($post_id))));
}

function dca_tb_media_filename($attachment_id) {
    $file = get_attached_file($attachment_id, true);
    return $file ? wp_basename($file) : '';
}

function dca_tb_build_media_block($post_id) {
    $refs = dca_tb_collect_media_refs($post_id);
    $ids = array_values(array_map('absint', array_keys($refs)));
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

        $sources = isset($refs[$attachment_id]['sources']) && is_array($refs[$attachment_id]['sources'])
            ? implode(', ', array_map('dca_tb_clean_text', $refs[$attachment_id]['sources']))
            : '';

        array_push(
            $out,
            '',
            'AFBEELDING ' . $i,
            'Attachment ID:',
            (string) $attachment_id,
            'Huidige URL:',
            (string) wp_get_attachment_url($attachment_id),
            'Bron:',
            $sources,
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
    $seen = [];
    $pattern = '/^AFBEELDING\s+\d+\s*$\n?(.*?)(?=^AFBEELDING\s+\d+\s*$|\z)/ims';
    if (!preg_match_all($pattern, $media, $matches, PREG_SET_ORDER)) {
        return [];
    }

    foreach ($matches as $m) {
        $block = trim($m[1]);
        $attachment_id = absint(dca_tb_label($block, 'Attachment ID:', ['Huidige URL:']));
        if ($attachment_id && isset($seen[$attachment_id])) {
            continue;
        }
        if ($attachment_id) {
            $seen[$attachment_id] = true;
        }

        $items[] = [
            'attachment_id' => $attachment_id,
            'current_url'   => dca_tb_label($block, 'Huidige URL:', ['Bron:', 'Bestandsnaam:']),
            'source'        => dca_tb_label($block, 'Bron:', ['Bestandsnaam:']),
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

function dca_tb_media_url_from_relative_path($relative_path) {
    $relative_path = ltrim(wp_normalize_path((string) $relative_path), '/');

    if ($relative_path === '') {
        return '';
    }

    $uploads = wp_upload_dir();
    if (empty($uploads['baseurl'])) {
        return '';
    }

    return trailingslashit($uploads['baseurl']) . str_replace('%2F', '/', rawurlencode($relative_path));
}

function dca_tb_add_media_replace_pair($old_relative, $new_relative, &$replace_pairs) {
    $old_url = dca_tb_media_url_from_relative_path($old_relative);
    $new_url = dca_tb_media_url_from_relative_path($new_relative);

    if ($old_url !== '' && $new_url !== '' && $old_url !== $new_url) {
        $replace_pairs[$old_url] = $new_url;
    }
}

function dca_tb_add_attachment_size_replace_pairs($old_relative, $new_relative, $old_metadata, $new_metadata, &$replace_pairs) {
    if (!is_array($old_metadata) || empty($old_metadata['sizes']) || !is_array($old_metadata['sizes'])) {
        return;
    }

    if (!is_array($new_metadata) || empty($new_metadata['sizes']) || !is_array($new_metadata['sizes'])) {
        return;
    }

    $old_dir = trim(dirname(wp_normalize_path((string) $old_relative)), '.\/');
    $new_dir = trim(dirname(wp_normalize_path((string) $new_relative)), '.\/');

    foreach ($old_metadata['sizes'] as $size_key => $old_size) {
        if (empty($old_size['file']) || empty($new_metadata['sizes'][$size_key]['file'])) {
            continue;
        }

        $old_size_relative = ($old_dir !== '' ? trailingslashit($old_dir) : '') . wp_basename($old_size['file']);
        $new_size_relative = ($new_dir !== '' ? trailingslashit($new_dir) : '') . wp_basename($new_metadata['sizes'][$size_key]['file']);

        dca_tb_add_media_replace_pair($old_size_relative, $new_size_relative, $replace_pairs);
    }
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

    $old_relative = dca_tb_normalize_upload_relative_path(get_post_meta($attachment_id, '_wp_attached_file', true));
    $relative = ltrim(str_replace(wp_normalize_path($uploads['basedir']), '', wp_normalize_path($new_file)), '/');
    $old_url = wp_get_attachment_url($attachment_id);
    $new_url = dca_tb_media_url_from_relative_path($relative);
    $old_metadata = wp_get_attachment_metadata($attachment_id);

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

    $attachment = get_post($attachment_id);
    if ($attachment && $old_url && $new_url && $attachment->guid === $old_url) {
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
        dca_tb_add_attachment_size_replace_pairs($old_relative, $relative, $old_metadata, $metadata, $replace_pairs);
    }

    dca_tb_add_media_replace_pair($old_relative, $relative, $replace_pairs);

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

function dca_tb_update_attachment_from_media_item($attachment_id, $item, &$replace_pairs, $source = 'media-import', $skip_file_rename = false) {
    $attachment_id = absint($attachment_id);

    if (!$attachment_id || get_post_type($attachment_id) !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
        return new WP_Error('dca_media_invalid_attachment', 'Attachment ontbreekt of is geen afbeelding.');
    }

    $identity = dca_tb_media_identity_error($item, $attachment_id);
    if (is_wp_error($identity)) {
        return $identity;
    }

    if (!current_user_can('edit_post', $attachment_id)) {
        return new WP_Error('dca_media_no_permission', 'Geen rechten voor attachment #' . $attachment_id . '.');
    }

    if (!DCA_TB_IMPORT_DRY_RUN) {
        dca_tb_add_media_backup($attachment_id, $source);
    }

    $renamed = false;
    if (!$skip_file_rename) {
        $rename = dca_tb_rename_attachment_file($attachment_id, (string) ($item['new_filename'] ?? ''), $replace_pairs);
        if (is_wp_error($rename)) {
            return $rename;
        }
        $renamed = !empty($rename['renamed']);
    }

    $post_update = ['ID' => $attachment_id];
    if (($item['title'] ?? null) !== null) {
        $post_update['post_title'] = dca_tb_clean_text($item['title']);
    }
    if (($item['caption'] ?? null) !== null) {
        $post_update['post_excerpt'] = dca_tb_clean_text($item['caption']);
    }
    if (($item['description'] ?? null) !== null) {
        $post_update['post_content'] = dca_tb_clean_html($item['description']);
    }

    if (!DCA_TB_IMPORT_DRY_RUN) {
        if (count($post_update) > 1) {
            $updated = wp_update_post($post_update, true);
            if (is_wp_error($updated)) {
                return $updated;
            }
        }

        if (($item['alt'] ?? null) !== null) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', dca_tb_clean_text($item['alt']));
        }
    }

    return [
        'updated' => true,
        'renamed' => $renamed,
    ];
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

        // Media-items in dit blok zijn juist de lokale gekoppelde attachments. Metadata
        // mag altijd worden bijgewerkt; bestandsnaam wijzigen blijft apart beschermd door
        // DCA_TB_ALLOW_MEDIA_FILE_RENAME en de extensie/bestandscontroles.
        $updated = dca_tb_update_attachment_from_media_item($id, $item, $replace_pairs, 'media-import', false);
        if (is_wp_error($updated)) {
            $result['media_errors']++;
            $result['messages'][] = 'Media overgeslagen voor attachment #' . $id . ': ' . $updated->get_error_message();
            continue;
        }

        if (!empty($updated['renamed'])) {
            $result['renamed']++;
        }
        $result['media_updated']++;
    }

    if (!empty($replace_pairs) && !DCA_TB_IMPORT_DRY_RUN) {
        $result['url_replaces'] = dca_tb_replace_media_urls_on_page($post_id, $replace_pairs);
    }

    return $result;
}

function dca_tb_acf_export_end_markers() {
    return ['SEO META', 'YOAST SEO', 'SAMENVATTING', 'UITGELICHTE AFBEELDING', 'MEDIA'];
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

function dca_tb_is_exportable_acf_field($field) {
    if (!is_array($field)) {
        return false;
    }

    $name = isset($field['name']) ? sanitize_key($field['name']) : '';

    if ($name === '') {
        return false;
    }

    $type = isset($field['type']) ? sanitize_key($field['type']) : '';

    return !in_array($type, ['tab', 'accordion', 'message'], true);
}

function dca_tb_normalize_acf_field($field, $post_id) {
    if (!dca_tb_is_exportable_acf_field($field)) {
        return null;
    }

    $name = sanitize_key((string) $field['name']);
    $key = isset($field['key']) ? sanitize_key($field['key']) : '';
    $type = isset($field['type']) ? sanitize_key($field['type']) : '';
    $selector = $key !== '' ? $key : $name;
    $value_loaded = false;
    $value = null;

    if (function_exists('get_field')) {
        $value = get_field($selector, $post_id, false);
        $value_loaded = true;

        if ($value === null && $key !== '' && $name !== '') {
            $fallback_value = get_field($name, $post_id, false);
            if ($fallback_value !== null) {
                $value = $fallback_value;
            }
        }
    }

    if ((!$value_loaded || $value === null) && array_key_exists('value', $field)) {
        $value = $field['value'];
    }

    if ($value === null) {
        $value = '';
    }

    return [
        'name'  => $name,
        'key'   => $key,
        'label' => isset($field['label']) ? dca_tb_clean_text($field['label']) : $name,
        'type'  => $type,
        'value' => $value,
    ];
}

function dca_tb_add_detected_acf_field(&$fields, &$seen, $field, $post_id) {
    $normalized = dca_tb_normalize_acf_field($field, $post_id);

    if (!$normalized) {
        return;
    }

    $identity = $normalized['key'] !== '' ? 'key:' . $normalized['key'] : 'name:' . $normalized['name'];

    if (isset($seen[$identity])) {
        return;
    }

    $seen[$identity] = true;
    $fields[] = $normalized;
}

function dca_tb_get_acf_group_fields_for_post($post_id) {
    if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
        return [];
    }

    $groups = acf_get_field_groups(['post_id' => $post_id]);

    if (!is_array($groups) || empty($groups)) {
        $post_type = get_post_type($post_id);
        $groups = acf_get_field_groups([
            'post_id'   => $post_id,
            'post_type' => $post_type ? $post_type : '',
        ]);
    }

    if (!is_array($groups)) {
        return [];
    }

    $fields = [];

    foreach ($groups as $group) {
        $group_fields = acf_get_fields($group);

        if (!is_array($group_fields)) {
            continue;
        }

        foreach ($group_fields as $field) {
            if (dca_tb_is_exportable_acf_field($field)) {
                $fields[] = $field;
            }
        }
    }

    return $fields;
}

function dca_tb_get_detected_acf_fields($post_id) {
    $post_id = absint($post_id);

    if (!$post_id) {
        return [];
    }

    $fields = [];
    $seen = [];

    foreach (dca_tb_get_acf_group_fields_for_post($post_id) as $field) {
        dca_tb_add_detected_acf_field($fields, $seen, $field, $post_id);
    }

    if (function_exists('get_field_objects')) {
        $objects = get_field_objects($post_id, false, true);

        if (!is_array($objects)) {
            $objects = get_field_objects($post_id, true, true);
        }

        if (is_array($objects)) {
            foreach ($objects as $field) {
                dca_tb_add_detected_acf_field($fields, $seen, $field, $post_id);
            }
        }
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

function dca_tb_is_empty_acf_import_value($value, $type = '') {
    if (DCA_TB_ALLOW_EMPTY_TEXT_OVERWRITE) {
        return false;
    }

    $type = sanitize_key((string) $type);

    if ($type === 'true_false') {
        return false;
    }

    if (is_array($value)) {
        return empty($value);
    }

    if (is_object($value)) {
        return empty(get_object_vars($value));
    }

    if (is_bool($value) || is_int($value) || is_float($value)) {
        return false;
    }

    $raw = trim((string) $value);

    if ($raw === '' || $raw === '[]' || $raw === '{}') {
        return true;
    }

    return false;
}

function dca_tb_validate_dynamic_acf_textblock($textblock) {
    if (dca_tb_marker_count($textblock, 'ACF VELDEN') !== 1) {
        return new WP_Error('dca_invalid_acf_fields', 'Opslaan gestopt: de kop "ACF VELDEN" ontbreekt of komt meerdere keren voor.');
    }

    foreach (['SEO META', 'YOAST SEO', 'MEDIA', 'SAMENVATTING', 'UITGELICHTE AFBEELDING'] as $marker) {
        if (dca_tb_marker_count($textblock, $marker) > 1) {
            return new WP_Error('dca_duplicate_marker', 'Opslaan gestopt: de kop "' . $marker . '" komt meerdere keren voor.');
        }
    }

    $seo_validation = dca_tb_validate_seo_meta_block($textblock, false);
    if (is_wp_error($seo_validation)) {
        return $seo_validation;
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

        if (dca_tb_is_empty_acf_import_value($item['value'], $field['type'])) {
            continue;
        }

        $value = dca_tb_clean_acf_import_value($item['value'], $field['type']);

        if (dca_tb_is_empty_acf_import_value($value, $field['type'])) {
            continue;
        }

        $selector = !empty($field['key']) ? $field['key'] : $field['name'];

        if (!DCA_TB_OVERWRITE_EXISTING_MEDIA && in_array($field['type'], ['image', 'file', 'gallery'], true)) {
            $current_value = function_exists('get_field') ? get_field($selector, $post_id, false) : null;
            if (!empty($current_value)) {
                continue;
            }
        }

        if (!DCA_TB_OVERWRITE_EXISTING_TEXT && dca_tb_is_text_like_acf_type($field['type'])) {
            $current_value = function_exists('get_field') ? get_field($selector, $post_id, false) : get_post_meta($post_id, $field['name'], true);
            if (dca_tb_has_existing_content_value($current_value)) {
                continue;
            }
        }

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
     * Berichten: alleen WordPress titel, content, samenvatting, Yoast SEO en media.
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
            dca_tb_build_yoast_meta_block($post_id, 'post'),
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
     * Pagina's en producten: exporteer alleen de ACF-velden die ACF voor dit object detecteert.
     */
    if (!function_exists('get_field_objects')) {
        return 'ACF is niet actief of get_field_objects() is niet beschikbaar.';
    }

    $out = [
        dca_tb_build_acf_fields_block($post_id),
        '',
        dca_tb_build_yoast_meta_block($post_id, 'post'),
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

    $seo_validation = dca_tb_validate_seo_meta_block($textblock, false);
    if (is_wp_error($seo_validation)) {
        return $seo_validation;
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

    $faq = dca_tb_section($textblock, 'FAQ', ['SEO META', 'YOAST SEO', 'SAMENVATTING', 'UITGELICHTE AFBEELDING', 'MEDIA']);

    if ($faq === null) {
        return new WP_Error('dca_invalid_faq', 'Opslaan gestopt: FAQ kon niet exact gelezen worden.');
    }

    if (count(dca_tb_blocks($faq)) !== 8) {
        return new WP_Error('dca_invalid_faq_count', 'Opslaan gestopt: onder FAQ moeten exact 8 blokken staan: vraag, antwoord, vraag, antwoord, vraag, antwoord, vraag, antwoord.');
    }

    return true;
}

function dca_tb_validate_post_textblock($textblock) {
    foreach (['TITEL', 'CONTENT'] as $marker) {
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

    $seo_validation = dca_tb_validate_seo_meta_block($textblock, false);
    if (is_wp_error($seo_validation)) {
        return $seo_validation;
    }

    $featured_validation = dca_tb_validate_featured_image_block($textblock);
    if (is_wp_error($featured_validation)) {
        return $featured_validation;
    }

    return true;
}

function dca_tb_has_existing_content_value($value) {
    if (is_array($value)) {
        return !empty($value);
    }

    return trim(wp_strip_all_tags((string) $value)) !== '';
}

function dca_tb_has_importable_text_value($value) {
    return DCA_TB_ALLOW_EMPTY_TEXT_OVERWRITE || dca_tb_has_existing_content_value($value);
}

function dca_tb_is_text_like_acf_type($type) {
    return in_array((string) $type, ['text', 'textarea', 'wysiwyg', 'oembed', 'email', 'url', 'password', 'number', 'range'], true);
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

    // Fallback op metakey wanneer ACF een veldnaam niet meer naar een field key kan herleiden.
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
     * Berichten: opslaan naar post_title, post_content, samenvatting en media.
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
        $content = dca_tb_section($textblock, 'CONTENT', dca_tb_post_content_end_markers());
        $post_update = [
            'ID' => $post_id,
        ];

        if (dca_tb_has_importable_text_value($title) && (DCA_TB_OVERWRITE_EXISTING_TITLE || !dca_tb_has_existing_content_value($post->post_title))) {
            $post_update['post_title'] = dca_tb_clean_text($title);
        }

        if (dca_tb_has_importable_text_value($content) && (DCA_TB_OVERWRITE_EXISTING_TEXT || !dca_tb_has_existing_content_value($post->post_content))) {
            $post_update['post_content'] = dca_tb_clean_html($content);
        }

        $summary = dca_tb_section($textblock, 'SAMENVATTING', ['UITGELICHTE AFBEELDING', 'MEDIA']);
        if ($summary !== null && dca_tb_has_importable_text_value($summary) && (DCA_TB_OVERWRITE_EXISTING_TEXT || !dca_tb_has_existing_content_value($post->post_excerpt))) {
            $post_update['post_excerpt'] = dca_tb_clean_text($summary);
        }

        if (count($post_update) > 1) {
            $updated = wp_update_post($post_update, true);

            if (is_wp_error($updated)) {
                return $updated;
            }
        }

        $seo_save = dca_tb_save_yoast_meta_from_textblock($post_id, $textblock, 'post');
        if (is_wp_error($seo_save)) {
            return $seo_save;
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

    $summary = dca_tb_section($textblock, 'SAMENVATTING', ['UITGELICHTE AFBEELDING', 'MEDIA']);
    if ($summary !== null && dca_tb_has_importable_text_value($summary) && (DCA_TB_OVERWRITE_EXISTING_TEXT || !dca_tb_has_existing_content_value($post->post_excerpt))) {
        $updated_excerpt = wp_update_post([
            'ID'           => $post_id,
            'post_excerpt' => dca_tb_clean_text($summary),
        ], true);

        if (is_wp_error($updated_excerpt)) {
            return $updated_excerpt;
        }
    }

    $seo_save = dca_tb_save_yoast_meta_from_textblock($post_id, $textblock, 'post');
    if (is_wp_error($seo_save)) {
        return $seo_save;
    }

    $featured = dca_tb_apply_featured_image_from_textblock($post_id, $textblock);
    if (is_wp_error($featured)) {
        return $featured;
    }

    dca_tb_mark_updated($post_id);
    clean_post_cache($post_id);

    return true;

}

function dca_tb_build_bulk_export($object_ids, $object_type = 'post', $taxonomy = '') {
    $out = [];
    $object_type = sanitize_key((string) $object_type);
    $taxonomy = sanitize_key((string) $taxonomy);

    foreach ($object_ids as $object_id) {
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
                dca_tb_build_term_textblock($object_id, $taxonomy),
                '',
                ''
            );

            continue;
        }

        $post_id = $object_id;
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
            'Object type: post',
            'Post type: ' . $post->post_type,
            str_repeat('=', 80),
            '',
            dca_tb_build_textblock($post_id),
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
        return false;
    }

    $source_path = dca_tb_normalize_compare_url_path($url);
    $target_path = dca_tb_normalize_compare_url_path(get_permalink($post_id));

    if ($source_path === '' || $target_path === '') {
        return false;
    }

    return $source_path === $target_path;
}


function dca_tb_import_label_to_post_type($label) {
    $label = strtoupper(dca_tb_clean_text((string) $label));

    if ($label === 'BERICHT') {
        return 'post';
    }

    if ($label === 'PRODUCT') {
        return 'product';
    }

    if ($label === 'PAGINA') {
        return 'page';
    }

    foreach (dca_tb_supported_post_types() as $post_type) {
        $object = get_post_type_object($post_type);
        $singular = $object && !empty($object->labels->singular_name) ? $object->labels->singular_name : $post_type;

        if ($label === strtoupper(dca_tb_clean_text($singular))) {
            return $post_type;
        }
    }

    return '';
}

function dca_tb_import_label_to_taxonomy($label) {
    $label = strtoupper(dca_tb_clean_text((string) $label));

    if ($label === 'CATEGORIE') {
        return 'category';
    }

    if ($label === 'PRODUCTCATEGORIE' || $label === 'PRODUCT CATEGORIE') {
        return 'product_cat';
    }

    foreach (dca_tb_supported_taxonomies() as $taxonomy) {
        if ($label === dca_tb_taxonomy_label_single($taxonomy)) {
            return $taxonomy;
        }
    }

    return '';
}

function dca_tb_find_post_by_url_path($url, array $allowed_post_types) {
    $source_path = dca_tb_normalize_compare_url_path($url);

    if ($source_path === '') {
        return 0;
    }

    $per_page = 100;
    $max_candidates = absint(apply_filters('dca_tb_url_resolve_max_candidates', 1000));
    $checked = 0;
    $paged = 1;

    do {
        $q = new WP_Query([
            'post_type'              => $allowed_post_types,
            'post_status'            => 'any',
            'posts_per_page'         => $per_page,
            'paged'                  => $paged,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        $posts = (array) $q->posts;

        foreach ($posts as $candidate_id) {
            $checked++;

            if (dca_tb_normalize_compare_url_path(get_permalink($candidate_id)) === $source_path) {
                return absint($candidate_id);
            }

            if ($max_candidates > 0 && $checked >= $max_candidates) {
                return 0;
            }
        }

        $paged++;
    } while (count($posts) === $per_page);

    return 0;
}

function dca_tb_term_url_matches($url, $term_id, $taxonomy) {
    $url = trim((string) $url);

    if ($url === '') {
        return false;
    }

    $term_link = get_term_link(absint($term_id), sanitize_key((string) $taxonomy));

    if (is_wp_error($term_link)) {
        return false;
    }

    $source_path = dca_tb_normalize_compare_url_path($url);
    $target_path = dca_tb_normalize_compare_url_path($term_link);

    if ($source_path === '' || $target_path === '') {
        return false;
    }

    return $source_path === $target_path;
}

function dca_tb_title_matches_term($title, $term_id, $taxonomy) {
    $title = trim((string) $title);
    $term = get_term(absint($term_id), sanitize_key((string) $taxonomy));

    if ($title === '' || !$term || is_wp_error($term)) {
        return false;
    }

    return dca_tb_normalize_title_for_match($title) === dca_tb_normalize_title_for_match($term->name);
}

function dca_tb_find_term_by_url_path($url, $taxonomy) {
    $taxonomy = sanitize_key((string) $taxonomy);
    $source_path = dca_tb_normalize_compare_url_path($url);

    if ($source_path === '' || !dca_tb_is_supported_taxonomy($taxonomy)) {
        return 0;
    }

    $per_page = 100;
    $max_candidates = absint(apply_filters('dca_tb_term_url_resolve_max_candidates', 1000));
    $checked = 0;
    $offset = 0;

    do {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'number'     => $per_page,
            'offset'     => $offset,
            'fields'     => 'ids',
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            break;
        }

        foreach ($terms as $candidate_id) {
            $checked++;

            if (dca_tb_term_url_matches($url, $candidate_id, $taxonomy)) {
                return absint($candidate_id);
            }

            if ($max_candidates > 0 && $checked >= $max_candidates) {
                return 0;
            }
        }

        $offset += $per_page;
    } while (count($terms) === $per_page);

    return 0;
}

function dca_tb_resolve_term_details($term_id, $url, $title, $taxonomy) {
    $term_id = absint($term_id);
    $url = trim((string) $url);
    $title = trim((string) $title);
    $taxonomy = sanitize_key((string) $taxonomy);

    if (!dca_tb_is_supported_taxonomy($taxonomy)) {
        return ['id' => 0, 'method' => 'invalid-taxonomy'];
    }

    if ($term_id && ($term = get_term($term_id, $taxonomy)) && !is_wp_error($term)) {
        $url_matches = dca_tb_term_url_matches($url, $term_id, $taxonomy);
        $title_matches = dca_tb_title_matches_term($title, $term_id, $taxonomy);

        if (($url === '' && $title === '') || $url_matches || $title_matches) {
            return ['id' => $term_id, 'method' => 'id'];
        }
    }

    if ($url !== '') {
        $path_id = dca_tb_find_term_by_url_path($url, $taxonomy);

        if ($path_id) {
            return ['id' => $path_id, 'method' => 'url-path'];
        }
    }

    if ($title !== '') {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'name'       => $title,
            'number'     => 2,
            'fields'     => 'ids',
        ]);

        if (!is_wp_error($terms) && count((array) $terms) === 1 && !empty($terms[0])) {
            return ['id' => absint($terms[0]), 'method' => 'title'];
        }
    }

    return ['id' => 0, 'method' => 'none'];
}

function dca_tb_resolve_content_item_details(array $item) {
    $object_type = sanitize_key((string) ($item['object_type'] ?? 'post'));

    if ($object_type === 'term') {
        return dca_tb_resolve_term_details($item['source_id'] ?? 0, $item['source_url'] ?? '', $item['source_title'] ?? '', $item['source_taxonomy'] ?? '');
    }

    return dca_tb_resolve_page_details($item['source_id'] ?? 0, $item['source_url'] ?? '', $item['source_title'] ?? '', $item['source_type'] ?? '');
}

function dca_tb_resolve_content_item_title($object_id, $object_type, $taxonomy = '') {
    $object_id = absint($object_id);
    $object_type = sanitize_key((string) $object_type);

    if ($object_type === 'term') {
        $term = get_term($object_id, sanitize_key((string) $taxonomy));
        return ($term && !is_wp_error($term)) ? $term->name : '';
    }

    return $object_id ? get_the_title($object_id) : '';
}

function dca_tb_resolve_page_details($page_id, $url, $title, $expected_post_type = '') {
    $page_id = absint($page_id);
    $url = trim((string) $url);
    $title = trim((string) $title);
    $expected_post_type = sanitize_key($expected_post_type);

    if ($expected_post_type !== '' && !dca_tb_is_supported_post_type($expected_post_type)) {
        return ['id' => 0, 'method' => 'invalid-post-type'];
    }

    $allowed_post_types = $expected_post_type !== '' ? [$expected_post_type] : dca_tb_supported_post_types();

    // Gebruik de geëxporteerde ID alleen wanneer die nog bij hetzelfde item hoort.
    if ($page_id && ($post = get_post($page_id)) && in_array($post->post_type, $allowed_post_types, true)) {
        $url_matches = dca_tb_url_matches_post($url, $page_id);
        $title_matches = dca_tb_title_matches_post($title, $page_id);

        if (($url === '' && $title === '') || $url_matches || $title_matches) {
            return ['id' => $page_id, 'method' => 'id'];
        }
    }

    if ($url !== '') {
        $url_id = url_to_postid($url);

        if ($url_id && ($post = get_post($url_id)) && in_array($post->post_type, $allowed_post_types, true)) {
            return ['id' => absint($url_id), 'method' => 'url'];
        }

        $path_id = dca_tb_find_post_by_url_path($url, $allowed_post_types);

        if ($path_id) {
            return ['id' => $path_id, 'method' => 'url-path'];
        }
    }

    if ($title !== '') {
        $q = new WP_Query([
            'post_type'              => $allowed_post_types,
            'post_status'            => 'any',
            'title'                  => $title,
            'posts_per_page'         => 2,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        if (count((array) $q->posts) === 1 && !empty($q->posts[0])) {
            return ['id' => absint($q->posts[0]), 'method' => 'title'];
        }
    }

    return ['id' => 0, 'method' => 'none'];
}

function dca_tb_resolve_page($page_id, $url, $title, $expected_post_type = '') {
    $details = dca_tb_resolve_page_details($page_id, $url, $title, $expected_post_type);

    return absint($details['id'] ?? 0);
}

function dca_tb_resolve_method_label($method) {
    $labels = [
        'id'       => 'ID-match',
        'url'      => 'URL-match',
        'url-path' => 'URL-pad-match',
        'title'    => 'titel-match',
    ];

    return $labels[$method] ?? '';
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

    // Accepteer kleine variaties in bestaande TXT-exports zonder het exportformaat te wijzigen.
    $pattern = '/^={10,}[^\S\n]*\n\s*([^:\n]+)\s*:\s*(.*?)\n\s*URL\s*:\s*(.*?)\n\s*ID\s*:\s*(\d+)\s*\n((?:\s*(?:Post type|Object type|Taxonomy)\s*:\s*[a-z0-9_-]+\s*\n)*)={10,}[^\S\n]*(?:\n)+(.*?)(?=^={10,}[^\S\n]*\n\s*[^:\n]+\s*:|\z)/ims';

    if (preg_match_all($pattern, $txt, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $meta = [];

            if (!empty($m[5]) && preg_match_all('/^\s*([^:\n]+)\s*:\s*([a-z0-9_-]+)\s*$/mi', $m[5], $meta_matches, PREG_SET_ORDER)) {
                foreach ($meta_matches as $meta_match) {
                    $meta[strtolower(trim($meta_match[1]))] = sanitize_key($meta_match[2]);
                }
            }

            $label = trim($m[1]);
            $object_type = isset($meta['object type']) ? sanitize_key($meta['object type']) : '';
            $taxonomy = isset($meta['taxonomy']) ? sanitize_key($meta['taxonomy']) : dca_tb_import_label_to_taxonomy($label);
            $post_type = isset($meta['post type']) ? sanitize_key($meta['post type']) : dca_tb_import_label_to_post_type($label);

            if ($object_type === '' && $taxonomy !== '' && dca_tb_is_supported_taxonomy($taxonomy)) {
                $object_type = 'term';
            }

            if ($object_type !== 'term') {
                $object_type = 'post';
            }

            $items[] = [
                'object_type'     => $object_type,
                'source_type'     => $object_type === 'post' && dca_tb_is_supported_post_type($post_type) ? $post_type : '',
                'source_taxonomy' => $object_type === 'term' && dca_tb_is_supported_taxonomy($taxonomy) ? $taxonomy : '',
                'source_title'    => trim($m[2]),
                'source_url'      => trim($m[3]),
                'source_id'       => absint($m[4]),
                'textblock'       => trim($m[6]),
            ];
        }
    }

    return empty($items)
        ? new WP_Error('dca_invalid_import_format', 'Geen geldige blokken gevonden. Gebruik tekst met itemkop, URL en ID uit een Content Sync-export.')
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
        $object_type = sanitize_key((string) ($item['object_type'] ?? 'post'));
        $taxonomy = sanitize_key((string) ($item['source_taxonomy'] ?? ''));
        $target_details = dca_tb_resolve_content_item_details($item);
        $target = absint($target_details['id'] ?? 0);
        $target_method = dca_tb_resolve_method_label($target_details['method'] ?? '');
        $status = 'error';
        $message = '';
        $media = $object_type === 'post' ? dca_tb_preview_media_changes($item['textblock']) : ['found' => 0, 'renames' => 0, 'errors' => 0];

        if (!$target) {
            $message = 'Overgeslagen: geen bijpassend ondersteund item gevonden.';
        } elseif ($object_type === 'term' && !dca_tb_can_edit_term($target, $taxonomy)) {
            $message = 'Overgeslagen: geen rechten om deze categorie te bewerken.';
        } elseif ($object_type !== 'term' && !current_user_can('edit_post', $target)) {
            $message = 'Overgeslagen: geen rechten om dit item te bewerken.';
        } else {
            if ($object_type === 'term') {
                $validation = dca_tb_validate_term_textblock($item['textblock']);
            } else {
                $target_post = get_post($target);
                $template_reason = $target_post ? dca_tb_template_skip_reason($target) : '';

                if ($template_reason !== '') {
                    $message = $template_reason;
                    $preview[] = [
                        'index'              => $i,
                        'source_title'       => $item['source_title'],
                        'source_id'          => $item['source_id'],
                        'target_id'          => $target,
                        'target_post_id'     => $target,
                        'target_object_type' => $object_type,
            'target_taxonomy'    => $taxonomy,
                        'target_title'       => dca_tb_resolve_content_item_title($target, $object_type, $taxonomy),
                        'status'             => $status,
                        'message'            => $message,
                        'media_found'        => absint($media['found']),
                        'media_renames'      => absint($media['renames']),
                        'media_errors'       => absint($media['errors']),
                    ];
                    continue;
                }

                $validation = ($target_post && $target_post->post_type === 'post')
                    ? dca_tb_validate_post_textblock($item['textblock'])
                    : dca_tb_validate_dynamic_acf_textblock($item['textblock']);
            }

            if (is_wp_error($validation)) {
                $message = 'Overgeslagen: ' . $validation->get_error_message();
            } else {
                $status = $media['errors'] > 0 ? 'partial' : 'success';
                $message = 'Klaar om op te slaan' . ($target_method ? ' via ' . $target_method : '') . '. ';
                $message .= $object_type === 'term' ? 'Naam, beschrijving en Yoast metabeschrijving worden verwerkt.' : 'Tekst, samenvatting en Yoast metabeschrijving worden verwerkt. Media: ' . absint($media['found']) . ' gevonden, ' . absint($media['renames']) . ' bestandsnamen te hernoemen';
                if ($media['errors'] > 0) {
                    $message .= ', ' . absint($media['errors']) . ' mediafout(en). Tekst wordt wel geïmporteerd.';
                }
                $message .= '.';
            }
        }

        $preview[] = [
            'index'              => $i,
            'source_title'       => $item['source_title'],
            'source_id'          => $item['source_id'],
            'target_id'          => $target,
            'target_post_id'     => $target,
            'target_object_type' => $object_type,
            'target_taxonomy'    => $taxonomy,
            'target_title'       => dca_tb_resolve_content_item_title($target, $object_type, $taxonomy),
            'status'             => $status,
            'message'            => $message,
            'media_found'        => absint($media['found']),
            'media_renames'      => absint($media['renames']),
            'media_errors'       => absint($media['errors']),
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
        $object_type = sanitize_key((string) ($item['object_type'] ?? 'post'));
        $taxonomy = sanitize_key((string) ($item['source_taxonomy'] ?? ''));
        $target_details = dca_tb_resolve_content_item_details($item);
        $target = absint($target_details['id'] ?? 0);
        $target_method = dca_tb_resolve_method_label($target_details['method'] ?? '');
        $title  = $item['source_title'] ?: 'Blok ' . ($i + 1);

        if (!$target) {
            $skipped++;
            $results[] = [
                'index'              => $i,
                'source_title'       => $title,
                'source_id'          => $item['source_id'],
                'target_id'          => 0,
                'target_post_id'     => 0,
                'target_object_type' => $object_type,
            'target_taxonomy'    => $taxonomy,
                'target_title'       => '',
                'status'             => 'skipped',
                'message'            => 'Overgeslagen: geen bijpassend ondersteund item gevonden.',
            ];
            continue;
        }

        if ($object_type === 'term' && !dca_tb_can_edit_term($target, $taxonomy)) {
            $skipped++;
            $results[] = [
                'index'              => $i,
                'source_title'       => $title,
                'source_id'          => $item['source_id'],
                'target_id'          => $target,
                'target_post_id'     => $target,
                'target_object_type' => $object_type,
            'target_taxonomy'    => $taxonomy,
                'target_title'       => dca_tb_resolve_content_item_title($target, $object_type, $taxonomy),
                'status'             => 'skipped',
                'message'            => 'Overgeslagen: geen rechten om deze categorie te bewerken.',
            ];
            continue;
        }

        if ($object_type !== 'term' && !current_user_can('edit_post', $target)) {
            $skipped++;
            $results[] = [
                'index'              => $i,
                'source_title'       => $title,
                'source_id'          => $item['source_id'],
                'target_id'          => $target,
                'target_post_id'     => $target,
                'target_object_type' => $object_type,
            'target_taxonomy'    => $taxonomy,
                'target_title'       => get_the_title($target),
                'status'             => 'skipped',
                'message'            => 'Overgeslagen: geen rechten om dit item te bewerken.',
            ];
            continue;
        }

        if ($object_type === 'term') {
            $validation = dca_tb_validate_term_textblock($item['textblock']);
        } else {
            $target_post = get_post($target);
            $template_reason = $target_post ? dca_tb_template_skip_reason($target) : '';

            if ($template_reason !== '') {
                $skipped++;
                $results[] = [
                    'index'              => $i,
                    'source_title'       => $title,
                    'source_id'          => $item['source_id'],
                    'target_id'          => $target,
                    'target_post_id'     => $target,
                    'target_object_type' => $object_type,
            'target_taxonomy'    => $taxonomy,
                    'target_title'       => get_the_title($target),
                    'status'             => 'skipped',
                    'message'            => $template_reason,
                ];
                continue;
            }

            $validation = ($target_post && $target_post->post_type === 'post')
                ? dca_tb_validate_post_textblock($item['textblock'])
                : dca_tb_validate_dynamic_acf_textblock($item['textblock']);
        }

        if (is_wp_error($validation)) {
            $skipped++;
            $results[] = [
                'index'              => $i,
                'source_title'       => $title,
                'source_id'          => $item['source_id'],
                'target_id'          => $target,
                'target_post_id'     => $target,
                'target_object_type' => $object_type,
            'target_taxonomy'    => $taxonomy,
                'target_title'       => dca_tb_resolve_content_item_title($target, $object_type, $taxonomy),
                'status'             => 'skipped',
                'message'            => 'Overgeslagen: ' . $validation->get_error_message(),
            ];
            continue;
        }

        if ($object_type === 'term') {
            $save = dca_tb_save_term_to_fields($target, $taxonomy, $item['textblock'], 'bulk');
            $media_result = ['media_updated' => 0, 'renamed' => 0, 'media_errors' => 0, 'url_replaces' => 0, 'messages' => []];
        } else {
            $save = dca_tb_save_to_fields($target, $item['textblock'], 'bulk');
            $media_result = is_wp_error($save) ? ['media_updated' => 0, 'renamed' => 0, 'media_errors' => 0, 'url_replaces' => 0, 'messages' => []] : dca_tb_save_media_items($target, $item['textblock']);
        }

        if (is_wp_error($save)) {
            $skipped++;
            $results[] = [
                'index'              => $i,
                'source_title'       => $title,
                'source_id'          => $item['source_id'],
                'target_id'          => $target,
                'target_post_id'     => $target,
                'target_object_type' => $object_type,
            'target_taxonomy'    => $taxonomy,
                'target_title'       => dca_tb_resolve_content_item_title($target, $object_type, $taxonomy),
                'status'             => 'skipped',
                'message'            => 'Overgeslagen: ' . $save->get_error_message(),
            ];
            continue;
        }

        $media_updated += absint($media_result['media_updated']);
        $renamed       += absint($media_result['renamed']);
        $media_errors  += absint($media_result['media_errors']);
        $url_replaces  += absint($media_result['url_replaces']);

        $imported++;
        $status = $media_result['media_errors'] > 0 ? 'partial' : 'success';
        $message = (DCA_TB_IMPORT_DRY_RUN ? 'Dry-run: zou importeren. ' : 'Geïmporteerd. ') . ($target_method ? 'Match: ' . $target_method . '. ' : '');
        $message .= $object_type === 'term' ? 'Categorievelden en Yoast metabeschrijving bijgewerkt.' : 'Media bijgewerkt: ' . absint($media_result['media_updated']) . ', bestandsnamen hernoemd: ' . absint($media_result['renamed']) . ', URL-vervangingen: ' . absint($media_result['url_replaces']) . '.';
        if ($media_result['media_errors'] > 0) {
            $message .= ' Mediafouten: ' . absint($media_result['media_errors']) . '. ' . implode(' ', array_slice($media_result['messages'], 0, 3));
        }

        $results[] = [
            'index'              => $i,
            'source_title'       => $title,
            'source_id'          => $item['source_id'],
            'target_id'          => $target,
            'target_post_id'     => $target,
            'target_object_type' => $object_type,
            'target_taxonomy'    => $taxonomy,
            'target_title'       => dca_tb_resolve_content_item_title($target, $object_type, $taxonomy),
            'status'             => $status,
            'message'            => $message,
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
        $lines[] = '[' . strtoupper((string) ($row['status'] ?? 'info')) . '] ' . (string) ($row['source_title'] ?? '-') . ' -> ' . (string) ($row['target_title'] ?? '-') . ' (#' . absint($row['target_id'] ?? ($row['target_post_id'] ?? 0)) . ')';
        $lines[] = (string) ($row['message'] ?? '');
        $lines[] = '';
    }

    return trim(implode("\n", $lines));
}

function dca_tb_restore_last_import_page_backups() {
    $log = dca_tb_get_last_import_log();
    $post_ids = [];
    $term_ids = [];

    foreach (($log['results'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }

        if (!in_array($row['status'] ?? '', ['success', 'partial'], true)) {
            continue;
        }

        $object_type = sanitize_key((string) ($row['target_object_type'] ?? 'post'));
        $id = absint($row['target_id'] ?? ($row['target_post_id'] ?? 0));

        if (!$id) {
            continue;
        }

        if ($object_type === 'term') {
            $taxonomy = sanitize_key((string) ($row['target_taxonomy'] ?? ''));
            if ($taxonomy !== '') {
                $term_ids[$taxonomy . ':' . $id] = ['id' => $id, 'taxonomy' => $taxonomy];
            }
            continue;
        }

        $post_ids[$id] = $id;
    }

    if (empty($post_ids) && empty($term_ids)) {
        return new WP_Error('dca_restore_no_import_items', 'Geen herstelbare items gevonden in het laatste importlog.');
    }

    $restored = 0;
    $skipped = 0;
    $messages = [];

    foreach ($post_ids as $id) {
        $restore = dca_tb_restore_last_page_backup($id);

        if (is_wp_error($restore)) {
            $skipped++;
            $messages[] = '#' . absint($id) . ': ' . $restore->get_error_message();
            continue;
        }

        $restored++;
    }

    foreach ($term_ids as $term_ref) {
        $restore = dca_tb_restore_last_term_backup($term_ref['id'], $term_ref['taxonomy']);

        if (is_wp_error($restore)) {
            $skipped++;
            $messages[] = '#' . absint($term_ref['id']) . ': ' . $restore->get_error_message();
            continue;
        }

        $restored++;
    }

    return [
        'restored' => $restored,
        'skipped'  => $skipped,
        'messages' => $messages,
    ];
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

function dca_tb_restore_last_term_backup($term_id, $taxonomy) {
    $term_id = absint($term_id);
    $taxonomy = sanitize_key((string) $taxonomy);

    if (!dca_tb_can_edit_term($term_id, $taxonomy)) {
        return new WP_Error('dca_restore_term_no_permission', 'Geen rechten om deze categorie te herstellen.');
    }

    if (DCA_TB_IMPORT_DRY_RUN) {
        return new WP_Error('dca_restore_term_dry_run', 'Rollback is niet beschikbaar zolang dry-run actief is.');
    }

    $backups = get_term_meta($term_id, '_dca_tb_backups', true);
    if (!is_array($backups) || empty($backups)) {
        return new WP_Error('dca_restore_term_no_backup', 'Geen categorieback-up gevonden.');
    }

    $last = end($backups);
    $text = isset($last['text']) ? (string) $last['text'] : '';
    if ($text === '') {
        return new WP_Error('dca_restore_term_empty_backup', 'Laatste categorieback-up bevat geen tekst.');
    }

    return dca_tb_save_term_to_fields($term_id, $taxonomy, $text, 'rollback');
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

function dca_tb_can_edit_term($term_id, $taxonomy) {
    $term_id = absint($term_id);
    $taxonomy = sanitize_key((string) $taxonomy);
    $term = $term_id ? get_term($term_id, $taxonomy) : null;

    if (!$term || is_wp_error($term) || !dca_tb_is_supported_taxonomy($taxonomy)) {
        return false;
    }

    $tax_object = get_taxonomy($taxonomy);
    $capability = ($tax_object && !empty($tax_object->cap->edit_terms)) ? $tax_object->cap->edit_terms : 'manage_categories';

    return current_user_can($capability);
}

function dca_tb_get_request_object_type() {
    $object_type = dca_tb_post_text('object_type');
    $object_type = sanitize_key($object_type);

    return $object_type === 'term' ? 'term' : 'post';
}

function dca_tb_post_int($key) {
    if (!isset($_POST[$key]) || is_array($_POST[$key])) {
        return 0;
    }

    return absint(wp_unslash($_POST[$key]));
}

function dca_tb_post_text($key) {
    if (!isset($_POST[$key]) || is_array($_POST[$key])) {
        return '';
    }

    return (string) wp_unslash($_POST[$key]);
}

function dca_tb_post_id_list($key) {
    if (!isset($_POST[$key]) || !is_array($_POST[$key])) {
        return [];
    }

    return dca_tb_sanitize_post_id_list(wp_unslash($_POST[$key]));
}

function dca_tb_current_user_can_use_manager() {
    /**
     * Filtert de capability die nodig is om Content Sync Manager te gebruiken.
     *
     * Standaard is manage_options nodig omdat de plugin bulkgewijs content
     * en media-metadata kan overschrijven.
     *
     * @param string $capability Vereiste capability.
     */
    $capability = apply_filters('dca_tb_manager_capability', 'manage_options');

    return is_string($capability) && $capability !== '' && current_user_can($capability);
}

function dca_tb_require_manager_access() {
    if (!dca_tb_current_user_can_use_manager()) {
        wp_send_json_error(['message' => 'Geen rechten om de Content Sync Manager te gebruiken.'], 403);
    }
}


function dca_tb_request_has_destructive_confirmation() {
    return hash_equals('1', dca_tb_post_text('destructive_confirm'));
}

function dca_tb_import_preview_hash($txt) {
    return hash_hmac('sha256', (string) $txt, wp_salt('nonce'));
}

function dca_tb_import_preview_transient_key($hash) {
    return 'dca_tb_import_preview_' . get_current_user_id() . '_' . preg_replace('/[^a-f0-9]/', '', (string) $hash);
}

function dca_tb_mark_import_previewed($txt, $preview) {
    $hash = dca_tb_import_preview_hash($txt);
    $preview = is_array($preview) ? $preview : [];
    $importable = 0;

    foreach ($preview as $item) {
        if (is_array($item) && in_array($item['status'] ?? '', ['success', 'partial'], true)) {
            $importable++;
        }
    }

    set_transient(dca_tb_import_preview_transient_key($hash), [
        'time'       => time(),
        'items'      => count($preview),
        'importable' => $importable,
    ], DCA_TB_IMPORT_PREVIEW_TTL);

    return $hash;
}

function dca_tb_require_matching_import_preview($txt) {
    $submitted_hash = sanitize_text_field(dca_tb_post_text('preview_hash'));
    $expected_hash = dca_tb_import_preview_hash($txt);

    if ($submitted_hash === '' || !hash_equals($expected_hash, $submitted_hash)) {
        wp_send_json_error(['message' => 'Import is geblokkeerd: controleer exact deze TXT-inhoud eerst opnieuw.'], 403);
    }

    $preview = get_transient(dca_tb_import_preview_transient_key($expected_hash));

    if (!is_array($preview) || empty($preview['importable'])) {
        wp_send_json_error(['message' => 'Import is geblokkeerd: er is geen geldige recente controle gevonden. Controleer het bestand opnieuw.'], 403);
    }
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
            'text'     => dca_tb_build_term_textblock($term_id, $taxonomy),
            'view_url' => !is_wp_error($term_link) ? $term_link : '',
        ]);
    }

    $post_id = dca_tb_post_int('post_id');

    if (!dca_tb_can_edit_post($post_id)) {
        wp_send_json_error(['message' => 'Geen toegang tot deze pagina.']);
    }

    wp_send_json_success([
        'title'    => get_the_title($post_id),
        'text'     => dca_tb_build_textblock($post_id),
        'view_url' => get_permalink($post_id),
    ]);
});

add_action('wp_ajax_dca_save_acf_textblock', function () {
    dca_tb_require_ajax_access();
    dca_tb_require_destructive_confirmation();

    $object_type = dca_tb_get_request_object_type();
    $text = dca_tb_post_text('textblock');

    if ($object_type === 'term') {
        $term_id = dca_tb_post_int('term_id');
        $taxonomy = sanitize_key(dca_tb_post_text('taxonomy'));

        $save = dca_tb_save_term_to_fields($term_id, $taxonomy, $text, 'single');

        if (is_wp_error($save)) {
            wp_send_json_error(['message' => $save->get_error_message()]);
        }

        wp_send_json_success(['message' => 'Categorie opgeslagen. Back-up is automatisch gemaakt.']);
    }

    $post_id = dca_tb_post_int('post_id');

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

    $text = dca_tb_build_bulk_export($object_ids, $object_type, $taxonomy);

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

    $txt = dca_tb_post_text('txt_content');
    $preview = dca_tb_bulk_preview($txt);

    if (is_wp_error($preview)) {
        wp_send_json_error(['message' => $preview->get_error_message()]);
    }

    wp_send_json_success([
        'items'        => $preview,
        'preview_hash' => dca_tb_mark_import_previewed($txt, $preview),
    ]);
});

add_action('wp_ajax_dca_txt_import_run', function () {
    dca_tb_require_ajax_access();
    dca_tb_require_destructive_confirmation();

    $txt = dca_tb_post_text('txt_content');
    dca_tb_require_matching_import_preview($txt);

    $result = dca_tb_bulk_save($txt);

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

    $post_id = dca_tb_post_int('post_id');
    $restore = dca_tb_restore_last_page_backup($post_id);

    if (is_wp_error($restore)) {
        wp_send_json_error(['message' => $restore->get_error_message()]);
    }

    wp_send_json_success(['message' => 'Laatste pagina-backup is hersteld.']);
});

add_action('wp_ajax_dca_restore_last_import_pages', function () {
    dca_tb_require_ajax_access();
    dca_tb_require_destructive_confirmation();

    $restore = dca_tb_restore_last_import_page_backups();

    if (is_wp_error($restore)) {
        wp_send_json_error(['message' => $restore->get_error_message()]);
    }

    $message = 'Laatste import hersteld: ' . absint($restore['restored'] ?? 0) . ' item(s) teruggezet, ' . absint($restore['skipped'] ?? 0) . ' overgeslagen.';

    if (!empty($restore['messages'])) {
        $message .= ' Meldingen: ' . implode(' ', array_slice(array_map('strval', $restore['messages']), 0, 3));
    }

    wp_send_json_success([
        'message'  => $message,
        'restored' => absint($restore['restored'] ?? 0),
        'skipped'  => absint($restore['skipped'] ?? 0),
    ]);
});

add_action('wp_ajax_dca_restore_last_media_backup', function () {
    dca_tb_require_ajax_access();
    dca_tb_require_destructive_confirmation();

    $attachment_id = dca_tb_post_int('attachment_id');
    $restore = dca_tb_restore_last_media_backup($attachment_id);

    if (is_wp_error($restore)) {
        wp_send_json_error(['message' => $restore->get_error_message()]);
    }

    wp_send_json_success(['message' => 'Laatste media-backup is hersteld.']);
});


function dca_tb_should_load_admin_ui($hook_suffix = '') {
    if ($hook_suffix !== '' && !in_array($hook_suffix, ['edit.php', 'edit-tags.php'], true)) {
        return false;
    }

    $screen = get_current_screen();
    $post_type = ($screen && isset($screen->post_type)) ? sanitize_key((string) $screen->post_type) : '';
    $taxonomy = ($screen && isset($screen->taxonomy)) ? sanitize_key((string) $screen->taxonomy) : '';

    if (!$screen) {
        return false;
    }

    if ($screen->base === 'edit') {
        return $post_type !== '' && dca_tb_is_supported_post_type($post_type);
    }

    if ($screen->base === 'edit-tags') {
        return $taxonomy !== '' && dca_tb_is_supported_taxonomy($taxonomy);
    }

    return false;
}

function dca_tb_admin_body_class($classes) {
    if (!dca_tb_should_load_admin_ui() || !dca_tb_current_user_can_use_manager()) {
        return $classes;
    }

    $class = 'dca-tb-list-screen';

    if (is_string($classes)) {
        return trim($classes . ' ' . $class);
    }

    if (is_array($classes)) {
        $classes[] = $class;
    }

    return $classes;
}
add_filter('admin_body_class', 'dca_tb_admin_body_class');

function dca_tb_get_admin_asset_settings() {
    $screen = get_current_screen();
    $post_type = ($screen && isset($screen->post_type)) ? sanitize_key((string) $screen->post_type) : 'page';
    $taxonomy = ($screen && isset($screen->taxonomy)) ? sanitize_key((string) $screen->taxonomy) : '';
    $object_type = ($screen && $screen->base === 'edit-tags') ? 'term' : 'post';
    $filter_url = '';
    $filter_label = '';

    if ($object_type === 'post') {
        $status_filter = dca_tb_get_list_status_filter();
        $template_filter = dca_tb_get_list_template_filter();
        $base_url = $post_type === 'post'
            ? admin_url('edit.php')
            : admin_url('edit.php?post_type=' . $post_type);

        if ($template_filter !== '' && dca_tb_is_supported_post_type($post_type)) {
            $base_url = add_query_arg('dca_tb_template', $template_filter, $base_url);
        }

        $not_done_url = add_query_arg('dca_tb_status', 'not_done', $base_url);
        $filter_url = $status_filter === 'not_done' ? $base_url : $not_done_url;
        $filter_label = $status_filter === 'not_done' ? 'Toon alles' : 'Verberg vandaag bijgewerkt';
    }

    return [
        'nonce'          => wp_create_nonce('dca_acf_textblock_nonce'),
        'filterUrl'      => esc_url_raw($filter_url),
        'filterLabel'    => $filter_label,
        'ajaxUrl'        => admin_url('admin-ajax.php'),
        'maxImportBytes' => DCA_TB_MAX_IMPORT_BYTES,
        'objectType'     => $object_type,
        'taxonomy'       => $taxonomy,
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

function dca_tb_acf_available() {
    return function_exists('get_field_objects') && function_exists('update_field');
}

add_action('admin_notices', function () {
    if (!dca_tb_current_user_can_use_manager() || !function_exists('get_current_screen')) {
        return;
    }

    $screen = get_current_screen();

    $post_type = ($screen && isset($screen->post_type)) ? sanitize_key((string) $screen->post_type) : '';

    if (!$screen || $screen->base !== 'edit' || !in_array($post_type, ['page', 'product'], true) || dca_tb_acf_available()) {
        return;
    }

    echo '<div class="notice notice-warning"><p>' . esc_html__('Content Sync Manager: ACF is niet actief of niet volledig beschikbaar. Pagina- en productimports met ACF-velden worden geblokkeerd totdat ACF actief is. Berichtimports blijven beschikbaar.', 'content-sync-manager') . '</p></div>';
});

function dca_tb_render_admin_modals() {
    if (!dca_tb_should_load_admin_ui() || !dca_tb_current_user_can_use_manager()) {
        return;
    }

    $html = <<<'HTML'
    <div class="dca-modal" id="dca-single-modal" role="dialog" aria-modal="true" aria-labelledby="dca-single-title" aria-hidden="true">
            <div class="dca-box">
                <div class="dca-head">
                    <h2 id="dca-single-title">Content Sync</h2>
                    <button type="button" class="button dca-close-single">Sluiten</button>
                </div>
                <div class="dca-content">
                    <label class="screen-reader-text" for="dca-single-output">Contenttekst</label>
                    <textarea class="dca-textarea" id="dca-single-output"></textarea>
                    <div class="dca-actions">
                        <button type="button" class="button button-primary" id="dca-single-save">Opslaan</button>
                        <button type="button" class="button" id="dca-single-copy">Kopieer tekst</button>
                        <button type="button" class="button" id="dca-single-download">Download .txt</button>
                        <a class="button" id="dca-single-view" href="#" target="_blank" rel="noopener">Open voorkant</a>
                        <span class="dca-status" id="dca-single-status" role="status" aria-live="polite"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="dca-modal" id="dca-bulk-modal" role="dialog" aria-modal="true" aria-labelledby="dca-bulk-title" aria-hidden="true">
            <div class="dca-box">
                <div class="dca-head">
                    <h2 id="dca-bulk-title">Bulkeditor</h2>
                    <button type="button" class="button dca-close-bulk">Sluiten</button>
                </div>
                <div class="dca-content">
                    <label class="screen-reader-text" for="dca-bulk-output">Bulktekst</label>
                    <textarea class="dca-textarea" id="dca-bulk-output"></textarea>
                    <div class="dca-actions">
                        <button type="button" class="button" id="dca-bulk-check">Controleer bulktekst</button>
                        <button type="button" class="button button-primary" id="dca-bulk-save" disabled>Bulk opslaan</button>
                        <button type="button" class="button" id="dca-bulk-copy">Kopieer alles</button>
                        <button type="button" class="button" id="dca-bulk-download">Download .txt</button>
                        <span class="dca-status" id="dca-bulk-status" role="status" aria-live="polite"></span>
                    </div>
                    <div class="dca-preview" id="dca-bulk-preview"></div>
                </div>
            </div>
        </div>

        <div class="dca-modal" id="dca-import-modal" role="dialog" aria-modal="true" aria-labelledby="dca-import-title" aria-hidden="true">
            <div class="dca-box">
                <div class="dca-head">
                    <h2 id="dca-import-title">TXT importeren</h2>
                    <button type="button" class="button dca-close-import">Sluiten</button>
                </div>
                <div class="dca-content">
                    <p class="dca-warning">Gebruik een TXT-bestand dat via “Exporteer als .txt” is gemaakt. Controleer het bestand verplicht vóór import. Berichten, pagina’s en producten met een standaardtemplate worden verwerkt; Elementor Canvas en Elementor Full Width worden overgeslagen. Import kan content, ACF-data, categorievelden, Yoast metabeschrijvingen, media metadata en fysieke mediabestandsnamen wijzigen.</p>
                    <label class="screen-reader-text" for="dca-import-file">TXT-bestand kiezen</label>
                    <input type="file" id="dca-import-file" accept=".txt,text/plain">
                    <div class="dca-actions">
                        <button type="button" class="button" id="dca-import-preview">Controleer bestand</button>
                        <button type="button" class="button button-primary" id="dca-import-run" disabled>Importeer gecontroleerde items</button>
                        <span class="dca-status" id="dca-import-status" role="status" aria-live="polite"></span>
                    </div>
                    <div class="dca-preview" id="dca-import-preview-box"></div>
                </div>
            </div>
        </div>

        <div class="dca-toast" id="dca-toast" role="status" aria-live="polite" aria-atomic="true"></div>
    HTML;
    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action('admin_footer-edit.php', 'dca_tb_render_admin_modals');
add_action('admin_footer-edit-tags.php', 'dca_tb_render_admin_modals');
