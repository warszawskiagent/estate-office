<?php
/**
 * Agent contact card.
 *
 * @var array<string,mixed>|null $agent
 */
if ( empty( $agent ) ) {
    return;
}

$photo_id  = isset( $agent['photo_id'] ) ? (int) $agent['photo_id'] : 0;
$name      = $agent['name'] ?? '';
$phone     = $agent['phone'] ?? '';
$phone_raw = $agent['phone_raw'] ?? '';
$email     = $agent['email'] ?? '';
$profile   = $agent['profile'] ?? '';
?>
<section class="estate-office-offer-agent">
    <h2><?php esc_html_e( 'Kontakt z agentem', 'estate-office' ); ?></h2>
    <div class="estate-office-offer-agent-card">
        <?php if ( $photo_id ) : ?>
            <div class="estate-office-offer-agent-photo"><?php echo wp_get_attachment_image( $photo_id, 'medium' ); ?></div>
        <?php endif; ?>
        <div class="estate-office-offer-agent-details">
            <?php if ( $name ) : ?>
                <p class="estate-office-offer-agent-name"><?php echo esc_html( $name ); ?></p>
            <?php endif; ?>
            <?php if ( $phone ) : ?>
                <p><a href="tel:<?php echo esc_attr( $phone_raw ?: preg_replace( '/\s+/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></p>
            <?php endif; ?>
            <?php if ( $email ) : ?>
                <p><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></p>
            <?php endif; ?>
            <?php if ( $profile ) : ?>
                <p><a class="estate-office-button tertiary" href="<?php echo esc_url( $profile ); ?>"><?php esc_html_e( 'Zobacz profil agenta', 'estate-office' ); ?></a></p>
            <?php endif; ?>
        </div>
    </div>
</section>
