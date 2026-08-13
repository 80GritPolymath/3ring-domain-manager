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

$rindoma_settings = get_option( 'rindoma_settings', array() );
$rindoma_drop     = ! empty( $rindoma_settings['drop_tables_on_uninstall'] );

delete_option( 'rindoma_settings' );
delete_option( 'rindoma_db_version' );
delete_option( 'rindoma_missing_admin_user' );
delete_option( 'rindoma_plugin_admin_user_id' );
delete_option( 'rindoma_legacy_migrated' );

$rindoma_timestamp = wp_next_scheduled( 'rindoma_daily_alert_check' );
if ( $rindoma_timestamp ) {
	wp_unschedule_event( $rindoma_timestamp, 'rindoma_daily_alert_check' );
}

if ( ! $rindoma_drop ) {
	return;
}

global $wpdb;

$rindoma_tables = array(
	$wpdb->prefix . 'rindoma_providers',
	$wpdb->prefix . 'rindoma_domains',
	$wpdb->prefix . 'rindoma_renewals',
	$wpdb->prefix . 'rindoma_documents',
	$wpdb->prefix . 'rindoma_notes',
	$wpdb->prefix . 'rindoma_dns_records',
	$wpdb->prefix . 'rindoma_alerts',
	$wpdb->prefix . 'rindoma_audit_log',
);

foreach ( $rindoma_tables as $rindoma_table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall table drop.
	$wpdb->query( "DROP TABLE IF EXISTS {$rindoma_table}" );
}
