<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The "Check my setup" overlay: an admin-only panel rendered on the front
 * end (opened from the Integrations page with a nonced link) that verifies
 * each connected integration actually printed its code on the page the
 * visitor sees — the real failure mode of tracking setups. Detection runs in
 * the browser against what genuinely loaded (dataLayer entries, fbq, the
 * form-listener marker), so a broken theme, a caching plugin, or the kill
 * switch all show up honestly.
 *
 * It also offers an optional "send a test conversion" button, gated behind a
 * clear warning: Google counts test conversions in real campaign statistics
 * and provides no way to delete individual ones.
 */
class Wpci_Setup_Check {

	const NONCE_ACTION = 'wpci_setup_check';

	public function __construct() {
		// Very late in the footer so every tag and the form listener have
		// already printed by the time the overlay inspects the page.
		add_action( 'wp_footer', array( $this, 'maybe_render_overlay' ), 9999 );
	}

	/**
	 * The nonced front-end URL the Integrations page button opens.
	 */
	public static function get_check_url() {
		return add_query_arg( 'wpci_check', rawurlencode( wp_create_nonce( self::NONCE_ACTION ) ), home_url( '/' ) );
	}

	public function maybe_render_overlay() {
		if ( ! isset( $_GET['wpci_check'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wpci_check'] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		// Every check the overlay should run, based on what is connected.
		// type tells the browser-side detector what to look for.
		$checks = array();
		if ( wpci_get_integration_connected_id( 'consent_mode' ) ) {
			$checks[] = array(
				'label' => __( 'Consent Mode V2 (must print before Google tags)', 'impulse-snippets' ),
				'type'  => 'consent',
			);
		}
		foreach ( array(
			'ga4'        => array( 'G-', __( 'Google Analytics 4', 'impulse-snippets' ) ),
			'google_ads' => array( 'AW-', __( 'Google Ads', 'impulse-snippets' ) ),
			'google_tag' => array( 'GT-', __( 'Google tag', 'impulse-snippets' ) ),
		) as $key => $def ) {
			if ( wpci_get_integration_connected_id( $key ) ) {
				$checks[] = array(
					'label'  => $def[1],
					'type'   => 'config',
					'prefix' => $def[0],
				);
			}
		}
		if ( wpci_get_integration_connected_id( 'gtm_head' ) ) {
			$checks[] = array(
				'label' => __( 'Google Tag Manager', 'impulse-snippets' ),
				'type'  => 'gtm',
			);
		}
		if ( wpci_get_integration_connected_id( 'meta_pixel' ) ) {
			$checks[] = array(
				'label' => __( 'Meta Pixel', 'impulse-snippets' ),
				'type'  => 'fbq',
			);
		}

		$tracked_forms = 0;
		foreach ( wpci_find_integration_post_ids( 'form_conversion' ) as $post_id ) {
			if ( 'publish' === get_post_status( $post_id ) ) {
				++$tracked_forms;
			}
		}
		if ( $tracked_forms ) {
			$checks[] = array(
				'label' => __( 'Form lead tracking listener', 'impulse-snippets' ),
				'type'  => 'forms',
				'count' => $tracked_forms,
			);
		}

		// Conversion targets the optional test-fire can send to. Published
		// snippets only, and only ones that actually have an Ads label.
		$targets = array();
		foreach ( array( 'google_ads_conversion', 'google_ads_purchase', 'form_conversion' ) as $key ) {
			foreach ( wpci_find_integration_post_ids( $key ) as $post_id ) {
				$send_to = get_post_meta( $post_id, '_wpci_integration_id', true );
				if ( $send_to && 'publish' === get_post_status( $post_id ) ) {
					$targets[ $send_to ] = get_the_title( $post_id );
				}
			}
		}

		$payload = wp_json_encode(
			array(
				'disabled' => Wpci_Settings::is_globally_disabled(),
				'checks'   => $checks,
				'targets'  => $targets,
				'i18n'     => array(
					'title'      => __( 'Impulse Snippets — setup check', 'impulse-snippets' ),
					'printed'    => __( 'printed on this page', 'impulse-snippets' ),
					'missing'    => __( 'NOT found on this page', 'impulse-snippets' ),
					'disabled'   => __( 'The emergency kill switch is ON (Settings) — every snippet is paused, so nothing below can print.', 'impulse-snippets' ),
					'none'       => __( 'No integrations are connected yet — nothing to check.', 'impulse-snippets' ),
					'blockers'   => __( 'A "NOT found" can also be caused by your own ad blocker — retry in a private window with the blocker off before changing anything.', 'impulse-snippets' ),
					'purchase'   => __( 'WooCommerce purchase tracking only prints on the order-received page, so it is not checked here.', 'impulse-snippets' ),
					'recheck'    => __( 'Re-check', 'impulse-snippets' ),
					'close'      => __( 'Close', 'impulse-snippets' ),
					'testTitle'  => __( 'Send a test conversion', 'impulse-snippets' ),
					'testWarn'   => __( 'Warning: Google counts this in your REAL campaign statistics and individual conversions cannot be deleted. Use once, only if you must prove the full pipeline.', 'impulse-snippets' ),
					'testButton' => __( 'Send test conversion', 'impulse-snippets' ),
					'testSent'   => __( 'Sent. It can take 3–24 hours to appear in Google Ads.', 'impulse-snippets' ),
				),
			)
		);
		?>
<style>
#wpci-setup-check{position:fixed;top:20px;right:20px;z-index:999999;width:360px;max-width:calc(100vw - 40px);max-height:calc(100vh - 40px);overflow-y:auto;background:#fff;color:#1d2327;border:1px solid #c3c4c7;border-radius:4px;box-shadow:0 4px 16px rgba(0,0,0,.25);font:13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;padding:14px 16px;text-align:left}
#wpci-setup-check h2{margin:0 0 10px;font-size:14px}
#wpci-setup-check ul{margin:0 0 10px;padding:0;list-style:none}
#wpci-setup-check li{margin:0 0 6px;padding:0}
#wpci-setup-check .wpci-ok{color:#00a32a}
#wpci-setup-check .wpci-bad{color:#b32d2e}
#wpci-setup-check .wpci-note{color:#646970;font-size:12px;margin:0 0 8px}
#wpci-setup-check .wpci-warnbox{background:#fcf0f1;border:1px solid #b32d2e;border-radius:3px;padding:8px 10px;margin:0 0 8px;font-size:12px}
#wpci-setup-check button,#wpci-setup-check select{font:inherit;margin:0 6px 6px 0}
</style>
<div id="wpci-setup-check"></div>
<script>
(function(){
	var data = <?php echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() of server-built, translated strings and scalars. ?>;
	var box = document.getElementById('wpci-setup-check');
	function esc(s){ var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
	function detect(check){
		var dl = window.dataLayer || [], i, e;
		if (check.type === 'gtm') { return !!window.google_tag_manager; }
		if (check.type === 'fbq') { return typeof window.fbq === 'function'; }
		if (check.type === 'forms') { return (window.wpciFormsTracked || 0) >= check.count; }
		for (i = 0; i < dl.length; i++) {
			e = dl[i];
			if (!e || typeof e[0] === 'undefined') { continue; }
			if (check.type === 'consent' && e[0] === 'consent' && e[1] === 'default') { return true; }
			if (check.type === 'config' && e[0] === 'config' && String(e[1]).indexOf(check.prefix) === 0) { return true; }
		}
		return false;
	}
	function render(){
		var html = '<h2>' + esc(data.i18n.title) + '</h2>', anyMissing = false, sendTos = [];
		if (data.disabled) {
			html += '<div class="wpci-warnbox">' + esc(data.i18n.disabled) + '</div>';
		} else if (!data.checks.length) {
			html += '<p class="wpci-note">' + esc(data.i18n.none) + '</p>';
		} else {
			html += '<ul>';
			data.checks.forEach(function(check){
				var ok = detect(check);
				if (!ok) { anyMissing = true; }
				html += '<li><span class="' + (ok ? 'wpci-ok' : 'wpci-bad') + '">' + (ok ? '✓' : '✗') + '</span> '
					+ esc(check.label) + ' — ' + esc(ok ? data.i18n.printed : data.i18n.missing) + '</li>';
			});
			html += '</ul>';
			if (anyMissing) { html += '<p class="wpci-note">' + esc(data.i18n.blockers) + '</p>'; }
			html += '<p class="wpci-note">' + esc(data.i18n.purchase) + '</p>';
		}
		for (var k in data.targets) { if (Object.prototype.hasOwnProperty.call(data.targets, k)) { sendTos.push(k); } }
		if (!data.disabled && sendTos.length) {
			html += '<h2>' + esc(data.i18n.testTitle) + '</h2>'
				+ '<div class="wpci-warnbox">' + esc(data.i18n.testWarn) + '</div>'
				+ '<select id="wpci-test-target">';
			sendTos.forEach(function(s){ html += '<option value="' + esc(s) + '">' + esc(data.targets[s]) + '</option>'; });
			html += '</select><br><button type="button" id="wpci-test-fire">' + esc(data.i18n.testButton) + '</button>'
				+ '<span id="wpci-test-result" class="wpci-note"></span>';
		}
		html += '<p><button type="button" id="wpci-recheck">' + esc(data.i18n.recheck) + '</button>'
			+ '<button type="button" id="wpci-close">' + esc(data.i18n.close) + '</button></p>';
		box.innerHTML = html;
		document.getElementById('wpci-recheck').onclick = render;
		document.getElementById('wpci-close').onclick = function(){ box.parentNode.removeChild(box); };
		var fireBtn = document.getElementById('wpci-test-fire');
		if (fireBtn) {
			fireBtn.onclick = function(){
				window.dataLayer = window.dataLayer || [];
				function gtag(){ dataLayer.push(arguments); }
				gtag('event', 'conversion', {'send_to': document.getElementById('wpci-test-target').value});
				document.getElementById('wpci-test-result').textContent = ' ' + data.i18n.testSent;
				fireBtn.disabled = true;
			};
		}
	}
	// Give gtm.js/fbevents a moment to finish loading before the first pass;
	// the Re-check button covers slow networks.
	window.setTimeout(render, 800);
})();
</script>
		<?php
	}
}
