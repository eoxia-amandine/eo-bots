<?php
/**
 * Admin menu, creation screen and activation redirect.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin side of EO-BOTS.
 */
class Admin {

	const SLUG          = 'eo-bots';
	const SNIPPETS_SLUG = 'eo-bots-snippets';

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'activation_redirect' ) );
		add_action( 'admin_notices', array( __CLASS__, 'creation_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'snippet_alert' ) );
		add_action( 'admin_post_eobots_snippet', array( __CLASS__, 'snippet_action' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( EO_BOTS_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Hook suffix of the creation screen.
	 *
	 * @var string
	 */
	public static $creator_hook = '';

	/**
	 * Hook suffix of the snippets screen.
	 *
	 * @var string
	 */
	public static $snippets_hook = '';

	/**
	 * Adds the "EO-BOTS" menu at the very bottom of the admin menu, with the snippets as a submenu.
	 */
	public static function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" fill-rule="evenodd" d="M10 1a1.2 1.2 0 0 1 .6 2.24V5H15a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V8a3 3 0 0 1 3-3h4.4V3.24A1.2 1.2 0 0 1 10 1zM7 9.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm6 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Inline menu icon.

		self::$creator_hook = (string) add_menu_page(
			__( 'My EO-BOT', 'eo-bots' ),
			__( 'EO-BOTS', 'eo-bots' ),
			Plugin::capability(),
			self::SLUG,
			array( __CLASS__, 'render_creator' ),
			$icon,
			// After every core and plugin menu, so it stays out of the way.
			1000
		);

		add_submenu_page( self::SLUG, __( 'My EO-BOT', 'eo-bots' ), __( 'My EO-BOT', 'eo-bots' ), Plugin::capability(), self::SLUG, array( __CLASS__, 'render_creator' ) );

		self::$snippets_hook = (string) add_submenu_page(
			self::SLUG,
			__( 'EO-BOTS snippets', 'eo-bots' ),
			__( 'Snippets', 'eo-bots' ),
			'manage_options',
			self::SNIPPETS_SLUG,
			array( __CLASS__, 'render_snippets' )
		);
	}

	/**
	 * URL of the snippets screen.
	 *
	 * @param int $id Snippet to scroll to.
	 * @return string
	 */
	public static function snippets_url( $id = 0 ) {
		return admin_url( 'admin.php?page=' . self::SNIPPETS_SLUG ) . ( $id ? '#snippet-' . $id : '' );
	}

	/**
	 * Snippets screen: what the EO-BOT wrote, with on/off and delete, plus the safe mode URL.
	 */
	public static function render_snippets() {
		delete_option( Snippets::ALERT );

		$types  = array(
			'css' => 'CSS',
			'php' => 'PHP',
		);
		$scopes = array(
			'everywhere' => __( 'Everywhere', 'eo-bots' ),
			'front'      => __( 'Public site', 'eo-bots' ),
			'admin'      => __( 'Admin', 'eo-bots' ),
		);

		echo '<div class="wrap eobots-snippets"><h1>' . esc_html__( 'EO-BOTS snippets', 'eo-bots' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Small pieces of code your EO-BOT wrote for this site. They are kept here, not in your theme, so updates do not erase them. Ask your EO-BOT in the chat to write or change one.', 'eo-bots' ) . '</p>';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Messages after a redirect.
		if ( isset( $_GET['eobots-error'] ) ) {
			$message = get_transient( 'eobots_snippet_notice_' . get_current_user_id() );
			delete_transient( 'eobots_snippet_notice_' . get_current_user_id() );
			if ( $message ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
			}
		} elseif ( isset( $_GET['eobots-done'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Done.', 'eo-bots' ) . '</p></div>';
		}
		// phpcs:enable

		if ( ! Snippets::user_can( 'php' ) ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'PHP snippets are disabled on this site (file editing is turned off, or your account cannot edit plugins). CSS snippets still work.', 'eo-bots' ) . '</p></div>';
		} elseif ( ! Snippets::dir_ready() ) {
			/* translators: %s: folder path. */
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( __( 'The folder %s cannot be written, so PHP snippets cannot run.', 'eo-bots' ), str_replace( ABSPATH, '', Snippets::dir() ) ) ) . '</p></div>';
		}

		$snippets = Snippets::all();
		if ( ! $snippets ) {
			echo '<div class="eobots-snippets-empty"><p>' . esc_html__( 'No snippet yet. Ask your EO-BOT for example: “Hide the author name on blog posts” or “Add a shortcode that shows the current year”.', 'eo-bots' ) . '</p></div>';
		}

		foreach ( $snippets as $snippet ) {
			$code = get_post_field( 'post_content', $snippet['id'] );
			$url  = static function ( $action ) use ( $snippet ) {
				return wp_nonce_url( admin_url( 'admin-post.php?action=eobots_snippet&do=' . $action . '&id=' . $snippet['id'] ), 'eobots_snippet_' . $snippet['id'] );
			};

			printf( '<div class="eobots-snippet%s" id="snippet-%d">', $snippet['active'] ? ' is-active' : '', (int) $snippet['id'] );
			echo '<div class="eobots-snippet-head"><div>';
			echo '<h2>' . esc_html( $snippet['title'] ) . '</h2>';
			printf(
				'<p class="eobots-snippet-meta"><span class="eobots-tag">%s</span> <span class="eobots-tag">%s</span> <span class="eobots-status">%s</span></p>',
				esc_html( $types[ $snippet['type'] ] ),
				esc_html( $scopes[ $snippet['scope'] ] ),
				$snippet['active'] ? esc_html__( 'Active', 'eo-bots' ) : esc_html__( 'Inactive', 'eo-bots' )
			);
			echo '</div><div class="eobots-snippet-actions">';
			if ( Snippets::user_can( $snippet['type'] ) ) {
				printf(
					'<a class="button %s" href="%s">%s</a> ',
					$snippet['active'] ? '' : 'button-primary',
					esc_url( $url( $snippet['active'] ? 'off' : 'on' ) ),
					$snippet['active'] ? esc_html__( 'Switch off', 'eo-bots' ) : esc_html__( 'Switch on', 'eo-bots' )
				);
				printf( '<a class="button-link-delete" href="%s">%s</a>', esc_url( $url( 'delete' ) ), esc_html__( 'Delete', 'eo-bots' ) );
			}
			echo '</div></div>';

			if ( '' !== $snippet['description'] ) {
				echo '<p>' . esc_html( $snippet['description'] ) . '</p>';
			}
			if ( ! empty( $snippet['error'] ) ) {
				/* translators: %s: PHP error. */
				echo '<div class="notice notice-error inline"><p>' . esc_html( sprintf( __( 'Last error: %s', 'eo-bots' ), $snippet['error'] ) ) . '</p></div>';
			}
			echo '<details><summary>' . esc_html__( 'See the code', 'eo-bots' ) . '</summary><pre><code>' . esc_html( $code ) . '</code></pre></details>';
			echo '</div>';
		}

		if ( Snippets::user_can( 'php' ) ) {
			echo '<div class="eobots-safe"><h2>' . esc_html__( 'If your site breaks', 'eo-bots' ) . '</h2>';
			echo '<p>' . esc_html__( 'Your EO-BOT tests every PHP snippet before switching it on and switches off any snippet that causes an error. If your site still shows an error, open this secret address: it switches off every PHP snippet. Keep it somewhere safe and do not share it.', 'eo-bots' ) . '</p>';
			echo '<p><input type="text" class="large-text code" readonly value="' . esc_attr( Snippets::safe_url() ) . '"></p>';
			echo '<p class="description">' . wp_kses( __( 'You can also add <code>define( \'EOBOTS_SAFE_MODE\', true );</code> to wp-config.php.', 'eo-bots' ), array( 'code' => array() ) ) . '</p></div>';
		}

		echo '</div>';
	}

	/**
	 * Switch on, switch off or delete a snippet from the snippets screen.
	 */
	public static function snippet_action() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Checked by check_admin_referer() below.
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$action = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '';
		// phpcs:enable
		check_admin_referer( 'eobots_snippet_' . $id );

		$snippet = Snippets::get( $id, false );
		if ( ! $snippet || ! Snippets::user_can( $snippet['type'] ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'eo-bots' ), 403 );
		}

		if ( 'delete' === $action ) {
			$result = wp_trash_post( $id ) ? true : new \WP_Error( 'eobots_trash_failed', __( 'The snippet could not be deleted.', 'eo-bots' ) );
			Snippets::rebuild();
		} else {
			$result = Snippets::set_active( $id, 'on' === $action );
		}

		if ( is_wp_error( $result ) ) {
			set_transient( 'eobots_snippet_notice_' . get_current_user_id(), $result->get_error_message(), MINUTE_IN_SECONDS );
			wp_safe_redirect( add_query_arg( 'eobots-error', 1, self::snippets_url() ) . '#snippet-' . $id );
		} else {
			wp_safe_redirect( add_query_arg( 'eobots-done', 1, self::snippets_url() ) . '#snippet-' . $id );
		}
		exit;
	}

	/**
	 * Tells administrators that a snippet was switched off after an error.
	 */
	public static function snippet_alert() {
		$alert = get_option( Snippets::ALERT );
		if ( ! $alert || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: 1: snippet title, 2: PHP error. */
					__( 'Your EO-BOT switched off the snippet “%1$s” because it caused an error: %2$s', 'eo-bots' ),
					$alert['title'],
					$alert['message']
				)
			),
			esc_url( self::snippets_url( (int) $alert['id'] ) ),
			esc_html__( 'See the snippets', 'eo-bots' )
		);
	}

	/**
	 * Creation screen; the UI is built by assets/js/creator.js.
	 */
	public static function render_creator() {
		echo '<div class="wrap eobots-creator-wrap"><h1 class="screen-reader-text">' . esc_html__( 'My EO-BOT', 'eo-bots' ) . '</h1>';
		echo '<div id="eobots-creator"><noscript>' . esc_html__( 'Your EO-BOT needs JavaScript to come to life.', 'eo-bots' ) . '</noscript></div></div>';
	}

	/**
	 * Sends the user who activated the plugin straight to the creation screen, unless their EO-BOT already exists.
	 */
	public static function activation_redirect() {
		$user_id = (int) get_transient( Install::REDIRECT_KEY );
		if ( ! $user_id || get_current_user_id() !== $user_id ) {
			return;
		}

		delete_transient( Install::REDIRECT_KEY );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of the core bulk activation flag.
		if ( isset( $_GET['activate-multi'] ) || wp_doing_ajax() || ! Plugin::user_can_use() || Avatar::get( $user_id ) ) {
			return;
		}

		wp_safe_redirect( Plugin::creator_url() );
		exit;
	}

	/**
	 * Reminds users on the dashboard that their EO-BOT is not created yet.
	 */
	public static function creation_notice() {
		$screen = get_current_screen();
		if ( ! $screen || 'dashboard' !== $screen->id || ! Plugin::user_can_use() || Avatar::get( get_current_user_id() ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Your EO-BOT is waiting to be created.', 'eo-bots' ),
			esc_url( Plugin::creator_url() ),
			esc_html__( 'Create my EO-BOT', 'eo-bots' )
		);
	}

	/**
	 * Adds a link to the creation screen on the plugins list.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( Plugin::creator_url() ), esc_html__( 'My EO-BOT', 'eo-bots' ) ) );
		return $links;
	}
}
