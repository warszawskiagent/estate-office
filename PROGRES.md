# Progres prac — EstateOffice CRM

## 0.3.0 (zakończone)
- Dodano moduł klientów (CRM frontend) z pełnym formularzem dodawania do własnej tabeli `eo_clients`.
- Wdrożono dynamiczny formularz klienta:
  - przełączanie pól Osoba fizyczna/Firma,
  - adres korespondencyjny zależny od checkboxa „taki sam”.
- Dodano bezpieczny endpoint `admin-post` do tworzenia klientów:
  - weryfikacja logowania,
  - kontrola uprawnień (`estateoffice_manage_clients`),
  - nonce,
  - sanityzacja i walidacja danych,
  - zapis JSON adresów do tabeli pluginu.
- Dodano listę klientów z kolumnami zgodnymi z założeniem etapu 0.3 oraz wyszukiwarkę po kluczowych polach.
- Rozszerzono CSS/JS CRM pod dynamiczne formularze i czytelny widok tabeli.

## 0.2.0 (zakończone)
- Rozszerzono panel administratora EstateOffice CRM.
- Dodano bezpieczne akcje `admin-post` z kontrolą uprawnień i nonce:
  - zapis ustawień,
  - tworzenie agenta.
- Dodano warstwę ustawień (`EstateOffice_Settings`) zapisującą dane do własnej tabeli `eo_settings`.
- Dodano funkcjonalny formularz ustawień:
  - Google Maps API key,
  - URL znaku wodnego,
  - URL logo biura,
  - pola dynamiczne (JSON) dla nieruchomości, umów i klientów.
- Dodano funkcjonalny formularz tworzenia agenta (imię, nazwisko, e-mail, telefon, bio) + lista agentów.
- Poprawiono frontendową nawigację CRM — zakładki prowadzą do właściwych stron utworzonych przy aktywacji.

## 0.1.0 (zakończone)
- Utworzono strukturę wtyczki (`estate-office/`) z bootstrapem i klasami inicjalizującymi.
- Dodano instalator aktywacyjny tworzący komplet bazowych tabel CRM:
  - klienci,
  - umowy,
  - historia etapów,
  - relacje umowa-klient,
  - nieruchomości,
  - multimedia nieruchomości,
  - poszukiwania,
  - ustawienia.
- Dodano rolę użytkownika `Agent Nieruchomości` z ograniczonymi uprawnieniami.
- Dodano automatyczne tworzenie domyślnego konta agenta przy aktywacji.
- Dodano automatyczne tworzenie stron CRM i stron ofert publicznych.
- Dodano podstawowe shortcody i szkielety widoków frontend CRM.
- Dodano podstawowe menu administratora Estate Office CRM (About / Agenci / Ustawienia / Licencja).

## Następny cel: 0.4.0
- W pełni funkcjonalne dodawanie umów.
