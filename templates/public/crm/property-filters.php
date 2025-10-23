<?php
/**
 * Property filters for front CRM.
 *
 * @var string               $base_url
 * @var string               $search
 * @var array<string,mixed>  $filters
 * @var array<string,string> $transaction_options
 * @var array<int,string>    $property_types
 */
?>
<form method="get" action="<?php echo esc_url( $base_url ); ?>" class="estate-office-filters" data-eo-filters="properties">
    <input type="hidden" name="eo_tab" value="properties" />
    <input type="hidden" name="eo_page" value="1" />
    <input type="hidden" name="eo_search" value="<?php echo esc_attr( $search ); ?>" />
    <div class="estate-office-filters__fields">
        <label class="estate-office-field">
            <span><?php esc_html_e( 'Typ transakcji', 'estate-office' ); ?></span>
            <select name="eo_transaction" data-auto-submit="1">
                <option value=""><?php esc_html_e( 'Wszystkie', 'estate-office' ); ?></option>
                <?php foreach ( $transaction_options as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['transaction'] ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="estate-office-field">
            <span><?php esc_html_e( 'Rodzaj nieruchomości', 'estate-office' ); ?></span>
            <select name="eo_property_type" data-auto-submit="1">
                <option value=""><?php esc_html_e( 'Dowolny', 'estate-office' ); ?></option>
                <?php foreach ( $property_types as $type ) : ?>
                    <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filters['property_type'] ?? '', $type ); ?>><?php echo esc_html( $type ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="estate-office-field estate-office-filters__range" data-eo-price-range>
            <span><?php esc_html_e( 'Zakres cen', 'estate-office' ); ?></span>
            <div class="estate-office-filters__range-inputs">
                <label>
                    <span class="screen-reader-text"><?php esc_html_e( 'Cena minimalna', 'estate-office' ); ?></span>
                    <input type="number" name="eo_price_min" value="<?php echo esc_attr( $filters['price_min_raw'] ?? '' ); ?>" min="0" step="1000" data-filter-min />
                </label>
                <label>
                    <span class="screen-reader-text"><?php esc_html_e( 'Cena maksymalna', 'estate-office' ); ?></span>
                    <input type="number" name="eo_price_max" value="<?php echo esc_attr( $filters['price_max_raw'] ?? '' ); ?>" min="0" step="1000" data-filter-max />
                </label>
            </div>
            <p class="estate-office-filters__range-display" data-filter-display></p>
        </div>
    </div>
    <div class="estate-office-filters__actions">
        <button type="submit" class="estate-office-button secondary"><?php esc_html_e( 'Filtruj', 'estate-office' ); ?></button>
        <button type="button" class="estate-office-button tertiary" data-filter-reset><?php esc_html_e( 'Wyczyść', 'estate-office' ); ?></button>
    </div>
</form>
