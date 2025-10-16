# EstateOffice CRM – Roadmap funkcjonalności

Autor: Tomasz Obarski  \\
Strona: http://warszawskiagent.pl  \\
Docelowe wersje: 0.1 – 1.0  \\
Minimalne wymagania: PHP 8.3, WordPress 6.8.3

## Status rozwoju (aktualizacja 0.5.0)
Poniższa tabela prezentuje wszystkie kluczowe moduły planowane dla wtyczki EstateOffice wraz z etapem realizacji, przewidzianą wersją wdrożenia oraz orientacyjnym postępem.

| Moduł / Funkcjonalność | Opis skrócony | Wersja docelowa | Postęp |
| --- | --- | --- | --- |
| Fundament wtyczki | Struktura plików, autoloader i hooki debug | 0.1.0 | 100% |
| Panel administratora – menu główne | Menu główne CRM i podstrony | 0.1.0 | 80% |
| Baza danych – tabele customowe | Tabele nieruchomości, klientów, umów, poszukiwań, agentów | 0.2.0 | 70% |
| Zarządzanie agentami | Profil agenta, rola i ograniczenia uprawnień | 0.3.0 | 60% |
| Ustawienia globalne | API Google, branding i dynamiczne pola | 0.3.0 | 55% |
| Moduł nieruchomości | CRUD nieruchomości, galerie, znaczniki, eksport WWW | 0.4.0 | 55% |
| Moduł poszukiwań | CRUD poszukiwań klientów | 0.5.0 | 40% |
| Moduł umów | Proces dodawania i etapy umowy | 0.6.0 | 0% |
| Moduł klientów | Profil klienta i powiązania danych | 0.6.0 | 0% |
| Dashboard CRM | Pulpit podsumowań i wskaźników | 0.7.0 | 0% |
| Frontend CRM (po zalogowaniu) | Widoki list i szczegółów dla użytkowników | 0.7.0 | 0% |
| Publiczne oferty nieruchomości | Strony ofert z kategoryzacją | 0.8.0 | 0% |
| System licencyjny | Aktywacja licencji i ograniczenia | 0.9.0 | 0% |
| Integracje z portalami | Eksport danych na portale zewnętrzne | 0.9.0 | 0% |
| Kalkulator notarialny | Kalkulator kosztów notarialnych | 1.0.0 | 0% |
| Kalkulator kredytowy | Kalkulator zdolności kredytowej | 1.0.0 | 0% |


## Plan iteracyjny
- **Wersja 0.1.0 (zakończona)**: Budowa fundamentów – plik główny wtyczki, ładowanie klas, konfiguracja debug oraz przygotowanie miejsc pod menu administratora. Testy funkcjonalne rozpoczną się po implementacji pierwszych ekranów (szacowany termin: wersja 0.3.0).
- **Wersja 0.2.0**: Utworzenie dedykowanych tabel w bazie danych i modelu danych wraz z instalatorem aktualizującym schemat. Planowane wewnętrzne testy instalatora oraz migracji danych.
- **Wersja 0.3.0**: Moduł agentów oraz ustawienia globalne (API Google, branding, pola dynamiczne). Testy funkcjonalne części administratora (agenci i konfiguracja) trwają i zostaną zamknięte po stabilizacji formularzy.
- **Wersja 0.4.0 (zakończona)**: Pierwsza iteracja modułu nieruchomości – formularz CRUD w panelu administratora, repozytorium danych, obsługa galerii i oznaczeń oraz walidacja numerów ofert. Testy modułu nieruchomości prowadzone są po każdej migracji danych; zakończenie planowane po iteracji 0.5.0.
- **Wersja 0.5.0 (bieżąca)**: Moduł poszukiwań – lista z filtrami, formularz preferencji oraz repozytorium z walidacją numerów. Rozpoczęto przygotowania do powiązań z umowami i klientami.
- **Wersja 0.6.0**: Kolejne moduły CRUD (umowy, klienci) wraz z powiązaniami danych.
- **Wersja 0.7.0**: Dashboard CRM i frontend po zalogowaniu.
- **Wersja 0.8.0 – 0.9.0**: Publiczne oferty oraz system licencyjny i integracje.
- **Wersja 1.0.0**: Kalkulatory notarialny i kredytowy, finalne testy akceptacyjne oraz publikacja stabilna.

## Komunikaty projektowe
- **Wprowadzone w tej wersji (0.5.0)**: moduł poszukiwań klientów z filtrowaną listą, formularzem preferencji (budynek, media, udogodnienia, powierzchnie dodatkowe) oraz repozytorium z automatyczną numeracją i walidacją unikalności.
- **Wprowadzone wcześniej (0.4.0)**: panel nieruchomości z listą, filtrowaniem i pełnym formularzem edycji (galerie, multimedia, oznaczenia, adresy i parametry techniczne), repozytorium nieruchomości z walidacją numerów ofert i automatycznym wygaszaniem znacznika "Nowa oferta".
- **Wprowadzone wcześniej (0.3.0)**: panel zarządzania agentami (tworzenie użytkowników, biografia, zdjęcie, kontakt), rola Agent z dedykowanymi uprawnieniami, ekran ustawień z konfiguracją API Google, brandingiem oraz dynamicznymi polami.
- **Wprowadzone wcześniej (0.2.0)**: dodano instalator bazy danych z automatyczną migracją schematu oraz kompletne definicje tabel dla agentów, klientów, umów, nieruchomości i poszukiwań.
- **Zrealizowane wcześniej (0.1.0)**: utworzenie roadmapy i fundamentów strukturalnych wtyczki (plik główny, klasy ładowania, rejestracja menu placeholder).
- **Następne kroki**: rozwinięcie powiązań nieruchomości z umowami, implementacja modułu poszukiwań i klientów (0.5.0) oraz przygotowanie testów integracyjnych.
- **Harmonogram testów**: testy migracji schematu startują w 0.2.0, sanity-checki formularzy nieruchomości prowadzone są po każdej aktualizacji 0.4.x; pełne testy CRUD zaplanowane są na koniec wersji 0.5.0.


