<?php
/**
 * Memory abilities: the bot saves or forgets notes, always with the user's confirmation.
 *
 * @package EoBots
 */

namespace EoBots\Includes\Abilities;

use EoBots\Includes\Journal;
use EoBots\Includes\Notes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The notes themselves are in the system instruction; these abilities only change them.
 */
class Memory {

	/**
	 * Registers the abilities.
	 */
	public static function register() {
		$available = array( Notes::class, 'can_manage' );

		Registry::add(
			'remember',
			array(
				'label'               => __( 'Remember a note', 'eo-bots' ),
				'description'         => 'Saves a note in your memory, shared by every EO-BOT of the site and followed in every conversation. Use it when the user corrects you or asks you to remember something: one short, general rule written as an instruction to yourself, in the user language (e.g. "To show a shortcode in a template, use the Shortcode block, not a paragraph."). The user confirms. Never save secrets, passwords or personal data.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'text' => array(
							'type'      => 'string',
							'maxLength' => Notes::MAX_LENGTH,
						),
					),
					'required'             => array( 'text' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'remember' ),
				'permission_callback' => $available,
				'meta'                => array(
					'annotations' => Registry::annotations( false ),
					'eobots'      => array(
						'confirm'   => 'always',
						'available' => $available,
						'progress'  => __( 'Writing it down…', 'eo-bots' ),
						'describe'  => static function ( $input ) {
							return array(
								'title'   => __( 'Remember this for the whole site?', 'eo-bots' ),
								'summary' => '“' . (string) ( $input['text'] ?? '' ) . '”',
							);
						},
					),
				),
			)
		);

		Registry::add(
			'forget',
			array(
				'label'               => __( 'Forget a note', 'eo-bots' ),
				'description'         => 'Deletes a note of your memory by its #ID, when the user asks or when it is wrong or outdated. To change a note, forget it and remember the new version. The user confirms.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( __CLASS__, 'forget' ),
				'permission_callback' => $available,
				'meta'                => array(
					'annotations' => Registry::annotations( false, true ),
					'eobots'      => array(
						'confirm'   => 'always',
						'available' => $available,
						'progress'  => __( 'Erasing the note…', 'eo-bots' ),
						'describe'  => static function ( $input ) {
							$note = current( wp_list_filter( Notes::all(), array( 'id' => (int) ( $input['id'] ?? 0 ) ) ) );
							return array(
								'title'   => __( 'Forget this note?', 'eo-bots' ),
								'summary' => $note ? '“' . $note['text'] . '”' : '#' . (int) ( $input['id'] ?? 0 ),
								'danger'  => true,
							);
						},
					),
				),
			)
		);
	}

	/**
	 * Saves a note.
	 *
	 * @param array<string, mixed> $input Input with text.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function remember( $input ) {
		$note = Notes::add( $input['text'] );
		if ( is_wp_error( $note ) ) {
			return $note;
		}

		$journal_id = Journal::record(
			array(
				'ability'     => 'eobots/remember',
				'object_type' => 'note',
				'object_id'   => $note['id'],
				/* translators: %s: note text. */
				'summary'     => sprintf( __( 'Noted: “%s”', 'eo-bots' ), $note['text'] ),
				'undo'        => array( 'action' => 'note_remove' ),
			)
		);

		return array(
			'id'         => $note['id'],
			'saved'      => true,
			'journal_id' => $journal_id,
		);
	}

	/**
	 * Deletes a note.
	 *
	 * @param array<string, mixed> $input Input with id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function forget( $input ) {
		$note = Notes::remove( (int) $input['id'] );
		if ( is_wp_error( $note ) ) {
			return $note;
		}

		$journal_id = Journal::record(
			array(
				'ability'     => 'eobots/forget',
				'object_type' => 'note',
				'object_id'   => $note['id'],
				/* translators: %s: note text. */
				'summary'     => sprintf( __( 'Forgotten: “%s”', 'eo-bots' ), $note['text'] ),
				'undo'        => array(
					'action' => 'note_restore',
					'note'   => $note,
				),
			)
		);

		return array(
			'id'         => $note['id'],
			'forgotten'  => true,
			'journal_id' => $journal_id,
		);
	}
}
