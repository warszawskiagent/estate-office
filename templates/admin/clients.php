<?php
/**
 * Clients listing page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$clients = $wpdb->get_results(
    "SELECT c.*, ac.agreement_id
    FROM {$wpdb->prefix}eo_clients c
    LEFT JOIN {$wpdb->prefix}eo_agreement_clients ac ON ac.client_id = c.id
    GROUP BY c.id
    ORDER BY c.created_at DESC",
    ARRAY_A
);
?>
<div class="wrap estate-office-wrap">
    <h1><?php esc_html_e( 'Klienci', 'estate-office' ); ?></h1>

    <div class="estate-office-toolbar">
        <a class="estate-office-button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-agreements&action=new' ) ); ?>">
            <?php esc_html_e( 'Dodaj nową umowę', 'estate-office' ); ?>
        </a>
        <input type="search" class="regular-text search-field" placeholder="<?php esc_attr_e( 'Szukaj...', 'estate-office' ); ?>" data-action="filter-table" data-target="#estate-office-clients-table">
    </div>

    <table class="estate-office-table" id="estate-office-clients-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Imię i nazwisko / Nazwa', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Adres', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Telefon', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'E-mail', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Powiązana umowa', 'estate-office' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if ( $clients ) : ?>
            <?php foreach ( $clients as $client ) :
                $label = $client['type'] === 'company' ? $client['company_name'] : trim( $client['first_name'] . ' ' . $client['last_name'] );
                $address = $client['address'] ? estate_office_format_address( $client['address'] ) : '';
                ?>
                <tr>
                    <td><?php echo esc_html( $label ); ?></td>
                    <td><?php echo esc_html( $address ); ?></td>
                    <td><a href="tel:<?php echo esc_attr( $client['phone'] ); ?>"><?php echo esc_html( $client['phone'] ); ?></a></td>
                    <td><a href="mailto:<?php echo esc_attr( $client['email'] ); ?>"><?php echo esc_html( $client['email'] ); ?></a></td>
                    <td>
                        <?php if ( $client['agreement_id'] ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'estate-office-agreements', 'view' => (int) $client['agreement_id'] ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Przejdź do umowy', 'estate-office' ); ?></a>
                        <?php else : ?>
                            <?php esc_html_e( 'Brak powiązania', 'estate-office' ); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="5"><?php esc_html_e( 'Brak klientów.', 'estate-office' ); ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
