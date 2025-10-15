<?php

declare(strict_types=1);

use EstateOffice\Frontend\AgentPublic;
use EstateOffice\Frontend\ContactForms;

defined('ABSPATH') || exit;

$context = AgentPublic::getContext();

get_header();
?>

<main class="estate-office-agent" id="primary">
    <header class="estate-office-agent__header">
        <?php if (! empty($context['avatar'])) : ?>
            <img class="estate-office-agent__avatar" src="<?php echo esc_url($context['avatar']); ?>" alt="" loading="lazy" />
        <?php endif; ?>
        <div>
            <h1 class="estate-office-agent__title"><?php echo esc_html($context['name'] ?? ''); ?></h1>
            <ul class="estate-office-agent__meta">
                <?php if (! empty($context['email'])) : ?>
                    <li>
                        <strong><?php esc_html_e('E-mail', 'estate-office'); ?>:</strong>
                        <a href="mailto:<?php echo esc_attr($context['email']); ?>"><?php echo esc_html($context['email']); ?></a>
                    </li>
                <?php endif; ?>
                <?php if (! empty($context['phones'])) : ?>
                    <?php foreach ($context['phones'] as $phone) : ?>
                        <li>
                            <strong><?php echo esc_html($phone['label']); ?>:</strong>
                            <?php if (! empty($phone['href'])) : ?>
                                <a href="<?php echo esc_url($phone['href']); ?>"><?php echo esc_html($phone['display']); ?></a>
                            <?php else : ?>
                                <span><?php echo esc_html($phone['display']); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (! empty($context['whatsapp'])) : ?>
                    <li>
                        <strong><?php echo esc_html($context['whatsapp']['label']); ?>:</strong>
                        <a href="<?php echo esc_url($context['whatsapp']['url']); ?>" target="_blank" rel="noopener">
                            <?php esc_html_e('Skontaktuj się', 'estate-office'); ?>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <?php if (! empty($context['office_logo'])) : ?>
                <div class="estate-office-agent__badge">
                    <span><?php esc_html_e('Biuro', 'estate-office'); ?>:</span>
                    <img class="estate-office-agent__logo" src="<?php echo esc_url($context['office_logo']); ?>" alt="" loading="lazy" />
                </div>
            <?php endif; ?>
        </div>
    </header>

    <?php
    if (! empty($context['email'])) {
        ContactForms::render([
            'context'        => 'agent-profile',
            'recipient'      => $context['email'],
            'recipient_name' => $context['name'] ?? '',
            'subject'        => sprintf(
                /* translators: %s: agent name */
                __('Zapytanie do agenta: %s', 'estate-office'),
                $context['name'] ?? ''
            ),
            'heading'        => __('Skontaktuj się z agentem', 'estate-office'),
            'success_message'=> __('Dziękujemy za kontakt. Agent skontaktuje się z Tobą najszybciej jak to możliwe.', 'estate-office'),
            'consent_label'  => __('Wyrażam zgodę na kontakt w sprawie usług pośrednictwa oraz przetwarzanie danych w celu obsługi zapytania.', 'estate-office'),
            'source'         => $context['profile_url'] ?? '',
            'assigned_to'    => $context['id'] ?? 0,
        ]);
    }
    ?>

    <?php if (! empty($context['specialisations']) || ! empty($context['service_areas'])) : ?>
        <section class="estate-office-agent__highlights">
            <?php if (! empty($context['specialisations'])) : ?>
                <div class="estate-office-agent__highlight">
                    <h2 class="estate-office-agent__highlight-title"><?php esc_html_e('Specjalizacje', 'estate-office'); ?></h2>
                    <ul class="estate-office-agent__chips">
                        <?php foreach ($context['specialisations'] as $item) : ?>
                            <li class="estate-office-agent__chip"><?php echo esc_html($item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if (! empty($context['service_areas'])) : ?>
                <div class="estate-office-agent__highlight">
                    <h2 class="estate-office-agent__highlight-title"><?php esc_html_e('Obsługiwane obszary', 'estate-office'); ?></h2>
                    <ul class="estate-office-agent__chips">
                        <?php foreach ($context['service_areas'] as $item) : ?>
                            <li class="estate-office-agent__chip estate-office-agent__chip--outline"><?php echo esc_html($item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (! empty($context['bio'])) : ?>
        <section class="estate-office-agent__bio">
            <?php echo wp_kses_post(wpautop($context['bio'])); ?>
        </section>
    <?php endif; ?>

    <section>
        <h2 class="estate-office-agent__section-title">
            <?php esc_html_e('Aktualne oferty', 'estate-office'); ?>
            <span class="estate-office-agent__badge">
                <?php echo esc_html(sprintf(__('Łącznie: %d', 'estate-office'), (int) ($context['properties_count'] ?? 0))); ?>
            </span>
        </h2>

        <?php if (! empty($context['properties'])) : ?>
            <ul class="estate-office-agent__properties">
                <?php foreach ($context['properties'] as $property) : ?>
                    <li class="estate-office-agent__card">
                        <div class="estate-office-agent__card-thumb">
                            <?php if (! empty($property['thumbnail'])) : ?>
                                <img src="<?php echo esc_url($property['thumbnail']); ?>" alt="" loading="lazy" />
                            <?php endif; ?>
                        </div>
                        <div class="estate-office-agent__card-body">
                            <?php if (! empty($property['reference'])) : ?>
                                <span class="estate-office-agent__card-meta">#<?php echo esc_html($property['reference']); ?></span>
                            <?php endif; ?>
                            <h3 class="estate-office-agent__card-title">
                                <a href="<?php echo esc_url($property['link']); ?>">
                                    <?php echo esc_html($property['title']); ?>
                                </a>
                            </h3>
                            <?php if (! empty($property['price'])) : ?>
                                <div class="estate-office-agent__card-price"><?php echo esc_html($property['price']); ?></div>
                            <?php endif; ?>
                            <div class="estate-office-agent__card-meta">
                                <?php if (! empty($property['area'])) : ?>
                                    <span><?php echo esc_html($property['area']); ?></span>
                                <?php endif; ?>
                                <?php if (! empty($property['price_per'])) : ?>
                                    <span><?php echo esc_html($property['price_per']); ?></span>
                                <?php endif; ?>
                                <?php if (! empty($property['rooms'])) : ?>
                                    <span><?php echo esc_html(sprintf(__('Pokoje: %s', 'estate-office'), $property['rooms'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php
                            $location = '';
                            if (! empty($property['district']) && ! empty($property['city'])) {
                                $location = $property['district'] . ', ' . $property['city'];
                            } elseif (! empty($property['city'])) {
                                $location = $property['city'];
                            } elseif (! empty($property['district'])) {
                                $location = $property['district'];
                            }
                            ?>
                            <?php if ($location !== '') : ?>
                                <div class="estate-office-agent__card-meta">
                                    <span><?php echo esc_html($location); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (! empty($property['badges'])) : ?>
                                <div class="estate-office-agent__badge-list">
                                    <?php foreach ($property['badges'] as $badge) : ?>
                                        <span class="estate-office-agent__badge-item estate-office-agent__badge-item--<?php echo esc_attr($badge['slug']); ?>">
                                            <?php echo esc_html($badge['label']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <div class="estate-office-agent__empty">
                <?php esc_html_e('Aktualnie brak ofert przypisanych do tego agenta. Wkrótce pojawią się nowe propozycje.', 'estate-office'); ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php get_footer(); ?>
