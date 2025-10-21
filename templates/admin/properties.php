<?php
/**
 * Properties listing page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$view_id    = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;

$properties = $wpdb->get_results(
    "SELECT p.*, a.contract_number
    FROM {$wpdb->prefix}eo_properties p
    LEFT JOIN {$wpdb->prefix}eo_agreements a ON p.agreement_id = a.id
    ORDER BY p.created_at DESC",
    ARRAY_A
);

function estate_office_format_address( $json ) {
    $data = json_decode( $json, true );
    if ( empty( $data ) ) {
        return '';
    }
    $parts = array_filter( [
        $data['street'] ?? '',
        $data['number'] ?? '',
        $data['city'] ?? '',
    ] );

    return implode( ', ', $parts );
}
?>
<div class="wrap estate-office-wrap">
    <h1><?php esc_html_e( 'Nieruchomości', 'estate-office' ); ?></h1>

    <div class="estate-office-toolbar">
        <a class="estate-office-button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-agreements&action=new' ) ); ?>">
            <?php esc_html_e( 'Dodaj nową umowę', 'estate-office' ); ?>
        </a>
        <input type="search" class="regular-text search-field" placeholder="<?php esc_attr_e( 'Szukaj...', 'estate-office' ); ?>" data-action="filter-table" data-target="#estate-office-properties-table">
    </div>

    <?php if ( $view_id ) :
        $property = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}eo_properties WHERE id = %d", $view_id ), ARRAY_A );
        if ( $property ) :
            $details = json_decode( $property['details'], true );
            $amenities = json_decode( $property['amenities'], true );
            $media = json_decode( $property['media'], true );
            ?>
            <div class="estate-office-section">
                <h2><?php echo esc_html( sprintf( 'EO-%05d', $property['id'] ) ); ?></h2>
                <p><strong><?php esc_html_e( 'Adres', 'estate-office' ); ?>:</strong> <?php echo esc_html( estate_office_format_address( $property['address'] ) ); ?></p>
                <p><strong><?php esc_html_e( 'Cena', 'estate-office' ); ?>:</strong> <?php echo esc_html( number_format_i18n( (float) $property['price'], 2 ) ); ?></p>
                <p><strong><?php esc_html_e( 'Metraż', 'estate-office' ); ?>:</strong> <?php echo esc_html( number_format_i18n( (float) $property['area'], 2 ) ); ?> m²</p>
                <p><strong><?php esc_html_e( 'Opis', 'estate-office' ); ?>:</strong></p>
                <div class="estate-office-card"><?php echo wp_kses_post( $property['description'] ); ?></div>
            </div>

            <div class="estate-office-section">
                <h3><?php esc_html_e( 'Szczegóły techniczne', 'estate-office' ); ?></h3>
                <ul>
                    <?php if ( ! empty( $details ) ) : ?>
                        <?php foreach ( $details as $key => $value ) : ?>
                            <li><?php echo esc_html( $key . ': ' . $value ); ?></li>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <li><?php esc_html_e( 'Brak dodatkowych informacji.', 'estate-office' ); ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="estate-office-section">
                <h3><?php esc_html_e( 'Udogodnienia', 'estate-office' ); ?></h3>
                <ul>
                    <?php if ( ! empty( $amenities ) ) : ?>
                        <?php foreach ( $amenities as $key => $value ) : ?>
                            <?php if ( $value ) : ?>
                                <li><?php echo esc_html( $key ); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <li><?php esc_html_e( 'Brak danych o udogodnieniach.', 'estate-office' ); ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="estate-office-section">
                <h3><?php esc_html_e( 'Multimedia', 'estate-office' ); ?></h3>
                <ul>
                    <?php if ( ! empty( $media ) ) : ?>
                        <?php foreach ( $media as $item ) : ?>
                            <li><?php echo esc_html( $item['type'] ?? 'media' ); ?> — <?php echo esc_html( $item['url'] ?? '' ); ?></li>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <li><?php esc_html_e( 'Brak dodanych multimediów.', 'estate-office' ); ?></li>
                    <?php endif; ?>
                </ul>
            </div>

            <p><a class="estate-office-button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-properties' ) ); ?>"><?php esc_html_e( 'Powrót do listy', 'estate-office' ); ?></a></p>
        <?php else : ?>
            <div class="notice notice-error"><p><?php esc_html_e( 'Nieruchomość nie została znaleziona.', 'estate-office' ); ?></p></div>
        <?php endif; ?>
    <?php else : ?>
    <table class="estate-office-table" id="estate-office-properties-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Numer oferty', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Adres', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Cena', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Cena za m²', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Metraż', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Liczba pokoi', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if ( $properties ) : ?>
            <?php foreach ( $properties as $property ) :
                $details = json_decode( $property['details'], true );
                $agent   = get_userdata( $details['agent_id'] ?? 0 );
                ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'estate-office-properties', 'view' => (int) $property['id'] ], admin_url( 'admin.php' ) ) ); ?>">
                            <?php echo esc_html( sprintf( 'EO-%05d', $property['id'] ) ); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html( estate_office_format_address( $property['address'] ) ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( (float) $property['price'], 2 ) ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( (float) $property['price_per_sqm'], 2 ) ); ?></td>
                    <td><?php echo esc_html( number_format_i18n( (float) $property['area'], 2 ) ); ?></td>
                    <td><?php echo esc_html( $details['rooms'] ?? '-' ); ?></td>
                    <td><?php echo esc_html( $agent ? $agent->display_name : __( 'Nie przypisano', 'estate-office' ) ); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="7"><?php esc_html_e( 'Brak nieruchomości do wyświetlenia.', 'estate-office' ); ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
