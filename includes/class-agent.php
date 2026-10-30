<?php
/**
 * The agent loop, one model call per request.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the conversation with the model through the core AI Client.
 *
 * The browser drives the loop: each request makes one model call, runs the abilities it asked for
 * and answers `continue`, `confirm` (the user must approve an action) or `done`. This keeps every
 * request short, which matters on shared hosting, and lets the bot show what it is doing.
 */
class Agent {

	const MAX_STEPS     = 16;
	const HISTORY_LIMIT = 40;

	/**
	 * Current user.
	 *
	 * @var \WP_User
	 */
	private $user;

	/**
	 * Conversation row, decoded.
	 *
	 * @var array<string, mixed>
	 */
	private $conv;

	/**
	 * Where the user is, sanitized.
	 *
	 * @var array<string, mixed>
	 */
	private $context;

	/**
	 * Events returned to the chat panel.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $events = array();

	/**
	 * Constructor.
	 *
	 * @param \WP_User             $user         Current user.
	 * @param array<string, mixed> $conversation Conversation row.
	 * @param array<string, mixed> $context      Sanitized context.
	 */
	public function __construct( $user, $conversation, $context ) {
		$this->user    = $user;
		$this->conv    = $conversation;
		$this->context = $context;
	}

	/**
	 * Adds the user's message, then runs the first step.
	 *
	 * @param string $text Message.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function send( $text ) {
		if ( $this->conv['pending'] ) {
			return new \WP_Error( 'eobots_pending', __( 'Answer my question above first.', 'eo-bots' ), array( 'status' => 409 ) );
		}

		$prompt = $text;
		if ( $this->conv['notes'] ) {
			$prompt = '[Since your last answer: ' . implode( ' ', $this->conv['notes'] ) . "]\n\n" . $text;
		}

		$history = $this->conv['history'];
		$last    = $history ? Message::fromArray( end( $history ) ) : null;

		if ( $last && $last->getRole()->isUser() ) {
			// A previous step failed or stopped: keep roles alternating by merging into the waiting user message.
			$history[ count( $history ) - 1 ] = $last->withPart( new MessagePart( $prompt ) )->toArray();
		} else {
			$history[] = ( new UserMessage( array( new MessagePart( $prompt ) ) ) )->toArray();
		}

		Store::add_message( $this->conv['id'], 'user', array( 'text' => $text ) );
		$this->save(
			array(
				'history' => $history,
				'notes'   => null,
				'steps'   => 0,
			)
		);

		return $this->step();
	}

	/**
	 * One model call, then the abilities it asked for.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function step() {
		$history = $this->conv['history'];
		if ( ! $history || $this->conv['pending'] ) {
			return $this->response( $this->conv['pending'] ? 'confirm' : 'done' );
		}

		$messages = self::trim( array_map( array( Message::class, 'fromArray' ), $history ) );
		if ( ! end( $messages )->getRole()->isUser() ) {
			return $this->response( 'done' );
		}

		if ( ! Plugin::ai_ready() ) {
			return new \WP_Error( 'eobots_no_ai', __( 'No AI provider is connected yet.', 'eo-bots' ), array( 'status' => 503 ) );
		}
		if ( ! Policy::consume_rate( $this->user->ID ) ) {
			return new \WP_Error( 'eobots_rate_limited', __( 'I need a little break. Try again in a few minutes.', 'eo-bots' ), array( 'status' => 429 ) );
		}
		if ( $this->conv['steps'] >= self::MAX_STEPS ) {
			return $this->say( __( 'I did a lot of steps in a row, so I am pausing here. Tell me if I should go on.', 'eo-bots' ), 'done' );
		}

		$abilities = Policy::abilities();
		$builder   = wp_ai_client_prompt( $messages )
			->using_system_instruction( Prompt::system( $this->user, $this->context ) )
			->using_abilities( ...$abilities )
			->using_max_tokens( (int) apply_filters( 'eobots_max_tokens', 8000 ) )
			->using_request_options( RequestOptions::fromArray( array( RequestOptions::KEY_TIMEOUT => 120 ) ) );

		/**
		 * Filters the preferred models, e.g. array( array( 'anthropic', 'claude-sonnet-5-5' ) ).
		 *
		 * @param array $models Preferred models, first is tried first.
		 */
		$models = (array) apply_filters( 'eobots_model_preference', Models::preference() );
		if ( $models ) {
			$builder->using_model_preference( ...$models );
		}

		$result = $builder->generate_text_result();
		if ( is_wp_error( $result ) ) {
			return $this->fail( $result );
		}

		try {
			$message = $result->toMessage();
			$usage   = $result->getTokenUsage();
			Store::add_usage( $this->conv['id'], $usage->getPromptTokens(), $usage->getCompletionTokens() );
		} catch ( \Throwable $e ) {
			return $this->fail( new \WP_Error( 'eobots_bad_answer', $e->getMessage() ) );
		}

