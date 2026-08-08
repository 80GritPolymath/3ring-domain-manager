<?php
/**
 * Frontend [domain-list] shortcode.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Frontend;

use ThreeRing\DomainManager\Db\Domains_Repository;
use ThreeRing\DomainManager\Db\Providers_Repository;
use ThreeRing\DomainManager\Db\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class Domain_List_Shortcode
 */
final class Domain_List_Shortcode {

	/**
	 * Whether critical CSS was printed this request.
	 *
	 * @var bool
	 */
	private static $styles_printed = false;

	/**
	 * Register shortcode and assets.
	 */
	public function register(): void {
		add_shortcode( 'domain-list', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register (but do not always enqueue) frontend assets.
	 */
	public function register_assets(): void {
		wp_register_style(
			'dm-domain-list',
			DM_PLUGIN_URL . 'assets/css/domain-list.css',
			array(),
			DM_VERSION
		);

		wp_register_script(
			'dm-domain-list',
			DM_PLUGIN_URL . 'assets/js/domain-list.js',
			array(),
			DM_VERSION,
			true
		);
	}

	/**
	 * Critical CSS printed with the shortcode so theme/Elementor cannot override it via cache order.
	 */
	private function critical_css(): string {
		return <<<'CSS'
.dm-domain-list{margin:1.5rem 0;overflow-x:auto}
.dm-domain-list table.dm-domain-list-table{width:100%;border-collapse:collapse;font-size:.95rem}
.dm-domain-list table.dm-domain-list-table th,
.dm-domain-list table.dm-domain-list-table td{border:1px solid #d0d0d0;padding:.6rem .75rem;text-align:left;vertical-align:top;background:#fff;color:#111}
.dm-domain-list table.dm-domain-list-table thead th{background:#f3f3f3 !important;color:#111 !important;font-weight:600}
.dm-domain-list table.dm-domain-list-table tbody tr:nth-child(even) td{background:#fafafa}
.dm-domain-list table.dm-domain-list-table thead th.dm-th-sort{cursor:pointer;user-select:none;background:#f3f3f3 !important;color:#111 !important}
.dm-domain-list table.dm-domain-list-table thead th.dm-th-sort:hover,
.dm-domain-list table.dm-domain-list-table thead th.dm-th-sort.dm-th-sort-hover{background:#e9e9e9 !important;color:#111 !important}
.dm-domain-list table.dm-domain-list-table thead th.dm-th-sort::after{content:" \2195";font-weight:400;opacity:.45}
.dm-domain-list table.dm-domain-list-table thead th.dm-th-sort.dm-th-sort-asc::after{content:" \2191";opacity:1}
.dm-domain-list table.dm-domain-list-table thead th.dm-th-sort.dm-th-sort-desc::after{content:" \2193";opacity:1}
CSS;
	}

	/**
	 * Render shortcode output.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function render( $atts = array() ): string {
		unset( $atts );

		if ( ! Schema::tables_exist() ) {
			return '';
		}

		wp_enqueue_style( 'dm-domain-list' );
		wp_enqueue_script( 'dm-domain-list' );

		$repo   = new Domains_Repository();
		$result = $repo->query(
			array(
				'per_page' => 500,
				'page'     => 1,
				'orderby'  => 'domain_name',
				'order'    => 'ASC',
			)
		);

		$providers = new Providers_Repository();
		$map       = array();
		foreach ( $providers->list_all( 'registrar' ) as $provider ) {
			$map[ (int) $provider->id ] = $provider->name;
		}

		$status_labels = Schema::portfolio_statuses();
		$usage_labels  = Schema::usage_types();
		$renew_labels  = Schema::auto_renew_statuses();

		$th_style = 'background-color:#f3f3f3!important;color:#111!important;';

		ob_start();

		if ( ! self::$styles_printed ) {
			self::$styles_printed = true;
			echo '<style id="dm-domain-list-critical">' . $this->critical_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
		<div class="dm-domain-list">
			<table class="dm-domain-list-table" data-dm-sortable="1">
				<thead>
					<tr>
						<th scope="col" class="dm-th-sort dm-th-sort-asc" data-sort-key="domain" data-sort-type="text" style="<?php echo esc_attr( $th_style ); ?>">
							<?php echo esc_html__( 'Domain Name', '3ring-domain-manager' ); ?>
						</th>
						<th scope="col" style="<?php echo esc_attr( $th_style ); ?>"><?php echo esc_html__( 'Status', '3ring-domain-manager' ); ?></th>
						<th scope="col" style="<?php echo esc_attr( $th_style ); ?>"><?php echo esc_html__( 'Usage', '3ring-domain-manager' ); ?></th>
						<th scope="col" class="dm-th-sort" data-sort-key="registrar" data-sort-type="text" style="<?php echo esc_attr( $th_style ); ?>">
							<?php echo esc_html__( 'Registrar', '3ring-domain-manager' ); ?>
						</th>
						<th scope="col" class="dm-th-sort" data-sort-key="expires" data-sort-type="date" style="<?php echo esc_attr( $th_style ); ?>">
							<?php echo esc_html__( 'Expires', '3ring-domain-manager' ); ?>
						</th>
						<th scope="col" style="<?php echo esc_attr( $th_style ); ?>"><?php echo esc_html__( 'Auto Renew', '3ring-domain-manager' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $result['items'] ) ) : ?>
						<tr>
							<td colspan="6"><?php echo esc_html__( 'No domains found.', '3ring-domain-manager' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $result['items'] as $domain ) : ?>
							<?php
							$registrar = $map[ (int) $domain->registrar_id ] ?? '—';
							$expires   = $domain->expires_on ? (string) $domain->expires_on : '';
							?>
							<tr>
								<td data-sort-value="<?php echo esc_attr( strtolower( (string) $domain->domain_name ) ); ?>"><?php echo esc_html( $domain->domain_name ); ?></td>
								<td><?php echo esc_html( $status_labels[ $domain->portfolio_status ] ?? $domain->portfolio_status ); ?></td>
								<td><?php echo esc_html( $usage_labels[ $domain->usage_type ] ?? $domain->usage_type ); ?></td>
								<td data-sort-value="<?php echo esc_attr( strtolower( $registrar ) ); ?>"><?php echo esc_html( $registrar ); ?></td>
								<td data-sort-value="<?php echo esc_attr( $expires ); ?>"><?php echo esc_html( $expires ? $expires : '—' ); ?></td>
								<td><?php echo esc_html( $renew_labels[ $domain->auto_renew_status ] ?? $domain->auto_renew_status ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
