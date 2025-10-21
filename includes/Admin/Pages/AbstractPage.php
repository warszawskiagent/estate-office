<?php
namespace EstateOffice\Admin\Pages;

/**
 * Base class for Estate Office admin pages.
 */
abstract class AbstractPage {
    /**
     * Renders the page markup.
     */
    abstract public function render(): void;

    /**
     * Outputs a wrapper header.
     */
    protected function render_header( string $title, string $subtitle = '' ): void {
        echo '<div class="wrap estate-office-admin">';
        echo '<h1 class="estate-office-admin__title">' . esc_html( $title ) . '</h1>';

        if ( $subtitle ) {
            echo '<p class="estate-office-admin__subtitle">' . esc_html( $subtitle ) . '</p>';
        }
    }

    /**
     * Outputs closing wrapper.
     */
    protected function render_footer(): void {
        echo '</div>';
    }
}
