=== EstateOffice CRM ===
Contributors: estateoffice
Tags: real estate, crm, agency, property management
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

EstateOffice CRM to fundament rozbudowanego systemu zarządzania biurem nieruchomości w WordPressie.
Wersja 0.2.0 dodaje publiczną prezentację ofert z automatycznie tworzonymi stronami nieruchomości.

== Description ==

Wersja 0.2.0 udostępnia kompletny front-end dla ofert oznaczonych do eksportu wraz z kartami nieruchomości, galerią oraz danymi agenta.
Administratorzy zyskują możliwość tworzenia stron `oferta/{nazwa}` oraz katalogów dla sprzedaży i wynajmu, zachowując wszystkie zależności z metadanymi CRM.

== Installation ==

1. Skopiuj katalog `estate-office` do folderu `wp-content/plugins/`.
2. Aktywuj wtyczkę w panelu WordPress w sekcji **Wtyczki**.
3. Skonfiguruj ustawienia w menu **Estate Office CRM → Ustawienia**.

== Changelog ==

= 0.2.0 =
* Publiczny katalog ofert na sprzedaż i wynajem pogrupowany według rodzaju nieruchomości, miasta i dzielnicy.
* Automatycznie renderowane strony ofert `oferta/{slug}` z opisem, galerią, dodatkowymi mediami i danymi opiekuna.
* Nowy shortcode `[estate_office_offers]` pozwalający wyświetlić oferty według typu transakcji bez potrzeby logowania.

= 0.1.0 =
* Dodane meta boxy dla nieruchomości, umów, klientów i poszukiwań z dynamicznymi polami zależnymi od wyborów użytkownika.
* Wybór opiekuna dla każdej encji CRM oraz automatyczne obliczanie ceny za metr kwadratowy.
* Udoskonalona warstwa JS/CSS w panelu administracyjnym zapewniająca lepszą ergonomię i podgląd danych.

= 0.0.1 =
* Rejestracja typów postów dla nieruchomości, umów, klientów i poszukiwań.
* Nowy typ użytkownika „Agent” z dedykowanym panelem zarządzania.
* Panel administracyjny z modułami Pulpit, Agenci, Ustawienia, Licencja i About.
* Shortcode `[estate_office_crm]` udostępniający przegląd CRM na froncie dla administratora.
* Integracja z WordPress Settings API dla konfiguracji klucza Google Maps, znaku wodnego, logo i pól niestandardowych.
