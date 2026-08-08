<?php
/**
 * Domains list table.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Admin;

use ThreeRing\DomainManager\Capabilities;
use ThreeRing\DomainManager\Db\Domains_Repository;
use ThreeRing\DomainManager\Db\Providers_Repository;
use ThreeRing\DomainManager\Db\Schema;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Domains_List_Table
 */
final class Domains_List_Table extends \WP_List_Table {

	/**
	 * Display mode: list (full Domains page) or dashboard (embedded, no filters).
	 *
	 * @var string
	 */
	private $mode = 'list';

	/**
	 * Constructor.
	 *
	 * @param string $mode list|dashboard.
	 */
	public function __construct( string $mode = 'list' ) {
		$this->mode = 'dashboard' === $mode ? 'dashboard' : 'list';

		parent::__construct(
			array(
				'singular' => 'domain',
				'plural'   => 'domains',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'domain_name'      => __( 'Domain', '3ring-domain-manager' ),
			'portfolio_status' => __( 'Status', '3ring-domain-manager' ),
			'usage_type'       => __( 'Usage', '3ring-domain-manager' ),
			'registrar'        => __( 'Registrar', '3ring-domain-manager' ),
			'expires_on'       => __( 'Expires', '3ring-domain-manager' ),
			'auto_renew'       => __( 'Auto-renew', '3ring-domain-manager' ),
			'internal_owner'   => __( 'Owner', '3ring-domain-manager' ),
			'active_card'      => __( 'Active Card', '3ring-domain-manager' ),
		);
	}

	/**
	 * Server-side sortable columns (disabled — sorting is client-side to avoid full page reloads).
	 *
	 * @return array<string,array>
	 */
	protected function get_sortable_columns(): array {
		return array();
	}

	/**
	 * Client-side sort type per column.
	 *
	 * @return array<string,string>
	 */
	private function client_sort_types(): array {
		return array(
			'domain_name'      => 'text',
			'portfolio_status' => 'text',
			'usage_type'       => 'text',
			'registrar'        => 'text',
			'expires_on'       => 'date',
			'auto_renew'       => 'text',
			'internal_owner'   => 'text',
			'active_card'      => 'text',
		);
	}

	/**
	 * Table CSS classes.
	 *
	 * @return string[]
	 */
	protected function get_table_classes() {
		$classes   = parent::get_table_classes();
		$classes[] = 'dm-domains-table';

		return $classes;
	}

	/**
	 * Print headers with client-sort markers (no navigation links).
	 *
	 * @param bool $with_id Whether to set the id attribute.
	 */
	public function print_column_headers( $with_id = true ) {
		list( $columns, $hidden, , $primary ) = $this->get_column_info();
		$sort_types = $this->client_sort_types();

		foreach ( $columns as $column_key => $column_display_name ) {
			$class = array( 'manage-column', "column-{$column_key}" );

			if ( in_array( $column_key, $hidden, true ) ) {
				$class[] = 'hidden';
			}

			if ( 'cb' === $column_key ) {
				$class[] = 'check-column';
			} elseif ( $column_key === $primary ) {
				$class[] = 'column-primary';
			}

			$sort_type = $sort_types[ $column_key ] ?? '';
			if ( $sort_type ) {
				$class[] = 'dm-th-sort';
			}

			if ( 'cb' === $column_key ) {
				$column_display_name = '<input type="checkbox" />';
			}

			$tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id    = $with_id ? "id='" . esc_attr( $column_key ) . "'" : '';

			$data_attrs = '';
			if ( $sort_type ) {
				$data_attrs = ' data-sort-type="' . esc_attr( $sort_type ) . '" tabindex="0" role="columnheader" aria-sort="none"';
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes escaped above; label is translated plain text or checkbox markup.
			echo "<{$tag} {$scope} {$id} class='" . esc_attr( implode( ' ', $class ) ) . "'{$data_attrs}>{$column_display_name}</{$tag}>";
		}
	}

	/**
	 * Sort value for a column (used by client-side sorting).
	 *
	 * @param object $item        Domain row.
	 * @param string $column_name Column key.
	 */
	private function sort_value( $item, string $column_name ): string {
		switch ( $column_name ) {
			case 'domain_name':
				return strtolower( (string) $item->domain_name );
			case 'portfolio_status':
				$labels = Schema::portfolio_statuses();
				$status = (string) $item->portfolio_status;
				return strtolower( (string) ( $labels[ $status ] ?? $status ) );
			case 'usage_type':
				$labels = Schema::usage_types();
				$usage  = (string) $item->usage_type;
				return strtolower( (string) ( $labels[ $usage ] ?? $usage ) );
			case 'registrar':
				if ( empty( $item->registrar_id ) ) {
					return '';
				}
				$provider = ( new Providers_Repository() )->get( (int) $item->registrar_id );
				return $provider ? strtolower( (string) $provider->name ) : '';
			case 'expires_on':
				return ! empty( $item->expires_on ) ? (string) $item->expires_on : '';
			case 'auto_renew':
				$labels     = Schema::auto_renew_statuses();
				$auto_renew = (string) $item->auto_renew_status;
				return strtolower( (string) ( $labels[ $auto_renew ] ?? $auto_renew ) );
			case 'internal_owner':
				return strtolower( (string) ( $item->internal_owner ?? '' ) );
			case 'active_card':
				return (string) ( $item->active_card ?? '' );
			default:
				return isset( $item->$column_name ) ? strtolower( (string) $item->$column_name ) : '';
		}
	}

	/**
	 * Render a single row's columns with data-sort-value for client sorting.
	 *
	 * @param object $item Domain row.
	 */
	protected function single_row_columns( $item ) {
		list( $columns, $hidden, , $primary ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$classes = "$column_name column-$column_name";
			if ( $primary === $column_name ) {
				$classes .= ' has-row-actions column-primary';
			}
			if ( in_array( $column_name, $hidden, true ) ) {
				$classes .= ' hidden';
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- class/attr escaped; cell content from column_* methods.
			echo '<td class="' . esc_attr( $classes ) . '" data-sort-value="' . esc_attr( $this->sort_value( $item, $column_name ) ) . '" data-colname="' . esc_attr( wp_strip_all_tags( (string) $column_display_name ) ) . '">';

			if ( method_exists( $this, '_column_' . $column_name ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo call_user_func(
					array( $this, '_column_' . $column_name ),
					$item,
					$classes,
					'',
					$primary
				);
			} elseif ( method_exists( $this, 'column_' . $column_name ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo call_user_func( array( $this, 'column_' . $column_name ), $item );
			} else {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->column_default( $item, $column_name );
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->handle_row_actions( $item, $column_name, $primary );
			echo '</td>';
		}
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items(): void {
		$per_page = 'dashboard' === $this->mode ? 100 : 20;
		$page     = $this->get_pagenum();

		// Initial order only — column clicks sort in the browser (no reload).
		$args = array(
			'per_page' => $per_page,
			'page'     => $page,
			'orderby'  => 'domain_name',
			'order'    => 'ASC',
		);

		if ( 'list' === $this->mode ) {
			$args['search'] = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			foreach ( array( 'portfolio_status', 'usage_type', 'internal_owner' ) as $key ) {
				if ( ! empty( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$args[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
			}

			if ( ! empty( $_GET['registrar_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$args['registrar_id'] = absint( $_GET['registrar_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			if ( ! empty( $_GET['expiry_within_days'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$args['expiry_within_days'] = absint( $_GET['expiry_within_days'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			if ( ! empty( $_GET['review_due'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$args['review_due'] = 1;
			}

			if ( ! empty( $_GET['show_archived'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$args['show_archived']   = 1;
				$args['archived_filter'] = sanitize_text_field( wp_unslash( $_GET['show_archived'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		$result = ( new Domains_Repository() )->query( $args );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->items           = $result['items'];
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Empty state.
	 */
	public function no_items(): void {
		echo Ui::empty_state( __( 'No domains match the current filters.', '3ring-domain-manager' ), 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Default column.
	 *
	 * @param object $item        Item.
	 * @param string $column_name Column.
	 */
	protected function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( (string) $item->$column_name ) : '';
	}

	/**
	 * Domain name column with actions.
	 *
	 * @param object $item Item.
	 */
	protected function column_domain_name( $item ): string {
		$edit_url    = admin_url( 'admin.php?page=dm-domains&action=edit&domain_id=' . (int) $item->id );
		$details_url = admin_url( 'admin.php?page=dm-domains&action=details&domain_id=' . (int) $item->id );
		$visit_url   = Domains_Repository::visit_url( $item );

		// Order: Details | Visit | Edit | Archive | Delete.
		$actions = array(
			'details' => '<a href="' . esc_url( $details_url ) . '">' . esc_html__( 'Details', '3ring-domain-manager' ) . '</a>',
		);

		if ( $visit_url ) {
			$actions['visit'] = '<a href="' . esc_url( $visit_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Visit', '3ring-domain-manager' ) . '</a>';
		}

		$actions['edit'] = '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', '3ring-domain-manager' ) . '</a>';

		if ( Capabilities::current_user_can_manage() ) {
			if ( empty( $item->archived_at ) ) {
				$actions['archive'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=dm-domains&action=archive&domain_id=' . (int) $item->id ), 'dm_archive_' . $item->id ) ) . '">' . esc_html__( 'Archive', '3ring-domain-manager' ) . '</a>';
			} else {
				$actions['restore'] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=dm-domains&action=restore&domain_id=' . (int) $item->id ), 'dm_restore_' . $item->id ) ) . '">' . esc_html__( 'Restore', '3ring-domain-manager' ) . '</a>';
			}

			$actions['delete'] = '<a class="dm-confirm-delete" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=dm-domains&action=delete&domain_id=' . (int) $item->id ), 'dm_delete_' . $item->id ) ) . '">' . esc_html__( 'Delete', '3ring-domain-manager' ) . '</a>';
		}

		$name = '<strong><a href="' . esc_url( $details_url ) . '">' . esc_html( $item->domain_name ) . '</a></strong>';
		if ( ! empty( $item->display_name ) ) {
			$name .= '<span class="dm-cell-sub">' . esc_html( $item->display_name ) . '</span>';
		}

		return $name . $this->row_actions( $actions );
	}

	/**
	 * Status column.
	 *
	 * @param object $item Item.
	 */
	protected function column_portfolio_status( $item ): string {
		$labels = Schema::portfolio_statuses();
		$status = (string) $item->portfolio_status;

		return Ui::badge( $labels[ $status ] ?? $status, Ui::status_tone( $status ) );
	}

	/**
	 * Usage column.
	 *
	 * @param object $item Item.
	 */
	protected function column_usage_type( $item ): string {
		$labels = Schema::usage_types();
		$usage  = (string) $item->usage_type;

		return Ui::badge( $labels[ $usage ] ?? $usage, Ui::usage_tone( $usage ), true );
	}

	/**
	 * Registrar column.
	 *
	 * @param object $item Item.
	 */
	protected function column_registrar( $item ): string {
		if ( empty( $item->registrar_id ) ) {
			return '<span class="dm-muted">&mdash;</span>';
		}
		$provider = ( new Providers_Repository() )->get( (int) $item->registrar_id );
		if ( ! $provider ) {
			return '<span class="dm-muted">&mdash;</span>';
		}

		$html = '<span class="dm-provider__name">' . esc_html( $provider->name ) . '</span>';
		if ( ! empty( $provider->management_url ) ) {
			$html .= '<a class="dm-provider__link" href="' . esc_url( $provider->management_url ) . '" target="_blank" rel="noopener noreferrer">'
				. Ui::icon( 'external' ) . esc_html__( 'Manage', '3ring-domain-manager' ) . '</a>';
		}

		return '<span class="dm-provider">' . $html . '</span>';
	}

	/**
	 * Active card last-4 column.
	 *
	 * @param object $item Item.
	 */
	protected function column_active_card( $item ): string {
		if ( empty( $item->active_card ) ) {
			return '<span class="dm-muted">&mdash;</span>';
		}

		return '<span class="dm-card-chip">' . Ui::icon( 'card' ) . esc_html( '•••• ' . $item->active_card ) . '</span>';
	}

	/**
	 * Expiry column.
	 *
	 * @param object $item Item.
	 */
	protected function column_expires_on( $item ): string {
		if ( empty( $item->expires_on ) ) {
			return '<span class="dm-muted">&mdash;</span>';
		}

		$days = (int) floor( ( strtotime( $item->expires_on ) - strtotime( current_time( 'Y-m-d' ) ) ) / DAY_IN_SECONDS );
		$tone = $days <= 30 ? ' dm-expiry--soon' : ( $days <= 90 ? ' dm-expiry--warn' : '' );

		if ( $days < 0 ) {
			$relative = sprintf( /* translators: %d: days */ __( '%d days overdue', '3ring-domain-manager' ), abs( $days ) );
		} else {
			$relative = sprintf( /* translators: %d: days */ __( 'in %d days', '3ring-domain-manager' ), $days );
		}

		return '<span class="dm-expiry' . esc_attr( $tone ) . '">'
			. '<span class="dm-expiry__date">' . esc_html( $item->expires_on ) . '</span>'
			. '<span class="dm-expiry__days">' . esc_html( $relative ) . '</span>'
			. '</span>';
	}

	/**
	 * Auto-renew column.
	 *
	 * @param object $item Item.
	 */
	protected function column_auto_renew( $item ): string {
		$labels     = Schema::auto_renew_statuses();
		$auto_renew = (string) $item->auto_renew_status;

		return Ui::badge( $labels[ $auto_renew ] ?? $auto_renew, Ui::auto_renew_tone( $auto_renew ) );
	}

	/**
	 * Extra filters.
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'dashboard' === $this->mode || 'top' !== $which ) {
			return;
		}

		$statuses   = Schema::portfolio_statuses();
		$usages     = Schema::usage_types();
		$registrars = ( new Providers_Repository() )->list_all( 'registrar' );
		$current_status = isset( $_GET['portfolio_status'] ) ? sanitize_text_field( wp_unslash( $_GET['portfolio_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_usage  = isset( $_GET['usage_type'] ) ? sanitize_text_field( wp_unslash( $_GET['usage_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_reg    = isset( $_GET['registrar_id'] ) ? absint( $_GET['registrar_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$expiry         = isset( $_GET['expiry_within_days'] ) ? absint( $_GET['expiry_within_days'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$show_archived  = isset( $_GET['show_archived'] ) ? sanitize_text_field( wp_unslash( $_GET['show_archived'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="alignleft actions">
			<select name="portfolio_status">
				<option value=""><?php esc_html_e( 'All statuses', '3ring-domain-manager' ); ?></option>
				<?php foreach ( $statuses as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_status, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="usage_type">
				<option value=""><?php esc_html_e( 'All usage types', '3ring-domain-manager' ); ?></option>
				<?php foreach ( $usages as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_usage, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="registrar_id">
				<option value=""><?php esc_html_e( 'All registrars', '3ring-domain-manager' ); ?></option>
				<?php foreach ( $registrars as $registrar ) : ?>
					<option value="<?php echo esc_attr( (string) $registrar->id ); ?>" <?php selected( $current_reg, (int) $registrar->id ); ?>><?php echo esc_html( $registrar->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="expiry_within_days">
				<option value=""><?php esc_html_e( 'Any expiry', '3ring-domain-manager' ); ?></option>
				<option value="30" <?php selected( $expiry, 30 ); ?>><?php esc_html_e( 'Expires within 30 days', '3ring-domain-manager' ); ?></option>
				<option value="60" <?php selected( $expiry, 60 ); ?>><?php esc_html_e( 'Expires in 31–60 days', '3ring-domain-manager' ); ?></option>
				<option value="90" <?php selected( $expiry, 90 ); ?>><?php esc_html_e( 'Expires in 61–90 days', '3ring-domain-manager' ); ?></option>
			</select>
			<select name="show_archived">
				<option value=""><?php esc_html_e( 'Hide archived', '3ring-domain-manager' ); ?></option>
				<option value="1" <?php selected( $show_archived, '1' ); ?>><?php esc_html_e( 'Include archived', '3ring-domain-manager' ); ?></option>
				<option value="only" <?php selected( $show_archived, 'only' ); ?>><?php esc_html_e( 'Archived only', '3ring-domain-manager' ); ?></option>
			</select>
			<?php submit_button( __( 'Filter', '3ring-domain-manager' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}
}
