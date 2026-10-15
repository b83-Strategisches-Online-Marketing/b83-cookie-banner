<?php
/**
 * Kategorien und Dienste-Katalog (Vorlagen mit Pflichtangaben für eine
 * informierte Einwilligung: Anbieter, Zweck, Cookies, Drittland, Datenschutz).
 *
 * Eigene Dienste kommen aus den Einstellungen dazu (Settings::custom).
 *
 * @package B83\CookieBanner
 */

declare(strict_types=1);

namespace B83\CookieBanner;

defined( 'ABSPATH' ) || exit;

final class Services {

	public const CATEGORIES = array( 'necessary', 'statistics', 'marketing', 'external_media' );

	/** Kategorien, denen Dienste zugeordnet werden können (ohne „notwendig“). */
	public const OPTIONAL_CATEGORIES = array( 'statistics', 'marketing', 'external_media' );

	private const GOOGLE = 'Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Irland';

	/**
	 * Bezeichnung und Beschreibung der Kategorien.
	 *
	 * @return array<string, array{label: array<string,string>, description: array<string,string>}>
	 */
	public static function categories(): array {
		return array(
			'necessary'      => array(
				'label'       => array(
					'de' => 'Notwendig',
					'en' => 'Necessary',
				),
				'description' => array(
					'de' => 'Diese Technologien sind für den Betrieb der Website erforderlich, z. B. um Ihre Datenschutz-Einstellungen zu speichern. Sie können nicht abgewählt werden.',
					'en' => 'These technologies are required for the website to work, e.g. to store your privacy settings. They cannot be deselected.',
				),
			),
			'statistics'     => array(
				'label'       => array(
					'de' => 'Statistik',
					'en' => 'Statistics',
				),
				'description' => array(
					'de' => 'Helfen uns zu verstehen, wie Besucher die Website nutzen, damit wir sie verbessern können.',
					'en' => 'Help us understand how visitors use the website so that we can improve it.',
				),
			),
			'marketing'      => array(
				'label'       => array(
					'de' => 'Marketing',
					'en' => 'Marketing',
				),
				'description' => array(
					'de' => 'Werden genutzt, um die Wirkung von Werbung zu messen und Ihnen passende Werbung zu zeigen – auch auf anderen Websites.',
					'en' => 'Used to measure the effectiveness of advertising and to show you relevant ads, including on other websites.',
				),
			),
			'external_media' => array(
				'label'       => array(
					'de' => 'Externe Medien',
					'en' => 'External media',
				),
				'description' => array(
					'de' => 'Inhalte von Videoplattformen und Kartendiensten werden erst nach Ihrer Zustimmung geladen. Dabei werden Daten an den jeweiligen Anbieter übertragen.',
					'en' => 'Content from video platforms and map services is only loaded with your consent. Data is transferred to the respective provider.',
				),
			),
		);
	}

