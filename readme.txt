=== EstateOffice CRM ===
Contributors: tomaszobarski
Requires at least: 6.8
Tested up to: 6.8.3
Requires PHP: 8.3
Stable tag: 0.1.12
License: Proprietary
License URI: http://warszawskiagent.pl

EstateOffice to zaawansowana wtyczka CRM dla biur nieruchomości. Wersja 0.1.12 tymczasowo wyłącza moduł licencyjny do czasu wydania 1.0.1, pozostawiając dotychczasowe usprawnienia kreatora i nawigacji CRM.

== Description ==
* Kompletny szkielet panelu administracyjnego z zakładkami: Licencja, Agenci, Ustawienia i O wtyczce oraz stroną przeglądową kierującą do panelu frontowego.
* Moduł licencyjny tymczasowo wstrzymany do wersji 1.0.1, dzięki czemu bieżące wdrożenia nie wymagają aktywacji klucza.
* Skróty do zakładki licencji oraz alerty statusu bezpośrednio na liście wtyczek WordPress.
* Automatyczne tworzenie stron z shortcode'ami CRM, katalogu agentów oraz dedykowanych podstron ofert na sprzedaż i wynajem wraz z menu nawigacyjnym podczas aktywacji.
* Pulpit CRM z licznikami rekordów, aktywnych umów, rankingiem aktywności agentów oraz alertami terminów umów i follow-up leadów.
* Dedykowana rola użytkownika `estate_agent` przygotowana do dalszej rozbudowy uprawnień.
* Sekcja ustawień z obsługą klucza API Map Google, materiałów graficznych i dynamicznych pól konfiguracyjnych.
* Konfigurowalne pola dodatkowe nieruchomości, umów, poszukiwań i klientów obsługiwane w panelu administracyjnym, CRM oraz publicznych widokach.
* Kreator umów z kompletnym formularzem preferencji poszukiwań obejmującym budynek, media, udogodnienia, wyposażenie i powierzchnie dodatkowe.
* Rozszerzone meta boksy nieruchomości z danymi adresowymi, prawnymi, technicznymi oraz informacjami CRM (numer oferty, opiekun).
* Integracja Map Google w edycji nieruchomości z zapisem współrzędnych i wyszukiwaniem adresów.
* Interaktywny podgląd lokalizacji w froncie CRM, katalogu ofert i na stronach ofertowych z automatycznym fallbackiem iframe przy braku klucza API.
* Widok listy nieruchomości w kokpicie dostosowany do potrzeb CRM z dedykowanymi kolumnami i sortowaniem.
* Znaczniki marketingowe ofert (Nowa oferta, Wyłączność, Premium, MLS itp.) z automatycznym wygaszaniem statusu "Nowa oferta".
* Profil agenta wzbogacony o zdjęcie, szczegóły kontaktowe, biografię oraz listy specjalizacji i obsługiwanych obszarów wykorzystywane w CRM i na stronie.
* Zakładka Agenci z wyszukiwarką, kontaktami i podsumowaniem przypisanych rekordów CRM.
* Frontowy panel CRM z zakładkami Pulpit, Nieruchomości, Umowy, Poszukiwania, Klienci i Leady oraz wyszukiwarką przeszukującą wszystkie kolumny tabel.
* Klikalne adresy w listach umów i klientów, prowadzące bezpośrednio do powiązanych nieruchomości w panelu CRM.
* Przypisanie opiekuna umowy w kreatorze frontowym i w panelu administracyjnym oraz filtr listy umów według agenta.
* Frontowe formularze szybkiego dodawania nieruchomości, poszukiwań i klientów dostępne bezpośrednio w CRM.
* Kreator frontowy umów prowadzący przez dodawanie danych i klientów bez opuszczania panelu CRM.
* Dynamiczny krok dodawania nieruchomości w kreatorze umów z warunkową widocznością pól, sekcjami budynku, udogodnień, wyposażenia i powierzchniami dodatkowymi.
* Możliwość wyszukania i przypisania istniejących nieruchomości lub poszukiwań w kreatorze umów oraz zmiany wyboru przed finalizacją.
* Walidacja finalizacji kreatora umów wymagająca przypisania nieruchomości lub poszukiwania zgodnie z typem transakcji.
* Obsługa dynamicznych pól umów, nieruchomości i poszukiwań bezpośrednio w kreatorze umów.
* Formularz dodawania klientów w kreatorze umów uwzględnia dynamiczne pola konfigurowane w ustawieniach CRM.
* Kreator umów po każdym przypisaniu klienta pyta, czy dodać kolejnego, co przyspiesza obsługę umów wieloosobowych.
* Konfigurowalne role klientów definiowane w ustawieniach CRM i wykorzystywane w umowach, kreatorze oraz raportach.
* Publiczny katalog ofert z filtrowaniem po transakcji, rodzaju, mieście i dzielnicy oraz odznakami marketingowymi.
* Publiczny szablon strony oferty z galerią, mapą, kartami danych i bezpośrednim kontaktem do opiekuna.
* Automatyczne meta tagi Open Graph/Twitter oraz dane strukturalne JSON-LD dla eksportowanych ofert wraz z dedykowanym wpisem w mapie witryny.
* Materiały do pobrania zarządzane w panelu nieruchomości oraz prezentowane na stronach ofertowych.
* Rekomendacje podobnych ofert na stronach nieruchomości ułatwiające użytkownikom odkrywanie alternatyw.
* Formularze kontaktowe na stronach ofert i profilach agentów z wysyłką AJAX oraz kontrolą zgód RODO.
* Automatyczny zapis leadów z formularzy kontaktowych z przypisaniami agentów, statusami i historią zgłoszeń.
* Konfigurowalne statusy leadów zarządzane w ustawieniach CRM, wykorzystywane w kokpicie, na froncie i w powiadomieniach.
* Powiadomienia e-mail o nowych leadach dla biura i przypisanych agentów z konfigurowalnymi tematami i treścią.
* Automatyczne przypomnienia follow-up leadów z konfigurowalnym terminem wysyłki, adresatami i tematami wiadomości.
* Publiczne profile agentów z wizytówką kontaktową, biografią i listą aktualnych ofert eksportowanych na WWW.
* Publiczny katalog agentów z filtrami po specjalizacjach i obszarach działania oraz linkami do profili.
* Szczegółowe widoki frontowego CRM z kartami danych, opisami, historią etapów i powiązaniami między rekordami.
* Stopki akcji w profilach CRM z przyciskami powrotu do listy, edycji i usuwania chronionymi odpowiednimi uprawnieniami.
* Panel leadów w frontowym CRM umożliwiający aktualizację statusów bez przechodzenia do kokpitu administracyjnego.
* Galeria nieruchomości z obsługą zdjęć, podpisów, wyboru zdjęcia głównego, rzutów 2D/3D, linków wideo oraz automatycznym nakładaniem znaku wodnego.
* Moduł umów z dedykowanymi polami meta, historią etapów oraz synchronizacją numeru z tytułem wpisu.
* Moduł poszukiwań z formularzem kryteriów, preferencjami budynku oraz przejrzystą listą w kokpicie.
* Moduł klientów z profilami osób i firm, pełnymi danymi kontaktowymi, adresowymi i obsługą opiekuna.
* Powiązania umów z klientami, nieruchomościami i poszukiwaniami wraz z synchronizacją widoków CRM.
* Automatyczne czyszczenie powiązań CRM przy usuwaniu rekordów, aby zapobiegać sierocym referencjom.
* Role klientów w umowach z możliwością wyboru w kreatorze i szybkim podglądem w panelach CRM.
* Filtry list CRM pozwalające zawęzić nieruchomości, poszukiwania i klientów do opiekuna.
* Przygotowanie pod przyszłe moduły CRM zgodnie z roadmapą projektu.
* Zakładka leadów w frontowym CRM z listą zgłoszeń, statusami, przypisanymi agentami i podglądem szczegółów.
* Oś czasu zmian statusu leadów z automatycznym zapisem aktualizacji i źródeł działań.

