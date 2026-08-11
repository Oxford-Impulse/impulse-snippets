<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dynamic-per-request Google Ads output — the pieces a static snippet can't
 * do because the values only exist at display time:
 *
 * 1. WooCommerce purchase conversions: printed on the order-received page
 *    (detected via is_order_received_page(), which covers both classic and
 *    block checkout, and simply doesn't exist when WooCommerce isn't
 *    installed), with the real order total, currency, and order number. The
 *    tagged google_ads_purchase snippet holds the configuration (send_to,
 *    enhanced flag) and gives the user list visibility and the on/off
 *    toggle; its conditions deliberately match nothing so the normal output
 *    loop never prints it — the endpoint detection IS its targeting.
 *
 * 2. Enhanced-conversion user_data for logged-in visitors: printed on
 *    wp_head at priority 0, one step before Wpci_Output's snippets (priority
 *    1), so the hashed email is always set before any conversion event that
 *    needs it. Only the SHA-256 hash is printed, never the address, and
 *    gtag itself withholds transmission when Consent Mode says denied.
 */
class Wpci_Ads_Dynamic {

	public function __construct() {
		// Deliberately NOT the woocommerce_thankyou hook: WooCommerce's
		// block-based checkout (the default since Woo 8.3) renders the order
		// confirmation without firing it. The order-received ENDPOINT is
		// identical in both classic and block checkout, so detecting the
		// page on wp_head covers both. Priority 5: after the base tag
		// snippets (1), before nothing that matters — the event queues via
		// its own stub either way.
		add_action( 'wp_head', array( $this, 'maybe_output_purchase_conversion' ), 5 );
		add_action( 'wp_head', array( $this, 'maybe_output_lead_user_data' ), 0 );

		// Form lead tracking: the listener goes in the footer (the forms it
		// watches are page content, so by then they exist), and the WPForms
		// server hook covers non-AJAX forms, where the success event never
		// reaches the browser because the page reloads.
		add_action( 'wp_footer', array( $this, 'maybe_output_form_listener' ), 20 );
		add_action( 'wpforms_process_complete', array( $this, 'remember_wpforms_lead' ), 10, 3 );
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

	public function maybe_output_purchase_conversion() {
		if ( Wpci_Settings::is_globally_disabled() ) {
			return;
		}

		// Only on WooCommerce's order-received page (classic or block
		// checkout — both use the same endpoint).
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return;
		}

		$snippet_id = $this->get_published_snippet( 'google_ads_purchase' );
		if ( ! $snippet_id ) {
			return;
		}

		$send_to  = get_post_meta( $snippet_id, '_wpci_integration_id', true );
		$order_id = absint( get_query_var( 'order-received' ) );
		$order    = ( $send_to && $order_id && function_exists( 'wc_get_order' ) ) ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			return;
		}

		// Same key check the thank-you page itself performs — the order data
		// (even non-personal) only prints for whoever holds the order link.
		$key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only comparison against the order key, exactly like WooCommerce's own thank-you template; no state changes.
		if ( ! $key || ! hash_equals( $order->get_order_key(), $key ) ) {
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

	/**
	 * The published form-tracking snippets as plain config rows for the
	 * listener: p = plugin, f = form ID, s = send_to ('' = GA4-only),
	 * v/c = optional fixed value+currency, h = hash the typed email.
	 */
	private function get_form_tracking_config() {
		$config = array();
		foreach ( wpci_find_integration_post_ids( 'form_conversion' ) as $post_id ) {
			if ( 'publish' !== get_post_status( $post_id ) ) {
				continue;
			}
			$value    = get_post_meta( $post_id, '_wpci_ads_value', true );
			$config[] = array(
				'p' => get_post_meta( $post_id, '_wpci_form_plugin', true ),
				'f' => (int) get_post_meta( $post_id, '_wpci_form_id', true ),
				's' => (string) get_post_meta( $post_id, '_wpci_integration_id', true ),
				'v' => ( '' !== $value ) ? (float) $value : 0,
				'c' => (string) get_post_meta( $post_id, '_wpci_ads_currency', true ),
				'h' => (bool) get_post_meta( $post_id, '_wpci_ads_enhanced', true ),
			);
		}
		return $config;
	}

	/**
	 * WPForms server-side fallback for non-AJAX forms: the page reloads on
	 * submit, so the browser event the listener waits for never happens.
	 * Instead this hook (which fires for AJAX submissions too) drops a
	 * short-lived cookie; the next page view's listener fires the conversion
	 * from it. For AJAX submissions the browser event handler clears the
	 * cookie in the same moment it fires — one submission, one count, in
	 * either mode.
	 */
	public function remember_wpforms_lead( $fields, $entry, $form_data ) {
		if ( Wpci_Settings::is_globally_disabled() || headers_sent() ) {
			return;
		}

		$form_id = isset( $form_data['id'] ) ? (int) $form_data['id'] : 0;
		$tracked = null;
		foreach ( $this->get_form_tracking_config() as $row ) {
			if ( 'wpforms' === $row['p'] && $row['f'] === $form_id ) {
				$tracked = $row;
				break;
			}
		}
		if ( ! $tracked ) {
			return;
		}

		// Hash server-side here — by the next page view the typed email is
		// long gone from the browser. Only the hash is ever stored.
		$hash = '';
		if ( $tracked['h'] && is_array( $fields ) ) {
			foreach ( $fields as $field ) {
				if ( isset( $field['type'], $field['value'] ) && 'email' === $field['type'] ) {
					$hash = wpci_hash_user_email( $field['value'] );
					break;
				}
			}
		}

		// NOT httponly: the listener must clear it after firing so a lead is
		// only ever counted once.
		setcookie( 'wpci_lead', $form_id . '|' . $hash, time() + 600, '/', '', is_ssl(), false );
	}

	/**
	 * Prints the submit-event listener for tracked forms, plus any pending
	 * lead remembered by the WPForms cookie fallback. Fires generate_lead
	 * always (a free GA4 lead event — inert if nothing reads the dataLayer)
	 * and the Google Ads conversion when a send_to label is configured. The
	 * enhanced-conversion email is normalized and SHA-256 hashed in the
	 * visitor's own browser (secure contexts only); the address itself is
	 * never transmitted.
	 */
	public function maybe_output_form_listener() {
		if ( Wpci_Settings::is_globally_disabled() ) {
			return;
		}

		$config = $this->get_form_tracking_config();
		if ( empty( $config ) ) {
			return;
		}

		// The cookie is validated strictly before anything from it is
		// re-emitted: integer ID that matches a tracked WPForms entry, and
		// either an empty hash or exactly 64 hex chars.
		$pending = null;
		if ( isset( $_COOKIE['wpci_lead'] ) ) {
			$parts   = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE['wpci_lead'] ) ), 2 );
			$form_id = (int) $parts[0];
			$hash    = isset( $parts[1] ) ? strtolower( $parts[1] ) : '';
			if ( preg_match( '/^([0-9a-f]{64})?$/', $hash ) ) {
				foreach ( $config as $row ) {
					if ( 'wpforms' === $row['p'] && $row['f'] === $form_id ) {
						$pending         = $row;
						$pending['hash'] = $hash;
						break;
					}
				}
			}
		}

		$payload = wp_json_encode(
			array(
				'forms'   => $config,
				'pending' => $pending,
			)
		);
		?>
