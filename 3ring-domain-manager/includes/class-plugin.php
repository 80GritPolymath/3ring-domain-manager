<?php
/**
 * Main plugin bootstrap.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager;

use ThreeRing\DomainManager\Admin\Admin_Menu;
use ThreeRing\DomainManager\Admin\Assets;
use ThreeRing\DomainManager\Admin\User_Profile;
use ThreeRing\DomainManager\Db\Schema;
use ThreeRing\DomainManager\Frontend\Domain_List_Shortcode;
use ThreeRing\DomainManager\Services\Alert_Service;
use ThreeRing\DomainManager\Services\Document_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Wire hooks.
	 */
	public function init(): void {
		// Create/upgrade tables if missing or version changed (also runs on activation).
		Schema::maybe_upgrade();

		if ( is_admin() ) {
			( new Admin_Menu() )->register();
			( new Assets() )->register();
			( new User_Profile() )->register();
			add_action( 'admin_notices', array( $this, 'maybe_show_notices' ) );
			add_action( 'admin_init', array( $this, 'ensure_plugin_admin' ) );
		}

		( new Document_Service() )->register();
		( new Alert_Service() )->register();
		( new Domain_List_Shortcode() )->register();
	}

	/**
	 * Re-grant Plugin Admin caps to the designated installing admin if needed.
	 */
	public function ensure_plugin_admin(): void {
		$stored_id = (int) get_option( Capabilities::PLUGIN_ADMIN_OPTION, 0 );
		if ( $stored_id > 0 ) {
			$user = get_userdata( $stored_id );
			if ( $user instanceof \WP_User ) {
				Capabilities::grant_plugin_admin( $user );
				return;
			}
		}

		// Stored admin missing (deleted user) — recover via current/fallback admin.
		Capabilities::grant_plugin_admin();
	}

	/**
	 * Admin notices for missing tables / admin user.
	 */
	public function maybe_show_notices(): void {
		if ( ! current_user_can( 'manage_options' ) && ! Capabilities::current_user_can_admin() ) {
			return;
		}

		if ( get_option( 'dm_missing_admin_user' ) ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__(
				'3RING Domain Manager: Could not assign a Plugin Administrator. Deactivate and reactivate the plugin while logged in as a site administrator.',
				'3ring-domain-manager'
			);
			echo '</p></div>';
		}

		if ( ! Schema::tables_exist() ) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__(
				'3RING Domain Manager: Required database tables are missing. Deactivate and reactivate the plugin to recreate them, or contact your site administrator.',
				'3ring-domain-manager'
			);
			echo '</p></div>';
		}
	}
}
