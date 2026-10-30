<?php
/**
 * Code workshop abilities: snippets and read-only access to theme and plugin code.
 *
 * @package EoBots
 */

namespace EoBots\Includes\Abilities;

use EoBots\Includes\Journal;
use EoBots\Includes\Snippets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Snippets are created switched off. Switching one on, editing an active one and deleting
 * always go through the user's confirmation, with the code shown on the card.
 */
class Code {

	/**
	 * File types the bot may read.
	 */
	const READABLE = array( 'php', 'css', 'scss', 'js', 'json', 'html', 'txt', 'md' );

	/**
	 * Registers the abilities.
	 */
	public static function register() {
		$id_schema = array(
			'type'        => 'integer',
			'minimum'     => 1,
			'description' => 'Snippet ID.',
		);
		$fields    = array(
			'title'       => array(
				'type'        => 'string',
				'description' => 'Short name, in the user language.',
			),
			'description' => array(
				'type'        => 'string',
				'description' => 'What it does and why, in one or two plain sentences in the user language.',
			),
			'scope'       => array(
				'type'        => 'string',
				'enum'        => Snippets::SCOPES,
				'description' => 'Where it runs: the public site (front), the admin, or everywhere.',
			),
			'code'        => array(
				'type'        => 'string',
				'description' => 'CSS, or PHP without the opening <?php tag.',
			),
		);
		$available = array( Snippets::class, 'user_can' );

		Registry::add(
			'list-snippets',
			array(
				'label'               => __( 'List the snippets', 'eo-bots' ),
				'description'         => 'Lists the code snippets of the site (CSS and PHP) with their status and last error. Check it before writing a new one: improve an existing snippet rather than adding a duplicate.',
				'input_schema'        => Site::object_schema( array() ),
				'execute_callback'    => static function () {
					return array(
						'php_allowed' => Snippets::user_can( 'php' ),
						'snippets'    => Snippets::all(),
					);
				},
				'permission_callback' => $available,
				'meta'                => array(
					'annotations' => Registry::annotations( true ),
					'eobots'      => array(
						'confirm'   => 'never',
						'available' => $available,
						'progress'  => __( 'Looking at the snippets…', 'eo-bots' ),
					),
				),
			)
		);

		Registry::add(
			'get-snippet',
			array(
				'label'               => __( 'Read a snippet', 'eo-bots' ),
				'description'         => 'Returns one snippet with its code.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => $id_schema ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input ) {
					return Snippets::get( (int) $input['id'] ) ?? new \WP_Error( 'eobots_snippet_not_found', 'No snippet with this ID.' );
				},
				'permission_callback' => $available,
				'meta'                => array(
					'annotations' => Registry::annotations( true ),
					'eobots'      => array(
						'confirm'   => 'never',
						'available' => $available,
						'progress'  => __( 'Reading the snippet…', 'eo-bots' ),
					),
				),
			)
		);

