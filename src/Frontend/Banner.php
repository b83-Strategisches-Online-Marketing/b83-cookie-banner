<?php
/**
 * Banner-Ausgabe, Google Consent Mode und Konfiguration für das Frontend-Skript.
 *
 * Cache-sicher: Das HTML ist für alle Besucher gleich. Ob der Banner
 * erscheint und welche Dienste geladen werden, entscheidet das Skript im
 * Browser anhand des Einwilligungs-Cookies.
 *
 * @package B83\CookieBanner
 */

declare(strict_types=1);

namespace B83\CookieBanner\Frontend;

use B83\CookieBanner\Rest\ConsentEndpoint;
use B83\CookieBanner\Services;
use B83\CookieBanner\Settings;

defined( 'ABSPATH' ) || exit;

final class Banner {

	public const COOKIE        = 'b83_consent';
	public const LEGACY_COOKIE = 'b83_cookie_consent';

	public function register(): void {
		add_action( 'wp_head', array( $this, 'consent_mode' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render' ), 100 );
		add_shortcode( 'b83_cookie_settings', array( $this, 'settings_link' ) );

		// WP Rocket: Banner-Skripte nie verzögern oder zusammenfassen.
		add_filter( 'rocket_delay_js_exclusions', array( $this, 'rocket_exclusions' ) );
		add_filter( 'rocket_exclude_js', array( $this, 'rocket_exclusions' ) );
		add_filter( 'rocket_exclude_defer_js', array( $this, 'rocket_exclusions' ) );
	}

	private function is_excluded_context(): bool {
		return is_admin() || is_feed() || ( function_exists( 'is_customize_preview' ) && is_customize_preview() )
			|| isset( $_GET['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Erkennung der Elementor-Vorschau.
	}

	/**
	 * @param string[] $exclusions Ausschlüsse.
	 * @return string[]
	 */
	public function rocket_exclusions( $exclusions ): array {
		$exclusions   = is_array( $exclusions ) ? $exclusions : array();
		$exclusions[] = 'b83-cookie-banner/assets/js/banner.js';
		$exclusions[] = 'b83CookieConfig';
		$exclusions[] = 'b83-consent-mode';
		return $exclusions;
	}

	/* ---------------------------------------------------------------------
	 * Google Consent Mode v2 – so früh wie möglich im <head>
	 * ------------------------------------------------------------------- */

	public function consent_mode(): void {
		if ( $this->is_excluded_context() ) {
			return;
		}
		$settings = Settings::get();
		$map      = array();
		foreach ( Services::active( $settings, 'de' ) as $id => $service ) {
			$map[ $id ] = $service['category'];
		}
		$config = array(
			'c' => self::COOKIE,
			'r' => (int) $settings['revision'],
			'm' => $map,
		);

		// Kleine Inline-Konfiguration: muss vor jedem Google-Tag ausgeführt werden.
		$js = 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
			. 'gtag("consent","default",{ad_storage:"denied",ad_user_data:"denied",ad_personalization:"denied",analytics_storage:"denied",personalization_storage:"denied",functionality_storage:"granted",security_storage:"granted",wait_for_update:500});'
			. 'gtag("set","ads_data_redaction",true);'
			. '(function(k){try{var m=document.cookie.match(new RegExp("(?:^|; )"+k.c+"=([^;]*)"));if(!m)return;var d=JSON.parse(decodeURIComponent(m[1]));'
			. 'if(!d||d.rev!==k.r||!Array.isArray(d.services))return;var s=false,a=false;d.services.forEach(function(id){if(k.m[id]==="statistics")s=true;if(k.m[id]==="marketing")a=true;});'
			. 'var g=function(b){return b?"granted":"denied";};gtag("consent","update",{analytics_storage:g(s),ad_storage:g(a),ad_user_data:g(a),ad_personalization:g(a),personalization_storage:g(a)});}catch(e){}})('
			. wp_json_encode( $config ) . ');';

		echo '<script id="b83-consent-mode" data-no-optimize="1" data-no-defer="1" data-cfasync="false">' . $js . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statischer Code mit JSON-kodierter Konfiguration.
	}

	/* ---------------------------------------------------------------------
	 * Skript und Stile
	 * ------------------------------------------------------------------- */

	public function enqueue(): void {
		if ( $this->is_excluded_context() ) {
			return;
		}
		$settings = Settings::get();
		$lang     = Settings::lang();

		wp_enqueue_style( 'b83-cookie-banner', B83_CB_URL . 'assets/css/banner.css', array(), B83_CB_VERSION );
		wp_add_inline_style( 'b83-cookie-banner', Design::css( $settings ) );

		wp_enqueue_script( 'b83-cookie-banner', B83_CB_URL . 'assets/js/banner.js', array(), B83_CB_VERSION, array( 'in_footer' => true ) );

		$services = array();
		foreach ( Services::active( $settings, $lang ) as $id => $service ) {
			$services[ $id ] = array(
				'category'       => $service['category'],
				'loader'         => $service['loader'],
				'config'         => $service['config'],
				'cookiePatterns' => $service['cookie_patterns'],
			);
		}
		$code = array();
		foreach ( $settings['custom'] as $custom ) {
			if ( '' !== trim( $custom['code'] ) ) {
				$code[ $custom['id'] ] = $custom['code'];
			}
		}

		wp_localize_script(
			'b83-cookie-banner',
			'b83CookieConfig',
			array(
				'cookie'       => self::COOKIE,
				'legacyCookie' => self::LEGACY_COOKIE,
				'revision'     => (int) $settings['revision'],
				'lifetimeDays' => (int) $settings['lifetime_months'] * 30,
				'lang'         => $lang,
				'restUrl'      => esc_url_raw( rest_url( ConsentEndpoint::NAMESPACE . '/consent' ) ),
				'services'     => $services,
				'code'         => $code,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * HTML
	 * ------------------------------------------------------------------- */

	public function render(): void {
		if ( $this->is_excluded_context() ) {
			return;
		}
		$settings   = Settings::get();
		$lang       = Settings::lang();
		$texts      = Settings::texts( $lang );
		$active     = Services::active( $settings, $lang );
		$categories = Services::categories();
		$t          = static fn( string $key ): string => Texts::get( $key, $lang );

		$by_category = array_fill_keys( Services::CATEGORIES, array() );
		foreach ( $active as $id => $service ) {
			$by_category[ $service['category'] ][ $id ] = $service;
		}
		?>
		<div id="b83-cb" class="b83-cb b83-cb--<?php echo esc_attr( $settings['position'] ); ?>" hidden lang="<?php echo esc_attr( $lang ); ?>">
			<div class="b83-cb__dialog" role="dialog" aria-modal="false" aria-labelledby="b83-cb-title" aria-describedby="b83-cb-text">

				<div class="b83-cb__view" data-b83-view="intro">
					<h2 id="b83-cb-title" class="b83-cb__title"><?php echo esc_html( $texts['title'] ); ?></h2>
					<div id="b83-cb-text" class="b83-cb__text"><?php echo wp_kses( wpautop( $texts['message'] ), Settings::message_html() + array( 'p' => array() ) ); ?></div>
					<?php $this->links( $texts, $t ); ?>
					<div class="b83-cb__actions">
						<button type="button" class="b83-cb__btn b83-cb__btn--choice" data-b83-cb="accept"><?php echo esc_html( $t( 'accept_all' ) ); ?></button>
						<button type="button" class="b83-cb__btn b83-cb__btn--choice" data-b83-cb="reject"><?php echo esc_html( $t( 'reject_all' ) ); ?></button>
						<button type="button" class="b83-cb__btn b83-cb__btn--secondary" data-b83-cb="settings"><?php echo esc_html( $t( 'settings' ) ); ?></button>
					</div>
				</div>

				<div class="b83-cb__view" data-b83-view="settings" hidden>
					<h2 class="b83-cb__title" tabindex="-1"><?php echo esc_html( $t( 'settings_title' ) ); ?></h2>
					<p class="b83-cb__text"><?php echo esc_html( $t( 'settings_intro' ) ); ?></p>

					<div class="b83-cb__cats">
						<?php
						foreach ( Services::CATEGORIES as $cat ) :
							$selectable = array_filter( $by_category[ $cat ], static fn( array $s ): bool => ! $s['loader'] );
							if ( 'necessary' !== $cat && empty( $selectable ) ) {
								continue;
							}
							$label = $categories[ $cat ]['label'][ $lang ] ?? $categories[ $cat ]['label']['de'];
							?>
							<section class="b83-cb__cat">
								<div class="b83-cb__cat-head">
									<label class="b83-cb__switch">
										<?php if ( 'necessary' === $cat ) : ?>
											<input type="checkbox" checked disabled>
										<?php else : ?>
											<input type="checkbox" data-b83-cat="<?php echo esc_attr( $cat ); ?>">
										<?php endif; ?>
										<span class="b83-cb__switch-ui" aria-hidden="true"></span>
										<span class="b83-cb__cat-label"><?php echo esc_html( $label ); ?></span>
									</label>
									<?php if ( 'necessary' === $cat ) : ?>
										<span class="b83-cb__badge"><?php echo esc_html( $t( 'always_active' ) ); ?></span>
									<?php endif; ?>
									<button type="button" class="b83-cb__more" aria-expanded="false" aria-controls="b83-cb-cat-<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $t( 'details' ) ); ?></button>
								</div>
								<p class="b83-cb__cat-desc"><?php echo esc_html( $categories[ $cat ]['description'][ $lang ] ?? $categories[ $cat ]['description']['de'] ); ?></p>
								<div id="b83-cb-cat-<?php echo esc_attr( $cat ); ?>" class="b83-cb__services" hidden>
									<?php
									if ( 'necessary' === $cat ) {
										$this->consent_cookie_info( $settings, $t );
									}
									foreach ( $by_category[ $cat ] as $id => $service ) {
										$this->service( (string) $id, $service, $t );
									}
									?>
								</div>
							</section>
						<?php endforeach; ?>
					</div>

					<p class="b83-cb__id" hidden><?php echo esc_html( $t( 'consent_id' ) ); ?>: <code data-b83-cb-id></code></p>
					<?php $this->links( $texts, $t ); ?>
					<div class="b83-cb__actions">
						<button type="button" class="b83-cb__btn b83-cb__btn--choice" data-b83-cb="save"><?php echo esc_html( $t( 'save' ) ); ?></button>
						<button type="button" class="b83-cb__btn b83-cb__btn--choice" data-b83-cb="accept"><?php echo esc_html( $t( 'accept_all' ) ); ?></button>
						<button type="button" class="b83-cb__btn b83-cb__btn--choice" data-b83-cb="reject"><?php echo esc_html( $t( 'reject_all' ) ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php if ( $settings['show_reopen'] ) : ?>
			<button type="button" id="b83-cb-reopen" class="b83-cb-reopen b83-cb-reopen--<?php echo esc_attr( $settings['position'] ); ?>" data-b83-cb-open aria-label="<?php echo esc_attr( $t( 'reopen' ) ); ?>" title="<?php echo esc_attr( $t( 'reopen' ) ); ?>" hidden>
				<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-4-4 4 4 0 0 1-4-4 2 2 0 0 1-2-2zm-3.5 7a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm6 5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm-7 2a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm4-9a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
			</button>
		<?php endif; ?>
		<?php
	}

	/**
	 * @param array<string,string> $texts Texte.
	 * @param callable             $t     Übersetzer.
	 */
	private function links( array $texts, callable $t ): void {
		$links = array();
		if ( '' !== $texts['privacy_url'] ) {
			$links[] = '<a href="' . esc_url( $texts['privacy_url'] ) . '">' . esc_html( $t( 'privacy' ) ) . '</a>';
		}
		if ( '' !== $texts['imprint_url'] ) {
			$links[] = '<a href="' . esc_url( $texts['imprint_url'] ) . '">' . esc_html( $t( 'imprint' ) ) . '</a>';
		}
		if ( $links ) {
			echo '<p class="b83-cb__links">' . implode( ' · ', $links ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- oben escaped.
		}
	}

	/**
	 * @param array<string,mixed> $settings Einstellungen.
	 * @param callable            $t        Übersetzer.
	 */
	private function consent_cookie_info( array $settings, callable $t ): void {
		?>
		<div class="b83-cb__service">
			<div class="b83-cb__service-name"><?php echo esc_html( 'b83 Cookie Banner' ); ?></div>
			<dl class="b83-cb__facts">
				<dt><?php echo esc_html( $t( 'provider' ) ); ?></dt><dd><?php echo esc_html( $t( 'this_website' ) ); ?></dd>
				<dt><?php echo esc_html( $t( 'purpose' ) ); ?></dt><dd><?php echo esc_html( $t( 'consent_cookie' ) ); ?></dd>
				<dt><?php echo esc_html( $t( 'cookies' ) ); ?></dt><dd><?php echo esc_html( self::COOKIE . ' (' . sprintf( $t( 'months' ), (int) $settings['lifetime_months'] ) . ')' ); ?></dd>
			</dl>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed> $service Dienst.
	 * @param callable            $t       Übersetzer.
	 */
	private function service( string $id, array $service, callable $t ): void {
		?>
		<div class="b83-cb__service">
			<?php if ( $service['loader'] ) : ?>
				<div class="b83-cb__service-name"><?php echo esc_html( $service['name'] ); ?></div>
				<p class="b83-cb__note"><?php echo esc_html( $t( 'loader_note' ) ); ?></p>
			<?php else : ?>
				<label class="b83-cb__service-name">
					<input type="checkbox" data-b83-service="<?php echo esc_attr( $id ); ?>" data-b83-category="<?php echo esc_attr( $service['category'] ); ?>">
					<?php echo esc_html( $service['name'] ); ?>
				</label>
			<?php endif; ?>
			<dl class="b83-cb__facts">
				<?php if ( '' !== $service['provider'] ) : ?>
					<dt><?php echo esc_html( $t( 'provider' ) ); ?></dt><dd><?php echo esc_html( $service['provider'] ); ?></dd>
				<?php endif; ?>
				<?php if ( '' !== $service['purpose'] ) : ?>
					<dt><?php echo esc_html( $t( 'purpose' ) ); ?></dt><dd><?php echo esc_html( $service['purpose'] ); ?></dd>
				<?php endif; ?>
				<?php if ( '' !== $service['cookies'] ) : ?>
					<dt><?php echo esc_html( $t( 'cookies' ) ); ?></dt><dd><?php echo esc_html( $service['cookies'] ); ?></dd>
				<?php endif; ?>
				<?php if ( '' !== $service['third_country'] ) : ?>
					<dt><?php echo esc_html( $t( 'third_country' ) ); ?></dt><dd><?php echo esc_html( $service['third_country'] ); ?></dd>
				<?php endif; ?>
				<?php if ( '' !== $service['privacy_url'] ) : ?>
					<dt><?php echo esc_html( $t( 'privacy' ) ); ?></dt><dd><a href="<?php echo esc_url( $service['privacy_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $t( 'privacy_link' ) ); ?></a></dd>
				<?php endif; ?>
			</dl>
		</div>
		<?php
	}

	/**
	 * [b83_cookie_settings]Text[/b83_cookie_settings] – öffnet die Einstellungen.
	 *
	 * @param array<string,string>|string $atts    Attribute.
	 * @param string|null                 $content Linktext.
	 */
	public function settings_link( $atts, $content = null ): string {
		$label = ( null !== $content && '' !== trim( wp_strip_all_tags( $content ) ) ) ? wp_strip_all_tags( $content ) : Texts::get( 'reopen', Settings::lang() );
		return '<button type="button" class="b83-cookie-reopen-link" data-b83-cb-open>' . esc_html( $label ) . '</button>';
	}
}