== Roadmap ==
* Rozbudowa pulpitu CRM o konfigurowalne widżety, wykresy oraz historię aktywności.
* Automatyzacje workflow: logi aktywności oraz powiązania na froncie użytkownika.
* Integracja galerii nieruchomości z eksportami portali (synchronizacja podpisów i zdjęć głównych).
* Eksport ofert na portale zewnętrzne (otodom, gratka, Morizon) oraz integracja MLS.
* Kalkulator notarialny i kredytowy dostępny zarówno w panelu, jak i na stronach ofertowych.
* Rozbudowa frontowego panelu CRM o edycję rekordów, zarządzanie relacjami i rozszerzone raporty.
* Rozbudowa kreatora umów o możliwość dodawania wielu nieruchomości/poszukiwań oraz edycję istniejących danych przed finalizacją.
* Rozwinięcie pól dynamicznych o typy danych, reguły walidacji oraz warunkową widoczność w formularzach.
* Powiązanie dynamicznych pól poszukiwań z raportami, eksportami i przyszłym modułem workflow.
* Personalizacja powiadomień leadów o własne szablony HTML, integrację z webhookami i harmonogram wysyłek.
* Raporty i analityka efektywności (top oferty, aktywność agentów, statusy umów).
* Rozszerzenia panelu agentów o masowe akcje, import danych i integrację z raportami.
* Integracja z modułem raportów czasu rzeczywistego i personalizacją panelu agenta.
* Mechanizmy archiwizacji i audytu powiązań (log zmian relacji, odzyskiwanie rekordów).
* Rozbudowa leadów o sekwencje follow-up, raportowanie skuteczności i automatyczną zmianę statusów.
* Wzbogacenie katalogu agentów o widoki mapy, sortowanie według aktywności oraz szybkie formularze kontaktowe.
* Rozszerzenie przypomnień follow-up o integrację z kalendarzami, zadaniami i wieloetapowymi sekwencjami.
* Rozbudowa widoków map o klastry wyników, tryb pełnoekranowy oraz filtrowanie po promieniu lokalizacji.
* Rozszerzenie komend WP-CLI o zarządzanie rekordami CRM, eksportami i zadaniami automatyzacji.
* Rozszerzenie kreatora umów o edycję i dodawanie kolejnych nieruchomości lub poszukiwań po utworzeniu rekordu.
* Rozbudowa kreatora umów o walidację typów danych pól dynamicznych i raportowanie braków konfiguracji.
* Rozszerzenie ról klientów o personalizację komunikatów, automatyczne powiadomienia i pogłębione raportowanie w CRM.
* Umożliwienie edycji oraz podglądu dynamicznych pól umów w kreatorze po utworzeniu rekordu.
* Integracja dynamicznych pól klientów z raportami, eksportami i automatyzacjami marketingowymi.
* Testy integracyjne kreatora umów i przepływu leadów (scenariusze E2E i jednostkowe dla krytycznych ścieżek).

