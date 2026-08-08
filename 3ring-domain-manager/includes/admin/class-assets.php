<?php
/**
 * Admin assets.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Admin;

use ThreeRing\DomainManager\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class Assets
 */
final class Assets {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Slugs of every Domain Manager admin screen.
	 *
	 * @return string[]
	 */
	public static function page_slugs(): array {
		return array(
			Admin_Menu::SLUG,
			'dm-domains',
			'dm-domain-new',
			'dm-providers',
			'dm-import-export',
			'dm-settings',
		);
	}

	/**
	 * Whether the current request is a Domain Manager screen.
	 */
	public static function is_plugin_screen(): bool {
		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return in_array( $page, self::page_slugs(), true );
	}

	/**
	 * Flag plugin screens so styles can scope to them.
	 *
	 * @param string $classes Existing body classes.
	 */
	public function body_class( string $classes ): string {
		if ( self::is_plugin_screen() && Capabilities::current_user_can_view() ) {
			$classes .= ' dm-page';
		}

		return $classes;
	}

	/**
	 * Enqueue admin CSS/JS on plugin pages only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( string $hook ): void {
		if ( ! Capabilities::current_user_can_view() ) {
			return;
		}

		if ( ! self::is_plugin_screen() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_enqueue_style(
			'dm-admin',
			DM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			DM_VERSION
		);

		wp_add_inline_style( 'dm-admin', Brand::inline_css() );

		$script_deps = array( 'jquery' );
		if ( 'dm-settings' === $page ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			$script_deps[] = 'wp-color-picker';
		}

		wp_enqueue_script(
			'dm-admin',
			DM_PLUGIN_URL . 'assets/js/admin.js',
			$script_deps,
			DM_VERSION,
			true
		);

		if ( 'dm-settings' === $page ) {
			wp_localize_script(
				'dm-admin',
				'dmBrand',
				array(
					'defaultColor' => Brand::DEFAULT_COLOR,
				)
			);
		}
	}
}
