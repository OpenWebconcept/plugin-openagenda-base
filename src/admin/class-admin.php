<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.openwebconcept.nl
 * @since      1.0.0
 *
 * @package    Openagenda_Base_Plugin
 * @subpackage Openagenda_Base_Plugin/Admin
 */

namespace Openagenda_Base_Plugin\Admin;

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Openagenda_Base_Plugin
 * @subpackage Openagenda_Base_Plugin/Admin
 * @author     Acato <richardkorthuis@acato.nl>
 */
class Admin {
	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'action_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_init', array( $this, 'check_plugin_dependency' ) );
		add_action( 'after_setup_theme', array( $this, 'after_setup_theme' ) );

		add_action( 'manage_event_posts_custom_column', array( $this, 'custom_columns' ), 10, 2 );
		add_filter( 'manage_event_posts_columns', array( $this, 'manage_event_posts_columns' ) );

		add_action( 'save_post_location', array( $this, 'remove_zero_opening_hours' ) );

		// Add custom post status Archive.
		add_filter( 'display_post_states', array( $this, 'display_post_status' ), 10, 2 );
		add_action( 'post_submitbox_misc_actions', array( $this, 'add_custom_status_to_dropdown' ) );
		add_action( 'admin_footer-edit.php', array( $this, 'add_custom_status_to_quick_edit' ), 10, 2 );
		add_action( 'edit_form_after_title', array( $this, 'set_custom_status_in_publish_box' ) );
	}

	/**
	 * Register the Event post type
	 */
	public function action_init() {
		$this->register_post_types();
		$this->register_post_status();
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		// Only include the script on the event and location edit pages.
		$screen = get_current_screen();
		if ( ! in_array( $screen->id, array( 'event', 'location' ), true ) ) {
			return;
		}

		wp_enqueue_script(
			'cmb2-conditional-logic',
			plugin_dir_url( __FILE__ ) . 'js/cmb2-conditional-logic.js',
			array( 'jquery', 'cmb2-scripts' ),
			filemtime( plugin_dir_path( __FILE__ ) . 'js/cmb2-conditional-logic.js' ),
			true
		);
	}

	/**
	 * Show admin notices
	 *
	 * @return void
	 */
	public function admin_notices() {
		$error_message = get_transient( 'oab_transient' );

		if ( $error_message ) {
			echo "<div class='error'><p>" . esc_html( $error_message ) . '</p></div>';
		}
	}

	/**
	 * Check if CMB2 plugin is installed and activated
	 *
	 * @return void
	 */
	public function check_plugin_dependency() {
		if (
			! is_plugin_active( 'cmb2/init.php' )
			&& is_plugin_active( 'openagenda-base/openagenda-base.php' )
		) {
			set_transient( 'oab_transient', __( 'The plugin OpenAgenda Base requires CMB2 plugin to be installed and activated. The plugin has been deactivated.', 'openagenda-base' ), 100 );
			deactivate_plugins( 'openagenda-base/openagenda-base.php' );
		} else {
			delete_transient( 'oab_transient' );
		}
	}

	/**
	 * Register the Event and Location post types.
	 *
	 * @return void
	 */
	public function register_post_types() {
		$labels = array(
			'name'               => __( 'Events', 'openagenda-base' ),
			'singular_name'      => __( 'Event', 'openagenda-base' ),
			'menu_name'          => __( 'Events', 'openagenda-base' ),
			'name_admin_bar'     => __( 'Event', 'openagenda-base' ),
			'add_new'            => __( 'Add New', 'openagenda-base' ),
			'add_new_item'       => __( 'Add New Event', 'openagenda-base' ),
			'new_item'           => __( 'New Event', 'openagenda-base' ),
			'edit_item'          => __( 'Edit Event', 'openagenda-base' ),
			'view_item'          => __( 'View Event', 'openagenda-base' ),
			'all_items'          => __( 'All Events', 'openagenda-base' ),
			'search_items'       => __( 'Search Events', 'openagenda-base' ),
			'parent_item_colon'  => __( 'Parent Events:', 'openagenda-base' ),
			'not_found'          => __( 'No events found.', 'openagenda-base' ),
			'not_found_in_trash' => __( 'No events found in Trash.', 'openagenda-base' ),
		);

		$args = array(
			'labels'       => $labels,
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-calendar',
			'hierarchical' => true,
			'supports'     => array( 'title', 'excerpt', 'thumbnail' ),
			'taxonomies'   => array( 'post_tag' ),
			'meta_box_cb'  => 'cmb2_event_metaboxes',
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'event' ),
			'show_in_rest' => true,
		);

		register_post_type( 'event', $args );

		// Register location post type.
		$labels = array(
			'name'               => __( 'Locations', 'openagenda-base' ),
			'singular_name'      => __( 'Location', 'openagenda-base' ),
			'menu_name'          => __( 'Locations', 'openagenda-base' ),
			'name_admin_bar'     => __( 'Location', 'openagenda-base' ),
			'add_new'            => __( 'Add New', 'openagenda-base' ),
			'add_new_item'       => __( 'Add New Location', 'openagenda-base' ),
			'new_item'           => __( 'New Location', 'openagenda-base' ),
			'edit_item'          => __( 'Edit Location', 'openagenda-base' ),
			'view_item'          => __( 'View Location', 'openagenda-base' ),
			'all_items'          => __( 'All Locations', 'openagenda-base' ),
			'search_items'       => __( 'Search Locations', 'openagenda-base' ),
			'parent_item_colon'  => __( 'Parent Locations:', 'openagenda-base' ),
			'not_found'          => __( 'No locations found.', 'openagenda-base' ),
			'not_found_in_trash' => __( 'No locations found in Trash.', 'openagenda-base' ),
		);

		$args = array(
			'labels'       => $labels,
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-location-alt',
			'hierarchical' => true,
			'supports'     => array( 'title', 'excerpt', 'thumbnail' ),
			'meta_box_cb'  => 'cmb2_location_metaboxes',
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'location' ),
			'show_in_rest' => true,
		);

		register_post_type( 'location', $args );
	}

	/**
	 * Register the Archive post status.
	 *
	 * @return void
	 */
	public function register_post_status() {
		register_post_status(
			'archive',
			array(
				'label'                     => __( 'Archive', 'openagenda-base' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				// translators: %s: number of posts.
				'label_count'               => _n_noop( 'Archive <span class="count">(%s)</span>', 'Archives <span class="count">(%s)</span>', 'openagenda-base' ),
			)
		);
	}

	/**
	 * Add theme support for post thumbnails
	 *
	 * @return void
	 */
	public function after_setup_theme() {
		add_theme_support( 'post-thumbnails' );
	}

	/**
	 * Add custom column to the post list.
	 *
	 * @param array $columns The columns.
	 *
	 * @return array Modified columns.
	 */
	public function manage_event_posts_columns( $columns ) {
		$new_columns = array( 'event_highlighted' => __( 'Highlighted event', 'openagenda-base' ) );

		// Add all custom taxonomies to the columns.
		$taxonomies = wp_list_filter(
			get_object_taxonomies( 'event', 'objects' ),
			array( 'show_in_rest' => true )
		);

		foreach ( $taxonomies as $taxonomy ) {
			$new_columns[ 'tax_' . $taxonomy->name ] = $taxonomy->label;
		}

		return array_merge( $columns, $new_columns );
	}

	/**
	 * Add custom column to the post list.
	 *
	 * @param string $column_key The column key.
	 * @param int    $post_id    The post ID.
	 *
	 * @return void
	 */
	public function custom_columns( $column_key, $post_id ) {
		if ( 'event_highlighted' === $column_key ) {
			echo esc_html( get_post_meta( $post_id, '_event_highlighted' ) ? __( 'Yes', 'openagenda-base' ) : __( 'No', 'openagenda-base' ) );
		}

		// Add all custom taxonomies to the columns.
		$taxonomies = wp_list_filter(
			get_object_taxonomies( 'event', 'objects' ),
			array( 'show_in_rest' => true )
		);

		foreach ( $taxonomies as $taxonomy ) {
			if ( 'tax_' . $taxonomy->name === $column_key ) {
				echo get_the_term_list( $post_id, $taxonomy->name, '', ', ', '' );
			}
		}
	}

	/**
	 * Remove zero opening hours from the location post type
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public function remove_zero_opening_hours( $post_id ) {
		if ( ! isset( $_POST['openagenda_cmb2_nonce'] ) || ! wp_verify_nonce( $_POST['openagenda_cmb2_nonce'], 'openagenda_cmb2_nonce' ) ) {
			return;
		}

		$location = get_post( $post_id );
		if ( 'location' !== $location->post_type ) {
			return;
		}

		$days_of_week = array(
			'monday',
			'tuesday',
			'wednesday',
			'thursday',
			'friday',
			'saturday',
			'sunday',
		);

		foreach ( $days_of_week as $day ) {
			$open  = $_POST[ 'location_' . $day . '_opening_hours_open' ];
			$close = $_POST[ 'location_' . $day . '_opening_hours_close' ];

			if ( '00:00' === $open ) {
				unset( $_POST[ 'location_' . $day . '_opening_hours_open' ] );
			}

			if ( '00:00' === $close ) {
				unset( $_POST[ 'location_' . $day . '_opening_hours_close' ] );
			}
		}
	}

	/**
	 * Display post status in dropdown at post list.
	 *
	 * @param array    $states The post states.
	 * @param \WP_Post $post The post object.
	 *
	 * @return array Modified post states.
	 */
	public function display_post_status( $states, $post ) {
		// Receive the post status object by post status name.
		$post_status_object = get_post_status_object( $post->post_status );

		// Checks if the label exists.
		if ( in_array( $post_status_object->label, $states, true ) ) {
			return $states;
		}

		// Adds the label of the current post status.
		$states[ $post_status_object->name ] = $post_status_object->label;

		return $states;
	}

	/**
	 * Add custom status to dropdown in edit screen of post.
	 *
	 * @return void
	 */
	public function add_custom_status_to_dropdown() {
		global $post;

		if ( 'event' === $post->post_type ) {
			$complete = '';
			$label    = '';

			if ( 'archive' === $post->post_status ) {
				$complete = ' selected="selected"';
				$label    = '<span id="post-status-display">' . __( 'Archive', 'openagenda-base' ) . '</span>';
			}

			echo '
        <script>
        jQuery(document).ready(function($){
            $("select#post_status").append(\'<option value="archive" ' . esc_html( $complete ) . '>' . esc_html__( 'Archive', 'openagenda-base' ) . '</option>\');
            $(".misc-pub-section label").append(\'' . esc_attr( $label ) . '\');
        });
        </script>';
		}
	}

	/**
	 * Add custom status to quick edit screen.
	 *
	 * @return void
	 */
	public function add_custom_status_to_quick_edit() {
		echo "<script>
	jQuery(document).ready( function() {
		jQuery( 'select[name=\"_status\"]' ).append( '<option value=\"archive\">' . __( 'Archive', 'openagenda-base' ) . '</option>' );
	});
	</script>";
	}

	/**
	 * Set the custom status in the publish box.
	 *
	 * @param WP_Post $post The post object.
	 *
	 * @return void
	 */
	public function set_custom_status_in_publish_box( $post ) {
		if ( 'archive' === $post->post_status ) {
			?>
			<script>
				document.addEventListener("DOMContentLoaded", function() {
					// Set the custom status in the post-status-display area.
					var postStatusDisplay = document.getElementById('post-status-display');
					if (postStatusDisplay) {
						postStatusDisplay.textContent = '<?php echo esc_js( __( 'Archive', 'openagenda-base' ) ); ?>';
					}
				});
			</script>
			<?php
		}
	}
}
