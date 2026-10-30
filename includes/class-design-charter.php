<?php
/**
 * UI/UX charter computed from the theme: theme.json merged with the site's Global Styles.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns the theme settings into roles the bot can apply without guessing:
 * which color for sections, cards, strong bands and text, which spacing for sections and cards,
 * which fonts and sizes, which page templates show the title. Served as the "design-charter" guide.
 */
class Design_Charter {

	const CACHE_KEY = 'eobots_design_charter';

	/**
	 * Flushes the cache when the theme or its styles change.
	 */
	public static function init() {
		foreach ( array( 'switch_theme', 'save_post_wp_global_styles', 'save_post_wp_template', 'customize_save_after' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush' ) );
		}
	}

	/**
	 * Clears the cache.
	 */
	public static function flush() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Site design tokens and their roles.
	 *
	 * @return array<string, mixed>
	 */
	public static function tokens() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$settings = wp_get_global_settings();
		$styles   = wp_get_global_styles();
		$palette  = self::palette( $settings );

		$page = self::preset( $styles['color']['background'] ?? '', 'color' );
		$text = self::preset( $styles['color']['text'] ?? '', 'color' );
		$page = isset( $palette[ $page ] ) ? $page : self::extreme( $palette, true );
		$text = isset( $palette[ $text ] ) ? $text : self::extreme( $palette, false );

		$button      = $styles['elements']['button']['color'] ?? array();
		$button_bg   = self::preset( $button['background'] ?? '', 'color' );
		$button_text = self::preset( $button['text'] ?? '', 'color' );

		$tokens = array(
			'palette'   => $palette,
			'page'      => $page,
			'text'      => $text,
			'surface'   => self::surface( $palette, $page, $text ),
			'strong'    => self::strong( $palette, $page, $text ),
			'highlight' => self::highlight( $palette, $page, $text ),
			'button'    => array(
				'background' => isset( $palette[ $button_bg ] ) ? $button_bg : null,
				'text'       => isset( $palette[ $button_text ] ) ? $button_text : null,
			),
			'readable'  => self::readable( $palette ),
			'fonts'     => array(
				'body'     => self::preset( $styles['typography']['fontFamily'] ?? '', 'font-family' ),
				'headings' => self::preset( $styles['elements']['heading']['typography']['fontFamily'] ?? '', 'font-family' ),
				'all'      => wp_list_pluck( self::presets( $settings['typography']['fontFamilies'] ?? array() ), 'name', 'slug' ),
			),
			'sizes'     => array(
				'all'  => wp_list_pluck( self::presets( $settings['typography']['fontSizes'] ?? array() ), 'slug' ),
				'body' => self::preset( $styles['typography']['fontSize'] ?? '', 'font-size' ),
				'h1'   => self::preset( $styles['elements']['h1']['typography']['fontSize'] ?? '', 'font-size' ),
				'h2'   => self::preset( $styles['elements']['h2']['typography']['fontSize'] ?? '', 'font-size' ),
			),
			'spacing'   => self::spacing( $settings ),
			'layout'    => array(
				'content' => $settings['layout']['contentSize'] ?? null,
				'wide'    => $settings['layout']['wideSize'] ?? null,
			),
			'templates' => self::templates(),
		);

		set_transient( self::CACHE_KEY, $tokens, DAY_IN_SECONDS );
		return $tokens;
	}

