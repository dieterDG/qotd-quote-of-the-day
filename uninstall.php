<?php

/**
 * QOTD – Quote of the Day
 * Uninstall routine — removes all plugin data.
 *
 * Deletes all quote posts (CPT: qotd_quote), their associated
 * meta fields, and the transient cache. Use the export function
 * before deleting the plugin if you want to keep your quotes.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

$post_ids = get_posts([
	'post_type'      => 'qotd_quote',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'no_found_rows'  => true,
]);

foreach ($post_ids as $post_id) {
	wp_delete_post($post_id, true);
}

delete_transient('qotd_quote_ids');
