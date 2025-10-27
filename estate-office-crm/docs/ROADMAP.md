# Roadmapa EstateOffice CRM (0.1 – 1.0)

Poniższy plan opisuje rozwój wtyczki od wersji 0.1 aż do wydania stabilnego 1.0.
Każda iteracja kończy się działającym, spójnym modułem, a jednocześnie przygotowuje
grunt pod kolejne rozszerzenia. Wszystkie zmiany są zgodne z wizją pełnego CRM
przedstawioną w specyfikacji wtyczki.

## 0.1 – Fundamenty CRUD
- zakończenie implementacji dedykowanych tabel dla umów, nieruchomości, klientów
  oraz poszukiwań wraz z migracjami bezpieczeństwa;
- dodanie warstwy repozytoriów i usług obsługujących podstawowe operacje CRUD w
  panelu administratora;
- walidacja formularzy i obsługa komunikatów o błędach;
- konfiguracja uprawnień ról (administrator, agent) dla nowych ekranów listowania
  i edycji rekordów.

## 0.2 – Kreator Umów
- wdrożenie wieloetapowego procesu dodawania umowy (Etap 1–3a/3b) z logiką
  zależną od typu transakcji;
- obsługa weryfikacji unikalności numeru umowy i dynamicznego blokowania pól dla
  umów bezterminowych;
- integracja wyszukiwarki klientów i dodawania wielu kontrahentów podczas
  tworzenia umowy;
- automatyczne kierowanie do formularza nieruchomości (SPRZEDAŻ/WYNAJEM) lub
  poszukiwania (KUPNO/NAJEM) zgodnie z ustalonym procesem.

## 0.3 – Profile i powiązania
- rozbudowa widoków szczegółowych umów, nieruchomości, poszukiwań i klientów;
- prezentacja danych w układzie lewa/prawa kolumna wraz z listami powiązanych
  elementów oraz przyciskami nawigacyjnymi;
- historia etapów umowy z rejestrowaniem zmian, dat oraz użytkownika dokonującego
  aktualizacji;
- egzekwowanie ograniczeń edycyjnych (np. brak możliwości zmiany typu transakcji
  po utworzeniu).

## 0.4 – Multimedia i formularze zaawansowane
- obsługa galerii zdjęć, rzutów 2D/3D oraz linków multimedialnych z poziomu
  formularzy;
- automatyczne nakładanie znaku wodnego na przesyłane zdjęcia;
- rozbudowa formularzy o sekcje "Szczegóły", "Media", "Udogodnienia",
  "Wyposażenie" i "Powierzchnie dodatkowe" z logiką warunkową zależną od typu
  nieruchomości;
- integracja pól niestandardowych konfigurowanych w ustawieniach wtyczki.

## 0.5 – CRM w panelu frontowym
- przygotowanie dedykowanego panelu CRM dostępnego po zalogowaniu, z zakładkami
  Pulpit, Nieruchomości, Poszukiwania, Umowy, Klienci;
- tabele danych z możliwością sortowania, filtrowania oraz wyszukiwania po
  wszystkich kolumnach;
- widoczne przyciski szybkich akcji (np. "Dodaj nową umowę") na każdej podstronie;
- wdrożenie dashboardu z widżetami prezentującymi kluczowe wskaźniki.

## 0.6 – Eksport ofert na WWW
- automatyczne generowanie stron ofertowych dla nieruchomości ze znacznikiem
  "Eksport na WWW";
- tworzenie hierarchii kategorii według typu transakcji, rodzaju nieruchomości,
  miasta i dzielnicy bez duplikowania taksonomii;
- przygotowanie szablonu strony oferty (nagłówek, galeria, opis, sekcje danych,
  widget kalkulatorów i mapa);
- obsługa znaczników ofert (nowa oferta, nowa cena, premium itp.) na liście i
  stronie pojedynczej.

## 0.7 – Moduł agentów i uprawnienia
- publiczne profile agentów z listą prowadzonych ofert oraz danymi kontaktowymi;
- ograniczenia funkcjonalne roli *estate_agent* (brak możliwości usuwania kluczowych
  rekordów, dostęp jedynie do modułów CRM);
- narzędzia administracyjne do przypisywania opiekunów do umów, ofert i klientów;
- przygotowanie bloków/kortkodów prezentujących agentów na stronie WWW.

## 0.8 – Integracje i kalkulatory
- integracja z Google Maps (wybór lokalizacji na mapie, zapis koordynatów);
- wdrożenie kalkulatorów notarialnego i kredytowego jako osobnych widoków,
  dostępnych również na stronach ofert;
- usprawnienia panelu ustawień (przechowywanie kluczy API, konfiguracja znaków
  wodnych, logotypów);
- implementacja systemu powiadomień (np. toastów) informujących o statusie operacji.

## 0.9 – Automatyzacje i raportowanie
- automatyczne oznaczanie ofert (np. zdejmowanie tagu "Nowa oferta" po 7 dniach);
- moduł raportów i statystyk (najlepsi agenci, aktywne umowy, liczba poszukiwań);
- eksport danych (CSV/Excel) z poziomu list CRM;
- optymalizacje zapytań i cache'owanie wybranych widoków.

## 1.0 – Stabilizacja i wydanie
- testy bezpieczeństwa oraz audyt ról i uprawnień;
- optymalizacja wydajności (zapytania, ładowanie zasobów, lazy loading galerii);
- internacjonalizacja (pliki .pot) i przygotowanie dokumentacji wdrożeniowej;
- finalne migracje baz danych oraz scenariusze aktualizacji.

## 1.1 – Moduł licencji
- aktywacja panelu Licencji w zapleczu, walidacja kluczy i rejestrowanie instalacji;
- mechanizm odświeżania statusu licencji (cron/API);
- odcinanie funkcji premium w przypadku braku aktywnej licencji oraz powiadomienia
  e-mail dla administratorów.
