<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the custom meta boxes on the snippet edit screen. The CPT has no
 * 'editor' support, so the code textarea here (wpci_code) is what the save
 * handler copies into post_content.
 */
class Wpci_Edit_Screen {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
	}

	public function add_meta_boxes() {
		add_meta_box(
			'wpci_conditions_box',
			__( 'Display Conditions', 'impulse-snippets' ),
			array( $this, 'render_conditions_box' ),
			Wpci_Cpt::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'wpci_code_box',
			__( 'Snippet Code', 'impulse-snippets' ),
			array( $this, 'render_code_box' ),
			Wpci_Cpt::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'wpci_location_box',
			__( 'Location', 'impulse-snippets' ),
			array( $this, 'render_location_box' ),
			Wpci_Cpt::POST_TYPE,
			'side',
			'high'
		);
	}

	public function render_code_box( $post ) {
		wp_nonce_field( 'wpci_save_snippet_' . $post->ID, 'wpci_snippet_nonce' );

		$code      = $post->post_content;
		$code_type = get_post_meta( $post->ID, '_wpci_code_type', true );
		if ( '' === $code_type ) {
			$code_type = 'auto';
		}
		$source = get_post_meta( $post->ID, '_wpci_source', true );
		if ( '' === $source ) {
			$source = 'inline';
		}

		$external_url = get_post_meta( $post->ID, '_wpci_external_url', true );
		if ( '' === $external_url && 'external' === $source && '' !== $code ) {
			// Legacy (pre-1.15.0): external snippets stored their URL in
			// post_content. Show it in the URL field, not the code box.
			$external_url = $code;
			$code         = '';
		}
		?>
		<div class="notice notice-warning inline wpci-warning">
			<p>
				<?php esc_html_e( 'This code is output on your live site exactly as written, without modification. Only paste code (or link to files) from sources you trust.', 'impulse-snippets' ); ?>
			</p>
		</div>

		<p>
			<label style="margin-right:20px;">
				<input type="radio" name="wpci_code_source" value="inline" <?php checked( $source, 'inline' ); ?> class="wpci-source-radio">
				<?php esc_html_e( 'Paste code', 'impulse-snippets' ); ?>
			</label>
			<label>
				<input type="radio" name="wpci_code_source" value="external" <?php checked( $source, 'external' ); ?> class="wpci-source-radio">
				<?php esc_html_e( 'Link to an external file (URL)', 'impulse-snippets' ); ?>
			</label>
		</p>

		<p>
			<label for="wpci_code_type"><strong><?php esc_html_e( 'Code type', 'impulse-snippets' ); ?></strong></label><br>
			<select name="wpci_code_type" id="wpci_code_type">
				<?php foreach ( wpci_get_code_types() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $code_type, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="description"><?php esc_html_e( '(Also used for external files: JavaScript or CSS decides which HTML tag is used to load the file.)', 'impulse-snippets' ); ?></span>
		</p>

		<div class="wpci-source-panel" data-source="inline" style="<?php echo 'inline' === $source ? '' : 'display:none;'; ?>">
			<p class="description">
				<?php esc_html_e( 'Auto-detect: if your code doesn\'t already include <script> or <style> tags, we\'ll add <script> tags for you.', 'impulse-snippets' ); ?>
			</p>
			<p>
				<textarea id="wpci_code" name="wpci_code" rows="12" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $code ); ?></textarea>
			</p>
		</div>

		<div class="wpci-source-panel" data-source="external" style="<?php echo 'external' === $source ? '' : 'display:none;'; ?>">
			<p>
				<label for="wpci_external_url"><?php esc_html_e( 'External file URL', 'impulse-snippets' ); ?></label><br>
				<input type="url" id="wpci_external_url" name="wpci_external_url" style="width:100%;" placeholder="https://example.com/library.js" value="<?php echo esc_attr( $external_url ); ?>">
			</p>
		</div>
		<?php
	}

	public function render_location_box( $post ) {
		$location = get_post_meta( $post->ID, '_wpci_location', true );
		if ( '' === $location ) {
			$location = 'head';
		}
		?>
		<p>
			<?php foreach ( wpci_get_locations() as $key => $label ) : ?>
				<label style="display:block;margin-bottom:6px;">
					<input type="radio" name="wpci_location" value="<?php echo esc_attr( $key ); ?>" <?php checked( $location, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</p>
		<hr>
		<p>
			<label for="wpci_priority"><strong><?php esc_html_e( 'Priority', 'impulse-snippets' ); ?></strong></label><br>
			<input type="number" id="wpci_priority" name="wpci_priority" value="<?php echo esc_attr( $post->menu_order ); ?>" step="1" style="width:90px;">
		</p>
		<p class="description">
			<?php esc_html_e( 'Snippets in the same location print in priority order, lowest number first. Leave at 0 unless one snippet must load before another (e.g. a consent script before analytics).', 'impulse-snippets' ); ?>
		</p>
		<?php
	}

	public function render_conditions_box( $post ) {
		$conditions = Wpci_Conditions::decode( get_post_meta( $post->ID, '_wpci_conditions', true ) );
		$type       = in_array( $conditions['type'], Wpci_Conditions::ALLOWED_TYPES, true ) ? $conditions['type'] : 'all';
		$post_ids   = ( ! empty( $conditions['post_ids'] ) && is_array( $conditions['post_ids'] ) ) ? $conditions['post_ids'] : array();
		$post_types = ( ! empty( $conditions['post_types'] ) && is_array( $conditions['post_types'] ) ) ? $conditions['post_types'] : array();
		$term_ids   = ( ! empty( $conditions['term_ids'] ) && is_array( $conditions['term_ids'] ) ) ? $conditions['term_ids'] : array();
		$special    = ( ! empty( $conditions['pages'] ) && is_array( $conditions['pages'] ) ) ? $conditions['pages'] : array();
		$visitor    = ( isset( $conditions['visitor'] ) && in_array( $conditions['visitor'], Wpci_Conditions::ALLOWED_VISITORS, true ) ) ? $conditions['visitor'] : 'all';
		?>
		<p>
			<label style="display:block;margin-bottom:6px;">
				<input type="radio" name="wpci_condition_type" value="all" <?php checked( $type, 'all' ); ?> class="wpci-condition-radio">
				<?php esc_html_e( 'All pages (site-wide)', 'impulse-snippets' ); ?>
			</label>
			<label style="display:block;margin-bottom:6px;">
				<input type="radio" name="wpci_condition_type" value="specific" <?php checked( $type, 'specific' ); ?> class="wpci-condition-radio">
				<?php esc_html_e( 'Specific pages or posts', 'impulse-snippets' ); ?>
			</label>
			<label style="display:block;margin-bottom:6px;">
				<input type="radio" name="wpci_condition_type" value="post_types" <?php checked( $type, 'post_types' ); ?> class="wpci-condition-radio">
				<?php esc_html_e( 'Post types', 'impulse-snippets' ); ?>
			</label>
			<label style="display:block;margin-bottom:6px;">
				<input type="radio" name="wpci_condition_type" value="categories" <?php checked( $type, 'categories' ); ?> class="wpci-condition-radio">
				<?php esc_html_e( 'Categories (single blog posts)', 'impulse-snippets' ); ?>
			</label>
			<label style="display:block;">
				<input type="radio" name="wpci_condition_type" value="special" <?php checked( $type, 'special' ); ?> class="wpci-condition-radio">
				<?php esc_html_e( 'Special pages (front page, 404, search)', 'impulse-snippets' ); ?>
			</label>
		</p>

		<div class="wpci-condition-panel" data-condition="specific" style="<?php echo 'specific' === $type ? '' : 'display:none;'; ?>">
			<p class="description"><?php esc_html_e( 'Select one or more pages/posts:', 'impulse-snippets' ); ?></p>
			<div class="wpci-searchable-list">
				<input type="text" class="wpci-checkbox-filter" placeholder="<?php esc_attr_e( 'Type to search…', 'impulse-snippets' ); ?>">
				<div class="wpci-checkbox-list">
					<?php foreach ( $this->get_selectable_posts( $post_ids ) as $selectable ) : ?>
						<label style="display:block;">
							<input type="checkbox" name="wpci_condition_post_ids[]" value="<?php echo esc_attr( $selectable->ID ); ?>" <?php echo in_array( $selectable->ID, $post_ids, true ) ? 'checked' : ''; ?>>
							<?php echo esc_html( $selectable->post_title . ' (' . $selectable->post_type . ')' ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
			<p style="margin-top:10px;">
				<label for="wpci_condition_post_url"><?php esc_html_e( 'Or paste a page/post link to add it directly:', 'impulse-snippets' ); ?></label><br>
				<input type="url" id="wpci_condition_post_url" name="wpci_condition_post_url" placeholder="https://yoursite.com/some-page/" style="width:100%;">
			</p>
		</div>

		<div class="wpci-condition-panel" data-condition="post_types" style="<?php echo 'post_types' === $type ? '' : 'display:none;'; ?>">
			<?php foreach ( $this->get_selectable_post_types() as $pt ) : ?>
				<label style="display:block;">
					<input type="checkbox" name="wpci_condition_post_types[]" value="<?php echo esc_attr( $pt->name ); ?>" <?php echo in_array( $pt->name, $post_types, true ) ? 'checked' : ''; ?>>
					<?php echo esc_html( $pt->label ); ?>
				</label>
			<?php endforeach; ?>
		</div>

		<div class="wpci-condition-panel" data-condition="categories" style="<?php echo 'categories' === $type ? '' : 'display:none;'; ?>">
			<p class="description"><?php esc_html_e( 'Applies to single blog posts in these categories (not the category archive pages).', 'impulse-snippets' ); ?></p>
			<div class="wpci-searchable-list">
				<input type="text" class="wpci-checkbox-filter" placeholder="<?php esc_attr_e( 'Type to search…', 'impulse-snippets' ); ?>">
				<div class="wpci-checkbox-list">
					<?php foreach ( get_categories( array( 'hide_empty' => false ) ) as $cat ) : ?>
						<label style="display:block;">
							<input type="checkbox" name="wpci_condition_term_ids[]" value="<?php echo esc_attr( $cat->term_id ); ?>" <?php echo in_array( $cat->term_id, $term_ids, true ) ? 'checked' : ''; ?>>
							<?php echo esc_html( $cat->name ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<div class="wpci-condition-panel" data-condition="special" style="<?php echo 'special' === $type ? '' : 'display:none;'; ?>">
			<label style="display:block;">
				<input type="checkbox" name="wpci_condition_special[]" value="front" <?php echo in_array( 'front', $special, true ) ? 'checked' : ''; ?>>
				<?php esc_html_e( 'Front page (homepage)', 'impulse-snippets' ); ?>
			</label>
			<label style="display:block;">
				<input type="checkbox" name="wpci_condition_special[]" value="404" <?php echo in_array( '404', $special, true ) ? 'checked' : ''; ?>>
				<?php esc_html_e( '404 "page not found" page', 'impulse-snippets' ); ?>
			</label>
			<label style="display:block;">
				<input type="checkbox" name="wpci_condition_special[]" value="search" <?php echo in_array( 'search', $special, true ) ? 'checked' : ''; ?>>
				<?php esc_html_e( 'Search results page', 'impulse-snippets' ); ?>
			</label>
		</div>

		<hr>
		<p>
			<label for="wpci_condition_visitor"><strong><?php esc_html_e( 'Show for', 'impulse-snippets' ); ?></strong></label><br>
			<select name="wpci_condition_visitor" id="wpci_condition_visitor">
				<option value="all" <?php selected( $visitor, 'all' ); ?>><?php esc_html_e( 'Everyone', 'impulse-snippets' ); ?></option>
				<option value="logged_in" <?php selected( $visitor, 'logged_in' ); ?>><?php esc_html_e( 'Logged-in users only', 'impulse-snippets' ); ?></option>
				<option value="logged_out" <?php selected( $visitor, 'logged_out' ); ?>><?php esc_html_e( 'Logged-out visitors only', 'impulse-snippets' ); ?></option>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'Applies on top of the page rule above. Handy for showing a snippet only to visitors (e.g. analytics) or only to logged-in users.', 'impulse-snippets' ); ?></p>
		<?php
	}

	private function get_selectable_posts( $include_ids = array() ) {
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'posts_per_page' => 200,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		// Always render the currently-targeted posts too, even when they fall
		// outside the 200-item window (large sites) or aren't a post/page
		// (custom post types added via the paste-a-URL field). Unrendered
		// checkboxes don't submit, so omitting them here would silently drop
		// that targeting on the next save.
		$missing = array_diff( $include_ids, wp_list_pluck( $posts, 'ID' ) );
		if ( ! empty( $missing ) ) {
			$extra = get_posts(
				array(
					'post_type'   => 'any',
					'post_status' => 'any',
					'include'     => $missing,
				)
			);
			$posts = array_merge( $extra, $posts );
		}

		return $posts;
	}

	private function get_selectable_post_types() {
		return get_post_types( array( 'public' => true ), 'objects' );
	}
}
