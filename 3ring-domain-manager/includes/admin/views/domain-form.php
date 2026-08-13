<?php
/**
 * Domain add/edit form view.
 *
 * @package ThreeRing\DomainManager
 *
 * @var object|null $domain
 * @var int $domain_id
 * @var bool $is_new
 * @var bool $can_edit
 * @var object[] $registrars
 * @var object[] $dns
 * @var object[] $hosting
 * @var object[] $email
 * @var object[] $renewals
 * @var object[] $notes
 * @var object[] $documents
 * @var object[] $audit
 * @var object[] $dns_records
 * @var int|null $dns_records_provider_id
 */

use ThreeRing\DomainManager\Admin\Ui;
use ThreeRing\DomainManager\Db\Schema;
use ThreeRing\DomainManager\Services\Document_Service;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template is included from a class method; these variables are not true globals.

$val = static function ( $field, $default = '' ) use ( $domain ) {
	if ( ! $domain ) {
		return $default;
	}
	return isset( $domain->$field ) && null !== $domain->$field ? (string) $domain->$field : $default;
};

$selected_registrar_id = (int) $val( 'registrar_id', '0' );
$registrar_account_id  = '';
$registrar_mgmt_url    = '';

foreach ( $registrars as $registrar_option ) {
	if ( (int) $registrar_option->id === $selected_registrar_id ) {
		$registrar_account_id = ! empty( $registrar_option->account_id ) ? (string) $registrar_option->account_id : '';
		$registrar_mgmt_url   = ! empty( $registrar_option->management_url ) ? (string) $registrar_option->management_url : '';
		break;
	}
}

$rindoma_header_actions = array();

if ( ! $is_new && $registrar_mgmt_url ) {
	$rindoma_header_actions[] = array(
		'label'  => __( 'Registrar management', '3ring-domain-manager' ),
		'url'    => $registrar_mgmt_url,
		'icon'   => 'external',
		'target' => '_blank',
	);
}

