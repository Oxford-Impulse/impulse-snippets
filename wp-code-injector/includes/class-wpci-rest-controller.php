<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One small REST route powering the list table's toggle switch: flips a
 * snippet between publish/draft without a full page reload. Everything else
 * in this plugin is plain form posts on purpose — this is the one place a
 * REST endpoint earns its keep, since a checkbox that reloads the whole page
 * on every click is a bad experience for something meant to be flipped often.
 */
class Wpci_Rest_Controller {

	const NAMESPACE_ = 'wpci/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/snippets/(?P<id>\d+)/toggle',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'toggle' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function permission_check( $request ) {
		$post_id = absint( $request['id'] );
		$post    = get_post( $post_id );

		if ( ! $post || Wpci_Cpt::POST_TYPE !== $post->post_type ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	public function toggle( $request ) {
		$post_id = absint( $request['id'] );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'wpci_not_found', __( 'Snippet not found.', 'wp-code-injector' ), array( 'status' => 404 ) );
		}

		$new_status = in_array( $post->post_status, array( 'publish', 'future' ), true ) ? 'draft' : 'publish';

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_status,
			)
		);

		return array(
			'id'     => $post_id,
			'status' => $new_status,
		);
	}
}
