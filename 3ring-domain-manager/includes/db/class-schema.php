<?php
/**
 * Database schema helpers.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Db;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom tables; names come from Schema::table(), values use $wpdb->prepare().

/**
 * Class Schema
 */
final class Schema {

	/**
	 * Pre-1.0.0 identifier prefix (2 characters). Used only to migrate stored data.
	 */
	private const LEGACY = 'dm';

	/**
	 * Table short names.
	 *
	 * @return string[]
	 */
	public static function table_keys(): array {
		return array(
			'providers',
			'domains',
			'renewals',
			'documents',
			'notes',
			'dns_records',
			'alerts',
			'audit_log',
		);
	}

	/**
	 * Full table name with WP prefix.
	 *
	 * @param string $key Short table key.
	 */
	public static function table( string $key ): string {
		global $wpdb;

		return $wpdb->prefix . 'rindoma_' . $key;
	}

	/**
	 * Whether a table exists (escapes LIKE wildcards in the name).
	 *
	 * @param string $table Full table name.
	 */
	private static function table_exists_named( string $table ): bool {
		global $wpdb;

		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

		return $found === $table;
	}

	/**
	 * Whether all required tables exist.
	 */
	public static function tables_exist(): bool {
		foreach ( self::table_keys() as $key ) {
			if ( ! self::table_exists_named( self::table( $key ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Create or update plugin tables (dbDelta).
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$providers       = self::table( 'providers' );
		$domains         = self::table( 'domains' );
		$renewals        = self::table( 'renewals' );
		$documents       = self::table( 'documents' );
		$notes           = self::table( 'notes' );
		$dns_records     = self::table( 'dns_records' );
		$alerts          = self::table( 'alerts' );
		$audit_log       = self::table( 'audit_log' );

		$sql = array();

		$sql[] = "CREATE TABLE {$providers} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			provider_type varchar(191) NOT NULL,
			account_id varchar(191) DEFAULT NULL,
			account_email varchar(191) DEFAULT NULL,
			website_url varchar(500) DEFAULT NULL,
			management_url varchar(500) DEFAULT NULL,
			notes text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY provider_type (provider_type),
			KEY name (name)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$domains} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			domain_name varchar(253) NOT NULL,
			domain_name_normalized varchar(253) NOT NULL,
			display_name varchar(191) DEFAULT NULL,
			portfolio_status varchar(32) NOT NULL DEFAULT 'active',
			usage_type varchar(32) NOT NULL DEFAULT 'unknown',
			business_purpose text,
			business_importance varchar(16) NOT NULL DEFAULT 'standard',
			internal_owner varchar(191) NOT NULL,
			technical_owner varchar(191) DEFAULT NULL,
			tags varchar(500) DEFAULT NULL,
			registrar_id bigint(20) unsigned DEFAULT NULL,
			registrar_account_reference varchar(191) DEFAULT NULL,
			registrar_management_url varchar(500) DEFAULT NULL,
			registered_on date DEFAULT NULL,
			expires_on date NOT NULL,
			last_renewed_on date DEFAULT NULL,
			auto_renew_status varchar(16) NOT NULL DEFAULT 'unknown',
			active_card varchar(4) DEFAULT NULL,
			dns_provider_id bigint(20) unsigned DEFAULT NULL,
			nameservers text,
			hosting_provider_id bigint(20) unsigned DEFAULT NULL,
			email_provider_id bigint(20) unsigned DEFAULT NULL,
			used_for_email tinyint(1) NOT NULL DEFAULT 0,
			primary_url varchar(500) DEFAULT NULL,
			expected_redirect_url varchar(500) DEFAULT NULL,
			redirect_type varchar(16) DEFAULT NULL,
			last_manually_verified_on date DEFAULT NULL,
			next_review_due_on date DEFAULT NULL,
			archived_at datetime DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			updated_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY domain_name_normalized (domain_name_normalized),
			KEY portfolio_status (portfolio_status),
			KEY usage_type (usage_type),
			KEY expires_on (expires_on),
			KEY registrar_id (registrar_id),
			KEY internal_owner (internal_owner),
			KEY next_review_due_on (next_review_due_on),
			KEY archived_at (archived_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$renewals} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			domain_id bigint(20) unsigned NOT NULL,
			renewed_on date NOT NULL,
			previous_expires_on date DEFAULT NULL,
			new_expires_on date NOT NULL,
			cost decimal(12,2) DEFAULT NULL,
			currency char(3) NOT NULL DEFAULT 'CAD',
			invoice_attachment_id bigint(20) unsigned DEFAULT NULL,
			vendor_invoice_number varchar(191) DEFAULT NULL,
			notes text,
			recorded_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY domain_id (domain_id),
			KEY renewed_on (renewed_on)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$documents} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			domain_id bigint(20) unsigned NOT NULL,
			attachment_id bigint(20) unsigned NOT NULL,
			title varchar(191) NOT NULL,
			doc_type varchar(32) NOT NULL DEFAULT 'other',
			uploaded_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY domain_id (domain_id),
			KEY attachment_id (attachment_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$notes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			domain_id bigint(20) unsigned NOT NULL,
			note_body text NOT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY domain_id (domain_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$dns_records} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			domain_id bigint(20) unsigned NOT NULL,
			provider_id bigint(20) unsigned DEFAULT NULL,
			record_type varchar(16) NOT NULL DEFAULT 'A',
			name varchar(253) NOT NULL DEFAULT '@',
			priority int(11) DEFAULT NULL,
			content text NOT NULL,
			ttl int(11) DEFAULT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY domain_id (domain_id),
			KEY provider_id (provider_id),
			KEY record_type (record_type)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$alerts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			domain_id bigint(20) unsigned NOT NULL,
			alert_type varchar(64) NOT NULL,
			threshold_days int(11) DEFAULT NULL,
			target_date date DEFAULT NULL,
			status varchar(32) NOT NULL DEFAULT 'pending',
			dedupe_key varchar(191) NOT NULL,
			emailed_at datetime DEFAULT NULL,
			acknowledged_at datetime DEFAULT NULL,
			acknowledged_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY dedupe_key (dedupe_key),
			KEY domain_id (domain_id),
			KEY status (status),
			KEY alert_type (alert_type)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$audit_log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			domain_id bigint(20) unsigned DEFAULT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			action varchar(64) NOT NULL,
			field_name varchar(64) DEFAULT NULL,
			old_value longtext,
			new_value longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY domain_id (domain_id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'rindoma_db_version', RINDOMA_DB_VERSION, false );
	}

	/**
	 * Install/upgrade schema when the stored DB version is behind.
	 */
	public static function maybe_upgrade(): void {
		global $wpdb;

		self::migrate_legacy_prefix();

		$legacy_domains = $wpdb->prefix . self::LEGACY . '_domains';
		if ( self::table_exists_named( $legacy_domains ) ) {
			return;
		}

		$installed = get_option( 'rindoma_db_version', '' );
		if ( RINDOMA_DB_VERSION !== $installed || ! self::tables_exist() ) {
			self::install();
		}
	}

	/**
	 * Copy tables, capabilities, cron, post meta, and options from the legacy 2-character prefix.
	 *
	 * Idempotent. Sets rindoma_legacy_migrated when finished so later requests skip this work.
	 */
	public static function migrate_legacy_prefix(): void {
		global $wpdb;

		if ( get_option( 'rindoma_legacy_migrated' ) ) {
			return;
		}

		$has_old_options = false !== get_option( self::LEGACY . '_settings', false )
			|| false !== get_option( self::LEGACY . '_db_version', false )
			|| false !== get_option( self::LEGACY . '_plugin_admin_user_id', false );

		$has_old_tables = self::table_exists_named( $wpdb->prefix . self::LEGACY . '_domains' );

		if ( ! $has_old_options && ! $has_old_tables ) {
			add_option( 'rindoma_legacy_migrated', 1, '', false );
			return;
		}

		foreach ( self::table_keys() as $key ) {
			$old_table = $wpdb->prefix . self::LEGACY . '_' . $key;
			$new_table = self::table( $key );

			if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $old_table ) || ! preg_match( '/^[A-Za-z0-9_]+$/', $new_table ) ) {
				continue;
			}

			if ( self::table_exists_named( $old_table ) && ! self::table_exists_named( $new_table ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-time table rename; names are validated.
				$wpdb->query( "RENAME TABLE `{$old_table}` TO `{$new_table}`" );
			}
		}

		$cap_map = array(
			self::LEGACY . '_view_domains'   => 'rindoma_view_domains',
			self::LEGACY . '_edit_domains'   => 'rindoma_edit_domains',
			self::LEGACY . '_manage_domains' => 'rindoma_manage_domains',
			self::LEGACY . '_admin_plugin'   => 'rindoma_admin_plugin',
		);

		$paged = 1;
		do {
			$users = get_users(
				array(
					'number' => 200,
					'paged'  => $paged,
					'fields' => 'all',
				)
			);

			if ( ! is_array( $users ) || ! $users ) {
				break;
			}

			foreach ( $users as $user ) {
				if ( ! $user instanceof \WP_User ) {
					continue;
				}

				foreach ( $cap_map as $old_cap => $new_cap ) {
					if ( ! empty( $user->caps[ $old_cap ] ) ) {
						$user->add_cap( $new_cap );
						$user->remove_cap( $old_cap );
					}
				}
			}

			$paged++;
		} while ( 200 === count( $users ) );

		wp_unschedule_hook( self::LEGACY . '_daily_alert_check' );

		$meta_map = array(
			'_' . self::LEGACY . '_private'   => '_rindoma_private',
			'_' . self::LEGACY . '_domain_id' => '_rindoma_domain_id',
		);

		foreach ( $meta_map as $old_meta => $new_meta ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time post meta key rename.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
					$new_meta,
					$old_meta
				)
			);
		}

		$option_suffixes = array(
			'settings',
			'db_version',
			'missing_admin_user',
			'plugin_admin_user_id',
		);

		foreach ( $option_suffixes as $suffix ) {
			$old_key = self::LEGACY . '_' . $suffix;
			$new_key = 'rindoma_' . $suffix;
			$old_val = get_option( $old_key, false );

			if ( false !== $old_val && false === get_option( $new_key, false ) ) {
				add_option( $new_key, $old_val, '', false );
			}

			delete_option( $old_key );
		}

		update_option( 'rindoma_legacy_migrated', 1, false );
	}

