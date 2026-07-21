<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the wpci_snippet post type that stores every code snippet.
 *
 * publish/draft status doubles as the on/off toggle, menu_order doubles as
 * display order — both native WP fields, no custom columns needed for them.
 */
class Wpci_Cpt {

	const POST_TYPE = 'wpci_snippet';

	// Dedicated capabilities for this post type (not aliased to manage_options
	// directly — aliasing plural caps like edit_posts breaks WordPress's own
	// list-table permission check). Granted only to the administrator role,
	// see ensure_administrator_capabilities() below.
	const CAPABILITIES = array(
		'edit_wpci_snippet',
		'read_wpci_snippet',
		'delete_wpci_snippet',
		'edit_wpci_snippets',
		'edit_others_wpci_snippets',
		'delete_wpci_snippets',
		'publish_wpci_snippets',
		'read_private_wpci_snippets',
		'delete_private_wpci_snippets',
		'delete_published_wpci_snippets',
		'delete_others_wpci_snippets',
		'edit_private_wpci_snippets',
		'edit_published_wpci_snippets',
		'create_wpci_snippets',
	);

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_filter( 'enter_title_here', array( $this, 'filter_title_placeholder' ) );
		add_action( 'admin_init', array( $this, 'ensure_administrator_capabilities' ) );
	}

	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Snippets', 'impulse-snippets' ),
			'singular_name'      => __( 'Snippet', 'impulse-snippets' ),
			'add_new'            => __( 'Add New', 'impulse-snippets' ),
			'add_new_item'       => __( 'Add New Snippet', 'impulse-snippets' ),
			'edit_item'          => __( 'Edit Snippet', 'impulse-snippets' ),
			'new_item'           => __( 'New Snippet', 'impulse-snippets' ),
			'view_item'          => __( 'View Snippet', 'impulse-snippets' ),
			'search_items'       => __( 'Search Snippets', 'impulse-snippets' ),
			'not_found'          => __( 'No snippets found. Add your first one to get started.', 'impulse-snippets' ),
			'not_found_in_trash' => __( 'No snippets found in Trash.', 'impulse-snippets' ),
			'all_items'          => __( 'All Snippets', 'impulse-snippets' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false, // Wpci_Admin_Menu builds a custom top-level menu instead.
				'show_in_rest'        => false, // Keeps the block editor out of the way; we render our own meta boxes.
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'query_var'           => false,
				'rewrite'             => false,
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => array( 'title' ), // No 'editor' (no block editor) and no revisions/autosave.
				'capability_type'     => array( 'wpci_snippet', 'wpci_snippets' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Grants this post type's capabilities to the administrator role if it
	 * doesn't already have them. Runs on admin_init (cheap after the first
	 * request, thanks to the has_cap short-circuit) rather than only on
	 * plugin activation, so it also self-heals installs that had the caps
	 * added before this fix existed, without requiring a deactivate/reactivate.
	 */
	public function ensure_administrator_capabilities() {
		$role = get_role( 'administrator' );
		if ( ! $role || $role->has_cap( 'edit_wpci_snippets' ) ) {
			return;
		}

		foreach ( self::CAPABILITIES as $cap ) {
			$role->add_cap( $cap );
		}
	}

	public function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			'_wpci_location',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( $this, 'sanitize_location' ),
				'auth_callback'     => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_wpci_code_type',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( $this, 'sanitize_code_type' ),
				'auth_callback'     => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_wpci_source',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( $this, 'sanitize_source' ),
				'auth_callback'     => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// Stores the targeting rule as one JSON blob (see Wpci_Conditions).
		// Actual field-by-field sanitizing happens in Wpci_Save_Handler
		// before this is written; sanitize_text_field here is just a
		// baseline safety net, not the primary sanitization path.
		register_post_meta(
			self::POST_TYPE,
			'_wpci_conditions',
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		// Marks a snippet as managed by the Integrations wizard (ga4 /
		// gtm_head / gtm_body / meta_pixel) so re-running the wizard updates
		// the existing snippet instead of creating a duplicate.
		register_post_meta(
			self::POST_TYPE,
			'_wpci_integration',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_key',
				'auth_callback'     => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_post_meta(
			self::POST_TYPE,
			'_wpci_integration_id',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	public function sanitize_location( $value ) {
		$allowed = array( 'head', 'body', 'footer' );
		return in_array( $value, $allowed, true ) ? $value : 'head';
	}

	public function sanitize_code_type( $value ) {
		$allowed = array( 'auto', 'script', 'style', 'html' );
		return in_array( $value, $allowed, true ) ? $value : 'auto';
	}

	public function sanitize_source( $value ) {
		$allowed = array( 'inline', 'external' );
		return in_array( $value, $allowed, true ) ? $value : 'inline';
	}

	public function filter_title_placeholder( $placeholder ) {
		$screen = get_current_screen();
		if ( $screen && self::POST_TYPE === $screen->post_type ) {
			return __( 'Snippet name (for your reference only, e.g. "Live Chat Widget")', 'impulse-snippets' );
		}
		return $placeholder;
	}
}
