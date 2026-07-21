<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds Settings/Contact/Documentation links to this plugin's own row on the
 * Plugins screen — the "Author URI" header field already makes the author
 * name a hyperlink; this covers the row action links, which WordPress
 * doesn't add automatically without a plugin being hosted on WordPress.org.
 */
class Wpci_Plugin_Links {

	public function __construct() {
		add_filter( 'plugin_action_links_' . WPCI_PLUGIN_BASENAME, array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	public function action_links( $links ) {
		$custom = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . Wpci_Admin_Menu::DASHBOARD_SLUG ) ) . '">' . esc_html__( 'Settings', 'impulse-snippets' ) . '</a>',
		);
		return array_merge( $custom, $links );
	}

	public function row_meta( $links, $file ) {
		if ( WPCI_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpci-docs' ) ) . '">' . esc_html__( 'View details', 'impulse-snippets' ) . '</a>';
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wpci-contact' ) ) . '">' . esc_html__( 'Contact', 'impulse-snippets' ) . '</a>';

		return $links;
	}
}
