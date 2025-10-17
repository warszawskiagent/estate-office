# EstateOffice CRM – Roadmap funkcjonalności

Autor: Tomasz Obarski  \\
Strona: http://warszawskiagent.pl  \\
Docelowe wersje: 0.1 – 1.0  \\
Minimalne wymagania: PHP 8.3, WordPress 6.8.3

## Status rozwoju (aktualizacja 0.9.2)
Poniższa tabela prezentuje wszystkie kluczowe moduły planowane dla wtyczki EstateOffice wraz z etapem realizacji, przewidzianą wersją wdrożenia oraz orientacyjnym postępem.

| Moduł / Funkcjonalność | Opis skrócony | Wersja docelowa | Postęp |
| --- | --- | --- | --- |
| Fundament wtyczki | Struktura plików, autoloader i hooki debug | 0.1.0 | 100% |
| Panel administratora – menu główne | Menu główne CRM i podstrony | 0.1.0 | 90% |
| Baza danych – tabele customowe | Tabele nieruchomości, klientów, umów, poszukiwań, agentów | 0.2.0 | 85% |
| Zarządzanie agentami | Profil agenta, rola i ograniczenia uprawnień | 0.3.0 | 80% |
| Ustawienia globalne | API Google, branding i dynamiczne pola | 0.3.0 | 80% |
| Moduł nieruchomości | CRUD nieruchomości, galerie, znaczniki, eksport WWW | 0.4.0 | 90% |
| Moduł poszukiwań | CRUD poszukiwań klientów | 0.5.0 | 80% |
| Moduł umów | Proces dodawania i etapy umowy | 0.6.0 | 75% |
| Moduł klientów | Profil klienta i powiązania danych | 0.6.0 | 75% |
| Dashboard CRM | Pulpit podsumowań i wskaźników | 0.7.0 | 80% |
| Frontend CRM (po zalogowaniu) | Widoki list i szczegółów dla użytkowników | 0.7.0 | 75% |
| Publiczne oferty nieruchomości | Strony ofert z kategoryzacją | 0.8.0 | 80% |
| System licencyjny | Aktywacja licencji i ograniczenia | 0.9.0 | 70% |
| Integracje z portalami | Eksport danych na portale zewnętrzne | 0.9.0 | 10% |
| Kalkulator notarialny | Kalkulator kosztów notarialnych | 1.0.0 | 0% |
| Kalkulator kredytowy | Kalkulator zdolności kredytowej | 1.0.0 | 0% |


## Plan iteracyjny
- **Wersja 0.1.0 (zakończona)**: Budowa fundamentów – plik główny wtyczki, ładowanie klas, konfiguracja debug oraz przygotowanie miejsc pod menu administratora. Testy funkcjonalne rozpoczną się po implementacji pierwszych ekranów (szacowany termin: wersja 0.3.0).
- **Wersja 0.2.0**: Utworzenie dedykowanych tabel w bazie danych i modelu danych wraz z instalatorem aktualizującym schemat. Planowane wewnętrzne testy instalatora oraz migracji danych.
- **Wersja 0.3.0**: Moduł agentów oraz ustawienia globalne (API Google, branding, pola dynamiczne). Testy funkcjonalne części administratora (agenci i konfiguracja) trwają i zostaną zamknięte po stabilizacji formularzy.
- **Wersja 0.4.0 (zakończona)**: Pierwsza iteracja modułu nieruchomości – formularz CRUD w panelu administratora, repozytorium danych, obsługa galerii i oznaczeń oraz walidacja numerów ofert. Testy modułu nieruchomości prowadzone są po każdej migracji danych; zakończenie planowane po iteracji 0.5.0.
- **Wersja 0.5.0 (zakończona)**: Moduł poszukiwań – lista z filtrami, formularz preferencji oraz repozytorium z walidacją numerów. Rozpoczęto przygotowania do powiązań z umowami i klientami.
- **Wersja 0.6.0 (zakończona)**: Moduł umów i klientów – wieloetapowy kreator umów, zarządzanie etapami, profil klienta i powiązania z nieruchomościami/poszukiwaniami.
- **Wersja 0.7.0 (zakończona)**: Dashboard CRM i frontend po zalogowaniu.
- **Wersja 0.8.0 (zakończona)**: Publiczne strony ofert z filtrowaniem i widokiem szczegółowym.
- **Wersja 0.9.2 (bieżąca)**: Uspójnienie procesu dodawania nieruchomości oraz unifikacja stylów formularzy w całym panelu administracyjnym, aby przyspieszyć pracę agentów przed finalizacją eksportów na portale.
- **Wersja 1.0.0**: Kalkulatory notarialny i kredytowy, finalne testy akceptacyjne oraz publikacja stabilna.

