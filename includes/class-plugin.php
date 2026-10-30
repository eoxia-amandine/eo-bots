<?php
/**
 * Main plugin hub: wires every part to WordPress.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

use WordPress\AiClient\AiClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton that registers hooks and exposes shared helpers.
 */
class Plugin {

	/**
	 * Single instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the single instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Registers hooks.
	 */
	private function __construct() {
		Install::maybe_upgrade();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_abilities_api_categories_init', array( Abilities\Registry::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( Abilities\Registry::class, 'register' ) );
		add_action( 'rest_api_init', array( Rest::class, 'register_routes' ) );

		Snippets::init();
		Admin::init();
		Assets::init();
		Site_Profile::init();
		Design_Charter::init();
	}

	/**
	 * Loads translations shipped with the plugin.
	 */
	public function load_textdomain() {
		// The French translation ships with the plugin; translate.wordpress.org files still take precedence.
		load_plugin_textdomain( 'eo-bots', false, dirname( plugin_basename( EO_BOTS_FILE ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
	}

	/**
	 * Capability needed to own an EO-BOT and talk to it.
	 * Each action the bot takes is still checked against the user's own rights.
	 *
	 * @return string
	 */
	public static function capability() {
		return (string) apply_filters( 'eobots_capability', 'edit_posts' );
	}

	/**
	 * Whether the current user may use EO-BOTS.
	 *
	 * @return bool
	 */
	public static function user_can_use() {
		return is_user_logged_in() && current_user_can( self::capability() );
	}

	/**
	 * Whether at least one AI provider is installed and has credentials (Settings > Connectors).
	 *
	 * @return bool
	 */
	public static function ai_ready() {
		if ( ! wp_supports_ai() ) {
			return false;
		}

		try {
			$registry = AiClient::defaultRegistry();
			foreach ( $registry->getRegisteredProviderIds() as $provider_id ) {
				if ( $registry->isProviderConfigured( $provider_id ) ) {
					return true;
				}
			}
		} catch ( \Throwable $e ) {
			return false;
		}

		return false;
	}

	/**
	 * Whether the current user can connect an AI provider.
	 *
	 * @return bool
	 */
	public static function can_connect() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Core Connectors screen, where providers are installed and API keys saved.
	 *
	 * @return string
	 */
	public static function connectors_url() {
		return admin_url( 'options-connectors.php' );
	}

	/**
	 * URL of the EO-BOT creation screen.
	 *
	 * @return string
	 */
	public static function creator_url() {
		return admin_url( 'admin.php?page=' . Admin::SLUG );
	}
}
