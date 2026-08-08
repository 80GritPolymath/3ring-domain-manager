<?php
/**
 * Plugin capabilities and role assignment.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Capabilities
 */
final class Capabilities {

	public const VIEW   = 'dm_view_domains';
	public const EDIT   = 'dm_edit_domains';
	public const MANAGE = 'dm_manage_domains';
	public const ADMIN  = 'dm_admin_plugin';

	public const PLUGIN_ADMIN_OPTION = 'dm_plugin_admin_user_id';

	/**
	 * All plugin capabilities.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::VIEW, self::EDIT, self::MANAGE, self::ADMIN );
	}

	/**
	 * Domain Manager capability set (excludes plugin admin).
	 *
	 * @return string[]
	 */
	public static function manager_caps(): array {
		return array( self::VIEW, self::EDIT, self::MANAGE );
	}

	/**
	 * Plugin Administrator capability set.
	 *
	 * @return string[]
	 */
	public static function admin_caps(): array {
		return self::all();
	}

	/**
	 * Grant Plugin Administrator caps to a user.
	 *
	 * When $user is null, grants to the stored plugin admin, otherwise the
	 * current user (activation), otherwise the first user who can activate plugins.
	 *
	 * @param \WP_User|null $user Optional explicit user.
	 * @return bool True if caps were granted to a user.
	 */
	public static function grant_plugin_admin( ?\WP_User $user = null ): bool {
		if ( ! $user instanceof \WP_User ) {
			$user = self::resolve_plugin_admin_user();
		}

		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		foreach ( self::admin_caps() as $cap ) {
			$user->add_cap( $cap );
		}

		update_option( self::PLUGIN_ADMIN_OPTION, (int) $user->ID, false );

		return true;
	}

	/**
	 * Resolve which user should hold Plugin Administrator rights.
	 */
	public static function resolve_plugin_admin_user(): ?\WP_User {
		$stored_id = (int) get_option( self::PLUGIN_ADMIN_OPTION, 0 );
		if ( $stored_id > 0 ) {
			$stored = get_userdata( $stored_id );
			if ( $stored instanceof \WP_User ) {
				return $stored;
			}
		}

		$current_id = get_current_user_id();
		if ( $current_id > 0 ) {
			$current = get_userdata( $current_id );
			if ( $current instanceof \WP_User ) {
				return $current;
			}
		}

		$admins = get_users(
			array(
				'capability' => 'activate_plugins',
				'number'     => 1,
				'orderby'    => 'ID',
				'order'      => 'ASC',
			)
		);

		if ( is_array( $admins ) && isset( $admins[0] ) && $admins[0] instanceof \WP_User ) {
			return $admins[0];
		}

		return null;
	}

	/**
	 * Grant Domain Manager caps to a user.
	 *
	 * @param \WP_User $user User object.
	 */
	public static function grant_manager( \WP_User $user ): void {
		foreach ( self::manager_caps() as $cap ) {
			$user->add_cap( $cap );
		}
	}

	/**
	 * Revoke Domain Manager caps from a user (does not touch dm_admin_plugin).
	 *
	 * @param \WP_User $user User object.
	 */
	public static function revoke_manager( \WP_User $user ): void {
		foreach ( self::manager_caps() as $cap ) {
			$user->remove_cap( $cap );
		}
	}

	/**
	 * Whether a user is a Plugin Administrator (has dm_admin_plugin).
	 *
	 * @param \WP_User $user User object.
	 */
	public static function is_plugin_admin_user( \WP_User $user ): bool {
		return user_can( $user, self::ADMIN );
	}

	/**
	 * Whether the current user can view domains.
	 */
	public static function current_user_can_view(): bool {
		return current_user_can( self::VIEW );
	}

	/**
	 * Whether the current user can edit domains.
	 */
	public static function current_user_can_edit(): bool {
		return current_user_can( self::EDIT );
	}

	/**
	 * Whether the current user can manage domains.
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( self::MANAGE );
	}

	/**
	 * Whether the current user is plugin admin.
	 */
	public static function current_user_can_admin(): bool {
		return current_user_can( self::ADMIN );
	}

	/**
	 * Get WP users who have Domain Manager (or higher) access.
	 *
	 * @return \WP_User[]
	 */
	public static function get_manager_users(): array {
		$found = array();

		// Prefer capability query when available.
		$users = get_users(
			array(
				'capability' => self::MANAGE,
				'fields'     => 'all',
			)
		);

		if ( is_array( $users ) && $users ) {
			return $users;
		}

		// Fallback: scan users and check capability directly (covers user-meta caps).
		$all = get_users( array( 'fields' => 'all', 'number' => 500 ) );
		if ( ! is_array( $all ) ) {
			return array();
		}

		foreach ( $all as $user ) {
			if ( user_can( $user, self::MANAGE ) ) {
				$found[] = $user;
			}
		}

		return $found;
	}
}