		Registry::add(
			'create-snippet',
			array(
				'label'               => __( 'Write a snippet', 'eo-bots' ),
				'description'         => 'Saves a new CSS or PHP snippet, SWITCHED OFF. PHP is checked for syntax errors. Then switch it on with set-snippet-status: the user confirms and the site is tested with it first. Read the guide "snippets" before your first snippet.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						array(
							'type' => array(
								'type' => 'string',
								'enum' => Snippets::TYPES,
							),
						),
						$fields
					),
					'required'             => array( 'type', 'title', 'description', 'code' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'create' ),
				'permission_callback' => static function ( $input ) {
					return Snippets::user_can( (string) ( $input['type'] ?? '' ) );
				},
				'meta'                => array(
					'annotations' => Registry::annotations( false ),
					'eobots'      => array(
						'confirm'   => 'never',
						'available' => $available,
						'progress'  => __( 'Writing code…', 'eo-bots' ),
					),
				),
			)
		);

		Registry::add(
			'update-snippet',
			array(
				'label'               => __( 'Edit a snippet', 'eo-bots' ),
				'description'         => 'Changes the title, description, scope or code of a snippet (send the full new code). If the snippet is switched on, the user confirms first and PHP is tested on the site before the new code is saved.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array_merge( array( 'id' => $id_schema ), $fields ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'update' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'meta'                => array(
					'annotations' => Registry::annotations( false, true ),
					'eobots'      => array(
						// Active snippets are published posts: "live" asks the user only for those.
						'confirm'   => 'live',
						'available' => $available,
						'progress'  => __( 'Editing the code…', 'eo-bots' ),
						'describe'  => static function ( $input ) {
							$snippet = Snippets::get( (int) $input['id'] );
							$code    = $input['code'] ?? $snippet['code'];
							return self::card(
								/* translators: %s: snippet title. */
								sprintf( __( 'Edit the active snippet “%s”', 'eo-bots' ), $snippet['title'] ),
								$input['description'] ?? $snippet['description'],
								$snippet['type'],
								$code
							);
						},
					),
				),
			)
		);

		Registry::add(
			'set-snippet-status',
			array(
				'label'               => __( 'Switch a snippet on or off', 'eo-bots' ),
				'description'         => 'Switches a snippet on (it runs on the site) or off. The user always confirms. Switching PHP on first loads the site with it: if that fails, the error is returned and the snippet stays off; fix it with update-snippet and try again.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'     => $id_schema,
						'active' => array( 'type' => 'boolean' ),
					),
					'required'             => array( 'id', 'active' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'set_status' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'meta'                => array(
					'annotations' => Registry::annotations( false ),
					'eobots'      => array(
						'confirm'   => 'always',
						'available' => $available,
						'progress'  => __( 'Testing the snippet on your site…', 'eo-bots' ),
						'describe'  => static function ( $input ) {
							$snippet = Snippets::get( (int) $input['id'] );
							if ( empty( $input['active'] ) ) {
								return array(
									/* translators: %s: snippet title. */
									'title'   => sprintf( __( 'Switch off the snippet “%s”', 'eo-bots' ), $snippet['title'] ),
									'summary' => __( 'What it does will stop right away.', 'eo-bots' ),
								);
							}
							return self::card(
								/* translators: %s: snippet title. */
								sprintf( __( 'Switch on the snippet “%s”', 'eo-bots' ), $snippet['title'] ),
								$snippet['description'],
								$snippet['type'],
								$snippet['code']
							);
						},
					),
				),
			)
		);

		Registry::add(
			'delete-snippet',
			array(
				'label'               => __( 'Delete a snippet', 'eo-bots' ),
				'description'         => 'Moves a snippet to the trash (it stops running). The user always confirms.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'id' => $id_schema ),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'delete' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'meta'                => array(
					'annotations' => Registry::annotations( false, true ),
					'eobots'      => array(
						'confirm'   => 'always',
						'available' => $available,
						'progress'  => __( 'Tidying up…', 'eo-bots' ),
						'describe'  => static function ( $input ) {
							$snippet = Snippets::get( (int) $input['id'] );
							return array(
								/* translators: %s: snippet title. */
								'title'   => sprintf( __( 'Delete the snippet “%s”', 'eo-bots' ), $snippet['title'] ?? '#' . $input['id'] ),
								'summary' => __( 'It stops running. You can undo it from the chat.', 'eo-bots' ),
								'danger'  => true,
							);
						},
					),
				),
			)
		);

		Registry::add(
			'read-source',
			array(
				'label'               => __( 'Read theme or plugin code', 'eo-bots' ),
				'description'         => 'Read-only access to the code of the installed themes and plugins, to find the right hooks, CSS classes and functions before writing a snippet. Give a folder ("plugins/woocommerce/includes") to list it, or a file to read it (by line range for long files). Paths are relative to wp-content and start with themes/ or plugins/.',
				'input_schema'        => Site::object_schema(
					array(
						'path'  => array(
							'type'        => 'string',
							'description' => 'e.g. themes/twentytwentyfive/style.css; empty to list the active theme and the plugins.',
						),
						'start' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'lines' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 800,
							'default' => 400,
						),
					)
				),
				'execute_callback'    => array( __CLASS__, 'read_source' ),
				'permission_callback' => $available,
				'meta'                => array(
					'annotations' => Registry::annotations( true ),
					'eobots'      => array(
						'confirm'   => 'never',
						'available' => $available,
						'progress'  => __( 'Reading the code…', 'eo-bots' ),
					),
				),
			)
		);

		Registry::add(
			'search-source',
			array(
				'label'               => __( 'Search theme or plugin code', 'eo-bots' ),
				'description'         => 'Searches text in the code of a theme or plugin folder (case insensitive), e.g. "do_action( \'woocommerce_" to find hooks, or a CSS class. Returns file:line matches.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'path'  => array(
							'type'        => 'string',
							'description' => 'Folder relative to wp-content, e.g. plugins/contact-form-7.',
						),
						'query' => array( 'type' => 'string' ),
					),
					'required'             => array( 'path', 'query' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'search_source' ),
				'permission_callback' => $available,
				'meta'                => array(
					'annotations' => Registry::annotations( true ),
					'eobots'      => array(
						'confirm'   => 'never',
						'available' => $available,
						'progress'  => __( 'Searching the code…', 'eo-bots' ),
					),
				),
			)
		);
	}

	/* ---------- Permissions ---------- */

	/**
	 * Whether the user can change the targeted snippet, given its type.
	 *
	 * @param array<string, mixed> $input Input with an id.
	 * @return bool
	 */
	public static function can_edit( $input ) {
		$snippet = Snippets::get( (int) ( $input['id'] ?? 0 ), false );
		return $snippet && Snippets::user_can( $snippet['type'] ) && current_user_can( 'edit_post', $snippet['id'] );
	}

	/* ---------- Callbacks ---------- */

	/**
	 * Creates a snippet, switched off.
	 *
	 * @param array<string, mixed> $input Snippet.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function create( $input ) {
		$id = Snippets::save( $input );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$journal_id = self::record(
			'create-snippet',
			$id,
			/* translators: %s: snippet title. */
			sprintf( __( 'Snippet “%s” written (switched off)', 'eo-bots' ), get_the_title( $id ) ),
			array( 'action' => 'trash' )
		);

		return self::result( $id, $journal_id );
	}

	/**
	 * Edits a snippet, keeping the previous version for undo.
	 *
	 * @param array<string, mixed> $input Changes.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function update( $input ) {
		$post   = get_post( (int) $input['id'] );
		$before = array(
			'post_title'   => $post->post_title,
			'post_excerpt' => $post->post_excerpt,
			'post_content' => $post->post_content,
		);

		$id = Snippets::save( $input );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$journal_id = self::record(
			'update-snippet',
			$id,
			/* translators: %s: snippet title. */
			sprintf( __( 'Snippet “%s” edited', 'eo-bots' ), get_the_title( $id ) ),
			array(
				'action' => 'restore',
				'fields' => $before,
			)
		);

		return self::result( $id, $journal_id );
	}

	/**
	 * Switches a snippet on or off.
	 *
	 * @param array<string, mixed> $input Input with id and active.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function set_status( $input ) {
		$id       = (int) $input['id'];
		$previous = get_post_status( $id );
		$active   = ! empty( $input['active'] );

		$result = Snippets::set_active( $id, $active );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$journal_id = self::record(
			'set-snippet-status',
			$id,
			$active
				/* translators: %s: snippet title. */
				? sprintf( __( 'Snippet “%s” switched on', 'eo-bots' ), get_the_title( $id ) )
				/* translators: %s: snippet title. */
				: sprintf( __( 'Snippet “%s” switched off', 'eo-bots' ), get_the_title( $id ) ),
			array(
				'action' => 'status',
				'status' => $previous,
			)
		);

		return self::result( $id, $journal_id );
	}

	/**
	 * Moves a snippet to the trash.
	 *
	 * @param array<string, mixed> $input Input with an id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function delete( $input ) {
		$id       = (int) $input['id'];
		$previous = get_post_status( $id );
		$title    = get_the_title( $id );

		if ( ! wp_trash_post( $id ) ) {
			return new \WP_Error( 'eobots_trash_failed', 'The snippet could not be deleted.' );
		}
		Snippets::rebuild();

		$journal_id = self::record(
			'delete-snippet',
			$id,
			/* translators: %s: snippet title. */
			sprintf( __( 'Snippet “%s” deleted', 'eo-bots' ), $title ),
			array(
				'action' => 'untrash',
				'status' => $previous,
			)
		);

		return array(
			'id'         => $id,
			'deleted'    => true,
			'journal_id' => $journal_id,
		);
	}

	/**
	 * Lists a folder or reads a file of a theme or plugin.
	 *
	 * @param array<string, mixed> $input Path and line range.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function read_source( $input = array() ) {
		$path = trim( (string) ( $input['path'] ?? '' ), '/' );
		if ( '' === $path ) {
			return array(
				'active_theme' => 'themes/' . get_stylesheet(),
				'parent_theme' => is_child_theme() ? 'themes/' . get_template() : null,
				'plugins'      => array_map( static fn( $file ) => 'plugins/' . dirname( $file ), (array) get_option( 'active_plugins', array() ) ),
			);
		}

		$real = self::resolve( $path );
		if ( is_wp_error( $real ) ) {
			return $real;
		}

		if ( is_dir( $real ) ) {
			$items = array();
			foreach ( (array) scandir( $real ) as $name ) {
				if ( '.' === $name[0] ) {
					continue;
				}
				$items[] = is_dir( $real . '/' . $name ) ? $name . '/' : $name;
				if ( count( $items ) >= 300 ) {
					break;
				}
			}
			return array(
				'path'  => $path,
				'items' => $items,
			);
		}

		if ( ! self::readable( $real ) ) {
			return new \WP_Error( 'eobots_source_type', 'Only code and text files can be read: ' . implode( ', ', self::READABLE ) . '.' );
		}

		$lines = file( $real, FILE_IGNORE_NEW_LINES );
		$start = max( 1, (int) ( $input['start'] ?? 1 ) );
		$count = min( 800, max( 1, (int) ( $input['lines'] ?? 400 ) ) );
		$part  = array_slice( (array) $lines, $start - 1, $count );
		$out   = '';
		foreach ( $part as $i => $line ) {
			$out .= ( $start + $i ) . ': ' . $line . "\n";
		}

		return array(
			'path'        => $path,
			'total_lines' => count( (array) $lines ),
			'from'        => $start,
			'to'          => $start + count( $part ) - 1,
			'content'     => Registry::clip( $out, 40000 ),
		);
	}

	/**
	 * Searches text in a theme or plugin folder.
	 *
	 * @param array<string, mixed> $input Path and query.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function search_source( $input ) {
		$path  = trim( (string) $input['path'], '/' );
		$query = (string) $input['query'];
		$real  = self::resolve( $path );
		if ( is_wp_error( $real ) ) {
			return $real;
		}
		if ( strlen( $query ) < 3 ) {
			return new \WP_Error( 'eobots_source_query', 'Search at least 3 characters.' );
		}

		$matches = array();
		$scanned = 0;
		$files   = is_dir( $real ) ? new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $real, \FilesystemIterator::SKIP_DOTS ) ) : array( new \SplFileInfo( $real ) );
		foreach ( $files as $file ) {
			$name = wp_normalize_path( $file->getPathname() );
			if ( ! self::readable( $name ) || $file->getSize() > 1048576 || preg_match( '#/(node_modules|\.git)/#', $name ) ) {
				continue;
			}
			if ( ++$scanned > 5000 ) {
				break;
			}
			foreach ( (array) file( $name, FILE_IGNORE_NEW_LINES ) as $n => $line ) {
				if ( false !== stripos( $line, $query ) ) {
					$matches[] = substr( $name, strlen( wp_normalize_path( WP_CONTENT_DIR ) ) + 1 ) . ':' . ( $n + 1 ) . ': ' . trim( substr( $line, 0, 200 ) );
					if ( count( $matches ) >= 60 ) {
						break 2;
					}
				}
			}
		}

		return array(
			'query'     => $query,
			'matches'   => $matches,
			'truncated' => count( $matches ) >= 60,
		);
	}

	/* ---------- Helpers ---------- */

	/**
	 * Resolves a path inside wp-content/themes or wp-content/plugins, refusing anything else.
	 *
	 * @param string $path Relative path.
	 * @return string|\WP_Error Real path.
	 */
	private static function resolve( $path ) {
		$real = realpath( WP_CONTENT_DIR . '/' . $path );
		if ( ! $real ) {
			return new \WP_Error( 'eobots_source_missing', 'This path does not exist. Call read-source without path to see the themes and plugins.' );
		}
		$real = wp_normalize_path( $real );
		foreach ( array( get_theme_root(), WP_PLUGIN_DIR ) as $root ) {
			$root = wp_normalize_path( (string) realpath( $root ) );
			if ( $root && ( $real === $root || 0 === strpos( $real, $root . '/' ) ) ) {
				return $real;
			}
		}
		return new \WP_Error( 'eobots_source_forbidden', 'Only the themes and plugins folders can be read.' );
	}

	/**
	 * Whether a file is a code or text file the bot may read.
	 *
	 * @param string $file Path.
	 * @return bool
	 */
	private static function readable( $file ) {
		return in_array( strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ), self::READABLE, true ) && is_readable( $file );
	}

	/**
	 * Confirmation card showing the code, flagged when PHP uses sensitive functions.
	 *
	 * @param string $title       Card title.
	 * @param string $description Snippet description.
	 * @param string $type        css or php.
	 * @param string $code        Code.
	 * @return array<string, mixed>
	 */
	private static function card( $title, $description, $type, $code ) {
		$card      = array(
			'title'   => $title,
			'summary' => (string) $description,
			'code'    => Registry::clip( Snippets::normalize( $code, $type ), 6000 ),
			'lang'    => $type,
		);
		$sensitive = 'php' === $type ? Snippets::sensitive( $code ) : array();
		if ( $sensitive ) {
			/* translators: %s: list of PHP functions. */
			$card['summary'] .= ' ' . sprintf( __( 'Careful: this code uses sensitive functions (%s). Only say yes if you trust what it does.', 'eo-bots' ), implode( ', ', $sensitive ) );
			$card['danger']   = true;
		} elseif ( 'php' === $type ) {
			$card['summary'] .= ' ' . __( 'I test your site with it first: if anything breaks, it stays off.', 'eo-bots' );
		}
		return $card;
	}

	/**
	 * Journals a snippet change.
	 *
	 * @param string               $ability Ability name without prefix.
	 * @param int                  $id      Snippet ID.
	 * @param string               $summary Card text.
	 * @param array<string, mixed> $undo    Undo data.
	 * @return int
	 */
	private static function record( $ability, $id, $summary, $undo ) {
		return Journal::record(
			array(
				'ability'     => 'eobots/' . $ability,
				'object_type' => 'snippet',
				'object_id'   => $id,
				'summary'     => $summary,
				'undo'        => $undo,
			)
		);
	}

	/**
	 * Result of write abilities.
	 *
	 * @param int $id         Snippet ID.
	 * @param int $journal_id Journal entry ID.
	 * @return array<string, mixed>
	 */
	private static function result( $id, $journal_id ) {
		return array_merge( (array) Snippets::get( $id, false ), array( 'journal_id' => $journal_id ) );
	}
}
