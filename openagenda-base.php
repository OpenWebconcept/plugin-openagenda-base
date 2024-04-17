<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and starts the plugin.
 *
 * @link              https://www.openwebconcept.nl
 * @since             1.0.0
 * @package           Openagenda_Base_Plugin
 *
 * @wordpress-plugin
 * Plugin Name:       OpenAgenda Base
 * Plugin URI:        https://www.openwebconcept.nl
 * Description:       The OpenAgenda Base plugin.
 * Version:           1.0.13
 * Author:            Acato
 * Author URI:        https://www.acato.nl
 * License:           EUPL-1.2
 * License URI:       https://opensource.org/licenses/EUPL-1.2
 * Text Domain:       openagenda-base
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
define( 'OWC_OPENAGENDA_BASE_VERSION', '1.0.13' );
require_once plugin_dir_path( __FILE__ ) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'class-autoloader.php';
spl_autoload_register( array( '\Openagenda_Base_Plugin\Autoloader', 'autoload' ) );
/**
 * Begins execution of the plugin.
 */
new \Openagenda_Base_Plugin\Plugin();
