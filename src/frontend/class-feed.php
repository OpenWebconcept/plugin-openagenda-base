<?php
/**
 * RSS 2.0 feed of upcoming events.
 *
 * @link       https://www.openwebconcept.nl
 * @since      1.3.0
 *
 * @package    Openagenda_Base_Plugin
 * @subpackage Openagenda_Base_Plugin/Frontend
 */

namespace Openagenda_Base_Plugin\Frontend;

use DateTimeImmutable;
use Openagenda_Base_Plugin\Admin\Event_Dates;

/**
 * RSS 2.0 feed of upcoming events, available at /feed/openagenda/.
 *
 * The feed only uses standard RSS 2.0 elements and established modules (content, media, the RSS 1.0 event
 * module), so it can be consumed by any feed reader or newsletter tool. Event data that has no standard element
 * (human-readable date, time and price) is added in the OpenAgenda namespace.
 *
 * The defaults (sorting, number of items, item date) are configured at Settings > OpenAgenda Settings and can be
 * overridden per request with query parameters:
 * - orderby:       event_date|publish_date
 * - order:         asc|desc
 * - limit:         number of items per page (max. 100)
 * - page:          the page, for feeds with more events than the limit; the pages are linked with atom:link
 *                  rel="first|previous|next|last" (RFC 5005) and counted with opensearch:totalResults
 * - {taxonomy}:    comma separated term slugs, e.g. ?thema=muziek,gratis (items match any of the terms)
 * - tax_relation:  AND|OR, how multiple taxonomies are combined (default AND)
 *
 * @package    Openagenda_Base_Plugin
 * @subpackage Openagenda_Base_Plugin/Frontend
 * @author     Acato <remon@acato.nl>
 */
class Feed {
	/**
	 * The feed name, used in the feed url.
	 *
	 * @var string
	 */
	const FEED_NAME = 'openagenda';

	/**
	 * The option holding the feed settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'openagenda_feed';

	/**
	 * Bump this to flush the rewrite rules after changing the feed registration.
	 *
	 * @var string
	 */
	const REWRITE_VERSION = '1';

	/**
	 * The maximum number of items in the feed, to keep the feed request reasonably light.
	 *
	 * @var int
	 */
	const MAX_ITEMS = 100;

	/**
	 * The arguments of the current feed request.
	 *
	 * @var array
	 */
	private $args = array();

	/**
	 * The number of events matching the current feed request, on all pages.
	 *
	 * @var int
	 */
	private $total = 0;

	/**
	 * Whether the hooks are registered, so other instances (e.g. a theme using get_item_data()) don't add them again.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * The If-None-Match header of the feed request, see take_conditional_request_headers().
	 *
	 * @var string
	 */
	private static $client_etag = '';

	/**
	 * The Event_Dates instance; its constructor registers hooks, so only one is created per feed.
	 *
	 * @var Event_Dates|null
	 */
	private $event_dates = null;

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;

