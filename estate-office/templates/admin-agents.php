<?php
if (! defined('ABSPATH')) {
    exit;
}

$agents = get_users([
    'role' => 'estateoffice_agent',
    'orderby' => 'display_name',
    'order' => 'ASC',
]);
?>
<div class="wrap">
    <h1>Agenci</h1>
    <p>Wersja 0.1 udostępnia widok listy agentów. Rozbudowane profile będą dodane w wersji 0.2+.</p>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Imię i nazwisko</th>
                <th>E-mail</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($agents)) : ?>
                <tr><td colspan="3">Brak agentów.</td></tr>
            <?php else : ?>
                <?php foreach ($agents as $agent) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $agent->ID); ?></td>
                        <td><?php echo esc_html($agent->display_name); ?></td>
                        <td><?php echo esc_html($agent->user_email); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
