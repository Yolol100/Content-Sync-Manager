<?php
/**
 * Persistent AI media context and smart media selection for Content Sync Manager.
 *
 * @package ContentSyncManager
 */

defined('ABSPATH') || exit;

if (!defined('DCA_TB_AI_MEDIA_INDEX_SEARCH_MAX_MEDIA')) {
    define('DCA_TB_AI_MEDIA_INDEX_SEARCH_MAX_MEDIA', 2000);
}

if (!defined('DCA_TB_AI_MEDIA_INDEX_MAX_LIST_ITEMS')) {
    define('DCA_TB_AI_MEDIA_INDEX_MAX_LIST_ITEMS', 30);
}

function dca_tb_ai_media_index_meta_key() {
    return '_dca_ai_media_context';
}

function dca_tb_ai_media_index_clean_text($value, $max_chars = 500) {
    $value = function_exists('dca_tb_clean_text') ? dca_tb_clean_text($value) : sanitize_text_field((string) $value);
    $value = trim((string) $value);
    $max_chars = max(1, absint($max_chars));

    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value) > $max_chars ? mb_substr($value, 0, $max_chars) : $value;
    }

    return strlen($value) > $max_chars ? substr($value, 0, $max_chars) : $value;
}

function dca_tb_ai_media_index_clean_list($values, $max_chars = 160) {
    if (!is_array($values)) {
        return [];
    }

    $clean = [];
    foreach ($values as $value) {
        if (!is_scalar($value) && $value !== null) {
            continue;
        }

        $value = dca_tb_ai_media_index_clean_text((string) $value, $max_chars);
        if ($value === '' || in_array($value, $clean, true)) {
            continue;
        }

        $clean[] = $value;
        if (count($clean) >= DCA_TB_AI_MEDIA_INDEX_MAX_LIST_ITEMS) {
            break;
        }
    }

    return $clean;
}

function dca_tb_ai_media_index_normalize_enum($value, $map, $default = '') {
    $value = strtolower(dca_tb_ai_media_index_clean_text($value, 40));
    return isset($map[$value]) ? $map[$value] : $default;
}

function dca_tb_ai_media_index_clean_use_cases($value) {
    if (!is_array($value)) {
        return [];
    }

    $allowed_keys = ['hero', 'featured', 'card', 'gallery', 'background', 'product', 'thumbnail', 'inline', 'social'];
    $rating_map = [
        'high' => 'high',
        'hoog' => 'high',
        'medium' => 'medium',
        'middel' => 'medium',
        'low' => 'low',
        'laag' => 'low',
        'unsuitable' => 'unsuitable',
        'ongeschikt' => 'unsuitable',
        'unknown' => 'unknown',
        'onbekend' => 'unknown',
    ];
    $clean = [];

    foreach ($allowed_keys as $key) {
        if (!array_key_exists($key, $value)) {
            continue;
        }

        $rating = dca_tb_ai_media_index_normalize_enum($value[$key], $rating_map, 'unknown');
        $clean[$key] = $rating;
    }

    return $clean;
}

function dca_tb_ai_media_index_empty_context() {
    return [
        'status' => '',
        'summary' => '',
        'confidence' => '',
        'tags' => [],
        'subjects' => [],
        'people' => [],
        'objects' => [],
        'products' => [],
        'logos' => [],
        'locations' => [],
        'screenshots' => [],
        'visible_text' => '',
        'colors' => [],
        'style' => [],
        'composition' => [],
        'use_cases' => [],
    ];
}

