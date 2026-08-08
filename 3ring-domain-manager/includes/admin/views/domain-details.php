<?php
/**
 * Domain details (read-only) view.
 *
 * @package ThreeRing\DomainManager
 *
 * @var object $domain
 * @var int $domain_id
 * @var bool $can_edit
 * @var string $registrar_name
 * @var string $dns_name
 * @var string $hosting_name
 * @var string $email_name
 * @var object[] $dns_records
 * @var string $dns_records_provider_name
 */

use ThreeRing\DomainManager\Admin\Ui;
use ThreeRing\DomainManager\Db\Domains_Repository;
use ThreeRing\DomainManager\Db\Schema;

defined( 'ABSPATH' ) || exit;

$val = static function ( $field, $default = '' ) use ( $domain ) {
	return isset( $domain->$field ) && null !== $domain->$field && '' !== (string) $domain->$field
		? (string) $domain->$field
		: $default;
};

$label_or_empty = static function ( $value ) {
	if ( '' === $value || null === $value ) {
		return '<span class="dm-detail-empty">' . esc_html__( '—', '3ring-domain-manager' ) . '</span>';
	}

	return '<span class="dm-detail-value">' . esc_html( (string) $value ) . '</span>';
};

$url_or_empty = static function ( $value ) {
	if ( '' === $value || null === $value ) {
		return '<span class="dm-detail-empty">' . esc_html__( '—', '3ring-domain-manager' ) . '</span>';
	}

	$url = (string) $value;

	return '<span class="dm-detail-value"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a></span>';
};

$portfolio_labels = Schema::portfolio_statuses();
$usage_labels     = Schema::usage_types();
$importance_labels = Schema::importance_levels();
$auto_renew_labels = Schema::auto_renew_statuses();

$portfolio_status = $val( 'portfolio_status' );
$usage_type       = $val( 'usage_type' );
$importance       = $val( 'business_importance', 'standard' );
$auto_renew       = $val( 'auto_renew_status', 'unknown' );

$edit_url  = admin_url( 'admin.php?page=dm-domains&action=edit&domain_id=' . $domain_id );
$visit_url = Domains_Repository::visit_url( $domain );

$registrar_account_id = '';
$registrar_mgmt_url   = '';
if ( $registrar ) {
	$registrar_account_id = ! empty( $registrar->account_id ) ? (string) $registrar->account_id : '';
	$registrar_mgmt_url   = ! empty( $registrar->management_url ) ? (string) $registrar->management_url : '';
}
if ( '' === $registrar_account_id && ! empty( $domain->registrar_account_reference ) ) {
	$registrar_account_id = (string) $domain->registrar_account_reference;
}
if ( '' === $registrar_mgmt_url && ! empty( $domain->registrar_management_url ) ) {
	$registrar_mgmt_url = (string) $domain->registrar_management_url;
}

$dm_header_actions = array();

if ( $can_edit ) {
	$dm_header_actions[] = array(
		'label' => __( 'Edit Domain', '3ring-domain-manager' ),
		'url'   => $edit_url,
		'icon'  => 'settings',
		'solid' => true,
	);
}

if ( $registrar_mgmt_url ) {
	$dm_header_actions[] = array(
		'label'  => __( 'Registrar management', '3ring-domain-manager' ),
		'url'    => $registrar_mgmt_url,
		'icon'   => 'external',
		'target' => '_blank',
	);
}

if ( $visit_url ) {
	$dm_header_actions[] = array(
		'label'  => __( 'Visit site', '3ring-domain-manager' ),
		'url'    => $visit_url,
		'icon'   => 'external',
		'target' => '_blank',
	);
}

$dm_header_actions[] = array(
	'label' => __( 'Back to domains', '3ring-domain-manager' ),
	'url'   => admin_url( 'admin.php?page=dm-domains' ),
	'icon'  => 'globe',
);

