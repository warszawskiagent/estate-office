<?php
if (! defined('ABSPATH')) {
    exit;
}

$status = isset($_GET['eo_status']) ? sanitize_key(wp_unslash((string) $_GET['eo_status'])) : '';
$message = isset($_GET['eo_message']) ? sanitize_text_field(rawurldecode(wp_unslash((string) $_GET['eo_message']))) : '';
$viewMode = isset($_GET['eo_view_mode']) ? sanitize_key(wp_unslash((string) $_GET['eo_view_mode'])) : 'list';
$step = isset($_GET['eo_step']) ? (int) $_GET['eo_step'] : 1;
$agreementId = isset($_GET['agreement_id']) ? (int) $_GET['agreement_id'] : 0;

$search = isset($_GET['eo_agreement_search']) ? sanitize_text_field(wp_unslash((string) $_GET['eo_agreement_search'])) : '';
$agreements = EstateOffice_Agreements::get_agreements($search);
$agreement = $agreementId > 0 ? EstateOffice_Agreements::get_agreement($agreementId) : null;
$linkedClients = $agreementId > 0 ? EstateOffice_Agreements::get_linked_clients($agreementId) : [];
$stageHistory = $agreementId > 0 ? EstateOffice_Agreements::get_stage_history($agreementId) : [];
$relatedProperties = $agreementId > 0 ? EstateOffice_Agreements::get_related_properties($agreementId) : [];
$relatedSearches = $agreementId > 0 ? EstateOffice_Agreements::get_related_searches($agreementId) : [];
$stageOptions = EstateOffice_Agreements::get_stage_options();

$clientSearch = isset($_GET['eo_client_search']) ? sanitize_text_field(wp_unslash((string) $_GET['eo_client_search'])) : '';
$clients = EstateOffice_Agreements::search_clients($clientSearch);
?>
<h2>Umowy</h2>

<?php if ($status === 'success' && $message !== '') : ?>
    <div class="estateoffice-notice estateoffice-notice--success"><?php echo esc_html($message); ?></div>
<?php elseif ($status === 'error' && $message !== '') : ?>
    <div class="estateoffice-notice estateoffice-notice--error"><?php echo esc_html($message); ?></div>
<?php endif; ?>

<p>
    <a class="button button-primary" href="<?php echo esc_url(add_query_arg(['eo_view_mode' => 'wizard', 'eo_step' => 1])); ?>">Dodaj nową Umowę</a>
    <a class="button" href="<?php echo esc_url(remove_query_arg(['eo_view_mode', 'eo_step', 'agreement_id', 'eo_client_search'])); ?>">Lista umów</a>
</p>

