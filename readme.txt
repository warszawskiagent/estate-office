=== EstateOffice CRM ===
Contributors: estateoffice
Tags: real estate, crm, agency, property management
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

EstateOffice CRM to fundament rozbudowanego systemu zarządzania biurem nieruchomości w WordPressie.
Wersja 0.3.0 dodaje pełny kreator umów, klientów i ofert dostępny bezpośrednio z panelu CRM.

== Description ==

Wersja 0.3.0 udostępnia trzyetapowy proces dodawania umów, klientów i nieruchomości/poszukiwań wraz z zabezpieczeniami przed duplikacją oraz automatycznym powiązaniem rekordów.
Administratorzy mogą korzystać z kreatora w interfejsie front-end CRM, zachowując pełną kontrolę nad danymi i przypisaniami agentów.

== Installation ==

1. Skopiuj katalog `estate-office` do folderu `wp-content/plugins/`.
2. Aktywuj wtyczkę w panelu WordPress w sekcji **Wtyczki**.
3. Skonfiguruj ustawienia w menu **Estate Office CRM → Ustawienia**.

== Changelog ==

= 0.3.0 =
* Trzystopniowy kreator umów dostępny z przycisku „Dodaj nową umowę” w panelu CRM z weryfikacją unikalności numeru i automatycznym historiowaniem etapu.
* REST API EstateOffice do tworzenia umów, klientów, nieruchomości i poszukiwań wraz z powiązaniami między rekordami.
* Dynamiczne formularze klientów oraz nieruchomości z zależnościami pól, obsługą adresów korespondencyjnych i znaczników ofert.
* Rozbudowany frontend JS/CSS z modalnym oknem kreatora, listą wybranych klientów i podsumowaniem z szybkim przejściem do edycji rekordów.

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
