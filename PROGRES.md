# Progres prac — EstateOffice CRM

## 0.5.1 (zakończone)
- Dodano moduł `EstateOffice_Properties` z bezpiecznym endpointem `admin-post` do tworzenia nieruchomości.
- Wdrożono walidację i sanityzację danych nieruchomości:
  - numer oferty (unikalny),
  - typ transakcji,
  - rodzaj nieruchomości,
  - dane adresowe,
  - dane cenowe i metraż.
- Dodano zapis nieruchomości do własnej tabeli `eo_properties` wraz z sekcjami JSON (`address_data`, `pricing_data`, `details_data`, `tags_data`).
- Wdrożono dynamiczny formularz CRM Nieruchomości:
  - przełączanie pól zależnych od rodzaju nieruchomości,
  - obsługa pola „Brak KW”,
  - automatyczne wyliczanie ceny za m².
- Dodano listę nieruchomości z wyszukiwarką i wymaganymi kolumnami:
  - Numer oferty,
  - Adres,
  - Cena,
  - Cena za m²,
  - Metraż,
  - Liczba pokoi,
  - Opiekun.

## 0.4.2 (zakończone)
- Rozszerzono moduł umów o widok listy umów z wyszukiwarką.
- Dodano profil umowy:
  - dane podstawowe umowy,
  - lista klientów powiązanych,
  - historia etapów (Data, Etap).
- Dodano bezpieczną aktualizację etapu umowy (`admin-post`):
  - kontrola uprawnień,
  - nonce,
  - walidacja dozwolonych etapów,
  - zapis aktualnego etapu i dopisanie wpisu do historii etapów.
- Uporządkowano przepływ widoków umów (`list` / `wizard` / `profile`) i nawigację między nimi.

## 0.4.1 (zakończone)
- Dodano moduł umów (`EstateOffice_Agreements`) z bezpiecznymi handlerami `admin-post`.
- Wdrożono **Etap 1: Nowa Umowa**:
  - numer umowy z kontrolą duplikatu,
  - typ transakcji,
  - data zawarcia / data zakończenia,
  - umowa bezterminowa (dynamicznie blokuje datę zakończenia),
  - prowizja (kwota + jednostka),
  - automatyczny zapis pierwszego etapu historii: „Umowa Pośrednictwa”.
- Wdrożono **Etap 2: Dodawanie Klienta**:
  - przypisanie istniejącego klienta do umowy,
  - dodanie nowego klienta bezpośrednio z poziomu umowy i przypisanie,
  - obsługa „Czy chcesz dodać kolejnego klienta? TAK/NIE”.
- Dodano listę klientów przypisanych do danej umowy w kroku 2.
- Dodano komunikat przejścia do kolejnych etapów 3a/3b zależnie od typu transakcji (implementacja 3a i 3b planowana na kolejne wersje).
- Rozszerzono JS/CSS pod dynamiczne elementy formularza umowy.

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

## Następny cel: 0.6.0
- Rozszerzenie funkcjonalności pozostałych modułów (poszukiwania i integracje).
