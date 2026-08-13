<?php
/**
 * User profile Domain Manager toggle.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Admin;

use ThreeRing\DomainManager\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Class User_Profile
 */
final class User_Profile {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'show_user_profile', array( $this, 'render_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_fields' ) );
	}

	/**
	 * Whether the current user may edit Domain Manager assignment.
	 */
	private function current_user_can_assign(): bool {
		return Capabilities::current_user_can_admin() || current_user_can( 'edit_users' );
	}

	/**
	 * Render profile section.
	 *
	 * @param \WP_User $user User being edited.
	 */
	public function render_fields( \WP_User $user ): void {
		if ( ! $this->current_user_can_assign() ) {
			return;
		}

		$is_plugin_admin = Capabilities::is_plugin_admin_user( $user );
		$is_manager      = user_can( $user, Capabilities::MANAGE ) && ! $is_plugin_admin;
		?>
		<h2><?php esc_html_e( '3RING Domain Manager', '3ring-domain-manager' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th>
					<label for="rindoma_is_domain_manager">
						<?php esc_html_e( 'Domain Manager', '3ring-domain-manager' ); ?>
					</label>
				</th>
				<td>
					<?php if ( $is_plugin_admin ) : ?>
						<p>
							<strong><?php esc_html_e( 'Plugin Administrator', '3ring-domain-manager' ); ?></strong>
						</p>
						<p class="description">
							<?php esc_html_e( 'This account has full Domain Manager plugin administrator access (granted to the user who activated the plugin). That role is managed by the plugin and cannot be removed with this checkbox.', '3ring-domain-manager' ); ?>
						</p>
					<?php else : ?>
						<label for="rindoma_is_domain_manager">
							<input
								type="checkbox"
								name="rindoma_is_domain_manager"
								id="rindoma_is_domain_manager"
								value="1"
								<?php checked( $is_manager || user_can( $user, Capabilities::MANAGE ) ); ?>
							/>
							<?php esc_html_e( 'Grant Domain Manager access', '3ring-domain-manager' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Allows this user to view and edit domains, record renewals, import/export, manage providers, and receive expiry alert emails.', '3ring-domain-manager' ); ?>
						</p>
						<?php wp_nonce_field( 'rindoma_save_user_profile_' . $user->ID, 'rindoma_user_profile_nonce' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save Domain Manager checkbox.
	 *
	 * @param int $user_id User ID.
	 */
	public function save_fields( int $user_id ): void {
		if ( ! $this->current_user_can_assign() ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		if ( Capabilities::is_plugin_admin_user( $user ) ) {
			Capabilities::grant_plugin_admin();
			return;
		}

		if (
			! isset( $_POST['rindoma_user_profile_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rindoma_user_profile_nonce'] ) ), 'rindoma_save_user_profile_' . $user_id )
		) {
			return;
		}

		$grant = isset( $_POST['rindoma_is_domain_manager'] ) && '1' === $_POST['rindoma_is_domain_manager'];

		if ( $grant ) {
			Capabilities::grant_manager( $user );
		} else {
			Capabilities::revoke_manager( $user );
		}
	}
}
