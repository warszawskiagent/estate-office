<?php

declare(strict_types=1);

namespace EstateOffice\Frontend;

use function absint;
use function add_action;
use function admin_url;
use function apply_filters;
use function check_ajax_referer;
use function do_action;
use function esc_attr;
use function esc_html__;
use function esc_url;
use function esc_url_raw;
use function get_permalink;
use function get_post;
use function is_email;
use function plugins_url;
use function sanitize_email;
use function sanitize_textarea_field;
use function sanitize_text_field;
use function sprintf;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;
use function wp_mail;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_strip_all_tags;
use function wp_unslash;

use const ESTATE_OFFICE_PLUGIN_FILE;
use const ESTATE_OFFICE_PLUGIN_VERSION;

/**
 * Handles reusable front-end contact forms for offers and agents.
 */
final class ContactForms
{
    private const ACTION = 'estate_office_contact';

    /**
     * Boots hooks required for handling contact form submissions.
     */
    public static function bootstrap(): void
    {
        add_action('init', [self::class, 'registerAssets']);
        add_action('wp_ajax_' . self::ACTION, [self::class, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [self::class, 'handle']);
    }

    /**
     * Registers shared styles and scripts.
     */
    public static function registerAssets(): void
    {
        wp_register_style(
            'estate-office-contact-forms',
            plugins_url('assets/css/contact-forms.css', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION
        );

        wp_register_script(
            'estate-office-contact-forms',
            plugins_url('assets/js/contact-forms.js', ESTATE_OFFICE_PLUGIN_FILE),
            [],
            ESTATE_OFFICE_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Enqueues assets and attaches localisation for AJAX handling.
     */
    public static function enqueueAssets(): void
    {
        wp_enqueue_style('estate-office-contact-forms');
        wp_enqueue_script('estate-office-contact-forms');

        wp_localize_script(
            'estate-office-contact-forms',
            'estateOfficeContactForms',
            [
                'ajaxUrl'    => esc_url(admin_url('admin-ajax.php')),
                'error'      => esc_html__(
                    'Wystąpił nieoczekiwany błąd. Spróbuj ponownie lub skontaktuj się z nami telefonicznie.',
                    'estate-office'
                ),
                'processing' => esc_html__('Wysyłanie...', 'estate-office'),
            ]
        );
    }

    /**
     * Renders contact form markup.
     *
     * @param array<string,mixed> $config
     */
    public static function render(array $config): void
    {
        $recipient = sanitize_email((string) ($config['recipient'] ?? ''));
        if ($recipient === '' || ! is_email($recipient)) {
            return;
        }

        $context = sanitize_text_field((string) ($config['context'] ?? '')); // e.g. offer or agent
        $subject = sanitize_text_field((string) ($config['subject'] ?? esc_html__('Zapytanie ze strony', 'estate-office')));
        $heading = sanitize_text_field((string) ($config['heading'] ?? esc_html__('Skontaktuj się z nami', 'estate-office')));
        $success = sanitize_text_field((string) ($config['success_message'] ?? esc_html__('Dziękujemy! Skontaktujemy się z Tobą wkrótce.', 'estate-office')));
        $submit  = sanitize_text_field((string) ($config['submit_label'] ?? esc_html__('Wyślij wiadomość', 'estate-office')));
        $consent = sanitize_text_field((string) ($config['consent_label'] ?? esc_html__('Wyrażam zgodę na kontakt w sprawie niniejszej oferty.', 'estate-office')));
        $record  = absint((int) ($config['record_id'] ?? 0));
        $source  = isset($config['source']) ? esc_url((string) $config['source']) : '';
        $recipientName = sanitize_text_field((string) ($config['recipient_name'] ?? ''));

        self::enqueueAssets();

        $nonce = wp_create_nonce(self::ACTION);
        $formId = 'estate-office-contact-' . uniqid('', false);
        ?>
        <section class="estate-office-contact-form" aria-labelledby="<?php echo esc_attr($formId); ?>">
            <h2 class="estate-office-contact-form__title" id="<?php echo esc_attr($formId); ?>">
                <?php echo esc_html($heading); ?>
            </h2>
            <form
                class="estate-office-contact-form__form"
                method="post"
                data-estate-office-contact-form
                data-success="<?php echo esc_attr($success); ?>"
            >
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>" />
                <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>" />
                <input type="hidden" name="context" value="<?php echo esc_attr($context); ?>" />
                <input type="hidden" name="subject" value="<?php echo esc_attr($subject); ?>" />
                <input type="hidden" name="recipient" value="<?php echo esc_attr($recipient); ?>" />
                <input type="hidden" name="recipient_name" value="<?php echo esc_attr($recipientName); ?>" />
                <input type="hidden" name="record_id" value="<?php echo esc_attr((string) $record); ?>" />
                <?php if ($source !== '') : ?>
                    <input type="hidden" name="source" value="<?php echo esc_attr($source); ?>" />
                <?php endif; ?>

                <label class="estate-office-contact-form__field">
                    <span><?php esc_html_e('Imię i nazwisko', 'estate-office'); ?>*</span>
                    <input type="text" name="name" required autocomplete="name" />
                </label>

                <label class="estate-office-contact-form__field">
                    <span><?php esc_html_e('Adres e-mail', 'estate-office'); ?>*</span>
                    <input type="email" name="email" required autocomplete="email" />
                </label>

                <label class="estate-office-contact-form__field">
                    <span><?php esc_html_e('Numer telefonu', 'estate-office'); ?></span>
                    <input type="tel" name="phone" autocomplete="tel" />
                </label>

                <label class="estate-office-contact-form__field">
                    <span><?php esc_html_e('Wiadomość', 'estate-office'); ?>*</span>
                    <textarea name="message" rows="4" required></textarea>
                </label>

                <label class="estate-office-contact-form__consent">
                    <input type="checkbox" name="privacy" value="1" required />
                    <span><?php echo esc_html($consent); ?>*</span>
                </label>

                <p class="estate-office-contact-form__notice" data-estate-office-contact-message role="status" aria-live="polite"></p>

                <button type="submit" class="estate-office-contact-form__submit">
                    <?php echo esc_html($submit); ?>
                </button>
            </form>
        </section>
        <?php
    }

    /**
     * Handles AJAX submissions from the contact forms.
     */
    public static function handle(): void
    {
        check_ajax_referer(self::ACTION, 'nonce');

        $name    = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $email   = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $phone   = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        $privacy = isset($_POST['privacy']) && (int) $_POST['privacy'] === 1;
        $subject = sanitize_text_field(wp_unslash($_POST['subject'] ?? esc_html__('Zapytanie ze strony', 'estate-office')));
        $recipient = sanitize_email(wp_unslash($_POST['recipient'] ?? ''));
        $recipientName = sanitize_text_field(wp_unslash($_POST['recipient_name'] ?? ''));
        $context = sanitize_text_field(wp_unslash($_POST['context'] ?? ''));
        $recordId = absint((int) ($_POST['record_id'] ?? 0));
        $source = isset($_POST['source']) ? esc_url_raw(wp_unslash($_POST['source'])) : '';

        if ($name === '' || $email === '' || $message === '' || ! $privacy) {
            wp_send_json_error([
                'message' => esc_html__(
                    'Uzupełnij wymagane pola i zaakceptuj zgodę na kontakt.',
                    'estate-office'
                ),
            ]);
        }

        if (! is_email($email)) {
            wp_send_json_error([
                'message' => esc_html__('Podany adres e-mail jest nieprawidłowy.', 'estate-office'),
            ]);
        }

        if ($recipient === '' || ! is_email($recipient)) {
            wp_send_json_error([
                'message' => esc_html__('Nie udało się ustalić adresata wiadomości.', 'estate-office'),
            ]);
        }

        $data = [
            'name'           => $name,
            'email'          => $email,
            'phone'          => $phone,
            'message'        => $message,
            'context'        => $context,
            'record_id'      => $recordId,
            'source'         => $source,
            'recipient'      => $recipient,
            'recipient_name' => $recipientName,
        ];

        /**
         * Allows filtering the email recipient before sending.
         */
        $recipient = apply_filters('estate_office_contact_recipient', $recipient, $data);

        if ($recipient === '' || ! is_email($recipient)) {
            wp_send_json_error([
                'message' => esc_html__('Nie udało się ustalić adresata wiadomości.', 'estate-office'),
            ]);
        }

        $bodyLines = [
            sprintf(esc_html__('Imię i nazwisko: %s', 'estate-office'), $name),
            sprintf(esc_html__('E-mail: %s', 'estate-office'), $email),
        ];

        if ($phone !== '') {
            $bodyLines[] = sprintf(esc_html__('Telefon: %s', 'estate-office'), $phone);
        }

        if ($context !== '') {
            $bodyLines[] = sprintf(esc_html__('Kontekst: %s', 'estate-office'), $context);
        }

        if ($recordId > 0) {
            $bodyLines[] = sprintf(esc_html__('ID rekordu: %d', 'estate-office'), $recordId);
            $post = get_post($recordId);
            if ($post) {
                $bodyLines[] = sprintf(esc_html__('Tytuł rekordu: %s', 'estate-office'), wp_strip_all_tags($post->post_title));
                $bodyLines[] = sprintf(esc_html__('Link: %s', 'estate-office'), get_permalink($post));
            }
        }

        if ($source !== '') {
            $bodyLines[] = sprintf(esc_html__('Strona źródłowa: %s', 'estate-office'), $source);
        }

        $bodyLines[] = '';
        $bodyLines[] = esc_html__('Treść wiadomości:', 'estate-office');
        $bodyLines[] = $message;

        $body = implode("\n", $bodyLines);

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('Reply-To: %s <%s>', $name, $email),
        ];

        /**
         * Filters the email subject.
         */
        $subject = apply_filters('estate_office_contact_subject', $subject, $data);

        /**
         * Filters headers before sending.
         *
         * @param array<int,string> $headers
         */
        $headers = apply_filters('estate_office_contact_headers', $headers, $data);

        $sent = wp_mail($recipient, $subject, $body, $headers);

        if (! $sent) {
            wp_send_json_error([
                'message' => esc_html__(
                    'Nie udało się wysłać wiadomości. Spróbuj ponownie później.',
                    'estate-office'
                ),
            ]);
        }

        /**
         * Fires after a successful submission.
         */
        do_action('estate_office_contact_submitted', $data);

        wp_send_json_success([
            'message' => esc_html__(
                'Wiadomość została wysłana. Skontaktujemy się z Tobą najszybciej jak to możliwe.',
                'estate-office'
            ),
        ]);
    }
}
