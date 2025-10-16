<?php

declare(strict_types=1);

use EstateOffice\Frontend\OfferSingle;
use EstateOffice\Frontend\ContactForms;

defined('ABSPATH') || exit;

$context = OfferSingle::getContext();

get_header();
?>

<main class="estate-office-offer" id="primary">
    <div class="estate-office-offer__breadcrumbs">
        <?php if (! empty($context['archive_link'])) : ?>
            <a class="estate-office-offer__back" href="<?php echo esc_url($context['archive_link']); ?>">
                &larr; <?php esc_html_e('Powrót do ofert', 'estate-office'); ?>
            </a>
        <?php endif; ?>
    </div>

    <header class="estate-office-offer__header">
        <?php if (! empty($context['logo'])) : ?>
            <div class="estate-office-offer__logo">
                <img src="<?php echo esc_url($context['logo']); ?>" alt="" loading="lazy" />
            </div>
        <?php endif; ?>
        <div class="estate-office-offer__title-group">
            <?php if (! empty($context['reference'])) : ?>
                <span class="estate-office-offer__reference"><?php echo esc_html($context['reference']); ?></span>
            <?php endif; ?>
            <h1 class="estate-office-offer__title"><?php the_title(); ?></h1>
            <?php if (! empty($context['chips'])) : ?>
                <div class="estate-office-offer__chips">
                    <?php foreach ($context['chips'] as $chip) : ?>
                        <span class="estate-office-offer__chip"><?php echo esc_html($chip); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (! empty($context['badges'])) : ?>
                <div class="estate-office-offer__badges">
                    <?php foreach ($context['badges'] as $badge) : ?>
                        <span class="estate-office-offer__badge estate-office-offer__badge--<?php echo esc_attr($badge['slug']); ?>">
                            <?php echo esc_html($badge['label']); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <section class="estate-office-offer__summary">
        <?php if (! empty($context['price'])) : ?>
            <div class="estate-office-offer__summary-card">
                <span class="estate-office-offer__summary-label"><?php esc_html_e('Cena', 'estate-office'); ?></span>
                <span class="estate-office-offer__summary-value"><?php echo esc_html($context['price']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (! empty($context['area'])) : ?>
            <div class="estate-office-offer__summary-card">
                <span class="estate-office-offer__summary-label"><?php esc_html_e('Powierzchnia', 'estate-office'); ?></span>
                <span class="estate-office-offer__summary-value"><?php echo esc_html($context['area']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (! empty($context['price_per_sqm'])) : ?>
            <div class="estate-office-offer__summary-card">
                <span class="estate-office-offer__summary-label"><?php esc_html_e('Cena za m²', 'estate-office'); ?></span>
                <span class="estate-office-offer__summary-value"><?php echo esc_html($context['price_per_sqm']); ?></span>
            </div>
        <?php endif; ?>
        <?php if (! empty($context['admin_fee'])) : ?>
            <div class="estate-office-offer__summary-card">
                <span class="estate-office-offer__summary-label"><?php esc_html_e('Czynsz administracyjny', 'estate-office'); ?></span>
                <span class="estate-office-offer__summary-value"><?php echo esc_html($context['admin_fee']); ?></span>
            </div>
        <?php endif; ?>
    </section>

    <div class="estate-office-offer__layout">
        <section class="estate-office-offer__gallery" aria-label="<?php esc_attr_e('Galeria nieruchomości', 'estate-office'); ?>">
            <?php if (! empty($context['gallery']['main'])) : ?>
                <figure class="estate-office-offer__gallery-main">
                    <img
                        src="<?php echo esc_url($context['gallery']['main']['url']); ?>"
                        alt="<?php echo esc_attr($context['gallery']['main']['alt']); ?>"
                        data-estate-office-offer-main
                    />
                </figure>
            <?php else : ?>
                <div class="estate-office-offer__gallery-empty">
                    <?php esc_html_e('Brak dodanych zdjęć. Dodaj media w panelu CRM, aby zaprezentować nieruchomość.', 'estate-office'); ?>
                </div>
            <?php endif; ?>

            <?php if (! empty($context['gallery']['items']) && $context['gallery']['count'] > 1) : ?>
                <div class="estate-office-offer__thumbnails" role="list">
                    <?php foreach ($context['gallery']['items'] as $index => $item) : ?>
                        <button
                            type="button"
                            class="estate-office-offer__thumb<?php echo $index === 0 ? ' is-active' : ''; ?>"
                            data-estate-office-offer-thumb
                            data-full="<?php echo esc_url($item['url']); ?>"
                            data-alt="<?php echo esc_attr($item['alt']); ?>"
                            aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                        >
                            <img src="<?php echo esc_url($item['thumb']); ?>" alt="<?php echo esc_attr($item['alt']); ?>" loading="lazy" />
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (! empty($context['floor_plans']) || ! empty($context['media_links'])) : ?>
                <div class="estate-office-offer__resources">
                    <?php foreach ($context['floor_plans'] as $plan) : ?>
                        <a class="estate-office-offer__resource" href="<?php echo esc_url($plan['url']); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html($plan['label']); ?>
                        </a>
                    <?php endforeach; ?>
                    <?php foreach ($context['media_links'] as $link) : ?>
                        <a class="estate-office-offer__resource" href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html($link['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if (! empty($context['manager'])) : ?>
            <aside class="estate-office-offer__contact" aria-label="<?php esc_attr_e('Kontakt z agentem', 'estate-office'); ?>">
                <?php if (! empty($context['manager']['avatar'])) : ?>
                    <img class="estate-office-offer__contact-avatar" src="<?php echo esc_url($context['manager']['avatar']); ?>" alt="" loading="lazy" />
                <?php endif; ?>
                <h2 class="estate-office-offer__contact-title"><?php esc_html_e('Twój opiekun oferty', 'estate-office'); ?></h2>
                <p class="estate-office-offer__contact-name"><?php echo esc_html($context['manager']['name']); ?></p>
                <?php if (! empty($context['manager']['phones'])) : ?>
                    <ul class="estate-office-offer__contact-list">
                        <?php foreach ($context['manager']['phones'] as $phone) : ?>
                            <li>
                                <?php if (! empty($phone['href'])) : ?>
                                    <a href="<?php echo esc_url($phone['href']); ?>">
                                        <strong><?php echo esc_html($phone['label']); ?>:</strong>
                                        <?php echo esc_html($phone['display']); ?>
                                    </a>
                                <?php else : ?>
                                    <span>
                                        <strong><?php echo esc_html($phone['label']); ?>:</strong>
                                        <?php echo esc_html($phone['display']); ?>
                                    </span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (! empty($context['manager']['email'])) : ?>
                    <a class="estate-office-offer__contact-mail" href="mailto:<?php echo esc_attr($context['manager']['email']); ?>">
                        <?php echo esc_html($context['manager']['email']); ?>
                    </a>
                <?php endif; ?>
                <?php if (! empty($context['manager']['whatsapp'])) : ?>
                    <a class="estate-office-offer__contact-whatsapp" href="<?php echo esc_url($context['manager']['whatsapp']['url']); ?>" target="_blank" rel="noopener">
                        <?php echo esc_html($context['manager']['whatsapp']['label']); ?>
                    </a>
                <?php endif; ?>
                <?php if (! empty($context['manager']['profile_url'])) : ?>
                    <a class="estate-office-offer__contact-profile" href="<?php echo esc_url($context['manager']['profile_url']); ?>">
                        <?php esc_html_e('Zobacz profil agenta', 'estate-office'); ?>
                    </a>
                <?php endif; ?>
                <?php if (! empty($context['manager']['bio'])) : ?>
                    <div class="estate-office-offer__contact-bio">
                        <?php echo wp_kses_post(wpautop($context['manager']['bio'])); ?>
                    </div>
                <?php endif; ?>
                <?php
                if (! empty($context['manager']['email'])) {
                    ContactForms::render([
                        'context'        => 'offer',
                        'recipient'      => $context['manager']['email'],
                        'recipient_name' => $context['manager']['name'] ?? '',
                        'record_id'      => get_the_ID(),
                        'source'         => get_permalink(),
                        'subject'        => sprintf(
                            /* translators: %s: offer title */
                            __('Zapytanie dotyczące oferty: %s', 'estate-office'),
                            get_the_title()
                        ),
                        'heading'        => __('Napisz wiadomość', 'estate-office'),
                        'success_message'=> __('Dziękujemy za wysłanie zapytania. Agent odezwie się wkrótce.', 'estate-office'),
                        'consent_label'  => __('Wyrażam zgodę na kontakt w sprawie tej oferty oraz przetwarzanie danych w celu obsługi zapytania.', 'estate-office'),
                        'assigned_to'    => $context['manager']['id'] ?? 0,
                    ]);
                }
                ?>
            </aside>
        <?php endif; ?>
    </div>

    <section class="estate-office-offer__section">
        <h2 class="estate-office-offer__section-title"><?php esc_html_e('Opis nieruchomości', 'estate-office'); ?></h2>
        <div class="estate-office-offer__content">
            <?php the_content(); ?>
        </div>
    </section>

    <?php if (! empty($context['facts'])) : ?>
        <section class="estate-office-offer__section">
            <h2 class="estate-office-offer__section-title"><?php esc_html_e('Szczegóły nieruchomości', 'estate-office'); ?></h2>
            <dl class="estate-office-offer__facts">
                <?php foreach ($context['facts'] as $fact) : ?>
                    <div class="estate-office-offer__fact">
                        <dt><?php echo esc_html($fact['label']); ?></dt>
                        <dd><?php echo esc_html($fact['value']); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
    <?php endif; ?>

    <?php if (! empty($context['custom_fields'])) : ?>
        <section class="estate-office-offer__section">
            <h2 class="estate-office-offer__section-title"><?php esc_html_e('Pola dodatkowe', 'estate-office'); ?></h2>
            <dl class="estate-office-offer__facts">
                <?php foreach ($context['custom_fields'] as $fact) : ?>
                    <div class="estate-office-offer__fact">
                        <dt><?php echo esc_html($fact['label']); ?></dt>
                        <dd><?php echo esc_html($fact['value']); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
    <?php endif; ?>

    <?php if (! empty($context['plot'])) : ?>
        <section class="estate-office-offer__section">
            <h2 class="estate-office-offer__section-title"><?php esc_html_e('Informacje o działce', 'estate-office'); ?></h2>
            <dl class="estate-office-offer__facts">
                <?php foreach ($context['plot'] as $fact) : ?>
                    <div class="estate-office-offer__fact">
                        <dt><?php echo esc_html($fact['label']); ?></dt>
                        <dd><?php echo esc_html($fact['value']); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
    <?php endif; ?>

    <section class="estate-office-offer__section">
        <h2 class="estate-office-offer__section-title"><?php esc_html_e('Lokalizacja', 'estate-office'); ?></h2>
        <div class="estate-office-offer__location">
            <div class="estate-office-offer__address">
                <?php if (! empty($context['address']['line1'])) : ?>
                    <span><?php echo esc_html($context['address']['line1']); ?></span>
                <?php endif; ?>
                <?php if (! empty($context['address']['line2'])) : ?>
                    <span><?php echo esc_html($context['address']['line2']); ?></span>
                <?php endif; ?>
                <?php if (! empty($context['address']['district'])) : ?>
                    <span><?php echo esc_html($context['address']['district']); ?></span>
                <?php endif; ?>
                <?php if (! empty($context['map']['address'])) : ?>
                    <span><?php echo esc_html($context['map']['address']); ?></span>
                <?php endif; ?>
            </div>
            <?php if (! empty($context['map']['has_coordinates'])) : ?>
                <?php
                $mapClasses = ['estate-office-offer__map'];
                if (empty($context['map']['interactive'])) {
                    $mapClasses[] = 'estate-office-offer__map--iframe';
                }
                ?>
                <div class="<?php echo esc_attr(implode(' ', $mapClasses)); ?>">
                    <?php if (! empty($context['map']['interactive'])) : ?>
                        <div
                            class="estate-office-map"
                            data-lat="<?php echo esc_attr(number_format((float) $context['map']['lat'], 6, '.', '')); ?>"
                            data-lng="<?php echo esc_attr(number_format((float) $context['map']['lng'], 6, '.', '')); ?>"
                            data-title="<?php echo esc_attr($context['map']['title'] ?? get_the_title()); ?>"
                            data-address="<?php echo esc_attr($context['map']['address'] ?? ''); ?>"
                            data-zoom="15"
                        ></div>
                    <?php elseif (! empty($context['map']['embed'])) : ?>
                        <iframe
                            src="<?php echo esc_url($context['map']['embed']); ?>"
                            allowfullscreen
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            title="<?php echo esc_attr(get_the_title()); ?>"
                        ></iframe>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php get_footer(); ?>
