=== EstateOffice CRM ===
Contributors: estateoffice
Tags: crm, real-estate, agencies, contracts, properties
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.6.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

EstateOffice to kompleksowy CRM dla biur nieruchomości z obsługą nieruchomości, umów, klientów, poszukiwań i agentów.

== Opis ==

EstateOffice CRM zapewnia profesjonalne zarządzanie ofertami nieruchomości bezpośrednio z panelu WordPress. Wtyczka umożliwia tworzenie wieloetapowego procesu obsługi umów, prowadzenie kart klientów oraz pracę na dedykowanych bazach danych. Panel administracyjny zawiera pulpity z podsumowaniami, listy nieruchomości, poszukiwań, umów i klientów, a także moduł zarządzania agentami. Konfigurowalne pola dynamiczne pozwalają dostosować formularze do potrzeb biura, a integracja z Google Maps ułatwia oznaczanie nieruchomości na mapie wraz z pełnymi danymi budynku, mediów, udogodnień i galerii. Wersja 0.6.0 dodaje konfigurację portali eksportowych wraz z możliwością wyboru konkretnych serwisów w formularzach nieruchomości i kreatorze umów, jednocześnie rozwijając modułowe szablony ofert oraz raportowanie CRM.

== Funkcje ==

* Dedykowane tabele `wp_eo_*` na potrzeby klientów, umów, nieruchomości, poszukiwań i agentów.
* Frontowy panel CRM dostępny po zalogowaniu dzięki stronie „EstateOffice CRM” (`[estate_office_crm]`).
* Publiczne listy ofert na sprzedaż i wynajem z grupowaniem po typie transakcji, rodzaju nieruchomości, mieście i dzielnicy (`[estate_office_offers transaction="SPRZEDAŻ"]`).
* Automatyczne tworzenie stron „EstateOffice CRM”, „Oferty na sprzedaż” oraz „Oferty na wynajem” podczas aktywacji wtyczki.
* Panel „Oferty” w kokpicie administratora pozwala przeglądać i ręcznie synchronizować strony eksportowanych nieruchomości.
* Sekcja ustawień „Eksport na portale” umożliwia definiowanie i aktywowanie serwisów zewnętrznych, które można wskazać w formularzach nieruchomości.
* Dynamiczne formularze z polami zależnymi od ustawień w sekcji **Estate Office CRM → Ustawienia**.
* Historia etapów umowy wypełnia się automatycznie, pilnując daty zawarcia i blokując usunięcie etapu startowego przy zmianach.
* Integracja z Google Maps (wprowadzony klucz API) oraz automatyczny znak wodny nanoszony na zdjęcia nieruchomości podczas zapisu i aktualizacji.
* Publiczne listy i profile agentów prezentują miniatury ofert wygenerowane na podstawie zdjęć z nałożonym znakiem wodnym.
* Logo biura z ustawień jest widoczne w nagłówkach CRM, kreatorach oraz publicznych stronach ofert i profili agentów.
* Modularne partiale frontowe (`templates/public/` i `templates/offers/`) z możliwością nadpisania w motywie oraz filtrami rodzaju i zakresu cen dla list CRM i katalogu ofert.
* Shortcode `[estate_office_offer id="123"]` generuje kompletną stronę oferty z galerią, znacznikami, mapą, kalkulatorami i kontaktem do agenta.
* Automatyczna synchronizacja ról `administrator` i `estate_agent`, aby zachować dostęp do panelu CRM.
* Możliwość przypisywania opiekuna (agenta) do klientów, umów, nieruchomości i poszukiwań z widoczną prezentacją na listach i profilach CRM.
* Rozbudowany pulpit administracyjny z kartami statystyk, rankingiem aktywnych agentów oraz listami ostatnich i wygasających umów.
* Flaga „Nowa oferta” wyłącza się automatycznie po siedmiu dniach dzięki zapamiętaniu czasu aktywacji.
* Walidacja unikalności numerów umów oraz oznaczeń statusów ofert dopasowanych do typu transakcji.
* Kalkulator notarialny i kredytowy dostępne w shortcode'ach oraz jako sekcja towarzysząca ofertom eksportowanym na witrynę.
* Publiczne profile agentów z biografiami, danymi kontaktowymi i listą ofert eksportowanych na WWW.
* Pełnotekstowe wyszukiwanie w listach CRM obejmujące wszystkie kolumny, wsparte dodatkowymi indeksami w bazie danych.
* Paginacja list CRM w panelu administracyjnym i shortcode'ach frontowych z zachowaniem aktywnej strony podczas przeglądania profili.
* Panel raportowy z wykresami liniowymi, słupkowymi i pierścieniowymi obrazującymi trendy umów, strukturę nieruchomości i typy transakcji oraz możliwością eksportu CSV.

