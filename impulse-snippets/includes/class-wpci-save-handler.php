<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles saving a snippet. This is the fix for the old prototype's biggest
 * gap: the prototype saved $_POST straight to options/postmeta with no
 * nonce and no explicit capability re-check. Every check here is deliberate
 * and re-verified inside the handler itself, not just relied on from the
 * screen that got the user here.
 */
class Wpci_Save_Handler {

	const HOOK = 'save_post_' . Wpci_Cpt::POST_TYPE;

	public function __construct() {
		add_action( self::HOOK, array( $this, 'save' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_unresolved_url_notice' ) );
	}

	public function save( $post_id ) {
		if ( ! isset( $_POST['wpci_snippet_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['wpci_snippet_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'wpci_save_snippet_' . $post_id ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$allowed_sources = array( 'inline', 'external' );
		$source          = isset( $_POST['wpci_code_source'] ) ? sanitize_key( wp_unslash( $_POST['wpci_code_source'] ) ) : 'inline';
		if ( ! in_array( $source, $allowed_sources, true ) ) {
			$source = 'inline';
		}

		// Both fields are saved on every submit, regardless of which source is
		// selected, so switching between "Paste code" and "External URL" never
		// destroys the other one's content (the hidden panel still submits).
		// The URL gets the same treatment as any other URL field.
		$external_url = isset( $_POST['wpci_external_url'] ) ? esc_url_raw( wp_unslash( $_POST['wpci_external_url'] ) ) : '';

		// Code content is intentionally left unmodified beyond wp_unslash
		// (undoing the quote-escaping WP applies to all $_POST data) — the
		// entire feature is outputting the user's raw code as-is. Access to
		// this save path is restricted to manage_options via the CPT's
		// capability mapping, which is the actual safeguard here.
		$code = isset( $_POST['wpci_code'] ) ? wp_unslash( $_POST['wpci_code'] ) : '';

		// Priority is stored as native menu_order — Wpci_Output already sorts
		// by it (menu_order ASC), this just gives users a way to set it.
		$priority = isset( $_POST['wpci_priority'] ) ? intval( wp_unslash( $_POST['wpci_priority'] ) ) : 0;

		// post_status is intentionally not touched here — the native
		// Publish/Draft control handles that from the edit screen, and the
		// list table's toggle switch (Wpci_Rest_Controller) handles it from
		// the snippets list.
		remove_action( self::HOOK, array( $this, 'save' ) );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $code,
				'menu_order'   => $priority,
			)
		);
		add_action( self::HOOK, array( $this, 'save' ) );

		$allowed_locations = array( 'head', 'body', 'footer' );
		$location          = isset( $_POST['wpci_location'] ) ? sanitize_key( wp_unslash( $_POST['wpci_location'] ) ) : 'head';
		if ( ! in_array( $location, $allowed_locations, true ) ) {
			$location = 'head';
		}
		update_post_meta( $post_id, '_wpci_location', $location );

		$allowed_types = array( 'auto', 'script', 'style', 'html' );
		$code_type     = isset( $_POST['wpci_code_type'] ) ? sanitize_key( wp_unslash( $_POST['wpci_code_type'] ) ) : 'auto';
		if ( ! in_array( $code_type, $allowed_types, true ) ) {
			$code_type = 'auto';
		}
		update_post_meta( $post_id, '_wpci_code_type', $code_type );

		update_post_meta( $post_id, '_wpci_source', $source );
		update_post_meta( $post_id, '_wpci_external_url', $external_url );

		update_post_meta( $post_id, '_wpci_conditions', wp_json_encode( $this->sanitize_conditions() ) );
	}

	/**
	 * Rebuilds a clean conditions array from $_POST rather than trusting
	 * posted data wholesale: the type is whitelisted, post/term IDs are
	 * cast to integers, and post type slugs are checked against the site's
	 * actual registered public post types.
	 */
	private function sanitize_conditions() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- only called from save(), which verifies the nonce and capability before reaching this.
		$type = isset( $_POST['wpci_condition_type'] ) ? sanitize_key( wp_unslash( $_POST['wpci_condition_type'] ) ) : 'all';
		if ( ! in_array( $type, Wpci_Conditions::ALLOWED_TYPES, true ) ) {
			$type = 'all';
		}

		$conditions = array( 'type' => $type );

		// The visitor gate is independent of the page rule. 'all' is the
		// default and is omitted from the stored JSON so pre-1.16.0 snippets
		// and new unrestricted ones look identical.
		$visitor = isset( $_POST['wpci_condition_visitor'] ) ? sanitize_key( wp_unslash( $_POST['wpci_condition_visitor'] ) ) : 'all';
		if ( in_array( $visitor, Wpci_Conditions::ALLOWED_VISITORS, true ) && 'all' !== $visitor ) {
			$conditions['visitor'] = $visitor;
		}

		if ( 'specific' === $type ) {
			$post_ids = ( isset( $_POST['wpci_condition_post_ids'] ) && is_array( $_POST['wpci_condition_post_ids'] ) )
				? array_map( 'absint', wp_unslash( $_POST['wpci_condition_post_ids'] ) )
				: array();

			$pasted_url = isset( $_POST['wpci_condition_post_url'] ) ? esc_url_raw( wp_unslash( $_POST['wpci_condition_post_url'] ) ) : '';
			if ( '' !== $pasted_url ) {
				$resolved_id = wpci_resolve_url_to_post_id( $pasted_url );
				if ( $resolved_id > 0 ) {
					$post_ids[] = $resolved_id;
				} else {
					set_transient(
						'wpci_url_not_found_' . get_current_user_id(),
						sprintf(
							/* translators: %s: the URL the user pasted. */
							__( 'The link you pasted (%s) didn\'t match any page or post on this site, so it wasn\'t added.', 'impulse-snippets' ),
							$pasted_url
						),
						60
					);
				}
			}

			$conditions['post_ids'] = array_values( array_unique( array_filter( $post_ids ) ) );
		} elseif ( 'post_types' === $type ) {
			$public_post_types        = array_keys( get_post_types( array( 'public' => true ) ) );
			$post_types               = ( isset( $_POST['wpci_condition_post_types'] ) && is_array( $_POST['wpci_condition_post_types'] ) )
				? array_map( 'sanitize_key', wp_unslash( $_POST['wpci_condition_post_types'] ) )
				: array();
			$conditions['post_types'] = array_values( array_intersect( $post_types, $public_post_types ) );
		} elseif ( 'categories' === $type ) {
			$term_ids               = ( isset( $_POST['wpci_condition_term_ids'] ) && is_array( $_POST['wpci_condition_term_ids'] ) )
				? array_map( 'absint', wp_unslash( $_POST['wpci_condition_term_ids'] ) )
				: array();
			$conditions['term_ids'] = array_values( array_filter( $term_ids ) );
		} elseif ( 'special' === $type ) {
			$pages               = ( isset( $_POST['wpci_condition_special'] ) && is_array( $_POST['wpci_condition_special'] ) )
				? array_map( 'sanitize_key', wp_unslash( $_POST['wpci_condition_special'] ) )
				: array();
			$conditions['pages'] = array_values( array_intersect( $pages, Wpci_Conditions::ALLOWED_SPECIAL ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $conditions;
	}

	public function maybe_show_unresolved_url_notice() {
		$key     = 'wpci_url_not_found_' . get_current_user_id();
		$message = get_transient( $key );
		if ( ! $message ) {
			return;
		}
		delete_transient( $key );
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html( $message )
		);
	}
}
