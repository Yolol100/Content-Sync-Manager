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
$composerJson = $read('composer.json');
$read('composer.lock');
$phpcsConfig = $read('phpcs.xml.dist');
$workflow = $read('.github/workflows/quality.yml');
$runtimeWorkflow = $read('.github/workflows/runtime-release-gate.yml');
$releaseWorkflow = $read('.github/workflows/release.yml');
$gitignore = $read('.gitignore');
$read('tests/runtime-smoke.php');
$debugLogCheck = $read('scripts/check_runtime_debug_log.py');
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
$assert(strpos($adminJs, "['dca-select-all', __('Selecteer alles', 'content-sync-manager'), 'button']") !== false, 'Select-all toolbar action is missing or is not localized.');
$assert(strpos($adminJs, 'function selectAll()') !== false, 'Select-all implementation is missing.');
$assert(strpos($adminJs, "toolbarSelectAll.addEventListener('click'") !== false, 'Select-all click handler is missing.');
$assert(strpos($adminJs, 'checkbox.checked = true;') !== false, 'Select-all action must actually select list rows.');
$assert(strpos($adminJs, "toolbarExport.addEventListener('click', () => fetchBulk()") !== false, 'Toolbar export must continue to export the current selection directly.');

$assert(preg_match('/uses:\s*actions\/checkout@[0-9a-f]{40}/', $workflow) === 1, 'Checkout action must be pinned to a full commit SHA.');
$assert(preg_match('/uses:\s*shivammathur\/setup-php@[0-9a-f]{40}/', $workflow) === 1, 'Setup PHP action must be pinned to a full commit SHA.');
$assert(strpos($workflow, 'persist-credentials: false') !== false, 'Checkout credentials must not persist in the quality job.');
$assert(strpos($workflow, 'permissions:') !== false && strpos($workflow, 'contents: read') !== false, 'Quality workflow must keep read-only repository permissions.');
$assert(strpos($workflow, 'timeout-minutes: 10') !== false, 'Quality job must have a bounded timeout.');
$assert(preg_match('/uses:\s*actions\/checkout@[0-9a-f]{40}/', $runtimeWorkflow) === 1, 'Runtime checkout action must be pinned to a full commit SHA.');
$assert(preg_match('/uses:\s*shivammathur\/setup-php@[0-9a-f]{40}/', $runtimeWorkflow) === 1, 'Runtime PHP action must be pinned to a full commit SHA.');
$assert(strpos($runtimeWorkflow, 'permissions:') !== false && strpos($runtimeWorkflow, 'contents: read') !== false, 'Runtime workflow must keep read-only repository permissions.');
$assert(strpos($runtimeWorkflow, 'timeout-minutes: 20') !== false, 'Runtime job must have a bounded timeout.');
$assert(strpos($runtimeWorkflow, 'ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c') !== false, 'WP-CLI download must retain its verified SHA-256.');
$assert(strpos($runtimeWorkflow, 'WordPress 6.2.11') !== false && strpos($runtimeWorkflow, 'WordPress 7.0.4') !== false && strpos($runtimeWorkflow, 'WordPress 7.1 - PHP 8.3') !== false && strpos($runtimeWorkflow, 'WordPress 7.1 - PHP 8.5') !== false, 'Runtime workflow must cover the minimum, existing ecosystem and WordPress 7.1 matrices.');
$assert(strpos($runtimeWorkflow, '--version=2.1.0') !== false && strpos($runtimeWorkflow, 'plugin check content-sync-manager') !== false, 'Official Plugin Check gate is missing or unpinned.');
$assert(strpos($workflow, 'wordpress-best-practices:') !== false && strpos($workflow, 'composer audit --locked') !== false && strpos($workflow, 'vendor/bin/phpcs --standard=phpcs.xml.dist') !== false, 'WPCS/Composer best-practice gate is missing.');
$assert(strpos($composerJson, '"wp-coding-standards/wpcs": "3.4.1"') !== false && strpos($composerJson, '"dealerdirect/phpcodesniffer-composer-installer": "1.2.1"') !== false, 'WPCS tooling versions are not pinned to the audited versions.');
$assert(strpos($phpcsConfig, 'WordPress.Security.NonceVerification') !== false && strpos($phpcsConfig, 'WordPress.Security.EscapeOutput') !== false, 'Release-critical WPCS rules are missing.');
$assert(strpos($manager, "['wp-i18n']") !== false && strpos($manager, "wp_set_script_translations('dca-tb-admin', 'content-sync-manager')") !== false, 'Admin JavaScript must load through WordPress i18n.');
$assert(strpos($adminJs, 'window.wp.i18n') !== false && strpos($adminJs, "__('Kopieer selectie', 'content-sync-manager')") !== false, 'Admin JavaScript user-facing strings must use wp-i18n.');
$assert(strpos($manager, 'role="dialog"') !== false && strpos($manager, 'aria-live="polite"') !== false && strpos($adminJs, "event.key === 'Tab'") !== false && strpos($adminJs, 'lastFocusedBeforeModal.focus()') !== false, 'Dialog semantics, live status and keyboard focus regression contract is incomplete.');
$assert(strpos($runtimeWorkflow, 'scripts/check_runtime_debug_log.py') !== false, 'Runtime workflow must reject unexpected WordPress debug output.');
$assert(
    strpos($debugLogCheck, '"<code>woocommerce</code>"') !== false
    && strpos($debugLogCheck, '"triggered too early"') !== false
    && strpos($debugLogCheck, 'len(allowed_woocommerce) > 1') !== false
    && strpos($debugLogCheck, '"Using null as an array offset is deprecated, use an empty string instead"') !== false
    && strpos($debugLogCheck, '"wp-cli.phar/vendor/wp-cli/php-cli-tools/lib/cli/Colors.php on line 95"') !== false
    && strpos($debugLogCheck, 'else:\n        unexpected.append(line)') !== false,
    'Debug-log allowlist must stay fail-closed and limited to the documented WooCommerce notice plus the exact WP-CLI 2.12.0 PHP 8.5 deprecation.'
);
$assert(strpos($releaseWorkflow, 'needs: runtime-gate') !== false, 'Draft releases must wait for the clean runtime gate.');
$assert(preg_match('/^\/dist\/$/m', $gitignore) === 1, 'Generated release artifacts must remain ignored under dist/.');

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
    if (strpos($relative, '.git/') === 0 || strpos($relative, 'dist/') === 0) {
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
