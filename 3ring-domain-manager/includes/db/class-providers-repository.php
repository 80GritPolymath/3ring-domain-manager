<?php
/**
 * Providers repository.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Db;

defined( 'ABSPATH' ) || exit;

/**
 * Class Providers_Repository
 */
final class Providers_Repository {

	/**
	 * Table name.
	 */
	private function table(): string {
		return Schema::table( 'providers' );
	}

	/**
	 * Get provider by ID.
	 *
	 * @param int $id Provider ID.
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
	 * List providers, optionally filtered by type (supports multi-type providers).
	 *
	 * @param string|null $type Provider type key.
	 * @return object[]
	 */
	public function list_all( ?string $type = null ): array {
		global $wpdb;

		$table = $this->table();

		if ( $type ) {
			$type = sanitize_key( $type );
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE FIND_IN_SET(%s, provider_type) ORDER BY name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$type
				)
			);
		} else {
			$results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Insert provider.
	 *
	 * @param array<string,mixed> $data Provider data.
	 * @return int|false
	 */
	public function insert( array $data ) {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table(),
			array(
				'name'           => $data['name'],
				'provider_type'  => self::normalize_types( $data['provider_type'] ?? array() ),
				'account_id'     => $this->null_string( $data['account_id'] ?? null ),
				'account_email'  => $this->null_email( $data['account_email'] ?? null ),
				'website_url'    => $data['website_url'] ?? null,
				'management_url' => $data['management_url'] ?? null,
				'notes'          => $data['notes'] ?? null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Update provider.
	 *
	 * @param int                 $id   Provider ID.
	 * @param array<string,mixed> $data Provider data.
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->table(),
			array(
				'name'           => $data['name'],
				'provider_type'  => self::normalize_types( $data['provider_type'] ?? array() ),
				'account_id'     => $this->null_string( $data['account_id'] ?? null ),
				'account_email'  => $this->null_email( $data['account_email'] ?? null ),
				'website_url'    => $data['website_url'] ?? null,
				'management_url' => $data['management_url'] ?? null,
				'notes'          => $data['notes'] ?? null,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete provider.
	 *
	 * @param int $id Provider ID.
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Parse stored provider_type CSV into an array of keys.
	 *
	 * @param string|null $stored Stored value.
	 * @return string[]
	 */
	public static function parse_types( $stored ): array {
		if ( ! is_string( $stored ) || '' === trim( $stored ) ) {
			return array();
		}

		$allowed = array_keys( Schema::provider_types() );
		$parts   = array_map( 'sanitize_key', explode( ',', $stored ) );
		$parts   = array_values( array_intersect( $allowed, array_filter( $parts ) ) );

		return $parts;
	}

	/**
	 * Normalize type input (array or CSV string) to a sorted CSV string.
	 *
	 * @param mixed $types Types.
	 */
	public static function normalize_types( $types ): string {
		$allowed = array_keys( Schema::provider_types() );

		if ( is_string( $types ) ) {
			$types = explode( ',', $types );
		}

		if ( ! is_array( $types ) ) {
			$types = array();
		}

		$clean = array();
		foreach ( $types as $type ) {
			$key = sanitize_key( (string) $type );
			if ( in_array( $key, $allowed, true ) ) {
				$clean[] = $key;
			}
		}

		$clean = array_values( array_unique( $clean ) );
		sort( $clean );

		return $clean ? implode( ',', $clean ) : 'registrar';
	}

	/**
	 * Human-readable type labels for a provider row.
	 *
	 * @param object|string $provider Provider object or stored CSV.
	 */
	public static function format_type_labels( $provider ): string {
		$stored = is_object( $provider ) ? ( $provider->provider_type ?? '' ) : (string) $provider;
		$keys   = self::parse_types( $stored );
		$labels = Schema::provider_types();
		$out    = array();

		foreach ( $keys as $key ) {
			$out[] = $labels[ $key ] ?? $key;
		}

		return $out ? implode( ', ', $out ) : '—';
	}

	/**
	 * Normalize empty strings to null.
	 *
	 * @param mixed $value Value.
	 */
	private function null_string( $value ): ?string {
		$value = is_string( $value ) ? trim( $value ) : '';
		return '' === $value ? null : sanitize_text_field( $value );
	}

	/**
	 * Normalize empty email strings to null.
	 *
	 * @param mixed $value Value.
	 */
	private function null_email( $value ): ?string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return null;
		}
		$email = sanitize_email( $value );
		return '' === $email ? null : $email;
	}
}
