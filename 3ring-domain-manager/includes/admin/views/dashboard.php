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

$rindoma_breakdowns = array(
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

$rindoma_actions = array(
	array(
		'label' => __( 'View all domains', '3ring-domain-manager' ),
		'url'   => $base,
		'icon'  => 'globe',
	),
);

if ( Capabilities::current_user_can_edit() ) {
	$rindoma_actions[] = array(
		'label' => __( 'Add domain', '3ring-domain-manager' ),
		'url'   => admin_url( 'admin.php?page=rindoma-domain-new' ),
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
			'actions'  => $rindoma_actions,
		)
	);
	?>

	<div class="dm-stats">
		<div class="dm-stat dm-stat--brand">
			<div class="dm-stat__top">
				<p class="dm-stat__value"><?php echo esc_html( (string) $stats['total'] ); ?></p>
				<span class="dm-stat__glyph"><?php Ui::print_icon( 'globe' ); ?></span>
			</div>
			<p class="dm-stat__label"><?php esc_html_e( 'Active domains', '3ring-domain-manager' ); ?></p>
			<a class="dm-stat__link" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'View all →', '3ring-domain-manager' ); ?></a>
		</div>

		<?php
		$rindoma_prev_window = 0;
		foreach ( array( 30, 60, 90 ) as $rindoma_days ) :
			$rindoma_count = (int) $stats['expiring'][ $rindoma_days ];
			$rindoma_tone  = 'ok';
			if ( $rindoma_count > 0 ) {
				$rindoma_tone = 30 === $rindoma_days ? 'danger' : 'warn';
			}
			if ( $rindoma_prev_window > 0 ) {
				$rindoma_label = sprintf(
					/* translators: 1: start day, 2: end day */
					__( 'Expiring in %1$d–%2$d days', '3ring-domain-manager' ),
					$rindoma_prev_window + 1,
					$rindoma_days
				);
			} else {
				$rindoma_label = sprintf(
					/* translators: %d: days */
					__( 'Expiring within %d days', '3ring-domain-manager' ),
					$rindoma_days
				);
			}
			?>
			<div class="dm-stat dm-stat--<?php echo esc_attr( $rindoma_tone ); ?>">
				<div class="dm-stat__top">
					<p class="dm-stat__value"><?php echo esc_html( (string) $rindoma_count ); ?></p>
					<span class="dm-stat__glyph"><?php Ui::print_icon( 'clock' ); ?></span>
				</div>
				<p class="dm-stat__label"><?php echo esc_html( $rindoma_label ); ?></p>
				<a class="dm-stat__link" href="<?php echo esc_url( $base . '&expiry_within_days=' . $rindoma_days ); ?>"><?php esc_html_e( 'Review →', '3ring-domain-manager' ); ?></a>
			</div>
			<?php
			$rindoma_prev_window = $rindoma_days;
		endforeach;
		?>

		<div class="dm-stat dm-stat--<?php echo esc_attr( $stats['review_due'] > 0 ? 'warn' : 'ok' ); ?>">
			<div class="dm-stat__top">
				<p class="dm-stat__value"><?php echo esc_html( (string) $stats['review_due'] ); ?></p>
				<span class="dm-stat__glyph"><?php Ui::print_icon( 'calendar' ); ?></span>
			</div>
			<p class="dm-stat__label"><?php esc_html_e( 'Reviews due / overdue', '3ring-domain-manager' ); ?></p>
			<a class="dm-stat__link" href="<?php echo esc_url( $base . '&review_due=1' ); ?>"><?php esc_html_e( 'Review →', '3ring-domain-manager' ); ?></a>
		</div>

		<div class="dm-stat dm-stat--<?php echo esc_attr( $stats['missing'] > 0 ? 'danger' : 'ok' ); ?>">
			<div class="dm-stat__top">
				<p class="dm-stat__value"><?php echo esc_html( (string) $stats['missing'] ); ?></p>
				<span class="dm-stat__glyph"><?php Ui::print_icon( 'warning' ); ?></span>
			</div>
			<p class="dm-stat__label"><?php esc_html_e( 'Missing required info', '3ring-domain-manager' ); ?></p>
			<span class="dm-stat__link dm-muted"><?php esc_html_e( 'Registrar, expiry or owner', '3ring-domain-manager' ); ?></span>
		</div>
	</div>

	<div class="dm-panel">
		<div class="dm-panel__head">
			<h2><?php Ui::print_icon( 'globe' ); ?><?php esc_html_e( 'Domain portfolio', '3ring-domain-manager' ); ?></h2>
			<a class="dm-panel__meta" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Open full list with filters →', '3ring-domain-manager' ); ?></a>
		</div>
		<div class="dm-panel__body dm-panel__body--flush">
			<?php $domains_table->display(); ?>
		</div>
	</div>

	<div class="dm-grid">
		<?php foreach ( $rindoma_breakdowns as $rindoma_panel ) : ?>
			<?php
			$rindoma_rows = $rindoma_panel['rows'];
			$rindoma_max  = 0;
			foreach ( $rindoma_rows as $rindoma_row ) {
				$rindoma_max = max( $rindoma_max, (int) $rindoma_row['total'] );
			}
			?>
			<div class="dm-panel">
				<div class="dm-panel__head">
					<h2><?php Ui::print_icon( $rindoma_panel['icon'] ); ?><?php echo esc_html( $rindoma_panel['title'] ); ?></h2>
				</div>
				<div class="dm-panel__body">
					<?php if ( empty( $rindoma_rows ) ) : ?>
						<?php Ui::print_empty_state( __( 'Nothing to show yet.', '3ring-domain-manager' ), 'chart' ); ?>
					<?php else : ?>
						<ul class="dm-bars">
							<?php
							foreach ( $rindoma_rows as $rindoma_row ) :
								$rindoma_key   = (string) $rindoma_row['label'];
								$rindoma_label = null !== $rindoma_panel['labels'] ? ( $rindoma_panel['labels'][ $rindoma_key ] ?? $rindoma_key ) : $rindoma_key;
								$rindoma_total = (int) $rindoma_row['total'];
								$rindoma_pct   = $rindoma_max > 0 ? round( ( $rindoma_total / $rindoma_max ) * 100 ) : 0;
								$rindoma_share = $stats['total'] > 0 ? round( ( $rindoma_total / (int) $stats['total'] ) * 100 ) : 0;
								?>
								<li>
									<div class="dm-bars__row">
										<span class="dm-bars__label"><?php echo esc_html( $rindoma_label ); ?></span>
										<span class="dm-bars__value"><strong><?php echo esc_html( (string) $rindoma_total ); ?></strong> · <?php echo esc_html( $rindoma_share . '%' ); ?></span>
									</div>
									<div class="dm-bars__track">
										<span class="dm-bars__fill" style="width: <?php echo esc_attr( (string) $rindoma_pct ); ?>%;"></span>
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
				<h2><?php Ui::print_icon( 'clock' ); ?><?php esc_html_e( 'Recently updated', '3ring-domain-manager' ); ?></h2>
			</div>
			<div class="dm-panel__body">
				<?php if ( empty( $stats['recent'] ) ) : ?>
					<?php Ui::print_empty_state( __( 'No recent changes.', '3ring-domain-manager' ), 'clock' ); ?>
				<?php else : ?>
					<ul class="dm-feed">
						<?php foreach ( $stats['recent'] as $rindoma_recent ) : ?>
							<li>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=rindoma-domains&action=edit&domain_id=' . (int) $rindoma_recent->id ) ); ?>">
									<?php echo esc_html( $rindoma_recent->domain_name ); ?>
								</a>
								<time datetime="<?php echo esc_attr( (string) $rindoma_recent->updated_at ); ?>"><?php echo esc_html( (string) $rindoma_recent->updated_at ); ?></time>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="dm-panel">
		<div class="dm-panel__head">
			<h2><?php Ui::print_icon( 'bell' ); ?><?php esc_html_e( 'Recent alerts', '3ring-domain-manager' ); ?></h2>
			<span class="dm-panel__meta"><?php esc_html_e( 'Latest 10 expiry notifications', '3ring-domain-manager' ); ?></span>
		</div>
		<?php if ( empty( $alerts ) ) : ?>
			<div class="dm-panel__body">
				<?php Ui::print_empty_state( __( 'No alerts yet — every domain is inside its renewal window.', '3ring-domain-manager' ), 'check' ); ?>
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
						foreach ( $alerts as $rindoma_alert ) :
							$rindoma_alert_tone = 'sent' === $rindoma_alert->status ? 'ok' : ( 'failed' === $rindoma_alert->status ? 'danger' : 'info' );
							?>
							<tr>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=rindoma-domains&action=details&domain_id=' . (int) $rindoma_alert->domain_id ) ); ?>">
										<strong><?php echo esc_html( $rindoma_alert->domain_name ?: '#' . $rindoma_alert->domain_id ); ?></strong>
									</a>
								</td>
								<td><?php echo esc_html( str_replace( '_', ' ', (string) $rindoma_alert->alert_type ) ); ?></td>
								<td>
									<?php
									if ( null !== $rindoma_alert->threshold_days ) {
										echo esc_html( sprintf( /* translators: %d: days */ __( '%d days', '3ring-domain-manager' ), (int) $rindoma_alert->threshold_days ) );
									} else {
										Ui::print_muted_dash();
									}
									?>
								</td>
								<td><?php Ui::print_badge( ucfirst( (string) $rindoma_alert->status ), $rindoma_alert_tone ); ?></td>
								<td class="dm-muted"><?php echo esc_html( (string) $rindoma_alert->created_at ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>
