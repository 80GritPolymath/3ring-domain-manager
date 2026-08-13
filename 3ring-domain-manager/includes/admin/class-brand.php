<?php
/**
 * Brand color helpers — sanitize, derive palette, emit CSS vars.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Brand
 */
final class Brand {

	public const DEFAULT_COLOR = '#3300FF';

	/**
	 * Sanitize a hex color to #RRGGBB, or return the default.
	 *
	 * @param mixed $color Raw color value.
	 */
	public static function sanitize( $color ): string {
		if ( ! is_string( $color ) ) {
			return self::DEFAULT_COLOR;
		}

		$color = trim( $color );
		if ( '' === $color ) {
			return self::DEFAULT_COLOR;
		}

		if ( '#' !== $color[0] ) {
			$color = '#' . $color;
		}

		if ( preg_match( '/^#([A-Fa-f0-9]{3})$/', $color, $m ) ) {
			$h     = $m[1];
			$color = '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
		}

		if ( ! preg_match( '/^#[A-Fa-f0-9]{6}$/', $color ) ) {
			return self::DEFAULT_COLOR;
		}

		return strtoupper( $color );
	}

	/**
	 * Brand color from rindoma_settings (with default).
	 */
	public static function get_color(): string {
		$settings = get_option( 'rindoma_settings', array() );
		$color    = is_array( $settings ) && isset( $settings['brand_color'] ) ? $settings['brand_color'] : self::DEFAULT_COLOR;

		return self::sanitize( $color );
	}

	/**
	 * Derived palette tokens for a brand hex.
	 *
	 * @param string|null $hex Optional hex; defaults to saved setting.
	 * @return array{brand:string,dark:string,darker:string,tint:string,tint_strong:string,accent:string,rgb:string}
	 */
	public static function palette( ?string $hex = null ): array {
		$brand = self::sanitize( null === $hex ? self::get_color() : $hex );
		$rgb   = self::hex_to_rgb( $brand );

		return array(
			'brand'       => $brand,
			'dark'        => self::rgb_to_hex( self::darken( $rgb, 0.80 ) ),
			'darker'      => self::rgb_to_hex( self::darken( $rgb, 0.60 ) ),
			'tint'        => self::rgb_to_hex( self::mix_with_white( $rgb, 0.94 ) ),
			'tint_strong' => self::rgb_to_hex( self::mix_with_white( $rgb, 0.88 ) ),
			'accent'      => self::rgb_to_hex( self::mix_with_white( $rgb, 0.22 ) ),
			'rgb'         => $rgb[0] . ', ' . $rgb[1] . ', ' . $rgb[2],
		);
	}

	/**
	 * Inline CSS that overrides body.dm-page brand custom properties.
	 *
	 * @param string|null $hex Optional hex; defaults to saved setting.
	 */
	public static function inline_css( ?string $hex = null ): string {
		$p = self::palette( $hex );

		return sprintf(
			'body.dm-page{--dm-brand:%1$s;--dm-brand-dark:%2$s;--dm-brand-darker:%3$s;--dm-brand-tint:%4$s;--dm-brand-tint-strong:%5$s;--dm-brand-accent:%6$s;--dm-brand-rgb:%7$s;}',
			esc_attr( $p['brand'] ),
			esc_attr( $p['dark'] ),
			esc_attr( $p['darker'] ),
			esc_attr( $p['tint'] ),
			esc_attr( $p['tint_strong'] ),
			esc_attr( $p['accent'] ),
			esc_attr( $p['rgb'] )
		);
	}

	/**
	 * @return array{0:int,1:int,2:int}
	 */
	private static function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );

		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * @param array{0:int|float,1:int|float,2:int|float} $rgb RGB channels.
	 */
	private static function rgb_to_hex( array $rgb ): string {
		return sprintf(
			'#%02X%02X%02X',
			(int) max( 0, min( 255, round( $rgb[0] ) ) ),
			(int) max( 0, min( 255, round( $rgb[1] ) ) ),
			(int) max( 0, min( 255, round( $rgb[2] ) ) )
		);
	}

	/**
	 * @param array{0:int,1:int,2:int} $rgb    RGB channels.
	 * @param float                    $factor Multiplier 0–1.
	 * @return array{0:float,1:float,2:float}
	 */
	private static function darken( array $rgb, float $factor ): array {
		return array(
			$rgb[0] * $factor,
			$rgb[1] * $factor,
			$rgb[2] * $factor,
		);
	}

	/**
	 * Mix RGB toward white. $white_amount 0 = pure color, 1 = white.
	 *
	 * @param array{0:int,1:int,2:int} $rgb          RGB channels.
	 * @param float                    $white_amount Mix ratio toward white.
	 * @return array{0:float,1:float,2:float}
	 */
	private static function mix_with_white( array $rgb, float $white_amount ): array {
		$white_amount = max( 0.0, min( 1.0, $white_amount ) );
		$keep         = 1.0 - $white_amount;

		return array(
			( $rgb[0] * $keep ) + ( 255 * $white_amount ),
			( $rgb[1] * $keep ) + ( 255 * $white_amount ),
			( $rgb[2] * $keep ) + ( 255 * $white_amount ),
		);
	}
}
