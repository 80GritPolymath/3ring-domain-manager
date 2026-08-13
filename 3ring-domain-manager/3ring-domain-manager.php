<?php
/**
 * Plugin Name:       3RING Domain Manager
 * Plugin URI:        https://github.com/80GritPolymath/3ring-domain-manager
 * Description:       WordPress admin tool by 3RING Studios for managing a company domain portfolio.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            3RING Studios
 * Author URI:        https://3ring.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       3ring-domain-manager
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RINDOMA_VERSION', '1.0.0' );
define( 'RINDOMA_DB_VERSION', '1.0.0' );
define( 'RINDOMA_PLUGIN_FILE', __FILE__ );
define( 'RINDOMA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RINDOMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RINDOMA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once RINDOMA_PLUGIN_DIR . 'includes/class-autoloader.php';

ThreeRing\DomainManager\Autoloader::register();

register_activation_hook( __FILE__, array( ThreeRing\DomainManager\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( ThreeRing\DomainManager\Activator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		ThreeRing\DomainManager\Plugin::instance()->init();
	}
);