	/**
	 * Vorlagen. Schlüssel = Dienst-ID.
	 *
	 * Felder: category, name, provider, purpose, cookies, privacy_url,
	 * third_country, fields (technische IDs), cookie_patterns (zum Löschen
	 * beim Widerruf), hosts (iFrames, die blockiert werden), loader (nur
	 * technisch, nicht einzeln wählbar).
	 *
	 * @return array<string, array<string,mixed>>
	 */
	public static function catalog(): array {
		$usa = array(
			'de' => 'USA (EU-US Data Privacy Framework)',
			'en' => 'USA (EU-US Data Privacy Framework)',
		);

		$catalog = array(
			'gtm'         => array(
				'category'        => 'statistics',
				'loader'          => true,
				'name'            => 'Google Tag Manager',
				'provider'        => self::GOOGLE,
				'purpose'         => array(
					'de' => 'Verwaltet und lädt die Statistik- und Marketing-Dienste dieser Website. Wird nur geladen, wenn Sie mindestens einem dieser Dienste zustimmen.',
					'en' => 'Manages and loads the statistics and marketing services of this website. Only loaded if you consent to at least one of these services.',
				),
				'cookies'         => array(
					'de' => 'Keine eigenen Cookies',
					'en' => 'No cookies of its own',
				),
				'privacy_url'     => 'https://policies.google.com/privacy',
				'third_country'   => $usa,
				'fields'          => array(
					'gtm_id' => array(
						'label'   => 'GTM-ID',
						'pattern' => '/^GTM-[A-Z0-9]{4,12}$/',
						'hint'    => 'GTM-XXXXXXX',
					),
				),
				'cookie_patterns' => array(),
				'hosts'           => array(),
			),
			'ga4'         => array(
				'category'        => 'statistics',
				'name'            => 'Google Analytics',
				'provider'        => self::GOOGLE,
				'purpose'         => array(
					'de' => 'Statistische Auswertung der Websitenutzung (z. B. aufgerufene Seiten, Verweildauer, Herkunft), um unser Angebot zu verbessern.',
					'en' => 'Statistical analysis of website usage (e.g. pages viewed, time spent, referrer) to improve our offering.',
				),
				'cookies'         => array(
					'de' => '_ga, _ga_<ID> (je 2 Jahre)',
					'en' => '_ga, _ga_<ID> (2 years each)',
				),
				'privacy_url'     => 'https://policies.google.com/privacy',
				'third_country'   => $usa,
				'fields'          => array(
					'measurement_id' => array(
						'label'   => 'Mess-ID',
						'pattern' => '/^G-[A-Z0-9]{4,15}$/',
						'hint'    => 'G-XXXXXXXXXX – leer lassen, wenn Google Analytics über den Tag Manager läuft',
					),
				),
				'cookie_patterns' => array( '_ga', '_ga_*', '_gid', '_gat*' ),
				'hosts'           => array(),
			),
			'meta_pixel'  => array(
				'category'        => 'marketing',
				'name'            => 'Meta Pixel',
				'provider'        => 'Meta Platforms Ireland Limited, Merrion Road, Dublin 4, D04 X2K5, Irland',
				'purpose'         => array(
					'de' => 'Messung der Wirksamkeit von Werbeanzeigen auf Facebook und Instagram sowie Bildung von Zielgruppen für Werbung.',
					'en' => 'Measuring the effectiveness of ads on Facebook and Instagram and building audiences for advertising.',
				),
				'cookies'         => array(
					'de' => '_fbp, _fbc (je 3 Monate)',
					'en' => '_fbp, _fbc (3 months each)',
				),
				'privacy_url'     => 'https://www.facebook.com/privacy/policy/',
				'third_country'   => $usa,
				'fields'          => array(
					'pixel_id' => array(
						'label'   => 'Pixel-ID',
						'pattern' => '/^\d{10,20}$/',
						'hint'    => 'leer lassen, wenn der Pixel über den Tag Manager läuft',
					),
				),
				'cookie_patterns' => array( '_fbp', '_fbc' ),
				'hosts'           => array(),
			),
			'youtube'     => array(
				'category'        => 'external_media',
				'name'            => 'YouTube',
				'provider'        => self::GOOGLE,
				'purpose'         => array(
					'de' => 'Einbindung von Videos. Beim Laden werden Daten (u. a. IP-Adresse) an YouTube übertragen.',
					'en' => 'Embedding videos. When loaded, data (including your IP address) is transferred to YouTube.',
				),
				'cookies'         => array(
					'de' => 'YSC (Sitzung), VISITOR_INFO1_LIVE (6 Monate) – gesetzt von YouTube',
					'en' => 'YSC (session), VISITOR_INFO1_LIVE (6 months) – set by YouTube',
				),
				'privacy_url'     => 'https://policies.google.com/privacy',
				'third_country'   => $usa,
				'fields'          => array(),
				'cookie_patterns' => array(),
				'hosts'           => array( 'youtube.com', 'youtube-nocookie.com', 'youtu.be' ),
			),
			'vimeo'       => array(
				'category'        => 'external_media',
				'name'            => 'Vimeo',
				'provider'        => 'Vimeo.com, Inc., 330 West 34th Street, 5th Floor, New York, NY 10001, USA',
				'purpose'         => array(
					'de' => 'Einbindung von Videos. Beim Laden werden Daten (u. a. IP-Adresse) an Vimeo übertragen.',
					'en' => 'Embedding videos. When loaded, data (including your IP address) is transferred to Vimeo.',
				),
				'cookies'         => array(
					'de' => 'vuid (2 Jahre) – gesetzt von Vimeo',
					'en' => 'vuid (2 years) – set by Vimeo',
				),
				'privacy_url'     => 'https://vimeo.com/privacy',
				'third_country'   => $usa,
				'fields'          => array(),
				'cookie_patterns' => array(),
				'hosts'           => array( 'vimeo.com' ),
			),
			'google_maps' => array(
				'category'        => 'external_media',
				'name'            => 'Google Maps',
				'provider'        => self::GOOGLE,
				'purpose'         => array(
					'de' => 'Darstellung interaktiver Karten. Beim Laden werden Daten (u. a. IP-Adresse) an Google übertragen.',
					'en' => 'Displaying interactive maps. When loaded, data (including your IP address) is transferred to Google.',
				),
				'cookies'         => array(
					'de' => 'NID (6 Monate) – gesetzt von Google',
					'en' => 'NID (6 months) – set by Google',
				),
				'privacy_url'     => 'https://policies.google.com/privacy',
				'third_country'   => $usa,
				'fields'          => array(),
				'cookie_patterns' => array(),
				'hosts'           => array( 'google.com/maps', 'maps.google.', 'goo.gl/maps', 'maps.app.goo.gl' ),
			),
		);

		/**
		 * Dienste-Katalog erweitern oder Angaben anpassen.
		 *
		 * @param array $catalog Katalog.
		 */
		return (array) apply_filters( 'b83_cookie_banner_catalog', $catalog );
	}

