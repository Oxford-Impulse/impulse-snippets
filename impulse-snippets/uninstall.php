<?php
// Runs standalone when the plugin is deleted from the Plugins screen — the
// main plugin file and its classes are NOT loaded here, so nothing below can
// reference them (the 'wpci_snippet' post type slug is hardcoded instead).

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Removes one site's snippets and options — but only if that site opted in.
 * Off by default (see Settings page), so deleting the plugin never loses
 * data unless the site owner explicitly chose a clean removal.
 */
function wpci_uninstall_current_site() {
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
}

// On multisite, a network-wide delete must clean every site, each honoring
// its own opt-in; a single-site install just cleans itself.
if ( is_multisite() ) {
	// 'number' => 0 disables get_sites()' default 100-site cap.
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $wpci_site_id ) {
		switch_to_blog( $wpci_site_id );
		wpci_uninstall_current_site();
		restore_current_blog();
	}
} else {
	wpci_uninstall_current_site();
}
