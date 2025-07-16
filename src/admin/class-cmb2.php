<?php
/**
 * Helper class for CMB2
 *
 * @package    Openagenda_Base_Plugin
 * @subpackage Openagenda_Base_Plugin/Admin
 * @author     Acato <eyal@acato.nl>
 */

namespace Openagenda_Base_Plugin\Admin;

/**
 * Helper class for CMB2
 */
class Cmb2 {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'cmb2_init', array( $this, 'action_cmb2_init' ) );
		add_action( 'post_submitbox_start', [ $this, 'add_nonce_field' ] );
	}

	/**
	 * Add required field indicator
	 *
	 * @return string
	 */
	private static function required() {
		return ' <strong style="color:red">*</strong>';
	}

	/**
	 * Register the CMB2 metaboxes
	 *
	 * @return void
	 */
	public function action_cmb2_init() {
		$this->cmb2_event_metaboxes();
		$this->cmb2_location_metaboxes();
	}

	/**
	 * Register the CMB2 metaboxes for the Event post type
	 *
	 * @return void
	 */
	public function cmb2_event_metaboxes() {
		$prefix = 'event_';

		$cmb = new_cmb2_box(
			array(
				'id'           => $prefix . 'metabox',
				'title'        => __( 'Event Details', 'openagenda-base' ),
				'object_types' => array( 'event' ),
				'context'      => 'normal',
				'priority'     => 'low',
				'show_names'   => true,
				'cmb_styles'   => true, // Enable CMB2 styles.
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'             => __( 'Language', 'openagenda-base' ) . self::required(),
				'id'               => $prefix . 'language',
				'type'             => 'select',
				'desc'             => __( 'Select the language of the event', 'openagenda-base' ),
				'attributes'       => array(
					'required' => 'required',
				),
				'default'          => 'nl_NL',
				'options_cb'       => array( $this, 'cmb2_dropdown_languages' ),
				'show_option_none' => false,
				'show_in_rest'     => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Teaser', 'openagenda-base' ) . self::required(),
				'id'           => $prefix . 'teaser',
				'type'         => 'text',
				'desc'         => __( 'Enter a teaser text for the event', 'openagenda-base' ),
				'attributes'   => array(
					'required' => 'required',
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Description', 'openagenda-base' ) . self::required(),
				'id'           => $prefix . 'description',
				'type'         => 'wysiwyg',
				'desc'         => __( 'Enter a description for the event', 'openagenda-base' ),
				'options'      => array(
					'media_buttons' => false,
				),
				'attributes'   => array(
					'required' => 'required',
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Organizer', 'openagenda-base' ) . self::required(),
				'id'           => $prefix . 'organizer',
				'type'         => 'text',
				'desc'         => __( 'Enter the name of the organizer', 'openagenda-base' ),
				'attributes'   => array(
					'required' => 'required',
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Contact person name', 'openagenda-base' ),
				'id'           => $prefix . 'contact_person',
				'type'         => 'text',
				'desc'         => __( 'Enter the name of the contact person', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Phone Number', 'openagenda-base' ),
				'id'           => $prefix . 'phone_number',
				'type'         => 'text',
				'desc'         => __( 'Enter the phone number of the organizer or contact person', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Phone number public', 'openagenda-base' ),
				'id'           => $prefix . 'phone_number_public',
				'type'         => 'checkbox',
				'desc'         => __( 'Should the phone number be public?', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Email Address', 'openagenda-base' ),
				'id'           => $prefix . 'email_address',
				'type'         => 'text',
				'desc'         => __( 'Enter the email address of the organizer or contact person', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Email address public', 'openagenda-base' ),
				'id'           => $prefix . 'email_address_public',
				'type'         => 'checkbox',
				'desc'         => __( 'Should the email address be public?', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'             => __( 'Location', 'openagenda-base' ),
				'id'               => $prefix . 'location',
				'type'             => 'select',
				'desc'             => __( 'Choose the location of the event from the location post type or enter the location address, zipcode and city separately for this event below.', 'openagenda-base' ),
				'options_cb'       => [ $this, 'cmb2_dropdown_locations' ],
				'show_option_none' => true,
				'none_value'       => __( 'Select a location', 'openagenda-base' ),
				'show_in_rest'     => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Location Address', 'openagenda-base' ),
				'id'           => $prefix . 'location_address',
				'type'         => 'text',
				'desc'         => __( 'Enter the address of the event location', 'openagenda-base' ),
				'attributes'   => array(
					'data-conditional-id'    => $prefix . 'location',
					'data-conditional-value' => wp_json_encode( array( '' ) ),
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Location Zipcode', 'openagenda-base' ),
				'id'           => $prefix . 'location_zipcode',
				'type'         => 'text',
				'desc'         => __( 'Enter the zipcode of the event location', 'openagenda-base' ),
				'attributes'   => array(
					'data-conditional-id'    => $prefix . 'location',
					'data-conditional-value' => wp_json_encode( array( '' ) ),
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Location city', 'openagenda-base' ),
				'id'           => $prefix . 'location_city',
				'type'         => 'text',
				'desc'         => __( 'Enter the city of the event location', 'openagenda-base' ),
				'attributes'   => array(
					'data-conditional-id'    => $prefix . 'location',
					'data-conditional-value' => wp_json_encode( array( '' ) ),
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Location Description', 'openagenda-base' ),
				'id'           => $prefix . 'location_description',
				'type'         => 'wysiwyg',
				'desc'         => __( 'Enter a description of the event location', 'openagenda-base' ),
				'options'      => array(
					'media_buttons' => false,
					'teeny'         => true,
				),
				'attributes'   => array(
					'data-conditional-id'    => $prefix . 'location',
					'data-conditional-value' => wp_json_encode( array( '' ) ),
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Itinerary', 'openagenda-base' ),
				'id'           => $prefix . 'itinerary',
				'type'         => 'textarea',
				'desc'         => __( 'Enter the event itinerary', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Publicity', 'openagenda-base' ),
				'id'           => $prefix . 'publicity',
				'type'         => 'select',
				'options'      => array(
					'public'        => __( 'Public', 'openagenda-base' ),
					'partly_public' => __( 'Partly public', 'openagenda-base' ),
					'closed'        => __( 'Closed', 'openagenda-base' ),
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Registration', 'openagenda-base' ),
				'id'           => $prefix . 'registration',
				'type'         => 'select',
				'options'      => array(
					'not_required' => __( 'Not Required', 'openagenda-base' ),
					'mandatory'    => __( 'Mandatory', 'openagenda-base' ),
					'optional'     => __( 'Optional', 'openagenda-base' ),
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Registration URL', 'openagenda-base' ),
				'id'           => $prefix . 'registration_url',
				'type'         => 'text_url',
				'attributes'   => array(
					'data-conditional-id'    => $prefix . 'registration',
					'data-conditional-value' => wp_json_encode( array( 'mandatory', 'optional' ) ),
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Accessibility', 'openagenda-base' ),
				'id'           => $prefix . 'accessibility',
				'type'         => 'textarea',
				'desc'         => __( 'Enter information about event accessibility', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Price type', 'openagenda-base' ) . self::required(),
				'id'           => $prefix . 'price_type',
				'type'         => 'select',
				'options'      => array(
					'fixed'   => __( 'Fixed (or free)', 'openagenda-base' ),
					'min'     => __( 'Starting from (minimum only)', 'openagenda-base' ),
					'min_max' => __( 'Range (minimum and maximum)', 'openagenda-base' ),
				),
				'attributes'   => array(
					'required' => 'required',
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Fixed price', 'openagenda-base' ),
				'id'           => $prefix . 'fixed_price',
				'type'         => 'text',
				'desc'         => __( 'Enter the fixed price of the event or 0 if the event is free', 'openagenda-base' ),
				'attributes'   => array(
					'type'                   => 'number',
					'pattern'                => '\d*',
					'min'                    => 0,
					'step'                   => 0.01,
					'data-conditional-id'    => $prefix . 'price_type',
					'data-conditional-value' => wp_json_encode( array( 'fixed' ) ),
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Minimum price', 'openagenda-base' ),
				'id'           => $prefix . 'min_price',
				'type'         => 'text',
				'attributes'   => array(
					'type'                   => 'number',
					'pattern'                => '\d*',
					'min'                    => 0,
					'step'                   => 0.01,
					'data-conditional-id'    => $prefix . 'price_type',
					'data-conditional-value' => wp_json_encode( array( 'min', 'min_max' ) ),
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Maximum price', 'openagenda-base' ),
				'id'           => $prefix . 'max_price',
				'type'         => 'text',
				'attributes'   => array(
					'type'                   => 'number',
					'pattern'                => '\d*',
					'min'                    => 0,
					'step'                   => 0.01,
					'data-conditional-id'    => $prefix . 'price_type',
					'data-conditional-value' => wp_json_encode( array( 'min_max' ) ),
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Event Website URL', 'openagenda-base' ),
				'id'           => $prefix . 'event_website_url',
				'type'         => 'text_url',
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Ticket Website URL', 'openagenda-base' ),
				'id'           => $prefix . 'ticket_website_url',
				'type'         => 'text_url',
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Organizer Website URL', 'openagenda-base' ),
				'id'           => $prefix . 'organizer_website_url',
				'type'         => 'text_url',
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Images', 'openagenda-base' ),
				'id'           => $prefix . 'images',
				'type'         => 'file_list',
				'query_args'   => array( 'type' => 'image' ),
				'show_in_rest' => true,
				'text'         => array(
					'add_upload_files_text' => __( 'Add Image', 'openagenda-base' ),
					'remove_image_text'     => __( 'Remove Image', 'openagenda-base' ),
					'file_text'             => __( 'Image:', 'openagenda-base' ),
					'file_download_text'    => __( 'Download Image', 'openagenda-base' ),
					'remove_text'           => __( 'Remove Image', 'openagenda-base' ),
				),
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Media files', 'openagenda-base' ),
				'id'           => $prefix . 'media_files',
				'type'         => 'file_list',
				'show_in_rest' => true,
				'text'         => array(
					'add_upload_files_text' => __( 'Add File', 'openagenda-base' ),
					'remove_image_text'     => __( 'Remove File', 'openagenda-base' ),
					'file_text'             => __( 'File:', 'openagenda-base' ),
					'file_download_text'    => __( 'Download File', 'openagenda-base' ),
					'remove_text'           => __( 'Remove File', 'openagenda-base' ),
				),
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Video URL', 'openagenda-base' ),
				'id'           => $prefix . 'video_url',
				'type'         => 'text_url',
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Highlighted event', 'openagenda-base' ),
				'id'           => $prefix . 'highlighted',
				'type'         => 'checkbox',
				'desc'         => __( 'Check this box if the event is highlighted', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Long-term event', 'openagenda-base' ),
				'id'           => $prefix . 'longterm',
				'type'         => 'checkbox',
				'desc'         => __( 'Check this box if the event is long-term', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		// DATE INPUT.
		// Add custom meta boxes using CMB2 for event dates.
		$prefix = 'event_dates_';

		$cmb = new_cmb2_box(
			array(
				'id'           => $prefix . 'metabox',
				'title'        => __( 'Event Dates', 'openagenda-base' ),
				'object_types' => array( 'event' ),
				'context'      => 'normal',
				'priority'     => 'low',
				'show_names'   => true,
				'cmb_styles'   => true, // Enable CMB2 styles.
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Recurring Event description', 'openagenda-base' ),
				'id'           => $prefix . 'recurring_description',
				'type'         => 'textarea',
				'desc'         => __( 'Enter the description for the recurring events', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Type', 'openagenda-base' ),
				'id'           => $prefix . 'type',
				'type'         => 'radio',
				'desc'         => __( 'Select the type of date. Based on the selection, open one of the sections below to enter the date information.', 'openagenda-base' ),
				'options'      => array(
					'specific' => __( 'A specific date or date-range', 'openagenda-base' ),
					'complex'  => __( 'A configurable repeating pattern', 'openagenda-base' ),
				),
				'default'      => 'specific',
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Repeats every year', 'openagenda-base' ),
				'id'           => $prefix . 'every_year',
				'type'         => 'checkbox',
				'desc'         => __( 'Check this box if the event repeats every year', 'openagenda-base' ),
				'attributes'   => array(
					'data-conditional-id'    => $prefix . 'type',
					'data-conditional-value' => wp_json_encode( array( 'specific' ) ),
				),
				'show_in_rest' => true,

			)
		);

		$event_dates_group_specific = $cmb->add_field(
			array(
				'id'               => $prefix . 'group_specific',
				'type'             => 'group',
				'options'          => array(
					'group_title'   => __( 'Date {#}', 'openagenda-base' ),
					'add_button'    => __( 'Add Another Date or Date Range', 'openagenda-base' ),
					'remove_button' => __( 'Remove Date', 'openagenda-base' ),
					'sortable'      => true,
					'closed'        => true,
				),
				'show_in_rest'     => true,
				'description'      => '<h3>' . __( 'Specific date or date-range', 'openagenda-base' ) . '</h3>',
				'before_group_row' => '<h4>' . __( 'Click on the title bar with Date # to open the date input', 'openagenda-base' ) . '</h4>',
			)
		);

		$cmb->add_group_field(
			$event_dates_group_specific,
			array(
				'name'         => __( 'Start date', 'openagenda-base' ) . self::required(),
				'id'           => $prefix . 'specific_start_date',
				'type'         => 'text_date',
				'date_format'  => 'd-m-Y',
				'desc'         => __( 'Enter the start date of a singular event or the date from which the event should start repeating for a repeating event', 'openagenda-base' ),
				'attributes'   => array(
					'data-conditional-required' => 'required',
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_group_field(
			$event_dates_group_specific,
			array(
				'name'         => __( 'End date (optional)', 'openagenda-base' ),
				'id'           => $prefix . 'specific_end_date',
				'type'         => 'text_date',
				'date_format'  => 'd-m-Y',
				'desc'         => __( 'Enter the end date of a singular event or the date from which the event should stop repeating for a repeating event', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_group_field(
			$event_dates_group_specific,
			array(
				'name'         => __( 'Start Time (optional)', 'openagenda-base' ),
				'id'           => $prefix . 'specific_start_time',
				'type'         => 'text_time',
				'desc'         => __( 'Select the start time of the event', 'openagenda-base' ),
				'time_format'  => 'H:i',
				'show_in_rest' => true,
			)
		);

		$cmb->add_group_field(
			$event_dates_group_specific,
			array(
				'name'         => __( 'End Time (optional)', 'openagenda-base' ),
				'id'           => $prefix . 'specific_end_time',
				'type'         => 'text_time',
				'desc'         => __( 'Select the end time of the event', 'openagenda-base' ),
				'time_format'  => 'H:i',
				'show_in_rest' => true,
			)
		);

		$event_dates_group_complex = $cmb->add_field(
			array(
				'id'               => $prefix . 'group_complex',
				'type'             => 'group',
				'options'          => array(
					'group_title'   => __( 'Pattern {#}', 'openagenda-base' ),
					'add_button'    => __( 'Add Another Pattern', 'openagenda-base' ),
					'remove_button' => __( 'Remove Pattern', 'openagenda-base' ),
					'sortable'      => true,
					'closed'        => true,
				),
				'show_in_rest'     => true,
				'description'      => '<h3>' . __( 'Configurable repeating pattern', 'openagenda-base' ) . '</h3>',
				'before_group_row' => '<h4>' . __( 'Click on the title bar with Pattern # to open the pattern input', 'openagenda-base' ) . '</h4>',
			)
		);

		$cmb->add_group_field(
			$event_dates_group_complex,
			array(
				'name'         => __( 'Pattern start date', 'openagenda-base' ) . self::required(),
				'id'           => $prefix . 'complex_start_date',
				'type'         => 'text_date',
				'date_format'  => 'd-m-Y',
				'desc'         => __( 'Enter the start date of a singular event or the date from which the event should start repeating for a repeating event', 'openagenda-base' ),
				'attributes'   => array(
					'data-conditional-required' => 'required',
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_group_field(
			$event_dates_group_complex,
			array(
				'name'         => __( 'Pattern end date', 'openagenda-base' ),
				'id'           => $prefix . 'complex_end_date',
				'type'         => 'text_date',
				'date_format'  => 'd-m-Y',
				'desc'         => __( 'Enter the end date of a singular event or the date from which the event should stop repeating for a repeating event', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_group_field(
			$event_dates_group_complex,
			array(
				'name'         => __( 'Pattern Start Time (optional)', 'openagenda-base' ),
				'id'           => $prefix . 'complex_start_time',
				'type'         => 'text_time',
				'desc'         => __( 'Select the start time of the event', 'openagenda-base' ),
				'time_format'  => 'H:i',
				'show_in_rest' => true,
			)
		);

		$cmb->add_group_field(
			$event_dates_group_complex,
			array(
				'name'         => __( 'Pattern End Time (optional)', 'openagenda-base' ),
				'id'           => $prefix . 'complex_end_time',
				'type'         => 'text_time',
				'desc'         => __( 'Select the end time of the event', 'openagenda-base' ),
				'time_format'  => 'H:i',
				'show_in_rest' => true,
			)
		);

		$cmb->add_group_field(
			$event_dates_group_complex,
			array(
				'name'         => __( 'Repeat pattern', 'openagenda-base' ),
				'id'           => $prefix . 'complex_weekday_occurrence',
				'type'         => 'select',
				'options'      => array(
					'every'  => __( 'Every', 'openagenda-base' ),
					'first'  => __( 'Every first', 'openagenda-base' ),
					'second' => __( 'Every second', 'openagenda-base' ),
					'third'  => __( 'Every third', 'openagenda-base' ),
					'fourth' => __( 'Every fourth', 'openagenda-base' ),
					'last'   => __( 'Every last', 'openagenda-base' ),
				),
				'show_in_rest' => true,
			)
		);

		$days_of_week = [
			'monday'    => __( 'Monday', 'openagenda-base' ),
			'tuesday'   => __( 'Tuesday', 'openagenda-base' ),
			'wednesday' => __( 'Wednesday', 'openagenda-base' ),
			'thursday'  => __( 'Thursday', 'openagenda-base' ),
			'friday'    => __( 'Friday', 'openagenda-base' ),
			'saturday'  => __( 'Saturday', 'openagenda-base' ),
			'sunday'    => __( 'Sunday', 'openagenda-base' ),
		];

		$cmb->add_group_field(
			$event_dates_group_complex,
			array(
				'name'         => __( 'Day(s) of the week', 'openagenda-base' ),
				'id'           => $prefix . 'complex_weekdays',
				'type'         => 'multicheck',
				'description'  => __( 'None means daily', 'openagenda-base' ),
				'options'      => $days_of_week,
				'show_in_rest' => true,
			)
		);

		foreach ( $days_of_week as $day_key => $day_label ) {
			$cmb->add_group_field(
				$event_dates_group_complex,
				array(
					// translators: %s is the day of the week, e.g. Monday.
					'name'         => sprintf( __( 'Start time of %s (optional)', 'openagenda-base' ), $day_label ),
					'id'           => $prefix . 'complex_start_time_' . $day_key,
					'type'         => 'text_time',
					// translators: %s is the day of the week, e.g. Monday.
					'desc'         => sprintf( __( 'Select the start time for %s. If you don\'t select a start time but the event occurs on this day, then the general start time is used for this day.', 'openagenda-base' ), $day_label ),
					'time_format'  => 'H:i',
					'show_in_rest' => true,
				)
			);

			$cmb->add_group_field(
				$event_dates_group_complex,
				array(
					// translators: %s is the day of the week, e.g. Monday.
					'name'         => sprintf( __( 'End time of %s (optional)', 'openagenda-base' ), $day_label ),
					'id'           => $prefix . 'complex_end_time_' . $day_key,
					'type'         => 'text_time',
					// translators: %s is the day of the week, e.g. Monday.
					'desc'         => sprintf( __( 'Select the end time for %s. If you don\'t select an end time but the event occurs on this day, then the general end time is used for this day.', 'openagenda-base' ), $day_label ),
					'time_format'  => 'H:i',
					'show_in_rest' => true,
				)
			);
		}

		$cmb->add_group_field(
			$event_dates_group_complex,
			array(
				'name'         => __( 'Month(s) of the year', 'openagenda-base' ),
				'id'           => $prefix . 'complex_months',
				'type'         => 'multicheck',
				'description'  => __( 'None means monthly', 'openagenda-base' ),
				'options'      => array(
					'january'   => __( 'January', 'openagenda-base' ),
					'february'  => __( 'February', 'openagenda-base' ),
					'march'     => __( 'March', 'openagenda-base' ),
					'april'     => __( 'April', 'openagenda-base' ),
					'may'       => __( 'May', 'openagenda-base' ),
					'june'      => __( 'June', 'openagenda-base' ),
					'july'      => __( 'July', 'openagenda-base' ),
					'august'    => __( 'August', 'openagenda-base' ),
					'september' => __( 'September', 'openagenda-base' ),
					'october'   => __( 'October', 'openagenda-base' ),
					'november'  => __( 'November', 'openagenda-base' ),
					'december'  => __( 'December', 'openagenda-base' ),
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Exclude dates from repeating pattern', 'openagenda-base' ),
				'id'           => $prefix . 'repeating_exclude_date',
				'type'         => 'text_date',
				'date_format'  => 'd-m-Y',
				'desc'         => __( 'Enter the dates to exclude from the repeating pattern', 'openagenda-base' ),
				'show_in_rest' => true,
				'repeatable'   => true,
				'before_row'   => '<h3>' . __( 'Enter dates to exclude from the repeating pattern, f.e. holidays', 'openagenda-base' ) . '</h3><hr></p>',
			)
		);
	}

	/**
	 * Register the CMB2 metaboxes for the Location post type
	 *
	 * @return void
	 */
	public function cmb2_location_metaboxes() {
		$prefix = 'location_';

		$cmb = new_cmb2_box(
			array(
				'id'           => $prefix . 'metabox',
				'title'        => __( 'Location Details', 'openagenda-base' ),
				'object_types' => array( 'location' ),
				'context'      => 'normal',
				'priority'     => 'low',
				'show_names'   => true,
				'cmb_styles'   => true, // Enable CMB2 styles.
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'             => __( 'Language', 'openagenda-base' ) . self::required(),
				'id'               => $prefix . 'language',
				'type'             => 'select',
				'desc'             => __( 'Select the language of the event', 'openagenda-base' ),
				'attributes'       => array(
					'required' => 'required',
				),
				'options_cb'       => array( $this, 'cmb2_dropdown_languages' ),
				'default'          => 'nl_NL',
				'show_option_none' => true,
				'show_in_rest'     => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Location Address', 'openagenda-base' ) . self::required(),
				'id'           => $prefix . 'address',
				'type'         => 'text',
				'desc'         => __( 'Enter the street and street number of the location', 'openagenda-base' ),
				'attributes'   => array(
					'required' => 'required',
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Location Zipcode', 'openagenda-base' ) . self::required(),
				'id'           => $prefix . 'zipcode',
				'type'         => 'text',
				'desc'         => __( 'Enter the zipcode of the location', 'openagenda-base' ),
				'attributes'   => array(
					'required' => 'required',
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Location City', 'openagenda-base' ) . self::required(),
				'id'           => $prefix . 'city',
				'type'         => 'text',
				'desc'         => __( 'Enter the city of the location', 'openagenda-base' ),
				'attributes'   => array(
					'required' => 'required',
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Description', 'openagenda-base' ),
				'id'           => $prefix . 'description',
				'type'         => 'wysiwyg',
				'desc'         => __( 'Description of the location', 'openagenda-base' ),
				'options'      => array(
					'media_buttons' => false,
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Phone Number', 'openagenda-base' ),
				'id'           => $prefix . 'phone_number',
				'type'         => 'text',
				'desc'         => __( 'Enter the phone number of the organizer or contact person', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Phone number public', 'openagenda-base' ),
				'id'           => $prefix . 'phone_number_public',
				'type'         => 'checkbox',
				'default'      => 'on',
				'desc'         => __( 'Should the phone number be public?', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Email Address', 'openagenda-base' ),
				'id'           => $prefix . 'email_address',
				'type'         => 'text',
				'desc'         => __( 'Enter the email address of the organizer or contact person', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Email address public', 'openagenda-base' ),
				'id'           => $prefix . 'email_address_public',
				'type'         => 'checkbox',
				'default'      => 'on',
				'desc'         => __( 'Should the email address be public?', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		$cmb->add_field(
			array(
				'name'         => __( 'Website URL', 'openagenda-base' ),
				'id'           => $prefix . 'website_url',
				'type'         => 'text_url',
				'desc'         => __( 'Enter the website URL of the location', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		// Create a new group with repeatable fields for social media channels.
		$cmb = new_cmb2_box(
			array(
				'id'           => $prefix . 'social_media_box',
				'title'        => __( 'Social media', 'openagenda-base' ),
				'object_types' => array( 'location' ),
				'context'      => 'normal',
				'priority'     => 'low',
				'show_names'   => true,
				'cmb_styles'   => true, // Enable CMB2 styles.
				'show_in_rest' => true,
			)
		);

		$social_media_group = $cmb->add_field(
			array(
				'id'           => $prefix . 'social_media',
				'type'         => 'group',
				'options'      => array(
					'group_title'   => __( 'Social media channel {#}', 'openagenda-base' ),
					'add_button'    => __( 'Add Another channel', 'openagenda-base' ),
					'remove_button' => __( 'Remove channel', 'openagenda-base' ),
					'sortable'      => true,
				),
				'show_in_rest' => true,
			)
		);

		$cmb->add_group_field(
			$social_media_group,
			array(
				'name'             => __( 'Channel', 'openagenda-base' ),
				'id'               => $prefix . 'social_media_channel_name',
				'type'             => 'select',
				'desc'             => __( 'Select the social media channel', 'openagenda-base' ),
				'options'          => array(
					'facebook'  => __( 'Facebook', 'openagenda-base' ),
					'twitter'   => __( 'Twitter', 'openagenda-base' ),
					'instagram' => __( 'Instagram', 'openagenda-base' ),
					'linkedin'  => __( 'LinkedIn', 'openagenda-base' ),
					'youtube'   => __( 'YouTube', 'openagenda-base' ),
					'pinterest' => __( 'Pinterest', 'openagenda-base' ),
					'tiktok'    => __( 'TikTok', 'openagenda-base' ),
					'snapchat'  => __( 'Snapchat', 'openagenda-base' ),
					'whatsapp'  => __( 'WhatsApp', 'openagenda-base' ),
					'telegram'  => __( 'Telegram', 'openagenda-base' ),
					'signal'    => __( 'Signal', 'openagenda-base' ),
					'discord'   => __( 'Discord', 'openagenda-base' ),
					'twitch'    => __( 'Twitch', 'openagenda-base' ),
					'other'     => __( 'Other', 'openagenda-base' ),
				),
				'show_option_none' => true,
				'show_in_rest'     => true,
			)
		);

		$cmb->add_group_field(
			$social_media_group,
			array(
				'name'         => __( 'Channel URL', 'openagenda-base' ),
				'id'           => $prefix . 'social_media_channel_url',
				'type'         => 'text_url',
				'desc'         => __( 'Enter the social media channel URL', 'openagenda-base' ),
				'show_in_rest' => true,
			)
		);

		// Create fields for openings hours.
		$cmb = new_cmb2_box(
			array(
				'id'           => $prefix . 'opening_hours_box',
				'title'        => __( 'Opening hours', 'openagenda-base' ),
				'object_types' => array( 'location' ),
				'context'      => 'normal',
				'priority'     => 'low',
				'show_names'   => true,
				'cmb_styles'   => true, // Enable CMB2 styles.
				'show_in_rest' => true,
			)
		);

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

			$cmb->add_field(
				array(
					'name'         => __( 'Opening time', 'openagenda-base' ),
					'id'           => $prefix . $day . '_opening_hours_open',
					'type'         => 'text_time',
					'desc'         => __( 'Select the opening time. Set to 00:00 to remove after input.', 'openagenda-base' ),
					'time_format'  => 'H:i',
					'before_row'   => '<h3>' . ucfirst( $day ) . '</h3>',
					'show_in_rest' => true,
				)
			);

			$cmb->add_field(
				array(
					'name'         => __( 'Closing time', 'openagenda-base' ),
					'id'           => $prefix . $day . '_opening_hours_close',
					'type'         => 'text_time',
					'desc'         => __( 'Select the closing time. Set to 00:00 to remove after input.', 'openagenda-base' ),
					'time_format'  => 'H:i',
					'show_in_rest' => true,
				)
			);

		}

		$cmb->add_field(
			array(
				'name'         => __( 'Extra information opening hours', 'openagenda-base' ),
				'id'           => $prefix . 'opening_hours_extra',
				'type'         => 'text',
				'desc'         => __( 'Enter extra information about the opening hours', 'openagenda-base' ),
				'before_row'   => '<h3>' . __( 'Extra information', 'openagenda-base' ) . '</h3>',
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Get the available languages for the dropdown
	 *
	 * @return array|bool
	 */
	public function cmb2_dropdown_languages() {
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';

		$translations = wp_get_available_translations();

		if ( ! $translations ) {
			return array(
				'en_US' => __( 'English (United States)', 'openagenda-base' ),
				'nl_NL' => __( 'Dutch (Netherlands)', 'openagenda-base' ),
				'fr_FR' => __( 'French (France)', 'openagenda-base' ),
				'de_DE' => __( 'German (Germany)', 'openagenda-base' ),
				'it_IT' => __( 'Italian (Italy)', 'openagenda-base' ),
			);
		}

		$options = array(
			'' => __( 'Select a language', 'openagenda-base' ),
		);
		foreach ( $translations as $translation ) {
			$options[ $translation['language'] ] = esc_html( $translation['native_name'] );
		}

		return $options;
	}

	/**
	 * Get the locations from the locations post type.
	 *
	 * @param array $query_args The query arguments.
	 *
	 * @return array
	 */
	public function cmb2_dropdown_locations( $query_args ) {

		$args = wp_parse_args(
			$query_args,
			array(
				'post_type'   => 'location',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$posts = get_posts( $args );

		$post_options = array();
		if ( $posts ) {
			foreach ( $posts as $post ) {
				$post_options[ $post->ID ] = $post->post_title;
			}
		}

		return $post_options;
	}

	/**
	 * Add a nonce field to the CMB2 form
	 *
	 * @return void
	 */
	public function add_nonce_field() {
		wp_nonce_field( 'openagenda_cmb2_nonce', 'openagenda_cmb2_nonce' );
	}
}
