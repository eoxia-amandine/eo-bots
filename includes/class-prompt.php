<?php
/**
 * System instruction given to the model.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the bot's personality, rules and current context.
 */
class Prompt {

	/**
	 * Full system instruction.
	 *
	 * @param \WP_User             $user    Current user.
	 * @param array<string, mixed> $context Where the user is (sanitized).
	 * @return string
	 */
	public static function system( $user, $context ) {
		$avatar   = Avatar::get( $user->ID ) ?? Avatar::defaults();
		$animal   = $avatar['animal']; // English slug (fox, cat…): the instruction is in English.
		$locale   = get_user_locale( $user );
		$language = class_exists( '\Locale' ) ? \Locale::getDisplayLanguage( $locale, 'en' ) : $locale;

		// The instruction lives in prompts/system.md so it reads as plain text; {{placeholders}} are filled below.
		$instruction = (string) file_get_contents( EO_BOTS_PATH . 'prompts/system.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file shipped with the plugin.

		return strtr(
			$instruction,
			array(
				'{{name}}'     => $avatar['name'],
				'{{animal}}'   => $animal,
				'{{language}}' => $language,
				'{{user}}'     => $user->display_name,
				'{{date}}'     => wp_date( 'l j F Y' ),
				'{{roles}}'    => implode( ', ', $user->roles ),
				'{{location}}' => self::location( $context ),
				'{{site}}'     => Site_Profile::summary(),
				'{{guides}}'   => Guides::summary(),
				'{{notes}}'    => self::notes(),
			)
		);
	}

	/**
	 * Notes the site team taught the bot.
	 *
	 * @return string
	 */
	private static function notes() {
		$notes = Notes::prompt();
		if ( '' === $notes ) {
			return 'No note yet.';
		}
		return "Notes from the site administrators. Follow them in everything you do; they win over the guides:\n" . $notes;
	}

	/**
	 * Describes where the user currently is.
	 *
	 * @param array<string, mixed> $context Sanitized context.
	 * @return string
	 */
	private static function location( $context ) {
		$post = ! empty( $context['postId'] ) ? get_post( (int) $context['postId'] ) : null;

		if ( $post && current_user_can( 'edit_post', $post->ID ) ) {
			$where = 'front' === ( $context['area'] ?? '' ) ? 'is viewing it on the public site' : 'has it open in the admin';
			return sprintf( 'The user %s: %s #%d "%s" (status %s). "This page" means this one.', $where, $post->post_type, $post->ID, get_the_title( $post ), $post->post_status );
		}

		if ( 'front' === ( $context['area'] ?? '' ) ) {
			return sprintf( 'The user is browsing the public site at %s.', $context['url'] ?? home_url( '/' ) );
		}

		return sprintf( 'The user is in the admin, on the "%s" screen.', $context['screen'] ?? 'dashboard' );
	}
}
