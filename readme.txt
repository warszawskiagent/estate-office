=== EstateOffice CRM ===
Contributors: tomaszobarski
Requires at least: 6.8
Tested up to: 6.8.3
Requires PHP: 8.3
Stable tag: 0.1.0
License: Proprietary
License URI: http://warszawskiagent.pl

EstateOffice to zaawansowana wtyczka CRM dla biur nieruchomości. Wersja 0.1.0 zawiera podstawową strukturę panelu administracyjnego, zarządzanie rolą agentów oraz sekcję ustawień dla kluczowych integracji.

== Description ==
* Kompletny szkielet panelu administracyjnego z zakładkami: Licencja, Agenci, Ustawienia, O wtyczce.
* Wspólne menu CRM w kokpicie z pulpitem startowym i szybkim dostępem do kluczowych modułów.
* Pulpit CRM z licznikami rekordów i rankingiem aktywności agentów opartym o przypisanych opiekunów.
* Dedykowana rola użytkownika `estate_agent` przygotowana do dalszej rozbudowy uprawnień.
* Sekcja ustawień z obsługą klucza API Map Google, materiałów graficznych i dynamicznych pól konfiguracyjnych.
* Rozszerzone meta boksy nieruchomości z danymi adresowymi, prawnymi, technicznymi oraz informacjami CRM (numer oferty, opiekun).
* Widok listy nieruchomości w kokpicie dostosowany do potrzeb CRM z dedykowanymi kolumnami i sortowaniem.
* Profil agenta wzbogacony o zdjęcie, szczegóły kontaktowe oraz biografię do wykorzystania w CRM i na stronie.
* Zakładka Agenci z wyszukiwarką, kontaktami i podsumowaniem przypisanych rekordów CRM.
* Moduł umów z dedykowanymi polami meta, historią etapów oraz synchronizacją numeru z tytułem wpisu.
* Moduł poszukiwań z formularzem kryteriów, preferencjami budynku oraz przejrzystą listą w kokpicie.
* Moduł klientów z profilami osób i firm, pełnymi danymi kontaktowymi, adresowymi i obsługą opiekuna.
* Powiązania umów z klientami, nieruchomościami i poszukiwaniami wraz z synchronizacją widoków CRM.
* Automatyczne czyszczenie powiązań CRM przy usuwaniu rekordów, aby zapobiegać sierocym referencjom.
* Filtry list CRM pozwalające zawęzić nieruchomości, poszukiwania i klientów do opiekuna.
* Przygotowanie pod przyszłe moduły CRM zgodnie z roadmapą projektu.

== Roadmap ==
* Integracja modułu licencji wraz z panelem aktywacji i walidacją kluczy.
* Rozbudowa pulpitu CRM o konfigurowalne widżety, wykresy oraz historię aktywności.
* Rozszerzenie eksportu nieruchomości na WWW o szablony frontowe i znaczniki graficzne.
* Automatyzacje workflow: powiadomienia e-mail, logi aktywności oraz powiązania na froncie użytkownika.
* Integracja Google Maps z możliwością zapisu współrzędnych i podglądu lokalizacji w panelu.
* Moduł galerii nieruchomości z obsługą znaku wodnego i różnymi wariantami rzutów.
* Eksport ofert na portale zewnętrzne (otodom, gratka, Morizon) oraz integracja MLS.
* Kalkulator notarialny i kredytowy dostępny zarówno w panelu, jak i na stronach ofertowych.
* Panel frontowy CRM z widokami list, wyszukiwaniem i dodatkowymi filtrami dla agentów.
* Raporty i analityka efektywności (top oferty, aktywność agentów, statusy umów).
* Rozszerzenia panelu agentów o masowe akcje, import danych i integrację z raportami.
* Integracja z modułem raportów czasu rzeczywistego i personalizacją panelu agenta.
* Mechanizmy archiwizacji i audytu powiązań (log zmian relacji, odzyskiwanie rekordów).

== Installation ==
1. Skopiuj katalog `estate-office` do folderu `wp-content/plugins/`.
2. Aktywuj wtyczkę w panelu WordPress w zakładce *Wtyczki*.
3. Przejdź do menu **Estate Office CRM**, aby uzupełnić ustawienia.

== Changelog ==
= 0.1.0 =
* Pierwsze wydanie deweloperskie: struktura kodu, rola agenta, ustawienia integracji.
* Dodano typ wpisu nieruchomości wraz z podstawowymi taksonomiami do kategoryzacji ofert.
* Wprowadzono formularze meta rozszerzające edycję ofert o kluczowe pola specyfikacji i informacje CRM.
* Dodano kolumny CRM na liście nieruchomości: numer oferty, adres, cena, cena za m², metraż, liczba pokoi i opiekun.
* Rozbudowano profil użytkownika agenta o metadane kontaktowe, obsługę zdjęcia i biografię.
* Wprowadzono typ wpisu umów z formularzem szczegółów, historią etapów i konfigurowalnymi kolumnami w kokpicie.
* Dodano typ wpisu poszukiwań z kryteriami budżetu, lokalizacji i preferencjami oraz dostosowanymi kolumnami listy.
* Wprowadzono typ wpisu klientów z rozbudowanymi metadanymi kontaktowymi, adresowymi i wsparciem dla roli opiekuna.
* Dodano powiązania umów z klientami, nieruchomościami i poszukiwaniami oraz podsumowania relacji w panelu CRM.
* Zapewniono automatyczne utrzymanie spójności powiązań CRM podczas usuwania umów, nieruchomości, klientów i poszukiwań.
* Skonsolidowano menu administracyjne CRM z pulpitem szybkich akcji i podpięciem typów wpisów pod główną sekcję wtyczki.
* Rozbudowano pulpit CRM o zestawienie liczby rekordów modułów i ranking aktywności agentów wraz z dedykowanym stylem.
* Dodano zakładkę Agenci z wyszukiwarką, kontaktami i licznikami przypisań oraz skrótami do list CRM.
* Wprowadzono filtr opiekuna na listach nieruchomości, poszukiwań i klientów w kokpicie WordPress.
