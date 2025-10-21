<?php
namespace EstateOffice\Admin\Pages;

use EstateOffice\Admin\Pages\Traits\DataFormattingTrait;
use EstateOffice\Meta\Keys;
use WP_Query;
use WP_User;

/**
 * Dashboard with key CRM indicators.
 */
class DashboardPage extends AbstractPage {
    use DataFormattingTrait;

    public function render(): void {
        $metrics            = $this->collect_metrics();
        $top_agents         = $this->get_top_agents();
        $upcoming_contracts = $this->get_upcoming_contracts();

        $this->render_header(
            __( 'Estate Office CRM – Pulpit', 'estate-office' ),
            __( 'Monitoruj kluczowe wskaźniki zespołu i reaguj na zbliżające się terminy.', 'estate-office' )
        );
        ?>
        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Najważniejsze wskaźniki', 'estate-office' ); ?></h2>
            <div class="estate-office-metrics">
                <div class="estate-office-metric">
                    <span class="estate-office-metric__label"><?php esc_html_e( 'Nieruchomości', 'estate-office' ); ?></span>
                    <span class="estate-office-metric__value"><?php echo esc_html( number_format_i18n( $metrics['properties'] ) ); ?></span>
                    <span class="estate-office-metric__hint"><?php printf( esc_html__( 'Eksport na WWW: %s', 'estate-office' ), esc_html( number_format_i18n( $metrics['exported_properties'] ) ) ); ?></span>
                </div>
                <div class="estate-office-metric">
                    <span class="estate-office-metric__label"><?php esc_html_e( 'Aktywne umowy', 'estate-office' ); ?></span>
                    <span class="estate-office-metric__value"><?php echo esc_html( number_format_i18n( $metrics['active_contracts'] ) ); ?></span>
                    <span class="estate-office-metric__hint"><?php printf( esc_html__( 'Wszystkich umów: %s', 'estate-office' ), esc_html( number_format_i18n( $metrics['contracts'] ) ) ); ?></span>
                </div>
                <div class="estate-office-metric">
                    <span class="estate-office-metric__label"><?php esc_html_e( 'Poszukiwania', 'estate-office' ); ?></span>
                    <span class="estate-office-metric__value"><?php echo esc_html( number_format_i18n( $metrics['searches'] ) ); ?></span>
                    <span class="estate-office-metric__hint"><?php printf( esc_html__( 'Powiązane umowy: %s', 'estate-office' ), esc_html( number_format_i18n( $metrics['search_contracts'] ) ) ); ?></span>
                </div>
                <div class="estate-office-metric">
                    <span class="estate-office-metric__label"><?php esc_html_e( 'Klienci', 'estate-office' ); ?></span>
                    <span class="estate-office-metric__value"><?php echo esc_html( number_format_i18n( $metrics['clients'] ) ); ?></span>
                    <span class="estate-office-metric__hint"><?php printf( esc_html__( 'Przypisani do umów: %s', 'estate-office' ), esc_html( number_format_i18n( $metrics['clients_with_contracts'] ) ) ); ?></span>
                </div>
            </div>
        </div>

        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Najlepsi agenci', 'estate-office' ); ?></h2>
            <?php if ( empty( $top_agents ) ) : ?>
                <p><?php esc_html_e( 'Brak danych do wyświetlenia – dodaj umowy i nieruchomości, aby zobaczyć ranking.', 'estate-office' ); ?></p>
            <?php else : ?>
                <ul class="estate-office-top-agents">
                    <?php foreach ( $top_agents as $agent ) : ?>
                        <li>
                            <span class="estate-office-top-agents__name"><?php echo esc_html( $agent['name'] ); ?></span>
                            <span class="estate-office-top-agents__stats"><?php
                                printf(
                                    esc_html__( 'Umowy: %1$s • Nieruchomości: %2$s', 'estate-office' ),
                                    esc_html( number_format_i18n( $agent['contracts'] ) ),
                                    esc_html( number_format_i18n( $agent['properties'] ) )
                                );
                            ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Nadchodzące zakończenia umów', 'estate-office' ); ?></h2>
            <?php if ( empty( $upcoming_contracts ) ) : ?>
                <p><?php esc_html_e( 'Brak umów z terminem zakończenia w najbliższych 30 dniach.', 'estate-office' ); ?></p>
            <?php else : ?>
                <ul class="estate-office-upcoming-contracts">
                    <?php foreach ( $upcoming_contracts as $contract ) : ?>
                        <li>
                            <div>
                                <strong><?php echo esc_html( $contract['number'] ); ?></strong>
                                <span class="estate-office-upcoming-contracts__meta"><?php echo esc_html( $contract['agent'] ); ?></span>
                            </div>
                            <div class="estate-office-upcoming-contracts__actions">
                                <span class="estate-office-upcoming-contracts__date"><?php echo esc_html( $contract['end_date'] ); ?></span>
                                <a class="button button-secondary" href="<?php echo esc_url( $contract['url'] ); ?>"><?php esc_html_e( 'Przejdź do umowy', 'estate-office' ); ?></a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Szybki start', 'estate-office' ); ?></h2>
            <ol>
                <li><?php esc_html_e( 'Skonfiguruj podstawowe ustawienia, w tym klucz Google Maps, logo i znak wodny.', 'estate-office' ); ?></li>
                <li><?php esc_html_e( 'Dodaj agentów biura i przypisz ich do nowych umów z poziomu kreatora.', 'estate-office' ); ?></li>
                <li><?php esc_html_e( 'Rozszerz pola nieruchomości, umów oraz klientów, aby formularze odpowiadały procesom Twojego biura.', 'estate-office' ); ?></li>
            </ol>
        </div>
        <?php
        $this->render_footer();
    }

