<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the top-level "Impulse Snippets" admin menu and enqueues admin assets
 * only on this plugin's own screens.
 */
class Wpci_Admin_Menu {

	const DASHBOARD_SLUG = 'wpci-dashboard';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		$dashboard_hook = add_menu_page(
			__( 'Impulse Snippets', 'impulse-snippets' ),
			__( 'Impulse Snippets', 'impulse-snippets' ),
			'manage_options',
			self::DASHBOARD_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-editor-code',
			80
		);

		if ( $dashboard_hook ) {
			add_action( 'load-' . $dashboard_hook, array( $this, 'enqueue_dashboard_assets' ) );
		}

		add_submenu_page(
			self::DASHBOARD_SLUG,
			__( 'Dashboard', 'impulse-snippets' ),
			__( 'Dashboard', 'impulse-snippets' ),
			'manage_options',
			self::DASHBOARD_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			self::DASHBOARD_SLUG,
			__( 'All Snippets', 'impulse-snippets' ),
			__( 'All Snippets', 'impulse-snippets' ),
			'manage_options',
			'edit.php?post_type=' . Wpci_Cpt::POST_TYPE
		);

		add_submenu_page(
			self::DASHBOARD_SLUG,
			__( 'Add New Snippet', 'impulse-snippets' ),
			__( 'Add New', 'impulse-snippets' ),
			'manage_options',
			'post-new.php?post_type=' . Wpci_Cpt::POST_TYPE
		);
	}

	public function enqueue_dashboard_assets() {
		wp_enqueue_style( 'wpci-admin', WPCI_PLUGIN_URL . 'assets/css/admin.css', array(), WPCI_VERSION );
	}

	public function render_dashboard() {
		$counts = wp_count_posts( Wpci_Cpt::POST_TYPE );
		$active = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$draft  = isset( $counts->draft ) ? (int) $counts->draft : 0;

		$integrations = array(
			__( 'Google Analytics 4', 'impulse-snippets' ) => wpci_get_integration_connected_id( 'ga4' ),
			__( 'Google Tag Manager', 'impulse-snippets' ) => wpci_get_integration_connected_id( 'gtm_head' ),
			__( 'Meta Pixel', 'impulse-snippets' )         => wpci_get_integration_connected_id( 'meta_pixel' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Impulse Snippets', 'impulse-snippets' ); ?></h1>
			<p><?php esc_html_e( 'Add code snippets to your site\'s head, body, or footer — manually, or with one-click integrations.', 'impulse-snippets' ); ?></p>

			<div class="wpci-dashboard-stats">
				<a class="wpci-stat-box" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Wpci_Cpt::POST_TYPE . '&post_status=publish' ) ); ?>">
					<span class="wpci-stat-number"><?php echo esc_html( $active ); ?></span>
					<span class="wpci-stat-label"><?php esc_html_e( 'Active snippets', 'impulse-snippets' ); ?></span>
				</a>
				<a class="wpci-stat-box" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Wpci_Cpt::POST_TYPE . '&post_status=draft' ) ); ?>">
					<span class="wpci-stat-number"><?php echo esc_html( $draft ); ?></span>
					<span class="wpci-stat-label"><?php esc_html_e( 'Inactive (draft) snippets', 'impulse-snippets' ); ?></span>
				</a>
			</div>

			<div class="wpci-integration-cards">
				<div class="postbox wpci-integration-card">
					<h2><?php esc_html_e( 'Snippets', 'impulse-snippets' ); ?></h2>
					<p><?php esc_html_e( 'Create and manage individual code snippets for your head, body, or footer, each with its own on/off switch and targeting rules.', 'impulse-snippets' ); ?></p>
					<div class="wpci-button-row">
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Wpci_Cpt::POST_TYPE ) ); ?>" class="button button-primary"><?php esc_html_e( 'Add New Snippet', 'impulse-snippets' ); ?></a>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Wpci_Cpt::POST_TYPE ) ); ?>" class="button"><?php esc_html_e( 'View All Snippets', 'impulse-snippets' ); ?></a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpci-settings' ) ); ?>" class="button"><?php esc_html_e( 'Settings', 'impulse-snippets' ); ?></a>
					</div>
				</div>

				<div class="postbox wpci-integration-card">
					<h2><?php esc_html_e( 'Integrations', 'impulse-snippets' ); ?></h2>
					<p><?php esc_html_e( 'One-click setup for common analytics and tracking tools.', 'impulse-snippets' ); ?></p>
					<ul style="margin:0 0 12px;list-style:none;padding:0;">
						<?php foreach ( $integrations as $label => $connected_id ) : ?>
							<li style="margin-bottom:4px;">
								<span class="dashicons <?php echo $connected_id ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" style="color:<?php echo $connected_id ? '#00a32a' : '#dcdcde'; ?>;"></span>
								<?php echo esc_html( $label ); ?>
								<?php if ( $connected_id ) : ?>
									&mdash; <?php esc_html_e( 'Connected', 'impulse-snippets' ); ?>
								<?php else : ?>
									&mdash; <?php esc_html_e( 'Not connected', 'impulse-snippets' ); ?>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpci-integrations' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Manage Integrations', 'impulse-snippets' ); ?></a>
					</p>
				</div>

				<div class="postbox wpci-integration-card">
					<h2><?php esc_html_e( 'Need help?', 'impulse-snippets' ); ?></h2>
					<p><?php esc_html_e( 'Found a bug, or have an idea for a feature? We would like to hear about it.', 'impulse-snippets' ); ?></p>
					<div class="wpci-button-row">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpci-contact#wpci-bug' ) ); ?>" class="button"><?php esc_html_e( 'Report a Bug', 'impulse-snippets' ); ?></a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpci-contact#wpci-feature' ) ); ?>" class="button"><?php esc_html_e( 'Suggest a Feature', 'impulse-snippets' ); ?></a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpci-docs' ) ); ?>" class="button"><?php esc_html_e( 'Documentation', 'impulse-snippets' ); ?></a>
					</div>
				</div>
			</div>

			<?php $this->render_feature_overview(); ?>
		</div>
		<?php
	}

	private function render_feature_overview() {
		$features = array(
			array(
				'icon'  => 'dashicons-editor-code',
				'title' => __( 'Unlimited snippets', 'impulse-snippets' ),
				'desc'  => __( 'Create as many named snippets as you need (e.g. "Live Chat Widget", "Google Analytics"). Each one has its own on/off switch, so turning one off never affects the others.', 'impulse-snippets' ),
			),
			array(
				'icon'  => 'dashicons-align-center',
				'title' => __( 'Three placements: Head, Body, Footer', 'impulse-snippets' ),
				'desc'  => __( 'Choose exactly where each snippet loads: in the page <head> (before the page renders), right after the opening <body> tag, or down in the footer.', 'impulse-snippets' ),
			),
			array(
				'icon'  => 'dashicons-media-code',
				'title' => __( 'Paste code, or link to a file', 'impulse-snippets' ),
				'desc'  => __( 'Paste JavaScript, CSS, or HTML directly into a snippet, or point it at an externally-hosted file (like a library .js or .css file) instead.', 'impulse-snippets' ),
			),
			array(
				'icon'  => 'dashicons-admin-tools',
				'title' => __( 'Auto-detect formatting', 'impulse-snippets' ),
				'desc'  => __( "If you paste bare code without <script> or <style> tags, the plugin adds the right tags for you automatically — you don't have to remember the syntax.", 'impulse-snippets' ),
			),
			array(
				'icon'  => 'dashicons-filter',
				'title' => __( 'Display conditions', 'impulse-snippets' ),
				'desc'  => __( 'Show a snippet on every page, or restrict it to specific pages/posts, post types, or categories — with a search box and a "paste a link" shortcut to find pages quickly.', 'impulse-snippets' ),
			),
			array(
				'icon'  => 'dashicons-chart-line',
				'title' => __( 'One-click integrations', 'impulse-snippets' ),
				'desc'  => __( 'Paste a Measurement/Container/Pixel ID and Google Analytics 4, Google Tag Manager, or Meta Pixel get set up correctly, automatically — no code required.', 'impulse-snippets' ),
			),
			array(
				'icon'  => 'dashicons-controls-play',
				'title' => __( 'Instant on/off', 'impulse-snippets' ),
				'desc'  => __( 'Every snippet can be switched to Draft to disable it immediately without deleting it, then switched back to Published to bring it back.', 'impulse-snippets' ),
			),
		);
		?>
		<h2><?php esc_html_e( 'What you can do', 'impulse-snippets' ); ?></h2>
		<div class="wpci-feature-grid">
			<?php foreach ( $features as $feature ) : ?>
				<div class="wpci-feature">
					<h3><span class="dashicons <?php echo esc_attr( $feature['icon'] ); ?>"></span> <?php echo esc_html( $feature['title'] ); ?></h3>
					<p><?php echo esc_html( $feature['desc'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function is_plugin_screen() {
		$screen = get_current_screen();
		return $screen && Wpci_Cpt::POST_TYPE === $screen->post_type;
	}

	public function enqueue_assets( $hook ) {
		if ( ! $this->is_plugin_screen() ) {
			return;
		}

		wp_enqueue_style(
			'wpci-admin',
			WPCI_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WPCI_VERSION
		);

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			$this->enqueue_code_editor();
		}

		if ( 'edit.php' === $hook ) {
			$this->enqueue_list_toggle_script();
		}
	}

	private function enqueue_list_toggle_script() {
		wp_enqueue_script(
			'wpci-list-toggle',
			WPCI_PLUGIN_URL . 'assets/js/admin-list-toggle.js',
			array( 'wp-api-fetch' ),
			WPCI_VERSION,
			true
		);

		// Standalone (non-block-editor) use of wp.apiFetch needs both
		// middlewares set up manually: the root URL (so relative 'path'
		// values resolve to /wp-json/...) and the REST nonce (so requests
		// are authenticated as the logged-in admin).
		wp_add_inline_script(
			'wpci-list-toggle',
			'wp.apiFetch.use( wp.apiFetch.createRootURLMiddleware( ' . wp_json_encode( esc_url_raw( rest_url() ) ) . ' ) );'
			. 'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( ' . wp_json_encode( wp_create_nonce( 'wp_rest' ) ) . ' ) );',
			'before'
		);
	}

	private function enqueue_code_editor() {
		// Snippets are fragments (a bare <meta> tag, a bare JS call, etc.),
		// not complete valid documents, so any linter would flag false
		// positives. 'codemirror' => 'lint' => false turns off the lint addon
		// entirely; we still get syntax highlighting, which
		// admin-edit-screen.js switches between javascript/css/htmlmixed
		// based on the Code type dropdown.
		$cm_settings = wp_enqueue_code_editor(
			array(
				'type'       => 'text/html',
				'codemirror' => array(
					'lint' => false,
				),
			)
		);

		wp_enqueue_script(
			'wpci-edit-screen',
			WPCI_PLUGIN_URL . 'assets/js/admin-edit-screen.js',
			array( 'jquery', 'wp-api-fetch' ),
			WPCI_VERSION,
			true
		);

		// Same standalone apiFetch middleware setup as the list toggle — the
		// edit screen's post-search picker needs the REST root and nonce.
		wp_add_inline_script(
			'wpci-edit-screen',
			'wp.apiFetch.use( wp.apiFetch.createRootURLMiddleware( ' . wp_json_encode( esc_url_raw( rest_url() ) ) . ' ) );'
			. 'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( ' . wp_json_encode( wp_create_nonce( 'wp_rest' ) ) . ' ) );',
			'before'
		);

		wp_localize_script(
			'wpci-edit-screen',
			'wpciCodeEditor',
			array( 'settings' => $cm_settings )
		);
	}
}
