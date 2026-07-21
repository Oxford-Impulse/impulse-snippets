<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page: a site-wide "pause everything" kill switch, and an opt-in
 * for whether uninstalling the plugin should delete its data (see
 * uninstall.php — off by default so deleting the plugin by accident never
 * loses anything).
 */
class Wpci_Settings {

	const PAGE_SLUG                  = 'wpci-settings';
	const OPTION_DISABLE_ALL          = 'wpci_disable_all';
	const OPTION_REMOVE_ON_UNINSTALL  = 'wpci_remove_data_on_uninstall';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_wpci_save_settings', array( $this, 'handle_save' ) );
	}

	public function register_menu() {
		$hook = add_submenu_page(
			Wpci_Admin_Menu::DASHBOARD_SLUG,
			__( 'Settings', 'impulse-snippets' ),
			__( 'Settings', 'impulse-snippets' ),
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
	}

	public static function is_globally_disabled() {
		return (bool) get_option( self::OPTION_DISABLE_ALL, false );
	}

	public function render_page() {
		$disable_all         = (bool) get_option( self::OPTION_DISABLE_ALL, false );
		$remove_on_uninstall = (bool) get_option( self::OPTION_REMOVE_ON_UNINSTALL, false );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'impulse-snippets' ); ?></h1>

			<?php $this->maybe_render_status_notice(); ?>

			<?php if ( $disable_all ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'All snippets are currently paused site-wide.', 'impulse-snippets' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="postbox wpci-integration-card" style="max-width:600px;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wpci_save_settings_action', 'wpci_settings_nonce' ); ?>
					<input type="hidden" name="action" value="wpci_save_settings">

					<p>
						<label>
							<input type="checkbox" name="wpci_disable_all" value="1" <?php checked( $disable_all ); ?>>
							<strong><?php esc_html_e( 'Pause all snippets (emergency kill switch)', 'impulse-snippets' ); ?></strong>
						</label><br>
						<span class="description"><?php esc_html_e( "Stops every snippet from outputting anywhere on the site, without changing any individual snippet's own on/off setting. Use this if a snippet breaks something and you need to shut everything off immediately.", 'impulse-snippets' ); ?></span>
					</p>

					<hr>

					<p>
						<label>
							<input type="checkbox" name="wpci_remove_data_on_uninstall" value="1" <?php checked( $remove_on_uninstall ); ?>>
							<strong><?php esc_html_e( 'Delete all snippets and settings when this plugin is deleted', 'impulse-snippets' ); ?></strong>
						</label><br>
						<span class="description"><?php esc_html_e( 'Off by default, so deleting the plugin by accident never loses your data. Turn this on only if you want a completely clean removal.', 'impulse-snippets' ); ?></span>
					</p>

					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'impulse-snippets' ); ?></button></p>
				</form>
			</div>
		</div>
		<?php
	}

	private function maybe_render_status_notice() {
		if ( isset( $_GET['wpci_saved'] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Settings saved.', 'impulse-snippets' ) );
		}
	}

	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'impulse-snippets' ) );
		}
		check_admin_referer( 'wpci_save_settings_action', 'wpci_settings_nonce' );

		update_option( self::OPTION_DISABLE_ALL, ! empty( $_POST['wpci_disable_all'] ) );
		update_option( self::OPTION_REMOVE_ON_UNINSTALL, ! empty( $_POST['wpci_remove_data_on_uninstall'] ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::PAGE_SLUG,
					'wpci_saved' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
