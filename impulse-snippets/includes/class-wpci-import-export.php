<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import/Export page plus a "Duplicate" row action on the snippets list.
 *
 * Export produces a JSON file of every snippet (all statuses). Import
 * re-creates them — always as drafts, so nothing a file brought in can start
 * outputting on the live site until someone reviews and enables it. Every
 * imported field passes the same whitelists as the normal save path; the
 * snippet code itself is imported raw, exactly like the editor saves it.
 */
class Wpci_Import_Export {

	const PAGE_SLUG      = 'wpci-import-export';
	const EXPORT_VERSION = 1;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_wpci_export_snippets', array( $this, 'handle_export' ) );
		add_action( 'admin_post_wpci_import_snippets', array( $this, 'handle_import' ) );
		add_action( 'admin_post_wpci_duplicate_snippet', array( $this, 'handle_duplicate' ) );
		add_filter( 'post_row_actions', array( $this, 'add_duplicate_row_action' ), 10, 2 );
	}

	public function register_menu() {
		$hook = add_submenu_page(
			Wpci_Admin_Menu::DASHBOARD_SLUG,
			__( 'Import / Export', 'impulse-snippets' ),
			__( 'Import / Export', 'impulse-snippets' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		if ( $hook ) {
			add_action(
				'load-' . $hook,
				function () {
					wp_enqueue_style( 'wpci-admin', WPCI_PLUGIN_URL . 'assets/css/admin.css', array(), WPCI_VERSION );
				}
			);
		}
	}

	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import / Export', 'impulse-snippets' ); ?></h1>

			<?php $this->maybe_render_status_notice(); ?>

			<div class="wpci-integration-cards">
				<div class="postbox wpci-integration-card">
					<h2><?php esc_html_e( 'Export', 'impulse-snippets' ); ?></h2>
					<p><?php esc_html_e( 'Downloads every snippet (including disabled ones) as a single JSON file — use it as a backup, or to move snippets to another site.', 'impulse-snippets' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'wpci_export_action', 'wpci_export_nonce' ); ?>
						<input type="hidden" name="action" value="wpci_export_snippets">
						<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Download Export File', 'impulse-snippets' ); ?></button></p>
					</form>
				</div>

				<div class="postbox wpci-integration-card">
					<h2><?php esc_html_e( 'Import', 'impulse-snippets' ); ?></h2>
					<p><?php esc_html_e( 'Upload an export file to add its snippets to this site. Imported snippets always arrive switched OFF (draft), so nothing runs until you review and enable it.', 'impulse-snippets' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<?php wp_nonce_field( 'wpci_import_action', 'wpci_import_nonce' ); ?>
						<input type="hidden" name="action" value="wpci_import_snippets">
						<p><input type="file" name="wpci_import_file" accept=".json,application/json" required></p>
						<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Import Snippets', 'impulse-snippets' ); ?></button></p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	private function maybe_render_status_notice() {
		if ( isset( $_GET['wpci_imported'] ) ) {
			$count = absint( $_GET['wpci_imported'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d: number of snippets imported. */
				esc_html( sprintf( _n( '%d snippet imported (as draft — enable it from the snippets list).', '%d snippets imported (as drafts — enable them from the snippets list).', $count, 'impulse-snippets' ), $count ) )
			);
		} elseif ( isset( $_GET['wpci_import_error'] ) ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html__( "That file couldn't be read as an Impulse Snippets export. Please upload an unmodified export file.", 'impulse-snippets' )
			);
		}
	}

	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'impulse-snippets' ) );
		}
		check_admin_referer( 'wpci_export_action', 'wpci_export_nonce' );

		$snippets = get_posts(
			array(
				'post_type'      => Wpci_Cpt::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		$items = array();
		foreach ( $snippets as $snippet ) {
			$items[] = array(
				'title'          => $snippet->post_title,
				'status'         => $snippet->post_status,
				'priority'       => (int) $snippet->menu_order,
				'code'           => $snippet->post_content,
				'location'       => get_post_meta( $snippet->ID, '_wpci_location', true ),
				'code_type'      => get_post_meta( $snippet->ID, '_wpci_code_type', true ),
				'source'         => get_post_meta( $snippet->ID, '_wpci_source', true ),
				'external_url'   => get_post_meta( $snippet->ID, '_wpci_external_url', true ),
				'conditions'     => Wpci_Conditions::decode( get_post_meta( $snippet->ID, '_wpci_conditions', true ) ),
				'integration'    => get_post_meta( $snippet->ID, '_wpci_integration', true ),
				'integration_id' => get_post_meta( $snippet->ID, '_wpci_integration_id', true ),
			);
		}

		$payload = array(
			'plugin'   => 'impulse-snippets',
			'version'  => self::EXPORT_VERSION,
			'exported' => gmdate( 'c' ),
			'snippets' => $items,
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=impulse-snippets-export-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
		exit;
	}

	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'impulse-snippets' ) );
		}
		check_admin_referer( 'wpci_import_action', 'wpci_import_nonce' );

		$redirect_args = array( 'page' => self::PAGE_SLUG );

		$tmp_name = isset( $_FILES['wpci_import_file']['tmp_name'] ) ? $_FILES['wpci_import_file']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- path comes from PHP's upload handling, read directly below.
		$payload  = ( $tmp_name && is_uploaded_file( $tmp_name ) ) ? json_decode( (string) file_get_contents( $tmp_name ), true ) : null;

		if ( ! is_array( $payload ) || 'impulse-snippets' !== ( isset( $payload['plugin'] ) ? $payload['plugin'] : '' ) || empty( $payload['snippets'] ) || ! is_array( $payload['snippets'] ) ) {
			$redirect_args['wpci_import_error'] = 1;
			wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
			exit;
		}

		$imported = 0;
		foreach ( $payload['snippets'] as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( $this->import_one( $item ) ) {
				$imported++;
			}
		}

		$redirect_args['wpci_imported'] = $imported;
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Creates one snippet from an import item. Every field goes through the
	 * same whitelists as the regular save path; unknown values fall back to
	 * safe defaults instead of failing the whole import. Always a draft.
	 */
	private function import_one( $item ) {
		$code = isset( $item['code'] ) && is_string( $item['code'] ) ? $item['code'] : '';

		$location = isset( $item['location'] ) ? sanitize_key( $item['location'] ) : 'head';
		if ( ! in_array( $location, array( 'head', 'body', 'footer' ), true ) ) {
			$location = 'head';
		}

		$code_type = isset( $item['code_type'] ) ? sanitize_key( $item['code_type'] ) : 'auto';
		if ( ! in_array( $code_type, array( 'auto', 'script', 'style', 'html' ), true ) ) {
			$code_type = 'auto';
		}

		$source = isset( $item['source'] ) ? sanitize_key( $item['source'] ) : 'inline';
		if ( ! in_array( $source, array( 'inline', 'external' ), true ) ) {
			$source = 'inline';
		}

		// Round-trip the conditions through decode(): whatever survives is a
		// known-valid structure; anything malformed becomes 'invalid' which
		// fails closed at output. Post/term IDs from another site won't match
		// this site's content — that's expected and visible in the list.
		$conditions = Wpci_Conditions::decode( wp_json_encode( isset( $item['conditions'] ) && is_array( $item['conditions'] ) ? $item['conditions'] : array( 'type' => 'all' ) ) );

		// Matching compares IDs strictly, so hand-edited files with quoted
		// numbers ("7") must be normalized to integers or they'd import fine
		// yet silently never match anything.
		foreach ( array( 'post_ids', 'term_ids' ) as $id_key ) {
			if ( isset( $conditions[ $id_key ] ) && is_array( $conditions[ $id_key ] ) ) {
				$conditions[ $id_key ] = array_values( array_filter( array_map( 'intval', $conditions[ $id_key ] ) ) );
			}
		}
		if ( isset( $conditions['pages'] ) && is_array( $conditions['pages'] ) ) {
			$conditions['pages'] = array_values( array_intersect( array_map( 'strval', $conditions['pages'] ), Wpci_Conditions::ALLOWED_SPECIAL ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => Wpci_Cpt::POST_TYPE,
				'post_title'   => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : __( 'Imported snippet', 'impulse-snippets' ),
				'post_content' => $code,
				'post_status'  => 'draft',
				'menu_order'   => isset( $item['priority'] ) ? intval( $item['priority'] ) : 0,
			)
		);

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return false;
		}

		update_post_meta( $post_id, '_wpci_location', $location );
		update_post_meta( $post_id, '_wpci_code_type', $code_type );
		update_post_meta( $post_id, '_wpci_source', $source );
		update_post_meta( $post_id, '_wpci_external_url', isset( $item['external_url'] ) ? esc_url_raw( (string) $item['external_url'] ) : '' );
		update_post_meta( $post_id, '_wpci_conditions', wp_json_encode( $conditions ) );

		// Integration tags are carried over so the wizard recognizes the
		// snippet on the new site instead of creating a duplicate.
		if ( ! empty( $item['integration'] ) ) {
			update_post_meta( $post_id, '_wpci_integration', sanitize_key( $item['integration'] ) );
		}
		if ( ! empty( $item['integration_id'] ) ) {
			update_post_meta( $post_id, '_wpci_integration_id', sanitize_text_field( $item['integration_id'] ) );
		}

		return true;
	}

	public function add_duplicate_row_action( $actions, $post ) {
		if ( Wpci_Cpt::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wpci_duplicate_snippet&post=' . $post->ID ),
			'wpci_duplicate_' . $post->ID
		);

		$actions['wpci_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate', 'impulse-snippets' ) . '</a>';
		return $actions;
	}

	public function handle_duplicate() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'impulse-snippets' ) );
		}
		check_admin_referer( 'wpci_duplicate_' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post || Wpci_Cpt::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Snippet not found.', 'impulse-snippets' ) );
		}

		$new_id = wp_insert_post(
			array(
				'post_type'    => Wpci_Cpt::POST_TYPE,
				/* translators: %s: title of the snippet being duplicated. */
				'post_title'   => sprintf( __( '%s (Copy)', 'impulse-snippets' ), $post->post_title ),
				'post_content' => $post->post_content,
				'post_status'  => 'draft', // The copy starts switched off.
				'menu_order'   => $post->menu_order,
			)
		);

		if ( $new_id && ! is_wp_error( $new_id ) ) {
			// Integration tags are deliberately NOT copied — the wizard must
			// keep updating the original, not the copy.
			foreach ( array( '_wpci_location', '_wpci_code_type', '_wpci_source', '_wpci_external_url', '_wpci_conditions' ) as $meta_key ) {
				update_post_meta( $new_id, $meta_key, get_post_meta( $post_id, $meta_key, true ) );
			}
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Wpci_Cpt::POST_TYPE ) );
		exit;
	}
}
