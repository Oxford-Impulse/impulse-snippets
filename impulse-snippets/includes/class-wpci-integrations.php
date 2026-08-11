<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-click GA4 / GTM / Meta Pixel setup. Each generates ordinary
 * wpci_snippet posts tagged with _wpci_integration, so re-entering an ID
 * later finds and updates the same snippet(s) instead of duplicating them.
 * The generated snippets are otherwise normal — editable/removable from the
 * regular Snippets list like anything else.
 */
class Wpci_Integrations {

	const PAGE_SLUG = 'wpci-integrations';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_wpci_save_integration', array( $this, 'handle_save' ) );
		add_action( 'admin_post_wpci_remove_integration', array( $this, 'handle_remove' ) );
		add_action( 'admin_post_wpci_add_ads_conversion', array( $this, 'handle_add_ads_conversion' ) );
		add_action( 'admin_post_wpci_add_ads_purchase', array( $this, 'handle_add_ads_purchase' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_conversion_handoff_notice' ) );
	}

	/**
	 * One-time notice shown on the snippet edit screen right after the wizard
	 * creates a conversion snippet, so the user knows the two steps that make
	 * it live: choose the page(s), then Publish. Transient-based because the
	 * handoff is a redirect and query args wouldn't survive a re-save.
	 */
	public function maybe_render_conversion_handoff_notice() {
		$notice = get_transient( 'wpci_ads_conversion_notice_' . get_current_user_id() );
		if ( ! $notice ) {
			return;
		}
		delete_transient( 'wpci_ads_conversion_notice_' . get_current_user_id() );
		printf(
			'<div class="notice notice-info"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Almost done:', 'impulse-snippets' ),
			esc_html__( 'search for the page that counts as this conversion (for example your thank-you page), add it under Display Conditions, then click Publish. Until then the snippet stays a draft and fires nowhere.', 'impulse-snippets' )
		);
	}

	public function register_rest_routes() {
		register_rest_route(
			'wpci/v1',
			'/integrations/(?P<key>ga4|gtm|meta_pixel|google_ads|google_tag|consent_mode)/toggle',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_toggle' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	public function rest_toggle( $request ) {
		$integration = sanitize_key( $request['key'] );
		$lookup_key  = ( 'gtm' === $integration ) ? 'gtm_head' : $integration;
		$keys        = ( 'gtm' === $integration ) ? array( 'gtm_head', 'gtm_body' ) : array( $integration );

		$new_status = $this->is_integration_active( $lookup_key ) ? 'draft' : 'publish';

		foreach ( $keys as $key ) {
			foreach ( wpci_find_integration_post_ids( $key ) as $post_id ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => $new_status,
					)
				);
			}
		}

