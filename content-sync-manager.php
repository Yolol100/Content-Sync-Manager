<?php
/**
 * Plugin Name: Content Sync Manager
 * Plugin URI: https://webactueel.nl/
 * Description: Admin-only TXT import/export voor gedetecteerde ACF-velden, Yoast SEO en media-metadata.
 * Version: 1.2.35
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Webactueel
 * Author URI: https://webactueel.nl/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: content-sync-manager
 *
 * @package ContentSyncManager
 */

defined('ABSPATH') || exit;

if (function_exists('dca_tb_usp_fields')) {
    add_action('admin_notices', function () {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . esc_html__('Content Sync Manager is niet geladen omdat een oude snippet of plugin met dezelfde functies al actief is. Zet de oude snippet/plugin uit en herlaad WordPress.', 'content-sync-manager') . '</p></div>';
    });

    return;
}

define('DCA_TB_VERSION', '1.2.35');
define('DCA_TB_PLUGIN_FILE', __FILE__);
define('DCA_TB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DCA_TB_PLUGIN_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', function () {
    load_plugin_textdomain('content-sync-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

require_once DCA_TB_PLUGIN_DIR . 'includes/manager.php';
