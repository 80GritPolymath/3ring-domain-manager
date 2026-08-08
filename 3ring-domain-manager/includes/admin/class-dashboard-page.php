<?php
/**
 * Dashboard page.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Admin;

use ThreeRing\DomainManager\Capabilities;
use ThreeRing\DomainManager\Db\Alerts_Repository;
use ThreeRing\DomainManager\Db\Domains_Repository;
use ThreeRing\DomainManager\Db\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class Dashboard_Page
 */
final class Dashboard_Page {

	/**
	 * Render dashboard.
	 */
	public static function render(): void {
		if ( ! Capabilities::current_user_can_view() ) {
			wp_die( esc_html__( 'You do not have permission to access Domain Manager.', '3ring-domain-manager' ) );
		}

		if ( ! Schema::tables_exist() ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Domain Manager', '3ring-domain-manager' ) . '</h1>';
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Database tables are missing. Run the SQL files in plugins/3ring-domain-manager/sql/ via PHPMyAdmin.', '3ring-domain-manager' ) . '</p></div></div>';
			return;
		}

		$stats         = ( new Domains_Repository() )->dashboard_stats();
		$alerts        = ( new Alerts_Repository() )->recent( 10 );
		$base          = admin_url( 'admin.php?page=dm-domains' );
		$domains_table = new Domains_List_Table( 'dashboard' );
		$domains_table->prepare_items();

		include DM_PLUGIN_DIR . 'includes/admin/views/dashboard.php';
	}
}
