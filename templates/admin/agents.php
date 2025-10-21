<?php
/**
 * Agents management page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$agents = get_users(
    [
        'role'    => 'estate_agent',
        'orderby' => 'display_name',
        'order'   => 'ASC',
    ]
);
?>
<div class="wrap estate-office-wrap">
    <h1><?php esc_html_e( 'Agenci', 'estate-office' ); ?></h1>

    <div class="estate-office-toolbar">
        <a class="estate-office-button-primary" href="<?php echo esc_url( admin_url( 'user-new.php?role=estate_agent' ) ); ?>">
            <?php esc_html_e( 'Dodaj nowego agenta', 'estate-office' ); ?>
        </a>
    </div>

    <table class="estate-office-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Agent', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'E-mail', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Telefon', 'estate-office' ); ?></th>
                <th><?php esc_html_e( 'Profil publiczny', 'estate-office' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if ( $agents ) : ?>
            <?php foreach ( $agents as $agent ) :
                $phone = get_user_meta( $agent->ID, 'phone', true );
                $profile_url = get_author_posts_url( $agent->ID );
                ?>
                <tr>
                    <td><?php echo esc_html( $agent->display_name ); ?></td>
                    <td><a href="mailto:<?php echo esc_attr( $agent->user_email ); ?>"><?php echo esc_html( $agent->user_email ); ?></a></td>
                    <td><?php echo esc_html( $phone ?: '—' ); ?></td>
                    <td><a href="<?php echo esc_url( $profile_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Zobacz profil', 'estate-office' ); ?></a></td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="4"><?php esc_html_e( 'Brak agentów.', 'estate-office' ); ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