	/**
	 * Portfolio status options.
	 *
	 * @return array<string,string>
	 */
	public static function portfolio_statuses(): array {
		return array(
			'active'          => __( 'Active', '3ring-domain-manager' ),
			'parked'          => __( 'Parked', '3ring-domain-manager' ),
			'redirecting'     => __( 'Redirecting', '3ring-domain-manager' ),
			'reserved'        => __( 'Reserved / defensive', '3ring-domain-manager' ),
			'planned'         => __( 'Planned', '3ring-domain-manager' ),
			'for_sale'        => __( 'For sale', '3ring-domain-manager' ),
			'retiring'        => __( 'Retiring', '3ring-domain-manager' ),
			'expired'         => __( 'Expired', '3ring-domain-manager' ),
			'transferred_out' => __( 'Transferred out', '3ring-domain-manager' ),
			'archived'        => __( 'Archived', '3ring-domain-manager' ),
		);
	}

	/**
	 * Usage type options.
	 *
	 * @return array<string,string>
	 */
	public static function usage_types(): array {
		return array(
			'live_website'      => __( 'Live website', '3ring-domain-manager' ),
			'redirect'          => __( 'Redirect', '3ring-domain-manager' ),
			'parked_page'       => __( 'Parked page', '3ring-domain-manager' ),
			'email_only'        => __( 'Email only', '3ring-domain-manager' ),
			'no_active_service' => __( 'No active service', '3ring-domain-manager' ),
			'unknown'           => __( 'Unknown', '3ring-domain-manager' ),
		);
	}