== Installation ==
1. Skopiuj katalog `estate-office` do folderu `wp-content/plugins/`.
2. Aktywuj wtyczkę w panelu WordPress w zakładce *Wtyczki*.
3. Przejdź do menu **Estate Office CRM**, aby uzupełnić ustawienia.

== Changelog ==
= 0.1.12 =
* Tymczasowo wyłączono moduł licencyjny i powiązane zadania cron/CLI do czasu wydania wersji 1.0.1.
* Dodano informację o wstrzymaniu obsługi licencji w zakładce administracyjnej, aby administratorzy znali aktualny status prac.

= 0.1.11 =
* Dodano możliwość wyboru opiekuna podczas tworzenia umowy i w panelu administracyjnym oraz prezentację opiekuna w szczegółach CRM.
* Rozszerzono filtry panelu WordPress o wybór agenta na liście umów, aby przyspieszyć raportowanie pracy zespołu.

= 0.1.10 =
* Dodano klikalne adresy nieruchomości w listach umów, prowadzące bezpośrednio do szczegółów ofert w CRM.
* Uzupełniono listę klientów o skróty do powiązanych nieruchomości, przy zachowaniu wyświetlania adresu korespondencyjnego, gdy brak powiązań.

= 0.1.9 =
* Rozszerzono wyszukiwarkę w panelu CRM o filtrowanie wszystkich kolumn tabel nieruchomości, umów, poszukiwań, klientów i leadów, niezależnie od układu danych.
* Ujednolicono normalizację wartości wyszukiwania i obsłużono etykiety statusów oraz odznaki, aby wyniki obejmowały również oznaczenia marketingowe i statusy leadów.

