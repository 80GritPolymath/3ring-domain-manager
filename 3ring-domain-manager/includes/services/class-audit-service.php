<?php
/**
 * Audit service.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Services;

use ThreeRing\DomainManager\Db\Audit_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Audit_Service
 */
final class Audit_Service {

	/**
	 * Audited domain fields.
	 *
	 * @var string[]
	 */
	private $fields = array(
		'domain_name',
		'portfolio_status',
		'usage_type',
		'business_importance',
		'internal_owner',
		'technical_owner',
		'registrar_id',
		'expires_on',
		'auto_renew_status',
		'dns_provider_id',
		'primary_url',
		'expected_redirect_url',
		'redirect_type',
		'active_card',
		'last_manually_verified_on',
		'next_review_due_on',
		'archived_at',
	);

	/**
	 * Log a simple action.
	 *
	 * @param string   $action    Action key.
	 * @param int|null $domain_id Domain ID.
	 * @param string|null $field  Field name.
	 * @param mixed    $old       Old value.
	 * @param mixed    $new       New value.
	 */
	public function log( string $action, ?int $domain_id = null, ?string $field = null, $old = null, $new = null ): void {
		( new Audit_Repository() )->insert(
			array(
				'domain_id'  => $domain_id,
				'action'     => $action,
				'field_name' => $field,
				'old_value'  => null === $old ? null : (string) $old,
				'new_value'  => null === $new ? null : (string) $new,
			)
		);
	}

	/**
	 * Diff and log changed fields between two domain objects/arrays.
	 *
	 * @param int                  $domain_id Domain ID.
	 * @param object|array         $before    Before state.
	 * @param object|array         $after     After state.
	 * @param string               $action    Action label.
	 */
	public function log_domain_changes( int $domain_id, $before, $after, string $action = 'domain_updated' ): void {
		$before_arr = (array) $before;
		$after_arr  = (array) $after;

		$changed = false;
		foreach ( $this->fields as $field ) {
			$old = isset( $before_arr[ $field ] ) ? (string) $before_arr[ $field ] : '';
			$new = isset( $after_arr[ $field ] ) ? (string) $after_arr[ $field ] : '';
			if ( $old !== $new ) {
				$this->log( $action, $domain_id, $field, $old, $new );
				$changed = true;
			}
		}

		if ( ! $changed && 'domain_created' === $action ) {
			$this->log( $action, $domain_id );
		}
	}
}