$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$notices = array(
	'created'   => __( 'Domain created.', '3ring-domain-manager' ),
	'updated'   => __( 'Domain updated.', '3ring-domain-manager' ),
	'dns_saved' => __( 'DNS records saved.', '3ring-domain-manager' ),
);
?>
<div class="wrap dm-wrap">
	<?php
	Ui::page_header(
		array(
			'title'    => $domain->domain_name ?? __( 'Domain Details', '3ring-domain-manager' ),
			'subtitle' => __( 'Read-only view of registration, DNS and ownership details.', '3ring-domain-manager' ),
			'actions'  => $dm_header_actions,
		)
	);
	?>

	<?php if ( isset( $notices[ $message ] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notices[ $message ] ); ?></p></div>
	<?php endif; ?>

	<?php
	$expiry_alert = Ui::expiry_alert( isset( $domain->expires_on ) ? (string) $domain->expires_on : null );
	if ( $expiry_alert ) :
		$expiry_icon = 'danger' === $expiry_alert['tone'] ? 'warning' : 'clock';
		?>
		<div class="dm-expiry-banner dm-expiry-banner--<?php echo esc_attr( $expiry_alert['tone'] ); ?>" role="status">
			<span class="dm-expiry-banner__glyph"><?php echo Ui::icon( $expiry_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div class="dm-expiry-banner__body">
				<p class="dm-expiry-banner__title"><?php echo esc_html( $expiry_alert['title'] ); ?></p>
				<p class="dm-expiry-banner__detail"><?php echo esc_html( $expiry_alert['detail'] ); ?></p>
			</div>
			<?php if ( $registrar_mgmt_url ) : ?>
				<a class="dm-expiry-banner__action" href="<?php echo esc_url( $registrar_mgmt_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo Ui::icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php esc_html_e( 'Renew at registrar', '3ring-domain-manager' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="dm-grid dm-grid--wide">
		<fieldset class="dm-fieldset">
			<legend><?php esc_html_e( 'Identity & purpose', '3ring-domain-manager' ); ?></legend>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Domain name', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $val( 'domain_name' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Display name', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $val( 'display_name' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Portfolio status', '3ring-domain-manager' ); ?></th>
					<td>
						<?php
						echo Ui::badge( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							$portfolio_labels[ $portfolio_status ] ?? $portfolio_status,
							Ui::status_tone( $portfolio_status )
						);
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Usage type', '3ring-domain-manager' ); ?></th>
					<td>
						<?php
						echo Ui::badge( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							$usage_labels[ $usage_type ] ?? $usage_type,
							Ui::usage_tone( $usage_type )
						);
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Business purpose', '3ring-domain-manager' ); ?></th>
					<td>
						<?php if ( $val( 'business_purpose' ) ) : ?>
							<span class="dm-detail-value dm-detail-pre"><?php echo esc_html( $val( 'business_purpose' ) ); ?></span>
						<?php else : ?>
							<span class="dm-detail-empty"><?php esc_html_e( '—', '3ring-domain-manager' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Importance', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $importance_labels[ $importance ] ?? $importance ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Internal owner', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $val( 'internal_owner' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Technical owner', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $val( 'technical_owner' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Tags', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $val( 'tags' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
			</table>
		</fieldset>

		<fieldset class="dm-fieldset">
			<legend><?php esc_html_e( 'Registration', '3ring-domain-manager' ); ?></legend>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Registrar', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $registrar_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Account#/ID', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $registrar_account_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Management URL', '3ring-domain-manager' ); ?></th>
					<td><?php echo $url_or_empty( $registrar_mgmt_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Registered on', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $val( 'registered_on' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Expires on', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $val( 'expires_on' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last renewed on', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $val( 'last_renewed_on' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Auto-renew', '3ring-domain-manager' ); ?></th>
					<td>
						<?php
						echo Ui::badge( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							$auto_renew_labels[ $auto_renew ] ?? $auto_renew,
							Ui::auto_renew_tone( $auto_renew )
						);
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Active Card', '3ring-domain-manager' ); ?></th>
					<td>
						<?php if ( $val( 'active_card' ) ) : ?>
							<span class="dm-detail-value">•••• <?php echo esc_html( $val( 'active_card' ) ); ?></span>
						<?php else : ?>
							<span class="dm-detail-empty"><?php esc_html_e( '—', '3ring-domain-manager' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last manually verified', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $val( 'last_manually_verified_on' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Next review due', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $val( 'next_review_due_on' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
			</table>
		</fieldset>

		<fieldset class="dm-fieldset">
			<legend><?php esc_html_e( 'DNS, website & email', '3ring-domain-manager' ); ?></legend>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'DNS provider', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $dns_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Nameservers', '3ring-domain-manager' ); ?></th>
					<td>
						<?php if ( $val( 'nameservers' ) ) : ?>
							<pre class="dm-detail-value dm-detail-pre"><?php echo esc_html( $val( 'nameservers' ) ); ?></pre>
						<?php else : ?>
							<span class="dm-detail-empty"><?php esc_html_e( '—', '3ring-domain-manager' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Hosting provider', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $hosting_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Email provider', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $email_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Used for email', '3ring-domain-manager' ); ?></th>
					<td>
						<span class="dm-detail-value">
							<?php echo '1' === $val( 'used_for_email', '0' ) ? esc_html__( 'Yes', '3ring-domain-manager' ) : esc_html__( 'No', '3ring-domain-manager' ); ?>
						</span>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Primary URL', '3ring-domain-manager' ); ?></th>
					<td><?php echo $url_or_empty( $val( 'primary_url' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Expected redirect URL', '3ring-domain-manager' ); ?></th>
					<td><?php echo $url_or_empty( $val( 'expected_redirect_url' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Redirect type', '3ring-domain-manager' ); ?></th>
					<td><?php echo $label_or_empty( $val( 'redirect_type' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
			</table>
		</fieldset>
	</div>

	<div class="dm-panel dm-dns-records">
		<div class="dm-panel__head">
			<h2><?php echo Ui::icon( 'server' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'DNS Records', '3ring-domain-manager' ); ?></h2>
		</div>
		<div class="dm-panel__body">
			<?php if ( empty( $dns_records ) ) : ?>
				<?php echo Ui::empty_state( __( 'No DNS records recorded yet.', '3ring-domain-manager' ), 'server' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php else : ?>
				<?php if ( $dns_records_provider_name ) : ?>
					<p class="dm-dns-records__provider-readonly">
						<strong><?php esc_html_e( 'Provider', '3ring-domain-manager' ); ?>:</strong>
						<?php echo esc_html( $dns_records_provider_name ); ?>
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
</div>