		$history[] = $message->toArray();
		$text      = '';
		$calls     = array();

		foreach ( $message->getParts() as $part ) {
			if ( $part->getType()->isText() && $part->getChannel()->isContent() ) {
				$text .= $part->getText();
			} elseif ( $part->getType()->isFunctionCall() && $part->getFunctionCall() ) {
				$calls[] = $part->getFunctionCall();
			}
		}

		$text = trim( $text );
		if ( '' !== $text ) {
			Store::add_message( $this->conv['id'], 'bot', array( 'text' => $text ) );
			$this->events[] = array(
				'type' => 'text',
				'text' => $text,
			);
		}

		$steps = $this->conv['steps'] + 1;

		if ( ! $calls ) {
			$this->save(
				array(
					'history' => $history,
					'steps'   => $steps,
				)
			);
			return $this->response( 'done' );
		}

		$resolver  = new \WP_AI_Client_Ability_Function_Resolver( ...$abilities );
		$responses = array();
		$pending   = array();

		foreach ( $calls as $call ) {
			$ability = $this->ability_for( $resolver, $call );

			if ( $ability ) {
				$input = $ability->normalize_input( $call->getArgs() ? $call->getArgs() : null );

				// Ask the user only for actions they are allowed to do anyway.
				if ( Policy::needs_confirmation( $ability, (array) $input ) && true === $ability->check_permissions( $input ) ) {
					$card    = Policy::describe( $ability, (array) $input );
					$call_id = (string) $call->getId();

					$pending[] = array(
						'call'    => $call->toArray(),
						'ability' => $ability->get_name(),
					);

					$card = array_merge(
						$card,
						array(
							'callId' => $call_id,
							'state'  => 'pending',
						)
					);

					$this->events[] = array_merge(
						$card,
						array(
							'type' => 'confirm',
							'id'   => Store::add_message( $this->conv['id'], 'confirm', $card ),
						)
					);
					continue;
				}
			}

			$responses[] = $this->execute( $resolver, $call, $ability )->toArray();
		}

		if ( $pending ) {
			$this->save(
				array(
					'history' => $history,
					'steps'   => $steps,
					'pending' => array(
						'calls'     => $pending,
						'responses' => $responses,
					),
				)
			);
			return $this->response( 'confirm' );
		}

		$history[] = self::responses_message( $responses )->toArray();
		$this->save(
			array(
				'history' => $history,
				'steps'   => $steps,
			)
		);

