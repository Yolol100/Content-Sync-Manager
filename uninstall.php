<?php
/**
 * Opruiming bij uninstall voor Content Sync Manager.
 *
 * Back-upmetadata per bericht en attachment blijft bewust staan voor rollback
 * van geïmporteerde content/media na verwijdering van de plugin. Alleen de
 * globale importlog-optie wordt verwijderd.
 *
 * @package ContentSyncManager
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('_dca_tb_last_import_log');