function dca_tb_ai_media_index_sanitize_context($value) {
    if (!is_array($value)) {
        return [];
    }

    $status = dca_tb_ai_media_index_normalize_enum(
        $value['status'] ?? '',
        [
            'clear' => 'clear',
            'duidelijk' => 'clear',
            'uncertain' => 'uncertain',
            'onzeker' => 'uncertain',
            'decorative' => 'decorative',
            'decoratief' => 'decorative',
        ]
    );
    $confidence = dca_tb_ai_media_index_normalize_enum(
        $value['confidence'] ?? '',
        [
            'high' => 'high',
            'hoog' => 'high',
            'medium' => 'medium',
            'middel' => 'medium',
            'low' => 'low',
            'laag' => 'low',
            'unknown' => 'unknown',
            'onbekend' => 'unknown',
        ]
    );

    $context = [
        'status' => $status,
        'summary' => dca_tb_ai_media_index_clean_text($value['summary'] ?? '', 1200),
        'confidence' => $confidence,
        'tags' => dca_tb_ai_media_index_clean_list($value['tags'] ?? []),
        'subjects' => dca_tb_ai_media_index_clean_list($value['subjects'] ?? []),
        'people' => dca_tb_ai_media_index_clean_list($value['people'] ?? []),
        'objects' => dca_tb_ai_media_index_clean_list($value['objects'] ?? []),
        'products' => dca_tb_ai_media_index_clean_list($value['products'] ?? []),
        'logos' => dca_tb_ai_media_index_clean_list($value['logos'] ?? []),
        'locations' => dca_tb_ai_media_index_clean_list($value['locations'] ?? []),
        'screenshots' => dca_tb_ai_media_index_clean_list($value['screenshots'] ?? []),
        'visible_text' => dca_tb_ai_media_index_clean_text($value['visible_text'] ?? '', 1500),
        'colors' => dca_tb_ai_media_index_clean_list($value['colors'] ?? []),
        'style' => dca_tb_ai_media_index_clean_list($value['style'] ?? []),
        'composition' => dca_tb_ai_media_index_clean_list($value['composition'] ?? []),
        'use_cases' => dca_tb_ai_media_index_clean_use_cases($value['use_cases'] ?? []),
    ];

    $has_value = false;
    foreach ($context as $item) {
        if ((is_array($item) && $item) || (!is_array($item) && $item !== '')) {
            $has_value = true;
            break;
        }
    }

    return $has_value ? $context : [];
}

function dca_tb_ai_media_index_attachment_fingerprint($attachment_id) {
    $attachment_id = absint($attachment_id);
    $attachment = get_post($attachment_id);

    if (!$attachment || $attachment->post_type !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
        return '';
    }

    $file = get_attached_file($attachment_id);
    $metadata = wp_get_attachment_metadata($attachment_id);
    $parts = [
        'id' => $attachment_id,
        'mime' => (string) get_post_mime_type($attachment_id),
        'size' => $file && is_file($file) ? (int) filesize($file) : 0,
        'mtime' => $file && is_file($file) ? (int) filemtime($file) : 0,
        'width' => is_array($metadata) ? absint($metadata['width'] ?? 0) : 0,
        'height' => is_array($metadata) ? absint($metadata['height'] ?? 0) : 0,
    ];

    return hash('sha256', wp_json_encode($parts));
}

function dca_tb_ai_media_index_context_state($attachment_id) {
    $attachment_id = absint($attachment_id);
    $stored = get_post_meta($attachment_id, dca_tb_ai_media_index_meta_key(), true);
    $stored = is_array($stored) ? $stored : [];
    $current_fingerprint = dca_tb_ai_media_index_attachment_fingerprint($attachment_id);
    $stored_fingerprint = isset($stored['attachment_fingerprint']) ? (string) $stored['attachment_fingerprint'] : '';
    $stale = $stored && ($current_fingerprint === '' || $stored_fingerprint === '' || !hash_equals($stored_fingerprint, $current_fingerprint));

    $semantic = $stored;
    unset($semantic['schema'], $semantic['source'], $semantic['indexed_at_gmt'], $semantic['attachment_fingerprint']);
    $semantic = dca_tb_ai_media_index_sanitize_context($semantic);

    return [
        'indexed' => (bool) $semantic,
        'stale' => (bool) $stale,
        'context' => $semantic,
        'attachment_fingerprint' => $current_fingerprint,
        'indexed_at_gmt' => isset($stored['indexed_at_gmt']) ? (string) $stored['indexed_at_gmt'] : '',
        'source' => isset($stored['source']) ? (string) $stored['source'] : '',
    ];
}

