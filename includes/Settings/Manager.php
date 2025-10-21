<?php
namespace EstateOffice\Settings;

/**
 * Handles registration of plugin settings.
 */
class Manager {
    public const OPTION_KEY = 'estate_office_settings';

    /**
     * Registers the settings and sections.
     */
    public function register(): void {
        register_setting( self::OPTION_KEY, self::OPTION_KEY, [ $this, 'sanitize' ] );

        add_settings_section(
            'estate_office_general',
            __( 'Integracje i branding', 'estate-office' ),
            '__return_null',
            self::OPTION_KEY
        );

        add_settings_field(
            'google_maps_api_key',
            __( 'Klucz API Google Maps', 'estate-office' ),
            [ $this, 'render_text_field' ],
            self::OPTION_KEY,
            'estate_office_general',
            [
                'label_for' => 'google_maps_api_key',
                'type'      => 'text',
                'option'    => 'google_maps_api_key',
            ]
        );

        add_settings_field(
            'watermark',
            __( 'Znak wodny', 'estate-office' ),
            [ $this, 'render_media_field' ],
            self::OPTION_KEY,
            'estate_office_general',
            [
                'label_for' => 'watermark',
                'option'    => 'watermark',
            ]
        );

        add_settings_field(
            'brand_logo',
            __( 'Logo biura', 'estate-office' ),
            [ $this, 'render_media_field' ],
            self::OPTION_KEY,
            'estate_office_general',
            [
                'label_for' => 'brand_logo',
                'option'    => 'brand_logo',
            ]
        );

        add_settings_section(
            'estate_office_dynamic_fields',
            __( 'Dynamiczne pola formularzy', 'estate-office' ),
            [ $this, 'render_dynamic_fields_intro' ],
            self::OPTION_KEY
        );

        add_settings_field(
            'property_fields',
            __( 'Pola nieruchomości', 'estate-office' ),
            [ $this, 'render_dynamic_fields' ],
            self::OPTION_KEY,
            'estate_office_dynamic_fields',
            [
                'option' => 'property_fields',
            ]
        );

        add_settings_field(
            'contract_fields',
            __( 'Pola umów', 'estate-office' ),
            [ $this, 'render_dynamic_fields' ],
            self::OPTION_KEY,
            'estate_office_dynamic_fields',
            [
                'option' => 'contract_fields',
            ]
        );

        add_settings_field(
            'client_fields',
            __( 'Pola klientów', 'estate-office' ),
            [ $this, 'render_dynamic_fields' ],
            self::OPTION_KEY,
            'estate_office_dynamic_fields',
            [
                'option' => 'client_fields',
            ]
        );
    }

    /**
     * Sanitizes the settings payload.
     */
    public function sanitize( $input ): array {
        if ( ! is_array( $input ) ) {
            $input = [];
        }

        $current = $this->get_settings();

        $current['google_maps_api_key'] = sanitize_text_field( $input['google_maps_api_key'] ?? '' );
        $current['watermark']           = absint( $input['watermark'] ?? 0 );
        $current['brand_logo']          = absint( $input['brand_logo'] ?? 0 );

        $current['property_fields'] = $this->sanitize_dynamic_fields( $input['property_fields'] ?? [] );
        $current['contract_fields'] = $this->sanitize_dynamic_fields( $input['contract_fields'] ?? [] );
        $current['client_fields']   = $this->sanitize_dynamic_fields( $input['client_fields'] ?? [] );

        return $current;
    }

    /**
     * Retrieves the stored settings.
     */
    public function get_settings(): array {
        $defaults = [
            'google_maps_api_key' => '',
            'watermark'           => 0,
            'brand_logo'          => 0,
            'property_fields'     => [],
            'contract_fields'     => [],
            'client_fields'       => [],
        ];

        $options = get_option( self::OPTION_KEY, [] );

        if ( ! is_array( $options ) ) {
            $options = [];
        }

        return wp_parse_args( $options, $defaults );
    }

    /**
     * Sanitizes repeater fields definitions.
     */
    private function sanitize_dynamic_fields( $fields ): array {
        if ( ! is_array( $fields ) ) {
            return [];
        }

        $sanitized = [];

        foreach ( $fields as $field ) {
            if ( empty( $field['label'] ) ) {
                continue;
            }

            $sanitized[] = [
                'label' => sanitize_text_field( $field['label'] ?? '' ),
                'type'  => sanitize_key( $field['type'] ?? 'text' ),
            ];
        }

        return $sanitized;
    }

