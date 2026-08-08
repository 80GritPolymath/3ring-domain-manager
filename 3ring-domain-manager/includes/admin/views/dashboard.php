<?php
/**
 * Dashboard view.
 *
 * @package ThreeRing\DomainManager
 *
 * @var array $stats
 * @var array $alerts
 * @var string $base
 * @var \ThreeRing\DomainManager\Admin\Domains_List_Table $domains_table
 */

use ThreeRing\DomainManager\Admin\Ui;
use ThreeRing\DomainManager\Capabilities;
use ThreeRing\DomainManager\Db\Schema;

defined( 'ABSPATH' ) || exit;

$dm_breakdowns = array(
	array(
		'title'  => __( 'By registrar', '3ring-domain-manager' ),
		'icon'   => 'server',
		'rows'   => $stats['by_registrar'],
		'labels' => null,
	),
	array(
		'title'  => __( 'By usage', '3ring-domain-manager' ),
		'icon'   => 'chart',
		'rows'   => $stats['by_usage'],
		'labels' => Schema::usage_types(),
	),
	array(
		'title'  => __( 'By status', '3ring-domain-manager' ),
		'icon'   => 'check',
		'rows'   => $stats['by_status'],
		'labels' => Schema::portfolio_statuses(),
	),
);

$dm_actions = array(
	array(
		'label' => __( 'View all domains', '3ring-domain-manager' ),
		'url'   => $base,
		'icon'  => 'globe',
	),
);

