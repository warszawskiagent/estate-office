# EstateOffice CRM – Roadmap funkcjonalności

Autor: Tomasz Obarski  \\
Strona: http://warszawskiagent.pl  \\
Docelowe wersje: 0.1 – 1.0  \\
Minimalne wymagania: PHP 8.3, WordPress 6.8.3

## Status rozwoju (aktualizacja 0.3.0)
Poniższa tabela prezentuje wszystkie kluczowe moduły planowane dla wtyczki EstateOffice wraz z etapem realizacji, przewidzianą wersją wdrożenia oraz orientacyjnym postępem.

| Moduł / Funkcjonalność                                         | Opis skrócony                                                                                  | Wersja docelowa | Postęp |
| -------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- | --------------- | ------ |
| Fundament wtyczki                                               | Struktura plików, rejestracja wtyczki, autoloader, podstawowe hooki, logowanie debug.           | 0.1.0           | 100%  |
| Panel administratora – menu główne                              | Dodanie menu "Estate Office CRM" oraz ekranów roboczych.                                        | 0.1.0           | 60%   |
| Baza danych – tabele customowe                                  | Projekt i wdrożenie tabel dla nieruchomości, klientów, umów, poszukiwań, agentów.               | 0.2.0           | 60%   |
| Zarządzanie agentami                                            | Dodawanie/edycja profili agentów, typ roli Agent, ograniczenia uprawnień.                       | 0.3.0           | 60%   |
| Ustawienia globalne                                             | Konfiguracja API Google, znaku wodnego, logo, pól dynamicznych.                                 | 0.3.0           | 50%   |
| Moduł nieruchomości                                             | CRUD nieruchomości, galerie, znaczniki, eksport na WWW.                                         | 0.4.0           | 0%    |
| Moduł poszukiwań                                                | CRUD poszukiwań klientów.                                                                      | 0.5.0           | 0%    |
| Moduł umów                                                      | Wieloetapowy proces dodawania, obsługa etapów umowy.                                            | 0.6.0           | 0%    |
| Moduł klientów                                                  | Profil klienta, powiązania, wyszukiwarka.                                                      | 0.6.0           | 0%    |
| Dashboard CRM                                                   | Pulpit z podsumowaniami i widgetami.                                                           | 0.7.0           | 0%    |
| Frontend CRM (po zalogowaniu)                                   | Widoki list i szczegółów dla użytkowników zalogowanych.                                         | 0.7.0           | 0%    |
| Publiczne oferty nieruchomości                                  | Strony ofert eksportowanych, kategoryzacja, filtry.                                             | 0.8.0           | 0%    |
| System licencyjny                                               | Aktywacja licencji, walidacja, ograniczenia dostępu.                                            | 0.9.0           | 0%    |
| Integracje z portalami                                          | Eksport na portale zewnętrzne.                                                                  | 0.9.0           | 0%    |
| Kalkulator notarialny                                           | Narzędzie do kalkulacji kosztów notarialnych, integracja z ofertą.                              | 1.0.0           | 0%    |
| Kalkulator kredytowy                                            | Narzędzie kredytowe, integracja z ofertą.                                                       | 1.0.0           | 0%    |

## Plan iteracyjny
- **Wersja 0.1.0 (zakończona)**: Budowa fundamentów – plik główny wtyczki, ładowanie klas, konfiguracja debug oraz przygotowanie miejsc pod menu administratora. Testy funkcjonalne rozpoczną się po implementacji pierwszych ekranów (szacowany termin: wersja 0.3.0).
- **Wersja 0.2.0**: Utworzenie dedykowanych tabel w bazie danych i modelu danych wraz z instalatorem aktualizującym schemat. Planowane wewnętrzne testy instalatora oraz migracji danych.
- **Wersja 0.3.0 (bieżąca)**: Moduł agentów oraz ustawienia globalne (API Google, branding, pola dynamiczne). Testy funkcjonalne części administratora (agenci i konfiguracja) trwają i zostaną zamknięte po stabilizacji formularzy.
- **Wersja 0.4.0 – 0.6.0**: Kolejne moduły CRUD (nieruchomości, poszukiwania, umowy, klienci) wraz z zaawansowanymi formularzami i powiązaniami danych.
- **Wersja 0.7.0**: Dashboard CRM i frontend po zalogowaniu.
- **Wersja 0.8.0 – 0.9.0**: Publiczne oferty oraz system licencyjny i integracje.
- **Wersja 1.0.0**: Kalkulatory notarialny i kredytowy, finalne testy akceptacyjne oraz publikacja stabilna.

## Komunikaty projektowe
- **Wprowadzone w tej wersji (0.3.0)**: panel zarządzania agentami (tworzenie użytkowników, biografia, zdjęcie, kontakt), rola Agent z dedykowanymi uprawnieniami, ekran ustawień z konfiguracją API Google, brandingiem oraz dynamicznymi polami.
- **Wprowadzone wcześniej (0.2.0)**: dodano instalator bazy danych z automatyczną migracją schematu oraz kompletne definicje tabel dla agentów, klientów, umów, nieruchomości i poszukiwań.
- **Zrealizowane wcześniej (0.1.0)**: utworzenie roadmapy i fundamentów strukturalnych wtyczki (plik główny, klasy ładowania, rejestracja menu placeholder).
- **Następne kroki**: rozpoczęcie modułu nieruchomości (formularz dodawania, galerie, znaczniki) oraz przygotowanie wspólnych komponentów CRUD (iteracja 0.4.0).
- **Harmonogram testów**: testy migracji schematu startują w 0.2.0, a walidacja modułów agentów i ustawień odbywa się w trakcie 0.3.0 przed przejściem do wersji 0.4.0.