    private function collect_metrics(): array {
        $properties = wp_count_posts( 'estate_property' )->publish ?? 0;
        $contracts  = wp_count_posts( 'estate_contract' )->publish ?? 0;
        $searches   = wp_count_posts( 'estate_search' )->publish ?? 0;
        $clients    = wp_count_posts( 'estate_client' )->publish ?? 0;

        $exported_query = new WP_Query(
            [
                'post_type'      => 'estate_property',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    [
                        'key'     => Keys::PROPERTY_BADGES,
                        'value'   => 'export_www',
                        'compare' => 'LIKE',
                    ],
                ],
            ]
        );

        $today          = wp_date( 'Y-m-d' );
        $active_query   = new WP_Query(
            [
                'post_type'      => 'estate_contract',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'     => Keys::CONTRACT_INDEFINITE,
                        'value'   => '1',
                        'compare' => '=',
                    ],
                    [
                        'key'     => Keys::CONTRACT_END_DATE,
                        'value'   => $today,
                        'compare' => '>=',
                        'type'    => 'DATE',
                    ],
                    [
                        'key'     => Keys::CONTRACT_END_DATE,
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => Keys::CONTRACT_END_DATE,
                        'value'   => '',
                        'compare' => '=',
                    ],
                ],
            ]
        );

        $search_contracts = new WP_Query(
            [
                'post_type'      => 'estate_search',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    [
                        'key'     => Keys::SEARCH_CONTRACT,
                        'compare' => 'EXISTS',
                    ],
                ],
            ]
        );

        $clients_with_contracts = new WP_Query(
            [
                'post_type'      => 'estate_client',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => [
                    [
                        'key'     => Keys::CLIENT_CONTRACTS,
                        'compare' => 'EXISTS',
                    ],
                ],
            ]
        );

        $metrics = [
            'properties'             => (int) $properties,
            'contracts'              => (int) $contracts,
            'searches'               => (int) $searches,
            'clients'                => (int) $clients,
            'exported_properties'    => count( $exported_query->posts ),
            'active_contracts'       => count( $active_query->posts ),
            'search_contracts'       => count( $search_contracts->posts ),
            'clients_with_contracts' => count( $clients_with_contracts->posts ),
        ];

        wp_reset_postdata();

        return $metrics;
    }

    /**
     * Returns top agents sorted by the volume of contracts and properties.
     */
    private function get_top_agents(): array {
        $users = get_users(
            [
                'role__in' => [ 'estate_office_agent', 'administrator', 'editor', 'author' ],
                'orderby'  => 'display_name',
                'order'    => 'ASC',
            ]
        );

        $agents = [];

        foreach ( $users as $user ) {
            if ( ! in_array( 'estate_office_agent', $user->roles, true ) && ! user_can( $user->ID, 'manage_options' ) ) {
                continue;
            }

            $contracts  = count_user_posts( $user->ID, 'estate_contract', false );
            $properties = count_user_posts( $user->ID, 'estate_property', false );

            if ( 0 === $contracts && 0 === $properties ) {
                continue;
            }

            $agents[] = [
                'id'         => $user->ID,
                'name'       => $this->format_user_name( $user ),
                'contracts'  => (int) $contracts,
                'properties' => (int) $properties,
            ];
        }

        usort(
            $agents,
            static function ( array $a, array $b ): int {
                $score_a = $a['contracts'] * 2 + $a['properties'];
                $score_b = $b['contracts'] * 2 + $b['properties'];

                if ( $score_a === $score_b ) {
                    return strcmp( $a['name'], $b['name'] );
                }

                return $score_b <=> $score_a;
            }
        );

        return array_slice( $agents, 0, 5 );
    }

    /**
     * Finds contracts ending within the next 30 days.
     */
    private function get_upcoming_contracts(): array {
        $today     = wp_date( 'Y-m-d' );
        $threshold = wp_date( 'Y-m-d', strtotime( '+30 days' ) );

        $query = new WP_Query(
            [
                'post_type'      => 'estate_contract',
                'post_status'    => 'publish',
                'posts_per_page' => 5,
                'meta_key'       => Keys::CONTRACT_END_DATE,
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'relation' => 'OR',
                        [
                            'key'     => Keys::CONTRACT_INDEFINITE,
                            'value'   => '1',
                            'compare' => '!=',
                        ],
                        [
                            'key'     => Keys::CONTRACT_INDEFINITE,
                            'compare' => 'NOT EXISTS',
                        ],
                    ],
                    [
                        'key'     => Keys::CONTRACT_END_DATE,
                        'value'   => [ $today, $threshold ],
                        'compare' => 'BETWEEN',
                        'type'    => 'DATE',
                    ],
                ],
            ]
        );

        if ( ! $query->have_posts() ) {
            return [];
        }

        $contracts = [];

        foreach ( $query->posts as $post ) {
            $number  = get_post_meta( $post->ID, Keys::CONTRACT_NUMBER, true ) ?: $post->post_title;
            $end     = get_post_meta( $post->ID, Keys::CONTRACT_END_DATE, true );
            $contracts[] = [
                'id'       => $post->ID,
                'number'   => $number,
                'end_date' => $this->format_date( $end ),
                'agent'    => $this->format_agent( $post ),
                'url'      => $this->get_profile_url( 'contract', $post->ID ),
            ];
        }

        wp_reset_postdata();

        return $contracts;
    }

    private function format_user_name( WP_User $user ): string {
        $name = trim( $user->first_name . ' ' . $user->last_name );

        if ( $name ) {
            return $name;
        }

        if ( $user->display_name ) {
            return $user->display_name;
        }

        return $user->user_login;
    }
}
