<?php
/**
 * Automatic design review of block content against the site's UI/UX charter.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks what code can check reliably (contrast, hierarchy, presets, rhythm, motion, images)
 * and returns short, actionable issues the bot must fix.
 */
class Design_Review {

	const MAX_ISSUES = 12;

	/**
	 * Site tokens.
	 *
	 * @var array<string, mixed>
	 */
	private $t;

	/**
	 * Collected facts.
	 *
	 * @var array<string, mixed>
	 */
	private $facts = array(
		'h1'          => 0,
		'last_level'  => 0,
		'skip'        => null,
		'animated'    => 0,
		'nested_anim' => 0,
		'images'      => 0,
		'no_alt'      => 0,
		'contrast'    => array(),
		'blend'       => 0,
		'raw'         => array(),
		'margins'     => 0,
		'long'        => 0,
		'centered'    => 0,
		'crowded'     => 0,
		'no_href'     => 0,
	);

	/**
	 * Reviews content.
	 *
	 * @param string $content Block markup.
	 * @param int    $post_id Post the content belongs to, to check its template.
	 * @return string[] Issues, most important first.
	 */
	public static function review( $content, $post_id = 0 ) {
		$review    = new self();
		$review->t = Design_Charter::tokens();
		return $review->run( $content, $post_id );
	}

	/**
	 * Runs every check.
	 *
	 * @param string $content Block markup.
	 * @param int    $post_id Post ID.
	 * @return string[]
	 */
	private function run( $content, $post_id ) {
		$blocks  = array_values( array_filter( parse_blocks( $content ), static fn( $b ) => ! empty( $b['blockName'] ) ) );
		$palette = $this->t['palette'];
		$f       = &$this->facts;

		$this->walk(
			$blocks,
			array(
				'bg'   => $palette[ $this->t['page'] ] ?? null,
				'text' => $palette[ $this->t['text'] ] ?? null,
				'anim' => false,
			)
		);

		$issues = array();

		// Hierarchy.
		$shows_title = $post_id ? Design_Charter::shows_title( $post_id ) : true;
		$no_title    = array_keys( array_filter( $this->t['templates'], static fn( $tpl ) => ! $tpl['title'] ) );
		// Prefer a template made for that (no title, blank, landing) over any other one without title.
		usort( $no_title, static fn( $a, $b ) => (int) ! preg_match( '/no-?title|sans-?titre|blank|landing|full/i', $a ) <=> (int) ! preg_match( '/no-?title|sans-?titre|blank|landing|full/i', $b ) );
		if ( $f['h1'] && $shows_title ) {
			$issues[] = 'The page template already shows the title as H1, so the content has two H1. ' . ( $no_title ? 'Set "template" to `' . $no_title[0] . '` (no title) since the page has its own hero, or turn your H1 into an H2.' : 'Turn your H1 into an H2.' );
		} elseif ( $f['h1'] > 1 ) {
			$issues[] = sprintf( 'There are %d H1 headings: keep a single H1 (the hero title), use H2 for sections.', $f['h1'] );
		} elseif ( ! $f['h1'] && ! $shows_title && $post_id ) {
			$issues[] = 'The template does not show the title and the content has no H1: give the hero title level 1.';
		}
		if ( $f['skip'] ) {
			$issues[] = 'Heading levels skip from ' . $f['skip'] . ': go down one level at a time (H2 for sections, H3 inside).';
		}

		// Color.
		foreach ( array_slice( $f['contrast'], 0, 3 ) as $problem ) {
			$issues[] = 'Text not readable: ' . $problem . '. Use a readable pair from the design charter.';
		}
		if ( $f['blend'] ) {
			$issues[] = sprintf( '%d button(s) have the same color as their section and disappear: on the strong color, use the page background color for the button or the outline style.', $f['blend'] );
		}

		// Sections.
		$sections = array_values( array_filter( $blocks, static fn( $b ) => in_array( $b['blockName'], array( 'core/group', 'core/cover' ), true ) ) );
		$issues   = array_merge( $issues, $this->sections( $sections ) );

		// Presets and spacing.
		if ( $f['raw'] ) {
			$issues[] = 'Raw values instead of theme presets for: ' . implode( ', ', array_keys( $f['raw'] ) ) . '. Use the color slugs, font size slugs and spacing presets of the design charter.';
		}
		if ( $f['margins'] ) {
			$issues[] = sprintf( '%d heading(s) or paragraph(s) have a margin set by hand: remove it and let the theme block gap space the elements.', $f['margins'] );
		}

		// Text.
		if ( $f['long'] ) {
			$issues[] = sprintf( '%d paragraph(s) are longer than 80 words: split them or turn them into a list.', $f['long'] );
		}
		if ( $f['centered'] ) {
			$issues[] = sprintf( '%d long paragraph(s) are centered: left align body text, center only short intros.', $f['centered'] );
		}

		// Buttons.
		if ( $f['crowded'] ) {
			$issues[] = 'A button group has more than 2 buttons: keep one primary action and at most one secondary.';
		}
		if ( $f['no_href'] ) {
			$issues[] = sprintf( '%d button(s) have no link: give each button a real URL (list-content for internal pages).', $f['no_href'] );
		}

		// Visuals and motion.
		if ( ! $f['images'] && count( $sections ) >= 3 ) {
			$issues[] = 'The page has no image: add visuals from the media library (list-media) in the hero and in some sections.';
		}
		if ( $f['no_alt'] ) {
			$issues[] = sprintf( '%d image(s) have no alt text: describe each image.', $f['no_alt'] );
		}
		if ( $f['nested_anim'] ) {
			$issues[] = sprintf( '%d block(s) are animated inside an animated parent: animate either the section or its children, not both.', $f['nested_anim'] );
		} elseif ( $f['animated'] > count( $sections ) + 6 ) {
			$issues[] = sprintf( '%d animations is too many for %d sections: keep one per section or one per card in a row.', $f['animated'], count( $sections ) );
		}

		return array_slice( $issues, 0, self::MAX_ISSUES );
	}

