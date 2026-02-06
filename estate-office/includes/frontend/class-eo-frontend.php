<?php

if (!defined('ABSPATH')) {
    exit;
}

class EstateOffice_Frontend
{
    public static function render_crm()
    {
        if (!is_user_logged_in()) {
            return '<p>Ta sekcja jest dostępna tylko dla zalogowanych użytkowników.</p>';
        }

        if (!current_user_can('estateoffice_access_crm')) {
            return '<p>Brak uprawnień do CRM.</p>';
        }

        wp_enqueue_style('estateoffice-frontend');
        wp_enqueue_script('estateoffice-frontend');

        ob_start();
        ?>
        <div class="estateoffice-crm">
            <div class="estateoffice-crm__header">
                <h2>EstateOffice CRM</h2>
                <a class="estateoffice-button" href="#">Dodaj nową Umowę</a>
            </div>
            <nav class="estateoffice-crm__nav">
                <button class="estateoffice-tab" data-tab="dashboard">Pulpit</button>
                <button class="estateoffice-tab" data-tab="properties">Nieruchomości</button>
                <button class="estateoffice-tab" data-tab="searches">Poszukiwania</button>
                <button class="estateoffice-tab" data-tab="contracts">Umowy</button>
                <button class="estateoffice-tab" data-tab="clients">Klienci</button>
            </nav>

            <section class="estateoffice-crm__panel" id="estateoffice-tab-dashboard">
                <h3>Pulpit</h3>
                <div class="estateoffice-cards">
                    <div class="estateoffice-card">Liczba nieruchomości: <strong>0</strong></div>
                    <div class="estateoffice-card">Aktywne umowy: <strong>0</strong></div>
                    <div class="estateoffice-card">Poszukiwania: <strong>0</strong></div>
                    <div class="estateoffice-card">Najlepsi agenci: <strong>Brak danych</strong></div>
                </div>
            </section>

            <section class="estateoffice-crm__panel" id="estateoffice-tab-properties" hidden>
                <h3>Nieruchomości</h3>
                <input class="estateoffice-search" type="search" placeholder="Szukaj po wszystkich kolumnach" />
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
                        <tr>
                            <td colspan="7">Brak danych w wersji 0.0.1.</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="estateoffice-crm__panel" id="estateoffice-tab-searches" hidden>
                <h3>Poszukiwania</h3>
                <input class="estateoffice-search" type="search" placeholder="Szukaj po wszystkich kolumnach" />
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
                        <tr>
                            <td colspan="5">Brak danych w wersji 0.0.1.</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="estateoffice-crm__panel" id="estateoffice-tab-contracts" hidden>
                <h3>Umowy</h3>
                <input class="estateoffice-search" type="search" placeholder="Szukaj po wszystkich kolumnach" />
                <table class="estateoffice-table">
                    <thead>
                        <tr>
                            <th>Numer umowy</th>
                            <th>Typ transakcji</th>
                            <th>Rodzaj nieruchomości</th>
                            <th>Adres</th>
                            <th>Data zawarcia</th>
                            <th>Data zakończenia</th>
                            <th>Aktualny etap</th>
                            <th>Opiekun</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="8">Brak danych w wersji 0.0.1.</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="estateoffice-crm__panel" id="estateoffice-tab-clients" hidden>
                <h3>Klienci</h3>
                <input class="estateoffice-search" type="search" placeholder="Szukaj po wszystkich kolumnach" />
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
                        <tr>
                            <td colspan="5">Brak danych w wersji 0.0.1.</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="estateoffice-crm__panel" id="estateoffice-tab-contract-form" hidden>
                <h3>Dodaj nową umowę</h3>
                <form class="estateoffice-form">
                    <label>
                        Numer umowy
                        <input type="text" name="contract_number" required />
                    </label>
                    <label>
                        Typ transakcji
                        <select name="transaction_type" required>
                            <option value="SPRZEDAZ">SPRZEDAŻ</option>
                            <option value="KUPNO">KUPNO</option>
                            <option value="WYNAJEM">WYNAJEM</option>
                            <option value="NAJEM">NAJEM</option>
                        </select>
                    </label>
                    <label>
                        Data zawarcia
                        <input type="date" name="signed_date" required />
                    </label>
                    <label>
                        Data zakończenia
                        <input type="date" name="end_date" data-estateoffice-end-date />
                    </label>
                    <label class="estateoffice-checkbox">
                        <input type="checkbox" name="open_ended" data-estateoffice-open-ended />
                        Umowa bezterminowa
                    </label>
                    <fieldset>
                        <legend>Wysokość prowizji</legend>
                        <label>
                            Kwota
                            <input type="number" step="0.01" name="commission_amount" />
                        </label>
                        <label>
                            Jednostka
                            <select name="commission_unit">
                                <option value="%">%</option>
                                <option value="PLN">PLN</option>
                                <option value="EUR">EUR</option>
                                <option value="USD">USD</option>
                            </select>
                        </label>
                    </fieldset>
                    <button type="button" class="estateoffice-button">DALEJ</button>
                </form>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_offers()
    {
        ob_start();
        ?>
        <div class="estateoffice-offers">
            <h2>Oferty</h2>
            <p>Oferty eksportowane na WWW pojawią się tutaj w kolejnych wersjach.</p>
        </div>
        <?php
        return ob_get_clean();
    }
}
