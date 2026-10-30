<?php
/**
 * Content abilities: read, create, edit, publish and trash posts and pages.
 *
 * @package EoBots
 */

namespace EoBots\Includes\Abilities;

use EoBots\Includes\Journal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guardrails: new content is always a draft, every change is journaled,
 * and anything touching the live site waits for the user's confirmation.
 */
class Content {

	const LIVE_STATUSES = array( 'publish', 'future', 'private' );

	/**
	 * Post types the bot may work with.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$excluded = array( 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles', 'wp_font_family', 'wp_font_face' );
		$types    = array_diff( get_post_types( array( 'show_ui' => true ) ), $excluded );

		return array_values( (array) apply_filters( 'eobots_post_types', $types ) );
	}

	/**
	 * Registers the abilities.
	 */
	public static function register() {
		$id_schema = array(
			'type'        => 'integer',
			'minimum'     => 1,
			'description' => 'Post ID.',
		);

		Registry::add(
			'list-content',
			array(
				'label'               => __( 'List content', 'eo-bots' ),
				'description'         => 'Lists posts, pages or other content the user can edit, newest first. Use it to find existing content before creating or editing.',
				'input_schema'        => Site::object_schema(
					array(
						'post_type' => array(
							'type'    => 'string',
							'enum'    => self::post_types(),
							'default' => 'page',
						),
						'search'    => array( 'type' => 'string' ),
						'status'    => array(
							'type'    => 'string',
							'enum'    => array( 'any', 'publish', 'draft', 'pending', 'private', 'future' ),
							'default' => 'any',
						),
						'per_page'  => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
							'default' => 20,
						),
						'page'      => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
					)
				),
				'execute_callback'    => array( __CLASS__, 'list_content' ),
				'permission_callback' => array( Registry::class, 'can_edit' ),
				'meta'                => array(
					'annotations' => Registry::annotations( true ),
					'eobots'      => array(
						'confirm'  => 'never',
						'progress' => __( 'Looking at your content…', 'eo-bots' ),
					),
				),
			)
		);

		Registry::add(
			'get-content',
			array(
				'label'               => __( 'Read content', 'eo-bots' ),
				'description'         => 'Returns one post or page with its block markup, status, links, template and featured image. Read comparable pages before writing so new content follows the same structure and tone.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => $id_schema ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'get_content' ),
				'permission_callback' => array( __CLASS__, 'can_edit_post' ),
				'meta'                => array(
					'annotations' => Registry::annotations( true ),
					'eobots'      => array(
						'confirm'  => 'never',
						'progress' => __( 'Reading a page…', 'eo-bots' ),
					),
				),
			)
		);

