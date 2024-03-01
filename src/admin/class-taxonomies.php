<?php
/**
 * Helper class for Taxonomies
 *
 * @package    Openagenda_Base_Plugin
 * @subpackage Openagenda_Base_Plugin/Admin
 * @author     Acato <eyal@acato.nl>
 */

namespace Openagenda_Base_Plugin\Admin;

/**
 * Helper class for Taxonomies
 */
class Taxonomies {

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'create_taxonomies_menu' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}

	/**
	 * Register the taxonomies
	 *
	 * @return void
	 */
	public function register_taxonomies() {
		$event_taxonomies_json  = get_option( 'event_taxonomies' );
		$event_taxonomies_array = json_decode( $event_taxonomies_json, true );
		if ( ! empty( $event_taxonomies_array ) ) {
			foreach ( $event_taxonomies_array as $taxonomy ) {
				register_taxonomy(
					sanitize_key( $taxonomy ),
					'event',
					array(
						'label'        => $taxonomy,
						'labels'       => array(
							'name'                       => $taxonomy,
							'singular_name'              => $taxonomy,
							'menu_name'                  => $taxonomy,
							/* translators: %s: taxonomy */
							'all_items'                  => sprintf( __( 'All %s', 'openagenda-base' ), $taxonomy ),
							/* translators: %s: taxonomy */
							'edit_item'                  => sprintf( __( 'Edit %s', 'openagenda-base' ), $taxonomy ),
							/* translators: %s: taxonomy */
							'view_item'                  => sprintf( __( 'View %s', 'openagenda-base' ), $taxonomy ),
							/* translators: %s: taxonomy */
							'update_item'                => sprintf( __( 'Update %s', 'openagenda-base' ), $taxonomy ),
							/* translators: %s: taxonomy */
							'add_new_item'               => sprintf( __( 'Add New %s', 'openagenda-base' ), $taxonomy ),
							/* translators: %s: taxonomy */
							'new_item_name'              => sprintf( __( 'New %s', 'openagenda-base' ), $taxonomy ),
							/* translators: %s: taxonomy */
							'search_items'               => sprintf( __( 'Search %s', 'openagenda-base' ), $taxonomy ),
							/* translators: %s: taxonomy */
							'popular_items'              => sprintf( __( 'Popular %s', 'openagenda-base' ), $taxonomy ),
							/* translators: %s: taxonomy */
							'separate_items_with_commas' => sprintf( __( 'Separate %s with commas', 'openagenda-base' ), $taxonomy ),
							/* translators: %s: taxonomy */
							'add_or_remove_items'        => sprintf( __( 'Add or remove %s', 'openagenda-base' ), $taxonomy ),
							/* translators: %s: taxonomy */
							'choose_from_most_used'      => sprintf( __( 'Choose from the most used %s', 'openagenda-base' ), $taxonomy ),
							/* translators: %s: taxonomy */
							'not_found'                  => sprintf( __( 'No %s found', 'openagenda-base' ), $taxonomy ),
							/* translators: %s: taxonomy */
							'parent_item'                => sprintf( __( 'Parent %s', 'openagenda-base' ), $taxonomy ),
							/* translators: %s: taxonomy */
							'parent_item_colon'          => sprintf( __( 'Parent %s:', 'openagenda-base' ), $taxonomy ),
							/* translators: %s: taxonomy */
							'back_to_items'              => sprintf( __( '&larr;  Back to %s', 'openagenda-base' ), $taxonomy ),
						),
						'rewrite'      => array( 'slug' => sanitize_key( $taxonomy ) ),
						'hierarchical' => true,
						'show_in_rest' => true,

					)
				);
			}
		}
	}

	/**
	 * Create Taxonomies Menu
	 *
	 * @return void
	 */
	public function create_taxonomies_menu() {
		// add a submenu page to the event post type menu and call the create_taxonomies function.
		add_submenu_page(
			'edit.php?post_type=event',
			__( 'Create Taxonomies', 'openagenda-base' ),
			__( 'Create Taxonomies', 'openagenda-base' ),
			'publish_pages',
			'create-taxonomies',
			array( $this, 'create_taxonomies' )
		);
	}

	/**
	 * Create Taxonomies
	 *
	 * @return   void
	 */
	public function create_taxonomies() {
		// add nonce verification.
		if ( isset( $_GET['create_taxonomies_nonce'] ) && ! wp_verify_nonce( $_GET['create_taxonomies_nonce'], 'create_taxonomies' ) ) {
			return;
		}

		$current_page           = admin_url( sprintf( 'edit.php?%s', http_build_query( $_GET ) ) );
		$event_taxonomies_json  = get_option( 'event_taxonomies' );
		$event_taxonomies_array = json_decode( $event_taxonomies_json, true );

		if ( isset( $_POST['new_taxonomy'] ) ) {
			$new_taxonomy = sanitize_text_field( $_POST['new_taxonomy'] );
			$new_taxonomy = str_replace( "\\'", "'", $new_taxonomy );

			if ( is_array( $event_taxonomies_array ) && in_array( $new_taxonomy, $event_taxonomies_array, true ) ) {
				/* translators: %s: taxonomy name */
				echo '<div class="error"><p><strong>' . sprintf( esc_html__( '%s taxonomy already exists', 'openagenda-base' ), esc_html( $new_taxonomy ) ) . '</strong></p></div>';
			} else {
				$event_taxonomies_array[] = $new_taxonomy;
				$event_taxonomies_json    = wp_json_encode( $event_taxonomies_array );

				update_option( 'event_taxonomies', $event_taxonomies_json );

				/* translators: %s: taxonomy name */
				echo '<div class="updated"><p><strong>' . sprintf( esc_html__( '%s taxonomy created', 'openagenda-base' ), esc_html( $new_taxonomy ) ) . '</strong></p></div>';
			}
		}

		if ( isset( $_POST['delete_taxonomy'] ) ) {
			$delete_taxonomy = $_POST['delete_taxonomy'];
			$delete_taxonomy = str_replace( "\\'", "'", $delete_taxonomy );
			if ( ! is_array( $delete_taxonomy ) ) {
				$delete_taxonomy = array( $delete_taxonomy );
			}
			$event_taxonomies_array = array_diff( $event_taxonomies_array, $delete_taxonomy );
			$event_taxonomies_json  = wp_json_encode( $event_taxonomies_array );

			update_option( 'event_taxonomies', $event_taxonomies_json );

			/* translators: %s: taxonomy name */
			echo '<div class="updated"><p><strong>' . sprintf( esc_html__( '%s taxonomy deleted', 'openagenda-base' ), esc_html( $delete_taxonomy ) ) . '</strong></p></div>';
		}
		?>
		<div class="wrap">
			<h2>Create Taxonomies</h2>
			<form method="post" action="<?php echo esc_html( $current_page ); ?>">
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php echo esc_html__( 'New Taxonomy', 'openagenda-base' ); ?></th>
						<td><input type="text" name="new_taxonomy" value="" /><br /><p><?php echo esc_html__( 'It\'s best to use singular words', 'openagenda-base' ); ?></p></td>
					</tr>
				</table>
				<?php submit_button( 'Save Taxonomy' ); ?>
			</form>
			<h3>Registered Taxonomies</h3>
			<p><?php echo esc_html__( 'A taxonomy can only be deleted if it has no terms yet.', 'openagenda-base' ); ?></p>
			<form method="post" action="<?php echo esc_html( $current_page ); ?>">
				<input type="hidden" name="create_taxonomies_nonce" value="<?php echo esc_html( wp_create_nonce( 'create_taxonomies' ) ); ?>" />
				<table class="form-table">
					<?php if ( ! empty( $event_taxonomies_array ) ) : ?>
						<?php
						foreach ( $event_taxonomies_array as $taxonomy ) :
							// get number of terms attached to taxonomy.
							$term_count = (int) wp_count_terms( strtolower( $taxonomy ) );
							?>
							<tr valign="top">
								<th scope="row"><?php echo esc_html( $taxonomy ); ?></th>
								<td>
									<?php if ( 0 === $term_count ) : ?>
										<label>
											<?php echo esc_html__( 'Delete', 'openagenda-base' ); ?>
										</label>
										<input type="checkbox" name="delete_taxonomy[]" value="<?php echo esc_html( $taxonomy ); ?>" />
									<?php else : ?>
										<span class="description"><?php echo esc_html__( 'This taxonomy has terms attached to it', 'openagenda-base' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<th colspan="2"><?php submit_button( __( 'Delete selected taxonomies', 'openagenda-base' ) ); ?></th>
						</tr>
					<?php else : ?>
						<tr valign="top">
							<th scope="row">No taxonomies created</th>
						</tr>
					<?php endif; ?>
				</table>
			</form>
		</div>
		<?php
	}
}
