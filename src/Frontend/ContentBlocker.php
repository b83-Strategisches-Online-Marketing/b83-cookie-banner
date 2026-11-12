<?php
/**
 * Blockiert eingebettete Inhalte (YouTube, Vimeo, Google Maps und eigene
 * Dienste mit Domain-Liste), bis der Besucher zustimmt.
 *
 * Das Original-iFrame steckt in einem <template>-Element: Dessen Inhalt wird
 * vom Browser nicht geladen. Nach Zustimmung setzt das Skript es ein.
 *
 * Erfasst: Beitragsinhalte, oEmbeds, Text-Widgets, alle Elementor-Widgets
 * (inkl. Video- und Google-Maps-Widget) sowie den alten Shortcode [b83_youtube].
 *
 * @package B83\CookieBanner
 */

declare(strict_types=1);

namespace B83\CookieBanner\Frontend;

use B83\CookieBanner\Services;
use B83\CookieBanner\Settings;

defined( 'ABSPATH' ) || exit;

final class ContentBlocker {

	private const IFRAME = '#<iframe\b[^>]*?\bsrc\s*=\s*(["\'])(.*?)\1[^>]*>.*?</iframe>#is';

	/** @var array<string, array<string,mixed>>|null */
	private ?array $active = null;

	/**
	 * Feste Höhe für den nächsten Platzhalter (Elementor-Karte), je Gerät.
	 *
	 * @var array<string,string>|null
	 */
	private ?array $fixed_height = null;

	public function register(): void {
		add_filter( 'the_content', array( $this, 'filter_html' ), 99 );
		add_filter( 'widget_text_content', array( $this, 'filter_html' ), 99 );
		add_filter( 'widget_block_content', array( $this, 'filter_html' ), 99 );
		add_filter( 'embed_oembed_html', array( $this, 'filter_html' ), 99 );
		add_filter( 'elementor/widget/render_content', array( $this, 'filter_elementor' ), 99, 2 );
		add_action( 'elementor/frontend/widget/before_render', array( $this, 'neutralize_elementor_video' ) );
		add_shortcode( 'b83_youtube', array( $this, 'youtube_shortcode' ) );
	}

	/** @return array<string, array<string,mixed>> */
	private function active(): array {
		if ( null === $this->active ) {
			$this->active = Services::active( Settings::get(), Settings::lang() );
		}
		return $this->active;
	}

	private function skip(): bool {
		if ( is_admin() || is_feed() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Erkennung der Elementor-Vorschau.
		return isset( $_GET['elementor-preview'] ) || ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode() );
	}