== Instalacja ==

1. Skopiuj folder `estate-office` do katalogu `/wp-content/plugins/`.
2. Aktywuj wtyczkę w panelu **Wtyczki** w WordPressie.
3. Po aktywacji przejdź do menu **Estate Office CRM** i uzupełnij ustawienia (klucz Google Maps, znak wodny, pola dynamiczne).
4. W menu stron pojawi się strona „EstateOffice CRM” – zawiera ona frontowy panel dostępny tylko dla osób z uprawnieniem `eo_view_crm`.
5. Dodaj agentów, klientów oraz zacznij tworzyć umowy i nieruchomości korzystając z wbudowanych formularzy.

== Shortcodes ==

* `[estate_office_crm]` – wyświetla panel CRM na froncie (wymagane zalogowanie i uprawnienie `eo_view_crm`).
* `[estate_office_offers transaction="SPRZEDAŻ"]` – prezentuje listę ofert eksportowanych na WWW. Dostępne wartości parametru `transaction`: `SPRZEDAŻ`, `KUPNO`, `WYNAJEM`, `NAJEM`.
* `[estate_office_offer id="123"]` – generuje pełną stronę oferty z galerią, mapą, kalkulatorami i kartą agenta.
* `[estate_office_notary_calculator]` – wyświetla kalkulator notarialny do wykorzystania na stronie lub w treści oferty.
* `[estate_office_mortgage_calculator]` – wyświetla kalkulator kredytowy obliczający raty i koszty finansowania.

== Często zadawane pytania ==

= Czy wtyczka tworzy własne tabele w bazie danych? =

Tak. EstateOffice CRM korzysta z dedykowanych tabel (`wp_eo_*`) dla agentów, klientów, umów, nieruchomości, poszukiwań i pól dynamicznych.

= Czy agent może korzystać z panelu? =

Tak, wtyczka dodaje rolę `estate_agent` z odpowiednimi uprawnieniami do pracy w panelu CRM bez dostępu do pełnej administracji WordPress.

== Changelog ==

= 0.6.0 =
* Dodano sekcję ustawień „Eksport na portale” z listą konfigurowalnych serwisów oraz domyślnymi wpisami Otodom, Gratka i Morizon.
* Formularze nieruchomości w panelu i w kreatorze umów pozwalają zaznaczać aktywne portale eksportowe w oparciu o wybraną konfigurację.
* Zapisy nieruchomości synchronizują powiązaną tabelę portali, a usunięcie rekordu sprząta relacje i zachowuje stan eksportu.
* Zaktualizowano stronę „O wtyczce” o listę dostępnych shortcode’ów oraz rozbudowano dokumentację o nowe funkcje.

= 0.5.0 =
* Przepisano shortcode `[estate_office_offer]`, aby korzystał z modularnego systemu szablonów w katalogu `templates/offers/` z możliwością nadpisania w motywie.
* Wydzielono sekcje galerii, danych szczegółowych, mapy oraz kalkulatorów do osobnych partiali współdzielonych przez frontową ofertę.
* Rozszerzono branding oferty o plakietkę biura w nagłówku i ujednolicony markup zgodny z nowymi komponentami.

= 0.3.0 =
* Dodano moduł raportów CRM z wykresami liniowymi, słupkowymi i pierścieniowymi obrazującymi dynamikę umów, strukturę nieruchomości oraz typy transakcji.
* Udostępniono eksport CSV (umowy wg miesięcy, struktura nieruchomości, typy transakcji) dostępny z poziomu pulpitu administratora.
* Zaimplementowano legendy i responsywne style kart raportowych w panelu i na froncie wraz z nowym silnikiem wykresów opartym o canvas.
* Włączono raporty w shortcode `[estate_office_crm]`, zachowując branding biura i zgodność z filtrami panelu.

= 0.0.22 =
* Wydzielono szablony frontowe do partiali w katalogu `templates/public/` z obsługą nadpisywania w motywie potomnym.
* Dodano formularze filtrów rodzaju transakcji, typu nieruchomości oraz zakresu cen w panelu CRM i katalogu ofert na froncie.
* Rozszerzono skrypt i style publiczne o dynamiczne sterowanie filtrami oraz zachowanie parametrów w paginacji.

= 0.0.21 =
* Linki w profilach CRM zachowują bieżący numer strony, dzięki czemu powrót do list nieruchomości, poszukiwań, umów i klientów nie resetuje wyników.
* Zapisy i usunięcia agentów oraz poszukiwań natychmiast czyszczą cache statystyk, utrzymując aktualne dane na pulpicie administratora i w panelu frontowym.
* Poprawiono generowanie adresów paginacji w shortcode `[estate_office_crm]`, aby linki były stabilne niezależnie od konfiguracji serwisu.

