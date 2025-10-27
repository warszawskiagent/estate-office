<?php
/**
 * Dashboard view.
 *
 * @package EstateOfficeCRM\Admin\Views
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap estate-office-crm-dashboard">
    <h1><?php esc_html_e( 'Estate Office CRM – Pulpit', 'estate-office-crm' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Wersja 0.1 skupia się na fundamentach CRUD i konfiguracji ról. W kolejnych wydaniach panel pulpitowy zyska widżety analityczne oraz zaawansowane raporty.', 'estate-office-crm' ); ?>
    </p>
    <div class="eo-crm-stats">
        <div class="eo-crm-card">
            <h3><?php esc_html_e( 'Klienci', 'estate-office-crm' ); ?></h3>
            <p class="eo-crm-card__value"><?php echo esc_html( number_format_i18n( $clients_count ) ); ?></p>
        </div>
        <div class="eo-crm-card">
            <h3><?php esc_html_e( 'Umowy', 'estate-office-crm' ); ?></h3>
            <p class="eo-crm-card__value"><?php echo esc_html( number_format_i18n( $contracts_count ) ); ?></p>
        </div>
        <div class="eo-crm-card">
            <h3><?php esc_html_e( 'Nieruchomości', 'estate-office-crm' ); ?></h3>
            <p class="eo-crm-card__value"><?php echo esc_html( number_format_i18n( $properties_count ) ); ?></p>
        </div>
        <div class="eo-crm-card">
            <h3><?php esc_html_e( 'Poszukiwania', 'estate-office-crm' ); ?></h3>
            <p class="eo-crm-card__value"><?php echo esc_html( number_format_i18n( $searches_count ) ); ?></p>
        </div>
    </div>
</div>
