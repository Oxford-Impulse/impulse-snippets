<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds Location/Type columns to the native CPT list table (edit.php?post_type=wpci_snippet).
 * Not a WP_List_Table subclass — the core one already renders for CPTs, we just hook into it.
 */
class Wpci_List_Table {

	public function __construct() {
		$post_type = Wpci_Cpt::POST_TYPE;
		add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_columns' ) );
		add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'sortable_columns' ) );
	}

	public function sortable_columns( $columns ) {
		// 'menu_order' is a native orderby value, so core handles the query.
		$columns['wpci_priority'] = 'menu_order';
		return $columns;
	}

	public function add_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			if ( 'title' === $key ) {
				$new_columns['wpci_location']   = __( 'Location', 'impulse-snippets' );
				$new_columns['wpci_type']       = __( 'Type', 'impulse-snippets' );
				$new_columns['wpci_conditions'] = __( 'Conditions', 'impulse-snippets' );
				$new_columns['wpci_priority']   = __( 'Priority', 'impulse-snippets' );
				$new_columns['wpci_active']     = __( 'Active', 'impulse-snippets' );
			}
		}
		return $new_columns;
	}

	public function render_column( $column, $post_id ) {
		if ( 'wpci_location' === $column ) {
			echo esc_html( wpci_get_location_label( get_post_meta( $post_id, '_wpci_location', true ) ) );
		}

		if ( 'wpci_type' === $column ) {
			$label = wpci_get_code_type_label( get_post_meta( $post_id, '_wpci_code_type', true ) );
			if ( 'external' === get_post_meta( $post_id, '_wpci_source', true ) ) {
				/* translators: %s: code type label, e.g. "JavaScript". */
				$label = sprintf( __( '%s (external file)', 'impulse-snippets' ), $label );
			}
			echo esc_html( $label );

			$integration = get_post_meta( $post_id, '_wpci_integration', true );
			if ( $integration ) {
				echo '<br><span class="description">' . esc_html( wpci_get_integration_label( $integration ) ) . '</span>';
			}
		}

		if ( 'wpci_conditions' === $column ) {
			echo esc_html( wpci_get_conditions_summary( get_post_meta( $post_id, '_wpci_conditions', true ) ) );
		}

		if ( 'wpci_priority' === $column ) {
			$post = get_post( $post_id );
			echo esc_html( $post ? (string) $post->menu_order : '0' );
		}

		if ( 'wpci_active' === $column ) {
			$is_active = in_array( get_post_status( $post_id ), array( 'publish', 'future' ), true );
			?>
			<label class="wpci-toggle-switch">
				<input type="checkbox" class="wpci-toggle-input" data-post-id="<?php echo esc_attr( $post_id ); ?>" <?php checked( $is_active ); ?>>
				<span class="wpci-toggle-slider"></span>
			</label>
			<?php
		}
	}
}
