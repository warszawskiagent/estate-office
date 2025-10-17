<?php
/**
 * Główny kontroler wtyczki EstateOffice.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Estate_Office_Plugin
 */
final class Estate_Office_Plugin {

    /**
     * Pojedyncza instancja klasy.
     *
     * @var Estate_Office_Plugin|null
     */
    private static ?Estate_Office_Plugin $instance = null;

    /**
     * Klasa odpowiedzialna za menu administratora.
     *
     * @var Estate_Office_Admin_Menu|null
     */
    private ?Estate_Office_Admin_Menu $admin_menu = null;

    /**
     * Klasa odpowiedzialna za instalację/aktualizację bazy danych.
     *
     * @var Estate_Office_Installer|null
     */
    private ?Estate_Office_Installer $installer = null;

    /**
     * Menedżer ról i uprawnień.
     *
     * @var Estate_Office_Roles|null
     */
    private ?Estate_Office_Roles $roles = null;

    /**
     * Kontroler portalu frontendowego.
     *
     * @var Estate_Office_Frontend_Portal|null
     */
    private ?Estate_Office_Frontend_Portal $frontend = null;

    /**
     * Kontroler publicznej prezentacji ofert.
     *
     * @var Estate_Office_Frontend_Offers|null
     */
    private ?Estate_Office_Frontend_Offers $offers = null;

    /**
     * Frontendowe kalkulatory finansowe.
     *
     * @var Estate_Office_Frontend_Calculators|null
     */
    private ?Estate_Office_Frontend_Calculators $calculators = null;

    /**
     * Menedżer licencji.
     *
     * @var Estate_Office_License_Manager|null
     */
    private ?Estate_Office_License_Manager $license_manager = null;

    /**
     * Menedżer eksportu na portale.
     *
     * @var Estate_Office_Portal_Export_Manager|null
     */
    private ?Estate_Office_Portal_Export_Manager $portal_export_manager = null;

    /**
     * Singleton – prywatny konstruktor.
     */
    private function __construct() {}

    /**
     * Pobierz instancję singletonu.
     *
     * @return Estate_Office_Plugin
     */
    public static function instance() : Estate_Office_Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Weryfikacja wymagań środowiska.
     *
     * @return bool
     */
    public static function requirements_met() : bool {
        global $wp_version;

        if ( version_compare( PHP_VERSION, ESTATE_OFFICE_MINIMUM_PHP, '<' ) ) {
            self::admin_notice( sprintf( /* translators: %s: wymagania wersji PHP */
                __( 'EstateOffice wymaga PHP w wersji %s lub wyższej.', 'estate-office' ),
                ESTATE_OFFICE_MINIMUM_PHP
            ) );

            return false;
        }

        if ( isset( $wp_version ) && version_compare( $wp_version, ESTATE_OFFICE_MINIMUM_WP, '<' ) ) {
            self::admin_notice( sprintf( /* translators: %s: wymagania wersji WordPress */
                __( 'EstateOffice wymaga WordPress w wersji %s lub wyższej.', 'estate-office' ),
                ESTATE_OFFICE_MINIMUM_WP
            ) );

            return false;
        }

        return true;
    }

    /**
     * Rejestracja hooków inicjalizujących plugin.
     *
     * @return void
     */
    public function boot() : void {
        $this->load_textdomain();
        $this->get_roles()->ensure_capabilities();
        $this->get_installer()->maybe_upgrade();
        $license_manager = $this->get_license_manager();
        $license_manager->hooks();
        $license_manager->schedule_events();
        $portal_export_manager = $this->get_portal_export_manager();
        $portal_export_manager->hooks();
        $portal_export_manager->schedule_events();
        $this->init_admin();
        $this->init_frontend();

        add_action( 'admin_init', [ $this, 'register_settings_placeholders' ] );
    }

    /**
     * Hook wykonywany podczas aktywacji wtyczki.
     *
     * @return void
     */
    public static function activate() : void {
        if ( ! self::requirements_met() ) {
            deactivate_plugins( ESTATE_OFFICE_BASENAME );
            return;
        }

        $instance = self::instance();
        $instance->get_roles()->register_roles();
        $instance->get_installer()->install();
        $instance->get_license_manager()->schedule_events();
        $instance->get_portal_export_manager()->schedule_events();
        $instance->get_portal_export_manager()->generate_exports();
        Estate_Office_Frontend_Portal::ensure_portal_page();
        Estate_Office_Frontend_Offers::ensure_offer_pages();
        Estate_Office_Frontend_Calculators::ensure_calculator_pages();
        flush_rewrite_rules();

        self::log_debug( 'EstateOffice aktywowana. Wersja: ' . ESTATE_OFFICE_VERSION );
    }