		add_action( 'init', array( $this, 'register_feed' ), 20 );
		add_action( 'wp_loaded', array( $this, 'maybe_flush_rewrite_rules' ) );
		add_action( 'parse_request', array( $this, 'take_conditional_request_headers' ) );
		add_filter( 'wp_headers', array( $this, 'remove_core_cache_headers' ), 10, 2 );
		add_filter( 'posts_pre_query', array( $this, 'skip_main_query' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'output_alternate_link' ) );
		add_filter( 'feed_content_type', array( $this, 'filter_feed_content_type' ), 10, 2 );
		add_filter( 'rest_post_dispatch', array( $this, 'add_rest_link_header' ), 10, 3 );
		add_filter( 'rest_namespace_index', array( $this, 'add_rest_namespace_link' ), 10, 2 );
	}

	/**
	 * The default feed settings. The number of items defaults to the site's "Syndication feeds show the most recent" setting.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'orderby'   => 'event_date',
			'order'     => 'asc',
			'limit'     => (int) get_option( 'posts_per_rss', 10 ),
			'item_date' => 'event_date',
		);
	}

	/**
	 * The feed settings, merged with the defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );
	}

	/**
	 * Sanitize the feed settings.
	 *
	 * @param mixed $settings The submitted settings.
	 *
	 * @return array
	 */
	public static function sanitize_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$defaults = self::default_settings();

		return array(
			'orderby'   => self::sanitize_choice( $settings['orderby'] ?? '', array( 'event_date', 'publish_date' ), $defaults['orderby'] ),
			'order'     => self::sanitize_choice( $settings['order'] ?? '', array( 'asc', 'desc' ), $defaults['order'] ),
			'limit'     => self::sanitize_limit( $settings['limit'] ?? '', $defaults['limit'] ),
			'item_date' => self::sanitize_choice( $settings['item_date'] ?? '', array( 'event_date', 'publish_date' ), $defaults['item_date'] ),
		);
	}

	/**
	 * The public url of the feed.
	 *
	 * @return string
	 */
	public static function get_feed_url() {
		return get_feed_link( self::FEED_NAME );
	}

	/**
	 * Register the feed.
	 *
	 * @return void
	 */
	public function register_feed() {
		add_feed( self::FEED_NAME, array( $this, 'render' ) );
	}

	/**
	 * Make sure the feed's rewrite rule exists. Runs on wp_loaded, after all post types and taxonomies are registered,
	 * so the flush doesn't leave out rules registered late on init.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules() {
		if ( get_option( 'openagenda_feed_rewrite_version' ) !== self::REWRITE_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'openagenda_feed_rewrite_version', self::REWRITE_VERSION );
		}
	}

	/**
	 * Whether the request is for this feed.
	 *
	 * @param \WP $wp The WordPress environment.
	 *
	 * @return bool
	 */
	private static function is_feed_request( $wp ) {
		return isset( $wp->query_vars['feed'] ) && self::FEED_NAME === $wp->query_vars['feed'];
	}

	/**
	 * Take over conditional GET for this feed. WordPress answers conditional feed requests itself, with an ETag based
	 * on the last modified post, before the feed is rendered. That ETag misses everything else that changes this feed
	 * (events that have passed, settings, terms), so readers would keep a stale feed. The If-None-Match header is
	 * kept for render(), which compares it with an ETag of the actual output, and removed from the request so
	 * WordPress doesn't answer 304 on its own.
	 *
	 * @param \WP $wp The WordPress environment.
	 *
	 * @return void
	 */
	public function take_conditional_request_headers( $wp ) {
		if ( ! self::is_feed_request( $wp ) ) {
			return;
		}

		self::$client_etag = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
		unset( $_SERVER['HTTP_IF_NONE_MATCH'], $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
	}

	/**
	 * Don't send WordPress's ETag and Last-Modified for this feed, render() sends its own ETag.
	 *
	 * @param array $headers The headers.
	 * @param \WP   $wp      The WordPress environment.
	 *
	 * @return array
	 */
	public function remove_core_cache_headers( $headers, $wp ) {
		if ( self::is_feed_request( $wp ) ) {
			unset( $headers['ETag'] );
			$headers['Last-Modified'] = false;
		}

		return $headers;
	}

	/**
	 * The content type of this feed, also for HEAD requests (which WordPress answers without rendering the feed).
	 *
	 * @param string $content_type The content type.
	 * @param string $type         The feed type.
	 *
	 * @return string
	 */
	public function filter_feed_content_type( $content_type, $type ) {
		return self::FEED_NAME === $type ? 'application/rss+xml' : $content_type;
	}

	/**
	 * Skip WordPress's main query for this feed; render() runs its own query.
	 *
	 * @param array|null $posts The posts, null to run the query.
	 * @param \WP_Query  $query The query.
	 *
	 * @return array|null
	 */
	public function skip_main_query( $posts, $query ) {
		if ( null === $posts && $query->is_main_query() && $query->is_feed( self::FEED_NAME ) ) {
			return array();
		}

		return $posts;
	}

	/**
	 * Advertise the feed in the html head, for feed readers and browsers. On a (filtered) event archive or an event
	 * category archive, the feed is filtered the same way.
	 *
	 * @return void
	 */
	public function output_alternate_link() {
		$feed_url = self::get_feed_url();
		if ( is_post_type_archive( 'event' ) || is_tax( get_object_taxonomies( 'event' ) ) || is_category() || is_tag() ) {
			foreach ( get_object_taxonomies( 'event', 'objects' ) as $taxonomy ) {
				$slugs = $taxonomy->public && $taxonomy->query_var ? get_query_var( $taxonomy->query_var ) : '';
				if ( $slugs && is_string( $slugs ) ) {
					$feed_url = add_query_arg( $taxonomy->name, rawurlencode( $slugs ), $feed_url );
				}
			}
		}

		printf(
			'<link rel="alternate" type="%s" title="%s" href="%s" />' . "\n",
			esc_attr( feed_content_type( 'rss2' ) ),
			esc_attr( $this->get_channel_title() ),
			esc_url( $feed_url )
		);
	}

	/**
	 * Advertise the feed in the responses of the OpenAgenda REST API, with a Link header, for headless sites.
	 *
	 * @param \WP_HTTP_Response $response The response.
	 * @param \WP_REST_Server   $server   The REST server.
	 * @param \WP_REST_Request  $request  The request.
	 *
	 * @return \WP_HTTP_Response
	 */
	public function add_rest_link_header( $response, $server, $request ) {
		if ( in_array( $request->get_method(), array( 'GET', 'HEAD' ), true ) && 0 === strpos( $request->get_route(), '/owc/openagenda/v1' ) && $response instanceof \WP_REST_Response ) {
			// WP_REST_Response::link_header() doesn't escape the parameters, so the header is built here (RFC 8288).
			$title = $this->get_channel_title();
			$link  = sprintf(
				'<%s>; rel="alternate"; type="%s"; title="%s"',
				esc_url_raw( self::get_feed_url() ),
				feed_content_type( 'rss2' ),
				addcslashes( preg_replace( '/[^\x20-\x7E]/u', '?', remove_accents( $title ) ), '"\\' )
			);
			// A title with non-ASCII characters is added as title* (RFC 8187), the title above is the ASCII fallback.
			if ( preg_match( '/[^\x20-\x7E]/', $title ) ) {
				$link .= "; title*=UTF-8''" . rawurlencode( $title );
			}
			$response->header( 'Link', $link, false );
		}

		return $response;
	}

	/**
	 * Add the feed to the index of the OpenAgenda REST namespace (/wp-json/owc/openagenda/v1), as an alternate link.
	 *
	 * @param \WP_REST_Response $response The namespace index.
	 * @param \WP_REST_Request  $request  The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function add_rest_namespace_link( $response, $request ) {
		if ( 'owc/openagenda/v1' === $request['namespace'] ) {
			$response->add_link(
				'alternate',
				self::get_feed_url(),
				array(
					'type'  => feed_content_type( 'rss2' ),
					'title' => $this->get_channel_title(),
				)
			);
		}

		return $response;
	}

	/**
	 * Output the feed.
	 *
	 * @return void
	 */
	public function render() {
		$this->args = $this->get_request_args();
		$events     = $this->get_events( $this->args );
		$items      = array_map( array( $this, 'get_item_data' ), $events );
		// The last change of the items, not the time of the request, so the output (and its ETag) only changes with the content.
		$modified = $events ? max( wp_list_pluck( $events, 'post_modified_gmt' ) ) : '';

		header( 'Content-Type: ' . feed_content_type( 'rss2' ) . '; charset=' . get_option( 'blog_charset' ), true );

		ob_start();
		echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?>' . "\n";
		?>
<rss version="2.0"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:opensearch="http://a9.com/-/spec/opensearch/1.1/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:media="http://search.yahoo.com/mrss/"
	xmlns:ev="http://purl.org/rss/1.0/modules/event/"
	xmlns:openagenda="https://www.openwebconcept.nl/openagenda/feed/"
>
<channel>
	<title><?php echo $this->xml_text( $this->get_channel_title() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by xml_text(). ?></title>
	<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
		<?php foreach ( $this->get_page_links() as $rel => $href ) : ?>
	<atom:link href="<?php echo esc_url( $href ); ?>" rel="<?php echo esc_attr( $rel ); ?>" type="application/rss+xml" />
		<?php endforeach; ?>
	<link><?php echo esc_url( Event_Permalink::get_overview_url() ); ?></link>
	<description><?php bloginfo_rss( 'description' ); ?></description>
	<language><?php bloginfo_rss( 'language' ); ?></language>
		<?php if ( $modified ) : ?>
	<lastBuildDate><?php echo esc_html( gmdate( DATE_RSS, strtotime( $modified . ' UTC' ) ) ); ?></lastBuildDate>
		<?php endif; ?>
	<generator>OpenAgenda Base <?php echo esc_html( OWC_OPENAGENDA_BASE_VERSION ); ?></generator>
	<opensearch:totalResults><?php echo (int) $this->total; ?></opensearch:totalResults>
	<opensearch:startIndex><?php echo (int) ( ( $this->args['page'] - 1 ) * $this->args['limit'] + 1 ); ?></opensearch:startIndex>
	<opensearch:itemsPerPage><?php echo (int) $this->args['limit']; ?></opensearch:itemsPerPage>
		<?php foreach ( $items as $item ) : ?>
	<item>
		<title><?php echo $this->xml_text( $item['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by xml_text(). ?></title>
		<link><?php echo esc_url( $item['link'] ); ?></link>
		<guid isPermaLink="false"><?php echo esc_html( $item['guid'] ); ?></guid>
		<pubDate><?php echo esc_html( $item['pub_date'] ); ?></pubDate>
		<description><?php echo $this->xml_text( $item['description'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by xml_text(). ?></description>
			<?php if ( $item['content'] ) : ?>
		<content:encoded><?php echo $this->xml_cdata( $item['content'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapped in CDATA by xml_cdata(). ?></content:encoded>
			<?php endif; ?>
			<?php foreach ( $item['categories'] as $category ) : ?>
		<category domain="<?php echo esc_attr( $category['taxonomy'] ); ?>"><?php echo $this->xml_text( $category['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by xml_text(). ?></category>
			<?php endforeach; ?>
			<?php if ( $item['image'] ) : ?>
		<enclosure url="<?php echo esc_url( $item['image']['url'] ); ?>" length="<?php echo (int) $item['image']['length']; ?>" type="<?php echo esc_attr( $item['image']['type'] ); ?>" />
		<media:content url="<?php echo esc_url( $item['image']['url'] ); ?>" medium="image" type="<?php echo esc_attr( $item['image']['type'] ); ?>" width="<?php echo (int) $item['image']['width']; ?>" height="<?php echo (int) $item['image']['height']; ?>" />
			<?php endif; ?>
			<?php if ( $item['start'] ) : ?>
		<ev:startdate><?php echo esc_html( $item['start'] ); ?></ev:startdate>
			<?php endif; ?>
			<?php if ( $item['end'] ) : ?>
		<ev:enddate><?php echo esc_html( $item['end'] ); ?></ev:enddate>
			<?php endif; ?>
			<?php if ( $item['location'] ) : ?>
		<ev:location><?php echo $this->xml_text( $item['location'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by xml_text(). ?></ev:location>
			<?php endif; ?>
			<?php if ( $item['organizer'] ) : ?>
		<ev:organizer><?php echo $this->xml_text( $item['organizer'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by xml_text(). ?></ev:organizer>
			<?php endif; ?>
			<?php foreach ( array( 'date_text', 'time_text', 'price_text', 'location_name', 'address' ) as $field ) : ?>
				<?php if ( '' !== (string) $item[ $field ] ) : ?>
		<openagenda:<?php echo esc_html( $field ); ?>><?php echo $this->xml_text( $item[ $field ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by xml_text(). ?></openagenda:<?php echo esc_html( $field ); ?>>
				<?php endif; ?>
			<?php endforeach; ?>
	</item>
		<?php endforeach; ?>
</channel>
</rss>
		<?php
		$this->send_output( ob_get_clean() );
	}

	/**
	 * Send the feed with an ETag of its content, or only a 304 Not Modified when the reader already has this version.
	 *
	 * @param string $output The feed.
	 *
	 * @return void
	 */
	private function send_output( $output ) {
		$etag = '"' . md5( $output ) . '"';
		header( 'ETag: ' . $etag );

		// If-None-Match can hold several (weak) ETags.
		$client_etags = array_map(
			function ( $client_etag ) {
				// Apache's mod_deflate adds "-gzip" to the ETag of compressed responses.
				return preg_replace( array( '#^W/#', '#-gzip"$#' ), array( '', '"' ), trim( $client_etag ) );
			},
			explode( ',', self::$client_etag )
		);
		if ( in_array( $etag, $client_etags, true ) || '*' === trim( self::$client_etag ) ) {
			status_header( 304 );

			return;
		}

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while building the feed in render().
	}

	/**
	 * The feed arguments: the settings, overridden by the request's query parameters.
	 *
	 * @return array
	 */
	public function get_request_args() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public, read-only feed parameters.
		$settings = self::get_settings();

		$args = array(
			'orderby'      => self::sanitize_choice( sanitize_key( wp_unslash( $_GET['orderby'] ?? '' ) ), array( 'event_date', 'publish_date' ), $settings['orderby'] ),
			'order'        => self::sanitize_choice( sanitize_key( wp_unslash( $_GET['order'] ?? '' ) ), array( 'asc', 'desc' ), $settings['order'] ),
			'limit'        => self::sanitize_limit( sanitize_text_field( wp_unslash( $_GET['limit'] ?? '' ) ), $settings['limit'] ),
			'page'         => max( 1, absint( wp_unslash( $_GET['page'] ?? 1 ) ) ),
			'item_date'    => $settings['item_date'],
			'tax_relation' => 'OR' === strtoupper( sanitize_key( wp_unslash( $_GET['tax_relation'] ?? '' ) ) ) ? 'OR' : 'AND',
			'terms'        => array(),
		);

		foreach ( get_object_taxonomies( 'event', 'objects' ) as $taxonomy ) {
			if ( ! $taxonomy->public || empty( $_GET[ $taxonomy->name ] ) ) {
				continue;
			}
			$slugs = array_filter( array_map( 'sanitize_title', explode( ',', sanitize_text_field( wp_unslash( $_GET[ $taxonomy->name ] ) ) ) ) );
			if ( $slugs ) {
				$args['terms'][ $taxonomy->name ] = $slugs;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		/**
		 * Filter the arguments used to build the event feed.
		 *
		 * @param array $args The feed arguments.
		 */
		return apply_filters( 'openagenda_base_feed_args', $args );
	}

	/**
	 * Get the upcoming events for the feed.
	 *
	 * @param array $args The feed arguments, see get_request_args().
	 *
	 * @return \WP_Post[]
	 */
	public function get_events( $args ) {
		$query_args = array(
			'post_type'           => 'event',
			'post_status'         => 'publish',
			'posts_per_page'      => $args['limit'],
			'paged'               => $args['page'] ?? 1,
			'has_password'        => false,
			'ignore_sticky_posts' => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- _next_date is the plugin's indexed sort key.
			'meta_query'          => array(
				'next_date' => array(
					'key'     => '_next_date',
					'compare' => '>=',
					'value'   => current_time( 'Y-m-d' ),
					'type'    => 'DATE',
				),
			),
		);

		if ( 'publish_date' === $args['orderby'] ) {
			$query_args['orderby'] = array( 'date' => strtoupper( $args['order'] ) );
		} else {
			$query_args['orderby'] = array(
				'next_date' => strtoupper( $args['order'] ),
				'title'     => 'ASC',
			);
		}

		if ( $args['terms'] ) {
			$query_args['tax_query'] = array( 'relation' => $args['tax_relation'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			foreach ( $args['terms'] as $taxonomy => $slugs ) {
				$query_args['tax_query'][] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $slugs,
				);
			}
		}

		/**
		 * Filter the WP_Query arguments for the event feed.
		 *
		 * @param array $query_args The WP_Query arguments.
		 * @param array $args       The feed arguments.
		 */
		$query_args = apply_filters( 'openagenda_base_feed_query_args', $query_args, $args );

		$query       = new \WP_Query( $query_args );
		$events      = $query->posts;
		$this->total = (int) $query->found_posts;
		// WordPress doesn't count the matches for a page past the last one.
		if ( ! $events && $query_args['paged'] > 1 ) {
			$count       = new \WP_Query(
				array_merge(
					$query_args,
					array(
						'paged'          => 1,
						'posts_per_page' => 1,
						'fields'         => 'ids',
					)
				)
			);
			$this->total = (int) $count->found_posts;
		}

		// Load the linked locations and images (with their meta) in one go, instead of one query per event.
		$related_ids = array();
		foreach ( $events as $event ) {
			$images        = get_post_meta( $event->ID, 'event_images', true );
			$related_ids[] = (int) get_post_meta( $event->ID, 'event_location', true );
			$related_ids[] = is_array( $images ) && $images ? (int) array_key_first( $images ) : (int) get_post_thumbnail_id( $event );
		}
		_prime_post_caches( array_filter( $related_ids ), false, true );

		// _next_date holds the date only, so order events on the same day by their start time.
		if ( 'event_date' === $args['orderby'] ) {
			$sort_keys = array();
			foreach ( $events as $event ) {
				$next_date               = $this->get_event_dates()->get_next_date( $event->ID );
				$sort_keys[ $event->ID ] = ( $next_date['date'] ?? '' ) . ' ' . ( $next_date['start_time'] ?? '' );
			}
			usort(
				$events,
				function ( $a, $b ) use ( $sort_keys, $args ) {
					$compare = strcmp( $sort_keys[ $a->ID ], $sort_keys[ $b->ID ] );

					return 'desc' === $args['order'] ? -$compare : $compare;
				}
			);
		}

		return $events;
	}

	/**
	 * Collect the feed data of an event.
	 *
	 * @param \WP_Post $event The event.
	 *
	 * @return array
	 */
	public function get_item_data( $event ) {
		$args     = $this->args ? $this->args : $this->get_request_args();
		$prefix   = 'event_';
		$timezone = wp_timezone();

		$event_dates = $this->get_event_dates();
		$next        = $event_dates->get_next_date( $event->ID );
		$next        = is_array( $next ) && ! empty( $next['date'] ) ? $next : null;
		$last        = $next;
		foreach ( $next ? get_post_meta( $event->ID, '_openagenda_event_date_time_list' ) : array() as $date ) {
			if ( ! empty( $date['date'] ) && $date['date'] > $last['date'] ) {
				$last = $date;
			}
		}

		$start = $next ? $this->create_date( $next['date'], $next['start_time'] ?? '', $timezone ) : null;
		$end   = null;
		if ( $start ) {
			if ( ! empty( $last['end_time'] ) ) {
				$end = $this->create_date( $last['date'], $last['end_time'], $timezone );
				// An end time before the start time on a single day means the event ends after midnight.
				if ( $end && $end <= $start ) {
					$end = $end->modify( '+1 day' );
				}
			} elseif ( $last['date'] !== $next['date'] ) {
				$end = $this->create_date( $last['date'], '', $timezone );
			}
		}

		$description = (string) get_post_meta( $event->ID, $prefix . 'description', true );
		$teaser      = (string) get_post_meta( $event->ID, $prefix . 'teaser', true );

		$categories = array();
		foreach ( get_object_taxonomies( 'event', 'objects' ) as $taxonomy ) {
			if ( ! $taxonomy->public ) {
				continue;
			}
			foreach ( (array) get_the_terms( $event->ID, $taxonomy->name ) as $term ) {
				if ( $term instanceof \WP_Term ) {
					$categories[] = array(
						'taxonomy' => $taxonomy->name,
						'name'     => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
					);
				}
			}
		}

		$location      = $this->get_location( $event->ID );
		$item_date     = 'event_date' === $args['item_date'] && $start ? $start : null;
		$time_text     = $next ? $this->get_time_text( $next ) : '';
		$location_text = implode( ', ', array_filter( array( $location['name'], $location['address'] ) ) );

		$item = array(
			'title'         => html_entity_decode( get_the_title( $event ), ENT_QUOTES, 'UTF-8' ),
			'link'          => get_permalink( $event ),
			'guid'          => get_post_type( $event ) . '-' . $event->ID,
			'pub_date'      => $item_date ? $item_date->format( DATE_RSS ) : get_post_time( DATE_RSS, true, $event ),
			'description'   => $teaser ? $teaser : wp_trim_words( html_entity_decode( wp_strip_all_tags( $description ), ENT_QUOTES, 'UTF-8' ), 55 ),
			// Newsletter tools put this html straight into their mails, so only allow the html of post content.
			'content'       => $description ? wp_kses_post( wpautop( $description ) ) : '',
			'categories'    => $categories,
			'image'         => $this->get_image( $event->ID ),
			'start'         => $start ? $start->format( ! empty( $next['start_time'] ) ? DATE_W3C : 'Y-m-d' ) : '',
			'end'           => $end ? $end->format( ! empty( $last['end_time'] ) ? DATE_W3C : 'Y-m-d' ) : '',
			'location'      => $location_text,
			'organizer'     => (string) get_post_meta( $event->ID, $prefix . 'organizer', true ),
			'date_text'     => $this->get_date_text( $event_dates, $event->ID ),
			'time_text'     => $time_text,
			'price_text'    => $this->get_price_text( $event->ID ),
			'location_name' => $location['name'],
			'address'       => $location['address'],
		);

		/**
		 * Filter the feed data of an event.
		 *
		 * @param array    $item  The item data.
		 * @param \WP_Post $event The event.
		 */
		return apply_filters( 'openagenda_base_feed_item', $item, $event );
	}

	/**
	 * The links to the other pages of the feed, keyed by relation (RFC 5005): first, previous, next and last. Only
	 * for feeds with more than one page.
	 *
	 * @return array
	 */
	private function get_page_links() {
		$pages = (int) ceil( $this->total / $this->args['limit'] );
		$page  = $this->args['page'];
		if ( $pages < 2 ) {
			return array();
		}

		$url  = remove_query_arg( 'page', get_self_link() );
		$link = function ( $number ) use ( $url ) {
			return 1 === $number ? $url : add_query_arg( 'page', $number, $url );
		};

		$links = array( 'first' => $link( 1 ) );
		if ( $page > 1 ) {
			$links['previous'] = $link( min( $page - 1, $pages ) );
		}
		if ( $page < $pages ) {
			$links['next'] = $link( $page + 1 );
		}
		$links['last'] = $link( $pages );

		return $links;
	}

	/**
	 * The Event_Dates instance.
	 *
	 * @return Event_Dates
	 */
	private function get_event_dates() {
		if ( ! $this->event_dates ) {
			$this->event_dates = new Event_Dates();
		}

		return $this->event_dates;
	}

	/**
	 * Create a date from the stored date and time, without breaking the feed on a malformed value.
	 *
	 * @param string        $date     The date.
	 * @param string        $time     The time, empty for the start of the day.
	 * @param \DateTimeZone $timezone The timezone.
	 *
	 * @return DateTimeImmutable|null Null for a malformed date or time.
	 */
	private function create_date( $date, $time, $timezone ) {
		try {
			return new DateTimeImmutable( $date . ' ' . ( $time ? $time : '00:00' ), $timezone );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * The channel title.
	 *
	 * @return string
	 */
	private function get_channel_title() {
		/* translators: %s: site name */
		$title = sprintf( __( '%s - Events', 'openagenda-base' ), get_bloginfo( 'name' ) );

		/**
		 * Filter the feed channel title.
		 *
		 * @param string $title The channel title.
		 */
		return apply_filters( 'openagenda_base_feed_title', html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * A readable description of all dates of the event, from Event_Dates::date_string().
	 *
	 * @param Event_Dates $event_dates The Event_Dates instance.
	 * @param int         $post_id     The event ID.
	 *
	 * @return string An empty string if the dates could not be described.
	 */
	private function get_date_text( $event_dates, $post_id ) {
		try {
			return $event_dates->date_string( $post_id );
		} catch ( \Exception $e ) {
			// date_string() throws on incomplete translations; don't let that break the whole feed.
			return '';
		}
	}

	/**
	 * A readable time, e.g. "10:30 - 11:30".
	 *
	 * @param array $date A date from Event_Dates, with start_time and end_time.
	 *
	 * @return string
	 */
	private function get_time_text( $date ) {
		return implode( ' - ', array_filter( array( $date['start_time'] ?? '', $date['end_time'] ?? '' ) ) );
	}

	/**
	 * A readable price, e.g. "Free", "€ 15,00", "From € 4,00" or "€ 5,00 - € 15,00".
	 *
	 * @param int $post_id The event ID.
	 *
	 * @return string
	 */
	private function get_price_text( $post_id ) {
		$prefix = 'event_';
		$format = function ( $price ) {
			return '€ ' . number_format_i18n( (float) $price, 2 );
		};

		switch ( get_post_meta( $post_id, $prefix . 'price_type', true ) ) {
			case 'min':
				/* translators: %s: price */
				return sprintf( __( 'From %s', 'openagenda-base' ), $format( get_post_meta( $post_id, $prefix . 'min_price', true ) ) );
			case 'min_max':
				return $format( get_post_meta( $post_id, $prefix . 'min_price', true ) ) . ' - ' . $format( get_post_meta( $post_id, $prefix . 'max_price', true ) );
			case 'fixed':
				$price = (float) get_post_meta( $post_id, $prefix . 'fixed_price', true );

				return $price > 0 ? $format( $price ) : __( 'Free', 'openagenda-base' );
		}

		return '';
	}

	/**
	 * The location name and address of an event, from the linked location or the event's own address fields.
	 *
	 * @param int $post_id The event ID.
	 *
	 * @return array With keys name and address.
	 */
	private function get_location( $post_id ) {
		$location_id = (int) get_post_meta( $post_id, 'event_location', true );
		$location    = $location_id ? get_post( $location_id ) : null;

		// Only a published location: drafts and private locations are not public.
		if ( $location && 'publish' === $location->post_status ) {
			$prefix = 'location_';
			$name   = html_entity_decode( get_the_title( $location ), ENT_QUOTES, 'UTF-8' );
			$id     = $location->ID;
		} else {
			$prefix = 'event_location_';
			$name   = '';
			$id     = $post_id;
		}

		$zipcode_city = trim( get_post_meta( $id, $prefix . 'zipcode', true ) . ' ' . get_post_meta( $id, $prefix . 'city', true ) );

		return array(
			'name'    => $name,
			'address' => implode( ', ', array_filter( array( get_post_meta( $id, $prefix . 'address', true ), $zipcode_city ) ) ),
		);
	}

	/**
	 * The main image of an event: the first event image, or the featured image.
	 *
	 * @param int $post_id The event ID.
	 *
	 * @return array|null With keys url, type, length, width and height.
	 */
	private function get_image( $post_id ) {
		$images        = get_post_meta( $post_id, 'event_images', true );
		$attachment_id = is_array( $images ) && $images ? (int) array_key_first( $images ) : (int) get_post_thumbnail_id( $post_id );
		if ( ! $attachment_id ) {
			return null;
		}

		/**
		 * Filter the image size used in the feed.
		 *
		 * @param string $size The image size.
		 */
		$size  = apply_filters( 'openagenda_base_feed_image_size', 'large' );
		$image = wp_get_attachment_image_src( $attachment_id, $size );
		if ( ! $image ) {
			return null;
		}

		// The file size from the attachment metadata (WordPress 6.0+), 0 (unknown) when it isn't there.
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$length   = $image[3] && is_string( $size ) ? ( $metadata['sizes'][ $size ]['filesize'] ?? 0 ) : ( $metadata['filesize'] ?? 0 );

		return array(
			'url'    => $image[0],
			'type'   => get_post_mime_type( $attachment_id ),
			'length' => (int) $length,
			'width'  => $image[1],
			'height' => $image[2],
		);
	}

	/**
	 * Escape text for use in an XML element.
	 *
	 * @param string $text The text.
	 *
	 * @return string
	 */
	private function xml_text( $text ) {
		return htmlspecialchars( $this->xml_characters( $text ), ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Wrap (HTML) content in a CDATA section.
	 *
	 * @param string $content The content.
	 *
	 * @return string
	 */
	private function xml_cdata( $content ) {
		return '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $this->xml_characters( $content ) ) . ']]>';
	}

	/**
	 * Remove what XML can't contain, not even escaped: invalid UTF-8 and control characters other than tab and newlines
	 * (e.g. the vertical tab in text pasted from Word). One such character would make the whole feed unreadable.
	 *
	 * @param string $text The text.
	 *
	 * @return string
	 */
	private function xml_characters( $text ) {
		$text = wp_check_invalid_utf8( (string) $text, true );

		return (string) preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text );
	}

	/**
	 * Return the value if it is one of the allowed choices, the default otherwise.
	 *
	 * @param string $value    The value, case insensitive.
	 * @param array  $choices  The allowed values.
	 * @param string $fallback The default value.
	 *
	 * @return string
	 */
	private static function sanitize_choice( $value, $choices, $fallback ) {
		$value = strtolower( (string) $value );

		return in_array( $value, $choices, true ) ? $value : $fallback;
	}

	/**
	 * Return the number of items, or the default, at most MAX_ITEMS.
	 *
	 * @param mixed $value    The value.
	 * @param int   $fallback The default value.
	 *
	 * @return int
	 */
	private static function sanitize_limit( $value, $fallback ) {
		$value = (int) $value;

		return min( $value > 0 ? $value : (int) $fallback, self::MAX_ITEMS );
	}
}
