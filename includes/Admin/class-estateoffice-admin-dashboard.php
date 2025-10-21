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
        $metrics    = self::get_metrics();
        $top_agents = self::get_top_agents();
        ?>
        <div class="wrap estate-office-wrap estate-office-dashboard">
            <h1><?php echo esc_html( $this->page_title ); ?></h1>
            <?php $this->render_global_action(); ?>
            <div class="estate-office-grid">
                <?php foreach ( $metrics as $metric ) : ?>
                    <div class="estate-office-card">
                        <h3><?php echo esc_html( $metric['label'] ); ?></h3>
                        <p class="estate-office-card-value"><?php echo esc_html( $metric['value'] ); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="estate-office-panels">
                <div class="estate-office-panel">
                    <h2><?php esc_html_e( 'Najlepsi agenci', 'estate-office' ); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Agent', 'estate-office' ); ?></th>
                                <th><?php esc_html_e( 'Liczba umów', 'estate-office' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $top_agents ) ) : ?>
                                <tr><td colspan="2"><?php esc_html_e( 'Brak przypisanych agentów.', 'estate-office' ); ?></td></tr>
                            <?php else : ?>
                                <?php foreach ( $top_agents as $agent ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $agent->name ); ?></td>
                                        <td><?php echo esc_html( $agent->contracts ); ?></td>
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

        $tables = [
            'properties' => [ $wpdb->prefix . 'eo_properties', __( 'Nieruchomości', 'estate-office' ) ],
            'contracts'  => [ $wpdb->prefix . 'eo_contracts', __( 'Aktywne umowy', 'estate-office' ) ],
            'clients'    => [ $wpdb->prefix . 'eo_clients', __( 'Klienci', 'estate-office' ) ],
            'searches'   => [ $wpdb->prefix . 'eo_searches', __( 'Poszukiwania', 'estate-office' ) ],
        ];

        $metrics = [];
        foreach ( $tables as $table => $config ) {
            $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$config[0]}" );
            $metrics[] = [
                'label' => $config[1],
                'value' => $count,
            ];
        }

        return $metrics;
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
                LEFT JOIN {$contracts_table} c ON JSON_EXTRACT(c.stage_history, '$.agent_id') = a.id
                GROUP BY a.id
                ORDER BY contracts DESC, name ASC
                LIMIT 5";

        return $wpdb->get_results( $sql );
    }
}
