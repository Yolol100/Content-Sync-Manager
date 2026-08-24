<?php
/**
 * Dependency-free source/package regression audit for Content Sync Manager.
 * Run: php tests/static-audit.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$failures, &$checks): void {
    ++$checks;
    if (!$condition) {
        $failures[] = $message;
    }
};

$read = static function (string $path) use ($root, $assert): string {
    $full = $root . '/' . $path;
    $assert(is_file($full), "Missing required file: {$path}");
    if (!is_file($full)) {
        return '';
    }
    $content = file_get_contents($full);
    $assert($content !== false, "Unreadable file: {$path}");
    return $content === false ? '' : $content;
};

$bootstrap = $read('content-sync-manager.php');
$manager = $read('includes/manager.php');
$adminJs = $read('assets/admin.js');
$readme = $read('readme.txt');
$readmeMd = $read('README.md');
$workflow = $read('.github/workflows/quality.yml');
$read('assets/admin.css');
$read('uninstall.php');

$assert(strpos($bootstrap, 'Plugin Name: Content Sync Manager') !== false, 'Main plugin header is missing.');
$assert(strpos($bootstrap, "require_once DCA_TB_PLUGIN_DIR . 'includes/manager.php';") !== false, 'Canonical manager include is missing.');
$assert(strpos($bootstrap, "remove_action('admin_enqueue_scripts', 'dca_tb_enqueue_admin_assets')") !== false, 'Capability-bound admin asset guard is missing.');
$assert(strpos($bootstrap, "function_exists('dca_tb_current_user_can_use_manager')") !== false, 'Capability guard must fail safely when manager helper is unavailable.');

preg_match('/\* Version:\s*([0-9.]+)/', $bootstrap, $headerVersion);
preg_match("/define\('DCA_TB_VERSION',\s*'([^']+)'\)/", $bootstrap, $constantVersion);
preg_match('/Stable tag:\s*([^\r\n]+)/', $readme, $stableTag);
preg_match('/## Versie\s+([0-9.]+)/s', $readmeMd, $readmeMdVersion);
$assert(isset($headerVersion[1], $constantVersion[1]) && $headerVersion[1] === $constantVersion[1], 'Plugin header version and DCA_TB_VERSION must match.');
$assert(isset($headerVersion[1], $stableTag[1]) && trim($stableTag[1]) === $headerVersion[1], 'readme Stable tag must match plugin version.');
$assert(isset($headerVersion[1], $readmeMdVersion[1]) && $readmeMdVersion[1] === $headerVersion[1], 'README version must match plugin version.');
$assert(isset($headerVersion[1]) && strpos($readme, '= ' . $headerVersion[1] . ' =') !== false, 'readme changelog must contain current plugin version.');
$assert(isset($headerVersion[1]) && strpos($readmeMd, '### ' . $headerVersion[1]) !== false, 'README changelog must contain current plugin version.');

$assert(!is_file($root . '/admin.js'), 'Misplaced root admin.js must not exist.');
$assert(!is_file($root . '/manager.php'), 'Misplaced root manager.php must not exist.');

$assert(strpos($manager, "apply_filters('dca_tb_manager_capability', 'manage_options')") !== false, 'Manager capability contract changed unexpectedly.');
$assert(strpos($manager, "check_ajax_referer('dca_acf_textblock_nonce', 'nonce', false)") !== false, 'AJAX nonce verification is missing.');
$assert(strpos($manager, "wp_ajax_dca_get_acf_textblock") !== false, 'Single export AJAX route is missing.');
$assert(strpos($manager, "wp_ajax_dca_bulk_get_acf_textblocks") !== false, 'Bulk export AJAX route is missing.');
$assert(strpos($manager, "'filename' => 'content-sync-' ") !== false, 'Bulk TXT filename response contract is missing.');
$assert(strpos($manager, "if (\$post->post_type === 'page' && dca_tb_template_skip_reason(\$post_id) !== '')") !== false, 'Bulk export template restriction must apply only to pages so posts and products are not silently skipped.');

$assert(strpos($adminJs, "ajax('dca_get_acf_textblock'") !== false, 'Client single export action does not match server route.');
$assert(strpos($adminJs, "ajax('dca_bulk_get_acf_textblocks'") !== false, 'Client bulk export action does not match server route.');
$assert(strpos($adminJs, "new Blob([text], { type: 'text/plain;charset=utf-8' })") !== false, 'TXT Blob download implementation is missing.');
$assert(strpos($adminJs, 'input[type="checkbox"][name="delete_tags[]"]') !== false, 'Term selection checkbox support is missing.');
$assert(strpos($adminJs, 'input[type="checkbox"][name="post[]"]') !== false, 'Post/page selection checkbox support is missing.');
$assert(strpos($adminJs, 'tbody th.check-column input[type="checkbox"]') === false, 'Selection must not depend on a th.check-column wrapper.');
$assert(strpos($adminJs, "['dca-select-all', 'Selecteer alles', 'button']") !== false, 'Select-all toolbar action is missing.');
$assert(strpos($adminJs, 'function selectAll()') !== false, 'Select-all implementation is missing.');
$assert(strpos($adminJs, "toolbarSelectAll.addEventListener('click'") !== false, 'Select-all click handler is missing.');
$assert(strpos($adminJs, 'checkbox.checked = true;') !== false, 'Select-all action must actually select list rows.');
$assert(strpos($adminJs, "toolbarExport.addEventListener('click', () => fetchBulk()") !== false, 'Toolbar export must continue to export the current selection directly.');

$assert(preg_match('/uses:\s*actions\/checkout@[0-9a-f]{40}/', $workflow) === 1, 'Checkout action must be pinned to a full commit SHA.');
$assert(preg_match('/uses:\s*shivammathur\/setup-php@[0-9a-f]{40}/', $workflow) === 1, 'Setup PHP action must be pinned to a full commit SHA.');
$assert(strpos($workflow, 'persist-credentials: false') !== false, 'Checkout credentials must not persist in the quality job.');
$assert(strpos($workflow, 'permissions:') !== false && strpos($workflow, 'contents: read') !== false, 'Quality workflow must keep read-only repository permissions.');
$assert(strpos($workflow, 'timeout-minutes: 10') !== false, 'Quality job must have a bounded timeout.');

$forbiddenPatterns = [
    '/(^|\/)\.env($|\.)/',
    '/\.log$/',
    '/\.zip$/',
    '/(^|\/)node_modules\//',
    '/(^|\/)vendor\/bin\//',
    '/(^|\/)\.idea\//',
    '/(^|\/)\.vscode\//',
];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (strpos($relative, '.git/') === 0) {
        continue;
    }
    foreach ($forbiddenPatterns as $pattern) {
        $assert(!preg_match($pattern, $relative), "Forbidden package residue: {$relative}");
    }
}

if ($failures) {
    fwrite(STDERR, "FAILED ({$checks} checks)\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS ({$checks} checks)\n";
