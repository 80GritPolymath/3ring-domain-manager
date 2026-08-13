<?php
/**
 * Settings page.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Admin;

use ThreeRing\DomainManager\Capabilities;
use ThreeRing\DomainManager\Services\Alert_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings_Page
 */
final class Settings_Page {

	/**
	 * Save settings.
	 */
	public static function maybe_handle_save(): void {
		if ( empty( $_POST['rindoma_save_settings'] ) ) {
			return;
		}

		if ( ! isset( $_GET['page'] ) || 'rindoma-settings' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', '3ring-domain-manager' ) );
		}

		check_admin_referer( 'rindoma_save_settings' );

		$windows_raw = isset( $_POST['alert_windows'] ) ? sanitize_text_field( wp_unslash( $_POST['alert_windows'] ) ) : '90,60,30';
		$windows     = array_values(
			array_filter(
				array_map(
					static function ( $part ) {
						return absint( trim( $part ) );
					},
					explode( ',', $windows_raw )
				)
			)
		);

		if ( ! $windows ) {
			$windows = array( 90, 60, 30 );
		}

		$settings = array(
			'alert_windows'            => $windows,
			'review_interval_days'     => isset( $_POST['review_interval_days'] ) ? absint( $_POST['review_interval_days'] ) : 180,
			'default_currency'         => isset( $_POST['default_currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['default_currency'] ) ) ) : 'CAD',
			'drop_tables_on_uninstall' => ! empty( $_POST['drop_tables_on_uninstall'] ),
			'max_upload_mb'            => isset( $_POST['max_upload_mb'] ) ? absint( $_POST['max_upload_mb'] ) : 10,
			'brand_color'              => Brand::sanitize( isset( $_POST['brand_color'] ) ? wp_unslash( $_POST['brand_color'] ) : Brand::DEFAULT_COLOR ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		);

		update_option( 'rindoma_settings', $settings, false );
		wp_safe_redirect( admin_url( 'admin.php?page=rindoma-settings&message=saved' ) );
		exit;
	}

	/**
	 * Send a test notification email.
	 */
	public static function maybe_handle_test_email(): void {
		if ( empty( $_POST['rindoma_test_email'] ) ) {
			return;
		}

		if ( ! isset( $_GET['page'] ) || 'rindoma-settings' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', '3ring-domain-manager' ) );
		}

		check_admin_referer( 'rindoma_test_email' );

		$result = ( new Alert_Service() )->send_test_email();
		set_transient( 'rindoma_test_email_result_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'admin.php?page=rindoma-settings&message=test_email' ) );
		exit;
	}

	/**
	 * Render settings.
	 */
	public static function render(): void {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', '3ring-domain-manager' ) );
		}

		$settings = wp_parse_args(
			get_option( 'rindoma_settings', array() ),
			array(
				'alert_windows'            => array( 90, 60, 30 ),
				'review_interval_days'     => 180,
				'default_currency'         => 'CAD',
				'drop_tables_on_uninstall' => false,
				'max_upload_mb'            => 10,
				'brand_color'              => Brand::DEFAULT_COLOR,
			)
		);
		$settings['brand_color'] = Brand::sanitize( $settings['brand_color'] );

		$managers = Capabilities::get_manager_users();
		$message  = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$test_result = null;
		if ( 'test_email' === $message ) {
			$test_result = get_transient( 'rindoma_test_email_result_' . get_current_user_id() );
			delete_transient( 'rindoma_test_email_result_' . get_current_user_id() );
		}
		?>
		<div class="wrap dm-wrap">
			<?php
			Ui::page_header(
				array(
					'title'    => __( 'Settings', '3ring-domain-manager' ),
					'subtitle' => __( 'Branding, alert windows, review cadence and housekeeping for Domain Manager.', '3ring-domain-manager' ),
				)
			);
			?>
			<?php if ( 'saved' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', '3ring-domain-manager' ); ?></p></div>
			<?php endif; ?>
			<?php if ( is_array( $test_result ) && isset( $test_result['status'] ) ) : ?>
				<?php if ( 'sent' === $test_result['status'] ) : ?>
					<div class="notice notice-success is-dismissible">
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: comma-separated email addresses */
									__( 'Test email sent via wp_mail() to: %s', '3ring-domain-manager' ),
									implode( ', ', array_map( 'strval', $test_result['emails'] ?? array() ) )
								)
							);
							?>
						</p>
						<p><?php esc_html_e( 'Check those inboxes (and spam). Subject starts with [Domain Manager].', '3ring-domain-manager' ); ?></p>
					</div>
				<?php elseif ( 'no_recipients' === $test_result['status'] ) : ?>
					<div class="notice notice-warning is-dismissible">
						<p><?php esc_html_e( 'No Domain Manager email addresses found. Grant Domain Manager access to at least one user, then try again.', '3ring-domain-manager' ); ?></p>
					</div>
				<?php else : ?>
					<div class="notice notice-error is-dismissible">
						<p><?php esc_html_e( 'wp_mail() returned false — WordPress could not hand the message to the mail system. Check hosting mail config or an SMTP plugin.', '3ring-domain-manager' ); ?></p>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'rindoma_save_settings' ); ?>
				<input type="hidden" name="rindoma_save_settings" value="1" />
				<div class="dm-panel">
					<div class="dm-panel__head">
						<h2><?php Ui::print_icon( 'settings' ); ?><?php esc_html_e( 'General', '3ring-domain-manager' ); ?></h2>
					</div>
					<div class="dm-panel__body">
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="brand_color"><?php esc_html_e( 'Brand color', '3ring-domain-manager' ); ?></label></th>
						<td>
							<input type="text" name="brand_color" id="brand_color" class="dm-brand-color" value="<?php echo esc_attr( $settings['brand_color'] ); ?>" data-default-color="<?php echo esc_attr( Brand::DEFAULT_COLOR ); ?>" />
							<p class="description"><?php esc_html_e( 'Primary accent used across Domain Manager admin screens. Related shades are derived automatically.', '3ring-domain-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="alert_windows"><?php esc_html_e( 'Expiry alert windows (days)', '3ring-domain-manager' ); ?></label></th>
						<td>
							<input type="text" name="alert_windows" id="alert_windows" class="regular-text" value="<?php echo esc_attr( implode( ',', array_map( 'strval', $settings['alert_windows'] ) ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Comma-separated. Default: 90,60,30. Emails go to all Domain Managers.', '3ring-domain-manager' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="review_interval_days"><?php esc_html_e( 'Review interval (days)', '3ring-domain-manager' ); ?></label></th>
						<td><input type="number" name="review_interval_days" id="review_interval_days" value="<?php echo esc_attr( (string) $settings['review_interval_days'] ); ?>" min="1" /></td>
					</tr>
					<tr>
						<th><label for="default_currency"><?php esc_html_e( 'Default currency', '3ring-domain-manager' ); ?></label></th>
						<td><input type="text" name="default_currency" id="default_currency" value="<?php echo esc_attr( $settings['default_currency'] ); ?>" maxlength="3" class="small-text" /></td>
					</tr>
					<tr>
						<th><label for="max_upload_mb"><?php esc_html_e( 'Max upload size (MB)', '3ring-domain-manager' ); ?></label></th>
						<td><input type="number" name="max_upload_mb" id="max_upload_mb" value="<?php echo esc_attr( (string) $settings['max_upload_mb'] ); ?>" min="1" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Uninstall', '3ring-domain-manager' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="drop_tables_on_uninstall" value="1" <?php checked( ! empty( $settings['drop_tables_on_uninstall'] ) ); ?> />
								<?php esc_html_e( 'Drop Domain Manager tables when the plugin is deleted', '3ring-domain-manager' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save settings', '3ring-domain-manager' ) ); ?>
					</div>
				</div>
			</form>

			<div class="dm-panel">
				<div class="dm-panel__head">
					<h2><?php Ui::print_icon( 'bell' ); ?><?php esc_html_e( 'Email notifications', '3ring-domain-manager' ); ?></h2>
					<span class="dm-panel__meta"><?php esc_html_e( 'Verify that WordPress can deliver Domain Manager alerts', '3ring-domain-manager' ); ?></span>
				</div>
				<div class="dm-panel__body">
					<p class="description">
						<?php esc_html_e( 'Sends a test message to all Domain Managers using the same wp_mail() path as expiry and review alerts. No fake domain is required.', '3ring-domain-manager' ); ?>
					</p>
					<form method="post">
						<?php wp_nonce_field( 'rindoma_test_email' ); ?>
						<input type="hidden" name="rindoma_test_email" value="1" />
						<?php submit_button( __( 'Send test email', '3ring-domain-manager' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			</div>

			<div class="dm-panel">
				<div class="dm-panel__head">
					<h2><?php Ui::print_icon( 'users' ); ?><?php esc_html_e( 'Current Domain Managers', '3ring-domain-manager' ); ?></h2>
					<span class="dm-panel__meta"><?php esc_html_e( 'Access is granted on each user’s Edit User screen', '3ring-domain-manager' ); ?></span>
				</div>
				<div class="dm-panel__body">
					<?php if ( empty( $managers ) ) : ?>
						<?php Ui::print_empty_state( __( 'No users currently have Domain Manager capabilities.', '3ring-domain-manager' ), 'users' ); ?>
					<?php else : ?>
						<ul class="dm-feed">
							<?php foreach ( $managers as $user ) : ?>
								<li>
									<span>
										<strong><?php echo esc_html( $user->display_name ); ?></strong>
										<span class="dm-cell-sub"><?php echo esc_html( $user->user_login ); ?> · <?php echo esc_html( $user->user_email ); ?></span>
									</span>
									<?php if ( Capabilities::is_plugin_admin_user( $user ) ) : ?>
										<?php Ui::print_badge( __( 'Plugin Administrator', '3ring-domain-manager' ), 'brand' ); ?>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