	/**
	 * The charter as a guide: universal rules, then this site's values.
	 *
	 * @return string
	 */
	public static function guide() {
		$t     = self::tokens();
		$lines = array();

		$lines[] = '# UI/UX charter';
		$lines[] = 'Follow it for every page you build or redesign. Every create-content and update-content result includes a "design_review": fix each issue it lists.';
		$lines[] = '';
		$lines[] = '## Universal rules';
		$lines[] = '- Consistency beats variety: one value per role (section padding, card padding, gaps, radius), reused everywhere.';
		$lines[] = '- Color 60/30/10: about 60% page background, 30% surface (light neutral for alternating sections and cards), 10% accent. Use the strong color for two bands at most (hero and/or final call to action). Never more than 3 different section backgrounds on a page.';
		$lines[] = '- Text color must be readable on its background (contrast 4.5:1 at least): only use the pairs listed below. A colored section sets both backgroundColor and textColor.';
		$lines[] = '- Buttons: keep the theme button style (set no colors) on light backgrounds. On the strong color, set the button to the page background color with the strong color as text, or use the outline style. One primary button per section, at most two buttons side by side, verbs as labels ("Ask for a quote").';
		$lines[] = '- Hierarchy: one H1 per page, H2 for each section, H3 inside cards and columns, no skipped level. Short paragraphs (3 or 4 lines, under 80 words); lists for features.';
		$lines[] = '- Alignment: center only the hero, section intros and the final call to action. Body text, cards and lists stay left aligned.';
		$lines[] = '- Spacing: full-width sections (align full) with the section padding top and bottom; cards with the card padding; let the theme block gap space the elements. No margins added by hand on paragraphs and headings.';
		$lines[] = '- Width: text in the content width (constrained layout), grids and image rows in wide alignment.';
		$lines[] = '- Visuals: give most sections a visual (image from the media library, gallery, carousel, figures). An image always has a meaningful alt text.';
		$lines[] = '- Cards: same padding, same radius (12px is fine), same structure on every card of a row; a light surface or a 1px border to separate them from the background.';
		$lines[] = '- Motion: one entrance animation per section, or per card in a row, never both a section and its children. Subtle ones (fade, slide-bottom, scale-up).';
		$lines[] = '- Presets only: theme colors, font sizes and spacing presets, never raw hex colors, pixel font sizes or pixel paddings.';
		$lines[] = '- Mobile: columns stack, so each column must make sense alone; keep at most 4 columns.';
		$lines[] = '';
		$lines[] = '## This site';

		$name    = static function ( $slug ) use ( $t ) {
			return $slug ? sprintf( '`%s` (%s)', $slug, $t['palette'][ $slug ] ?? '?' ) : 'none';
		};
		$lines[] = 'Colors (slug → hex): ' . implode( ', ', array_map( static fn( $slug, $hex ) => "`$slug` $hex", array_keys( $t['palette'] ), $t['palette'] ) ) . '.';
		$lines[] = '- Page background: ' . $name( $t['page'] ) . '. Main text: ' . $name( $t['text'] ) . '.';
		$lines[] = '- Surface (alternating sections, cards): ' . $name( $t['surface'] ) . '.';
		$lines[] = '- Strong color (hero or final call to action band): ' . $name( $t['strong'] ) . '.';
		$lines[] = '- Highlight (small touches, one section at most): ' . $name( $t['highlight'] ) . '.';
		if ( $t['button']['background'] ) {
			$lines[] = '- Theme buttons: ' . $name( $t['button']['background'] ) . ' background, ' . $name( $t['button']['text'] ) . ' text.';
		}

		$lines[] = '- Readable text colors on each background:';
		foreach ( $t['readable'] as $bg => $texts ) {
			$lines[] = sprintf( '  - on `%s`: %s', $bg, $texts ? '`' . implode( '`, `', $texts ) . '`' : 'nothing readable, never use it as a background' );
		}

		if ( $t['fonts']['all'] ) {
			$lines[] = '- Fonts: ' . implode( ', ', array_map( static fn( $slug, $label ) => "`$slug` ($label)", array_keys( $t['fonts']['all'] ), $t['fonts']['all'] ) ) . '. Body text uses ' . ( $t['fonts']['body'] ? '`' . $t['fonts']['body'] . '`' : 'the theme default' ) . ', headings ' . ( $t['fonts']['headings'] ? '`' . $t['fonts']['headings'] . '`' : 'the same font' ) . ': keep them, do not set fontFamily on blocks.';
		}
		if ( $t['sizes']['all'] ) {
			$lines[] = '- Font sizes, small to large: `' . implode( '`, `', $t['sizes']['all'] ) . '`. Theme defaults: body `' . ( $t['sizes']['body'] ? $t['sizes']['body'] : '?' ) . '`, H1 `' . ( $t['sizes']['h1'] ? $t['sizes']['h1'] : '?' ) . '`, H2 `' . ( $t['sizes']['h2'] ? $t['sizes']['h2'] : '?' ) . '`. Only set a fontSize to stand out (hero title one size up, intro paragraph `large`).';
		}

		$s = $t['spacing'];
		if ( $s['all'] ) {
			$lines[] = sprintf( '- Spacing presets: %s. Roles: section padding `var:preset|spacing|%s` (hero `%s`), card padding `var:preset|spacing|%s`, gap between cards and columns `var:preset|spacing|%s`.', implode( ', ', array_map( static fn( $slug, $size ) => "`$slug` $size", array_keys( $s['all'] ), $s['all'] ) ), $s['section'], $s['hero'], $s['card'], $s['gap'] );
		}
		if ( $t['layout']['content'] ) {
			$lines[] = sprintf( '- Content width %s, wide width %s.', $t['layout']['content'], $t['layout']['wide'] );
		}

		if ( $t['templates'] ) {
			$lines[] = '- Page templates (create-content / update-content "template"):';
			foreach ( $t['templates'] as $slug => $template ) {
				$lines[] = sprintf( '  - `%s` %s: %s', $slug, $template['name'], $template['title'] ? 'shows the page title as H1 above the content, so the content must not contain an H1' : 'does not show the title: use it when the page opens with its own hero and H1' );
			}
		}

		return implode( "\n", $lines );
	}

