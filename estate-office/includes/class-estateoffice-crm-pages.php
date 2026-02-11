<?php

if (! defined('ABSPATH')) {
    exit;
}

class EstateOffice_CRM_Pages
{
    public static function boot(): void
    {
        add_shortcode('estateoffice_crm', [self::class, 'render_crm_shortcode']);
        add_shortcode('estateoffice_offers', [self::class, 'render_offers_shortcode']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function enqueue_assets(): void
    {
        wp_register_style(
            'estateoffice-crm-style',
            ESTATEOFFICE_URL . 'assets/css/crm.css',
            [],
            ESTATEOFFICE_VERSION
        );

        wp_register_script(
            'estateoffice-crm-script',
            ESTATEOFFICE_URL . 'assets/js/crm.js',
            [],
            ESTATEOFFICE_VERSION,
            true
        );
    }

    public static function render_crm_shortcode(array $atts): string
    {
        if (! is_user_logged_in()) {
            return '<p class="estateoffice-login-required">Zaloguj się, aby uzyskać dostęp do CRM.</p>';
        }

        $atts = shortcode_atts([
            'view' => 'dashboard',
        ], $atts, 'estateoffice_crm');

        wp_enqueue_style('estateoffice-crm-style');
        wp_enqueue_script('estateoffice-crm-script');

        $view = sanitize_key($atts['view']);
        $template = ESTATEOFFICE_PATH . 'templates/crm/' . $view . '.php';

        if (! file_exists($template)) {
            return '<p>Nie znaleziono widoku CRM.</p>';
        }

        ob_start();
        include ESTATEOFFICE_PATH . 'templates/crm/layout.php';

        return (string) ob_get_clean();
    }

    public static function render_offers_shortcode(array $atts): string
    {
        $atts = shortcode_atts([
            'transaction' => 'SPRZEDAŻ',
        ], $atts, 'estateoffice_offers');

        global $wpdb;
        $table = $wpdb->prefix . 'eo_properties';
        $transaction = mb_strtoupper(sanitize_text_field($atts['transaction']));

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT offer_number, property_type, address_data, pricing_data FROM {$table} WHERE export_www = 1 AND transaction_type = %s ORDER BY created_at DESC LIMIT 50",
                $transaction
            )
        );

        ob_start();
        ?>
        <section class="estateoffice-offers">
            <h2><?php echo esc_html(sprintf('Oferty: %s', $transaction)); ?></h2>
            <?php if (empty($results)) : ?>
                <p>Brak ofert oznaczonych do eksportu.</p>
            <?php else : ?>
                <ul>
                    <?php foreach ($results as $offer) :
                        $address = json_decode((string) $offer->address_data, true);
                        $pricing = json_decode((string) $offer->pricing_data, true);
                        $city = is_array($address) ? ($address['city'] ?? '') : '';
                        $price = is_array($pricing) ? ($pricing['price'] ?? '') : '';
                        ?>
                        <li>
                            <strong><?php echo esc_html((string) $offer->offer_number); ?></strong>
                            — <?php echo esc_html((string) $offer->property_type); ?>
                            — <?php echo esc_html((string) $city); ?>
                            — <?php echo esc_html((string) $price); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }
}
