<?php
/**
 * Alerts repository.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Db;

defined( 'ABSPATH' ) || exit;

/**
 * Class Alerts_Repository
 */
final class Alerts_Repository {

	/**
	 * Table name.
	 */
	private function table(): string {
		return Schema::table( 'alerts' );
	}

	/**
	 * Find by dedupe key.
	 *
	 * @param string $key Dedupe key.
	 * @return object|null
	 */
	public function get_by_dedupe_key( string $key ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE dedupe_key = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$key
			)
		);

		return $row ?: null;
	}

	/**
	 * Insert alert if dedupe key is new.
	 *
	 * @param array<string,mixed> $data Alert data.
	 * @return int|false Existing or new ID, false on failure.
	 */
	public function create_if_new( array $data ) {
		$existing = $this->get_by_dedupe_key( $data['dedupe_key'] );
		if ( $existing ) {
			return (int) $existing->id;
		}

		global $wpdb;

		$result = $wpdb->insert(
			$this->table(),
			array(
				'domain_id'      => (int) $data['domain_id'],
				'alert_type'     => $data['alert_type'],
				'threshold_days' => $data['threshold_days'] ?? null,
				'target_date'    => $data['target_date'] ?? null,
				'status'         => $data['status'] ?? 'pending',
				'dedupe_key'     => $data['dedupe_key'],
			)
		);

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Mark alert emailed.
	 *
	 * @param int $id Alert ID.
	 */
	public function mark_emailed( int $id ): void {
		global $wpdb;

		$wpdb->update(
			$this->table(),
			array(
				'emailed_at' => current_time( 'mysql' ),
				'status'     => 'emailed',
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Acknowledge alert.
	 *
	 * @param int $id Alert ID.
	 */
	public function acknowledge( int $id ): void {
		global $wpdb;

		$wpdb->update(
			$this->table(),
			array(
				'acknowledged_at' => current_time( 'mysql' ),
				'acknowledged_by' => get_current_user_id() ?: null,
				'status'          => 'acknowledged',
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Recent alerts for dashboard.
	 *
	 * @param int $limit Limit.
	 * @return object[]
	 */
	public function recent( int $limit = 20 ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT a.*, d.domain_name FROM ' . $this->table() . ' a LEFT JOIN ' . Schema::table( 'domains' ) . ' d ON d.id = a.domain_id ORDER BY a.created_at DESC LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$limit
			)
		);

		return is_array( $results ) ? $results : array();
	}
}