function dca_tb_ai_media_index_get_context($attachment_id, $allow_stale = false) {
    $state = dca_tb_ai_media_index_context_state($attachment_id);
    if (!$state['indexed'] || ($state['stale'] && !$allow_stale)) {
        return [];
    }

    return $state['context'];
}

function dca_tb_ai_media_index_store_context($attachment_id, $context) {
    $attachment_id = absint($attachment_id);
    $attachment = get_post($attachment_id);

    if (!$attachment || $attachment->post_type !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
        return new WP_Error('dca_ai_media_index_attachment', 'De AI-context hoort niet bij een geldige afbeelding.');
    }

    if (!current_user_can('edit_post', $attachment_id)) {
        return new WP_Error('dca_ai_media_index_permission', 'Geen rechten om AI-context voor deze afbeelding op te slaan.');
    }

    $semantic = dca_tb_ai_media_index_sanitize_context($context);
    if (!$semantic) {
        return false;
    }

    $current = dca_tb_ai_media_index_get_context($attachment_id, true);
    if ($current === $semantic && !dca_tb_ai_media_index_context_state($attachment_id)['stale']) {
        return false;
    }

    $stored = $semantic;
    $stored['schema'] = 1;
    $stored['source'] = 'chatgpt-assisted-import';
    $stored['indexed_at_gmt'] = current_time('mysql', true);
    $stored['attachment_fingerprint'] = dca_tb_ai_media_index_attachment_fingerprint($attachment_id);

    $updated = update_post_meta($attachment_id, dca_tb_ai_media_index_meta_key(), $stored);
    if ($updated === false && get_post_meta($attachment_id, dca_tb_ai_media_index_meta_key(), true) !== $stored) {
        return new WP_Error('dca_ai_media_index_store', 'De AI-context kon niet worden opgeslagen.');
    }

    return true;
}

function dca_tb_ai_media_index_inventory($attachment_id) {
    $attachment_id = absint($attachment_id);
    $attachment = get_post($attachment_id);

    if (!$attachment || $attachment->post_type !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
        return [];
    }

    $metadata = wp_get_attachment_metadata($attachment_id);
    $metadata = is_array($metadata) ? $metadata : [];
    $width = absint($metadata['width'] ?? 0);
    $height = absint($metadata['height'] ?? 0);
    $file = get_attached_file($attachment_id);
    $file_size = $file && is_file($file) ? (int) filesize($file) : 0;
    $aspect_ratio = $width > 0 && $height > 0 ? round($width / $height, 4) : 0;
    $orientation = 'unknown';

    if ($width > 0 && $height > 0) {
        if (abs($width - $height) <= max(1, (int) round(max($width, $height) * 0.02))) {
            $orientation = 'square';
        } else {
            $orientation = $width > $height ? 'landscape' : 'portrait';
        }
    }

    $sizes = [];
    foreach ((array) ($metadata['sizes'] ?? []) as $name => $size_data) {
        if (!is_array($size_data) || count($sizes) >= 50) {
            continue;
        }

        $src = wp_get_attachment_image_src($attachment_id, (string) $name);
        $sizes[] = [
            'name' => sanitize_key((string) $name),
            'url' => $src && !empty($src[0]) ? esc_url_raw($src[0]) : '',
            'width' => absint($size_data['width'] ?? ($src[1] ?? 0)),
            'height' => absint($size_data['height'] ?? ($src[2] ?? 0)),
            'mime_type' => dca_tb_ai_media_index_clean_text($size_data['mime-type'] ?? '', 100),
        ];
    }

    return [
        'attachment_id' => $attachment_id,
        'url' => esc_url_raw(wp_get_attachment_url($attachment_id)),
        'filename' => function_exists('dca_tb_media_filename') ? dca_tb_media_filename($attachment_id) : basename((string) $file),
        'mime_type' => (string) get_post_mime_type($attachment_id),
        'file_size_bytes' => max(0, $file_size),
        'width' => $width,
        'height' => $height,
        'aspect_ratio' => $aspect_ratio,
        'orientation' => $orientation,
        'available_sizes' => $sizes,
    ];
}

