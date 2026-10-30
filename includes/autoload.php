<?php
/**
 * Autoloader for EO-BOTS.
 *
 * Maps `EoBots\Includes\Foo_Bar` to `includes/class-foo-bar.php`
 * and `EoBots\Includes\Abilities\Foo` to `includes/abilities/class-foo.php`.
 *
 * @package EoBots
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'EoBots\\Includes\\';

		if ( 0 !== strncmp( $prefix, $class_name, strlen( $prefix ) ) ) {
			return;
		}

		$parts = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$class = array_pop( $parts );
		$dir   = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
		$file  = EO_BOTS_PATH . 'includes/' . $dir . 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