<?php if ($viewMode === 'wizard') : ?>
    <section class="estateoffice-stepper">
        <strong>Proces:</strong> Etap 1. Nowa Umowa → Etap 2. Dodawanie Klienta → Etap 3a/3b (kolejne wersje)
    </section>

    <?php if ($step <= 1 || ! $agreement) : ?>
        <section>
            <h3>Etap 1: Nowa umowa</h3>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="estateoffice-grid estateoffice-grid--2">
                <?php wp_nonce_field('estateoffice_agreement_step1'); ?>
                <input type="hidden" name="action" value="estateoffice_agreement_step1" />

                <label>Numer umowy <input type="text" name="agreement_number" required /></label>
                <label>Typ transakcji
                    <select name="transaction_type" required>
                        <option value="SPRZEDAŻ">SPRZEDAŻ</option>
                        <option value="KUPNO">KUPNO</option>
                        <option value="WYNAJEM">WYNAJEM</option>
                        <option value="NAJEM">NAJEM</option>
                    </select>
                </label>
                <label>Data zawarcia <input type="date" name="start_date" required /></label>
                <label>Data zakończenia <input type="date" name="end_date" id="eo-agreement-end-date" /></label>
                <label><input type="checkbox" name="is_open_ended" value="1" id="eo-agreement-open-ended" /> Umowa bezterminowa</label>
                <label>Wysokość prowizji <input type="number" step="0.01" min="0" name="commission_value" required /></label>
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
            <p><strong>Umowa:</strong> <?php echo esc_html((string) $agreement['agreement_number']); ?> | <strong>Typ:</strong> <?php echo esc_html((string) $agreement['transaction_type']); ?></p>

            <h4>Klienci przypisani</h4>
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

            <h4>Wybierz istniejącego klienta</h4>
            <form method="get" class="estateoffice-search-form">
                <input type="hidden" name="eo_view_mode" value="wizard" />
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
                            <option value="<?php echo esc_attr((string) $client['id']); ?>"><?php echo esc_html($display !== '' ? $display : '—'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p><button type="submit" class="button">Przypisz klienta</button></p>
            </form>

            <h4>Dodaj nowego klienta do umowy</h4>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="estateoffice-grid estateoffice-grid--2">
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

            <h4>Czy chcesz dodać kolejnego klienta?</h4>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="estateoffice-grid estateoffice-grid--2">
                <?php wp_nonce_field('estateoffice_agreement_step2_continue'); ?>
                <input type="hidden" name="action" value="estateoffice_agreement_step2_continue" />
                <input type="hidden" name="agreement_id" value="<?php echo esc_attr((string) $agreementId); ?>" />

                <label>Wybór
                    <select name="add_another_client">
                        <option value="yes">TAK</option>
                        <option value="no">NIE</option>
                    </select>
                </label>
                <p><button type="submit" class="button button-primary">DALEJ</button></p>
            </form>
        </section>
    <?php endif; ?>

<?php elseif ($viewMode === 'profile' && $agreement) : ?>
    <section>
        <h3>Profil umowy</h3>
        <div class="estateoffice-grid estateoffice-grid--2">
            <div>
                <p><strong>Numer umowy:</strong> <?php echo esc_html((string) $agreement['agreement_number']); ?></p>
                <p><strong>Typ transakcji:</strong> <?php echo esc_html((string) $agreement['transaction_type']); ?></p>
                <p><strong>Data zawarcia:</strong> <?php echo esc_html((string) $agreement['start_date']); ?></p>
                <p><strong>Data zakończenia:</strong> <?php echo esc_html((string) ($agreement['end_date'] ?: 'Bezterminowa')); ?></p>
                <p><strong>Prowizja:</strong> <?php echo esc_html((string) $agreement['commission_value'] . ' ' . (string) $agreement['commission_unit']); ?></p>
            </div>
            <div>
                <p><strong>Aktualny etap:</strong> <?php echo esc_html((string) $agreement['current_stage']); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="estateoffice-grid">
                    <?php wp_nonce_field('estateoffice_agreement_update_stage'); ?>
                    <input type="hidden" name="action" value="estateoffice_agreement_update_stage" />
                    <input type="hidden" name="agreement_id" value="<?php echo esc_attr((string) $agreement['id']); ?>" />

                    <label>Zmiana etapu
                        <select name="stage_name" required>
                            <?php foreach ($stageOptions as $opt) : ?>
                                <option value="<?php echo esc_attr($opt); ?>" <?php selected($agreement['current_stage'], $opt); ?>><?php echo esc_html($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Data <input type="date" name="stage_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required /></label>
                    <p><button type="submit" class="button">Aktualizuj etap</button></p>
                </form>
            </div>
        </div>

        <h4>Historia etapów</h4>
        <div class="estateoffice-table-wrap">
            <table class="estateoffice-table">
                <thead><tr><th>Data</th><th>Etap</th></tr></thead>
                <tbody>
                <?php if (empty($stageHistory)) : ?>
                    <tr><td colspan="2">Brak historii.</td></tr>
                <?php else : ?>
                    <?php foreach ($stageHistory as $stage) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $stage['stage_date']); ?></td>
                            <td><?php echo esc_html((string) $stage['stage_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h4>Klienci powiązani</h4>
        <ul>
            <?php if (empty($linkedClients)) : ?>
                <li>Brak powiązanych klientów.</li>
            <?php else : ?>
                <?php foreach ($linkedClients as $client) :
                    $name = $client['client_type'] === 'company'
                        ? ((string) ($client['company_name'] ?: $client['representative_name']))
                        : trim(((string) $client['first_name']) . ' ' . ((string) $client['last_name']));
                    ?>
                    <li><?php echo esc_html($name); ?></li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>


        <h4>Nieruchomości powiązane</h4>
        <ul>
            <?php if (empty($relatedProperties)) : ?>
                <li>Brak powiązanych nieruchomości.</li>
            <?php else : ?>
                <?php foreach ($relatedProperties as $property) : ?>
                    <li><?php echo esc_html((string) $property['offer_number'] . ' — ' . (string) $property['property_type'] . ' (' . (string) $property['transaction_type'] . ')'); ?></li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>

        <h4>Poszukiwania powiązane</h4>
        <ul>
            <?php if (empty($relatedSearches)) : ?>
                <li>Brak powiązanych poszukiwań.</li>
            <?php else : ?>
                <?php foreach ($relatedSearches as $searchItem) : ?>
                    <li><?php echo esc_html((string) $searchItem['search_number'] . ' — ' . (string) $searchItem['property_type'] . ' (' . (string) $searchItem['transaction_type'] . ')'); ?></li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </section>

<?php else : ?>
    <section>
        <h3>Lista umów</h3>
        <form method="get" class="estateoffice-search-form">
            <input type="text" name="eo_agreement_search" value="<?php echo esc_attr($search); ?>" placeholder="Szukaj: numer, typ, etap" />
            <button type="submit" class="button">Szukaj</button>
        </form>

        <div class="estateoffice-table-wrap">
            <table class="estateoffice-table">
                <thead>
                    <tr>
                        <th>Numer umowy</th>
                        <th>Typ transakcji</th>
                        <th>Data zawarcia</th>
                        <th>Data zakończenia</th>
                        <th>Aktualny etap</th>
                        <th>Opiekun</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agreements)) : ?>
                        <tr><td colspan="6">Brak umów.</td></tr>
                    <?php else : ?>
                        <?php foreach ($agreements as $item) :
                            $owner = ! empty($item['owner_user_id']) ? get_userdata((int) $item['owner_user_id']) : null;
                            $ownerName = $owner instanceof WP_User ? $owner->display_name : '—';
                            $profileUrl = add_query_arg([
                                'eo_view_mode' => 'profile',
                                'agreement_id' => (int) $item['id'],
                            ]);
                            ?>
                            <tr>
                                <td><a href="<?php echo esc_url($profileUrl); ?>"><?php echo esc_html((string) $item['agreement_number']); ?></a></td>
                                <td><?php echo esc_html((string) $item['transaction_type']); ?></td>
                                <td><?php echo esc_html((string) $item['start_date']); ?></td>
                                <td><?php echo esc_html((string) ($item['end_date'] ?: 'Bezterminowa')); ?></td>
                                <td><?php echo esc_html((string) $item['current_stage']); ?></td>
                                <td><?php echo esc_html($ownerName); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
