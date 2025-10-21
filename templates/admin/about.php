<?php
/**
 * About page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap estate-office-wrap">
    <h1><?php esc_html_e( 'O wtyczce Estate Office', 'estate-office' ); ?></h1>

    <div class="estate-office-section">
        <p><?php esc_html_e( 'Estate Office CRM to kompleksowe narzędzie do zarządzania nieruchomościami, klientami, umowami i agentami w jednym miejscu. Wtyczka została zaprojektowana z myślą o bezpieczeństwie danych, przejrzystości procesów i intuicyjnej obsłudze.', 'estate-office' ); ?></p>
    </div>

    <div class="estate-office-section">
        <h2><?php esc_html_e( 'Roadmapa', 'estate-office' ); ?></h2>
        <ul>
            <?php if ( ! empty( $roadmap ) ) : ?>
                <?php foreach ( $roadmap as $entry ) : ?>
                    <li><strong><?php echo esc_html( $entry['version'] ?? '' ); ?>:</strong> <?php echo esc_html( $entry['description'] ?? '' ); ?></li>
                <?php endforeach; ?>
            <?php else : ?>
                <li><?php esc_html_e( 'Roadmapa zostanie wkrótce zaktualizowana.', 'estate-office' ); ?></li>
            <?php endif; ?>
        </ul>
    </div>
</div>
