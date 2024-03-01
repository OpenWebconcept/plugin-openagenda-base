<?php
/**
 * The controller for everything that has to do with event dates.
 *
 * @package    Openagenda_Base_Plugin
 * @subpackage Openagenda_Base_Plugin/Admin
 * @author     Acato <eyal@acato.nl>
 */

namespace Openagenda_Base_Plugin\Admin;

/**
 * Event_Dates class.
 */
class Event_Dates {
	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		add_action( 'manage_event_posts_custom_column', array( $this, 'custom_columns' ), 10, 2 );
		add_filter( 'manage_event_posts_columns', array( $this, 'manage_event_posts_columns' ) );

		// These methods are intentionally static, as they are utility in an OOP class.
		// Schedule event processor.
		add_action( 'openagenda_cron_event_weekly', array( $this, 'cron_weekly' ) );
		if ( ! wp_next_scheduled( 'openagenda_cron_event_weekly' ) ) {
			wp_schedule_event( strtotime( 'NEXT SUNDAY' ), 'weekly', 'openagenda_cron_event_weekly' );
		}
		// Update event data after save.
		add_action( 'save_post_event', array( $this, 'save_handler' ), 10, 1 );
	}

	/**
	 * CRON: Every week re-populate the event dates meta.
	 *
	 * @return void
	 */
	public function cron_weekly() {
		global $wpdb;
		$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'event'" );
		foreach ( $post_ids as $post_id ) {
			$this->save_handler( $post_id );
		}

		// Clear the wp-rest-cache.
		if ( class_exists( \Caching::class ) ) {
			\Caching::get_instance()->delete_cache_by_endpoint( '%/openagenda/v1/items', \Caching::FLUSH_LOOSE, true );
		}
	}

	/**
	 * Add custom column to the post list.
	 *
	 * @param array $columns The columns.
	 *
	 * @return array Modified columns.
	 */
	public function manage_event_posts_columns( $columns ) {
		return array_merge(
			$columns,
			array( 'event_date' => __( 'Event Date', 'openagenda-base' ) ),
			array( 'excluded_dates' => __( 'Excluded Dates', 'openagenda-base' ) )
		);
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
		if ( 'event_date' === $column_key ) {
			echo esc_html( $this->date_string( $post_id, true ) );
			echo '<br />';
			echo esc_html( $this->time_string( $post_id ) );
		} elseif ( 'excluded_dates' === $column_key ) {
			// JSON decode the excluded dates.
			$excluded_dates = get_post_meta( $post_id, 'event_dates_repeating_exclude_date', true );
			if ( ! empty( $excluded_dates ) ) {
				$excluded_dates = array_map( array( $this, 'readable_date' ), $excluded_dates );
				echo esc_html( implode( ', ', $excluded_dates ) );
			}
		}
	}

	/**
	 * On post-save, re-popupate the list of dates for this event.
	 *
	 * @param int $post_id The WP_Post->ID for the event.
	 *
	 * @return void
	 */
	public function save_handler( $post_id ) {
		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( doing_action( 'save_post_event' ) ) {
			// We cannot do this now. The post we are trying to update doesn't exist yet.
			add_action(
				'shutdown',
				function () use ( $post_id ) {
					// Do the save-actions.
					$this->save_handler( $post_id );
					// And clear the wp-rest-cache.
					if ( class_exists( \Caching::class ) ) {
						\Caching::get_instance()->delete_cache_by_endpoint( '%/openagenda/v1/items', \Caching::FLUSH_LOOSE, true );
					}
				}
			);

			return;
		}

		$openagenda_event_date_list = $this->create_date_list( $post_id, 'Y-m-d' );
		delete_post_meta( $post_id, '_openagenda_event_date_list' );
		foreach ( $openagenda_event_date_list as $openagenda_event_date ) {
			add_post_meta( $post_id, '_openagenda_event_date_list', $openagenda_event_date );
		}

		$openagenda_event_date_time_list = $this->create_date_list( $post_id, 'Y-m-d', null, 'times' );
		delete_post_meta( $post_id, '_openagenda_event_date_time_list' );
		foreach ( $openagenda_event_date_time_list as $openagenda_event_date_time ) {
			add_post_meta( $post_id, '_openagenda_event_date_time_list', $openagenda_event_date_time );
		}
		// UPDATE POST_EXPIRATION DATA.

		// By nature of a loop, the last element is the very last date.
		$event_date = $openagenda_event_date_list ? max( $openagenda_event_date_list ) : null;
		if ( $event_date ) {
			$expiration_date = date_i18n( 'Y-m-d H:i:s', strtotime( $event_date . ' + 1 day' ) );

			update_post_meta( $post_id, '_expire_date', $expiration_date );
			update_post_meta( $post_id, '_newly_created', '0' );
		}
	}

	/**
	 * Generates all the dates for this event.
	 *
	 * @param int        $post_id            The WP_Post->ID for the event.
	 * @param string     $format             Can be 'timestamp', 'readable' or a PHP Date function style date-format.
	 * @param array|null $internal_meta_item For internal use only. Instead of working on ALL items, just work on THIS date-section only.
	 * @param string     $return_type        Can be 'dates' or 'times'. If 'times', the function will return the start and end times for each date.
	 *
	 * @return int[]|string[] The dates in timestamp or string format.
	 */
	private function create_date_list( $post_id, $format = 'timestamp', $internal_meta_item = null, $return_type = 'dates' ) {

		$prefix = 'event_dates_';

		$dates           = array();
		$dates_times     = array();
		$dates_type      = get_post_meta( $post_id, $prefix . 'type', true );
		$date_meta_field = 'complex' === $dates_type ? $prefix . 'group_complex' : $prefix . 'group_specific';

		$candidates = $internal_meta_item ? array( $internal_meta_item ) : ( get_post_meta( $post_id, $date_meta_field, true ) ? get_post_meta( $post_id, $date_meta_field, true ) : array() );

		foreach ( $candidates as $meta ) {
			$type                = $dates_type;
			$meta['repeat_days'] = $meta[ $prefix . 'complex_weekdays' ] ? $meta[ $prefix . 'complex_weekdays' ] : array();
			if ( empty( $meta['repeat_days'] ) ) {
				$meta['repeat_days'] = self::all_days();
			}
			if ( 'complex' === $type && $meta['repeat_days'] && count( $meta['repeat_days'] ) > 0 ) {

				// Holds all years this event is on. within the limits of -1 through +1 years relative to "this year".
				$triggered_years = range( gmdate( 'Y' ) - 1, gmdate( 'Y' ) + 1 );

				// Holds all months this event is on.
				$triggered_months = self::all_months();
				if ( ( $meta[ $prefix . 'complex_months' ] ) && count( $meta[ $prefix . 'complex_months' ] ) > 0 ) {
					$triggered_months = array_intersect( $triggered_months, $meta[ $prefix . 'complex_months' ] );
				}

				// Holds all week-days this event is on.
				$triggered_days        = array_intersect( self::all_days(), $meta['repeat_days'] );
				$triggered_occurrences = array_intersect( self::all_occurences(), (array) $meta[ $prefix . 'complex_weekday_occurrence' ] );
				if ( empty( $triggered_occurrences ) ) {
					$triggered_occurrences = self::all_occurences();
				}

				// Start and End of the list, absolute boundaries to prevent over-taxing.
				$absolute_first_date      = gmdate( 'Y' ) - 1 . '-01-01';
				$absolute_last_date       = gmdate( 'Y' ) + 1 . '-12-31';
				$absolute_first_timestamp = strtotime( $absolute_first_date );
				$absolute_last_timestamp  = strtotime( $absolute_last_date );

				// Boundaries.
				$first_date_to_process      = $meta[ $prefix . 'complex_start_date' ] ? $meta[ $prefix . 'complex_start_date' ] : false;
				$first_date_to_process      = $first_date_to_process ? $first_date_to_process : $absolute_first_date;
				$first_timestamp_to_process = strtotime( $first_date_to_process );
				$last_date_to_process       = $meta[ $prefix . 'complex_end_date' ] ? $meta[ $prefix . 'complex_end_date' ] : false;
				$last_date_to_process       = $last_date_to_process ? $last_date_to_process : $absolute_last_date;
				$last_timestamp_to_process  = strtotime( $last_date_to_process );

				// Respect absolute boundaries.
				$first_timestamp_to_process = max( $first_timestamp_to_process, $absolute_first_timestamp );
				$last_timestamp_to_process  = min( $last_timestamp_to_process, $absolute_last_timestamp );

				// Build "last of the month" day number list;.
				$last_of_the_month = array();
				$start_year        = gmdate( 'Y', $first_timestamp_to_process );
				$end_year          = gmdate( 'Y', $last_timestamp_to_process );
				foreach ( range( $start_year, $end_year ) as $the_year ) {
					foreach ( range( 1, 12 ) as $month_number ) {
						$last_of_the_month[ (int) $the_year ][ $month_number ] = gmdate( 't', strtotime( sprintf( '%d-%02d-01', $the_year, $month_number ) ) ) - 6;
					}
				}

				// Now render all applicable dates (timestamps).
				foreach ( range( $first_timestamp_to_process, $last_timestamp_to_process, DAY_IN_SECONDS ) as $a_timestamp ) {
					// Is first, second etc?
					$current_year       = (int) gmdate( 'Y', $a_timestamp );
					$current_month      = (int) gmdate( 'm', $a_timestamp );
					$current_month_name = self::all_months()[ $current_month - 1 ];
					$current_day        = (int) gmdate( 'w', $a_timestamp ) - 1; // PHP Date is 0-based starting sunday, our list is 0-based starting monday.
					$current_day_index  = $current_day < 0 ? 6 : $current_day;
					$current_day_name   = self::all_days()[ $current_day_index ];

					$nth_of_the_month    = floor( ( gmdate( 'd', $a_timestamp ) - 1 ) / 7 );
					$current_occurrences = array( self::all_occurences()[ $nth_of_the_month ] );
					if ( gmdate( 'd', $a_timestamp ) >= $last_of_the_month[ (int) $current_year ][ (int) $current_month ] ) {
						$current_occurrences[] = 'last';
					}
					// Overlap?
					if ( ! array_intersect( array( $current_year ), $triggered_years ) ) {
						// Wrong year.
						continue;
					}
					if ( ! array_intersect( array( $current_month_name ), $triggered_months ) ) {
						// Wrong month.
						continue;
					}
					if ( ! array_intersect( array( $current_day_name ), $triggered_days ) ) {
						// Wrong day.
						continue;
					}
					if ( ! array_intersect( $current_occurrences, $triggered_occurrences ) ) {
						// Wrong occurrence.
						continue;
					}
					if ( $a_timestamp ) {
						$dates[]       = (int) $a_timestamp;
						$dates_times[] = array(
							'date'       => gmdate( 'Y-m-d', $a_timestamp ),
							'start_time' => $meta[ $prefix . 'complex_start_time' ],
							'end_time'   => $meta[ $prefix . 'complex_end_time' ],
						);
					}
				}
			}

			if ( 'specific' === $type ) {
				$the_timestamp       = strtotime( $meta[ $prefix . 'specific_start_date' ] );
				$start_year_as_setup = gmdate( 'Y', $the_timestamp );
				if ( $meta[ $prefix . 'every_year' ] ) {
					$the_years = range( gmdate( 'Y' ) - 1, gmdate( 'Y' ) + 1 );
				} else {
					$the_years = array( $start_year_as_setup );
				}

				if ( ! empty( $meta[ $prefix . 'specific_end_date' ] ) ) {
					$end_timestamp     = strtotime( $meta[ $prefix . 'specific_end_date' ] );
					$end_year_as_setup = gmdate( 'Y', $end_timestamp );
					$end_year_offset   = $end_year_as_setup - $start_year_as_setup;
				} else {
					// Make data loop-able.
					$end_timestamp     = $the_timestamp;
					$end_year_as_setup = $start_year_as_setup;
					$end_year_offset   = 0;
				}

				foreach ( $the_years as $the_year ) {
					// Process Date.
					$process_timestamp_start = strtotime( $the_year . gmdate( '-m-d', $the_timestamp ) );
					$process_timestamp_end   = strtotime( ( $the_year + $end_year_offset ) . gmdate( '-m-d', $end_timestamp ) );
					foreach ( range( $process_timestamp_start, $process_timestamp_end, DAY_IN_SECONDS ) as $a_timestamp ) {
						$dates[]       = (int) $a_timestamp;
						$dates_times[] = array(
							'date'       => gmdate( 'Y-m-d', $a_timestamp ),
							'start_time' => $meta[ $prefix . 'specific_start_time' ],
							'end_time'   => $meta[ $prefix . 'specific_end_time' ],
						);
					}
				}
			}
		}

		// Exclude the dates which are selected in the field repeating_exclude_date.
		$exclude_dates = get_post_meta( $post_id, $prefix . 'repeating_exclude_date', true );
		if ( ! empty( $exclude_dates ) ) {
			$exclude_dates = array_map(
				function ( $value ) {
					return gmdate( 'Y-m-d', strtotime( $value ) );
				},
				$exclude_dates
			);
			$dates         = array_diff( $dates, $exclude_dates );
			$dates_times   = array_filter(
				$dates_times,
				function ( $item ) use ( $exclude_dates ) {
					return ! in_array( $item['date'], $exclude_dates, true );
				}
			);
		}

		if ( $format && 'timestamp' !== $format ) {
			if ( 'readable' === $format ) {
				$format = array( self::class, 'readable_date' );
			}
			if ( is_callable( $format ) ) {
				$dates = array_map( $format, $dates );
			} else {
				foreach ( $dates as &$date ) {
					$date = date_i18n( $format, $date );
				}
				unset( $date );
			}
		}

		if ( 'times' === $return_type ) {
			return $dates_times;
		}

		return array_unique( $dates );
	}

	/**
	 * Public access: gives you the list of cached dates for this event. This will be a list of ALL days that match this event.
	 * You should use this only if you need the list itself, but it also gives you a hint on where to find them;
	 *
	 * @param int      $post_id The WP_Post->ID for the event.
	 * @param string[] $limits An array with a 'start' and/or 'end' element containing the first and/or last date to return.
	 *
	 * @return string[] The dates in Y-m-d format.
	 */
	public function get_date_list( $post_id, $limits = array() ) {
		$date_list = get_post_meta( $post_id, '_openagenda_event_date_time_list' );
		if ( array_filter( $limits ) ) {
			if ( ! empty( $limits['start'] ) ) {
				$start     = strtotime( $limits['start'] );
				$date_list = array_filter(
					$date_list,
					function ( $item ) use ( $start ) {
						return strtotime( $item['date'] ) >= $start;
					}
				);
			}
			if ( ! empty( $limits['end'] ) ) {
				$end       = strtotime( $limits['end'] );
				$date_list = array_filter(
					$date_list,
					function ( $item ) use ( $end ) {
						return strtotime( $item['date'] ) <= $end;
					}
				);
			}
		}

		return $date_list;
	}

	/**
	 * Gives you the first date for this event in the future. This will be the first date that matches this event.
	 *
	 * @param int $post_id The WP_Post->ID for the event.
	 * @return mixed|null The date object with date, start_time and end_time or null if no future date is found.
	 */
	public function get_next_date( $post_id ) {
		$date_list = get_post_meta( $post_id, '_openagenda_event_date_time_list' );
		// Get item from array where date is the earliest but not in the past.
		$next_date = null;
		foreach ( $date_list as $date ) {
			if ( strtotime( $date['date'] ) >= strtotime( 'today' ) ) {
				if ( ! $next_date || strtotime( $date['date'] ) < strtotime( $next_date['date'] ) ) {
					$next_date = $date;
				}
			}
		}

		return $next_date;
	}

	/**
	 * Assertion: is this date in this year?
	 *
	 * @param string $date_string A parsable date.
	 *
	 * @return bool
	 */
	private function is_this_year( $date_string ) {
		$date_timestamp = strtotime( $date_string );

		return gmdate( 'Y' ) === gmdate( 'Y', $date_timestamp );
	}

	/**
	 * Internal: Convert a date to a readable string.
	 *
	 * @param int|string $date_string        If INTEGER, a timestamp, if STRING, a parsable date string.
	 * @param bool       $force_year_display If true, the year number is always shown, if false, the year is suppressed if it is the current year.
	 *
	 * @return string
	 */
	private function readable_date( $date_string, $force_year_display = false ) {
		$date_timestamp = is_int( $date_string ) ? $date_string : strtotime( $date_string );
		$format         = _x( 'F jS', 'Readable date without year', 'openagenda-base' );
		if ( ! $this->is_this_year( $date_string ) || $force_year_display ) {
			$format = _x( 'F jS, Y', 'Readable date with year', 'openagenda-base' );
		}

		return date_i18n( $format, $date_timestamp );
	}

	/**
	 * Internal: A list of all relevant values of weekdays.
	 *
	 * @return string[]
	 */
	private static function all_days() {
		return array(
			'monday',
			'tuesday',
			'wednesday',
			'thursday',
			'friday',
			'saturday',
			'sunday',
		);
	}

	/**
	 * Internal: A list of all relevant values of months.
	 *
	 * @return string[]
	 */
	private static function all_months() {
		return array(
			'january',
			'february',
			'march',
			'april',
			'may',
			'june',
			'july',
			'august',
			'september',
			'october',
			'november',
			'december',
		);
	}

	/**
	 * Internal: A list of all relevant values of an occurrence.
	 *
	 * @return string[]
	 */
	private static function all_occurences() {
		return array(
			'first',
			'second',
			'third',
			'fourth',
			'last',
		);
	}

	/**
	 * Internal: Helper to translate an occurrence value to a nice word.
	 *
	 * @param string $occurrence_string One of the words listed in first, second, third, fourth or last.
	 *
	 * @return string
	 */
	private function translate_occurrence( $occurrence_string ) {
		$occurrence_strings = array(
			'first'  => _x( 'first', 'first as in every first monday of the month', 'openagenda-base' ),
			'second' => _x( 'second', 'second as in every second monday of the month', 'openagenda-base' ),
			'third'  => _x( 'third', 'third as in every third monday of the month', 'openagenda-base' ),
			'fourth' => _x( 'fourth', 'fourth as in every fourth monday of the month', 'openagenda-base' ),
			'last'   => _x( 'last', 'last as in every last monday of the month', 'openagenda-base' ),
		);

		return $occurrence_strings[ $occurrence_string ] ? $occurrence_strings[ $occurrence_string ] : '';
	}

	/**
	 * Public access: creates a very nicely and condensed where possible concatenated string.
	 *
	 * @param string[]     $concatenates List of strings to concatenate.
	 *
	 * @param false|string $contract     Can be days or months to contract strings that end the same or are a list of things.
	 *
	 * @return string
	 *
	 * @throws Exception When a translated string is missing markers.
	 */
	public function nice_concat( $concatenates, $contract = false ) {
		// translators: %1$s and %2$s are the start and end of a run of consecutive items, for example monday and friday to become "monday through friday".
		$run_of_items_concatenation = _x( '%1$s through %2$s', 'As monday through friday', 'openagenda-base' );
		if ( ! str_contains( $run_of_items_concatenation, '%1$s' ) || ! str_contains( $run_of_items_concatenation, '%1$s' ) ) {
			throw new Exception( 'Translated string does not contain required placeholders.', 3 );
		}
		if ( ! is_array( $concatenates ) ) {
			return $concatenates;
		}
		if ( 0 === count( $concatenates ) ) {
			return '';
		}

		// Simplify text.
		if ( 2 <= count( $concatenates ) ) {

			// Contract runs of months or days.
			if ( $contract ) {
				$concatenates = $this->contract( $concatenates, $contract, $run_of_items_concatenation );
			}

			$this->simplify( $concatenates );
		}

		if ( 1 === count( $concatenates ) ) {
			return reset( $concatenates );
		}

		$format = _x( 'ITEM1, ITEM2 and ITEM3', 'ITEMx are special tokens, keep those as-is, translate the rest.', 'openagenda-base' );
		if ( ! str_contains( $format, 'ITEM1' ) || ! str_contains( $format, 'ITEM2' ) || ! str_contains( $format, 'ITEM3' ) ) {
			throw new Exception( 'The translation of the format string is wrong. Please make sure the special keywords ITEM1, ITEM2 and ITEM3 are present.', 2 );
		}
		preg_match_all( '/(ITEM[123])/', $format, $matches, PREG_OFFSET_CAPTURE );
		$concatenator      = substr( $format, (int) $matches[1][0][1] + 5, (int) $matches[1][1][1] - (int) $matches[1][0][1] - 5 );
		$nice_concatenator = substr( $format, (int) $matches[1][1][1] + 5, (int) $matches[1][2][1] - (int) $matches[1][1][1] - 5 );

		$rtl = false;
		if ( 'ITEM3' === $matches[1][0][0] ) {
			$rtl = true;
		}
		$last_element = $rtl ? array_shift( $concatenates ) : array_pop( $concatenates );

		return $rtl ? $last_element . $nice_concatenator . implode( $concatenator, $concatenates ) : implode( $concatenator, $concatenates ) . $nice_concatenator . $last_element;
	}

	/**
	 * Internal helper: translate month names.
	 *
	 * @param string[] $month_identifiers List of months to translate, by there INTERNAL name.
	 *
	 * @return array
	 */
	private function translate_months( $month_identifiers ) {
		return array_map( array( $this, 'translate_month' ), $month_identifiers );
	}

	/**
	 * Internal helper: translate a single month name.
	 *
	 * @param string $month_identifier A month identifier.
	 *
	 * @return string
	 */
	private function translate_month( $month_identifier ) {
		// The month_id to month_number using strtotime.
		$timestamp = strtotime( '11th ' . $month_identifier );

		// The translated month output using date_i18n.
		return date_i18n( 'F', $timestamp );
	}

	/**
	 * Internal helper: translate day names.
	 *
	 * @param string[] $day_identifiers List of days to translate, by there INTERNAL name.
	 *
	 * @return string[]
	 */
	private function translate_days( $day_identifiers ) {
		return array_map( array( $this, 'translate_day' ), $day_identifiers );
	}

	/**
	 * Internal helper: translate a single day name.
	 *
	 * @param string $day_identifier A day identifier.
	 *
	 * @return string
	 */
	private function translate_day( $day_identifier ) {
		// The day_id to day_number using strtotime.
		$timestamp = strtotime( 'next ' . $day_identifier );

		// The translated month output using date_i18n.
		return date_i18n( 'l', $timestamp );
	}

	/**
	 * Internal helper: Simplify strings in an array by removing a repeating ending on all but the last item.
	 *
	 * @param string[] $array_strings List of strings to simplify.
	 * @param int      $max_recursion To prevent lockup, we allow a maximum of 40 steps by default.
	 *
	 * @return void
	 */
	private function simplify( &$array_strings, $max_recursion = 40 ) {
		// Max recursion reached.
		if ( ! $max_recursion ) {
			return;
		}
		if ( ! is_array( $array_strings ) || count( $array_strings ) < 2 ) {
			return;
		}
		$common_end = '';
		// Make all elements arrays of words.
		$array_words = array_map(
			function ( $item ) {
				return explode( ' ', $item );
			},
			$array_strings
		);

		// Find words that are in all arrays at the end.
		$a_common_word = end( $array_words[0] );
		// Keep track of the number of items that should have this common ending.
		$n_with_common_word = count( $array_words );
		foreach ( $array_words as $item ) {
			if ( end( $item ) === $a_common_word ) {
				--$n_with_common_word;
			}
		}

		// All items have this word in common.
		if ( 0 === $n_with_common_word ) {

			$common_end = $a_common_word;
			// Back to string so we can try simplifying again.
			foreach ( $array_words as &$item ) {
				array_pop( $item );
				$item = implode( ' ', $item );
			}

			$this->simplify( $array_words, $max_recursion - 1 );
			$array_words[ count( $array_words ) - 1 ] .= ' ' . $common_end;

			$array_strings = $array_words;
		}
	}

	/**
	 * Internal helper: contract a run of words to a shortlist.
	 *
	 * @param string[] $items        Items to shorten.
	 * @param string   $type         'days' or 'months'. Nothing else is supported.
	 * @param string   $join_pattern A join pattern for the shortlist.
	 *
	 * @return string[]
	 */
	private function contract( $items, $type, $join_pattern = '%s - %s' ) {
		// Sort.
		switch ( $type ) {
			case 'days':
				// the intersection will sort.
				$master = $this->translate_days( self::all_days() );
				$items  = array_intersect( $master, $items );
				$items  = $this->reduce_elements( $master, $items, $join_pattern );
				break;
			case 'months':
				// the intersection will sort.
				$master = $this->translate_months( self::all_months() );
				$items  = array_intersect( $master, $items );
				$items  = $this->reduce_elements( $master, $items, $join_pattern );
				break;
		}

		return $items;
	}

	/**
	 * Internal Helper: reduce a run of elements to a short list.
	 *
	 * @param string[] $master_list     Allowed values. All others are filtered out.
	 * @param string[] $candidate_array List to be reduced.
	 * @param string[] $join_pattern    A pattern to replace the run with.
	 *
	 * @return array
	 */
	private function reduce_elements( $master_list, $candidate_array, $join_pattern = '%s - %s' ) {
		$result = array();
		$start  = null;
		$end    = null;
		foreach ( $master_list as $value ) {
			if ( in_array( $value, $candidate_array, true ) ) {
				if ( null === $start ) {
					$start = $value;
				}
				$end = $value;
			} elseif ( null !== $start ) {
				if ( $start === $end ) {
					$result[] = $start;
				} else {
					$result[] = sprintf( $join_pattern, $start, $end );
				}
					$start = null;
					$end   = null;
			}
		}
		if ( null !== $start ) {
			if ( $start === $end ) {
				$result[] = $start;
			} else {
				$result[] = sprintf( $join_pattern, $start, $end );
			}
		}

		return $result;
	}

	/**
	 * Public access: returns the magic textual format of the date-selection.
	 *
	 * @param int     $post_id                    The post ID.
	 * @param boolean $long_format                If true, the full format is returned, if false, the short format is returned.
	 * @param string  $short_format_for_this_date If set, the month name will be the long format of the month of the date given by a datestring or integer timestamp in $short_format_for_this_date.
	 *
	 * @return string
	 *
	 * @throws Exception When nice_concat chokes, see nice_concat.
	 */
	public function date_string( $post_id, $long_format = true, $short_format_for_this_date = null ) {
		// IMPORTANT NOTE.
		// date_string ONLY concerns about the DATE.
		// It will NOT take times into consideration.
		// This also means that we CAN shorten multiple consecutive days to a 'x through y' string.

		$prefix = 'event_dates_';

		// Get the information, format for readable.
		// Build string parts.
		$date_string_parts = array();
		// Pre-check; do we have multiple days in succession with the same settings? All items must match $pattern.
		$repeat_yearly = 'on' === get_post_meta( $post_id, $prefix . 'every_year', true );
		$pattern       = array( 'repeat_yearly' => $repeat_yearly );

		$dates_type      = get_post_meta( $post_id, $prefix . 'type', true );
		$date_meta_field = 'complex' === $dates_type ? $prefix . 'group_complex' : $prefix . 'group_specific';
		$event_dates     = get_post_meta( $post_id, $date_meta_field, true );

		$dates      = array();
		$timestamps = array();
		foreach ( $event_dates as $meta ) {
			$type = $dates_type;
			// Only works on date selections, with single date selected.
			if ( 'specific' !== $type || ! empty( $meta[ $prefix . 'complex_end_date' ] ) ) {
				break;
			}
			$test = array( 'repeat_yearly' => $repeat_yearly ? $repeat_yearly : false );
            // @phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison -- Intentional loose array matching.
			if ( $test === $pattern ) {
				$dates[]      = $meta['event_date_start'];
				$timestamps[] = strtotime( $meta['event_date_start'] );
			}
		}
		sort( $timestamps );
		// Assertion.
		if ( count( $timestamps ) > 1 && count( $timestamps ) === count( $event_dates ) && range( reset( $timestamps ), end( $timestamps ), DAY_IN_SECONDS ) === $timestamps ) {
			// YES! Days are consecutive and have identical date settings.
			$event_dates                   = array( $event_dates[0] );
			$event_dates['event_date_end'] = end( $dates );
		}
		// End of consecutive-date-consolidation.

		$string_index = 0;

		foreach ( $event_dates as $meta ) {
			++$string_index;

			$type                = $dates_type;
			$meta['repeat_days'] = $meta[ $prefix . 'complex_weekdays' ] ? $meta[ $prefix . 'complex_weekdays' ] : array();
			if ( empty( $meta['repeat_days'] ) ) {
				$meta['repeat_days'] = self::all_days();
			}
			if ( 'complex' === $type && ( $meta['repeat_days'] ) && count( $meta['repeat_days'] ) > 0 ) {
				$number_insert = $this->translate_occurrence( $meta[ $prefix . 'complex_weekday_occurrence' ] );

				$number_insert = $number_insert ? ' ' . $number_insert . ' ' : ' ';

				// The generic ... of the month. suffix only applies when "first", "second" etc., but not with every.
				// If it is every monday of every month, then it's just every monday.
				$month_suffix = trim( $number_insert ) ? _x( 'of the month', 'of the month as in Every last friday of the month', 'openagenda-base' ) : '';

				if ( ( $meta[ $prefix . 'complex_months' ] ) && count( $meta[ $prefix . 'complex_months' ] ) > 0 ) {
					$month_suffix = _x( 'of', 'of as in Every last friday of January', 'openagenda-base' ) . ' ' . $this->nice_concat( $this->translate_months( $meta[ $prefix . 'complex_months' ] ) );

					if ( ! $long_format ) { // only list "current month".
						if ( ! empty( $short_format_for_this_date ) ) {
							// long format month name for the date given by a datestring or integer timestamp in $short_format_for_this_date.
							$the_month_name = gmdate( 'F', is_int( $short_format_for_this_date ) ? $short_format_for_this_date : strtotime( $short_format_for_this_date ) );
							$month_suffix   = _x( 'of', 'of as in Every last friday of January', 'openagenda-base' ) . ' ' . $this->translate_month( $the_month_name );
						} else {
							$month_suffix = '';
						}
					}
				}

				$full_date_text = lcfirst( _x( 'Every', 'Every as in Every tuesday.', 'openagenda-base' ) ) . $number_insert . $this->nice_concat( $this->translate_days( $meta['repeat_days'] ), 'days' ) . ' ' . $month_suffix;
				// Tweak: replace "Every monday through sunday" with "daily".
				$full_date_text      = str_replace(
					lcfirst( _x( 'Every', 'Every as in Every tuesday.', 'openagenda-base' ) ) . ' ' /* no number insert */ . $this->nice_concat( $this->translate_days( self::all_days() ), 'days' ),
					lcfirst( _x( 'Daily', 'The expression for Every day of the week', 'openagenda-base' ) ),
					$full_date_text
				);
				$date_string_parts[] = $full_date_text;

			}
			if ( 'specific' === $type ) {

				$the_string = '';

				if ( $repeat_yearly && 1 === $string_index ) {
					$the_string = _x( 'Every year on', 'Every year on as in Every year on 1st of January', 'openagenda-base' ) . ' ';
				}

				$the_string .= $this->readable_date( $meta[ $prefix . 'specific_start_date' ] );
				if ( ! empty( $meta[ $prefix . 'specific_end_date' ] ) ) {
					if ( ! $this->is_this_year( $meta[ $prefix . 'specific_end_date' ] ) ) {
						$the_string = $this->readable_date( $meta[ $prefix . 'specific_start_date' ], true );
					}
					// translators: %1$s and %2$s are the start and end of a run of consecutive items, for example monday and friday to become "monday through friday".
					$the_string .= sprintf( _x( '%1$s through %2$s', 'As monday through friday', 'openagenda-base' ), '', '' ) . $this->readable_date( $meta[ $prefix . 'specific_end_date' ] );
				}
				$date_string_parts[] = $the_string;
			}
		}

		// Nice concatenation.
		return ucfirst( trim( $this->nice_concat( $date_string_parts ) ) );
	}

	/**
	 * Public access: Gives the time-string for this event.
	 *
	 * @param int             $post_id       The post ID.
	 * @param bool            $rich          (Default false) If TRUE, a larger display format is used.
	 * @param string|int|null $specific_date A date (string parsable by strtotime or integer timestamp) for which to get the exact time(s).
	 *
	 * @return string
	 */
	public function time_string( $post_id, $rich = false, $specific_date = null ) {
		$time_strings = $this->time_strings( $post_id, $specific_date );
		$time_strings = array_filter( $time_strings );
		// Which variant?
		switch ( true ) {
			case $rich && empty( $time_strings['end'] ) && ! empty( $time_strings['start'] ):
				// translators: %s is a time.
				return sprintf( _x( 'Starts at %s', 'Example: Starts at 17:00', 'openagenda-base' ), $time_strings['start'] );
			case $rich && ! empty( $time_strings['end'] ) && empty( $time_strings['start'] ):
				// translators: %s is a time.
				return sprintf( _x( 'Ends at %s', 'Example: Ends at 23:00', 'openagenda-base' ), $time_strings['end'] );
			case $rich && ! empty( $time_strings['end'] ) && ! empty( $time_strings['start'] ):
				// translators: %s are a time.
				return sprintf( _x( 'From %1$s til %2$s', 'Example: From 17:00 til 23:00', 'openagenda-base' ), $time_strings['start'], $time_strings['end'] );
			case ! $rich && empty( $time_strings['end'] ) && ! empty( $time_strings['start'] ):
				return $time_strings['start'];
			case ! $rich && ! empty( $time_strings['end'] ) && empty( $time_strings['start'] ):
				// translators: %s is a time.
				return sprintf( _x( 'Til %s', 'Example: Til 23:00', 'openagenda-base' ), $time_strings['end'] );
			case ! $rich && ! empty( $time_strings['end'] ) && ! empty( $time_strings['start'] ):
				// translators: %s are a time.
				return sprintf( _x( '%1$s - %2$s', 'Example: 17:00 - 23:00', 'openagenda-base' ), $time_strings['start'], $time_strings['end'] );
		}

		return '';
	}

	/**
	 * Gets the start and end times of an event.
	 *
	 * @param int             $post_id       The post ID.
	 * @param string|int|null $specific_date A date (string parsable by strtotime or integer timestamp) for which to get the exact time(s).
	 *
	 * @return false[]|string[] Always two elements, start and end, either of which can be false or a string in Y-m-d format.
	 */
	private function time_strings( $post_id, $specific_date = null ) {
		// IMPORTANT NOTE.
		// time_string ONLY concerns about the TIME.

		// In case we have multiple days, we state the first known begin and end times, unless we ask for a specific date.
		$times = array(
			'start' => false,
			'end'   => false,
		);

		$event_dates = $this->get_date_list( $post_id );
		// Bail early if the date is not in the list.
		if ( $specific_date ) {
			if ( is_int( $specific_date ) ) {
				$specific_date = gmdate( 'Y-m-d', $specific_date );
			} else {
				$specific_date = gmdate( 'Y-m-d', strtotime( $specific_date ) );
			}

			if ( ! in_array( $specific_date, $event_dates, true ) ) {
				return $times;
			}
		}

		$prefix          = 'event_dates_';
		$dates_type      = get_post_meta( $post_id, $prefix . 'type', true );
		$date_meta_field = 'complex' === $dates_type ? $prefix . 'group_complex' : $prefix . 'group_specific';
		$event_dates     = get_post_meta( $post_id, $date_meta_field, true );

		// No specific date, try to find the best information.
		if ( ! $specific_date ) {
			foreach ( $event_dates as $meta ) {
				if ( 'complex' === $dates_type ) {
					if ( ! empty( $meta[ $prefix . 'complex_start_date' ] ) && ! empty( $meta[ $prefix . 'complex_end_date' ] ) ) {
						$times['start'] = $meta[ $prefix . 'complex_start_time' ];
						$times['end']   = $meta[ $prefix . 'complex_end_time' ];
						break;
					}
				} elseif ( ! empty( $meta[ $prefix . 'specific_start_date' ] ) && ! empty( $meta[ $prefix . 'specific_end_date' ] ) ) {
						$times['start'] = $meta[ $prefix . 'specific_start_time' ];
						$times['end']   = $meta[ $prefix . 'specific_end_time' ];
						break;
				}
			}

			if ( array_filter( $times ) ) { // we didn't fail, we have a full set.
				return $times;
			}
			// We failed to get a full set, now we just find any time we can find.
			foreach ( $event_dates as $meta ) {
				if ( 'complex' === $dates_type ) {
					if ( ! $times['start'] && ! empty( $meta[ $prefix . 'complex_start_time' ] ) ) {
						$times['start'] = $meta[ $prefix . 'complex_start_time' ];
					}
					if ( ! $times['end'] && ! empty( $meta[ $prefix . 'complex_end_time' ] ) ) {
						$times['end'] = $meta[ $prefix . 'complex_end_time' ];
					}
				} else {
					if ( ! $times['start'] && ! empty( $meta[ $prefix . 'specific_start_time' ] ) ) {
						$times['start'] = $meta[ $prefix . 'specific_start_time' ];
					}
					if ( ! $times['end'] && ! empty( $meta[ $prefix . 'specific_end_time' ] ) ) {
						$times['end'] = $meta[ $prefix . 'specific_end_time' ];
					}
				}
			}
			if ( array_filter( $times ) ) {
				return $times;
			}
		} else {
			foreach ( $event_dates as $meta ) {
				$meta_dates = $this->create_date_list( $post_id, 'Y-m-d', $meta );
				if ( in_array( $specific_date, $meta_dates, true ) ) {
					if ( 'complex' === $dates_type ) {
						$times['start'] = $meta[ $prefix . 'complex_start_time' ] ? $meta[ $prefix . 'complex_start_time' ] : false;
						$times['end']   = $meta[ $prefix . 'complex_end_time' ] ? $meta[ $prefix . 'complex_end_time' ] : false;
					} else {
						$times['start'] = $meta[ $prefix . 'specific_start_time' ] ? $meta[ $prefix . 'specific_start_time' ] : false;
						$times['end']   = $meta[ $prefix . 'specific_end_time' ] ? $meta[ $prefix . 'specific_end_time' ] : false;

					}
				}
			}
		}

		return $times;
	}
}
