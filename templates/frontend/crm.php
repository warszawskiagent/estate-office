<?php
/**
 * Front-end CRM view for logged-in administrators/agents.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$agreements = $wpdb->get_results( "SELECT contract_number, transaction_type, stage FROM {$wpdb->prefix}eo_agreements ORDER BY created_at DESC LIMIT 10", ARRAY_A );
$properties = $wpdb->get_results( "SELECT id, address, price FROM {$wpdb->prefix}eo_properties ORDER BY created_at DESC LIMIT 10", ARRAY_A );
$searches   = $wpdb->get_results( "SELECT id, transaction_type, criteria FROM {$wpdb->prefix}eo_searches ORDER BY created_at DESC LIMIT 10", ARRAY_A );
?>
<div class="estate-office-crm-front">
    <h2><?php esc_html_e( 'Panel CRM', 'estate-office' ); ?></h2>

    <div class="estate-office-crm-tabs">
        <button type="button" class="estate-office-crm-tab" data-target="#crm-dashboard"><?php esc_html_e( 'Pulpit', 'estate-office' ); ?></button>
        <button type="button" class="estate-office-crm-tab" data-target="#crm-agreements"><?php esc_html_e( 'Umowy', 'estate-office' ); ?></button>
        <button type="button" class="estate-office-crm-tab" data-target="#crm-properties"><?php esc_html_e( 'Nieruchomości', 'estate-office' ); ?></button>
        <button type="button" class="estate-office-crm-tab" data-target="#crm-searches"><?php esc_html_e( 'Poszukiwania', 'estate-office' ); ?></button>
    </div>

    <div id="crm-dashboard" class="estate-office-crm-panel">
        <p><?php esc_html_e( 'Witaj w Estate Office CRM. Korzystaj z menu, aby zarządzać danymi.', 'estate-office' ); ?></p>
        <p><a class="estate-office-button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-crm' ) ); ?>"><?php esc_html_e( 'Przejdź do panelu administratora', 'estate-office' ); ?></a></p>
    </div>

    <div id="crm-agreements" class="estate-office-crm-panel">
        <h3><?php esc_html_e( 'Ostatnie umowy', 'estate-office' ); ?></h3>
        <ul>
            <?php foreach ( $agreements as $agreement ) : ?>
                <li><strong><?php echo esc_html( $agreement['contract_number'] ); ?></strong> — <?php echo esc_html( $agreement['transaction_type'] ); ?> (<?php echo esc_html( $agreement['stage'] ); ?>)</li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div id="crm-properties" class="estate-office-crm-panel">
        <h3><?php esc_html_e( 'Ostatnie nieruchomości', 'estate-office' ); ?></h3>
        <ul>
            <?php foreach ( $properties as $property ) : ?>
                <li><?php echo esc_html( estate_office_format_address( $property['address'] ) ); ?> — <?php echo esc_html( number_format_i18n( (float) $property['price'], 2 ) ); ?> PLN</li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div id="crm-searches" class="estate-office-crm-panel">
        <h3><?php esc_html_e( 'Ostatnie poszukiwania', 'estate-office' ); ?></h3>
        <ul>
            <?php foreach ( $searches as $search ) :
                $criteria = json_decode( $search['criteria'], true );
                ?>
                <li><?php echo esc_html( sprintf( '%s — %s', $search['transaction_type'], $criteria['location'] ?? '-' ) ); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
