<?php
/**
 * AI image context export for Content Sync Manager.
 *
 * @package ContentSyncManager
 */

defined('ABSPATH') || exit;

if (!defined('DCA_TB_AI_IMAGE_CONTEXT_MAX_POSTS')) {
    define('DCA_TB_AI_IMAGE_CONTEXT_MAX_POSTS', 50);
}

if (!defined('DCA_TB_AI_IMAGE_CONTEXT_MAX_CONTEXT_CHARS')) {
    define('DCA_TB_AI_IMAGE_CONTEXT_MAX_CONTEXT_CHARS', 5000);
}

function dca_tb_ai_image_context_screen_is_supported() {
    global $pagenow;

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
        DCA_TB_VERSION,
        true
    );
}
add_action('admin_enqueue_scripts', 'dca_tb_enqueue_ai_image_context_assets', 20);

function dca_tb_ai_image_context_clean_excerpt($value, $max_chars = 1000) {
    $value = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $value)));

    if ($value === '') {
        return '';
    }

    $max_chars = max(100, absint($max_chars));

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value) > $max_chars ? rtrim(mb_substr($value, 0, $max_chars - 1)) . '…' : $value;
    }

    return strlen($value) > $max_chars ? rtrim(substr($value, 0, $max_chars - 3)) . '...' : $value;
}

function dca_tb_ai_image_context_page_text($post_id) {
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

    return dca_tb_ai_image_context_clean_excerpt(implode("\n", $parts), DCA_TB_AI_IMAGE_CONTEXT_MAX_CONTEXT_CHARS);
}

function dca_tb_ai_image_context_preview($attachment_id) {
    $attachment_id = absint($attachment_id);
    $sizes = ['large', 'medium_large', 'medium'];

    foreach ($sizes as $size) {
        $preview = wp_get_attachment_image_src($attachment_id, $size);
        if (!$preview || empty($preview[0])) {
            continue;
        }

        $width = isset($preview[1]) ? absint($preview[1]) : 0;
        $height = isset($preview[2]) ? absint($preview[2]) : 0;

        if ($width > 0 && $height > 0 && max($width, $height) <= 1280) {
            return [
                'url'    => esc_url_raw($preview[0]),
                'width'  => $width,
                'height' => $height,
                'size'   => $size,
                'status' => 'ready',
            ];
        }
    }

    $full = wp_get_attachment_image_src($attachment_id, 'full');
    if ($full && !empty($full[0])) {
        $width = isset($full[1]) ? absint($full[1]) : 0;
        $height = isset($full[2]) ? absint($full[2]) : 0;

        if ($width > 0 && $height > 0 && max($width, $height) <= 1280) {
            return [
                'url'    => esc_url_raw($full[0]),
                'width'  => $width,
                'height' => $height,
                'size'   => 'full-small',
                'status' => 'ready',
            ];
        }
    }

    return [
        'url'    => '',
        'width'  => 0,
        'height' => 0,
        'size'   => '',
        'status' => 'manual-preview-needed',
    ];
}

function dca_tb_ai_image_context_usage_map($post_ids) {
    $usage = [];

    foreach ($post_ids as $post_id) {
        $post_id = absint($post_id);
        if (!$post_id || !function_exists('dca_tb_collect_media_refs')) {
            continue;
        }

        foreach (dca_tb_collect_media_refs($post_id) as $attachment_id => $ref) {
            $attachment_id = absint($attachment_id);
            if (!$attachment_id) {
                continue;
            }

            if (!isset($usage[$attachment_id])) {
                $usage[$attachment_id] = [];
            }

            $usage[$attachment_id][$post_id] = true;
        }
    }

    return $usage;
}

function dca_tb_ai_image_context_instruction_block() {
    return implode("\n", [
        'INSTRUCTIE VOOR CHATGPT',
        'Analyseer per afbeelding eerst metadata, bronveld en paginacontext.',
        'Gebruik de AI-preview alleen wanneer tekst en metadata onvoldoende duidelijk zijn.',
        'De preview-URL is een kleine WordPress-weergave; gebruik niet automatisch het volledige origineel.',
        'Benoem alleen wat visueel betrouwbaar is vastgesteld. Gok niet op personen, locaties, merken of eigenschappen.',
        'Is de afbeelding ook met preview niet duidelijk genoeg, zet Status op onzeker en laat bestaande metadata ongemoeid.',
        'Combineer wat zichtbaar is met de functie van de afbeelding binnen de pagina.',
        'Voor puur decoratieve afbeeldingen mag de geadviseerde alt-tekst leeg zijn.',
        'Bij een gedeelde Attachment ID moet metadata bruikbaar blijven voor alle gemelde pagina’s; maak geen te paginaspecifieke gedeelde metadata.',
        'Maak waar voldoende zeker een SEO-bestandsnaam zonder extensie, alt-tekst, titel, caption en description.',
        'Geef daarna per pagina een importklaar Content Sync Manager TXT-blok terug. Wijzig daarin alleen Nieuwe bestandsnaam, Title, Alt text, Caption en Description onder MEDIA. Verwijder de AI-contextregels uit het importbestand.',
    ]);
}

