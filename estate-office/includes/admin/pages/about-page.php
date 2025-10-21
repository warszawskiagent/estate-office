<?php
/**
 * About page renderer.
 *
 * @package EstateOffice\Admin\Pages
 */

namespace EstateOffice\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Displays information about the plugin.
 */
class AboutPage {
/**
 * Render About page content.
 */
public static function render() {
if ( ! current_user_can( 'manage_options' ) ) {
wp_die( esc_html__( 'Brak uprawnień do przeglądania tej strony.', 'estateoffice' ) );
}

$roadmap = [
'0.0.1' => __( 'Struktura wtyczki, rejestracja ról i typów danych.', 'estateoffice' ),
'0.1.0' => __( 'Rozszerzone formularze umów, klientów i nieruchomości.', 'estateoffice' ),
'0.5.0' => __( 'Panel agenta, eksport ofert na WWW i mapy Google.', 'estateoffice' ),
'1.0.0' => __( 'Kompletny CRM z automatyzacją etapów umów i raportami.', 'estateoffice' ),
];
?>
<div class="wrap">
<h1><?php esc_html_e( 'EstateOffice CRM', 'estateoffice' ); ?></h1>
<p><?php esc_html_e( 'EstateOffice to zaawansowany CRM dla biur nieruchomości, łączący zarządzanie nieruchomościami, klientami, umowami i agentami.', 'estateoffice' ); ?></p>
<h2><?php esc_html_e( 'Roadmapa', 'estateoffice' ); ?></h2>
<ul>
<?php foreach ( $roadmap as $version => $description ) : ?>
<li><strong><?php echo esc_html( $version ); ?></strong>: <?php echo esc_html( $description ); ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php
}
}
