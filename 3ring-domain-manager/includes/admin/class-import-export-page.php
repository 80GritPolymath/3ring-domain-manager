<?php
/**
 * CSV import / export page.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Admin;

use ThreeRing\DomainManager\Capabilities;
use ThreeRing\DomainManager\Db\Schema;
use ThreeRing\DomainManager\Services\Csv_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Class Import_Export_Page
 */
final class Import_Export_Page {

	/**
	 * Handle export / template download early.
	 */
	public static function maybe_handle_export(): void {
		if ( ! isset( $_GET['page'] ) || 'dm-import-export' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! Capabilities::current_user_can_manage() ) {
			return;
		}

		$csv = new Csv_Service();

		if ( isset( $_GET['dm_export'] ) && '1' === $_GET['dm_export'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'dm_export_csv' );
			$filters = array();
			if ( ! empty( $_GET['show_archived'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$filters['show_archived'] = 1;
			}
			$csv->export( $filters );
		}

		if ( isset( $_GET['dm_template'] ) && '1' === $_GET['dm_template'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'dm_csv_template' );
			$csv->download_template();
		}
	}

	/**
	 * Render page.
	 */
	public static function render(): void {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', '3ring-domain-manager' ) );
		}

		if ( ! Schema::tables_exist() ) {
			echo '<div class="wrap"><h1>Import / Export</h1><div class="notice notice-error"><p>' . esc_html__( 'Database tables are missing.', '3ring-domain-manager' ) . '</p></div></div>';
			return;
		}

		$result = null;
		if ( ! empty( $_POST['dm_import_csv'] ) ) {
			check_admin_referer( 'dm_import_csv' );
			if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
				$result = array(
					'created' => 0,
					'updated' => 0,
					'errors'  => array( __( 'Please choose a CSV file.', '3ring-domain-manager' ) ),
				);
			} else {
				$update = ! empty( $_POST['update_existing'] );
				$result = ( new Csv_Service() )->import( $_FILES['csv_file']['tmp_name'], $update );
			}
		}
		?>
		<div class="wrap dm-wrap">
			<?php
			Ui::page_header(
				array(
					'title'    => __( 'Import / Export', '3ring-domain-manager' ),
					'subtitle' => __( 'Move the portfolio in and out of Domain Manager as CSV.', '3ring-domain-manager' ),
				)
			);
			?>

			<?php if ( is_array( $result ) ) : ?>
				<div class="notice notice-<?php echo empty( $result['errors'] ) ? 'success' : 'warning'; ?> is-dismissible">
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: created, 2: updated */
								__( 'Import finished. Created: %1$d. Updated: %2$d.', '3ring-domain-manager' ),
								(int) $result['created'],
								(int) $result['updated']
							)
						);
						?>
					</p>
					<?php if ( ! empty( $result['errors'] ) ) : ?>
						<ul>
							<?php foreach ( $result['errors'] as $error ) : ?>
								<li><?php echo esc_html( $error ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="dm-grid dm-grid--wide">
				<div class="dm-panel">
					<div class="dm-panel__head">
						<h2><?php echo Ui::icon( 'file' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Export', '3ring-domain-manager' ); ?></h2>
					</div>
					<div class="dm-panel__body">
					<p class="description"><?php esc_html_e( 'Download the current domain portfolio as CSV.', '3ring-domain-manager' ); ?></p>
					<p class="dm-actions">
						<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dm-import-export&dm_export=1' ), 'dm_export_csv' ) ); ?>">
							<?php esc_html_e( 'Export domains CSV', '3ring-domain-manager' ); ?>
						</a>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dm-import-export&dm_template=1' ), 'dm_csv_template' ) ); ?>">
							<?php esc_html_e( 'Download CSV template', '3ring-domain-manager' ); ?>
						</a>
					</p>
					</div>
				</div>

				<div class="dm-panel">
					<div class="dm-panel__head">
						<h2><?php echo Ui::icon( 'transfer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Import', '3ring-domain-manager' ); ?></h2>
					</div>
					<div class="dm-panel__body">
					<p class="description"><?php esc_html_e( 'Upload a CSV using the template columns. Registrar and provider columns must match existing provider names.', '3ring-domain-manager' ); ?></p>
					<form method="post" enctype="multipart/form-data">
						<?php wp_nonce_field( 'dm_import_csv' ); ?>
						<input type="hidden" name="dm_import_csv" value="1" />
						<p><input type="file" name="csv_file" accept=".csv,text/csv" required /></p>
						<p>
							<label>
								<input type="checkbox" name="update_existing" value="1" checked />
								<?php esc_html_e( 'Update existing domains matched by domain name', '3ring-domain-manager' ); ?>
							</label>
						</p>
						<?php submit_button( __( 'Import CSV', '3ring-domain-manager' ), 'primary', 'submit', false ); ?>
					</form>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
