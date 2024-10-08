<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the public-facing side of the site and
 * the admin area.
 *
 * @link       https://www.openwebconcept.nl
 *
 * @package    Openkaarten_Base_Functions
 */

namespace Openkaarten_Base_Functions;

use Openkaarten_Base_Functions\Admin\Admin;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and public-facing site hooks.
 *
 * @package    Openkaarten_Base_Plugin
 * @author     Acato <eyal@acato.nl>
 */
class Plugin {
	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {

		/**
		 * Register admin specific functionality.
		 */
		Admin::get_instance();
	}
}
