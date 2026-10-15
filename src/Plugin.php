<?php
/**
 * Einstiegspunkt: verdrahtet alle Komponenten.
 *
 * @package B83\CookieBanner
 */

declare(strict_types=1);

namespace B83\CookieBanner;

use B83\CookieBanner\Admin\AdminPage;
use B83\CookieBanner\Frontend\Banner;
use B83\CookieBanner\Frontend\ContentBlocker;
use B83\CookieBanner\Rest\ConsentEndpoint;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;
	private bool $booted             = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		Migration::maybe_run();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( ConsentLog::CRON, array( ConsentLog::class, 'purge' ) );
		add_action( 'b83_cookie_banner_settings_saved', array( self::class, 'purge_page_cache' ) );

		( new Updater() )->register();
		( new ConsentEndpoint() )->register();

		$settings = Settings::get();
		if ( $settings['enabled'] ) {
			( new Banner() )->register();
			( new ContentBlocker() )->register();
		}

		if ( is_admin() ) {
			( new AdminPage() )->register();
		}

		/**
		 * Nach dem Laden aller Komponenten.
		 *
		 * @param Plugin $plugin Instanz.
		 */
		do_action( 'b83_cookie_banner_loaded', $this );
	}

	/**
	 * Banner-HTML und Texte stecken in jeder zwischengespeicherten Seite:
	 * nach dem Speichern den gesamten Seiten-Cache leeren.
	 */
	public static function purge_page_cache(): void {
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain(); // WP Rocket.
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all(); // W3 Total Cache.
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache(); // WP Super Cache.
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache(); // SiteGround.
		}
		do_action( 'litespeed_purge_all' ); // LiteSpeed Cache.
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'b83-cookie-banner', false, dirname( plugin_basename( B83_CB_FILE ) ) . '/languages' );
	}
}
