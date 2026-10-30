<?php
/**
 * Conversations and their displayed messages.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Custom tables, per-user data that is not worth caching.

/**
 * Reads and writes the conversation tables.
 *
 * A conversation keeps two things apart:
 * - `history`: the raw AI Client messages sent back to the model on every step;
 * - the messages table: what the chat panel shows (text bubbles, action and confirmation cards).
 */
class Store {

	/**
	 * Creates a conversation.
	 *
	 * @param int    $user_id Owner.
	 * @param string $title   Title shown in the history list.
	 * @return array<string, mixed>
	 */
	public static function create_conversation( $user_id, $title ) {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$wpdb->insert(
			Install::tables()['conversations'],
			array(
				'user_id'    => $user_id,
				'title'      => self::shorten( $title, 80 ),
				'history'    => '[]',
				'created_at' => $now,
				'updated_at' => $now,
			)
		);

		return self::get_conversation( (int) $wpdb->insert_id, $user_id );
	}

	/**
	 * Returns a conversation owned by the user, decoded.
	 *
	 * @param int $id      Conversation ID.
	 * @param int $user_id Owner.
	 * @return array<string, mixed>|null
	 */
	public static function get_conversation( $id, $user_id ) {
		global $wpdb;

		$table = Install::tables()['conversations'];
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND user_id = %d", $id, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $row ) {
			return null;
		}

		$row['id']      = (int) $row['id'];
		$row['steps']   = (int) $row['steps'];
		$row['history'] = json_decode( $row['history'], true ) ?: array(); // phpcs:ignore Universal.Operators.DisallowShortTernary
		$row['pending'] = $row['pending'] ? json_decode( $row['pending'], true ) : null;
		$row['notes']   = $row['notes'] ? json_decode( $row['notes'], true ) : array();

		return $row;
	}

	/**
	 * Saves changed fields of a conversation.
	 *
	 * @param int                  $id     Conversation ID.
	 * @param array<string, mixed> $fields Fields to save; arrays are JSON-encoded.
	 */
	public static function update_conversation( $id, $fields ) {
		global $wpdb;

		foreach ( array( 'history', 'pending', 'notes' ) as $key ) {
			if ( array_key_exists( $key, $fields ) ) {
				$fields[ $key ] = null === $fields[ $key ] ? null : wp_json_encode( $fields[ $key ] );
			}
		}
		$fields['updated_at'] = current_time( 'mysql', true );

		$wpdb->update( Install::tables()['conversations'], $fields, array( 'id' => $id ) );
	}

	/**
	 * Adds token usage to a conversation.
	 *
	 * @param int $id         Conversation ID.
	 * @param int $tokens_in  Prompt tokens.
	 * @param int $tokens_out Completion tokens.
	 */
	public static function add_usage( $id, $tokens_in, $tokens_out ) {
		global $wpdb;

		$table = Install::tables()['conversations'];
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET tokens_in = tokens_in + %d, tokens_out = tokens_out + %d WHERE id = %d", $tokens_in, $tokens_out, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Lists a user's latest conversations.
	 *
	 * @param int $user_id Owner.
	 * @param int $limit   Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_conversations( $user_id, $limit = 30 ) {
		global $wpdb;

		$table = Install::tables()['conversations'];
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, title, updated_at FROM {$table} WHERE user_id = %d ORDER BY updated_at DESC LIMIT %d", $user_id, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map(
			static function ( $row ) {
				return array(
					'id'        => (int) $row['id'],
					'title'     => $row['title'],
					'updatedAt' => mysql_to_rfc3339( $row['updated_at'] ),
				);
			},
			$rows
		);
	}

	/**
	 * Deletes a conversation and its messages. Journal entries stay, so undo keeps working.
	 *
	 * @param int $id      Conversation ID.
	 * @param int $user_id Owner.
	 * @return bool
	 */
	public static function delete_conversation( $id, $user_id ) {
		global $wpdb;

		$t       = Install::tables();
		$deleted = $wpdb->delete(
			$t['conversations'],
			array(
				'id'      => $id,
				'user_id' => $user_id,
			)
		);
		if ( $deleted ) {
			$wpdb->delete( $t['messages'], array( 'conversation_id' => $id ) );
		}
		return (bool) $deleted;
	}

	/**
	 * Adds a displayed message.
	 *
	 * @param int                  $conversation_id Conversation ID.
	 * @param string               $role            user, bot, action, confirm or error.
	 * @param array<string, mixed> $content         Payload rendered by the chat panel.
	 * @return int Message ID.
	 */
	public static function add_message( $conversation_id, $role, $content ) {
		global $wpdb;

		$wpdb->insert(
			Install::tables()['messages'],
			array(
				'conversation_id' => $conversation_id,
				'role'            => $role,
				'content'         => wp_json_encode( $content ),
				'created_at'      => current_time( 'mysql', true ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Returns the displayed messages of a conversation, oldest first.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_messages( $conversation_id ) {
		global $wpdb;

		$table = Install::tables()['messages'];
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, role, content FROM {$table} WHERE conversation_id = %d ORDER BY id ASC", $conversation_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map(
			static function ( $row ) {
				return array_merge(
					json_decode( $row['content'], true ) ?: array(), // phpcs:ignore Universal.Operators.DisallowShortTernary
					array(
						'id'   => (int) $row['id'],
						'type' => $row['role'],
					)
				);
			},
			$rows
		);
	}

	/**
	 * Updates the state of a confirmation card (pending, approved, declined).
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $call_id         Function call ID.
	 * @param string $state           New state.
	 */
	public static function set_confirm_state( $conversation_id, $call_id, $state ) {
		global $wpdb;

		$table = Install::tables()['messages'];
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, content FROM {$table} WHERE conversation_id = %d AND role = 'confirm'", $conversation_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $rows as $row ) {
			$content = json_decode( $row['content'], true );
			if ( isset( $content['callId'] ) && $content['callId'] === $call_id ) {
				$content['state'] = $state;
				$wpdb->update( $table, array( 'content' => wp_json_encode( $content ) ), array( 'id' => $row['id'] ) );
			}
		}
	}

	/**
	 * Shortens a string for titles.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max characters.
	 * @return string
	 */
	public static function shorten( $text, $max ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $max ) {
			return rtrim( mb_substr( $text, 0, $max - 1 ) ) . '…';
		}
		return $text;
	}
}