		Registry::add(
			'review-design',
			array(
				'label'               => __( 'Review the design', 'eo-bots' ),
				'description'         => 'Checks an existing page against the UI/UX charter of the site (hierarchy, contrast, spacing, colors, images, animations) and lists what to fix. Use it before improving or redesigning a page.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => $id_schema ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					$post   = get_post( (int) $input['id'] );
					$issues = \EoBots\Includes\Design_Review::review( $post->post_content, $post->ID );
					return array( 'design_review' => $issues ? $issues : 'OK, nothing to fix.' );
				},
				'permission_callback' => array( __CLASS__, 'can_edit_post' ),
				'meta'                => array(
					'annotations' => Registry::annotations( true ),
					'eobots'      => array(
						'confirm'  => 'never',
						'progress' => __( 'Checking the design…', 'eo-bots' ),
					),
				),
			)
		);

		Registry::add(
			'create-content',
			array(
				'label'               => __( 'Create a draft', 'eo-bots' ),
				'description'         => 'Creates a new post, page or other content as a DRAFT (never published directly). Content must be serialized block markup (<!-- wp:paragraph --><p>…</p><!-- /wp:paragraph -->). Returns warnings about invalid or unknown blocks: fix them with update-content.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_type' => array(
							'type'    => 'string',
							'enum'    => self::post_types(),
							'default' => 'page',
						),
						'title'     => array( 'type' => 'string' ),
						'content'   => array(
							'type'        => 'string',
							'description' => 'Serialized block markup.',
						),
						'excerpt'   => array( 'type' => 'string' ),
						'parent'    => array(
							'type'        => 'integer',
							'description' => 'Parent page ID, for hierarchical types.',
						),
						'slug'      => array( 'type' => 'string' ),
						'template'  => array(
							'type'        => 'string',
							'description' => 'Page template slug from the design charter, "default" for the default one.',
						),
					),
					'required'             => array( 'title', 'content' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'create_content' ),
				'permission_callback' => array( __CLASS__, 'can_create' ),
				'meta'                => array(
					'annotations' => Registry::annotations( false ),
					'eobots'      => array(
						'confirm'  => 'never',
						'progress' => __( 'Putting the blocks together…', 'eo-bots' ),
					),
				),
			)
		);

		Registry::add(
			'update-content',
			array(
				'label'               => __( 'Edit content', 'eo-bots' ),
				'description'         => 'Changes the title, block content or excerpt of existing content. The previous version is kept as a revision. Send the full new block markup for content, not a diff. When the content is live on the site, the user is asked to confirm first.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'       => $id_schema,
						'title'    => array( 'type' => 'string' ),
						'content'  => array(
							'type'        => 'string',
							'description' => 'Full serialized block markup.',
						),
						'excerpt'  => array( 'type' => 'string' ),
						'template' => array(
							'type'        => 'string',
							'description' => 'Page template slug from the design charter, "default" for the default one.',
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'update_content' ),
				'permission_callback' => array( __CLASS__, 'can_edit_post' ),
				'meta'                => array(
					'annotations' => Registry::annotations( false, true ),
					'eobots'      => array(
						'confirm'  => 'live',
						'progress' => __( 'Editing the page…', 'eo-bots' ),
						'describe' => static function ( $input ) {
							$fields = array_intersect_key(
								array(
									'title'   => __( 'title', 'eo-bots' ),
									'content' => __( 'content', 'eo-bots' ),
									'excerpt' => __( 'excerpt', 'eo-bots' ),
								),
								(array) $input
							);
							return array(
								/* translators: %s: content title. */
								'title'   => sprintf( __( 'Edit “%s”', 'eo-bots' ), self::label( $input ) ),
								/* translators: %s: list of changed fields. */
								'summary' => sprintf( __( 'It is visible on your site. I will change: %s. The current version stays in the revisions.', 'eo-bots' ), implode( ', ', $fields ) ),
							);
						},
					),
				),
			)
		);

		Registry::add(
			'set-content-status',
			array(
				'label'               => __( 'Publish or unpublish', 'eo-bots' ),
				'description'         => 'Changes the status of content: publish it, move it back to draft, mark it pending review or private. The user always confirms first.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'     => $id_schema,
						'status' => array(
							'type' => 'string',
							'enum' => array( 'publish', 'draft', 'pending', 'private' ),
						),
					),
					'required'             => array( 'id', 'status' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'set_status' ),
				'permission_callback' => array( __CLASS__, 'can_set_status' ),
				'meta'                => array(
					'annotations' => Registry::annotations( false ),
					'eobots'      => array(
						'confirm'  => 'always',
						'progress' => __( 'Updating the status…', 'eo-bots' ),
						'describe' => static function ( $input ) {
							$title = self::label( $input );
							if ( 'publish' === ( $input['status'] ?? '' ) ) {
								return array(
									/* translators: %s: content title. */
									'title'   => sprintf( __( 'Publish “%s”', 'eo-bots' ), $title ),
									'summary' => __( 'It will be visible to every visitor.', 'eo-bots' ),
								);
							}
							return array(
								/* translators: %s: content title. */
								'title'   => sprintf( __( 'Unpublish “%s”', 'eo-bots' ), $title ),
								'summary' => __( 'Visitors will no longer see it.', 'eo-bots' ),
								'danger'  => true,
							);
						},
					),
				),
			)
		);

		Registry::add(
			'trash-content',
			array(
				'label'               => __( 'Move to trash', 'eo-bots' ),
				'description'         => 'Moves content to the trash (it can be restored for 30 days). The user always confirms first.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => $id_schema ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'trash' ),
				'permission_callback' => static function ( $input ) {
					return current_user_can( 'delete_post', (int) ( $input['id'] ?? 0 ) );
				},
				'meta'                => array(
					'annotations' => Registry::annotations( false, true ),
					'eobots'      => array(
						'confirm'  => 'always',
						'progress' => __( 'Tidying up…', 'eo-bots' ),
						'describe' => static function ( $input ) {
							return array(
								/* translators: %s: content title. */
								'title'   => sprintf( __( 'Move “%s” to the trash', 'eo-bots' ), self::label( $input ) ),
								'summary' => __( 'You can restore it from the trash for 30 days.', 'eo-bots' ),
								'danger'  => true,
							);
						},
					),
				),
			)
		);
	}

	/* ---------- Permissions ---------- */

	/**
	 * Whether the user can edit the targeted post.
	 *
	 * @param array<string, mixed> $input Input with an id.
	 * @return bool
	 */
	public static function can_edit_post( $input ) {
		$post = get_post( (int) ( $input['id'] ?? 0 ) );
		return $post && in_array( $post->post_type, self::post_types(), true ) && current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Whether the user can create content of the requested type.
	 *
	 * @param array<string, mixed> $input Input with a post_type.
	 * @return bool
	 */
	public static function can_create( $input ) {
		$object = get_post_type_object( $input['post_type'] ?? 'page' );
		return $object && current_user_can( $object->cap->create_posts );
	}

	/**
	 * Publishing needs the publish capability; other statuses need edit rights.
	 *
	 * @param array<string, mixed> $input Input with id and status.
	 * @return bool
	 */
	public static function can_set_status( $input ) {
		if ( ! self::can_edit_post( $input ) ) {
			return false;
		}
		if ( in_array( $input['status'] ?? '', array( 'publish', 'private' ), true ) ) {
			return current_user_can( 'publish_post', (int) $input['id'] );
		}
		return true;
	}

	/* ---------- Callbacks ---------- */

	/**
	 * Lists content.
	 *
	 * @param array<string, mixed> $input Filters.
	 * @return array<string, mixed>
	 */
	public static function list_content( $input = array() ) {
		$query = new \WP_Query(
			array(
				'post_type'      => $input['post_type'] ?? 'page',
				'post_status'    => $input['status'] ?? 'any',
				's'              => $input['search'] ?? '',
				'posts_per_page' => $input['per_page'] ?? 20,
				'paged'          => $input['page'] ?? 1,
				'perm'           => 'editable',
				'orderby'        => 'modified',
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = array(
				'id'       => $post->ID,
				'title'    => get_the_title( $post ),
				'status'   => $post->post_status,
				'parent'   => $post->post_parent,
				'modified' => $post->post_modified,
				'link'     => get_permalink( $post ),
			);
		}

		return array(
			'total' => (int) $query->found_posts,
			'items' => $items,
		);
	}

	/**
	 * Returns one piece of content.
	 *
	 * @param array<string, mixed> $input Input with an id.
	 * @return array<string, mixed>
	 */
	public static function get_content( $input ) {
		$post   = get_post( (int) $input['id'] );
		$blocks = array();
		foreach ( parse_blocks( $post->post_content ) as $block ) {
			if ( $block['blockName'] ) {
				$blocks[] = $block['blockName'];
			}
		}

		return array(
			'id'             => $post->ID,
			'type'           => $post->post_type,
			'title'          => $post->post_title,
			'status'         => $post->post_status,
			'link'           => get_permalink( $post ),
			'parent'         => $post->post_parent,
			'template'       => get_page_template_slug( $post ),
			'excerpt'        => $post->post_excerpt,
			'featured_image' => get_the_post_thumbnail_url( $post, 'large' ) ? get_the_post_thumbnail_url( $post, 'large' ) : null,
			'top_blocks'     => $blocks,
			'content'        => Registry::clip( $post->post_content, 30000 ),
		);
	}

	/**
	 * Creates a draft.
	 *
	 * @param array<string, mixed> $input Content.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function create_content( $input ) {
		$data = array(
			'post_type'    => $input['post_type'] ?? 'page',
			'post_title'   => sanitize_text_field( $input['title'] ),
			'post_content' => $input['content'],
			'post_status'  => 'draft',
			'post_author'  => get_current_user_id(),
		);
		foreach ( array(
			'excerpt' => 'post_excerpt',
			'parent'  => 'post_parent',
			'slug'    => 'post_name',
		) as $key => $field ) {
			if ( isset( $input[ $key ] ) ) {
				$data[ $field ] = $input[ $key ];
			}
		}
		if ( isset( $input['template'] ) ) {
			$template = self::template( $input['template'], $data['post_type'] );
			if ( is_wp_error( $template ) ) {
				return $template;
			}
			$data['page_template'] = $template;
		}

		// wp_insert_post() runs the usual KSES filters for users without unfiltered_html.
		$id = wp_insert_post( wp_slash( $data ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$journal_id = Journal::record(
			array(
				'ability'     => 'eobots/create-content',
				'object_type' => 'post',
				'object_id'   => $id,
				/* translators: %s: content title. */
				'summary'     => sprintf( __( 'Draft “%s” created', 'eo-bots' ), get_the_title( $id ) ),
				'undo'        => array( 'action' => 'trash' ),
			)
		);

		return self::result( $id, $journal_id, self::check_blocks( $input['content'] ), true );
	}

	/**
	 * Edits content, keeping the previous version for undo.
	 *
	 * @param array<string, mixed> $input Changes.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function update_content( $input ) {
		$post    = get_post( (int) $input['id'] );
		$data    = array( 'ID' => $post->ID );
		$before  = array();
		$fields  = array(
			'title'   => 'post_title',
			'content' => 'post_content',
			'excerpt' => 'post_excerpt',
		);
		$changed = false;

		foreach ( $fields as $key => $field ) {
			if ( isset( $input[ $key ] ) ) {
				$data[ $field ]   = 'title' === $key ? sanitize_text_field( $input[ $key ] ) : $input[ $key ];
				$before[ $field ] = $post->$field;
				$changed          = true;
			}
		}
		if ( isset( $input['template'] ) ) {
			$template = self::template( $input['template'], $post->post_type );
			if ( is_wp_error( $template ) ) {
				return $template;
			}
			$data['page_template']   = $template;
			$before['page_template'] = get_page_template_slug( $post );
			$changed                 = true;
		}
		if ( ! $changed ) {
			return new \WP_Error( 'eobots_nothing_to_update', 'Give at least a title, content, excerpt or template.' );
		}

		$result = wp_update_post( wp_slash( $data ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$journal_id = Journal::record(
			array(
				'ability'     => 'eobots/update-content',
				'object_type' => 'post',
				'object_id'   => $post->ID,
				/* translators: %s: content title. */
				'summary'     => sprintf( __( '“%s” edited', 'eo-bots' ), get_the_title( $post->ID ) ),
				'undo'        => array(
					'action' => 'restore',
					'fields' => $before,
				),
			)
		);

		return self::result( $post->ID, $journal_id, isset( $input['content'] ) ? self::check_blocks( $input['content'] ) : array(), true );
	}

	/**
	 * Changes the status.
	 *
	 * @param array<string, mixed> $input Input with id and status.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function set_status( $input ) {
		$post     = get_post( (int) $input['id'] );
		$previous = $post->post_status;

		$result = wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => $input['status'],
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$summary = 'publish' === $input['status']
			/* translators: %s: content title. */
			? sprintf( __( '“%s” published', 'eo-bots' ), get_the_title( $post->ID ) )
			/* translators: %s: content title. */
			: sprintf( __( '“%s” unpublished', 'eo-bots' ), get_the_title( $post->ID ) );

		$journal_id = Journal::record(
			array(
				'ability'     => 'eobots/set-content-status',
				'object_type' => 'post',
				'object_id'   => $post->ID,
				'summary'     => $summary,
				'undo'        => array(
					'action' => 'status',
					'status' => $previous,
				),
			)
		);

		return self::result( $post->ID, $journal_id );
	}

	/**
	 * Moves content to the trash.
	 *
	 * @param array<string, mixed> $input Input with an id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function trash( $input ) {
		$post     = get_post( (int) $input['id'] );
		$previous = $post->post_status;
		$title    = get_the_title( $post );

		if ( ! wp_trash_post( $post->ID ) ) {
			return new \WP_Error( 'eobots_trash_failed', 'The content could not be moved to the trash.' );
		}

		$journal_id = Journal::record(
			array(
				'ability'     => 'eobots/trash-content',
				'object_type' => 'post',
				'object_id'   => $post->ID,
				/* translators: %s: content title. */
				'summary'     => sprintf( __( '“%s” moved to the trash', 'eo-bots' ), $title ),
				'undo'        => array(
					'action' => 'untrash',
					'status' => $previous,
				),
			)
		);

		return array(
			'id'         => $post->ID,
			'status'     => 'trash',
			'journal_id' => $journal_id,
		);
	}

	/* ---------- Helpers ---------- */

	/**
	 * Common result of write abilities.
	 *
	 * @param int      $id         Post ID.
	 * @param int      $journal_id Journal entry ID.
	 * @param string[] $warnings   Block warnings.
	 * @param bool     $review     Add the design review of the saved content.
	 * @return array<string, mixed>
	 */
	private static function result( $id, $journal_id, $warnings = array(), $review = false ) {
		$post   = get_post( $id );
		$result = array(
			'id'           => $id,
			'title'        => get_the_title( $post ),
			'status'       => $post->post_status,
			'template'     => get_page_template_slug( $post ) ? get_page_template_slug( $post ) : 'default',
			'edit_link'    => get_edit_post_link( $id, 'raw' ),
			'preview_link' => 'publish' === $post->post_status ? get_permalink( $post ) : get_preview_post_link( $post ),
			'journal_id'   => $journal_id,
		);
		if ( $warnings ) {
			$result['warnings'] = $warnings;
		}
		if ( $review ) {
			$issues                  = \EoBots\Includes\Design_Review::review( $post->post_content, $post->ID );
			$result['design_review'] = $issues ? $issues : 'OK, nothing to fix.';
		}
		return $result;
	}

	/**
	 * Validates a page template slug for a post type.
	 *
	 * @param string $slug      Template slug; "default" or empty for the default template.
	 * @param string $post_type Post type.
	 * @return string|\WP_Error Slug to save ('' for default).
	 */
	private static function template( $slug, $post_type ) {
		$slug = (string) $slug;
		if ( '' === $slug || 'default' === $slug ) {
			return '';
		}
		$templates = wp_get_theme()->get_page_templates( null, $post_type );
		if ( ! isset( $templates[ $slug ] ) ) {
			return new \WP_Error( 'eobots_unknown_template', 'Unknown template. Available: default, ' . implode( ', ', array_keys( $templates ) ) . '.' );
		}
		return $slug;
	}

	/**
	 * Checks block markup and reports problems the model can fix.
	 *
	 * @param string $content Block markup.
	 * @return string[]
	 */
	public static function check_blocks( $content ) {
		$warnings = array();
		if ( '' !== trim( $content ) && false === strpos( $content, '<!-- wp:' ) ) {
			$warnings[] = 'The content has no block markup and will show as a single Classic block. Rewrite it as serialized blocks.';
		}

		$registry = \WP_Block_Type_Registry::get_instance();
		$unknown  = array();
		$walk     = static function ( $blocks ) use ( &$walk, $registry, &$unknown ) {
			foreach ( $blocks as $block ) {
				if ( $block['blockName'] && ! $registry->is_registered( $block['blockName'] ) ) {
					$unknown[] = $block['blockName'];
				}
				$walk( $block['innerBlocks'] );
			}
		};
		$walk( parse_blocks( $content ) );

		if ( $unknown ) {
			$warnings[] = 'These blocks are not installed and will show as errors: ' . implode( ', ', array_unique( $unknown ) ) . '. Replace them with available blocks.';
		}
		return $warnings;
	}

	/**
	 * Title of the targeted post, for confirmation cards.
	 *
	 * @param array<string, mixed> $input Input with an id.
	 * @return string
	 */
	private static function label( $input ) {
		$id    = (int) ( $input['id'] ?? 0 );
		$title = $id ? get_the_title( $id ) : '';
		return '' !== $title ? $title : '#' . $id;
	}
}
