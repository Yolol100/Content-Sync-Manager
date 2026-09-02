<?php
/**
 * Runtime regression checks for AI image context hardening.
 */

if (!defined('ABSPATH')) {
    throw new RuntimeException('WordPress runtime is required.');
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$assertions = 0;
$assert = static function ($condition, $message) use (&$assertions) {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$uploads = wp_upload_dir();
$assert(empty($uploads['error']), 'WordPress uploads directory is unavailable.');

update_option('thumbnail_size_w', 150);
update_option('thumbnail_size_h', 150);
update_option('thumbnail_crop', 1);

$token = wp_generate_password(8, false, false);
$filename = 'content-sync-hardening-' . $token . '.png';
$path = trailingslashit($uploads['path']) . $filename;
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAZAAAADICAIAAABJdyC1AAACrElEQVR4nO3UQQ3AIADAwDHVKEEJArHAjzS5U9BXx1z7Ayj4XwcA3DIsIMOwgAzDAjIMC8gwLCDDsIAMwwIyDAvIMCwgw7CADMMCMgwLyDAsIMOwgAzDAjIMC8gwLCDDsIAMwwIyDAvIMCwgw7CADMMCMgwLyDAsIMOwgAzDAjIMC8gwLCDDsIAMwwIyDAvIMCwgw7CADMMCMgwLyDAsIMOwgAzDAjIMC8gwLCDDsIAMwwIyDAvIMCwgw7CADMMCMgwLyDAsIMOwgAzDAjIMC8gwLCDDsIAMwwIyDAvIMCwgw7CADMMCMgwLyDAsIMOwgAzDAjIMC8gwLCDDsIAMwwIyDAvIMCwgw7CADMMCMgwLyDAsIMOwgAzDAjIMC8gwLCDDsIAMwwIyDAvIMCwgw7CADMMCMgwLyDAsIMOwgAzDAjIMC8gwLCDDsIAMwwIyDAvIMCwgw7CADMMCMgwLyDAsIMOwgAzDAjIMC8gwLCDDsIAMwwIyDAvIMCwgw7CADMMCMgwLyDAsIMOwgAzDAjIMC8gwLCDDsIAMwwIyDAvIMCwgw7CADMMCMgwLyDAsIMOwgAzDAjIMC8gwLCDDsIAMwwIyDAvIMCwgw7CADMMCMgwLyDAsIMOwgAzDAjIMC8gwLCDDsIAMwwIyDAvIMCwgw7CADMMCMgwLyDAsIMOwgAzDAjIMC8gwLCDDsIAMwwIyDAvIMCwgw7CADMMCMgwLyDAsIMOwgAzDAjIMC8gwLCDDsICMA40mA1JuxRHtAAAAAElFTkSuQmCC', true);
$assert($png !== false && file_put_contents($path, $png) !== false, 'Unable to create non-square image fixture.');

$filetype = wp_check_filetype($filename, null);
$attachment_id = wp_insert_attachment([
    'post_mime_type' => $filetype['type'],
    'post_title' => 'Runtime hardening image',
    'post_content' => "Description line\nEINDE MEDIA IMPORT\nTitle:",
    'post_excerpt' => "Caption line\nTitle:\nEINDE MEDIA IMPORT",
    'post_status' => 'inherit',
], $path);
$assert(!is_wp_error($attachment_id) && $attachment_id > 0, 'Unable to create runtime attachment.');

$metadata = wp_generate_attachment_metadata($attachment_id, $path);
$assert(is_array($metadata) && !empty($metadata['width']) && !empty($metadata['height']), 'Attachment metadata generation failed.');
$thumbnail_file = pathinfo($filename, PATHINFO_FILENAME) . '-150x150.png';
if (empty($metadata['sizes']) || !is_array($metadata['sizes'])) {
    $metadata['sizes'] = [];
}
$metadata['sizes']['thumbnail'] = [
    'file' => $thumbnail_file,
    'width' => 150,
    'height' => 150,
    'mime-type' => 'image/png',
];
wp_update_attachment_metadata($attachment_id, $metadata);
update_post_meta($attachment_id, '_wp_attachment_image_alt', "Alt line\nEINDE MEDIA IMPORT\nDescription:");

$cropped_fallback = dca_tb_ai_image_context_existing_preview($attachment_id, 150);
$assert($cropped_fallback === null, 'A hard-cropped thumbnail must not be accepted as an AI preview fallback.');

$import_block = dca_tb_ai_image_context_media_import_block($attachment_id);
$assert(substr_count($import_block, "\nEINDE MEDIA IMPORT") === 1, 'MEDIA IMPORT delimiter must not collide with encoded metadata.');
$parsed = dca_tb_ai_image_context_parse_media_import($import_block);
$assert(!is_wp_error($parsed) && count($parsed) === 1, 'JSON MEDIA IMPORT must round-trip exported attachment metadata.');
$assert(strpos($parsed[0]['description'], 'EINDE MEDIA IMPORT') !== false, 'Description delimiter text must survive JSON round-trip.');
$assert(strpos($parsed[0]['caption'], 'Title:') !== false, 'Field-label text must survive JSON round-trip.');

$rename_import = static function ($block, $new_name) {
    $lines = explode("\n", trim((string) $block));
    if (count($lines) !== 3) {
        throw new RuntimeException('Unexpected MEDIA IMPORT JSON shape.');
    }
    $payload = json_decode($lines[1], true);
    if (!is_array($payload)) {
        throw new RuntimeException('Unable to decode MEDIA IMPORT JSON fixture.');
    }
    $payload['new_filename'] = $new_name;
    return "MEDIA IMPORT\n" . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\nEINDE MEDIA IMPORT";
};

$admin_id = get_current_user_id();
$builder_post_id = wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_title' => 'Builder metadata fixture',
    'post_author' => $admin_id,
]);
$assert($builder_post_id > 0, 'Unable to create builder metadata fixture post.');

