<?php
/**
 * Installation, Datenbank-Versionen und Übernahme aus Version 6.x.
 *
 * @package B83\CookieBanner
 */

declare(strict_types=1);

namespace B83\CookieBanner;

defined( 'ABSPATH' ) || exit;

final class Migration {

	public const DB_VERSION        = 1;
	public const OPTION_DB_VERSION = 'b83_cookie_banner_db_version';
	public const OPTION_MIGRATED   = 'b83_cookie_banner_migrated_v6';

	/** Optionen von Version 6.x. */
	private const LEGACY_OPTION = 'b83_cookie_banner_options';
	private const LEGACY_LOG    = 'b83_cookie_banner_logs';

	/** Läuft bei jedem Seitenaufruf, tut aber nur bei Versionswechsel etwas (Updates lösen keine Aktivierung aus). */
	public static function maybe_run(): void {
		if ( (int) get_option( self::OPTION_DB_VERSION, 0 ) < self::DB_VERSION ) {
			self::run();
		}
	}

	public static function run(): void {
		ConsentLog::create_table();

		if ( false === get_option( Settings::OPTION, false ) ) {
			$legacy = get_option( self::LEGACY_OPTION, null );
			if ( is_array( $legacy ) ) {
				update_option( Settings::OPTION, Settings::sanitize( self::convert_settings( $legacy ) ), true );
				self::migrate_log();
				update_option( self::OPTION_MIGRATED, time(), false );
			} else {
				add_option( Settings::OPTION, Settings::sanitize( array() ), '', true );
			}
			Settings::flush_cache();
		}

		ConsentLog::schedule();
		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, true );
	}

	/**
	 * Wandelt die Einstellungen von 6.x um.
	 *
	 * @param array<mixed> $old Alte Einstellungen.
	 * @return array<string,mixed>
	 */
	public static function convert_settings( array $old ): array {
		$new = Settings::defaults();

		$new['enabled']      = ( $old['enabled'] ?? '1' ) === '1';
		$new['accent_color'] = (string) ( $old['accent_color'] ?? $new['accent_color'] );
		$new['custom_css']   = (string) ( $old['custom_css'] ?? '' );
		$new['show_reopen']  = ( $old['show_settings_icon'] ?? '1' ) === '1';
		$new['position']     = (string) ( $old['position'] ?? 'bottom_center' );

		// Bisherige Einwilligungen enthielten nicht alle Pflichtangaben → alle Besucher einmal neu fragen.
		$new['revision'] = max( 1, (int) ( $old['consent_revision'] ?? 1 ) ) + 1;

		if ( ! empty( $old['privacy_url'] ) ) {
			$new['texts']['de']['privacy_url'] = (string) $old['privacy_url'];
		}

		$gtm = strtoupper( trim( (string) ( $old['gtm_id'] ?? '' ) ) );
		if ( '' !== $gtm ) {
			$new['services']['gtm'] = array(
				'enabled' => true,
				'gtm_id'  => $gtm,
			);
			// Annahme: Google Analytics läuft über den Tag Manager. Im Backend wird zur Prüfung aufgefordert.
			$new['services']['ga4']['enabled'] = true;
		}

		foreach ( array(
			'statistics_code' => array( 'statistics', 'Statistik-Skript (aus Version 6)' ),
			'marketing_code'  => array( 'marketing', 'Marketing-Skript (aus Version 6)' ),
		) as $key => $meta ) {
			$code = trim( (string) ( $old[ $key ] ?? '' ) );
			if ( '' !== $code ) {
				$new['custom'][] = array(
					'name'       => $meta[1],
					'category'   => $meta[0],
					'purpose_de' => 'Bitte Zweck, Anbieter und Cookies ergänzen.',
					'code'       => $code,
				);
			}
		}

		return $new;
	}

	/** Übernimmt das alte Protokoll (Option) in die neue Tabelle und entfernt die Option. */
	private static function migrate_log(): void {
		global $wpdb;
		$logs = get_option( self::LEGACY_LOG, array() );
		if ( ! is_array( $logs ) || empty( $logs ) ) {
			return;
		}

		foreach ( $logs as $log ) {
			if ( ! is_array( $log ) ) {
				continue;
			}
			$categories = array_values( array_intersect( array( 'necessary', 'statistics', 'marketing' ), (array) ( $log['categories'] ?? array() ) ) );
			$optional   = array_diff( $categories, array( 'necessary' ) );
			$action     = empty( $optional ) ? 'reject_all' : ( count( $optional ) === 2 ? 'accept_all' : 'custom' );
			$time       = (string) ( $log['time'] ?? '' );
			$path       = (string) wp_parse_url( (string) ( $log['url'] ?? '' ), PHP_URL_PATH );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- einmalige Übernahme in eigene Tabelle.
			$wpdb->insert(
				ConsentLog::table(),
				array(
					'consent_id' => 'v6',
					'created_at' => '' !== $time ? get_gmt_from_date( $time ) : gmdate( 'Y-m-d H:i:s' ),
					'action'     => $action,
					'categories' => implode( ',', $categories ),
					'services'   => '',
					'revision'   => 0,
					'lang'       => 'de',
					'page'       => mb_substr( $path, 0, 255 ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}
		delete_option( self::LEGACY_LOG );
	}
}