= 0.1.8 =
* Rozbudowano pulpit CRM o liczniki aktywnych umów oraz modułów widocznych zgodnie z uprawnieniami użytkownika.
* Dodano panel monitorujący terminy zakończenia umów z kolorystycznymi alertami i szybkim dostępem do profili.
* Dodano panel leadów wymagających follow-up z uwzględnieniem zaległych zgłoszeń i kontekstu przypisanych agentów.

= 0.1.7 =
* Dodano stopkę akcji w profilach rekordów CRM z przyciskami powrotu, edycji i usuwania zgodnie z wymaganiami bezpieczeństwa.

= 0.1.6 =
* Dodano sekcję „Etap umowy” w profilu umowy z podsumowaniem aktualnego etapu i daty oraz formularzem zmiany zgodnym ze specyfikacją CRM.
* Wprowadzono bezpieczną aktualizację etapów przez AJAX z komunikatami zwrotnymi, kontrolą uprawnień i automatycznym dopisywaniem historii.
* Przebudowano widok historii etapów na tabelę z kolumnami daty i etapu, uzupełniając styl warstw szczegółów CRM.
= 0.1.5 =
* Rozszerzono krok dodawania poszukiwania o komplet sekcji preferencji obejmujących budynek, media, udogodnienia, wyposażenie oraz powierzchnie dodatkowe zgodnie ze specyfikacją CRM.

= 0.1.4 =
* Dodano pole lokalizacji z przyciskiem „Zaznacz na mapie” w kroku nieruchomości kreatora umów wraz z integracją Map Google i zapisem współrzędnych.
* Wprowadzono skrypt ładujący Mapy Google na żądanie z dynamiczną obsługą wyszukiwania adresów, przeciągania pinezki oraz geokodowania wyników.
* Rozszerzono interfejs kreatora o panel mapy, odświeżanie stanu po zapisaniu rekordów oraz stylizację kontrolek i komunikatów mapy dla spójności z CRM.

= 0.1.3 =
* Rozszerzono krok dodawania nieruchomości w kreatorze umów o pełną listę pól technicznych, prawnych i marketingowych zgodną z metadanymi panelu administracyjnego.
* Dodano logikę JavaScript sterującą widocznością sekcji formularza zależnie od typu nieruchomości, statusu KW, powierzchni dodatkowych oraz automatyczne wyliczanie ceny za metr.
* Uzupełniono style frontowe o nowe fieldsety, grupy checkboxów oraz zagnieżdżone kontenery, aby zachować przejrzystość złożonego formularza.

= 0.1.2 =
* Dodano skrót do zakładki licencji w tabeli wtyczek oraz kontekstowe alerty statusu i błędów sprawdzeń.

