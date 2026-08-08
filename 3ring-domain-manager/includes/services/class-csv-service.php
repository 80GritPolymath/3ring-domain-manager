<?php
/**
 * CSV import / export.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Services;

use ThreeRing\DomainManager\Db\Domains_Repository;
use ThreeRing\DomainManager\Db\Providers_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Csv_Service
 */
final class Csv_Service {

	/**
	 * CSV columns.
	 *
	 * @return string[]
	 */
	public static function columns(): array {
		return array(
			'domain_name',
			'display_name',
			'portfolio_status',
			'usage_type',
			'business_purpose',
			'business_importance',
			'internal_owner',
			'technical_owner',
			'registrar',
			'registrar_account_reference',
			'registrar_management_url',
			'registered_on',
			'expires_on',
			'auto_renew_status',
			'last_renewed_on',
			'dns_provider',
			'hosting_provider',
			'email_provider',
			'nameservers',
			'used_for_email',
			'primary_url',
			'expected_redirect_url',
			'redirect_type',
			'tags',
			'last_manually_verified_on',
		);
	}

	/**
	 * Download an empty template.
	 */
	public function download_template(): void {
		$this->output_csv( '3ring-domains-template.csv', array( self::columns() ) );
	}

	/**
	 * Export domains.
	 *
	 * @param array<string,mixed> $filters Query filters.
	 */
	public function export( array $filters = array() ): void {
		$repo    = new Domains_Repository();
		$filters = array_merge(
			$filters,
			array(
				'per_page' => 10000,
				'page'     => 1,
			)
		);
		$result  = $repo->query( $filters );
		$providers = new Providers_Repository();
		$provider_map = array();
		foreach ( $providers->list_all() as $provider ) {
			$provider_map[ (int) $provider->id ] = $provider->name;
		}

		$rows   = array( self::columns() );
		foreach ( $result['items'] as $domain ) {
			$rows[] = array(
				$domain->domain_name,
				$domain->display_name,
				$domain->portfolio_status,
				$domain->usage_type,
				$domain->business_purpose,
				$domain->business_importance,
				$domain->internal_owner,
				$domain->technical_owner,
				$provider_map[ (int) $domain->registrar_id ] ?? '',
				$domain->registrar_account_reference,
				$domain->registrar_management_url,
				$domain->registered_on,
				$domain->expires_on,
				$domain->auto_renew_status,
				$domain->last_renewed_on,
				$provider_map[ (int) $domain->dns_provider_id ] ?? '',
				$provider_map[ (int) $domain->hosting_provider_id ] ?? '',
				$provider_map[ (int) $domain->email_provider_id ] ?? '',
				$domain->nameservers,
				$domain->used_for_email ? '1' : '0',
				$domain->primary_url,
				$domain->expected_redirect_url,
				$domain->redirect_type,
				$domain->tags,
				$domain->last_manually_verified_on,
			);
		}

		$this->output_csv( '3ring-domains-export-' . gmdate( 'Ymd-His' ) . '.csv', $rows );
	}

	/**
	 * Import CSV file path.
	 *
	 * @param string $path    File path.
	 * @param bool   $update  Update existing by domain name.
	 * @return array{created:int,updated:int,errors:string[]}
	 */
	public function import( string $path, bool $update = true ): array {
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return array(
				'created' => 0,
				'updated' => 0,
				'errors'  => array( __( 'Could not read CSV file.', '3ring-domain-manager' ) ),
			);
		}

		$header = fgetcsv( $handle );
		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return array(
				'created' => 0,
				'updated' => 0,
				'errors'  => array( __( 'CSV header row is missing.', '3ring-domain-manager' ) ),
			);
		}

		$header = array_map(
			static function ( $col ) {
				return strtolower( trim( (string) $col ) );
			},
			$header
		);

		$providers = new Providers_Repository();
		$name_to_id = array();
		foreach ( $providers->list_all() as $provider ) {
			$name_to_id[ strtolower( $provider->name ) ] = (int) $provider->id;
		}

		$repo    = new Domains_Repository();
		$audit   = new Audit_Service();
		$created = 0;
		$updated = 0;
		$errors  = array();
		$row_num = 1;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			++$row_num;
			if ( count( $row ) === 1 && '' === trim( (string) $row[0] ) ) {
				continue;
			}

			$data = array();
			foreach ( $header as $i => $col ) {
				$data[ $col ] = $row[ $i ] ?? '';
			}

			if ( empty( $data['domain_name'] ) ) {
				$errors[] = sprintf( /* translators: %d: row number */ __( 'Row %d: domain_name is required.', '3ring-domain-manager' ), $row_num );
				continue;
			}

			$payload = $data;
			if ( ! empty( $data['registrar'] ) ) {
				$key = strtolower( trim( (string) $data['registrar'] ) );
				if ( empty( $name_to_id[ $key ] ) ) {
					$errors[] = sprintf( /* translators: 1: row, 2: registrar */ __( 'Row %1$d: unknown registrar “%2$s”.', '3ring-domain-manager' ), $row_num, $data['registrar'] );
					continue;
				}
				$payload['registrar_id'] = $name_to_id[ $key ];
			}

			foreach ( array( 'dns_provider' => 'dns_provider_id', 'hosting_provider' => 'hosting_provider_id', 'email_provider' => 'email_provider_id' ) as $csv_key => $field ) {
				if ( ! empty( $data[ $csv_key ] ) ) {
					$key = strtolower( trim( (string) $data[ $csv_key ] ) );
					if ( empty( $name_to_id[ $key ] ) ) {
						$errors[] = sprintf( /* translators: 1: row, 2: provider */ __( 'Row %1$d: unknown provider “%2$s”.', '3ring-domain-manager' ), $row_num, $data[ $csv_key ] );
						continue 2;
					}
					$payload[ $field ] = $name_to_id[ $key ];
				}
			}

			$normalized = Domain_Normalizer::normalize( (string) $data['domain_name'] );
			$existing   = $repo->get_by_normalized( $normalized );

			if ( $existing ) {
				if ( ! $update ) {
					$errors[] = sprintf( /* translators: 1: row, 2: domain */ __( 'Row %1$d: domain “%2$s” already exists.', '3ring-domain-manager' ), $row_num, $data['domain_name'] );
					continue;
				}
				$before = $existing;
				$result = $repo->update( (int) $existing->id, $payload );
				if ( is_wp_error( $result ) ) {
					$errors[] = sprintf( 'Row %d: %s', $row_num, $result->get_error_message() );
					continue;
				}
				$audit->log_domain_changes( (int) $existing->id, $before, $repo->get( (int) $existing->id ), 'domain_updated_import' );
				++$updated;
			} else {
				$result = $repo->insert( $payload );
				if ( is_wp_error( $result ) ) {
					$errors[] = sprintf( 'Row %d: %s', $row_num, $result->get_error_message() );
					continue;
				}
				$audit->log( 'domain_created_import', (int) $result );
				++$created;
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return compact( 'created', 'updated', 'errors' );
	}

	/**
	 * Stream CSV to browser.
	 *
	 * @param string  $filename Filename.
	 * @param array[] $rows     Rows.
	 */
	private function output_csv( string $filename, array $rows ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		foreach ( $rows as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
