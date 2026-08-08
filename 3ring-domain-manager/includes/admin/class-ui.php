<?php
/**
 * Shared admin UI components (hero header, sub navigation, badges, icons).
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Admin;

use ThreeRing\DomainManager\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class Ui
 */
final class Ui {

	/**
	 * Inline SVG icon.
	 *
	 * @param string $name  Icon key.
	 * @param string $class Extra CSS classes.
	 */
	public static function icon( string $name, string $class = '' ): string {
		$shapes = array(
			'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.7 3.8 9S14.5 18.4 12 21c-2.5-2.6-3.8-5.7-3.8-9S9.5 5.6 12 3z"/>',
			'grid'     => '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/>',
			'plus'     => '<path d="M12 5v14M5 12h14"/>',
			'server'   => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
			'transfer' => '<path d="M4 8h13l-3.5-3.5M20 16H7l3.5 3.5"/>',
			'settings' => '<circle cx="12" cy="12" r="3.2"/><path d="M12 2.6v2.6M12 18.8v2.6M21.4 12h-2.6M5.2 12H2.6M18.6 5.4l-1.9 1.9M7.3 16.7l-1.9 1.9M18.6 18.6l-1.9-1.9M7.3 7.3 5.4 5.4"/>',
			'bell'     => '<path d="M18 9a6 6 0 1 0-12 0c0 5-2 6.5-2 6.5h16S18 14 18 9z"/><path d="M13.7 19a2 2 0 0 1-3.4 0"/>',
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.3l3.4 2"/>',
			'calendar' => '<rect x="3.5" y="5" width="17" height="16" rx="2.5"/><path d="M3.5 10h17M8.5 3v4M15.5 3v4"/>',
			'check'    => '<circle cx="12" cy="12" r="9"/><path d="m8.2 12.3 2.6 2.6 5-5.4"/>',
			'warning'  => '<path d="M12 3.8 21 19.5H3L12 3.8z"/><path d="M12 10v4M12 17h.01"/>',
			'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
			'file'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/>',
			'external' => '<path d="M14 4h6v6M20 4l-8.5 8.5"/><path d="M18 14.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4.5"/>',
			'chart'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
			'shield'   => '<path d="M12 3.2 19 6v6.2c0 4.2-3 7.5-7 8.6-4-1.1-7-4.4-7-8.6V6l7-2.8z"/><path d="m9 12.2 2.2 2.2 4-4.3"/>',
			'card'     => '<rect x="2.8" y="5" width="18.4" height="14" rx="2.5"/><path d="M2.8 10h18.4M6.5 15h3"/>',
			'link'     => '<path d="M10.5 13.5a4 4 0 0 0 5.7 0l2.6-2.6a4 4 0 0 0-5.7-5.7l-1.2 1.2"/><path d="M13.5 10.5a4 4 0 0 0-5.7 0l-2.6 2.6a4 4 0 1 0 5.7 5.7l1.2-1.2"/>',
			'users'    => '<circle cx="9.5" cy="8.5" r="3.5"/><path d="M3 20c0-3.3 2.9-5.5 6.5-5.5S16 16.7 16 20"/><path d="M17 5.4a3.5 3.5 0 0 1 0 6.6M18.5 14.8c1.6.8 2.5 2.2 2.5 4.2"/>',
		);

		if ( ! isset( $shapes[ $name ] ) ) {
			return '';
		}

		return sprintf(
			'<svg class="dm-icon %1$s" viewBox="0 0 24 24" aria-hidden="true" focusable="false">%2$s</svg>',
			esc_attr( $class ),
			$shapes[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup above.
		);
	}

	/**
	 * Render the branded page header.
	 *
	 * @param array $args {
	 *     @type string $title    Page title.
	 *     @type string $eyebrow  Small label above the title.
	 *     @type string $subtitle Supporting copy.
	 *     @type array  $actions  List of ['label','url','solid'(bool),'icon','target'].
	 *     @type bool   $subnav   Whether to render the sub navigation.
	 * }
	 */
	public static function page_header( array $args ): void {
		$args = array_merge(
			array(
				'title'    => '',
				'eyebrow'  => get_bloginfo( 'name' ),
				'subtitle' => '',
				'actions'  => array(),
				'subnav'   => true,
			),
			$args
		);
		?>
		<div class="dm-hero">
			<div class="dm-hero__inner">
				<div class="dm-hero__title">
					<p class="dm-hero__eyebrow"><?php echo self::icon( 'shield' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $args['eyebrow'] ); ?></p>
					<h1><?php echo esc_html( $args['title'] ); ?></h1>
					<?php if ( $args['subtitle'] ) : ?>
						<p class="dm-hero__sub"><?php echo esc_html( $args['subtitle'] ); ?></p>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $args['actions'] ) ) : ?>
					<div class="dm-hero__actions">
						<?php foreach ( $args['actions'] as $action ) : ?>
							<a
								class="dm-btn <?php echo ! empty( $action['solid'] ) ? 'dm-btn--solid' : ''; ?>"
								href="<?php echo esc_url( $action['url'] ); ?>"
								<?php if ( ! empty( $action['target'] ) ) : ?>
									target="<?php echo esc_attr( $action['target'] ); ?>" rel="noopener noreferrer"
								<?php endif; ?>
							>
								<?php
								if ( ! empty( $action['icon'] ) ) {
									echo self::icon( $action['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}
								echo esc_html( $action['label'] );
								?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<hr class="wp-header-end" />
		<?php
		if ( $args['subnav'] ) {
			self::subnav();
		}
	}

	/**
	 * Render the plugin sub navigation.
	 */
	public static function subnav(): void {
		$current = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$items = array(
			array(
				'slug'  => Admin_Menu::SLUG,
				'label' => __( 'Domains Dashboard', '3ring-domain-manager' ),
				'icon'  => 'grid',
				'can'   => Capabilities::current_user_can_view(),
			),
			array(
				'slug'  => 'dm-domains',
				'label' => __( 'Domain List', '3ring-domain-manager' ),
				'icon'  => 'globe',
				'can'   => Capabilities::current_user_can_view(),
			),
			array(
				'slug'  => 'dm-providers',
				'label' => __( 'Service Providers', '3ring-domain-manager' ),
				'icon'  => 'server',
				'can'   => Capabilities::current_user_can_manage(),
			),
			array(
				'slug'  => 'dm-import-export',
				'label' => __( 'Import / Export', '3ring-domain-manager' ),
				'icon'  => 'transfer',
				'can'   => Capabilities::current_user_can_manage(),
			),
			array(
				'slug'  => 'dm-settings',
				'label' => __( 'Settings', '3ring-domain-manager' ),
				'icon'  => 'settings',
				'can'   => Capabilities::current_user_can_manage(),
			),
		);
		?>
		<nav class="dm-subnav" aria-label="<?php esc_attr_e( 'Domain Manager sections', '3ring-domain-manager' ); ?>">
			<?php
			foreach ( $items as $item ) :
				if ( ! $item['can'] ) {
					continue;
				}
				$is_active = $current === $item['slug'];
				?>
				<a
					href="<?php echo esc_url( admin_url( 'admin.php?page=' . $item['slug'] ) ); ?>"
					class="<?php echo $is_active ? 'is-active' : ''; ?>"
					<?php echo $is_active ? 'aria-current="page"' : ''; ?>
				>
					<?php echo self::icon( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo esc_html( $item['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Badge markup.
	 *
	 * @param string $label Visible label.
	 * @param string $tone  neutral|ok|brand|info|warn|danger.
	 * @param bool   $plain Hide the leading dot.
	 */
	public static function badge( string $label, string $tone = 'neutral', bool $plain = false ): string {
		$classes = 'dm-badge';
		if ( 'neutral' !== $tone ) {
			$classes .= ' dm-badge--' . $tone;
		}
		if ( $plain ) {
			$classes .= ' dm-badge--plain';
		}

		return '<span class="' . esc_attr( $classes ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Tone for a portfolio status key.
	 *
	 * @param string $status Status key.
	 */
	public static function status_tone( string $status ): string {
		$map = array(
			'active'          => 'ok',
			'redirecting'     => 'info',
			'parked'          => 'neutral',
			'reserved'        => 'neutral',
			'planned'         => 'info',
			'for_sale'        => 'warn',
			'retiring'        => 'warn',
			'expired'         => 'danger',
			'transferred_out' => 'danger',
			'archived'        => 'neutral',
		);

		return $map[ $status ] ?? 'neutral';
	}

	/**
	 * Tone for a usage type key.
	 *
	 * @param string $usage Usage key.
	 */
	public static function usage_tone( string $usage ): string {
		$map = array(
			'live_website'      => 'brand',
			'redirect'          => 'info',
			'parked_page'       => 'neutral',
			'email_only'        => 'info',
			'no_active_service' => 'neutral',
			'unknown'           => 'warn',
		);

		return $map[ $usage ] ?? 'neutral';
	}

	/**
	 * Tone for an auto-renew key.
	 *
	 * @param string $auto_renew Auto-renew key.
	 */
	public static function auto_renew_tone( string $auto_renew ): string {
		$map = array(
			'on'      => 'ok',
			'off'     => 'danger',
			'unknown' => 'warn',
		);

		return $map[ $auto_renew ] ?? 'neutral';
	}

	/**
	 * Expiry warning for a domain, matching dashboard dm-stat windows.
	 *
	 * Windows are exclusive: 0–30 (danger), 31–60 (warn), 61–90 (warn).
	 * Overdue domains also return a danger alert. Returns null when outside those windows.
	 *
	 * @param string|null $expires_on Y-m-d expiry date.
	 * @return array{tone: string, window: int, days: int, title: string, detail: string}|null
	 */
	public static function expiry_alert( ?string $expires_on ): ?array {
		if ( null === $expires_on || '' === $expires_on ) {
			return null;
		}

		$days = (int) floor( ( strtotime( $expires_on ) - strtotime( current_time( 'Y-m-d' ) ) ) / DAY_IN_SECONDS );

		if ( $days < 0 ) {
			return array(
				'tone'   => 'danger',
				'window' => 0,
				'days'   => $days,
				'title'  => __( 'Domain expired', '3ring-domain-manager' ),
				'detail' => sprintf(
					/* translators: 1: expiry date, 2: days overdue */
					__( 'Expired on %1$s · %2$d days overdue', '3ring-domain-manager' ),
					$expires_on,
					abs( $days )
				),
			);
		}

		if ( $days <= 30 ) {
			return array(
				'tone'   => 'danger',
				'window' => 30,
				'days'   => $days,
				'title'  => __( 'Expiring within 30 days', '3ring-domain-manager' ),
				'detail' => sprintf(
					/* translators: 1: expiry date, 2: days remaining */
					__( 'Expires on %1$s · in %2$d days', '3ring-domain-manager' ),
					$expires_on,
					$days
				),
			);
		}

		if ( $days <= 60 ) {
			return array(
				'tone'   => 'warn',
				'window' => 60,
				'days'   => $days,
				'title'  => __( 'Expiring in 31–60 days', '3ring-domain-manager' ),
				'detail' => sprintf(
					/* translators: 1: expiry date, 2: days remaining */
					__( 'Expires on %1$s · in %2$d days', '3ring-domain-manager' ),
					$expires_on,
					$days
				),
			);
		}

		if ( $days <= 90 ) {
			return array(
				'tone'   => 'warn',
				'window' => 90,
				'days'   => $days,
				'title'  => __( 'Expiring in 61–90 days', '3ring-domain-manager' ),
				'detail' => sprintf(
					/* translators: 1: expiry date, 2: days remaining */
					__( 'Expires on %1$s · in %2$d days', '3ring-domain-manager' ),
					$expires_on,
					$days
				),
			);
		}

		return null;
	}

	/**
	 * Empty-state block.
	 *
	 * @param string $message Message.
	 * @param string $icon    Icon key.
	 */
	public static function empty_state( string $message, string $icon = 'file' ): string {
		return '<div class="dm-empty">' . self::icon( $icon ) . '<p>' . esc_html( $message ) . '</p></div>';
	}
}
