<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs published snippets on the front end.
 *
 * wp_body_open (added in WP 5.2) is the primary path for "body" location
 * snippets, but not every theme calls it, so wp_footer at the same priority
 * is used as a fallback — guarded so it never double-prints if wp_body_open
 * did fire. This mirrors the old prototype's compatibility trick, which was
 * a good pragmatic fix worth keeping.
 *
 * Each published snippet's stored targeting rule (Wpci_Conditions) is
 * checked per-request against the current page, so a snippet can be
 * restricted to specific pages/posts, post types, or categories instead of
 * always being site-wide.
 */
class Wpci_Output {

	private $body_output_done = false;

	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_head' ), 1 );
		add_action( 'wp_body_open', array( $this, 'output_body' ), 1 );
		add_action( 'wp_footer', array( $this, 'output_body' ), 1 );
		add_action( 'wp_footer', array( $this, 'output_footer' ), 20 );
	}

	public function output_head() {
		$this->output_location( 'head' );
	}

	public function output_body() {
		if ( $this->body_output_done ) {
			return;
		}
		$this->body_output_done = true;
		$this->output_location( 'body' );
	}

	public function output_footer() {
		$this->output_location( 'footer' );
	}

	private function output_location( $location ) {
		if ( Wpci_Settings::is_globally_disabled() ) {
			return;
		}

		$snippets = get_posts(
			array(
				'post_type'              => Wpci_Cpt::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'menu_order',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_key'               => '_wpci_location',
				'meta_value'             => $location,
			)
		);

		foreach ( $snippets as $snippet ) {
			$conditions = get_post_meta( $snippet->ID, '_wpci_conditions', true );
			if ( ! Wpci_Conditions::matches( $conditions ) ) {
				continue;
			}

			$code_type = get_post_meta( $snippet->ID, '_wpci_code_type', true );
			$source    = get_post_meta( $snippet->ID, '_wpci_source', true );

			if ( 'external' === $source ) {
				$url = get_post_meta( $snippet->ID, '_wpci_external_url', true );
				if ( '' === $url ) {
					$url = $snippet->post_content; // Legacy pre-1.15.0 storage; migrated to meta on next save.
				}
				echo wpci_render_external_tag( $url, $code_type ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wpci_render_external_tag() already esc_url()s the URL itself.
			} else {
				echo wpci_maybe_wrap_code( $snippet->post_content, $code_type ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentional: user-authored injection code, output as-is by design. Access to author/edit snippets is restricted to manage_options.
			}
		}
	}
}
