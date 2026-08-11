<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small stateless helpers shared across admin and output classes.
 */

function wpci_get_locations() {
	return array(
		'head'   => __( 'Head', 'impulse-snippets' ),
		'body'   => __( 'Body (after opening <body> tag)', 'impulse-snippets' ),
		'footer' => __( 'Footer', 'impulse-snippets' ),
	);
}

function wpci_get_location_label( $location ) {
	$locations = wpci_get_locations();
	return isset( $locations[ $location ] ) ? $locations[ $location ] : $location;
}

function wpci_get_code_types() {
	return array(
		'auto'   => __( 'Auto-detect', 'impulse-snippets' ),
		'script' => __( 'JavaScript', 'impulse-snippets' ),
		'style'  => __( 'CSS', 'impulse-snippets' ),
		'html'   => __( 'HTML / mixed', 'impulse-snippets' ),
	);
}

function wpci_get_code_type_label( $type ) {
	$types = wpci_get_code_types();
	return isset( $types[ $type ] ) ? $types[ $type ] : $type;
}

/**
 * Wraps raw code in the right tag at output time, unless it already looks
 * tagged. Runs on every request rather than at save time so changing a
 * snippet's type later doesn't require re-editing its code.
 */
function wpci_maybe_wrap_code( $code, $type ) {
	$code = trim( $code );
	if ( '' === $code ) {
		return '';
	}

	if ( 'html' === $type ) {
		return $code;
	}

	if ( 'script' === $type ) {
		return ( false !== stripos( $code, '<script' ) ) ? $code : '<script>' . $code . '</script>';
	}

	if ( 'style' === $type ) {
		return ( false !== stripos( $code, '<style' ) ) ? $code : '<style>' . $code . '</style>';
	}

	// 'auto': treat as markup only when the code *starts* with a real tag —
	// '<' followed by a letter (<div>, <script>), '!' (<!-- -->, <!DOCTYPE>),
	// or '/' (stray closing tag). A '<' anywhere else is almost always a
	// less-than operator in bare JS, the overwhelmingly common "I forgot the
	// <script> tags" case (e.g. for (i = 0; i < 10; i++)).
	if ( preg_match( '/^<[a-z!\/]/i', $code ) ) {
		return $code;
	}

	return '<script>' . $code . '</script>';
}

/**
 * Renders the tag for an external-file snippet: a stylesheet <link> for
 * code type 'style', a <script src="..."> for anything else (script/html/
 * auto all default to a script tag here — the overwhelmingly common
 * external-file use case is a JS library).
 */
function wpci_render_external_tag( $url, $type ) {
	$url = esc_url( trim( $url ) );
	if ( '' === $url ) {
		return '';
	}

	if ( 'style' === $type ) {
		return '<link rel="stylesheet" href="' . $url . '">'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- this builds the user's snippet for front-end output; it is the product, not an admin asset.
	}

	return '<script src="' . $url . '"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- same as above: user-configured snippet output, not an admin asset.
}

/**
 * Human-readable summary of a snippet's targeting rule, for the list table.
 */
