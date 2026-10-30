<?php
/**
 * What the bot knows about the site before doing anything.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

use EoBots\Includes\Abilities\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and caches a compact overview of the site: theme, plugins, content types, languages.
 */
class Site_Profile {

	const CACHE_KEY = 'eobots_site_profile';

	/**
	 * Flushes the cache whenever the overview may change.
	 */
	public static function init() {
		foreach ( array( 'switch_theme', 'activated_plugin', 'deactivated_plugin', 'save_post', 'deleted_post', 'wp_update_nav_menu', 'update_option_blogname', 'update_option_show_on_front' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush' ) );
		}
	}

	/**
	 * Clears the cached overview.
	 */
	public static function flush() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Returns the overview.
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$theme   = wp_get_theme();
		$profile = array(
			'name'         => get_bloginfo( 'name' ),
			'tagline'      => get_bloginfo( 'description' ),
			'url'          => home_url( '/' ),
			'language'     => get_locale(),
			'timezone'     => wp_timezone_string(),
			'theme'        => array(
				'name'        => $theme->get( 'Name' ),
				'block_theme' => wp_is_block_theme(),
				'parent'      => $theme->parent() ? $theme->parent()->get( 'Name' ) : null,
			),
			'plugins'      => self::active_plugins(),
			'content'      => self::content_types(),
			'front_page'   => self::front_page(),
			'menus'        => wp_list_pluck( wp_get_nav_menus(), 'name' ),
			'multilingual' => self::multilingual(),
		);

		set_transient( self::CACHE_KEY, $profile, 12 * HOUR_IN_SECONDS );
		return $profile;
	}

	/**
	 * Short text version for the system prompt.
	 *
	 * @return string
	 */
	public static function summary() {
		$p     = self::get();
		$lines = array(
			sprintf( 'Site: "%s"%s, %s, language %s.', $p['name'], $p['tagline'] ? ' (' . $p['tagline'] . ')' : '', $p['url'], $p['language'] ),
			sprintf( 'Theme: %s (%s theme%s).', $p['theme']['name'], $p['theme']['block_theme'] ? 'block' : 'classic', $p['theme']['parent'] ? ', child of ' . $p['theme']['parent'] : '' ),
		);

		$types = array();
		foreach ( $p['content'] as $type ) {
			$types[] = sprintf( '%s [%s] %d published, %d drafts', $type['label'], $type['slug'], $type['published'], $type['drafts'] );
		}
		$lines[] = 'Content: ' . implode( '; ', $types ) . '.';
		$lines[] = 'Home page: ' . $p['front_page'] . '.';
		if ( $p['plugins'] ) {
			$lines[] = 'Active plugins: ' . implode( ', ', $p['plugins'] ) . '.';
		}
		if ( $p['multilingual'] ) {
			$lines[] = 'Multilingual with ' . $p['multilingual'] . '.';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Names of active plugins, except this one.
	 *
	 * @return string[]
	 */
	private static function active_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$names = array();
		foreach ( get_plugins() as $file => $data ) {
			if ( is_plugin_active( $file ) && plugin_basename( EO_BOTS_FILE ) !== $file ) {
				$names[] = $data['Name'];
			}
		}
		return $names;
	}

	/**
	 * Content types the bot can work with, with counts.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function content_types() {
		$types = array();
		foreach ( Content::post_types() as $slug ) {
			$object  = get_post_type_object( $slug );
			$counts  = wp_count_posts( $slug );
			$types[] = array(
				'slug'      => $slug,
				'label'     => $object->labels->name,
				'published' => (int) ( $counts->publish ?? 0 ),
				'drafts'    => (int) ( $counts->draft ?? 0 ),
			);
		}
		return $types;
	}

	/**
	 * Describes what the home page shows.
	 *
	 * @return string
	 */
	private static function front_page() {
		if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
			$id = (int) get_option( 'page_on_front' );
			return sprintf( 'static page "%s" (#%d)', get_the_title( $id ), $id );
		}
		return 'latest posts';
	}

	/**
	 * Name of the multilingual plugin, if any.
	 *
	 * @return string|null
	 */
	private static function multilingual() {
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return 'WPML';
		}
		if ( function_exists( 'pll_languages_list' ) ) {
			return 'Polylang';
		}
		return null;
	}
}
