<?php
/**
 * Settings page implementation.
 *
 * @package EstateOffice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EstateOffice_Admin_Settings extends EstateOffice_Admin_Page {

    public const SLUG = 'estate-office-crm-settings';

    /**
     * Constructor.
     */
    public function __construct( string $parent_slug ) {
        parent::__construct( $parent_slug );
        $this->slug        = self::SLUG;
        $this->menu_title  = __( 'Ustawienia', 'estate-office' );
        $this->page_title  = __( 'Ustawienia EstateOffice CRM', 'estate-office' );
        $this->capability  = 'manage_options';
    }

    /**
     * Render settings page.
     */
    public function render(): void {
        $maps_key   = get_option( 'estate_office_google_maps_api_key', '' );
        $watermark  = (int) get_option( 'estate_office_watermark_attachment', 0 );
        $logo       = (int) get_option( 'estate_office_office_logo_attachment', 0 );
        $agent_base = get_option( 'estate_office_agent_slug_base', estate_office_get_agent_base_slug() );

        $field_groups = [
            'property' => self::get_dynamic_fields( 'property' ),
            'contract' => self::get_dynamic_fields( 'contract' ),
            'client'   => self::get_dynamic_fields( 'client' ),
        ];
        ?>
        <div class="wrap estate-office-wrap">
            <h1><?php echo esc_html( $this->page_title ); ?></h1>
            <?php $this->render_notice(); ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="estate-office-settings">
                <?php wp_nonce_field( 'estate_office_save_settings' ); ?>
                <input type="hidden" name="action" value="estate_office_save_settings" />

                <h2 class="title"><?php esc_html_e( 'Integracje', 'estate-office' ); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="google_maps_api_key"><?php esc_html_e( 'Klucz Google Maps API', 'estate-office' ); ?></label></th>
                            <td>
                                <input type="text" id="google_maps_api_key" name="google_maps_api_key" class="regular-text" value="<?php echo esc_attr( $maps_key ); ?>" autocomplete="off" />
                                <p class="description"><?php esc_html_e( 'Wprowadź klucz API, aby umożliwić zaznaczanie nieruchomości na mapie.', 'estate-office' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Znak wodny', 'estate-office' ); ?></th>
                            <td>
                                <?php $this->render_media_field( 'watermark_attachment', $watermark ); ?>
                                <p class="description"><?php esc_html_e( 'Znak wodny zostanie automatycznie dodany do zdjęć nieruchomości.', 'estate-office' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Logo biura', 'estate-office' ); ?></th>
                            <td>
                                <?php $this->render_media_field( 'office_logo_attachment', $logo ); ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="agent_slug_base"><?php esc_html_e( 'Bazowy adres stron agentów', 'estate-office' ); ?></label></th>
                            <td>
                                <input type="text" id="agent_slug_base" name="agent_slug_base" class="regular-text" value="<?php echo esc_attr( $agent_base ); ?>" placeholder="<?php esc_attr_e( 'np. agenci', 'estate-office' ); ?>" />
                                <p class="description">
                                    <?php echo esc_html__( 'Strony agentów będą dostępne pod adresem', 'estate-office' ) . ' '; ?>
                                    <code><?php echo esc_html( trailingslashit( home_url( trailingslashit( $agent_base ?: 'agenci' ) ) ) ); ?></code>
                                    <?php echo esc_html__( 'imię-nazwisko.', 'estate-office' ); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2 class="title"><?php esc_html_e( 'Pola dynamiczne', 'estate-office' ); ?></h2>
                <p><?php esc_html_e( 'Dodaj dodatkowe pola dla nieruchomości, umów i klientów. Pola te będą widoczne w odpowiednich formularzach.', 'estate-office' ); ?></p>

                <?php foreach ( $field_groups as $group_key => $fields ) : ?>
                    <div class="estate-office-dynamic-group" data-group="<?php echo esc_attr( $group_key ); ?>">
                        <h3>
                            <?php
                            switch ( $group_key ) {
                                case 'property':
                                    esc_html_e( 'Pola nieruchomości', 'estate-office' );
                                    break;
                                case 'contract':
                                    esc_html_e( 'Pola umów', 'estate-office' );
                                    break;
                                default:
                                    esc_html_e( 'Pola klientów', 'estate-office' );
                                    break;
                            }
                            ?>
                        </h3>
                        <table class="widefat striped estate-office-dynamic-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Etykieta', 'estate-office' ); ?></th>
                                    <th><?php esc_html_e( 'Klucz', 'estate-office' ); ?></th>
                                    <th><?php esc_html_e( 'Typ', 'estate-office' ); ?></th>
                                    <th><?php esc_html_e( 'Opcje', 'estate-office' ); ?></th>
                                    <th><?php esc_html_e( 'Wymagane', 'estate-office' ); ?></th>
                                    <th><?php esc_html_e( 'Akcje', 'estate-office' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( empty( $fields ) ) : ?>
                                    <tr class="no-items"><td colspan="6"><?php esc_html_e( 'Brak zdefiniowanych pól.', 'estate-office' ); ?></td></tr>
                                <?php else : ?>
                                    <?php foreach ( $fields as $index => $field ) : ?>
                                        <?php $this->render_dynamic_row( $group_key, $index, $field ); ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <p>
                            <button type="button" class="button button-secondary estate-office-add-field" data-group="<?php echo esc_attr( $group_key ); ?>">
                                <?php esc_html_e( 'Dodaj pole', 'estate-office' ); ?>
                            </button>
                        </p>
                    </div>
                <?php endforeach; ?>

                <?php submit_button( __( 'Zapisz ustawienia', 'estate-office' ) ); ?>
            </form>
        </div>
        <script type="text/html" id="tmpl-estate-office-dynamic-row">
            <?php $this->render_dynamic_row_template(); ?>
        </script>
        <?php
    }

    /**
     * Render success notice.
     */
    protected function render_notice(): void {
        if ( isset( $_GET['status'] ) && 'saved' === $_GET['status'] ) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html__( 'Ustawienia zostały zapisane.', 'estate-office' )
            );
        }
    }

    /**
     * Render dynamic field row.
     */
    protected function render_dynamic_row( string $group, int $index, array $field ): void {
        ?>
        <tr>
            <td><input type="text" name="<?php echo esc_attr( $group ); ?>_custom_fields[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $field['label'] ?? '' ); ?>" required /></td>
            <td><input type="text" name="<?php echo esc_attr( $group ); ?>_custom_fields[<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $field['key'] ?? '' ); ?>" required pattern="[a-zA-Z0-9_]+" /></td>
            <td>
                <select name="<?php echo esc_attr( $group ); ?>_custom_fields[<?php echo esc_attr( $index ); ?>][type]">
                    <?php echo self::render_field_type_options( $field['type'] ?? 'text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </select>
            </td>
            <td><input type="text" name="<?php echo esc_attr( $group ); ?>_custom_fields[<?php echo esc_attr( $index ); ?>][options]" value="<?php echo esc_attr( $field['options'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Opcje rozdzielone przecinkami', 'estate-office' ); ?>" /></td>
            <td><label><input type="checkbox" name="<?php echo esc_attr( $group ); ?>_custom_fields[<?php echo esc_attr( $index ); ?>][required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?> /> <?php esc_html_e( 'Tak', 'estate-office' ); ?></label></td>
            <td><button type="button" class="button-link-delete estate-office-remove-field" aria-label="<?php esc_attr_e( 'Usuń pole', 'estate-office' ); ?>">&times;</button></td>
        </tr>
        <?php
    }

    /**
     * Render underscore template for dynamic rows.
     */
    protected function render_dynamic_row_template(): void {
        ?>
        <tr>
            <td><input type="text" name="{{data.group}}_custom_fields[{{data.index}}][label]" required /></td>
            <td><input type="text" name="{{data.group}}_custom_fields[{{data.index}}][key]" pattern="[a-zA-Z0-9_]+" required /></td>
            <td>
                <select name="{{data.group}}_custom_fields[{{data.index}}][type]">
                    <?php echo self::render_field_type_options(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </select>
            </td>
            <td><input type="text" name="{{data.group}}_custom_fields[{{data.index}}][options]" placeholder="<?php esc_attr_e( 'Opcje rozdzielone przecinkami', 'estate-office' ); ?>" /></td>
            <td><label><input type="checkbox" name="{{data.group}}_custom_fields[{{data.index}}][required]" value="1" /> <?php esc_html_e( 'Tak', 'estate-office' ); ?></label></td>
            <td><button type="button" class="button-link-delete estate-office-remove-field" aria-label="<?php esc_attr_e( 'Usuń pole', 'estate-office' ); ?>">&times;</button></td>
        </tr>
        <?php
    }

    /**
     * Render field type options.
     */
    protected static function render_field_type_options( string $selected = 'text' ): string {
        $types = [
            'text'     => __( 'Tekst', 'estate-office' ),
            'textarea' => __( 'Textarea', 'estate-office' ),
            'number'   => __( 'Liczba', 'estate-office' ),
            'select'   => __( 'Lista rozwijana', 'estate-office' ),
            'checkbox' => __( 'Checkbox', 'estate-office' ),
            'date'     => __( 'Data', 'estate-office' ),
        ];

        $options_html = '';
        foreach ( $types as $value => $label ) {
            $options_html .= sprintf(
                '<option value="%1$s" %3$s>%2$s</option>',
                esc_attr( $value ),
                esc_html( $label ),
                selected( $selected, $value, false )
            );
        }

        return $options_html;
    }

    /**
     * Render media uploader control.
     */
    protected function render_media_field( string $name, int $attachment_id ): void {
        $image = $attachment_id ? wp_get_attachment_image( $attachment_id, 'thumbnail', false, [ 'class' => 'estate-office-media-preview' ] ) : '';
        $button_label = $attachment_id ? __( 'Zmień plik', 'estate-office' ) : __( 'Wybierz plik', 'estate-office' );
        ?>
        <div class="estate-office-media-field" data-target="<?php echo esc_attr( $name ); ?>">
            <div class="estate-office-media-preview-wrap"><?php echo $image ? wp_kses_post( $image ) : '<span class="placeholder">' . esc_html__( 'Brak podglądu', 'estate-office' ) . '</span>'; ?></div>
            <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $attachment_id ); ?>" />
            <button type="button" class="button estate-office-media-select"><?php echo esc_html( $button_label ); ?></button>
            <button type="button" class="button-link estate-office-media-remove" data-placeholder="<?php esc_attr_e( 'Brak podglądu', 'estate-office' ); ?>" <?php disabled( ! $attachment_id ); ?>><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
        </div>
        <?php
    }

    /**
     * Get dynamic fields for group.
     */
    public static function get_dynamic_fields( string $group ): array {
        $option = get_option( 'estate_office_' . $group . '_custom_fields', [] );
        if ( empty( $option ) || ! is_array( $option ) ) {
            return [];
        }
        return array_values( array_filter( $option, static function ( $field ) {
            return isset( $field['key'] ) && '' !== $field['key'] && isset( $field['label'] );
        } ) );
    }

    /**
     * Save dynamic fields.
     */
    public static function save_dynamic_fields( string $group, array $fields ): void {
        $sanitized = [];
        foreach ( $fields as $field ) {
            $key = isset( $field['key'] ) ? sanitize_key( $field['key'] ) : '';
            if ( empty( $key ) ) {
                continue;
            }
            $sanitized[] = [
                'key'      => $key,
                'label'    => sanitize_text_field( $field['label'] ?? '' ),
                'type'     => sanitize_key( $field['type'] ?? 'text' ),
                'options'  => sanitize_text_field( $field['options'] ?? '' ),
                'required' => ! empty( $field['required'] ) ? 1 : 0,
            ];
        }

        update_option( 'estate_office_' . $group . '_custom_fields', $sanitized );
    }

    /**
     * Filter submission for dynamic fields.
     */
    public static function filter_dynamic_submission( string $group, array $values ): array {
        $fields = self::get_dynamic_fields( $group );
        if ( empty( $fields ) ) {
            return [];
        }

        $allowed = wp_list_pluck( $fields, 'key' );
        $filtered = [];
        foreach ( $values as $key => $value ) {
            $sanitized_key = sanitize_key( $key );
            if ( ! in_array( $sanitized_key, $allowed, true ) ) {
                continue;
            }

            if ( is_array( $value ) ) {
                $filtered[ $sanitized_key ] = array_map( 'sanitize_text_field', wp_unslash( $value ) );
            } else {
                $filtered[ $sanitized_key ] = sanitize_text_field( wp_unslash( $value ) );
            }
        }

        return $filtered;
    }
}
