=== EstateOffice CRM ===
Contributors: estateoffice
Tags: real estate, crm, agency, property management
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

EstateOffice CRM to fundament rozbudowanego systemu zarządzania biurem nieruchomości w WordPressie.

== Description ==

Pierwsza wersja wtyczki tworzy bezpieczną strukturę danych, nowy panel administracyjny oraz rolę "Agent". Udostępnia podstawowe formularze wprowadzania danych, konfigurację integracji i responsywny panel CRM widoczny z poziomu front-endu po zalogowaniu administratora.

== Installation ==

1. Skopiuj katalog `estate-office` do folderu `wp-content/plugins/`.
2. Aktywuj wtyczkę w panelu WordPress w sekcji **Wtyczki**.
3. Skonfiguruj ustawienia w menu **Estate Office CRM → Ustawienia**.

== Changelog ==

= 0.0.1 =
* Rejestracja typów postów dla nieruchomości, umów, klientów i poszukiwań.
* Nowy typ użytkownika „Agent” z dedykowanym panelem zarządzania.
* Panel administracyjny z modułami Pulpit, Agenci, Ustawienia, Licencja i About.
* Shortcode `[estate_office_crm]` udostępniający przegląd CRM na froncie dla administratora.
* Integracja z WordPress Settings API dla konfiguracji klucza Google Maps, znaku wodnego, logo i pól niestandardowych.
