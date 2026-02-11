<?php
if (! defined('ABSPATH')) {
    exit;
}

$agents = get_users([
    'role' => 'estateoffice_agent',
    'orderby' => 'display_name',
    'order' => 'ASC',
]);

$status = isset($_GET['status']) ? sanitize_key(wp_unslash((string) $_GET['status'])) : '';
$message = isset($_GET['message']) ? sanitize_text_field(rawurldecode(wp_unslash((string) $_GET['message']))) : '';
?>
<div class="wrap">
    <h1>Agenci</h1>

    <?php if ($status === 'success' && $message !== '') : ?>
        <div class="notice notice-success"><p><?php echo esc_html($message); ?></p></div>
    <?php elseif ($status === 'error' && $message !== '') : ?>
        <div class="notice notice-error"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <h2>Dodaj nowego agenta</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('estateoffice_create_agent'); ?>
        <input type="hidden" name="action" value="estateoffice_create_agent" />

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="first_name">Imię</label></th>
                <td><input type="text" name="first_name" id="first_name" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="last_name">Nazwisko</label></th>
                <td><input type="text" name="last_name" id="last_name" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="email">E-mail</label></th>
                <td><input type="email" name="email" id="email" class="regular-text" required /></td>
            </tr>
            <tr>
                <th scope="row"><label for="phone">Telefon</label></th>
                <td><input type="text" name="phone" id="phone" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="bio">Opis/Biografia</label></th>
                <td><textarea name="bio" id="bio" class="large-text" rows="4"></textarea></td>
            </tr>
        </table>

        <?php submit_button('Utwórz agenta'); ?>
    </form>

    <hr />

    <h2>Lista agentów</h2>
    <table class="widefat striped">
        <thead>
        <tr>
            <th>ID</th>
            <th>Imię i nazwisko</th>
            <th>E-mail</th>
            <th>Telefon</th>
            <th>Edycja</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($agents)) : ?>
            <tr><td colspan="5">Brak agentów.</td></tr>
        <?php else : ?>
            <?php foreach ($agents as $agent) : ?>
                <tr>
                    <td><?php echo esc_html((string) $agent->ID); ?></td>
                    <td><?php echo esc_html($agent->display_name); ?></td>
                    <td><?php echo esc_html($agent->user_email); ?></td>
                    <td><?php echo esc_html((string) get_user_meta($agent->ID, 'estateoffice_phone', true)); ?></td>
                    <td><a href="<?php echo esc_url(get_edit_user_link($agent->ID)); ?>">Edytuj użytkownika</a></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
