<?php
/**
 * Each user's EO-BOT: catalog of choices, validation and storage.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The look and name of a user's EO-BOT, stored in user meta.
 */
class Avatar {

	const META     = 'eobots_avatar';
	const NAME_MAX = 14;

	/**
	 * Animals, keyed by id.
	 *
	 * @return array<string, string>
	 */
	public static function animals() {
		return array(
			'fox'    => __( 'Fox', 'eo-bots' ),
			'cat'    => __( 'Cat', 'eo-bots' ),
			'dog'    => __( 'Dog', 'eo-bots' ),
			'rabbit' => __( 'Rabbit', 'eo-bots' ),
			'sheep'  => __( 'Sheep', 'eo-bots' ),
			'panda'  => __( 'Panda', 'eo-bots' ),
		);
	}

	/**
	 * Colors, keyed by hex value.
	 *
	 * @return array<string, string>
	 */
	public static function colors() {
		return array(
			'#3cc7ae' => __( 'Mint', 'eo-bots' ),
			'#2f9fe0' => __( 'Lagoon', 'eo-bots' ),
			'#8b7cf6' => __( 'Lavender', 'eo-bots' ),
			'#ff7466' => __( 'Coral', 'eo-bots' ),
			'#f5b82e' => __( 'Sunshine', 'eo-bots' ),
			'#e4508c' => __( 'Raspberry', 'eo-bots' ),
			'#6cc04a' => __( 'Apple', 'eo-bots' ),
			'#ff8f3d' => __( 'Tangerine', 'eo-bots' ),
			'#5f7180' => __( 'Slate', 'eo-bots' ),
		);
	}

	/**
	 * Head accessories, keyed by id.
	 *
	 * @return array<string, string>
	 */
	public static function accessories() {
		return array(
			'none'       => __( 'Nothing', 'eo-bots' ),
			'beanie'     => __( 'Beanie', 'eo-bots' ),
			'beret'      => __( 'Beret', 'eo-bots' ),
			'bowler'     => __( 'Bowler hat', 'eo-bots' ),
			'tophat'     => __( 'Top hat', 'eo-bots' ),
			'crown'      => __( 'Crown', 'eo-bots' ),
			'headphones' => __( 'Headphones', 'eo-bots' ),
			'glasses'    => __( 'Glasses', 'eo-bots' ),
			'bow'        => __( 'Bow', 'eo-bots' ),
		);
	}

	/**
	 * Name ideas for the dice.
	 *
	 * @return string[]
	 */
	public static function name_ideas() {
		return array( 'Nova', 'Mochi', 'Pixel', 'Biscuit', 'Gizmo', 'Noisette', 'Plume', 'Comète', 'Sushi', 'Byte', 'Praline', 'Zéphyr', 'Pistache', 'Bolt' );
	}

	/**
	 * Catalog for the JavaScript side, as ordered lists.
	 *
	 * @return array<string, mixed>
	 */
	public static function catalog() {
		$list = static function ( $items, $key ) {
			$out = array();
			foreach ( $items as $id => $label ) {
				$out[] = array(
					$key    => $id,
					'label' => $label,
				);
			}
			return $out;
		};

		return array(
			'animals'     => $list( self::animals(), 'id' ),
			'colors'      => $list( self::colors(), 'hex' ),
			'accessories' => $list( self::accessories(), 'id' ),
			'names'       => self::name_ideas(),
			'nameMax'     => self::NAME_MAX,
		);
	}

	/**
	 * Default EO-BOT shown on the creation screen.
	 *
	 * @return array<string, string>
	 */
	public static function defaults() {
		return array(
			'animal'    => 'fox',
			'color'     => '#3cc7ae',
			'accessory' => 'headphones',
			'name'      => 'Nova',
		);
	}

	/**
	 * Returns a user's EO-BOT, or null when they have not created one yet.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, string>|null
	 */
	public static function get( $user_id ) {
		$data = get_user_meta( $user_id, self::META, true );
		return is_array( $data ) && ! empty( $data['name'] ) ? self::sanitize( $data ) : null;
	}

	/**
	 * Saves a user's EO-BOT.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $data    Raw data.
	 * @return array<string, string> The saved EO-BOT.
	 */
	public static function save( $user_id, $data ) {
		$avatar = self::sanitize( $data );
		update_user_meta( $user_id, self::META, $avatar );
		return $avatar;
	}

	/**
	 * Keeps only known values; anything unknown falls back to the default.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, string>
	 */
	public static function sanitize( $data ) {
		$defaults = self::defaults();
		$data     = wp_parse_args( is_array( $data ) ? $data : array(), $defaults );

		$color = sanitize_hex_color( (string) $data['color'] );
		$name  = trim( sanitize_text_field( (string) $data['name'] ) );
		$name  = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, self::NAME_MAX ) : substr( $name, 0, self::NAME_MAX );

		return array(
			'animal'    => isset( self::animals()[ $data['animal'] ] ) ? $data['animal'] : $defaults['animal'],
			'color'     => $color ? strtolower( $color ) : $defaults['color'],
			'accessory' => isset( self::accessories()[ $data['accessory'] ] ) ? $data['accessory'] : $defaults['accessory'],
			'name'      => '' !== $name ? $name : $defaults['name'],
		);
	}
}
