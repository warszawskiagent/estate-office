=== EstateOffice CRM ===
Contributors: estateoffice
Tags: crm, real-estate, agencies, contracts, properties
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.0.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

EstateOffice to kompleksowy CRM dla biur nieruchomości z obsługą nieruchomości, umów, klientów, poszukiwań i agentów.

== Opis ==

EstateOffice CRM zapewnia profesjonalne zarządzanie ofertami nieruchomości bezpośrednio z panelu WordPress. Wtyczka umożliwia tworzenie wieloetapowego procesu obsługi umów, prowadzenie kart klientów oraz pracę na dedykowanych bazach danych. Panel administracyjny zawiera pulpity z podsumowaniami, listy nieruchomości, poszukiwań, umów i klientów, a także moduł zarządzania agentami. Konfigurowalne pola dynamiczne pozwalają dostosować formularze do potrzeb biura, a integracja z Google Maps ułatwia oznaczanie nieruchomości na mapie.

== Instalacja ==

1. Skopiuj folder `estate-office` do katalogu `/wp-content/plugins/`.
2. Aktywuj wtyczkę w panelu **Wtyczki** w WordPressie.
3. Po aktywacji przejdź do menu **Estate Office CRM** i uzupełnij ustawienia (klucz Google Maps, znak wodny, pola dynamiczne).
4. Dodaj agentów, klientów oraz zacznij tworzyć umowy i nieruchomości korzystając z wbudowanych formularzy.

== Często zadawane pytania ==

= Czy wtyczka tworzy własne tabele w bazie danych? =

Tak. EstateOffice CRM korzysta z dedykowanych tabel (`wp_eo_*`) dla agentów, klientów, umów, nieruchomości, poszukiwań i pól dynamicznych.

= Czy agent może korzystać z panelu? =

Tak, wtyczka dodaje rolę `estate_agent` z odpowiednimi uprawnieniami do pracy w panelu CRM bez dostępu do pełnej administracji WordPress.

== Changelog ==

= 0.0.1 =
* Pierwsze wydanie publiczne.
* Implementacja struktury baz danych, panelu administracyjnego oraz formularzy dla agentów, klientów, umów, nieruchomości i poszukiwań.
* Obsługa pól dynamicznych i integracji z Google Maps.
