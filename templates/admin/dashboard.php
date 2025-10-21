<?php
/**
 * CRM dashboard view.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$properties_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eo_properties" );
$agreements_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eo_agreements" );
$clients_count     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eo_clients" );
$searches_count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eo_searches" );

$top_agents = get_users(
    [
        'role'    => 'estate_agent',
        'orderby' => 'display_name',
        'order'   => 'ASC',
        'number'  => 5,
    ]
);
?>
<div class="wrap estate-office-wrap">
    <h1><?php esc_html_e( 'Estate Office CRM — Pulpit', 'estate-office' ); ?></h1>

    <div class="estate-office-section">
        <div class="estate-office-grid">
            <div class="estate-office-card">
                <div class="estate-office-badge"><?php esc_html_e( 'Nieruchomości', 'estate-office' ); ?></div>
                <h2><?php echo esc_html( number_format_i18n( $properties_count ) ); ?></h2>
                <p><?php esc_html_e( 'Łączna liczba ofert w CRM.', 'estate-office' ); ?></p>
            </div>
            <div class="estate-office-card">
                <div class="estate-office-badge"><?php esc_html_e( 'Umowy', 'estate-office' ); ?></div>
                <h2><?php echo esc_html( number_format_i18n( $agreements_count ) ); ?></h2>
                <p><?php esc_html_e( 'Umowy w toku i zakończone.', 'estate-office' ); ?></p>
            </div>
            <div class="estate-office-card">
                <div class="estate-office-badge"><?php esc_html_e( 'Klienci', 'estate-office' ); ?></div>
                <h2><?php echo esc_html( number_format_i18n( $clients_count ) ); ?></h2>
                <p><?php esc_html_e( 'Aktywne profile klientów.', 'estate-office' ); ?></p>
            </div>
            <div class="estate-office-card">
                <div class="estate-office-badge"><?php esc_html_e( 'Poszukiwania', 'estate-office' ); ?></div>
                <h2><?php echo esc_html( number_format_i18n( $searches_count ) ); ?></h2>
                <p><?php esc_html_e( 'Aktywne zlecenia poszukiwań.', 'estate-office' ); ?></p>
            </div>
        </div>
    </div>

    <div class="estate-office-section">
        <h2><?php esc_html_e( 'Najlepsi Agenci', 'estate-office' ); ?></h2>
        <table class="estate-office-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Agent', 'estate-office' ); ?></th>
                    <th><?php esc_html_e( 'E-mail', 'estate-office' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( $top_agents ) : ?>
                <?php foreach ( $top_agents as $agent ) : ?>
                    <tr>
                        <td><?php echo esc_html( $agent->display_name ); ?></td>
                        <td><a href="mailto:<?php echo esc_attr( $agent->user_email ); ?>"><?php echo esc_html( $agent->user_email ); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="2"><?php esc_html_e( 'Brak przypisanych agentów.', 'estate-office' ); ?></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
