<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two small REST routes where a page reload would be a bad experience:
 * the list table's on/off toggle, and the edit screen's page-search picker.
 * Everything else in this plugin is plain form posts on purpose.
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

		// Powers the type-to-search picker on the Display Conditions box, so
		// large sites aren't stuck scrolling a fixed checkbox list.
		register_rest_route(
			self::NAMESPACE_,
			'/posts/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search_posts' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'term' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public function search_posts( $request ) {
		$term = trim( (string) $request['term'] );
		if ( strlen( $term ) < 2 ) {
			return array();
		}

		// Public content only — attachments are excluded because targeting a
		// media page is almost never what a snippet author means.
		$post_types = array_values( array_diff( array_keys( get_post_types( array( 'public' => true ) ) ), array( 'attachment' ) ) );

		$posts = get_posts(
			array(
				's'              => $term,
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
			)
		);

		$results = array();
		foreach ( $posts as $post ) {
			$results[] = array(
				'id'    => $post->ID,
				'title' => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'impulse-snippets' ),
				'type'  => $post->post_type,
			);
		}

		return $results;
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
			return new WP_Error( 'wpci_not_found', __( 'Snippet not found.', 'impulse-snippets' ), array( 'status' => 404 ) );
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