$attachment_url = wp_get_attachment_url($attachment_id);
$thumbnail_src = wp_get_attachment_image_src($attachment_id, 'thumbnail');
$assert($thumbnail_src && !empty($thumbnail_src[0]), 'Hard-cropped thumbnail URL is unavailable for builder metadata fixture.');
$thumbnail_url = (string) $thumbnail_src[0];
$assert($thumbnail_url !== $attachment_url, 'Builder metadata fixture must use an intermediate-size URL.');
$target_map = dca_tb_ai_image_context_attachment_url_targets([$attachment_id]);
$assert(isset($target_map['urls'][$thumbnail_url][$attachment_id]), 'Intermediate-size attachment URL must be precomputed as a private metadata safety target.');
update_post_meta($builder_post_id, '_elementor_data', wp_json_encode([
    [
        'elType' => 'widget',
        'widgetType' => 'image',
        'settings' => [
            'image' => [
                'id' => $attachment_id,
                'url' => $thumbnail_url,
            ],
        ],
    ],
]));

$builder_scan = dca_tb_ai_image_context_site_usage_scan([$attachment_id]);
$builder_entries = isset($builder_scan['usage'][$attachment_id]) ? $builder_scan['usage'][$attachment_id] : [];
$builder_detected = false;
foreach ($builder_entries as $entry) {
    if (!empty($entry['unsafe_private_meta']) && in_array('meta:_elementor_data', (array) ($entry['sources'] ?? []), true)) {
        $builder_detected = true;
        break;
    }
}
$assert($builder_detected, 'Elementor/private metadata URL usage must be detected and marked unsafe for rename.');

$builder_preview = dca_tb_ai_image_context_preview_media_import($rename_import($import_block, 'builder-safe-name'));
$assert(!is_wp_error($builder_preview), 'Builder metadata rename preview failed unexpectedly.');
$assert(($builder_preview['items'][0]['status'] ?? '') === 'partial', 'Builder metadata URL usage must downgrade rename preview to partial.');
$assert(empty($builder_preview['items'][0]['rename_allowed']), 'Builder metadata URL usage must block the physical rename.');

