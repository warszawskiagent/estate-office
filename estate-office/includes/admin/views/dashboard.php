<?php
global $wpdb;

$agents_count      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eo_agents" );
$clients_count     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eo_clients" );
$contracts_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eo_contracts" );
$properties_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eo_properties" );
$searches_count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eo_searches" );
?>
<div class="wrap estate-office-dashboard">
    <h1><?php esc_html_e( 'Estate Office CRM – Pulpit', 'estate-office' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Przegląd kluczowych wskaźników CRM.', 'estate-office' ); ?></p>

    <div class="estate-office-grid">
        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Nieruchomości', 'estate-office' ); ?></h2>
            <p class="estate-office-metric"><?php echo esc_html( number_format_i18n( $properties_count ) ); ?></p>
            <p class="description"><?php esc_html_e( 'Łączna liczba nieruchomości w bazie.', 'estate-office' ); ?></p>
        </div>
        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Umowy', 'estate-office' ); ?></h2>
            <p class="estate-office-metric"><?php echo esc_html( number_format_i18n( $contracts_count ) ); ?></p>
            <p class="description"><?php esc_html_e( 'Aktywne i archiwalne umowy.', 'estate-office' ); ?></p>
        </div>
        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Klienci', 'estate-office' ); ?></h2>
            <p class="estate-office-metric"><?php echo esc_html( number_format_i18n( $clients_count ) ); ?></p>
            <p class="description"><?php esc_html_e( 'Wszyscy klienci w systemie.', 'estate-office' ); ?></p>
        </div>
        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Agenci', 'estate-office' ); ?></h2>
            <p class="estate-office-metric"><?php echo esc_html( number_format_i18n( $agents_count ) ); ?></p>
            <p class="description"><?php esc_html_e( 'Zarejestrowani agenci nieruchomości.', 'estate-office' ); ?></p>
        </div>
        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Poszukiwania', 'estate-office' ); ?></h2>
            <p class="estate-office-metric"><?php echo esc_html( number_format_i18n( $searches_count ) ); ?></p>
            <p class="description"><?php esc_html_e( 'Aktywne potrzeby klientów.', 'estate-office' ); ?></p>
        </div>
    </div>

    <div class="estate-office-card">
        <h2><?php esc_html_e( 'Szybkie akcje', 'estate-office' ); ?></h2>
        <p><?php esc_html_e( 'Rozpocznij pracę z CRM, przechodząc do kluczowych sekcji.', 'estate-office' ); ?></p>
        <p class="estate-office-quick-actions">
            <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-agents' ) ); ?>"><?php esc_html_e( 'Dodaj agenta', 'estate-office' ); ?></a>
            <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-settings' ) ); ?>"><?php esc_html_e( 'Konfiguruj ustawienia', 'estate-office' ); ?></a>
        </p>
    </div>
</div>
