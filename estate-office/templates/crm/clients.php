<?php
if (! defined('ABSPATH')) {
    exit;
}

$search = isset($_GET['eo_client_search']) ? sanitize_text_field(wp_unslash((string) $_GET['eo_client_search'])) : '';
$clients = EstateOffice_Clients::get_clients($search);
$status = isset($_GET['eo_status']) ? sanitize_key(wp_unslash((string) $_GET['eo_status'])) : '';
$message = isset($_GET['eo_message']) ? sanitize_text_field(rawurldecode(wp_unslash((string) $_GET['eo_message']))) : '';
?>
<h2>Klienci</h2>

<?php if ($status === 'success' && $message !== '') : ?>
    <div class="estateoffice-notice estateoffice-notice--success"><?php echo esc_html($message); ?></div>
<?php elseif ($status === 'error' && $message !== '') : ?>
    <div class="estateoffice-notice estateoffice-notice--error"><?php echo esc_html($message); ?></div>
<?php endif; ?>

<section class="estateoffice-clients-add">
    <h3>Dodaj klienta</h3>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="estateoffice-clients-form" id="estateoffice-clients-form">
        <?php wp_nonce_field('estateoffice_create_client'); ?>
        <input type="hidden" name="action" value="estateoffice_create_client" />

        <div class="estateoffice-grid estateoffice-grid--2">
            <label>
                Typ klienta
                <select name="client_type" id="eo-client-type" required>
                    <option value="person">Osoba fizyczna</option>
                    <option value="company">Firma</option>
                </select>
            </label>

            <label>
                Telefon
                <input type="text" name="phone" required />
            </label>

            <label>
                E-mail
                <input type="email" name="email" required />
            </label>

            <label class="eo-company-only" style="display:none;">
                Strona WWW
                <input type="url" name="website" />
            </label>
        </div>

        <div class="eo-person-only">
            <h4>Dane podstawowe — osoba fizyczna</h4>
            <div class="estateoffice-grid estateoffice-grid--2">
                <label>Imię <input type="text" name="first_name" /></label>
                <label>Nazwisko <input type="text" name="last_name" /></label>
                <label>PESEL <input type="text" name="pesel" /></label>
                <label>
                    Rodzaj dokumentu
                    <select name="document_type">
                        <option value="">— wybierz —</option>
                        <option value="dowod">Dowód osobisty</option>
                        <option value="paszport">Paszport</option>
                        <option value="karta_pobytu">Karta pobytu</option>
                    </select>
                </label>
                <label>Numer dokumentu <input type="text" name="document_number" /></label>
            </div>
        </div>

        <div class="eo-company-only" style="display:none;">
            <h4>Dane podstawowe — firma</h4>
            <div class="estateoffice-grid estateoffice-grid--2">
                <label>Nazwa firmy <input type="text" name="company_name" /></label>
                <label>Imię i nazwisko reprezentanta <input type="text" name="representative_name" /></label>
                <label>NIP <input type="text" name="nip" /></label>
                <label>KRS <input type="text" name="krs" /></label>
                <label>REGON <input type="text" name="regon" /></label>
            </div>
        </div>

        <h4>Adres zamieszkania/rejestrowy</h4>
        <div class="estateoffice-grid estateoffice-grid--3">
            <label>Ulica <input type="text" name="residential_street" required /></label>
            <label>Numer <input type="text" name="residential_number" required /></label>
            <label>Lokal <input type="text" name="residential_apartment" /></label>
            <label>Kod pocztowy <input type="text" name="residential_postal_code" required /></label>
            <label>Miasto <input type="text" name="residential_city" required /></label>
            <label>Kraj <input type="text" name="residential_country" required value="Polska" /></label>
        </div>

        <h4>Adres korespondencyjny</h4>
        <p>
            <label><input type="checkbox" name="same_correspondence" value="1" checked id="eo-same-correspondence" /> Adres korespondencyjny taki sam</label>
        </p>

        <div id="eo-correspondence-fields" style="display:none;">
            <div class="estateoffice-grid estateoffice-grid--3">
                <label>Ulica <input type="text" name="correspondence_street" /></label>
                <label>Numer <input type="text" name="correspondence_number" /></label>
                <label>Lokal <input type="text" name="correspondence_apartment" /></label>
                <label>Kod pocztowy <input type="text" name="correspondence_postal_code" /></label>
                <label>Miasto <input type="text" name="correspondence_city" /></label>
                <label>Kraj <input type="text" name="correspondence_country" value="Polska" /></label>
            </div>
        </div>

        <p>
            <button type="submit" class="button button-primary">Dodaj klienta</button>
        </p>
    </form>
</section>

<section class="estateoffice-clients-list">
    <h3>Lista klientów</h3>
    <form method="get" class="estateoffice-search-form">
        <input type="hidden" name="eo_client_searching" value="1" />
        <input type="text" name="eo_client_search" value="<?php echo esc_attr($search); ?>" placeholder="Szukaj: imię, nazwisko, firma, telefon, e-mail" />
        <button type="submit" class="button">Szukaj</button>
    </form>

    <div class="estateoffice-table-wrap">
        <table class="estateoffice-table">
            <thead>
                <tr>
                    <th>Imię i nazwisko / Nazwa</th>
                    <th>Adres</th>
                    <th>Telefon</th>
                    <th>E-mail</th>
                    <th>Opiekun</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients)) : ?>
                    <tr><td colspan="5">Brak klientów.</td></tr>
                <?php else : ?>
                    <?php foreach ($clients as $client) :
                        $fullName = trim(((string) ($client['first_name'] ?? '')) . ' ' . ((string) ($client['last_name'] ?? '')));
                        $displayName = $client['client_type'] === 'company'
                            ? ((string) ($client['company_name'] ?: $client['representative_name']))
                            : $fullName;

                        $address = json_decode((string) ($client['residential_address'] ?? ''), true);
                        $addressLabel = '';
                        if (is_array($address)) {
                            $addressLabel = trim(((string) ($address['street'] ?? '')) . ' ' . ((string) ($address['number'] ?? '')) . ', ' . ((string) ($address['city'] ?? '')));
                        }

                        $ownerId = (int) ($client['owner_user_id'] ?? 0);
                        $owner = $ownerId > 0 ? get_userdata($ownerId) : null;
                        $ownerName = $owner instanceof WP_User ? $owner->display_name : '—';
                        ?>
                        <tr>
                            <td><a href="#"><?php echo esc_html($displayName !== '' ? $displayName : '—'); ?></a></td>
                            <td><a href="#"><?php echo esc_html($addressLabel !== '' ? $addressLabel : '—'); ?></a></td>
                            <td><?php echo esc_html((string) ($client['phone'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($client['email'] ?? '')); ?></td>
                            <td><?php echo esc_html($ownerName); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
