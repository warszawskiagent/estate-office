<?php
/** @var array $settings */
?>
<div class="wrap estate-office-settings">
    <h1><?php esc_html_e( 'Ustawienia Estate Office', 'estate-office' ); ?></h1>

    <?php if ( ! empty( $message ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! empty( $error ) ) : ?>
        <div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=estate-office-settings' ) ); ?>">
        <?php wp_nonce_field( 'estate_office_save_settings', 'estate_office_settings_nonce' ); ?>

        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Integracje', 'estate-office' ); ?></h2>
            <div class="estate-office-field-group">
                <label for="estate-office-google-api"><?php esc_html_e( 'Klucz API Map Google', 'estate-office' ); ?></label>
                <input type="text" id="estate-office-google-api" name="google_maps_api_key" value="<?php echo esc_attr( $settings['google_maps_api_key'] ); ?>" />
            </div>
        </div>

        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Branding', 'estate-office' ); ?></h2>
            <div class="estate-office-field-group estate-office-media-field">
                <label><?php esc_html_e( 'Znak wodny', 'estate-office' ); ?></label>
                <div class="estate-office-media-preview">
                    <?php if ( ! empty( $settings['watermark_attachment'] ) ) : ?>
                        <?php echo wp_get_attachment_image( (int) $settings['watermark_attachment'], 'medium', false, array( 'id' => 'estate-office-watermark-preview' ) ); ?>
                    <?php else : ?>
                        <img id="estate-office-watermark-preview" src="" alt="" style="display:none;" />
                    <?php endif; ?>
                </div>
                <input type="hidden" name="watermark_attachment" id="estate-office-watermark" value="<?php echo (int) $settings['watermark_attachment']; ?>" />
                <button type="button" class="button estate-office-select-media" data-target="estate-office-watermark"><?php esc_html_e( 'Wybierz plik', 'estate-office' ); ?></button>
                <button type="button" class="button button-secondary estate-office-remove-media" data-target="estate-office-watermark"><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
            </div>

            <div class="estate-office-field-group estate-office-media-field">
                <label><?php esc_html_e( 'Logo biura', 'estate-office' ); ?></label>
                <div class="estate-office-media-preview">
                    <?php if ( ! empty( $settings['logo_attachment'] ) ) : ?>
                        <?php echo wp_get_attachment_image( (int) $settings['logo_attachment'], 'medium', false, array( 'id' => 'estate-office-logo-preview' ) ); ?>
                    <?php else : ?>
                        <img id="estate-office-logo-preview" src="" alt="" style="display:none;" />
                    <?php endif; ?>
                </div>
                <input type="hidden" name="logo_attachment" id="estate-office-logo" value="<?php echo (int) $settings['logo_attachment']; ?>" />
                <button type="button" class="button estate-office-select-media" data-target="estate-office-logo"><?php esc_html_e( 'Wybierz plik', 'estate-office' ); ?></button>
                <button type="button" class="button button-secondary estate-office-remove-media" data-target="estate-office-logo"><?php esc_html_e( 'Usuń', 'estate-office' ); ?></button>
            </div>
        </div>

        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Pola nieruchomości', 'estate-office' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Zdefiniuj dostępne pola dla ofert nieruchomości. Używaj unikalnych identyfikatorów (np. cena, metraz).', 'estate-office' ); ?></p>
            <div class="estate-office-dynamic-list" data-template="estate-office-property-template">
                <?php foreach ( $settings['property_fields'] as $field ) : ?>
                    <div class="estate-office-dynamic-item">
                        <input type="text" name="property_fields[]" value="<?php echo esc_attr( $field ); ?>" />
                        <button type="button" class="button-link estate-office-remove-row">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button estate-office-add-row" data-template="estate-office-property-template"><?php esc_html_e( 'Dodaj pole', 'estate-office' ); ?></button>
        </div>

        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Pola umów', 'estate-office' ); ?></h2>
            <div class="estate-office-dynamic-list" data-template="estate-office-contract-template">
                <?php foreach ( $settings['contract_fields'] as $field ) : ?>
                    <div class="estate-office-dynamic-item">
                        <input type="text" name="contract_fields[]" value="<?php echo esc_attr( $field ); ?>" />
                        <button type="button" class="button-link estate-office-remove-row">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button estate-office-add-row" data-template="estate-office-contract-template"><?php esc_html_e( 'Dodaj pole', 'estate-office' ); ?></button>
        </div>

        <div class="estate-office-card">
            <h2><?php esc_html_e( 'Pola klientów', 'estate-office' ); ?></h2>
            <div class="estate-office-dynamic-list" data-template="estate-office-client-template">
                <?php foreach ( $settings['client_fields'] as $field ) : ?>
                    <div class="estate-office-dynamic-item">
                        <input type="text" name="client_fields[]" value="<?php echo esc_attr( $field ); ?>" />
                        <button type="button" class="button-link estate-office-remove-row">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button estate-office-add-row" data-template="estate-office-client-template"><?php esc_html_e( 'Dodaj pole', 'estate-office' ); ?></button>
        </div>

        <p>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Zapisz ustawienia', 'estate-office' ); ?></button>
        </p>
    </form>
</div>

<script type="text/html" id="estate-office-property-template">
    <div class="estate-office-dynamic-item">
        <input type="text" name="property_fields[]" value="" />
        <button type="button" class="button-link estate-office-remove-row">&times;</button>
    </div>
</script>
<script type="text/html" id="estate-office-contract-template">
    <div class="estate-office-dynamic-item">
        <input type="text" name="contract_fields[]" value="" />
        <button type="button" class="button-link estate-office-remove-row">&times;</button>
    </div>
</script>
<script type="text/html" id="estate-office-client-template">
    <div class="estate-office-dynamic-item">
        <input type="text" name="client_fields[]" value="" />
        <button type="button" class="button-link estate-office-remove-row">&times;</button>
    </div>
</script>
