<?php
/**
 * Notes repository.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Db;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom tables; names come from Schema::table(), values use $wpdb->prepare().

/**
 * Class Notes_Repository
 */
final class Notes_Repository {

	/**
	 * Table name.
	 */
	private function table(): string {
		return Schema::table( 'notes' );
	}

	/**
	 * List notes for a domain.
	 *
	 * @param int $domain_id Domain ID.
	 * @return object[]
	 */
	public function list_for_domain( int $domain_id ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE domain_id = %d ORDER BY created_at DESC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$domain_id
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Insert note.
	 *
	 * @param int    $domain_id Domain ID.
	 * @param string $body      Note body.
	 * @return int|false
	 */
	public function insert( int $domain_id, string $body ) {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table(),
			array(
				'domain_id'   => $domain_id,
				'note_body'   => $body,
				'created_by'  => get_current_user_id() ?: null,
			)
		);

		return false === $result ? false : (int) $wpdb->insert_id;
	}
}
