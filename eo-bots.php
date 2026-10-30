<?php
/**
 * Plugin Name:       EO-BOTS - Mon agent IA
 * Plugin URI:        https://www.eoxia.com
 * Description:       A cute, customizable AI agent that lives in the corner of your screen and runs your WordPress site for you through a simple chat.
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Version:           0.1.10
 * Author:            Eoxia
 * Author URI:        https://www.eoxia.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       eo-bots
 * Domain Path:       /languages
 *
 * @package EoBots
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'EO_BOTS_FILE', __FILE__ );
define( 'EO_BOTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'EO_BOTS_URL', plugin_dir_url( __FILE__ ) );
define( 'EO_BOTS_VERSION', '0.1.10' );

require_once EO_BOTS_PATH . 'includes/autoload.php';

register_activation_hook( __FILE__, array( '\EoBots\Includes\Install', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		// EO-BOTS is built on the AI Client and Abilities API shipped with WordPress 7.0.
		if ( ! function_exists( 'wp_ai_client_prompt' ) || ! function_exists( 'wp_register_ability' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'EO-BOTS needs WordPress 7.0 or later.', 'eo-bots' ) . '</p></div>';
				}
			);
			return;
		}

		\EoBots\Includes\Plugin::instance();
	}
);
