<?php
/**
 * Feste Oberflächentexte des Banners auf Deutsch und Englisch.
 *
 * Titel, Bannertext und Links sind in den Einstellungen änderbar; diese
 * Texte sind bewusst einheitlich formuliert (gleichwertige Buttons).
 *
 * @package B83\CookieBanner
 */

declare(strict_types=1);

namespace B83\CookieBanner\Frontend;

defined( 'ABSPATH' ) || exit;

final class Texts {

	/** @var array<string, array<string,string>> */
	private const TEXTS = array(
		'accept_all'       => array(
			'de' => 'Alle akzeptieren',
			'en' => 'Accept all',
		),
		'reject_all'       => array(
			'de' => 'Alle ablehnen',
			'en' => 'Reject all',
		),
		'settings'         => array(
			'de' => 'Einstellungen',
			'en' => 'Settings',
		),
		'save'             => array(
			'de' => 'Auswahl speichern',
			'en' => 'Save selection',
		),
		'back'             => array(
			'de' => 'Zurück',
			'en' => 'Back',
		),
		'settings_title'   => array(
			'de' => 'Datenschutz-Einstellungen',
			'en' => 'Privacy settings',
		),
		'settings_intro'   => array(
			'de' => 'Hier können Sie festlegen, welche Kategorien und Dienste wir verwenden dürfen. Ihre Auswahl können Sie jederzeit ändern.',
			'en' => 'Choose which categories and services we may use. You can change your selection at any time.',
		),
		'privacy'          => array(
			'de' => 'Datenschutzerklärung',
			'en' => 'Privacy policy',
		),
		'imprint'          => array(
			'de' => 'Impressum',
			'en' => 'Legal notice',
		),
		'details'          => array(
			'de' => 'Details',
			'en' => 'Details',
		),
		'provider'         => array(
			'de' => 'Anbieter',
			'en' => 'Provider',
		),
		'purpose'          => array(
			'de' => 'Zweck',
			'en' => 'Purpose',
		),
		'cookies'          => array(
			'de' => 'Cookies / Speicherdauer',
			'en' => 'Cookies / duration',
		),
		'third_country'    => array(
			'de' => 'Übermittlung in Drittland',
			'en' => 'Transfer to third country',
		),
		'privacy_link'     => array(
			'de' => 'Datenschutzerklärung des Anbieters',
			'en' => 'Provider privacy policy',
		),
		'loader_note'      => array(
			'de' => 'Wird automatisch geladen, sobald Sie einem Statistik- oder Marketing-Dienst zustimmen.',
			'en' => 'Loaded automatically as soon as you consent to a statistics or marketing service.',
		),
		'consent_cookie'   => array(
			'de' => 'Speichert Ihre Datenschutz-Einstellungen.',
			'en' => 'Stores your privacy settings.',
		),
		'this_website'     => array(
			'de' => 'Betreiber dieser Website',
			'en' => 'Operator of this website',
		),
		'months'           => array(
			'de' => '%d Monate',
			'en' => '%d months',
		),
		'consent_id'       => array(
			'de' => 'Ihre Einwilligungs-ID',
			'en' => 'Your consent ID',
		),
		'reopen'           => array(
			'de' => 'Cookie-Einstellungen',
			'en' => 'Cookie settings',
		),
		'blocked_title'    => array(
			'de' => 'Externer Inhalt: %s',
			'en' => 'External content: %s',
		),
		'blocked_text'     => array(
			'de' => 'Hier wird ein Inhalt von %1$s (%2$s) angezeigt. Beim Laden werden Daten an den Anbieter übertragen, gegebenenfalls auch in Drittländer.',
			'en' => 'This is content from %1$s (%2$s). When loaded, data is transferred to the provider, possibly to third countries.',
		),
		'blocked_button'   => array(
			'de' => 'Inhalt laden',
			'en' => 'Load content',
		),
		'blocked_note'     => array(
			'de' => 'Mit dem Laden stimmen Sie der Nutzung von %s zu. Widerruf jederzeit über die Cookie-Einstellungen.',
			'en' => 'By loading, you consent to the use of %s. You can withdraw at any time via the cookie settings.',
		),
		'always_active'    => array(
			'de' => 'Immer aktiv',
			'en' => 'Always active',
		),
	);

	public static function get( string $key, string $lang ): string {
		$text = self::TEXTS[ $key ][ $lang ] ?? self::TEXTS[ $key ]['de'] ?? $key;
		/**
		 * Einzelne Oberflächentexte anpassen.
		 *
		 * @param string $text Text.
		 * @param string $key  Schlüssel.
		 * @param string $lang Sprache.
		 */
		return (string) apply_filters( 'b83_cookie_banner_text', $text, $key, $lang );
	}
}
