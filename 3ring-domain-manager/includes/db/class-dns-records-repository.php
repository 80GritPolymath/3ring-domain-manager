<?php
/**
 * DNS records repository.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Db;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom tables; names come from Schema::table(), values use $wpdb->prepare().

/**
 * Class Dns_Records_Repository
 */
final class Dns_Records_Repository {

	/**
	 * Table name.
	 */
	private function table(): string {
		return Schema::table( 'dns_records' );
	}

	/**
	 * List DNS records for a domain.
	 *
	 * @param int $domain_id Domain ID.
	 * @return object[]
	 */
	public function list_for_domain( int $domain_id ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE domain_id = %d ORDER BY sort_order ASC, id ASC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$domain_id
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Provider ID currently associated with a domain's DNS records (first non-null).
	 *
	 * @param int $domain_id Domain ID.
	 */
	public function provider_id_for_domain( int $domain_id ): ?int {
		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT provider_id FROM ' . $this->table() . ' WHERE domain_id = %d AND provider_id IS NOT NULL ORDER BY id ASC LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$domain_id
			)
		);

		return null !== $value ? (int) $value : null;
	}

	/**
	 * Replace all DNS records for a domain.
	 *
	 * @param int                  $domain_id   Domain ID.
	 * @param int|null             $provider_id DNS provider ID.
	 * @param array<int,array>     $records     Record rows.
	 * @return bool
	 */
	public function replace_for_domain( int $domain_id, ?int $provider_id, array $records ): bool {
		global $wpdb;

		$deleted = $wpdb->delete( $this->table(), array( 'domain_id' => $domain_id ), array( '%d' ) );
		if ( false === $deleted ) {
			return false;
		}

		$sort = 0;
		foreach ( $records as $record ) {
			$type    = isset( $record['record_type'] ) ? strtoupper( sanitize_key( (string) $record['record_type'] ) ) : '';
			$name    = isset( $record['name'] ) ? sanitize_text_field( (string) $record['name'] ) : '';
			$content = isset( $record['content'] ) ? sanitize_textarea_field( (string) $record['content'] ) : '';

			if ( '' === $type || '' === $name || '' === $content ) {
				continue;
			}

			if ( ! isset( Schema::dns_record_types()[ $type ] ) ) {
				continue;
			}

			$priority = null;
			if ( isset( $record['priority'] ) && '' !== (string) $record['priority'] && is_numeric( $record['priority'] ) ) {
				$priority = (int) $record['priority'];
			}

			$ttl = null;
			if ( isset( $record['ttl'] ) && '' !== (string) $record['ttl'] && is_numeric( $record['ttl'] ) ) {
				$ttl = max( 0, (int) $record['ttl'] );
			}

			$row = array(
				'domain_id'   => $domain_id,
				'record_type' => $type,
				'name'        => $name,
				'content'     => $content,
				'sort_order'  => $sort,
			);
			$formats = array( '%d', '%s', '%s', '%s', '%d' );

			if ( null !== $provider_id ) {
				$row['provider_id'] = $provider_id;
				$formats[]          = '%d';
			}

			if ( null !== $priority ) {
				$row['priority'] = $priority;
				$formats[]       = '%d';
			}

			if ( null !== $ttl ) {
				$row['ttl'] = $ttl;
				$formats[]  = '%d';
			}

			$result = $wpdb->insert( $this->table(), $row, $formats );

			if ( false === $result ) {
				return false;
			}

			++$sort;
		}

		return true;
	}

	/**
	 * Delete all DNS records for a domain.
	 *
	 * @param int $domain_id Domain ID.
	 */
	public function delete_for_domain( int $domain_id ): bool {
		global $wpdb;

		$result = $wpdb->delete( $this->table(), array( 'domain_id' => $domain_id ), array( '%d' ) );

		return false !== $result;
	}
}
