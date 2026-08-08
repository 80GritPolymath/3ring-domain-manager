<?php
/**
 * Uninstall cleanup.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$dm_settings = get_option( 'dm_settings', array() );
$dm_drop     = ! empty( $dm_settings['drop_tables_on_uninstall'] );

delete_option( 'dm_settings' );
delete_option( 'dm_db_version' );
delete_option( 'dm_missing_admin_user' );
delete_option( 'dm_plugin_admin_user_id' );

$dm_timestamp = wp_next_scheduled( 'dm_daily_alert_check' );
if ( $dm_timestamp ) {
	wp_unschedule_event( $dm_timestamp, 'dm_daily_alert_check' );
}

if ( ! $dm_drop ) {
	return;
}

global $wpdb;

$dm_tables = array(
	$wpdb->prefix . 'dm_providers',
	$wpdb->prefix . 'dm_domains',
	$wpdb->prefix . 'dm_renewals',
	$wpdb->prefix . 'dm_documents',
	$wpdb->prefix . 'dm_notes',
	$wpdb->prefix . 'dm_dns_records',
	$wpdb->prefix . 'dm_alerts',
	$wpdb->prefix . 'dm_audit_log',
);

foreach ( $dm_tables as $dm_table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall table drop.
	$wpdb->query( "DROP TABLE IF EXISTS {$dm_table}" );
}
