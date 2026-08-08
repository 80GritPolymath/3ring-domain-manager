<?php
/**
 * Documents repository.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Db;

defined( 'ABSPATH' ) || exit;

/**
 * Class Documents_Repository
 */
final class Documents_Repository {

	/**
	 * Table name.
	 */
	private function table(): string {
		return Schema::table( 'documents' );
	}

	/**
	 * Get document by ID.
	 *
	 * @param int $id Document ID.
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
	 * List documents for a domain.
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
	 * Insert document row.
	 *
	 * @param array<string,mixed> $data Document data.
	 * @return int|false
	 */
	public function insert( array $data ) {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table(),
			array(
				'domain_id'     => (int) $data['domain_id'],
				'attachment_id' => (int) $data['attachment_id'],
				'title'         => $data['title'],
				'doc_type'      => $data['doc_type'] ?? 'other',
				'uploaded_by'   => get_current_user_id() ?: null,
			)
		);

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Delete document row.
	 *
	 * @param int $id Document ID.
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );

		return false !== $result;
	}
}
