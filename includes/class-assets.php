<?php
/**
 * Scripts and styles: creation screen and the floating EO-BOT.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the creator on its screen, and the floating EO-BOT everywhere else
 * (admin and public site) for users who own one.
 */
class Assets {

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front' ) );
	}

	/**
	 * Registers the shared avatar engine.
	 */
	private static function register_avatar() {
		wp_register_style( 'eobots-avatar', EO_BOTS_URL . 'assets/css/eobot.css', array(), EO_BOTS_VERSION );
		wp_register_script( 'eobots-avatar', EO_BOTS_URL . 'assets/js/eobot.js', array(), EO_BOTS_VERSION, array( 'in_footer' => true ) );
	}

	/**
	 * Admin pages.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function admin( $hook_suffix ) {
		if ( ! Plugin::user_can_use() || ( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST ) ) {
			return;
		}

		self::register_avatar();

		if ( Admin::$snippets_hook === $hook_suffix ) {
			wp_enqueue_style( 'eobots-snippets', EO_BOTS_URL . 'assets/css/snippets.css', array(), EO_BOTS_VERSION );
		}

		if ( Admin::$creator_hook === $hook_suffix ) {
			self::creator();
			return;
		}

		$screen = get_current_screen();
		$post   = get_post();
		self::widget(
			array(
				'area'   => 'admin',
				'screen' => $screen ? $screen->id : '',
				'postId' => $screen && 'post' === $screen->base && $post ? $post->ID : 0,
			)
		);
	}

	/**
	 * Public site, for logged-in users only.
	 */
	public static function front() {
		if ( ! Plugin::user_can_use() || is_customize_preview() ) {
			return;
		}

		self::register_avatar();
		self::widget(
			array(
				'area'   => 'front',
				'postId' => is_singular() ? get_queried_object_id() : 0,
			)
		);
	}

	/**
	 * Creation screen.
	 */
	private static function creator() {
		wp_enqueue_style( 'eobots-creator', EO_BOTS_URL . 'assets/css/creator.css', array( 'eobots-avatar' ), EO_BOTS_VERSION );
		wp_enqueue_script( 'eobots-creator', EO_BOTS_URL . 'assets/js/creator.js', array( 'eobots-avatar', 'wp-api-fetch', 'wp-i18n' ), EO_BOTS_VERSION, array( 'in_footer' => true ) );
		wp_set_script_translations( 'eobots-creator', 'eo-bots', EO_BOTS_PATH . 'languages' );

		$user_id = get_current_user_id();
		self::data(
			'eobots-creator',
			'eobotsCreator',
			array(
				'catalog'       => Avatar::catalog(),
				'avatar'        => Avatar::get( $user_id ) ?? Avatar::defaults(),
				'isNew'         => null === Avatar::get( $user_id ),
				'aiReady'       => Plugin::ai_ready(),
				'canConnect'    => Plugin::can_connect(),
				'connectorsUrl' => Plugin::connectors_url(),
				'dashboardUrl'  => admin_url(),
			)
		);
	}

	/**
	 * Floating EO-BOT and its chat. It renders in a shadow root, so it loads its own styles there.
	 *
	 * @param array<string, mixed> $context Where the user is.
	 */
	private static function widget( $context ) {
		$avatar = Avatar::get( get_current_user_id() );
		if ( ! $avatar ) {
			return;
		}

		wp_enqueue_script( 'eobots-widget', EO_BOTS_URL . 'assets/js/widget.js', array( 'eobots-avatar', 'wp-api-fetch', 'wp-i18n' ), EO_BOTS_VERSION, array( 'in_footer' => true ) );
		wp_set_script_translations( 'eobots-widget', 'eo-bots', EO_BOTS_PATH . 'languages' );

		self::data(
			'eobots-widget',
			'eobotsWidget',
			array(
				'avatar'        => $avatar,
				'aiReady'       => Plugin::ai_ready(),
				'canConnect'    => Plugin::can_connect(),
				'connectorsUrl' => Plugin::connectors_url(),
				'creatorUrl'    => Plugin::creator_url(),
				'userId'        => get_current_user_id(),
				'context'       => $context,
				'styles'        => array(
					EO_BOTS_URL . 'assets/css/eobot.css?ver=' . EO_BOTS_VERSION,
					EO_BOTS_URL . 'assets/css/widget.css?ver=' . EO_BOTS_VERSION,
				),
			)
		);
	}

	/**
	 * Prints data for a script as a global.
	 *
	 * @param string               $handle Script handle.
	 * @param string               $name   Global name.
	 * @param array<string, mixed> $data   Data.
	 */
	private static function data( $handle, $name, $data ) {
		wp_add_inline_script( $handle, 'window.' . $name . ' = ' . wp_json_encode( $data ) . ';', 'before' );
	}
}
