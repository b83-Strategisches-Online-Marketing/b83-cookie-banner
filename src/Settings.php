<?php
/**
 * Einstellungen: Laden, Standardwerte, zentrale Validierung, Sprache.
 *
 * @package B83\CookieBanner
 */

declare(strict_types=1);

namespace B83\CookieBanner;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION     = 'b83_cookie_banner_settings';
	public const POSITIONS  = array( 'bottom_left', 'bottom_center', 'bottom_right', 'center' );
	public const MAX_CUSTOM = 30;

	/** @var array<string,mixed>|null */
	private static ?array $cache = null;

	/** @return array<string,mixed> */
	public static function get(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = self::sanitize( is_array( $stored ) ? $stored : array(), true );
		}
		return self::$cache;
	}

	/**
	 * Speichert bereits bereinigte bzw. zu bereinigende Einstellungen.
	 *
	 * @param array<string,mixed> $settings Einstellungen.
	 */
	public static function save( array $settings ): void {
		$clean       = self::sanitize( $settings, true );
		self::$cache = $clean;
		update_option( self::OPTION, $clean, true );
		do_action( 'b83_cookie_banner_settings_saved', $clean );
	}

	public static function flush_cache(): void {
		self::$cache = null;
	}

	/** Aktuelle Sprache des Banners: 'en', wenn aktiviert und die Seite englisch ist, sonst 'de'. */
	public static function lang(): string {
		$settings = self::get();
		if ( ! $settings['en_enabled'] ) {
			return 'de';
		}
		// WPML, Polylang und TranslatePress setzen die Locale der aktuellen Sprache.
		$locale = (string) apply_filters( 'b83_cookie_banner_locale', get_locale() );
		return str_starts_with( strtolower( (string) $locale ), 'en' ) ? 'en' : 'de';
	}

	/**
	 * Texte für eine Sprache (leere englische Felder fallen auf Deutsch zurück).
	 *
	 * @return array{title:string,message:string,privacy_url:string,imprint_url:string}
	 */
	public static function texts( string $lang ): array {
		$texts = self::get()['texts'];
		$de    = $texts['de'];
		if ( 'en' !== $lang ) {
			return $de;
		}
		$en = $texts['en'];
		foreach ( $en as $key => $value ) {
			if ( '' === $value ) {
				$en[ $key ] = $de[ $key ];
			}
		}
		return $en;
	}

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return array(
			'enabled'              => true,
			'revision'             => 1,
			'lifetime_months'      => 6,
			'log_retention_months' => 36,
			'position'             => 'bottom_left',
			'show_reopen'          => true,
			'accent_color'         => '#d96f21',
			'custom_css'           => '',
			'en_enabled'           => false,
			'texts'                => array(
				'de' => array(
					'title'       => 'Ihre Privatsphäre ist uns wichtig',
					'message'     => 'Wir verwenden Cookies und ähnliche Technologien. Einige davon sind für den Betrieb der Website notwendig. Andere – für Statistik, Marketing und externe Medien wie Videos und Karten – setzen wir nur mit Ihrer Einwilligung ein. Dabei können Daten auch an Anbieter in Drittländern wie den USA übermittelt werden. Ihre Einwilligung ist freiwillig und kann jederzeit über die Cookie-Einstellungen widerrufen werden.',
					'privacy_url' => home_url( '/datenschutzerklaerung/' ),
					'imprint_url' => home_url( '/impressum/' ),
				),
				'en' => array(
					'title'       => 'We respect your privacy',
					'message'     => 'We use cookies and similar technologies. Some of them are necessary for the website to work. Others – for statistics, marketing and external media such as videos and maps – are only used with your consent. Data may also be transferred to providers in third countries such as the USA. Your consent is voluntary and can be withdrawn at any time via the cookie settings.',
					'privacy_url' => '',
					'imprint_url' => '',
				),
			),
			'services'             => array(
				'gtm'         => array(
					'enabled' => false,
					'gtm_id'  => '',
				),
				'ga4'         => array(
					'enabled'        => false,
					'measurement_id' => '',
				),
				'meta_pixel'  => array(
					'enabled'  => false,
					'pixel_id' => '',
				),
				'youtube'     => array( 'enabled' => true ),
				'vimeo'       => array( 'enabled' => true ),
				'google_maps' => array( 'enabled' => true ),
			),
			'custom'               => array(),
		);
	}

	/**
	 * Zentrale Validierung. Erwartet ungeslashte Eingabe.
	 *
	 * @param array<mixed> $raw          Rohdaten.
	 * @param bool         $keep_missing Fehlende Werte mit Standardwerten auffüllen.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $raw, bool $keep_missing = true ): array {
		$d   = self::defaults();
		$raw = $keep_missing ? array_replace_recursive( $d, self::only_arrays_where_expected( $raw, $d ) ) : $raw;

		$position = is_string( $raw['position'] ?? null ) ? $raw['position'] : '';
		$color    = sanitize_hex_color( is_string( $raw['accent_color'] ?? null ) ? $raw['accent_color'] : '' );

		$clean = array(
			'enabled'              => self::bool( $raw['enabled'] ?? false ),
			'revision'             => max( 1, min( 100000, (int) ( $raw['revision'] ?? 1 ) ) ),
			'lifetime_months'      => max( 1, min( 12, (int) ( $raw['lifetime_months'] ?? 6 ) ) ),
			'log_retention_months' => max( 6, min( 120, (int) ( $raw['log_retention_months'] ?? 36 ) ) ),
			'position'             => in_array( $position, self::POSITIONS, true ) ? $position : 'bottom_left',
			'show_reopen'          => self::bool( $raw['show_reopen'] ?? false ),
			'accent_color'         => $color ? $color : '#d96f21',
			'custom_css'           => self::css( $raw['custom_css'] ?? '' ),
			'en_enabled'           => self::bool( $raw['en_enabled'] ?? false ),
			'texts'                => array(),
			'services'             => array(),
			'custom'               => array(),
		);

		foreach ( array( 'de', 'en' ) as $lang ) {
			$t                       = is_array( $raw['texts'][ $lang ] ?? null ) ? $raw['texts'][ $lang ] : array();
			$clean['texts'][ $lang ] = array(
				'title'       => self::text( $t['title'] ?? '', 120 ),
				'message'     => mb_substr( wp_kses( (string) ( is_scalar( $t['message'] ?? null ) ? $t['message'] : '' ), self::message_html() ), 0, 2000 ),
				'privacy_url' => esc_url_raw( (string) ( is_scalar( $t['privacy_url'] ?? null ) ? $t['privacy_url'] : '' ), array( 'http', 'https' ) ),
				'imprint_url' => esc_url_raw( (string) ( is_scalar( $t['imprint_url'] ?? null ) ? $t['imprint_url'] : '' ), array( 'http', 'https' ) ),
			);
		}
		// Deutsch braucht immer Titel und Text.
		foreach ( array( 'title', 'message' ) as $key ) {
			if ( '' === trim( wp_strip_all_tags( $clean['texts']['de'][ $key ] ) ) ) {
				$clean['texts']['de'][ $key ] = $d['texts']['de'][ $key ];
			}
		}

		foreach ( Services::catalog() as $id => $service ) {
			$in   = is_array( $raw['services'][ $id ] ?? null ) ? $raw['services'][ $id ] : array();
			$conf = array( 'enabled' => self::bool( $in['enabled'] ?? false ) );
			foreach ( (array) ( $service['fields'] ?? array() ) as $field => $def ) {
				$value          = strtoupper( trim( (string) ( is_scalar( $in[ $field ] ?? null ) ? $in[ $field ] : '' ) ) );
				$conf[ $field ] = ( '' !== $value && 1 === preg_match( (string) $def['pattern'], $value ) ) ? $value : '';
			}
			$clean['services'][ $id ] = $conf;
		}

		$ids = array_keys( Services::catalog() );
		foreach ( (array) ( $raw['custom'] ?? array() ) as $custom ) {
			if ( count( $clean['custom'] ) >= self::MAX_CUSTOM ) {
				break;
			}
			$item = self::custom_service( $custom, $ids );
			if ( null !== $item ) {
				$clean['custom'][] = $item;
				$ids[]             = $item['id'];
			}
		}

		return $clean;
	}

	/**
	 * Eigener Dienst (z. B. Wetter-Widget oder eigenes Skript).
	 *
	 * @param mixed    $raw   Rohdaten.
	 * @param string[] $taken Vergebene IDs.
	 * @return array<string,mixed>|null
	 */
	private static function custom_service( $raw, array $taken ): ?array {
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$name = self::text( $raw['name'] ?? '', 80 );
		if ( '' === $name ) {
			return null;
		}

		$id = sanitize_key( (string) ( is_scalar( $raw['id'] ?? null ) ? $raw['id'] : '' ) );
		if ( '' === $id || in_array( $id, $taken, true ) || ! str_starts_with( $id, 'custom_' ) ) {
			$base = 'custom_' . substr( (string) preg_replace( '/[^a-z0-9]+/', '_', strtolower( remove_accents( $name ) ) ), 0, 40 );
			$base = rtrim( $base, '_' );
			$id   = $base;
			$n    = 2;
			while ( in_array( $id, $taken, true ) ) {
				$id = $base . '_' . $n++;
			}
		}

		$category = is_string( $raw['category'] ?? null ) ? $raw['category'] : '';

		return array(
			'id'              => $id,
			'name'            => $name,
			'category'        => in_array( $category, Services::OPTIONAL_CATEGORIES, true ) ? $category : 'marketing',
			'provider'        => self::text( $raw['provider'] ?? '', 200 ),
			'purpose_de'      => self::text( $raw['purpose_de'] ?? '', 500 ),
			'purpose_en'      => self::text( $raw['purpose_en'] ?? '', 500 ),
			'cookies'         => self::text( $raw['cookies'] ?? '', 300 ),
			'privacy_url'     => esc_url_raw( (string) ( is_scalar( $raw['privacy_url'] ?? null ) ? $raw['privacy_url'] : '' ), array( 'http', 'https' ) ),
			'third_country'   => self::text( $raw['third_country'] ?? '', 120 ),
			'hosts'           => self::list( $raw['hosts'] ?? '', '/^[a-z0-9.\-\/]+$/' ),
			'cookie_patterns' => self::list( $raw['cookie_patterns'] ?? '', '/^[A-Za-z0-9_\-*]+$/' ),
			// Skript-Code: nur von Administratoren mit unfiltered_html änderbar (Prüfung beim Speichern im Backend).
			'code'            => mb_substr( (string) ( is_string( $raw['code'] ?? null ) ? $raw['code'] : '' ), 0, 20000 ),
		);
	}

	/** Erlaubtes HTML im Bannertext. */
	public static function message_html(): array {
		return array(
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'strong' => array(),
			'em'     => array(),
			'br'     => array(),
		);
	}

	/**
	 * Kommagetrennte bzw. zeilenweise Liste → bereinigtes Array.
	 *
	 * @param mixed $raw Rohwert.
	 * @return string[]
	 */
	private static function list( $raw, string $pattern ): array {
		if ( is_array( $raw ) ) {
			$raw = implode( ',', array_filter( $raw, 'is_scalar' ) );
		}
		$parts = preg_split( '/[\s,]+/', strtolower( (string) ( is_scalar( $raw ) ? $raw : '' ) ) ) ?: array();
		$out   = array();
		foreach ( $parts as $part ) {
			$part = (string) preg_replace( '#^https?://(www\.)?#', '', trim( $part ) );
			if ( '' !== $part && strlen( $part ) <= 100 && 1 === preg_match( $pattern, $part ) ) {
				$out[] = $part;
			}
		}
		return array_slice( array_values( array_unique( $out ) ), 0, 20 );
	}

	/** @param mixed $value Rohwert. */
	private static function text( $value, int $max ): string {
		return is_scalar( $value ) ? mb_substr( sanitize_text_field( (string) $value ), 0, $max ) : '';
	}

	/** @param mixed $value Rohwert. */
	private static function bool( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'on' === $value;
	}

	/** @param mixed $value Rohwert. */
	private static function css( $value ): string {
		$css = is_string( $value ) ? wp_strip_all_tags( $value ) : '';
		return mb_substr( str_ireplace( array( '</style', '<script' ), '', $css ), 0, 10000 );
	}

	/**
	 * Verhindert, dass ein Skalar einen erwarteten Unterbaum überschreibt.
	 *
	 * @param array<mixed> $raw      Rohdaten.
	 * @param array<mixed> $defaults Standardwerte.
	 * @return array<mixed>
	 */
	private static function only_arrays_where_expected( array $raw, array $defaults ): array {
		foreach ( $raw as $key => $value ) {
			if ( isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) && ! is_array( $value ) ) {
				unset( $raw[ $key ] );
			}
		}
		// Eigene Dienste sind eine Liste: nicht mit Standardwerten zusammenführen.
		return $raw;
	}
}
