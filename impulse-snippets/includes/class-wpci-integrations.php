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
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	public function register_rest_routes() {
		register_rest_route(
			'wpci/v1',
			'/integrations/(?P<key>ga4|gtm|meta_pixel)/toggle',
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
					)
				);
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
			$id = isset( $_POST['wpci_meta_pixel_id'] ) ? sanitize_text_field( wp_unslash( $_POST['wpci_meta_pixel_id'] ) ) : '';
			if ( ! preg_match( '/^\d+$/', $id ) ) {
				$redirect_args['wpci_error'] = 'meta_pixel';
			} else {
				$this->upsert_snippet(
					'meta_pixel',
					/* translators: %s: Meta Pixel ID. */
					sprintf( __( 'Meta Pixel (%s)', 'impulse-snippets' ), $id ),
					'head',
					$this->meta_pixel_code( $id ),
					$id
				);
				$redirect_args['wpci_success'] = 'meta_pixel';
			}
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

	public function handle_remove() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'impulse-snippets' ) );
		}
		check_admin_referer( 'wpci_remove_integration_action', 'wpci_remove_nonce' );

		$integration = isset( $_POST['wpci_integration'] ) ? sanitize_key( wp_unslash( $_POST['wpci_integration'] ) ) : '';
		$keys        = ( 'gtm' === $integration ) ? array( 'gtm_head', 'gtm_body' ) : array( $integration );

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
	private function upsert_snippet( $integration_key, $title, $location, $code, $integration_id ) {
		$existing = wpci_find_integration_post_ids( $integration_key );

		if ( ! empty( $existing ) ) {
			$post_id = $existing[0];
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

	private function meta_pixel_code( $id ) {
		$id_js  = esc_js( $id );
		$id_url = rawurlencode( $id );
		return "<script>\n!function(f,b,e,v,n,t,s)\n{if(f.fbq)return;n=f.fbq=function(){n.callMethod?\nn.callMethod.apply(n,arguments):n.queue.push(arguments)};\nif(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';\nn.queue=[];t=b.createElement(e);t.async=!0;\nt.src=v;s=b.getElementsByTagName(e)[0];\ns.parentNode.insertBefore(t,s)}(window, document,'script',\n'https://connect.facebook.net/en_US/fbevents.js');\nfbq('init', '{$id_js}');\nfbq('track', 'PageView');\n</script>\n"
			. "<noscript><img height=\"1\" width=\"1\" style=\"display:none\" src=\"https://www.facebook.com/tr?id={$id_url}&ev=PageView&noscript=1\" /></noscript>";
	}
}
