=== EstateOffice CRM ===
Contributors: estateoffice
Tags: crm, real-estate, agencies, contracts, properties
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.0.7
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

EstateOffice to kompleksowy CRM dla biur nieruchomości z obsługą nieruchomości, umów, klientów, poszukiwań i agentów.

== Opis ==

EstateOffice CRM zapewnia profesjonalne zarządzanie ofertami nieruchomości bezpośrednio z panelu WordPress. Wtyczka umożliwia tworzenie wieloetapowego procesu obsługi umów, prowadzenie kart klientów oraz pracę na dedykowanych bazach danych. Panel administracyjny zawiera pulpity z podsumowaniami, listy nieruchomości, poszukiwań, umów i klientów, a także moduł zarządzania agentami. Konfigurowalne pola dynamiczne pozwalają dostosować formularze do potrzeb biura, a integracja z Google Maps ułatwia oznaczanie nieruchomości na mapie. Wersja 0.0.7 porządkuje formularze nieruchomości i poszukiwań tak, aby typ transakcji był zawsze dziedziczony z umowy.

== Funkcje ==

* Dedykowane tabele `wp_eo_*` na potrzeby klientów, umów, nieruchomości, poszukiwań i agentów.
* Frontowy panel CRM dostępny po zalogowaniu dzięki stronie „EstateOffice CRM” (`[estate_office_crm]`).
* Publiczne listy ofert na sprzedaż i wynajem z grupowaniem po typie transakcji, rodzaju nieruchomości, mieście i dzielnicy (`[estate_office_offers transaction="SPRZEDAŻ"]`).
* Automatyczne tworzenie stron „EstateOffice CRM”, „Oferty na sprzedaż” oraz „Oferty na wynajem” podczas aktywacji wtyczki.
* Dynamiczne formularze z polami zależnymi od ustawień w sekcji **Estate Office CRM → Ustawienia**.
* Integracja z Google Maps (wprowadzony klucz API) oraz automatyczny znak wodny na materiałach zdjęciowych.
* Automatyczna synchronizacja ról `administrator` i `estate_agent`, aby zachować dostęp do panelu CRM.

== Instalacja ==

1. Skopiuj folder `estate-office` do katalogu `/wp-content/plugins/`.
2. Aktywuj wtyczkę w panelu **Wtyczki** w WordPressie.
3. Po aktywacji przejdź do menu **Estate Office CRM** i uzupełnij ustawienia (klucz Google Maps, znak wodny, pola dynamiczne).
4. W menu stron pojawi się strona „EstateOffice CRM” – zawiera ona frontowy panel dostępny tylko dla osób z uprawnieniem `eo_view_crm`.
5. Dodaj agentów, klientów oraz zacznij tworzyć umowy i nieruchomości korzystając z wbudowanych formularzy.

== Shortcodes ==

* `[estate_office_crm]` – wyświetla panel CRM na froncie (wymagane zalogowanie i uprawnienie `eo_view_crm`).
* `[estate_office_offers transaction="SPRZEDAŻ"]` – prezentuje listę ofert eksportowanych na WWW. Dostępne wartości parametru `transaction`: `SPRZEDAŻ`, `KUPNO`, `WYNAJEM`, `NAJEM`.

== Często zadawane pytania ==

= Czy wtyczka tworzy własne tabele w bazie danych? =

Tak. EstateOffice CRM korzysta z dedykowanych tabel (`wp_eo_*`) dla agentów, klientów, umów, nieruchomości, poszukiwań i pól dynamicznych.

= Czy agent może korzystać z panelu? =

Tak, wtyczka dodaje rolę `estate_agent` z odpowiednimi uprawnieniami do pracy w panelu CRM bez dostępu do pełnej administracji WordPress.

== Changelog ==

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
