<?php
/**
 * Einwilligungsprotokoll (Nachweis nach Art. 7 Abs. 1 DSGVO) in einer
 * eigenen Tabelle. Gespeichert wird nur, was für den Nachweis nötig ist:
 * Einwilligungs-ID (auch im Cookie des Besuchers), Zeitpunkt, Auswahl,
 * Banner-Version, Sprache und Seitenpfad – keine IP-Adresse, kein User-Agent.
 *
 * @package B83\CookieBanner
 */

declare(strict_types=1);

namespace B83\CookieBanner;

defined( 'ABSPATH' ) || exit;

final class ConsentLog {

	public const CRON    = 'b83_cookie_banner_purge_log';
	public const ACTIONS = array( 'accept_all', 'reject_all', 'custom', 'service' );

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'b83_consent_log';
	}

	public static function create_table(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				consent_id char(36) NOT NULL,
				created_at datetime NOT NULL,
				action varchar(20) NOT NULL,
				categories varchar(200) NOT NULL DEFAULT '',
				services varchar(1000) NOT NULL DEFAULT '',
				revision int(10) unsigned NOT NULL DEFAULT 0,
				lang char(2) NOT NULL DEFAULT 'de',
				page varchar(255) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY consent_id (consent_id),
				KEY created_at (created_at)
			) {$charset};"
		);
	}

	/**
	 * Schreibt einen Eintrag. Die Werte müssen bereits validiert sein (Rest\ConsentEndpoint).
	 *
	 * @param array{consent_id:string,action:string,categories:string[],services:string[],revision:int,lang:string,page:string} $entry Eintrag.
	 */
	public static function insert( array $entry ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- eigene Tabelle, Schreibzugriff.
		$ok = $wpdb->insert(
			self::table(),
			array(
				'consent_id' => $entry['consent_id'],
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'action'     => $entry['action'],
				'categories' => implode( ',', $entry['categories'] ),
				'services'   => mb_substr( implode( ',', $entry['services'] ), 0, 1000 ),
				'revision'   => $entry['revision'],
				'lang'       => $entry['lang'],
				'page'       => mb_substr( $entry['page'], 0, 255 ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		return false !== $ok;
	}

	/** @return array<int, array<string,mixed>> */
	public static function recent( int $limit = 50, string $consent_id = '' ): array {
		global $wpdb;
		$table = self::table();
		if ( '' !== $consent_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabellenname intern.
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE consent_id = %s ORDER BY id DESC LIMIT %d", $consent_id, $limit ), ARRAY_A );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	/**
	 * Anzahl je Aktion in den letzten Tagen.
	 *
	 * @return array<string,int>
	 */
	public static function stats( int $days = 30 ): array {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT action, COUNT(*) AS n FROM {$table} WHERE created_at >= %s GROUP BY action", $since ), ARRAY_A );
		$stats = array_fill_keys( self::ACTIONS, 0 );
		foreach ( $rows as $row ) {
			if ( isset( $stats[ $row['action'] ] ) ) {
				$stats[ $row['action'] ] = (int) $row['n'];
			}
		}
		return $stats;
	}

	public static function count(): int {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/** Gibt das gesamte Protokoll als CSV aus (Aufrufer prüft Berechtigung und Nonce). */
	public static function export_csv(): void {
		global $wpdb;
		$table = self::table();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="b83-einwilligungsprotokoll-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Ausgabe-Stream.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- BOM für Excel.
		fputcsv( $out, array( 'Zeitpunkt (UTC)', 'Einwilligungs-ID', 'Aktion', 'Kategorien', 'Dienste', 'Revision', 'Sprache', 'Seite' ), ';' );

		$last = PHP_INT_MAX;
		do {
			// In Blöcken lesen, damit auch große Protokolle den Speicher nicht sprengen.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id < %d ORDER BY id DESC LIMIT 2000", $last ), ARRAY_A );
			foreach ( $rows as $row ) {
				fputcsv(
					$out,
					array_map(
						array( self::class, 'csv_safe' ),
						array( $row['created_at'], $row['consent_id'], $row['action'], $row['categories'], $row['services'], $row['revision'], $row['lang'], $row['page'] )
					),
					';'
				);
				$last = (int) $row['id'];
			}
		} while ( count( $rows ) === 2000 );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/** Verhindert Formel-Ausführung beim Öffnen in Excel (CSV-Injection). */
	public static function csv_safe( $value ): string {
		$value = (string) $value;
		return ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) ? "'" . $value : $value;
	}

	/** Löscht Einträge, die älter als die Aufbewahrungsfrist sind. */
	public static function purge(): void {
		global $wpdb;
		$months = (int) Settings::get()['log_retention_months'];
		$before = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $months . ' months' ) );
		$table  = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $before ) );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON );
	}
}
