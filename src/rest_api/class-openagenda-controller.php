<?php
/**
 * The Openagenda_Controller class.
 *
 * @package    Openagenda_Base_Plugin
 * @subpackage Openagenda_Base_Plugin/Rest_Api
 * @author     Acato <eyal@acato.nl>
 */

namespace Openagenda_Base_Plugin\Rest_Api;

use DateTime;
use Openagenda_Base_Plugin\Admin\Event_Dates;

/**
 * The Openagenda_Controller class.
 */
class Openagenda_Controller extends \WP_REST_Posts_Controller {

	/**
	 * The post types returned by this API endpoint, mapped against their meta prefix.
	 *
	 * @var string[] $post_type_mappings
	 */
	private $post_type_mappings = array(
		'event'    => array(
			'rest_endpoint_items' => 'items',
		),
		'location' => array(
			'rest_endpoint_items' => 'locations',
		),
	);

	/**
	 * The CMB2 fields to filter by.
	 *
	 * @var string[] $cmb2_fields_filter
	 */
	private $cmb2_fields_filter = array(
		'highlighted',
		'language',
		'location',
		'location_city',
		'organizer',
		'publicity',
		'registration',
	);

	/**
	 * The CMB2 fields to use in the extended search endpoint.
	 *
	 * @var string[] $cmb2_fields_search
	 */
	private $cmb2_fields_search = array(
		'accessibility',
		'description',
		'itinerary',
		'language',
		'location',
		'location_address',
		'location_zipcode',
		'location_city',
		'organizer',
		'publicity',
		'registration',
		'teaser',
	);

