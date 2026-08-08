<?php
/**
 * Activation / deactivation hooks.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager;

use ThreeRing\DomainManager\Admin\Brand;
use ThreeRing\DomainManager\Db\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 */
final class Activator {

	public const CRON_HOOK = 'dm_daily_alert_check';

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		Schema::install();

		$defaults = array(
			'alert_windows'            => array( 90, 60, 30 ),
			'review_interval_days'     => 180,
			'default_currency'         => 'CAD',
			'drop_tables_on_uninstall' => false,
			'max_upload_mb'            => 10,
			'brand_color'              => Brand::DEFAULT_COLOR,
		);

		if ( false === get_option( 'dm_settings' ) ) {
			add_option( 'dm_settings', $defaults, '', false );
		}

		// Grant Plugin Administrator to the user activating the plugin.
		$activator = wp_get_current_user();
		$granted   = ( $activator instanceof \WP_User && $activator->ID > 0 )
			? Capabilities::grant_plugin_admin( $activator )
			: Capabilities::grant_plugin_admin();

		if ( ! $granted ) {
			update_option( 'dm_missing_admin_user', 1, false );
		} else {
			delete_option( 'dm_missing_admin_user' );
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}
}
