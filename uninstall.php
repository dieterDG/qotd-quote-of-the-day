<?php
/**
 * Uninstall script for QOTD (Zitat des Tages).
 *
 * Runs automatically when the plugin is deleted via the WordPress admin.
 * Removes all data created by the plugin.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Remove cached quote IDs.
delete_transient('qotd_quote_ids');

// Remove all post meta entries created by the plugin.
global $wpdb;

$meta_keys = ['_qotd_text', '_qotd_author', '_qotd_extra'];

foreach ($meta_keys as $key) {
	$wpdb->delete(
		$wpdb->postmeta,
		['meta_key' => $key],
		['%s']
	);
}