		return $this->response( 'continue' );
	}

	/**
	 * The user approved or declined a pending action.
	 *
	 * @param string $call_id Function call ID.
	 * @param bool   $approve Whether the user approved.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function resolve( $call_id, $approve ) {
		$pending = $this->conv['pending'];
		$index   = null;

		foreach ( $pending['calls'] ?? array() as $i => $item ) {
			if ( (string) ( $item['call']['id'] ?? '' ) === $call_id ) {
				$index = $i;
				break;
			}
		}
		if ( null === $index ) {
			return new \WP_Error( 'eobots_not_pending', __( 'This action is no longer waiting for an answer.', 'eo-bots' ), array( 'status' => 409 ) );
		}

		$item = $pending['calls'][ $index ];
		$call = FunctionCall::fromArray( $item['call'] );

		if ( $approve ) {
			$resolver = new \WP_AI_Client_Ability_Function_Resolver( ...Policy::abilities() );
			$response = $this->execute( $resolver, $call, wp_get_ability( $item['ability'] ) );
			$state    = 'approved';
		} else {
			$response = new FunctionResponse(
				$call->getId(),
				$call->getName(),
				array(
					'error' => 'The user declined this action. Do not try again unless they ask.',
					'code'  => 'user_declined',
				)
			);
			$state    = 'declined';
		}

		Store::set_confirm_state( $this->conv['id'], $call_id, $state );
		$this->events[] = array(
			'type'   => 'confirm-state',
			'callId' => $call_id,
			'state'  => $state,
		);

		$pending['responses'][] = $response->toArray();
		array_splice( $pending['calls'], $index, 1 );

		if ( $pending['calls'] ) {
			$this->save( array( 'pending' => $pending ) );
			return $this->response( 'confirm' );
		}

		$history   = $this->conv['history'];
		$history[] = self::responses_message( $pending['responses'] )->toArray();
		$this->save(
			array(
				'history' => $history,
				'pending' => null,
			)
		);

		return $this->response( 'continue' );
	}

	/**
	 * Runs an ability and turns journaled changes into action cards.
	 *
	 * @param \WP_AI_Client_Ability_Function_Resolver $resolver Resolver limited to allowed abilities.
	 * @param FunctionCall                            $call     Call from the model.
	 * @param \WP_Ability|null                        $ability  Matching ability, if allowed.
	 * @return FunctionResponse
	 */
	private function execute( $resolver, $call, $ability ) {
		Journal::$conversation_id = $this->conv['id'];

		if ( $ability ) {
			$this->events[] = array(
				'type'  => 'tool',
				'label' => Policy::progress( $ability ),
			);
		}

		try {
			$response = $resolver->execute_ability( $call );
		} catch ( \Throwable $e ) {
			$response = new FunctionResponse(
				$call->getId(),
				$call->getName(),
				array(
					'error' => $e->getMessage(),
					'code'  => 'exception',
				)
			);
		}

		$data = $response->getResponse();
		if ( is_array( $data ) && ! empty( $data['journal_id'] ) ) {
			$card = Journal::card( (int) $data['journal_id'] );
			if ( $card ) {
				$this->events[] = array_merge(
					$card,
					array(
						'type' => 'action',
						'id'   => Store::add_message( $this->conv['id'], 'action', $card ),
					)
				);
			}
		}

		return $response;
	}

	/**
	 * Finds the allowed ability behind a function call.
	 *
	 * @param \WP_AI_Client_Ability_Function_Resolver $resolver Resolver.
	 * @param FunctionCall                            $call     Call.
	 * @return \WP_Ability|null
	 */
	private function ability_for( $resolver, $call ) {
		if ( ! $resolver->is_ability_call( $call ) ) {
			return null;
		}
		$name = \WP_AI_Client_Ability_Function_Resolver::function_name_to_ability_name( (string) $call->getName() );
		foreach ( Policy::abilities() as $ability ) {
			if ( $ability->get_name() === $name ) {
				return $ability;
			}
		}
		return null;
	}

	/**
	 * Records a failed step for the chat.
	 *
	 * @param \WP_Error $error Error.
	 * @return array<string, mixed>
	 */
	private function fail( $error ) {
		$text = __( 'Oops, I could not reach my AI. Try again in a moment.', 'eo-bots' );
		if ( current_user_can( 'manage_options' ) ) {
			$text .= ' (' . $error->get_error_message() . ')';
		}

		Store::add_message( $this->conv['id'], 'error', array( 'text' => $text ) );
		$this->events[] = array(
			'type'  => 'error',
			'text'  => $text,
			'retry' => true,
		);
		return $this->response( 'error' );
	}

	/**
	 * Adds a bot message without calling the model.
	 *
	 * @param string $text   Text.
	 * @param string $status Response status.
	 * @return array<string, mixed>
	 */
	private function say( $text, $status ) {
		Store::add_message( $this->conv['id'], 'bot', array( 'text' => $text ) );
		$this->events[] = array(
			'type' => 'text',
			'text' => $text,
		);
		return $this->response( $status );
	}

	/**
	 * Saves fields and keeps the in-memory row in sync.
	 *
	 * @param array<string, mixed> $fields Fields.
	 */
	private function save( $fields ) {
		Store::update_conversation( $this->conv['id'], $fields );
		$this->conv = array_merge( $this->conv, $fields );
	}

	/**
	 * Response sent to the browser.
	 *
	 * @param string $status continue, confirm, done or error.
	 * @return array<string, mixed>
	 */
	private function response( $status ) {
		return array(
			'status'       => $status,
			'conversation' => array(
				'id'    => $this->conv['id'],
				'title' => $this->conv['title'],
			),
			'events'       => $this->events,
		);
	}

	/**
	 * Wraps function responses in the user message that answers the model.
	 *
	 * @param array<int, array<string, mixed>> $responses Function responses as arrays.
	 * @return UserMessage
	 */
	private static function responses_message( $responses ) {
		return new UserMessage(
			array_map(
				static function ( $response ) {
					return new MessagePart( FunctionResponse::fromArray( $response ) );
				},
				$responses
			)
		);
	}

	/**
	 * Keeps the latest messages, starting on a plain user message so tool calls stay paired.
	 *
	 * @param Message[] $messages Messages.
	 * @return Message[]
	 */
	private static function trim( $messages ) {
		if ( count( $messages ) <= self::HISTORY_LIMIT ) {
			return $messages;
		}

		$kept = array_slice( $messages, -self::HISTORY_LIMIT );
		while ( isset( $kept[1] ) ) {
			$first = $kept[0];
			$plain = $first->getRole()->isUser();
			foreach ( $first->getParts() as $part ) {
				if ( $part->getType()->isFunctionResponse() ) {
					$plain = false;
				}
			}
			if ( $plain ) {
				break;
			}
			array_shift( $kept );
		}
		return array_values( $kept );
	}
}
