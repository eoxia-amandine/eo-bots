<?php
/**
 * Which AI model the EO-BOTs of the site use.
 *
 * @package EoBots
 */

namespace EoBots\Includes;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Models\DTO\ModelRequirements;
use WordPress\AiClient\Providers\Models\DTO\RequiredOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-wide model choice. Empty means "automatic": WordPress picks a suitable model.
 */
class Models {

	const OPTION    = 'eobots_model';
	const CACHE_KEY = 'eobots_models';

	/**
	 * Models of the connected providers that can drive the agent (text generation with tools).
	 *
	 * @param bool $refresh Ignore the cache.
	 * @return array<int, array{id: string, provider: string, name: string}>
	 */
	public static function available( $refresh = false ) {
		$cached = $refresh ? false : get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$models       = array();
		$requirements = new ModelRequirements(
			array( CapabilityEnum::textGeneration() ),
			array( new RequiredOption( OptionEnum::functionDeclarations(), true ) )
		);

		try {
			$registry = AiClient::defaultRegistry();
			foreach ( $registry->getRegisteredProviderIds() as $provider_id ) {
				if ( ! $registry->isProviderConfigured( $provider_id ) ) {
					continue;
				}
				$class    = $registry->getProviderClassName( $provider_id );
				$provider = $class::metadata()->getName();
				foreach ( $registry->findProviderModelsMetadataForSupport( $provider_id, $requirements ) as $model ) {
					// Providers also list image, audio, research or robotics models that cannot hold a chat.
					if ( preg_match( '/image|banana|lyria|transcribe|tts|audio|speech|embed|veo|imagen|robotics|computer-use|deep-research|antigravity|customtools/i', $model->getId() ) ) {
						continue;
					}
					$models[] = array(
						'id'       => $provider_id . '::' . $model->getId(),
						'provider' => $provider,
						'name'     => $model->getName(),
					);
				}
			}
		} catch ( \Throwable $e ) {
			return $models;
		}

		set_transient( self::CACHE_KEY, $models, 6 * HOUR_IN_SECONDS );
		return $models;
	}

	/**
	 * Saved choice, "provider::model" or empty for automatic.
	 *
	 * @return string
	 */
	public static function current() {
		return (string) get_option( self::OPTION, '' );
	}

	/**
	 * Saves the choice if it is one of the available models (or empty).
	 *
	 * @param string $id Model ID.
	 * @return bool
	 */
	public static function save( $id ) {
		if ( '' !== $id && ! in_array( $id, wp_list_pluck( self::available(), 'id' ), true ) ) {
			return false;
		}
		update_option( self::OPTION, $id, false );
		return true;
	}

	/**
	 * Preference passed to the AI Client.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	public static function preference() {
		$parts = explode( '::', self::current(), 2 );
		return 2 === count( $parts ) ? array( $parts ) : array();
	}
}
