<?php
/**
 * Guardrails: which abilities the bot may use and which need the user's confirmation.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the bot from going off the rails.
 */
class Policy {

	/**
	 * Abilities handed to the model.
	 *
	 * @return \WP_Ability[]
	 */
	public static function abilities() {
		// Its own abilities, plus the plugin abilities that an active guide enables.
		$patterns  = array_merge( array( 'eobots/*' ), Guides::ability_patterns() );
		$abilities = array_filter(
			wp_get_abilities(),
			static function ( $ability ) use ( $patterns ) {
				// Abilities the user can never use (e.g. code for non-admins) are not shown to the model at all.
				$available = $ability->get_meta_item( 'eobots', array() )['available'] ?? null;
				if ( is_callable( $available ) && ! call_user_func( $available ) ) {
					return false;
				}
				foreach ( $patterns as $pattern ) {
					if ( fnmatch( $pattern, $ability->get_name() ) ) {
						return true;
					}
				}
				return false;
			}
		);

		/**
		 * Filters the abilities EO-BOTS may call. Abilities without a confirmation rule
		 * (`eobots.confirm` meta or guide `confirm:` line) always ask the user first unless they are annotated read-only.
		 *
		 * @param \WP_Ability[] $abilities Abilities.
		 */
		return array_values( (array) apply_filters( 'eobots_abilities', $abilities ) );
	}

	/**
	 * Whether the user must confirm before the ability runs.
	 *
	 * @param \WP_Ability          $ability Ability.
	 * @param array<string, mixed> $input   Arguments from the model.
	 * @return bool
	 */
	public static function needs_confirmation( $ability, $input ) {
		$rule = $ability->get_meta_item( 'eobots', array() )['confirm'] ?? Guides::confirm_rule( $ability->get_name() );

		if ( null === $rule ) {
			$rule = ! empty( $ability->get_meta_item( 'annotations', array() )['readonly'] ) ? 'never' : 'always';
		}

		if ( 'live' === $rule ) {
			$post = self::target_post( $input );
			// Unknown target: ask rather than guess.
			return ! $post || in_array( $post->post_status, Abilities\Content::LIVE_STATUSES, true );
		}

		return 'never' !== $rule;
	}

	/**
	 * Post targeted by an ability input: id, post_id or permalink.
	 *
	 * @param array<string, mixed> $input Input.
	 * @return \WP_Post|null
	 */
	public static function target_post( $input ) {
		$id = (int) ( $input['id'] ?? $input['post_id'] ?? 0 );
		if ( ! $id && ! empty( $input['permalink'] ) ) {
			$id = url_to_postid( (string) $input['permalink'] );
		}
		return $id ? get_post( $id ) : null;
	}

	/**
	 * Texts of the confirmation card.
	 *
	 * @param \WP_Ability          $ability Ability.
	 * @param array<string, mixed> $input   Arguments from the model.
	 * @return array{title: string, summary: string, danger: bool, code?: string, lang?: string}
	 */
	public static function describe( $ability, $input ) {
		$describe = $ability->get_meta_item( 'eobots', array() )['describe'] ?? null;
		$card     = is_callable( $describe ) ? (array) call_user_func( $describe, $input ) : array();

		// Other plugins' abilities: name the targeted page and list the changed settings.
		if ( ! $card ) {
			$post   = self::target_post( $input );
			$fields = array_diff( array_keys( (array) $input ), array( 'id', 'post_id', 'permalink' ) );
			if ( $post ) {
				/* translators: 1: action label, 2: content title. */
				$card['title'] = sprintf( __( '%1$s: “%2$s”', 'eo-bots' ), $ability->get_label(), get_the_title( $post ) );
			}
			if ( $fields ) {
				/* translators: %s: list of settings. */
				$card['summary'] = sprintf( __( 'Settings changed: %s.', 'eo-bots' ), implode( ', ', array_map( static fn( $f ) => str_replace( '_', ' ', $f ), $fields ) ) );
			}
		}

		return array_merge(
			array(
				'title'   => $card['title'] ?? $ability->get_label(),
				'summary' => $card['summary'] ?? '',
				'danger'  => ! empty( $card['danger'] ) || ! empty( $ability->get_meta_item( 'annotations', array() )['destructive'] ),
			),
			// Code shown on the card, for snippets.
			array_intersect_key( $card, array_flip( array( 'code', 'lang' ) ) )
		);
	}

	/**
	 * Short text shown while the ability runs.
	 *
	 * @param \WP_Ability $ability Ability.
	 * @return string
	 */
	public static function progress( $ability ) {
		return $ability->get_meta_item( 'eobots', array() )['progress'] ?? $ability->get_label();
	}

	/**
	 * Limits how many model calls a user can trigger in 10 minutes.
	 *
	 * @param int $user_id User ID.
	 * @return bool True when the call is allowed.
	 */
	public static function consume_rate( $user_id ) {
		$key   = 'eobots_rate_' . $user_id;
		$count = (int) get_transient( $key );
		$max   = (int) apply_filters( 'eobots_max_steps_per_10_minutes', 150 );

		if ( $count >= $max ) {
			return false;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}
}
