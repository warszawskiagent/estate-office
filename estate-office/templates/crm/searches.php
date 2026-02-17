<?php
if (! defined('ABSPATH')) {
    exit;
}

$status = isset($_GET['eo_status']) ? sanitize_key(wp_unslash((string) $_GET['eo_status'])) : '';
$message = isset($_GET['eo_message']) ? sanitize_text_field(rawurldecode(wp_unslash((string) $_GET['eo_message']))) : '';
$search = isset($_GET['eo_searches_query']) ? sanitize_text_field(wp_unslash((string) $_GET['eo_searches_query'])) : '';
$searches = EstateOffice_Searches::get_searches($search);
?>
<h2>Poszukiwania</h2>

<?php if ($status === 'success' && $message !== '') : ?>
    <div class="estateoffice-notice estateoffice-notice--success"><?php echo esc_html($message); ?></div>
<?php elseif ($status === 'error' && $message !== '') : ?>
    <div class="estateoffice-notice estateoffice-notice--error"><?php echo esc_html($message); ?></div>
<?php endif; ?>

<section>
    <h3>Dodaj poszukiwanie</h3>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="estateoffice-grid estateoffice-grid--2" id="eo-search-form">
        <?php wp_nonce_field('estateoffice_create_search'); ?>
        <input type="hidden" name="action" value="estateoffice_create_search" />

        <label>Numer poszukiwania <input type="text" name="search_number" required /></label>
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
            <select name="property_type" required>
                <option value="MIESZKANIE">MIESZKANIE</option>
                <option value="DOM">DOM</option>
                <option value="DZIAŁKA">DZIAŁKA</option>
                <option value="LOKAL H/U">LOKAL H/U</option>
            </select>
        </label>

        <label>Budżet od <input type="number" step="0.01" min="0" name="budget_from" /></label>
        <label>Budżet do <input type="number" step="0.01" min="0" name="budget_to" /></label>
        <label>Metraż od <input type="number" step="0.01" min="0" name="area_from" /></label>
        <label>Metraż do <input type="number" step="0.01" min="0" name="area_to" /></label>
        <label>Pokoje od <input type="number" min="0" name="rooms_from" /></label>
        <label>Pokoje do <input type="number" min="0" name="rooms_to" /></label>

        <label>Lokalizacja <input type="text" name="location" placeholder="Miasto / dzielnica" /></label>
        <label>Ogrzewanie <input type="text" name="heating" /></label>
        <label>Woda <input type="text" name="water" /></label>
        <label>Kanalizacja <input type="text" name="sewage" /></label>
        <label>Umeblowanie
            <select name="furnished">
                <option value="">—</option>
                <option value="Tak">Tak</option>
                <option value="Nie">Nie</option>
                <option value="Częściowe">Częściowe</option>
            </select>
        </label>

        <fieldset>
            <legend>Media i udogodnienia</legend>
            <label><input type="checkbox" name="gas" /> Gaz</label>
            <label><input type="checkbox" name="elevator" /> Winda</label>
            <label><input type="checkbox" name="air_conditioning" /> Klimatyzacja</label>
            <label><input type="checkbox" name="monitoring" /> Monitoring/Ochrona</label>
        </fieldset>

        <fieldset>
            <legend>Wyposażenie</legend>
            <label><input type="checkbox" name="equipment_washing_machine" /> Pralka</label>
            <label><input type="checkbox" name="equipment_dishwasher" /> Zmywarka</label>
            <label><input type="checkbox" name="equipment_fridge" /> Lodówka</label>
            <label><input type="checkbox" name="equipment_oven" /> Piekarnik</label>
        </fieldset>

        <fieldset>
            <legend>Powierzchnie dodatkowe</legend>
            <label><input type="checkbox" name="extra_balcony" /> Balkon</label>
            <label><input type="checkbox" name="extra_terrace" /> Taras</label>
            <label><input type="checkbox" name="extra_basement" /> Piwnica</label>
            <label><input type="checkbox" name="extra_garden" /> Ogródek</label>
        </fieldset>

        <label>Opis poszukiwania
            <textarea name="description" rows="5"></textarea>
        </label>

        <p><button type="submit" class="button button-primary">Dodaj poszukiwanie</button></p>
    </form>
</section>

<section>
    <h3>Lista poszukiwań</h3>
    <form method="get" class="estateoffice-search-form">
        <input type="text" name="eo_searches_query" value="<?php echo esc_attr($search); ?>" placeholder="Szukaj: numer, typ transakcji, rodzaj" />
        <button type="submit" class="button">Szukaj</button>
    </form>

    <div class="estateoffice-table-wrap">
        <table class="estateoffice-table">
            <thead>
                <tr>
                    <th>Numer poszukiwania</th>
                    <th>Rodzaj nieruchomości</th>
                    <th>Budżet</th>
                    <th>Lokalizacja</th>
                    <th>Typ transakcji</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($searches)) : ?>
                    <tr><td colspan="5">Brak poszukiwań.</td></tr>
                <?php else : ?>
                    <?php foreach ($searches as $item) :
                        $criteria = json_decode((string) ($item['criteria_data'] ?? ''), true);
                        $location = is_array($criteria) ? (string) ($criteria['location'] ?? '') : '';
                        $budget = trim((string) ($item['budget_from'] ?? '') . ' - ' . (string) ($item['budget_to'] ?? ''));
                        ?>
                        <tr>
                            <td><a href="#"><?php echo esc_html((string) $item['search_number']); ?></a></td>
                            <td><?php echo esc_html((string) $item['property_type']); ?></td>
                            <td><?php echo esc_html($budget); ?></td>
                            <td><?php echo esc_html($location !== '' ? $location : '—'); ?></td>
                            <td><?php echo esc_html((string) $item['transaction_type']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
