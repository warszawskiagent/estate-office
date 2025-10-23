<?php
/**
 * Front CRM layout.
 *
 * @var string               $brand_badge_html
 * @var array<string,string> $tabs
 * @var string               $active_tab
 * @var string               $base_url
 * @var string               $search
 * @var string               $search_placeholder
 * @var string               $tab_content
 * @var string               $property_filters_html
 * @var string               $add_contract_url
 */
?>
<div class="estate-office-crm" data-active-tab="<?php echo esc_attr( $active_tab ); ?>">
    <div class="estate-office-crm-header">
        <div class="estate-office-crm-heading">
            <?php echo $brand_badge_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <h2><?php esc_html_e( 'EstateOffice CRM', 'estate-office' ); ?></h2>
        </div>
        <a class="estate-office-button" href="<?php echo esc_url( $add_contract_url ); ?>">
            <?php esc_html_e( 'Dodaj nową umowę', 'estate-office' ); ?>
        </a>
    </div>
    <nav class="estate-office-crm-nav" aria-label="<?php esc_attr_e( 'Nawigacja CRM', 'estate-office' ); ?>">
        <ul>
            <?php foreach ( $tabs as $key => $label ) : ?>
                <li class="<?php echo $key === $active_tab ? 'is-active' : ''; ?>">
                    <a href="<?php echo esc_url( add_query_arg( [ 'eo_tab' => $key, 'eo_search' => '' ], $base_url ) ); ?>"><?php echo esc_html( $label ); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <form method="get" class="estate-office-crm-search">
        <input type="hidden" name="eo_tab" value="<?php echo esc_attr( $active_tab ); ?>" />
        <input type="hidden" name="eo_page" value="1" />
        <label for="estate-office-crm-search" class="screen-reader-text"><?php esc_html_e( 'Szukaj', 'estate-office' ); ?></label>
        <input type="search" id="estate-office-crm-search" name="eo_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr( $search_placeholder ); ?>" />
        <button type="submit" class="estate-office-button secondary"><?php esc_html_e( 'Szukaj', 'estate-office' ); ?></button>
    </form>
    <div class="estate-office-crm-body">
        <?php if ( ! empty( $property_filters_html ) ) : ?>
            <?php echo $property_filters_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endif; ?>
        <?php echo $tab_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</div>