= 0.0.20 =
* Kreator umów automatycznie dodaje pierwszy wpis historii z etapem „Umowa pośrednictwa” oraz datą zawarcia kontraktu.
* Edycja umowy dopisuje nowe etapy wraz z bieżącą datą, a interfejs blokuje usunięcie podstawowego rekordu i synchronizuje historię przy zmianach.
* Aktualizacja wtyczki uzupełnia istniejące kontrakty o brakujące wpisy historii, aby profile i widoki frontowe zawsze prezentowały pełny przebieg umowy.

= 0.0.19 =
* W nagłówkach panelu CRM, kreatorów oraz publicznych shortcode'ów wyświetlane jest logo biura lub inicjały tworzone automatycznie, dzięki czemu branding towarzyszy każdemu ekranowi.
* Strony ofert eksportowanych na WWW oraz publiczne profile agentów otrzymały nowe sekcje nagłówkowe podkreślające identyfikację wizualną biura.
* Dodano helpery i style fallback dla logotypu, zabezpieczając prezentację nawet wtedy, gdy plik został usunięty z biblioteki mediów.

= 0.0.18 =
* Wyszukiwanie w zakładkach CRM (nieruchomości, umowy, poszukiwania, klienci) obejmuje wszystkie kolumny tabel, w tym pola JSON i dane agentów, oraz działa analogicznie w shortcode `[estate_office_crm]`.
* Dodano indeksy w tabelach `eo_properties`, `eo_contracts`, `eo_clients` i `eo_searches`, aby przyspieszyć filtrowanie po najczęściej wykorzystywanych polach.
* Ujednolicono komunikaty formularzy wyszukiwania, zachęcając do przeszukiwania dowolnych kolumn list CRM.

= 0.0.17 =
* Rozszerzono schemat bazy `eo_properties` o identyfikator strony eksportowanej, aby śledzić cykl życia publikacji ofert.
* Dodano stronę administracyjną **Oferty** z wyszukiwarką, ręczną synchronizacją oraz podglądem kategorii eksportowanych stron WWW.
* Wprowadzono shortcode `[estate_office_offer]`, który tworzy publiczną stronę oferty z galerią, mapą Google, kalkulatorami i kartą agenta.
* Uzupełniono listy i karty frontowe o linki do stron ofert, obsługę mapy przez atrybuty danych oraz nowe style dla sekcji szczegółów.

= 0.0.16 =
* Rozbudowano pulpit administracyjny o sekcje ostatnich umów oraz nadchodzących zakończeń kontraktów.
* Ranking najlepszych agentów bazuje teraz wyłącznie na aktywnych umowach, zapewniając spójność z danymi CRM i widoku frontowego.
* Ujednolicono liczniki aktywnych umów na pulpicie administratora z warunkami obowiązującymi w panelu frontowym.

= 0.0.15 =
* Dodano pełny pipeline znaku wodnego – zapisywanie i edycja nieruchomości generuje chronione kopie zdjęć w dedykowanym katalogu.
* Uzupełniono galerie historycznych ofert podczas aktualizacji wtyczki, aby wszystkie eksportowane zdjęcia posiadały znak wodny.
* Publiczne listy ofert i karty agentów prezentują miniatury okładkowe z nałożonym znakiem wodnym oraz dostosowanym alt tekstem.

= 0.0.14 =
* Dodano publiczne strony agentów z biogramem, kontaktami oraz ofertami eksportowanymi na WWW wraz z linkami do panelu CRM.
* Wprowadzono unikalne slugi agentów, możliwość konfiguracji bazowego adresu w ustawieniach oraz automatyczne odświeżanie reguł przepisywania.
* Rozszerzono listy CRM o linki do profili agentów i dodano przycisk podglądu strony z poziomu panelu administracyjnego.

= 0.0.13 =
* Wprowadzono automatyczne wygaszanie znacznika „Nowa oferta” po siedmiu dniach wraz z zapisem czasu aktywacji.
* Formularze nieruchomości zachowują datę aktywacji „Nowej oferty” przy edycji, aby zachować spójność z mechanizmem wygaszania.
* Publiczne listy i profile ofert respektują nowy format znaczników, prezentując wyłącznie aktywne oznaczenia.

= 0.0.12 =
* Wprowadzono przypisywanie opiekuna (agenta) w formularzach klientów, umów, nieruchomości i poszukiwań wraz z zapisem w bazie.
* Listy CRM oraz frontowe widoki prezentują aktualnego opiekuna, a profile danych pokazują szczegóły agenta.
* Formularze synchronizują wybór opiekuna między umową, nieruchomością i poszukiwaniem, a zestawienie najlepszych agentów korzysta z nowych danych.

