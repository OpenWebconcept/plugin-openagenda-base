<?php
/**
 * Helper class for Settings
 *
 * @package    Openagenda_Base_Plugin
 * @subpackage Openagenda_Base_Plugin/Admin
 * @author     Acato <eyal@acato.nl>
 */

namespace Openagenda_Base_Plugin\Admin;

use Openagenda_Base_Plugin\Frontend\Event_Permalink;
use Openagenda_Base_Plugin\Frontend\Feed;

/**
 * Helper class for Settings
 */
class Settings {

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_plugin_settings' ), 10 );
	}

	/**
	 * This function is used to create the settings page
	 *
	 * @since   2018.1.1
	 * @return  void
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'OpenAgenda Settings', 'openagenda-base' ),
			__( 'OpenAgenda Settings', 'openagenda-base' ),
			'manage_options',
			'openagenda-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * This function is used to create the settings group
	 *
	 * @since   2018.1.1
	 * @return  void
	 */
	public function register_plugin_settings() {
		register_setting( 'openagenda-settings-group', 'openagenda_api_submit_user' );
		register_setting(
			'openagenda-settings-group',
			Event_Permalink::OPTION_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( Event_Permalink::class, 'sanitize_structure' ),
				'default'           => '',
			)
		);
		register_setting(
			'openagenda-settings-group',
			Feed::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Feed::class, 'sanitize_settings' ),
				'default'           => Feed::default_settings(),
			)
		);
	}

	/**
	 * This function add the html for the settings page
	 *
	 * @since   2018.1.1
	 * @return  void
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'OpenAgenda Settings', 'openagenda-base' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'openagenda-settings-group' ); ?>
				<?php do_settings_sections( 'openagenda-settings-group' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'API submit user', 'openagenda-base' ); ?></th>
						<td>
							<select name="openagenda_api_submit_user">
								<option value=""><?php esc_html_e( 'Select a user', 'openagenda-base' ); ?></option>
								<?php
								$users = get_users();
								foreach ( $users as $user ) {
									echo '<option value="' . esc_attr( $user->ID ) . '" ' . selected( get_option( 'openagenda_api_submit_user' ), $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>';
								}
								?>
							</select>
						</td>
					</tr>
				</table>
				<?php $this->permalink_settings(); ?>
				<?php $this->feed_settings(); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * The html for the event permalink settings, with buttons to insert the tags like on Settings > Permalinks.
	 *
	 * @return  void
	 */
	private function permalink_settings() {
		$explanations = array(
			/* translators: %s: permalink structure tag */
			'%year%'     => __( '%s (The year the event was published, four digits, for example 2026.)', 'openagenda-base' ),
			/* translators: %s: permalink structure tag */
			'%monthnum%' => __( '%s (Month of the year, for example 05.)', 'openagenda-base' ),
			/* translators: %s: permalink structure tag */
			'%day%'      => __( '%s (Day of the month, for example 28.)', 'openagenda-base' ),
			/* translators: %s: permalink structure tag */
			'%hour%'     => __( '%s (Hour of the day, for example 15.)', 'openagenda-base' ),
			/* translators: %s: permalink structure tag */
			'%minute%'   => __( '%s (Minute of the hour, for example 43.)', 'openagenda-base' ),
			/* translators: %s: permalink structure tag */
			'%second%'   => __( '%s (Second of the minute, for example 33.)', 'openagenda-base' ),
			/* translators: %s: permalink structure tag */
			'%post_id%'  => __( '%s (The unique ID of the event, for example 423.)', 'openagenda-base' ),
			/* translators: %s: permalink structure tag */
			'%postname%' => __( '%s (The slug of the event.)', 'openagenda-base' ),
			/* translators: %s: permalink structure tag */
			'%category%' => __( '%s (The first term of the first event taxonomy. Nested terms appear as nested directories in the URL.)', 'openagenda-base' ),
			/* translators: %s: permalink structure tag */
			'%author%'   => __( '%s (A sanitized version of the author name.)', 'openagenda-base' ),
		);
		/* translators: %s: taxonomy name; %%s: the permalink structure tag */
		$taxonomy_explanation = __( '%%s (The first term of the taxonomy %s.)', 'openagenda-base' );
		?>
		<h2><?php esc_html_e( 'Event permalinks', 'openagenda-base' ); ?></h2>
		<p>
			<?php esc_html_e( 'Leave empty to use the WordPress url of the event. When the events are shown on another website (e.g. a headless front end), enter the url structure of the event pages on that website. It is used for the event links in the feed, the REST API and the admin.', 'openagenda-base' ); ?>
		</p>
		<table class="form-table openagenda-permalink-structure">
			<tr valign="top">
				<th scope="row"><label for="openagenda-permalink-structure"><?php esc_html_e( 'Event url structure', 'openagenda-base' ); ?></label></th>
				<td>
					<input type="text" inputmode="url" id="openagenda-permalink-structure" class="large-text code" name="<?php echo esc_attr( Event_Permalink::OPTION_NAME ); ?>" value="<?php echo esc_attr( Event_Permalink::get_structure() ); ?>" placeholder="https://www.example.com/agenda/%postname%/" aria-describedby="openagenda-permalink-structure-description" />
					<p class="description" id="openagenda-permalink-structure-description">
						<?php
						printf(
							/* translators: 1: example url structure, 2: tag, 3: tag */
							esc_html__( 'For example %1$s. Must contain %2$s or %3$s.', 'openagenda-base' ),
							'<code>https://www.example.com/agenda/%postname%/</code>',
							'<code>%postname%</code>',
							'<code>%post_id%</code>'
						);
						?>
					</p>
					<div class="available-structure-tags hide-if-no-js">
						<div id="openagenda-permalink-structure-updated" aria-live="assertive" class="screen-reader-text"></div>
						<fieldset>
							<legend><?php esc_html_e( 'Available tags:', 'openagenda-base' ); ?></legend>
							<ul role="list">
								<?php foreach ( Event_Permalink::get_tags() as $tag ) : ?>
									<?php $explanation = $explanations[ $tag ] ?? sprintf( $taxonomy_explanation, trim( $tag, '%' ) ); ?>
									<li>
										<button type="button" class="button button-secondary" aria-label="<?php echo esc_attr( sprintf( $explanation, $tag ) ); ?>" aria-pressed="false"
											<?php /* translators: %s: permalink structure tag */ ?>
											data-added="<?php echo esc_attr( sprintf( __( '%s added to permalink structure', 'openagenda-base' ), $tag ) ); ?>"
											<?php /* translators: %s: permalink structure tag */ ?>
											data-removed="<?php echo esc_attr( sprintf( __( '%s removed from permalink structure', 'openagenda-base' ), $tag ) ); ?>"
										><?php echo esc_html( $tag ); ?></button>
									</li>
								<?php endforeach; ?>
							</ul>
						</fieldset>
					</div>
				</td>
			</tr>
		</table>
		<style>
			.openagenda-permalink-structure .available-structure-tags ul { display: flex; flex-wrap: wrap; margin: 8px 0 0; }
			.openagenda-permalink-structure .available-structure-tags li { margin: 6px 5px 0 0; }
		</style>
		<script>
			( function () {
				var input = document.getElementById( 'openagenda-permalink-structure' );
				var status = document.getElementById( 'openagenda-permalink-structure-updated' );
				var buttons = document.querySelectorAll( '.openagenda-permalink-structure .available-structure-tags button' );
				var focused = false;

				function updateButtons() {
					buttons.forEach( function ( button ) {
						var used = -1 !== input.value.indexOf( button.textContent.trim() );
						button.setAttribute( 'aria-pressed', used ? 'true' : 'false' );
						button.classList.toggle( 'active', used );
					} );
				}

				input.addEventListener( 'focus', function () { focused = true; } );
				input.addEventListener( 'input', updateButtons );

				buttons.forEach( function ( button ) {
					button.addEventListener( 'click', function () {
						var tag = button.textContent.trim();
						var value = input.value;
						var start = focused ? input.selectionStart : value.length;
						var end = focused ? input.selectionEnd : value.length;
						var text = tag;

						if ( -1 !== value.indexOf( tag ) ) {
							input.value = value.replace( tag + '/', '' ).replace( tag, '' );
							status.textContent = button.dataset.removed;
							updateButtons();
							return;
						}

						// Add the slashes around the tag, like on Settings > Permalinks.
						if ( '/' !== value.substr( 0, start ).substr( -1 ) ) {
							text = '/' + text;
						}
						if ( '/' !== value.substr( end, 1 ) ) {
							text = text + '/';
						}
						input.value = value.substr( 0, start ) + text + value.substr( end );
						status.textContent = button.dataset.added;
						updateButtons();

						if ( focused ) {
							input.focus();
							input.setSelectionRange( start + text.length, start + text.length );
						}
					} );
				} );

				updateButtons();
			}() );
		</script>
		<?php
	}

	/**
	 * The html for the feed settings.
	 *
	 * @return  void
	 */
	private function feed_settings() {
		$settings  = Feed::get_settings();
		$name      = Feed::OPTION_NAME;
		$feed_url  = Feed::get_feed_url();
		$sort_keys = array(
			'event_date'   => __( 'Event date (next occurrence)', 'openagenda-base' ),
			'publish_date' => __( 'Publication date', 'openagenda-base' ),
		);
		?>
		<h2><?php esc_html_e( 'Event feed', 'openagenda-base' ); ?></h2>
		<p>
			<?php esc_html_e( 'An RSS 2.0 feed of upcoming events, for use in newsletters, feed readers and other websites:', 'openagenda-base' ); ?>
			<a href="<?php echo esc_url( $feed_url ); ?>" target="_blank"><code><?php echo esc_html( $feed_url ); ?></code></a>
		</p>
		<p class="description">
			<?php
			printf(
				/* translators: 1: example query string, 2: example query string */
				esc_html__( 'Filter on categories with the taxonomy name and term slugs, e.g. %1$s. The settings below are defaults and can be overridden per feed url, e.g. %2$s.', 'openagenda-base' ),
				'<code>?thema=muziek,gratis</code>',
				'<code>?orderby=publish_date&amp;order=desc&amp;limit=50</code>'
			);
			?>
		</p>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="openagenda-feed-orderby"><?php esc_html_e( 'Sort by', 'openagenda-base' ); ?></label></th>
				<td>
					<select id="openagenda-feed-orderby" name="<?php echo esc_attr( $name ); ?>[orderby]">
						<?php foreach ( $sort_keys as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['orderby'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="openagenda-feed-order"><?php esc_html_e( 'Sort order', 'openagenda-base' ); ?></label></th>
				<td>
					<select id="openagenda-feed-order" name="<?php echo esc_attr( $name ); ?>[order]">
						<option value="asc" <?php selected( $settings['order'], 'asc' ); ?>><?php esc_html_e( 'Ascending (soonest / oldest first)', 'openagenda-base' ); ?></option>
						<option value="desc" <?php selected( $settings['order'], 'desc' ); ?>><?php esc_html_e( 'Descending (latest / newest first)', 'openagenda-base' ); ?></option>
					</select>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="openagenda-feed-limit"><?php esc_html_e( 'Number of items', 'openagenda-base' ); ?></label></th>
				<td>
					<input type="number" id="openagenda-feed-limit" class="small-text" min="1" max="<?php echo (int) Feed::MAX_ITEMS; ?>" name="<?php echo esc_attr( $name ); ?>[limit]" value="<?php echo (int) $settings['limit']; ?>" />
					<p class="description">
						<?php
						/* translators: %d: maximum number of items */
						printf( esc_html__( 'Only upcoming events are included. Maximum %d.', 'openagenda-base' ), (int) Feed::MAX_ITEMS );
						?>
					</p>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="openagenda-feed-item-date"><?php esc_html_e( 'Item date (pubDate)', 'openagenda-base' ); ?></label></th>
				<td>
					<select id="openagenda-feed-item-date" name="<?php echo esc_attr( $name ); ?>[item_date]">
						<?php foreach ( $sort_keys as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['item_date'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Many newsletter tools and feed readers show this date with each item. The event start and end are always available as ev:startdate and ev:enddate.', 'openagenda-base' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}
}
