<?php
// Runs standalone when the plugin is deleted from the Plugins screen — the
// main plugin file and its classes are NOT loaded here, so nothing below can
// reference them (the 'wpci_snippet' post type slug is hardcoded instead).

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Off by default (see Settings page) — deleting the plugin never loses data
// unless the site owner explicitly opted in to a clean removal.
if ( ! get_option( 'wpci_remove_data_on_uninstall' ) ) {
	return;
}

$snippet_ids = get_posts(
	array(
		'post_type'      => 'wpci_snippet',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $snippet_ids as $post_id ) {
	wp_delete_post( $post_id, true );
}

delete_option( 'wpci_disable_all' );
delete_option( 'wpci_remove_data_on_uninstall' );
