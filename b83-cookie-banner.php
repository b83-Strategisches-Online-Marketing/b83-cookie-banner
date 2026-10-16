<?php
/**
 * Plugin Name:       b83 Cookie Banner
 * Plugin URI:        https://b83.at
 * Description:       DSGVO-konformer Cookie-Banner mit zwei Ebenen, Dienste-Katalog, Google Consent Mode v2, Blockieren von YouTube, Vimeo und Google Maps, Einwilligungsprotokoll und Mehrsprachigkeit (DE/EN).
 * Version:           7.1.0
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            b83 – Wolfgang Grabs-Schrempf
 * Author URI:        https://b83.at
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       b83-cookie-banner
 * Domain Path:       /languages
 * Update URI:        https://github.com/b83-Strategisches-Online-Marketing/b83-cookie-banner
 *
 * @package B83\CookieBanner
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'B83_CB_VERSION', '7.1.0' );
define( 'B83_CB_FILE', __FILE__ );
define( 'B83_CB_DIR', plugin_dir_path( __FILE__ ) );
define( 'B83_CB_URL', plugin_dir_url( __FILE__ ) );

/*
 * PSR-4-Autoloader für den Namespace B83\CookieBanner → src/.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'B83\\CookieBanner\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$file = B83_CB_DIR . 'src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( \B83\CookieBanner\Migration::class, 'run' ) );
register_deactivation_hook( __FILE__, array( \B83\CookieBanner\ConsentLog::class, 'unschedule' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		\B83\CookieBanner\Plugin::instance()->boot();
	}
);