if ( ! $is_new ) {
	$rindoma_header_actions[] = array(
		'label' => __( 'Back to domains', '3ring-domain-manager' ),
		'url'   => admin_url( 'admin.php?page=rindoma-domains' ),
		'icon'  => 'globe',
		'solid' => true,
	);
}
?>
<div class="wrap dm-wrap">
	<?php
	Ui::page_header(
		array(
			'title'    => $is_new ? __( 'Add Domain', '3ring-domain-manager' ) : ( $domain->domain_name ?? __( 'Edit Domain', '3ring-domain-manager' ) ),
			'subtitle' => $is_new
				? __( 'Register a new domain record with ownership, registration and DNS details.', '3ring-domain-manager' )
				: __( 'Update registration, DNS and ownership details, then record renewals or notes below.', '3ring-domain-manager' ),
			'actions'  => $rindoma_header_actions,
		)
	);
	?>
	<?php settings_errors( 'rindoma_domain' ); ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'rindoma_save_domain_' . $domain_id ); ?>
		<input type="hidden" name="rindoma_save_domain" value="1" />

		<div class="dm-grid dm-grid--wide">
			<fieldset class="dm-fieldset">
				<legend><?php esc_html_e( 'Identity & purpose', '3ring-domain-manager' ); ?></legend>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="domain_name"><?php esc_html_e( 'Domain name', '3ring-domain-manager' ); ?> *</label></th>
						<td><input name="domain_name" id="domain_name" type="text" class="regular-text" required value="<?php echo esc_attr( $val( 'domain_name' ) ); ?>" <?php disabled( ! $can_edit ); ?> /></td>
					</tr>
					<tr>
						<th><label for="display_name"><?php esc_html_e( 'Display name', '3ring-domain-manager' ); ?></label></th>
						<td><input name="display_name" id="display_name" type="text" class="regular-text" value="<?php echo esc_attr( $val( 'display_name' ) ); ?>" <?php disabled( ! $can_edit ); ?> /></td>
					</tr>
					<tr>
						<th><label for="portfolio_status"><?php esc_html_e( 'Portfolio status', '3ring-domain-manager' ); ?> *</label></th>
						<td>
							<select name="portfolio_status" id="portfolio_status" required <?php disabled( ! $can_edit ); ?>>
								<?php foreach ( Schema::portfolio_statuses() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $val( 'portfolio_status', 'active' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="usage_type"><?php esc_html_e( 'Usage type', '3ring-domain-manager' ); ?> *</label></th>
						<td>
							<select name="usage_type" id="usage_type" required <?php disabled( ! $can_edit ); ?>>
								<?php foreach ( Schema::usage_types() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $val( 'usage_type', 'unknown' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="business_purpose"><?php esc_html_e( 'Business purpose', '3ring-domain-manager' ); ?></label></th>
						<td><textarea name="business_purpose" id="business_purpose" class="large-text" rows="3" <?php disabled( ! $can_edit ); ?>><?php echo esc_textarea( $val( 'business_purpose' ) ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="business_importance"><?php esc_html_e( 'Importance', '3ring-domain-manager' ); ?></label></th>
						<td>
							<select name="business_importance" id="business_importance" <?php disabled( ! $can_edit ); ?>>
								<?php foreach ( Schema::importance_levels() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $val( 'business_importance', 'standard' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="internal_owner"><?php esc_html_e( 'Internal owner', '3ring-domain-manager' ); ?> *</label></th>
						<td><input name="internal_owner" id="internal_owner" type="text" class="regular-text" required value="<?php echo esc_attr( $val( 'internal_owner' ) ); ?>" <?php disabled( ! $can_edit ); ?> /></td>
					</tr>
					<tr>
						<th><label for="technical_owner"><?php esc_html_e( 'Technical owner', '3ring-domain-manager' ); ?></label></th>
						<td><input name="technical_owner" id="technical_owner" type="text" class="regular-text" value="<?php echo esc_attr( $val( 'technical_owner' ) ); ?>" <?php disabled( ! $can_edit ); ?> /></td>
					</tr>
					<tr>
						<th><label for="tags"><?php esc_html_e( 'Tags', '3ring-domain-manager' ); ?></label></th>
						<td><input name="tags" id="tags" type="text" class="regular-text" value="<?php echo esc_attr( $val( 'tags' ) ); ?>" <?php disabled( ! $can_edit ); ?> /></td>
					</tr>
				</table>
			</fieldset>

			<fieldset class="dm-fieldset">
				<legend><?php esc_html_e( 'Registration', '3ring-domain-manager' ); ?></legend>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="registrar_id"><?php esc_html_e( 'Registrar', '3ring-domain-manager' ); ?> *</label></th>
						<td>
							<select name="registrar_id" id="registrar_id" required <?php disabled( ! $can_edit ); ?>>
								<option value="" data-account-id="" data-management-url=""><?php esc_html_e( 'Select…', '3ring-domain-manager' ); ?></option>
								<?php foreach ( $registrars as $provider ) : ?>
									<option
										value="<?php echo esc_attr( (string) $provider->id ); ?>"
										data-account-id="<?php echo esc_attr( (string) ( $provider->account_id ?? '' ) ); ?>"
										data-management-url="<?php echo esc_attr( (string) ( $provider->management_url ?? '' ) ); ?>"
										<?php selected( $val( 'registrar_id' ), (string) $provider->id ); ?>
									><?php echo esc_html( $provider->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Account#/ID', '3ring-domain-manager' ); ?></th>
						<td>
							<span id="dm-registrar-account-id" class="dm-detail-value<?php echo '' === $registrar_account_id ? ' dm-detail-value--muted' : ''; ?>">
								<?php echo '' !== $registrar_account_id ? esc_html( $registrar_account_id ) : esc_html__( '—', '3ring-domain-manager' ); ?>
							</span>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Management URL', '3ring-domain-manager' ); ?></th>
						<td>
							<span id="dm-registrar-management-url" class="dm-detail-value<?php echo '' === $registrar_mgmt_url ? ' dm-detail-value--muted' : ''; ?>">
								<?php if ( $registrar_mgmt_url ) : ?>
									<a href="<?php echo esc_url( $registrar_mgmt_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $registrar_mgmt_url ); ?></a>
								<?php else : ?>
									<?php esc_html_e( '—', '3ring-domain-manager' ); ?>
								<?php endif; ?>
							</span>
						</td>
					</tr>
					<tr>
						<th><label for="registered_on"><?php esc_html_e( 'Registered on', '3ring-domain-manager' ); ?></label></th>
						<td><input name="registered_on" id="registered_on" type="date" value="<?php echo esc_attr( $val( 'registered_on' ) ); ?>" <?php disabled( ! $can_edit ); ?> /></td>
					</tr>
					<tr>
						<th><label for="expires_on"><?php esc_html_e( 'Expires on', '3ring-domain-manager' ); ?> *</label></th>
						<td><input name="expires_on" id="expires_on" type="date" required value="<?php echo esc_attr( $val( 'expires_on' ) ); ?>" <?php disabled( ! $can_edit ); ?> /></td>
					</tr>
					<tr>
						<th><label for="last_renewed_on"><?php esc_html_e( 'Last renewed on', '3ring-domain-manager' ); ?></label></th>
						<td><input name="last_renewed_on" id="last_renewed_on" type="date" value="<?php echo esc_attr( $val( 'last_renewed_on' ) ); ?>" <?php disabled( ! $can_edit ); ?> /></td>
					</tr>
					<tr>
						<th><label for="auto_renew_status"><?php esc_html_e( 'Auto-renew', '3ring-domain-manager' ); ?></label></th>
						<td>
							<select name="auto_renew_status" id="auto_renew_status" <?php disabled( ! $can_edit ); ?>>
								<?php foreach ( Schema::auto_renew_statuses() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $val( 'auto_renew_status', 'unknown' ), $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="active_card"><?php esc_html_e( 'Active Card', '3ring-domain-manager' ); ?></label></th>
						<td>
							<input name="active_card" id="active_card" type="text" class="small-text" maxlength="4" pattern="[0-9]{4}" inputmode="numeric" placeholder="1234" value="<?php echo esc_attr( $val( 'active_card' ) ); ?>" <?php disabled( ! $can_edit ); ?> />
							<p class="description"><?php esc_html_e( 'Last 4 digits of the credit card on file at the registrar (reference only).', '3ring-domain-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="last_manually_verified_on"><?php esc_html_e( 'Last manually verified', '3ring-domain-manager' ); ?></label></th>
						<td><input name="last_manually_verified_on" id="last_manually_verified_on" type="date" value="<?php echo esc_attr( $val( 'last_manually_verified_on' ) ); ?>" <?php disabled( ! $can_edit ); ?> /></td>
					</tr>
					<tr>
						<th><label for="next_review_due_on"><?php esc_html_e( 'Next review due', '3ring-domain-manager' ); ?></label></th>
						<td><input name="next_review_due_on" id="next_review_due_on" type="date" value="<?php echo esc_attr( $val( 'next_review_due_on' ) ); ?>" <?php disabled( ! $can_edit ); ?> /></td>
					</tr>
				</table>
			</fieldset>

			<fieldset class="dm-fieldset">
				<legend><?php esc_html_e( 'DNS, website & email', '3ring-domain-manager' ); ?></legend>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="dns_provider_id"><?php esc_html_e( 'DNS provider', '3ring-domain-manager' ); ?></label></th>
						<td>
							<select name="dns_provider_id" id="dns_provider_id" <?php disabled( ! $can_edit ); ?>>
								<option value=""><?php esc_html_e( 'None', '3ring-domain-manager' ); ?></option>
								<?php foreach ( $dns as $provider ) : ?>
									<option value="<?php echo esc_attr( (string) $provider->id ); ?>" <?php selected( $val( 'dns_provider_id' ), (string) $provider->id ); ?>><?php echo esc_html( $provider->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="nameservers"><?php esc_html_e( 'Nameservers', '3ring-domain-manager' ); ?></label></th>
						<td><textarea name="nameservers" id="nameservers" class="large-text" rows="3" <?php disabled( ! $can_edit ); ?>><?php echo esc_textarea( $val( 'nameservers' ) ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="hosting_provider_id"><?php esc_html_e( 'Hosting provider', '3ring-domain-manager' ); ?></label></th>
						<td>
							<select name="hosting_provider_id" id="hosting_provider_id" <?php disabled( ! $can_edit ); ?>>
								<option value=""><?php esc_html_e( 'None', '3ring-domain-manager' ); ?></option>
								<?php foreach ( $hosting as $provider ) : ?>
									<option value="<?php echo esc_attr( (string) $provider->id ); ?>" <?php selected( $val( 'hosting_provider_id' ), (string) $provider->id ); ?>><?php echo esc_html( $provider->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="email_provider_id"><?php esc_html_e( 'Email provider', '3ring-domain-manager' ); ?></label></th>
						<td>
							<select name="email_provider_id" id="email_provider_id" <?php disabled( ! $can_edit ); ?>>
								<option value=""><?php esc_html_e( 'None', '3ring-domain-manager' ); ?></option>
								<?php foreach ( $email as $provider ) : ?>
									<option value="<?php echo esc_attr( (string) $provider->id ); ?>" <?php selected( $val( 'email_provider_id' ), (string) $provider->id ); ?>><?php echo esc_html( $provider->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Used for email', '3ring-domain-manager' ); ?></th>
						<td><label><input type="checkbox" name="used_for_email" value="1" <?php checked( $val( 'used_for_email', '0' ), '1' ); ?> <?php disabled( ! $can_edit ); ?> /> <?php esc_html_e( 'Yes', '3ring-domain-manager' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="primary_url"><?php esc_html_e( 'Primary URL', '3ring-domain-manager' ); ?></label></th>
						<td><input name="primary_url" id="primary_url" type="url" class="regular-text" value="<?php echo esc_attr( $val( 'primary_url' ) ); ?>" <?php disabled( ! $can_edit ); ?> /></td>
					</tr>
					<tr>
						<th><label for="expected_redirect_url"><?php esc_html_e( 'Expected redirect URL', '3ring-domain-manager' ); ?></label></th>
						<td><input name="expected_redirect_url" id="expected_redirect_url" type="url" class="regular-text" value="<?php echo esc_attr( $val( 'expected_redirect_url' ) ); ?>" <?php disabled( ! $can_edit ); ?> /></td>
					</tr>
					<tr>
						<th><label for="redirect_type"><?php esc_html_e( 'Redirect type', '3ring-domain-manager' ); ?></label></th>
						<td><input name="redirect_type" id="redirect_type" type="text" class="small-text" placeholder="301" value="<?php echo esc_attr( $val( 'redirect_type' ) ); ?>" <?php disabled( ! $can_edit ); ?> /></td>
					</tr>
				</table>
			</fieldset>
		</div>

		<?php if ( $can_edit ) : ?>
			<?php submit_button( $is_new ? __( 'Create domain', '3ring-domain-manager' ) : __( 'Save Domain', '3ring-domain-manager' ) ); ?>
		<?php endif; ?>
	</form>

	<?php if ( ! $is_new && $can_edit ) : ?>
		<div class="dm-grid">
			<div class="dm-panel">
				<div class="dm-panel__head">
					<h2><?php Ui::print_icon( 'check' ); ?><?php esc_html_e( 'Review', '3ring-domain-manager' ); ?></h2>
				</div>
				<div class="dm-panel__body">
					<p class="description"><?php esc_html_e( 'Confirm the record is still accurate and reset the review clock.', '3ring-domain-manager' ); ?></p>
					<form method="post">
						<?php wp_nonce_field( 'rindoma_side_' . $domain_id ); ?>
						<input type="hidden" name="rindoma_side_action" value="mark_reviewed" />
						<?php submit_button( __( 'Mark reviewed today', '3ring-domain-manager' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			</div>

			<div class="dm-panel">
				<div class="dm-panel__head">
					<h2><?php Ui::print_icon( 'calendar' ); ?><?php esc_html_e( 'Record renewal', '3ring-domain-manager' ); ?></h2>
				</div>
				<div class="dm-panel__body">
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'rindoma_side_' . $domain_id ); ?>
					<input type="hidden" name="rindoma_side_action" value="add_renewal" />
					<p><label><?php esc_html_e( 'Renewed on', '3ring-domain-manager' ); ?><br /><input type="date" name="renewed_on" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required /></label></p>
					<p><label><?php esc_html_e( 'New expiry date', '3ring-domain-manager' ); ?><br /><input type="date" name="new_expires_on" required /></label></p>
					<p><label><?php esc_html_e( 'Cost', '3ring-domain-manager' ); ?><br /><input type="number" step="0.01" name="cost" class="small-text" /></label>
					<label><?php esc_html_e( 'Currency', '3ring-domain-manager' ); ?> <input type="text" name="currency" value="CAD" class="small-text" maxlength="3" /></label></p>
					<p><label><?php esc_html_e( 'Vendor invoice #', '3ring-domain-manager' ); ?><br /><input type="text" name="vendor_invoice_number" class="regular-text" /></label></p>
					<p><label><?php esc_html_e( 'Invoice file', '3ring-domain-manager' ); ?><br /><input type="file" name="invoice_file" /></label></p>
					<p><label><?php esc_html_e( 'Notes', '3ring-domain-manager' ); ?><br /><textarea name="renewal_notes" class="large-text" rows="2"></textarea></label></p>
					<?php submit_button( __( 'Save renewal', '3ring-domain-manager' ), 'secondary', 'submit', false ); ?>
				</form>
				</div>
			</div>

			<div class="dm-panel">
				<div class="dm-panel__head">
					<h2><?php Ui::print_icon( 'file' ); ?><?php esc_html_e( 'Add note', '3ring-domain-manager' ); ?></h2>
				</div>
				<div class="dm-panel__body">
				<form method="post">
					<?php wp_nonce_field( 'rindoma_side_' . $domain_id ); ?>
					<input type="hidden" name="rindoma_side_action" value="add_note" />
					<textarea name="note_body" class="large-text" rows="3" required></textarea>
					<?php submit_button( __( 'Add note', '3ring-domain-manager' ), 'secondary', 'submit', false ); ?>
				</form>
				</div>
			</div>

			<div class="dm-panel">
				<div class="dm-panel__head">
					<h2><?php Ui::print_icon( 'transfer' ); ?><?php esc_html_e( 'Upload document', '3ring-domain-manager' ); ?></h2>
				</div>
				<div class="dm-panel__body">
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'rindoma_side_' . $domain_id ); ?>
					<input type="hidden" name="rindoma_side_action" value="upload_document" />
					<p><label><?php esc_html_e( 'Title', '3ring-domain-manager' ); ?><br /><input type="text" name="document_title" class="regular-text" /></label></p>
					<p><label><?php esc_html_e( 'Type', '3ring-domain-manager' ); ?><br />
						<select name="doc_type">
							<option value="other"><?php esc_html_e( 'Other', '3ring-domain-manager' ); ?></option>
							<option value="invoice"><?php esc_html_e( 'Invoice', '3ring-domain-manager' ); ?></option>
							<option value="ownership"><?php esc_html_e( 'Ownership', '3ring-domain-manager' ); ?></option>
							<option value="transfer"><?php esc_html_e( 'Transfer', '3ring-domain-manager' ); ?></option>
						</select>
					</label></p>
					<p><input type="file" name="document_file" required /></p>
					<?php submit_button( __( 'Upload', '3ring-domain-manager' ), 'secondary', 'submit', false ); ?>
				</form>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( ! $is_new ) : ?>
		<?php
		$dns_record_types = Schema::dns_record_types();
		$dns_provider_selected = $dns_records_provider_id ? (string) $dns_records_provider_id : '';
		$dns_provider_label    = '';
		if ( $dns_provider_selected ) {
			foreach ( $dns as $provider ) {
				if ( (string) $provider->id === $dns_provider_selected ) {
					$dns_provider_label = (string) $provider->name;
					break;
				}
			}
		}
		$dns_rows = ! empty( $dns_records ) ? $dns_records : array();
		if ( $can_edit && empty( $dns_rows ) ) {
			$dns_rows = array(
				(object) array(
					'record_type' => 'A',
					'name'        => '@',
					'priority'    => '',
					'content'     => '',
					'ttl'         => '3600',
				),
			);
		}
		?>
		<div class="dm-panel dm-dns-records">
			<div class="dm-panel__head">
				<h2><?php Ui::print_icon( 'server' ); ?><?php esc_html_e( 'DNS Records', '3ring-domain-manager' ); ?></h2>
			</div>
			<div class="dm-panel__body">
				<?php if ( $can_edit ) : ?>
					<form method="post" class="dm-dns-records__form">
						<?php wp_nonce_field( 'rindoma_side_' . $domain_id ); ?>
						<input type="hidden" name="rindoma_side_action" value="save_dns_records" />

						<p class="dm-dns-records__provider">
							<label for="dns_records_provider_id">
								<strong><?php esc_html_e( 'Provider', '3ring-domain-manager' ); ?></strong>
								<span class="description"><?php esc_html_e( 'Where these DNS records are managed.', '3ring-domain-manager' ); ?></span>
							</label>
							<select name="dns_records_provider_id" id="dns_records_provider_id">
								<option value=""><?php esc_html_e( 'Select DNS provider…', '3ring-domain-manager' ); ?></option>
								<?php foreach ( $dns as $provider ) : ?>
									<option value="<?php echo esc_attr( (string) $provider->id ); ?>" <?php selected( $dns_provider_selected, (string) $provider->id ); ?>>
										<?php echo esc_html( $provider->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</p>

						<div class="dm-dns-records__table-wrap">
							<table class="widefat striped dm-dns-records__table" id="dm-dns-records-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Type', '3ring-domain-manager' ); ?></th>
										<th><?php esc_html_e( 'Name', '3ring-domain-manager' ); ?></th>
										<th><?php esc_html_e( 'Priority', '3ring-domain-manager' ); ?></th>
										<th><?php esc_html_e( 'Content', '3ring-domain-manager' ); ?></th>
										<th><?php esc_html_e( 'TTL', '3ring-domain-manager' ); ?></th>
										<th class="dm-dns-records__actions-col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', '3ring-domain-manager' ); ?></span></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $dns_rows as $record ) : ?>
										<tr class="dm-dns-records__row">
											<td>
												<select name="dns_record_type[]" aria-label="<?php esc_attr_e( 'Type', '3ring-domain-manager' ); ?>">
													<?php foreach ( $dns_record_types as $type_key => $type_label ) : ?>
														<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( (string) ( $record->record_type ?? 'A' ), $type_key ); ?>><?php echo esc_html( $type_label ); ?></option>
													<?php endforeach; ?>
												</select>
											</td>
											<td>
												<input type="text" name="dns_record_name[]" value="<?php echo esc_attr( (string) ( $record->name ?? '@' ) ); ?>" placeholder="@" aria-label="<?php esc_attr_e( 'Name', '3ring-domain-manager' ); ?>" />
											</td>
											<td>
												<input type="number" name="dns_record_priority[]" value="<?php echo esc_attr( null !== ( $record->priority ?? null ) && '' !== (string) $record->priority ? (string) $record->priority : '' ); ?>" min="0" step="1" placeholder="—" aria-label="<?php esc_attr_e( 'Priority', '3ring-domain-manager' ); ?>" class="small-text" />
											</td>
											<td>
												<input type="text" name="dns_record_content[]" value="<?php echo esc_attr( (string) ( $record->content ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Value / target', '3ring-domain-manager' ); ?>" aria-label="<?php esc_attr_e( 'Content', '3ring-domain-manager' ); ?>" class="regular-text" />
											</td>
											<td>
												<input type="number" name="dns_record_ttl[]" value="<?php echo esc_attr( null !== ( $record->ttl ?? null ) && '' !== (string) $record->ttl ? (string) $record->ttl : '' ); ?>" min="0" step="1" placeholder="3600" aria-label="<?php esc_attr_e( 'TTL', '3ring-domain-manager' ); ?>" class="small-text" />
											</td>
											<td class="dm-dns-records__actions-col">
												<button type="button" class="button-link dm-dns-records__remove"><?php esc_html_e( 'Remove', '3ring-domain-manager' ); ?></button>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>

						<template id="dm-dns-record-row-template">
							<tr class="dm-dns-records__row">
								<td>
									<select name="dns_record_type[]" aria-label="<?php esc_attr_e( 'Type', '3ring-domain-manager' ); ?>">
										<?php foreach ( $dns_record_types as $type_key => $type_label ) : ?>
											<option value="<?php echo esc_attr( $type_key ); ?>"><?php echo esc_html( $type_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<input type="text" name="dns_record_name[]" value="@" placeholder="@" aria-label="<?php esc_attr_e( 'Name', '3ring-domain-manager' ); ?>" />
								</td>
								<td>
									<input type="number" name="dns_record_priority[]" value="" min="0" step="1" placeholder="—" aria-label="<?php esc_attr_e( 'Priority', '3ring-domain-manager' ); ?>" class="small-text" />
								</td>
								<td>
									<input type="text" name="dns_record_content[]" value="" placeholder="<?php esc_attr_e( 'Value / target', '3ring-domain-manager' ); ?>" aria-label="<?php esc_attr_e( 'Content', '3ring-domain-manager' ); ?>" class="regular-text" />
								</td>
								<td>
									<input type="number" name="dns_record_ttl[]" value="3600" min="0" step="1" placeholder="3600" aria-label="<?php esc_attr_e( 'TTL', '3ring-domain-manager' ); ?>" class="small-text" />
								</td>
								<td class="dm-dns-records__actions-col">
									<button type="button" class="button-link dm-dns-records__remove"><?php esc_html_e( 'Remove', '3ring-domain-manager' ); ?></button>
								</td>
							</tr>
						</template>

						<p class="dm-dns-records__toolbar">
							<button type="button" class="button" id="dm-dns-records-add"><?php esc_html_e( 'Add New Record', '3ring-domain-manager' ); ?></button>
							<?php submit_button( __( 'Save DNS records', '3ring-domain-manager' ), 'primary', 'submit', false ); ?>
						</p>
					</form>
				<?php elseif ( empty( $dns_records ) ) : ?>
					<?php Ui::print_empty_state( __( 'No DNS records recorded yet.', '3ring-domain-manager' ), 'server' ); ?>
				<?php else : ?>
					<?php if ( $dns_provider_label ) : ?>
						<p class="dm-dns-records__provider-readonly">
							<strong><?php esc_html_e( 'Provider', '3ring-domain-manager' ); ?>:</strong>
							<?php echo esc_html( $dns_provider_label ); ?>
						</p>
					<?php endif; ?>
					<div class="dm-dns-records__table-wrap">
						<table class="widefat striped dm-dns-records__table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Type', '3ring-domain-manager' ); ?></th>
									<th><?php esc_html_e( 'Name', '3ring-domain-manager' ); ?></th>
									<th><?php esc_html_e( 'Priority', '3ring-domain-manager' ); ?></th>
									<th><?php esc_html_e( 'Content', '3ring-domain-manager' ); ?></th>
									<th><?php esc_html_e( 'TTL', '3ring-domain-manager' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $dns_records as $record ) : ?>
									<tr>
										<td><code><?php echo esc_html( (string) $record->record_type ); ?></code></td>
										<td><?php echo esc_html( (string) $record->name ); ?></td>
										<td><?php echo esc_html( null !== $record->priority ? (string) $record->priority : '—' ); ?></td>
										<td><code><?php echo esc_html( (string) $record->content ); ?></code></td>
										<td><?php echo esc_html( null !== $record->ttl ? (string) $record->ttl : '—' ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="dm-panel">
			<div class="dm-panel__head">
				<h2><?php Ui::print_icon( 'calendar' ); ?><?php esc_html_e( 'Renewals', '3ring-domain-manager' ); ?></h2>
			</div>
			<?php if ( empty( $renewals ) ) : ?>
				<div class="dm-panel__body">
					<?php Ui::print_empty_state( __( 'No renewals recorded yet.', '3ring-domain-manager' ), 'calendar' ); ?>
				</div>
			<?php else : ?>
				<div class="dm-panel__body dm-panel__body--flush">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Renewed', '3ring-domain-manager' ); ?></th>
							<th><?php esc_html_e( 'Previous expiry', '3ring-domain-manager' ); ?></th>
							<th><?php esc_html_e( 'New expiry', '3ring-domain-manager' ); ?></th>
							<th><?php esc_html_e( 'Cost', '3ring-domain-manager' ); ?></th>
							<th><?php esc_html_e( 'Invoice #', '3ring-domain-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $renewals as $renewal ) : ?>
							<tr>
								<td><?php echo esc_html( $renewal->renewed_on ); ?></td>
								<td><?php echo esc_html( (string) $renewal->previous_expires_on ); ?></td>
								<td><?php echo esc_html( $renewal->new_expires_on ); ?></td>
								<td><?php echo esc_html( null !== $renewal->cost ? $renewal->currency . ' ' . $renewal->cost : '—' ); ?></td>
								<td><?php echo esc_html( (string) $renewal->vendor_invoice_number ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			<?php endif; ?>
		</div>

		<div class="dm-grid">
			<div class="dm-panel">
				<div class="dm-panel__head">
					<h2><?php Ui::print_icon( 'file' ); ?><?php esc_html_e( 'Documents', '3ring-domain-manager' ); ?></h2>
				</div>
				<div class="dm-panel__body">
					<?php if ( empty( $documents ) ) : ?>
						<?php Ui::print_empty_state( __( 'No documents uploaded.', '3ring-domain-manager' ), 'file' ); ?>
					<?php else : ?>
						<ul class="dm-docs">
							<?php foreach ( $documents as $doc ) : ?>
								<li>
									<?php Ui::print_icon( 'file' ); ?>
									<a href="<?php echo esc_url( Document_Service::download_url( (int) $doc->id ) ); ?>">
										<?php echo esc_html( $doc->title ); ?>
									</a>
									<span class="description"><?php echo esc_html( $doc->doc_type ); ?> · <?php echo esc_html( $doc->created_at ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>

			<div class="dm-panel">
				<div class="dm-panel__head">
					<h2><?php Ui::print_icon( 'bell' ); ?><?php esc_html_e( 'Notes', '3ring-domain-manager' ); ?></h2>
				</div>
				<div class="dm-panel__body">
					<?php if ( empty( $notes ) ) : ?>
						<?php Ui::print_empty_state( __( 'No notes yet.', '3ring-domain-manager' ), 'file' ); ?>
					<?php else : ?>
						<?php foreach ( $notes as $note ) : ?>
							<div class="dm-note">
								<p><?php echo esc_html( $note->note_body ); ?></p>
								<p class="description"><?php echo esc_html( $note->created_at ); ?></p>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="dm-panel">
			<div class="dm-panel__head">
				<h2><?php Ui::print_icon( 'clock' ); ?><?php esc_html_e( 'Activity / audit', '3ring-domain-manager' ); ?></h2>
			</div>
			<?php if ( empty( $audit ) ) : ?>
				<div class="dm-panel__body">
					<?php Ui::print_empty_state( __( 'No activity recorded.', '3ring-domain-manager' ), 'clock' ); ?>
				</div>
			<?php else : ?>
				<div class="dm-panel__body dm-panel__body--flush">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'When', '3ring-domain-manager' ); ?></th>
							<th><?php esc_html_e( 'User', '3ring-domain-manager' ); ?></th>
							<th><?php esc_html_e( 'Action', '3ring-domain-manager' ); ?></th>
							<th><?php esc_html_e( 'Field', '3ring-domain-manager' ); ?></th>
							<th><?php esc_html_e( 'Old', '3ring-domain-manager' ); ?></th>
							<th><?php esc_html_e( 'New', '3ring-domain-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $audit as $entry ) : ?>
							<?php
							$user = $entry->user_id ? get_userdata( (int) $entry->user_id ) : null;
							?>
							<tr>
								<td><?php echo esc_html( $entry->created_at ); ?></td>
								<td><?php echo esc_html( $user ? $user->user_login : '—' ); ?></td>
								<td><?php echo esc_html( $entry->action ); ?></td>
								<td><?php echo esc_html( (string) $entry->field_name ); ?></td>
								<td><code><?php echo esc_html( (string) $entry->old_value ); ?></code></td>
								<td><code><?php echo esc_html( (string) $entry->new_value ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
