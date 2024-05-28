<?php
/**
 * Helper class for Post Expiration
 *
 * @package    Openagenda_Base_Plugin
 * @subpackage Openagenda_Base_Plugin/Admin
 * @author     Acato <eyal@acato.nl>
 */

namespace Openagenda_Base_Plugin\Admin;

/**
 * Helper class for Post Expiration
 */
class Post_Expiration {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'openagenda_add_cron_schedule' ) );

		// Schedule an action if it's not already scheduled.
		if ( ! wp_next_scheduled( 'openagenda_add_cron_schedule' ) ) {
			wp_schedule_event( time(), 'every_15_minutes', 'openagenda_add_cron_schedule' );
		}

		/**
		 * Event function for the every_15_minutes cron schedule.
		 *
		 * @package Auto_Post_Expiration
		 * @since 1.0.0
		 */
		add_action( 'openagenda_add_cron_schedule', array( $this, 'openagenda_expire_events' ) );

		/**
		 * Register the CMB2 metaboxes
		 */
		add_action( 'cmb2_init', array( $this, 'action_cmb2_init' ) );

		/**
		 * Add custom column to the post list
		 */
		add_action( 'manage_event_posts_custom_column', array( $this, 'custom_columns' ), 10, 2 );
		add_filter( 'manage_event_posts_columns', array( $this, 'manage_event_posts_columns' ) );
	}

	/**
	 * Add custom cron schedule: Every 5 Minutes.
	 *
	 * @param array $schedules Existing cron schedules.
	 *
	 * @return array Modified cron schedules.
	 */
	public function openagenda_add_cron_schedule( $schedules ) {
		$schedules['every_15_minutes'] = array(
			'interval' => 60 * 15,
			'display'  => __( 'Every 15 Minutes', 'openagenda-base' ),
		);
		return $schedules;
	}

	/**
	 * Function to perform actions every 15 minutes for expired posts.
	 *
	 * @package Auto_Post_Expiration
	 * @since 1.0.0
	 */
	public function openagenda_expire_events() {

		$args = array(
			'post_type'      => 'event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_expire_date',
					'value'   => current_time( 'mysql' ),
					'compare' => '<',
					'type'    => 'DATETIME',
				),
			),
		);

		$post_ids = get_posts( $args );

		foreach ( $post_ids as $post_id ) {
			$update_post = array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			);
			wp_update_post( $update_post );
		}
	}

	/**
	 * Register the CMB2 metaboxes
	 *
	 * @return void
	 */
	public function action_cmb2_init() {
		$this->cmb2_event_metaboxes();
	}

	/**
	 * Add Fields in meta box
	 *
	 * @package Auto Post Expiration
	 * @since 1.0.0
	 */
	public function cmb2_event_metaboxes() {

		$cmb = new_cmb2_box(
			array(
				'id'           => 'metabox',
				'title'        => __( 'Event unpublish date', 'openagenda-base' ),
				'object_types' => array( 'event' ),
				'context'      => 'side',
				'priority'     => 'low',
				'show_names'   => true,
				'cmb_styles'   => true, // Enable CMB2 styles.
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Event unpublish date', 'openagenda-base' ),
				'id'           => '_expire_date',
				'type'         => 'text_date',
				'date_format'  => __( 'd-m-Y', 'openagenda-base' ),
				'desc'         => __( 'Select the date on which the event will be unpublished and won\'t be included in the REST API results anymore', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Add custom column to the post list.
	 *
	 * @param array $columns The columns.
	 *
	 * @return array Modified columns.
	 */
	public function manage_event_posts_columns( $columns ) {
		return array_merge( $columns, array( 'expired' => __( 'Unpublish date', 'openagenda-base' ) ) );
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
		if ( 'expired' === $column_key ) {
			$expired_date = get_post_meta( $post_id, '_expire_date', true );

			if ( $expired_date ) {
				$expire_draft_date = $this->openagenda_calc_datetime( $post_id );
				if ( 1 === $expire_draft_date ) {
					echo '<span style="color:red">';
					echo esc_html( $expired_date );
					echo '</span>';
				} else {
					echo '<span  style="color:green">';
					echo esc_html( $expired_date );
					echo '</span>';
				}
			}
		}
	}

	/**
	 * Post expiration calculation.
	 *
	 * @param int $post_id The ID of the post.
	 *
	 * @return int The difference between the current time and the expiration date.
	 */
	public function openagenda_calc_datetime( $post_id ) {
		$field_date  = get_post_meta( $post_id, '_expire_date', true );
		$first_date  = new \DateTime( current_time( 'mysql' ) );
		$second_date = new \DateTime( $field_date );
		$interval    = $first_date->diff( $second_date );
		return $interval->invert;
	}
}