## Komunikaty projektowe
- **Wprowadzone w tej wersji (0.9.2)**: finalizacja przepływu dodawania nieruchomości (powiązania z umową, walidacje i synchronizacja relacji) wraz z jednolitym CSS dla wszystkich formularzy administratora, w tym kart licencyjnych oraz kreatorów CRUD.
- **Wprowadzone wcześniej (0.9.0)**: moduł zarządzania licencją z aktywacją, dezaktywacją i harmonogramem kontroli, a także wstępna konfiguracja integracji portali.
- **Wprowadzone wcześniej (0.8.0)**: automatyczne tworzenie publicznych stron „Oferty na sprzedaż” i „Oferty na wynajem” z filtrowaniem według typu transakcji, typu nieruchomości, miasta i dzielnicy, widokiem kart ofert, paginacją oraz sekcją szczegółów ze zdjęciami.
- **Wprowadzone wcześniej (0.7.0)**: interaktywny pulpit administratora z metrykami, rankingiem agentów i historią etapów oraz portal frontendowy CRM dla zalogowanych użytkowników z zakładkami (pulpit, nieruchomości, poszukiwania, umowy, klienci) i widokami szczegółowymi.
- **Wprowadzone wcześniej (0.6.0)**: kreator umów z etapami i historią, przypisywanie klientów (wyszukiwanie + szybkie tworzenie), widok profilu umowy oraz pełny moduł klientów (lista, formularz, profil, powiązania). Integracja nieruchomości i poszukiwań z umowami.
- **Wprowadzone wcześniej (0.5.0)**: moduł poszukiwań klientów z filtrowaną listą, formularzem preferencji (budynek, media, udogodnienia, powierzchnie dodatkowe) oraz repozytorium z automatyczną numeracją i walidacją unikalności.
- **Wprowadzone wcześniej (0.4.0)**: panel nieruchomości z listą, filtrowaniem i pełnym formularzem edycji (galerie, multimedia, oznaczenia, adresy i parametry techniczne), repozytorium nieruchomości z walidacją numerów ofert i automatycznym wygaszaniem znacznika "Nowa oferta".
- **Wprowadzone wcześniej (0.3.0)**: panel zarządzania agentami (tworzenie użytkowników, biografia, zdjęcie, kontakt), rola Agent z dedykowanymi uprawnieniami, ekran ustawień z konfiguracją API Google, brandingiem oraz dynamicznymi polami.
- **Wprowadzone wcześniej (0.2.0)**: dodano instalator bazy danych z automatyczną migracją schematu oraz kompletne definicje tabel dla agentów, klientów, umów, nieruchomości i poszukiwań.
- **Zrealizowane wcześniej (0.1.0)**: utworzenie roadmapy i fundamentów strukturalnych wtyczki (plik główny, klasy ładowania, rejestracja menu placeholder).
- **Następne kroki**: dokończenie integracji eksportu na portale (0.9.x) oraz przygotowanie kalkulatorów finansowych (1.0.0).
- **Harmonogram testów**: testy migracji schematu startują w 0.2.0, sanity-checki formularzy nieruchomości prowadzone są po każdej aktualizacji 0.4.x; pełne testy CRUD dla umów i klientów zaplanowane są na zakończenie 0.6.x, a testy integracyjne CRM na 0.7.0.


