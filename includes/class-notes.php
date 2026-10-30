<?php
/**
 * EO-BOTS memory: short notes from the site team, followed in every conversation.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notes are shared by every EO-BOT of the site and added to the system instruction.
 * Only administrators write them; the bot saves one only after the user confirms it,
 * so text found in pages or forms can never settle in its memory on its own.
 */
class Notes {

	const OPTION     = 'eobots_notes';
	const MAX        = 40;
	const MAX_LENGTH = 300;

	/**
	 * Whether the current user can add, change and delete notes.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		/**
		 * Filters the capability needed to edit the EO-BOTS memory.
		 *
		 * @param string $capability Default manage_options.
		 */
		return current_user_can( (string) apply_filters( 'eobots_notes_capability', 'manage_options' ) );
	}

	/**
	 * All notes, oldest first.
	 *
	 * @return array<int, array{id: int, text: string, author: int, created: string}>
	 */
	public static function all() {
		$notes = get_option( self::OPTION, array() );
		return is_array( $notes ) ? array_values( $notes ) : array();
	}

	/**
	 * Notes for the chat panel, with author names.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_display() {
		return array_map(
			static function ( $note ) {
				$author = get_userdata( $note['author'] );
				return array(
					'id'      => $note['id'],
					'text'    => $note['text'],
					'author'  => $author ? $author->display_name : '',
					'created' => gmdate( 'c', strtotime( $note['created'] . ' UTC' ) ),
				);
			},
			self::all()
		);
	}

	/**
	 * Adds a note.
	 *
	 * @param string $text Note.
	 * @return array<string, mixed>|\WP_Error The note.
	 */
	public static function add( $text ) {
		$text = self::clean( $text );
		if ( is_wp_error( $text ) ) {
			return $text;
		}

		$notes = self::all();
		if ( count( $notes ) >= self::MAX ) {
			/* translators: %d: maximum number of notes. */
			return new \WP_Error( 'eobots_notes_full', sprintf( __( 'The memory is full (%d notes). Delete or merge some notes first.', 'eo-bots' ), self::MAX ), array( 'status' => 400 ) );
		}

		$note    = array(
			'id'      => $notes ? max( array_column( $notes, 'id' ) ) + 1 : 1,
			'text'    => $text,
			'author'  => get_current_user_id(),
			'created' => current_time( 'mysql', true ),
		);
		$notes[] = $note;
		self::save( $notes );
		return $note;
	}

	/**
	 * Changes the text of a note.
	 *
	 * @param int    $id   Note ID.
	 * @param string $text New text.
	 * @return array<string, mixed>|\WP_Error The note.
	 */
	public static function update( $id, $text ) {
		$text = self::clean( $text );
		if ( is_wp_error( $text ) ) {
			return $text;
		}

		$notes = self::all();
		foreach ( $notes as $i => $note ) {
			if ( (int) $note['id'] === (int) $id ) {
				$notes[ $i ]['text'] = $text;
				self::save( $notes );
				return $notes[ $i ];
			}
		}
		return self::not_found();
	}

	/**
	 * Deletes a note.
	 *
	 * @param int $id Note ID.
	 * @return array<string, mixed>|\WP_Error The deleted note, to restore it.
	 */
	public static function remove( $id ) {
		$notes = self::all();
		foreach ( $notes as $i => $note ) {
			if ( (int) $note['id'] === (int) $id ) {
				unset( $notes[ $i ] );
				self::save( $notes );
				return $note;
			}
		}
		return self::not_found();
	}

	/**
	 * Puts a deleted note back at its place.
	 *
	 * @param array<string, mixed> $note Note returned by remove().
	 * @return true
	 */
	public static function restore( $note ) {
		$notes   = array_filter( self::all(), static fn( $n ) => (int) $n['id'] !== (int) $note['id'] );
		$notes[] = $note;
		usort( $notes, static fn( $a, $b ) => $a['id'] <=> $b['id'] );
		self::save( $notes );
		return true;
	}

	/**
	 * Notes as given to the model.
	 *
	 * @return string Empty when there is none.
	 */
	public static function prompt() {
		$lines = array();
		foreach ( self::all() as $note ) {
			$lines[] = sprintf( '- (#%d) %s', $note['id'], $note['text'] );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Validates note text.
	 *
	 * @param string $text Text.
	 * @return string|\WP_Error
	 */
	private static function clean( $text ) {
		$text = trim( preg_replace( '/\s+/', ' ', sanitize_textarea_field( (string) $text ) ) );
		if ( '' === $text ) {
			return new \WP_Error( 'eobots_note_empty', __( 'The note is empty.', 'eo-bots' ), array( 'status' => 400 ) );
		}
		if ( mb_strlen( $text ) > self::MAX_LENGTH ) {
			/* translators: %d: maximum length. */
			return new \WP_Error( 'eobots_note_long', sprintf( __( 'A note is %d characters at most: keep one short rule per note.', 'eo-bots' ), self::MAX_LENGTH ), array( 'status' => 400 ) );
		}
		return $text;
	}

	/**
	 * Saves the notes.
	 *
	 * @param array<int, array<string, mixed>> $notes Notes.
	 */
	private static function save( $notes ) {
		update_option( self::OPTION, array_values( $notes ), true );
	}

	/**
	 * Error for an unknown note.
	 *
	 * @return \WP_Error
	 */
	private static function not_found() {
		return new \WP_Error( 'eobots_note_not_found', __( 'This note no longer exists.', 'eo-bots' ), array( 'status' => 404 ) );
	}
}
