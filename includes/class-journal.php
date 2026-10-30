<?php
/**
 * Journal of what the bot changed, with undo.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom table.

/**
 * Every write action of the bot is recorded here so the user can undo it from the chat.
 */
class Journal {

	/**
	 * Conversation in which the running action happens. Set by the agent before executing an ability.
	 *
	 * @var int
	 */
	public static $conversation_id = 0;

	/**
	 * Records an action.
	 *
	 * @param array<string, mixed> $entry {
	 *     Journal entry.
	 *     @type string $ability     Ability name.
	 *     @type string $object_type Changed object type, e.g. post.
	 *     @type int    $object_id   Changed object ID.
	 *     @type string $summary     Human sentence shown on the action card.
	 *     @type array  $undo        How to undo: action plus data.
	 * }
	 * @return int Journal entry ID.
	 */
	public static function record( $entry ) {
		global $wpdb;

		$wpdb->insert(
			Install::tables()['journal'],
			array(
				'user_id'         => get_current_user_id(),
				'conversation_id' => self::$conversation_id,
				'ability'         => $entry['ability'],
				'object_type'     => $entry['object_type'] ?? '',
				'object_id'       => $entry['object_id'] ?? 0,
				'summary'         => $entry['summary'],
				'undo_data'       => isset( $entry['undo'] ) ? wp_json_encode( $entry['undo'] ) : null,
				'created_at'      => current_time( 'mysql', true ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Returns an entry.
	 *
	 * @param int $id Entry ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = Install::tables()['journal'];
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			return null;
		}
		$row['undo'] = $row['undo_data'] ? json_decode( $row['undo_data'], true ) : null;
		return $row;
	}

	/**
	 * Builds the action card shown in the chat for an entry.
	 *
	 * @param int $id Entry ID.
	 * @return array<string, mixed>|null
	 */
	public static function card( $id ) {
		$entry = self::get( $id );
		if ( ! $entry ) {
			return null;
		}

		$card = array(
			'journalId' => (int) $entry['id'],
			'summary'   => $entry['summary'],
			'state'     => $entry['status'],
			'undoable'  => ! empty( $entry['undo'] ) && 'done' === $entry['status'],
			'links'     => array(),
		);

		if ( 'post' === $entry['object_type'] ) {
			$post = get_post( (int) $entry['object_id'] );
			if ( $post && 'trash' !== $post->post_status ) {
				$card['title'] = get_the_title( $post );
				$view          = 'publish' === $post->post_status ? get_permalink( $post ) : get_preview_post_link( $post );
				if ( $view ) {
					$card['links']['view'] = $view;
				}
				if ( current_user_can( 'edit_post', $post->ID ) ) {
					$card['links']['edit'] = get_edit_post_link( $post->ID, 'raw' );
				}
			}
		}

		if ( in_array( $entry['object_type'], array( 'template', 'navigation' ), true ) && current_user_can( 'edit_theme_options' ) ) {
			$card['links']['view'] = home_url( '/' );
			$card['links']['edit'] = 'template' === $entry['object_type']
				? Abilities\Site_Editor::editor_url( $entry['undo']['type'], $entry['undo']['template'] )
				: Abilities\Site_Editor::editor_url( 'wp_navigation', (string) $entry['object_id'] );
		}

		if ( 'snippet' === $entry['object_type'] && current_user_can( 'manage_options' ) ) {
			$card['links']['edit'] = Admin::snippets_url( (int) $entry['object_id'] );
		}

		return $card;
	}

	/**
	 * Current state of several entries, used to refresh cards when a conversation is reopened.
	 *
	 * @param int[] $ids Entry IDs.
	 * @return array<int, string> Map of ID to status.
	 */
	public static function states( $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( ! $ids ) {
			return array();
		}

		$table = Install::tables()['journal'];
		$in    = implode( ',', $ids );
		$rows  = $wpdb->get_results( "SELECT id, status FROM {$table} WHERE id IN ({$in})", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return wp_list_pluck( $rows, 'status', 'id' );
	}

	/**
	 * Undoes an entry. Only its author can undo it, and their capabilities are checked again.
	 *
	 * @param int $id      Entry ID.
	 * @param int $user_id Current user.
	 * @return array<string, mixed>|\WP_Error The refreshed card.
	 */
	public static function undo( $id, $user_id ) {
		global $wpdb;

		$entry = self::get( $id );
		if ( ! $entry || (int) $entry['user_id'] !== $user_id ) {
			return new \WP_Error( 'eobots_not_found', __( 'This action no longer exists.', 'eo-bots' ), array( 'status' => 404 ) );
		}
		if ( 'done' !== $entry['status'] || empty( $entry['undo'] ) ) {
			return new \WP_Error( 'eobots_cannot_undo', __( 'This action cannot be undone.', 'eo-bots' ), array( 'status' => 400 ) );
		}

		$undo    = $entry['undo'];
		$post_id = (int) $entry['object_id'];
		$result  = new \WP_Error( 'eobots_cannot_undo', __( 'This action cannot be undone.', 'eo-bots' ) );

		switch ( $undo['action'] ) {
			case 'trash':
				$result = current_user_can( 'delete_post', $post_id ) ? wp_trash_post( $post_id ) : false;
				break;

			case 'restore':
				if ( current_user_can( 'edit_post', $post_id ) ) {
					$result = wp_update_post( wp_slash( array_merge( array( 'ID' => $post_id ), $undo['fields'] ) ), true );
				}
				break;

			case 'status':
				if ( current_user_can( 'edit_post', $post_id ) ) {
					$result = wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => $undo['status'],
						),
						true
					);
				}
				break;

			case 'meta':
				if ( current_user_can( 'edit_post', $post_id ) ) {
					if ( '' === (string) $undo['value'] ) {
						delete_post_meta( $post_id, $undo['key'] );
					} else {
						update_post_meta( $post_id, $undo['key'], $undo['value'] );
					}
					$result = true;
				}
				break;

			case 'note_remove':
				$result = Notes::can_manage() ? Notes::remove( $post_id ) : false;
				break;

			case 'note_restore':
				$result = Notes::can_manage() ? Notes::restore( $undo['note'] ) : false;
				break;

			case 'template':
				if ( current_user_can( 'edit_theme_options' ) ) {
					// No previous customized markup: the theme original was in use, so remove the customization.
					$result = null === $undo['content']
						? Abilities\Site_Editor::revert( $undo['type'], $undo['template'] )
						: Abilities\Site_Editor::write( $undo['type'], $undo['template'], $undo['content'] );
				}
				break;

			case 'untrash':
				if ( current_user_can( 'delete_post', $post_id ) ) {
					$result = wp_untrash_post( $post_id );
					if ( $result && ! empty( $undo['status'] ) ) {
						wp_update_post(
							array(
								'ID'          => $post_id,
								'post_status' => $undo['status'],
							)
						);
					}
				}
				break;
		}

		if ( ! $result || is_wp_error( $result ) ) {
			return is_wp_error( $result ) ? $result : new \WP_Error( 'eobots_cannot_undo', __( 'This action cannot be undone.', 'eo-bots' ), array( 'status' => 403 ) );
		}

		$wpdb->update( Install::tables()['journal'], array( 'status' => 'undone' ), array( 'id' => $id ) );

		return self::card( $id );
	}
}
