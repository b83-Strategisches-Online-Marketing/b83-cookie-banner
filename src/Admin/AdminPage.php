<?php
/**
 * Backend unter „b83 → Cookie Banner“.
 *
 * Tabs: Allgemein, Texte, Dienste, Protokoll, Hilfe.
 *
 * @package B83\CookieBanner
 */

declare(strict_types=1);

namespace B83\CookieBanner\Admin;

use B83\CookieBanner\ConsentLog;
use B83\CookieBanner\Frontend\Design;
use B83\CookieBanner\Migration;
use B83\CookieBanner\Services;
use B83\CookieBanner\Settings;

defined( 'ABSPATH' ) || exit;

final class AdminPage {

	public const SLUG = 'b83-cookie-banner';
	private const CAP = 'manage_options';

	private string $hook = '';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_b83_cb_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_b83_cb_new_revision', array( $this, 'handle_new_revision' ) );
		add_action( 'admin_post_b83_cb_export', array( $this, 'handle_export' ) );
		add_filter( 'b83_admin_plugin_links', array( $this, 'overview_link' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( B83_CB_FILE ), array( $this, 'action_links' ) );

		( new B83Menu( B83_CB_URL, B83_CB_DIR ) )->register();
	}

	public function add_menu(): void {
		$this->hook = (string) add_submenu_page(
			B83Menu::SLUG,
			__( 'Cookie Banner', 'b83-cookie-banner' ),
			__( 'Cookie Banner', 'b83-cookie-banner' ),
			self::CAP,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * @param array<string,string> $links Links.
	 * @return array<string,string>
	 */
	public function overview_link( array $links ): array {
		$links[ plugin_basename( B83_CB_FILE ) ] = self::url();
		return $links;
	}

	/**
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Einstellungen', 'b83-cookie-banner' ) . '</a>' );
		return $links;
	}

	public static function url( string $tab = '', array $args = array() ): string {
		$args = array_merge( array( 'page' => self::SLUG ), '' !== $tab ? array( 'tab' => $tab ) : array(), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	private static function redirect( string $tab, string $notice ): void {
		wp_safe_redirect( self::url( $tab, array( 'b83_cb_notice' => $notice ) ) );
		exit;
	}

	public function enqueue( string $hook ): void {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'b83-cookie-banner-admin', B83_CB_URL . 'assets/css/admin.css', array(), B83_CB_VERSION );
		if ( 'design' === $this->current_tab() ) {
			// Echte Banner-Stile für die Vorschau, ohne eigenes CSS der Website.
			wp_enqueue_style( 'b83-cookie-banner-preview', B83_CB_URL . 'assets/css/banner.css', array(), B83_CB_VERSION );
			wp_add_inline_style( 'b83-cookie-banner-preview', Design::css( Settings::get(), false ) );
		}
		wp_enqueue_script( 'b83-cookie-banner-admin', B83_CB_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), B83_CB_VERSION, true );
		wp_localize_script(
			'b83-cookie-banner-admin',
			'b83CookieAdmin',
			array(
				'confirmRevision' => __( 'Alle Besucher werden beim nächsten Besuch erneut um ihre Einwilligung gebeten. Fortfahren?', 'b83-cookie-banner' ),
				'confirmRemove'   => __( 'Diesen Dienst entfernen?', 'b83-cookie-banner' ),
				'shadows'         => Design::SHADOWS,
				'fonts'           => Design::FONTS,
				'pill'            => Design::PILL,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Ausgabe
	 * ------------------------------------------------------------------- */

	/** @return array<string,string> */
	private function tabs(): array {
		return array(
			'allgemein' => __( 'Allgemein', 'b83-cookie-banner' ),
			'design'    => __( 'Design', 'b83-cookie-banner' ),
			'texte'     => __( 'Texte', 'b83-cookie-banner' ),
			'dienste'   => __( 'Dienste', 'b83-cookie-banner' ),
			'protokoll' => __( 'Protokoll', 'b83-cookie-banner' ),
			'hilfe'     => __( 'Hilfe', 'b83-cookie-banner' ),
		);
	}

	private function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'allgemein';
		return array_key_exists( $tab, $this->tabs() ) ? $tab : 'allgemein';
	}

	public function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-cookie-banner' ), '', array( 'response' => 403 ) );
		}
		$tab      = $this->current_tab();
		$settings = Settings::get();

