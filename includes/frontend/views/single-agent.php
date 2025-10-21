<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$agent = get_query_var( 'estate_office_agent_data' );
if ( empty( $agent ) || ! is_array( $agent ) ) {
    return;
}

$listings = $agent['listings'];
$offers   = $agent['offers'];
?>
<div class="estate-office-agent-profile">
    <header class="estate-office-agent-header">
        <?php if ( ! empty( $agent['avatar_html'] ) ) : ?>
            <div class="estate-office-agent-photo">
                <?php echo $agent['avatar_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>
        <div class="estate-office-agent-details">
            <h1 class="estate-office-agent-name"><?php echo esc_html( $agent['name'] ); ?></h1>
            <ul class="estate-office-agent-contact">
                <?php if ( ! empty( $agent['phone'] ) ) : ?>
                    <li>
                        <strong><?php esc_html_e( 'Telefon:', 'estate-office' ); ?></strong>
                        <a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $agent['phone'] ) ); ?>"><?php echo esc_html( $agent['phone'] ); ?></a>
                    </li>
                <?php endif; ?>
                <?php if ( ! empty( $agent['email'] ) ) : ?>
                    <li>
                        <strong><?php esc_html_e( 'E-mail:', 'estate-office' ); ?></strong>
                        <a href="mailto:<?php echo esc_attr( $agent['email'] ); ?>"><?php echo esc_html( $agent['email'] ); ?></a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="estate-office-agent-actions">
                <?php if ( ! empty( $agent['phone'] ) ) : ?>
                    <a class="estate-office-agent-button" href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $agent['phone'] ) ); ?>"><?php esc_html_e( 'Zadzwoń do agenta', 'estate-office' ); ?></a>
                <?php endif; ?>
                <?php if ( ! empty( $agent['email'] ) ) : ?>
                    <a class="estate-office-agent-button" href="mailto:<?php echo esc_attr( $agent['email'] ); ?>"><?php esc_html_e( 'Wyślij wiadomość', 'estate-office' ); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <?php if ( ! empty( $agent['bio'] ) ) : ?>
        <section class="estate-office-agent-bio">
            <h2><?php esc_html_e( 'O agencie', 'estate-office' ); ?></h2>
            <?php echo wpautop( wp_kses_post( $agent['bio'] ) ); ?>
        </section>
    <?php endif; ?>

    <section class="estate-office-agent-offers">
        <h2><?php esc_html_e( 'Oferty agenta', 'estate-office' ); ?></h2>
        <?php if ( ! empty( $offers ) ) : ?>
            <?php foreach ( $offers as $transaction => $entries ) :
                $label = $agent['transaction_labels'][ $transaction ] ?? $transaction;
                ?>
                <div class="estate-office-agent-offers-group" data-transaction="<?php echo esc_attr( $transaction ); ?>">
                    <h3><?php echo esc_html( sprintf( __( 'Oferty na %s', 'estate-office' ), $label ) ); ?></h3>
                    <div class="estate-office-property-grid">
                        <?php foreach ( $entries as $entry ) : ?>
                            <?php echo $listings->get_offer_card_html( $entry ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p class="estate-office-agent-empty"><?php esc_html_e( 'Agent nie ma jeszcze ofert opublikowanych na stronie WWW.', 'estate-office' ); ?></p>
        <?php endif; ?>
    </section>
</div>