<script>
(function(){
	var cfg = <?php echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() of server-built scalars only. ?>;
	window.dataLayer = window.dataLayer || [];
	function gtag(){dataLayer.push(arguments);}
	function clearCookie(){ document.cookie = 'wpci_lead=; Max-Age=0; path=/'; }
	function fire(t, hash){
		if (hash) { gtag('set', 'user_data', {'sha256_email_address': hash}); }
		var lead = {}, conv;
		if (t.v > 0) { lead.value = t.v; lead.currency = t.c; }
		gtag('event', 'generate_lead', lead);
		if (t.s) {
			conv = {'send_to': t.s};
			if (t.v > 0) { conv.value = t.v; conv.currency = t.c; }
			gtag('event', 'conversion', conv);
		}
	}
	function hashThenFire(t, email){
		if (!t.h || !email || !window.isSecureContext || !window.crypto || !crypto.subtle || !window.TextEncoder) { fire(t, ''); return; }
		email = email.trim().toLowerCase();
		var at = email.lastIndexOf('@'), local, domain;
		if (at > 0) {
			local = email.slice(0, at); domain = email.slice(at + 1);
			if (domain === 'gmail.com' || domain === 'googlemail.com') { local = local.split('.').join(''); }
			email = local + '@' + domain;
		}
		crypto.subtle.digest('SHA-256', new TextEncoder().encode(email)).then(function(buf){
			var hex = Array.prototype.map.call(new Uint8Array(buf), function(b){ return ('0' + b.toString(16)).slice(-2); }).join('');
			fire(t, hex);
		}).catch(function(){ fire(t, ''); });
	}
	function tracked(plugin, id){
		for (var i = 0; i < cfg.forms.length; i++) {
			if (cfg.forms[i].p === plugin && cfg.forms[i].f === id) { return cfg.forms[i]; }
		}
		return null;
	}
	document.addEventListener('wpcf7mailsent', function(ev){
		var t = tracked('cf7', parseInt(ev.detail && ev.detail.contactFormId, 10));
		if (!t) { return; }
		var el = ev.target && ev.target.querySelector ? ev.target.querySelector('input[type=email]') : null;
		hashThenFire(t, el ? el.value : '');
	});
	if (window.jQuery) {
		jQuery(document).on('wpformsAjaxSubmitSuccess', function(ev){
			clearCookie();
			var form = ev.target, idInput = form && form.querySelector ? form.querySelector('input[name="wpforms[id]"]') : null;
			var t = tracked('wpforms', idInput ? parseInt(idInput.value, 10) : 0);
			if (!t) { return; }
			var el = form.querySelector('input[type=email]');
			hashThenFire(t, el ? el.value : '');
		});
	}
	if (cfg.pending) { fire(cfg.pending, cfg.pending.hash || ''); clearCookie(); }
})();
</script>
		<?php
	}
}