		echo '<div class="wrap b83-cb-admin">';
		echo '<h1>' . esc_html__( 'Cookie Banner', 'b83-cookie-banner' ) . '</h1>';
		$this->notices( $settings );

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $this->tabs() as $key => $label ) {
			printf(
				'<a class="nav-tab%1$s" href="%2$s"%3$s>%4$s</a>',
				$tab === $key ? ' nav-tab-active' : '',
				esc_url( self::url( $key ) ),
				$tab === $key ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		if ( in_array( $tab, array( 'allgemein', 'design', 'texte', 'dienste' ), true ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="b83-cb-form">';
			echo '<input type="hidden" name="action" value="b83_cb_save"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
			wp_nonce_field( 'b83_cb_save', 'b83_cb_nonce' );
			$this->{'tab_' . $tab}( $settings );
			submit_button( __( 'Speichern', 'b83-cookie-banner' ) );
			echo '</form>';
		} else {
			$this->{'tab_' . $tab}( $settings );
		}

		echo '<p class="b83-cb-admin__footer">b83 Cookie Banner ' . esc_html( B83_CB_VERSION ) . ' · <a href="https://b83.at" target="_blank" rel="noopener">b83.at</a></p>';
		echo '</div>';
	}

	/** @param array<string,mixed> $settings Einstellungen. */
	private function notices( array $settings ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Anzeige fester Texte.
		$key     = isset( $_GET['b83_cb_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['b83_cb_notice'] ) ) : '';
		$notices = array(
			'saved'    => array( 'success', __( 'Gespeichert. Der Seiten-Cache wurde geleert (WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, SiteGround).', 'b83-cookie-banner' ) ),
			'revision' => array( 'success', __( 'Neue Version des Banners aktiv: Alle Besucher werden erneut gefragt.', 'b83-cookie-banner' ) ),
		);
		if ( isset( $notices[ $key ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $notices[ $key ][0] ), esc_html( $notices[ $key ][1] ) );
		}

		if ( get_option( Migration::OPTION_MIGRATED ) && ! get_option( 'b83_cookie_banner_services_reviewed' ) ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Bitte prüfen:', 'b83-cookie-banner' ) . '</strong> ';
			echo esc_html__( 'Die Einstellungen aus Version 6 wurden übernommen. Bitte unter „Dienste“ kontrollieren, welche Dienste tatsächlich eingesetzt werden (z. B. was über den Google Tag Manager läuft), und die Angaben zu übernommenen Skripten ergänzen. Alle Besucher werden einmalig erneut gefragt.', 'b83-cookie-banner' );
			echo ' <a href="' . esc_url( self::url( 'dienste' ) ) . '">' . esc_html__( 'Zu den Diensten', 'b83-cookie-banner' ) . '</a></p></div>';
		}

		$de = $settings['texts']['de'];
		if ( '' === $de['privacy_url'] || '' === $de['imprint_url'] ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Link zur Datenschutzerklärung und zum Impressum fehlt. Beide sollten im Banner erreichbar sein (Tab „Texte“).', 'b83-cookie-banner' ) . '</p></div>';
		}
		if ( ! $settings['enabled'] ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Der Banner ist derzeit ausgeschaltet.', 'b83-cookie-banner' ) . '</p></div>';
		}
	}

	/* ---------------------------------------------------------------------
	 * Tabs
	 * ------------------------------------------------------------------- */

	/** @param array<string,mixed> $s Einstellungen. */
	private function tab_allgemein( array $s ): void {
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->row_checkbox( 'enabled', __( 'Banner aktiv', 'b83-cookie-banner' ), $s['enabled'], __( 'Banner anzeigen und Dienste erst nach Zustimmung laden', 'b83-cookie-banner' ) );

		$this->row_checkbox( 'show_reopen', __( 'Einstellungs-Symbol', 'b83-cookie-banner' ), $s['show_reopen'], __( 'Kleines Symbol am Bildschirmrand, über das Besucher ihre Auswahl jederzeit ändern können', 'b83-cookie-banner' ), __( 'Wenn ausgeschaltet: unbedingt den Shortcode [b83_cookie_settings] z. B. im Footer einbauen – der Widerruf muss so einfach sein wie die Zustimmung.', 'b83-cookie-banner' ) );

		$this->row_checkbox( 'en_enabled', __( 'Englisch', 'b83-cookie-banner' ), $s['en_enabled'], __( 'Auf englischen Seiten (WPML, Polylang, TranslatePress) die englischen Texte verwenden', 'b83-cookie-banner' ) );

		$this->row_number( 'lifetime_months', __( 'Gültigkeit der Einwilligung', 'b83-cookie-banner' ), $s['lifetime_months'], 1, 12, __( 'Monate. Danach wird erneut gefragt.', 'b83-cookie-banner' ) );
		$this->row_number( 'log_retention_months', __( 'Aufbewahrung des Protokolls', 'b83-cookie-banner' ), $s['log_retention_months'], 6, 120, __( 'Monate. Ältere Einträge werden automatisch gelöscht.', 'b83-cookie-banner' ) );

		echo '<tr><th scope="row">' . esc_html__( 'Banner-Version', 'b83-cookie-banner' ) . '</th><td>';
		/* translators: %d: Versionsnummer */
		echo '<p>' . esc_html( sprintf( __( 'Aktuell: %d', 'b83-cookie-banner' ), (int) $s['revision'] ) ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Wenn neue Dienste dazukommen oder sich Zwecke ändern, müssen alle Besucher erneut gefragt werden.', 'b83-cookie-banner' ) . '</p>';
		echo '<p><a class="button b83-cb-new-revision" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=b83_cb_new_revision' ), 'b83_cb_new_revision' ) ) . '">' . esc_html__( 'Alle Besucher erneut fragen', 'b83-cookie-banner' ) . '</a></p>';
		echo '</td></tr>';

		echo '</tbody></table>';
	}

	/** @param array<string,mixed> $s Einstellungen. */
	private function tab_design( array $s ): void {
		$d      = $s['design'];
		$issues = Design::contrast_issues( $s );
		if ( $issues ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Schwer lesbar (Kontrast unter 4,5 : 1):', 'b83-cookie-banner' ) . ' ' . esc_html( implode( ', ', $issues ) ) . '</p></div>';
		}

		echo '<div class="b83-cb-design">';
		echo '<div class="b83-cb-design__fields">';

		echo '<div class="b83-cb-card"><h2>' . esc_html__( 'Allgemein', 'b83-cookie-banner' ) . '</h2><table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="b83-cb-position">' . esc_html__( 'Position', 'b83-cookie-banner' ) . '</label></th><td><select id="b83-cb-position" name="b83_cb[position]">';
		foreach ( array(
			'bottom_left'   => __( 'Unten links', 'b83-cookie-banner' ),
			'bottom_center' => __( 'Unten mittig', 'b83-cookie-banner' ),
			'bottom_right'  => __( 'Unten rechts', 'b83-cookie-banner' ),
			'center'        => __( 'Mittig', 'b83-cookie-banner' ),
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $s['position'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="b83-cb-font">' . esc_html__( 'Schrift', 'b83-cookie-banner' ) . '</label></th><td><select id="b83-cb-font" name="b83_cb[design][font]" data-b83-design>';
		foreach ( array(
			'system'  => __( 'Systemschrift', 'b83-cookie-banner' ),
			'inherit' => __( 'Schrift der Website übernehmen', 'b83-cookie-banner' ),
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $d['font'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->color_row( 'b83_cb[accent_color]', __( 'Akzentfarbe', 'b83-cookie-banner' ), $s['accent_color'], '#d96f21', __( 'Links, Schalter und Standardfarbe der Buttons.', 'b83-cookie-banner' ) );
		echo '</tbody></table></div>';

		echo '<div class="b83-cb-card"><h2>' . esc_html__( 'Box', 'b83-cookie-banner' ) . '</h2><table class="form-table" role="presentation"><tbody>';
		$this->color_row( 'b83_cb[design][box_bg]', __( 'Hintergrund', 'b83-cookie-banner' ), $d['box_bg'], '#ffffff' );
		$this->color_row( 'b83_cb[design][box_text]', __( 'Textfarbe', 'b83-cookie-banner' ), $d['box_text'], '#1f2328' );
		$this->color_row( 'b83_cb[design][box_border_color]', __( 'Rahmenfarbe', 'b83-cookie-banner' ), $d['box_border_color'], '#e1e4e8' );
		$this->number_row( 'box_border_width', __( 'Rahmenstärke', 'b83-cookie-banner' ), $d, 'px' );
		$this->number_row( 'box_radius', __( 'Eckenabrundung', 'b83-cookie-banner' ), $d, 'px' );
		echo '<tr><th scope="row"><label for="b83-cb-shadow">' . esc_html__( 'Schatten', 'b83-cookie-banner' ) . '</label></th><td><select id="b83-cb-shadow" name="b83_cb[design][box_shadow]" data-b83-design>';
		foreach ( array(
			'none'   => __( 'Kein Schatten', 'b83-cookie-banner' ),
			'light'  => __( 'Leicht', 'b83-cookie-banner' ),
			'medium' => __( 'Mittel', 'b83-cookie-banner' ),
			'strong' => __( 'Stark', 'b83-cookie-banner' ),
		) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $d['box_shadow'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		$this->number_row( 'box_width', __( 'Breite', 'b83-cookie-banner' ), $d, 'px', __( 'Auf dem Handy immer volle Breite.', 'b83-cookie-banner' ) );
		$this->number_row( 'box_padding', __( 'Innenabstand', 'b83-cookie-banner' ), $d, 'px' );
		echo '</tbody></table></div>';

		echo '<div class="b83-cb-card"><h2>' . esc_html__( 'Buttons „Alle akzeptieren“, „Alle ablehnen“, „Auswahl speichern“', 'b83-cookie-banner' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Diese Buttons teilen sich bewusst eine Gestaltung: Ablehnen muss genauso auffällig sein wie Zustimmen. Leere Farbfelder = automatisch aus der Akzentfarbe.', 'b83-cookie-banner' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->color_row( 'b83_cb[design][btn_bg]', __( 'Hintergrund', 'b83-cookie-banner' ), $d['btn_bg'], '' );
		$this->color_row( 'b83_cb[design][btn_text]', __( 'Schriftfarbe', 'b83-cookie-banner' ), $d['btn_text'], '#ffffff' );
		$this->color_row( 'b83_cb[design][btn_border]', __( 'Rahmenfarbe', 'b83-cookie-banner' ), $d['btn_border'], '' );
		$this->color_row( 'b83_cb[design][btn_hover_bg]', __( 'Hintergrund (Hover)', 'b83-cookie-banner' ), $d['btn_hover_bg'], '' );
		$this->color_row( 'b83_cb[design][btn_hover_text]', __( 'Schriftfarbe (Hover)', 'b83-cookie-banner' ), $d['btn_hover_text'], '#ffffff' );
		$this->color_row( 'b83_cb[design][btn_hover_border]', __( 'Rahmenfarbe (Hover)', 'b83-cookie-banner' ), $d['btn_hover_border'], '' );
		echo '</tbody></table></div>';

		echo '<div class="b83-cb-card"><h2>' . esc_html__( 'Button „Einstellungen“', 'b83-cookie-banner' ) . '</h2><table class="form-table" role="presentation"><tbody>';
		$this->color_row( 'b83_cb[design][btn2_bg]', __( 'Hintergrund', 'b83-cookie-banner' ), $d['btn2_bg'], '#ffffff' );
		$this->color_row( 'b83_cb[design][btn2_text]', __( 'Schriftfarbe', 'b83-cookie-banner' ), $d['btn2_text'], '#1f2328' );
		$this->color_row( 'b83_cb[design][btn2_border]', __( 'Rahmenfarbe', 'b83-cookie-banner' ), $d['btn2_border'], '#d0d7de' );
		$this->color_row( 'b83_cb[design][btn2_hover_bg]', __( 'Hintergrund (Hover)', 'b83-cookie-banner' ), $d['btn2_hover_bg'], '#f3f4f6' );
		$this->color_row( 'b83_cb[design][btn2_hover_text]', __( 'Schriftfarbe (Hover)', 'b83-cookie-banner' ), $d['btn2_hover_text'], '#1f2328' );
		$this->color_row( 'b83_cb[design][btn2_hover_border]', __( 'Rahmenfarbe (Hover)', 'b83-cookie-banner' ), $d['btn2_hover_border'], '#afb8c1' );
		echo '</tbody></table></div>';

		echo '<div class="b83-cb-card"><h2>' . esc_html__( 'Alle Buttons', 'b83-cookie-banner' ) . '</h2><table class="form-table" role="presentation"><tbody>';
		/* translators: %d: Wert für vollständig runde Buttons */
		$this->number_row( 'btn_radius', __( 'Abrundung', 'b83-cookie-banner' ), $d, 'px', sprintf( __( '%d = vollständig rund (Pillenform)', 'b83-cookie-banner' ), Design::PILL ) );
		$this->number_row( 'btn_border_width', __( 'Rahmenstärke', 'b83-cookie-banner' ), $d, 'px' );
		echo '</tbody></table></div>';

		echo '<div class="b83-cb-card"><h2>' . esc_html__( 'Eigenes CSS', 'b83-cookie-banner' ) . '</h2>';
		echo '<textarea class="large-text code" rows="6" name="b83_cb[custom_css]" aria-label="' . esc_attr__( 'Eigenes CSS', 'b83-cookie-banner' ) . '">' . esc_textarea( $s['custom_css'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Nur für Sonderfälle, ohne <style>-Tag. Zustimmen und Ablehnen müssen gleich auffällig bleiben.', 'b83-cookie-banner' ) . '</p></div>';

		echo '</div>';

		// Live-Vorschau mit den echten Banner-Stilen.
		$texts = Settings::texts( 'de' );
		echo '<div class="b83-cb-design__preview"><div class="b83-cb-design__sticky">';
		echo '<h2>' . esc_html__( 'Vorschau', 'b83-cookie-banner' ) . '</h2>';
		echo '<div class="b83-cb-preview-stage"><div class="b83-cb b83-cb--preview"><div class="b83-cb__dialog"><div class="b83-cb__view">';
		echo '<h2 class="b83-cb__title">' . esc_html( $texts['title'] ) . '</h2>';
		echo '<div class="b83-cb__text">' . wp_kses( wpautop( wp_trim_words( $texts['message'], 28 ) ), Settings::message_html() + array( 'p' => array() ) ) . '</div>';
		echo '<p class="b83-cb__links"><a href="#">Datenschutzerklärung</a> · <a href="#">Impressum</a></p>';
		echo '<div class="b83-cb__actions">';
		echo '<button type="button" class="b83-cb__btn b83-cb__btn--choice">Alle akzeptieren</button>';
		echo '<button type="button" class="b83-cb__btn b83-cb__btn--choice">Alle ablehnen</button>';
		echo '<button type="button" class="b83-cb__btn b83-cb__btn--secondary">Einstellungen</button>';
		echo '</div></div></div></div></div>';
		echo '<p class="description">' . esc_html__( 'Mit der Maus über die Buttons fahren, um die Hover-Farben zu sehen. Änderungen werden erst mit „Speichern“ übernommen.', 'b83-cookie-banner' ) . '</p>';
		echo '</div></div>';

		echo '</div>';
	}

	private function color_row( string $name, string $label, string $value, string $default, string $hint = '' ): void {
		$auto = '' === $default;
		printf(
			'<tr><th scope="row">%1$s</th><td><input type="text" class="b83-cb-color" name="%2$s" value="%3$s" data-default-color="%4$s" data-b83-design aria-label="%1$s">%5$s</td></tr>',
			esc_html( $label ),
			esc_attr( $name ),
			esc_attr( $value ),
			esc_attr( $default ),
			( $auto || '' !== $hint ) ? '<p class="description">' . esc_html( $auto ? __( 'Leer = automatisch', 'b83-cookie-banner' ) . ( '' !== $hint ? ' – ' . $hint : '' ) : $hint ) . '</p>' : ''
		);
	}

	/**
	 * @param array<string,mixed> $d Design-Einstellungen.
	 */
	private function number_row( string $key, string $label, array $d, string $unit, string $hint = '' ): void {
		[ $min, $max ] = Settings::DESIGN_NUMBERS[ $key ];
		printf(
			'<tr><th scope="row"><label for="b83-cb-%1$s">%2$s</label></th><td><input type="range" id="b83-cb-%1$s" min="%3$d" max="%4$d" value="%5$d" name="b83_cb[design][%1$s]" data-b83-design data-unit="%6$s"> <output for="b83-cb-%1$s">%5$d %6$s</output>%7$s</td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			$min,
			$max,
			(int) $d[ $key ],
			esc_html( $unit ),
			'' !== $hint ? '<p class="description">' . esc_html( $hint ) . '</p>' : ''
		);
	}

	/** @param array<string,mixed> $s Einstellungen. */
	private function tab_texte( array $s ): void {
		echo '<p>' . esc_html__( 'Der Bannertext soll knapp erklären, wofür Einwilligungen eingeholt werden, dass Daten in Drittländer gehen können und dass ein Widerruf jederzeit möglich ist. Die Details zu den einzelnen Diensten zeigt der Banner automatisch.', 'b83-cookie-banner' ) . '</p>';
		echo '<div class="b83-cb-langs">';
		foreach ( array(
			'de' => __( 'Deutsch', 'b83-cookie-banner' ),
			'en' => __( 'Englisch (leere Felder übernehmen den deutschen Text)', 'b83-cookie-banner' ),
		) as $lang => $label ) {
			$t    = $s['texts'][ $lang ];
			$base = 'b83_cb[texts][' . $lang . ']';
			echo '<div class="b83-cb-card"><h2>' . esc_html( $label ) . '</h2>';
			printf( '<p><label>%1$s<br><input type="text" class="large-text" name="%2$s[title]" value="%3$s" maxlength="120"></label></p>', esc_html__( 'Überschrift', 'b83-cookie-banner' ), esc_attr( $base ), esc_attr( $t['title'] ) );
			printf( '<p><label>%1$s<br><textarea class="large-text" rows="7" name="%2$s[message]">%3$s</textarea></label></p>', esc_html__( 'Text (erlaubt: Links, fett, kursiv)', 'b83-cookie-banner' ), esc_attr( $base ), esc_textarea( $t['message'] ) );
			printf( '<p><label>%1$s<br><input type="url" class="large-text" name="%2$s[privacy_url]" value="%3$s" placeholder="https://"></label></p>', esc_html__( 'Link Datenschutzerklärung', 'b83-cookie-banner' ), esc_attr( $base ), esc_attr( $t['privacy_url'] ) );
			printf( '<p><label>%1$s<br><input type="url" class="large-text" name="%2$s[imprint_url]" value="%3$s" placeholder="https://"></label></p>', esc_html__( 'Link Impressum', 'b83-cookie-banner' ), esc_attr( $base ), esc_attr( $t['imprint_url'] ) );
			echo '</div>';
		}
		echo '</div>';
	}

	/** @param array<string,mixed> $s Einstellungen. */
	private function tab_dienste( array $s ): void {
		$categories = Services::categories();

		echo '<p>' . esc_html__( 'Nur die Dienste aktivieren, die auf dieser Website tatsächlich eingesetzt werden. Anbieter, Zweck, Cookies und Drittland zeigt der Banner automatisch an.', 'b83-cookie-banner' ) . '</p>';

		echo '<h2>' . esc_html__( 'Vorlagen', 'b83-cookie-banner' ) . '</h2>';
		echo '<table class="widefat striped b83-cb-services"><thead><tr><th>' . esc_html__( 'Dienst', 'b83-cookie-banner' ) . '</th><th>' . esc_html__( 'Kategorie', 'b83-cookie-banner' ) . '</th><th>' . esc_html__( 'Einstellungen', 'b83-cookie-banner' ) . '</th></tr></thead><tbody>';
		foreach ( Services::catalog() as $id => $service ) {
			$conf = $s['services'][ $id ] ?? array( 'enabled' => false );
			$base = 'b83_cb[services][' . $id . ']';
			echo '<tr><td>';
			printf(
				'<input type="hidden" name="%1$s[enabled]" value="0"><label><input type="checkbox" name="%1$s[enabled]" value="1"%2$s> <strong>%3$s</strong></label><p class="description">%4$s</p>',
				esc_attr( $base ),
				checked( ! empty( $conf['enabled'] ), true, false ),
				esc_html( (string) $service['name'] ),
				esc_html( (string) ( $service['purpose']['de'] ?? '' ) )
			);
			echo '</td><td>' . esc_html( $categories[ $service['category'] ]['label']['de'] ?? '' ) . '</td><td>';
			foreach ( (array) $service['fields'] as $field => $def ) {
				printf(
					'<p><label>%1$s<br><input type="text" class="regular-text code" name="%2$s[%3$s]" value="%4$s" placeholder="%5$s"></label></p>',
					esc_html( (string) $def['label'] ),
					esc_attr( $base ),
					esc_attr( $field ),
					esc_attr( (string) ( $conf[ $field ] ?? '' ) ),
					esc_attr( (string) $def['hint'] )
				);
			}
			if ( ! empty( $service['hosts'] ) ) {
				echo '<p class="description">' . esc_html__( 'Eingebettete Inhalte werden automatisch blockiert, bis der Besucher zustimmt.', 'b83-cookie-banner' ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Eigene Dienste', 'b83-cookie-banner' ) . '</h2>';
		echo '<p>' . esc_html__( 'Für alles, was keine Vorlage hat – z. B. Wetter-Widgets, Buchungssysteme oder eigene Skripte. Bitte alle Angaben ausfüllen, sie erscheinen im Banner.', 'b83-cookie-banner' ) . '</p>';
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			echo '<p class="b83-cb-warning">' . esc_html__( 'Skript-Code kann nur von Administratoren mit voller HTML-Berechtigung geändert werden.', 'b83-cookie-banner' ) . '</p>';
		}

		echo '<div class="b83-cb-custom-list">';
		foreach ( $s['custom'] as $i => $custom ) {
			$this->custom_row( 'c' . $i, $custom );
		}
		echo '</div>';
		echo '<script type="text/template" id="b83-cb-custom-template">';
		ob_start();
		$this->custom_row( '__KEY__', array() );
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in custom_row() escaped.
		echo '</script>';
		echo '<p><button type="button" class="button b83-cb-add-custom">' . esc_html__( '+ Eigenen Dienst hinzufügen', 'b83-cookie-banner' ) . '</button></p>';
	}

	/**
	 * @param array<string,mixed> $c Dienst.
	 */
	private function custom_row( string $key, array $c ): void {
		$c    = array_merge(
			array(
				'id'              => '',
				'name'            => '',
				'category'        => 'external_media',
				'provider'        => '',
				'purpose_de'      => '',
				'purpose_en'      => '',
				'cookies'         => '',
				'privacy_url'     => '',
				'third_country'   => '',
				'hosts'           => array(),
				'cookie_patterns' => array(),
				'code'            => '',
			),
			$c
		);
		$base = 'b83_cb[custom][' . $key . ']';
		$cats = Services::categories();

		echo '<div class="b83-cb-card b83-cb-custom">';
		echo '<input type="hidden" name="' . esc_attr( $base ) . '[id]" value="' . esc_attr( (string) $c['id'] ) . '">';
		echo '<div class="b83-cb-grid">';
		$this->custom_input( $base, 'name', __( 'Name *', 'b83-cookie-banner' ), (string) $c['name'], 'z. B. Wetter-Widget' );
		echo '<p><label>' . esc_html__( 'Kategorie', 'b83-cookie-banner' ) . '<br><select name="' . esc_attr( $base ) . '[category]">';
		foreach ( Services::OPTIONAL_CATEGORIES as $cat ) {
			echo '<option value="' . esc_attr( $cat ) . '"' . selected( $c['category'], $cat, false ) . '>' . esc_html( $cats[ $cat ]['label']['de'] ) . '</option>';
		}
		echo '</select></label></p>';
		$this->custom_input( $base, 'provider', __( 'Anbieter (Name, Anschrift)', 'b83-cookie-banner' ), (string) $c['provider'], '' );
		$this->custom_input( $base, 'privacy_url', __( 'Datenschutzerklärung des Anbieters', 'b83-cookie-banner' ), (string) $c['privacy_url'], 'https://', 'url' );
		$this->custom_input( $base, 'purpose_de', __( 'Zweck (Deutsch)', 'b83-cookie-banner' ), (string) $c['purpose_de'], '' );
		$this->custom_input( $base, 'purpose_en', __( 'Zweck (Englisch)', 'b83-cookie-banner' ), (string) $c['purpose_en'], '' );
		$this->custom_input( $base, 'cookies', __( 'Cookies und Speicherdauer', 'b83-cookie-banner' ), (string) $c['cookies'], 'z. B. abc_id (1 Jahr)' );
		$this->custom_input( $base, 'third_country', __( 'Drittland (falls zutreffend)', 'b83-cookie-banner' ), (string) $c['third_country'], 'z. B. USA' );
		$this->custom_input( $base, 'hosts', __( 'Domains eingebetteter Inhalte (blockieren)', 'b83-cookie-banner' ), implode( ', ', (array) $c['hosts'] ), 'z. B. widget.example.com' );
		$this->custom_input( $base, 'cookie_patterns', __( 'Cookie-Namen zum Löschen beim Widerruf', 'b83-cookie-banner' ), implode( ', ', (array) $c['cookie_patterns'] ), 'z. B. abc_id, _xyz*' );
		echo '</div>';

		$readonly = current_user_can( 'unfiltered_html' ) ? '' : ' readonly';
		echo '<p><label>' . esc_html__( 'Skript-Code (wird erst nach Zustimmung ausgeführt)', 'b83-cookie-banner' ) . '<br>';
		echo '<textarea class="large-text code" rows="4" name="' . esc_attr( $base ) . '[code]"' . $readonly . '>' . esc_textarea( (string) $c['code'] ) . '</textarea></label></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $readonly ist fest.
		echo '<p><button type="button" class="button-link b83-cb-remove-custom">' . esc_html__( 'Dienst entfernen', 'b83-cookie-banner' ) . '</button></p>';
		echo '</div>';
	}

	private function custom_input( string $base, string $field, string $label, string $value, string $placeholder, string $type = 'text' ): void {
		printf(
			'<p><label>%1$s<br><input type="%2$s" class="large-text" name="%3$s[%4$s]" value="%5$s" placeholder="%6$s"></label></p>',
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $base ),
			esc_attr( $field ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);
	}

	/** @param array<string,mixed> $s Einstellungen. */
	private function tab_protokoll( array $s ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Suche.
		$search = isset( $_GET['consent_id'] ) ? strtolower( sanitize_text_field( wp_unslash( (string) $_GET['consent_id'] ) ) ) : '';
		if ( '' !== $search && 1 !== preg_match( '/^[0-9a-f-]{36}$/', $search ) ) {
			$search = '';
		}

		$stats = ConsentLog::stats( 30 );
		$total = array_sum( $stats );

		echo '<div class="b83-cb-card"><h2>' . esc_html__( 'Letzte 30 Tage', 'b83-cookie-banner' ) . '</h2><div class="b83-cb-stats">';
		foreach ( array(
			'accept_all' => __( 'Alle akzeptiert', 'b83-cookie-banner' ),
			'reject_all' => __( 'Alle abgelehnt', 'b83-cookie-banner' ),
			'custom'     => __( 'Eigene Auswahl', 'b83-cookie-banner' ),
			'service'    => __( 'Einzelner Dienst', 'b83-cookie-banner' ),
		) as $action => $label ) {
			$n   = (int) $stats[ $action ];
			$pct = $total > 0 ? round( $n / $total * 100 ) : 0;
			/* translators: 1: Anzahl, 2: Prozent */
			echo '<div class="b83-cb-stat"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( sprintf( __( '%1$d (%2$d %%)', 'b83-cookie-banner' ), $n, $pct ) ) . '</strong></div>';
		}
		echo '</div>';
		/* translators: 1: Anzahl Einträge, 2: Monate */
		echo '<p class="description">' . esc_html( sprintf( __( 'Insgesamt %1$d Einträge. Aufbewahrung: %2$d Monate.', 'b83-cookie-banner' ), ConsentLog::count(), (int) $s['log_retention_months'] ) ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=b83_cb_export' ), 'b83_cb_export' ) ) . '">' . esc_html__( 'Protokoll als CSV exportieren', 'b83-cookie-banner' ) . '</a></p></div>';

		echo '<form method="get" class="b83-cb-search"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="tab" value="protokoll">';
		echo '<label>' . esc_html__( 'Nach Einwilligungs-ID suchen', 'b83-cookie-banner' ) . ' <input type="search" name="consent_id" value="' . esc_attr( $search ) . '" class="regular-text code" placeholder="xxxxxxxx-xxxx-4xxx-xxxx-xxxxxxxxxxxx"></label> ';
		submit_button( __( 'Suchen', 'b83-cookie-banner' ), 'secondary', '', false );
		echo '</form>';

		$rows = ConsentLog::recent( 50, $search );
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Zeitpunkt', 'b83-cookie-banner' ), __( 'Einwilligungs-ID', 'b83-cookie-banner' ), __( 'Aktion', 'b83-cookie-banner' ), __( 'Dienste', 'b83-cookie-banner' ), __( 'Version', 'b83-cookie-banner' ), __( 'Seite', 'b83-cookie-banner' ) ) as $th ) {
			echo '<th>' . esc_html( $th ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( empty( $rows ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'Keine Einträge.', 'b83-cookie-banner' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( get_date_from_gmt( (string) $row['created_at'], 'd.m.Y H:i:s' ) ) . '</td>';
			echo '<td><code>' . esc_html( (string) $row['consent_id'] ) . '</code></td>';
			echo '<td>' . esc_html( (string) $row['action'] ) . '</td>';
			echo '<td>' . esc_html( '' !== $row['services'] ? (string) $row['services'] : '–' ) . '</td>';
			echo '<td>' . esc_html( (string) $row['revision'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['page'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/** @param array<string,mixed> $s Einstellungen. */
	private function tab_hilfe( array $s ): void {
		$items = array(
			__( 'Shortcodes', 'b83-cookie-banner' )                => array(
				'[b83_cookie_settings]Cookie-Einstellungen[/b83_cookie_settings] – ' . __( 'Link zum Öffnen der Einstellungen, z. B. im Footer oder in der Datenschutzerklärung.', 'b83-cookie-banner' ),
				'[b83_youtube url="https://youtu.be/…"] – ' . __( 'YouTube-Video mit Platzhalter (normale Einbettungen werden ohnehin automatisch blockiert).', 'b83-cookie-banner' ),
			),
			__( 'Google Tag Manager', 'b83-cookie-banner' )        => array(
				__( 'Der Tag Manager wird erst geladen, wenn der Besucher einem Statistik- oder Marketing-Dienst zustimmt. Google Consent Mode v2 ist vorab auf „verweigert“ gesetzt.', 'b83-cookie-banner' ),
				__( 'Im Tag Manager bei jedem Tag die Einwilligungsprüfung aktivieren (z. B. Google Analytics: analytics_storage, Meta Pixel: ad_storage).', 'b83-cookie-banner' ),
				__( 'Zusätzlich steht das Ereignis „b83_consent“ mit der Variable „b83_services“ (Liste der erlaubten Dienste) als Auslöser zur Verfügung.', 'b83-cookie-banner' ),
				__( 'Den Tag Manager nicht zusätzlich über ein anderes Plugin oder das Theme einbinden.', 'b83-cookie-banner' ),
			),
			__( 'Caching und Optimierung', 'b83-cookie-banner' )   => array(
				__( 'Der Banner funktioniert mit Seiten-Cache. Bei WP Rocket werden die nötigen Ausnahmen automatisch gesetzt; bei anderen Optimierungs-Plugins banner.js von „JavaScript verzögern“ ausnehmen.', 'b83-cookie-banner' ),
			),
			__( 'Häufige Lücken prüfen', 'b83-cookie-banner' )     => array(
				__( 'Elementor: unter Elementor → Einstellungen → Erweitert „Google Fonts laden“ deaktivieren bzw. auf lokal umstellen.', 'b83-cookie-banner' ),
				__( 'Keine weiteren Tracking-Codes im Theme oder in anderen Plugins einbinden – sie würden am Banner vorbei geladen.', 'b83-cookie-banner' ),
				__( 'Die Datenschutzerklärung muss die eingesetzten Dienste ebenfalls nennen.', 'b83-cookie-banner' ),
			),
		);
		foreach ( $items as $title => $lines ) {
			echo '<div class="b83-cb-card"><h2>' . esc_html( $title ) . '</h2><ul class="b83-cb-list">';
			foreach ( $lines as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul></div>';
		}
		echo '<p class="description">' . esc_html__( 'Hinweis: Der Banner unterstützt eine rechtskonforme Umsetzung, ersetzt aber keine Rechtsberatung.', 'b83-cookie-banner' ) . '</p>';
	}

	/* ---------------------------------------------------------------------
	 * Formular-Helfer
	 * ------------------------------------------------------------------- */

	private function row_checkbox( string $key, string $title, bool $value, string $label, string $hint = '' ): void {
		printf(
			'<tr><th scope="row">%1$s</th><td><input type="hidden" name="b83_cb[%2$s]" value="0"><label><input type="checkbox" name="b83_cb[%2$s]" value="1"%3$s> %4$s</label>%5$s</td></tr>',
			esc_html( $title ),
			esc_attr( $key ),
			checked( $value, true, false ),
			esc_html( $label ),
			'' !== $hint ? '<p class="description">' . esc_html( $hint ) . '</p>' : ''
		);
	}

	private function row_number( string $key, string $title, int $value, int $min, int $max, string $hint ): void {
		printf(
			'<tr><th scope="row"><label for="b83-cb-%1$s">%2$s</label></th><td><input type="number" id="b83-cb-%1$s" class="small-text" name="b83_cb[%1$s]" value="%3$d" min="%4$d" max="%5$d"> <span class="description">%6$s</span></td></tr>',
			esc_attr( $key ),
			esc_html( $title ),
			$value,
			$min,
			$max,
			esc_html( $hint )
		);
	}

	/* ---------------------------------------------------------------------
	 * Aktionen
	 * ------------------------------------------------------------------- */

	public function handle_save(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-cookie-banner' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'b83_cb_save', 'b83_cb_nonce' );

		$tab  = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( (string) $_POST['tab'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Bereinigung zentral in Settings::sanitize().
		$post = isset( $_POST['b83_cb'] ) && is_array( $_POST['b83_cb'] ) ? wp_unslash( $_POST['b83_cb'] ) : array();

		$settings = self::merge( Settings::get(), $post, $tab, current_user_can( 'unfiltered_html' ) );
		Settings::save( $settings );

		if ( 'dienste' === $tab ) {
			update_option( 'b83_cookie_banner_services_reviewed', 1, false );
		}
		self::redirect( $tab, 'saved' );
	}

	/**
	 * Übernimmt nur die Felder des gespeicherten Tabs in die aktuellen Einstellungen.
	 *
	 * @param array<string,mixed> $current     Aktuelle Einstellungen.
	 * @param array<mixed>        $post        Formulardaten.
	 * @param bool                $allow_code  Darf Skript-Code geändert werden?
	 * @return array<string,mixed>
	 */
	public static function merge( array $current, array $post, string $tab, bool $allow_code ): array {
		if ( 'allgemein' === $tab ) {
			foreach ( array( 'enabled', 'show_reopen', 'en_enabled', 'lifetime_months', 'log_retention_months' ) as $key ) {
				if ( array_key_exists( $key, $post ) ) {
					$current[ $key ] = $post[ $key ];
				}
			}
		} elseif ( 'design' === $tab ) {
			foreach ( array( 'position', 'accent_color', 'custom_css' ) as $key ) {
				if ( array_key_exists( $key, $post ) ) {
					$current[ $key ] = $post[ $key ];
				}
			}
			if ( is_array( $post['design'] ?? null ) ) {
				$current['design'] = $post['design'];
			}
		} elseif ( 'texte' === $tab && is_array( $post['texts'] ?? null ) ) {
			$current['texts'] = $post['texts'];
		} elseif ( 'dienste' === $tab ) {
			if ( is_array( $post['services'] ?? null ) ) {
				$current['services'] = $post['services'];
			}
			$old_code = array();
			foreach ( $current['custom'] as $custom ) {
				$old_code[ $custom['id'] ] = $custom['code'];
			}
			$custom = array();
			foreach ( (array) ( $post['custom'] ?? array() ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				if ( ! $allow_code ) {
					$id          = is_string( $row['id'] ?? null ) ? $row['id'] : '';
					$row['code'] = $old_code[ $id ] ?? '';
				}
				$custom[] = $row;
			}
			$current['custom'] = $custom;
		}
		return $current;
	}

	public function handle_new_revision(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-cookie-banner' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'b83_cb_new_revision' );
		$settings             = Settings::get();
		$settings['revision'] = (int) $settings['revision'] + 1;
		Settings::save( $settings );
		self::redirect( 'allgemein', 'revision' );
	}

	public function handle_export(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'b83-cookie-banner' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'b83_cb_export' );
		ConsentLog::export_csv();
	}
}
