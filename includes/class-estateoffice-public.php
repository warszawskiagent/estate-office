<?php
/**
 * Public-facing functionality.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EstateOffice_Public {

    /**
     * Register WordPress hooks for the public module.
     */
    public function hooks(): void {
        add_shortcode( 'estate_office_crm', [ $this, 'render_crm_shortcode' ] );
        add_shortcode( 'estate_office_offers', [ $this, 'render_offers_shortcode' ] );
        add_shortcode( 'estate_office_notary_calculator', [ $this, 'render_notary_calculator_shortcode' ] );
        add_shortcode( 'estate_office_mortgage_calculator', [ $this, 'render_mortgage_calculator_shortcode' ] );
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_links' ], 100 );
    }

    /**
     * Append quick links to the admin bar for fast navigation.
     */
    public function add_admin_bar_links( \WP_Admin_Bar $admin_bar ): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'eo_view_crm' ) ) {
            return;
        }

        $crm_page_id = (int) get_option( 'estate_office_crm_page_id' );
        if ( $crm_page_id ) {
            $admin_bar->add_node(
                [
                    'id'    => 'estate-office-crm',
                    'title' => __( 'EstateOffice CRM', 'estate-office' ),
                    'href'  => get_permalink( $crm_page_id ),
                    'meta'  => [ 'class' => 'estate-office-admin-bar-link' ],
                ]
            );
        }
    }

    /**
     * Render CRM workspace shortcode available to privileged users on the front-end.
     *
     * @param array<string,mixed> $atts Shortcode attributes.
     * @return string
     */
    public function render_crm_shortcode( array $atts ): string {
        if ( ! is_user_logged_in() || ! current_user_can( 'eo_view_crm' ) ) {
            return '<div class="estate-office-notice">' . esc_html__( 'Ten obszar jest dostępny wyłącznie dla zespołu biura.', 'estate-office' ) . '</div>';
        }

        $this->enqueue_assets();

        $tabs = [
            'dashboard'   => __( 'Pulpit', 'estate-office' ),
            'properties'  => __( 'Nieruchomości', 'estate-office' ),
            'searches'    => __( 'Poszukiwania', 'estate-office' ),
            'contracts'   => __( 'Umowy', 'estate-office' ),
            'clients'     => __( 'Klienci', 'estate-office' ),
        ];

        $tab = isset( $_GET['eo_tab'] ) ? sanitize_key( wp_unslash( $_GET['eo_tab'] ) ) : 'dashboard';
        if ( ! isset( $tabs[ $tab ] ) ) {
            $tab = 'dashboard';
        }

        $search = isset( $_GET['eo_search'] ) ? sanitize_text_field( wp_unslash( $_GET['eo_search'] ) ) : '';

        $base_url = $this->get_current_url();

        ob_start();
        ?>
        <div class="estate-office-crm" data-active-tab="<?php echo esc_attr( $tab ); ?>">
            <div class="estate-office-crm-header">
                <h2><?php esc_html_e( 'EstateOffice CRM', 'estate-office' ); ?></h2>
                <a class="estate-office-button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . EstateOffice_Admin_Contracts::SLUG . '&action=new' ) ); ?>">
                    <?php esc_html_e( 'Dodaj nową umowę', 'estate-office' ); ?>
                </a>
            </div>
            <nav class="estate-office-crm-nav" aria-label="<?php esc_attr_e( 'Nawigacja CRM', 'estate-office' ); ?>">
                <ul>
                    <?php foreach ( $tabs as $key => $label ) : ?>
                        <li class="<?php echo $key === $tab ? 'is-active' : ''; ?>">
                            <a href="<?php echo esc_url( add_query_arg( [ 'eo_tab' => $key, 'eo_search' => '' ], $base_url ) ); ?>"><?php echo esc_html( $label ); ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <form method="get" class="estate-office-crm-search">
                <input type="hidden" name="eo_tab" value="<?php echo esc_attr( $tab ); ?>" />
                <label for="estate-office-crm-search" class="screen-reader-text"><?php esc_html_e( 'Szukaj', 'estate-office' ); ?></label>
                <input type="search" id="estate-office-crm-search" name="eo_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Szukaj w bieżącej sekcji', 'estate-office' ); ?>" />
                <button type="submit" class="estate-office-button secondary"><?php esc_html_e( 'Szukaj', 'estate-office' ); ?></button>
            </form>
            <div class="estate-office-crm-body">
                <?php
                switch ( $tab ) {
                    case 'properties':
                        $this->render_properties_table( $search, $base_url );
                        break;
                    case 'searches':
                        $this->render_searches_table( $search, $base_url );
                        break;
                    case 'contracts':
                        $this->render_contracts_table( $search, $base_url );
                        break;
                    case 'clients':
                        $this->render_clients_table( $search, $base_url );
                        break;
                    case 'dashboard':
                    default:
                        $this->render_dashboard();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render dashboard summary section.
     */
    protected function render_dashboard(): void {
        $metrics = $this->get_dashboard_metrics();
        ?>
        <section class="estate-office-dashboard">
            <div class="estate-office-cards">
                <article class="estate-office-card">
                    <h3><?php esc_html_e( 'Nieruchomości', 'estate-office' ); ?></h3>
                    <p class="estate-office-card-value"><?php echo esc_html( $metrics['properties'] ); ?></p>
                </article>
                <article class="estate-office-card">
                    <h3><?php esc_html_e( 'Aktywne umowy', 'estate-office' ); ?></h3>
                    <p class="estate-office-card-value"><?php echo esc_html( $metrics['contracts'] ); ?></p>
                </article>
                <article class="estate-office-card">
                    <h3><?php esc_html_e( 'Poszukiwania', 'estate-office' ); ?></h3>
                    <p class="estate-office-card-value"><?php echo esc_html( $metrics['searches'] ); ?></p>
                </article>
                <article class="estate-office-card">
                    <h3><?php esc_html_e( 'Klienci', 'estate-office' ); ?></h3>
                    <p class="estate-office-card-value"><?php echo esc_html( $metrics['clients'] ); ?></p>
                </article>
            </div>
            <?php if ( ! empty( $metrics['recent_contracts'] ) ) : ?>
                <div class="estate-office-dashboard-section">
                    <h3><?php esc_html_e( 'Ostatnie umowy', 'estate-office' ); ?></h3>
                    <ul class="estate-office-list">
                        <?php foreach ( $metrics['recent_contracts'] as $contract ) : ?>
                            <li>
                                <span class="estate-office-list-primary">#<?php echo esc_html( sprintf( '%05d', $contract->id ) ); ?></span>
                                <span><?php echo esc_html( $contract->contract_number ); ?></span>
                                <span><?php echo esc_html( $this->format_date( $contract->start_date ) ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ( ! empty( $metrics['top_agents'] ) ) : ?>
                <div class="estate-office-dashboard-section">
                    <h3><?php esc_html_e( 'Najlepsi agenci', 'estate-office' ); ?></h3>
                    <ul class="estate-office-list">
                        <?php foreach ( $metrics['top_agents'] as $agent ) : ?>
                            <li>
                                <span class="estate-office-list-primary"><?php echo esc_html( $agent['name'] ); ?></span>
                                <span><?php echo esc_html( sprintf( _n( '%d umowa', '%d umowy', $agent['contracts'], 'estate-office' ), $agent['contracts'] ) ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    /**
     * Render properties table view.
     */
    protected function render_properties_table( string $search, string $base_url ): void {
        $properties     = EstateOffice_Admin_Properties::get_properties( $search );
        $selected       = isset( $_GET['eo_property'] ) ? absint( $_GET['eo_property'] ) : 0;
        $selected_entry = $selected ? EstateOffice_Admin_Properties::get_property( $selected ) : null;
        ?>
        <section class="estate-office-table-section">
            <table class="estate-office-table">
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
                    <?php if ( empty( $properties ) ) : ?>
                        <tr><td colspan="7"><?php esc_html_e( 'Brak nieruchomości.', 'estate-office' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $properties as $property ) : ?>
                            <tr class="<?php echo (int) $property->id === $selected ? 'is-selected' : ''; ?>">
                                <td><a href="<?php echo esc_url( add_query_arg( [ 'eo_tab' => 'properties', 'eo_property' => (int) $property->id ], $base_url ) ); ?>"><?php echo esc_html( sprintf( '#%05d', $property->id ) ); ?></a></td>
                                <td><?php echo esc_html( $property->address_display ); ?></td>
                                <td><?php echo esc_html( $this->format_currency( $property->price ) ); ?></td>
                                <td><?php echo esc_html( $this->format_currency( $property->price_m2 ) ); ?></td>
                                <td><?php echo esc_html( $property->area ? $property->area . ' m²' : '' ); ?></td>
                                <td><?php echo esc_html( $property->rooms ); ?></td>
                                <td><?php echo esc_html( EstateOffice_Admin_Agents::format_agent_from_row( $property ) ?: '—' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ( $selected_entry ) : ?>
                <?php $this->render_property_profile( $selected_entry ); ?>
            <?php endif; ?>
        </section>
        <?php
    }

    /**
     * Render property profile details.
     *
     * @param object $property Database row.
     */
    protected function render_property_profile( $property ): void {
        $details = $property->details ? json_decode( $property->details, true ) : [];
        $address = $property->address ? json_decode( $property->address, true ) : [];
        ?>
        <div class="estate-office-profile">
            <div>
                <h3><?php esc_html_e( 'Szczegóły nieruchomości', 'estate-office' ); ?></h3>
                <dl>
                    <div><dt><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></dt><dd><?php echo esc_html( $property->transaction_type ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Rodzaj', 'estate-office' ); ?></dt><dd><?php echo esc_html( $property->property_type ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Cena', 'estate-office' ); ?></dt><dd><?php echo esc_html( $this->format_currency( $details['price'] ?? '' ) ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Metraż', 'estate-office' ); ?></dt><dd><?php echo esc_html( ! empty( $details['area'] ) ? $details['area'] . ' m²' : '' ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Pokoje', 'estate-office' ); ?></dt><dd><?php echo esc_html( $details['rooms'] ?? '' ); ?></dd></div>
                    <?php if ( ! empty( $property->agent_id ) ) :
                        $agent = EstateOffice_Admin_Agents::get_agent( (int) $property->agent_id );
                        $agent_label = EstateOffice_Admin_Agents::format_agent_name( $agent );
                        ?>
                        <div><dt><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></dt><dd><?php echo esc_html( $agent_label ?: sprintf( __( 'Agent #%d', 'estate-office' ), (int) $property->agent_id ) ); ?></dd></div>
                    <?php endif; ?>
                </dl>
                <h4><?php esc_html_e( 'Adres', 'estate-office' ); ?></h4>
                <p>
                    <?php echo esc_html( $this->format_address( $address ) ); ?>
                </p>
            </div>
            <div>
                <h3><?php esc_html_e( 'Opis', 'estate-office' ); ?></h3>
                <div class="estate-office-profile-content"><?php echo wp_kses_post( wpautop( $property->description ?? '' ) ); ?></div>
                <?php
                $contract_id = (int) $property->contract_id;
                if ( $contract_id ) {
                    $contract = EstateOffice_Admin_Contracts::get_contract( $contract_id );
                    if ( $contract ) {
                        ?>
                        <p><a class="estate-office-button secondary" href="<?php echo esc_url( add_query_arg( [ 'eo_tab' => 'contracts', 'eo_contract' => $contract_id ], $this->get_current_url() ) ); ?>"><?php esc_html_e( 'Przejdź do umowy', 'estate-office' ); ?></a></p>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render searches table view.
     */
    protected function render_searches_table( string $search, string $base_url ): void {
        $searches = EstateOffice_Admin_Searches::get_searches( $search );
        $selected = isset( $_GET['eo_search_id'] ) ? absint( $_GET['eo_search_id'] ) : 0;
        $entry    = $selected ? EstateOffice_Admin_Searches::get_search( $selected ) : null;
        ?>
        <section class="estate-office-table-section">
            <table class="estate-office-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Numer poszukiwania', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Budżet', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Lokalizacja', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $searches ) ) : ?>
                        <tr><td colspan="6"><?php esc_html_e( 'Brak poszukiwań.', 'estate-office' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $searches as $item ) :
                            $criteria = $item->criteria ? json_decode( $item->criteria, true ) : [];
                            ?>
                            <tr class="<?php echo (int) $item->id === $selected ? 'is-selected' : ''; ?>">
                                <td><a href="<?php echo esc_url( add_query_arg( [ 'eo_tab' => 'searches', 'eo_search_id' => (int) $item->id ], $base_url ) ); ?>"><?php echo esc_html( sprintf( '#POS%05d', $item->id ) ); ?></a></td>
                                <td><?php echo esc_html( $criteria['property_type'] ?? '' ); ?></td>
                                <td><?php echo esc_html( $this->format_range( $criteria['budget_min'] ?? '', $criteria['budget_max'] ?? '' ) ); ?></td>
                                <td><?php echo esc_html( $criteria['city'] ?? '' ); ?></td>
                                <td><?php echo esc_html( $item->transaction_type ); ?></td>
                                <td><?php echo esc_html( EstateOffice_Admin_Agents::format_agent_from_row( $item ) ?: '—' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ( $entry ) : ?>
                <?php $this->render_search_profile( $entry ); ?>
            <?php endif; ?>
        </section>
        <?php
    }

    /**
     * Render profile for search criteria.
     */
    protected function render_search_profile( $search ): void {
        $criteria = $search->criteria ? json_decode( $search->criteria, true ) : [];
        ?>
        <div class="estate-office-profile">
            <div>
                <h3><?php esc_html_e( 'Parametry poszukiwania', 'estate-office' ); ?></h3>
                <dl>
                    <div><dt><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></dt><dd><?php echo esc_html( $search->transaction_type ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></dt><dd><?php echo esc_html( $criteria['property_type'] ?? '' ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Budżet', 'estate-office' ); ?></dt><dd><?php echo esc_html( $this->format_range( $criteria['budget_min'] ?? '', $criteria['budget_max'] ?? '' ) ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Metraż', 'estate-office' ); ?></dt><dd><?php echo esc_html( $this->format_range( $criteria['area_min'] ?? '', $criteria['area_max'] ?? '', ' m²' ) ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Liczba pokoi', 'estate-office' ); ?></dt><dd><?php echo esc_html( $this->format_range( $criteria['rooms_min'] ?? '', $criteria['rooms_max'] ?? '' ) ); ?></dd></div>
                    <?php if ( ! empty( $search->agent_id ) ) :
                        $agent = EstateOffice_Admin_Agents::get_agent( (int) $search->agent_id );
                        $agent_label = EstateOffice_Admin_Agents::format_agent_name( $agent );
                        ?>
                        <div><dt><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></dt><dd><?php echo esc_html( $agent_label ?: sprintf( __( 'Agent #%d', 'estate-office' ), (int) $search->agent_id ) ); ?></dd></div>
                    <?php endif; ?>
                </dl>
            </div>
            <div>
                <h3><?php esc_html_e( 'Opis', 'estate-office' ); ?></h3>
                <div class="estate-office-profile-content"><?php echo wp_kses_post( wpautop( $search->description ?? '' ) ); ?></div>
                <?php
                $contract_id = (int) $search->contract_id;
                if ( $contract_id ) {
                    ?>
                    <p><a class="estate-office-button secondary" href="<?php echo esc_url( add_query_arg( [ 'eo_tab' => 'contracts', 'eo_contract' => $contract_id ], $this->get_current_url() ) ); ?>"><?php esc_html_e( 'Przejdź do umowy', 'estate-office' ); ?></a></p>
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render contracts table view.
     */
    protected function render_contracts_table( string $search, string $base_url ): void {
        $contracts = EstateOffice_Admin_Contracts::get_contracts( $search );
        $selected  = isset( $_GET['eo_contract'] ) ? absint( $_GET['eo_contract'] ) : 0;
        $entry     = $selected ? EstateOffice_Admin_Contracts::get_contract( $selected ) : null;
        ?>
        <section class="estate-office-table-section">
            <table class="estate-office-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Numer umowy', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Adres', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Data zawarcia', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Data zakończenia', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Aktualny etap', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $contracts ) ) : ?>
                        <tr><td colspan="8"><?php esc_html_e( 'Brak umów.', 'estate-office' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $contracts as $contract ) : ?>
                            <tr class="<?php echo (int) $contract->id === $selected ? 'is-selected' : ''; ?>">
                                <td><a href="<?php echo esc_url( add_query_arg( [ 'eo_tab' => 'contracts', 'eo_contract' => (int) $contract->id ], $base_url ) ); ?>"><?php echo esc_html( $contract->contract_number ); ?></a></td>
                                <td><?php echo esc_html( $contract->transaction_type ); ?></td>
                                <td><?php echo esc_html( $contract->property_type ?? '' ); ?></td>
                                <td><?php echo esc_html( $contract->address ?? '' ); ?></td>
                                <td><?php echo esc_html( $this->format_date( $contract->start_date ) ); ?></td>
                                <td><?php echo esc_html( $contract->indefinite ? __( 'Bezterminowa', 'estate-office' ) : $this->format_date( $contract->end_date ) ); ?></td>
                                <td><?php echo esc_html( $this->format_stage( $contract->stage ) ); ?></td>
                                <td><?php echo esc_html( EstateOffice_Admin_Agents::format_agent_from_row( $contract ) ?: '—' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ( $entry ) : ?>
                <?php $this->render_contract_profile( $entry ); ?>
            <?php endif; ?>
        </section>
        <?php
    }

    /**
     * Render contract profile information.
     */
    protected function render_contract_profile( $contract ): void {
        $stage_history = $contract->stage_history ? json_decode( $contract->stage_history, true ) : [];
        if ( ! is_array( $stage_history ) ) {
            $stage_history = [];
        }
        $client_ids = EstateOffice_Admin_Contracts::get_contract_clients( (int) $contract->id );
        $clients    = [];
        foreach ( $client_ids as $client_id ) {
            $client = EstateOffice_Admin_Clients::get_client( (int) $client_id );
            if ( $client ) {
                $clients[] = $client;
            }
        }
        $property = EstateOffice_Admin_Contracts::get_property_for_contract( (int) $contract->id );
        $search   = EstateOffice_Admin_Contracts::get_search_for_contract( (int) $contract->id );
        ?>
        <div class="estate-office-profile">
            <div>
                <h3><?php esc_html_e( 'Dane umowy', 'estate-office' ); ?></h3>
                <dl>
                    <div><dt><?php esc_html_e( 'Numer umowy', 'estate-office' ); ?></dt><dd><?php echo esc_html( $contract->contract_number ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></dt><dd><?php echo esc_html( $contract->transaction_type ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Data zawarcia', 'estate-office' ); ?></dt><dd><?php echo esc_html( $this->format_date( $contract->start_date ) ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Data zakończenia', 'estate-office' ); ?></dt><dd><?php echo esc_html( $contract->indefinite ? __( 'Bezterminowa', 'estate-office' ) : $this->format_date( $contract->end_date ) ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Prowizja', 'estate-office' ); ?></dt><dd><?php echo esc_html( $this->format_commission( $contract ) ); ?></dd></div>
                    <?php if ( ! empty( $contract->agent_id ) ) :
                        $agent = EstateOffice_Admin_Agents::get_agent( (int) $contract->agent_id );
                        $agent_label = EstateOffice_Admin_Agents::format_agent_name( $agent );
                        ?>
                        <div><dt><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></dt><dd><?php echo esc_html( $agent_label ?: sprintf( __( 'Agent #%d', 'estate-office' ), (int) $contract->agent_id ) ); ?></dd></div>
                    <?php endif; ?>
                </dl>
                <?php if ( ! empty( $clients ) ) : ?>
                    <h4><?php esc_html_e( 'Klienci', 'estate-office' ); ?></h4>
                    <ul class="estate-office-list">
                        <?php foreach ( $clients as $client ) : ?>
                            <li><a href="<?php echo esc_url( add_query_arg( [ 'eo_tab' => 'clients', 'eo_client' => (int) $client->id ], $this->get_current_url() ) ); ?>"><?php echo esc_html( $this->format_client_name( $client ) ); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ( $property ) : ?>
                    <p><a class="estate-office-button secondary" href="<?php echo esc_url( add_query_arg( [ 'eo_tab' => 'properties', 'eo_property' => (int) $property->id ], $this->get_current_url() ) ); ?>"><?php esc_html_e( 'Powiązana nieruchomość', 'estate-office' ); ?></a></p>
                <?php elseif ( $search ) : ?>
                    <p><a class="estate-office-button secondary" href="<?php echo esc_url( add_query_arg( [ 'eo_tab' => 'searches', 'eo_search_id' => (int) $search->id ], $this->get_current_url() ) ); ?>"><?php esc_html_e( 'Powiązane poszukiwanie', 'estate-office' ); ?></a></p>
                <?php endif; ?>
            </div>
            <div>
                <h3><?php esc_html_e( 'Etapy umowy', 'estate-office' ); ?></h3>
                <p><?php esc_html_e( 'Aktualny etap:', 'estate-office' ); ?> <strong><?php echo esc_html( $this->format_stage( $contract->stage ) ); ?></strong></p>
                <?php if ( ! empty( $stage_history ) ) : ?>
                    <table class="estate-office-table compact">
                        <thead><tr><th><?php esc_html_e( 'Data', 'estate-office' ); ?></th><th><?php esc_html_e( 'Etap', 'estate-office' ); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ( $stage_history as $entry ) :
                                $stage_label = isset( $entry['stage'] ) ? $this->format_stage( $entry['stage'] ) : '';
                                ?>
                                <tr>
                                    <td><?php echo esc_html( isset( $entry['date'] ) ? $this->format_date( $entry['date'] ) : '' ); ?></td>
                                    <td><?php echo esc_html( $stage_label ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render clients table view.
     */
    protected function render_clients_table( string $search, string $base_url ): void {
        $clients  = EstateOffice_Admin_Clients::get_clients( $search );
        $selected = isset( $_GET['eo_client'] ) ? absint( $_GET['eo_client'] ) : 0;
        $entry    = $selected ? EstateOffice_Admin_Clients::get_client( $selected ) : null;
        ?>
        <section class="estate-office-table-section">
            <table class="estate-office-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Imię i nazwisko/Nazwa', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Adres', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Telefon', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'E-mail', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $clients ) ) : ?>
                        <tr><td colspan="5"><?php esc_html_e( 'Brak klientów.', 'estate-office' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $clients as $client ) : ?>
                            <tr class="<?php echo (int) $client->id === $selected ? 'is-selected' : ''; ?>">
                                <td><a href="<?php echo esc_url( add_query_arg( [ 'eo_tab' => 'clients', 'eo_client' => (int) $client->id ], $base_url ) ); ?>"><?php echo esc_html( $this->format_client_name( $client ) ); ?></a></td>
                                <td><?php echo esc_html( $this->format_address_from_json( $client->address ?? '' ) ); ?></td>
                                <td><?php echo esc_html( $client->phone ); ?></td>
                                <td><?php echo esc_html( $client->email ); ?></td>
                                <td><?php echo esc_html( EstateOffice_Admin_Agents::format_agent_from_row( $client ) ?: '—' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ( $entry ) : ?>
                <?php $this->render_client_profile( $entry ); ?>
            <?php endif; ?>
        </section>
        <?php
    }

    /**
     * Render client profile data.
     */
    protected function render_client_profile( $client ): void {
        $address          = $client->address ? json_decode( $client->address, true ) : [];
        $correspondence   = $client->correspondence_address ? json_decode( $client->correspondence_address, true ) : [];
        $identification   = $client->identification ? json_decode( $client->identification, true ) : [];
        $custom_fields    = $client->custom_fields ? json_decode( $client->custom_fields, true ) : [];
        $contracts        = $this->get_contracts_for_client( (int) $client->id );
        $properties_links = $this->get_properties_for_client( (int) $client->id );
        ?>
        <div class="estate-office-profile">
            <div>
                <h3><?php esc_html_e( 'Profil klienta', 'estate-office' ); ?></h3>
                <dl>
                    <div><dt><?php esc_html_e( 'Typ klienta', 'estate-office' ); ?></dt><dd><?php echo esc_html( 'company' === $client->client_type ? __( 'Firma', 'estate-office' ) : __( 'Osoba fizyczna', 'estate-office' ) ); ?></dd></div>
                    <?php if ( 'company' === $client->client_type ) : ?>
                        <div><dt><?php esc_html_e( 'Nazwa firmy', 'estate-office' ); ?></dt><dd><?php echo esc_html( $client->company_name ); ?></dd></div>
                        <div><dt><?php esc_html_e( 'Reprezentant', 'estate-office' ); ?></dt><dd><?php echo esc_html( trim( $client->first_name . ' ' . $client->last_name ) ); ?></dd></div>
                    <?php else : ?>
                        <div><dt><?php esc_html_e( 'Imię i nazwisko', 'estate-office' ); ?></dt><dd><?php echo esc_html( $this->format_client_name( $client ) ); ?></dd></div>
                    <?php endif; ?>
                    <div><dt><?php esc_html_e( 'Telefon', 'estate-office' ); ?></dt><dd><?php echo esc_html( $client->phone ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'E-mail', 'estate-office' ); ?></dt><dd><?php echo esc_html( $client->email ); ?></dd></div>
                    <?php if ( ! empty( $client->agent_id ) ) :
                        $agent = EstateOffice_Admin_Agents::get_agent( (int) $client->agent_id );
                        $agent_label = EstateOffice_Admin_Agents::format_agent_name( $agent );
                        ?>
                        <div><dt><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></dt><dd><?php echo esc_html( $agent_label ?: sprintf( __( 'Agent #%d', 'estate-office' ), (int) $client->agent_id ) ); ?></dd></div>
                    <?php endif; ?>
                </dl>
                <?php if ( ! empty( $address ) ) : ?>
                    <h4><?php esc_html_e( 'Adres zamieszkania/rejestrowy', 'estate-office' ); ?></h4>
                    <p><?php echo esc_html( $this->format_address( $address ) ); ?></p>
                <?php endif; ?>
                <?php if ( ! empty( $correspondence ) ) : ?>
                    <h4><?php esc_html_e( 'Adres korespondencyjny', 'estate-office' ); ?></h4>
                    <p><?php echo esc_html( $this->format_address( $correspondence ) ); ?></p>
                <?php endif; ?>
            </div>
            <div>
                <?php if ( ! empty( $identification ) ) : ?>
                    <h3><?php esc_html_e( 'Dane identyfikacyjne', 'estate-office' ); ?></h3>
                    <dl>
                        <?php foreach ( $identification as $key => $value ) : ?>
                            <div><dt><?php echo esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
                <?php if ( ! empty( $custom_fields ) ) : ?>
                    <h3><?php esc_html_e( 'Dodatkowe informacje', 'estate-office' ); ?></h3>
                    <dl>
                        <?php foreach ( $custom_fields as $key => $value ) : ?>
                            <div><dt><?php echo esc_html( $key ); ?></dt><dd><?php echo esc_html( is_scalar( $value ) ? $value : wp_json_encode( $value ) ); ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
                <?php if ( ! empty( $contracts ) ) : ?>
                    <h3><?php esc_html_e( 'Powiązane umowy', 'estate-office' ); ?></h3>
                    <ul class="estate-office-list">
                        <?php foreach ( $contracts as $contract ) : ?>
                            <li><a href="<?php echo esc_url( add_query_arg( [ 'eo_tab' => 'contracts', 'eo_contract' => (int) $contract->id ], $this->get_current_url() ) ); ?>"><?php echo esc_html( $contract->contract_number ); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ( ! empty( $properties_links ) ) : ?>
                    <h3><?php esc_html_e( 'Powiązane oferty', 'estate-office' ); ?></h3>
                    <ul class="estate-office-list">
                        <?php foreach ( $properties_links as $link ) : ?>
                            <li><a href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render publicly available property offers grouped by taxonomy-like categories.
     *
     * @param array<string,mixed> $atts Shortcode attributes.
     * @return string
     */
    public function render_offers_shortcode( array $atts ): string {
        $atts = shortcode_atts(
            [
                'transaction' => 'SPRZEDAŻ',
            ],
            $atts,
            'estate_office_offers'
        );

        $transaction = strtoupper( sanitize_text_field( $atts['transaction'] ) );
        if ( ! in_array( $transaction, EstateOffice_Admin_Contracts::TRANSACTION_TYPES, true ) ) {
            $transaction = 'SPRZEDAŻ';
        }

        $this->enqueue_assets();

        $properties = $this->get_public_offers( $transaction );
        if ( empty( $properties ) ) {
            return '<div class="estate-office-offers-empty">' . esc_html__( 'Brak ofert spełniających kryteria.', 'estate-office' ) . '</div>';
        }

        $grouped = [];
        foreach ( $properties as $property ) {
            $grouped[ $property['property_type'] ][ $property['city'] ][ $property['district'] ][] = $property;
        }

        ob_start();
        ?>
        <div class="estate-office-offers" data-transaction="<?php echo esc_attr( $transaction ); ?>">
            <?php foreach ( $grouped as $type => $cities ) : ?>
                <section class="estate-office-offers-section">
                    <h3><?php echo esc_html( $type ); ?></h3>
                    <?php foreach ( $cities as $city => $districts ) : ?>
                        <div class="estate-office-offers-city">
                            <h4><?php echo esc_html( $city ); ?></h4>
                            <?php foreach ( $districts as $district => $items ) : ?>
                                <div class="estate-office-offers-district">
                                    <?php if ( $district ) : ?>
                                        <h5><?php echo esc_html( $district ); ?></h5>
                                    <?php endif; ?>
                                    <div class="estate-office-offers-grid">
                                        <?php foreach ( $items as $item ) : ?>
                                            <article class="estate-office-offer-card">
                                                <header>
                                                    <span class="estate-office-offer-number"><?php echo esc_html( sprintf( '#%05d', $item['id'] ) ); ?></span>
                                                    <?php if ( ! empty( $item['tags'] ) ) : ?>
                                                        <ul class="estate-office-offer-tags">
                                                            <?php foreach ( $item['tags'] as $tag ) : ?>
                                                                <li><?php echo esc_html( $tag ); ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </header>
                                                <p class="estate-office-offer-address"><?php echo esc_html( $item['address'] ); ?></p>
                                                <p class="estate-office-offer-price"><?php echo esc_html( $this->format_currency( $item['price'] ) ); ?></p>
                                                <ul class="estate-office-offer-meta">
                                                    <?php if ( $item['area'] ) : ?>
                                                        <li><?php echo esc_html( $item['area'] . ' m²' ); ?></li>
                                                    <?php endif; ?>
                                                    <?php if ( $item['rooms'] ) : ?>
                                                        <li><?php echo esc_html( sprintf( _n( '%d pokój', '%d pokoje', (int) $item['rooms'], 'estate-office' ), (int) $item['rooms'] ) ); ?></li>
                                                    <?php endif; ?>
                                                    <?php if ( $item['price_m2'] ) : ?>
                                                        <li><?php echo esc_html( sprintf( __( '%s / m²', 'estate-office' ), $this->format_currency( $item['price_m2'] ) ) ); ?></li>
                                                    <?php endif; ?>
                                                </ul>
                                                <a class="estate-office-button secondary" href="<?php echo esc_url( add_query_arg( [ 'eo_tab' => 'properties', 'eo_property' => (int) $item['id'] ], $this->get_crm_page_url() ) ); ?>"><?php esc_html_e( 'Szczegóły w CRM', 'estate-office' ); ?></a>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
        <section class="estate-office-offers-calculators" aria-label="<?php esc_attr_e( 'Kalkulatory dla kupujących', 'estate-office' ); ?>">
            <h2><?php esc_html_e( 'Kalkulatory dla kupujących', 'estate-office' ); ?></h2>
            <div class="estate-office-offers-calculators-grid">
                <?php echo $this->get_notary_calculator_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo $this->get_mortgage_calculator_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <?php
            $notary_link   = $this->get_calculator_page_link( 'estate_office_notary_page_id' );
            $mortgage_link = $this->get_calculator_page_link( 'estate_office_mortgage_page_id' );
            if ( $notary_link || $mortgage_link ) :
                ?>
                <p class="estate-office-offers-calculators-links">
                    <?php if ( $notary_link ) : ?>
                        <a class="estate-office-button tertiary" href="<?php echo esc_url( $notary_link ); ?>"><?php esc_html_e( 'Pełny kalkulator notarialny', 'estate-office' ); ?></a>
                    <?php endif; ?>
                    <?php if ( $mortgage_link ) : ?>
                        <a class="estate-office-button tertiary" href="<?php echo esc_url( $mortgage_link ); ?>"><?php esc_html_e( 'Pełny kalkulator kredytowy', 'estate-office' ); ?></a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Render notary calculator shortcode.
     */
    public function render_notary_calculator_shortcode( array $atts ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        $this->enqueue_assets();
        return $this->get_notary_calculator_markup();
    }

    /**
     * Render mortgage calculator shortcode.
     */
    public function render_mortgage_calculator_shortcode( array $atts ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        $this->enqueue_assets();
        return $this->get_mortgage_calculator_markup();
    }

    /**
     * Fetch publicly available offers exported to the website.
     *
     * @param string $transaction Transaction type.
     * @return array<int,array<string,mixed>>
     */
    protected function get_public_offers( string $transaction ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'eo_properties';

        $sql = $wpdb->prepare(
            "SELECT id, property_type, address, details, tags FROM {$table} WHERE export_www = 1 AND transaction_type = %s ORDER BY created_at DESC",
            $transaction
        );

        $rows = $wpdb->get_results( $sql );
        if ( empty( $rows ) ) {
            return [];
        }

        $offers = [];
        foreach ( $rows as $row ) {
            $details = $row->details ? json_decode( $row->details, true ) : [];
            $address = $row->address ? json_decode( $row->address, true ) : [];
            $tags    = $row->tags ? json_decode( $row->tags, true ) : [];

            $offers[] = [
                'id'            => (int) $row->id,
                'property_type' => $row->property_type,
                'city'          => $address['city'] ?? __( 'Nieznane miasto', 'estate-office' ),
                'district'      => $address['district'] ?? '',
                'address'       => $this->format_address( $address ),
                'price'         => $details['price'] ?? '',
                'price_m2'      => $details['price_m2'] ?? '',
                'area'          => $details['area'] ?? '',
                'rooms'         => $details['rooms'] ?? '',
                'tags'          => $this->map_offer_tags( $tags ),
            ];
        }

        return $offers;
    }

    /**
     * Provide mapping for offer tags to display strings.
     *
     * @param array<string,mixed> $tags Raw tags map.
     * @return array<int,string>
     */
    protected function map_offer_tags( array $tags ): array {
        $map = [
            'new_offer' => __( 'Nowa oferta', 'estate-office' ),
            'exclusive' => __( 'Wyłączność', 'estate-office' ),
            'new_price' => __( 'Nowa cena', 'estate-office' ),
            'premium'   => __( 'Premium', 'estate-office' ),
        ];

        $output = [];
        foreach ( $tags as $key => $value ) {
            if ( ! isset( $map[ $key ] ) || ! $this->is_tag_active( $value ) ) {
                continue;
            }
            $output[] = $map[ $key ];
        }
        return $output;
    }

    /**
     * Determine whether a tag should be treated as active.
     *
     * @param mixed $value Stored tag value.
     * @return bool
     */
    protected function is_tag_active( $value ): bool {
        if ( is_array( $value ) ) {
            if ( array_key_exists( 'active', $value ) ) {
                return (bool) $value['active'];
            }

            return ! empty( $value );
        }

        return ! empty( $value );
    }

    /**
     * Gather metrics for the CRM dashboard.
     *
     * @return array<string,mixed>
     */
    protected function get_dashboard_metrics(): array {
        global $wpdb;
        $metrics = [
            'properties'       => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'eo_properties' ),
            'contracts'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}eo_contracts WHERE indefinite = 1 OR end_date >= CURDATE()" ),
            'searches'         => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'eo_searches' ),
            'clients'          => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'eo_clients' ),
            'recent_contracts' => $wpdb->get_results( 'SELECT id, contract_number, start_date FROM ' . $wpdb->prefix . 'eo_contracts ORDER BY created_at DESC LIMIT 5' ),
            'top_agents'       => $this->get_top_agents(),
        ];

        return $metrics;
    }

    /**
     * Determine agents ranked by the number of contracts in their stage history.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function get_top_agents(): array {
        global $wpdb;
        $contracts_table = $wpdb->prefix . 'eo_contracts';
        $agents_table    = $wpdb->prefix . 'eo_agents';

        $sql = "SELECT a.id, a.first_name, a.last_name, COUNT(c.id) AS contracts
                FROM {$agents_table} a
                LEFT JOIN {$contracts_table} c ON c.agent_id = a.id
                GROUP BY a.id
                HAVING contracts > 0
                ORDER BY contracts DESC, a.last_name ASC, a.first_name ASC
                LIMIT 5";

        $rows = $wpdb->get_results( $sql );
        if ( empty( $rows ) ) {
            $rows = $wpdb->get_results( "SELECT id, first_name, last_name, 0 AS contracts FROM {$agents_table} ORDER BY created_at DESC LIMIT 5" );
        }

        $agents = [];
        foreach ( $rows as $row ) {
            $name = trim( $row->first_name . ' ' . $row->last_name );
            if ( '' === $name ) {
                $name = __( 'Agent bez danych', 'estate-office' );
            }
            $agents[] = [
                'id'        => (int) $row->id,
                'name'      => $name,
                'contracts' => (int) $row->contracts,
            ];
        }

        return $agents;
    }

    /**
     * Fetch contracts linked to a specific client.
     *
     * @param int $client_id Client identifier.
     * @return array<int,object>
     */
    protected function get_contracts_for_client( int $client_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'eo_contract_clients';
        $contracts_table = $wpdb->prefix . 'eo_contracts';

        $sql = $wpdb->prepare(
            "SELECT c.* FROM {$contracts_table} c INNER JOIN {$table} cc ON cc.contract_id = c.id WHERE cc.client_id = %d",
            $client_id
        );

        return $wpdb->get_results( $sql );
    }

    /**
     * Build links to properties/searches associated with a client.
     *
     * @param int $client_id Client identifier.
     * @return array<int,array<string,string>>
     */
    protected function get_properties_for_client( int $client_id ): array {
        global $wpdb;
        $contract_table  = $wpdb->prefix . 'eo_contract_clients';
        $properties_table = $wpdb->prefix . 'eo_properties';
        $search_table     = $wpdb->prefix . 'eo_searches';

        $sql_properties = $wpdb->prepare(
            "SELECT p.id FROM {$properties_table} p INNER JOIN {$contract_table} cc ON cc.contract_id = p.contract_id WHERE cc.client_id = %d",
            $client_id
        );
        $sql_searches = $wpdb->prepare(
            "SELECT s.id FROM {$search_table} s INNER JOIN {$contract_table} cc ON cc.contract_id = s.contract_id WHERE cc.client_id = %d",
            $client_id
        );

        $links = [];
        foreach ( $wpdb->get_results( $sql_properties ) as $row ) {
            $links[] = [
                'url'   => add_query_arg( [ 'eo_tab' => 'properties', 'eo_property' => (int) $row->id ], $this->get_current_url() ),
                'label' => sprintf( __( 'Nieruchomość #%1$05d', 'estate-office' ), (int) $row->id ),
            ];
        }
        foreach ( $wpdb->get_results( $sql_searches ) as $row ) {
            $links[] = [
                'url'   => add_query_arg( [ 'eo_tab' => 'searches', 'eo_search_id' => (int) $row->id ], $this->get_current_url() ),
                'label' => sprintf( __( 'Poszukiwanie #%1$05d', 'estate-office' ), (int) $row->id ),
            ];
        }

        return $links;
    }

    /**
     * Format monetary value.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    protected function format_currency( $value ): string {
        if ( '' === $value || null === $value ) {
            return '';
        }
        return number_format_i18n( (float) $value, 2 ) . ' PLN';
    }

    /**
     * Format a friendly range.
     *
     * @param mixed  $min Minimum value.
     * @param mixed  $max Maximum value.
     * @param string $suffix Optional suffix.
     * @return string
     */
    protected function format_range( $min, $max, string $suffix = '' ): string {
        $min = '' !== $min ? $min : '';
        $max = '' !== $max ? $max : '';
        if ( '' === $min && '' === $max ) {
            return '';
        }
        if ( '' === $min ) {
            return sprintf( __( 'do %1$s%2$s', 'estate-office' ), $max, $suffix );
        }
        if ( '' === $max ) {
            return sprintf( __( 'od %1$s%2$s', 'estate-office' ), $min, $suffix );
        }
        return sprintf( __( '%1$s–%2$s%3$s', 'estate-office' ), $min, $max, $suffix );
    }

    /**
     * Format a date string to locale aware output.
     *
     * @param string|null $date Date string.
     * @return string
     */
    protected function format_date( ?string $date ): string {
        if ( empty( $date ) || '0000-00-00' === $date ) {
            return '';
        }
        return date_i18n( get_option( 'date_format' ), strtotime( $date ) );
    }

    /**
     * Format contract commission information.
     *
     * @param object $contract Contract row.
     * @return string
     */
    protected function format_commission( $contract ): string {
        if ( null === $contract->commission_amount || '' === $contract->commission_amount ) {
            return '';
        }
        $amount = $contract->commission_amount;
        if ( '%' === $contract->commission_unit ) {
            return sprintf( '%s%%', $amount );
        }
        $currencies = [ 'PLN', 'EUR', 'USD' ];
        if ( in_array( $contract->commission_unit, $currencies, true ) ) {
            return number_format_i18n( (float) $amount, 2 ) . ' ' . $contract->commission_unit;
        }
        return $this->format_currency( $amount );
    }

    /**
     * Convert stage key into label.
     *
     * @param string $stage Stage identifier.
     * @return string
     */
    protected function format_stage( string $stage ): string {
        $stages = EstateOffice_Admin_Contracts::get_stages();
        return $stages[ $stage ] ?? $stage;
    }

    /**
     * Build address string from associative array.
     *
     * @param array<string,mixed> $address Address parts.
     * @return string
     */
    protected function format_address( array $address ): string {
        if ( empty( $address ) ) {
            return '';
        }
        $parts = [];
        if ( ! empty( $address['street'] ) ) {
            $street = $address['street'];
            if ( ! empty( $address['number'] ) ) {
                $street .= ' ' . $address['number'];
            }
            if ( ! empty( $address['unit'] ) ) {
                $street .= '/' . $address['unit'];
            }
            $parts[] = $street;
        }
        if ( ! empty( $address['postal_code'] ) ) {
            $parts[] = $address['postal_code'];
        }
        if ( ! empty( $address['district'] ) ) {
            $parts[] = $address['district'];
        }
        if ( ! empty( $address['city'] ) ) {
            $parts[] = $address['city'];
        }
        if ( ! empty( $address['country'] ) ) {
            $parts[] = $address['country'];
        }
        return implode( ', ', $parts );
    }

    /**
     * Format address stored as JSON.
     *
     * @param string $json JSON string.
     * @return string
     */
    protected function format_address_from_json( string $json ): string {
        if ( empty( $json ) ) {
            return '';
        }
        $address = json_decode( $json, true );
        if ( ! is_array( $address ) ) {
            return '';
        }
        return $this->format_address( $address );
    }

    /**
     * Format client name from DB row.
     *
     * @param object $client Client row.
     * @return string
     */
    protected function format_client_name( $client ): string {
        if ( 'company' === $client->client_type ) {
            return $client->company_name ?: __( 'Firma', 'estate-office' );
        }
        return trim( $client->first_name . ' ' . $client->last_name );
    }

    /**
     * Determine current URL without pagination parameters.
     *
     * @return string
     */
    protected function get_current_url(): string {
        global $wp;
        $request = home_url();
        if ( isset( $wp->request ) ) {
            $request = home_url( add_query_arg( [], $wp->request ) );
        }
        if ( is_singular() ) {
            $permalink = get_permalink();
            if ( $permalink ) {
                $request = $permalink;
            }
        }
        return $request;
    }

    /**
     * Retrieve CRM page URL if available.
     *
     * @return string
     */
    protected function get_crm_page_url(): string {
        $page_id = (int) get_option( 'estate_office_crm_page_id' );
        if ( $page_id ) {
            $link = get_permalink( $page_id );
            if ( $link ) {
                return $link;
            }
        }
        return home_url();
    }

    /**
     * Retrieve calculator page permalink if registered.
     *
     * @param string $option Option name with stored page ID.
     * @return string|null
     */
    protected function get_calculator_page_link( string $option ): ?string {
        $page_id = (int) get_option( $option );
        if ( $page_id ) {
            $link = get_permalink( $page_id );
            if ( $link ) {
                return $link;
            }
        }
        return null;
    }

    /**
     * Build markup for the notary calculator widget.
     */
    protected function get_notary_calculator_markup(): string {
        static $instance = 0;
        $instance++;
        $suffix = (string) $instance;

        ob_start();
        ?>
        <section class="estate-office-calculator estate-office-calculator-notary" aria-labelledby="estate-office-notary-heading-<?php echo esc_attr( $suffix ); ?>">
            <h3 id="estate-office-notary-heading-<?php echo esc_attr( $suffix ); ?>"><?php esc_html_e( 'Kalkulator notarialny', 'estate-office' ); ?></h3>
            <form class="estate-office-calculator-form" data-calculator="notary">
                <div class="estate-office-calculator-grid">
                    <p class="full">
                        <label for="eo-notary-price-<?php echo esc_attr( $suffix ); ?>"><?php esc_html_e( 'Wartość nieruchomości (PLN)', 'estate-office' ); ?></label>
                        <input type="number" id="eo-notary-price-<?php echo esc_attr( $suffix ); ?>" name="price" min="0" step="1000" value="500000" />
                    </p>
                    <p>
                        <label for="eo-notary-market-<?php echo esc_attr( $suffix ); ?>"><?php esc_html_e( 'Rynek', 'estate-office' ); ?></label>
                        <select id="eo-notary-market-<?php echo esc_attr( $suffix ); ?>" name="market">
                            <option value="primary"><?php esc_html_e( 'Pierwotny (PCC 0%)', 'estate-office' ); ?></option>
                            <option value="secondary"><?php esc_html_e( 'Wtórny (PCC 2%)', 'estate-office' ); ?></option>
                        </select>
                    </p>
                    <p>
                        <label for="eo-notary-mortgage-<?php echo esc_attr( $suffix ); ?>"><?php esc_html_e( 'Kwota kredytu (PLN)', 'estate-office' ); ?></label>
                        <input type="number" id="eo-notary-mortgage-<?php echo esc_attr( $suffix ); ?>" name="mortgage" min="0" step="1000" value="0" />
                    </p>
                    <p>
                        <label for="eo-notary-copies-<?php echo esc_attr( $suffix ); ?>"><?php esc_html_e( 'Liczba wypisów aktu', 'estate-office' ); ?></label>
                        <input type="number" id="eo-notary-copies-<?php echo esc_attr( $suffix ); ?>" name="copies" min="1" step="1" value="2" />
                        <span class="description"><?php esc_html_e( 'Każdy wypis to 6 PLN.', 'estate-office' ); ?></span>
                    </p>
                    <p class="full checkbox-row">
                        <label><input type="checkbox" name="registry" value="1" checked /> <?php esc_html_e( 'Wpis do księgi wieczystej (200 PLN)', 'estate-office' ); ?></label>
                    </p>
                    <p class="full checkbox-row">
                        <label><input type="checkbox" name="hypothec" value="1" checked /> <?php esc_html_e( 'Ustanowienie hipoteki (200 PLN)', 'estate-office' ); ?></label>
                    </p>
                </div>
            </form>
            <div class="estate-office-calculator-results" aria-live="polite">
                <dl>
                    <div><dt><?php esc_html_e( 'Taksa notarialna (netto)', 'estate-office' ); ?></dt><dd><span data-eo-result="notary-fee-net">0,00 PLN</span></dd></div>
                    <div><dt><?php esc_html_e( 'VAT (23%)', 'estate-office' ); ?></dt><dd><span data-eo-result="notary-fee-vat">0,00 PLN</span></dd></div>
                    <div><dt><?php esc_html_e( 'Taksa notarialna (brutto)', 'estate-office' ); ?></dt><dd><span data-eo-result="notary-fee-total">0,00 PLN</span></dd></div>
                    <div><dt><?php esc_html_e( 'Podatek PCC', 'estate-office' ); ?></dt><dd><span data-eo-result="notary-pcc">0,00 PLN</span></dd></div>
                    <div><dt><?php esc_html_e( 'Wpis do księgi', 'estate-office' ); ?></dt><dd><span data-eo-result="notary-registry">0,00 PLN</span></dd></div>
                    <div><dt><?php esc_html_e( 'Hipoteka', 'estate-office' ); ?></dt><dd><span data-eo-result="notary-mortgage">0,00 PLN</span></dd></div>
                    <div><dt><?php esc_html_e( 'Wypisy aktu', 'estate-office' ); ?></dt><dd><span data-eo-result="notary-copies">0,00 PLN</span></dd></div>
                    <div><dt><?php esc_html_e( 'Łączny koszt', 'estate-office' ); ?></dt><dd><span data-eo-result="notary-total">0,00 PLN</span></dd></div>
                </dl>
                <p class="estate-office-calculator-hint"><?php esc_html_e( 'Wyliczenia bazują na maksymalnych stawkach i mają charakter informacyjny.', 'estate-office' ); ?></p>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Build markup for the mortgage calculator widget.
     */
    protected function get_mortgage_calculator_markup(): string {
        static $instance = 0;
        $instance++;
        $suffix = (string) $instance;

        ob_start();
        ?>
        <section class="estate-office-calculator estate-office-calculator-mortgage" aria-labelledby="estate-office-mortgage-heading-<?php echo esc_attr( $suffix ); ?>">
            <h3 id="estate-office-mortgage-heading-<?php echo esc_attr( $suffix ); ?>"><?php esc_html_e( 'Kalkulator kredytowy', 'estate-office' ); ?></h3>
            <form class="estate-office-calculator-form" data-calculator="mortgage">
                <div class="estate-office-calculator-grid">
                    <p>
                        <label for="eo-mortgage-price-<?php echo esc_attr( $suffix ); ?>"><?php esc_html_e( 'Wartość nieruchomości (PLN)', 'estate-office' ); ?></label>
                        <input type="number" id="eo-mortgage-price-<?php echo esc_attr( $suffix ); ?>" name="price" min="0" step="1000" value="600000" />
                    </p>
                    <p>
                        <label for="eo-mortgage-down-<?php echo esc_attr( $suffix ); ?>"><?php esc_html_e( 'Wkład własny (PLN)', 'estate-office' ); ?></label>
                        <input type="number" id="eo-mortgage-down-<?php echo esc_attr( $suffix ); ?>" name="down_payment" min="0" step="1000" value="120000" />
                    </p>
                    <p>
                        <label for="eo-mortgage-amount-<?php echo esc_attr( $suffix ); ?>"><?php esc_html_e( 'Kwota kredytu (PLN)', 'estate-office' ); ?></label>
                        <input type="number" id="eo-mortgage-amount-<?php echo esc_attr( $suffix ); ?>" name="loan" min="0" step="1000" value="" placeholder="<?php esc_attr_e( 'Oblicz automatycznie', 'estate-office' ); ?>" />
                        <span class="description"><?php esc_html_e( 'Pozostaw puste, aby wyliczyć kwotę na podstawie ceny i wkładu własnego.', 'estate-office' ); ?></span>
                    </p>
                    <p>
                        <label for="eo-mortgage-interest-<?php echo esc_attr( $suffix ); ?>"><?php esc_html_e( 'Oprocentowanie roczne (%)', 'estate-office' ); ?></label>
                        <input type="number" id="eo-mortgage-interest-<?php echo esc_attr( $suffix ); ?>" name="interest" min="0" step="0.01" value="7" />
                    </p>
                    <p>
                        <label for="eo-mortgage-years-<?php echo esc_attr( $suffix ); ?>"><?php esc_html_e( 'Okres spłaty (lata)', 'estate-office' ); ?></label>
                        <input type="number" id="eo-mortgage-years-<?php echo esc_attr( $suffix ); ?>" name="years" min="1" max="35" step="1" value="25" />
                    </p>
                    <p>
                        <label for="eo-mortgage-commission-<?php echo esc_attr( $suffix ); ?>"><?php esc_html_e( 'Prowizja banku (%)', 'estate-office' ); ?></label>
                        <input type="number" id="eo-mortgage-commission-<?php echo esc_attr( $suffix ); ?>" name="commission" min="0" step="0.1" value="0" />
                    </p>
                    <p class="full">
                        <label for="eo-mortgage-type-<?php echo esc_attr( $suffix ); ?>"><?php esc_html_e( 'Rodzaj rat', 'estate-office' ); ?></label>
                        <select id="eo-mortgage-type-<?php echo esc_attr( $suffix ); ?>" name="installment_type">
                            <option value="annuity"><?php esc_html_e( 'Raty równe', 'estate-office' ); ?></option>
                            <option value="decreasing"><?php esc_html_e( 'Raty malejące', 'estate-office' ); ?></option>
                        </select>
                    </p>
                </div>
            </form>
            <div class="estate-office-calculator-results" aria-live="polite">
                <dl>
                    <div><dt><?php esc_html_e( 'Kwota kredytu', 'estate-office' ); ?></dt><dd><span data-eo-result="mortgage-loan">0,00 PLN</span></dd></div>
                    <div><dt><?php esc_html_e( 'Miesięczna rata', 'estate-office' ); ?></dt><dd><span data-eo-result="mortgage-monthly">0,00 PLN</span></dd></div>
                    <div><dt><?php esc_html_e( 'Prowizja banku', 'estate-office' ); ?></dt><dd><span data-eo-result="mortgage-commission">0,00 PLN</span></dd></div>
                    <div><dt><?php esc_html_e( 'Łączne odsetki', 'estate-office' ); ?></dt><dd><span data-eo-result="mortgage-interest">0,00 PLN</span></dd></div>
                    <div><dt><?php esc_html_e( 'Do spłaty łącznie', 'estate-office' ); ?></dt><dd><span data-eo-result="mortgage-total">0,00 PLN</span></dd></div>
                    <div><dt><?php esc_html_e( 'Minimalny dochód netto (40% DTI)', 'estate-office' ); ?></dt><dd><span data-eo-result="mortgage-income">0,00 PLN</span></dd></div>
                    <div><dt><?php esc_html_e( 'Wkład własny', 'estate-office' ); ?></dt><dd><span data-eo-result="mortgage-down-percent">0%</span></dd></div>
                </dl>
                <p class="estate-office-calculator-hint"><?php esc_html_e( 'Wyniki są orientacyjne i nie stanowią oferty banku.', 'estate-office' ); ?></p>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue shared public assets.
     */
    protected function enqueue_assets(): void {
        if ( ! wp_style_is( 'estate-office-public', 'enqueued' ) ) {
            wp_enqueue_style( 'estate-office-public', ESTATE_OFFICE_URL . 'assets/css/public.css', [], ESTATE_OFFICE_VERSION );
        }
        if ( ! wp_script_is( 'estate-office-public', 'enqueued' ) ) {
            wp_enqueue_script( 'estate-office-public', ESTATE_OFFICE_URL . 'assets/js/public.js', [ 'jquery' ], ESTATE_OFFICE_VERSION, true );
        }
    }
}