	/**
	 * Openagenda_Controller constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize the controller
	 */
	public function init() {
		parent::__construct( 'event' );

		$this->namespace = 'owc/openagenda/v1';
		$this->rest_base = 'items';

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'posts_where', array( $this, 'extend_wp_query_where' ), 10, 2 );
	}

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * Main endpoint.
	 *
	 * @link https://url/wp-json/owc/openagenda/v1
	 *
	 * Endpoint of the openagenda-items.
	 * @link https://url/wp-json/owc/openagenda/v1/items
	 *
	 * Endpoint of the openagenda-items filtered by date.
	 * @link https://url/wp-json/owc/openagenda/v1/items?date=YYYY-MM-DD
	 *
	 * Endpoint of the openagenda-items filtered by time period.
	 * @link https://url/wp-json/owc/openagenda/v1/items?time_period={today|tomorrow|thisweek|thisweekend|nextweek|thismonth|nextmonth}
	 *
	 * Endpoint to filter openagenda-items on search term.
	 * @link https://url/wp-json/owc/openagenda/v1/items?search={search_term}
	 *
	 * Endpoint to filter openagenda-items on taxonomy terms id's
	 * @link https://url/wp-json/owc/openagenda/v1/items?{taxonomy}=term_id1,term_id2
	 *
	 * Endpoint to filter openagenda-items on cmb2 custom fields.
	 * @link https://url/wp-json/owc/openagenda/v1/items?{cmb2_field}=value1
	 *
	 * Endpoint of the openagenda-item detail page by id.
	 * @link https://url/wp-json/owc/openagenda/v1/items/id/{id}
	 *
	 * Endpoint of the openagenda-item detail page by slug.
	 * @link https://url/wp-json/owc/openagenda/v1/items?slug={slug}
	 *
	 * Endpoint to retrieve all event fields for the submit endpoint.
	 * @link https://url/wp-json/owc/openagenda/v1/fields
	 *
	 * Endpoint to submit a new event (POST).
	 * @link https://url/wp-json/owc/openagenda/v1/items
	 *
	 * Endpoint to retrieve all existing taxonomies in post type event.
	 * @link https://url/wp-json/wp/v2/taxonomies?type=event
	 *
	 * Endpoint to retrieve all items of a specific taxonomy.
	 * @link https://url/wp-json/wp/v2/{taxonomy}
	 *
	 * Endpoint to retrieve all locations.
	 * @link https://url/wp-json/owc/openagenda/v1/locations
	 *
	 * Endpoint to retrieve specific location based on id.
	 * @link https://url/wp-json/owc/openagenda/v1/locations/id/{id}
	 *
	 * Endpoint to retrieve specific location based on slug.
	 * @link https://url/wp-json/owc/openagenda/v1/locations?slug={slug}
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/items',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_items_collection_params(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/items/id/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item_by_id' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(),
			)
		);

		// Create REST API endpoint to retrieve all the CMB2 fields available for event post type.
		register_rest_route(
			$this->namespace,
			'/fields',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item_fields' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(),
			)
		);

		// Create REST API endpoint to submit forms and add new events as draft.
		register_rest_route(
			$this->namespace,
			'/items',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_item' ),
				'permission_callback' => array( $this, 'submit_item_permissions_check' ),
				'args'                => array(),
			)
		);

		Register_rest_route(
			$this->namespace,
			'/locations',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items_locations' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args'                => parent::get_collection_params(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/locations/id/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_location_by_id' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(),
			)
		);
	}

	/**
	 * Extend the WP_Query WHERE clause with custom WHERE clauses.
	 *
	 * @param string    $where The WHERE clause.
	 * @param \WP_Query $wp_query The WP_Query instance.
	 *
	 * @return string The extended WHERE clause.
	 */
	public function extend_wp_query_where( $where, $wp_query ) {
		$extend_where = $wp_query->get( 'extend_where' );

		if ( ! $extend_where ) {
			return $where;
		}

		if ( is_array( $extend_where ) ) {
			$where .= ' AND ' . implode( ' AND ', $extend_where );
		} else {
			$where .= ' AND ' . $extend_where;
		}
		return $where;
	}

	/**
	 * Check if a given request has permission to read items.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return bool Whether the request has permission to read items.
	 */
	public function get_items_permissions_check( $request ) {
		return true;
	}

	/**
	 * Check if a given request has permission to submit items.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_Error|bool Whether the request has permission to submit items.
	 */
	public function submit_item_permissions_check( $request ) {
		if ( defined( 'WP_ENV' ) && 'development' === WP_ENV ) {
			return true;
		}
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		global $wpdb;

		// Retrieve the list of registered collection query parameters.
		$registered = $this->get_items_collection_params();
		$args       = array();

		$prefix        = 'event_';
		$date_meta_key = '_openagenda_event_date_list';

		/*
		 * This array defines mappings between public API query parameters whose
		 * values are accepted as-passed, and their internal WP_Query parameter
		 * name equivalents (some are the same). Only values which are also
		 * present in $registered will be set.
		 */
		$parameter_mappings = array(
			'author'         => 'author__in',
			'author_exclude' => 'author__not_in',
			'exclude'        => 'post__not_in',
			'include'        => 'post__in',
			'menu_order'     => 'menu_order',
			'offset'         => 'offset',
			'order'          => 'order',
			'orderby'        => 'orderby',
			'page'           => 'paged',
			'parent'         => 'post_parent__in',
			'parent_exclude' => 'post_parent__not_in',
			'search'         => 's',
			'slug'           => 'post_name__in',
			'status'         => 'post_status',
		);

		/*
		 * For each known parameter which is both registered and present in the request,
		 * set the parameter's value on the query $args.
		 */
		foreach ( $parameter_mappings as $api_param => $wp_param ) {
			if ( isset( $registered[ $api_param ], $request[ $api_param ] ) ) {
				$args[ $wp_param ] = $request[ $api_param ];
			}
		}

		// Check for & assign any parameters which require special handling or setting.
		$args['date_query'] = array();

		if ( isset( $registered['modified_before'], $request['modified_before'] ) ) {
			$args['date_query'][] = array(
				'before' => $request['modified_before'],
				'column' => 'post_modified',
			);
		}

		if ( isset( $registered['modified_after'], $request['modified_after'] ) ) {
			$args['date_query'][] = array(
				'after'  => $request['modified_after'],
				'column' => 'post_modified',
			);
		}

		if ( isset( $registered['date'], $request['date'] ) ) {
			// Check if input date is actually a valid date.
			if ( DateTime::createFromFormat( 'Y-m-d', $request['date'] ) ) {
				$date_where_clauses = array();
				if ( ! is_array( $request['date'] ) ) {
					$request['date'] = array( $request['date'] );
				}

				foreach ( $request['date'] as $date ) {
					$date_where_clauses[] = sprintf( "ID IN ( SELECT `post_id` FROM `{$wpdb->postmeta}` WHERE `meta_key` = '{$date_meta_key}' AND `meta_value` = '%s' )", $date );
				}
				if ( count( $date_where_clauses ) ) {
					$args['extend_where'][] = '( ' . implode( ' OR ', $date_where_clauses ) . ' )';
				}
			}
		}

		if ( isset( $registered['time_period'], $request['time_period'] ) ) {
			switch ( $request['time_period'] ) {
				case 'today':
				default:
					$request['after']  = gmdate( 'Y-m-d', strtotime( 'today' ) );
					$request['before'] = gmdate( 'Y-m-d', strtotime( 'today' ) );
					break;
				case 'tomorrow':
					$request['after']  = gmdate( 'Y-m-d', strtotime( 'tomorrow' ) );
					$request['before'] = gmdate( 'Y-m-d', strtotime( 'tomorrow' ) );
					break;
				case 'thisweek':
					$request['after']  = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
					$request['before'] = gmdate( 'Y-m-d', strtotime( 'sunday this week' ) );
					break;
				case 'thisweekend':
					$request['after']  = gmdate( 'Y-m-d', strtotime( 'saturday this week' ) );
					$request['before'] = gmdate( 'Y-m-d', strtotime( 'sunday this week' ) );
					break;
				case 'nextweek':
					$request['after']  = gmdate( 'Y-m-d', strtotime( 'monday next week' ) );
					$request['before'] = gmdate( 'Y-m-d', strtotime( 'sunday next week' ) );
					break;
				case 'thismonth':
					$request['after']  = gmdate( 'Y-m-d', strtotime( 'first day of this month' ) );
					$request['before'] = gmdate( 'Y-m-d', strtotime( 'last day of this month' ) );
					break;
				case 'nextmonth':
					$request['after']  = gmdate( 'Y-m-d', strtotime( 'first day of next month' ) );
					$request['before'] = gmdate( 'Y-m-d', strtotime( 'last day of next month' ) );
					break;
			}
		}

		if ( isset( $registered['year'], $request['year'] ) ) {
			$year_where_clauses = array();
			foreach ( $request['year'] as $year ) {
				$year_where_clauses[] = sprintf( "ID IN ( SELECT `post_id` FROM `{$wpdb->postmeta}` WHERE `meta_key` = '{$date_meta_key}' AND YEAR( `meta_value` ) = %d )", $year );
			}
			if ( count( $year_where_clauses ) ) {
				$args['extend_where'][] = '( ' . implode( ' OR ', $year_where_clauses ) . ' )';
			}
		}

		if ( isset( $registered['after'], $request['after'] ) ) {
			$args['extend_where'][] = sprintf( "ID IN ( SELECT `post_id` FROM `{$wpdb->postmeta}` WHERE `meta_key` = '{$date_meta_key}' AND meta_value >= '%s' )", $request['after'] );
		}
		if ( isset( $registered['before'], $request['before'] ) ) {
			$args['extend_where'][] = sprintf( "ID IN ( SELECT `post_id` FROM `{$wpdb->postmeta}` WHERE `meta_key` = '{$date_meta_key}' AND meta_value <= '%s' )", $request['before'] );
		}

		// Filter by custom CMB2 fields.
		$cmb2_where_clauses = array();
		foreach ( $this->cmb2_fields_filter as $cmb2_field ) {
			if ( isset( $registered[ $cmb2_field ], $request[ $cmb2_field ] ) ) {
				foreach ( $request[ $cmb2_field ] as $request_item ) {
					// Change input value of "1" or "true" to "on" for checkbox fields.
					if ( '1' === $request_item || 'true' === $request_item ) {
						$request_item = 'on';
					}

					$cmb2_where_clauses[] = sprintf( "ID IN ( SELECT `post_id` FROM `{$wpdb->postmeta}` WHERE `meta_key` = '{$prefix}{$cmb2_field}' AND `meta_value` LIKE '%%%s%%' )", $request_item );
				}
			}
		}

		// Extend WordPress REST API search query to include custom fields.
		if ( isset( $registered['search'], $request['search'] ) ) {
			$taxonomies = get_object_taxonomies( 'event' );

			$request['search'] = reset( $request['search'] );
			// Search on every CMB2 custom field.
			if ( $this->cmb2_fields_search ) {
				// Add event_ as prefix to all values in the array.
				$this->cmb2_fields_search = array_map(
					function ( $field ) use ( $prefix ) {
						return $prefix . $field;
					},
					$this->cmb2_fields_search
				);

				$meta_keys_string     = implode( "','", $this->cmb2_fields_search );
				$cmb2_where_clauses[] = sprintf( "ID IN ( SELECT `post_id` FROM `{$wpdb->postmeta}` WHERE `meta_key` IN ('{$meta_keys_string}') AND `meta_value` LIKE '%%%s%%' )", $request['search'] );

			}
		}

		if ( count( $cmb2_where_clauses ) ) {
			$args['extend_where'][] = '( ' . implode( ' OR ', $cmb2_where_clauses ) . ' )';
		}

		// Ensure our per_page parameter overrides any provided posts_per_page filter.
		if ( isset( $registered['per_page'] ) ) {
			$args['posts_per_page'] = $request['per_page'];
		}

		// Sort events by next date.
		if ( ! isset( $request['orderby'] ) || 'next_date' === $request['orderby'] ) {
			$args['meta_query'] = [
				[
					'key'     => '_next_date',
					'compare' => '>=',
					'value'   => gmdate( 'Y-m-d' ),
					'type'    => 'DATE',
				],
			];
			$args['meta_key']   = '_next_date';
			$args['orderby']    = 'meta_value';
			$args['order']      = 'ASC';
		}

		$args = $this->prepare_tax_query( $args, $request );

		$args['post_type'] = 'event';

		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query  = new \WP_Query();
		$query_result = $posts_query->query( $query_args );

		$posts = array();
		foreach ( $query_result as $post ) {
			$posts[] = $this->prepare_item_for_response( $post, $request );
		}

		// Sort events.
		if ( ! isset( $request['orderby'] ) || 'next_date' === $request['orderby'] ) {
			$posts = $this->sort_events( $posts );
		}

		$response = array(
			'results'    => $posts,
			'pagination' => array(
				'total' => $posts_query->found_posts,
				'limit' => (int) $posts_query->query_vars['posts_per_page'],
				'pages' => array(
					'total'   => ceil( $posts_query->found_posts / (int) $posts_query->query_vars['posts_per_page'] ),
					'current' => (int) $query_args['paged'],
				),
			),
			'_links'     => array(),
		);

		foreach ( $this->post_type_mappings as $type => $post_type_mapping ) {
			$response['_links'][ $type ] = rest_url() . $this->namespace . '/' . $post_type_mapping['rest_endpoint_items'] . '/id/{id}';
		}

		$response = rest_ensure_response( $response );

		return $response;
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items_locations( $request ) {
		$args = array();

		$registered = parent::get_collection_params();
		$prefix     = 'location_';

		/*
		 * This array defines mappings between public API query parameters whose
		 * values are accepted as-passed, and their internal WP_Query parameter
		 * name equivalents (some are the same). Only values which are also
		 * present in $registered will be set.
		 */
		$parameter_mappings = array(
			'author'         => 'author__in',
			'author_exclude' => 'author__not_in',
			'exclude'        => 'post__not_in',
			'include'        => 'post__in',
			'menu_order'     => 'menu_order',
			'offset'         => 'offset',
			'order'          => 'order',
			'orderby'        => 'orderby',
			'page'           => 'paged',
			'parent'         => 'post_parent__in',
			'parent_exclude' => 'post_parent__not_in',
			'search'         => 's',
			'slug'           => 'post_name__in',
			'status'         => 'post_status',
		);

		/*
		 * For each known parameter which is both registered and present in the request,
		 * set the parameter's value on the query $args.
		 */
		foreach ( $parameter_mappings as $api_param => $wp_param ) {
			if ( isset( $registered[ $api_param ], $request[ $api_param ] ) ) {
				$args[ $wp_param ] = $request[ $api_param ];
			}
		}

		// Ensure our per_page parameter overrides any provided posts_per_page filter.
		if ( isset( $registered['per_page'] ) ) {
			$args['posts_per_page'] = $request['per_page'];
		}

		$args = $this->prepare_tax_query( $args, $request );

		$args['post_type'] = 'location';

		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query  = new \WP_Query();
		$query_result = $posts_query->query( $query_args );

		$posts = array();
		foreach ( $query_result as $post ) {
			$posts[] = $this->prepare_location_for_response( $post, $request );
		}

		$response = array(
			'results'    => $posts,
			'pagination' => array(
				'total' => $posts_query->found_posts,
				'limit' => (int) $posts_query->query_vars['posts_per_page'],
				'pages' => array(
					'total'   => ceil( $posts_query->found_posts / (int) $posts_query->query_vars['posts_per_page'] ),
					'current' => isset( $query_args['paged'] ) ? (int) $query_args['paged'] : 1,
				),
			),
			'_links'     => array(),
		);

		foreach ( $this->post_type_mappings as $type => $post_type_mapping ) {
			$response['_links'][ $type ] = rest_url() . $this->namespace . '/' . $post_type_mapping['rest_endpoint_items'] . '/id/{id}';
		}

		$response = rest_ensure_response( $response );

		return $response;
	}

	/**
	 * Get event item by id
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item_by_id( $request ) {
		$id = $request['id'];

		$post = get_post( $id );
		if ( ! $post ) {
			return new \WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.', 'openagenda-base' ), array( 'status' => 404 ) );
		}

		$response = $this->prepare_item_for_response( $post, $request );
		return rest_ensure_response( $response );
	}

	/**
	 * Get location item by id
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_location_by_id( $request ) {
		$id = $request['id'];

		$post = get_post( $id );
		if ( ! $post ) {
			return new \WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.', 'openagenda-base' ), array( 'status' => 404 ) );
		}

		$response = $this->prepare_location_for_response( $post, $request );
		return rest_ensure_response( $response );
	}

	/**
	 * Get event item fields
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item_fields( $request ) {
		$fields = array(
			array(
				'id'   => 'post_title',
				'name' => 'Post title',
				'desc' => 'The post title of the event.',
				'type' => 'text',
			),
		);

		// Get custom fields via CMB2 REST API.
		$cmb2_boxes = \CMB2_Boxes::get_all();

		if ( ! $cmb2_boxes ) {
			return new \WP_Error( 'rest_missing_fields', __( 'CMB2 custom fields are missing.', 'openagenda-base' ), array( 'status' => 400 ) );
		}

		foreach ( $cmb2_boxes as $cmb2_box ) {
			$cmb2_box = $cmb2_box->__get( 'meta_box' );

			if ( ! empty( $cmb2_box['object_types'] ) && in_array( 'event', $cmb2_box['object_types'], true ) ) {
				if ( empty( $cmb2_box['fields'] ) ) {
					return new \WP_Error( 'rest_missing_fields', __( 'CMB2 custom fields are missing.', 'openagenda-base' ), array( 'status' => 400 ) );
				}

				$cmb2_box_fields = $cmb2_box['fields'];

				$prefix = str_replace( 'metabox', '', $cmb2_box['id'] );

				foreach ( $cmb2_box_fields as $cmb2_key => $cmb2_field ) {
					$cmb2_key_data = str_replace( $prefix, '', $cmb2_key );

					if ( 'group_specific' === substr( $cmb2_key_data, 0, 14 ) || 'group_complex' === substr( $cmb2_key_data, 0, 13 ) ) {

						// Get the fields of the group.
						$group_fields = array();
						foreach ( $cmb2_field['fields'] as $group_field ) {
							$group_field_id   = str_replace( array( 'event_dates_specific_', 'event_dates_complex_' ), '', $group_field['id'] );
							$group_field_item = array(
								'id'   => $group_field_id,
								'name' => $group_field['name'],
								'desc' => $group_field['desc'],
								'type' => $group_field['type'],
							);

							if ( $group_field['attributes'] ) {
								$group_field_item['attributes'] = $group_field['attributes'];
							}

							if ( $group_field['date_format'] ) {
								$group_field_item['date_format'] = $group_field['date_format'];
							}

							if ( $group_field['time_format'] ) {
								$group_field_item['time_format'] = $group_field['time_format'];
							}

							if ( $group_field['options'] ) {
								$group_field_item['options'] = $group_field['options'];
							}

							$group_fields[] = $group_field_item;
						}

						$fields[] = array(
							'id'     => 'dates',
							'type'   => 'repeatable',
							'fields' => $group_fields,
						);
						continue;
					}

					// remove prefix from key.
					$cmb2_key_data = str_replace( $prefix, '', $cmb2_key );
					$field_item    = array(
						'id'   => $cmb2_key_data,
						'name' => $cmb2_field['name'],
						'desc' => $cmb2_field['desc'],
						'type' => $cmb2_field['type'],
					);

					if ( $cmb2_field['attributes'] ) {
						$field_item['attributes'] = $cmb2_field['attributes'];
					}

					if ( $cmb2_field['options'] ) {
						$field_item['options'] = $cmb2_field['options'];
					}

					$fields[] = $field_item;
				}
			}
		}

		$response = array(
			'fields' => $fields,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Submit a new event.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function submit_item( $request ) {
		$body = $request->get_body();

		if ( ! wp_is_json_request() ) {
			return new \WP_Error( 'rest_invalid_content_type', __( 'Invalid content type.', 'openagenda-base' ), array( 'status' => 415 ) );
		}

		// Get custom fields via CMB2 REST API.
		$cmb2_boxes = \CMB2_Boxes::get_all();

		// Check which cmb2 fields are required and are not submitted in the request or are empty.
		$event_prefix    = 'event_';
		$required_fields = array();
		foreach ( $cmb2_boxes as $cmb2_box ) {
			$cmb2_box = $cmb2_box->__get( 'meta_box' );
			if ( ! empty( $cmb2_box['object_types'] ) && in_array( 'event', $cmb2_box['object_types'], true ) ) {
				if ( empty( $cmb2_box['fields'] ) ) {
					return new \WP_Error( 'rest_missing_fields', __( 'Can\'t submit event, because CMB2 custom fields are missing.', 'openagenda-base' ), array( 'status' => 400 ) );
				}

				$cmb2_box_fields = $cmb2_box['fields'];

				foreach ( $cmb2_box_fields as $cmb2_key => $cmb2_field ) {
					if ( isset( $cmb2_field['attributes']['required'] ) && 'required' === $cmb2_field['attributes']['required'] ) {
						$cmb2_field_id     = str_replace( $event_prefix, '', $cmb2_field['id'] );
						$required_fields[] = $cmb2_field_id;
					}
				}
			}
		}

		// Add extra required fields to cmb2 box required fields.
		$missing_required_fields = [];
		$required_fields         = array_merge(
			$required_fields,
			array(
				'title',
				'dates_type',
				'dates',
			)
		);

		$data = json_decode( $body, true );

		// Add required fields for price_type.
		if ( ! empty( $data['price_type'] ) ) {
			switch ( $data['price_type'] ) {
				case 'fixed':
					$required_fields[] = 'fixed_price';
					break;
				case 'min':
					$required_fields[] = 'min_price';
					break;
				case 'min_max':
					$required_fields[] = 'min_price';
					$required_fields[] = 'max_price';
					break;
			}
		}

		// Add required fields for dates_type.
		if ( ! empty( $data['dates_type'] ) ) {
			switch ( $data['dates_type'] ) {
				case 'specific':
					$required_fields['dates'][] = 'start_date';
					break;
				case 'complex':
					$required_fields['dates'][] = 'start_date';
					$required_fields['dates'][] = 'end_date';
					break;
			}
		}

		// Check if required fields are submitted.
		foreach ( $required_fields as $key => $required_field ) {
			// Check whether required field is an array and the key is dates.
			if ( is_array( $required_field ) && 'dates' === $key ) {
				// Check whether every submitted dates array item has all required dates fields.
				foreach ( $data['dates'] as $dates_item ) {
					foreach ( $required_field as $dates_sub_field ) {
						if ( ! isset( $dates_item[ $dates_sub_field ] ) || empty( $dates_item[ $dates_sub_field ] ) ) {
							$missing_required_fields[] = $dates_sub_field;
						}
					}
				}
			} elseif ( ! isset( $data[ $required_field ] ) ) {
				$missing_required_fields[] = $required_field;
			}
		}

		// Remove duplicates in missing required fields.
		$missing_required_fields = array_unique( $missing_required_fields );

		if ( ! empty( $missing_required_fields ) ) {
			return new \WP_Error(
				'rest_missing_fields',
				/* translators: %s: comma-seperated list of required fields that are missing in the submit data */
				sprintf( __( 'Can\'t submit event, because required fields are missing: %s', 'openagenda-base' ), implode( ', ', $missing_required_fields ) ),
				array( 'status' => 400 )
			);
		}

		$default_args = array(
			'post_title'   => $data['title'],
			'post_excerpt' => $data['description'],
			'post_status'  => 'draft',
			'post_type'    => 'event',
			'post_author'  => get_option( 'openagenda_api_submit_user' ) ? get_option( 'openagenda_api_submit_user' ) : 1, // Select API user from plugin settings.
		);

		$new_event_id = wp_insert_post( $default_args );

		if ( ! $new_event_id ) {
			return new \WP_Error( 'rest_cannot_create', __( 'Cannot create item.', 'openagenda-base' ), array( 'status' => 500 ) );
		}

		// set featured image.
		if ( ! empty( $data['thumbnail'] ) ) {
			// upload base64 encoded file to WordPress media library and retrieve url.
			$media_attachment = $this->save_file( 'image', $data['thumbnail'], $data['title'] );
			if ( is_wp_error( $media_attachment ) ) {
				return $media_attachment;
			}
			set_post_thumbnail( $new_event_id, $media_attachment['id'] );
			unset( $data['thumbnail'] );
		}

		foreach ( $cmb2_boxes as $cmb2_box ) {
			$cmb2_box = $cmb2_box->__get( 'meta_box' );
			if ( ! empty( $cmb2_box['object_types'] ) && in_array( 'event', $cmb2_box['object_types'], true ) ) {

				if ( empty( $cmb2_box['fields'] ) ) {
					// Delete event again, because there is an error.
					wp_delete_post( $new_event_id, true );
					return new \WP_Error( 'rest_missing_fields', __( 'Can\'t submit event, because CMB2 custom fields are missing.', 'openagenda-base' ), array( 'status' => 400 ) );
				}

				$cmb2_box_fields = $cmb2_box['fields'];

				$prefix = str_replace( 'metabox', '', $cmb2_box['id'] );

				foreach ( $cmb2_box_fields as $cmb2_key => $cmb2_field ) {
					// remove prefix from key.
					$cmb2_key_data = str_replace( $prefix, '', $cmb2_key );

					// format base64 media files.
					if ( 'media_files' === $cmb2_key_data ) {
						if ( ! empty( $data[ $cmb2_key_data ] ) ) {
							foreach ( $data[ $cmb2_key_data ] as $key => $base64_media_file ) {
								if ( ! empty( $base64_media_file ) ) {
									// upload base64 encoded file to WordPress media library and retrieve url.
									$media_attachment = $this->save_file( 'file', $base64_media_file, $data['title'] );
									if ( is_wp_error( $media_attachment ) ) {
										return $media_attachment;
									}
									$data[ $cmb2_key_data ][ $media_attachment['id'] ] = $media_attachment['url'];
									unset( $data[ $cmb2_key_data ][ $key ] );
								}
							}
						}
					}

					// format base64 images.
					if ( 'images' === $cmb2_key_data ) {
						if ( ! empty( $data[ $cmb2_key_data ] ) ) {
							foreach ( $data[ $cmb2_key_data ] as $key => $base64_media_file ) {
								if ( ! empty( $base64_media_file ) ) {
									// upload base64 encoded image to WordPress media library and retrieve url.
									$media_attachment = $this->save_file( 'image', $base64_media_file, $data['title'] );
									if ( is_wp_error( $media_attachment ) ) {
										return $media_attachment;
									}
									$data[ $cmb2_key_data ][ $media_attachment['id'] ] = $media_attachment['url'];
									unset( $data[ $cmb2_key_data ][ $key ] );
								}
							}
						}
					}

					// Insert post meta for the event.
					if ( isset( $data[ $cmb2_key_data ] ) ) {
						add_post_meta( $new_event_id, $cmb2_key, $data[ $cmb2_key_data ] );
					}
				}
			}
		}

		// check if there are also fields in the data that are taxonomy fields starting with tax_ and add the taxonomy terms to the database.
		foreach ( $data as $cmb2_key_data => $value ) {
			if ( 'tax_' === substr( $cmb2_key_data, 0, 4 ) ) {
				$taxonomy  = str_replace( 'tax_', '', $cmb2_key_data );
				$terms     = $value;
				$terms_ids = array();

				// Create array if it's not an array.
				if ( ! is_array( $terms ) ) {
					$terms = array( $terms );
				}

				// Check if term is numeric or string and add term to the post.
				foreach ( $terms as $term ) {
					if ( is_numeric( $term ) ) {
						$terms_ids[] = (int) $term;
					} else {
						$term        = get_term_by( 'name', $term, $taxonomy );
						$terms_ids[] = $term->term_id;
					}
				}

				// add term to the post.
				wp_set_post_terms( $new_event_id, $terms_ids, $taxonomy );
			}

			// check the dates field and add the dates to the database.
			if ( 'dates' === $cmb2_key_data ) {
				$dates_type = $data['dates_type']; // specific or complex.
				$prefix     = 'event_dates_';

				add_post_meta( $new_event_id, $prefix . 'type', $dates_type );

				$dates      = $data[ $cmb2_key_data ];
				$post_dates = array();

				if ( is_array( $dates ) ) {
					foreach ( $dates as $date ) {
						// str replace / with - for date format.
						$date['start_date'] = str_replace( '/', '-', $date['start_date'] );
						$date['end_date']   = str_replace( '/', '-', $date['end_date'] );

						// check if start_time and end_time are arrays and convert them to string.
						if ( is_array( $date['start_time'] ) ) {
							$date['start_time'] = implode( ':', $date['start_time'] );
						}
						if ( is_array( $date['end_time'] ) ) {
							$date['end_time'] = implode( ':', $date['end_time'] );
						}

						if ( 'specific' === $dates_type ) {
							$post_dates[] = array(
								$prefix . 'specific_start_date' => ! empty( $date['start_date'] ) ? $date['start_date'] : '',
								$prefix . 'specific_end_date' => ! empty( $date['end_date'] ) ? $date['end_date'] : '',
								$prefix . 'specific_start_time' => ! empty( $date['start_time'] ) ? $date['start_time'] : '',
								$prefix . 'specific_end_time' => ! empty( $date['end_time'] ) ? $date['end_time'] : '',
							);
						} elseif ( 'complex' === $dates_type ) {
							$post_dates[] = array(
								$prefix . 'complex_start_date' => ! empty( $date['start_date'] ) ? $date['start_date'] : '',
								$prefix . 'complex_end_date' => ! empty( $date['end_date'] ) ? $date['end_date'] : '',
								$prefix . 'complex_start_time' => ! empty( $date['start_time'] ) ? $date['start_time'] : '',
								$prefix . 'complex_end_time' => ! empty( $date['end_time'] ) ? $date['end_time'] : '',
								$prefix . 'complex_weekday_occurrence' => ! empty( $date['weekday_occurrence'] ) ? $date['weekday_occurrence'] : '',
								$prefix . 'complex_weekdays' => ! empty( $date['weekdays'] ) ? $date['weekdays'] : '',
								$prefix . 'complex_months' => ! empty( $date['months'] ) ? $date['months'] : '',
							);
						}
					}

					add_post_meta( $new_event_id, $prefix . 'group_' . $dates_type, $post_dates );
				}
			}
		}

		$response = array(
			'status'  => 'success',
			'message' => 'Event submitted successfully',
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Save base64 file to WordPress media library
	 *
	 * @param string $type The type, either image or file.
	 * @param string $base64_file The base64 file.
	 * @param string $title The title of the image.
	 *
	 * @return array|\WP_Error The attachment ID and URL.
	 */
	public function save_file( $type, $base64_file, $title ) {

		// Upload dir.
		$upload_dir  = wp_upload_dir();
		$upload_path = str_replace( '/', DIRECTORY_SEPARATOR, $upload_dir['path'] ) . DIRECTORY_SEPARATOR;

		if ( 'data:' === substr( $base64_file, 0, 5 ) ) {
			$file_array = explode( ',', $base64_file );
			$file       = $file_array[1];
		} else {
			$file = $base64_file;
		}
		$file = str_replace( ' ', '+', $file );

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$decoded   = base64_decode( $file );
		$finfo     = finfo_open();
		$mime_type = finfo_buffer( $finfo, $decoded, FILEINFO_MIME_TYPE );
		$extension = explode( '/', finfo_buffer( $finfo, $decoded, FILEINFO_EXTENSION ) )[0];

		$filename        = sanitize_title( $title ) . '.' . $extension;
		$hashed_filename = md5( $filename . microtime() ) . '_' . $filename;

		// Save the image in the uploads directory.
		include_once ABSPATH . 'wp-admin/includes/file.php';

		global $wp_filesystem;
		WP_Filesystem();

		if ( false === $wp_filesystem->put_contents( $upload_path . $hashed_filename, $decoded ) ) {
			return new \WP_Error(
				'rest_cant_create_file',
				/* translators: %s: comma-seperated list of required fields that are missing in the submit data */
				sprintf( __( 'Can\'t create file on filesystem: %s', 'openagenda-base' ), $upload_path . $hashed_filename ),
				array( 'status' => 400 )
			);
		}

		$attachment = array(
			'post_mime_type' => $mime_type,
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $hashed_filename ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => $upload_dir['url'] . '/' . basename( $hashed_filename ),
		);

		$attach_id = wp_insert_attachment( $attachment, $upload_dir['path'] . '/' . $hashed_filename );

		// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attach_data = wp_generate_attachment_metadata( $attach_id, $upload_dir['path'] . '/' . $hashed_filename );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		return array(
			'id'  => $attach_id,
			'url' => wp_get_attachment_url( $attach_id ),
		);
	}

	/**
	 * Retrieves the query params for the collection.
	 *
	 * @return array Collection parameters.
	 */
	public function get_items_collection_params() {
		$query_params = parent::get_collection_params();

		$query_params['context']['default'] = 'view';

		$query_params['type'] = array(
			'description' => __( 'Limit response to posts within (a) specific post type(s).', 'openagenda-base' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
			'default'     => array(),
		);

		$query_params['year'] = array(
			'description' => __( 'Limit response to posts within (a) specific year(s).', 'openagenda-base' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['date'] = array(
			'description' => __( 'Limit response to posts for (a) specific date.', 'openagenda-base' ),
			'type'        => 'string',
		);

		$query_params['time_period'] = array(
			'description' => __( 'Limit response to posts within (a) specific time period.', 'openagenda-base' ),
			'type'        => 'string',
			'enum'        => array(
				'today',
				'thisweek',
				'nextweek',
				'thismonth',
				'nextmonth',
			),
		);

		$query_params['after'] = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit response to posts published after a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);

		$query_params['modified_after'] = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit response to posts modified after a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);

		$query_params['author']         = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to posts assigned to specific authors.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);
		$query_params['author_exclude'] = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Ensure result set excludes posts assigned to specific authors.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['before'] = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit response to posts published before a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);

		$query_params['modified_before'] = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit response to posts modified before a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);

		$query_params['exclude'] = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Ensure result set excludes specific IDs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['include'] = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to specific IDs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['offset'] = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Offset the result set by a specific number of items.' ),
			'type'        => 'integer',
		);

		$query_params['order'] = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Order sort attribute ascending or descending.' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array( 'asc', 'desc' ),
		);

		$query_params['orderby'] = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Sort collection by post attribute.' ),
			'type'        => 'string',
			'default'     => 'next_date',
			'enum'        => array(
				'author',
				'id',
				'include',
				'modified',
				'parent',
				'relevance',
				'slug',
				'include_slugs',
				'title',
				'next_date',
			),
		);

		$query_params['parent']         = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to items with particular parent IDs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);
		$query_params['parent_exclude'] = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to all items except those of a particular parent ID.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => array(),
		);

		$query_params['slug'] = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to posts with one or more specific slugs.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		$query_params['search'] = array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit results to those matching a string.' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		// combine cmb2_fields_filter and cmb2_fields_search to one array and filter out duplicates.
		$cmb2_fields = array_merge( $this->cmb2_fields_filter, $this->cmb2_fields_search );
		foreach ( $cmb2_fields as $cmb2_field ) {
			$query_params[ $cmb2_field ] = array(
				/* translators: %s: cmb2 field name */
				'description' => sprintf( __( 'Limit result set to posts on specific %s.', 'openagenda-base' ), $cmb2_field ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
				),
			);
		}

		return $query_params;
	}

	/**
	 * Prepares the 'tax_query' for a collection of posts.
	 *
	 * @since 5.7.0
	 *
	 * @param array            $args    WP_Query arguments.
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return array Updated query arguments.
	 */
	public function prepare_tax_query( array $args, \WP_REST_Request $request ) {
		$relation = $request['tax_relation'];

		if ( $relation ) {
			$args['tax_query'] = array( 'relation' => $relation );
		}

		$taxonomies = wp_list_filter(
			get_object_taxonomies( $this->post_type, 'objects' ),
			array( 'show_in_rest' => true )
		);

		foreach ( $taxonomies as $taxonomy ) {
			$base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;

			$tax_include = $request[ $base ];
			$tax_exclude = $request[ $base . '_exclude' ];

			if ( $tax_include ) {
				$terms            = array();
				$include_children = false;
				$operator         = 'IN';

				if ( rest_is_array( $tax_include ) ) {
					$terms = $tax_include;
				} elseif ( rest_is_object( $tax_include ) ) {
					$terms            = empty( $tax_include['terms'] ) ? array() : $tax_include['terms'];
					$include_children = ! empty( $tax_include['include_children'] );

					if ( isset( $tax_include['operator'] ) && 'AND' === $tax_include['operator'] ) {
						$operator = 'AND';
					}
				}

				if ( $terms ) {
					$args['tax_query'][] = array(
						'taxonomy'         => $taxonomy->name,
						'field'            => 'term_id',
						'terms'            => $terms,
						'include_children' => $include_children,
						'operator'         => $operator,
					);
				}
			}

			if ( $tax_exclude ) {
				$terms            = array();
				$include_children = false;

				if ( rest_is_array( $tax_exclude ) ) {
					$terms = $tax_exclude;
				} elseif ( rest_is_object( $tax_exclude ) ) {
					$terms            = empty( $tax_exclude['terms'] ) ? array() : $tax_exclude['terms'];
					$include_children = ! empty( $tax_exclude['include_children'] );
				}

				if ( $terms ) {
					$args['tax_query'][] = array(
						'taxonomy'         => $taxonomy->name,
						'field'            => 'term_id',
						'terms'            => $terms,
						'include_children' => $include_children,
						'operator'         => 'NOT IN',
					);
				}
			}
		}

		return $args;
	}

	/**
	 * Prepares the query for the collection of items.
	 *
	 * @param \WP_POST         $item The post object.
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return array The item data.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$prefix = 'event_';

		// Get the post excerpt and if it's empty get the description field as post excerpt.
		if (
			empty( $item->post_excerpt ) &&
			! empty( get_post_meta( $item->ID, $prefix . 'description', true ) )
		) {
			$item->post_excerpt = get_post_meta( $item->ID, $prefix . 'description', true );
		}

		$item_data = array(
			'id'            => $item->ID,
			'title'         => $item->post_title,
			'slug'          => $item->post_name,
			'excerpt'       => $item->post_excerpt,
			'post_status'   => $item->post_status,
			'post_modified' => $item->post_modified,
		);

		// Check if the post has a featured image and add it to the item data.
		if ( get_the_post_thumbnail_url( $item->ID, 'large' ) ) {
			$thumb_image                 = get_the_post_thumbnail_url( $item->ID, 'large' );
			$thumb_id                    = get_post_thumbnail_id( $item->ID );
			$item_data['post_thumbnail'] = $this->create_image_output( $thumb_id, $thumb_image );
		} else {
			$item_data['post_thumbnail'] = null;
		}

		$default_meta_keys = array(
			'accessibility',
			'contact_person',
			'description',
			'email_address',
			'event_website_url',
			'itinerary',
			'highlighted',
			'language',
			'location_address',
			'location_description',
			'location_zipcode',
			'location_city',
			'organizer',
			'organizer_website_url',
			'phone_number',
			'publicity',
			'registration',
			'registration_url',
			'teaser',
			'ticket_website_url',
			'video_url',
		);

		$maybe_hidden_meta_keys = array(
			'email_address',
			'phone_number',
		);

		foreach ( $default_meta_keys as $meta_key ) {
			if ( in_array( $meta_key, $maybe_hidden_meta_keys, true ) ) {
				$maybe_hidden_meta_key_value = get_post_meta( $item->ID, $prefix . $meta_key . '_public', true );

				if ( 'on' !== $maybe_hidden_meta_key_value ) {
					continue;
				}
			}

			$meta_value = get_post_meta( $item->ID, $prefix . $meta_key, true );
			if ( ! $meta_value ) {
				$item_data[ $meta_key ] = null;
				continue;
			}

			if ( 'on' === $meta_value ) {
				$meta_value = true;
			}

			$item_data[ $meta_key ] = $meta_value;
		}

		// Create Lat/long based on address data from OSM.
		$osm_address = $this->get_latlng_from_address( $item_data['location_address'], $item_data['location_zipcode'], $item_data['location_city'] );
		if ( ! empty( $osm_address ) ) {
			$item_data['latitude']  = $osm_address['latitude'];
			$item_data['longitude'] = $osm_address['longitude'];
		}

		// get the price of the event.
		$price_type              = get_post_meta( $item->ID, $prefix . 'price_type', true );
		$item_data['price_type'] = $price_type;
		switch ( $price_type ) {
			case 'fixed':
			default:
				$item_data['price'] = get_post_meta( $item->ID, $prefix . 'fixed_price', true );
				break;
			case 'min':
				$item_data['price'] = get_post_meta( $item->ID, $prefix . 'min_price', true );
				break;
			case 'min_max':
				$item_data['price']     = get_post_meta( $item->ID, $prefix . 'min_price', true );
				$item_data['price_max'] = get_post_meta( $item->ID, $prefix . 'max_price', true );
		}

		// Add date type to the item data.
		$item_data['dates_type'] = get_post_meta( $item->ID, 'event_dates_type', true );

		// Check if fields corresponding to dates_type are set.
		$event_dates_row = get_post_meta( $item->ID, 'event_dates_group_' . $item_data['dates_type'], true );
		if ( empty( $event_dates_row ) || empty( $event_dates_row[0][ 'event_dates_' . $item_data['dates_type'] . '_start_date' ] ) ) {
			// If not, set dates and next_date to null.
			$item_data['dates']     = null;
			$item_data['next_date'] = null;
		} else {
			// Sort event dates by date ASC.
			$event_dates_class = new Event_Dates();
			$event_dates       = $event_dates_class->get_date_list( $item->ID );
			sort( $event_dates );
			$item_data['dates'] = $event_dates;

			// Get first date in the near future to sort events by date ASC.
			$item_data['next_date'] = $event_dates_class->get_next_date( $item->ID );
		}

		// add media files to the item data with some extra information from the attachment.
		$media_files = get_post_meta( $item->ID, 'event_media_files', true );
		if ( ! empty( $media_files ) ) {
			foreach ( $media_files as $id => $media_file ) {
				$media_files[ $id ] = array(
					'id'  => $id,
					'url' => $media_file,
				);
			}
			$item_data['media_files'] = $media_files;
		} else {
			$item_data['media_files'] = null;
		}

		// add images to the item data with some extra information from the attachment.
		$images = get_post_meta( $item->ID, 'event_images', true );
		if ( ! empty( $images ) ) {
			foreach ( $images as $id => $image ) {
				$images[ $id ] = $this->create_image_output( $id, $image );
			}
			$item_data['images'] = $images;
		} else {
			$item_data['images'] = null;
		}

		// get all taxonomies with entered value of post type.
		$taxonomies = get_object_taxonomies( $item, 'names' );
		// check which taxonomies are not empty and add them to the item data.
		foreach ( $taxonomies as $taxonomy ) {
			// check if post has taxonomy values.
			$terms = get_the_terms( $item->ID, $taxonomy );
			if ( ! empty( $terms ) ) {
				$item_data['taxonomies'][ $taxonomy ] = $terms;
			}
		}

		// Check if this event has a location linked and add it to the item data.
		$location_id = get_post_meta( $item->ID, 'event_location', true );
		if ( $location_id ) {
			$location              = get_post( $location_id );
			$item_data['location'] = $this->prepare_location_for_response( $location, $request, false );

			// Remove the location fields which are used if not connected to a post type.
			unset( $item_data['location_address'] );
			unset( $item_data['location_description'] );
			unset( $item_data['location_zipcode'] );
			unset( $item_data['location_city'] );
		}

		return $item_data;
	}

	/**
	 * Prepares the query for the collection of items.
	 *
	 * @param \WP_POST         $item The post object.
	 * @param \WP_REST_Request $request Full details about the request.
	 * @param bool             $include_events Whether to include events in the response.
	 *
	 * @return array The item data.
	 */
	public function prepare_location_for_response( $item, $request, $include_events = true ) {
		$prefix = 'location_';

		// Get the post excerpt and if it's empty get the description field as post excerpt.
		if (
			empty( $item->post_excerpt ) &&
			! empty( get_post_meta( $item->ID, $prefix . 'description', true ) )
		) {
			$item->post_excerpt = get_post_meta( $item->ID, $prefix . 'description', true );
		}

		$item_data = array(
			'id'          => $item->ID,
			'title'       => $item->post_title,
			'slug'        => $item->post_name,
			'excerpt'     => $item->post_excerpt,
			'post_status' => $item->post_status,
		);

		// Check if the post has a featured image and add it to the item data.
		if ( get_the_post_thumbnail_url( $item->ID, 'large' ) ) {
			$thumb_image                 = get_the_post_thumbnail_url( $item->ID, 'large' );
			$thumb_id                    = get_post_thumbnail_id( $item->ID );
			$item_data['post_thumbnail'] = $this->create_image_output( $thumb_id, $thumb_image );
		} else {
			$item_data['post_thumbnail'] = null;
		}

		$default_meta_keys = array(
			'language',
			'address',
			'zipcode',
			'city',
			'description',
			'phone_number',
			'email_address',
			'website_url',
		);

		$maybe_hidden_meta_keys = array(
			'email_address',
			'phone_number',
		);

		foreach ( $default_meta_keys as $meta_key ) {
			if ( in_array( $meta_key, $maybe_hidden_meta_keys, true ) ) {
				$maybe_hidden_meta_key_value = get_post_meta( $item->ID, $prefix . $meta_key . '_public', true );

				if ( 'on' !== $maybe_hidden_meta_key_value ) {
					continue;
				}
			}

			$meta_value = get_post_meta( $item->ID, $prefix . $meta_key, true );
			if ( ! $meta_value ) {
				$item_data[ $meta_key ] = null;
				continue;
			}

			if ( 'on' === $meta_value ) {
				$meta_value = true;
			}

			$item_data[ $meta_key ] = $meta_value;
		}

		// Create Lat/long based on address data from OSM.
		$osm_address = $this->get_latlng_from_address( $item_data['address'], $item_data['zipcode'], $item_data['city'] );
		if ( ! empty( $osm_address ) ) {
			$item_data['latitude']  = $osm_address['latitude'];
			$item_data['longitude'] = $osm_address['longitude'];
		}

		// Add all opening hours to the item data.
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
			$item_data['opening_hours'][ $day ]['open']  = get_post_meta( $item->ID, $prefix . $day . '_opening_hours_open', true );
			$item_data['opening_hours'][ $day ]['close'] = get_post_meta( $item->ID, $prefix . $day . '_opening_hours_close', true );
		}
		$item_data['opening_hours']['extra'] = get_post_meta( $item->ID, $prefix . 'opening_hours_extra', true );

		// Add social media channels to the item data.
		$social_media_group = get_post_meta( $item->ID, $prefix . 'social_media', true );
		if ( ! empty( $social_media_group ) ) {
			foreach ( $social_media_group as $social_media ) {
				$item_data['social_media'][ $social_media[ $prefix . 'social_media_channel_name' ] ] = $social_media[ $prefix . 'social_media_channel_url' ];
			}
		}

		if ( $include_events ) {
			// Get events which are linked to this location.
			$event_args = array(
				'post_type'      => 'event',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => 'event_location',
						'value' => $item->ID,
					),
				),
			);

			$posts_query  = new \WP_Query();
			$query_result = $posts_query->query( $event_args );

			$events = array();
			foreach ( $query_result as $post ) {
				$events[] = $this->prepare_item_for_response( $post, $request );
			}

			// Sort events.
			$events = $this->sort_events( $events );

			$item_data['events'] = $events;
		}

		return $item_data;
	}

	/**
	 * Create image output.
	 *
	 * @param int    $id    The attachment ID.
	 * @param string $image The image URL.
	 *
	 * @return array The image data.
	 */
	public function create_image_output( $id, $image ) {
		$attachment_meta = wp_get_attachment_metadata( $id );
		$attachment      = get_post( $id );

		if ( ! $attachment_meta || ! $attachment ) {
			return [];
		}

		// get focal point information.
		$focal_point = get_post_meta( $id, 'bg_pos_desktop', true );

		$output = [
			'id'          => $id,
			'url'         => $image,
			'width'       => ! empty( $attachment_meta['width'] ) ? $attachment_meta['width'] : null,
			'height'      => ! empty( $attachment_meta['height'] ) ? $attachment_meta['height'] : null,
			'filesize'    => ! empty( $attachment_meta['filesize'] ) ? $attachment_meta['filesize'] : null,
			'alt'         => get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'caption'     => wp_get_attachment_caption( $id ),
			'description' => ! empty( $attachment->post_content ) ? $attachment->post_content : '',
		];

		if ( $focal_point ) {
			$output['focal_point'] = $focal_point;
		}

		return $output;
	}

	/**
	 * Prepares the response for the collection of items.
	 *
	 * @param array $data The data.
	 *
	 * @return mixed The data.
	 */
	public function prepare_response_for_collection( $data ) {
		return $data;
	}

	/**
	 * Sort events by date and time and then by title.
	 *
	 * @param array $posts The posts.
	 *
	 * @return mixed The sorted posts.
	 */
	public function sort_events( $posts ) {
		usort(
			$posts,
			function ( $a, $b ) {
				$a_date = $a['next_date'];
				$b_date = $b['next_date'];

				if ( isset( $a_date['date'] ) && isset( $b_date['date'] ) ) {
					if ( $a_date['date'] === $b_date['date'] ) {
						$a_start_time = $a_date['start_time'];
						$b_start_time = $b_date['start_time'];
						if ( $a_start_time === $b_start_time ) {
							return strcmp( $a['title'], $b['title'] );
						}
						return strcmp( $a_start_time, $b_start_time );
					}
					return strcmp( $a_date['date'], $b_date['date'] );
				}
				return 0;
			}
		);

		return $posts;
	}

	/**
	 * Get latitude and longitude from address.
	 *
	 * @param string $address The address.
	 * @param string $zipcode The zipcode.
	 * @param string $city The city.
	 *
	 * @return array|bool The latitude and longitude.
	 */
	public function get_latlng_from_address( $address, $zipcode, $city ) {
		if ( empty( $address ) || empty( $zipcode ) || empty( $city ) ) {
			return false;
		}

		$address_full = $address . ', ' . $zipcode . ' ' . $city;
		$address_full = rawurlencode( $address_full );

		// Get the address data from OSM (OpenStreetMap).
		$osm_url     = 'https://nominatim.openstreetmap.org/search?q=' . $address_full . '&format=json&addressdetails=1';
		$osm_address = wp_remote_get( $osm_url );

		// Check if the request was successful or an error.
		if ( ! $osm_address || is_wp_error( $osm_address ) ) {
			return null;
		}

		$osm_address = json_decode( $osm_address['body'] );

		if ( ! $osm_address || ! $osm_address[0]->lat || ! $osm_address[0]->lon ) {
			return null;
		}

		$latitude  = $osm_address[0]->lat;
		$longitude = $osm_address[0]->lon;

		return [
			'latitude'  => $latitude,
			'longitude' => $longitude,
		];
	}
}
