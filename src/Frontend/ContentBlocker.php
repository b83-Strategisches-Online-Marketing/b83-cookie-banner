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

	public function register(): void {
		add_filter( 'the_content', array( $this, 'filter_html' ), 99 );
		add_filter( 'widget_text_content', array( $this, 'filter_html' ), 99 );
		add_filter( 'widget_block_content', array( $this, 'filter_html' ), 99 );
		add_filter( 'embed_oembed_html', array( $this, 'filter_html' ), 99 );
		add_filter( 'elementor/widget/render_content', array( $this, 'filter_elementor' ), 99, 2 );
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

		$ratio = '16 / 9';
		if ( preg_match( '/\bwidth\s*=\s*["\']?(\d{2,4})/i', $iframe, $w ) && preg_match( '/\bheight\s*=\s*["\']?(\d{2,4})/i', $iframe, $h ) && (int) $h[1] > 0 ) {
			$ratio = (int) $w[1] . ' / ' . (int) $h[1];
		} elseif ( 'google_maps' === $service ) {
			$ratio = '4 / 3';
		}

		return $this->placeholder( $service, $iframe, $ratio );
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

		if ( is_object( $widget ) && method_exists( $widget, 'get_name' ) && 'video' === $widget->get_name() && method_exists( $widget, 'get_settings_for_display' ) ) {
			$settings = (array) $widget->get_settings_for_display();
			$type     = (string) ( $settings['video_type'] ?? 'youtube' );
			$url      = (string) ( 'vimeo' === $type ? ( $settings['vimeo_url'] ?? '' ) : ( $settings['youtube_url'] ?? '' ) );
			$embed    = self::embed_url( 'vimeo' === $type ? 'vimeo' : 'youtube', $url );
			$service  = 'vimeo' === $type ? 'vimeo' : 'youtube';
			if ( '' !== $embed && in_array( $type, array( 'youtube', 'vimeo' ), true ) && isset( $this->active()[ $service ] ) ) {
				$iframe = '<iframe src="' . esc_url( $embed ) . '" width="640" height="360" style="width:100%;height:100%;border:0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen loading="lazy" title="' . esc_attr( ucfirst( $service ) ) . '"></iframe>';
				return $this->placeholder( $service, $iframe, '16 / 9' );
			}
		}

		return $this->filter_html( $content );
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

	private function placeholder( string $service_id, string $iframe, string $ratio ): string {
		$service = $this->active()[ $service_id ];
		$lang    = Settings::lang();
		$name    = (string) $service['name'];
		$owner   = '' !== $service['provider'] ? (string) strtok( (string) $service['provider'], ',' ) : $name;

		$html  = '<div class="b83-cb-blocked" data-b83-blocked="' . esc_attr( $service_id ) . '" style="aspect-ratio:' . esc_attr( $ratio ) . '">';
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
		$html .= '<template class="b83-cb-blocked__embed">' . $iframe . '</template>';
		$html .= '</div>';
		return $html;
	}
}