if ( Capabilities::current_user_can_edit() ) {
	$dm_actions[] = array(
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
			'title'    => __( 'Domain Manager', '3ring-domain-manager' ),
			'subtitle' => __( 'Portfolio health, upcoming renewals and registrar coverage at a glance.', '3ring-domain-manager' ),
			'actions'  => $dm_actions,
		)
	);
	?>

	<div class="dm-stats">
		<div class="dm-stat dm-stat--brand">
			<div class="dm-stat__top">
				<p class="dm-stat__value"><?php echo esc_html( (string) $stats['total'] ); ?></p>
				<span class="dm-stat__glyph"><?php echo Ui::icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>
			<p class="dm-stat__label"><?php esc_html_e( 'Active domains', '3ring-domain-manager' ); ?></p>
			<a class="dm-stat__link" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'View all →', '3ring-domain-manager' ); ?></a>
		</div>

		<?php
		$dm_prev_window = 0;
		foreach ( array( 30, 60, 90 ) as $dm_days ) :
			$dm_count = (int) $stats['expiring'][ $dm_days ];
			$dm_tone  = 'ok';
			if ( $dm_count > 0 ) {
				$dm_tone = 30 === $dm_days ? 'danger' : 'warn';
			}
			if ( $dm_prev_window > 0 ) {
				$dm_label = sprintf(
					/* translators: 1: start day, 2: end day */
					__( 'Expiring in %1$d–%2$d days', '3ring-domain-manager' ),
					$dm_prev_window + 1,
					$dm_days
				);
			} else {
				$dm_label = sprintf(
					/* translators: %d: days */
					__( 'Expiring within %d days', '3ring-domain-manager' ),
					$dm_days
				);
			}
			?>
			<div class="dm-stat dm-stat--<?php echo esc_attr( $dm_tone ); ?>">
				<div class="dm-stat__top">
					<p class="dm-stat__value"><?php echo esc_html( (string) $dm_count ); ?></p>
					<span class="dm-stat__glyph"><?php echo Ui::icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<p class="dm-stat__label"><?php echo esc_html( $dm_label ); ?></p>
				<a class="dm-stat__link" href="<?php echo esc_url( $base . '&expiry_within_days=' . $dm_days ); ?>"><?php esc_html_e( 'Review →', '3ring-domain-manager' ); ?></a>
			</div>
			<?php
			$dm_prev_window = $dm_days;
		endforeach;
		?>

		<div class="dm-stat dm-stat--<?php echo $stats['review_due'] > 0 ? 'warn' : 'ok'; ?>">
			<div class="dm-stat__top">
				<p class="dm-stat__value"><?php echo esc_html( (string) $stats['review_due'] ); ?></p>
				<span class="dm-stat__glyph"><?php echo Ui::icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>
			<p class="dm-stat__label"><?php esc_html_e( 'Reviews due / overdue', '3ring-domain-manager' ); ?></p>
			<a class="dm-stat__link" href="<?php echo esc_url( $base . '&review_due=1' ); ?>"><?php esc_html_e( 'Review →', '3ring-domain-manager' ); ?></a>
		</div>

		<div class="dm-stat dm-stat--<?php echo $stats['missing'] > 0 ? 'danger' : 'ok'; ?>">
			<div class="dm-stat__top">
				<p class="dm-stat__value"><?php echo esc_html( (string) $stats['missing'] ); ?></p>
				<span class="dm-stat__glyph"><?php echo Ui::icon( 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>
			<p class="dm-stat__label"><?php esc_html_e( 'Missing required info', '3ring-domain-manager' ); ?></p>
			<span class="dm-stat__link dm-muted"><?php esc_html_e( 'Registrar, expiry or owner', '3ring-domain-manager' ); ?></span>
		</div>
	</div>

	<div class="dm-panel">
		<div class="dm-panel__head">
			<h2><?php echo Ui::icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Domain portfolio', '3ring-domain-manager' ); ?></h2>
			<a class="dm-panel__meta" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Open full list with filters →', '3ring-domain-manager' ); ?></a>
		</div>
		<div class="dm-panel__body dm-panel__body--flush">
			<?php $domains_table->display(); ?>
		</div>
	</div>

	<div class="dm-grid">
		<?php foreach ( $dm_breakdowns as $dm_panel ) : ?>
			<?php
			$dm_rows = $dm_panel['rows'];
			$dm_max  = 0;
			foreach ( $dm_rows as $dm_row ) {
				$dm_max = max( $dm_max, (int) $dm_row['total'] );
			}
			?>
			<div class="dm-panel">
				<div class="dm-panel__head">
					<h2><?php echo Ui::icon( $dm_panel['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $dm_panel['title'] ); ?></h2>
				</div>
				<div class="dm-panel__body">
					<?php if ( empty( $dm_rows ) ) : ?>
						<?php echo Ui::empty_state( __( 'Nothing to show yet.', '3ring-domain-manager' ), 'chart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php else : ?>
						<ul class="dm-bars">
							<?php
							foreach ( $dm_rows as $dm_row ) :
								$dm_key   = (string) $dm_row['label'];
								$dm_label = null !== $dm_panel['labels'] ? ( $dm_panel['labels'][ $dm_key ] ?? $dm_key ) : $dm_key;
								$dm_total = (int) $dm_row['total'];
								$dm_pct   = $dm_max > 0 ? round( ( $dm_total / $dm_max ) * 100 ) : 0;
								$dm_share = $stats['total'] > 0 ? round( ( $dm_total / (int) $stats['total'] ) * 100 ) : 0;
								?>
								<li>
									<div class="dm-bars__row">
										<span class="dm-bars__label"><?php echo esc_html( $dm_label ); ?></span>
										<span class="dm-bars__value"><strong><?php echo esc_html( (string) $dm_total ); ?></strong> · <?php echo esc_html( $dm_share . '%' ); ?></span>
									</div>
									<div class="dm-bars__track">
										<span class="dm-bars__fill" style="width: <?php echo esc_attr( (string) $dm_pct ); ?>%;"></span>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<div class="dm-panel">
			<div class="dm-panel__head">
				<h2><?php echo Ui::icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Recently updated', '3ring-domain-manager' ); ?></h2>
			</div>
			<div class="dm-panel__body">
				<?php if ( empty( $stats['recent'] ) ) : ?>
					<?php echo Ui::empty_state( __( 'No recent changes.', '3ring-domain-manager' ), 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<ul class="dm-feed">
						<?php foreach ( $stats['recent'] as $dm_recent ) : ?>
							<li>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=dm-domains&action=edit&domain_id=' . (int) $dm_recent->id ) ); ?>">
									<?php echo esc_html( $dm_recent->domain_name ); ?>
								</a>
								<time datetime="<?php echo esc_attr( (string) $dm_recent->updated_at ); ?>"><?php echo esc_html( (string) $dm_recent->updated_at ); ?></time>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="dm-panel">
		<div class="dm-panel__head">
			<h2><?php echo Ui::icon( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Recent alerts', '3ring-domain-manager' ); ?></h2>
			<span class="dm-panel__meta"><?php esc_html_e( 'Latest 10 expiry notifications', '3ring-domain-manager' ); ?></span>
		</div>
		<?php if ( empty( $alerts ) ) : ?>
			<div class="dm-panel__body">
				<?php echo Ui::empty_state( __( 'No alerts yet — every domain is inside its renewal window.', '3ring-domain-manager' ), 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php else : ?>
			<div class="dm-panel__body dm-panel__body--flush">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Domain', '3ring-domain-manager' ); ?></th>
							<th><?php esc_html_e( 'Type', '3ring-domain-manager' ); ?></th>
							<th><?php esc_html_e( 'Threshold', '3ring-domain-manager' ); ?></th>
							<th><?php esc_html_e( 'Status', '3ring-domain-manager' ); ?></th>
							<th><?php esc_html_e( 'Created', '3ring-domain-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $alerts as $dm_alert ) :
							$dm_alert_tone = 'sent' === $dm_alert->status ? 'ok' : ( 'failed' === $dm_alert->status ? 'danger' : 'info' );
							?>
							<tr>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=dm-domains&action=details&domain_id=' . (int) $dm_alert->domain_id ) ); ?>">
										<strong><?php echo esc_html( $dm_alert->domain_name ?: '#' . $dm_alert->domain_id ); ?></strong>
									</a>
								</td>
								<td><?php echo esc_html( str_replace( '_', ' ', (string) $dm_alert->alert_type ) ); ?></td>
								<td>
									<?php
									echo null !== $dm_alert->threshold_days
										? esc_html( sprintf( /* translators: %d: days */ __( '%d days', '3ring-domain-manager' ), (int) $dm_alert->threshold_days ) )
										: '<span class="dm-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									?>
								</td>
								<td><?php echo Ui::badge( ucfirst( (string) $dm_alert->status ), $dm_alert_tone ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td class="dm-muted"><?php echo esc_html( (string) $dm_alert->created_at ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>