function dca_tb_ai_media_index_export_context($attachment_id) {
    $state = dca_tb_ai_media_index_context_state($attachment_id);
    if ($state['indexed'] && !$state['stale']) {
        return $state['context'];
    }

    return dca_tb_ai_media_index_empty_context();
}

function dca_tb_ai_media_index_enrich_import_blocks($text) {
    return preg_replace_callback(
        '/^MEDIA IMPORT\s*$\n(\{.*?\})\n^EINDE MEDIA IMPORT\s*$/ims',
        static function ($matches) {
            $payload = json_decode($matches[1], true);
            if (!is_array($payload) || empty($payload['attachment_id'])) {
                return $matches[0];
            }

            $attachment_id = absint($payload['attachment_id']);
            $payload['inventory'] = dca_tb_ai_media_index_inventory($attachment_id);
            $payload['ai'] = dca_tb_ai_media_index_export_context($attachment_id);
            $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (!is_string($json) || $json === '') {
                return $matches[0];
            }

            return "MEDIA IMPORT\n" . $json . "\nEINDE MEDIA IMPORT";
        },
        (string) $text
    );
}

function dca_tb_ai_media_index_tokens($text) {
    $text = function_exists('remove_accents') ? remove_accents((string) $text) : (string) $text;
    $text = strtolower(wp_strip_all_tags($text));
    $parts = preg_split('/[^a-z0-9]+/i', $text, -1, PREG_SPLIT_NO_EMPTY);
    $stop = [
        'aan', 'als', 'bij', 'dat', 'deze', 'een', 'geen', 'het', 'met', 'naar', 'niet', 'ook', 'voor', 'van',
        'and', 'are', 'for', 'from', 'into', 'not', 'the', 'this', 'with', 'your',
    ];
    $tokens = [];

    foreach ((array) $parts as $part) {
        if (strlen($part) < 3 || in_array($part, $stop, true) || isset($tokens[$part])) {
            continue;
        }
        $tokens[$part] = true;
        if (count($tokens) >= 80) {
            break;
        }
    }

    return array_keys($tokens);
}

function dca_tb_ai_media_index_match_score($tokens, $value, $weight) {
    if (!$tokens || $value === '' || $weight <= 0) {
        return 0;
    }

    $haystack = strtolower(function_exists('remove_accents') ? remove_accents((string) $value) : (string) $value);
    $score = 0;
    foreach ($tokens as $token) {
        if (strpos($haystack, $token) !== false) {
            $score += $weight;
        }
    }

    return $score;
}