		return array(
			'key'    => $integration,
			'status' => $new_status,
		);
	}

	public function register_menu() {
		$hook = add_submenu_page(
			Wpci_Admin_Menu::DASHBOARD_SLUG,
			__( 'Integrations', 'impulse-snippets' ),
			__( 'Integrations', 'impulse-snippets' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		if ( $hook ) {
			add_action( 'load-' . $hook, array( $this, 'enqueue_assets' ) );
		}
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'wpci-admin', WPCI_PLUGIN_URL . 'assets/css/admin.css', array(), WPCI_VERSION );

		wp_enqueue_script(
			'wpci-integrations-toggle',
			WPCI_PLUGIN_URL . 'assets/js/admin-integrations-toggle.js',
			array( 'wp-api-fetch' ),
			WPCI_VERSION,
			true
		);

		wp_add_inline_script(
			'wpci-integrations-toggle',
			'wp.apiFetch.use( wp.apiFetch.createRootURLMiddleware( ' . wp_json_encode( esc_url_raw( rest_url() ) ) . ' ) );'
			. 'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( ' . wp_json_encode( wp_create_nonce( 'wp_rest' ) ) . ' ) );',
			'before'
		);
	}

	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Integrations', 'impulse-snippets' ); ?></h1>
			<p><?php esc_html_e( 'Paste an ID below and the correct snippet(s) are created for you automatically. Re-entering a different ID later updates the same snippet(s) instead of creating duplicates.', 'impulse-snippets' ); ?></p>

			<?php $this->maybe_render_status_notice(); ?>

			<div class="wpci-integration-cards">
				<?php
				$this->render_card(
					array(
						'key'         => 'ga4',
						'title'       => __( 'Google Analytics 4', 'impulse-snippets' ),
						'description' => __( 'Tracks visitor behavior on your site — pageviews, events, and conversions — inside Google Analytics.', 'impulse-snippets' ),
						'help'        => __( 'Find it in Google Analytics: Admin (gear icon) → Data Streams → select your web stream. The Measurement ID is shown at the top right.', 'impulse-snippets' ),
						'prefix'      => 'G-',
						'example'     => 'ABC1234XY',
						'field_name'  => 'wpci_ga4_id',
					)
				);
				$this->render_card(
					array(
						'key'         => 'gtm',
						'title'       => __( 'Google Tag Manager', 'impulse-snippets' ),
						'description' => __( 'Lets you manage multiple tracking scripts (Analytics, Ads, other pixels) from one place, without editing code every time.', 'impulse-snippets' ),
						'help'        => __( 'Find it in Google Tag Manager: your Container ID is shown next to your container name in the top-left of the workspace.', 'impulse-snippets' ),
						'prefix'      => 'GTM-',
						'example'     => 'ABC1234',
						'field_name'  => 'wpci_gtm_id',
					)
				);
				$this->render_card(
					array(
						'key'         => 'meta_pixel',
						'title'       => __( 'Meta Pixel', 'impulse-snippets' ),
						'description' => __( 'Tracks visitor actions for Facebook/Instagram ad targeting and reporting.', 'impulse-snippets' ),
						'help'        => __( 'Find it in Meta Events Manager: Data Sources → select your pixel → Settings tab.', 'impulse-snippets' ),
						'prefix'      => '',
						'example'     => '1234567890123',
						'field_name'  => 'wpci_meta_pixel_id',
						'form_extra'  => array( $this, 'render_meta_consent_checkbox' ),
					)
				);
				$this->render_card(
					array(
						'key'         => 'google_ads',
						'title'       => __( 'Google Ads', 'impulse-snippets' ),
						'description' => __( 'Measures which ad clicks lead to sales or sign-ups (conversions), and powers remarketing audiences for your campaigns.', 'impulse-snippets' ),
						'help'        => __( 'Find it in Google Ads: your Google Ads ID (starts with AW-) is shown when you create a conversion action under Goals → Conversions.', 'impulse-snippets' ),
						'prefix'      => 'AW-',
						'example'     => '123456789',
						'field_name'  => 'wpci_google_ads_id',
						'extra'       => array( $this, 'render_ads_conversions_section' ),
					)
				);
				$this->render_card(
					array(
						'key'         => 'google_tag',
						'title'       => __( 'Google tag (GT-)', 'impulse-snippets' ),
						'description' => __( 'Newer Google accounts get a single unified "Google tag" that can serve Analytics and Ads together. If Google shows you a GT- ID, connect it here.', 'impulse-snippets' ),
						'help'        => __( 'Find it in Google Analytics or Google Ads under Admin → Google tag. Note: connect EITHER this GT- tag OR the separate GA4/Google Ads IDs above — connecting both loads the same tag twice.', 'impulse-snippets' ),
						'prefix'      => 'GT-',
						'example'     => 'ABC1234',
						'field_name'  => 'wpci_google_tag_id',
					)
				);
				$this->render_consent_mode_card();
				?>
			</div>
		</div>
		<?php
	}

	private function render_card( $args ) {
		$key        = $args['key'];
		$lookup_key = ( 'gtm' === $key ) ? 'gtm_head' : $key;
		$current_id = wpci_get_integration_connected_id( $lookup_key );
		$is_active  = $this->is_integration_active( $lookup_key );
		$prefix     = $args['prefix'];
		// The stored ID always includes the prefix; the input only ever
		// shows/collects the part after it, so users can't mistype or omit
		// the fixed part.
		$current_suffix = ( $current_id && $prefix ) ? preg_replace( '/^' . preg_quote( $prefix, '/' ) . '/i', '', $current_id ) : $current_id;
		?>
		<div class="postbox wpci-integration-card">
			<h2><?php echo esc_html( $args['title'] ); ?></h2>
			<p><?php echo esc_html( $args['description'] ); ?></p>
			<p class="description"><?php echo esc_html( $args['help'] ); ?></p>

			<?php if ( $current_id && $is_active ) : ?>
				<p>
					<span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span>
					<?php
					printf(
						/* translators: %s: the connected ID. */
						esc_html__( 'Connected: %s', 'impulse-snippets' ),
						esc_html( $current_id )
					);
					?>
				</p>
			<?php elseif ( $current_id && ! $is_active ) : ?>
				<p>
					<span class="dashicons dashicons-controls-pause" style="color:#dba617;"></span>
					<?php
					printf(
						/* translators: %s: the connected ID. */
						esc_html__( 'Paused: %s', 'impulse-snippets' ),
						esc_html( $current_id )
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( $current_id && in_array( $key, array( 'ga4', 'gtm', 'google_ads', 'google_tag' ), true ) && ! wpci_get_integration_connected_id( 'consent_mode' ) ) : ?>
				<p class="description">
					<span class="dashicons dashicons-privacy" style="color:#dba617;"></span>
					<?php esc_html_e( 'Recommended for EU visitors: set up Consent Mode V2 (card below) so this tag respects cookie consent.', 'impulse-snippets' ); ?>
				</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wpci_save_integration_action', 'wpci_integration_nonce' ); ?>
				<input type="hidden" name="action" value="wpci_save_integration">
				<input type="hidden" name="wpci_integration" value="<?php echo esc_attr( $key ); ?>">
				<p>
					<label for="<?php echo esc_attr( $args['field_name'] ); ?>">
						<?php
						printf(
							/* translators: %s: example ID suffix, e.g. "ABC1234XY". */
							esc_html__( 'Example: %s', 'impulse-snippets' ),
							esc_html( $prefix . $args['example'] )
						);
						?>
					</label><br>
					<?php if ( $prefix ) : ?>
						<div style="display:flex;">
							<span style="display:inline-flex;align-items:center;padding:0 8px;background:#f0f0f1;border:1px solid #8c8f94;border-right:none;border-radius:4px 0 0 4px;font-family:monospace;"><?php echo esc_html( $prefix ); ?></span>
							<input type="text" id="<?php echo esc_attr( $args['field_name'] ); ?>" name="<?php echo esc_attr( $args['field_name'] ); ?>" value="<?php echo esc_attr( $current_suffix ); ?>" placeholder="<?php echo esc_attr( $args['example'] ); ?>" style="flex:1;border-radius:0 4px 4px 0;">
						</div>
					<?php else : ?>
						<input type="text" id="<?php echo esc_attr( $args['field_name'] ); ?>" name="<?php echo esc_attr( $args['field_name'] ); ?>" value="<?php echo esc_attr( $current_suffix ); ?>" placeholder="<?php echo esc_attr( $args['example'] ); ?>" style="width:100%;">
					<?php endif; ?>
				</p>
				<?php
				// Card-specific extra form fields (e.g. the Meta Pixel consent
				// checkbox) — rendered inside the save form so they post with it.
				if ( ! empty( $args['form_extra'] ) && is_callable( $args['form_extra'] ) ) {
					call_user_func( $args['form_extra'] );
				}
				?>
				<button type="submit" class="button button-primary">
					<?php echo $current_id ? esc_html__( 'Update', 'impulse-snippets' ) : esc_html__( 'Connect', 'impulse-snippets' ); ?>
				</button>
			</form>

			<?php if ( $current_id ) : ?>
				<p style="margin-top:8px;display:flex;align-items:center;gap:10px;">
					<label class="wpci-toggle-switch">
						<input type="checkbox" class="wpci-integration-toggle" data-integration="<?php echo esc_attr( $key ); ?>" <?php checked( $is_active ); ?>>
						<span class="wpci-toggle-slider"></span>
					</label>
					<span><?php esc_html_e( 'Active', 'impulse-snippets' ); ?></span>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-left:auto;" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this integration and its snippet(s)?', 'impulse-snippets' ) ); ?>');">
						<?php wp_nonce_field( 'wpci_remove_integration_action', 'wpci_remove_nonce' ); ?>
						<input type="hidden" name="action" value="wpci_remove_integration">
						<input type="hidden" name="wpci_integration" value="<?php echo esc_attr( $key ); ?>">
						<button type="submit" class="button-link-delete"><?php esc_html_e( 'Remove', 'impulse-snippets' ); ?></button>
					</form>
				</p>
			<?php endif; ?>

			<?php
			// Card-specific extra content (e.g. Google Ads conversion actions),
			// rendered only once the integration is connected — the extras all
			// depend on the base snippet existing.
			if ( $current_id && ! empty( $args['extra'] ) && is_callable( $args['extra'] ) ) {
				call_user_func( $args['extra'], $current_id );
			}
			?>
		</div>
		<?php
	}

	/**
	 * The Meta Pixel card's consent checkbox. Checked state is derived from
	 * the existing snippet's content (does it carry the revoke call?) — no
	 * separate stored flag to drift out of sync, and it survives
	 * export/import since it lives in the snippet itself.
	 *
	 * Unlike Google's Consent Mode there is no region scoping and no data
	 * modeling: revoked means the pixel collects nothing, worldwide, until a
	 * consent banner calls fbq('consent','grant'). Hence default off and the
	 * explicit warning.
	 */
	public function render_meta_consent_checkbox() {
		$consent_on = false;
		$existing   = wpci_find_integration_post_ids( 'meta_pixel' );
		if ( ! empty( $existing ) ) {
			$post       = get_post( $existing[0] );
			$consent_on = $post && false !== strpos( $post->post_content, "fbq('consent', 'revoke')" );
		}
		?>
		<p>
			<label>
				<input type="checkbox" name="wpci_meta_pixel_consent" value="1" <?php checked( $consent_on ); ?>>
				<?php esc_html_e( 'Wait for cookie consent before tracking', 'impulse-snippets' ); ?>
			</label><br>
			<span class="description"><?php esc_html_e( 'Recommended if you have EU/UK visitors AND a consent banner plugin — the pixel stays silent until the banner grants consent. Warning: unlike Google\'s Consent Mode this cannot be limited to EU visitors and collects nothing at all while waiting, so without a consent banner the pixel would simply never track anyone.', 'impulse-snippets' ); ?></span>
		</p>
		<?php
	}

	/**
	 * The "Conversion actions" section of the Google Ads card: lists existing
	 * conversion snippets and offers the add form. Each conversion action is
	 * an ordinary draft snippet handed off to the edit screen, where the user
	 * picks which page counts as the conversion (thank-you page etc.).
	 */
	public function render_ads_conversions_section() {
		$conversion_ids = wpci_find_integration_post_ids( 'google_ads_conversion' );
		?>
		<details class="wpci-card-section">
			<summary>
				<?php esc_html_e( 'Conversion actions', 'impulse-snippets' ); ?>
				<span class="description">
					<?php
					/* translators: %d: number of conversion actions set up. */
					echo esc_html( sprintf( _n( '(%d set up)', '(%d set up)', count( $conversion_ids ), 'impulse-snippets' ), count( $conversion_ids ) ) );
					?>
				</span>
			</summary>
		<p class="description"><?php esc_html_e( 'A conversion action fires on the page you choose — usually the thank-you page a visitor lands on after buying or submitting a form. You pick that page on the next screen.', 'impulse-snippets' ); ?></p>

		<?php if ( ! empty( $conversion_ids ) ) : ?>
			<ul style="margin:8px 0;">
				<?php foreach ( $conversion_ids as $conversion_id ) : ?>
					<li>
						<a href="<?php echo esc_url( get_edit_post_link( $conversion_id ) ); ?>"><?php echo esc_html( get_the_title( $conversion_id ) ); ?></a>
						<span class="description">
							— <?php echo ( 'publish' === get_post_status( $conversion_id ) ) ? esc_html__( 'active', 'impulse-snippets' ) : esc_html__( 'draft (no page chosen yet, or switched off)', 'impulse-snippets' ); ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wpci_add_ads_conversion_action', 'wpci_ads_conversion_nonce' ); ?>
			<input type="hidden" name="action" value="wpci_add_ads_conversion">
			<p>
				<label for="wpci_ads_conversion_label"><strong><?php esc_html_e( 'Conversion label', 'impulse-snippets' ); ?></strong></label><br>
				<input type="text" id="wpci_ads_conversion_label" name="wpci_ads_conversion_label" placeholder="AbCdEfGhIj-D2sNzQ" style="width:100%;">
				<span class="description"><?php esc_html_e( 'Google Ads shows this when you create a conversion action — it\'s the part after the slash in AW-123456789/AbCdEfGhIj.', 'impulse-snippets' ); ?></span>
			</p>
			<p style="display:flex;gap:8px;">
				<span style="flex:1;">
					<label for="wpci_ads_conversion_value"><?php esc_html_e( 'Value (optional)', 'impulse-snippets' ); ?></label><br>
					<input type="number" id="wpci_ads_conversion_value" name="wpci_ads_conversion_value" min="0" step="any" placeholder="50" style="width:100%;">
				</span>
				<span style="flex:1;">
					<label for="wpci_ads_conversion_currency"><?php esc_html_e( 'Currency', 'impulse-snippets' ); ?></label><br>
					<input type="text" id="wpci_ads_conversion_currency" name="wpci_ads_conversion_currency" maxlength="3" placeholder="EUR" style="width:100%;">
				</span>
			</p>
			<p>
				<label>
					<input type="checkbox" name="wpci_ads_conversion_enhanced" value="1">
					<?php esc_html_e( 'Also send the hashed email of logged-in visitors (enhanced conversions for leads)', 'impulse-snippets' ); ?>
				</label><br>
				<span class="description"><?php esc_html_e( 'Only a one-way SHA-256 hash is sent, never the address, and it respects Consent Mode. Applies to logged-in visitors only — logged-out form submitters are a planned future integration.', 'impulse-snippets' ); ?></span>
			</p>
			<p><button type="submit" class="button"><?php esc_html_e( 'Add conversion action', 'impulse-snippets' ); ?></button></p>
		</form>
		</details>

		<?php $this->render_woocommerce_purchase_section(); ?>
		<?php
	}

	/**
	 * WooCommerce purchase tracking, inside the Google Ads card. The
	 * woocommerce_thankyou hook does the page targeting (the order-received
	 * page is a dynamic endpoint the page picker can't target), so setup is
	 * one field: the Purchase conversion action's label. Published
	 * immediately — it can only ever fire on a real completed order.
	 */
	private function render_woocommerce_purchase_section() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			?>
			<details class="wpci-card-section">
				<summary><?php esc_html_e( 'WooCommerce purchase tracking', 'impulse-snippets' ); ?></summary>
				<p class="description"><?php esc_html_e( 'Activates automatically here when WooCommerce is installed: purchases get reported with the real order total, currency, and order number.', 'impulse-snippets' ); ?></p>
			</details>
			<?php
			return;
		}

		$purchase_ids = wpci_find_integration_post_ids( 'google_ads_purchase' );
		$purchase_id  = ! empty( $purchase_ids ) ? $purchase_ids[0] : 0;
		$enhanced_on  = $purchase_id ? (bool) get_post_meta( $purchase_id, '_wpci_ads_enhanced', true ) : false;
		$is_active    = $purchase_id && 'publish' === get_post_status( $purchase_id );
		?>
		<details class="wpci-card-section">
			<summary>
				<?php esc_html_e( 'WooCommerce purchase tracking', 'impulse-snippets' ); ?>
				<span class="description">
					<?php echo $is_active ? esc_html__( '(active)', 'impulse-snippets' ) : esc_html__( '(not set up)', 'impulse-snippets' ); ?>
				</span>
			</summary>
		<p class="description"><?php esc_html_e( 'Reports each order on the thank-you page with its real total, currency, and order number (Google deduplicates repeat visits automatically). Paste the conversion label of your "Purchase" conversion action from Google Ads.', 'impulse-snippets' ); ?></p>

		<?php if ( $purchase_id ) : ?>
			<p>
				<span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span>
				<a href="<?php echo esc_url( get_edit_post_link( $purchase_id ) ); ?>"><?php echo esc_html( get_the_title( $purchase_id ) ); ?></a>
				<span class="description">
					— <?php echo ( 'publish' === get_post_status( $purchase_id ) ) ? esc_html__( 'active', 'impulse-snippets' ) : esc_html__( 'switched off', 'impulse-snippets' ); ?>
				</span>
			</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wpci_add_ads_purchase_action', 'wpci_ads_purchase_nonce' ); ?>
			<input type="hidden" name="action" value="wpci_add_ads_purchase">
			<p>
				<label for="wpci_ads_purchase_label"><strong><?php esc_html_e( 'Purchase conversion label', 'impulse-snippets' ); ?></strong></label><br>
				<input type="text" id="wpci_ads_purchase_label" name="wpci_ads_purchase_label" placeholder="AbCdEfGhIj-D2sNzQ" style="width:100%;">
			</p>
			<p>
				<label>
					<input type="checkbox" name="wpci_ads_purchase_enhanced" value="1" <?php checked( $enhanced_on ); ?>>
					<?php esc_html_e( 'Enhanced conversions: also send the hashed billing email', 'impulse-snippets' ); ?>
				</label><br>
				<span class="description"><?php esc_html_e( 'Improves conversion matching. Only a one-way SHA-256 hash is sent, never the address, and it respects Consent Mode.', 'impulse-snippets' ); ?></span>
			</p>
			<p><button type="submit" class="button"><?php echo $purchase_id ? esc_html__( 'Update purchase tracking', 'impulse-snippets' ) : esc_html__( 'Set up purchase tracking', 'impulse-snippets' ); ?></button></p>
		</form>
		<p class="description"><?php esc_html_e( 'Tip: enhanced conversions also have an automatic mode you can simply switch on inside Google Ads (Goals → Conversions → Settings → Enhanced conversions). No extra code needed.', 'impulse-snippets' ); ?></p>
		</details>
		<?php
	}

	/**
	 * Consent Mode V2 card. Unlike the other cards there is no ID to paste —
	 * the only choice is which visitors start as "denied". The generated
	 * snippet is the consent SIGNAL Google requires; the banner that asks the
	 * visitor belongs to a CMP plugin, which also sends the 'update' call.
	 */
	private function render_consent_mode_card() {
		$preset    = wpci_get_integration_connected_id( 'consent_mode' );
		$is_active = $this->is_integration_active( 'consent_mode' );

		// The consent signal only has an effect once some Google tag is on
		// the site. It still governs manually pasted Google snippets, so this
		// informs rather than blocks — consent-first is the ideal order anyway.
		$has_google_tag = wpci_get_integration_connected_id( 'ga4' )
			|| wpci_get_integration_connected_id( 'gtm_head' )
			|| wpci_get_integration_connected_id( 'google_ads' )
			|| wpci_get_integration_connected_id( 'google_tag' );
		?>
		<div class="postbox wpci-integration-card">
			<h2><?php esc_html_e( 'Consent Mode V2', 'impulse-snippets' ); ?></h2>
			<p><?php esc_html_e( "Tells Google's tags what a visitor has consented to, before any tag runs. Google requires this for sites with EU/UK visitors — without it, remarketing audiences and conversion accuracy degrade.", 'impulse-snippets' ); ?></p>
			<p class="description"><?php esc_html_e( 'This is the consent signal, not a cookie banner. Pair it with a consent banner plugin (e.g. Complianz, Cookiebot, CookieYes) — certified banners flip the signal to "granted" automatically when the visitor accepts. Without a banner, denied visitors simply stay denied.', 'impulse-snippets' ); ?></p>

			<?php if ( ! $has_google_tag ) : ?>
				<p class="description">
					<span class="dashicons dashicons-info-outline" style="color:#dba617;"></span>
					<?php esc_html_e( "Heads up: no Google integration is connected yet, so this signal has nothing to govern for now. It's still safe to set up — it will automatically apply to any Google tag you connect above or paste as a snippet later.", 'impulse-snippets' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $preset && $is_active ) : ?>
				<p>
					<span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span>
					<?php echo ( 'all' === $preset ) ? esc_html__( 'Active: denied by default for everyone', 'impulse-snippets' ) : esc_html__( 'Active: denied by default for EU/UK visitors', 'impulse-snippets' ); ?>
				</p>
			<?php elseif ( $preset && ! $is_active ) : ?>
				<p>
					<span class="dashicons dashicons-controls-pause" style="color:#dba617;"></span>
					<?php esc_html_e( 'Paused', 'impulse-snippets' ); ?>
				</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wpci_save_integration_action', 'wpci_integration_nonce' ); ?>
				<input type="hidden" name="action" value="wpci_save_integration">
				<input type="hidden" name="wpci_integration" value="consent_mode">
				<p>
					<strong><?php esc_html_e( 'Deny tracking by default for:', 'impulse-snippets' ); ?></strong><br>
					<label>
						<input type="radio" name="wpci_consent_preset" value="eu" <?php checked( 'all' !== $preset ); ?>>
						<?php esc_html_e( 'EU/UK visitors only (recommended — visitors elsewhere keep full tracking)', 'impulse-snippets' ); ?>
					</label><br>
					<label>
						<input type="radio" name="wpci_consent_preset" value="all" <?php checked( 'all' === $preset ); ?>>
						<?php esc_html_e( 'Everyone (strictest — one global rule)', 'impulse-snippets' ); ?>
					</label>
				</p>
				<button type="submit" class="button button-primary">
					<?php echo $preset ? esc_html__( 'Update', 'impulse-snippets' ) : esc_html__( 'Set up Consent Mode', 'impulse-snippets' ); ?>
				</button>
			</form>

			<?php if ( $preset ) : ?>
				<p style="margin-top:8px;display:flex;align-items:center;gap:10px;">
					<label class="wpci-toggle-switch">
						<input type="checkbox" class="wpci-integration-toggle" data-integration="consent_mode" <?php checked( $is_active ); ?>>
						<span class="wpci-toggle-slider"></span>
					</label>
					<span><?php esc_html_e( 'Active', 'impulse-snippets' ); ?></span>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-left:auto;" onsubmit="return confirm('<?php echo esc_js( __( 'Remove this integration and its snippet(s)?', 'impulse-snippets' ) ); ?>');">
						<?php wp_nonce_field( 'wpci_remove_integration_action', 'wpci_remove_nonce' ); ?>
						<input type="hidden" name="action" value="wpci_remove_integration">
						<input type="hidden" name="wpci_integration" value="consent_mode">
						<button type="submit" class="button-link-delete"><?php esc_html_e( 'Remove', 'impulse-snippets' ); ?></button>
					</form>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	private function maybe_render_status_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display of a status flag set by our own redirect; no state changes here.
		if ( isset( $_GET['wpci_success'] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Integration saved.', 'impulse-snippets' ) );
		} elseif ( isset( $_GET['wpci_error'] ) ) {
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html__( "That ID doesn't look right for this integration — please check the format and try again.", 'impulse-snippets' ) );
		} elseif ( isset( $_GET['wpci_removed'] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Integration removed.', 'impulse-snippets' ) );
		} elseif ( isset( $_GET['wpci_ads_conv_error'] ) ) {
			$messages = array(
				'nobase'   => __( 'Connect your Google Ads ID first — conversion actions need the base tag on every page.', 'impulse-snippets' ),
				'label'    => __( "That conversion label doesn't look right — paste it exactly as Google Ads shows it (the part after the slash in AW-123456789/AbCdEfGhIj).", 'impulse-snippets' ),
				'currency' => __( 'When you set a value, enter the currency as a 3-letter code too (for example EUR or USD).', 'impulse-snippets' ),
			);
			$code     = sanitize_key( wp_unslash( $_GET['wpci_ads_conv_error'] ) );
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( isset( $messages[ $code ] ) ? $messages[ $code ] : $messages['label'] )
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'impulse-snippets' ) );
		}
		check_admin_referer( 'wpci_save_integration_action', 'wpci_integration_nonce' );

		$integration   = isset( $_POST['wpci_integration'] ) ? sanitize_key( wp_unslash( $_POST['wpci_integration'] ) ) : '';
		$redirect_args = array( 'page' => self::PAGE_SLUG );

		if ( 'ga4' === $integration ) {
			$id = $this->build_id_from_suffix( 'wpci_ga4_id', 'G-' );
			if ( ! preg_match( '/^G-[A-Z0-9]+$/i', $id ) ) {
				$redirect_args['wpci_error'] = 'ga4';
			} else {
				$this->upsert_snippet(
					'ga4',
					/* translators: %s: GA4 Measurement ID. */
					sprintf( __( 'Google Analytics 4 (%s)', 'impulse-snippets' ), $id ),
					'head',
					$this->ga4_code( $id ),
					$id
				);
				$redirect_args['wpci_success'] = 'ga4';
			}
		} elseif ( 'gtm' === $integration ) {
			$id = $this->build_id_from_suffix( 'wpci_gtm_id', 'GTM-' );
			if ( ! preg_match( '/^GTM-[A-Z0-9]+$/i', $id ) ) {
				$redirect_args['wpci_error'] = 'gtm';
			} else {
				$this->upsert_snippet(
					'gtm_head',
					/* translators: %s: GTM Container ID. */
					sprintf( __( 'Google Tag Manager – Head (%s)', 'impulse-snippets' ), $id ),
					'head',
					$this->gtm_head_code( $id ),
					$id
				);
				$this->upsert_snippet(
					'gtm_body',
					/* translators: %s: GTM Container ID. */
					sprintf( __( 'Google Tag Manager – Body (%s)', 'impulse-snippets' ), $id ),
					'body',
					$this->gtm_body_code( $id ),
					$id
				);
				$redirect_args['wpci_success'] = 'gtm';
			}
		} elseif ( 'meta_pixel' === $integration ) {
			$id      = isset( $_POST['wpci_meta_pixel_id'] ) ? sanitize_text_field( wp_unslash( $_POST['wpci_meta_pixel_id'] ) ) : '';
			$consent = ! empty( $_POST['wpci_meta_pixel_consent'] );
			if ( ! preg_match( '/^\d+$/', $id ) ) {
				$redirect_args['wpci_error'] = 'meta_pixel';
			} else {
				$this->upsert_snippet(
					'meta_pixel',
					/* translators: %s: Meta Pixel ID. */
					sprintf( __( 'Meta Pixel (%s)', 'impulse-snippets' ), $id ),
					'head',
					$this->meta_pixel_code( $id, $consent ),
					$id
				);
				$redirect_args['wpci_success'] = 'meta_pixel';
			}
		} elseif ( 'google_ads' === $integration ) {
			$id = $this->build_id_from_suffix( 'wpci_google_ads_id', 'AW-' );
			if ( ! preg_match( '/^AW-[A-Z0-9]+$/i', $id ) ) {
				$redirect_args['wpci_error'] = 'google_ads';
			} else {
				$this->upsert_snippet(
					'google_ads',
					/* translators: %s: Google Ads ID (AW-…). */
					sprintf( __( 'Google Ads (%s)', 'impulse-snippets' ), $id ),
					'head',
					$this->google_ads_code( $id ),
					$id
				);
				$redirect_args['wpci_success'] = 'google_ads';
			}
		} elseif ( 'google_tag' === $integration ) {
			$id = $this->build_id_from_suffix( 'wpci_google_tag_id', 'GT-' );
			if ( ! preg_match( '/^GT-[A-Z0-9]+$/i', $id ) ) {
				$redirect_args['wpci_error'] = 'google_tag';
			} else {
				$this->upsert_snippet(
					'google_tag',
					/* translators: %s: unified Google tag ID (GT-…). */
					sprintf( __( 'Google tag (%s)', 'impulse-snippets' ), $id ),
					'head',
					$this->google_ads_code( $id ),
					$id
				);
				$redirect_args['wpci_success'] = 'google_tag';
			}
		} elseif ( 'consent_mode' === $integration ) {
			$preset = isset( $_POST['wpci_consent_preset'] ) ? sanitize_key( wp_unslash( $_POST['wpci_consent_preset'] ) ) : 'eu';
			$preset = in_array( $preset, array( 'eu', 'all' ), true ) ? $preset : 'eu';
			$this->upsert_snippet(
				'consent_mode',
				( 'all' === $preset )
					? __( 'Consent Mode V2 (denied by default, everyone)', 'impulse-snippets' )
					: __( 'Consent Mode V2 (denied by default, EU/UK)', 'impulse-snippets' ),
				'head',
				$this->consent_mode_code( $preset ),
				$preset,
				// Must print before every Google tag: bases are 0, so -10.
				-10
			);
			$redirect_args['wpci_success'] = 'consent_mode';
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Reconstructs the full ID from the fixed prefix (shown as a locked
	 * label in the UI, not part of the input) plus whatever the user typed.
	 * Strips the prefix first in case they pasted a full ID into the field
	 * anyway, so it doesn't get doubled up.
	 */
	private function build_id_from_suffix( $post_key, $prefix ) {
		$suffix = isset( $_POST[ $post_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- only called from handle_save(), which runs check_admin_referer() first.
		$suffix = trim( $suffix );
		$suffix = preg_replace( '/^' . preg_quote( $prefix, '/' ) . '/i', '', $suffix );

		if ( '' === $suffix ) {
			return '';
		}

		return $prefix . $suffix;
	}

	private function is_integration_active( $integration_key ) {
		$ids = wpci_find_integration_post_ids( $integration_key );
		if ( empty( $ids ) ) {
			return false;
		}
		$post = get_post( $ids[0] );
		return $post && in_array( $post->post_status, array( 'publish', 'future' ), true );
	}

	/**
	 * Creates (or updates) a conversion-event snippet from the Google Ads
	 * card, then hands the user to the edit screen to pick the conversion
	 * page and publish. New conversions arrive as drafts targeting nothing,
	 * so a half-configured conversion can never fire.
	 */
	public function handle_add_ads_conversion() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'impulse-snippets' ) );
		}
		check_admin_referer( 'wpci_add_ads_conversion_action', 'wpci_ads_conversion_nonce' );

		$redirect_args = array( 'page' => self::PAGE_SLUG );

		$ads_id = wpci_get_integration_connected_id( 'google_ads' );
		if ( ! $ads_id ) {
			$redirect_args['wpci_ads_conv_error'] = 'nobase';
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;
		}

		// Accept either the bare label or a pasted full "AW-…/label" string.
		$label = isset( $_POST['wpci_ads_conversion_label'] ) ? sanitize_text_field( wp_unslash( $_POST['wpci_ads_conversion_label'] ) ) : '';
		$label = trim( preg_replace( '/^AW-[A-Z0-9]+\//i', '', trim( $label ) ) );
		if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $label ) ) {
			$redirect_args['wpci_ads_conv_error'] = 'label';
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;
		}

		// Optional fixed value. Google requires a currency whenever a value
		// is sent, so value-without-currency is refused rather than guessed.
		$value_raw = isset( $_POST['wpci_ads_conversion_value'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['wpci_ads_conversion_value'] ) ) ) : '';
		$currency  = isset( $_POST['wpci_ads_conversion_currency'] ) ? strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['wpci_ads_conversion_currency'] ) ) ) ) : '';
		$value     = ( '' !== $value_raw && is_numeric( $value_raw ) && (float) $value_raw > 0 ) ? (float) $value_raw : null;
		if ( null !== $value && ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$redirect_args['wpci_ads_conv_error'] = 'currency';
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;
		}

		$send_to = $ads_id . '/' . $label;
		$code    = $this->ads_conversion_code( $send_to, $value, ( null !== $value ) ? $currency : '' );
		/* translators: %s: Google Ads conversion label. */
		$title = sprintf( __( 'Google Ads conversion (%s)', 'impulse-snippets' ), $label );

		// Same-label re-add updates the code only — the user's chosen page,
		// status, and priority on the existing snippet must survive.
		$existing_id = 0;
		foreach ( wpci_find_integration_post_ids( 'google_ads_conversion' ) as $conversion_id ) {
			if ( get_post_meta( $conversion_id, '_wpci_integration_id', true ) === $send_to ) {
				$existing_id = $conversion_id;
				break;
			}
		}

		$enhanced = ! empty( $_POST['wpci_ads_conversion_enhanced'] );

		if ( $existing_id ) {
			wp_update_post(
				array(
					'ID'           => $existing_id,
					'post_title'   => $title,
					'post_content' => $code,
				)
			);
			update_post_meta( $existing_id, '_wpci_ads_enhanced', $enhanced ? 1 : '' );
			$post_id = $existing_id;
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type'    => Wpci_Cpt::POST_TYPE,
					'post_title'   => $title,
					'post_content' => $code,
					'post_status'  => 'draft',
					// Priority 10 guarantees the event prints after the base
					// tag (priority 0) — gtag() must already exist on the page.
					'menu_order'   => 10,
				)
			);
			if ( ! $post_id || is_wp_error( $post_id ) ) {
				$redirect_args['wpci_ads_conv_error'] = 'label';
				wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
				exit;
			}
			update_post_meta( $post_id, '_wpci_location', 'head' );
			update_post_meta( $post_id, '_wpci_code_type', 'html' );
			update_post_meta( $post_id, '_wpci_source', 'inline' );
			// 'specific' with no pages matches nowhere — fails safe until the
			// user picks the conversion page on the edit screen.
			update_post_meta(
				$post_id,
				'_wpci_conditions',
				wp_json_encode(
					array(
						'type'     => 'specific',
						'post_ids' => array(),
					)
				)
			);
			update_post_meta( $post_id, '_wpci_integration', 'google_ads_conversion' );
			update_post_meta( $post_id, '_wpci_integration_id', $send_to );
			if ( $enhanced ) {
				update_post_meta( $post_id, '_wpci_ads_enhanced', 1 );
			}
		}

		set_transient( 'wpci_ads_conversion_notice_' . get_current_user_id(), 1, 300 );
		wp_safe_redirect( get_edit_post_link( $post_id, 'url' ) );
		exit;
	}

	/**
	 * Creates/updates the WooCommerce purchase-tracking snippet. Published
	 * immediately: unlike page-based conversions there is nothing to pick —
	 * the woocommerce_thankyou hook is the targeting, and it can only fire
	 * on a real completed order. Wpci_Ads_Dynamic renders the actual event;
	 * the snippet's content is an explanatory preview.
	 */
	public function handle_add_ads_purchase() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'impulse-snippets' ) );
		}
		check_admin_referer( 'wpci_add_ads_purchase_action', 'wpci_ads_purchase_nonce' );

		$redirect_args = array( 'page' => self::PAGE_SLUG );

		$ads_id = wpci_get_integration_connected_id( 'google_ads' );
		if ( ! $ads_id ) {
			$redirect_args['wpci_ads_conv_error'] = 'nobase';
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;
		}

		$label = isset( $_POST['wpci_ads_purchase_label'] ) ? sanitize_text_field( wp_unslash( $_POST['wpci_ads_purchase_label'] ) ) : '';
		$label = trim( preg_replace( '/^AW-[A-Z0-9]+\//i', '', trim( $label ) ) );
		if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $label ) ) {
			$redirect_args['wpci_ads_conv_error'] = 'label';
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;
		}

		$enhanced = ! empty( $_POST['wpci_ads_purchase_enhanced'] );
		$send_to  = $ads_id . '/' . $label;
		/* translators: %s: Google Ads conversion label. */
		$title = sprintf( __( 'Google Ads purchase conversion (%s)', 'impulse-snippets' ), $label );

		$preview = '<!-- '
			. __( 'WooCommerce purchase tracking (managed by Impulse Snippets). This snippet is printed automatically on the WooCommerce order-received page with the real order total, currency, and order number filled in. The code below is only a preview — editing it has no effect.', 'impulse-snippets' )
			. " -->\n"
			. "<script>\ngtag('event', 'conversion', {'send_to': '" . esc_js( $send_to ) . "', 'value': ORDER_TOTAL, 'currency': 'ORDER_CURRENCY', 'transaction_id': 'ORDER_NUMBER'});\n</script>";

		$existing = wpci_find_integration_post_ids( 'google_ads_purchase' );
		if ( ! empty( $existing ) ) {
			$post_id = $existing[0];
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_title'   => $title,
					'post_content' => $preview,
					'post_status'  => 'publish',
				)
			);
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type'    => Wpci_Cpt::POST_TYPE,
					'post_title'   => $title,
					'post_content' => $preview,
					'post_status'  => 'publish',
					'menu_order'   => 10,
				)
			);
		}

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_wpci_location', 'head' );
			update_post_meta( $post_id, '_wpci_code_type', 'html' );
			update_post_meta( $post_id, '_wpci_source', 'inline' );
			// Matches nothing on purpose: the normal output loop must never
			// print this — the woocommerce_thankyou hook is its only outlet.
			update_post_meta(
				$post_id,
				'_wpci_conditions',
				wp_json_encode(
					array(
						'type'     => 'specific',
						'post_ids' => array(),
					)
				)
			);
			update_post_meta( $post_id, '_wpci_integration', 'google_ads_purchase' );
			update_post_meta( $post_id, '_wpci_integration_id', $send_to );
			update_post_meta( $post_id, '_wpci_ads_enhanced', $enhanced ? 1 : '' );
		}

		$redirect_args['wpci_success'] = 'google_ads_purchase';
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_remove() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'impulse-snippets' ) );
		}
		check_admin_referer( 'wpci_remove_integration_action', 'wpci_remove_nonce' );

		$integration = isset( $_POST['wpci_integration'] ) ? sanitize_key( wp_unslash( $_POST['wpci_integration'] ) ) : '';
		$keys        = ( 'gtm' === $integration ) ? array( 'gtm_head', 'gtm_body' ) : array( $integration );

		// Conversion events are dead weight without the base tag, and the
		// Remove button already confirms with the user first.
		if ( 'google_ads' === $integration ) {
			$keys[] = 'google_ads_conversion';
			$keys[] = 'google_ads_purchase';
		}

		foreach ( $keys as $key ) {
			foreach ( wpci_find_integration_post_ids( $key ) as $post_id ) {
				wp_trash_post( $post_id );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::PAGE_SLUG,
					'wpci_removed' => $integration,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Finds the existing snippet tagged with this integration key and
	 * updates it, or creates a new one. This is what lets re-running the
	 * wizard with a new ID replace the old snippet instead of duplicating.
	 */
	private function upsert_snippet( $integration_key, $title, $location, $code, $integration_id, $menu_order = 0 ) {
		$existing = wpci_find_integration_post_ids( $integration_key );

		if ( ! empty( $existing ) ) {
			$post_id = $existing[0];
			// menu_order is deliberately not updated here — a priority the
			// user customized on the existing snippet must survive re-runs.
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_title'   => $title,
					'post_content' => $code,
					'post_status'  => 'publish',
				)
			);
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type'    => Wpci_Cpt::POST_TYPE,
					'post_title'   => $title,
					'post_content' => $code,
					'post_status'  => 'publish',
					'menu_order'   => $menu_order,
				)
			);
		}

		update_post_meta( $post_id, '_wpci_location', $location );
		update_post_meta( $post_id, '_wpci_code_type', 'html' );
		update_post_meta( $post_id, '_wpci_source', 'inline' );
		update_post_meta( $post_id, '_wpci_conditions', wp_json_encode( array( 'type' => 'all' ) ) );
		update_post_meta( $post_id, '_wpci_integration', $integration_key );
		update_post_meta( $post_id, '_wpci_integration_id', $integration_id );

		return $post_id;
	}

	/**
	 * The Consent Mode V2 default signal (Google's "Advanced" mode: tags load
	 * but behave according to consent). Self-contained gtag stub so it works
	 * regardless of which Google snippet loads gtag.js later — what matters
	 * is that this prints first (the wizard gives it priority -10).
	 *
	 * ads_data_redaction is always on (Google's recommended privacy pairing
	 * with denied defaults). url_passthrough is deliberately not emitted — it
	 * mutates page URLs and sits in a legal gray zone.
	 */
	private function consent_mode_code( $preset ) {
		// EEA members + UK + CH (Swiss law mirrors the EU requirement).
		$eu_regions = "'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IS','IE','IT','LV','LI','LT','LU','MT','NL','NO','PL','PT','RO','SK','SI','ES','SE','GB','CH'";

		$region_line = ( 'all' === $preset ) ? '' : ",\n  'region': [" . $eu_regions . ']';

		return "<script>\nwindow.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\ngtag('consent', 'default', {\n  'ad_storage': 'denied',\n  'ad_user_data': 'denied',\n  'ad_personalization': 'denied',\n  'analytics_storage': 'denied',\n  'wait_for_update': 500{$region_line}\n});\ngtag('set', 'ads_data_redaction', true);\n</script>";
	}

	private function google_ads_code( $id ) {
		$id = esc_js( $id );
		return "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$id}\"></script>\n" // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- this string becomes the user's front-end snippet; it is not an admin asset.
			. "<script>\nwindow.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\ngtag('js', new Date());\ngtag('config', '{$id}');\n</script>";
	}

	/**
	 * The conversion-event snippet. Value/currency are the optional fixed
	 * "same value for each conversion" amounts; dynamic per-order values need
	 * shop data this plugin can't see (parked as a future WooCommerce task).
	 */
	private function ads_conversion_code( $send_to, $value, $currency ) {
		$params = "'send_to': '" . esc_js( $send_to ) . "'";
		if ( null !== $value ) {
			$params .= ", 'value': " . wp_json_encode( (float) $value ) . ", 'currency': '" . esc_js( $currency ) . "'";
		}
		return "<script>\ngtag('event', 'conversion', {" . $params . "});\n</script>";
	}

	private function ga4_code( $id ) {
		$id = esc_js( $id );
		return "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$id}\"></script>\n" // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- this string becomes the user's front-end snippet; it is not an admin asset.
			. "<script>\nwindow.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\ngtag('js', new Date());\ngtag('config', '{$id}');\n</script>";
	}

	private function gtm_head_code( $id ) {
		$id = esc_js( $id );
		return "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$id}');</script>";
	}

	private function gtm_body_code( $id ) {
		$id = rawurlencode( $id );
		return "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id={$id}\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>";
	}

	/**
	 * With $consent, fbq('consent','revoke') precedes init — the pixel queues
	 * silently until a consent banner calls fbq('consent','grant'). The
	 * <noscript> fallback image is omitted in that case: it would fire
	 * unconditionally for no-JS visitors, defeating the consent gate.
	 */
	private function meta_pixel_code( $id, $consent = false ) {
		$id_js        = esc_js( $id );
		$id_url       = rawurlencode( $id );
		$consent_line = $consent ? "fbq('consent', 'revoke');\n" : '';

		$code = "<script>\n!function(f,b,e,v,n,t,s)\n{if(f.fbq)return;n=f.fbq=function(){n.callMethod?\nn.callMethod.apply(n,arguments):n.queue.push(arguments)};\nif(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';\nn.queue=[];t=b.createElement(e);t.async=!0;\nt.src=v;s=b.getElementsByTagName(e)[0];\ns.parentNode.insertBefore(t,s)}(window, document,'script',\n'https://connect.facebook.net/en_US/fbevents.js');\n{$consent_line}fbq('init', '{$id_js}');\nfbq('track', 'PageView');\n</script>";

		if ( ! $consent ) {
			$code .= "\n<noscript><img height=\"1\" width=\"1\" style=\"display:none\" src=\"https://www.facebook.com/tr?id={$id_url}&ev=PageView&noscript=1\" /></noscript>";
		}

		return $code;
	}
}
