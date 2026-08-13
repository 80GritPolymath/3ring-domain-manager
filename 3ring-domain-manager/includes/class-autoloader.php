<?php
/**
 * PSR-4 style autoloader for ThreeRing\DomainManager.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader
 */
final class Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'autoload' ) );
	}

	/**
	 * Autoload a class in the ThreeRing\DomainManager namespace.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public static function autoload( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$parts    = explode( '\\', $relative );
		$class_part = array_pop( $parts );

		// Convert Class_Name to class-class-name.php (WordPress style).
		$file = 'class-' . str_replace( '_', '-', strtolower( $class_part ) ) . '.php';

		$subdir = '';
		if ( ! empty( $parts ) ) {
			$subdir = strtolower( implode( '/', $parts ) ) . '/';
		}

		$path = RINDOMA_PLUGIN_DIR . 'includes/' . $subdir . $file;

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}
