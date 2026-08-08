<?php
/**
 * Admin menu registration.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Admin;

use ThreeRing\DomainManager\Capabilities;
use ThreeRing\DomainManager\Db\Schema;
use ThreeRing\DomainManager\Services\Alert_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Menu
 */
final class Admin_Menu {

	public const SLUG = '3ring-domain-manager';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_init', array( $this, 'maybe_run_alerts_on_admin' ) );
	}

	/**
	 * Re-run alert checks when any Domain Manager admin page loads.
	 */
	public function maybe_run_alerts_on_admin(): void {
		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $page, Assets::page_slugs(), true ) ) {
			return;
		}

		if ( Schema::tables_exist() ) {
			( new Alert_Service() )->run_checks();
		}
	}

	/**
	 * Register menu pages.
	 */
	public function add_menus(): void {
		add_menu_page(
			__( 'Domain Manager', '3ring-domain-manager' ),
			__( 'Domain Manager', '3ring-domain-manager' ),
			Capabilities::VIEW,
			self::SLUG,
			array( Dashboard_Page::class, 'render' ),
			'dashicons-admin-site-alt3',
			58
		);

		add_submenu_page(
			self::SLUG,
			__( 'Dashboard', '3ring-domain-manager' ),
			__( 'Dashboard', '3ring-domain-manager' ),
			Capabilities::VIEW,
			self::SLUG,
			array( Dashboard_Page::class, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Domains', '3ring-domain-manager' ),
			__( 'Domains', '3ring-domain-manager' ),
			Capabilities::VIEW,
			'dm-domains',
			array( Domain_Edit_Page::class, 'render_list_or_edit' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Add Domain', '3ring-domain-manager' ),
			__( 'Add Domain', '3ring-domain-manager' ),
			Capabilities::EDIT,
			'dm-domain-new',
			array( Domain_Edit_Page::class, 'render_new' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Providers', '3ring-domain-manager' ),
			__( 'Providers', '3ring-domain-manager' ),
			Capabilities::MANAGE,
			'dm-providers',
			array( Providers_Page::class, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Import / Export', '3ring-domain-manager' ),
			__( 'Import / Export', '3ring-domain-manager' ),
			Capabilities::MANAGE,
			'dm-import-export',
			array( Import_Export_Page::class, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Settings', '3ring-domain-manager' ),
			__( 'Settings', '3ring-domain-manager' ),
			Capabilities::MANAGE,
			'dm-settings',
			array( Settings_Page::class, 'render' )
		);
	}

	/**
	 * Early admin actions (export download, deletes, etc.).
	 */
	public function handle_actions(): void {
		if ( ! is_admin() ) {
			return;
		}

		Import_Export_Page::maybe_handle_export();
		Domain_Edit_Page::maybe_handle_list_actions();
		Domain_Edit_Page::maybe_handle_post();
		Providers_Page::maybe_handle_actions();
		Settings_Page::maybe_handle_save();
		Settings_Page::maybe_handle_test_email();
	}
}
