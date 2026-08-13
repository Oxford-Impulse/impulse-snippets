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

		// An empty (or too-short) box lists every page, so site owners can
		// browse instead of guessing what a page title contains — brand-name
		// searches were only finding pages that had the brand in the title.
		if ( strlen( $term ) < 2 ) {
			$pages   = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
			$results = array();

			foreach ( $pages as $post ) {
				$results[] = $this->format_result( $post );
			}

			return $results;
		}

		// A pasted link resolves straight to its page/post, with the same
		// fallback-laden resolver the save handler uses.
		if ( 0 === stripos( $term, 'http://' ) || 0 === stripos( $term, 'https://' ) ) {
			$post_id = wpci_resolve_url_to_post_id( $term );
			$post    = $post_id ? get_post( $post_id ) : null;

			if ( ! $post || 'publish' !== $post->post_status ) {
				return array();
			}

			return array( $this->format_result( $post ) );
		}

		// Public content only — attachments are excluded because targeting a
		// media page is almost never what a snippet author means.
		$post_types = array_values( array_diff( array_keys( get_post_types( array( 'public' => true ) ) ), array( 'attachment' ) ) );

		// Pages are queried separately from everything else so a large blog
		// can't flood them out of the shared result cap, and search_columns
		// (WP 6.2+; older versions ignore it) restricts matching to titles —
		// body text matches made brand-name searches return dozens of posts
		// that merely mention the term.
		$results = array();
		$type_groups = array(
			array( 'page' ),
			array_values( array_diff( $post_types, array( 'page' ) ) ),
		);

		foreach ( $type_groups as $group ) {
			if ( empty( $group ) ) {
				continue;
			}

			$posts = get_posts(
				array(
					's'              => $term,
					'search_columns' => array( 'post_title' ),
					'post_type'      => $group,
					'post_status'    => 'publish',
					'posts_per_page' => 10,
				)
			);

			foreach ( $posts as $post ) {
				$results[] = $this->format_result( $post );
			}
		}

		return $results;
	}

	private function format_result( $post ) {
		return array(
			'id'    => $post->ID,
			'title' => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'impulse-snippets' ),
			'type'  => $post->post_type,
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
