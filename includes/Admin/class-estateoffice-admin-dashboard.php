<?php
/**
 * Dashboard page.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EstateOffice_Admin_Dashboard extends EstateOffice_Admin_Page {

    public const SLUG = 'estate-office-crm';

    public function __construct( string $parent_slug = '' ) {
        parent::__construct( $parent_slug );
        $this->slug        = self::SLUG;
        $this->menu_title  = __( 'Estate Office CRM', 'estate-office' );
        $this->page_title  = __( 'Pulpit CRM', 'estate-office' );
        $this->capability  = 'eo_view_crm';
        $this->icon        = 'dashicons-building';
        $this->position    = 26;
    }

    public function render(): void {
        $metrics = wp_parse_args(
            self::get_metrics(),
            [
                'cards'              => [],
                'top_agents'         => [],
                'recent_contracts'   => [],
                'upcoming_contracts' => [],
            ]
        );
        ?>
        <div class="wrap estate-office-wrap estate-office-dashboard">
            <?php echo estate_office_get_brand_badge_html( 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="estate-office-admin-heading">
                <h1><?php echo esc_html( $this->page_title ); ?></h1>
                <div class="estate-office-admin-heading-actions">
                    <?php $this->render_global_action(); ?>
                </div>
            </div>
            <div class="estate-office-grid">
                <?php foreach ( $metrics['cards'] as $metric ) : ?>
                    <div class="estate-office-card">
                        <h3><?php echo esc_html( $metric['label'] ); ?></h3>
                        <p class="estate-office-card-value"><?php echo esc_html( $metric['value'] ); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="estate-office-panels">
                <?php if ( ! empty( $metrics['top_agents'] ) ) : ?>
                    <div class="estate-office-panel">
                        <h2><?php esc_html_e( 'Najlepsi agenci', 'estate-office' ); ?></h2>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Agent', 'estate-office' ); ?></th>
                                    <th><?php esc_html_e( 'Aktywne umowy', 'estate-office' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $metrics['top_agents'] as $agent ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $agent->name ); ?></td>
                                        <td><?php echo esc_html( (int) $agent->contracts ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else : ?>
                    <div class="estate-office-panel">
                        <h2><?php esc_html_e( 'Najlepsi agenci', 'estate-office' ); ?></h2>
                        <p><?php esc_html_e( 'Brak przypisanych agentów.', 'estate-office' ); ?></p>
                    </div>
                <?php endif; ?>
                <div class="estate-office-panel">
                    <h2><?php esc_html_e( 'Nadchodzące zakończenia umów', 'estate-office' ); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Umowa', 'estate-office' ); ?></th>
                                <th><?php esc_html_e( 'Data zakończenia', 'estate-office' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $metrics['upcoming_contracts'] ) ) : ?>
                                <tr><td colspan="2"><?php esc_html_e( 'Brak nadchodzących zakończeń.', 'estate-office' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $metrics['upcoming_contracts'] as $contract ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( sprintf( '%1$s (%2$s)', $contract->contract_number, sprintf( '#%05d', $contract->id ) ) ); ?></td>
                                        <td><?php echo esc_html( $contract->end_date ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="estate-office-panel">
                    <h2><?php esc_html_e( 'Ostatnie umowy', 'estate-office' ); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Umowa', 'estate-office' ); ?></th>
                                <th><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></th>
                                <th><?php esc_html_e( 'Data zawarcia', 'estate-office' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $metrics['recent_contracts'] ) ) : ?>
                                <tr><td colspan="3"><?php esc_html_e( 'Brak zarejestrowanych umów.', 'estate-office' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $metrics['recent_contracts'] as $contract ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( sprintf( '%1$s (%2$s)', $contract->contract_number, sprintf( '#%05d', $contract->id ) ) ); ?></td>
                                        <td><?php echo esc_html( $contract->transaction_type ); ?></td>
                                        <td><?php echo esc_html( $contract->start_date ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="estate-office-panel">
                    <h2><?php esc_html_e( 'Szybkie działania', 'estate-office' ); ?></h2>
                    <ul>
                        <li><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . EstateOffice_Admin_Contracts::SLUG . '&action=new' ) ); ?>"><?php esc_html_e( 'Dodaj nową umowę', 'estate-office' ); ?></a></li>
                        <li><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . EstateOffice_Admin_Clients::SLUG . '&action=new' ) ); ?>"><?php esc_html_e( 'Dodaj klienta', 'estate-office' ); ?></a></li>
                        <li><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . EstateOffice_Admin_Properties::SLUG . '&action=new' ) ); ?>"><?php esc_html_e( 'Dodaj nieruchomość', 'estate-office' ); ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render top action button.
     */
    protected function render_global_action(): void {
        $url = admin_url( 'admin.php?page=' . EstateOffice_Admin_Contracts::SLUG . '&action=new' );
        printf(
            '<a href="%1$s" class="page-title-action">%2$s</a>',
            esc_url( $url ),
            esc_html__( 'Dodaj nową umowę', 'estate-office' )
        );
    }

    /**
     * Collect metrics for dashboard.
     */
    public static function get_metrics(): array {
        global $wpdb;

        $contracts_table  = $wpdb->prefix . 'eo_contracts';
        $properties_table = $wpdb->prefix . 'eo_properties';
        $clients_table    = $wpdb->prefix . 'eo_clients';
        $searches_table   = $wpdb->prefix . 'eo_searches';

        $cards = [
            [
                'label' => __( 'Nieruchomości', 'estate-office' ),
                'value' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$properties_table}" ),
            ],
            [
                'label' => __( 'Aktywne umowy', 'estate-office' ),
                'value' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$contracts_table} WHERE indefinite = 1 OR end_date >= CURDATE()" ),
            ],
            [
                'label' => __( 'Poszukiwania', 'estate-office' ),
                'value' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$searches_table}" ),
            ],
            [
                'label' => __( 'Klienci', 'estate-office' ),
                'value' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$clients_table}" ),
            ],
        ];

        $recent_contracts = $wpdb->get_results(
            "SELECT id, contract_number, transaction_type, start_date FROM {$contracts_table} ORDER BY created_at DESC LIMIT 5"
        );

        $upcoming_contracts = $wpdb->get_results(
            "SELECT id, contract_number, end_date FROM {$contracts_table} WHERE end_date IS NOT NULL AND end_date >= CURDATE() AND end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY end_date ASC LIMIT 5"
        );

        return [
            'cards'              => $cards,
            'recent_contracts'   => $recent_contracts,
            'upcoming_contracts' => $upcoming_contracts,
            'top_agents'         => self::get_top_agents(),
        ];
    }

    /**
     * Fetch top agents by contracts count.
     */
    public static function get_top_agents(): array {
        global $wpdb;
        $agents_table   = $wpdb->prefix . 'eo_agents';
        $contracts_table = $wpdb->prefix . 'eo_contracts';

        $sql = "SELECT a.id, CONCAT_WS(' ', a.first_name, a.last_name) AS name, COUNT(c.id) AS contracts
                FROM {$agents_table} a
                LEFT JOIN {$contracts_table} c
                    ON c.agent_id = a.id
                    AND ( c.indefinite = 1 OR c.end_date >= CURDATE() )
                GROUP BY a.id
                ORDER BY contracts DESC, name ASC
                LIMIT 5";

        return $wpdb->get_results( $sql );
    }
}
