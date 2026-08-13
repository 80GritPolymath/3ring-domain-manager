<?php
/**
 * Expiry and review email alerts.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Services;

use ThreeRing\DomainManager\Activator;
use ThreeRing\DomainManager\Capabilities;
use ThreeRing\DomainManager\Db\Alerts_Repository;
use ThreeRing\DomainManager\Db\Domains_Repository;
use ThreeRing\DomainManager\Db\Providers_Repository;
use ThreeRing\DomainManager\Db\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class Alert_Service
 */
final class Alert_Service {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( Activator::CRON_HOOK, array( $this, 'run_checks' ) );
	}

	/**
	 * Run expiry and review alert checks.
	 */
	public function run_checks(): void {
		if ( ! Schema::tables_exist() ) {
			return;
		}

		$settings = get_option( 'rindoma_settings', array() );
		$windows  = isset( $settings['alert_windows'] ) && is_array( $settings['alert_windows'] )
			? array_map( 'intval', $settings['alert_windows'] )
			: array( 90, 60, 30 );

		$domains_repo = new Domains_Repository();
		$alerts_repo  = new Alerts_Repository();
		$domains      = $domains_repo->list_active();
		$today        = current_time( 'Y-m-d' );

		foreach ( $domains as $domain ) {
			if ( ! empty( $domain->expires_on ) ) {
				$days_left = (int) floor( ( strtotime( $domain->expires_on ) - strtotime( $today ) ) / DAY_IN_SECONDS );

				if ( $days_left >= 0 ) {
					foreach ( $windows as $threshold ) {
						if ( $days_left > $threshold ) {
							continue;
						}

						// One email per domain + expiry date + threshold (deduped).
						$dedupe   = sprintf( '%d|expiry|%s|%d', (int) $domain->id, $domain->expires_on, $threshold );
						$existing = $alerts_repo->get_by_dedupe_key( $dedupe );
						if ( $existing && ! empty( $existing->emailed_at ) ) {
							continue;
						}

						$alerts_repo->create_if_new(
							array(
								'domain_id'      => (int) $domain->id,
								'alert_type'     => 'expiry',
								'threshold_days' => $threshold,
								'target_date'    => $domain->expires_on,
								'dedupe_key'     => $dedupe,
							)
						);

						$fresh = $alerts_repo->get_by_dedupe_key( $dedupe );
						if ( $fresh && empty( $fresh->emailed_at ) ) {
							$this->send_expiry_email( $domain, $threshold, $days_left );
							$alerts_repo->mark_emailed( (int) $fresh->id );
						}
					}
				}
			}

			if ( ! empty( $domain->next_review_due_on ) && $domain->next_review_due_on <= $today ) {
				$dedupe   = sprintf( '%d|review|%s', (int) $domain->id, $domain->next_review_due_on );
				$existing = $alerts_repo->get_by_dedupe_key( $dedupe );
				if ( $existing && ! empty( $existing->emailed_at ) ) {
					continue;
				}

				$alerts_repo->create_if_new(
					array(
						'domain_id'   => (int) $domain->id,
						'alert_type'  => 'review_due',
						'target_date' => $domain->next_review_due_on,
						'dedupe_key'  => $dedupe,
					)
				);

				$fresh = $alerts_repo->get_by_dedupe_key( $dedupe );
				if ( $fresh && empty( $fresh->emailed_at ) ) {
					$this->send_review_email( $domain );
					$alerts_repo->mark_emailed( (int) $fresh->id );
				}
			}
		}
	}

	/**
	 * Send a test notification using the same recipient path as real alerts.
	 *
	 * @return array{status: string, emails: string[]} Status is sent|failed|no_recipients.
	 */
	public function send_test_email(): array {
		$emails = $this->recipient_emails();
		if ( ! $emails ) {
			return array(
				'status' => 'no_recipients',
				'emails' => array(),
			);
		}

		$user     = wp_get_current_user();
		$subject  = __( '[Domain Manager] Test email notification', '3ring-domain-manager' );
		$settings = admin_url( 'admin.php?page=rindoma-settings' );

		$body  = __( 'This is a test email from the 3ring Domain Manager plugin.', '3ring-domain-manager' ) . "\n\n";
		$body .= sprintf(
			/* translators: %s: WordPress user login */
			__( 'Triggered by: %s', '3ring-domain-manager' ) . "\n",
			$user instanceof \WP_User ? $user->user_login : 'unknown'
		);
		$body .= sprintf(
			/* translators: %s: local datetime */
			__( 'Sent at: %s', '3ring-domain-manager' ) . "\n",
			current_time( 'Y-m-d H:i:s' )
		);
		$body .= sprintf(
			/* translators: %s: comma-separated email list */
			__( 'Recipients: %s', '3ring-domain-manager' ) . "\n",
			implode( ', ', $emails )
		);
		$body .= "\n" . sprintf(
			/* translators: %s: settings URL */
			__( 'Settings: %s', '3ring-domain-manager' ) . "\n",
			$settings
		);
		$body .= __( 'If you received this, WordPress wp_mail() is delivering Domain Manager notifications.', '3ring-domain-manager' ) . "\n";

		$sent = wp_mail( $emails, $subject, $body );

		return array(
			'status' => $sent ? 'sent' : 'failed',
			'emails' => $emails,
		);
	}

	/**
	 * Recipient email addresses.
	 *
	 * @return string[]
	 */
	private function recipient_emails(): array {
		$emails = array();
		foreach ( Capabilities::get_manager_users() as $user ) {
			if ( ! empty( $user->user_email ) ) {
				$emails[] = $user->user_email;
			}
		}
		return array_values( array_unique( $emails ) );
	}

	/**
	 * Send expiry alert email.
	 *
	 * @param object $domain    Domain row.
	 * @param int    $threshold Threshold days.
	 * @param int    $days_left Days remaining.
	 */
	private function send_expiry_email( $domain, int $threshold, int $days_left ): void {
		$emails = $this->recipient_emails();
		if ( ! $emails ) {
			return;
		}

		$edit_url = admin_url( 'admin.php?page=rindoma-domains&action=edit&domain_id=' . (int) $domain->id );
		$subject  = sprintf(
			/* translators: 1: domain name, 2: days */
			__( '[Domain Manager] %1$s expires in %2$d days', '3ring-domain-manager' ),
			$domain->domain_name,
			$days_left
		);

		$body  = sprintf( "Domain: %s\n", $domain->domain_name );
		$body .= sprintf( "Expires on: %s\n", $domain->expires_on );
		$body .= sprintf( "Days remaining: %d (alert window: %d days)\n", $days_left, $threshold );
		$body .= sprintf( "Owner: %s\n", $domain->internal_owner );
		$management_url = $this->registrar_management_url( $domain );
		if ( $management_url ) {
			$body .= sprintf( "Registrar management: %s\n", $management_url );
		}
		$body .= sprintf( "Open in WordPress: %s\n", $edit_url );

		wp_mail( $emails, $subject, $body );
	}

	/**
	 * Management URL from the domain's registrar provider (fallback to stored domain value).
	 *
	 * @param object $domain Domain row.
	 */
	private function registrar_management_url( $domain ): string {
		if ( ! empty( $domain->registrar_id ) ) {
			$provider = ( new Providers_Repository() )->get( (int) $domain->registrar_id );
			if ( $provider && ! empty( $provider->management_url ) ) {
				return (string) $provider->management_url;
			}
		}

		return ! empty( $domain->registrar_management_url ) ? (string) $domain->registrar_management_url : '';
	}

	/**
	 * Send review-due email.
	 *
	 * @param object $domain Domain row.
	 */
	private function send_review_email( $domain ): void {
		$emails = $this->recipient_emails();
		if ( ! $emails ) {
			return;
		}

		$edit_url = admin_url( 'admin.php?page=rindoma-domains&action=edit&domain_id=' . (int) $domain->id );
		$subject  = sprintf(
			/* translators: %s: domain name */
			__( '[Domain Manager] Review due for %s', '3ring-domain-manager' ),
			$domain->domain_name
		);

		$body  = sprintf( "Domain: %s\n", $domain->domain_name );
		$body .= sprintf( "Review due on: %s\n", $domain->next_review_due_on );
		$body .= sprintf( "Owner: %s\n", $domain->internal_owner );
		$body .= "Please open the domain record and click “Mark reviewed”.\n";
		$body .= sprintf( "Open in WordPress: %s\n", $edit_url );

		wp_mail( $emails, $subject, $body );
	}
}
