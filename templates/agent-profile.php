<?php
/**
 * Template for Estate Office agent profile.
 */

global $estate_office_agent_context;

$context    = is_array( $estate_office_agent_context ?? null ) ? $estate_office_agent_context : [];
$agent      = isset( $context['agent'] ) && is_array( $context['agent'] ) ? $context['agent'] : [];
$properties = isset( $context['properties'] ) && is_array( $context['properties'] ) ? $context['properties'] : [];

get_header();
?>
<main id="primary" class="estate-office-agent">
    <div class="estate-office-agent__header">
        <div class="estate-office-agent__photo">
            <?php if ( ! empty( $agent['photo'] ) ) : ?>
                <img src="<?php echo esc_url( $agent['photo'] ); ?>" alt="<?php echo esc_attr( $agent['name'] ?? '' ); ?>" />
            <?php else : ?>
                <span class="description"><?php esc_html_e( 'Brak zdjęcia', 'estate-office' ); ?></span>
            <?php endif; ?>
        </div>
        <div class="estate-office-agent__info">
            <h1 class="estate-office-agent__name"><?php echo esc_html( $agent['name'] ?? '' ); ?></h1>
            <div class="estate-office-agent__contact">
                <?php if ( ! empty( $agent['phone'] ) ) : ?>
                    <?php
                    $phone_href = ! empty( $agent['phone_href'] ) ? $agent['phone_href'] : preg_replace( '/[^0-9+]/', '', (string) $agent['phone'] );
                    ?>
                    <?php if ( $phone_href ) : ?>
                        <a href="tel:<?php echo esc_attr( $phone_href ); ?>"><?php echo esc_html( $agent['phone'] ); ?></a>
                    <?php else : ?>
                        <span><?php echo esc_html( $agent['phone'] ); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ( ! empty( $agent['email'] ) ) : ?>
                    <a href="mailto:<?php echo esc_attr( $agent['email'] ); ?>"><?php echo esc_html( $agent['email'] ); ?></a>
                <?php endif; ?>
            </div>
            <?php if ( ! empty( $agent['bio'] ) ) : ?>
                <div class="estate-office-agent__bio"><?php echo wp_kses_post( wpautop( $agent['bio'] ) ); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( ! empty( $properties ) ) : ?>
        <section class="estate-office-agent__properties">
            <h2><?php esc_html_e( 'Oferty prowadzone przez agenta', 'estate-office' ); ?></h2>
            <div class="estate-office-agent__properties-grid">
                <?php foreach ( $properties as $property ) : ?>
                    <article class="estate-office-offer-card estate-office-agent__property-card">
                        <h5 class="estate-office-offer-card__title">
                            <a href="<?php echo esc_url( $property['permalink'] ?? '#' ); ?>">
                                <?php echo esc_html( $property['reference'] ?? '' ); ?>
                            </a>
                        </h5>
                        <?php if ( ! empty( $property['address'] ) ) : ?>
                            <p class="estate-office-offer-card__address"><?php echo esc_html( $property['address'] ); ?></p>
                        <?php endif; ?>
                        <ul class="estate-office-offer-card__meta">
                            <?php if ( ! empty( $property['price'] ) ) : ?>
                                <li><strong><?php esc_html_e( 'Cena:', 'estate-office' ); ?></strong> <?php echo esc_html( $property['price'] ); ?></li>
                            <?php endif; ?>
                            <?php if ( ! empty( $property['area'] ) ) : ?>
                                <li><strong><?php esc_html_e( 'Metraż:', 'estate-office' ); ?></strong> <?php echo esc_html( $property['area'] ); ?></li>
                            <?php endif; ?>
                            <?php if ( ! empty( $property['rooms'] ) ) : ?>
                                <li><strong><?php esc_html_e( 'Pokoje:', 'estate-office' ); ?></strong> <?php echo esc_html( (string) $property['rooms'] ); ?></li>
                            <?php endif; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php else : ?>
        <p class="estate-office-message"><?php esc_html_e( 'Ten agent nie posiada jeszcze ofert przeznaczonych do prezentacji na stronie.', 'estate-office' ); ?></p>
    <?php endif; ?>
</main>
<?php
get_footer();
?>