    /**
     * Renders a simple text input field.
     */
    public function render_text_field( array $args ): void {
        $settings = $this->get_settings();
        $option   = $args['option'];
        $value    = $settings[ $option ] ?? '';
        ?>
        <input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( self::OPTION_KEY . '[' . $option . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" autocomplete="off" />
        <?php
    }

    /**
     * Renders an attachment selector.
     */
    public function render_media_field( array $args ): void {
        $settings = $this->get_settings();
        $option   = $args['option'];
        $value    = absint( $settings[ $option ] ?? 0 );
        $url      = $value ? wp_get_attachment_image_url( $value, 'medium' ) : '';
        ?>
        <div class="estate-office-media-field" data-target="<?php echo esc_attr( self::OPTION_KEY . '[' . $option . ']' ); ?>">
            <div class="estate-office-media-preview">
                <?php if ( $url ) : ?>
                    <img src="<?php echo esc_url( $url ); ?>" alt="" />
                <?php else : ?>
                    <span class="description"><?php esc_html_e( 'Brak wybranego pliku', 'estate-office' ); ?></span>
                <?php endif; ?>
            </div>
            <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY . '[' . $option . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" />
            <button type="button" class="button estate-office-media-upload" data-label="<?php echo esc_attr__( 'Wybierz plik', 'estate-office' ); ?>"><?php esc_html_e( 'Wybierz plik', 'estate-office' ); ?></button>
            <button type="button" class="button-link estate-office-media-remove"><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
        </div>
        <?php
    }

    /**
     * Outputs introduction for dynamic fields.
     */
    public function render_dynamic_fields_intro(): void {
        echo '<p>' . esc_html__( 'Dodaj niestandardowe pola, które zostaną wyświetlone w formularzach nieruchomości, umów oraz klientów.', 'estate-office' ) . '</p>';
    }

    /**
     * Renders a repeater list of dynamic fields.
     */
    public function render_dynamic_fields( array $args ): void {
        $settings = $this->get_settings();
        $option   = $args['option'];
        $fields   = $settings[ $option ] ?? [];
        ?>
        <div class="estate-office-dynamic-fields" data-name="<?php echo esc_attr( self::OPTION_KEY . '[' . $option . ']' ); ?>">
            <template class="estate-office-dynamic-template">
                <div class="estate-office-dynamic-row">
                    <label>
                        <span><?php esc_html_e( 'Etykieta pola', 'estate-office' ); ?></span>
                        <input type="text" name="__NAME__[__INDEX__][label]" value="" />
                    </label>
                    <label>
                        <span><?php esc_html_e( 'Typ pola', 'estate-office' ); ?></span>
                        <select name="__NAME__[__INDEX__][type]">
                            <option value="text"><?php esc_html_e( 'Tekst', 'estate-office' ); ?></option>
                            <option value="number"><?php esc_html_e( 'Liczba', 'estate-office' ); ?></option>
                            <option value="textarea"><?php esc_html_e( 'Tekst długi', 'estate-office' ); ?></option>
                            <option value="select"><?php esc_html_e( 'Lista wyboru', 'estate-office' ); ?></option>
                            <option value="checkbox"><?php esc_html_e( 'Checkbox', 'estate-office' ); ?></option>
                        </select>
                    </label>
                    <button type="button" class="button-link estate-office-dynamic-remove"><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
                </div>
            </template>
            <div class="estate-office-dynamic-container">
                <?php foreach ( $fields as $index => $field ) : ?>
                    <div class="estate-office-dynamic-row">
                        <label>
                            <span><?php esc_html_e( 'Etykieta pola', 'estate-office' ); ?></span>
                            <input type="text" name="<?php echo esc_attr( self::OPTION_KEY . '[' . $option . ']' ); ?>[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $field['label'] ?? '' ); ?>" />
                        </label>
                        <label>
                            <span><?php esc_html_e( 'Typ pola', 'estate-office' ); ?></span>
                            <select name="<?php echo esc_attr( self::OPTION_KEY . '[' . $option . ']' ); ?>[<?php echo esc_attr( $index ); ?>][type]">
                                <option value="text" <?php selected( $field['type'] ?? '', 'text' ); ?>><?php esc_html_e( 'Tekst', 'estate-office' ); ?></option>
                                <option value="number" <?php selected( $field['type'] ?? '', 'number' ); ?>><?php esc_html_e( 'Liczba', 'estate-office' ); ?></option>
                                <option value="textarea" <?php selected( $field['type'] ?? '', 'textarea' ); ?>><?php esc_html_e( 'Tekst długi', 'estate-office' ); ?></option>
                                <option value="select" <?php selected( $field['type'] ?? '', 'select' ); ?>><?php esc_html_e( 'Lista wyboru', 'estate-office' ); ?></option>
                                <option value="checkbox" <?php selected( $field['type'] ?? '', 'checkbox' ); ?>><?php esc_html_e( 'Checkbox', 'estate-office' ); ?></option>
                            </select>
                        </label>
                        <button type="button" class="button-link estate-office-dynamic-remove"><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button estate-office-dynamic-add"><?php esc_html_e( 'Dodaj pole', 'estate-office' ); ?></button>
        </div>
        <?php
    }
}