= 0.1.1 =
* Włączono moduł licencji wraz z harmonogramem automatycznych sprawdzeń i powiadomieniami w panelu administracyjnym.
* Dodano integrację WP-CLI umożliwiającą aktywację, odświeżanie i dezaktywację licencji z wiersza poleceń.
* Zarejestrowano obsługę cron przy aktywacji i czyszczenie harmonogramu przy dezaktywacji wtyczki.

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
* Przebudowano menu administracyjne zgodnie ze specyfikacją – główny wpis „Estate Office CRM” posiada zakładki Licencja, Agenci, Ustawienia i O wtyczce oraz stronę przeglądową odsyłającą do panelu frontowego.
* Rozbudowano pulpit CRM o zestawienie liczby rekordów modułów i ranking aktywności agentów wraz z dedykowanym stylem.
* Dodano zakładkę Agenci z wyszukiwarką, kontaktami i licznikami przypisań oraz skrótami do list CRM.
* Wprowadzono filtr opiekuna na listach nieruchomości, poszukiwań i klientów w kokpicie WordPress.
* Udostępniono frontowy moduł CRM z pulpitem statystyk, listami rekordów i interaktywnym wyszukiwaniem.
* Dodano frontowe karty szczegółowe nieruchomości, umów, poszukiwań i klientów wraz z powiązaniami oraz historią etapów.
* Dodano moduł znaczników i eksportu nieruchomości z automatycznym wygaszaniem statusu "Nowa oferta" oraz odznakami w CRM.
* Udostępniono shortcode katalogu ofert z filtrowaniem oraz prezentacją odznak i danych opiekunów dla nieruchomości eksportowanych na WWW.
* Zintegrowano mapę Google w edycji nieruchomości, umożliwiając zapisywanie współrzędnych, adresu oraz czyszczenie lokalizacji z poziomu metaboxu.
* Dodano galerię nieruchomości z automatycznym nakładaniem znaku wodnego, wyborem rzutów 2D/3D oraz polami linków multimedialnych.
* Udostępniono szczegółowy szablon strony oferty z prezentacją galerii, danych technicznych, kontaktu agenta i mapy lokalizacji.
* Udostępniono publiczne profile agentów z listą aktualnych ofert, wizytówką kontaktową i linkami z katalogu ofert oraz strony oferty.
* Rozszerzono profil agenta o specjalizacje i obsługiwane obszary dostępne w CRM, na stronie oferty oraz w katalogach.
* Dodano publiczny katalog agentów z filtrami specjalizacji i obszarów działania, kartami kontaktów oraz paginacją.
* Dodano formularze kontaktowe na stronach ofert i profilach agentów z obsługą AJAX, walidacją oraz dedykowanymi filtrami nagłówków e-mail.
* Dodano moduł leadów CRM rejestrujący zgłoszenia z formularzy, statusy oraz przypisania opiekunów z podsumowaniami w panelu administracyjnym.
* Rozbudowano frontowy CRM o zakładkę leadów z tabelą zgłoszeń, kartą detali oraz aktualizacją statystyk i rankingów pod kątem leadów.
* Udostępniono możliwość aktualizacji statusu leadów z poziomu frontowego CRM wraz z zabezpieczonym zapisem AJAX i komunikatami dla użytkownika.
* Dodano historię statusów leadów z automatycznym zapisem zmian i osią czasu w panelu CRM.
* Dodano notatki leadów z przypomnieniami follow-up dostępnymi w kokpicie administracyjnym i froncie CRM.
* Udostępniono pola dynamiczne dla nieruchomości, umów i klientów z meta boksami, walidacją, zapisem oraz prezentacją w CRM i widokach publicznych.
* Dodano pola dynamiczne poszukiwań konfigurowane w ustawieniach z obsługą meta boksów, REST API i prezentacją w panelu CRM.
* Dodano moduł powiadomień leadów wysyłający e-maile do agentów i biura wraz z konfigurowalnymi tematami i treścią wiadomości.
* Dodano notatki leadów wraz z przypomnieniami follow-up i formularzem dodawania dostępnym w kokpicie i panelu CRM na froncie.
* Dodano automatyczne przypomnienia follow-up leadów z harmonogramem cron, konfiguracją czasu wysyłki i wyboru odbiorców.
* Rozszerzono integrację Map Google o interaktywne podglądy lokalizacji w CRM, katalogu i stronach ofertowych wraz z fallbackiem dla braku klucza API oraz dedykowanym skryptem map.
* Dodano kreator tworzenia umów w panelu frontowego CRM z obsługą przypisywania klientów i finalizacją procesu.
* Rozszerzono kreator umów o etap dodawania nieruchomości lub poszukiwań z automatycznym powiązaniem w CRM i przekierowaniem do nowego rekordu.
* Umożliwiono wyszukiwanie i przypisywanie istniejących nieruchomości lub poszukiwań w kreatorze umów oraz ponowne otwieranie formularzy bezpośrednio z podsumowania.
* Dodano walidację finalizacji kreatora umów, która wymaga powiązania nieruchomości lub poszukiwania zgodnie z typem transakcji.
* Dodano obsługę dynamicznych pól nieruchomości i poszukiwań w kreatorze umów na froncie CRM.
* Dodano obsługę dynamicznych pól umów w kreatorze frontowego CRM, aby zbierać niestandardowe dane od pierwszego kroku.
* Dodano obsługę dynamicznych pól klientów w kreatorze frontowego CRM, dzięki czemu nowe kontakty mogą być uzupełniane o dodatkowe informacje.
* Wprowadzono pytanie o dodanie kolejnego klienta w kroku kreatora, z przyciskami TAK/NIE umożliwiającymi kontynuację dodawania lub przejście do kolejnego etapu.
* Dodano możliwość przypisywania ról klientom w umowach z obsługą w kreatorze, kokpicie CRM oraz profilach klientów.
* Wprowadzono konfigurator ról klientów w ustawieniach CRM wraz z integracją w kreatorze umów i panelach CRM.
* Dodano konfigurator statusów leadów w ustawieniach oraz dynamiczne etykiety wykorzystywane w CRM, powiadomieniach i kreatorach.
* Dodano materiały do pobrania w metadanych nieruchomości z obsługą w panelu, CRM i publicznym szablonie oferty.
* Rozszerzono publiczny szablon oferty o sekcję rekomendowanych nieruchomości dopasowanych po typie transakcji, rodzaju i lokalizacji.
* Dodano podpisy zdjęć i wybór zdjęcia głównego w galerii nieruchomości oraz prezentację podpisów w publicznym szablonie oferty.
* Dodano moduł SEO ofert generujący metadane Open Graph/Twitter, dane strukturalne JSON-LD oraz dedykowany wpis w mapie witryny dla nieruchomości eksportowanych na WWW.
* Podczas aktywacji wtyczki automatycznie tworzone są strony panelu CRM, katalogu agentów oraz podstrony ofert na sprzedaż i wynajem z odpowiednio skonfigurowanymi shortcode’ami w dedykowanym menu nawigacyjnym.
* Ukryto typy wpisów CRM z menu kokpitu WordPress, aby zarządzanie odbywało się z poziomu frontowego panelu zgodnego ze specyfikacją.
* Shortcode katalogu ofert obsługuje atrybuty wstępnych filtrów (transakcja, rodzaj nieruchomości, miasto, dzielnica), co pozwala budować oddzielne widoki sprzedaży i wynajmu.
* Tymczasowo dezaktywowano moduł licencji do czasu przygotowania generatora kluczy – dotychczasowe akcje i harmonogramy nie są ładowane.
* Udostępniono moduł QuickCreate z przyciskami w CRM i modalem umożliwiającym tworzenie nieruchomości, klientów i poszukiwań po stronie frontu.
* Dodano dedykowane style i skrypty obsługujące szybkie formularze wraz z komunikatami oraz przekierowaniami do nowych rekordów.
