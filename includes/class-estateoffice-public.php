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
     * Track whether the Google Maps script for offers was enqueued.
     *
     * @var bool
     */
    protected $offer_map_script_enqueued = false;

    /**
     * Register WordPress hooks for the public module.
     */
    public function hooks(): void {
        add_action( 'init', [ $this, 'register_offer_taxonomy' ], 5 );
        add_action( 'init', [ $this, 'register_rewrite_rules' ] );
        add_shortcode( 'estate_office_crm', [ $this, 'render_crm_shortcode' ] );
        add_shortcode( 'estate_office_offers', [ $this, 'render_offers_shortcode' ] );
        add_shortcode( 'estate_office_offer', [ $this, 'render_offer_shortcode' ] );
        add_shortcode( 'estate_office_notary_calculator', [ $this, 'render_notary_calculator_shortcode' ] );
        add_shortcode( 'estate_office_mortgage_calculator', [ $this, 'render_mortgage_calculator_shortcode' ] );
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_links' ], 100 );
        add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
        add_filter( 'template_include', [ $this, 'template_loader' ] );
        add_filter( 'body_class', [ $this, 'add_agent_body_class' ] );
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
     * Register hierarchical taxonomy used by exported offers.
     */
    public function register_offer_taxonomy(): void {
        $labels = [
            'name'              => __( 'Kategorie ofert', 'estate-office' ),
            'singular_name'     => __( 'Kategoria ofert', 'estate-office' ),
            'search_items'      => __( 'Szukaj kategorii ofert', 'estate-office' ),
            'all_items'         => __( 'Wszystkie kategorie ofert', 'estate-office' ),
            'parent_item'       => __( 'Kategoria nadrzędna', 'estate-office' ),
            'parent_item_colon' => __( 'Kategoria nadrzędna:', 'estate-office' ),
            'edit_item'         => __( 'Edytuj kategorię', 'estate-office' ),
            'update_item'       => __( 'Zaktualizuj kategorię', 'estate-office' ),
            'add_new_item'      => __( 'Dodaj nową kategorię', 'estate-office' ),
            'new_item_name'     => __( 'Nazwa nowej kategorii', 'estate-office' ),
            'menu_name'         => __( 'Kategorie ofert', 'estate-office' ),
        ];

        register_taxonomy(
            'estate_office_offer_category',
            'page',
            [
                'labels'            => $labels,
                'public'            => false,
                'hierarchical'      => true,
                'show_ui'           => true,
                'show_admin_column' => true,
                'show_in_quick_edit'=> false,
                'show_in_nav_menus' => false,
                'show_in_rest'      => false,
                'rewrite'           => false,
            ]
        );
    }

    /**
     * Register rewrite rules for public agent profiles.
     */
    public function register_rewrite_rules(): void {
        $base = estate_office_get_agent_base_slug();
        add_rewrite_rule( $base . '/([^/]+)/?$', 'index.php?estate_office_agent=$matches[1]', 'top' );
    }

    /**
     * Expose plugin query vars.
     */
    public function register_query_vars( array $vars ): array {
        $vars[] = 'estate_office_agent';
        return $vars;
    }

    /**
     * Provide template for agent profile pages.
     */
    public function template_loader( string $template ): string {
        $slug = get_query_var( 'estate_office_agent' );
        if ( ! $slug ) {
            return $template;
        }

        $agent = EstateOffice_Admin_Agents::get_agent_by_slug( $slug );
        if ( ! $agent ) {
            global $wp_query;
            if ( $wp_query ) {
                $wp_query->set_404();
            }
            status_header( 404 );
            $fallback = get_404_template();
            return $fallback ?: $template;
        }

        $this->enqueue_assets();

        $GLOBALS['estate_office_agent_profile'] = [
            'agent'     => $agent,
            'relations' => EstateOffice_Admin_Agents::get_agent_relations( (int) $agent->id ),
        ];

        global $wp_query;
        if ( $wp_query ) {
            $wp_query->is_page     = true;
            $wp_query->is_singular = true;
            $wp_query->is_404      = false;
        }

        add_filter(
            'pre_get_document_title',
            static function () use ( $agent ) {
                $name = EstateOffice_Admin_Agents::format_agent_name( $agent );
                if ( '' === $name ) {
                    $name = __( 'Agent EstateOffice', 'estate-office' );
                }
                return sprintf( __( '%s – EstateOffice', 'estate-office' ), $name );
            },
            20
        );

        return $this->locate_template( 'agent-profile.php' );
    }

    /**
     * Append contextual class to body tag.
     */
    public function add_agent_body_class( array $classes ): array {
        if ( get_query_var( 'estate_office_agent' ) ) {
            $classes[] = 'estate-office-agent-page';
        }

        return $classes;
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
                <div class="estate-office-crm-heading">
                    <?php echo estate_office_get_brand_badge_html( 'public' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <h2><?php esc_html_e( 'EstateOffice CRM', 'estate-office' ); ?></h2>
                </div>
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
                        <?php foreach ( $metrics['top_agents'] as $agent ) :
                            $agent_name = esc_html( $agent['name'] );
                            ?>
                            <li>
                                <span class="estate-office-list-primary">
                                    <?php if ( ! empty( $agent['url'] ) ) : ?>
                                        <a href="<?php echo esc_url( $agent['url'] ); ?>"><?php echo $agent_name; ?></a>
                                    <?php else : ?>
                                        <?php echo $agent_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <?php endif; ?>
                                </span>
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
                                <td><?php echo $this->render_agent_reference( $property ); ?></td>
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
                        ?>
                        <div><dt><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></dt><dd><?php echo $this->render_agent_reference_from_agent( $agent ); ?></dd></div>
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
                                <td><?php echo $this->render_agent_reference( $item ); ?></td>
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
                        ?>
                        <div><dt><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></dt><dd><?php echo $this->render_agent_reference_from_agent( $agent ); ?></dd></div>
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
                                <td><?php echo $this->render_agent_reference( $contract ); ?></td>
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
                        ?>
                        <div><dt><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></dt><dd><?php echo $this->render_agent_reference_from_agent( $agent ); ?></dd></div>
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
                                <td><?php echo $this->render_agent_reference( $client ); ?></td>
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
                        ?>
                        <div><dt><?php esc_html_e( 'Opiekun', 'estate-office' ); ?></dt><dd><?php echo $this->render_agent_reference_from_agent( $agent ); ?></dd></div>
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

        $transaction_label = estate_office_get_transaction_label( $transaction );

        $grouped = [];
        foreach ( $properties as $property ) {
            $grouped[ $property['property_type'] ][ $property['city'] ][ $property['district'] ][] = $property;
        }

        ob_start();
        ?>
        <div class="estate-office-offers" data-transaction="<?php echo esc_attr( $transaction ); ?>">
            <div class="estate-office-offers-header">
                <?php echo estate_office_get_brand_badge_html( 'public' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <h2><?php printf( esc_html__( 'Oferty – %s', 'estate-office' ), esc_html( $transaction_label ) ); ?></h2>
            </div>
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
                                                <?php if ( ! empty( $item['image_url'] ) ) : ?>
                                                    <figure class="estate-office-offer-image">
                                                        <img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="<?php echo esc_attr( $item['image_alt'] ); ?>" loading="lazy" />
                                                    </figure>
                                                <?php endif; ?>
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
                                                <?php
                                                $crm_link  = add_query_arg(
                                                    [
                                                        'eo_tab'      => 'properties',
                                                        'eo_property' => (int) $item['id'],
                                                    ],
                                                    $this->get_crm_page_url()
                                                );
                                                $target_url   = ! empty( $item['page_url'] ) ? $item['page_url'] : $crm_link;
                                                $button_label = ! empty( $item['page_url'] )
                                                    ? __( 'Zobacz ofertę', 'estate-office' )
                                                    : __( 'Szczegóły w CRM', 'estate-office' );
                                                ?>
                                                <a class="estate-office-button secondary" href="<?php echo esc_url( $target_url ); ?>"><?php echo esc_html( $button_label ); ?></a>
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
            "SELECT id, property_type, address, details, tags, export_page_id FROM {$table} WHERE export_www = 1 AND transaction_type = %s ORDER BY created_at DESC",
            $transaction
        );

        $rows = $wpdb->get_results( $sql );
        if ( empty( $rows ) ) {
            return [];
        }

        $property_ids = array_map(
            static function ( $row ) {
                return (int) $row->id;
            },
            $rows
        );

        $cover_media = EstateOffice_Admin_Properties::get_cover_media_for_properties( $property_ids );

        $offers = [];
        foreach ( $rows as $row ) {
            $details = $row->details ? json_decode( $row->details, true ) : [];
            $address = $row->address ? json_decode( $row->address, true ) : [];
            $tags    = $row->tags ? json_decode( $row->tags, true ) : [];

            $media      = $cover_media[ (int) $row->id ] ?? [];
            $image_id   = isset( $media['watermarked_id'] ) && $media['watermarked_id'] ? (int) $media['watermarked_id'] : (int) ( $media['attachment_id'] ?? 0 );
            $image_url  = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
            $image_alt  = $this->format_address( $address );
            if ( ! $image_alt ) {
                $image_alt = trim( $row->property_type . ' ' . $row->transaction_type );
            }

            $page_id  = (int) ( $row->export_page_id ?? 0 );
            $page_url = $page_id ? get_permalink( $page_id ) : '';

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
                'image_id'      => $image_id,
                'image_url'     => $image_url,
                'image_alt'     => $image_alt,
                'page_url'      => $page_url,
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
        if ( empty( $tags ) ) {
            return [];
        }

        return estate_office_map_offer_tag_labels( $tags );
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

        $sql = "SELECT a.id, a.first_name, a.last_name, a.slug, COUNT(c.id) AS contracts
                FROM {$agents_table} a
                LEFT JOIN {$contracts_table} c
                    ON c.agent_id = a.id
                    AND ( c.indefinite = 1 OR c.end_date >= CURDATE() )
                GROUP BY a.id
                HAVING contracts > 0
                ORDER BY contracts DESC, a.last_name ASC, a.first_name ASC
                LIMIT 5";

        $rows = $wpdb->get_results( $sql );
        if ( empty( $rows ) ) {
            $rows = $wpdb->get_results( "SELECT id, first_name, last_name, slug, 0 AS contracts FROM {$agents_table} ORDER BY created_at DESC LIMIT 5" );
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
                'slug'      => $row->slug ?? '',
                'url'       => estate_office_get_agent_url( $row->slug ?? '' ),
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
     * Locate template file allowing theme overrides.
     */
    protected function locate_template( string $template ): string {
        $paths = [
            trailingslashit( get_stylesheet_directory() ) . 'estate-office/' . $template,
            trailingslashit( get_template_directory() ) . 'estate-office/' . $template,
            ESTATE_OFFICE_PATH . 'templates/' . $template,
        ];

        foreach ( $paths as $path ) {
            if ( file_exists( $path ) ) {
                return $path;
            }
        }

        return $template;
    }

    /**
     * Return formatted agent reference for list rows.
     */
    protected function render_agent_reference( $row ): string {
        $label = EstateOffice_Admin_Agents::format_agent_from_row( $row );
        if ( '' === $label ) {
            return esc_html__( '—', 'estate-office' );
        }

        $slug = $row->agent_slug ?? '';
        if ( $slug ) {
            $url = estate_office_get_agent_url( $slug );
            if ( $url ) {
                return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
            }
        }

        return esc_html( $label );
    }

    /**
     * Return formatted agent reference for loaded agent objects.
     */
    protected function render_agent_reference_from_agent( $agent ): string {
        if ( ! $agent ) {
            return esc_html__( '—', 'estate-office' );
        }

        $label = EstateOffice_Admin_Agents::format_agent_name( $agent );
        if ( '' === $label && ! empty( $agent->id ) ) {
            $label = sprintf( __( 'Agent #%d', 'estate-office' ), (int) $agent->id );
        }

        if ( '' === $label ) {
            return esc_html__( '—', 'estate-office' );
        }

        if ( ! empty( $agent->slug ) ) {
            $url = estate_office_get_agent_url( $agent->slug );
            if ( $url ) {
                return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
            }
        }

        return esc_html( $label );
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
        return estate_office_format_address( $address );
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
        return estate_office_format_address( $address );
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
        return estate_office_get_crm_page_url();
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
    /**
     * Render single offer page content.
     */
    public function render_offer_shortcode( array $atts ): string {
        $atts = shortcode_atts(
            [
                'id' => 0,
            ],
            $atts,
            'estate_office_offer'
        );

        $property_id = absint( $atts['id'] );
        if ( ! $property_id ) {
            return '<div class="estate-office-notice">' . esc_html__( 'Nie znaleziono oferty.', 'estate-office' ) . '</div>';
        }

        $property = EstateOffice_Admin_Properties::get_property( $property_id );
        if ( ! $property || empty( $property->export_www ) ) {
            return '<div class="estate-office-notice">' . esc_html__( 'Oferta jest niedostępna.', 'estate-office' ) . '</div>';
        }

        $this->enqueue_assets();

        $details = $property->details ? json_decode( $property->details, true ) : [];
        $address = $property->address ? json_decode( $property->address, true ) : [];
        $legal   = $property->legal ? json_decode( $property->legal, true ) : [];
        $tags    = $property->tags ? json_decode( $property->tags, true ) : [];

        $tag_labels        = estate_office_map_offer_tag_labels( is_array( $tags ) ? $tags : [] );
        $transaction_label = estate_office_get_transaction_label( (string) $property->transaction_type );
        $title             = $this->build_offer_title( $property, $address );
        $address_text      = $this->format_address( $address );

        $price_label = $this->format_price_for_transaction( $details['price'] ?? '', (string) $property->transaction_type );
        $admin_fee   = $this->format_price_for_transaction( $details['admin_fee'] ?? '', (string) $property->transaction_type );
        $price_m2    = isset( $details['price_m2'] ) && '' !== $details['price_m2'] ? sprintf( __( '%s / m²', 'estate-office' ), $this->format_currency( $details['price_m2'] ) ) : '';
        $area_label  = isset( $details['area'] ) && '' !== $details['area'] ? number_format_i18n( (float) $details['area'], 2 ) . ' m²' : '';

        $basic_info = $this->filter_info_rows(
            [
                [ 'label' => __( 'Cena', 'estate-office' ), 'value' => $price_label ],
                [ 'label' => __( 'Cena za m²', 'estate-office' ), 'value' => $price_m2 ],
                [ 'label' => __( 'Powierzchnia', 'estate-office' ), 'value' => $area_label ],
                [ 'label' => __( 'Czynsz administracyjny', 'estate-office' ), 'value' => $admin_fee ],
                [ 'label' => __( 'Liczba pokoi', 'estate-office' ), 'value' => $this->format_count_label( $details['rooms'] ?? 0, __( '%d pokój', 'estate-office' ), __( '%d pokoje', 'estate-office' ) ) ],
                [ 'label' => __( 'Liczba sypialni', 'estate-office' ), 'value' => $this->format_count_label( $details['bedrooms'] ?? 0, __( '%d sypialnia', 'estate-office' ), __( '%d sypialnie', 'estate-office' ) ) ],
                [ 'label' => __( 'Liczba łazienek', 'estate-office' ), 'value' => $this->format_count_label( $details['bathrooms'] ?? 0, __( '%d łazienka', 'estate-office' ), __( '%d łazienki', 'estate-office' ) ) ],
                [ 'label' => __( 'Liczba toalet', 'estate-office' ), 'value' => $this->format_count_label( $details['toilets'] ?? 0, __( '%d toaleta', 'estate-office' ), __( '%d toalety', 'estate-office' ) ) ],
                [ 'label' => __( 'Piętro', 'estate-office' ), 'value' => isset( $details['floor'] ) && '' !== $details['floor'] ? (string) $details['floor'] : '' ],
                [ 'label' => __( 'Liczba pięter budynku', 'estate-office' ), 'value' => isset( $details['floors'] ) && '' !== $details['floors'] ? (string) $details['floors'] : '' ],
                [ 'label' => __( 'Rok budowy', 'estate-office' ), 'value' => isset( $details['build_year'] ) && '' !== $details['build_year'] ? (string) $details['build_year'] : '' ],
                [ 'label' => __( 'Typ domu', 'estate-office' ), 'value' => $this->format_title_case( (string) ( $details['house_type'] ?? '' ) ) ],
            ]
        );

        $building  = isset( $details['building'] ) && is_array( $details['building'] ) ? $details['building'] : [];
        $utilities = isset( $details['utilities'] ) && is_array( $details['utilities'] ) ? $details['utilities'] : [];
        $amenities = isset( $details['amenities'] ) && is_array( $details['amenities'] ) ? $details['amenities'] : [];
        $equipment = isset( $details['equipment'] ) && is_array( $details['equipment'] ) ? $details['equipment'] : [];
        $surfaces  = isset( $details['surfaces'] ) && is_array( $details['surfaces'] ) ? $details['surfaces'] : [];
        $plot      = isset( $details['plot'] ) && is_array( $details['plot'] ) ? $details['plot'] : [];

        $exposure_map = [
            'north' => __( 'Północ', 'estate-office' ),
            'south' => __( 'Południe', 'estate-office' ),
            'east'  => __( 'Wschód', 'estate-office' ),
            'west'  => __( 'Zachód', 'estate-office' ),
        ];
        $view_map = [
            'panorama' => __( 'Panorama miasta', 'estate-office' ),
            'park'     => __( 'Park / Zieleń', 'estate-office' ),
            'street'   => __( 'Ulica', 'estate-office' ),
            'water'    => __( 'Woda', 'estate-office' ),
        ];
        $layout_map = [
            'separate'    => __( 'Oddzielne pokoje', 'estate-office' ),
            'open'        => __( 'Otwarte przestrzenie', 'estate-office' ),
            'walkthrough' => __( 'Pokoje przechodnie', 'estate-office' ),
        ];
        $parking_map = [
            'rented'     => __( 'Najemne', 'estate-office' ),
            'underground'=> __( 'Podziemne', 'estate-office' ),
            'garage'     => __( 'Garaż wolnostojący / przylegający', 'estate-office' ),
        ];
        $amenity_map = [
            'winda'        => __( 'Winda', 'estate-office' ),
            'klimatyzacja' => __( 'Klimatyzacja', 'estate-office' ),
            'monitoring'   => __( 'Monitoring / Ochrona', 'estate-office' ),
            'recepcja'     => __( 'Recepcja', 'estate-office' ),
            'teren'        => __( 'Teren zamknięty', 'estate-office' ),
            'domofon'      => __( 'Domofon', 'estate-office' ),
        ];
        $equipment_map = [
            'pralka'    => __( 'Pralka', 'estate-office' ),
            'zmywarka'  => __( 'Zmywarka', 'estate-office' ),
            'lodowka'   => __( 'Lodówka', 'estate-office' ),
            'kuchenka'  => __( 'Kuchenka', 'estate-office' ),
            'piekarnik' => __( 'Piekarnik', 'estate-office' ),
            'telewizor' => __( 'Telewizor', 'estate-office' ),
            'mikrofala' => __( 'Mikrofala', 'estate-office' ),
        ];
        $surface_map = [
            'balcony'  => __( 'Balkon', 'estate-office' ),
            'terrace'  => __( 'Taras', 'estate-office' ),
            'basement' => __( 'Piwnica', 'estate-office' ),
            'storage'  => __( 'Komórka lokatorska', 'estate-office' ),
            'garden'   => __( 'Ogródek', 'estate-office' ),
        ];

        $building_info = $this->filter_info_rows(
            [
                [ 'label' => __( 'Stan wykończenia', 'estate-office' ), 'value' => $this->format_title_case( (string) ( $building['finish_state'] ?? '' ) ) ],
                [ 'label' => __( 'Ekspozycja', 'estate-office' ), 'value' => implode( ', ', $this->collect_flag_labels( isset( $building['exposure'] ) && is_array( $building['exposure'] ) ? $building['exposure'] : [], $exposure_map ) ) ],
                [ 'label' => __( 'Widok', 'estate-office' ), 'value' => implode( ', ', $this->collect_flag_labels( isset( $building['view'] ) && is_array( $building['view'] ) ? $building['view'] : [], $view_map ) ) ],
                [ 'label' => __( 'Poddasze', 'estate-office' ), 'value' => ! empty( $building['attic'] ) ? __( 'Tak', 'estate-office' ) : '' ],
                [ 'label' => __( 'Wielopoziomowe', 'estate-office' ), 'value' => ! empty( $building['multilevel'] ) ? __( 'Tak', 'estate-office' ) : '' ],
                [ 'label' => __( 'Rozkład', 'estate-office' ), 'value' => implode( ', ', $this->collect_flag_labels( isset( $building['layout'] ) && is_array( $building['layout'] ) ? $building['layout'] : [], $layout_map ) ) ],
                [ 'label' => __( 'Kuchnia', 'estate-office' ), 'value' => $this->format_title_case( (string) ( $building['kitchen'] ?? '' ) ) ],
                [ 'label' => __( 'Miejsce parkingowe', 'estate-office' ), 'value' => implode( ', ', $this->collect_flag_labels( isset( $building['parking'] ) && is_array( $building['parking'] ) ? $building['parking'] : [], $parking_map ) ) ],
            ]
        );

        $utilities_info = $this->filter_info_rows(
            [
                [ 'label' => __( 'Ogrzewanie', 'estate-office' ), 'value' => $this->format_title_case( (string) ( $utilities['heating'] ?? '' ) ) ],
                [ 'label' => __( 'Woda', 'estate-office' ), 'value' => $this->format_title_case( (string) ( $utilities['water'] ?? '' ) ) ],
                [ 'label' => __( 'Kanalizacja', 'estate-office' ), 'value' => $this->format_title_case( (string) ( $utilities['sewage'] ?? '' ) ) ],
                [ 'label' => __( 'Gaz', 'estate-office' ), 'value' => ! empty( $utilities['gas'] ) ? __( 'Tak', 'estate-office' ) : '' ],
            ]
        );

        $amenity_labels = $this->collect_flag_labels( $amenities, $amenity_map );
        $amenities_info = $this->filter_info_rows(
            [
                [ 'label' => __( 'Udogodnienia', 'estate-office' ), 'value' => implode( ', ', $amenity_labels ) ],
                [ 'label' => __( 'Umeblowanie', 'estate-office' ), 'value' => $this->map_furnishing_label( (string) ( $amenities['umeblowanie'] ?? '' ) ) ],
            ]
        );

        $equipment_labels = $this->collect_flag_labels( $equipment, $equipment_map );
        $surface_info     = $this->format_surface_details( $surfaces, $surface_map );
        $dynamic_fields   = $this->get_dynamic_property_fields( $details );

        $ownership_map = [
            'wlasnosc'      => __( 'Własność', 'estate-office' ),
            'wspolwlasnosc' => __( 'Współwłasność', 'estate-office' ),
            'spoldzielcze'  => __( 'Spółdzielcze własnościowe prawo do lokalu', 'estate-office' ),
            'dzierzawa'     => __( 'Dzierżawa', 'estate-office' ),
            'inne'          => __( 'Inne', 'estate-office' ),
        ];

        $legal_info = $this->filter_info_rows(
            [
                [ 'label' => __( 'Numer księgi wieczystej', 'estate-office' ), 'value' => $legal['land_register'] ?? '' ],
                [ 'label' => __( 'Brak księgi wieczystej', 'estate-office' ), 'value' => ! empty( $legal['no_register'] ) ? __( 'Tak', 'estate-office' ) : '' ],
                [ 'label' => __( 'Stan prawny', 'estate-office' ), 'value' => isset( $legal['ownership'] ) && isset( $ownership_map[ $legal['ownership'] ] ) ? $ownership_map[ $legal['ownership'] ] : '' ],
            ]
        );

        $plot_info = $this->filter_info_rows(
            [
                [ 'label' => __( 'Kształt działki', 'estate-office' ), 'value' => $this->map_plot_shape( (string) ( $plot['shape'] ?? '' ) ) ],
                [ 'label' => __( 'Wymiary działki', 'estate-office' ), 'value' => $this->format_plot_dimensions( $plot ) ],
                [ 'label' => __( 'Opis wymiarów', 'estate-office' ), 'value' => $plot['description'] ?? '' ],
            ]
        );

        $contract      = $property->contract_id ? EstateOffice_Admin_Contracts::get_contract( (int) $property->contract_id ) : null;
        $contract_rows = [];
        $contract_link = '';
        if ( $contract ) {
            if ( ! empty( $contract->contract_number ) ) {
                $contract_rows[] = [ 'label' => __( 'Numer umowy', 'estate-office' ), 'value' => $contract->contract_number ];
            }
            $contract_rows[] = [ 'label' => __( 'Typ transakcji', 'estate-office' ), 'value' => estate_office_get_transaction_label( (string) $contract->transaction_type ) ];
            $contract_rows[] = [ 'label' => __( 'Data zawarcia', 'estate-office' ), 'value' => $this->format_date( $contract->start_date ?? '' ) ];
            $contract_rows[] = [ 'label' => __( 'Data zakończenia', 'estate-office' ), 'value' => $this->format_date( $contract->end_date ?? '' ) ];
            $contract_rows[] = [ 'label' => __( 'Etap', 'estate-office' ), 'value' => $this->format_stage( (string) ( $contract->stage ?? '' ) ) ];
            $commission = $this->format_commission( $contract );
            if ( $commission ) {
                $contract_rows[] = [ 'label' => __( 'Prowizja', 'estate-office' ), 'value' => $commission ];
            }
            $contract_link = add_query_arg(
                [
                    'eo_tab'      => 'contracts',
                    'eo_contract' => (int) $contract->id,
                ],
                $this->get_crm_page_url()
            );
        }

        $agent       = $property->agent_id ? EstateOffice_Admin_Agents::get_agent( (int) $property->agent_id ) : null;
        $agent_photo = $agent && ! empty( $agent->photo_id ) ? (int) $agent->photo_id : 0;
        $agent_url   = ( $agent && ! empty( $agent->slug ) ) ? estate_office_get_agent_url( $agent->slug ) : '';

        $media_rows  = EstateOffice_Admin_Properties::get_property_media( $property_id );
        $gallery     = [];
        $floor_plans = [];
        $media_links = [];
        if ( ! empty( $media_rows ) ) {
            $floor_labels = [ 'floor_2d' => __( 'Rzut 2D', 'estate-office' ), 'floor_3d' => __( 'Rzut 3D', 'estate-office' ) ];
            foreach ( $media_rows as $row ) {
                $type = $row->media_type ?? '';
                if ( 'gallery' === $type ) {
                    $attachment_id = (int) ( $row->watermarked_id ?? 0 );
                    if ( ! $attachment_id ) {
                        $attachment_id = (int) ( $row->attachment_id ?? 0 );
                    }
                    if ( ! $attachment_id ) {
                        continue;
                    }
                    $image_url = wp_get_attachment_image_url( $attachment_id, 'large' );
                    if ( ! $image_url ) {
                        continue;
                    }
                    $gallery[] = [
                        'url'  => $image_url,
                        'full' => wp_get_attachment_image_url( $attachment_id, 'full' ) ?: $image_url,
                        'alt'  => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: ( $address_text ?: $title ),
                    ];
                } elseif ( isset( $floor_labels[ $type ] ) ) {
                    $attachment_id = (int) ( $row->attachment_id ?? 0 );
                    if ( $attachment_id ) {
                        $url = wp_get_attachment_url( $attachment_id );
                        if ( $url ) {
                            $floor_plans[] = [ 'label' => $floor_labels[ $type ], 'url' => $url ];
                        }
                    }
                } elseif ( in_array( $type, [ 'video', 'virtual' ], true ) && ! empty( $row->media_url ) ) {
                    $media_links[ $type ] = esc_url( $row->media_url );
                }
            }
        }

        $map_key    = trim( (string) get_option( 'estate_office_google_maps_api_key', '' ) );
        $has_coords = ! empty( $address['lat'] ) && ! empty( $address['lng'] );
        $show_map   = $map_key && $has_coords;
        if ( $show_map ) {
            $map_data = [
                'lat'   => (float) $address['lat'],
                'lng'   => (float) $address['lng'],
                'title' => $address_text ?: $title,
                'zoom'  => 15,
            ];
            wp_add_inline_script( 'estate-office-public', 'window.estateOfficeOfferMap = ' . wp_json_encode( $map_data ) . ';', 'before' );
            $src = add_query_arg(
                [
                    'key'      => $map_key,
                    'callback' => 'EstateOfficeOfferMapInit',
                ],
                'https://maps.googleapis.com/maps/api/js'
            );
            if ( ! $this->offer_map_script_enqueued ) {
                wp_enqueue_script( 'estate-office-google-maps', $src, [], null, true );
                wp_script_add_data( 'estate-office-google-maps', 'async', true );
                wp_script_add_data( 'estate-office-google-maps', 'defer', true );
                $this->offer_map_script_enqueued = true;
            } else {
                wp_enqueue_script( 'estate-office-google-maps' );
            }
        }

        $notary_link   = $this->get_calculator_page_link( 'estate_office_notary_page_id' );
        $mortgage_link = $this->get_calculator_page_link( 'estate_office_mortgage_page_id' );

        ob_start();
        ?>
        <article class="estate-office-offer-page" data-offer-id="<?php echo esc_attr( $property_id ); ?>">
            <header class="estate-office-offer-hero">
                <div class="estate-office-offer-hero-meta">
                    <p class="estate-office-offer-transaction"><?php echo esc_html( $transaction_label ); ?></p>
                    <h1><?php echo esc_html( $title ); ?></h1>
                    <?php if ( ! empty( $tag_labels ) ) : ?>
                        <ul class="estate-office-offer-tags">
                            <?php foreach ( $tag_labels as $label ) : ?>
                                <li><?php echo esc_html( $label ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ( $address_text ) : ?>
                        <p class="estate-office-offer-address"><?php echo esc_html( $address_text ); ?></p>
                    <?php endif; ?>
                    <?php $this->render_info_list( $basic_info, 'estate-office-offer-highlights' ); ?>
                </div>
            </header>

            <?php if ( ! empty( $gallery ) ) : ?>
                <section class="estate-office-offer-gallery" aria-label="<?php esc_attr_e( 'Galeria nieruchomości', 'estate-office' ); ?>">
                    <div class="estate-office-offer-gallery-grid">
                        <?php foreach ( $gallery as $image ) : ?>
                            <figure>
                                <a href="<?php echo esc_url( $image['full'] ); ?>" target="_blank" rel="noopener noreferrer">
                                    <img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" loading="lazy" />
                                </a>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ( ! empty( $floor_plans ) ) : ?>
                <section class="estate-office-offer-floorplans">
                    <h2><?php esc_html_e( 'Rzuty', 'estate-office' ); ?></h2>
                    <ul>
                        <?php foreach ( $floor_plans as $plan ) : ?>
                            <li><a href="<?php echo esc_url( $plan['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $plan['label'] ); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ( ! empty( $media_links ) ) : ?>
                <section class="estate-office-offer-media">
                    <h2><?php esc_html_e( 'Materiały dodatkowe', 'estate-office' ); ?></h2>
                    <div class="estate-office-offer-media-links">
                        <?php if ( ! empty( $media_links['video'] ) ) : ?>
                            <a class="estate-office-button tertiary" href="<?php echo esc_url( $media_links['video'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Zobacz film', 'estate-office' ); ?></a>
                        <?php endif; ?>
                        <?php if ( ! empty( $media_links['virtual'] ) ) : ?>
                            <a class="estate-office-button tertiary" href="<?php echo esc_url( $media_links['virtual'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Wirtualny spacer', 'estate-office' ); ?></a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ( ! empty( $property->description ) ) : ?>
                <section class="estate-office-offer-description">
                    <h2><?php esc_html_e( 'Opis nieruchomości', 'estate-office' ); ?></h2>
                    <div class="estate-office-offer-description-content"><?php echo wp_kses_post( wpautop( $property->description ) ); ?></div>
                </section>
            <?php endif; ?>

            <?php $this->render_info_section( __( 'Budynek', 'estate-office' ), $building_info ); ?>
            <?php $this->render_info_section( __( 'Media', 'estate-office' ), $utilities_info ); ?>
            <?php $this->render_info_section( __( 'Udogodnienia', 'estate-office' ), $amenities_info ); ?>
            <?php if ( ! empty( $equipment_labels ) ) : ?>
                <section class="estate-office-offer-section">
                    <h2><?php esc_html_e( 'Wyposażenie', 'estate-office' ); ?></h2>
                    <ul class="estate-office-offer-list">
                        <?php foreach ( $equipment_labels as $label ) : ?>
                            <li><?php echo esc_html( $label ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
            <?php $this->render_info_section( __( 'Powierzchnie dodatkowe', 'estate-office' ), $surface_info ); ?>
            <?php $this->render_info_section( __( 'Informacje o działce', 'estate-office' ), $plot_info ); ?>
            <?php $this->render_info_section( __( 'Dodatkowe informacje', 'estate-office' ), $dynamic_fields ); ?>
            <?php $this->render_info_section( __( 'Stan prawny', 'estate-office' ), $legal_info ); ?>

            <?php if ( ! empty( $contract_rows ) ) : ?>
                <section class="estate-office-offer-section">
                    <h2><?php esc_html_e( 'Powiązana umowa', 'estate-office' ); ?></h2>
                    <?php $this->render_info_list( $contract_rows ); ?>
                    <?php if ( $contract_link ) : ?>
                        <p><a class="estate-office-button tertiary" href="<?php echo esc_url( $contract_link ); ?>"><?php esc_html_e( 'Zobacz umowę w CRM', 'estate-office' ); ?></a></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ( $agent ) : ?>
                <section class="estate-office-offer-agent">
                    <h2><?php esc_html_e( 'Kontakt z agentem', 'estate-office' ); ?></h2>
                    <div class="estate-office-offer-agent-card">
                        <?php if ( $agent_photo ) : ?>
                            <div class="estate-office-offer-agent-photo"><?php echo wp_get_attachment_image( $agent_photo, 'medium' ); ?></div>
                        <?php endif; ?>
                        <div class="estate-office-offer-agent-details">
                            <p class="estate-office-offer-agent-name"><?php echo esc_html( EstateOffice_Admin_Agents::format_agent_name( $agent ) ); ?></p>
                            <?php if ( ! empty( $agent->phone ) ) : ?>
                                <p><a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $agent->phone ) ); ?>"><?php echo esc_html( $agent->phone ); ?></a></p>
                            <?php endif; ?>
                            <?php if ( ! empty( $agent->email ) ) : ?>
                                <p><a href="mailto:<?php echo esc_attr( $agent->email ); ?>"><?php echo esc_html( $agent->email ); ?></a></p>
                            <?php endif; ?>
                            <?php if ( $agent_url ) : ?>
                                <p><a class="estate-office-button tertiary" href="<?php echo esc_url( $agent_url ); ?>"><?php esc_html_e( 'Zobacz profil agenta', 'estate-office' ); ?></a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ( $show_map ) : ?>
                <section class="estate-office-offer-map-section">
                    <h2><?php esc_html_e( 'Lokalizacja', 'estate-office' ); ?></h2>
                    <div
                        id="estate-office-offer-map"
                        class="estate-office-offer-map"
                        data-lat="<?php echo esc_attr( $address['lat'] ); ?>"
                        data-lng="<?php echo esc_attr( $address['lng'] ); ?>"
                        data-title="<?php echo esc_attr( $address_text ?: $title ); ?>"
                        data-zoom="<?php echo esc_attr( isset( $map_data['zoom'] ) ? $map_data['zoom'] : 15 ); ?>"
                        aria-label="<?php esc_attr_e( 'Mapa nieruchomości', 'estate-office' ); ?>"
                    ></div>
                </section>
            <?php endif; ?>

            <section class="estate-office-offer-calculators" aria-label="<?php esc_attr_e( 'Kalkulatory dla kupujących', 'estate-office' ); ?>">
                <h2><?php esc_html_e( 'Kalkulatory dla kupujących', 'estate-office' ); ?></h2>
                <div class="estate-office-offer-calculators-grid">
                    <?php echo wp_kses_post( $this->get_notary_calculator_markup() ); ?>
                    <?php echo wp_kses_post( $this->get_mortgage_calculator_markup() ); ?>
                </div>
                <?php if ( $notary_link || $mortgage_link ) : ?>
                    <p class="estate-office-offer-calculators-links">
                        <?php if ( $notary_link ) : ?>
                            <a class="estate-office-button tertiary" href="<?php echo esc_url( $notary_link ); ?>"><?php esc_html_e( 'Pełny kalkulator notarialny', 'estate-office' ); ?></a>
                        <?php endif; ?>
                        <?php if ( $mortgage_link ) : ?>
                            <a class="estate-office-button tertiary" href="<?php echo esc_url( $mortgage_link ); ?>"><?php esc_html_e( 'Pełny kalkulator kredytowy', 'estate-office' ); ?></a>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </section>
        </article>
        <?php
        return ob_get_clean();
    }

    protected function build_offer_title( $property, array $address ): string {
        $parts = [];
        if ( ! empty( $property->property_type ) ) {
            $parts[] = (string) $property->property_type;
        }
        if ( ! empty( $address['city'] ) ) {
            $parts[] = (string) $address['city'];
        }

        $title = implode( ' – ', $parts );
        if ( '' === $title ) {
            $title = sprintf( __( 'Oferta #%d', 'estate-office' ), (int) $property->id );
        }

        return $title;
    }

    protected function format_price_for_transaction( $value, string $transaction ): string {
        if ( '' === $value || null === $value ) {
            return '';
        }

        $formatted = $this->format_currency( $value );
        if ( in_array( strtoupper( $transaction ), [ 'WYNAJEM', 'NAJEM' ], true ) ) {
            return sprintf( __( '%s / miesiąc', 'estate-office' ), $formatted );
        }

        return $formatted;
    }

    protected function format_count_label( $value, string $singular, string $plural ): string {
        $count = (int) $value;
        if ( $count <= 0 ) {
            return '';
        }

        return sprintf( _n( $singular, $plural, $count, 'estate-office' ), $count );
    }

    protected function format_title_case( string $value ): string {
        $value = trim( $value );
        if ( '' === $value ) {
            return '';
        }

        return function_exists( 'mb_convert_case' ) ? mb_convert_case( strtolower( $value ), MB_CASE_TITLE, 'UTF-8' ) : ucwords( strtolower( $value ) );
    }

    protected function collect_flag_labels( array $flags, array $map ): array {
        $output = [];
        foreach ( $map as $key => $label ) {
            if ( ! empty( $flags[ $key ] ) ) {
                $output[] = $label;
            }
        }

        return $output;
    }

    protected function filter_info_rows( array $rows ): array {
        return array_values(
            array_filter(
                $rows,
                static function ( $row ) {
                    return ! empty( $row['value'] );
                }
            )
        );
    }

    protected function render_info_section( string $title, array $rows ): void {
        if ( empty( $rows ) ) {
            return;
        }

        echo '<section class="estate-office-offer-section">';
        echo '<h2>' . esc_html( $title ) . '</h2>';
        $this->render_info_list( $rows );
        echo '</section>';
    }

    protected function render_info_list( array $rows, string $list_class = 'estate-office-info-list' ): void {
        if ( empty( $rows ) ) {
            return;
        }

        echo '<dl class="' . esc_attr( $list_class ) . '">';
        foreach ( $rows as $row ) {
            echo '<div><dt>' . esc_html( $row['label'] ) . '</dt><dd>' . esc_html( $row['value'] ) . '</dd></div>';
        }
        echo '</dl>';
    }

    protected function map_furnishing_label( string $value ): string {
        switch ( strtoupper( $value ) ) {
            case 'TAK':
                return __( 'Tak', 'estate-office' );
            case 'NIE':
                return __( 'Nie', 'estate-office' );
            case 'CZĘŚCIOWE':
                return __( 'Częściowe', 'estate-office' );
        }

        return '';
    }

    protected function format_surface_details( array $surfaces, array $labels ): array {
        $rows = [];
        foreach ( $labels as $key => $label ) {
            if ( empty( $surfaces[ $key ]['enabled'] ) ) {
                continue;
            }
            $surface = $surfaces[ $key ];
            $parts   = [];
            if ( ! empty( $surface['count'] ) ) {
                $count = (int) $surface['count'];
                $parts[] = sprintf( _n( '%d szt.', '%d szt.', $count, 'estate-office' ), $count );
            }
            if ( isset( $surface['area'] ) && '' !== $surface['area'] ) {
                $parts[] = number_format_i18n( (float) $surface['area'], 2 ) . ' m²';
            }
            $rows[] = [
                'label' => $label,
                'value' => implode( ', ', $parts ),
            ];
        }

        return $this->filter_info_rows( $rows );
    }

    protected function map_plot_shape( string $shape ): string {
        $shape = strtoupper( trim( $shape ) );
        if ( 'REGULARNY' === $shape ) {
            return __( 'Regularny', 'estate-office' );
        }
        if ( 'NIEREGULARNY' === $shape ) {
            return __( 'Nieregularny', 'estate-office' );
        }

        return '';
    }

    protected function format_plot_dimensions( array $plot ): string {
        $parts = [];
        if ( isset( $plot['length'] ) && '' !== $plot['length'] ) {
            $parts[] = number_format_i18n( (float) $plot['length'], 2 ) . ' m';
        }
        if ( isset( $plot['width'] ) && '' !== $plot['width'] ) {
            $parts[] = number_format_i18n( (float) $plot['width'], 2 ) . ' m';
        }

        return implode( ' × ', $parts );
    }

    protected function get_dynamic_property_fields( array $details ): array {
        $fields = EstateOffice_Admin_Settings::get_dynamic_fields( 'property' );
        if ( empty( $fields ) ) {
            return [];
        }

        $output = [];
        foreach ( $fields as $field ) {
            $key = $field['key'] ?? '';
            if ( '' === $key || ! isset( $details[ $key ] ) || '' === $details[ $key ] ) {
                continue;
            }
            $value = $details[ $key ];
            if ( is_array( $value ) ) {
                $value = implode( ', ', array_filter( array_map( 'strval', $value ) ) );
            }
            $output[] = [
                'label' => $field['label'] ?? $key,
                'value' => $value,
            ];
        }

        return $this->filter_info_rows( $output );
    }

    protected function enqueue_assets(): void {
        if ( ! wp_style_is( 'estate-office-public', 'enqueued' ) ) {
            wp_enqueue_style( 'estate-office-public', ESTATE_OFFICE_URL . 'assets/css/public.css', [], ESTATE_OFFICE_VERSION );
        }
        if ( ! wp_script_is( 'estate-office-public', 'enqueued' ) ) {
            wp_enqueue_script( 'estate-office-public', ESTATE_OFFICE_URL . 'assets/js/public.js', [ 'jquery' ], ESTATE_OFFICE_VERSION, true );
        }
    }
}