= 0.0.11 =
* Dodano shortcode'y `[estate_office_notary_calculator]` i `[estate_office_mortgage_calculator]` wraz z automatycznym tworzeniem dedykowanych stron podczas aktywacji.
* Wzbogacono katalog ofert o sekcję kalkulatorów dla kupujących, prezentując jednocześnie notarialny i kredytowy wariant na każdej stronie eksportowanych ofert.
* Rozszerzono zasoby frontowe (CSS/JS) o logikę obliczeń taks notarialnych, PCC, prowizji oraz rat kredytu w układzie responsywnym.

= 0.0.10 =
* Dodano walidację numerów umów – kreator informuje o duplikacie i wymaga nadania unikalnego identyfikatora.
* Zsynchronizowano formularz nieruchomości z typem transakcji, w tym dynamiczną etykietę ceny dla wynajmu i przekazywanie stanu do JS.
* Ograniczono znaczniki „Sprzedane” i „Wynajęte” do właściwych transakcji oraz spięto je z logiką kreatora umów.

= 0.0.9 =
* Przebudowano dodawanie umowy na trzyetapowy kreator (dane umowy → klienci → nieruchomość/poszukiwanie) zgodnie ze specyfikacją.
* Dodano wyszukiwarkę istniejących klientów z filtrowaniem po imieniu, nazwisku, telefonie i e-mailu oraz listę wybranych kontaktów.
* Umożliwiono tworzenie nowych klientów bez wychodzenia z kreatora wraz z pełnymi danymi identyfikacyjnymi i adresowymi.
* Rozszerzono skrypt administracyjny o obsługę kroków, walidację obecności klientów oraz aktualizację etapu nieruchomość/poszukiwanie.

= 0.0.8 =
* Rozszerzono formularz nieruchomości o komplet sekcji (adresy zależne od typu, dane budynku, media, udogodnienia, wyposażenie, powierzchnie dodatkowe) oraz integrację z mapą Google i galerią zdjęć z rzutami.
* Zaimplementowano synchronizację materiałów graficznych nieruchomości w dedykowanej tabeli wraz z podglądami i linkami do filmu lub spaceru.
* Formularz poszukiwań otrzymał lustrzane kryteria budynku, mediów, udogodnień i powierzchni dodatkowych, aby precyzyjnie odwzorować wymagania klientów.
* Dodano nowe znaczniki ofert (sprzedane, wynajęte, bez prowizji, MLS) oraz wzmocniono obsługę pól prawnych, w tym brak księgi wieczystej.

= 0.0.7 =
* Formularze nieruchomości i poszukiwań automatycznie odczytują typ transakcji z wybranej umowy i blokują ręczną edycję tego pola.
* Zapisywanie nieruchomości oraz poszukiwań weryfikuje typ transakcji względem powiązanej umowy, aby utrzymać spójność danych.

= 0.0.6 =
* Naprawiono rejestrację górnego poziomu menu, aby dział **Estate Office CRM** był widoczny dla administratorów natychmiast po aktywacji lub aktualizacji wtyczki.

= 0.0.5 =
* Dodano dodatkową synchronizację uprawnień podczas ładowania panelu administracyjnego, aby menu **Estate Office CRM** było zawsze widoczne.
* Wprowadzono mechanizm awaryjny dla administratorów bez zsynchronizowanych uprawnień, wymuszający wyświetlenie całego menu CRM.

= 0.0.4 =
* Wymuszono synchronizację uprawnień ról podczas ładowania wtyczki, aby menu **Estate Office CRM** było dostępne natychmiast po aktualizacji.

= 0.0.3 =
* Naprawiono uprawnienia administratorów tak, aby menu Estate Office CRM było zawsze widoczne po aktualizacji wtyczki.
* Dodano automatyczną synchronizację uprawnień roli `estate_agent` przy każdym żądaniu.

= 0.0.2 =
* Dodano frontowy panel CRM z szybkim dostępem do pulpitów, list i profili (klienci, umowy, nieruchomości, poszukiwania).
* Utworzono shortcode `[estate_office_offers]` prezentujący oferty eksportowane na WWW wraz z grupowaniem po kategoriach.
* Dodano automatyczne tworzenie strony „EstateOffice CRM” oraz link w pasku administratora.
* Wprowadzono stylizacje i skrypty frontendowe dla kart CRM i katalogu ofert.

= 0.0.1 =
* Pierwsze wydanie publiczne.
* Implementacja struktury baz danych, panelu administracyjnego oraz formularzy dla agentów, klientów, umów, nieruchomości i poszukiwań.
* Obsługa pól dynamicznych i integracji z Google Maps.