	/**
	 * Section rhythm: padding, backgrounds, strong color usage.
	 *
	 * @param array<int, array<string, mixed>> $sections Top-level groups and covers.
	 * @return string[]
	 */
	private function sections( $sections ) {
		$issues   = array();
		$paddings = array();
		$colors   = array();
		$bare     = 0;

		foreach ( $sections as $i => $section ) {
			$a = $section['attrs'];
			// A cover with a photo is a visual, not a colored band.
			$bg      = $a['backgroundColor'] ?? ( 'core/cover' === $section['blockName'] && empty( $a['url'] ) ? ( $a['overlayColor'] ?? null ) : null );
			$padding = $a['style']['spacing']['padding']['top'] ?? null;

			if ( $bg ) {
				$colors[] = $bg;
			}
			if ( 'full' === ( $a['align'] ?? '' ) && $bg && ! $padding && 'core/cover' !== $section['blockName'] ) {
				++$bare;
			}
			if ( $i > 0 && $padding ) {
				$paddings[] = $padding;
			}
		}

		$paddings = array_unique( $paddings );
		if ( count( $paddings ) > 1 ) {
			$issues[] = 'Sections use different paddings (' . implode( ', ', $paddings ) . '): use the section padding of the design charter on every section after the hero.';
		}
		if ( $bare ) {
			$issues[] = sprintf( '%d colored full-width section(s) have no padding: add the section padding top and bottom.', $bare );
		}

		$neutral = array_filter( array( $this->t['page'], $this->t['surface'] ) );
		$accents = array_unique( array_diff( $colors, $neutral ) );
		if ( count( $accents ) > 2 ) {
			$issues[] = 'Too many section colors (' . implode( ', ', $accents ) . '): alternate page background and surface, and keep the strong color for the hero and the final call to action.';
		}

		$strong = $this->t['strong'];
		if ( $strong && count( array_keys( $colors, $strong, true ) ) > 2 ) {
			$issues[] = 'The strong color is used on more than two sections: keep it for the hero and the final call to action.';
		}
		$highlight = $this->t['highlight'];
		if ( $highlight && count( array_keys( $colors, $highlight, true ) ) > 1 ) {
			$issues[] = 'The highlight color is used on several sections: use it once at most.';
		}

		return $issues;
	}

