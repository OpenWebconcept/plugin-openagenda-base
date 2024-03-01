<?php
/**
 * Helper class for Settings
 *
 * @package    Openagenda_Base_Plugin
 * @subpackage Openagenda_Base_Plugin/Admin
 * @author     Acato <eyal@acato.nl>
 */

namespace Openagenda_Base_Plugin\Admin;

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
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
