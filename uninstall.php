<?php
/**
 * Deinstallation.
 *
 * Einstellungen und Einwilligungsprotokoll bleiben bewusst erhalten: Das
 * Protokoll dient als Nachweis (Art. 7 Abs. 1 DSGVO) und darf nicht durch
 * versehentliches Löschen des Plugins verloren gehen. Entfernt wird nur der
 * geplante Aufräum-Job; ältere Protokolleinträge können über den CSV-Export
 * gesichert und danach manuell gelöscht werden.
 *
 * @package B83\CookieBanner
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'b83_cookie_banner_purge_log' );
delete_transient( 'b83_cookie_banner_release' );
