<?php
/**
 * Registers the EO-BOTS abilities with the core Abilities API.
 *
 * @package EoBots
 */

namespace EoBots\Includes\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Category and registration entry point.
 *
 * Besides the core `annotations`, each ability carries an `eobots` meta entry read by the agent:
 * - `confirm`:  never | always | live (only when the targeted post is visible on the site);
 * - `describe`: callable( array $input ): array{title: string, summary: string, danger?: bool}, for confirmation cards;
 * - `progress`: short text shown while the bot works;
 * - `available`: callable(): bool, hides the ability from the model when the user can never use it.
 */
class Registry {

	const CATEGORY = 'eobots';

	/**
	 * Registers the ability category.
	 */
	public static function register_category() {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'EO-BOTS', 'eo-bots' ),
				'description' => __( 'What your EO-BOT can do on the site.', 'eo-bots' ),
			)
		);
	}

	/**
	 * Registers every ability.
	 */
	public static function register() {
		Site::register();
		Content::register();
		Memory::register();
		Site_Editor::register();
		Code::register();

		if ( defined( 'WPSEO_VERSION' ) ) {
			\EoBots\Includes\Integrations\Yoast::register();
		}
	}

	/**
	 * Registers one ability with shared defaults.
	 *
	 * @param string               $name Ability name without the `eobots/` prefix.
	 * @param array<string, mixed> $args Ability arguments.
	 */
	public static function add( $name, $args ) {
		$args['category'] = self::CATEGORY;
		$args['meta']     = array_merge(
			array(
				// Kept out of the REST API for now; the chat runs them server side.
				'show_in_rest' => false,
			),
			$args['meta'] ?? array()
		);

		wp_register_ability( 'eobots/' . $name, $args );
	}

	/**
	 * Annotations shortcut.
	 *
	 * @param bool $read_only   Does not change anything.
	 * @param bool $destructive May remove or overwrite things.
	 * @return array<string, bool>
	 */
	public static function annotations( $read_only, $destructive = false ) {
		return array(
			'readonly'    => $read_only,
			'destructive' => $destructive,
			'idempotent'  => $read_only,
		);
	}

	/**
	 * Whether the user can edit content at all.
	 *
	 * @return bool
	 */
	public static function can_edit() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Cuts long text so tool results stay small.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max characters.
	 * @return string
	 */
	public static function clip( $text, $max ) {
		$text = (string) $text;
		if ( strlen( $text ) <= $max ) {
			return $text;
		}
		return substr( $text, 0, $max ) . "\n[… truncated, " . ( strlen( $text ) - $max ) . ' more characters]';
	}
}