function dca_tb_ai_image_context_build_export($post_ids) {
    $post_ids = array_values(array_unique(array_filter(array_map('absint', (array) $post_ids))));
    $post_ids = array_slice($post_ids, 0, DCA_TB_AI_IMAGE_CONTEXT_MAX_POSTS);
    $usage = dca_tb_ai_image_context_usage_map($post_ids);
    $sections = [
        'AI AFBEELDINGSCONTEXT EXPORT',
        'Schema: 1',
        'Gegenereerd: ' . current_time('mysql'),
        '',
        dca_tb_ai_image_context_instruction_block(),
    ];

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);

        if (!$post || !function_exists('dca_tb_is_supported_post_type') || !dca_tb_is_supported_post_type($post->post_type)) {
            continue;
        }

        if (function_exists('dca_tb_can_edit_post') && !dca_tb_can_edit_post($post_id)) {
            continue;
        }

        $refs = function_exists('dca_tb_collect_media_refs') ? dca_tb_collect_media_refs($post_id) : [];
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
            $attachment = get_post($attachment_id);
            if (!$attachment || $attachment->post_type !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
                continue;
            }

            $preview = dca_tb_ai_image_context_preview($attachment_id);
            $sources = !empty($ref['sources']) && is_array($ref['sources'])
                ? implode(', ', array_map('dca_tb_clean_text', $ref['sources']))
                : '';
            $used_by = isset($usage[$attachment_id]) ? array_map('absint', array_keys($usage[$attachment_id])) : [];

            $metadata = wp_get_attachment_metadata($attachment_id);
            $width = is_array($metadata) && isset($metadata['width']) ? absint($metadata['width']) : 0;
            $height = is_array($metadata) && isset($metadata['height']) ? absint($metadata['height']) : 0;

            $sections[] = '';
            $sections[] = 'AFBEELDINGSCONTEXT ' . $index;
            $sections[] = 'Attachment ID: ' . $attachment_id;
            $sections[] = 'Bron/ACF-veld: ' . $sources;
            $sections[] = 'Gebruikt in geselecteerde Post IDs: ' . implode(', ', $used_by);
            $sections[] = 'Gedeeld binnen selectie: ' . (count($used_by) > 1 ? 'ja' : 'nee');
            $sections[] = 'Sitebreed gedeeld: onbekend; controleer vóór paginaspecifieke metadata als dezelfde attachment elders wordt hergebruikt.';
            $sections[] = 'Huidige URL: ' . esc_url_raw(wp_get_attachment_url($attachment_id));
            $sections[] = 'Afmetingen origineel: ' . $width . 'x' . $height;
            $sections[] = 'AI-preview status: ' . $preview['status'];
            $sections[] = 'AI-preview URL: ' . $preview['url'];
            $sections[] = 'AI-preview afmetingen: ' . $preview['width'] . 'x' . $preview['height'];
            $sections[] = 'AI-preview bronformaat: ' . $preview['size'];
            $sections[] = 'Huidige bestandsnaam: ' . (function_exists('dca_tb_media_filename') ? dca_tb_media_filename($attachment_id) : '');
            $sections[] = 'Huidige title: ' . dca_tb_ai_image_context_clean_excerpt($attachment->post_title, 1000);
            $sections[] = 'Huidige alt: ' . dca_tb_ai_image_context_clean_excerpt(get_post_meta($attachment_id, '_wp_attachment_image_alt', true), 1000);
            $sections[] = 'Huidige caption: ' . dca_tb_ai_image_context_clean_excerpt($attachment->post_excerpt, 1500);
            $sections[] = 'Huidige description: ' . dca_tb_ai_image_context_clean_excerpt($attachment->post_content, 2000);
            $sections[] = 'AI status: [invullen: duidelijk/onzeker/decoratief]';
            $sections[] = 'Wat is zichtbaar: [invullen]';
            $sections[] = 'Zekerheid: [invullen: hoog/middel/laag]';
            $sections[] = 'SEO bestandsnaam zonder extensie: [invullen]';
            $sections[] = 'SEO alt: [invullen]';
            $sections[] = 'SEO title: [invullen]';
            $sections[] = 'SEO caption: [invullen indien nuttig]';
            $sections[] = 'SEO description: [invullen indien nuttig]';
            $index++;
        }

        if (function_exists('dca_tb_build_textblock')) {
            $sections[] = '';
            $sections[] = 'IMPORTBLOK VOOR DEZE PAGINA';
            $sections[] = 'Gebruik dit blok als basis voor het uiteindelijke importbestand. Verwijder deze labelregel zelf uit de uiteindelijke import.';
            $sections[] = dca_tb_build_textblock($post_id);
        }
    }

    return trim(implode("\n", $sections));
}

function dca_tb_ajax_ai_image_context_export() {
    if (!function_exists('dca_tb_require_ajax_access')) {
        wp_send_json_error(['message' => 'Content Sync Manager is niet volledig geladen.'], 500);
    }

    dca_tb_require_ajax_access();

    $raw_ids = isset($_POST['ids']) ? wp_unslash($_POST['ids']) : [];
    if (!is_array($raw_ids)) {
        wp_send_json_error(['message' => 'Ongeldige selectie.'], 400);
    }

    $ids = array_values(array_unique(array_filter(array_map('absint', $raw_ids))));
    if (!$ids) {
        wp_send_json_error(['message' => 'Selecteer minimaal één pagina, bericht of product.'], 400);
    }

    if (count($ids) > DCA_TB_AI_IMAGE_CONTEXT_MAX_POSTS) {
        wp_send_json_error(['message' => 'Selecteer maximaal ' . DCA_TB_AI_IMAGE_CONTEXT_MAX_POSTS . ' items per AI-context-export.'], 400);
    }

    $text = dca_tb_ai_image_context_build_export($ids);
    if ($text === '') {
        wp_send_json_error(['message' => 'Voor deze selectie kon geen AI-afbeeldingscontext worden gemaakt.'], 422);
    }

    wp_send_json_success([
        'text'     => $text,
        'filename' => 'content-sync-ai-images-' . current_time('Y-m-d-His') . '.txt',
    ]);
}
add_action('wp_ajax_dca_ai_image_context_export', 'dca_tb_ajax_ai_image_context_export');