	/**
	 * Business importance options.
	 *
	 * @return array<string,string>
	 */
	public static function importance_levels(): array {
		return array(
			'critical' => __( 'Critical', '3ring-domain-manager' ),
			'high'     => __( 'High', '3ring-domain-manager' ),
			'standard' => __( 'Standard', '3ring-domain-manager' ),
			'low'      => __( 'Low', '3ring-domain-manager' ),
		);
	}

	/**
	 * Auto-renew options.
	 *
	 * @return array<string,string>
	 */
	public static function auto_renew_statuses(): array {
		return array(
			'on'      => __( 'On', '3ring-domain-manager' ),
			'off'     => __( 'Off', '3ring-domain-manager' ),
			'unknown' => __( 'Unknown', '3ring-domain-manager' ),
		);
	}

	/**
	 * Provider type options.
	 *
	 * @return array<string,string>
	 */
	public static function provider_types(): array {
		return array(
			'registrar' => __( 'Registrar', '3ring-domain-manager' ),
			'dns'       => __( 'DNS', '3ring-domain-manager' ),
			'hosting'   => __( 'Hosting', '3ring-domain-manager' ),
			'email'     => __( 'Email', '3ring-domain-manager' ),
		);
	}

	/**
	 * DNS record type options.
	 *
	 * @return array<string,string>
	 */
	public static function dns_record_types(): array {
		return array(
			'A'     => 'A',
			'AAAA'  => 'AAAA',
			'ALIAS' => 'ALIAS',
			'CNAME' => 'CNAME',
			'MX'    => 'MX',
			'TXT'   => 'TXT',
			'NS'    => 'NS',
			'SRV'   => 'SRV',
			'CAA'   => 'CAA',
			'PTR'   => 'PTR',
		);
	}
}