	/**
	 * Walks blocks with the background and text color they inherit.
	 *
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @param array<string, mixed>             $ctx    Inherited bg, text and animation.
	 */
	private function walk( $blocks, $ctx ) {
		$palette = $this->t['palette'];
		$f       = &$this->facts;

		foreach ( $blocks as $block ) {
			$name = $block['blockName'];
			if ( ! $name ) {
				continue;
			}
			$a   = is_array( $block['attrs'] ) ? $block['attrs'] : array();
			$own = $ctx;

			// Colors this block sets for itself and its children.
			$bg = $this->color( $a, 'backgroundColor', 'background' );
			if ( 'core/cover' === $name ) {
				$overlay = $this->color( $a, 'overlayColor', null ) ?? Design_Charter::hex( $a['customOverlayColor'] ?? '' );
				$dim     = $a['dimRatio'] ?? 50;
				$bg      = $overlay && $dim >= 50 ? $overlay : ( ! empty( $a['url'] ) ? false : $overlay );
				if ( ! empty( $a['url'] ) ) {
					++$f['images'];
				}
			}
			if ( false === $bg ) {
				$own['bg'] = null; // Photo background: contrast can't be checked.
			} elseif ( $bg ) {
				$own['bg'] = $bg;
			}

			$text = $this->color( $a, 'textColor', 'text' );
			if ( $text ) {
				$own['text'] = $text;
			} elseif ( 'core/cover' === $name && $own['bg'] ) {
				$own['text'] = Design_Charter::luminance( $own['bg'] ) < 0.4 ? '#ffffff' : '#000000';
			}

			$this->raw_values( $a );

			switch ( $name ) {
				case 'core/heading':
					$level = (int) ( $a['level'] ?? 2 );
					if ( 1 === $level ) {
						++$f['h1'];
					}
					if ( $f['last_level'] && $level > $f['last_level'] + 1 && ! $f['skip'] ) {
						$f['skip'] = 'H' . $f['last_level'] . ' to H' . $level;
					}
					$f['last_level'] = $level;
					$this->spacing_by_hand( $a );
					$this->check_text( $own, $name );
					break;

				case 'core/paragraph':
					$words = count( preg_split( '/\s+/u', trim( wp_strip_all_tags( $block['innerHTML'] ) ), -1, PREG_SPLIT_NO_EMPTY ) );
					if ( $words > 80 ) {
						++$f['long'];
					}
					if ( 'center' === ( $a['align'] ?? '' ) && $words > 35 ) {
						++$f['centered'];
					}
					$this->spacing_by_hand( $a );
					$this->check_text( $own, $name );
					break;

				case 'core/list':
				case 'core/quote':
					$this->check_text( $own, $name );
					break;

				case 'core/buttons':
					if ( count( $block['innerBlocks'] ) > 2 ) {
						++$f['crowded'];
					}
					break;

				case 'core/button':
					$this->check_button( $block, $ctx );
					break;

				case 'core/image':
					++$f['images'];
					if ( preg_match( '/<img[^>]*\salt="\s*"/', $block['innerHTML'] ) || ! preg_match( '/<img[^>]*\salt="/', $block['innerHTML'] ) ) {
						++$f['no_alt'];
					}
					break;

				case 'core/media-text':
				case 'eo-blocks/gallery':
				case 'core/gallery':
					++$f['images'];
					break;
			}

			if ( ! empty( $a['eoAnimationIn'] ) ) {
				++$f['animated'];
				if ( $ctx['anim'] ) {
					++$f['nested_anim'];
				}
				$own['anim'] = true;
			}

			$this->walk( $block['innerBlocks'], $own );
		}
	}

