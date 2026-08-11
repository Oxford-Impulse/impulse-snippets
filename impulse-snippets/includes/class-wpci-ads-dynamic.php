<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dynamic-per-request Google Ads output — the pieces a static snippet can't
 * do because the values only exist at display time:
 *
 * 1. WooCommerce purchase conversions: printed on the order-received page
 *    via the woocommerce_thankyou hook (which simply never fires when
 *    WooCommerce isn't installed, so no detection is needed here), with the
 *    real order total, currency, and order number. The tagged
 *    google_ads_purchase snippet holds the configuration (send_to, enhanced
 *    flag) and gives the user list visibility and the on/off toggle; its
 *    conditions deliberately match nothing so the normal output loop never
 *    prints it — this hook IS its targeting.
 *
 * 2. Enhanced-conversion user_data for logged-in visitors: printed on
 *    wp_head at priority 0, one step before Wpci_Output's snippets (priority
 *    1), so the hashed email is always set before any conversion event that
 *    needs it. Only the SHA-256 hash is printed, never the address, and
 *    gtag itself withholds transmission when Consent Mode says denied.
 */
class Wpci_Ads_Dynamic {

	public function __construct() {
		add_action( 'woocommerce_thankyou', array( $this, 'output_purchase_conversion' ), 10, 1 );
		add_action( 'wp_head', array( $this, 'maybe_output_lead_user_data' ), 0 );
	}

	/**
	 * Finds the single published snippet for an integration key, or null.
	 * (wpci_find_integration_post_ids also returns drafts — draft here means
	 * the user switched the feature off, so it must be respected.)
	 */
	private function get_published_snippet( $integration_key ) {
		foreach ( wpci_find_integration_post_ids( $integration_key ) as $post_id ) {
			if ( 'publish' === get_post_status( $post_id ) ) {
				return $post_id;
			}
		}
		return null;
	}

	public function output_purchase_conversion( $order_id ) {
		if ( Wpci_Settings::is_globally_disabled() ) {
			return;
		}

		$snippet_id = $this->get_published_snippet( 'google_ads_purchase' );
		if ( ! $snippet_id ) {
			return;
		}

		$send_to = get_post_meta( $snippet_id, '_wpci_integration_id', true );
		$order   = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( ! $send_to || ! $order ) {
			return;
		}

		$user_data_line = '';
		if ( get_post_meta( $snippet_id, '_wpci_ads_enhanced', true ) ) {
			$hash = wpci_hash_user_email( $order->get_billing_email() );
			if ( '' !== $hash ) {
				$user_data_line = "gtag('set', 'user_data', {'sha256_email_address': '" . esc_js( $hash ) . "'});\n";
			}
		}

		// transaction_id lets Google deduplicate: revisiting the thank-you
		// page re-runs this hook, but the conversion is only counted once.
		printf(
			"<script>\nwindow.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\n%sgtag('event', 'conversion', {'send_to': '%s', 'value': %s, 'currency': '%s', 'transaction_id': '%s'});\n</script>\n",
			$user_data_line, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from an esc_js()'d hash only.
			esc_js( $send_to ),
			esc_js( wp_json_encode( (float) $order->get_total() ) ),
			esc_js( $order->get_currency() ),
			esc_js( $order->get_order_number() )
		);
	}

	/**
	 * If any conversion snippet that will print on this request has the
	 * enhanced flag and the visitor is logged in, set their hashed email
	 * once, ahead of every conversion event.
	 */
	public function maybe_output_lead_user_data() {
		if ( Wpci_Settings::is_globally_disabled() || ! is_user_logged_in() ) {
			return;
		}

		$needs_user_data = false;
		foreach ( wpci_find_integration_post_ids( 'google_ads_conversion' ) as $post_id ) {
			if ( 'publish' !== get_post_status( $post_id ) || ! get_post_meta( $post_id, '_wpci_ads_enhanced', true ) ) {
				continue;
			}
			if ( Wpci_Conditions::matches( get_post_meta( $post_id, '_wpci_conditions', true ) ) ) {
				$needs_user_data = true;
				break;
			}
		}

		if ( ! $needs_user_data ) {
			return;
		}

		$hash = wpci_hash_user_email( wp_get_current_user()->user_email );
		if ( '' === $hash ) {
			return;
		}

		printf(
			"<script>\nwindow.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\ngtag('set', 'user_data', {'sha256_email_address': '%s'});\n</script>\n",
			esc_js( $hash )
		);
	}
}