function dca_tb_ai_media_index_catalog() {
    static $catalog = null;

    if ($catalog !== null) {
        return $catalog;
    }

    $limit = max(1, absint(DCA_TB_AI_MEDIA_INDEX_SEARCH_MAX_MEDIA));
    $query = new WP_Query([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'post_mime_type' => 'image',
        'posts_per_page' => $limit + 1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'DESC',
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);
    $ids = array_values(array_map('absint', (array) $query->posts));
    $complete = count($ids) <= $limit;
    $ids = array_slice($ids, 0, $limit);

    $catalog = [
        'complete' => $complete,
        'limit' => $limit,
        'ids' => $ids,
    ];

    return $catalog;
}

function dca_tb_ai_media_index_search($query_text, $args = []) {
    $tokens = dca_tb_ai_media_index_tokens($query_text);
    if (!$tokens) {
        return [];
    }

    $limit = isset($args['limit']) ? max(1, min(20, absint($args['limit']))) : 5;
    $catalog = dca_tb_ai_media_index_catalog();
    $results = [];

    foreach ($catalog['ids'] as $attachment_id) {
        if (!current_user_can('edit_post', $attachment_id)) {
            continue;
        }

        $attachment = get_post($attachment_id);
        if (!$attachment) {
            continue;
        }

        $context = dca_tb_ai_media_index_get_context($attachment_id);
        $score = 0;
        $score += dca_tb_ai_media_index_match_score($tokens, (string) $attachment->post_title, 2);
        $score += dca_tb_ai_media_index_match_score($tokens, (string) $attachment->post_excerpt, 1);
        $score += dca_tb_ai_media_index_match_score($tokens, (string) $attachment->post_content, 1);
        $score += dca_tb_ai_media_index_match_score($tokens, (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true), 3);
        $score += dca_tb_ai_media_index_match_score($tokens, function_exists('dca_tb_media_filename') ? dca_tb_media_filename($attachment_id) : '', 2);

        if ($context) {
            $score += dca_tb_ai_media_index_match_score($tokens, $context['summary'] ?? '', 8);
            $score += dca_tb_ai_media_index_match_score($tokens, $context['visible_text'] ?? '', 5);
            foreach (['tags', 'subjects', 'people', 'objects', 'products', 'logos', 'locations', 'screenshots', 'colors', 'style', 'composition'] as $key) {
                $score += dca_tb_ai_media_index_match_score($tokens, implode(' ', (array) ($context[$key] ?? [])), 10);
            }
            $score += dca_tb_ai_media_index_match_score($tokens, implode(' ', array_keys((array) ($context['use_cases'] ?? []))), 4);
            if (($context['confidence'] ?? '') === 'high') {
                $score += 4;
            } elseif (($context['confidence'] ?? '') === 'medium') {
                $score += 2;
            }
        }

        if ($score < 1) {
            continue;
        }

        $metadata = wp_get_attachment_metadata($attachment_id);
        $metadata = is_array($metadata) ? $metadata : [];
        $preview = wp_get_attachment_image_src($attachment_id, 'medium');
        if (!$preview || empty($preview[0])) {
            $preview = wp_get_attachment_image_src($attachment_id, 'full');
        }

        $results[] = [
            'attachment_id' => $attachment_id,
            'score' => $score,
            'indexed' => (bool) $context,
            'url' => esc_url_raw(wp_get_attachment_url($attachment_id)),
            'preview_url' => $preview && !empty($preview[0]) ? esc_url_raw($preview[0]) : '',
            'filename' => function_exists('dca_tb_media_filename') ? dca_tb_media_filename($attachment_id) : '',
            'title' => dca_tb_ai_media_index_clean_text($attachment->post_title, 300),
            'alt' => dca_tb_ai_media_index_clean_text(get_post_meta($attachment_id, '_wp_attachment_image_alt', true), 500),
            'width' => absint($metadata['width'] ?? 0),
            'height' => absint($metadata['height'] ?? 0),
            'summary' => dca_tb_ai_media_index_clean_text($context['summary'] ?? '', 600),
            'confidence' => (string) ($context['confidence'] ?? ''),
            'tags' => array_slice((array) ($context['tags'] ?? []), 0, 12),
            'use_cases' => (array) ($context['use_cases'] ?? []),
        ];
    }

    usort($results, static function ($a, $b) {
        $score_compare = ((int) $b['score']) <=> ((int) $a['score']);
        if ($score_compare !== 0) {
            return $score_compare;
        }
        return ((int) $b['attachment_id']) <=> ((int) $a['attachment_id']);
    });

    return array_slice($results, 0, $limit);
}

function dca_tb_ai_media_index_selection_section($post_ids) {
    $lines = [];
    $catalog = dca_tb_ai_media_index_catalog();

    foreach (array_slice(array_values(array_unique(array_filter(array_map('absint', (array) $post_ids)))), 0, DCA_TB_AI_IMAGE_CONTEXT_MAX_POSTS) as $post_id) {
        $post = get_post($post_id);
        if (!$post || !function_exists('dca_tb_is_supported_post_type') || !dca_tb_is_supported_post_type($post->post_type)) {
            continue;
        }
        if (function_exists('dca_tb_can_edit_post') && !dca_tb_can_edit_post($post_id)) {
            continue;
        }

        $context = function_exists('dca_tb_ai_image_context_page_text') ? dca_tb_ai_image_context_page_text($post_id) : get_the_title($post_id);
        $matches = dca_tb_ai_media_index_search($context, ['limit' => 5]);
        $lines[] = '';
        $lines[] = 'SLIMME MEDIASELECTIE VOOR POST #' . $post_id;
        $lines[] = 'Bibliotheekscan: ' . (!empty($catalog['complete']) ? 'compleet' : 'gedeeltelijk, limiet ' . absint($catalog['limit']));

        if (!$matches) {
            $lines[] = 'Kandidaten: geen aantoonbare match. Indexeer relevante afbeeldingen via Media > Bibliotheek en exporteer opnieuw.';
            continue;
        }

        foreach ($matches as $index => $match) {
            $number = $index + 1;
            $lines[] = 'Kandidaat ' . $number . ': Attachment ID ' . absint($match['attachment_id']) . ' | score ' . absint($match['score']) . ' | AI-index ' . (!empty($match['indexed']) ? 'ja' : 'nee');
            $lines[] = 'Kandidaat ' . $number . ' preview: ' . esc_url_raw($match['preview_url']);
            $lines[] = 'Kandidaat ' . $number . ' URL: ' . esc_url_raw($match['url']);
            $lines[] = 'Kandidaat ' . $number . ' afmetingen: ' . absint($match['width']) . 'x' . absint($match['height']);
            $lines[] = 'Kandidaat ' . $number . ' samenvatting: ' . dca_tb_ai_media_index_clean_text($match['summary'], 600);
            $lines[] = 'Kandidaat ' . $number . ' zekerheid: ' . dca_tb_ai_media_index_clean_text($match['confidence'], 40);
            $lines[] = 'Kandidaat ' . $number . ' tags: ' . implode(', ', array_map('dca_tb_ai_media_index_clean_text', (array) $match['tags']));
            if (!empty($match['use_cases'])) {
                $pairs = [];
                foreach ($match['use_cases'] as $use_case => $rating) {
                    $pairs[] = sanitize_key((string) $use_case) . '=' . sanitize_key((string) $rating);
                }
                $lines[] = 'Kandidaat ' . $number . ' gebruik: ' . implode(', ', $pairs);
            }
        }
    }

    return $lines ? implode("\n", $lines) : '';
}

function dca_tb_ai_media_index_enrich_export_text($text, $scope, $ids) {
    $text = dca_tb_ai_media_index_enrich_import_blocks($text);
    $text = str_replace(
        'MEDIA IMPORT bevat per afbeelding een enkele JSON-regel. Wijzig uitsluitend de JSON-waarden new_filename, title, alt, caption en description.',
        'MEDIA IMPORT bevat per afbeelding een enkele JSON-regel. Wijzig alleen new_filename, title, alt, caption, description en de waarden binnen ai. Vul ai met uitsluitend visueel onderbouwde informatie; laat onzekere velden leeg.',
        $text
    );
    $text = str_replace(
        'Wijzig nooit schema, attachment_id of filename. Laat de labels MEDIA IMPORT en EINDE MEDIA IMPORT staan zodat het bestand veilig kan worden teruggeimporteerd.',
        'Wijzig nooit schema, attachment_id, filename of inventory. Gebruik voor ai status clear/uncertain/decorative, confidence high/medium/low/unknown en use_cases met hero/featured/card/gallery/background/product/thumbnail/inline/social als sleutel en high/medium/low/unsuitable/unknown als waarde. Laat de labels MEDIA IMPORT en EINDE MEDIA IMPORT staan zodat het bestand veilig kan worden teruggeimporteerd.',
        $text
    );

    if ($scope !== 'media') {
        $selection = dca_tb_ai_media_index_selection_section($ids);
        if ($selection !== '') {
            $text = rtrim($text) . "\n\n" . $selection;
        }
    }

    return $text;
}

function dca_tb_ai_media_index_parse_contexts($text) {
    $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
    preg_match_all('/^MEDIA IMPORT\s*$\n(.*?)^EINDE MEDIA IMPORT\s*$/ims', $text, $matches);
    $contexts = [];

    foreach ((array) ($matches[1] ?? []) as $block) {
        $trimmed = ltrim((string) $block);
        if (!isset($trimmed[0]) || $trimmed[0] !== '{') {
            continue;
        }

        $decoded = json_decode(trim($block), true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE || empty($decoded['attachment_id'])) {
            continue;
        }

        if (isset($decoded['ai']) && !is_array($decoded['ai'])) {
            return new WP_Error('dca_ai_media_index_ai_shape', 'MEDIA IMPORT bevat een ongeldig ai-object.');
        }

        $context = dca_tb_ai_media_index_sanitize_context($decoded['ai'] ?? []);
        if ($context) {
            $contexts[absint($decoded['attachment_id'])] = $context;
        }
    }

    return $contexts;
}

function dca_tb_ai_media_index_context_changed($attachment_id, $context) {
    $context = dca_tb_ai_media_index_sanitize_context($context);
    if (!$context) {
        return false;
    }

    $state = dca_tb_ai_media_index_context_state($attachment_id);
    return $state['stale'] || $state['context'] !== $context;
}

function dca_tb_ai_media_index_preview_media_import($text) {
    $preview = dca_tb_ai_image_context_preview_media_import($text);
    if (is_wp_error($preview)) {
        return $preview;
    }

    $contexts = dca_tb_ai_media_index_parse_contexts($text);
    if (is_wp_error($contexts)) {
        return $contexts;
    }

    foreach ($preview['items'] as &$row) {
        $attachment_id = absint($row['attachment_id'] ?? 0);
        $ai_change = ($row['status'] ?? '') !== 'error'
            && isset($contexts[$attachment_id])
            && dca_tb_ai_media_index_context_changed($attachment_id, $contexts[$attachment_id]);
        $row['ai_context_change'] = $ai_change;
        if ($ai_change) {
            $row['changes'] = absint($row['changes'] ?? 0) + 1;
            $preview['changes'] = absint($preview['changes'] ?? 0) + 1;
        }
    }
    unset($row);

    $preview['ai_contexts'] = $contexts;
    return $preview;
}

function dca_tb_ai_media_index_apply_contexts($preview) {
    $result = [
        'indexed' => 0,
        'skipped' => 0,
        'errors' => 0,
        'messages' => [],
    ];
    $rows = [];

    foreach ((array) ($preview['items'] ?? []) as $row) {
        $rows[absint($row['attachment_id'] ?? 0)] = $row;
    }

    foreach ((array) ($preview['ai_contexts'] ?? []) as $attachment_id => $context) {
        $attachment_id = absint($attachment_id);
        $row = $rows[$attachment_id] ?? null;
        if (!$row || ($row['status'] ?? '') === 'error') {
            $result['skipped']++;
            continue;
        }

        if (!dca_tb_ai_media_index_context_changed($attachment_id, $context)) {
            $result['skipped']++;
            continue;
        }

        $stored = dca_tb_ai_media_index_store_context($attachment_id, $context);
        if (is_wp_error($stored)) {
            $result['errors']++;
            $result['messages'][] = 'Attachment #' . $attachment_id . ': ' . $stored->get_error_message();
            continue;
        }

        if ($stored) {
            $result['indexed']++;
        } else {
            $result['skipped']++;
        }
    }

    return $result;
}

function dca_tb_ai_media_index_ajax_export() {
    if (!function_exists('dca_tb_require_ajax_access')) {
        wp_send_json_error(['message' => 'Content Sync Manager is niet volledig geladen.'], 500);
    }

    dca_tb_require_ajax_access();
    $scope = sanitize_key(dca_tb_post_text('scope'));
    $scope = $scope !== '' ? $scope : 'content';
    $ids = dca_tb_post_id_list('ids');

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
            'text' => dca_tb_ai_media_index_enrich_export_text($text, 'media', $ids),
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
        'text' => dca_tb_ai_media_index_enrich_export_text($text, 'content', $ids),
        'filename' => 'content-sync-ai-images-' . current_time('Y-m-d-His') . '.txt',
    ]);
}

