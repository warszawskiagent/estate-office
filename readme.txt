=== Estate Office CRM ===
Contributors: tomaszobarski
Requires at least: 6.8.3
Tested up to: 6.8.3
Requires PHP: 8.3
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Estate Office CRM to kompleksowy system do zarządzania biurem nieruchomości w WordPressie.

== Description ==

Wtyczka tworzy fundamenty do budowy zamkniętego panelu CRM dla agentów nieruchomości. Zawiera rejestrację dedykowanych tabel bazy danych, custom post type dla nieruchomości oraz podstawowe widoki w panelu administracyjnym.

== Funkcje ==

* REST API zapewniające bezpieczny CRUD dla klientów, nieruchomości, umów oraz zapisanych wyszukiwań z kontrolą uprawnień.
* Dedykowany routing `/crm` wyświetlający panel CRM tylko dla uprawnionych użytkowników.
* Szablony `single` i `archive` dla nieruchomości z możliwością nadpisania w motywie.

== Installation ==

1. Prześlij katalog `estate-office` do katalogu `/wp-content/plugins/`.
2. Aktywuj wtyczkę przez menu `Wtyczki` w WordPressie.
3. Po aktywacji odwiedź `Estate Office -> Ustawienia` aby skonfigurować podstawowe opcje.

== Changelog ==

= 0.1.0 =
* Pierwsze wydanie szkieletu wtyczki.
