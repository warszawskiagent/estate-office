<?php
/**
 * Searches listing page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$view_id  = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;

$searches = $wpdb->get_results(
    "SELECT s.*, a.contract_number
    FROM {$wpdb->prefix}eo_searches s
    LEFT JOIN {$wpdb->prefix}eo_agreements a ON s.agreement_id = a.id
    ORDER BY s.created_at DESC",
    ARRAY_A
);
?>
<div class="wrap estate-office-wrap">
    <h1><?php esc_html_e( 'Poszukiwania', 'estate-office' ); ?></h1>

    <div class="estate-office-toolbar">
        <a class="estate-office-button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-agreements&action=new' ) ); ?>">
            <?php esc_html_e( 'Dodaj nową umowę', 'estate-office' ); ?>
        </a>
        <input type="search" class="regular-text search-field" placeholder="<?php esc_attr_e( 'Szukaj...', 'estate-office' ); ?>" data-action="filter-table" data-target="#estate-office-searches-table">
    </div>

    <?php if ( $view_id ) :
        $search = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}eo_searches WHERE id = %d", $view_id ), ARRAY_A );
        if ( $search ) :
            $criteria = json_decode( $search['criteria'], true );
            ?>
            <div class="estate-office-section">
                <h2><?php echo esc_html( sprintf( 'EOS-%05d', $search['id'] ) ); ?></h2>
                <p><strong><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?>:</strong> <?php echo esc_html( $search['transaction_type'] ); ?></p>
                <p><strong><?php esc_html_e( 'Budżet', 'estate-office' ); ?>:</strong> <?php echo esc_html( ( $criteria['budget_from'] ?? '-' ) . ' - ' . ( $criteria['budget_to'] ?? '-' ) ); ?></p>
                <p><strong><?php esc_html_e( 'Metraż', 'estate-office' ); ?>:</strong> <?php echo esc_html( ( $criteria['area_from'] ?? '-' ) . ' - ' . ( $criteria['area_to'] ?? '-' ) ); ?></p>
                <p><strong><?php esc_html_e( 'Lokalizacja', 'estate-office' ); ?>:</strong> <?php echo esc_html( $criteria['location'] ?? '-' ); ?></p>
            </div>

            <div class="estate-office-section">
                <h3><?php esc_html_e( 'Opis poszukiwania', 'estate-office' ); ?></h3>
                <div class="estate-office-card"><?php echo wp_kses_post( $search['description'] ); ?></div>
            </div>

            <p><a class="estate-office-button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-searches' ) ); ?>"><?php esc_html_e( 'Powrót do listy', 'estate-office' ); ?></a></p>
        <?php else : ?>
            <div class="notice notice-error"><p><?php esc_html_e( 'Poszukiwanie nie zostało znalezione.', 'estate-office' ); ?></p></div>
        <?php endif; ?>
    <?php else : ?>
    <table class="estate-office-table" id="estate-office-searches-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Numer poszukiwania', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Budżet', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Lokalizacja', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if ( $searches ) : ?>
            <?php foreach ( $searches as $search ) :
                $criteria = json_decode( $search['criteria'], true );
                ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'estate-office-searches', 'view' => (int) $search['id'] ], admin_url( 'admin.php' ) ) ); ?>">
                            <?php echo esc_html( sprintf( 'EOS-%05d', $search['id'] ) ); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html( $criteria['property_type'] ?? '-' ); ?></td>
                    <td><?php echo esc_html( $criteria['budget'] ?? '-' ); ?></td>
                    <td><?php echo esc_html( $criteria['location'] ?? '-' ); ?></td>
                    <td><?php echo esc_html( strtoupper( $search['transaction_type'] ) ); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="5"><?php esc_html_e( 'Brak aktywnych poszukiwań.', 'estate-office' ); ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
