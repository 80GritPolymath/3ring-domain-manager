<?php
/**
 * Audit log repository.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Db;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom tables; names come from Schema::table(), values use $wpdb->prepare().

/**
 * Class Audit_Repository
 */
final class Audit_Repository {

	/**
	 * Table name.
	 */
	private function table(): string {
		return Schema::table( 'audit_log' );
	}

	/**
	 * Insert audit row.
	 *
	 * @param array<string,mixed> $data Audit data.
	 * @return int|false
	 */
	public function insert( array $data ) {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table(),
			array(
				'domain_id'  => $data['domain_id'] ?? null,
				'user_id'    => $data['user_id'] ?? ( get_current_user_id() ?: null ),
				'action'     => $data['action'],
				'field_name' => $data['field_name'] ?? null,
				'old_value'  => $data['old_value'] ?? null,
				'new_value'  => $data['new_value'] ?? null,
			)
		);

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * List audit entries for a domain.
	 *
	 * @param int $domain_id Domain ID.
	 * @param int $limit     Limit.
	 * @return object[]
	 */
	public function list_for_domain( int $domain_id, int $limit = 50 ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE domain_id = %d ORDER BY created_at DESC, id DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$domain_id,
				$limit
			)
		);

		return is_array( $results ) ? $results : array();
	}
}