    /**
     * Hook wykonywany podczas dezaktywacji wtyczki.
     *
     * @return void
     */
    public static function deactivate() : void {
        $instance = self::instance();
        $instance->get_license_manager()->clear_scheduled_events();
        $instance->get_portal_export_manager()->clear_scheduled_events();
        self::log_debug( 'EstateOffice dezaktywowana.' );
    }

    /**
     * Ładowanie plików tłumaczeń.
     *
     * @return void
     */
    private function load_textdomain() : void {
        load_plugin_textdomain( 'estate-office', false, dirname( ESTATE_OFFICE_BASENAME ) . '/languages' );
    }

    /**
     * Inicjalizacja menu administratora.
     *
     * @return void
     */
    private function init_admin() : void {
        if ( is_admin() ) {
            $this->admin_menu = new Estate_Office_Admin_Menu(
                $this->get_license_manager(),
                $this->get_portal_export_manager()
            );
            $this->admin_menu->hooks();
        }
    }

    /**
     * Inicjalizuje komponent frontendowy.
     *
     * @return void
     */
    private function init_frontend() : void {
        if ( null === $this->frontend ) {
            $this->frontend = new Estate_Office_Frontend_Portal();
        }

        $this->frontend->hooks();

        if ( null === $this->offers ) {
            $this->offers = new Estate_Office_Frontend_Offers();
        }

        $this->offers->hooks();

        if ( null === $this->calculators ) {
            $this->calculators = new Estate_Office_Frontend_Calculators();
        }

        $this->calculators->hooks();
    }

    /**
     * Pobiera menedżera licencji.
     *
     * @return Estate_Office_License_Manager
     */
    private function get_license_manager() : Estate_Office_License_Manager {
        if ( null === $this->license_manager ) {
            $this->license_manager = new Estate_Office_License_Manager();
        }

        return $this->license_manager;
    }

    /**
     * Pobiera menedżera eksportu na portale.
     *
     * @return Estate_Office_Portal_Export_Manager
     */
    private function get_portal_export_manager() : Estate_Office_Portal_Export_Manager {
        if ( null === $this->portal_export_manager ) {
            $this->portal_export_manager = new Estate_Office_Portal_Export_Manager();
        }

        return $this->portal_export_manager;
    }

    /**
     * Pobiera instancję instalatora.
     *
     * @return Estate_Office_Installer
     */
    private function get_installer() : Estate_Office_Installer {
        if ( null === $this->installer ) {
            $this->installer = new Estate_Office_Installer();
        }

        return $this->installer;
    }

    /**
     * Pobiera menedżera ról.
     *
     * @return Estate_Office_Roles
     */
    private function get_roles() : Estate_Office_Roles {
        if ( null === $this->roles ) {
            $this->roles = new Estate_Office_Roles();
        }

        return $this->roles;
    }

    /**
     * Rejestracja placeholderów ustawień (do rozbudowy w kolejnych wersjach).
     *
     * @return void
     */
    public function register_settings_placeholders() : void {
        // Placeholder dla przyszłych ustawień API Google oraz pól dynamicznych.
        do_action( 'estate_office/register_settings_placeholders' );
    }

    /**
     * Wyświetlanie komunikatu w panelu administratora.
     *
     * @param string $message Treść komunikatu.
     *
     * @return void
     */
    private static function admin_notice( string $message ) : void {
        add_action(
            'admin_notices',
            static function () use ( $message ) : void {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    esc_html( $message )
                );
            }
        );
    }

    /**
     * Pomocnicza metoda do logowania informacji debug.
     *
     * @param string $message Treść logu.
     * @param array  $context Dodatkowy kontekst.
     *
     * @return void
     */
    public static function log_debug( string $message, array $context = [] ) : void {
        if ( ! ESTATE_OFFICE_DEBUG ) {
            return;
        }

        if ( ! empty( $context ) ) {
            try {
                $message .= ' | ' . wp_json_encode( $context, JSON_THROW_ON_ERROR );
            } catch ( JsonException $exception ) {
                $message .= ' | ' . __( 'Błąd kodowania JSON w logu debug.', 'estate-office' );
            }
        }

        error_log( '[EstateOffice] ' . $message );
    }
}
