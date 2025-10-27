# EstateOffice CRM

Wersja 0.0.1 stanowi fundament wtyczki CRM dla biur nieruchomości. Rozszerzenie dostarcza:

- strukturę menu administracyjnego *Estate Office CRM* wraz z podstronami Pulpit, Agenci, Ustawienia, About oraz Licencja;
- moduł zarządzania agentami działający w oparciu o dedykowaną tabelę bazy danych;
- konfigurację pól dynamicznych dla nieruchomości, umów i klientów;
- podstawowe ustawienia globalne (klucz API Map Google, znak wodny, logo biura);
- zestaw niestandardowych tabel przygotowanych pod obsługę umów, nieruchomości, klientów i poszukiwań;
- rolę użytkownika *estate_agent* ograniczoną do funkcji CRM.

## Roadmapa rozwoju

### 0.1 – 0.4: Fundamenty danych i interfejsów
- **0.1** – finalizacja schematów bazodanowych dla umów, nieruchomości, klientów i poszukiwań wraz z kompletem akcji CRUD w panelu administracyjnym.
- **0.2** – wdrożenie pełnego procesu dodawania umowy (etapy 1–3a/3b) z walidacją, dynamicznymi formularzami oraz obsługą wielu klientów.
- **0.3** – rozbudowa profili (umowy, nieruchomości, poszukiwania, klienci) o widoki szczegółowe, historię etapów, powiązania oraz logikę ograniczeń edycyjnych.
- **0.4** – integracja z mediami WordPress: zarządzanie galerią, znakiem wodnym, plikami rzutów i linkami multimedialnymi.

### 0.5 – 0.8: Funkcje frontowe i automatyzacje
- **0.5** – przygotowanie front-endowego modułu CRM (po zalogowaniu) z pulpitem, tabelami i wyszukiwarką we wszystkich sekcjach.
- **0.6** – publikacja ofert nieruchomości na stronie WWW (eksport „na WWW”), kategoryzacja według transakcji/typu/miasta/dzielnicy oraz automatyczna eliminacja duplikatów kategorii.
- **0.7** – wdrożenie dedykowanych stron agentów oraz ograniczeń uprawnień nowej roli *estate_agent* w warstwie interfejsu.
- **0.8** – integracja z Google Maps, kalkulatorem notarialnym i kredytowym, konfiguracja ustawień API z poziomu panelu.

### 0.9 – 1.0: Optymalizacje i wydanie stabilne
- **0.9** – implementacja mechanizmów raportowania, widgetów pulpitu i automatycznych oznaczeń ofert (nowa cena, sprzedane, premium itd.).
- **1.0** – finalne testy bezpieczeństwa, optymalizacja wydajności zapytań, internacjonalizacja (i18n) i przygotowanie kompletu migracji produkcyjnych.

### 1.1: Moduł licencji
- Aktywacja i obsługa modułu licencyjnego w panelu *Estate Office CRM → Licencja*, w tym walidacja klucza, monitorowanie aktywnych instalacji oraz mechanizm dezaktywacji funkcji premium w przypadku wygasłej licencji.