	/**
	 * Hex color set by a preset slug or a custom style value.
	 *
	 * @param array<string, mixed> $a      Attributes.
	 * @param string               $preset Preset attribute name.
	 * @param string|null          $style  Key under style.color.
	 * @return string|null
	 */
	private function color( $a, $preset, $style ) {
		if ( ! empty( $a[ $preset ] ) && isset( $this->t['palette'][ $a[ $preset ] ] ) ) {
			return $this->t['palette'][ $a[ $preset ] ];
		}
		if ( $style && ! empty( $a['style']['color'][ $style ] ) ) {
			return Design_Charter::hex( $a['style']['color'][ $style ] );
		}
		return null;
	}

	/**
	 * Records text that is hard to read on its background.
	 *
	 * @param array<string, mixed> $ctx  Colors in effect.
	 * @param string               $name Block name.
	 */
	private function check_text( $ctx, $name ) {
		if ( ! $ctx['bg'] || ! $ctx['text'] ) {
			return;
		}
		$ratio = Design_Charter::contrast( $ctx['bg'], $ctx['text'] );
		if ( $ratio < 4.5 ) {
			$key                             = $ctx['text'] . '/' . $ctx['bg'];
			$this->facts['contrast'][ $key ] = sprintf( '%s text %s on %s (contrast %.1f)', str_replace( 'core/', '', $name ), $this->slug( $ctx['text'] ), $this->slug( $ctx['bg'] ), $ratio );
		}
	}

	/**
	 * Checks a button: link, readable label, visible against its section.
	 *
	 * @param array<string, mixed> $block Button block.
	 * @param array<string, mixed> $ctx   Colors of the section around it.
	 */
	private function check_button( $block, $ctx ) {
		$a       = is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$palette = $this->t['palette'];
		$outline = false !== strpos( $a['className'] ?? '', 'is-style-outline' );

		if ( false === strpos( $block['innerHTML'], 'href=' ) ) {
			++$this->facts['no_href'];
		}

		$theme_bg   = $this->t['button']['background'] ? $palette[ $this->t['button']['background'] ] : null;
		$theme_text = $this->t['button']['text'] ? $palette[ $this->t['button']['text'] ] : null;
		$bg         = $outline ? $ctx['bg'] : ( $this->color( $a, 'backgroundColor', 'background' ) ?? $theme_bg );
		$text       = $this->color( $a, 'textColor', 'text' ) ?? ( $outline ? $ctx['text'] : $theme_text );

		if ( $bg && $text ) {
			$this->check_text(
				array(
					'bg'   => $bg,
					'text' => $text,
				),
				'button'
			);
		}
		if ( ! $outline && $bg && $ctx['bg'] && Design_Charter::contrast( $bg, $ctx['bg'] ) < 1.3 ) {
			++$this->facts['blend'];
		}
	}

	/**
	 * Records raw values where presets exist.
	 *
	 * @param array<string, mixed> $a Attributes.
	 */
	private function raw_values( $a ) {
		$style = $a['style'] ?? array();
		if ( ! empty( $style['color']['background'] ) || ! empty( $style['color']['text'] ) ) {
			$this->facts['raw']['colors'] = true;
		}
		if ( ! empty( $style['typography']['fontSize'] ) && 0 !== strpos( (string) $style['typography']['fontSize'], 'var:' ) ) {
			$this->facts['raw']['font sizes'] = true;
		}
		foreach ( (array) ( $style['spacing']['padding'] ?? array() ) as $value ) {
			if ( is_string( $value ) && '' !== $value && '0' !== $value && 0 !== strpos( $value, 'var:' ) ) {
				$this->facts['raw']['paddings'] = true;
			}
		}
	}

	/**
	 * Counts margins set by hand on text blocks.
	 *
	 * @param array<string, mixed> $a Attributes.
	 */
	private function spacing_by_hand( $a ) {
		if ( ! empty( $a['style']['spacing']['margin'] ) ) {
			++$this->facts['margins'];
		}
	}

	/**
	 * Palette slug of a hex color, for readable messages.
	 *
	 * @param string $hex Color.
	 * @return string
	 */
	private function slug( $hex ) {
		$slug = array_search( $hex, $this->t['palette'], true );
		return $slug ? '`' . $slug . '`' : $hex;
	}
}