	/**
	 * Ersetzt alle iFrames bekannter Dienste durch Platzhalter.
	 *
	 * @param mixed $html HTML.
	 * @return mixed
	 */
	public function filter_html( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, '<iframe' ) || $this->skip() ) {
			return $html;
		}
		return (string) preg_replace_callback( self::IFRAME, array( $this, 'replace_iframe' ), $html );
	}

	/**
	 * @param array<int,string> $m Treffer.
	 */
	private function replace_iframe( array $m ): string {
		// Schon blockiert (steckt in einem Platzhalter): nicht noch einmal einpacken.
		// Elementor-Inhalte laufen sonst zweimal durch – Widget-Filter und the_content.
		if ( false !== stripos( $m[0], 'data-b83-cb-embed' ) ) {
			return $m[0];
		}
		$src     = html_entity_decode( $m[2], ENT_QUOTES );
		$service = Services::match_url( $src, $this->active() );
		if ( null === $service ) {
			return $m[0];
		}

		$iframe = $m[0];
		$new    = self::privacy_friendly_src( $service, $src );
		if ( $new !== $src ) {
			$iframe = str_replace( $m[2], esc_attr( $new ), $iframe );
		}

		if ( null !== $this->fixed_height ) {
			return $this->placeholder( $service, $iframe, '', $this->fixed_height );
		}

		// Pixelangaben; „100%“ zählt nicht als Breite.
		$width  = preg_match( '/\bwidth\s*=\s*["\']?(\d{2,4})(?![\d%])/i', $iframe, $w ) ? (int) $w[1] : 0;
		$height = preg_match( '/\bheight\s*=\s*["\']?(\d{2,4})(?![\d%])/i', $iframe, $h ) ? (int) $h[1] : 0;
		if ( $width > 0 && $height > 0 ) {
			return $this->placeholder( $service, $iframe, $width . ' / ' . $height );
		}
		if ( $height > 0 ) {
			// Volle Breite mit fester Höhe (typisch bei Karten): Höhe übernehmen statt Seitenverhältnis.
			return $this->placeholder( $service, $iframe, '', array( 'desktop' => $height . 'px' ) );
		}
		return $this->placeholder( $service, $iframe, 'google_maps' === $service ? '4 / 3' : '16 / 9' );
	}

	/** YouTube ohne Tracking-Cookies vor dem Abspielen, Vimeo mit „Do Not Track“. */
	public static function privacy_friendly_src( string $service, string $src ): string {
		if ( 'youtube' === $service ) {
			return (string) preg_replace( '#^(https?:)?//(www\.)?youtube\.com/embed/#i', 'https://www.youtube-nocookie.com/embed/', $src );
		}
		if ( 'vimeo' === $service && false === stripos( $src, 'dnt=' ) ) {
			return $src . ( str_contains( $src, '?' ) ? '&' : '?' ) . 'dnt=1';
		}
		return $src;
	}

	/**
	 * Elementor: alle Widgets durchsuchen; das Video-Widget erzeugt den Player
	 * erst per JavaScript und wird daher gesondert behandelt.
	 *
	 * @param mixed  $content HTML.
	 * @param object $widget  Widget.
	 * @return mixed
	 */
	public function filter_elementor( $content, $widget ) {
		if ( ! is_string( $content ) || $this->skip() ) {
			return $content;
		}

		// Elementor-Karte: Höhe aus dem Widget statt Seitenverhältnis, sonst entsteht
		// bei breiten Spalten ein hoher Leerraum unter der Karte.
		$map_height = $this->elementor_map_height( $widget );
		if ( null !== $map_height ) {
			$this->fixed_height = $map_height;
			$out                = $this->filter_html( $content );
			$this->fixed_height = null;
			return $out;
		}

		$video = $this->elementor_video( $widget );
		if ( null !== $video ) {
			$iframe = '<iframe src="' . esc_url( $video['embed'] ) . '" width="640" height="360" style="width:100%;height:100%;border:0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen loading="lazy" title="' . esc_attr( ucfirst( $video['service'] ) ) . '"></iframe>';
			return $this->placeholder( $video['service'], $iframe, '16 / 9' );
		}

		return $this->filter_html( $content );
	}

	/**
	 * Elementor-Video-Widget: Das Elementor-Skript liest Typ und URL aus
	 * data-settings und lädt die YouTube-API sofort, auch wenn der Player
	 * durch den Platzhalter ersetzt ist. Elementor hängt sein Video-Skript
	 * über den Widget-Typ an; mit einem eigenen Typ am Wrapper läuft es nicht
	 * (Animationen und andere globale Effekte bleiben erhalten).
	 * Nach der Zustimmung setzt das Skript ein normales iFrame ein.
	 *
	 * @param object $widget Widget.
	 */
	public function neutralize_elementor_video( $widget ): void {
		if ( $this->skip() || null === $this->elementor_video( $widget ) || ! method_exists( $widget, 'set_render_attribute' ) ) {
			return;
		}
		$widget->set_render_attribute( '_wrapper', 'data-widget_type', 'b83-cb-video.default' );
	}

	/**
	 * Blockiertes Elementor-Video-Widget erkennen.
	 *
	 * @param mixed $widget Widget.
	 * @return array{service: string, embed: string}|null
	 */
	private function elementor_map_height( $widget ): ?array {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'google_maps' !== $widget->get_name() || ! isset( $this->active()['google_maps'] ) || ! method_exists( $widget, 'get_settings_for_display' ) ) {
			return null;
		}
		$settings = (array) $widget->get_settings_for_display();
		$heights  = array();
		foreach ( array( 'desktop' => 'height', 'tablet' => 'height_tablet', 'mobile' => 'height_mobile' ) as $device => $key ) {
			$value = $settings[ $key ] ?? null;
			$size  = is_array( $value ) ? ( $value['size'] ?? '' ) : '';
			$unit  = is_array( $value ) ? (string) ( $value['unit'] ?? 'px' ) : 'px';
			if ( is_numeric( $size ) && (float) $size > 0 && in_array( $unit, array( 'px', 'vh', 'em', 'rem' ), true ) ) {
				$heights[ $device ] = ( (float) $size + 0 ) . $unit;
			}
		}
		$heights['desktop'] = $heights['desktop'] ?? '300px'; // Standardhöhe des Elementor-Widgets.
		return $heights;
	}

	/**
	 * Elementor-Video-Widget: YouTube- oder Vimeo-Adresse, wenn der Dienst blockiert wird.
	 *
	 * @param mixed $widget Widget.
	 * @return array{service: string, embed: string}|null
	 */
	private function elementor_video( $widget ): ?array {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'video' !== $widget->get_name() || ! method_exists( $widget, 'get_settings_for_display' ) ) {
			return null;
		}
		$settings = (array) $widget->get_settings_for_display();
		$type     = (string) ( $settings['video_type'] ?? 'youtube' );
		if ( ! in_array( $type, array( 'youtube', 'vimeo' ), true ) || ! isset( $this->active()[ $type ] ) ) {
			return null;
		}
		$url   = (string) ( 'vimeo' === $type ? ( $settings['vimeo_url'] ?? '' ) : ( $settings['youtube_url'] ?? '' ) );
		$embed = self::embed_url( $type, $url );
		if ( '' === $embed ) {
			return null;
		}
		return array(
			'service' => $type,
			'embed'   => $embed,
		);
	}

	/**
	 * Alter Shortcode [b83_youtube url="…" title="…"].
	 *
	 * @param array<string,string>|string $atts Attribute.
	 */
	public function youtube_shortcode( $atts ): string {
		$atts  = shortcode_atts(
			array(
				'url'   => '',
				'title' => 'YouTube Video',
			),
			is_array( $atts ) ? $atts : array(),
			'b83_youtube'
		);
		$embed = self::embed_url( 'youtube', (string) $atts['url'] );
		if ( '' === $embed ) {
			return '';
		}
		$iframe = '<iframe src="' . esc_url( $embed ) . '" width="560" height="315" style="width:100%;height:100%;border:0" title="' . esc_attr( (string) $atts['title'] ) . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>';
		if ( ! isset( $this->active()['youtube'] ) ) {
			return $iframe;
		}
		return $this->placeholder( 'youtube', $iframe, '16 / 9' );
	}

	/** Baut aus einer Video-URL die Einbettungs-URL ('' wenn nicht erkannt). */
	public static function embed_url( string $service, string $url ): string {
		if ( 'youtube' === $service && preg_match( '#(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|shorts/|watch\?(?:.*&)?v=))([A-Za-z0-9_-]{6,20})#', $url, $m ) ) {
			return 'https://www.youtube-nocookie.com/embed/' . $m[1];
		}
		if ( 'vimeo' === $service && preg_match( '#vimeo\.com/(?:video/)?(\d{5,12})#', $url, $m ) ) {
			return 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1';
		}
		return '';
	}

	/**
	 * iFrame im Template markieren: data-b83-cb-embed verhindert doppeltes
	 * Blockieren, data-no-lazy hält Lazy-Load-Plugins (WP Rocket, LiteSpeed,
	 * Autoptimize …) fern, die sonst src durch about:blank ersetzen.
	 */
	private static function no_lazy( string $iframe ): string {
		if ( false !== stripos( $iframe, 'data-b83-cb-embed' ) ) {
			return $iframe;
		}
		return (string) preg_replace( '/^<iframe\b/i', '<iframe data-b83-cb-embed="1" data-no-lazy="1" data-skip-lazy="1"', $iframe, 1 );
	}

	/**
	 * @param string                    $ratio  Seitenverhältnis, z. B. „16 / 9“ (leer bei fester Höhe).
	 * @param array<string,string>|null $height Feste Höhe je Gerät (desktop, tablet, mobile).
	 */
	private function placeholder( string $service_id, string $iframe, string $ratio, ?array $height = null ): string {
		$service = $this->active()[ $service_id ];
		$lang    = Settings::lang();
		$name    = (string) $service['name'];
		$owner   = '' !== $service['provider'] ? (string) strtok( (string) $service['provider'], ',' ) : $name;

		if ( null !== $height ) {
			$style = '--b83-cb-h:' . $height['desktop'];
			foreach ( array( 'tablet' => 't', 'mobile' => 'm' ) as $device => $suffix ) {
				if ( isset( $height[ $device ] ) ) {
					$style .= ';--b83-cb-h-' . $suffix . ':' . $height[ $device ];
				}
			}
			$html = '<div class="b83-cb-blocked b83-cb-blocked--fixed" data-b83-blocked="' . esc_attr( $service_id ) . '" style="' . esc_attr( $style ) . '">';
		} else {
			$html = '<div class="b83-cb-blocked" data-b83-blocked="' . esc_attr( $service_id ) . '" style="aspect-ratio:' . esc_attr( $ratio ) . '">';
		}
		$html .= '<div class="b83-cb-blocked__inner">';
		$html .= '<p class="b83-cb-blocked__title">' . esc_html( sprintf( Texts::get( 'blocked_title', $lang ), $name ) ) . '</p>';
		$html .= '<p class="b83-cb-blocked__text">' . esc_html( sprintf( Texts::get( 'blocked_text', $lang ), $name, $owner ) );
		if ( '' !== $service['privacy_url'] ) {
			$html .= ' <a href="' . esc_url( (string) $service['privacy_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( Texts::get( 'privacy', $lang ) ) . '</a>';
		}
		$html .= '</p>';
		$html .= '<button type="button" class="b83-cb-blocked__btn" data-b83-cb-allow="' . esc_attr( $service_id ) . '">' . esc_html( Texts::get( 'blocked_button', $lang ) ) . '</button>';
		$html .= '<p class="b83-cb-blocked__note">' . esc_html( sprintf( Texts::get( 'blocked_note', $lang ), $name ) ) . '</p>';
		$html .= '</div>';
		$html .= '<template class="b83-cb-blocked__embed">' . self::no_lazy( $iframe ) . '</template>';
		$html .= '</div>';
		return $html;
	}
}