	/* ---------- Color helpers ---------- */

	/**
	 * Palette as slug => hex (non-hex values such as color-mix are left out).
	 *
	 * @param array<string, mixed> $settings Global settings.
	 * @return array<string, string>
	 */
	private static function palette( $settings ) {
		$out = array();
		foreach ( self::presets( $settings['color']['palette'] ?? array() ) as $color ) {
			$hex = self::hex( $color['color'] ?? '' );
			if ( $hex ) {
				$out[ $color['slug'] ] = $hex;
			}
		}
		return $out;
	}

	/**
	 * Presets of the theme (user edits included), else WordPress defaults, plus the site's custom ones.
	 *
	 * @param array<string, array> $origins Presets by origin.
	 * @return array<int, array<string, mixed>>
	 */
	private static function presets( $origins ) {
		$base = ! empty( $origins['theme'] ) ? $origins['theme'] : ( $origins['default'] ?? array() );
		return array_merge( $base, $origins['custom'] ?? array() );
	}

	/**
	 * Preset slug from a style value: "var:preset|color|base" or "var(--wp--preset--color--base)".
	 *
	 * @param mixed  $value Style value.
	 * @param string $type  color, font-size, font-family or spacing.
	 * @return string|null
	 */
	public static function preset( $value, $type ) {
		if ( is_string( $value ) && preg_match( '/(?:var:preset\|' . $type . '\||--wp--preset--' . $type . '--)([a-z0-9-]+)/', $value, $m ) ) {
			return $m[1];
		}
		return null;
	}

