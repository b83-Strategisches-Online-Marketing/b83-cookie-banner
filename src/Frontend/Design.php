<?php
/**
 * Gestaltung: wandelt die Design-Einstellungen in CSS-Variablen um.
 *
 * Alle Werte sind bereits in Settings::sanitize() geprüft (Hex-Farben,
 * Zahlen mit Grenzen, feste Auswahllisten), daher kann das Ergebnis direkt
 * als Inline-CSS ausgegeben werden.
 *
 * @package B83\CookieBanner
 */

declare(strict_types=1);

namespace B83\CookieBanner\Frontend;

defined( 'ABSPATH' ) || exit;

final class Design {

	public const SHADOWS = array(
		'none'   => 'none',
		'light'  => '0 4px 14px rgba(0,0,0,.10)',
		'medium' => '0 18px 50px rgba(0,0,0,.20)',
		'strong' => '0 24px 70px rgba(0,0,0,.35)',
	);

	public const FONTS = array(
		'inherit' => 'inherit',
		'system'  => '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif',
	);

	/** Ab diesem Wert werden Buttons vollständig rund (Pillenform). */
	public const PILL = 30;

	/**
	 * CSS-Variablen (Name ohne „--b83-cb-“ => Wert).
	 *
	 * @param array<string,mixed> $settings Bereinigte Einstellungen.
	 * @return array<string,string>
	 */
	public static function vars( array $settings ): array {
		$d      = $settings['design'];
		$accent = (string) $settings['accent_color'];
		$bg     = (string) $d['box_bg'];
		$text   = (string) $d['box_text'];

		$btn_bg       = '' !== $d['btn_bg'] ? $d['btn_bg'] : $accent;
		$btn_hover_bg = '' !== $d['btn_hover_bg'] ? $d['btn_hover_bg'] : self::darken( $btn_bg, 0.12 );

		$px = static fn( $n ): string => (int) $n . 'px';

		return array(
			'accent'       => $accent,
			'bg'           => $bg,
			'text'         => $text,
			'muted'        => self::mix( $text, $bg, 0.72 ),
			'line'         => self::mix( $text, $bg, 0.14 ),
			'soft'         => self::mix( $text, $bg, 0.04 ),
			'border'       => (string) $d['box_border_color'],
			'border-w'     => $px( $d['box_border_width'] ),
			'radius'       => $px( $d['box_radius'] ),
			'shadow'       => self::SHADOWS[ $d['box_shadow'] ] ?? self::SHADOWS['medium'],
			'width'        => $px( $d['box_width'] ),
			'pad'          => $px( $d['box_padding'] ),
			'font'         => self::FONTS[ $d['font'] ] ?? self::FONTS['system'],
			'btn-radius'   => (int) $d['btn_radius'] >= self::PILL ? '999px' : $px( $d['btn_radius'] ),
			'btn-bw'       => $px( $d['btn_border_width'] ),
			'btn-bg'       => $btn_bg,
			'btn-text'     => (string) $d['btn_text'],
			'btn-border'   => '' !== $d['btn_border'] ? $d['btn_border'] : $btn_bg,
			'btn-hbg'      => $btn_hover_bg,
			'btn-htext'    => (string) $d['btn_hover_text'],
			'btn-hborder'  => '' !== $d['btn_hover_border'] ? $d['btn_hover_border'] : $btn_hover_bg,
			'btn2-bg'      => (string) $d['btn2_bg'],
			'btn2-text'    => (string) $d['btn2_text'],
			'btn2-border'  => (string) $d['btn2_border'],
			'btn2-hbg'     => (string) $d['btn2_hover_bg'],
			'btn2-htext'   => (string) $d['btn2_hover_text'],
			'btn2-hborder' => (string) $d['btn2_hover_border'],
		);
	}

	/**
	 * Inline-CSS für die Ausgabe.
	 *
	 * @param array<string,mixed> $settings   Bereinigte Einstellungen.
	 * @param bool                $custom_css Eigenes CSS anhängen (im Backend nicht).
	 */
	public static function css( array $settings, bool $custom_css = true ): string {
		$css = ':root{';
		foreach ( self::vars( $settings ) as $name => $value ) {
			$css .= '--b83-cb-' . $name . ':' . $value . ';';
		}
		$css .= '}';
		if ( $custom_css && '' !== $settings['custom_css'] ) {
			$css .= "\n" . $settings['custom_css'];
		}
		return $css;
	}

	/**
	 * Hinweise zu schlechter Lesbarkeit (WCAG-Kontrast unter 4,5:1).
	 *
	 * @param array<string,mixed> $settings Bereinigte Einstellungen.
	 * @return string[] Bezeichnungen der betroffenen Bereiche.
	 */
	public static function contrast_issues( array $settings ): array {
		$v      = self::vars( $settings );
		$pairs  = array(
			__( 'Text im Banner', 'b83-cookie-banner' )                        => array( $v['text'], $v['bg'] ),
			__( 'Buttons Zustimmen/Ablehnen', 'b83-cookie-banner' )            => array( $v['btn-text'], $v['btn-bg'] ),
			__( 'Buttons Zustimmen/Ablehnen (Hover)', 'b83-cookie-banner' )    => array( $v['btn-htext'], $v['btn-hbg'] ),
			__( 'Button Einstellungen', 'b83-cookie-banner' )                  => array( $v['btn2-text'], $v['btn2-bg'] ),
			__( 'Links', 'b83-cookie-banner' )                                 => array( $v['accent'], $v['bg'] ),
		);
		$issues = array();
		foreach ( $pairs as $label => $pair ) {
			if ( self::contrast( $pair[0], $pair[1] ) < 4.5 ) {
				$issues[] = $label;
			}
		}
		return $issues;
	}

	/* ---------------------------------------------------------------------
	 * Farbrechnung
	 * ------------------------------------------------------------------- */

	/** @return array{0:int,1:int,2:int} */
	private static function rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return array( 0, 0, 0 );
		}
		return array( (int) hexdec( substr( $hex, 0, 2 ) ), (int) hexdec( substr( $hex, 2, 2 ) ), (int) hexdec( substr( $hex, 4, 2 ) ) );
	}

	/** @param array{0:int|float,1:int|float,2:int|float} $rgb Farbe. */
	private static function hex( array $rgb ): string {
		return sprintf( '#%02x%02x%02x', ...array_map( static fn( $c ): int => max( 0, min( 255, (int) round( $c ) ) ), $rgb ) );
	}

	/** Mischt Farbe $a mit Anteil $weight in Farbe $b. */
	public static function mix( string $a, string $b, float $weight ): string {
		$x = self::rgb( $a );
		$y = self::rgb( $b );
		return self::hex( array( $x[0] * $weight + $y[0] * ( 1 - $weight ), $x[1] * $weight + $y[1] * ( 1 - $weight ), $x[2] * $weight + $y[2] * ( 1 - $weight ) ) );
	}

	public static function darken( string $hex, float $amount ): string {
		return self::mix( '#000000', $hex, $amount );
	}

	/** Kontrastverhältnis nach WCAG 2.x. */
	public static function contrast( string $a, string $b ): float {
		$lum = static function ( string $hex ): float {
			$c = array_map(
				static function ( int $v ): float {
					$v /= 255;
					return $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4;
				},
				self::rgb( $hex )
			);
			return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
		};
		$l1 = $lum( $a );
		$l2 = $lum( $b );
		return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
	}
}
