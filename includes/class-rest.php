<?php
/**
 * REST routes used by the creator screen and the chat panel.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Namespace eobots/v1. Every route requires a logged-in user allowed to use EO-BOTS
 * and only ever touches that user's own EO-BOT, conversations and journal.
 */
class Rest {

	const NS = 'eobots/v1';

	/**
	 * Registers routes.
	 */
	public static function register_routes() {
		$can_use = array( Plugin::class, 'user_can_use' );
		$id_arg  = array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
			),
		);
		$context = array(
			'type'              => 'object',
			'default'           => array(),
			'sanitize_callback' => array( __CLASS__, 'sanitize_context' ),
		);

		register_rest_route(
			self::NS,
			'/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'me' ),
				'permission_callback' => $can_use,
			)
		);

		register_rest_route(
			self::NS,
			'/avatar',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'save_avatar' ),
				'permission_callback' => $can_use,
				'args'                => array(
					'animal'    => array(
						'type'     => 'string',
						'enum'     => array_keys( Avatar::animals() ),
						'required' => true,
					),
					'color'     => array(
						'type'     => 'string',
						'required' => true,
					),
					'accessory' => array(
						'type'     => 'string',
						'enum'     => array_keys( Avatar::accessories() ),
						'required' => true,
					),
					'name'      => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/models',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_models' ),
					'permission_callback' => array( Plugin::class, 'can_connect' ),
					'args'                => array(
						'refresh' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_model' ),
					'permission_callback' => array( Plugin::class, 'can_connect' ),
					'args'                => array(
						'model' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/conversations',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_conversations' ),
				'permission_callback' => $can_use,
			)
		);

		register_rest_route(
			self::NS,
			'/conversations/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_conversation' ),
					'permission_callback' => $can_use,
					'args'                => $id_arg,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'delete_conversation' ),
					'permission_callback' => $can_use,
					'args'                => $id_arg,
				),
			)
		);

		$note_text = array(
			'text' => array(
				'type'     => 'string',
				'required' => true,
			),
		);

		register_rest_route(
			self::NS,
			'/notes',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_notes' ),
					'permission_callback' => $can_use,
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'add_note' ),
					'permission_callback' => array( Notes::class, 'can_manage' ),
					'args'                => $note_text,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/notes/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'update_note' ),
					'permission_callback' => array( Notes::class, 'can_manage' ),
					'args'                => array_merge( $id_arg, $note_text ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'delete_note' ),
					'permission_callback' => array( Notes::class, 'can_manage' ),
					'args'                => $id_arg,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'chat' ),
				'permission_callback' => $can_use,
				'args'                => array(
					'conversation_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'message'         => array(
						'type'      => 'string',
						'required'  => true,
						'minLength' => 1,
						'maxLength' => 8000,
					),
					'context'         => $context,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/chat/continue',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'chat_continue' ),
				'permission_callback' => $can_use,
				'args'                => array(
					'conversation_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'context'         => $context,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/chat/confirm',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'chat_confirm' ),
				'permission_callback' => $can_use,
				'args'                => array(
					'conversation_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'call_id'         => array(
						'type'     => 'string',
						'required' => true,
					),
					'approve'         => array(
						'type'     => 'boolean',
						'required' => true,
					),
					'context'         => $context,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/journal/(?P<id>\d+)/undo',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'undo' ),
				'permission_callback' => $can_use,
				'args'                => $id_arg,
			)
		);
	}

	/**
	 * Current user's EO-BOT and whether the AI is ready.
	 *
	 * @return \WP_REST_Response
	 */
	public static function me() {
		return rest_ensure_response(
			array(
				'avatar'     => Avatar::get( get_current_user_id() ),
				'aiReady'    => Plugin::ai_ready(),
				'canConnect' => Plugin::can_connect(),
			)
		);
	}

	/**
	 * Saves the current user's EO-BOT.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function save_avatar( $request ) {
		$avatar = Avatar::save( get_current_user_id(), $request->get_params() );

		return rest_ensure_response(
			array(
				'avatar'  => $avatar,
				'aiReady' => Plugin::ai_ready(),
			)
		);
	}

	/**
	 * Models of the connected providers and the current choice.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function get_models( $request ) {
		return rest_ensure_response(
			array(
				'aiReady' => Plugin::ai_ready(),
				'current' => Models::current(),
				'models'  => Models::available( (bool) $request['refresh'] ),
			)
		);
	}

	/**
	 * Saves the site's model.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function save_model( $request ) {
		if ( ! Models::save( (string) $request['model'] ) ) {
			return new \WP_Error( 'eobots_unknown_model', __( 'This model is not available.', 'eo-bots' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( array( 'current' => Models::current() ) );
	}

	/**
	 * Lists conversations.
	 *
	 * @return \WP_REST_Response
	 */
	public static function list_conversations() {
		return rest_ensure_response( Store::list_conversations( get_current_user_id() ) );
	}

	/**
	 * One conversation with its displayed messages; action cards get their current undo state.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_conversation( $request ) {
		$conv = self::conversation( (int) $request['id'] );
		if ( is_wp_error( $conv ) ) {
			return $conv;
		}

		$messages = Store::get_messages( $conv['id'] );
		$states   = Journal::states( wp_list_pluck( wp_list_filter( $messages, array( 'type' => 'action' ) ), 'journalId' ) );

		foreach ( $messages as &$message ) {
			if ( 'action' === $message['type'] && isset( $states[ $message['journalId'] ] ) ) {
				$message['state']    = $states[ $message['journalId'] ];
				$message['undoable'] = $message['undoable'] && 'done' === $message['state'];
			}
		}
		unset( $message );

		return rest_ensure_response(
			array(
				'conversation' => array(
					'id'    => $conv['id'],
					'title' => $conv['title'],
				),
				'messages'     => $messages,
				'pending'      => (bool) $conv['pending'],
			)
		);
	}

	/**
	 * Deletes a conversation.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function delete_conversation( $request ) {
		if ( ! Store::delete_conversation( (int) $request['id'], get_current_user_id() ) ) {
			return new \WP_Error( 'eobots_not_found', __( 'Conversation not found.', 'eo-bots' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * The EO-BOTS memory, for the chat panel.
	 *
	 * @return \WP_REST_Response
	 */
	public static function list_notes() {
		return rest_ensure_response(
			array(
				'notes'     => Notes::for_display(),
				'canEdit'   => Notes::can_manage(),
				'max'       => Notes::MAX,
				'maxLength' => Notes::MAX_LENGTH,
			)
		);
	}

	/**
	 * Adds a note by hand.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function add_note( $request ) {
		$note = Notes::add( $request['text'] );
		return is_wp_error( $note ) ? $note : self::list_notes();
	}

	/**
	 * Changes a note.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function update_note( $request ) {
		$note = Notes::update( (int) $request['id'], $request['text'] );
		return is_wp_error( $note ) ? $note : self::list_notes();
	}

	/**
	 * Deletes a note.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function delete_note( $request ) {
		$note = Notes::remove( (int) $request['id'] );
		return is_wp_error( $note ) ? $note : self::list_notes();
	}

	/**
	 * Sends a message; starts a conversation when needed.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function chat( $request ) {
		$message = trim( (string) $request['message'] );
		if ( '' === $message ) {
			return new \WP_Error( 'eobots_empty', __( 'Write a message first.', 'eo-bots' ), array( 'status' => 400 ) );
		}
		if ( ! Plugin::ai_ready() ) {
			return new \WP_Error( 'eobots_no_ai', __( 'No AI provider is connected yet.', 'eo-bots' ), array( 'status' => 503 ) );
		}

		if ( $request['conversation_id'] ) {
			$conv = self::conversation( (int) $request['conversation_id'] );
			if ( is_wp_error( $conv ) ) {
				return $conv;
			}
		} else {
			$conv = Store::create_conversation( get_current_user_id(), $message );
		}

		self::extend_time();
		$agent  = new Agent( wp_get_current_user(), $conv, $request['context'] );
		$result = self::locked( $conv['id'], static fn() => $agent->send( $message ) );
		return null === $result ? self::busy_error() : rest_ensure_response( $result );
	}

	/**
	 * Runs the next step.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function chat_continue( $request ) {
		$conv = self::conversation( (int) $request['conversation_id'] );
		if ( is_wp_error( $conv ) ) {
			return $conv;
		}

		self::extend_time();
		$agent  = new Agent( wp_get_current_user(), $conv, $request['context'] );
		$result = self::locked( $conv['id'], array( $agent, 'step' ) );
		if ( null === $result ) {
			// A step started on a page the user left is still running: the browser waits and asks again.
			return rest_ensure_response(
				array(
					'status'       => 'busy',
					'conversation' => array(
						'id'    => $conv['id'],
						'title' => $conv['title'],
					),
					'events'       => array(),
				)
			);
		}
		return rest_ensure_response( $result );
	}

	/**
	 * Approves or declines a pending action.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function chat_confirm( $request ) {
		$conv = self::conversation( (int) $request['conversation_id'] );
		if ( is_wp_error( $conv ) ) {
			return $conv;
		}

		self::extend_time();
		$agent  = new Agent( wp_get_current_user(), $conv, $request['context'] );
		$result = self::locked( $conv['id'], static fn() => $agent->resolve( (string) $request['call_id'], (bool) $request['approve'] ) );
		return null === $result ? self::busy_error() : rest_ensure_response( $result );
	}

	/**
	 * Undoes a journaled action and tells the bot at the next message.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function undo( $request ) {
		$user_id = get_current_user_id();
		$card    = Journal::undo( (int) $request['id'], $user_id );
		if ( is_wp_error( $card ) ) {
			return $card;
		}

		$entry = Journal::get( (int) $request['id'] );
		$conv  = Store::get_conversation( (int) $entry['conversation_id'], $user_id );
		if ( $conv ) {
			$notes   = $conv['notes'];
			$notes[] = sprintf( 'The user undid this action: "%s".', $entry['summary'] );
			Store::update_conversation( $conv['id'], array( 'notes' => $notes ) );
		}

		return rest_ensure_response( $card );
	}

	/**
	 * Keeps only known context fields.
	 *
	 * @param mixed $value Raw context.
	 * @return array<string, mixed>
	 */
	public static function sanitize_context( $value ) {
		$value = is_array( $value ) ? $value : array();

		return array(
			'area'   => isset( $value['area'] ) && 'front' === $value['area'] ? 'front' : 'admin',
			'screen' => isset( $value['screen'] ) ? sanitize_key( $value['screen'] ) : '',
			'postId' => isset( $value['postId'] ) ? absint( $value['postId'] ) : 0,
			'url'    => isset( $value['url'] ) ? esc_url_raw( $value['url'] ) : '',
		);
	}

	/**
	 * Loads a conversation owned by the current user.
	 *
	 * @param int $id Conversation ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function conversation( $id ) {
		$conv = Store::get_conversation( $id, get_current_user_id() );
		return $conv ? $conv : new \WP_Error( 'eobots_not_found', __( 'Conversation not found.', 'eo-bots' ), array( 'status' => 404 ) );
	}

	/**
	 * Model calls can be long: give the request more time when the host allows it.
	 */
	private static function extend_time() {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- AI calls can take minutes.
		}
		// A step goes to its end even if the user leaves the page: its result is saved and picked up later.
		ignore_user_abort( true );
	}

	/**
	 * Runs one agent step at a time per conversation, so a step started on a page the user left
	 * and the same conversation resumed on the next page never run side by side.
	 *
	 * @param int      $conversation_id Conversation ID.
	 * @param callable $callback        Agent call.
	 * @return mixed|null Callback result, or null when another step is running.
	 */
	private static function locked( $conversation_id, $callback ) {
		$key = 'eobots_lock_' . $conversation_id;
		// add_option() is atomic: it fails when the row already exists.
		if ( ! add_option( $key, time(), '', false ) ) {
			wp_cache_delete( $key, 'options' );
			if ( time() - (int) get_option( $key ) < 5 * MINUTE_IN_SECONDS ) {
				return null;
			}
			update_option( $key, time(), false ); // Stale lock left by a crashed request.
		}

		try {
			return call_user_func( $callback );
		} finally {
			delete_option( $key );
		}
	}

	/**
	 * Error returned when a message arrives while the bot is still working on the conversation.
	 *
	 * @return \WP_Error
	 */
	private static function busy_error() {
		return new \WP_Error( 'eobots_busy', __( 'I am still working on your previous message. Give me a moment.', 'eo-bots' ), array( 'status' => 409 ) );
	}
}
