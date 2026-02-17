<?php
if (! defined('ABSPATH')) {
    exit;
}

$status = isset($_GET['eo_status']) ? sanitize_key(wp_unslash((string) $_GET['eo_status'])) : '';
$message = isset($_GET['eo_message']) ? sanitize_text_field(rawurldecode(wp_unslash((string) $_GET['eo_message']))) : '';
$search = isset($_GET['eo_property_search']) ? sanitize_text_field(wp_unslash((string) $_GET['eo_property_search'])) : '';
$properties = EstateOffice_Properties::get_properties($search);
?>
<h2>Nieruchomości</h2>

<?php if ($status === 'success' && $message !== '') : ?>
    <div class="estateoffice-notice estateoffice-notice--success"><?php echo esc_html($message); ?></div>
<?php elseif ($status === 'error' && $message !== '') : ?>
    <div class="estateoffice-notice estateoffice-notice--error"><?php echo esc_html($message); ?></div>
<?php endif; ?>

<section>
    <h3>Dodaj nieruchomość</h3>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="estateoffice-grid estateoffice-grid--2" id="eo-property-form">
        <?php wp_nonce_field('estateoffice_create_property'); ?>
        <input type="hidden" name="action" value="estateoffice_create_property" />

        <label>Numer oferty <input type="text" name="offer_number" required /></label>
        <label>Powiązana umowa ID (opcjonalnie) <input type="number" min="1" name="agreement_id" /></label>

        <label>Typ transakcji
            <select name="transaction_type" required>
                <option value="SPRZEDAŻ">SPRZEDAŻ</option>
                <option value="KUPNO">KUPNO</option>
                <option value="WYNAJEM">WYNAJEM</option>
                <option value="NAJEM">NAJEM</option>
            </select>
        </label>

        <label>Rodzaj nieruchomości
            <select name="property_type" id="eo-property-type" required>
                <option value="MIESZKANIE">MIESZKANIE</option>
                <option value="DOM">DOM</option>
                <option value="DZIAŁKA">DZIAŁKA</option>
                <option value="LOKAL H/U">LOKAL H/U</option>
            </select>
        </label>

        <label>Stan prawny
            <select name="legal_status">
                <option value="Własność">Własność</option>
                <option value="Współwłasność">Współwłasność</option>
                <option value="Spółdzielcze Własnościowe Prawo do Lokalu">Spółdzielcze Własnościowe Prawo do Lokalu</option>
                <option value="Dzierżawa">Dzierżawa</option>
                <option value="Inne">Inne</option>
            </select>
        </label>

        <label>Ulica <input type="text" name="street" /></label>
        <label>Numer <input type="text" name="number" /></label>
        <label>Lokal <input type="text" name="apartment" /></label>
        <label>Kod pocztowy <input type="text" name="postal_code" required /></label>
        <label>Dzielnica <input type="text" name="district" /></label>
        <label>Miasto <input type="text" name="city" required /></label>

        <div class="eo-only-house-plot" style="display:none;">
            <label>Powiat <input type="text" name="county" /></label>
            <label>Numer działki <input type="text" name="plot_number" /></label>
        </div>

        <div class="eo-only-house" style="display:none;">
            <label>Typ domu
                <select name="house_type">
                    <option value="">—</option>
                    <option value="WOLNOSTOJĄCY">WOLNOSTOJĄCY</option>
                    <option value="BLIŹNIAK">BLIŹNIAK</option>
                    <option value="SZEREGOWIEC">SZEREGOWIEC</option>
                    <option value="WIELORODZINNY">WIELORODZINNY</option>
                </select>
            </label>
        </div>

        <label>Numer KW <input type="text" name="land_register_number" id="eo-land-register" /></label>
        <label><input type="checkbox" name="no_land_register" value="1" id="eo-no-land-register" /> Brak KW</label>

        <label>Cena <input type="number" step="0.01" min="0" name="price" id="eo-price" required /></label>
        <label>Czynsz administracyjny <input type="number" step="0.01" min="0" name="administrative_rent" /></label>
        <label>Metraż m² <input type="number" step="0.01" min="0" name="area" id="eo-area" required /></label>
        <label>Cena za m² <input type="number" step="0.01" min="0" id="eo-price-per-m2" readonly /></label>

        <label>Waluta
            <select name="currency">
                <option value="PLN">PLN</option>
                <option value="EUR">EUR</option>
                <option value="USD">USD</option>
            </select>
        </label>

        <div class="eo-not-plot-only">
            <label>Rok budowy <input type="number" min="1700" max="2200" name="year_built" /></label>
            <label>Piętro <input type="number" min="0" name="floor" /></label>
            <label>Liczba pięter <input type="number" min="0" name="floors" /></label>
            <label>Liczba pokoi <input type="number" min="0" name="rooms" /></label>
        </div>

        <div class="eo-only-plot" style="display:none;">
            <label>Kształt działki
                <select name="plot_shape" id="eo-plot-shape">
                    <option value="">—</option>
                    <option value="Regularny">Regularny</option>
                    <option value="Nieregularny">Nieregularny</option>
                </select>
            </label>
            <label>Wymiary działki <input type="text" name="plot_dimensions" /></label>
        </div>

        <fieldset>
            <legend>Znaczniki</legend>
            <label><input type="checkbox" name="tag_new_offer" /> Nowa oferta</label>
            <label><input type="checkbox" name="tag_exclusive" /> Wyłączność</label>
            <label><input type="checkbox" name="tag_sold" /> Sprzedane</label>
            <label><input type="checkbox" name="tag_rented" /> Wynajęte</label>
            <label><input type="checkbox" name="tag_new_price" /> Nowa cena</label>
            <label><input type="checkbox" name="tag_no_commission" /> Bez prowizji</label>
            <label><input type="checkbox" name="tag_mls" /> Oferta MLS</label>
            <label><input type="checkbox" name="tag_premium" /> Premium</label>
        </fieldset>

        <fieldset>
            <legend>Eksport</legend>
            <label><input type="checkbox" name="export_www" /> Eksport na WWW</label>
            <label><input type="checkbox" name="export_portals" /> Eksport na portale</label>
        </fieldset>

        <label>Opis nieruchomości
            <textarea name="description" rows="5"></textarea>
        </label>

        <p><button type="submit" class="button button-primary">Dodaj nieruchomość</button></p>
    </form>
