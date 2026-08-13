<?php
/**
 * Domains repository.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Db;

use ThreeRing\DomainManager\Services\Domain_Normalizer;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom tables; names come from Schema::table(), values use $wpdb->prepare().

/**
 * Class Domains_Repository
 */
final class Domains_Repository {

	/**
	 * Table name.
	 */
	private function table(): string {
		return Schema::table( 'domains' );
	}

	/**
	 * Get domain by ID.
	 *
	 * @param int $id Domain ID.
	 * @return object|null
	 */
	public function get( int $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE id = %d', $id ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return $row ?: null;
	}

	/**
	 * Get by normalized domain name.
	 *
	 * @param string $normalized Normalized domain.
	 * @return object|null
	 */
	public function get_by_normalized( string $normalized ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE domain_name_normalized = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$normalized
			)
		);

		return $row ?: null;
	}

	/**
	 * Query domains with filters.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array{items:object[],total:int}
	 */
	public function query( array $args = array() ): array {
		global $wpdb;

		$table   = $this->table();
		$where   = array( '1=1' );
		$params  = array();
		$show_archived = ! empty( $args['show_archived'] );

		if ( ! $show_archived ) {
			$where[] = 'archived_at IS NULL';
		} elseif ( 'only' === ( $args['archived_filter'] ?? '' ) ) {
			$where[] = 'archived_at IS NOT NULL';
		}

		if ( ! empty( $args['search'] ) ) {
			$like      = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]   = '(domain_name LIKE %s OR display_name LIKE %s OR internal_owner LIKE %s OR tags LIKE %s OR business_purpose LIKE %s)';
			$params[]  = $like;
			$params[]  = $like;
			$params[]  = $like;
			$params[]  = $like;
			$params[]  = $like;
		}

		if ( ! empty( $args['portfolio_status'] ) ) {
			$where[]  = 'portfolio_status = %s';
			$params[] = $args['portfolio_status'];
		}

		if ( ! empty( $args['usage_type'] ) ) {
			$where[]  = 'usage_type = %s';
			$params[] = $args['usage_type'];
		}

		if ( ! empty( $args['registrar_id'] ) ) {
			$where[]  = 'registrar_id = %d';
			$params[] = (int) $args['registrar_id'];
		}

		if ( ! empty( $args['internal_owner'] ) ) {
			$where[]  = 'internal_owner = %s';
			$params[] = $args['internal_owner'];
		}

		if ( ! empty( $args['expiry_within_days'] ) ) {
			[ $clause, $clause_params ] = $this->expiry_within_days_clause( (int) $args['expiry_within_days'] );
			$where[]                    = $clause . ' AND archived_at IS NULL';
			foreach ( $clause_params as $param ) {
				$params[] = $param;
			}
		}

		if ( ! empty( $args['review_due'] ) ) {
			$where[] = 'next_review_due_on IS NOT NULL AND next_review_due_on <= CURDATE() AND archived_at IS NULL';
		}

		$requested_orderby = sanitize_key( (string) ( $args['orderby'] ?? 'domain_name' ) );
		$orderby_map       = array(
			'domain_name'      => 'domain_name',
			'expires_on'       => 'expires_on',
			'portfolio_status' => 'portfolio_status',
			'usage_type'       => 'usage_type',
			'auto_renew'       => 'auto_renew_status',
			'internal_owner'   => 'internal_owner',
			'active_card'      => 'active_card',
			'updated_at'       => 'updated_at',
			'created_at'       => 'created_at',
		);

		if ( 'registrar' === $requested_orderby ) {
			$providers = Schema::table( 'providers' );
			$orderby   = "(SELECT name FROM {$providers} WHERE id = {$table}.registrar_id)";
		} else {
			$orderby = $orderby_map[ $requested_orderby ] ?? 'domain_name';
		}

		$order    = ( isset( $args['order'] ) && 'DESC' === strtoupper( (string) $args['order'] ) ) ? 'DESC' : 'ASC';
		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$list_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * List all non-archived domains (for alerts/dashboard).
	 *
	 * @return object[]
	 */
	public function list_active(): array {
		global $wpdb;

		$table   = $this->table();
		$results = $wpdb->get_results( "SELECT * FROM {$table} WHERE archived_at IS NULL ORDER BY expires_on ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Dashboard counts.
	 *
	 * @return array<string,int|array>
	 */
	public function dashboard_stats(): array {
		global $wpdb;

		$table = $this->table();

		$expiring = array();
		foreach ( array( 30, 60, 90 ) as $days ) {
			[ $clause, $clause_params ] = $this->expiry_within_days_clause( $days );
			$expiring[ $days ]         = (int) $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE archived_at IS NULL AND {$clause}",
					$clause_params
				)
			);
		}

		$review_due = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE archived_at IS NULL AND next_review_due_on IS NOT NULL AND next_review_due_on <= CURDATE()" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$missing = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE archived_at IS NULL AND (registrar_id IS NULL OR expires_on IS NULL OR internal_owner = '' OR internal_owner IS NULL)" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE archived_at IS NULL" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$by_status = $wpdb->get_results(
			"SELECT portfolio_status AS label, COUNT(*) AS total FROM {$table} WHERE archived_at IS NULL GROUP BY portfolio_status ORDER BY total DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$by_usage = $wpdb->get_results(
			"SELECT usage_type AS label, COUNT(*) AS total FROM {$table} WHERE archived_at IS NULL GROUP BY usage_type ORDER BY total DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$providers_table = Schema::table( 'providers' );
		$by_registrar    = $wpdb->get_results(
			"SELECT COALESCE(p.name, 'Unassigned') AS label, COUNT(*) AS total
			 FROM {$table} d
			 LEFT JOIN {$providers_table} p ON p.id = d.registrar_id
			 WHERE d.archived_at IS NULL
			 GROUP BY d.registrar_id
			 ORDER BY total DESC",
			ARRAY_A
		);

		$recent = $wpdb->get_results(
			"SELECT id, domain_name, updated_at FROM {$table} ORDER BY updated_at DESC LIMIT 8"
		);

		return array(
			'expiring'     => $expiring,
			'review_due'   => $review_due,
			'missing'      => $missing,
			'total'        => $total,
			'by_status'    => is_array( $by_status ) ? $by_status : array(),
			'by_usage'     => is_array( $by_usage ) ? $by_usage : array(),
			'by_registrar' => is_array( $by_registrar ) ? $by_registrar : array(),
			'recent'       => is_array( $recent ) ? $recent : array(),
		);
	}

	/**
	 * Prepare row data for insert/update.
	 *
	 * @param array<string,mixed> $data Input data.
	 * @return array<string,mixed>
	 */
	public function prepare_row( array $data ): array {
		$normalized = Domain_Normalizer::normalize( (string) ( $data['domain_name'] ?? '' ) );

		$review_days = 180;
		$settings    = get_option( 'rindoma_settings', array() );
		if ( ! empty( $settings['review_interval_days'] ) ) {
			$review_days = (int) $settings['review_interval_days'];
		}

		$verified = $data['last_manually_verified_on'] ?? null;
		$next_review = $data['next_review_due_on'] ?? null;
		if ( $verified && empty( $next_review ) ) {
			$next_review = gmdate( 'Y-m-d', strtotime( $verified . ' +' . $review_days . ' days' ) );
		}

		return array(
			'domain_name'                 => sanitize_text_field( (string) ( $data['domain_name'] ?? '' ) ),
			'domain_name_normalized'      => $normalized,
			'display_name'                => $this->null_string( $data['display_name'] ?? null ),
			'portfolio_status'            => sanitize_key( (string) ( $data['portfolio_status'] ?? 'active' ) ),
			'usage_type'                  => sanitize_key( (string) ( $data['usage_type'] ?? 'unknown' ) ),
			'business_purpose'            => isset( $data['business_purpose'] ) ? sanitize_textarea_field( (string) $data['business_purpose'] ) : null,
			'business_importance'         => sanitize_key( (string) ( $data['business_importance'] ?? 'standard' ) ),
			'internal_owner'              => sanitize_text_field( (string) ( $data['internal_owner'] ?? '' ) ),
			'technical_owner'             => $this->null_string( $data['technical_owner'] ?? null ),
			'tags'                        => $this->null_string( $data['tags'] ?? null ),
			'registrar_id'                => $this->null_int( $data['registrar_id'] ?? null ),
			'registrar_account_reference' => $this->null_string( $data['registrar_account_reference'] ?? null ),
			'registrar_management_url'    => $this->null_url( $data['registrar_management_url'] ?? null ),
			'registered_on'               => $this->null_date( $data['registered_on'] ?? null ),
			'expires_on'                  => $this->null_date( $data['expires_on'] ?? null ),
			'last_renewed_on'             => $this->null_date( $data['last_renewed_on'] ?? null ),
			'auto_renew_status'           => sanitize_key( (string) ( $data['auto_renew_status'] ?? 'unknown' ) ),
			'active_card'                 => $this->null_card( $data['active_card'] ?? null ),
			'dns_provider_id'             => $this->null_int( $data['dns_provider_id'] ?? null ),
			'nameservers'                 => isset( $data['nameservers'] ) ? sanitize_textarea_field( (string) $data['nameservers'] ) : null,
			'hosting_provider_id'         => $this->null_int( $data['hosting_provider_id'] ?? null ),
			'email_provider_id'           => $this->null_int( $data['email_provider_id'] ?? null ),
			'used_for_email'              => ! empty( $data['used_for_email'] ) ? 1 : 0,
			'primary_url'                 => $this->null_url( $data['primary_url'] ?? null ),
			'expected_redirect_url'       => $this->null_url( $data['expected_redirect_url'] ?? null ),
			'redirect_type'               => $this->null_string( $data['redirect_type'] ?? null ),
			'last_manually_verified_on'   => $this->null_date( $verified ),
			'next_review_due_on'          => $this->null_date( $next_review ),
			'updated_by'                  => get_current_user_id() ?: null,
		);
	}

	/**
	 * Insert domain.
	 *
	 * @param array<string,mixed> $data Domain data.
	 * @return int|\WP_Error
	 */
	public function insert( array $data ) {
		global $wpdb;

		$row = $this->prepare_row( $data );
		$errors = $this->validate( $row );
		if ( is_wp_error( $errors ) ) {
			return $errors;
		}

		if ( $this->get_by_normalized( $row['domain_name_normalized'] ) ) {
			return new \WP_Error( 'rindoma_duplicate', __( 'A domain with this name already exists.', '3ring-domain-manager' ) );
		}

		$row['created_by'] = get_current_user_id() ?: null;

		$result = $wpdb->insert( $this->table(), $row );
		if ( false === $result ) {
			return new \WP_Error( 'rindoma_db', __( 'Could not save domain.', '3ring-domain-manager' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update domain.
	 *
	 * @param int                 $id   Domain ID.
	 * @param array<string,mixed> $data Domain data.
	 * @return true|\WP_Error
	 */
	public function update( int $id, array $data ) {
		global $wpdb;

		$existing = $this->get( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'rindoma_missing', __( 'Domain not found.', '3ring-domain-manager' ) );
		}

		$row = $this->prepare_row( $data );
		$errors = $this->validate( $row );
		if ( is_wp_error( $errors ) ) {
			return $errors;
		}

		$dup = $this->get_by_normalized( $row['domain_name_normalized'] );
		if ( $dup && (int) $dup->id !== $id ) {
			return new \WP_Error( 'rindoma_duplicate', __( 'A domain with this name already exists.', '3ring-domain-manager' ) );
		}

		$result = $wpdb->update( $this->table(), $row, array( 'id' => $id ) );
		if ( false === $result ) {
			return new \WP_Error( 'rindoma_db', __( 'Could not update domain.', '3ring-domain-manager' ) );
		}

		return true;
	}

	/**
	 * Mark reviewed today.
	 *
	 * @param int $id Domain ID.
	 */
	public function mark_reviewed( int $id ): bool {
		$settings    = get_option( 'rindoma_settings', array() );
		$review_days = ! empty( $settings['review_interval_days'] ) ? (int) $settings['review_interval_days'] : 180;
		$today       = current_time( 'Y-m-d' );
		$next        = gmdate( 'Y-m-d', strtotime( $today . ' +' . $review_days . ' days' ) );

		global $wpdb;
		$result = $wpdb->update(
			$this->table(),
			array(
				'last_manually_verified_on' => $today,
				'next_review_due_on'        => $next,
				'updated_by'                => get_current_user_id() ?: null,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Archive domain.
	 *
	 * @param int $id Domain ID.
	 */
	public function archive( int $id ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->table(),
			array(
				'archived_at'      => current_time( 'mysql' ),
				'portfolio_status' => 'archived',
				'updated_by'       => get_current_user_id() ?: null,
			),
			array( 'id' => $id )
		);

		return false !== $result;
	}

	/**
	 * Restore archived domain.
	 *
	 * @param int $id Domain ID.
	 */
	public function restore( int $id ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->table(),
			array(
				'archived_at'      => null,
				'portfolio_status' => 'active',
				'updated_by'       => get_current_user_id() ?: null,
			),
			array( 'id' => $id )
		);

		return false !== $result;
	}

	/**
	 * Permanently delete domain.
	 *
	 * @param int $id Domain ID.
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		( new Dns_Records_Repository() )->delete_for_domain( $id );

		$result = $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Validate required fields.
	 *
	 * @param array<string,mixed> $row Prepared row.
	 * @return true|\WP_Error
	 */
	private function validate( array $row ) {
		if ( empty( $row['domain_name'] ) || empty( $row['domain_name_normalized'] ) ) {
			return new \WP_Error( 'rindoma_required', __( 'Domain name is required.', '3ring-domain-manager' ) );
		}
		if ( empty( $row['portfolio_status'] ) ) {
			return new \WP_Error( 'rindoma_required', __( 'Portfolio status is required.', '3ring-domain-manager' ) );
		}
		if ( empty( $row['usage_type'] ) ) {
			return new \WP_Error( 'rindoma_required', __( 'Usage type is required.', '3ring-domain-manager' ) );
		}
		if ( empty( $row['registrar_id'] ) ) {
			return new \WP_Error( 'rindoma_required', __( 'Registrar is required.', '3ring-domain-manager' ) );
		}
		if ( empty( $row['expires_on'] ) ) {
			return new \WP_Error( 'rindoma_required', __( 'Expiry date is required.', '3ring-domain-manager' ) );
		}
		if ( empty( $row['internal_owner'] ) ) {
			return new \WP_Error( 'rindoma_required', __( 'Internal owner is required.', '3ring-domain-manager' ) );
		}

		return true;
	}

	/**
	 * @param mixed $value Value.
	 */
	private function null_string( $value ): ?string {
		$value = is_string( $value ) ? trim( $value ) : '';
		return '' === $value ? null : sanitize_text_field( $value );
	}

	/**
	 * @param mixed $value Value.
	 */
	private function null_url( $value ): ?string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return null;
		}
		return esc_url_raw( $value );
	}

	/**
	 * @param mixed $value Value.
	 */
	private function null_int( $value ): ?int {
		if ( null === $value || '' === $value ) {
			return null;
		}
		return (int) $value;
	}

	/**
	 * @param mixed $value Value.
	 */
	private function null_date( $value ): ?string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return null;
		}
		$ts = strtotime( $value );
		return $ts ? gmdate( 'Y-m-d', $ts ) : null;
	}

	/**
	 * Exclusive expiry-window SQL fragment keyed by upper bound (30 / 60 / 90).
	 *
	 * A domain belongs only to the nearest window:
	 * 0–30, 31–60, or 61–90 days from today.
	 *
	 * @param int $days Upper-bound window in days.
	 * @return array{0: string, 1: int[]} SQL clause with %d placeholders, and bind values.
	 */
	private function expiry_within_days_clause( int $days ): array {
		$windows = array( 30, 60, 90 );
		$prev    = 0;

		foreach ( $windows as $window ) {
			if ( $window >= $days ) {
				break;
			}
			$prev = $window;
		}

		if ( $prev > 0 ) {
			return array(
				'expires_on IS NOT NULL AND expires_on > DATE_ADD(CURDATE(), INTERVAL %d DAY) AND expires_on <= DATE_ADD(CURDATE(), INTERVAL %d DAY)',
				array( $prev, $days ),
			);
		}

		return array(
			'expires_on IS NOT NULL AND expires_on >= CURDATE() AND expires_on <= DATE_ADD(CURDATE(), INTERVAL %d DAY)',
			array( $days ),
		);
	}

	/**
	 * Store last 4 digits of card on file (reference only).
	 *
	 * @param mixed $value Value.
	 */
	private function null_card( $value ): ?string {
		$value = is_string( $value ) ? preg_replace( '/\D+/', '', $value ) : '';
		if ( '' === $value ) {
			return null;
		}
		// Keep only the last 4 digits if more were entered.
		$value = substr( $value, -4 );
		return 4 === strlen( $value ) ? $value : null;
	}

	/**
	 * Public visit URL for a domain (https).
	 *
	 * @param object $domain Domain row.
	 */
	public static function visit_url( $domain ): string {
		if ( ! empty( $domain->primary_url ) ) {
			$url = (string) $domain->primary_url;
			if ( 0 === stripos( $url, 'http://' ) || 0 === stripos( $url, 'https://' ) ) {
				return set_url_scheme( $url, 'https' );
			}
			return 'https://' . ltrim( $url, '/' );
		}

		$name = ! empty( $domain->domain_name ) ? (string) $domain->domain_name : '';
		if ( '' === $name ) {
			return '';
		}

		return 'https://' . Domain_Normalizer::normalize( $name );
	}
}
