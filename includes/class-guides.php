<?php
/**
 * Guides: one Markdown notice per plugin, telling the bot how to use that plugin's features.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the guides shipped in `guides/`, then the ones dropped in `wp-content/eo-bots-guides/`
 * (a site guide with the same file name replaces the shipped one). A guide only counts when
 * its plugin is active. See guides/README.md for the format.
 */
class Guides {

	/**
	 * Parsed guides, keyed by slug.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static $guides = null;

	/**
	 * Folders searched for guides, later ones win.
	 *
	 * @return string[]
	 */
	public static function paths() {
		/**
		 * Filters the folders searched for EO-BOTS guides (*.md files).
		 *
		 * @param string[] $paths Folders, later ones override earlier ones.
		 */
		return (array) apply_filters( 'eobots_guide_paths', array( EO_BOTS_PATH . 'guides', WP_CONTENT_DIR . '/eo-bots-guides' ) );
	}

	/**
	 * Guides whose plugin is active.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function active() {
		if ( null !== self::$guides ) {
			return self::$guides;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all = array();
		foreach ( self::paths() as $path ) {
			foreach ( glob( trailingslashit( $path ) . '*.md' ) ?: array() as $file ) { // phpcs:ignore Universal.Operators.DisallowShortTernary
				if ( 'readme' === strtolower( basename( $file, '.md' ) ) ) {
					continue;
				}
				$guide = self::parse( $file );
				if ( $guide ) {
					$all[ $guide['slug'] ] = $guide;
				}
			}
		}

		// The charter is computed from the theme, not stored in a file.
		$charter = array(
			'design-charter' => array(
				'slug'      => 'design-charter',
				'title'     => 'UI/UX charter of this site',
				'summary'   => 'Colors, fonts, spacing, templates and layout rules computed from this site\'s theme and styles. Read it before designing or redesigning anything.',
				'plugins'   => array( 'core' ),
				'abilities' => array(),
				'confirm'   => array(),
				'content'   => Design_Charter::guide(),
			),
		);

		self::$guides = $charter + array_filter(
			$all,
			static function ( $guide ) {
				foreach ( $guide['plugins'] as $plugin ) {
					if ( 'core' === $plugin || is_plugin_active( $plugin ) ) {
						return true;
					}
				}
				return false;
			}
		);

		return self::$guides;
	}

	/**
	 * One active guide.
	 *
	 * @param string $slug Guide slug (file name without .md).
	 * @return array<string, mixed>|null
	 */
	public static function get( $slug ) {
		return self::active()[ $slug ] ?? null;
	}

	/**
	 * List for the system prompt.
	 *
	 * @return string
	 */
	public static function summary() {
		$lines = array();
		foreach ( self::active() as $guide ) {
			$lines[] = sprintf( '- %s (%s): %s', $guide['slug'], $guide['title'], $guide['summary'] );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Reads a guide: a front matter block between "---" lines, then Markdown.
	 *
	 * @param string $file Path.
	 * @return array<string, mixed>|null
	 */
	private static function parse( $file ) {
		$raw = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! preg_match( '/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $raw, $m ) ) {
			return null;
		}

		$meta = array();
		foreach ( explode( "\n", $m[1] ) as $line ) {
			if ( preg_match( '/^\s*([a-z_]+)\s*:\s*(.*?)\s*$/i', $line, $kv ) ) {
				$meta[ strtolower( $kv[1] ) ] = $kv[2];
			}
		}
		if ( empty( $meta['plugins'] ) ) {
			return null;
		}

		// "confirm: yoast-seo/update-*=live, yoast-seo/set-*=always".
		$confirm = array();
		foreach ( self::split( $meta['confirm'] ?? '' ) as $rule ) {
			$parts = array_map( 'trim', explode( '=', $rule, 2 ) );
			if ( 2 === count( $parts ) && in_array( $parts[1], array( 'never', 'always', 'live' ), true ) ) {
				$confirm[ $parts[0] ] = $parts[1];
			}
		}

		$slug = sanitize_key( basename( $file, '.md' ) );
		return array(
			'slug'      => $slug,
			'title'     => $meta['title'] ?? $slug,
			'summary'   => $meta['summary'] ?? '',
			'plugins'   => self::split( $meta['plugins'] ),
			'abilities' => self::split( $meta['abilities'] ?? '' ),
			'confirm'   => $confirm,
			'content'   => trim( $m[2] ),
		);
	}

	/**
	 * Comma-separated list.
	 *
	 * @param string $value Value.
	 * @return string[]
	 */
	private static function split( $value ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) );
	}

	/**
	 * Abilities of other plugins enabled by active guides (patterns such as "yoast-seo/get-*").
	 *
	 * @return string[]
	 */
	public static function ability_patterns() {
		$patterns = array();
		foreach ( self::active() as $guide ) {
			$patterns = array_merge( $patterns, $guide['abilities'] ?? array() );
		}
		return array_unique( $patterns );
	}

	/**
	 * Confirmation rule an active guide sets for an ability, if any.
	 *
	 * @param string $name Ability name.
	 * @return string|null never, always or live.
	 */
	public static function confirm_rule( $name ) {
		foreach ( self::active() as $guide ) {
			foreach ( $guide['confirm'] ?? array() as $pattern => $rule ) {
				if ( fnmatch( $pattern, $name ) ) {
					return $rule;
				}
			}
		}
		return null;
	}
}
