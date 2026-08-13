<?php
/**
 * Renewals repository.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Db;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom tables; names come from Schema::table(), values use $wpdb->prepare().

/**
 * Class Renewals_Repository
 */
final class Renewals_Repository {

	/**
	 * Table name.
	 */
	private function table(): string {
		return Schema::table( 'renewals' );
	}

	/**
	 * List renewals for a domain.
	 *
	 * @param int $domain_id Domain ID.
	 * @return object[]
	 */
	public function list_for_domain( int $domain_id ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE domain_id = %d ORDER BY renewed_on DESC, id DESC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$domain_id
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Insert renewal.
	 *
	 * @param array<string,mixed> $data Renewal data.
	 * @return int|false
	 */
	public function insert( array $data ) {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table(),
			array(
				'domain_id'              => (int) $data['domain_id'],
				'renewed_on'             => $data['renewed_on'],
				'previous_expires_on'    => $data['previous_expires_on'] ?? null,
				'new_expires_on'         => $data['new_expires_on'],
				'cost'                   => isset( $data['cost'] ) && '' !== $data['cost'] ? $data['cost'] : null,
				'currency'               => $data['currency'] ?? 'CAD',
				'invoice_attachment_id'  => ! empty( $data['invoice_attachment_id'] ) ? (int) $data['invoice_attachment_id'] : null,
				'vendor_invoice_number'  => $data['vendor_invoice_number'] ?? null,
				'notes'                  => $data['notes'] ?? null,
				'recorded_by'            => get_current_user_id() ?: null,
			)
		);

		return false === $result ? false : (int) $wpdb->insert_id;
	}
}