	/**
	 * Alle aktiven Dienste (Vorlagen + eigene), aufbereitet für eine Sprache.
	 *
	 * @param array<string,mixed> $settings Bereinigte Einstellungen.
	 * @return array<string, array<string,mixed>>
	 */
	public static function active( array $settings, string $lang ): array {
		$out = array();

		foreach ( self::catalog() as $id => $service ) {
			$conf = $settings['services'][ $id ] ?? array();
			if ( empty( $conf['enabled'] ) ) {
				continue;
			}
			$out[ $id ] = array(
				'id'              => $id,
				'category'        => (string) $service['category'],
				'loader'          => ! empty( $service['loader'] ),
				'name'            => (string) $service['name'],
				'provider'        => (string) $service['provider'],
				'purpose'         => self::pick( $service['purpose'], $lang ),
				'cookies'         => self::pick( $service['cookies'], $lang ),
				'privacy_url'     => (string) $service['privacy_url'],
				'third_country'   => self::pick( $service['third_country'], $lang ),
				'cookie_patterns' => (array) $service['cookie_patterns'],
				'hosts'           => (array) $service['hosts'],
				'config'          => array_intersect_key( $conf, (array) $service['fields'] ),
				'custom'          => false,
			);
		}

		foreach ( $settings['custom'] as $custom ) {
			$out[ $custom['id'] ] = array(
				'id'              => $custom['id'],
				'category'        => $custom['category'],
				'loader'          => false,
				'name'            => $custom['name'],
				'provider'        => $custom['provider'],
				'purpose'         => 'en' === $lang && '' !== $custom['purpose_en'] ? $custom['purpose_en'] : $custom['purpose_de'],
				'cookies'         => $custom['cookies'],
				'privacy_url'     => $custom['privacy_url'],
				'third_country'   => $custom['third_country'],
				'cookie_patterns' => $custom['cookie_patterns'],
				'hosts'           => $custom['hosts'],
				'config'          => array(),
				'custom'          => true,
			);
		}

		return $out;
	}

	/**
	 * Welcher aktive Dienst ist für eine eingebettete URL zuständig?
	 *
	 * @param array<string, array<string,mixed>> $active Aktive Dienste.
	 */
	public static function match_url( string $url, array $active ): ?string {
		$url = strtolower( $url );
		$url = (string) preg_replace( '#^(https?:)?//(www\.)?#', '', $url );
		foreach ( $active as $id => $service ) {
			foreach ( $service['hosts'] as $host ) {
				$host = strtolower( (string) $host );
				if ( '' !== $host && ( str_starts_with( $url, $host ) || str_contains( $url, '.' . $host ) || ( str_ends_with( $host, '.' ) && str_starts_with( $url, $host ) ) ) ) {
					return (string) $id;
				}
			}
		}
		return null;
	}

	/**
	 * @param array<string,string>|string $texts Texte je Sprache.
	 */
	private static function pick( $texts, string $lang ): string {
		if ( ! is_array( $texts ) ) {
			return (string) $texts;
		}
		return (string) ( $texts[ $lang ] ?? $texts['de'] ?? '' );
	}
}