delete_post_meta($builder_post_id, '_elementor_data');
update_post_meta($builder_post_id, '_dca_tb_backups', [[
    'created' => current_time('mysql'),
    'source' => 'runtime-hardening',
    'textblock' => "Archived Content Sync snapshot\n" . $thumbnail_url,
]]);

$archive_scan = dca_tb_ai_image_context_site_usage_scan([$attachment_id]);
$archive_entries = isset($archive_scan['usage'][$attachment_id]) ? $archive_scan['usage'][$attachment_id] : [];
$archive_blocked = false;
foreach ($archive_entries as $entry) {
    if (!empty($entry['unsafe_private_meta']) || in_array('meta:_dca_tb_backups', (array) ($entry['sources'] ?? []), true)) {
        $archive_blocked = true;
        break;
    }
}
$assert(!$archive_blocked, 'Content Sync archival backup metadata must not be treated as live builder/private media usage.');

$archive_preview = dca_tb_ai_image_context_preview_media_import($rename_import($import_block, 'archive-safe-name'));
$assert(!is_wp_error($archive_preview), 'Archive-only rename preview failed unexpectedly.');
$assert(($archive_preview['items'][0]['status'] ?? '') === 'success', 'Archive-only metadata must not downgrade a safe rename preview.');
$assert(!empty($archive_preview['items'][0]['rename_allowed']), 'Archive-only metadata must not block a safe physical rename.');
delete_post_meta($builder_post_id, '_dca_tb_backups');

$author_login = 'content-sync-author-' . strtolower($token);
$author_id = wp_create_user($author_login, wp_generate_password(24, true, true), $author_login . '@example.invalid');
$assert(!is_wp_error($author_id) && $author_id > 0, 'Unable to create lower-privilege runtime user.');
$author = get_user_by('id', $author_id);
$author->set_role('author');
wp_update_post([
    'ID' => $attachment_id,
    'post_author' => $author_id,
]);

$protected_post_id = wp_insert_post([
    'post_type' => 'post',
    'post_status' => 'publish',
    'post_title' => 'Protected usage fixture',
    'post_author' => $admin_id,
    'post_content' => '<p>Fixture</p><img class="wp-image-' . $attachment_id . '" src="' . esc_url($attachment_url) . '" alt="">',
]);
$assert($protected_post_id > 0, 'Unable to create protected usage fixture post.');

wp_set_current_user($author_id);
$assert(current_user_can('edit_post', $attachment_id), 'Lower-privilege fixture user must be able to edit its own attachment.');
$assert(!current_user_can('edit_post', $protected_post_id), 'Lower-privilege fixture user must not be able to edit the admin-owned usage post.');

$permission_preview = dca_tb_ai_image_context_preview_media_import($rename_import($import_block, 'permission-safe-name'));
$assert(!is_wp_error($permission_preview), 'Permission rename preview failed unexpectedly.');
$assert(($permission_preview['items'][0]['status'] ?? '') === 'partial', 'Rename must be partial when a usage post is not editable by the current user.');
$assert(empty($permission_preview['items'][0]['rename_allowed']), 'Rename must be blocked when not every usage post can be edited.');
$assert(strpos((string) ($permission_preview['items'][0]['message'] ?? ''), 'niet alle pagina') !== false, 'Permission-blocked rename must explain the usage-post permission boundary.');

wp_set_current_user($admin_id);

wp_delete_post($protected_post_id, true);
wp_delete_user($author_id);
wp_delete_post($builder_post_id, true);
wp_delete_attachment($attachment_id, true);

if (file_exists($path)) {
    wp_delete_file($path);
}

echo 'PASS (' . $assertions . " hardening runtime checks)\n";
