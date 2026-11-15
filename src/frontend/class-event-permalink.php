<?php
/**
 * Public event urls, for sites that show the events on another (headless) front end.
 *
 * @link       https://www.openwebconcept.nl
 * @since      1.3.0
 *
 * @package    Openagenda_Base_Plugin
 * @subpackage Openagenda_Base_Plugin/Frontend
 */

namespace Openagenda_Base_Plugin\Frontend;

/**
 * Public event urls.
 *
 * By default events use their WordPress permalink. When the plugin is used as a backend for another front end, a
 * permalink structure can be set at Settings > OpenAgenda Settings, e.g. https://www.example.com/agenda/%postname%/.
 * Published events then get their url from that structure everywhere WordPress builds an event url: the feed, the
 * REST API, the "View" links in the admin, the sitemap.
 *
 * @package    Openagenda_Base_Plugin
 * @subpackage Openagenda_Base_Plugin/Frontend
 * @author     Acato <remon@acato.nl>
 */
class Event_Permalink {
	/**
	 * The option holding the permalink structure.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'openagenda_event_permalink_structure';

	/**
	 * A tag in the permalink structure, e.g. %postname%; not a percent-encoded character like %C3% in %C3%A9.
	 *
	 * @var string
	 */
	const TAG_PATTERN = '/%(?![0-9a-f]{2}%)[a-z_][a-z0-9_-]*%/i';

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		add_filter( 'post_type_link', array( $this, 'filter_post_type_link' ), 10, 3 );
		add_filter( 'preview_post_link', array( $this, 'filter_preview_post_link' ), 10, 2 );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'filter_sitemap_post_types' ) );
		add_filter( 'redirect_canonical', array( $this, 'filter_redirect_canonical' ) );
	}

	/**
	 * The WordPress permalink tags.
	 *
	 * @return string[]
	 */
	private static function get_core_tags() {
		return array( '%year%', '%monthnum%', '%day%', '%hour%', '%minute%', '%second%', '%post_id%', '%postname%', '%category%', '%author%' );
	}

	/**
	 * The permalink structure, an empty string for the WordPress permalink.
	 *
	 * @return string
	 */
	public static function get_structure() {
		return (string) get_option( self::OPTION_NAME, '' );
	}

	/**
	 * The available tags: the WordPress permalink tags, plus one per public event taxonomy (e.g. %thema%).
	 *
	 * @return string[]
	 */
	public static function get_tags() {
		$tags = self::get_core_tags();
		foreach ( self::get_taxonomies() as $taxonomy ) {
			$tags[] = '%' . $taxonomy . '%';
		}

		// A taxonomy named like a WordPress tag (e.g. "year") doesn't get a tag of its own.
		return array_values( array_unique( $tags ) );
	}

	/**
	 * Sanitize the permalink structure: an absolute http(s) url that identifies the event with %postname% or %post_id%.
	 *
	 * @param mixed $structure The submitted structure.
	 *
	 * @return string The structure, or the current one when the submitted structure is invalid.
	 */
	public static function sanitize_structure( $structure ) {
		// Not sanitize_text_field(): that strips "%xx" sequences, which breaks tags like %day% and %category%.
		$structure = preg_replace( '/\s+/', '', wp_strip_all_tags( (string) $structure ) );
		if ( '' === $structure ) {
			return '';
		}

		$url = wp_parse_url( str_replace( self::get_tags(), 'tag', $structure ) );
		// Tags can only be used in the path and query string, not in the host or port.
		$authority = preg_replace( '#^[a-z][a-z0-9+.-]*://([^/?\#]*).*$#is', '$1', $structure );
		if ( empty( $url['scheme'] ) || ! in_array( strtolower( $url['scheme'] ), array( 'http', 'https' ), true ) || empty( $url['host'] ) || false !== strpos( $authority, '%' ) ) {
			self::add_error( 'invalid', __( 'The event permalink structure must be a full url, starting with https://.', 'openagenda-base' ) );

			return self::get_structure();
		}
		preg_match_all( self::TAG_PATTERN, $structure, $used_tags );
		$unknown_tags = array_diff( $used_tags[0], self::get_tags() );
		if ( $unknown_tags ) {
			/* translators: %s: the unknown tags */
			self::add_error( 'unknown_tag', sprintf( __( 'Unknown tag(s) in the event permalink structure: %s.', 'openagenda-base' ), implode( ', ', $unknown_tags ) ) );

			return self::get_structure();
		}
		if ( false === strpos( $structure, '%postname%' ) && false === strpos( $structure, '%post_id%' ) ) {
			self::add_error( 'not_unique', __( 'The event permalink structure must contain %postname% or %post_id%, so every event gets its own url.', 'openagenda-base' ) );

			return self::get_structure();
		}

		return esc_url_raw( $structure, array( 'http', 'https' ) );
	}

	/**
	 * Show an error on the settings page, once: WordPress sanitizes a new option twice.
	 *
	 * @param string $code    The error code.
	 * @param string $message The message.
	 *
	 * @return void
	 */
	private static function add_error( $code, $message ) {
		if ( ! in_array( $code, wp_list_pluck( get_settings_errors( self::OPTION_NAME ), 'code' ), true ) ) {
			add_settings_error( self::OPTION_NAME, $code, $message );
		}
	}

	/**
	 * Use the permalink structure for published events.
	 *
	 * @param string   $link      The permalink.
	 * @param \WP_Post $post      The post.
	 * @param bool     $leavename Whether to keep the %postname% tag, for the slug editor in the admin.
	 *
	 * @return string
	 */
	public function filter_post_type_link( $link, $post, $leavename = false ) {
		$structure = self::get_structure();
		// Drafts and scheduled events only exist in WordPress, keep their (preview) links.
		if ( '' === $structure || 'event' !== $post->post_type || 'publish' !== $post->post_status ) {
			return $link;
		}

		return $this->build_link( $structure, $post, $leavename );
	}

	/**
	 * Keep previews in WordPress: the other website can't show unsaved changes, and the preview url holds a nonce.
	 *
	 * @param string   $preview_link The preview url, built from the permalink.
	 * @param \WP_Post $post         The post.
	 *
	 * @return string
	 */
	public function filter_preview_post_link( $preview_link, $post ) {
		if ( '' === self::get_structure() || 'event' !== $post->post_type ) {
			return $preview_link;
		}

		// Only the preview arguments: the structure's own query string belongs to the other website.
		wp_parse_str( (string) wp_parse_url( $preview_link, PHP_URL_QUERY ), $query_args );
		$preview_args = array_intersect_key( $query_args, array_flip( array( 'preview', 'preview_id', 'preview_nonce', '_thumbnail_id' ) ) );

		remove_filter( 'post_type_link', array( $this, 'filter_post_type_link' ), 10 );
		$link = get_permalink( $post );
		add_filter( 'post_type_link', array( $this, 'filter_post_type_link' ), 10, 3 );

		return add_query_arg( $preview_args, $link );
	}

	/**
	 * Redirect old and short links of an event (e.g. /?p=123) to its public url. WordPress would otherwise redirect
	 * to the path of the public url on this site, as it only redirects within its own host.
	 *
	 * @param string|false $redirect_url The url WordPress redirects to, false for no redirect.
	 *
	 * @return string|false
	 */
	public function filter_redirect_canonical( $redirect_url ) {
		if ( '' === self::get_structure() ) {
			return $redirect_url;
		}

		// /?p=123 is a 404 for anything but a blog post, WordPress then looks the post up by its ID.
		if ( is_singular( 'event' ) ) {
			$post = $redirect_url ? get_queried_object() : null;
		} else {
			$post = is_404() && get_query_var( 'p' ) ? get_post( (int) get_query_var( 'p' ) ) : null;
		}

		if ( $post instanceof \WP_Post && 'event' === $post->post_type && 'publish' === $post->post_status ) {
			wp_redirect( get_permalink( $post ), 301 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the host of the permalink structure, set by an admin.
			exit;
		}

		return $redirect_url;
	}

	/**
	 * Leave the events out of the WordPress sitemap when their urls are on another website: a sitemap may only list
	 * urls of its own site. The other website lists them in its own sitemap.
	 *
	 * @param \WP_Post_Type[] $post_types The post types in the sitemap.
	 *
	 * @return \WP_Post_Type[]
	 */
	public function filter_sitemap_post_types( $post_types ) {
		if ( '' !== self::get_structure() ) {
			unset( $post_types['event'] );
		}

		return $post_types;
	}

	/**
	 * The overview of all events. With a permalink structure: its path up to the first tag, e.g.
	 * https://www.example.com/agenda/ for https://www.example.com/agenda/%postname%/. Otherwise the event archive of
	 * the site, if the theme has one, or the home page.
	 *
	 * @return string
	 */
	public static function get_overview_url() {
		$structure = self::get_structure();
		$parts     = array();

		if ( '' !== $structure ) {
			// The path up to the last slash before the first tag; the query string and fragment are left out.
			$first = preg_match( self::TAG_PATTERN, $structure, $match, PREG_OFFSET_CAPTURE ) ? $match[0][1] : strlen( $structure );
			$parts = wp_parse_url( substr( $structure, 0, $first ) );
		}
		if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
			$path = $parts['path'] ?? '/';
			$url  = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
			$url .= substr( $path, 0, strrpos( $path, '/' ) + 1 );
			$url  = '/' === substr( $url, -1 ) ? $url : $url . '/';
		} else {
			$url = get_post_type_archive_link( 'event' );
			$url = $url ? $url : home_url( '/' );
		}

		/**
		 * Filter the url of the events overview, used as the feed's channel link.
		 *
		 * @param string $url The url.
		 */
		return apply_filters( 'openagenda_base_event_overview_url', $url );
	}

	/**
	 * The slug of the event for %postname%, after the slugs of its parents: events are hierarchical, so two child
	 * events can have the same slug. WordPress builds the paths of hierarchical post types the same way.
	 *
	 * @param \WP_Post $post      The event.
	 * @param bool     $leavename Whether to keep the %postname% tag for the event's own slug.
	 *
	 * @return string
	 */
	private function get_postname( $post, $leavename ) {
		// All parents, whatever their status, as WordPress does: slugs are only unique among siblings.
		$parent_path = '';
		foreach ( get_post_ancestors( $post ) as $ancestor ) {
			$ancestor = get_post( $ancestor );
			if ( ! $ancestor ) {
				continue;
			}
			// A trashed parent gets its original slug back on restore; a draft may have no slug yet.
			$slug        = 'trash' === $ancestor->post_status ? get_post_meta( $ancestor->ID, '_wp_desired_post_slug', true ) : $ancestor->post_name;
			$slug        = $slug ? $slug : sanitize_title( $ancestor->post_title, $ancestor->ID );
			$parent_path = $slug . '/' . $parent_path;
		}
		if ( $leavename ) {
			return $parent_path . '%postname%';
		}

		return $parent_path . ( $post->post_name ? $post->post_name : sanitize_title( $post->post_title, $post->ID ) );
	}

	/**
	 * Replace the tags in the permalink structure.
	 *
	 * @param string   $structure The permalink structure.
	 * @param \WP_Post $post      The event.
	 * @param bool     $leavename Whether to keep the %postname% tag.
	 *
	 * @return string
	 */
	private function build_link( $structure, $post, $leavename = false ) {
		$date   = explode( ' ', str_replace( array( '-', ':' ), ' ', $post->post_date ) );
		$author = get_userdata( (int) $post->post_author );

		$replacements = array(
			'%year%'     => $date[0],
			'%monthnum%' => $date[1],
			'%day%'      => $date[2],
			'%hour%'     => $date[3],
			'%minute%'   => $date[4],
			'%second%'   => $date[5],
			'%post_id%'  => $post->ID,
			'%postname%' => $this->get_postname( $post, $leavename ),
			'%author%'   => $author ? $author->user_nicename : '',
			'%category%' => '',
		);
		foreach ( self::get_taxonomies() as $taxonomy ) {
			if ( in_array( '%' . $taxonomy . '%', self::get_core_tags(), true ) ) {
				continue;
			}
			$replacements[ '%' . $taxonomy . '%' ] = $this->get_term_path( $post, $taxonomy );
			// Like WordPress's %category%: the first term of the (first) event taxonomy.
			if ( '' === $replacements['%category%'] ) {
				$replacements['%category%'] = $replacements[ '%' . $taxonomy . '%' ];
			}
		}

		$link = str_replace( array_keys( $replacements ), array_values( $replacements ), $structure );
		// An empty tag would leave a double slash in the path.
		$link = preg_replace( '#(?<!:)/{2,}#', '/', $link );

		/**
		 * Filter the public url of an event, built from the permalink structure.
		 *
		 * @param string   $link      The url.
		 * @param \WP_Post $post      The event.
		 * @param string   $structure The permalink structure.
		 */
		return apply_filters( 'openagenda_base_event_permalink', $link, $post, $structure );
	}

	/**
	 * The slug of the event's first term (lowest term ID, like WordPress's %category%), including its parents.
	 *
	 * @param \WP_Post $post     The event.
	 * @param string   $taxonomy The taxonomy.
	 *
	 * @return string E.g. "muziek" or "cultuur/muziek".
	 */
	private function get_term_path( $post, $taxonomy ) {
		$terms = get_the_terms( $post, $taxonomy );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}

		$terms = wp_list_sort( $terms, 'term_id' );
		$term  = $terms[0];
		$slugs = array( $term->slug );
		foreach ( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $taxonomy );
			if ( $ancestor instanceof \WP_Term ) {
				array_unshift( $slugs, $ancestor->slug );
			}
		}

		return implode( '/', $slugs );
	}

	/**
	 * The names of the public event taxonomies.
	 *
	 * @return string[]
	 */
	private static function get_taxonomies() {
		return array_keys( wp_list_filter( get_object_taxonomies( 'event', 'objects' ), array( 'public' => true ) ) );
	}
}
