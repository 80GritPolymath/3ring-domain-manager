<?php
/**
 * Domain list / add / edit pages.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Admin;

use ThreeRing\DomainManager\Capabilities;
use ThreeRing\DomainManager\Db\Audit_Repository;
use ThreeRing\DomainManager\Db\Dns_Records_Repository;
use ThreeRing\DomainManager\Db\Domains_Repository;
use ThreeRing\DomainManager\Db\Documents_Repository;
use ThreeRing\DomainManager\Db\Notes_Repository;
use ThreeRing\DomainManager\Db\Providers_Repository;
use ThreeRing\DomainManager\Db\Renewals_Repository;
use ThreeRing\DomainManager\Db\Schema;
use ThreeRing\DomainManager\Services\Audit_Service;
use ThreeRing\DomainManager\Services\Document_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Class Domain_Edit_Page
 */
final class Domain_Edit_Page {

	/**
	 * Handle archive / restore / delete from list.
	 */
	public static function maybe_handle_list_actions(): void {
		if ( ! isset( $_GET['page'] ) || 'dm-domains' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$action    = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$domain_id = isset( $_GET['domain_id'] ) ? absint( $_GET['domain_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $domain_id || ! in_array( $action, array( 'archive', 'restore', 'delete' ), true ) ) {
			return;
		}

		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', '3ring-domain-manager' ) );
		}

		check_admin_referer( 'dm_' . $action . '_' . $domain_id );

		$repo  = new Domains_Repository();
		$audit = new Audit_Service();
		$before = $repo->get( $domain_id );

		if ( 'archive' === $action ) {
			$repo->archive( $domain_id );
			$audit->log( 'domain_archived', $domain_id, 'archived_at', '', current_time( 'mysql' ) );
		} elseif ( 'restore' === $action ) {
			$repo->restore( $domain_id );
			$audit->log( 'domain_restored', $domain_id );
		} elseif ( 'delete' === $action ) {
			$repo->delete( $domain_id );
			$audit->log( 'domain_deleted', $domain_id, 'domain_name', $before ? $before->domain_name : '', '' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=dm-domains&message=' . $action ) );
		exit;
	}

	/**
	 * Handle domain create/update and edit-page side actions before admin HTML is sent.
	 *
	 * Must run on admin_init so wp_safe_redirect can succeed (page callbacks run too late).
	 */
	public static function maybe_handle_post(): void {
		if ( empty( $_POST['dm_save_domain'] ) && empty( $_POST['dm_side_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'dm-domain-new' === $page && ! empty( $_POST['dm_save_domain'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			self::handle_save( 0 );
			return;
		}

		if ( 'dm-domains' !== $page ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'edit' !== $action ) {
			return;
		}

		$domain_id = isset( $_GET['domain_id'] ) ? absint( $_GET['domain_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $domain_id ) {
			return;
		}

		if ( ! empty( $_POST['dm_save_domain'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			self::handle_save( $domain_id );
			return;
		}

		self::handle_side_actions( $domain_id );
	}

	/**
	 * Render list or edit based on query.
	 */
	public static function render_list_or_edit(): void {
		if ( ! Capabilities::current_user_can_view() ) {
			wp_die( esc_html__( 'Permission denied.', '3ring-domain-manager' ) );
		}

		if ( ! Schema::tables_exist() ) {
			echo '<div class="wrap"><h1>Domains</h1><div class="notice notice-error"><p>' . esc_html__( 'Database tables are missing.', '3ring-domain-manager' ) . '</p></div></div>';
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'edit' === $action ) {
			self::render_edit();
			return;
		}

		if ( 'details' === $action ) {
			self::render_details();
			return;
		}

		self::render_list();
	}

	/**
	 * Render add-new page.
	 */
	public static function render_new(): void {
		if ( ! Capabilities::current_user_can_edit() ) {
			wp_die( esc_html__( 'Permission denied.', '3ring-domain-manager' ) );
		}

		if ( ! Schema::tables_exist() ) {
			echo '<div class="wrap"><h1>Add Domain</h1><div class="notice notice-error"><p>' . esc_html__( 'Database tables are missing.', '3ring-domain-manager' ) . '</p></div></div>';
			return;
		}

		$domain = null;
		self::render_form( $domain );
	}

	/**
	 * Render edit page.
	 */
	private static function render_edit(): void {
		$domain_id = isset( $_GET['domain_id'] ) ? absint( $_GET['domain_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$repo      = new Domains_Repository();
		$domain    = $repo->get( $domain_id );

		if ( ! $domain ) {
			echo '<div class="wrap"><h1>Edit Domain</h1><div class="notice notice-error"><p>' . esc_html__( 'Domain not found.', '3ring-domain-manager' ) . '</p></div></div>';
			return;
		}

		self::render_form( $domain );
	}

	/**
	 * Render read-only domain details page.
	 */
	private static function render_details(): void {
		$domain_id = isset( $_GET['domain_id'] ) ? absint( $_GET['domain_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$repo      = new Domains_Repository();
		$domain    = $repo->get( $domain_id );

		if ( ! $domain ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Domain Details', '3ring-domain-manager' ) . '</h1><div class="notice notice-error"><p>' . esc_html__( 'Domain not found.', '3ring-domain-manager' ) . '</p></div></div>';
			return;
		}

		$providers = new Providers_Repository();

		$registrar = ! empty( $domain->registrar_id ) ? $providers->get( (int) $domain->registrar_id ) : null;
		$dns       = ! empty( $domain->dns_provider_id ) ? $providers->get( (int) $domain->dns_provider_id ) : null;
		$hosting   = ! empty( $domain->hosting_provider_id ) ? $providers->get( (int) $domain->hosting_provider_id ) : null;
		$email     = ! empty( $domain->email_provider_id ) ? $providers->get( (int) $domain->email_provider_id ) : null;

		$registrar_name = $registrar ? (string) $registrar->name : '';
		$dns_name       = $dns ? (string) $dns->name : '';
		$hosting_name   = $hosting ? (string) $hosting->name : '';
		$email_name     = $email ? (string) $email->name : '';
		$can_edit       = Capabilities::current_user_can_edit();

		$dns_records_repo        = new Dns_Records_Repository();
		$dns_records             = $dns_records_repo->list_for_domain( $domain_id );
		$dns_records_provider_id = $dns_records_repo->provider_id_for_domain( $domain_id );
		$dns_records_provider    = $dns_records_provider_id ? $providers->get( $dns_records_provider_id ) : null;
		$dns_records_provider_name = $dns_records_provider ? (string) $dns_records_provider->name : '';

		include DM_PLUGIN_DIR . 'includes/admin/views/domain-details.php';
	}

	/**
	 * Render list table.
	 */
	private static function render_list(): void {
		$table = new Domains_List_Table();
		$table->prepare_items();

		$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$actions = array();

		if ( Capabilities::current_user_can_edit() ) {
			$actions[] = array(
				'label' => __( 'Add domain', '3ring-domain-manager' ),
				'url'   => admin_url( 'admin.php?page=dm-domain-new' ),
				'icon'  => 'plus',
				'solid' => true,
			);
		}
		?>
		<div class="wrap dm-wrap">
			<?php
			Ui::page_header(
				array(
					'title'    => __( 'Domains', '3ring-domain-manager' ),
					'subtitle' => __( 'Search, filter and maintain every domain in your domain portfolio.', '3ring-domain-manager' ),
					'actions'  => $actions,
				)
			);
			?>
			<?php if ( $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( ucfirst( str_replace( '_', ' ', $message ) ) ); ?></p></div>
			<?php endif; ?>
			<div class="dm-panel">
				<form method="get">
					<input type="hidden" name="page" value="dm-domains" />
					<?php
					$table->search_box( __( 'Search domains', '3ring-domain-manager' ), 'dm-domain' );
					$table->display();
					?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle domain save.
	 *
	 * @param int $domain_id Domain ID (0 = create).
	 */
	private static function handle_save( int $domain_id ): void {
		if ( empty( $_POST['dm_save_domain'] ) ) {
			return;
		}

		if ( ! Capabilities::current_user_can_edit() ) {
			wp_die( esc_html__( 'Permission denied.', '3ring-domain-manager' ) );
		}

		check_admin_referer( 'dm_save_domain_' . $domain_id );

		$data = array();
		$fields = array(
			'domain_name', 'display_name', 'portfolio_status', 'usage_type', 'business_purpose',
			'business_importance', 'internal_owner', 'technical_owner', 'tags',
			'registrar_id',
			'registered_on', 'expires_on', 'last_renewed_on', 'auto_renew_status', 'active_card',
			'dns_provider_id', 'nameservers', 'hosting_provider_id', 'email_provider_id',
			'primary_url', 'expected_redirect_url', 'redirect_type',
			'last_manually_verified_on', 'next_review_due_on',
		);

		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$data[ $field ] = wp_unslash( $_POST[ $field ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
		}
		$data['used_for_email'] = ! empty( $_POST['used_for_email'] ) ? 1 : 0;

		// Keep domain registrar account/URL columns in sync with the selected provider.
		$registrar_id = isset( $data['registrar_id'] ) ? absint( $data['registrar_id'] ) : 0;
		if ( $registrar_id > 0 ) {
			$registrar = ( new Providers_Repository() )->get( $registrar_id );
			$data['registrar_account_reference'] = $registrar && ! empty( $registrar->account_id ) ? (string) $registrar->account_id : '';
			$data['registrar_management_url']    = $registrar && ! empty( $registrar->management_url ) ? (string) $registrar->management_url : '';
		} else {
			$data['registrar_account_reference'] = '';
			$data['registrar_management_url']    = '';
		}

		$repo  = new Domains_Repository();
		$audit = new Audit_Service();

		if ( $domain_id > 0 ) {
			$before = $repo->get( $domain_id );
			$result = $repo->update( $domain_id, $data );
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'dm_domain', 'dm_error', $result->get_error_message(), 'error' );
				return;
			}
			$after = $repo->get( $domain_id );
			$audit->log_domain_changes( $domain_id, $before, $after );
			wp_safe_redirect( admin_url( 'admin.php?page=dm-domains&action=details&domain_id=' . $domain_id . '&message=updated' ) );
			exit;
		}

		$result = $repo->insert( $data );
		if ( is_wp_error( $result ) ) {
			add_settings_error( 'dm_domain', 'dm_error', $result->get_error_message(), 'error' );
			return;
		}
		$audit->log( 'domain_created', (int) $result );
		wp_safe_redirect( admin_url( 'admin.php?page=dm-domains&action=details&domain_id=' . (int) $result . '&message=created' ) );
		exit;
	}

	/**
	 * Handle renewal / note / document / mark-reviewed actions.
	 *
	 * @param int $domain_id Domain ID.
	 */
	private static function handle_side_actions( int $domain_id ): void {
		if ( empty( $_POST['dm_side_action'] ) ) {
			return;
		}

		if ( ! Capabilities::current_user_can_edit() ) {
			wp_die( esc_html__( 'Permission denied.', '3ring-domain-manager' ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['dm_side_action'] ) );
		check_admin_referer( 'dm_side_' . $domain_id );

		$repo  = new Domains_Repository();
		$audit = new Audit_Service();
		$domain = $repo->get( $domain_id );

		if ( 'mark_reviewed' === $action ) {
			$before = $domain;
			$repo->mark_reviewed( $domain_id );
			$after = $repo->get( $domain_id );
			$audit->log_domain_changes( $domain_id, $before, $after, 'domain_reviewed' );
			add_settings_error( 'dm_domain', 'dm_reviewed', __( 'Domain marked as reviewed.', '3ring-domain-manager' ), 'updated' );
			return;
		}

		if ( 'add_note' === $action ) {
			$body = isset( $_POST['note_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note_body'] ) ) : '';
			if ( $body ) {
				( new Notes_Repository() )->insert( $domain_id, $body );
				$audit->log( 'note_added', $domain_id );
				add_settings_error( 'dm_domain', 'dm_note', __( 'Note added.', '3ring-domain-manager' ), 'updated' );
			}
			return;
		}

		if ( 'add_renewal' === $action ) {
			$settings = get_option( 'dm_settings', array() );
			$currency = $settings['default_currency'] ?? 'CAD';
			$renewed_on = isset( $_POST['renewed_on'] ) ? sanitize_text_field( wp_unslash( $_POST['renewed_on'] ) ) : current_time( 'Y-m-d' );
			$new_expires = isset( $_POST['new_expires_on'] ) ? sanitize_text_field( wp_unslash( $_POST['new_expires_on'] ) ) : '';
			$cost = isset( $_POST['cost'] ) ? sanitize_text_field( wp_unslash( $_POST['cost'] ) ) : '';
			$invoice_no = isset( $_POST['vendor_invoice_number'] ) ? sanitize_text_field( wp_unslash( $_POST['vendor_invoice_number'] ) ) : '';
			$notes = isset( $_POST['renewal_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['renewal_notes'] ) ) : '';

			if ( ! $new_expires ) {
				add_settings_error( 'dm_domain', 'dm_renewal', __( 'New expiry date is required.', '3ring-domain-manager' ), 'error' );
				return;
			}

			$invoice_id = null;
			if ( ! empty( $_FILES['invoice_file']['tmp_name'] ) ) {
				$upload = ( new Document_Service() )->upload( $domain_id, $_FILES['invoice_file'], 'Renewal invoice ' . $renewed_on, 'invoice' );
				if ( is_wp_error( $upload ) ) {
					add_settings_error( 'dm_domain', 'dm_invoice', $upload->get_error_message(), 'error' );
					return;
				}
				$doc = ( new Documents_Repository() )->get( (int) $upload );
				$invoice_id = $doc ? (int) $doc->attachment_id : null;
			}

			$renewal_id = ( new Renewals_Repository() )->insert(
				array(
					'domain_id'             => $domain_id,
					'renewed_on'            => $renewed_on,
					'previous_expires_on'   => $domain->expires_on,
					'new_expires_on'        => $new_expires,
					'cost'                  => $cost,
					'currency'              => isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : $currency,
					'invoice_attachment_id' => $invoice_id,
					'vendor_invoice_number' => $invoice_no,
					'notes'                 => $notes,
				)
			);

			if ( $renewal_id ) {
				$before = $domain;
				$repo->update(
					$domain_id,
					array_merge(
						(array) $domain,
						array(
							'expires_on'      => $new_expires,
							'last_renewed_on' => $renewed_on,
						)
					)
				);
				$after = $repo->get( $domain_id );
				$audit->log_domain_changes( $domain_id, $before, $after, 'renewal_recorded' );
				$audit->log( 'renewal_created', $domain_id, 'cost', '', (string) $cost );
				add_settings_error( 'dm_domain', 'dm_renewal', __( 'Renewal recorded.', '3ring-domain-manager' ), 'updated' );
			}
			return;
		}

		if ( 'upload_document' === $action ) {
			$title = isset( $_POST['document_title'] ) ? sanitize_text_field( wp_unslash( $_POST['document_title'] ) ) : '';
			$type  = isset( $_POST['doc_type'] ) ? sanitize_key( wp_unslash( $_POST['doc_type'] ) ) : 'other';
			if ( empty( $_FILES['document_file']['tmp_name'] ) ) {
				add_settings_error( 'dm_domain', 'dm_doc', __( 'Please choose a file.', '3ring-domain-manager' ), 'error' );
				return;
			}
			$result = ( new Document_Service() )->upload( $domain_id, $_FILES['document_file'], $title, $type );
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'dm_domain', 'dm_doc', $result->get_error_message(), 'error' );
				return;
			}
			$audit->log( 'document_uploaded', $domain_id );
			add_settings_error( 'dm_domain', 'dm_doc', __( 'Document uploaded.', '3ring-domain-manager' ), 'updated' );
			return;
		}

		if ( 'save_dns_records' === $action ) {
			$provider_id = isset( $_POST['dns_records_provider_id'] ) ? absint( $_POST['dns_records_provider_id'] ) : 0;
			$provider_id = $provider_id > 0 ? $provider_id : null;

			if ( $provider_id ) {
				$provider = ( new Providers_Repository() )->get( $provider_id );
				$types    = $provider ? Providers_Repository::parse_types( $provider->provider_type ?? '' ) : array();
				if ( ! $provider || ! in_array( 'dns', $types, true ) ) {
					add_settings_error( 'dm_domain', 'dm_dns', __( 'Please choose a provider with DNS status.', '3ring-domain-manager' ), 'error' );
					return;
				}
			}

			$raw_types     = isset( $_POST['dns_record_type'] ) && is_array( $_POST['dns_record_type'] ) ? wp_unslash( $_POST['dns_record_type'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw_names     = isset( $_POST['dns_record_name'] ) && is_array( $_POST['dns_record_name'] ) ? wp_unslash( $_POST['dns_record_name'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw_priorities = isset( $_POST['dns_record_priority'] ) && is_array( $_POST['dns_record_priority'] ) ? wp_unslash( $_POST['dns_record_priority'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw_contents  = isset( $_POST['dns_record_content'] ) && is_array( $_POST['dns_record_content'] ) ? wp_unslash( $_POST['dns_record_content'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw_ttls      = isset( $_POST['dns_record_ttl'] ) && is_array( $_POST['dns_record_ttl'] ) ? wp_unslash( $_POST['dns_record_ttl'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			$records = array();
			$count   = max( count( $raw_types ), count( $raw_names ), count( $raw_contents ) );

			for ( $i = 0; $i < $count; $i++ ) {
				$records[] = array(
					'record_type' => isset( $raw_types[ $i ] ) ? (string) $raw_types[ $i ] : '',
					'name'        => isset( $raw_names[ $i ] ) ? (string) $raw_names[ $i ] : '',
					'priority'    => isset( $raw_priorities[ $i ] ) ? (string) $raw_priorities[ $i ] : '',
					'content'     => isset( $raw_contents[ $i ] ) ? (string) $raw_contents[ $i ] : '',
					'ttl'         => isset( $raw_ttls[ $i ] ) ? (string) $raw_ttls[ $i ] : '',
				);
			}

			$saved = ( new Dns_Records_Repository() )->replace_for_domain( $domain_id, $provider_id, $records );
			if ( ! $saved ) {
				add_settings_error( 'dm_domain', 'dm_dns', __( 'Could not save DNS records.', '3ring-domain-manager' ), 'error' );
				return;
			}

			$audit->log( 'dns_records_saved', $domain_id );
			wp_safe_redirect( admin_url( 'admin.php?page=dm-domains&action=details&domain_id=' . $domain_id . '&message=dns_saved' ) );
			exit;
		}
	}

	/**
	 * Render domain form.
	 *
	 * @param object|null $domain Domain row or null for new.
	 */
	private static function render_form( $domain ): void {
		$is_new     = null === $domain;
		$domain_id  = $is_new ? 0 : (int) $domain->id;
		$providers  = new Providers_Repository();
		$registrars = $providers->list_all( 'registrar' );
		$dns        = $providers->list_all( 'dns' );
		$hosting    = $providers->list_all( 'hosting' );
		$email      = $providers->list_all( 'email' );

		$renewals    = $is_new ? array() : ( new Renewals_Repository() )->list_for_domain( $domain_id );
		$notes       = $is_new ? array() : ( new Notes_Repository() )->list_for_domain( $domain_id );
		$documents   = $is_new ? array() : ( new Documents_Repository() )->list_for_domain( $domain_id );
		$audit       = $is_new ? array() : ( new Audit_Repository() )->list_for_domain( $domain_id );
		$dns_records = $is_new ? array() : ( new Dns_Records_Repository() )->list_for_domain( $domain_id );

		$dns_records_provider_id = null;
		if ( ! $is_new ) {
			$dns_records_provider_id = ( new Dns_Records_Repository() )->provider_id_for_domain( $domain_id );
			if ( null === $dns_records_provider_id && ! empty( $domain->dns_provider_id ) ) {
				$dns_records_provider_id = (int) $domain->dns_provider_id;
			}
		}

		$can_edit = Capabilities::current_user_can_edit();

		include DM_PLUGIN_DIR . 'includes/admin/views/domain-form.php';
	}
}