function wpci_get_conditions_summary( $raw_conditions ) {
	$conditions = Wpci_Conditions::decode( $raw_conditions );

	switch ( $conditions['type'] ) {
		case 'specific':
			$count = ! empty( $conditions['post_ids'] ) ? count( $conditions['post_ids'] ) : 0;
			/* translators: %d: number of specific pages/posts targeted. */
			$summary = sprintf( _n( '%d specific page/post', '%d specific pages/posts', $count, 'impulse-snippets' ), $count );
			break;

		case 'post_types':
			$count = ! empty( $conditions['post_types'] ) ? count( $conditions['post_types'] ) : 0;
			/* translators: %d: number of post types targeted. */
			$summary = sprintf( _n( '%d post type', '%d post types', $count, 'impulse-snippets' ), $count );
			break;

		case 'categories':
			$count = ! empty( $conditions['term_ids'] ) ? count( $conditions['term_ids'] ) : 0;
			/* translators: %d: number of categories targeted. */
			$summary = sprintf( _n( '%d category', '%d categories', $count, 'impulse-snippets' ), $count );
			break;

		case 'special':
			$count = ! empty( $conditions['pages'] ) ? count( $conditions['pages'] ) : 0;
			/* translators: %d: number of special pages targeted (front page, 404, search). */
			$summary = sprintf( _n( '%d special page', '%d special pages', $count, 'impulse-snippets' ), $count );
			break;

		// Matches Wpci_Conditions::matches(), which suppresses output for
		// malformed data — the list must not claim "All pages" for a snippet
		// that in fact prints nowhere.
		case 'invalid':
			return __( 'Invalid targeting — output disabled', 'impulse-snippets' );

		default:
			$summary = __( 'All pages', 'impulse-snippets' );
	}

	if ( isset( $conditions['visitor'] ) && 'logged_in' === $conditions['visitor'] ) {
		$summary .= ' — ' . __( 'logged-in only', 'impulse-snippets' );
	} elseif ( isset( $conditions['visitor'] ) && 'logged_out' === $conditions['visitor'] ) {
		$summary .= ' — ' . __( 'logged-out only', 'impulse-snippets' );
	}

	return $summary;
}

/**
 * Normalizes an email the way Google's enhanced conversions expect, then
 * SHA-256 hashes it: trim, lowercase, and for gmail.com/googlemail.com
 * addresses strip the dots in the local part (Google treats them as the
 * same inbox). Returns '' for anything that isn't a plausible address, so
 * callers can simply skip emitting user_data. The raw address never leaves
 * the server — only the hash is printed.
 */
function wpci_hash_user_email( $email ) {
	$email = strtolower( trim( (string) $email ) );

	$at = strrpos( $email, '@' );
	if ( false === $at || 0 === $at || strlen( $email ) - 1 === $at ) {
		return '';
	}

	$local  = substr( $email, 0, $at );
	$domain = substr( $email, $at + 1 );

	if ( 'gmail.com' === $domain || 'googlemail.com' === $domain ) {
		$local = str_replace( '.', '', $local );
	}

	return hash( 'sha256', $local . '@' . $domain );
}

/**
 * Human-readable label for a wizard-managed snippet's _wpci_integration tag.
 */
function wpci_get_integration_label( $integration ) {
	$labels = array(
		'ga4'                   => __( 'Google Analytics 4 integration', 'impulse-snippets' ),
		'gtm_head'              => __( 'Google Tag Manager integration', 'impulse-snippets' ),
		'gtm_body'              => __( 'Google Tag Manager integration', 'impulse-snippets' ),
		'meta_pixel'            => __( 'Meta Pixel integration', 'impulse-snippets' ),
		'google_ads'            => __( 'Google Ads integration', 'impulse-snippets' ),
		'google_ads_conversion' => __( 'Google Ads conversion action', 'impulse-snippets' ),
		'google_tag'            => __( 'Google tag integration', 'impulse-snippets' ),
		'consent_mode'          => __( 'Consent Mode V2 integration', 'impulse-snippets' ),
		'google_ads_purchase'   => __( 'Google Ads purchase conversion (WooCommerce)', 'impulse-snippets' ),
	);
	return isset( $labels[ $integration ] ) ? $labels[ $integration ] : $integration;
}

/**
 * Finds the snippet ID(s) tagged with a given _wpci_integration key. Shared
 * by Wpci_Integrations (find-or-update logic) and the dashboard (status
 * display) so both read the same query instead of duplicating it.
 */
function wpci_find_integration_post_ids( $integration_key ) {
	return get_posts(
		array(
			'post_type'      => Wpci_Cpt::POST_TYPE,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			'meta_key'       => '_wpci_integration',
			'meta_value'     => $integration_key,
			'fields'         => 'ids',
		)
	);
}

/**
 * The currently-connected ID for an integration key, or '' if not connected.
 */
function wpci_get_integration_connected_id( $integration_key ) {
	$ids = wpci_find_integration_post_ids( $integration_key );
	if ( empty( $ids ) ) {
		return '';
	}
	return get_post_meta( $ids[0], '_wpci_integration_id', true );
}
