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
	}

	/**
	 * Register the Event post type
	 */
	public function action_init() {
		$this->register_post_types();
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
}
