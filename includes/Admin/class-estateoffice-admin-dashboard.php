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
                'reports'            => [],
            ]
        );
        $reports = is_array( $metrics['reports'] ?? null ) ? $metrics['reports'] : self::get_report_datasets();
        $reports_json = $reports ? wp_json_encode( $reports ) : '';
        ?>
        <div class="wrap estate-office-wrap estate-office-dashboard">
            <?php echo estate_office_get_brand_badge_html( 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="estate-office-admin-heading">
                <h1><?php echo esc_html( $this->page_title ); ?></h1>
                <div class="estate-office-admin-heading-actions">
                    <?php $this->render_global_action(); ?>
                </div>
            </div>
            <?php $this->render_portal_alerts_panel(); ?>
            <div class="estate-office-grid">
                <?php foreach ( $metrics['cards'] as $metric ) : ?>
                    <div class="estate-office-card">
                        <h3><?php echo esc_html( $metric['label'] ); ?></h3>
                        <p class="estate-office-card-value"><?php echo esc_html( $metric['value'] ); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="estate-office-panels">
                <?php if ( ! empty( $reports['charts'] ) && $reports_json ) : ?>
                    <div class="estate-office-panel estate-office-panel--reports" data-estate-office-reports="<?php echo esc_attr( (string) $reports_json ); ?>">
                        <div class="estate-office-report-grid">
                            <?php foreach ( $reports['charts'] as $key => $chart ) : ?>
                                <figure class="estate-office-report-card">
                                    <header class="estate-office-report-card__header">
                                        <h2><?php echo esc_html( $chart['title'] ?? '' ); ?></h2>
                                    </header>
                                    <div class="estate-office-report-card__chart">
                                        <canvas data-report="<?php echo esc_attr( (string) $key ); ?>" role="img" aria-label="<?php echo esc_attr( $chart['title'] ?? '' ); ?>"></canvas>
                                    </div>
                                    <p class="estate-office-report-card__fallback"><?php esc_html_e( 'Aby wyświetlić wykres, upewnij się, że JavaScript jest włączony.', 'estate-office' ); ?></p>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                        <?php if ( ! empty( $reports['exports'] ) ) : ?>
                            <form class="estate-office-report-export" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'estate_office_export_report', '_estate_office_export_nonce' ); ?>
                                <input type="hidden" name="action" value="estate_office_export_report" />
                                <label for="estate-office-report-export-select"><?php esc_html_e( 'Eksportuj raport', 'estate-office' ); ?></label>
                                <select id="estate-office-report-export-select" name="report">
                                    <?php foreach ( $reports['exports'] as $export_key => $export ) : ?>
                                        <option value="<?php echo esc_attr( (string) $export_key ); ?>"><?php echo esc_html( $export['label'] ?? $export_key ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="button button-secondary"><?php esc_html_e( 'Pobierz CSV', 'estate-office' ); ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
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
     * Render portal alert panel if failures exist.
     */
    protected function render_portal_alerts_panel(): void {
        if ( ! function_exists( 'estate_office_get_portal_alerts' ) ) {
            return;
        }

        $alerts = array_slice( estate_office_get_portal_alerts(), 0, 5 );
        if ( empty( $alerts ) ) {
            return;
        }

        $exports_url = admin_url( 'admin.php?page=' . EstateOffice_Admin_Exports::SLUG );
        ?>
        <div class="estate-office-alert-panel">
            <h2><?php esc_html_e( 'Alerty eksportu portali', 'estate-office' ); ?></h2>
            <ul>
                <?php foreach ( $alerts as $alert ) :
                    $property_id = (int) ( $alert['property_id'] ?? 0 );
                    $portal_name = $alert['portal_name'] ?? '';
                    $message     = $alert['message'] ?? '';
                    $timestamp   = isset( $alert['timestamp'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $alert['timestamp'] ) : '';
                    $property_url = $property_id ? admin_url( 'admin.php?page=' . EstateOffice_Admin_Properties::SLUG . '&action=edit&property=' . $property_id ) : '';
                    ?>
                    <li>
                        <strong><?php echo esc_html( sprintf( '#%05d – %s', $property_id, $portal_name ?: __( 'Portal', 'estate-office' ) ) ); ?></strong>
                        <?php if ( $timestamp ) : ?>
                            <span class="description"><?php echo esc_html( $timestamp ); ?></span>
                        <?php endif; ?>
                        <?php if ( $message ) : ?>
                            <div class="description"><?php echo esc_html( $message ); ?></div>
                        <?php endif; ?>
                        <?php if ( $property_url ) : ?>
                            <a class="estate-office-alert-link" href="<?php echo esc_url( $property_url ); ?>"><?php esc_html_e( 'Edytuj nieruchomość', 'estate-office' ); ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p><a class="button" href="<?php echo esc_url( $exports_url ); ?>"><?php esc_html_e( 'Zobacz kolejkę eksportów', 'estate-office' ); ?></a></p>
        </div>
        <?php
    }

    /**
     * Collect metrics for dashboard.
     */
    public static function get_metrics(): array {
        $cache_key = 'estate_office_dashboard_metrics';
        $cached    = get_transient( $cache_key );

        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        global $wpdb;

        $contracts_table  = $wpdb->prefix . 'eo_contracts';
        $properties_table = $wpdb->prefix . 'eo_properties';
        $clients_table    = $wpdb->prefix . 'eo_clients';
        $searches_table   = $wpdb->prefix . 'eo_searches';

        $counts = [
            'properties' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$properties_table}" ),
            'contracts'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$contracts_table} WHERE indefinite = 1 OR end_date >= CURDATE()" ),
            'searches'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$searches_table}" ),
            'clients'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$clients_table}" ),
        ];

        $cards = [
            [
                'label' => __( 'Nieruchomości', 'estate-office' ),
                'value' => $counts['properties'],
            ],
            [
                'label' => __( 'Aktywne umowy', 'estate-office' ),
                'value' => $counts['contracts'],
            ],
            [
                'label' => __( 'Poszukiwania', 'estate-office' ),
                'value' => $counts['searches'],
            ],
            [
                'label' => __( 'Klienci', 'estate-office' ),
                'value' => $counts['clients'],
            ],
        ];

        $recent_contracts = $wpdb->get_results(
            "SELECT id, contract_number, transaction_type, start_date FROM {$contracts_table} ORDER BY created_at DESC LIMIT 5"
        );

        $upcoming_contracts = $wpdb->get_results(
            "SELECT id, contract_number, end_date FROM {$contracts_table} WHERE end_date IS NOT NULL AND end_date >= CURDATE() AND end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY end_date ASC LIMIT 5"
        );

        $metrics = [
            'counts'             => $counts,
            'cards'              => $cards,
            'recent_contracts'   => $recent_contracts,
            'upcoming_contracts' => $upcoming_contracts,
            'top_agents'         => self::get_top_agents(),
            'reports'            => self::get_report_datasets(),
        ];

        set_transient( $cache_key, $metrics, MINUTE_IN_SECONDS * 10 );

        return $metrics;
    }

    /**
     * Provide chart and export datasets used by CRM reports.
     *
     * @return array<string,mixed>
     */
    public static function get_report_datasets(): array {
        $contracts   = self::prepare_contracts_by_month_dataset();
        $properties  = self::prepare_properties_by_type_dataset();
        $transactions = self::prepare_transactions_by_type_dataset();

        $charts = [
            'contracts_by_month'    => $contracts['chart'],
            'properties_by_type'    => $properties['chart'],
            'transactions_by_type'  => $transactions['chart'],
        ];

        $exports = [
            'contracts_by_month'   => $contracts['export'],
            'properties_by_type'   => $properties['export'],
            'transactions_by_type' => $transactions['export'],
        ];

        return [
            'charts'  => $charts,
            'exports' => $exports,
        ];
    }

    /**
     * Retrieve export dataset by key.
     */
    public static function get_export_dataset( string $key ): array {
        $reports = self::get_report_datasets();
        return $reports['exports'][ $key ] ?? [];
    }

    /**
     * Clear cached dashboard metrics.
     */
    public static function flush_metrics_cache(): void {
        delete_transient( 'estate_office_dashboard_metrics' );
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

    /**
     * Prepare dataset counting contracts per month for the last twelve months.
     *
     * @return array<string,mixed>
     */
    protected static function prepare_contracts_by_month_dataset(): array {
        global $wpdb;

        $contracts_table = $wpdb->prefix . 'eo_contracts';
        $start_timestamp = strtotime( 'first day of -11 month midnight' );
        $periods         = [];

        for ( $i = 0; $i < 12; $i++ ) {
            $timestamp           = strtotime( sprintf( '+%d month', $i ), $start_timestamp );
            $key                 = gmdate( 'Y-m', $timestamp );
            $periods[ $key ]     = [
                'timestamp' => $timestamp,
                'label'     => wp_date( 'F Y', $timestamp ),
                'value'     => 0,
            ];
        }

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(start_date, '%%Y-%%m') AS period, COUNT(*) AS total
                FROM {$contracts_table}
                WHERE start_date IS NOT NULL AND start_date >= %s
                GROUP BY period
                ORDER BY period ASC",
                gmdate( 'Y-m-01', $start_timestamp )
            )
        );

        foreach ( $results as $row ) {
            $key = $row->period;
            if ( isset( $periods[ $key ] ) ) {
                $periods[ $key ]['value'] = (int) $row->total;
            }
        }

        $labels = [];
        $values = [];
        foreach ( $periods as $period ) {
            $labels[] = $period['label'];
            $values[] = $period['value'];
        }

        return [
            'chart'  => [
                'title'    => __( 'Umowy wg miesięcy', 'estate-office' ),
                'type'     => 'line',
                'labels'   => $labels,
                'datasets' => [
                    [
                        'label'           => __( 'Umowy', 'estate-office' ),
                        'data'            => $values,
                        'borderColor'     => '#2563eb',
                        'backgroundColor' => 'rgba(37, 99, 235, 0.2)',
                        'borderWidth'     => 2,
                        'fill'            => true,
                        'tension'         => 0.3,
                    ],
                ],
            ],
            'export' => [
                'label'    => __( 'Umowy wg miesięcy', 'estate-office' ),
                'filename' => 'estate-office-contracts-by-month-' . gmdate( 'Ymd' ) . '.csv',
                'headers'  => [ __( 'Okres', 'estate-office' ), __( 'Liczba umów', 'estate-office' ) ],
                'rows'     => array_map(
                    static function ( string $label, int $value ): array {
                        return [ $label, (string) $value ];
                    },
                    $labels,
                    $values
                ),
            ],
        ];
    }

    /**
     * Prepare dataset describing inventory by property type.
     *
     * @return array<string,mixed>
     */
    protected static function prepare_properties_by_type_dataset(): array {
        global $wpdb;

        $table          = $wpdb->prefix . 'eo_properties';
        $types          = estate_office_get_property_types();
        $type_counts    = array_fill_keys( $types, 0 );

        $placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
        $results      = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT property_type, COUNT(*) AS total FROM {$table} WHERE property_type IN ({$placeholders}) GROUP BY property_type",
                ...$types
            )
        );

        foreach ( $results as $row ) {
            $type = $row->property_type;
            if ( isset( $type_counts[ $type ] ) ) {
                $type_counts[ $type ] = (int) $row->total;
            }
        }

        $labels = array_values( $types );
        $values = array_map(
            static function ( string $type ) use ( $type_counts ): int {
                return (int) ( $type_counts[ $type ] ?? 0 );
            },
            $types
        );

        $palette = self::get_chart_palette();
        $colors  = [];
        foreach ( $labels as $index => $label ) {
            $colors[] = $palette[ $index % count( $palette ) ];
        }

        return [
            'chart'  => [
                'title'    => __( 'Struktura nieruchomości', 'estate-office' ),
                'type'     => 'doughnut',
                'labels'   => $labels,
                'datasets' => [
                    [
                        'label'           => __( 'Nieruchomości', 'estate-office' ),
                        'data'            => $values,
                        'backgroundColor' => $colors,
                        'borderColor'     => '#ffffff',
                        'borderWidth'     => 1,
                    ],
                ],
            ],
            'export' => [
                'label'    => __( 'Struktura nieruchomości', 'estate-office' ),
                'filename' => 'estate-office-properties-by-type-' . gmdate( 'Ymd' ) . '.csv',
                'headers'  => [ __( 'Rodzaj nieruchomości', 'estate-office' ), __( 'Liczba', 'estate-office' ) ],
                'rows'     => array_map(
                    static function ( string $label, int $value ): array {
                        return [ $label, (string) $value ];
                    },
                    $labels,
                    $values
                ),
            ],
        ];
    }

    /**
     * Prepare dataset summarising contracts by transaction type.
     *
     * @return array<string,mixed>
     */
    protected static function prepare_transactions_by_type_dataset(): array {
        global $wpdb;

        $contracts_table = $wpdb->prefix . 'eo_contracts';
        $types           = [ 'SPRZEDAŻ', 'KUPNO', 'WYNAJEM', 'NAJEM' ];
        $counts          = array_fill_keys( $types, 0 );

        $placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
        $results      = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT transaction_type, COUNT(*) AS total FROM {$contracts_table} WHERE transaction_type IN ({$placeholders}) GROUP BY transaction_type",
                ...$types
            )
        );

        foreach ( $results as $row ) {
            $type = $row->transaction_type;
            if ( isset( $counts[ $type ] ) ) {
                $counts[ $type ] = (int) $row->total;
            }
        }

        $labels = $types;
        $values = array_map(
            static function ( string $type ) use ( $counts ): int {
                return (int) ( $counts[ $type ] ?? 0 );
            },
            $types
        );

        $palette = self::get_chart_palette();

        return [
            'chart'  => [
                'title'    => __( 'Typy transakcji', 'estate-office' ),
                'type'     => 'bar',
                'labels'   => $labels,
                'datasets' => [
                    [
                        'label'           => __( 'Umowy', 'estate-office' ),
                        'data'            => $values,
                        'backgroundColor' => array_slice( $palette, 0, count( $labels ) ),
                        'borderColor'     => '#0f172a',
                        'borderWidth'     => 1,
                    ],
                ],
            ],
            'export' => [
                'label'    => __( 'Typy transakcji', 'estate-office' ),
                'filename' => 'estate-office-contracts-by-transaction-' . gmdate( 'Ymd' ) . '.csv',
                'headers'  => [ __( 'Typ transakcji', 'estate-office' ), __( 'Liczba umów', 'estate-office' ) ],
                'rows'     => array_map(
                    static function ( string $label, int $value ): array {
                        return [ $label, (string) $value ];
                    },
                    $labels,
                    $values
                ),
            ],
        ];
    }

    /**
     * Provide colour palette shared by charts.
     *
     * @return array<int,string>
     */
    protected static function get_chart_palette(): array {
        return [
            '#2563eb',
            '#10b981',
            '#f59e0b',
            '#ef4444',
            '#8b5cf6',
            '#ec4899',
            '#14b8a6',
            '#9333ea',
        ];
    }
}
