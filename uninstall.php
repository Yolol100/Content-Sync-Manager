<?php
/**
 * Uninstall cleanup for Content Sync Manager.
 *
 * Keeps per-post and per-attachment backup metadata intentionally, because it can
 * be needed to roll back imported content/media after plugin removal. Only the
 * global import log option is removed.
 *
 * @package ContentSyncManager
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('_dca_tb_last_import_log');
