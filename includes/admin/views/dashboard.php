<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$counts = [
    'properties'  => wp_count_posts( 'estate_property' ),
    'contracts'   => wp_count_posts( 'estate_contract' ),
    'clients'     => wp_count_posts( 'estate_client' ),
    'searches'    => wp_count_posts( 'estate_search' ),
];
$agents = get_users( [ 'role' => 'estate_agent', 'number' => 5, 'orderby' => 'display_name', 'order' => 'DESC' ] );
?>
<div class="wrap estate-office-dashboard">
    <h1><?php esc_html_e( 'Estate Office CRM – Pulpit', 'estate-office' ); ?></h1>

    <div class="estate-office-stats">
        <div class="stat">
            <span class="label"><?php esc_html_e( 'Nieruchomości', 'estate-office' ); ?></span>
            <span class="value"><?php echo esc_html( number_format_i18n( $counts['properties']->publish ?? 0 ) ); ?></span>
        </div>
        <div class="stat">
            <span class="label"><?php esc_html_e( 'Umowy aktywne', 'estate-office' ); ?></span>
            <span class="value"><?php echo esc_html( number_format_i18n( $counts['contracts']->publish ?? 0 ) ); ?></span>
        </div>
        <div class="stat">
            <span class="label"><?php esc_html_e( 'Poszukiwania', 'estate-office' ); ?></span>
            <span class="value"><?php echo esc_html( number_format_i18n( $counts['searches']->publish ?? 0 ) ); ?></span>
        </div>
        <div class="stat">
            <span class="label"><?php esc_html_e( 'Klienci', 'estate-office' ); ?></span>
            <span class="value"><?php echo esc_html( number_format_i18n( $counts['clients']->publish ?? 0 ) ); ?></span>
        </div>
    </div>

    <div class="estate-office-agents-list">
        <h2><?php esc_html_e( 'Najaktywniejsi agenci', 'estate-office' ); ?></h2>
        <?php if ( $agents ) : ?>
            <ul>
                <?php foreach ( $agents as $agent ) : ?>
                    <li>
                        <strong><?php echo esc_html( $agent->display_name ); ?></strong>
                        <span><?php echo esc_html( get_user_meta( $agent->ID, '_estate_office_phone', true ) ); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p><?php esc_html_e( 'Brak przypisanych agentów.', 'estate-office' ); ?></p>
        <?php endif; ?>
    </div>

    <p class="description">
        <?php esc_html_e( 'W kolejnych wersjach pulpitu pojawią się szczegółowe wskaźniki sprzedaży, aktywne umowy oraz harmonogram działań.', 'estate-office' ); ?>
    </p>
</div>