function dca_tb_ai_media_index_ajax_import_preview() {
    if (!function_exists('dca_tb_require_ajax_access')) {
        wp_send_json_error(['message' => 'Content Sync Manager is niet volledig geladen.'], 500);
    }

    dca_tb_require_ajax_access();
    $text = dca_tb_post_text('text');
    $size_check = dca_tb_ai_image_context_validate_import_text($text);
    if (is_wp_error($size_check)) {
        wp_send_json_error(['message' => $size_check->get_error_message()], 400);
    }

    $preview = dca_tb_ai_media_index_preview_media_import($text);
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

function dca_tb_ai_media_index_ajax_import_run() {
    if (!function_exists('dca_tb_require_ajax_access')) {
        wp_send_json_error(['message' => 'Content Sync Manager is niet volledig geladen.'], 500);
    }

    dca_tb_require_ajax_access();
    $text = dca_tb_post_text('text');
    $size_check = dca_tb_ai_image_context_validate_import_text($text);
    if (is_wp_error($size_check)) {
        wp_send_json_error(['message' => $size_check->get_error_message()], 400);
    }

    dca_tb_require_matching_import_preview($text);
    dca_tb_require_destructive_confirmation();

    $preview = dca_tb_ai_media_index_preview_media_import($text);
    if (is_wp_error($preview)) {
        wp_send_json_error(['message' => $preview->get_error_message()], 422);
    }

    $result = dca_tb_ai_image_context_apply_media_import($text);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 422);
    }

    $index_result = dca_tb_ai_media_index_apply_contexts($preview);
    $messages = array_merge((array) $result['messages'], (array) $index_result['messages']);

    wp_send_json_success([
        'message' => 'AI-media-import voltooid: ' . absint($result['updated']) . ' media-items bijgewerkt, ' . absint($index_result['indexed']) . ' AI-contexten geindexeerd, ' . absint($result['renamed']) . ' bestandsnamen hernoemd, ' . absint($result['rename_blocked']) . ' hernoemingen veilig geblokkeerd, ' . (absint($result['errors']) + absint($index_result['errors'])) . ' fouten.',
        'updated' => absint($result['updated']),
        'ai_indexed' => absint($index_result['indexed']),
        'renamed' => absint($result['renamed']),
        'skipped' => absint($result['skipped']) + absint($index_result['skipped']),
        'errors' => absint($result['errors']) + absint($index_result['errors']),
        'url_replaces' => absint($result['url_replaces']),
        'rename_blocked' => absint($result['rename_blocked']),
        'messages' => $messages,
    ]);
}

function dca_tb_ai_media_index_register_ajax_handlers() {
    remove_action('wp_ajax_dca_ai_image_context_export', 'dca_tb_ajax_ai_image_context_export');
    remove_action('wp_ajax_dca_ai_image_context_import_preview', 'dca_tb_ajax_ai_image_context_import_preview');
    remove_action('wp_ajax_dca_ai_image_context_import_run', 'dca_tb_ajax_ai_image_context_import_run');

    add_action('wp_ajax_dca_ai_image_context_export', 'dca_tb_ai_media_index_ajax_export');
    add_action('wp_ajax_dca_ai_image_context_import_preview', 'dca_tb_ai_media_index_ajax_import_preview');
    add_action('wp_ajax_dca_ai_image_context_import_run', 'dca_tb_ai_media_index_ajax_import_run');
}
dca_tb_ai_media_index_register_ajax_handlers();
