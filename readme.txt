=== Estate Office CRM ===
Contributors: estateoffice
Tags: real estate, crm, property management
Requires at least: 6.0
Tested up to: 6.4
Stable tag: 0.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Estate Office to kompleksowy CRM dla biur nieruchomości budowany etapami od wersji 0.1 do 1.0.

== Description ==
Estate Office CRM zapewnia fundament pod rozbudowany system zarządzania biurem nieruchomości:

* Dedykowane role – nowy typ użytkownika "Agent" z ograniczonymi uprawnieniami.
* Rozbudowane ustawienia – integracja z Google Maps, znaki wodne, logo oraz dynamiczne pola formularzy.
* Rejestracja kluczowych danych – niestandardowe typy wpisów dla nieruchomości, umów, klientów oraz poszukiwań.
* Przygotowanie do front-endowego panelu CRM – automatycznie generowana strona z kafelkami nawigacyjnymi.
* Przyjazne zarządzanie agentami – formularz dodawania i lista istniejących agentów w panelu administratora.

== Installation ==
1. Prześlij katalog `estate-office` do folderu `/wp-content/plugins/`.
2. Aktywuj wtyczkę w menu "Wtyczki" w panelu WordPress.
3. Skonfiguruj ustawienia w sekcji **Estate Office CRM → Ustawienia**.

== Frequently Asked Questions ==
= Czy agent może usuwać nieruchomości lub umowy? =
Nie. Rola "Agent" została pozbawiona możliwości usuwania rekordów.

= Gdzie znajdę panel CRM na stronie? =
Podczas aktywacji wtyczka tworzy stronę "Estate Office CRM" z panelem dostępnym jedynie dla administratorów.

== Changelog ==
= 0.6.0 =
* Dodano możliwość edycji profili agentów w panelu administratora wraz ze wsparciem dla zdjęcia, danych kontaktowych i biografii.
* Udostępniono publiczne strony agentów prezentujące profil, dane teleadresowe oraz aktywne oferty eksportowane na WWW.
* Rozszerzono strony ofert o sekcję opiekuna z danymi kontaktowymi i odnośnikiem do profilu agenta.

= 0.5.0 =
* Dodano wybór opiekuna podczas tworzenia umowy – wybrany agent jest automatycznie przypisywany do powiązanych nieruchomości i poszukiwań.
* Rozbudowano pulpit CRM o kluczowe wskaźniki, listę najlepszych agentów oraz nadchodzące zakończenia umów.
* Uzupełniono profile nieruchomości i poszukiwań o prezentację opiekuna oraz dopracowano stylizację widżetów statystyk.

= 0.4.0 =
* Uruchomiono automatyczny eksport nieruchomości oznaczonych znacznikiem "Eksport na WWW" do publicznych stron ofertowych wraz z kategoryzacją według typu transakcji, rodzaju, miasta i dzielnicy.
* Dodano stronę administracyjną "Oferty" z możliwością podglądu statusu eksportu, informacji o znaczniku "Nowa oferta" oraz ręczną synchronizacją stron ofert.
* Wprowadzono automatyczne wygaszanie znacznika "Nowa oferta" po 7 dniach i aktualizację powiązanych stron ofertowych.
* Rozszerzono front-endowe style oraz układ kart i profili ofert, w tym sekcje multimediów oraz listy udogodnień.

= 0.3.0 =
* Dodano widoki profili dla umów, nieruchomości, poszukiwań oraz klientów wraz z akcjami edycji i usuwania ograniczonymi do administratorów.
* Udostępniono możliwość aktualizacji etapów umowy wraz z historią zmian.
* Powiązano widok CRM z profilami szczegółowymi i rozszerzono formatowanie metadanych.
* Dodano stylizację interfejsu profili w panelu administratora.

= 0.2.0 =
* Dodano panel CRM z tabelami nieruchomości, klientów, umów oraz poszukiwań wraz z wyszukiwarką wielokolumnową.
* Wprowadzono kreator umów prowadzący przez etapy dodawania umowy, klientów i nieruchomości lub poszukiwań.
* Rozszerzono metadane umów, nieruchomości i klientów o struktury wymagane do profili oraz powiązań.
* Dodano skrypty JS wspierające dynamiczne formularze zgodnie z opisem wtyczki.

= 0.1.0 =
* Pierwsza wersja robocza wtyczki.
* Rejestracja niestandardowych typów wpisów oraz podstawowych taksonomii.
* Konfiguracja ustawień integracji i dynamicznych pól formularzy.
* Dodanie roli użytkownika "Agent" wraz z panelem zarządzania.
* Utworzenie strony front-end CRM z kafelkami nawigacyjnymi.

== Upgrade Notice ==
= 0.1.0 =
Pierwsze wydanie – aktualizacja nie wymaga dodatkowych kroków.
