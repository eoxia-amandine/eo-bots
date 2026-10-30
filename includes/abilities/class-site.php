<?php
/**
 * Read-only abilities: the site, its design system, blocks, patterns and media.
 *
 * @package EoBots
 */

namespace EoBots\Includes\Abilities;

use EoBots\Includes\Site_Profile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets the bot look around before acting, so new content matches what already exists.
 */
class Site {

	/**
	 * Registers the abilities.
	 */
	public static function register() {
		$read = array(
			'annotations' => Registry::annotations( true ),
			'eobots'      => array( 'confirm' => 'never' ),
		);

		Registry::add(
			'get-site-overview',
			array(
				'label'               => __( 'Look at the site', 'eo-bots' ),
				'description'         => 'Returns an overview of the site: name, URL, language, theme, active plugins, content types with counts, home page, menus and multilingual setup.',
				'input_schema'        => self::object_schema( array() ),
				'execute_callback'    => array( Site_Profile::class, 'get' ),
				'permission_callback' => array( Registry::class, 'can_edit' ),
				'meta'                => array_merge_recursive( $read, array( 'eobots' => array( 'progress' => __( 'Looking around the site…', 'eo-bots' ) ) ) ),
			)
		);

		Registry::add(
			'get-design-system',
			array(
				'label'               => __( 'Read the theme styles', 'eo-bots' ),
				'description'         => 'Returns the theme design system from theme.json: color palette, gradients, font families, font sizes, spacing sizes and layout widths. Use the preset slugs in block attributes (e.g. "backgroundColor":"primary") instead of raw colors.',
				'input_schema'        => self::object_schema( array() ),
				'execute_callback'    => array( __CLASS__, 'design_system' ),
				'permission_callback' => array( Registry::class, 'can_edit' ),
				'meta'                => array_merge_recursive( $read, array( 'eobots' => array( 'progress' => __( 'Reading your theme styles…', 'eo-bots' ) ) ) ),
			)
		);

		Registry::add(
			'list-block-types',
			array(
				'label'               => __( 'List available blocks', 'eo-bots' ),
				'description'         => 'Lists the blocks available in the editor (core and plugins). Filter by namespace (e.g. "core", "eo-blocks") or search text. Use it to know which blocks you may use in content.',
				'input_schema'        => self::object_schema(
					array(
						'namespace' => array(
							'type'        => 'string',
							'description' => 'Block namespace, e.g. core.',
						),
						'search'    => array(
							'type'        => 'string',
							'description' => 'Text searched in block names and titles.',
						),
					)
				),
				'execute_callback'    => array( __CLASS__, 'block_types' ),
				'permission_callback' => array( Registry::class, 'can_edit' ),
				'meta'                => array_merge_recursive( $read, array( 'eobots' => array( 'progress' => __( 'Checking the available blocks…', 'eo-bots' ) ) ) ),
			)
		);

		Registry::add(
			'list-patterns',
			array(
				'label'               => __( 'List block patterns', 'eo-bots' ),
				'description'         => 'Lists the block patterns registered by the theme and plugins (ready-made sections such as heroes, call to actions, testimonials). Prefer them when building pages so the result matches the theme.',
				'input_schema'        => self::object_schema(
					array(
						'search'   => array(
							'type'        => 'string',
							'description' => 'Text searched in pattern titles and descriptions.',
						),
						'category' => array(
							'type'        => 'string',
							'description' => 'Pattern category slug.',
						),
					)
				),
				'execute_callback'    => array( __CLASS__, 'patterns' ),
				'permission_callback' => array( Registry::class, 'can_edit' ),
				'meta'                => array_merge_recursive( $read, array( 'eobots' => array( 'progress' => __( 'Browsing the theme patterns…', 'eo-bots' ) ) ) ),
			)
		);

		Registry::add(
			'get-pattern',
			array(
				'label'               => __( 'Read a block pattern', 'eo-bots' ),
				'description'         => 'Returns the block markup of a registered pattern, to reuse or adapt it in content.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Pattern name, as returned by list-patterns.',
						),
					),
					'required'             => array( 'name' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'pattern' ),
				'permission_callback' => array( Registry::class, 'can_edit' ),
				'meta'                => array_merge_recursive( $read, array( 'eobots' => array( 'progress' => __( 'Studying a pattern…', 'eo-bots' ) ) ) ),
			)
		);

		Registry::add(
			'read-guide',
			array(
				'label'               => __( 'Read a plugin guide', 'eo-bots' ),
				'description'         => 'Returns the guide of a plugin or of WordPress itself: its features, the exact block markup and attributes to use them, and design advice. Read the relevant guides before building or redesigning a page.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Guide slug, from the guide list.',
						),
					),
					'required'             => array( 'slug' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'guide' ),
				'permission_callback' => array( Registry::class, 'can_edit' ),
				'meta'                => array_merge_recursive( $read, array( 'eobots' => array( 'progress' => __( 'Reading the user guide…', 'eo-bots' ) ) ) ),
			)
		);

		Registry::add(
			'find-block-examples',
			array(
				'label'               => __( 'Find block examples', 'eo-bots' ),
				'description'         => 'Finds how a block (e.g. "eo-blocks/carousel") or a block attribute (e.g. "eoAnimationIn") is already used in the site content and returns the real markup. Copy these working examples instead of guessing the markup of a block you have not used yet.',
				'input_schema'        => self::object_schema(
					array(
						'block'     => array(
							'type'        => 'string',
							'description' => 'Block name, e.g. core/cover or eo-blocks/tabs.',
						),
						'attribute' => array(
							'type'        => 'string',
							'description' => 'Attribute name to look for in block comments.',
						),
					)
				),
				'execute_callback'    => array( __CLASS__, 'block_examples' ),
				'permission_callback' => array( Registry::class, 'can_edit' ),
				'meta'                => array_merge_recursive( $read, array( 'eobots' => array( 'progress' => __( 'Looking for examples on your site…', 'eo-bots' ) ) ) ),
			)
		);

		Registry::add(
			'list-media',
			array(
				'label'               => __( 'Search the media library', 'eo-bots' ),
				'description'         => 'Searches images in the media library. Returns ID, URL, alt text and size, to insert existing images in content.',
				'input_schema'        => self::object_schema(
					array(
						'search'   => array(
							'type'        => 'string',
							'description' => 'Text searched in title, caption and file name.',
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 40,
							'default' => 20,
						),
					)
				),
				'execute_callback'    => array( __CLASS__, 'media' ),
				'permission_callback' => static function () {
					return current_user_can( 'upload_files' );
				},
				'meta'                => array_merge_recursive( $read, array( 'eobots' => array( 'progress' => __( 'Looking for images…', 'eo-bots' ) ) ) ),
			)
		);
	}

	/**
	 * Object schema with optional properties and an empty default.
	 *
	 * @param array<string, mixed> $properties Properties.
	 * @return array<string, mixed>
	 */
	public static function object_schema( $properties ) {
		$schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'default'              => array(),
		);
		if ( $properties ) {
			$schema['properties'] = $properties;
		}
		return $schema;
	}

	/**
	 * Design tokens from theme.json.
	 *
	 * @return array<string, mixed>
	 */
	public static function design_system() {
		$settings = wp_get_global_settings();
		$pick     = static function ( $presets, $fields ) {
			$out = array();
			foreach ( array( 'theme', 'custom', 'default' ) as $origin ) {
				foreach ( $presets[ $origin ] ?? array() as $preset ) {
					$out[] = array_intersect_key( $preset, array_flip( $fields ) );
				}
				if ( $out ) {
					break;
				}
			}
			return $out;
		};

		return array(
			'colors'        => $pick( $settings['color']['palette'] ?? array(), array( 'slug', 'name', 'color' ) ),
			'gradients'     => wp_list_pluck( $pick( $settings['color']['gradients'] ?? array(), array( 'slug', 'name' ) ), 'slug' ),
			'font_families' => $pick( $settings['typography']['fontFamilies'] ?? array(), array( 'slug', 'name' ) ),
			'font_sizes'    => $pick( $settings['typography']['fontSizes'] ?? array(), array( 'slug', 'name', 'size' ) ),
			'spacing_sizes' => $pick( $settings['spacing']['spacingSizes'] ?? array(), array( 'slug', 'name', 'size' ) ),
			'layout'        => $settings['layout'] ?? array(),
		);
	}

	/**
	 * Registered block types.
	 *
	 * @param array<string, string> $input Filters.
	 * @return array<string, mixed>
	 */
	public static function block_types( $input = array() ) {
		$namespace = isset( $input['namespace'] ) ? strtolower( (string) $input['namespace'] ) : '';
		$search    = isset( $input['search'] ) ? strtolower( (string) $input['search'] ) : '';
		$blocks    = array();

		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
			if ( isset( $type->supports['inserter'] ) && false === $type->supports['inserter'] ) {
				continue;
			}
			if ( $namespace && 0 !== strpos( $name, $namespace . '/' ) ) {
				continue;
			}
			if ( $search && false === strpos( strtolower( $name . ' ' . $type->title ), $search ) ) {
				continue;
			}
			$block = array(
				'name'  => $name,
				'title' => $type->title,
			);
			if ( $type->parent ) {
				$block['parent'] = $type->parent;
			}
			if ( $namespace || $search ) {
				$block['description'] = Registry::clip( $type->description, 160 );
				$block['attributes']  = array_keys( $type->attributes ?? array() );
			}
			$blocks[] = $block;
		}

		return array(
			'count'  => count( $blocks ),
			'blocks' => $blocks,
		);
	}

	/**
	 * Registered block patterns.
	 *
	 * @param array<string, string> $input Filters.
	 * @return array<string, mixed>
	 */
	public static function patterns( $input = array() ) {
		$search   = isset( $input['search'] ) ? strtolower( (string) $input['search'] ) : '';
		$category = isset( $input['category'] ) ? (string) $input['category'] : '';
		$out      = array();

		foreach ( \WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
			if ( isset( $pattern['inserter'] ) && false === $pattern['inserter'] ) {
				continue;
			}
			$categories = $pattern['categories'] ?? array();
			if ( $category && ! in_array( $category, $categories, true ) ) {
				continue;
			}
			if ( $search && false === strpos( strtolower( $pattern['title'] . ' ' . ( $pattern['description'] ?? '' ) ), $search ) ) {
				continue;
			}
			$out[] = array(
				'name'       => $pattern['name'],
				'title'      => $pattern['title'],
				'categories' => $categories,
			);
		}

		return array(
			'count'    => count( $out ),
			'patterns' => array_slice( $out, 0, 80 ),
		);
	}

	/**
	 * One pattern's markup.
	 *
	 * @param array<string, string> $input Pattern name.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function pattern( $input ) {
		$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( (string) $input['name'] );
		if ( ! $pattern ) {
			return new \WP_Error( 'eobots_pattern_not_found', 'Pattern not found.' );
		}
		return array(
			'name'    => $pattern['name'],
			'title'   => $pattern['title'],
			'content' => Registry::clip( $pattern['content'], 20000 ),
		);
	}

	/**
	 * One guide's content.
	 *
	 * @param array<string, string> $input Guide slug.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function guide( $input ) {
		$guide = \EoBots\Includes\Guides::get( sanitize_key( $input['slug'] ) );
		if ( ! $guide ) {
			return new \WP_Error( 'eobots_guide_not_found', 'No active guide with this slug. Available: ' . implode( ', ', array_keys( \EoBots\Includes\Guides::active() ) ) );
		}
		return array(
			'title'   => $guide['title'],
			'content' => $guide['content'],
		);
	}

	/**
	 * Real usages of a block or attribute in content the user can edit.
	 *
	 * @param array<string, string> $input Block name and/or attribute.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function block_examples( $input = array() ) {
		global $wpdb;

		$block     = isset( $input['block'] ) ? strtolower( trim( (string) $input['block'] ) ) : '';
		$attribute = isset( $input['attribute'] ) ? trim( (string) $input['attribute'] ) : '';
		if ( '' === $block && '' === $attribute ) {
			return new \WP_Error( 'eobots_missing_filter', 'Give a block name or an attribute.' );
		}

		$block = preg_replace( '#^core/#', '', $block );
		$like  = $block ? '<!-- wp:' . $block . ' ' : '"' . $attribute . '"';
		$types = Content::post_types();
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') AND post_type IN ($in) AND post_content LIKE %s ORDER BY post_modified DESC LIMIT 20", array_merge( $types, array( '%' . $wpdb->esc_like( $like ) . '%' ) ) ) );

		$full_name = $block && false === strpos( $block, '/' ) ? 'core/' . $block : $block;
		$examples  = array();

		foreach ( $ids as $id ) {
			if ( count( $examples ) >= 3 || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$match = self::first_block( parse_blocks( get_post_field( 'post_content', $id ) ), $full_name, $attribute );
			if ( $match ) {
				$examples[] = array(
					'post'   => get_the_title( $id ) . ' (#' . $id . ')',
					'markup' => Registry::clip( serialize_block( $match ), 3000 ),
				);
			}
		}

		return array(
			'count'    => count( $examples ),
			'examples' => $examples,
		);
	}

	/**
	 * First block matching a name and/or carrying an attribute.
	 *
	 * @param array<int, array<string, mixed>> $blocks    Parsed blocks.
	 * @param string                           $name      Block name, or empty.
	 * @param string                           $attribute Attribute name, or empty.
	 * @return array<string, mixed>|null
	 */
	private static function first_block( $blocks, $name, $attribute ) {
		foreach ( $blocks as $block ) {
			$name_ok = ! $name || $block['blockName'] === $name;
			$attr_ok = ! $attribute || array_key_exists( $attribute, (array) $block['attrs'] );
			if ( $block['blockName'] && $name_ok && $attr_ok ) {
				return $block;
			}
			$found = self::first_block( $block['innerBlocks'], $name, $attribute );
			if ( $found ) {
				return $found;
			}
		}
		return null;
	}

	/**
	 * Images from the media library.
	 *
	 * @param array<string, mixed> $input Filters.
	 * @return array<string, mixed>
	 */
	public static function media( $input = array() ) {
		$query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				's'              => isset( $input['search'] ) ? (string) $input['search'] : '',
				'posts_per_page' => isset( $input['per_page'] ) ? (int) $input['per_page'] : 20,
				'no_found_rows'  => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$meta    = wp_get_attachment_metadata( $post->ID );
			$items[] = array(
				'id'     => $post->ID,
				'title'  => get_the_title( $post ),
				'url'    => wp_get_attachment_url( $post->ID ),
				'alt'    => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
				'width'  => $meta['width'] ?? null,
				'height' => $meta['height'] ?? null,
			);
		}

		return array( 'images' => $items );
	}
}