	/**
	 * Normalized #rrggbb, or null.
	 *
	 * @param string $value Color.
	 * @return string|null
	 */
	public static function hex( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( preg_match( '/^#([0-9a-f]{3})$/', $value, $m ) ) {
			return '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}
		return preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : null;
	}

	/**
	 * WCAG relative luminance.
	 *
	 * @param string $hex Color.
	 * @return float
	 */
	public static function luminance( $hex ) {
		$out = array();
		foreach ( array( 1, 3, 5 ) as $i ) {
			$v     = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$out[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $out[0] + 0.7152 * $out[1] + 0.0722 * $out[2];
	}

	/**
	 * WCAG contrast ratio.
	 *
	 * @param string $a Color.
	 * @param string $b Color.
	 * @return float
	 */
	public static function contrast( $a, $b ) {
		$x = self::luminance( $a );
		$y = self::luminance( $b );
		return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
	}

	/**
	 * HSL saturation (0-1).
	 *
	 * @param string $hex Color.
	 * @return float
	 */
	private static function saturation( $hex ) {
		$r  = hexdec( substr( $hex, 1, 2 ) ) / 255;
		$g  = hexdec( substr( $hex, 3, 2 ) ) / 255;
		$b  = hexdec( substr( $hex, 5, 2 ) ) / 255;
		$mx = max( $r, $g, $b );
		$mn = min( $r, $g, $b );
		$l  = ( $mx + $mn ) / 2;
		if ( $mx === $mn ) {
			return 0.0;
		}
		return ( $mx - $mn ) / ( 1 - abs( 2 * $l - 1 ) );
	}

	/**
	 * Lightest or darkest palette color.
	 *
	 * @param array<string, string> $palette Palette.
	 * @param bool                  $light   Lightest when true.
	 * @return string|null
	 */
	private static function extreme( $palette, $light ) {
		if ( ! $palette ) {
			return null;
		}
		$sorted = $palette;
		uasort( $sorted, static fn( $a, $b ) => self::luminance( $a ) <=> self::luminance( $b ) );
		$keys = array_keys( $sorted );
		return $light ? end( $keys ) : $keys[0];
	}

	/**
	 * Light neutral close to the page background, with very readable text.
	 *
	 * @param array<string, string> $palette Palette.
	 * @param string|null           $page    Page background slug.
	 * @param string|null           $text    Text slug.
	 * @return string|null
	 */
	private static function surface( $palette, $page, $text ) {
		if ( ! $page || ! $text ) {
			return null;
		}
		$best = null;
		$gap  = INF;
		foreach ( $palette as $slug => $hex ) {
			if ( $slug === $page || $slug === $text || self::saturation( $hex ) > 0.5 || self::contrast( $hex, $palette[ $text ] ) < 7 ) {
				continue;
			}
			$diff = abs( self::luminance( $hex ) - self::luminance( $palette[ $page ] ) );
			if ( $diff > 0.005 && $diff < $gap ) {
				$best = $slug;
				$gap  = $diff;
			}
		}
		return $best;
	}

	/**
	 * Most saturated color that carries the page background color as readable text.
	 *
	 * @param array<string, string> $palette Palette.
	 * @param string|null           $page    Page background slug.
	 * @param string|null           $text    Text slug.
	 * @return string|null
	 */
	private static function strong( $palette, $page, $text ) {
		$best  = null;
		$score = -1;
		foreach ( $palette as $slug => $hex ) {
			if ( $slug === $page || ! $page || self::contrast( $hex, $palette[ $page ] ) < 4.5 ) {
				continue;
			}
			$sat = self::saturation( $hex );
			if ( $sat > $score ) {
				$best  = $slug;
				$score = $sat;
			}
		}
		return $best ? $best : $text;
	}

	/**
	 * Light, colorful color that keeps the main text readable.
	 *
	 * @param array<string, string> $palette Palette.
	 * @param string|null           $page    Page background slug.
	 * @param string|null           $text    Text slug.
	 * @return string|null
	 */
	private static function highlight( $palette, $page, $text ) {
		$best  = null;
		$score = 0.35;
		foreach ( $palette as $slug => $hex ) {
			if ( $slug === $page || ! $text || self::contrast( $hex, $palette[ $text ] ) < 4.5 ) {
				continue;
			}
			$sat = self::saturation( $hex );
			if ( $sat > $score ) {
				$best  = $slug;
				$score = $sat;
			}
		}
		return $best;
	}

	/**
	 * Readable text colors for each background (contrast 4.5 or more).
	 *
	 * @param array<string, string> $palette Palette.
	 * @return array<string, string[]>
	 */
	private static function readable( $palette ) {
		$out = array();
		foreach ( $palette as $bg => $bg_hex ) {
			$out[ $bg ] = array();
			foreach ( $palette as $fg => $fg_hex ) {
				if ( $fg !== $bg && self::contrast( $bg_hex, $fg_hex ) >= 4.5 ) {
					$out[ $bg ][] = $fg;
				}
			}
		}
		return $out;
	}

	/* ---------- Spacing and templates ---------- */

	/**
	 * Spacing presets and the role given to each.
	 *
	 * @param array<string, mixed> $settings Global settings.
	 * @return array<string, mixed>
	 */
	private static function spacing( $settings ) {
		$all  = wp_list_pluck( self::presets( $settings['spacing']['spacingSizes'] ?? array() ), 'size', 'slug' );
		$all  = array_map( static fn( $size ) => is_string( $size ) ? $size : '', $all );
		$keys = array_keys( $all );
		$n    = count( $keys );

		return array(
			'all'     => $all,
			'hero'    => $n ? $keys[ $n - 1 ] : null,
			'section' => $n ? $keys[ max( 0, $n - 2 ) ] : null,
			'card'    => $n ? $keys[ max( 0, intdiv( $n, 2 ) - 1 ) ] : null,
			'gap'     => $n ? $keys[ max( 0, intdiv( $n, 2 ) - 1 ) ] : null,
		);
	}

	/**
	 * Page templates and whether they show the title.
	 *
	 * @return array<string, array{name: string, title: bool}>
	 */
	public static function templates() {
		$out = array();

		if ( wp_is_block_theme() ) {
			$default = get_block_template( get_stylesheet() . '//page' );
			if ( $default ) {
				$out['default'] = array(
					'name'  => __( 'Default template', 'eo-bots' ),
					'title' => false !== strpos( $default->content, 'wp:post-title' ),
				);
			}
			foreach ( get_block_templates( array( 'post_type' => 'page' ) ) as $template ) {
				$out[ $template->slug ] = array(
					'name'  => $template->title,
					'title' => false !== strpos( $template->content, 'wp:post-title' ),
				);
			}
		}

		return $out;
	}

	/**
	 * Whether a page shows its title through its template.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function shows_title( $post_id ) {
		$slug      = get_page_template_slug( $post_id );
		$templates = self::tokens()['templates'];
		$key       = $slug ? $slug : 'default';
		return isset( $templates[ $key ] ) ? $templates[ $key ]['title'] : true;
	}
}
