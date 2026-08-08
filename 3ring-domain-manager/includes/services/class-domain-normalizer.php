<?php
/**
 * Domain name normalization.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class Domain_Normalizer
 */
final class Domain_Normalizer {

	/**
	 * Normalize a domain for uniqueness comparisons.
	 *
	 * @param string $domain Domain name.
	 */
	public static function normalize( string $domain ): string {
		$domain = trim( strtolower( $domain ) );
		$domain = preg_replace( '#^https?://#', '', $domain );
		$domain = preg_replace( '#^www\.#', '', $domain );
		$domain = rtrim( $domain, '/' );
		$domain = preg_replace( '#/.*$#', '', $domain );

		return (string) $domain;
	}
}
