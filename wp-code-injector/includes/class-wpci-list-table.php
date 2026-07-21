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
	}

	public function add_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			if ( 'title' === $key ) {
				$new_columns['wpci_location']   = __( 'Location', 'wp-code-injector' );
				$new_columns['wpci_type']       = __( 'Type', 'wp-code-injector' );
				$new_columns['wpci_conditions'] = __( 'Conditions', 'wp-code-injector' );
				$new_columns['wpci_active']     = __( 'Active', 'wp-code-injector' );
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
				$label = sprintf( __( '%s (external file)', 'wp-code-injector' ), $label );
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
