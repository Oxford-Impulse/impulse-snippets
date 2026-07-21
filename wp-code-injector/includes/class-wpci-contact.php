<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contact page: plain mailto: links, one per topic, each with a pre-filled
 * subject line. Deliberately not a form that sends via wp_mail() — outgoing
 * mail is unreliable on a lot of hosting, and a mailto: link that opens the
 * user's own email client always works.
 */
class Wpci_Contact {

	const PAGE_SLUG = 'wpci-contact';
	const RECIPIENT = 'info@oxfordimpulse.com';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	public function register_menu() {
		$hook = add_submenu_page(
			Wpci_Admin_Menu::DASHBOARD_SLUG,
			__( 'Contact', 'wp-code-injector' ),
			__( 'Contact / Support', 'wp-code-injector' ),
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

	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Contact / Support', 'wp-code-injector' ); ?></h1>
			<p><?php esc_html_e( 'Click a button below to open your email app with the right subject line already filled in.', 'wp-code-injector' ); ?></p>

			<div class="wpci-integration-cards">
				<div class="postbox wpci-integration-card" id="wpci-bug">
					<h2><?php esc_html_e( 'Report a Bug', 'wp-code-injector' ); ?></h2>
					<p><?php esc_html_e( 'Tell us what went wrong and we will take a look.', 'wp-code-injector' ); ?></p>
					<p><a class="button button-primary" href="<?php echo esc_url( $this->mailto_link( __( 'Bug Report', 'wp-code-injector' ) ) ); ?>"><?php esc_html_e( 'Email a Bug Report', 'wp-code-injector' ); ?></a></p>
				</div>

				<div class="postbox wpci-integration-card" id="wpci-feature">
					<h2><?php esc_html_e( 'Suggest a Feature', 'wp-code-injector' ); ?></h2>
					<p><?php esc_html_e( 'Have an idea that would make this plugin more useful?', 'wp-code-injector' ); ?></p>
					<p><a class="button button-primary" href="<?php echo esc_url( $this->mailto_link( __( 'Feature Request', 'wp-code-injector' ) ) ); ?>"><?php esc_html_e( 'Email a Feature Request', 'wp-code-injector' ); ?></a></p>
				</div>

				<div class="postbox wpci-integration-card" id="wpci-question">
					<h2><?php esc_html_e( 'General Question', 'wp-code-injector' ); ?></h2>
					<p><?php esc_html_e( 'Anything else — just ask.', 'wp-code-injector' ); ?></p>
					<p><a class="button button-primary" href="<?php echo esc_url( $this->mailto_link( __( 'Question', 'wp-code-injector' ) ) ); ?>"><?php esc_html_e( 'Email Us', 'wp-code-injector' ); ?></a></p>
				</div>
			</div>

			<p class="description">
				<?php
				printf(
					/* translators: %s: support email address as a mailto link. */
					esc_html__( 'Or email us directly at %s.', 'wp-code-injector' ),
					'<a href="mailto:' . esc_attr( self::RECIPIENT ) . '">' . esc_html( self::RECIPIENT ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	private function mailto_link( $topic ) {
		$site    = wp_parse_url( home_url(), PHP_URL_HOST );
		$subject = sprintf( '[WP Code Injector] %1$s from %2$s', $topic, $site );
		return 'mailto:' . self::RECIPIENT . '?subject=' . rawurlencode( $subject );
	}
}
