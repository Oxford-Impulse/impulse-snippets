<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates a snippet's stored targeting rule against the current request.
 *
 * Conditions are stored as one JSON blob per snippet (a tagged union: 'type'
 * plus whichever extra key that type needs, and an optional 'visitor' key
 * that applies on top of any type). A single field is simpler to
 * sanitize/version than several parallel meta keys, and at the snippet
 * counts this plugin deals with, query performance isn't a concern — the
 * per-location query in Wpci_Output already pulls a small list of published
 * snippets; this class just filters that list in PHP per request.
 */
class Wpci_Conditions {

	const ALLOWED_TYPES    = array( 'all', 'specific', 'post_types', 'categories', 'special' );
	const ALLOWED_SPECIAL  = array( 'front', '404', 'search' );
	const ALLOWED_VISITORS = array( 'all', 'logged_in', 'logged_out' );

	/**
	 * Decodes a snippet's raw _wpci_conditions meta value.
	 *
	 * - Never configured (empty/missing meta, e.g. snippets saved before
	 *   this feature existed) defaults to site-wide — that's the same
	 *   behavior those snippets already had.
	 * - Malformed/corrupted data (should not happen since this plugin is
	 *   the only writer, but defensive) decodes to type 'invalid', which
	 *   matches() below treats as fail-closed rather than fail-open.
	 */
	public static function decode( $raw ) {
		if ( empty( $raw ) ) {
			return array( 'type' => 'all' );
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded['type'] ) || ! in_array( $decoded['type'], self::ALLOWED_TYPES, true ) ) {
			return array( 'type' => 'invalid' );
		}

		// 'visitor' is optional (older snippets don't have it), but if it's
		// present it must be a known value — anything else is corruption.
		if ( isset( $decoded['visitor'] ) && ! in_array( $decoded['visitor'], self::ALLOWED_VISITORS, true ) ) {
			return array( 'type' => 'invalid' );
		}

		return $decoded;
	}

	public static function matches( $raw ) {
		$conditions = self::decode( $raw );

		// The visitor gate applies on top of whichever page rule is set.
		$visitor = isset( $conditions['visitor'] ) ? $conditions['visitor'] : 'all';
		if ( 'logged_in' === $visitor && ! is_user_logged_in() ) {
			return false;
		}
		if ( 'logged_out' === $visitor && is_user_logged_in() ) {
			return false;
		}

		switch ( $conditions['type'] ) {
			case 'all':
				return true;

			case 'specific':
				$post_ids = ( ! empty( $conditions['post_ids'] ) && is_array( $conditions['post_ids'] ) ) ? $conditions['post_ids'] : array();
				return is_singular() && in_array( get_the_ID(), $post_ids, true );

			case 'post_types':
				$post_types = ( ! empty( $conditions['post_types'] ) && is_array( $conditions['post_types'] ) ) ? $conditions['post_types'] : array();
				return ! empty( $post_types ) && is_singular( $post_types );

			case 'categories':
				$term_ids = ( ! empty( $conditions['term_ids'] ) && is_array( $conditions['term_ids'] ) ) ? $conditions['term_ids'] : array();
				return ! empty( $term_ids ) && is_singular( 'post' ) && has_category( $term_ids, get_the_ID() );

			case 'special':
				$pages = ( ! empty( $conditions['pages'] ) && is_array( $conditions['pages'] ) ) ? $conditions['pages'] : array();
				foreach ( $pages as $page ) {
					if ( 'front' === $page && is_front_page() ) {
						return true;
					}
					if ( '404' === $page && is_404() ) {
						return true;
					}
					if ( 'search' === $page && is_search() ) {
						return true;
					}
				}
				return false;

			default:
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					echo "<!-- WPCI: snippet has invalid targeting data, output suppressed -->\n";
				}
				return false;
		}
	}
}