</section>

<section>
    <h3>Lista nieruchomości</h3>
    <form method="get" class="estateoffice-search-form">
        <input type="text" name="eo_property_search" value="<?php echo esc_attr($search); ?>" placeholder="Szukaj: numer, typ transakcji, rodzaj, stan prawny" />
        <button type="submit" class="button">Szukaj</button>
    </form>

    <div class="estateoffice-table-wrap">
        <table class="estateoffice-table">
            <thead>
                <tr>
                    <th>Numer oferty</th>
                    <th>Adres</th>
                    <th>Cena</th>
                    <th>Cena za m²</th>
                    <th>Metraż</th>
                    <th>Liczba pokoi</th>
                    <th>Opiekun</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($properties)) : ?>
                <tr><td colspan="7">Brak nieruchomości.</td></tr>
            <?php else : ?>
                <?php foreach ($properties as $property) :
                    $address = json_decode((string) ($property['address_data'] ?? ''), true);
                    $pricing = json_decode((string) ($property['pricing_data'] ?? ''), true);
                    $details = json_decode((string) ($property['details_data'] ?? ''), true);

                    $addressLabel = is_array($address)
                        ? trim(((string) ($address['street'] ?? '')) . ' ' . ((string) ($address['number'] ?? '')) . ', ' . ((string) ($address['city'] ?? '')))
                        : '';
                    $price = is_array($pricing) ? (string) ($pricing['price'] ?? '') : '';
                    $pricePerM2 = is_array($pricing) ? (string) ($pricing['price_per_m2'] ?? '') : '';
                    $area = is_array($pricing) ? (string) ($pricing['area'] ?? '') : '';
                    $rooms = is_array($details) ? (string) ($details['rooms'] ?? '') : '';

                    $owner = ! empty($property['owner_user_id']) ? get_userdata((int) $property['owner_user_id']) : null;
                    $ownerName = $owner instanceof WP_User ? $owner->display_name : '—';
                    ?>
                    <tr>
                        <td><a href="#"><?php echo esc_html((string) $property['offer_number']); ?></a></td>
                        <td><?php echo esc_html($addressLabel !== '' ? $addressLabel : '—'); ?></td>
                        <td><?php echo esc_html($price); ?></td>
                        <td><?php echo esc_html($pricePerM2); ?></td>
                        <td><?php echo esc_html($area); ?></td>
                        <td><?php echo esc_html($rooms); ?></td>
                        <td><?php echo esc_html($ownerName); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
