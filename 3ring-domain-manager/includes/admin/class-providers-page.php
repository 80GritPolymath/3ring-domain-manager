<?php
/**
 * Providers admin page.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Admin;

use ThreeRing\DomainManager\Capabilities;
use ThreeRing\DomainManager\Db\Providers_Repository;
use ThreeRing\DomainManager\Db\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class Providers_Page
 */
final class Providers_Page {

	/**
	 * Handle create/update/delete.
	 */
	public static function maybe_handle_actions(): void {
		if ( ! isset( $_GET['page'] ) || 'dm-providers' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! Capabilities::current_user_can_manage() ) {
			return;
		}

		if ( ! empty( $_POST['dm_save_provider'] ) ) {
			check_admin_referer( 'dm_save_provider' );
			$repo = new Providers_Repository();
			$data = array(
				'name'           => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
				'provider_type'  => isset( $_POST['provider_types'] ) && is_array( $_POST['provider_types'] )
					? array_map( 'sanitize_key', wp_unslash( $_POST['provider_types'] ) )
					: array(),
				'account_id'     => isset( $_POST['account_id'] ) ? sanitize_text_field( wp_unslash( $_POST['account_id'] ) ) : '',
				'account_email'  => isset( $_POST['account_email'] ) ? sanitize_email( wp_unslash( $_POST['account_email'] ) ) : '',
				'website_url'    => isset( $_POST['website_url'] ) ? esc_url_raw( wp_unslash( $_POST['website_url'] ) ) : '',
				'management_url' => isset( $_POST['management_url'] ) ? esc_url_raw( wp_unslash( $_POST['management_url'] ) ) : '',
				'notes'          => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			);

			if ( empty( $data['provider_type'] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=dm-providers&message=type_required' . ( ! empty( $_POST['provider_id'] ) ? '&edit=' . absint( $_POST['provider_id'] ) : '' ) ) );
				exit;
			}

			$id = isset( $_POST['provider_id'] ) ? absint( $_POST['provider_id'] ) : 0;
			if ( $id ) {
				$repo->update( $id, $data );
			} else {
				$repo->insert( $data );
			}

			wp_safe_redirect( admin_url( 'admin.php?page=dm-providers&message=saved' ) );
			exit;
		}

		if ( isset( $_GET['action'], $_GET['provider_id'] ) && 'delete' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$id = absint( $_GET['provider_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'dm_delete_provider_' . $id );
			( new Providers_Repository() )->delete( $id );
			wp_safe_redirect( admin_url( 'admin.php?page=dm-providers&message=deleted' ) );
			exit;
		}
	}

	/**
	 * Render providers page.
	 */
	public static function render(): void {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', '3ring-domain-manager' ) );
		}

		if ( ! Schema::tables_exist() ) {
			echo '<div class="wrap"><h1>Providers</h1><div class="notice notice-error"><p>' . esc_html__( 'Database tables are missing.', '3ring-domain-manager' ) . '</p></div></div>';
			return;
		}

		$repo      = new Providers_Repository();
		$providers = $repo->list_all();
		$edit_id   = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$editing   = $edit_id ? $repo->get( $edit_id ) : null;
		$types     = Schema::provider_types();
		$message   = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_types = $editing ? Providers_Repository::parse_types( $editing->provider_type ?? '' ) : array( 'registrar' );
		?>
		<div class="wrap dm-wrap">
			<?php
			Ui::page_header(
				array(
					'title'    => __( 'Providers', '3ring-domain-manager' ),
					'subtitle' => __( 'Registrars, DNS, hosting and email vendors used across the portfolio.', '3ring-domain-manager' ),
				)
			);
			?>
			<?php if ( 'type_required' === $message ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Please select at least one provider type.', '3ring-domain-manager' ); ?></p></div>
			<?php elseif ( $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( ucfirst( $message ) ); ?></p></div>
			<?php endif; ?>

			<div class="dm-grid dm-grid--providers">
				<div class="dm-panel dm-panel--providers-list">
					<div class="dm-panel__head">
						<h2><?php echo Ui::icon( 'server' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'All providers', '3ring-domain-manager' ); ?></h2>
						<span class="dm-panel__meta">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: provider count */
									_n( '%d provider', '%d providers', count( $providers ), '3ring-domain-manager' ),
									count( $providers )
								)
							);
							?>
						</span>
					</div>
					<div class="dm-panel__body dm-panel__body--flush">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', '3ring-domain-manager' ); ?></th>
								<th><?php esc_html_e( 'Type', '3ring-domain-manager' ); ?></th>
								<th><?php esc_html_e( 'Account#/ID', '3ring-domain-manager' ); ?></th>
								<th><?php esc_html_e( 'Account Email', '3ring-domain-manager' ); ?></th>
								<th><?php esc_html_e( 'Management', '3ring-domain-manager' ); ?></th>
								<th><?php esc_html_e( 'Actions', '3ring-domain-manager' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $providers as $provider ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $provider->name ); ?></strong></td>
									<td>
										<?php
										foreach ( Providers_Repository::parse_types( $provider->provider_type ?? '' ) as $provider_type ) {
											echo Ui::badge( $types[ $provider_type ] ?? $provider_type, 'registrar' === $provider_type ? 'brand' : 'info', true ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										}
										?>
									</td>
									<td><?php echo ! empty( $provider->account_id ) ? esc_html( (string) $provider->account_id ) : '<span class="dm-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
									<td><?php echo ! empty( $provider->account_email ) ? esc_html( (string) $provider->account_email ) : '<span class="dm-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
									<td>
										<?php if ( $provider->management_url ) : ?>
											<a class="dm-provider__link" href="<?php echo esc_url( $provider->management_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo Ui::icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Open', '3ring-domain-manager' ); ?></a>
										<?php else : ?>
											<span class="dm-muted">—</span>
										<?php endif; ?>
									</td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=dm-providers&edit=' . (int) $provider->id ) ); ?>"><?php esc_html_e( 'Edit', '3ring-domain-manager' ); ?></a>
										<span class="dm-muted">|</span>
										<a class="dm-confirm-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dm-providers&action=delete&provider_id=' . (int) $provider->id ), 'dm_delete_provider_' . $provider->id ) ); ?>"><?php esc_html_e( 'Delete', '3ring-domain-manager' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					</div>
				</div>

				<div class="dm-panel">
					<div class="dm-panel__head">
						<h2>
							<?php echo Ui::icon( $editing ? 'settings' : 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo $editing ? esc_html__( 'Edit provider', '3ring-domain-manager' ) : esc_html__( 'Add provider', '3ring-domain-manager' ); ?>
						</h2>
					</div>
					<div class="dm-panel__body">
					<form method="post">
						<?php wp_nonce_field( 'dm_save_provider' ); ?>
						<input type="hidden" name="dm_save_provider" value="1" />
						<input type="hidden" name="provider_id" value="<?php echo esc_attr( (string) ( $editing ? $editing->id : 0 ) ); ?>" />
						<table class="form-table" role="presentation">
							<tr>
								<th><label for="name"><?php esc_html_e( 'Name', '3ring-domain-manager' ); ?></label></th>
								<td><input type="text" name="name" id="name" class="regular-text" required value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>" /></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Type', '3ring-domain-manager' ); ?></th>
								<td>
									<fieldset>
										<legend class="screen-reader-text"><?php esc_html_e( 'Provider types', '3ring-domain-manager' ); ?></legend>
										<?php foreach ( $types as $key => $label ) : ?>
											<label style="display:block;margin-bottom:4px;">
												<input type="checkbox" name="provider_types[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $selected_types, true ) ); ?> />
												<?php echo esc_html( $label ); ?>
											</label>
										<?php endforeach; ?>
									</fieldset>
									<p class="description"><?php esc_html_e( 'Select all roles this provider fills. Multi-type providers appear in each matching selector on the domain form.', '3ring-domain-manager' ); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="account_id"><?php esc_html_e( 'Account#/ID', '3ring-domain-manager' ); ?></label></th>
								<td>
									<input type="text" name="account_id" id="account_id" class="regular-text" value="<?php echo esc_attr( $editing ? (string) ( $editing->account_id ?? '' ) : '' ); ?>" />
									<p class="description"><?php esc_html_e( 'Customer or account number used at this provider.', '3ring-domain-manager' ); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="account_email"><?php esc_html_e( 'Account Email', '3ring-domain-manager' ); ?></label></th>
								<td>
									<input type="email" name="account_email" id="account_email" class="regular-text" value="<?php echo esc_attr( $editing ? (string) ( $editing->account_email ?? '' ) : '' ); ?>" />
									<p class="description"><?php esc_html_e( 'Login or contact email for this provider account.', '3ring-domain-manager' ); ?></p>
								</td>
							</tr>
							<tr>
								<th><label for="website_url"><?php esc_html_e( 'Website URL', '3ring-domain-manager' ); ?></label></th>
								<td><input type="url" name="website_url" id="website_url" class="regular-text" value="<?php echo esc_attr( $editing ? (string) $editing->website_url : '' ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="management_url"><?php esc_html_e( 'Management URL', '3ring-domain-manager' ); ?></label></th>
								<td><input type="url" name="management_url" id="management_url" class="regular-text" value="<?php echo esc_attr( $editing ? (string) $editing->management_url : '' ); ?>" /></td>
							</tr>
							<tr>
								<th><label for="notes"><?php esc_html_e( 'Notes', '3ring-domain-manager' ); ?></label></th>
								<td><textarea name="notes" id="notes" class="large-text" rows="3"><?php echo esc_textarea( $editing ? (string) $editing->notes : '' ); ?></textarea></td>
							</tr>
						</table>
						<?php submit_button( $editing ? __( 'Update provider', '3ring-domain-manager' ) : __( 'Add provider', '3ring-domain-manager' ) ); ?>
						<?php if ( $editing ) : ?>
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=dm-providers' ) ); ?>"><?php esc_html_e( 'Cancel', '3ring-domain-manager' ); ?></a>
						<?php endif; ?>
					</form>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
