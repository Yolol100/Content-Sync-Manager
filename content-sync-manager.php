<?php
/**
 * Plugin Name: Content Sync Manager
 * Description: Admin-only TXT import/export voor content, ACF-velden, samenvattingen, uitgelichte afbeeldingen en media-metadata.
 * Version: 1.2.63
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Webactueel
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: content-sync-manager
 * Update URI: false
 *
 * @package ContentSyncManager
 */

defined('ABSPATH') || exit;

if (function_exists('dca_tb_usp_fields') || function_exists('dca_tb_supported_post_types')) {
    add_action('admin_notices', static function () {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . esc_html('Content Sync Manager is niet geladen omdat een oude snippet of pluginversie met dezelfde functies al actief is. Zet die oude versie eerst uit en activeer deze plugin daarna opnieuw.') . '</p></div>';
    });

    return;
}

define('DCA_TB_VERSION', '1.2.63');
define('DCA_TB_PLUGIN_FILE', __FILE__);
define('DCA_TB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DCA_TB_PLUGIN_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', static function () {
    load_plugin_textdomain('content-sync-manager', false, dirname(plugin_basename(DCA_TB_PLUGIN_FILE)) . '/languages');
});

require_once DCA_TB_PLUGIN_DIR . 'includes/manager.php';
require_once DCA_TB_PLUGIN_DIR . 'includes/ai-image-context.php';
require_once DCA_TB_PLUGIN_DIR . 'includes/ai-media-index.php';

/*
 * Keep the client-side manager and the server-rendered modals on the same
 * capability boundary. Without this guard a lower-privilege user could receive
 * the JavaScript toolbar while dca_tb_render_admin_modals() correctly withheld
 * the modal markup, causing the script to abort with visible but inert buttons.
 */
add_action('admin_enqueue_scripts', static function () {
    if (function_exists('dca_tb_current_user_can_use_manager') && !dca_tb_current_user_can_use_manager()) {
        remove_action('admin_enqueue_scripts', 'dca_tb_enqueue_admin_assets');
        remove_action('admin_enqueue_scripts', 'dca_tb_enqueue_ai_image_context_assets', 20);
    }
}, 0);
