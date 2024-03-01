<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the public-facing side of the site and
 * the admin area.
 *
 * @link       https://www.openwebconcept.nl
 * @since      1.0.0
 *
 * @package    Openagenda_Base_Plugin
 */

namespace Openagenda_Base_Plugin;

use Openagenda_Base_Plugin\admin\Admin;
use Openagenda_Base_Plugin\admin\Cmb2;
use Openagenda_Base_Plugin\Admin\Event_Dates;
use Openagenda_Base_Plugin\Admin\Post_Expiration;
use Openagenda_Base_Plugin\Admin\Settings;
use Openagenda_Base_Plugin\Admin\Taxonomies;
use Openagenda_Base_Plugin\frontend\Frontend;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and public-facing site hooks.
 *
 * @since      1.0.0
 * @package    Openagenda_Base_Plugin
 * @author     Acato <richardkorthuis@acato.nl>
 */
class Plugin {
	/**
	 * Define the core functionality of the plugin.
	 *
	 * Define the locale, and set the hooks for the admin area and the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		/**
		 * Enable internationalization.
		 */
		new I18n();
		/**
		 * Register admin specific functionality.
		 */
		new Admin();
		new Cmb2();
		new Event_Dates();
		new Post_Expiration();
		new Settings();
		new Taxonomies();
		/**
		 * Register frontend specific functionality.
		 */
		new Frontend();

		new Rest_Api\Openagenda_Controller();
	}
}
