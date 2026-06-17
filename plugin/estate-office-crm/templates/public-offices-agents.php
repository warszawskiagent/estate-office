<?php
if (! defined('ABSPATH')) {
    exit;
}

$global_office_name = is_string($office_name ?? null) ? (string) $office_name : '';
$global_office_logo_url = is_string($office_logo_url ?? null) ? (string) $office_logo_url : '';
$public_unassigned_agents = isset($unassigned_agents) && is_array($unassigned_agents) ? $unassigned_agents : [];
?>
<div class="eocrm-wrap eocrm-public-wrap eocrm-team-wrap">
    <header class="eocrm-team-hero">
        <div class="eocrm-team-hero-content">
            <h2><?php echo esc_html((string) ($offices_agents_page_title ?? 'Biura i Agenci')); ?></h2>
            <p><?php echo esc_html((string) ($offices_agents_page_intro ?? 'Poznaj nasz zespol doradcow nieruchomosci i sprawdz, kto prowadzi oferty w Twojej okolicy.')); ?></p>
            <?php if ($global_office_name !== '') : ?>
                <p class="eocrm-team-brand"><?php echo esc_html($global_office_name); ?></p>
            <?php endif; ?>
        </div>
        <?php if ($global_office_logo_url !== '') : ?>
            <div class="eocrm-team-hero-logo">
                <img src="<?php echo esc_url($global_office_logo_url); ?>" alt="Logo biura" class="eocrm-office-logo">
            </div>
        <?php endif; ?>
    </header>

    <?php if (empty($offices) && empty($public_unassigned_agents)) : ?>
        <div class="eocrm-card">
            <p>Brak opublikowanych danych biur i agentow.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($offices as $office) : ?>
        <?php
        $office_id = isset($office['id']) ? (int) $office['id'] : 0;
        $office_agents = isset($grouped_agents[$office_id]) && is_array($grouped_agents[$office_id]) ? $grouped_agents[$office_id] : [];
        $office_logo = isset($office['logo_url']) ? (string) $office['logo_url'] : '';
        $office_address = trim((string) ($office['address_line'] ?? '') . ', ' . (string) ($office['postal_code'] ?? '') . ' ' . (string) ($office['city'] ?? ''));
        $office_description = trim((string) ($office['description'] ?? ''));
        ?>
        <section class="eocrm-card eocrm-office-section" id="eocrm-office-<?php echo (int) $office_id; ?>">
            <div class="eocrm-office-head">
                <div>
                    <p class="eocrm-team-kicker">Biuro</p>
                    <h3><?php echo esc_html((string) ($office['office_name'] ?? 'Biuro')); ?></h3>
                    <?php if ($office_address !== ',') : ?>
                        <p><strong>Adres:</strong> <?php echo esc_html(trim($office_address, ', ')); ?></p>
                    <?php endif; ?>
                    <?php if (! empty($office['office_phone'])) : ?>
                        <p><strong>Telefon:</strong> <?php echo esc_html((string) $office['office_phone']); ?></p>
                    <?php endif; ?>
                    <?php if (! empty($office['office_email'])) : ?>
                        <p><strong>E-mail:</strong> <a href="mailto:<?php echo esc_attr((string) $office['office_email']); ?>"><?php echo esc_html((string) $office['office_email']); ?></a></p>
                    <?php endif; ?>
                </div>
                <?php if ($office_logo !== '') : ?>
                    <img src="<?php echo esc_url($office_logo); ?>" alt="Logo biura" class="eocrm-office-logo">
                <?php endif; ?>
            </div>

            <div class="eocrm-description">
                <?php echo $office_description !== '' ? wp_kses_post(wpautop($office_description)) : '<p>Brak opisu biura.</p>'; ?>
            </div>

            <?php if (empty($office_agents)) : ?>
                <p class="eocrm-muted">Brak przypisanych agentow do tego biura.</p>
            <?php else : ?>
                <div class="eocrm-team-grid eocrm-team-list-vertical">
                    <?php foreach ($office_agents as $agent) : ?>
                        <?php
                        $agent_photo_url = isset($agent['photo_url']) ? (string) $agent['photo_url'] : '';
                        $agent_address = trim((string) ($agent['address_line'] ?? '') . ', ' . (string) ($agent['postal_code'] ?? '') . ' ' . (string) ($agent['city'] ?? ''));
                        ?>
                        <article class="eocrm-team-card eocrm-team-card-horizontal" id="eocrm-agent-<?php echo (int) ($agent['ID'] ?? 0); ?>">
                            <div class="eocrm-team-card-media eocrm-team-card-media-horizontal">
                                <?php if ($agent_photo_url !== '') : ?>
                                    <img src="<?php echo esc_url($agent_photo_url); ?>" alt="Zdjecie agenta" class="eocrm-team-photo">
                                <?php else : ?>
                                    <div class="eocrm-team-photo eocrm-team-photo-placeholder"><?php echo esc_html(substr((string) ($agent['display_name'] ?? 'A'), 0, 1)); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="eocrm-team-card-body eocrm-team-card-body-horizontal">
                                <h4><?php echo esc_html((string) ($agent['display_name'] ?? 'Agent')); ?></h4>
                                <?php if (! empty($agent['is_manager'])) : ?>
                                    <p class="eocrm-team-role-badge">Mened&#380;er Biura</p>
                                <?php endif; ?>
                                <?php if (! empty($agent['phone'])) : ?>
                                    <p><strong>Telefon:</strong> <?php echo esc_html((string) $agent['phone']); ?></p>
                                <?php endif; ?>
                                <?php if (! empty($agent['user_email'])) : ?>
                                    <p><strong>E-mail:</strong> <a href="mailto:<?php echo esc_attr((string) $agent['user_email']); ?>"><?php echo esc_html((string) $agent['user_email']); ?></a></p>
                                <?php endif; ?>
                                <?php if ($agent_address !== ',') : ?>
                                    <p><strong>Lokalizacja:</strong> <?php echo esc_html(trim($agent_address, ', ')); ?></p>
                                <?php endif; ?>
                                <?php if (! empty($agent['bio'])) : ?>
                                    <div class="eocrm-description">
                                        <?php echo wp_kses_post(wp_trim_words((string) $agent['bio'], 35)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <?php if (! empty($public_unassigned_agents)) : ?>
        <section class="eocrm-card eocrm-office-section" id="eocrm-office-unassigned">
            <div class="eocrm-team-grid eocrm-team-list-vertical">
                <?php foreach ($public_unassigned_agents as $agent) : ?>
                    <?php
                    $agent_photo_url = isset($agent['photo_url']) ? (string) $agent['photo_url'] : '';
                    $agent_address = trim((string) ($agent['address_line'] ?? '') . ', ' . (string) ($agent['postal_code'] ?? '') . ' ' . (string) ($agent['city'] ?? ''));
                    ?>
                    <article class="eocrm-team-card eocrm-team-card-horizontal" id="eocrm-agent-<?php echo (int) ($agent['ID'] ?? 0); ?>">
                        <div class="eocrm-team-card-media eocrm-team-card-media-horizontal">
                            <?php if ($agent_photo_url !== '') : ?>
                                <img src="<?php echo esc_url($agent_photo_url); ?>" alt="Zdjecie agenta" class="eocrm-team-photo">
                            <?php else : ?>
                                <div class="eocrm-team-photo eocrm-team-photo-placeholder"><?php echo esc_html(substr((string) ($agent['display_name'] ?? 'A'), 0, 1)); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="eocrm-team-card-body eocrm-team-card-body-horizontal">
                            <h4><?php echo esc_html((string) ($agent['display_name'] ?? 'Agent')); ?></h4>
                            <?php if (! empty($agent['is_manager'])) : ?>
                                <p class="eocrm-team-role-badge">Mened&#380;er Biura</p>
                            <?php endif; ?>
                            <?php if (! empty($agent['phone'])) : ?>
                                <p><strong>Telefon:</strong> <?php echo esc_html((string) $agent['phone']); ?></p>
                            <?php endif; ?>
                            <?php if (! empty($agent['user_email'])) : ?>
                                <p><strong>E-mail:</strong> <a href="mailto:<?php echo esc_attr((string) $agent['user_email']); ?>"><?php echo esc_html((string) $agent['user_email']); ?></a></p>
                            <?php endif; ?>
                            <?php if ($agent_address !== ',') : ?>
                                <p><strong>Lokalizacja:</strong> <?php echo esc_html(trim($agent_address, ', ')); ?></p>
                            <?php endif; ?>
                            <?php if (! empty($agent['bio'])) : ?>
                                <div class="eocrm-description">
                                    <?php echo wp_kses_post(wp_trim_words((string) $agent['bio'], 35)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

