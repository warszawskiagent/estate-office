<?php
if (! defined('ABSPATH')) {
    exit;
}

$status = isset($_GET['eo_status']) ? sanitize_key(wp_unslash((string) $_GET['eo_status'])) : '';
$message = isset($_GET['eo_message']) ? sanitize_text_field(rawurldecode(wp_unslash((string) $_GET['eo_message']))) : '';
$step = isset($_GET['eo_step']) ? (int) $_GET['eo_step'] : 1;
$agreementId = isset($_GET['agreement_id']) ? (int) $_GET['agreement_id'] : 0;

$agreement = $agreementId > 0 ? EstateOffice_Agreements::get_agreement($agreementId) : null;
$clientSearch = isset($_GET['eo_client_search']) ? sanitize_text_field(wp_unslash((string) $_GET['eo_client_search'])) : '';
$clients = EstateOffice_Agreements::search_clients($clientSearch);
$linkedClients = $agreementId > 0 ? EstateOffice_Agreements::get_linked_clients($agreementId) : [];
?>
<h2>Umowy — proces dodawania (0.4.1)</h2>

<?php if ($status === 'success' && $message !== '') : ?>
    <div class="estateoffice-notice estateoffice-notice--success"><?php echo esc_html($message); ?></div>
<?php elseif ($status === 'error' && $message !== '') : ?>
    <div class="estateoffice-notice estateoffice-notice--error"><?php echo esc_html($message); ?></div>
<?php endif; ?>

<section class="estateoffice-stepper">
    <strong>Etapy:</strong> 1. Nowa umowa → 2. Dodawanie klienta → 3. Nieruchomość/Poszukiwanie
</section>

<?php if ($step <= 1 || ! $agreement) : ?>
    <section>
        <h3>Etap 1: Nowa umowa</h3>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="estateoffice-grid estateoffice-grid--2">
            <?php wp_nonce_field('estateoffice_agreement_step1'); ?>
            <input type="hidden" name="action" value="estateoffice_agreement_step1" />

            <label>Numer umowy
                <input type="text" name="agreement_number" required />
            </label>

            <label>Typ transakcji
                <select name="transaction_type" required>
                    <option value="SPRZEDAŻ">SPRZEDAŻ</option>
                    <option value="KUPNO">KUPNO</option>
                    <option value="WYNAJEM">WYNAJEM</option>
                    <option value="NAJEM">NAJEM</option>
                </select>
            </label>

            <label>Data zawarcia
                <input type="date" name="start_date" required />
            </label>

            <label>Data zakończenia
                <input type="date" name="end_date" id="eo-agreement-end-date" />
            </label>

            <label><input type="checkbox" name="is_open_ended" value="1" id="eo-agreement-open-ended" /> Umowa bezterminowa</label>

            <label>Wysokość prowizji
                <input type="number" step="0.01" min="0" name="commission_value" required />
            </label>

            <label>Jednostka prowizji
                <select name="commission_unit" required>
                    <option value="%">%</option>
                    <option value="PLN">PLN</option>
                    <option value="EUR">EUR</option>
                    <option value="USD">USD</option>
                </select>
            </label>

            <p><button type="submit" class="button button-primary">DALEJ</button></p>
        </form>
    </section>
<?php else : ?>
    <section>
        <h3>Etap 2: Dodawanie klienta</h3>
        <p><strong>Umowa:</strong> <?php echo esc_html((string) ($agreement['agreement_number'] ?? '')); ?> | <strong>Typ:</strong> <?php echo esc_html((string) ($agreement['transaction_type'] ?? '')); ?></p>

        <h4>Klienci przypisani do umowy</h4>
        <ul>
            <?php if (empty($linkedClients)) : ?>
                <li>Brak przypisanych klientów.</li>
            <?php else : ?>
                <?php foreach ($linkedClients as $client) :
                    $name = $client['client_type'] === 'company'
                        ? ((string) ($client['company_name'] ?: $client['representative_name']))
                        : trim(((string) $client['first_name']) . ' ' . ((string) $client['last_name']));
                    ?>
                    <li><?php echo esc_html($name !== '' ? $name : '—'); ?></li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>

        <hr />

        <h4>Wybierz istniejącego klienta</h4>
        <form method="get" class="estateoffice-search-form">
            <input type="hidden" name="eo_step" value="2" />
            <input type="hidden" name="agreement_id" value="<?php echo esc_attr((string) $agreementId); ?>" />
            <input type="text" name="eo_client_search" value="<?php echo esc_attr($clientSearch); ?>" placeholder="Szukaj klienta" />
            <button type="submit" class="button">Szukaj</button>
        </form>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="estateoffice-grid estateoffice-grid--2">
            <?php wp_nonce_field('estateoffice_agreement_step2_existing_client'); ?>
            <input type="hidden" name="action" value="estateoffice_agreement_step2_existing_client" />
            <input type="hidden" name="agreement_id" value="<?php echo esc_attr((string) $agreementId); ?>" />

            <label>Istniejący klient
                <select name="client_id" required>
                    <option value="">— wybierz —</option>
                    <?php foreach ($clients as $client) :
                        $display = $client['client_type'] === 'company'
                            ? ((string) ($client['company_name'] ?: $client['representative_name']))
                            : trim(((string) $client['first_name']) . ' ' . ((string) $client['last_name']));
                        ?>
                        <option value="<?php echo esc_attr((string) $client['id']); ?>"><?php echo esc_html($display !== '' ? $display : '—'); ?> (<?php echo esc_html((string) ($client['email'] ?? 'brak e-mail')); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>

            <p><button type="submit" class="button">Przypisz klienta</button></p>
        </form>

        <hr />

        <h4>Dodaj nowego klienta do umowy</h4>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="estateoffice-grid estateoffice-grid--2" id="eo-agreement-client-form">
            <?php wp_nonce_field('estateoffice_agreement_step2_new_client'); ?>
            <input type="hidden" name="action" value="estateoffice_agreement_step2_new_client" />
            <input type="hidden" name="agreement_id" value="<?php echo esc_attr((string) $agreementId); ?>" />

            <label>Typ klienta
                <select name="client_type" id="eo-agreement-client-type">
                    <option value="person">Osoba fizyczna</option>
                    <option value="company">Firma</option>
                </select>
            </label>

            <label>Telefon <input type="text" name="phone" /></label>
            <label>E-mail <input type="email" name="email" /></label>

            <div class="eo-agreement-person-fields">
                <label>Imię <input type="text" name="first_name" /></label>
                <label>Nazwisko <input type="text" name="last_name" /></label>
            </div>

            <div class="eo-agreement-company-fields" style="display:none;">
                <label>Nazwa firmy <input type="text" name="company_name" /></label>
                <label>Reprezentant <input type="text" name="representative_name" /></label>
            </div>

            <p><button type="submit" class="button">Dodaj i przypisz klienta</button></p>
        </form>

        <hr />

        <h4>Czy chcesz dodać kolejnego klienta?</h4>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="estateoffice-grid estateoffice-grid--2">
            <?php wp_nonce_field('estateoffice_agreement_step2_continue'); ?>
            <input type="hidden" name="action" value="estateoffice_agreement_step2_continue" />
            <input type="hidden" name="agreement_id" value="<?php echo esc_attr((string) $agreementId); ?>" />

            <label>
                Wybór
                <select name="add_another_client">
                    <option value="yes">TAK</option>
                    <option value="no">NIE</option>
                </select>
            </label>

            <p><button type="submit" class="button button-primary">DALEJ</button></p>
        </form>
    </section>
<?php endif; ?>
