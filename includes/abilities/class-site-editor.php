<?php
/**
 * Site Editor abilities: templates, template parts (header, footer…) and navigation menus of block themes.
 *
 * @package EoBots
 */

namespace EoBots\Includes\Abilities;

use EoBots\Includes\Journal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Changes here show on every page of the site: editing a template or a part always asks the user,
 * and every change is journaled (undo restores the previous version or the theme's original).
 * Templates go through the core REST controllers, which handle theme files vs customized copies.
 */
class Site_Editor {

	const ROUTES = array(
		'wp_template'      => '/wp/v2/templates/',
		'wp_template_part' => '/wp/v2/template-parts/',
	);

	/**
	 * Registers the abilities.
	 */
	public static function register() {
		$available   = array( __CLASS__, 'available' );
		$type_schema = array(
			'type'        => 'string',
			'enum'        => array( 'part', 'template' ),
			'default'     => 'part',
			'description' => 'part = template part (header, footer, sidebar…), template = full page template.',
		);
		$id_schema   = array(
			'type'        => 'string',
			'description' => 'Template ID from list-templates (e.g. "twentytwentyfive//footer") or its slug.',
		);

		Registry::add(
			'list-templates',
			array(
				'label'               => __( 'List the templates', 'eo-bots' ),
				'description'         => 'Lists the template parts (header, footer, sidebar) or the page templates of the theme, telling which ones are used and which were customized. Start here to change the header, the footer or the menu.',
				'input_schema'        => Site::object_schema( array( 'type' => $type_schema ) ),
				'execute_callback'    => array( __CLASS__, 'list_templates' ),
				'permission_callback' => $available,
				'meta'                => array(
					'annotations' => Registry::annotations( true ),
					'eobots'      => array(
						'confirm'   => 'never',
						'available' => $available,
						'progress'  => __( 'Opening the Site Editor…', 'eo-bots' ),
					),
				),
			)
		);

		Registry::add(
			'get-template',
			array(
				'label'               => __( 'Read a template', 'eo-bots' ),
				'description'         => 'Returns the block markup of a template or template part, and the navigation menus it uses.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'type' => $type_schema,
						'id'   => $id_schema,
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'get_template' ),
				'permission_callback' => $available,
				'meta'                => array(
					'annotations' => Registry::annotations( true ),
					'eobots'      => array(
						'confirm'   => 'never',
						'available' => $available,
						'progress'  => __( 'Reading the template…', 'eo-bots' ),
					),
				),
			)
		);

		Registry::add(
			'update-template',
			array(
				'label'               => __( 'Edit a template', 'eo-bots' ),
				'description'         => 'Replaces the block markup of a template or template part (send the full new markup). It changes every page using it, so the user always confirms. The theme original stays available (reset-template). To change the menu links, use update-navigation instead.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'type'    => $type_schema,
						'id'      => $id_schema,
						'content' => array(
							'type'        => 'string',
							'description' => 'Full serialized block markup.',
						),
					),
					'required'             => array( 'id', 'content' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'update_template' ),
				'permission_callback' => $available,
				'meta'                => array(
					'annotations' => Registry::annotations( false, true ),
					'eobots'      => array(
						'confirm'   => 'always',
						'available' => $available,
						'progress'  => __( 'Editing the template…', 'eo-bots' ),
						'describe'  => static function ( $input ) {
							return array(
								/* translators: %s: template name, e.g. Footer. */
								'title'   => sprintf( __( 'Edit “%s”', 'eo-bots' ), self::name( $input ) ),
								'summary' => __( 'It shows on every page that uses it. You can undo it from the chat.', 'eo-bots' ),
							);
						},
					),
				),
			)
		);

		Registry::add(
			'reset-template',
			array(
				'label'               => __( 'Reset a template', 'eo-bots' ),
				'description'         => 'Removes the customizations of a template or template part and goes back to the theme original. The user always confirms.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'type' => $type_schema,
						'id'   => $id_schema,
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'reset_template' ),
				'permission_callback' => $available,
				'meta'                => array(
					'annotations' => Registry::annotations( false, true ),
					'eobots'      => array(
						'confirm'   => 'always',
						'available' => $available,
						'progress'  => __( 'Restoring the theme original…', 'eo-bots' ),
						'describe'  => static function ( $input ) {
							return array(
								/* translators: %s: template name, e.g. Footer. */
								'title'   => sprintf( __( 'Reset “%s” to the theme original', 'eo-bots' ), self::name( $input ) ),
								'summary' => __( 'Your customizations of it are removed. You can undo it from the chat.', 'eo-bots' ),
								'danger'  => true,
							);
						},
					),
				),
			)
		);

		Registry::add(
			'list-navigations',
			array(
				'label'               => __( 'List the menus', 'eo-bots' ),
				'description'         => 'Lists the navigation menus (wp_navigation) with their links as block markup and the template parts showing them.',
				'input_schema'        => Site::object_schema( array() ),
				'execute_callback'    => array( __CLASS__, 'list_navigations' ),
				'permission_callback' => $available,
				'meta'                => array(
					'annotations' => Registry::annotations( true ),
					'eobots'      => array(
						'confirm'   => 'never',
						'available' => $available,
						'progress'  => __( 'Looking at your menus…', 'eo-bots' ),
					),
				),
			)
		);

		Registry::add(
			'update-navigation',
			array(
				'label'               => __( 'Edit a menu', 'eo-bots' ),
				'description'         => 'Replaces the links of a navigation menu: content is the inner block markup (core/navigation-link, core/navigation-submenu, core/page-list…), without the core/navigation wrapper. The user confirms when the menu is in use.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'      => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'update_navigation' ),
				'permission_callback' => array( __CLASS__, 'can_edit_navigation' ),
				'meta'                => array(
					'annotations' => Registry::annotations( false, true ),
					'eobots'      => array(
						'confirm'   => 'live',
						'available' => $available,
						'progress'  => __( 'Rearranging the menu…', 'eo-bots' ),
						'describe'  => static function ( $input ) {
							$labels = self::link_labels( (string) ( $input['content'] ?? '' ) );
							return array(
								/* translators: %s: menu name. */
								'title'   => sprintf( __( 'Edit the menu “%s”', 'eo-bots' ), get_the_title( (int) $input['id'] ) ),
								'summary' => $labels
									/* translators: %s: list of menu links. */
									? sprintf( __( 'New links: %s.', 'eo-bots' ), implode( ', ', $labels ) )
									: __( 'Visitors will see the change right away.', 'eo-bots' ),
							);
						},
					),
				),
			)
		);

		Registry::add(
			'create-navigation',
			array(
				'label'               => __( 'Create a menu', 'eo-bots' ),
				'description'         => 'Creates a navigation menu (it shows nowhere until a navigation block of a template part uses it with {"ref":ID}). Content is the inner block markup of the links.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
					),
					'required'             => array( 'title', 'content' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'create_navigation' ),
				'permission_callback' => $available,
				'meta'                => array(
					'annotations' => Registry::annotations( false ),
					'eobots'      => array(
						'confirm'   => 'never',
						'available' => $available,
						'progress'  => __( 'Building a menu…', 'eo-bots' ),
					),
				),
			)
		);
	}

	/* ---------- Permissions ---------- */

	/**
	 * The Site Editor exists for block themes, for users who can edit the theme options.
	 *
	 * @return bool
	 */
	public static function available() {
		return wp_is_block_theme() && current_user_can( 'edit_theme_options' );
	}

	/**
	 * Whether the targeted post is a menu the user can edit.
	 *
	 * @param array<string, mixed> $input Input with an id.
	 * @return bool
	 */
	public static function can_edit_navigation( $input ) {
		$post = get_post( (int) ( $input['id'] ?? 0 ) );
		return $post && 'wp_navigation' === $post->post_type && current_user_can( 'edit_post', $post->ID );
	}

	/* ---------- Templates ---------- */

	/**
	 * Lists templates or template parts.
	 *
	 * @param array<string, mixed> $input Type.
	 * @return array<string, mixed>
	 */
	public static function list_templates( $input = array() ) {
		$type  = self::post_type( $input );
		$items = array();
		foreach ( get_block_templates( array(), $type ) as $template ) {
			$item = array(
				'id'         => $template->id,
				'title'      => $template->title,
				'customized' => 'custom' === $template->source,
				'theme_file' => (bool) $template->has_theme_file,
			);
			if ( 'wp_template_part' === $type ) {
				$item['area'] = $template->area;
			} elseif ( $template->description ) {
				$item['description'] = wp_strip_all_tags( $template->description );
			}
			$items[] = $item;
		}

		$result = array(
			'theme' => get_stylesheet(),
			'items' => $items,
		);
		if ( 'wp_template_part' === $type ) {
			$result['used_in_templates'] = self::used_parts();
		}
		return $result;
	}

	/**
	 * Returns one template.
	 *
	 * @param array<string, mixed> $input Type and id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_template( $input ) {
		$template = self::find( $input );
		if ( is_wp_error( $template ) ) {
			return $template;
		}

		// Theme parts often only reference a pattern: show its blocks so they can be edited.
		$content  = $template->content;
		$expanded = false;
		if ( has_block( 'core/pattern', $content ) && function_exists( 'resolve_pattern_blocks' ) ) {
			$content  = serialize_blocks( resolve_pattern_blocks( parse_blocks( $content ) ) );
			$expanded = true;
		}

		$refs = array();
		if ( preg_match_all( '/<!-- wp:navigation \{[^}]*"ref":(\d+)/', $content, $matches ) ) {
			foreach ( array_unique( $matches[1] ) as $ref ) {
				$refs[] = array(
					'id'    => (int) $ref,
					'title' => get_the_title( (int) $ref ),
				);
			}
		}

		$result = array(
			'id'          => $template->id,
			'title'       => $template->title,
			'area'        => $template->area ?? null,
			'customized'  => 'custom' === $template->source,
			'navigations' => $refs,
			'content'     => Registry::clip( $content, 30000 ),
		);
		if ( $expanded ) {
			$result['note'] = 'The template referenced theme patterns; they are expanded here. Edit and save this expanded markup.';
		}
		return $result;
	}

	/**
	 * Saves new markup for a template.
	 *
	 * @param array<string, mixed> $input Type, id and content.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function update_template( $input ) {
		$template = self::find( $input );
		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$type   = $template->type;
		$before = 'custom' === $template->source ? $template->content : null;
		$saved  = self::write( $type, $template->id, $input['content'] );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$journal_id = self::record(
			'update-template',
			$template,
			/* translators: %s: template name. */
			sprintf( __( '“%s” edited', 'eo-bots' ), $template->title ),
			$before
		);

		$result   = array(
			'id'         => $template->id,
			'title'      => $template->title,
			'journal_id' => $journal_id,
			'view'       => home_url( '/' ),
		);
		$warnings = Content::check_blocks( $input['content'] );
		if ( $warnings ) {
			$result['warnings'] = $warnings;
		}
		return $result;
	}

	/**
	 * Goes back to the theme original.
	 *
	 * @param array<string, mixed> $input Type and id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function reset_template( $input ) {
		$template = self::find( $input );
		if ( is_wp_error( $template ) ) {
			return $template;
		}
		if ( 'custom' !== $template->source ) {
			return new \WP_Error( 'eobots_template_not_customized', 'This template is already the theme original.' );
		}
		if ( ! $template->has_theme_file ) {
			return new \WP_Error( 'eobots_template_no_original', 'This template was created on the site, it has no theme original to go back to.' );
		}

		$reset = self::revert( $template->type, $template->id );
		if ( is_wp_error( $reset ) ) {
			return $reset;
		}

		$journal_id = self::record(
			'reset-template',
			$template,
			/* translators: %s: template name. */
			sprintf( __( '“%s” reset to the theme original', 'eo-bots' ), $template->title ),
			$template->content
		);

		return array(
			'id'         => $template->id,
			'reset'      => true,
			'journal_id' => $journal_id,
		);
	}

	/**
	 * Writes template markup through the core REST controller (creates the customized copy if needed).
	 *
	 * @param string $type    wp_template or wp_template_part.
	 * @param string $id      Template ID.
	 * @param string $content Block markup.
	 * @return true|\WP_Error
	 */
	public static function write( $type, $id, $content ) {
		$request = new \WP_REST_Request( 'POST', self::ROUTES[ $type ] . $id );
		$request->set_param( 'content', $content );
		return self::dispatch( $request );
	}

	/**
	 * Deletes the customized copy of a theme template, so the theme file is used again.
	 *
	 * @param string $type wp_template or wp_template_part.
	 * @param string $id   Template ID.
	 * @return true|\WP_Error
	 */
	public static function revert( $type, $id ) {
		$request = new \WP_REST_Request( 'DELETE', self::ROUTES[ $type ] . $id );
		$request->set_param( 'force', true );
		return self::dispatch( $request );
	}

	/**
	 * Site Editor link for a template.
	 *
	 * @param string $type wp_template or wp_template_part.
	 * @param string $id   Template ID.
	 * @return string
	 */
	public static function editor_url( $type, $id ) {
		return add_query_arg(
			array(
				'p'      => rawurlencode( '/' . $type . '/' . $id ),
				'canvas' => 'edit',
			),
			admin_url( 'site-editor.php' )
		);
	}

	/* ---------- Navigation ---------- */

	/**
	 * Lists the navigation menus.
	 *
	 * @return array<string, mixed>
	 */
	public static function list_navigations() {
		$used = array();
		foreach ( get_block_templates( array(), 'wp_template_part' ) as $part ) {
			if ( preg_match_all( '/<!-- wp:navigation \{[^}]*"ref":(\d+)/', $part->content, $matches ) ) {
				foreach ( $matches[1] as $ref ) {
					$used[ (int) $ref ][] = $part->id;
				}
			}
		}

		$items = array();
		foreach ( get_posts(
			array(
				'post_type'      => 'wp_navigation',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 50,
			)
		) as $post ) {
			$items[] = array(
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'used_in' => $used[ $post->ID ] ?? array(),
				'content' => Registry::clip( $post->post_content, 8000 ),
			);
		}

		return array(
			'items' => $items,
			'note'  => 'A navigation block without "ref" shows a fallback menu. core/page-list lists every published page automatically; replace it with navigation-link blocks to choose the links.',
		);
	}

	/**
	 * Changes the links or the name of a menu.
	 *
	 * @param array<string, mixed> $input Id, title, content.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function update_navigation( $input ) {
		$post   = get_post( (int) $input['id'] );
		$data   = array( 'ID' => $post->ID );
		$before = array();
		foreach ( array(
			'title'   => 'post_title',
			'content' => 'post_content',
		) as $key => $field ) {
			if ( isset( $input[ $key ] ) ) {
				$data[ $field ]   = 'title' === $key ? sanitize_text_field( $input[ $key ] ) : $input[ $key ];
				$before[ $field ] = $post->$field;
			}
		}
		if ( ! $before ) {
			return new \WP_Error( 'eobots_nothing_to_update', 'Give a title or content.' );
		}

		$result = wp_update_post( wp_slash( $data ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$journal_id = Journal::record(
			array(
				'ability'     => 'eobots/update-navigation',
				'object_type' => 'navigation',
				'object_id'   => $post->ID,
				/* translators: %s: menu name. */
				'summary'     => sprintf( __( 'Menu “%s” edited', 'eo-bots' ), get_the_title( $post->ID ) ),
				'undo'        => array(
					'action' => 'restore',
					'fields' => $before,
				),
			)
		);

		return self::navigation_result( $post->ID, $journal_id, $input['content'] ?? '' );
	}

	/**
	 * Creates a menu.
	 *
	 * @param array<string, mixed> $input Title and content.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function create_navigation( $input ) {
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => 'wp_navigation',
					'post_status'  => 'publish',
					'post_title'   => sanitize_text_field( $input['title'] ),
					'post_content' => $input['content'],
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$journal_id = Journal::record(
			array(
				'ability'     => 'eobots/create-navigation',
				'object_type' => 'navigation',
				'object_id'   => $id,
				/* translators: %s: menu name. */
				'summary'     => sprintf( __( 'Menu “%s” created', 'eo-bots' ), get_the_title( $id ) ),
				'undo'        => array( 'action' => 'trash' ),
			)
		);

		return self::navigation_result( $id, $journal_id, $input['content'] );
	}

	/* ---------- Helpers ---------- */

	/**
	 * Post type behind the "type" input.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return string
	 */
	private static function post_type( $input ) {
		return 'template' === ( $input['type'] ?? 'part' ) ? 'wp_template' : 'wp_template_part';
	}

	/**
	 * Finds a template by full ID or slug.
	 *
	 * @param array<string, mixed> $input Type and id.
	 * @return \WP_Block_Template|\WP_Error
	 */
	private static function find( $input ) {
		$id = trim( (string) ( $input['id'] ?? '' ) );
		if ( false === strpos( $id, '//' ) ) {
			$id = get_stylesheet() . '//' . $id;
		}
		$template = get_block_template( $id, self::post_type( $input ) );
		if ( ! $template ) {
			return new \WP_Error( 'eobots_template_not_found', 'No such template. Call list-templates (type "part" or "template") to get the IDs.' );
		}
		return $template;
	}

	/**
	 * Human name of the targeted template, for confirmation cards.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return string
	 */
	private static function name( $input ) {
		$template = self::find( $input );
		return is_wp_error( $template ) ? (string) ( $input['id'] ?? '' ) : $template->title;
	}

	/**
	 * Which template parts each page template includes.
	 *
	 * @return array<string, string[]>
	 */
	private static function used_parts() {
		$used = array();
		foreach ( get_block_templates( array(), 'wp_template' ) as $template ) {
			if ( preg_match_all( '/<!-- wp:template-part \{[^}]*"slug":"([^"]+)"/', $template->content, $matches ) ) {
				foreach ( array_unique( $matches[1] ) as $slug ) {
					$used[ $slug ][] = $template->slug;
				}
			}
		}
		return $used;
	}

	/**
	 * Runs an internal REST request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	private static function dispatch( $request ) {
		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			return $response->as_error();
		}
		return true;
	}

	/**
	 * Journals a template change. Undo writes the previous markup back, or reverts to the theme file when there was none.
	 *
	 * @param string             $ability  Ability name without prefix.
	 * @param \WP_Block_Template $template Template before the change.
	 * @param string             $summary  Card text.
	 * @param string|null        $before   Previous customized markup; null when it was the theme original.
	 * @return int
	 */
	private static function record( $ability, $template, $summary, $before ) {
		return Journal::record(
			array(
				'ability'     => 'eobots/' . $ability,
				'object_type' => 'template',
				'object_id'   => 0,
				'summary'     => $summary,
				'undo'        => array(
					'action'   => 'template',
					'type'     => $template->type,
					'template' => $template->id,
					'content'  => $before,
				),
			)
		);
	}

	/**
	 * Result of menu changes.
	 *
	 * @param int    $id         Menu ID.
	 * @param int    $journal_id Journal entry ID.
	 * @param string $content    Markup sent.
	 * @return array<string, mixed>
	 */
	private static function navigation_result( $id, $journal_id, $content ) {
		$result = array(
			'id'         => $id,
			'title'      => get_the_title( $id ),
			'journal_id' => $journal_id,
		);
		if ( '' !== $content ) {
			$warnings = Content::check_blocks( $content );
			if ( preg_match( '/<!-- wp:navigation[\s{]/', $content ) ) {
				$warnings[] = 'Send only the links, without the core/navigation wrapper block.';
			}
			if ( $warnings ) {
				$result['warnings'] = $warnings;
			}
		}
		return $result;
	}

	/**
	 * Labels of the links in navigation markup, for the confirmation card.
	 *
	 * @param string $content Markup.
	 * @return string[]
	 */
	private static function link_labels( $content ) {
		$labels = array();
		$walk   = static function ( $blocks ) use ( &$walk, &$labels ) {
			foreach ( $blocks as $block ) {
				if ( ! empty( $block['attrs']['label'] ) ) {
					$labels[] = wp_strip_all_tags( $block['attrs']['label'] );
				} elseif ( 'core/page-list' === $block['blockName'] ) {
					$labels[] = __( 'all pages', 'eo-bots' );
				}
				$walk( $block['innerBlocks'] );
			}
		};
		$walk( parse_blocks( $content ) );
		return array_slice( $labels, 0, 12 );
	}
}
