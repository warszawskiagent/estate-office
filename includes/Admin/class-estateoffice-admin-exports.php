<?php
/**
 * Admin page presenting portal export queue.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EstateOffice_Admin_Exports extends EstateOffice_Admin_Page {

    public const SLUG = 'estate-office-exports';

    public function __construct( string $parent_slug ) {
        parent::__construct( $parent_slug );
        $this->slug       = self::SLUG;
        $this->menu_title = __( 'Eksporty', 'estate-office' );
        $this->page_title = __( 'Eksport na portale', 'estate-office' );
        $this->capability = 'eo_manage_properties';
    }

    public function render(): void {
        $status_filter = isset( $_GET['queue_status'] ) ? sanitize_text_field( wp_unslash( $_GET['queue_status'] ) ) : '';
        $queue         = EstateOffice_Portal_Manager::get_queue_items( $status_filter );
        ?>
        <div class="wrap estate-office-wrap estate-office-exports">
            <?php echo estate_office_get_brand_badge_html( 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="estate-office-admin-heading">
                <h1><?php echo esc_html( $this->page_title ); ?></h1>
                <div class="estate-office-admin-heading-actions">
                    <?php $this->render_global_action(); ?>
                </div>
            </div>
            <?php $this->render_notice(); ?>
            <p class="description">
                <?php esc_html_e( 'Kolejka eksportów obsługuje przesyłanie nieruchomości na skonfigurowane portale. Poniżej znajdziesz statusy wysyłek, historię logów oraz możliwość ponowienia eksportu.', 'estate-office' ); ?>
            </p>
            <form method="get" class="estate-office-filter-form">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
                <label for="estate-office-queue-status" class="screen-reader-text"><?php esc_html_e( 'Filtruj po statusie', 'estate-office' ); ?></label>
                <select id="estate-office-queue-status" name="queue_status">
                    <option value=""><?php esc_html_e( 'Wszystkie statusy', 'estate-office' ); ?></option>
                    <?php foreach ( $this->get_status_labels() as $status_key => $label ) : ?>
                        <option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $status_filter, $status_key ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button"><?php esc_html_e( 'Filtruj', 'estate-office' ); ?></button>
            </form>
            <table class="widefat striped estate-office-table estate-office-exports-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Nieruchomość', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Portal', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Próby', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Zaplanowano', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Ostatnia aktualizacja', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Logi', 'estate-office' ); ?></th>
                        <th><?php esc_html_e( 'Akcje', 'estate-office' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $queue ) ) : ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e( 'Brak pozycji w kolejce eksportu.', 'estate-office' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $queue as $item ) :
                            $property_id = (int) ( $item['property_id'] ?? 0 );
                            $queue_id    = (int) ( $item['id'] ?? 0 );
                            $status      = $item['status'] ?? '';
                            $status_label = $this->get_status_labels()[ $status ] ?? $status;
                            $scheduled   = $item['scheduled_at'] ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['scheduled_at'] ) : '—';
                            $updated     = $item['updated_at'] ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['updated_at'] ) : '—';
                            $property_edit_url = $property_id ? admin_url( 'admin.php?page=' . EstateOffice_Admin_Properties::SLUG . '&action=edit&property=' . $property_id ) : '';
                            $last_log_message  = $item['last_log_message'] ?? '';
                            $logs              = EstateOffice_Portal_Manager::get_logs_for_queue( $queue_id );
                            ?>
                            <tr>
                                <td>
                                    <?php if ( $property_id ) : ?>
                                        <strong><?php echo esc_html( sprintf( '#%05d', $property_id ) ); ?></strong><br />
                                        <?php if ( $property_edit_url ) : ?>
                                            <a href="<?php echo esc_url( $property_edit_url ); ?>"><?php esc_html_e( 'Edytuj nieruchomość', 'estate-office' ); ?></a>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="description">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( $item['portal_name'] ?? '' ); ?></strong><br />
                                    <span class="description"><?php echo esc_html( $item['portal_slug'] ?? '' ); ?></span>
                                </td>
                                <td>
                                    <span class="estate-office-status-badge status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_label ); ?></span>
                                    <?php if ( ! empty( $item['last_error'] ) && in_array( $status, [ 'failed', 'retry', 'cancelled', 'skipped' ], true ) ) : ?>
                                        <p class="description"><?php echo esc_html( $item['last_error'] ); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( (string) ( $item['attempts'] ?? 0 ) ); ?></td>
                                <td><?php echo esc_html( $scheduled ); ?></td>
                                <td><?php echo esc_html( $updated ); ?></td>
                                <td>
                                    <?php if ( empty( $logs ) ) : ?>
                                        <span class="description">&mdash;</span>
                                    <?php else : ?>
                                        <details>
                                            <summary><?php echo esc_html( $last_log_message ?: __( 'Pokaż logi', 'estate-office' ) ); ?></summary>
                                            <ul>
                                                <?php foreach ( $logs as $log ) : ?>
                                                    <li>
                                                        <strong><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $log['created_at'] ) ); ?></strong>
                                                        – <?php echo esc_html( $log['status'] ); ?>
                                                        <?php if ( ! empty( $log['message'] ) ) : ?>
                                                            <div class="description"><?php echo esc_html( $log['message'] ); ?></div>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </details>
                                    <?php endif; ?>
                                </td>
                                <td class="estate-office-exports-actions">
                                    <?php $has_action = false; ?>
                                    <?php if ( in_array( $status, [ 'pending', 'retry', 'throttled', 'failed' ], true ) ) :
                                        $has_action = true; ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="estate-office-inline-form">
                                            <?php wp_nonce_field( 'estate_office_process_portal_export' ); ?>
                                            <input type="hidden" name="action" value="estate_office_process_portal_export" />
                                            <input type="hidden" name="queue_id" value="<?php echo esc_attr( $queue_id ); ?>" />
                                            <button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Wyślij teraz', 'estate-office' ); ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ( in_array( $status, [ 'failed', 'cancelled', 'skipped', 'retry', 'throttled' ], true ) ) :
                                        $has_action = true; ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="estate-office-inline-form">
                                            <?php wp_nonce_field( 'estate_office_retry_portal_export' ); ?>
                                            <input type="hidden" name="action" value="estate_office_retry_portal_export" />
                                            <input type="hidden" name="queue_id" value="<?php echo esc_attr( $queue_id ); ?>" />
                                            <button type="submit" class="button button-small"><?php esc_html_e( 'Ponów eksport', 'estate-office' ); ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ( ! $has_action ) : ?>
                                        <span class="description">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    protected function render_notice(): void {
        if ( empty( $_GET['status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $status = sanitize_key( wp_unslash( $_GET['status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ( 'processed' === $status ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Eksport został uruchomiony.', 'estate-office' ) . '</p></div>';
        } elseif ( 'requeued' === $status ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Pozycję dodano ponownie do kolejki.', 'estate-office' ) . '</p></div>';
        } elseif ( 'failed' === $status ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Nie udało się ponowić eksportu.', 'estate-office' ) . '</p></div>';
        }
    }

    protected function render_global_action(): void {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="estate-office-run-exports">
            <?php wp_nonce_field( 'estate_office_run_portal_exports' ); ?>
            <input type="hidden" name="action" value="estate_office_run_portal_exports" />
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Uruchom eksport teraz', 'estate-office' ); ?></button>
        </form>
        <?php
    }

    /**
     * Provide status map.
     *
     * @return array<string,string>
     */
    protected function get_status_labels(): array {
        return estate_office_get_portal_status_labels();
    }
}
