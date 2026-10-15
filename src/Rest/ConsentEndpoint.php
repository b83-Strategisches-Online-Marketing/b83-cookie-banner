<?php
/**
 * REST-Endpunkt für das Einwilligungsprotokoll.
 *
 * POST /wp-json/b83-cookie-banner/v1/consent
 *
 * Bewusst öffentlich und ohne Nonce: Seiten werden von Cache-Plugins
 * zwischengespeichert, ein Nonce wäre dort nach spätestens 24 Stunden
 * abgelaufen und der Nachweis ginge verloren. Schutz stattdessen durch
 * strenge Validierung und eine Begrenzung der Anfragen je Besucher.
 *
 * @package B83\CookieBanner
 */

declare(strict_types=1);

namespace B83\CookieBanner\Rest;

use B83\CookieBanner\ConsentLog;
use B83\CookieBanner\Services;
use B83\CookieBanner\Settings;

defined( 'ABSPATH' ) || exit;

final class ConsentEndpoint {

	public const NAMESPACE = 'b83-cookie-banner/v1';
	private const LIMIT    = 20; // Anfragen je Besucher und Minute.

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/consent',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				// Öffentlich: Jeder Besucher muss seine Einwilligung protokollieren können.
				'permission_callback' => '__return_true',
				'args'                => array(
					'consent_id' => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
					),
					'action'     => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => ConsentLog::ACTIONS,
					),
					'services'   => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'string' ),
						'maxItems' => 60,
						'default'  => array(),
					),
					'revision'   => array(
						'type'    => 'integer',
						'minimum' => 0,
						'maximum' => 100000,
						'default' => 0,
					),
					'lang'       => array(
						'type'    => 'string',
						'enum'    => array( 'de', 'en' ),
						'default' => 'de',
					),
					'page'       => array(
						'type'      => 'string',
						'maxLength' => 2000,
						'default'   => '',
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Anfrage.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( $request ) {
		if ( $this->rate_limited() ) {
			return new \WP_Error( 'b83_cb_rate_limit', 'Too many requests', array( 'status' => 429 ) );
		}

		$entry = self::validate(
			array(
				'consent_id' => $request->get_param( 'consent_id' ),
				'action'     => $request->get_param( 'action' ),
				'services'   => $request->get_param( 'services' ),
				'revision'   => $request->get_param( 'revision' ),
				'lang'       => $request->get_param( 'lang' ),
				'page'       => $request->get_param( 'page' ),
			)
		);
		if ( null === $entry ) {
			return new \WP_Error( 'b83_cb_invalid', 'Invalid consent', array( 'status' => 400 ) );
		}

		if ( ! ConsentLog::insert( $entry ) ) {
			return new \WP_Error( 'b83_cb_db', 'Could not store consent', array( 'status' => 500 ) );
		}
		return new \WP_REST_Response( array( 'ok' => true ), 201 );
	}

	/**
	 * Prüft und normalisiert einen Eintrag. Unbekannte Dienste werden verworfen,
	 * die Kategorien werden aus den Diensten abgeleitet (nicht vom Browser übernommen).
	 *
	 * @param array<string,mixed> $raw Rohdaten.
	 * @return array{consent_id:string,action:string,categories:string[],services:string[],revision:int,lang:string,page:string}|null
	 */
	public static function validate( array $raw ): ?array {
		$id = is_string( $raw['consent_id'] ?? null ) ? strtolower( $raw['consent_id'] ) : '';
		if ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id ) ) {
			return null;
		}
		$action = is_string( $raw['action'] ?? null ) ? $raw['action'] : '';
		if ( ! in_array( $action, ConsentLog::ACTIONS, true ) ) {
			return null;
		}

		$active   = Services::active( Settings::get(), 'de' );
		$services = array();
		foreach ( (array) ( $raw['services'] ?? array() ) as $service ) {
			if ( is_string( $service ) && isset( $active[ $service ] ) ) {
				$services[] = $service;
			}
		}
		$services = array_values( array_unique( $services ) );

		$categories = array( 'necessary' );
		foreach ( $services as $service ) {
			$categories[] = $active[ $service ]['category'];
		}

		$page = is_string( $raw['page'] ?? null ) ? (string) wp_parse_url( $raw['page'], PHP_URL_PATH ) : '';

		return array(
			'consent_id' => $id,
			'action'     => $action,
			'categories' => array_values( array_unique( $categories ) ),
			'services'   => $services,
			'revision'   => max( 0, (int) ( $raw['revision'] ?? 0 ) ),
			'lang'       => ( $raw['lang'] ?? '' ) === 'en' ? 'en' : 'de',
			'page'       => mb_substr( sanitize_text_field( $page ), 0, 255 ),
		);
	}

	/** Einfache Begrenzung je IP und Minute. Die IP wird nur gehasht und nur kurz im Zwischenspeicher gehalten. */
	private function rate_limited(): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'b83_cb_rl_' . substr( hash( 'sha256', $ip . wp_salt( 'nonce' ) . gmdate( 'YmdHi' ) ), 0, 24 );
		$n   = (int) get_transient( $key );
		if ( $n >= self::LIMIT ) {
			return true;
		}
		set_transient( $key, $n + 1, 2 * MINUTE_IN_SECONDS );
		return false;
	}
}
