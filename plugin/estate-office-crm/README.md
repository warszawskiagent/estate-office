# Estate Office CRM

Wersja: 1.1725

## Zakres wersji 1.1725 (Ustawienia AML, RODO, UODO)

- `Ustawienia`:
  - dodano zakladke `AML, RODO, UODO`,
  - dodano checklisty dokumentow dla AML/CFT, RODO oraz dokumentow operacyjnych UODO,
  - przy kazdym dokumencie mozna wgrac plik do biblioteki Media WordPress i dopisac notatki,
  - dodano linki do zrodel urzedowych przy zakladce.
- `Panel admina`:
  - uploader mediow obsluguje teraz rowniez dokumenty, nie tylko obrazy,
  - dodano mozliwosc wyczyszczenia przypisanego dokumentu.
## Zakres wersji 1.1724 (Hotfix widocznosci pol w poszukiwaniach)

- `Poszukiwania`:
  - wybor `DOM` nie ukrywa juz kryterium `Wielopoziomowe`,
  - pola `Liczba miejsc w garazu` i `Liczba miejsc postojowych` pojawiaja sie dopiero po zaznaczeniu odpowiedniego udogodnienia,
  - pola ilosci pozostaja opcjonalne.
## Zakres wersji 1.1723 (Hotfix formularzy umow, poszukiwan i sortowania list)

- `Nieruchomosci`:
  - powierzchnia balkonu, piwnicy i komorki lokatorskiej nie jest juz wymagana po zaznaczeniu danej powierzchni dodatkowej.
- `Poszukiwania`:
  - dodano wybor wielu rodzajow nieruchomosci w jednym poszukiwaniu,
  - do udogodnien dodano garaz i miejsce postojowe z opcjonalna iloscia,
  - widoki listy i karty czytelnie wyswietlaja wiele rodzajow nieruchomosci.
- `Umowy`:
  - formularz dodawania i edycji pozwala przypisac wielu istniejacych klientow oraz dodac wielu nowych klientow w jednym przebiegu,
  - administrator moze edytowac i usuwac wpisy historii etapow umowy.
- `Listy CRM`:
  - Umowy, Nieruchomosci i Poszukiwania sortuja rekordy wedlug daty zawarcia powiazanej umowy od najnowszej.

## Zakres wersji 1.1722 (Hotfix zakonczenia umowy i transakcji czesciowych)

- `Umowy`:
  - przy etapie `Umowa zakonczona` CRM nie pyta juz o transakcje, jezeli wszystkie zdefiniowane etapy prowizji sa rozliczone,
  - jezeli istnieje transakcja czesciowa i sa jeszcze nierozliczone etapy, CRM otwiera ja do uzupelnienia zamiast tworzyc duplikat.
- `Transakcje`:
  - finalny brakujacy etap prowizji jest dopisywany do istniejacej transakcji czesciowej,
  - os `Etapy platnosci prowizji` pokazuje pokryte etapy jako zrealizowane, rowniez ostatni etap.
## Zakres wersji 1.1721 (Hotfix szybkiej edycji ceny nieruchomosci)

- `Karta nieruchomosci`:
  - w sekcji szybkiej edycji dodano mozliwosc zmiany ceny i waluty,
  - zapis ceny dziala razem ze znacznikami oraz eksportem WWW/portale.
- `Nieruchomosci`:
  - po szybkiej zmianie ceny CRM przelicza cene za m2 na podstawie obecnego metrazu,
  - zapis odswieza oferte WWW i kolejke eksportu portalowego, jezeli eksport jest aktywny.
## Zakres wersji 1.1720 (Hotfix powrotu do umowy i UX prowizji etapowej)

- `Flow dodawania umowy`:
  - po dodaniu nieruchomosci albo poszukiwania powiazanych z nowa umowa CRM wraca na karte tej umowy,
  - jezeli aktualny etap ma prowizje czesciowa, karta umowy pokazuje komunikat z informacja o kolejnym kroku.
- `Transakcje`:
  - komunikat `Transakcja prowizyjna` ma wiecej oddechu i dodatkowo wyjasnia, ze prowizja jest suma dotychczasowych kwot ustalonych etapow.
- `Karta umowy`:
  - podzial prowizji jest wyswietlany kompaktowo, z czytelniejszym ukladem dla dlugich nazw etapow.

## Zakres wersji 1.1719 (Hotfix flow transakcji czesciowych)

- `Dodawanie umowy`:
  - transakcja czesciowa dla etapu poczatkowego nie przerywa juz procesu dodawania nieruchomosci albo poszukiwania,
  - CRM proponuje uzupelnienie transakcji czesciowej dopiero po zapisaniu powiazanej nieruchomosci albo poszukiwania.
- `Etapy umowy`:
  - po przejsciu na kolejny etap prowizyjny CRM otwiera istniejaca transakcje czesciowa do aktualizacji,
  - kolejne etapy prowizji sa dopisywane do tej samej transakcji czesciowej zamiast tworzyc duplikaty.
- `Karta umowy`:
  - przy istniejacej transakcji czesciowej przycisk `Dodaj transakcje` pyta, czy dopisac etap do istniejacej transakcji, czy utworzyc nowa.

## Zakres wersji 1.1718 (Hotfix prowizji kwotowych w transakcjach czesciowych)

- `Transakcje czesciowe`:
  - przy prowizji kwotowej `Cena transakcyjna` nie jest juz wymagana,
  - przy prowizji procentowej cena transakcyjna nadal jest wymagana, bo od niej zalezy wyliczenie wynagrodzenia,
  - zwykle transakcje i transakcje koncowe nadal wymagaja ceny transakcyjnej.
- `Pulpit CRM`:
  - wynagrodzenie z transakcji czesciowych kwotowych jest liczone takze wtedy, gdy cena transakcyjna jest pusta.

## Zakres wersji 1.1717 (Hotfix transakcji startowych i statusow)

- `Umowy`:
  - jezeli etap poczatkowy `Umowa posrednictwa` ma ustawiony podzial prowizji, po dodaniu umowy CRM otwiera formularz transakcji czesciowej dla tego etapu.
- `Transakcje`:
  - lista transakcji ma nowa kolumne `Status`, pokazujaca aktualny etap/status powiazanej umowy.

## Zakres wersji 1.1716 (Hotfix transakcji czesciowych)

- `Baza danych`:
  - dodano jawna migracje zgodnosci, aby `transaction_date` moglo byc puste dla transakcji czesciowych.
- `Transakcje`:
  - etapy prowizji przed rozliczeniem koncowym sa oznaczane jako `Transakcje czesciowe`,
  - formularz zmienia etykiete przycisku na `Dodaj transakcje czesciowa` / `Zapisz transakcje czesciowa`,
  - lista i karta umowy pokazuja oznaczenie transakcji czesciowej.
- `Karta transakcji`:
  - wizualizacja etapow platnosci prowizji pokazuje cala os: rozliczone, aktualny etap i oczekujace.

## Zakres wersji 1.1715 (Hotfix etapow prowizji)

- `Umowy`:
  - w edycji podzialu prowizji lista etapow pokazuje teraz tylko etapy zgodne z typem transakcji danej umowy,
  - procentowy etap prowizji jest traktowany jako procent pelnej prowizji bazowej umowy.
- `Transakcje`:
  - jezeli uzytkownik pominie wczesniejszy etap prowizji, CRM dolicza go do kolejnej transakcji prowizyjnej,
  - dla etapow posrednich data transakcji nie jest wymagana,
  - data przeniesienia wlasnosci / podpisania umowy najmu jest wymagana dopiero przy etapach `Umowa przyrzeczona` albo `Umowa najmu`,
  - karta transakcji pokazuje wizualizacje etapow platnosci prowizji.

## Zakres wersji 1.1714 (Podzia&#322; prowizji na etapy)

- `Umowy`:
  - w sekcji `Wysoko&#347;&#263; prowizji` dodano opcj&#281; `Podzia&#322; prowizji na etapy`,
  - po w&#322;&#261;czeniu mo&#380;na doda&#263; etapy umowy, przy kt&oacute;rych ma by&#263; op&#322;acana cz&#281;&#347;&#263; prowizji,
  - dla ka&#380;dego etapu mo&#380;na poda&#263; wysoko&#347;&#263; prowizji procentowo albo kwotowo.
- `Aktualizacja etapu umowy`:
  - po osi&#261;gni&#281;ciu etapu zdefiniowanego w podziale prowizji CRM pyta, czy utworzy&#263; transakcj&#281; prowizyjn&#261;,
  - dotychczasowy mechanizm pytania przy `Umowa zako&#324;czona` zosta&#322; zachowany.
- `Transakcje`:
  - formularz transakcji mo&#380;e wskaza&#263; etap prowizji,
  - prowizja w transakcji jest automatycznie podstawiana z wybranego etapu,
  - karta i lista transakcji pokazuj&#261; etap prowizji, je&#380;eli transakcja jest z nim powi&#261;zana.
- `Baza danych`:
  - rozszerzono tabele um&oacute;w i transakcji o metadane podzia&#322;u prowizji.

## Zakres wersji 1.1710 (Panel logowania + wylogowanie CRM)

- `Panel logowania Agenta`:
  - przebudowano formularz logowania na kontrolowany formularz CRM,
  - dodano widoczny przycisk `Zaloguj`,
  - po zalogowaniu dodano wyraÄąĹźny przycisk `Panel CRM`.
- `Panel CRM`:
  - na samym dole dodano przycisk `Wyloguj`,
  - wylogowanie wraca domyÄąâ€şlnie do strony panelu logowania Agenta.
- Role CRM:
  - dla Agenta i MenedÄąÄ˝era ukryto gÄ‚Ĺ‚rny pasek administratora WordPress na froncie,
  - administrator nadal widzi pasek admina.

## Zakres wersji 1.1709 (Onboarding e-mail)

- `Agenci -> dodawanie Agenta/MenedÄąÄ˝era`:
  - po utworzeniu nowego uÄąÄ˝ytkownika CRM wysyÄąâ€šany jest e-mail onboardingowy,
  - wysyÄąâ€ška uÄąÄ˝ywa linkÄ‚Ĺ‚w aktywnych w `Ustawienia -> Onboarding` dla roli odbiorcy,
  - bÄąâ€šĂ„â€¦d wysyÄąâ€ški nie blokuje utworzenia konta, ale jest dopisywany do komunikatu w panelu.
- `Ustawienia -> Onboarding`:
  - dodano rĂ„â„˘cznĂ„â€¦ wysyÄąâ€škĂ„â„˘ e-maila onboardingowego do wybranego Agenta lub MenedÄąÄ˝era,
  - przy rĂ„â„˘cznej wysyÄąâ€šce ustawienia z zakÄąâ€šadki sĂ„â€¦ najpierw zapisywane.
- E-mail onboardingowy:
  - dodano szablon HTML z logo Estate Office CRM,
  - dodano linki startowe, login uÄąÄ˝ytkownika, przycisk logowania i link do resetowania hasÄąâ€ša.

## Zakres wersji 1.1708 (Logo + zakladka Onboarding)

- `O CRM`:
  - podmieniono logo na nowy znak kwadratowy,
  - ograniczono rozmiar logo, zeby sekcja wygladala schludnie w panelu administratora.
- `Estate Office CRM -> Pulpit`:
  - dodano horyzontalne logo Estate Office CRM na gorze pulpitu administratora,
  - ograniczono jego szerokosc i wysokosc, aby nie dominowalo nad KPI i skrotami.
- `Ustawienia -> Onboarding`:
  - dodano nowa zakladke miedzy `Ogolne` i `Numeracja Umow`,
  - dodano liste elementow: Panel logowania, Profil Agenta, Agenci i biura, Panel CRM, Nieruchomosci, Poszukiwania, Klienci, Umowy,
  - przy kazdym elemencie dodano przelaczniki TAK/NIE osobno dla Agenta i Menedzera,
  - dodano sekcje `Wlasne linki` z mozliwoscia dodawania kolejnych linkow.

## Zakres wersji 1.1707 (Hotfix: popup konfiguracji PDF oferty)

- `Panel CRM -> Nieruchomosci -> Profil oferty`:
  - przywrocono otwieranie popupu wyboru sekcji przed wygenerowaniem PDF oferty,
  - poprawiono JS, aby przycisk PDF w naglowku profilu odnajdywal modal nawet wtedy, gdy markup popupu jest renderowany w osobnym kontenerze profilu,
  - bez JavaScriptu link PDF nadal dziala jako awaryjne bezposrednie generowanie.

## Zakres wersji 1.1702 (Hotfix: nowa ikona transakcji)

- `Panel CRM -> Transakcje`:
  - zamieniono dotychczasowa ikonke transakcji na nowoczesna ikone SVG,
  - nowa ikona jest widoczna na liscie transakcji, pustej liscie i w profilu transakcji.
- `Panel CRM -> Umowy`:
  - podmieniono ikone w sekcji `Powiazane transakcje`,
  - podmieniono ikone w popupie pytajacym o utworzenie transakcji po zamknieciu umowy,
  - dopasowano kolory i rozmiary do obecnego stylu CRM.

## Zakres wersji 1.1701 (Hotfix: profil transakcji i tytul strony CRM)

- `Panel CRM -> Transakcje`:
  - naprawiono bledne odczytywanie profilu transakcji po zapisie,
  - usunieto warning `Undefined variable $users_table` w profilu transakcji,
  - po zapisie, edycji lub usunieciu transakcji CRM wraca na glowna strone CRM z zakladka `Transakcje`, zamiast przechodzic na osobna strone `Transakcje CRM`.

## Zakres wersji 1.1700 (Transakcje: edycja, usuwanie i wspolpraca agentow)

- `Panel CRM -> Transakcje`:
  - dodano edycje zapisanej transakcji,
  - dodano usuwanie transakcji dostepne tylko dla Administratora,
  - przy wspolpracy z innym Agentem dodano wybor: `Biuro wlasne` albo `Inne biuro`,
  - dla `Biuro wlasne` mozna wybrac Agenta z CRM,
  - dla `Inne biuro` mozna wpisac nazwe biura oraz Agenta wspolpracujacego.
- `Panel CRM -> Pulpit`:
  - wynagrodzenie brutto dalej respektuje zakres widocznosci: Agent swoje, Menedzer biuro, Administrator calosc,
  - Administrator i Menedzer widza dodatkowe rozbicie wynagrodzen poszczegolnych Agentow.
- `Baza danych`:
  - tabela transakcji zostala rozszerzona o pola opisujace wspolprace z Agentem / biurem zewnetrznym.

## Zakres wersji 1.1699 (Hotfix: prowizje transakcji i wynagrodzenie brutto)

- `Panel CRM -> Transakcje`:
  - podczas tworzenia transakcji mozna teraz zmienic kwote i jednostke prowizji,
  - jezeli prowizja nie zostanie zmieniona, formularz nadal podstawia wartosc z umowy.
- `Panel CRM -> Pulpit`:
  - dodano panel `Wynagrodzenie brutto`,
  - panel ma 3 zakladki: biezacy miesiac, poprzedni miesiac i 2 miesiace temu,
  - kwoty sa wyliczane z zapisanych transakcji wg daty transakcji i respektuja zakres widocznosci Agenta, Menedzera oraz Administratora.

## Zakres wersji 1.1698 (Hotfix: transakcje w karcie umowy)

- `Panel CRM -> Umowy`:
  - w karcie `Powiazane transakcje` dodano przycisk `Dodaj transakcje`,
  - popup po zmianie etapu na `Umowa zakonczona` zostal zastapiony modalem w stylu panelu CRM,
  - modal pozwala wybrac: utworzenie transakcji albo samo zapisanie etapu.

## Zakres wersji 1.1697 (Transakcje CRM)

- `Panel CRM -> Transakcje`:
  - dodano nowa zakladke CRM z lista transakcji, profilem transakcji i formularzem dodawania,
  - transakcja jest powiazana z umowa i opcjonalnie z nieruchomoscia,
  - karta transakcji wylicza `Wynagrodzenie` na podstawie ceny transakcyjnej i prowizji z umowy.
- `Panel CRM -> Umowy`:
  - w profilu umowy dodano przycisk `Dodaj transakcje`,
  - w profilu umowy dodano liste powiazanych transakcji,
  - zmiana etapu na `Umowa zakonczona` moze od razu przekierowac do formularza transakcji po potwierdzeniu popupu.

## Zakres wersji 1.1696 (Hotfix: widok Agenta na pulpicie)

- `Panel CRM -> Pulpit`:
  - Agent widzi panel `Najaktywniejsi w biurze`,
  - ranking Agenta i Menedzera pokazuje wszystkich uzytkownikow przypisanych do tego samego biura, takze z wynikiem `0` aktywnych umow.
- `Panel CRM -> Tabele`:
  - potwierdzono i utrzymano ukrywanie kolumny `Opiekun` dla zwyklego Agenta w sekcjach `Nieruchomosci`, `Umowy`, `Klienci` i `Poszukiwania`,
  - kolumna `Opiekun` pozostaje widoczna tylko dla Administratora i Menedzera.

## Zakres wersji 1.1695 (Hotfix: zdjecia opiekunow)

- `Panel CRM -> Nieruchomosci / Poszukiwania / Umowy / Klienci`:
  - zdjecia opiekunow sa pobierane z profilu Agenta CRM, a nie z domyslnego avatara WordPress,
  - na kartach rekordow dzial `Opiekun` pokazuje zdjecie Agenta, jezeli zostalo dodane w profilu,
  - zwykly Agent nie widzi juz kolumny `Opiekun` w tabelach; kolumna pozostaje widoczna dla Administratora i Menedzera.

## Zakres wersji 1.1694 (Hotfix: multimedia po bledzie walidacji)

- `Panel CRM -> Nieruchomosci`:
  - po bledzie walidacji formularz nie traci juz wybranej galerii zdjec,
  - po bledzie walidacji formularz nie traci juz dodanych rzutow kondygnacji,
  - podglad galerii i rzutow jest odbudowywany z wersji roboczej formularza.

## Zakres wersji 1.1693 (Lokalizacja - kolejnosc adresu)

- `Panel CRM -> Nieruchomosci`:
  - w kolumnie `Lokalizacja` ulica z numerem budynku i lokalem jest teraz glowna, wieksza linia,
  - miasto i dzielnica sa teraz mniejsza linia pod adresem.

## Zakres wersji 1.1692 (Lokalizacja nieruchomosci + filtr Aktywne)

- `Panel CRM -> Nieruchomosci`:
  - w kolumnie `Lokalizacja` dodano ulice, numer budynku i lokal, jezeli sa uzupelnione,
  - dodano filtr `Aktywne`, ktory pokazuje nieruchomosci z aktywna, niezakonczona umowa.
- `Panel CRM -> Poszukiwania`:
  - dodano filtr `Aktywne`, ktory pokazuje poszukiwania z aktywna, niezakonczona umowa.
- `Panel CRM -> Umowy`:
  - dodano filtr `Aktywne`, ktory pokazuje umowy bez etapu zakonczonego.

## Zakres wersji 1.1677 (Panel logowania Agenta)

- Dodano publiczny shortcode `[eocrm_agent_login]` z panelem logowania dla Agentow, Menedzerow i Administratorow.
- Instalator tworzy strone `Panel logowania Agenta` i aktualizuje na niej tylko shortcode, jezeli strona juz istnieje.
- W menu administratora Estate Office CRM dodano pozycje `Panel logowania`.
- W adminowym Pulpicie dodano szybki skrot do publicznego panelu logowania.

## Zakres wersji 1.1676 (Licencja: wyrejestrowanie domeny)

- `Estate Office CRM -> Licencja`:
  - dodano przycisk `Wyrejestruj domenĂ„â„˘`,
  - po skutecznym wyrejestrowaniu domeny lokalny klucz licencji jest czyszczony, aby strona nie aktywowala go ponownie automatycznie,
  - odswiezono wyglad ekranu licencji tak, aby pasowal do paneli administracyjnych CRM.

## Zakres wersji 1.1675 (O CRM i Dostep programisty - porzadki)

- `Ustawienia -> Ogolne`:
  - usunieto opis pod naglowkiem `Dostep programisty`.
- `O CRM -> Funkcje zaplanowane`:
  - usunieto placeholder `Lista funkcji zostanie uzupelniona`,
  - usunieto informacje o publikowaniu listy przez Estate Office License Manager,
  - dodano wpisy `Eksport na portale` oraz `Import z MLS WSPON`.

## Zakres wersji 1.1674 (Dostep programisty i Import MLS)

- Ustawienia -> Ogolne:
  - dodano przycisk Dostep programisty na koncu ustawien ogolnych,
  - wlaczenie wymaga hasla sprawdzanego po stronie serwera; w kodzie nie publikujemy hasla w HTML ani JavaScript.
- Ustawienia -> Import MLS:
  - dodano nowa zakladke techniczna widoczna tylko po wlaczeniu dostepu programisty,
  - zakladka jest przygotowana jako bezpieczny placeholder pod przyszly import MLS.
- Ustawienia -> Esport na portale:
  - ukryto provider OtoDom + OLX.pl dla zwyklego administratora,
  - dodano serwerowy fallback na bezpieczny provider, jezeli OtoDom/OLX byl wczesniej wybrany,
  - callbacki i kolejka OtoDom/OLX wymagaja aktywnego dostepu programisty.

## Zakres wersji 1.1673 (Filtry transakcji w Poszukiwaniach i Umowach)

- `Panel CRM -> Poszukiwania`:
  - dodano filtry typu transakcji `KUPNO` i `NAJEM`,
  - filtry dzialaja razem z istniejacym wyszukiwaniem po wszystkich kolumnach.
- `Panel CRM -> Umowy`:
  - dodano filtry typu transakcji `SPRZEDAZ`, `KUPNO`, `WYNAJEM`, `NAJEM`,
  - mechanizm korzysta ze wspolnego filtrowania wierszy tabeli i nie narusza filtrow nieruchomosci.

## Zakres wersji 1.1672 (Rollback funkcji zaplanowanych z serwera licencji)

- `O CRM -> Funkcje zaplanowane`:
  - przywrÄ‚Ĺ‚cono lokalny mechanizm placeholdera / filtra `eocrm_planned_features`,
  - usuniĂ„â„˘to pobieranie planÄ‚Ĺ‚w rozwojowych z serwera licencji,
  - sekcja pozostaje bezpieczna i niezaleÄąÄ˝na od wtyczki licencyjnej.
- `SDK licencyjne`:
  - cofniĂ„â„˘to dodatkowe pobieranie endpointu `development-features`.

## Zakres wersji 1.1670 (Hotfix: Szybkie PrzejÄąâ€şcia front-end zgodne z dowolnymi Permalinks)

- `Pulpit -> Szybkie PrzejÄąâ€şcia (front-end CRM)`:
  - usuniĂ„â„˘to sztywne Äąâ€şcieÄąÄ˝ki `home_url('/crm/')` z parametrami `?crm=...`, ktÄ‚Ĺ‚re dziaÄąâ€šaÄąâ€šy tylko przy Permalinks "Post name" / "Custom" i zwracaÄąâ€šy 404 przy ustawieniu "Plain" oraz innych konfiguracjach.
  - Linki front-endu sĂ„â€¦ teraz budowane dynamicznie poprzez `get_permalink()` na ID stron zapisanych w opcji `eocrm_page_ids` (utworzonej podczas aktywacji wtyczki dla stron `CRM`, `NieruchomoÄąâ€şci CRM`, `Poszukiwania CRM`, `Umowy CRM`, `Klienci CRM`).
  - W razie braku ID w opcji Ă˘â‚¬â€ś fallback do `get_page_by_path()` po slugu (`crm`, `crm-nieruchomosci`, `crm-poszukiwania`, `crm-klienci`, `crm-umowy`).
  - JeÄąÄ˝eli strona zostaÄąâ€ša usuniĂ„â„˘ta rĂ„â„˘cznie przez administratora Ă˘â‚¬â€ś odpowiedni kafelek jest pomijany w siatce, zamiast pokazywaĂ„â€ˇ martwy link.
  - DziĂ„â„˘ki temu Szybkie PrzejÄąâ€şcia dziaÄąâ€šajĂ„â€¦ poprawnie niezaleÄąÄ˝nie od ustawieÄąâ€ž WordPress Ă˘â€ â€™ Ustawienia Ă˘â€ â€™ BezpoÄąâ€şrednie odnoÄąâ€şniki: Prosty / Numeryczny / DzieÄąâ€ž + nazwa / MiesiĂ„â€¦c + nazwa / TytuÄąâ€š wpisu / Niestandardowe.
  - Dodana metoda `EstateOfficeCRM_Admin::resolve_crm_frontend_url(string $page_key, string $slug_fallback): string` Ă˘â‚¬â€ś reuÄąÄ˝ywalna w innych miejscach panelu administratora.

## Zakres wersji 1.1669 (Pulpit: Szybkie PrzejÄąâ€şcia rozszerzone o front-end CRM)

- `Pulpit (panel administratora) -> Szybkie PrzejÄąâ€şcia`:
  - na samym poczĂ„â€¦tku siatki przyciskÄ‚Ĺ‚w dodano 5 przyciskÄ‚Ĺ‚w prowadzĂ„â€¦cych do publicznego front-endu CRM:
    - `Panel CRM` -> `home_url('/crm/')` (`dashicons-dashboard`),
    - `NieruchomoÄąâ€şci` -> `?crm=properties` (`dashicons-building`),
    - `Poszukiwania` -> `?crm=searches` (`dashicons-search`),
    - `Klienci` -> `?crm=clients` (`dashicons-businessperson`),
    - `Umowy` -> `?crm=agreements` (`dashicons-media-document`),
  - przyciski front-endu otwierajĂ„â€¦ siĂ„â„˘ w nowej karcie (`target="_blank"` + `rel="noopener noreferrer"`), aby administrator nie traciÄąâ€š kontekstu pracy w wp-admin,
  - przyciski front-endu majĂ„â€¦ widoczny szewron `dashicons-external` zamiast strzaÄąâ€ški, dziĂ„â„˘ki czemu nowy-tab od razu komunikuje swÄ‚Ĺ‚j charakter,
  - dodano modyfikatory `eocrm-dashboard-quick-link--frontend` / `eocrm-dashboard-quick-link--admin` na elementach `<a>`, aby w przyszÄąâ€šoÄąâ€şci mÄ‚Ĺ‚c precyzyjnie ostylowaĂ„â€ˇ obie grupy bez zmian w PHP.

## Zakres wersji 1.1668 (Wtyczki + O CRM + Pulpit: poprawki linkÄ‚Ĺ‚w i Szybkie PrzejÄąâ€şcia)

- `Widok Wtyczki w panelu WordPress`:
  - linki `Regulamin abonamentu` i `EULA` w wierszu wtyczki przepiĂ„â„˘te na wÄąâ€šaÄąâ€şciwe strony: `https://estateofficecrm.pl/?page_id=204` (Regulamin) oraz `https://estateofficecrm.pl/?page_id=205` (EULA),
  - usuniĂ„â„˘to duplikat Ă˘â‚¬â€ś domyÄąâ€şlny link `OdwiedÄąĹź witrynĂ„â„˘ wtyczki` (generowany przez WordPress z `Plugin URI`), poniewaÄąÄ˝ tĂ„â„˘ samĂ„â€¦ rolĂ„â„˘ peÄąâ€šni juÄąÄ˝ wÄąâ€šasny link `Estate Office CRM`.
- `O CRM`:
  - piguÄąâ€ška `Licencja wtyczki / EULA` przepiĂ„â„˘ta na `https://estateofficecrm.pl/?page_id=205`,
  - dodano nowĂ„â€¦ piguÄąâ€škĂ„â„˘ `Regulamin abonamentu` prowadzĂ„â€¦cĂ„â€¦ do `https://estateofficecrm.pl/?page_id=204`.
- `Pulpit (panel administratora)`:
  - sekcja `Szybkie przejÄąâ€şcia` zostaÄąâ€ša przeniesiona z dolnej czĂ„â„˘Äąâ€şci pulpitu na samĂ„â€¦ gÄ‚Ĺ‚rĂ„â„˘ (pod nagÄąâ€šÄ‚Ĺ‚wkiem i opisem),
  - zamiast listy odnoÄąâ€şnikÄ‚Ĺ‚w wprowadzono nowoczesny grid przyciskÄ‚Ĺ‚w (ikona Dashicons + nazwa + krÄ‚Ĺ‚tki opis + szewron); hover/focus majĂ„â€¦ animacjĂ„â„˘ uniesienia, gradient tÄąâ€ša i kolorystykĂ„â„˘ zgodnĂ„â€¦ z resztĂ„â€¦ panelu CRM,
  - przyciski automatycznie ukÄąâ€šadajĂ„â€¦ siĂ„â„˘ w siatkĂ„â„˘ dopasowanĂ„â€¦ do szerokoÄąâ€şci pulpitu i schodzĂ„â€¦ do jednej kolumny na ekranach poniÄąÄ˝ej 600 px,
  - sekcja `SkutecznoÄąâ€şĂ„â€ˇ agentÄ‚Ĺ‚w` przejĂ„â„˘Äąâ€ša wczeÄąâ€şniejszĂ„â€¦ wskazÄ‚Ĺ‚wkĂ„â„˘ o pochodzeniu liczb z pulpitu CRM, wiĂ„â„˘c nie utracono kontekstu po usuniĂ„â„˘ciu starej listy odnoÄąâ€şnikÄ‚Ĺ‚w.

## Zakres wersji 1.1667 (O CRM: linki, funkcje i status produkcyjny)

- `O CRM`:
  - usuniĂ„â„˘to oznaczenie wersji testowej z aktualnego statusu wersji,
  - dodano link do licencji wtyczki / EULA,
  - zaktualizowano listĂ„â„˘ `DziaÄąâ€šajĂ„â€¦ce funkcje` i usuniĂ„â„˘to z niej licencjonowanie.
- `Funkcje zaplanowane`:
  - dodano nowĂ„â€¦ sekcjĂ„â„˘ w `O CRM`,
  - sekcja jest przygotowana pod zasilanie z wtyczki licencyjnej przez filtr `eocrm_planned_features`.
- `Widok Wtyczki w panelu WordPress`:
  - ustawiono autora jako `Tomasz Obarski`,
  - dodano linki do strony Estate Office CRM, regulaminu abonamentu oraz EULA.

## Zakres wersji 1.1666 BETA (Hotfix: filtry przyciskowe w tabeli NieruchomoÄąâ€şci CRM)

- `Panel CRM -> NieruchomoÄąâ€şci`:
  - dodano przyciski filtrowania tabeli: `SPRZEDAÄąÂ»`, `WYNAJEM`, `EKSPORT WWW`, `EKSPORT PORTALE`, `SPRZEDANE/WYNAJĂ„ÂTE`,
  - filtry dziaÄąâ€šajĂ„â€¦ bez przeÄąâ€šadowania strony,
  - filtry dziaÄąâ€šajĂ„â€¦ razem z dotychczasowym wyszukiwaniem tekstowym po tabeli,
  - dane do filtrowania sĂ„â€¦ pobierane z pÄ‚Ĺ‚l nieruchomoÄąâ€şci: typ transakcji, eksport WWW, eksport portale, sprzedane/wynajĂ„â„˘te.

## Zakres wersji 1.1665 BETA (Hotfix: paski MLS/Premium, czas Nowej oferty, przyciski wyboru)

- `Paski statusÄ‚Ĺ‚w na ofertach`:
  - dodano pasek `MLS`,
  - paski `Premium` i `MLS` przeniesiono na lewĂ„â€¦ stronĂ„â„˘ zdjĂ„â„˘cia,
  - paski po lewej i prawej stronie majĂ„â€¦ osobne liczniki pozycji, wiĂ„â„˘c nie nachodzĂ„â€¦ na siebie.
- `Nowa oferta`:
  - dodano w Ustawieniach ogÄ‚Ĺ‚lnych czas wyÄąâ€şwietlania znacznika `Nowa oferta`,
  - domyÄąâ€şlnie jest to 7 dni,
  - po wygaÄąâ€şniĂ„â„˘ciu codzienne czyszczenie usuwa aktywnĂ„â€¦ flagĂ„â„˘ i sam tag `nowa_oferta`.
- `Formularze CRM`:
  - checkboxy i pola radio w formularzach dodawania/edycji nieruchomoÄąâ€şci, umÄ‚Ĺ‚w, klientÄ‚Ĺ‚w i poszukiwaÄąâ€ž otrzymaÄąâ€šy wyglĂ„â€¦d przeÄąâ€šĂ„â€¦cznikÄ‚Ĺ‚w-przyciskÄ‚Ĺ‚w,
  - aktywne opcje sĂ„â€¦ niebieskie, nieaktywne biaÄąâ€še,
  - logika zapisu pÄ‚Ĺ‚l pozostaÄąâ€ša bez zmian.

## Zakres wersji 1.1664 BETA (Hotfix: paski statusÄ‚Ĺ‚w + szybkie znaczniki CRM)

- `Lista ofert nieruchomoÄąâ€şci` i `Pojedyncza oferta nieruchomoÄąâ€şci`:
  - rozszerzono ukoÄąâ€şne paski na gÄąâ€šÄ‚Ĺ‚wnych zdjĂ„â„˘ciach o znaczniki `WynajĂ„â„˘te`, `Nowa Oferta` i `Premium`,
  - paski majĂ„â€¦ osobne kolory: czerwony dla `Sprzedane`, niebieski dla `WynajĂ„â„˘te`, bÄąâ€šĂ„â„˘kitny dla `Nowa Oferta`, zÄąâ€šoty dla `Premium`,
  - przy kilku aktywnych znacznikach paski ukÄąâ€šadajĂ„â€¦ siĂ„â„˘ pod sobĂ„â€¦.
- `Karta nieruchomoÄąâ€şci w CRM`:
  - pod galeriĂ„â€¦ i rzutami dodano szybkie przeÄąâ€šĂ„â€¦czniki znacznikÄ‚Ĺ‚w bez wchodzenia w peÄąâ€šnĂ„â€¦ edycjĂ„â„˘,
  - obsÄąâ€šugiwane sĂ„â€¦ wszystkie znaczniki oferty oraz `Eksport na WWW` i `Eksport na Portale`,
  - aktywne przyciski sĂ„â€¦ niebieskie, nieaktywne biaÄąâ€še.

## Zakres wersji 1.1663 BETA (Hotfix: pasek SPRZEDANE na zdjĂ„â„˘ciach)

- `Lista ofert nieruchomoÄąâ€şci`:
  - po zaznaczeniu znacznika `Sprzedane` na gÄąâ€šÄ‚Ĺ‚wnym zdjĂ„â„˘ciu pojawia siĂ„â„˘ ukoÄąâ€şny czerwony pasek z biaÄąâ€šym napisem `SPRZEDANE`,
  - pasek jest widoczny w prawym gÄ‚Ĺ‚rnym obszarze zdjĂ„â„˘cia.
- `Pojedyncza oferta nieruchomoÄąâ€şci`:
  - dodano ten sam pasek na gÄąâ€šÄ‚Ĺ‚wnym zdjĂ„â„˘ciu/galerii,
  - obsÄąâ€šugiwane sĂ„â€¦ wszystkie aktualne szablony oferty: `v1-v6`.

## Zakres wersji 1.1662 BETA (Hotfix: galeria mobile + peÄąâ€šna szerokoÄąâ€şĂ„â€ˇ sekcji)

- `Szablony ofert nieruchomoÄąâ€şci / mobile`:
  - naprawiono znikajĂ„â€¦cĂ„â€¦ galeriĂ„â„˘ na telefonach po poprzednim hotfixie,
  - wymuszono peÄąâ€šnĂ„â€¦ szerokoÄąâ€şĂ„â€ˇ dla galerii, podstawowych informacji, opiekuna, szczegÄ‚Ĺ‚Äąâ€šÄ‚Ĺ‚w, udogodnieÄąâ€ž, mediÄ‚Ĺ‚w, opisu i formularza wizyty,
  - zachowano docelowĂ„â€¦ kolejnoÄąâ€şĂ„â€ˇ: galeria, cena i podstawowe informacje, opiekun, dalsza treÄąâ€şĂ„â€ˇ, formularz kontaktowy.
- `Formularze`:
  - poprawiono pozostaÄąâ€še komunikaty formularza opiekuna oferty bez polskich znakÄ‚Ĺ‚w.

## Zakres wersji 1.1661 BETA (Hotfix: mobile oferta + polskie znaki)

- `Szablony ofert nieruchomoÄąâ€şci`:
  - wymuszono na telefonach kolejnoÄąâ€şĂ„â€ˇ: galeria, cena i podstawowe informacje, opiekun oferty,
  - poprawiono ukÄąâ€šad mobilny dla szablonÄ‚Ĺ‚w `v2`, `v3`, `v5`, `v6`, gdzie panel opiekuna mÄ‚Ĺ‚gÄąâ€š spadaĂ„â€ˇ pod caÄąâ€šĂ„â€¦ treÄąâ€şĂ„â€ˇ oferty,
  - dodano dodatkowe zabezpieczenie kolejnoÄąâ€şci dla bazowego hero i szablonu z miniaturami.
- `Kalkulator kredytowy / formularze kontaktowe`:
  - poprawiono polskie znaki w komunikatach formularza doradcy kredytowego,
  - poprawiono polskie znaki w komunikatach formularza kontaktu z opiekunem oferty.

## Zakres wersji 1.1660 BETA (Mobile: galeria -> cena -> opiekun)

- `Szablony ofert nieruchomosci`:
  - poprawiono kolejnosc blokow na telefonach komorkowych dla ukladow `v2`, `v3`, `v5`, `v6`,
  - pod galeria od razu pojawiaja sie:
    - cena i podstawowe informacje,
    - karta opiekuna oferty,
  - dopiero ponizej wyswietlana jest dalsza tresc oferty,
  - formularz kontaktowy pozostaje nizej, aby nie wypychal najwazniejszych informacji poza pierwszy ekran mobile.

## Zakres wersji 1.1659 BETA (OtoDom/OLX: zgodnosc testowa + lokalizacje + statusy)

- `Ustawienia -> Eksport na portale -> OtoDom + OLX.pl`:
  - dodano przeÄąâ€šĂ„â€¦cznik `Tryb testowego konta OtoDom`,
  - uproszczono konfiguracjĂ„â„˘ `site_urn` do gÄąâ€šÄ‚Ĺ‚wnego pola dla Polski (`urn:site:otodompl`),
  - przeÄąâ€šĂ„â€¦cznik OLX odpowiada teraz za dodatkowe walidacje treÄąâ€şci zgodne z wymaganiami OLX, a nie za drugi rÄ‚Ĺ‚wnolegÄąâ€šy `site_urn`,
  - dopisano informacjĂ„â„˘, ÄąÄ˝e dane testowego konta agencyjnego wpisuje siĂ„â„˘ na ekranie logowania OtoDom po klikniĂ„â„˘ciu autoryzacji, a nie w formularzu CRM.
- `Payload / lokalizacje`:
  - dla trybu testowego CRM automatycznie dodaje prefiks `[qatest-mercury]` oraz testowy opis ogÄąâ€šoszenia,
  - eksport wymaga poprawnego mapowania `city_id` dla OtoDom,
  - do `location.custom_fields` wysyÄąâ€šane sĂ„â€¦:
    - `city_id`,
    - opcjonalnie `district_id`,
    - `street_name` jeÄąâ€şli jest dostĂ„â„˘pna nazwa ulicy,
  - zapytania lokalizacyjne korzystajĂ„â€¦ z endpointu `locations/v1/urn:site:otodompl/...`.
- `Statusy ogÄąâ€šoszeÄąâ€ž / synchronizacja`:
  - po publikacji CRM pobiera `GET /advert/v1/<uuid>/meta`,
  - webhook aktualizuje zapisany stan oferty po `object_id`,
  - w nieruchomoÄąâ€şci przechowywane sĂ„â€¦ m.in.:
    - `advert_uuid`,
    - `last_action_status`,
    - `state_code`,
    - `visible_in_profile`,
    - `url`,
    - `ttl`,
    - daty utworzenia / aktywacji / modyfikacji,
    - `last_error`,
    - `last_event_type`,
    - `last_synced_at`.
- `Delete / debug`:
  - przy bÄąâ€šĂ„â„˘dach `POST/PUT/DELETE` CRM zapisuje bÄąâ€šĂ„â€¦d synchronizacji do danych referencyjnych ogÄąâ€šoszenia,
  - po skutecznym `DELETE` pozostawia historiĂ„â„˘ ostatniego statusu, ale czyÄąâ€şci `advert_uuid`, aby nie prÄ‚Ĺ‚bowaĂ„â€ˇ usuwaĂ„â€ˇ oferty ponownie.

## Zakres wersji 1.1658 BETA (Hotfix: autoryzacja OtoDom/OLX + URN)

- `Ustawienia -> Eksport na portale -> OtoDom + OLX.pl`:
  - dodano osobne pole `Partner URN (z panelu aplikacji)` dla wartosci typu `urn:partner:...`,
  - doprecyzowano opisy przy `Site URN`, aby odroznic je od `Partner URN`,
  - przycisk autoryzacji nie pokazuje sie juz na niepelnej konfiguracji.
- `OAuth / token`:
  - walidacja danych API przed autoryzacja i przed wymiana kodu na token obejmuje:
    - `Client ID`,
    - `Client Secret`,
    - `API Key`,
  - komunikat bledu pokazuje teraz konkretnie, czego brakuje w zapisanych ustawieniach.

## Zakres wersji 1.1657 BETA (Eksport OtoDom + OLX.pl)

- `Ustawienia -> Eksport na portale`:
  - dodano nowy provider: `OtoDom + OLX.pl (OLX Group API)`,
  - dodano pola konfiguracyjne:
    - `Client ID`,
    - `Client Secret`,
    - `API Key`,
    - `Notification Secret`,
    - `Authorization host` i `Authorization locale`,
    - `Site URN (OtoDom)` i `Site URN (OLX.pl)`,
    - przeÄąâ€šĂ„â€¦cznik rÄ‚Ĺ‚wnolegÄąâ€šej wysyÄąâ€ški do OLX.
  - dodano generowane URL-e callbackÄ‚Ĺ‚w:
    - `Authentication callback URL`: `/?eocrm_portal_auth=otodom_olx`,
    - `Notification callback URL`: `/?eocrm_portal_notify=otodom_olx`.
- `OAuth + API`:
  - wdroÄąÄ˝ono callback autoryzacji i zapis tokenÄ‚Ĺ‚w (access/refresh, scope, data wygaÄąâ€şniĂ„â„˘cia),
  - wdroÄąÄ˝ono eksport asynchroniczny do `https://api.olxgroup.com/advert/v1` (POST/PUT/DELETE),
  - zapis i aktualizacja zewnĂ„â„˘trznego `advert_uuid` per `site_urn` dla kaÄąÄ˝dej nieruchomoÄąâ€şci.
- `Webhook`:
  - wdroÄąÄ˝ono callback webhooka,
  - walidacja podpisu `x-signature` przez HMAC SHA1 na podstawie:
    - `object_id`,
    - `transaction_id`,
    - `notification_secret`.
- `Hardening`:
  - token OAuth zapisywany tylko przy poprawnym `state`,
  - poprawiono helper tÄąâ€šumienia warningÄ‚Ĺ‚w (brak bÄąâ€šĂ„â„˘du typu przy wywoÄąâ€šaniach bez parametru),
  - uodporniono mapowanie typÄ‚Ĺ‚w transakcji/nieruchomoÄąâ€şci (rÄ‚Ĺ‚wnieÄąÄ˝ warianty z polskimi znakami).

## Zakres wersji 1.1656 BETA (Lista Nieruchomosci: kolumna statusow)

- `CRM -> Nieruchomosci (lista)`:
  - dodano kolumne `Status` z trzema ikonkami:
    - `W` - eksport WWW,
    - `P` - eksport na portale,
    - `U` - status umowy (aktywna/zakonczona),
  - kolory ikon:
    - zielony = aktywne,
    - czerwony = nieaktywne / zakonczone.
- Rozszerzono zapytanie listy nieruchomosci o:
  - `export_www`, `export_portals`,
  - `agreement_id`, `agreement_stage`, `agreement_is_active`.

## Zakres wersji 1.1655 BETA (Pulpit CRM + retencja eksportu sprzedane/wynajete)

- `CRM -> Pulpit`:
  - `Aktywne umowy` nie licza umow z etapem koncowym `Umowa zakonczona`,
  - licznik nieruchomosci zmieniono na `Opublikowane nieruchomosci` (tylko `export_www = 1` bez `Sprzedane/Wynajete`),
  - licznik `Poszukiwania` pomija rekordy powiazane z umowami zakonczonymi.
- `Ustawienia -> Ogolne`:
  - dodano retencje eksportu dla nieruchomosci oznaczonych jako `Sprzedane/Wynajete`:
    - dni dla `Eksport WWW`,
    - dni dla `Eksport na Portale`.
- `Cron dzienny`:
  - po przekroczeniu retencji automatycznie wylacza `export_www` i `export_portals`,
  - dla portali kolejkowane jest zdarzenie `delete`, aby usunac oferte z portalu.
- `Oferty publiczne`:
  - listingi i liczniki uwzgledniaja retencje WWW dla `Sprzedane/Wynajete`.

## Zakres wersji 1.1534 BETA (Panel CRM: profil nieruchomosci + copy mode)

- `CRM -> Profil nieruchomosci`:
  - poprawiono zrodlo danych dla sekcji mediow (`media` JSON zamiast glownego rekordu),
  - dane takie jak `Ogrzewanie`, `Woda`, `Kanalizacja`, `Gaz`, `Prad`, `Umeblowanie`, `Poddasze`, `Wielopoziomowe` sa teraz poprawnie wyswietlane,
  - nadal obowiazuje zasada: pokazywane sa tylko pola uzupelnione.
- `CRM -> Nieruchomosci -> Kopiuj`:
  - tryb `copy-property` laduje pelne assety formularza (Media + Google Maps + edytor), tak jak `new/edit`.

## Zakres wersji 1.1533 BETA (Panel CRM: adres + profil nieruchomosci + kopiowanie)

- `CRM -> Nieruchomosci (tabela)`:
  - rozszerzono kolumne `Adres` o numer lokalu/mieszkania (`building_no/apartment_no`),
  - wyszukiwanie tabeli uwzglednia teraz rowniez `apartment_no`.
- `CRM -> Profil nieruchomosci`:
  - przebudowano sekcje danych tak, aby wyswietlac tylko pola, ktore maja uzupelniona wartosc,
  - dodano brakujace pozycje, m.in. `Pietro` i `Liczba pieter`,
  - zachowano wyswietlanie wszystkich uzupelnionych sekcji (szczegoly, media, udogodnienia, wyposazenie, portal).
- `CRM -> Kopiowanie rekordow (admin)`:
  - dodano przycisk `Kopiuj` na profilu `Nieruchomosci`,
  - dodano przycisk `Kopiuj` na profilu `Umowy`,
  - kopiowanie otwiera uzupelniony formularz, ale z domyslnym numerem:
    - dla nieruchomosci: domyslny numer oferty,
    - dla umowy: domyslny numer umowy wg typu transakcji.
- `Uprawnienia`:
  - tryby `copy-property` i `copy-agreement` sa dostepne wylacznie dla administratora.

## Zakres wersji 1.1532 BETA (Hotfix: bezpieczenstwo SQL po raporcie Plugin Check)

- `Bezpieczenstwo / SQL (Plugin Check)`:
  - usunieto dynamiczny fragment `where_active` z zapytania w eksporcie portalowym i zastapiono go dwoma jawnymi, bezpiecznymi wariantami `SELECT` (z i bez filtra `is_active = 1`),
  - dodano precyzyjne adnotacje `phpcs:ignore` dla wykryc `UnescapedDBParameter` w miejscach, gdzie zapytania sa:
    - przygotowane przez `$wpdb->prepare(...)`,
    - oparte o wewnetrzne mapy tabel/kolumn pluginu (bez danych od uzytkownika).
- `Zakres naprawionych plikow`:
  - `includes/class-estate-office-crm-admin.php`,
  - `includes/class-estate-office-crm-agreements.php`,
  - `includes/class-estate-office-crm-pages.php`,
  - `includes/class-estate-office-crm-portal-export.php`,
  - `includes/class-estate-office-crm-searches.php`.
- `Wersjonowanie`:
  - podbito wersje do `1.1532` w plikach pluginu i dokumentacji.

## Zakres wersji 1.1531 BETA (Hotfix: wyszukiwanie klientow + Menedzer Biura na liscie agentow)

- `Umowy -> dodawanie/edycja`:
  - naprawiono wyszukiwanie klientow (brakujaca funkcja normalizacji w JS),
  - rozszerzono indeks wyszukiwania klienta o dane:
    - imie/nazwisko,
    - nazwa firmy,
    - telefon,
    - e-mail,
    - ulica/numer/miasto.
- `Biura i Agenci`:
  - Menedzer biura jest zawsze wyswietlany na gorze listy agentow w swoim biurze,
  - dodano etykiete pod nazwa agenta i nad telefonem:
    - `Menedzer Biura`.

## Zakres wersji 1.1530 BETA (Patch: Menedzer, paginacja ofert, wyszukiwanie klientow w umowie)

- `Role i uprawnienia`:
  - dodano pelne wsparcie roli `Menedzer` w CRM (runtime caps + panel Agenci),
  - menedzer widzi/edytuje rekordy swojego biura (zakres oparty o `office_id` i `owner_user_id`).
- `Umowy -> klienci`:
  - lista klientow przy dodawaniu/edycji umowy nie renderuje sie juz cala na starcie,
  - klienci pojawiaja sie dopiero po wyszukaniu (zachowane wybrane pozycje przy edycji).
- `Listy ofert publicznych`:
  - dodano licznik paginacji `Strona X z Y`,
  - dodano bezposredni link `Ostatnia (Y)`.
- `Ustawienia -> Szablony Ofert`:
  - dodano opcje `TAK/NIE` otwierania oferty w nowej karcie,
  - opcja dziala dla przycisku `Zobacz oferte` i linku w popupie mapy.
- `Stabilizacja danych`:
  - poprawiono przypisanie ownera nowego klienta dodawanego podczas edycji umowy (owner = opiekun umowy).

## Zakres wersji 1.1529 BETA (Hotfix: polskie znaki w kalkulatorze i formularzu doradcy)

- `Kalkulator kredytowy + formularz doradcy`:
  - poprawiono brakujace polskie znaki we wszystkich etykietach i opisach:
    - sekcja wynikow i ustawien kalkulatora,
    - opis zrodel WIBOR,
    - formularz kontaktu do doradcy pod kalkulatorem.
  - poprawiono komunikaty walidacji i tresci e-mail z formularza doradcy (w tym nazwy parametrow kalkulatora) na poprawny zapis PL.
- Zmiany wdrozone we wszystkich publicznych szablonach ofert (`v1-v6`) oraz w JS frontendu.

## Zakres wersji 1.1528 BETA (Hotfix: usuniecie ramki logo doradcy)

- `Strona oferty (SPRZEDAZ) -> sekcja doradcy pod kalkulatorem`:
  - usunieto ramke wokol logo doradcy (border/shadow),
  - logo jest wyswietlane bez obrysu.

## Zakres wersji 1.1527 BETA (Hotfix: formatowanie logo doradcy w ustawieniach)

- `Ustawienia -> Kalkulator kredytowy`:
  - dodano kontrolke `Maksymalna wysokosc logo wzgledem wysokosci formularza (%)` (zakres `30-100`),
  - dodano wyrownanie logo w osi szerokosci:
    - do lewej,
    - wycentrowane,
    - do prawej,
  - os wysokosci pozostaje zawsze wycentrowana.
- `Strona oferty (SPRZEDAZ) -> sekcja doradcy pod kalkulatorem`:
  - logo respektuje ustawiony limit wysokosci,
  - logo nie rozciaga sie juz nadmiernie wzgledem formularza,
  - wyrownanie poziome jest pobierane z ustawien.

## Zakres wersji 1.1526 BETA (Hotfix: logo doradcy bez obcinania)

- `Strona oferty (SPRZEDAZ) -> sekcja doradcy pod kalkulatorem`:
  - poprawiono render logo/zdjecia doradcy:
    - usunieto wymuszone kadrowanie kwadratowe (`aspect-ratio: 1/1`),
    - ustawiono `object-fit: contain`, aby zawsze bylo widoczne cale logo.
  - dopracowano wyrownanie pionowe bloku:
    - logo nie rozjezdza juz wysokosci sekcji,
    - wysokosc grafiki jest ograniczona do wysokosci ukladu formularza.

## Zakres wersji 1.1525 BETA (Hotfix: proporcja logo/formularza doradcy 60/40)

- `Strona oferty (SPRZEDAZ) -> sekcja doradcy pod kalkulatorem`:
  - zmieniono proporcje layoutu:
    - lewa kolumna (logo/zdjecie doradcy): **60%**,
    - prawa kolumna (formularz): **40%**,
  - dostosowano szerokosc grafiki do nowej proporcji (bez sztywnego limitu 220px).

## Zakres wersji 1.1524 BETA (Hotfix: poprawne miejsce sekcji doradcy)

- `Strona oferty (SPRZEDAZ)`:
  - naprawiono finalnie pozycje renderowania bloku doradcy kredytowego:
    - sekcja byla osadzona w module `Multimedia`, przez co znikala, gdy multimedia byly puste,
    - blok zostal przeniesiony bezposrednio pod `Kalkulator kredytowy` we wszystkich szablonach (`v1-v6`).
- Efekt:
  - sekcja z logo/zdjeciem, haslem i formularzem doradcy wyswietla sie zawsze pod kalkulatorem (jesli opcja `TAK` w ustawieniach).

## Zakres wersji 1.1523 BETA (Hotfix: widocznosc bloku doradcy + przelacznik TAK/NIE)

- `Strona oferty (SPRZEDAZ)`:
  - naprawiono render sekcji pod kalkulatorem:
    - logo/zdjecie doradcy kredytowego,
    - tekst "Umow sie z naszym doradca kredytowym",
    - formularz kontaktowy doradcy.
  - render sekcji jest teraz realizowany bezposrednio przez backend (bez zaleznosci od osobnego include-template), co eliminuje problem niewyswietlania.
- `Ustawienia -> Kalkulator kredytowy`:
  - dodano opcje:
    - `Czy "Umow sie z naszym doradca kredytowym" ma sie wyswietlac pod kalkulatorem?`
    - wybor `TAK / NIE`,
  - gdy ustawione na `NIE`, sekcja pod kalkulatorem nie jest renderowana.

## Zakres wersji 1.1522 BETA (Kalkulator kredytowy + kontakt do doradcy)

- `Strona oferty (SPRZEDAZ)`:
  - pod kalkulatorem kredytowym dodano nowy blok kontaktu do doradcy kredytowego,
  - blok zawiera grafike doradcy, haslo "Umow sie z naszym doradca kredytowym" i formularz:
    - Imie,
    - Nazwisko,
    - E-mail,
    - Telefon,
  - po wyslaniu formularza CRM wysyla e-mail zawierajacy:
    - dane kontaktowe klienta,
    - numer oferty i URL oferty,
    - aktualne wyliczenia kalkulatora kredytowego.
- `Ustawienia -> Kalkulator kredytowy`:
  - dodano nowa zakladke konfiguracyjna,
  - dodano upload logo/zdjecia doradcy kredytowego,
  - dodano pole e-mail odbiorcy powiadomien z formularza.
- `Kalkulator kredytowy (UX)`:
  - dopracowano responsywnosc: kluczowe etykiety i wartosci nie zawijaja sie przy zwezaniu widoku,
  - zamiast zawijania tekstu stosowane jest skalowanie rozmiaru czcionek.

## Zakres wersji 1.1521 BETA (Nowe szablony ofert v4-v6)

- `Ustawienia -> Szablony Ofert`:
  - dodano 3 nowe warianty publicznej strony oferty:
    - `Modern v4`,
    - `Modern v5`,
    - `Modern v6`.
- `Frontend ofert`:
  - dodano 3 nowe layouty strony oferty nieruchomosci,
  - dodano 3 style slidera galerii:
    - miniatury (v4),
    - pionowe numerowane sterowanie (v5),
    - auto-slider z paskiem postepu (v6).
- `Silnik renderowania`:
  - dodano mapowanie nowych wariantow do nowych plikow szablonow.

## Licencja i zgodnosc GPL

- Kod pluginu Estate Office CRM jest udostepniany na licencji `GPL-2.0-or-later`.
- Naglowek pluginu zawiera pola `License` i `License URI`.
- Pelny tekst licencji znajduje sie w pliku `LICENSE`.
- Informacje o komponentach i zasobach dolaczonych do paczki znajduja sie w `THIRD-PARTY-LICENSES.md`.
- Mechanizm klucza licencyjnego (aktywacja/tryb read-only) nie zmienia praw wynikajacych z licencji GPL do kodu zrodlowego.

## Zakres wersji 1.1506 BETA (Eksport na portale - Morizon-Gratka)

- `Ustawienia -> Esport na portale`:
  - dodano drugi portal: `Morizon-Gratka`,
  - dodano osobny formularz konfiguracji Morizon-Gratka:
    - host/port/login/haslo FTP,
    - katalog FTP, timeout i tryb `roznica/calosc`,
    - wojewodztwo, nazwa agencji, informacje naglowka, fallbacki kontaktowe agenta,
  - dodano dynamiczne przelaczanie formularza po wyborze portalu z listy.
- `Silnik eksportu`:
  - kolejka eksportu obsluguje teraz wiele targetow (`Nieruchomosci Online`, `Morizon-Gratka`),
  - zadania sa kolejkowane z `export_target` zgodnym z wybranym portalem,
  - przetwarzanie kolejki dispatchuje do dedykowanego eksportera portalu.
- `Morizon-Gratka (domy.pl/OFERTY.NET)`:
  - dodano generator XML zgodny z wymaganym formatem (`<plik>`, `<lista_ofert>`, `<dzial>`, `<oferta>/<oferta_usun>`),
  - dodano pakowanie eksportu do `oferty_YYYYmmDDHHiiSS.zip`,
  - paczka zawiera `oferty.xml` oraz zdjecia oferty, a XML zawiera referencje `zdjecie1..zdjecie15`,
  - eksport obsluguje zdarzenia: `insert`, `update`, `delete`.
- `Statusy profilu nieruchomosci`:
  - odczyt statusu eksportu pobiera najnowszy rekord kolejki niezaleznie od targetu portalu.

## Zakres wersji 1.15 BETA (Esport na portale - Nieruchomosci Online)

- `Ustawienia -> Esport na portale`:
  - dodano nowa zakladke konfiguracji eksportu portalowego,
  - dodano lewy selector portalu (aktualnie: `Nieruchomosci Online`),
  - dodano formularz po prawej stronie:
    - aktywacja eksportu,
    - host/port/login/haslo FTP,
    - katalog FTP, timeout i tryb `full/incremental`,
    - region (`idRegion` + nazwa),
    - nazwa oprogramowania i fallbacki kontaktowe agenta.
- `Automatyczna wysylka po zapisie nieruchomosci`:
  - po zaznaczeniu `Eksport na Portale` CRM automatycznie kolejkowuje i wysyla eksport NOE 2.0,
  - obsluzone scenariusze:
    - dodanie nieruchomosci (`insert`),
    - edycja nieruchomosci (`update`),
    - odlaczenie eksportu lub usuniecie nieruchomosci (`delete`).
- `Kolejka eksportu`:
  - wykorzystano tabele `eocrm_exports_queue` do zapisu zadan i statusow wysylki,
  - dodano przetwarzanie cykliczne przez cron (`eocrm_portal_export_queue`) jako fallback.
- `Format eksportu`:
  - CRM buduje XML zgodny z NOE 2.0 (`export`, `agents`, `ads`),
  - fotografie i rzuty sa osadzane w XML jako `base64` (`fileContent`),
  - wysylka odbywa sie przez FTP na konto portalu.

## Zakres wersji 1.14 BETA (Lista ofert - wieksze odstepy w bloku informacji)

- `Lista ofert (SPRZEDAZ/WYNAJEM)`:
  - zwiekszono odstepy w kolumnie informacji oferty (od ceny do przycisku `Zobacz oferte`),
  - uporzadkowano odstepy pionowe przez:
    - wiekszy `gap` w kontenerze informacji,
    - reset marginesow paragrafow w tym bloku,
    - jednolity margines dla ceny.

## Zakres wersji 1.13 BETA (Szablony ofert - numer oferty w tytule)

- `Ustawienia -> Szablony Ofert`:
  - dodano nowy checkbox:
    - `Pokazuj numer oferty w tytule oferty`.
- `Tytul strony oferty`:
  - ustawienie steruje dodawaniem numeru oferty w tytule strony publicznej oferty,
  - dziala dla:
    - naglowka strony,
    - document title (SEO title),
    - tytulu strony eksportowanej oferty.
- `Domyslne zachowanie`:
  - nowa opcja ma wartosc domyslna `wlaczone`.

## Zakres wersji 1.12 BETA (Agenci - jakosc zdjec i kadrowanie)

- `Jakosc zdjec agenta (frontend)`:
  - zdjecia agentow na stronach publicznych sa pobierane teraz w rozmiarze `large` (zamiast `thumbnail`/`medium`),
  - poprawiono ostrosc i czytelnosc zdjec na stronie `Biura i Agenci` oraz na karcie oferty.
- `Panel administratora -> Agenci`:
  - dodano flow `Wybierz i wykadruj zdjecie` przy dodawaniu i edycji agenta,
  - po wyborze obrazu otwiera sie modal kadrowania 1:1 (X, Y, Przyblizenie),
  - zapis kadru tworzy nowy zalacznik i automatycznie podmienia `agent_photo_id`.
- `Technicznie`:
  - dodano endpoint AJAX `eocrm_crop_agent_photo` z walidacja uprawnien i nonce,
  - dodano dedykowane style modalu kadrowania.

## Zakres wersji 1.11 BETA (Instalator stron - aktualizacja tylko shortcode)

- `Instalator / aktualizacje pluginu`:
  - dla istniejacych stron systemowych nie jest juz nadpisywany:
    - tytul,
    - slug,
    - status,
    - pozostala tresc strony.
  - aktualizowany jest tylko shortcode strony CRM/ofert.
- `Synchronizacja tresci`:
  - jesli strona ma juz shortcode `eocrm_*`, jest on podmieniany na aktualny wariant wymagany przez plugin,
  - jesli shortcode brak, jest dopisywany na koncu strony.
- `Bezpieczenstwo przed skutkami ubocznymi`:
  - usunieto mechanizm wykrywania duplikatow po identycznej tresci strony (dezaktywacja po samym `post_content`),
  - pozostawiono tylko porzadkowanie duplikatow po kluczu systemowym strony.

## Zakres wersji 1.10 BETA (Biura i Agenci - nizsze ramki agentow)

- `Strona publiczna Biura i Agenci`:
  - obnizono wysokosc ramek agentow,
  - karty poziome nie trzymaja juz sztucznie wysokiego minimum (`430px`),
  - wysokosc kart zostala dopasowana do wysokosci zdjecia (uklad bardziej zwarty i czytelny).

## Zakres wersji 1.09 BETA (Biura i Agenci - reczna kolejnosc biur i agentow)

- `Menu administratora -> Agenci`:
  - dodano pole `Kolejnosc wyswietlania` dla biur (tworzenie i edycja),
  - dodano pole `Kolejnosc wyswietlania` dla agentow (tworzenie i edycja),
  - lista biur i lista agentow pokazuje teraz kolumne `Kolejnosc`.
- `Sortowanie publiczne`:
  - biura na stronie publicznej sa wyswietlane wg `kolejnosci`, potem alfabetycznie,
  - agenci na stronie publicznej sa wyswietlani wg `kolejnosci`, potem alfabetycznie.
- `UX strony Biura i Agenci`:
  - usunieto tekst przed lista agentow bez przypisanego biura:
    - `Agenci bez przypisanego biura`,
    - `Lista agentow publikowanych bez przypisania do biura.`.

## Zakres wersji 1.08 BETA (Biura i Agenci - publikacja agentow bez przypisanego biura)

- `Strona publiczna Biura i Agenci`:
  - dodano osobna sekcje `Agenci bez przypisanego biura`,
  - agenci z wlaczona publikacja, ale bez przypisanego biura, sa teraz widoczni na stronie publicznej,
  - sekcja pojawia sie automatycznie tylko wtedy, gdy sa tacy agenci.
- `UX i jezyki`:
  - dopracowano komunikaty i tlumaczenia (EN/DE/UK) dla sekcji agentow bez biura.

## Zakres wersji 1.07 BETA (Biura i Agenci - edycja tresci naglowka)

- `Strona publiczna Biura i Agenci`:
  - usunieto statyczny napis `Estate Office CRM` z sekcji hero,
  - tytul i opis naglowka strony sa teraz dynamiczne.
- `Menu administratora -> Agenci`:
  - dodano nowa zakladke `Strona Biura i Agenci`,
  - dodano formularz edycji:
    - `Tytul strony`,
    - `Opis pod tytulem`.
- `Backend`:
  - dodano zapis ustawien tresci strony do tabeli ustawien,
  - dodano nowy komunikat po zapisie tresci.
- `Instalacja / defaults`:
  - dodano domyslne ustawienia:
    - `offices_agents_page_title`,
    - `offices_agents_page_intro`.

## Zakres wersji 1.06 BETA (Ustawienia szerokosci stron CRM/ofert)

- `Ustawienia -> Ogolne`:
  - dodano opcje sterowania szerokoscia stron:
    - `Tryb szerokosci`: `Wzgledem kontenera wtyczki` lub `Wzgledem szerokosci monitora`,
    - `Szerokosc (%)`: zakres `60-100`.
- `Frontend (CRM + Oferty + Oferta pojedyncza + Biura i Agenci)`:
  - szerokosc wrappera jest teraz dynamicznie wyliczana na podstawie ustawien administratora,
  - styl jest aplikowany inline do `eocrm-frontend`, dzieki czemu dziala niezaleznie od ograniczen motywu.
- `Instalacja / defaults`:
  - dodano domyslne ustawienia:
    - `frontend_width_mode = screen`,
    - `frontend_width_percent = 100`.

## Zakres wersji 1.05 BETA (hotfix - pelna szerokosc stron CRM/ofert)

- `Frontend (CRM + Oferty + Biura i Agenci)`:
  - dodano wymuszenie pelnej szerokosci dla stron renderowanych przez shortcode wtyczki,
  - wrappery wtyczki dzialaja teraz w trybie full-width/full-bleed (`100vw/100dvw`) niezaleznie od domyslnego kontenera motywu,
  - usunieto boczne ograniczenia ramki na stronach pelnoekranowych, aby tresc wykorzystala cala szerokosc.

## Zakres wersji 1.04 BETA (Pulpit backend - zgodnosc licznikow i rozbudowa menedzerska)

- `Menu administratora`:
  - podmenu `Dashboard` zmieniono na `Pulpit`.
- `Pulpit`:
  - naprawiono zliczanie danych tak, aby backendowy Pulpit liczyl aktywne rekordy spĂ„â€šÄąâ€šjnie z frontendowym Pulpitem CRM,
  - dodano rozszerzony zestaw KPI:
    - aktywne nieruchomosci, umowy, poszukiwania, klienci, agenci, biura,
    - liczba ofert z eksportem WWW,
    - liczba umow wygasajacych w horyzoncie 30 dni.
  - dodano sekcje menedzerskie:
    - wykres `Umowy wg typu transakcji`,
    - wykres `Nieruchomosci wg rodzaju`,
    - wykres lejka etapow umow,
    - panel kontroli danych (braki opiekuna/mapy),
    - tabele `Najnowsze umowy` i `Najnowsze nieruchomosci`,
    - tabela `Skutecznosc agentow`,
    - sekcja rekomendacji dla menedzera.
- `UX`:
  - dodano dedykowane style CSS dla nowego widoku Pulpitu (karty KPI, wykresy paskowe, panele analityczne).

## Zakres wersji 1.03 BETA (O CRM - podmiana logo i redesign UX)

- `O CRM`:
  - podmieniono logo na nowy plik dostarczony przez uzytkownika,
  - usunieto tytul strony, aby panel byl czystszy wizualnie,
  - poprawiono teksty o polskie znaki i aktualna pisownie.
- UX sekcji:
  - `Dzialajace funkcje` otrzymaly nowoczesny, kartowy uklad,
  - `Change Log` zostal przebudowany do schludnej, czytelnej formy zgodnej stylistycznie z `Ustawieniami`,
  - dodano estetyczne kafelki kontaktowe (WWW, e-mail, status wersji).

## Zakres wersji 1.02 BETA (O CRM + redesign sekcji produktu)

- Menu administratora:
  - zmieniono nazwe podmenu `About` na `O CRM`.
- Strona `O CRM`:
  - przebudowano caly widok na czytelny layout podobny stylistycznie do `Ustawien`,
  - dodano logo produktu na gorze strony,
  - dodano dane kontaktowe:
    - `https://estateofficecrm.pl/`
    - `support@estateofficecrm.pl`
  - dodano sekcje `Dzialajace funkcje` (bez rozpisywania historii wszystkich poprzednich wersji),
  - dodano `Change Log` pokazujacy tylko zmiany wzgledem poprzedniej wersji.
- Porzadki tresci:
  - usunieto sekcje `Roadmapa wersji` z ekranu informacji o produkcie.

## Zakres wersji 1.01 BETA (UX + stabilizacja + hardening)

- `Ustawienia -> Ogolne`:
  - dodano ustawienie `Liczba ofert na listach ofert (2-20, tylko parzyste)`.
  - ustawienie jest walidowane po stronie backendu i zapisywane jako `public_offers_per_page`.
- Publiczne listy ofert (`Oferty na Sprzedaz`, `Oferty na Wynajem`):
  - paginacja korzysta teraz z ustawienia administratora (`public_offers_per_page`) zamiast stalej wartosci.
  - dodano pasek informacyjny UX: liczba wynikow i liczba ofert na stronie.
- Stabilizacja i hardening filtrow:
  - filtry `Rodzaj nieruchomosci / Miasto / Dzielnica` sa walidowane po whitelistach dostepnych opcji.
  - zakresy `od-do` (cena, metraz, pokoje) sa normalizowane (gdy `od > do`, wartosci sa zamieniane).
  - dodano dodatkowa normalizacje zakresow po stronie JS dla filtrowania na zywo.

## Zakres wersji 1.00 BETA (wejscie w etap beta)

- `About -> Co jest do zrobienia`:
  - `0.95-1.0: beta testy, poprawki UX, stabilizacja i hardening bezpieczenstwa.`
  - `0.95-1.0: testy regresji wszystkich flow CRM (CRUD, profile, eksport WWW, role i uprawnienia).`
  - `1.0: finalizacja dokumentacji i przygotowanie wydania produkcyjnego.`
- `About -> Aktualny stan`:
  - ustawiono status wersji na `1.00 BETA`.
- polityka wersjonowania na etapie beta:
  - do wyjscia z BETA kolejne wydania beda podbijane o `0.01`.

## Zakres wersji 0.9970 (hotfix - dodatkowe pola, poprawka dodawania kolejnych wierszy)

- `Ustawienia -> Dodatkowe pola`:
  - naprawiono blad renderowania kolejnych pol po kliknieciu `Dodaj pole`,
  - po dodaniu drugiego i kolejnych wierszy formularz zachowuje poprawny layout tabeli,
  - pola `Nazwa`, `Sekcja`, `Typ`, `Pokaz w ofercie WWW` i `Akcje` sa poprawnie renderowane dla kazdego nowego wiersza.

## Zakres wersji 0.9969 (hotfix - dodatkowe pola nieruchomosci)

- `Ustawienia`:
  - konfiguracja dodatkowych pol zostala przeniesiona do dedykowanej zakladki `Dodatkowe pola`,
  - konfiguracja obejmuje wylacznie `Nieruchomosci`,
  - dla kazdego pola mozna ustawic:
    - sekcje formularza (`Szczegoly nieruchomosci`, `Media`, `Udogodnienia`, `Wyposazenie`),
    - typ pola (`Tekst`, `Liczba`, `Tak/Nie`),
    - widocznosc na publicznej stronie oferty (`Pokaz w ofercie WWW`).
- CRM frontend (`Nieruchomosci`):
  - dodatkowe pola sa renderowane dynamicznie w formularzu `Dodawanie nieruchomosci`,
  - dodatkowe pola sa renderowane dynamicznie w formularzu `Edycja nieruchomosci`,
  - wartosci pol sa zapisywane i odczytywane z bazy (`custom_fields_json`),
  - wartosci sa widoczne w `Profilu nieruchomosci`.
- Oferta publiczna (v1, modern v2, modern v3):
  - pokazywanie dodatkowych pol respektuje ustawienie `Pokaz w ofercie WWW`,
  - pola sa wyswietlane zgodnie z przypisana sekcja:
    - `Szczegoly nieruchomosci`,
    - `Media`,
    - `Udogodnienia`,
    - `Wyposazenie`.

## Zakres wersji 0.9968 (hotfix - domyslne etapy umow)

- zaktualizowano domyslne zestawy etapow umow:
  - `KUPNO`:
    - Umowa Posrednictwa,
    - Pierwsze oferty,
    - Pierwsze ogladanie,
    - Kolejne ogladanie,
    - Zlozenie oferty,
    - Negocjacje,
    - Umowa przedwstepna,
    - Wniosek kredytowy,
    - Umowa Kredytowa,
    - Umowa przyrzeczona,
    - Uruchomienie Kredytu,
    - Przejecie lokalu,
    - Kwestie wieczysto-ksiegowe,
    - Umowa zakonczona.
  - `WYNAJEM`:
    - Umowa Posrednictwa,
    - Publikacja w MLS,
    - Przygotowanie oferty,
    - Publikacja oferty,
    - Marketing i prezentacje,
    - Weryfikacja najemcy,
    - Umowa najmu,
    - Przekazanie lokalu,
    - Umowa zakonczona.
  - `NAJEM`:
    - Umowa Posrednictwa,
    - Pierwsze oferty,
    - Pierwsze ogladanie,
    - Kolejne ogladanie,
    - Zlozenie oferty,
    - Negocjacje,
    - Umowa najmu,
    - Przejecie lokalu,
    - Umowa zakonczona.

## Zakres wersji 0.9967 (hotfix - zakladki Biura/Agenci w panelu admina)

- dzial `Agenci` w menu administratora zostal podzielony na dwie czytelne zakladki:
  - `Biura`,
  - `Agenci`.
- formularze i listy sa teraz kontekstowe:
  - zakladka `Biura` pokazuje tylko liste biur + dodawanie/edycje biura,
  - zakladka `Agenci` pokazuje tylko liste agentow + dodawanie/edycje agenta.
- po zapisach i walidacjach ekran wraca do wlasciwej zakladki (bez przeskakiwania miedzy sekcjami),
- linki `Edytuj` w tabelach zachowuja odpowiednia zakladke.

## Zakres wersji 0.9966 (admin UI - Ustawienia + Agenci)

- uporzadkowano i unowoczesniono wyglad strony `Ustawienia` w panelu administratora:
  - czytelniejsze zakladki,
  - spojne panele i sekcje formularzy,
  - dopracowane style pol, focus, opisow i przyciskow,
  - lepsza czytelnosc sekcji numeracji i szablonow.
- analogicznie odswiezono dzial `Agenci`:
  - czytelniejsze tabele biur i agentow,
  - uporzadkowane formularze dodawania/edycji,
  - nowoczesne card/panel spacing i border-radius,
  - poprawiona ergonomia na mniejszych ekranach.
- dodano klasy kontekstowe stron backendu (`eocrm-settings-page`, `eocrm-agents-page`) oraz klasy tabel/form, aby styl byl precyzyjny i bezpieczny.

## Zakres wersji 0.9965 (hotfix pionowego wyrownania danych agenta)

- na stronie `Biura i Agenci` dane w karcie agenta sa wyrownane do srodka zdjecia,
- poprawka dotyczy poziomego ukladu karty agenta (zdjecie po lewej, dane po prawej),
- bez zmian w strukturze danych i funkcjonalnosci.

## Zakres wersji 0.9964 (Biura i Agenci - lista pionowa agentow)

- przebudowano widok agentow na stronie `Biura i Agenci`:
  - agenci wyswietlani sa teraz jeden pod drugim (uklad pionowej listy),
  - karta agenta ma uklad poziomy: zdjecie po lewej, dane po prawej,
  - poprawiono proporcje zdjecia, odstepy i czytelnosc danych kontaktowych,
  - zachowano responsywnosc (na telefonie karta przechodzi do ukladu jednokolumnowego).

## Zakres wersji 0.9963 (hotfix page_id w live filtrach)

- naprawiono krytyczny przypadek dla stron ofert dzialajacych jako `?page_id=...`:
  - live-filtr nie gubi juz `page_id` i nie przechodzi na `/?district=...`,
  - URL filtrow jest budowany z `action` formularza (zachowuje identyfikator strony),
  - backend przy wyznaczaniu URL strony ofert zachowuje pelny `REQUEST_URI` (path + query).
- efekt: filtry i paginacja live pozostaja na poprawnej stronie ofert (`page_id=92` / `page_id=93`).

## Zakres wersji 0.9962 (live wyszukiwarka ofert + stabilny URL)

- przebudowano filtry list ofert na tryb `live`:
  - filtrowanie uruchamia sie automatycznie po zmianie pola (bez klikania `Filtruj`),
  - wyniki odswiezaja sie dynamicznie przez `fetch` (bez pelnego przeladowania strony),
  - paginacja listy ofert dziala dynamicznie (klikniecie strony paginacji laduje nowy widok AJAX-owo),
  - przycisk `Wyczysc` resetuje filtry i odswieza widok bez reloadu calej strony.
- naprawiono problem przekierowania filtrow na `/?...`:
  - URL bazowy strony ofert jest teraz budowany z aktualnej sciezki requestu,
  - dodatkowo fallback korzysta z dedykowanych stron `sale_offers` / `rent_offers`.
- odswiezono UI formularza filtrow:
  - nowy, bardziej nowoczesny wyglad pol,
  - badge statusu `Filtrowanie na zywo`,
  - wizualny stan ladowania podczas dynamicznej aktualizacji listy.

## Zakres wersji 0.9961 (hotfix filtry list ofert)

- naprawiono przekierowanie formularza filtrow na stronie listy ofert (form ma teraz jawny `action` ustawiony na URL biezacej strony ofert),
- dodano nowe filtry w listach ofert:
  - `Cena od`, `Cena do`,
  - `Metraz od`, `Metraz do`,
  - `Liczba pokoi od`, `Liczba pokoi do`,
- rozszerzono backend zapytan SQL (`get_public_offers`, `count_public_offers`) o ww. zakresy,
- paginacja zachowuje wszystkie aktywne filtry zakresowe i slownikowe.

## Zakres wersji 0.9960 (hotfix krytyczny PDF export)

- naprawiono blad krytyczny przy kliknieciu ikony generowania PDF oferty (`Call to undefined method EstateOfficeCRM_Units::sanitize_area_unit()`),
- w eksporcie PDF podlaczono poprawne API jednostek:
  - `EstateOfficeCRM_Units::load_settings(...)`,
  - `EstateOfficeCRM_Units::get_area_unit_for_property(...)`,
  - `EstateOfficeCRM_Units::unit_label(...)`,
- format `Cena za m2` w PDF korzysta teraz z tej samej logiki jednostek co oferta publiczna.

## Zakres wersji 0.9959 (media + rozbudowany PDF oferty)

- sekcja `Media` na stronach oferty (`Modern v1`, `Modern v2`, `Modern v3`) wyswietla teraz parametry obok siebie, zamiast jednego pod drugim,
- `Gaz` nie jest wyswietlany w ofercie, gdy jest odznaczony (analogicznie ukrywane sa puste wartosci mediow),
- przebudowano generator PDF oferty:
  - na gorze PDF jest glowne zdjecie oferty na cala szerokosc,
  - ponizej dane sa prezentowane kartami w ukladzie zblizonym do `v2` (bez filmu/spaceru i bez kalkulatora),
  - sekcja rzutow: jeden rzut w formacie zblizonym do `16:9`, wiele rzutow w siatce `2` na rzad.

## Zakres wersji 0.9958 (media w ofercie + prad dla dzialki)

- dodano sekcje `Media` na stronach pojedynczej oferty (`Modern v1`, `Modern v2`, `Modern v3`),
- sekcja `Media` wyswietla sie miedzy `Szczegoly nieruchomosci` a `Opis oferty`,
- dodano pole `Prad` (checkbox) w formularzu dodawania i edycji nieruchomosci,
- zapisano `Prad` do `media_json` i dodano jego wyswietlanie w sekcji `Media` oferty,
- sekcja `Media` pokazuje tylko dane, ktore sa dostepne (z fallbackiem `Brak danych.`).

## Zakres wersji 0.9957 (hotfix szczegoly oferty)

- w sekcji `Szczegoly nieruchomosci` na stronach oferty (wszystkie szablony: `Modern v1`, `Modern v2`, `Modern v3`) wyswietlane sa teraz tylko parametry, ktore maja realna wartosc,
- ukryto pozycje puste i placeholdery `-`,
- jezeli brak danych dla sekcji, pokazywany jest komunikat `Brak danych.`.

## Zakres wersji 0.9956 (hotfix krytyczny BOM / Media)

- usunieto ukryte znaczniki BOM z plikow:
  - `includes/class-estate-office-crm-admin.php`,
  - `includes/class-estate-office-crm-properties.php`,
  - `templates/frontend-crm.php`,
- BOM powodowal niespodziewany output podczas aktywacji (`6 znakow`) i zaburzal odpowiedzi AJAX/Media,
- po poprawce pliki PHP nie generuja juz dodatkowego outputu przy ladowaniu pluginu.

## Zakres wersji 0.9955 (hotfix krytyczny Media)

- Naprawa stabilnosci WordPress Media:
  - filtry tytulu ofert (`the_title`, `document_title_parts`) sa rejestrowane tylko na frontendzie (bez panelu admin/AJAX/REST),
  - callback `the_title` ma bezpieczna sygnature i nie powoduje `ArgumentCountError` przy niestandardowych wywolaniach filtra.
- Dodatkowe bezpieczniki licencji:
  - requesty ekranow i akcji mediow WP (`upload.php`, `media-new.php`, `async-upload.php`, `query-attachments`, `upload-attachment`) sa wylaczone z globalnej blokady zapisu CRM.
- Cel hotfixu: przywrocenie poprawnego dzialania biblioteki Media i uploadu zdjec przy aktywnej wtyczce CRM.

## Zakres wersji 0.9954 (hotfix)

- Formularz nieruchomosci (`DOM` / `DZIALKA`):
  - `Ksztalt dzialki` nie jest juz polem wymaganym,
  - poprawiono walidacje bokow dzialki: pola wymiarow sa wymagane tylko wtedy, gdy ksztalt zostanie wybrany.
- Odtwarzanie danych po bledzie zapisu:
  - dla dodawania i edycji nieruchomosci CRM zapamietuje ostatni payload formularza i odtwarza go po przekierowaniu z bledem.
- Oferta publiczna - dzialka:
  - naprawiono pobieranie danych `Ksztalt dzialki` i `Wymiary dzialki` (byly puste `-` mimo zapisu),
  - dodano brakujace pola do zapytania oferty publicznej (`plot_shape`, `plot_length`, `plot_width`, `plot_dimensions_text`).
- Informacje podstawowe (oferta + lista ofert):
  - dodano dedykowana ikonke `LOT` dla wielkosci dzialki,
  - dla `DOM` pokazywany jest metraz + osobno wielkosc dzialki,
  - dla `DZIALKA` powierzchnia glowna jest prezentowana z ikonka `LOT`.
- `DZIALKA` - czynsz i powierzchnie dodatkowe:
  - ukryto `Czynsz administracyjny` i `Powierzchnie dodatkowe` w formularzu dodawania/edycji nieruchomosci,
  - backend wymusza dla `DZIALKA`: `admin_rent = null` i czyszczenie danych powierzchni dodatkowych,
  - na stronie oferty dla `DZIALKA` ukryto wiersz `Czynsz`.
- Stabilizacja pod `Media` WordPress:
  - dodano bezpieczniki, by logika menu/guard/licencji nie wchodzila w requesty `AJAX` i `REST`,
  - filtry ukrywania menu CRM dla gosci sa podpiete tylko na frontendzie.

## Zakres wersji 0.9953 (hotfix)

- Nieruchomosci `DOM`:
  - przywrocono pelna obsluge dwoch niezaleznych pol:
    - `Metraz (m2)` (pole `area`),
    - `Wielkosc dzialki (m2)` (pole `plot_area`),
  - walidacja zapisu wymaga podania `Wielkosci dzialki` dla `DOM`.
- Formularz CRM:
  - dodawanie i edycja nieruchomosci `DOM` pokazuje oba pola jednoczesnie (`Metraz` + `Wielkosc dzialki`),
  - dla `DZIALKA` zachowane jest pole powierzchni dzialki w `area`.
- Karta nieruchomosci i oferta publiczna:
  - `Wielkosc dzialki` dla `DOM` jest pobierana z `plot_area`,
  - `Wielkosc dzialki` dla `DZIALKA` pozostaje pobierana z `area`,
  - fallback do wyliczenia z wymiarow dzialki zostal zachowany.
- Publiczne szablony oferty:
  - poprawiono spojnosc we wszystkich wariantach (`v1`, `modern v2`, `modern v3`) tak, aby `DOM` nie tracil `Wielkosci dzialki`.

## Zakres wersji 0.9952 (hotfix)

- Nieruchomosci `DOM`:
  - przywrocono metraz jako glowna powierzchnie w formularzu (etykieta pola `area` to ponownie `Metraz (m2)`),
  - cena za `m2` jest dalej liczona po polu `area`, czyli po metrazu (jak dla mieszkania),
  - `Wielkosc dzialki` w kartach/profilach jest liczona z wymiarow dzialki (ksztalt + boki), a nie z metrazu.
- Nieruchomosci `DZIALKA`:
  - usunieto pole `Rok budowy` z formularza dodawania i edycji,
  - backend podczas zapisu wymusza `year_built = null`,
  - `Rok budowy` nie wyswietla sie juz na profilu nieruchomosci ani na publicznych stronach oferty (v1/v2/v3).

## Zakres wersji 0.9951 (hotfix)

- Nieruchomosci (DOM i DZIALKA):
  - dodano pelna obsluge ksztaltu dzialki: `Kwadrat`, `Prostokat`, `Trojkat`, `Nieregularna`,
  - formularz dodawania/edycji dynamicznie pokazuje wymiary bokow:
    - `Kwadrat`: bok A,
    - `Prostokat`: bok A i bok B,
    - `Trojkat`: bok A, bok B, bok C,
    - `Nieregularna`: bez pol wymiarow,
  - backend waliduje wymagane boki zgodnie z wybranym ksztaltem,
  - zachowana kompatybilnosc ze starszymi wartosciami (`regularny`/`nieregularny`).
- Karta nieruchomosci w CRM:
  - dodano sekcje `Wielkosc dzialki`, `Ksztalt dzialki`, `Wymiary dzialki` (dla DOM i DZIALKA).
- Publiczna strona oferty (v1/v2/v3):
  - dodano wyswietlanie `Wielkosc dzialki`, `Ksztalt dzialki`, `Wymiary dzialki` (dla DOM i DZIALKA).
- Jezyk domyslny przy instalacji:
  - plugin ustawia domyslny jezyk na jezyk WordPress, jesli jest wspierany (`pl_PL`, `en_US`, `de_DE`, `uk_UA`),
  - jesli nie jest wspierany, domyslnie ustawia `en_US`.

## Zakres wersji 0.995 (pre-beta)

- aktywne tlumaczenia frontend CRM + strony ofert dla jezykow:
  - `pl_PL`,
  - `en_US`,
  - `de_DE`,
  - `uk_UA`,
- ustawienie `Jezyk` w `Ustawienia > Ogolne` realnie steruje jezykiem interfejsu CRM/ofert,
- przebudowana zakladka `Jednostki Miary`:
  - usunieto nieuzywane pola,
  - dodano jednostki realnie wykorzystywane w ofertach (`Powierzchnia lokali/domow`, `Powierzchnia dzialki`),
  - metraz i cena za powierzchnie na listach ofert oraz kartach ofert sa teraz przeliczane wg ustawien,
- strona oferty: dodany subtelny separator przed kalkulatorem kredytowym,
- profil nieruchomosci w CRM:
  - dodany czerwony przycisk eksportu PDF oferty,
  - backendowy eksport `pdf_property` z kontrola uprawnien i nonce,
- `Ustawienia > Szablony Ofert`:
  - dodana sekcja `Szablony PDF` (na teraz `PDF v1` z podgladem),
- dodana nowa zakladka `Etapy Umow`:
  - osobna edycja etapow dla `SPRZEDAZ`,
  - osobna edycja etapow dla `KUPNO`,
  - osobna edycja etapow dla `WYNAJEM`,
  - osobna edycja etapow dla `NAJEM`,
  - walidacja i aktualizacja etapu umowy bazuje teraz na konfiguracji tej zakladki,
  - etap poczatkowy nowych umow jest pobierany z konfiguracji typu transakcji.

## Zakres wersji 0.1

- podstawowa struktura wtyczki,
- tworzenie kompletnej bazy CRM podczas aktywacji,
- tworzenie roli i domyslnego uzytkownika Agent Nieruchomosci,
- tworzenie stron CRM i stron ofert publicznych,
- frontend CRM (menu, pulpit, tabele, wyszukiwanie),
- frontend ofert publicznych z kategoryzacja po rodzaju, miescie i dzielnicy.

## Zakres wersji 0.2

- menu administratora `Estate Office CRM`:
  - Dashboard,
  - Agenci,
  - Ustawienia,
  - About,
  - Licencja (placeholder).
- backendowe formularze zarzadzania agentami,
- backendowe ustawienia API Google Maps, watermark i logo,
- konfiguracja dodatkowych pol dynamicznych (nieruchomosci, umowy, klienci),
- zabezpieczenia formularzy (capability + nonce + sanityzacja danych).

## Zakres wersji 0.3

- pelne dodawanie klientow we frontend CRM,
- zapis do wlasnych tabel `eocrm_clients` + `eocrm_client_addresses`,
- dynamiczny formularz klienta,
- walidacja i bezpieczna obsluga POST.

## Zakres wersji 0.4

- pelne dodawanie umow we frontend CRM,
- walidacja numeru umowy, dat i prowizji,
- obsluga umowy bezterminowej (dynamiczne pole daty zakonczenia),
- powiazanie umowy z klientami,
- zapis etapu poczatkowego umowy do `eocrm_agreement_stages`.


## Zakres wersji 0.5

- pelne dodawanie nieruchomosci we frontend CRM,
- dynamiczny formularz nieruchomosci (typy, warunki, pola zalezne),
- integracja map Google (adres, pin, lat/lng, klik na mapie),
- obsluga galerii i rzutow 2D/3D przez WordPress Media,
- walidacja i bezpieczny zapis danych nieruchomosci + mediow do tabel `eocrm_properties` i `eocrm_property_media`.

## Zakres wersji 0.6

- pelne dodawanie poszukiwan we frontend CRM,
- formularz poszukiwania z powiazaniem do umowy typu `KUPNO`/`NAJEM`,
- dynamiczny formularz poszukiwania (pola zalezne od rodzaju nieruchomosci, zakresy, pola dodatkowe),
- walidacja zakresow i unikalnosci numeru poszukiwania,
- zapis do tabel `eocrm_searches` i `eocrm_search_clients`,
- automatyczne przepiecie klientow z powiazanej umowy do poszukiwania,
- tabela poszukiwan i wyszukiwanie po wszystkich kolumnach.


## Zakres wersji 0.6.1 (hotfix)

- naprawa przekierowan po zapisie formularzy CRM (eliminuje blad `Not Found` po zapisie klienta/umowy),
- formularz umowy rozszerzony o dodawanie nowego klienta bez opuszczania etapu umowy,
- po zapisie umowy automatyczne przejscie do kolejnego etapu:
  - `SPRZEDAZ`/`WYNAJEM` -> formularz nowej nieruchomosci,
  - `KUPNO`/`NAJEM` -> formularz nowego poszukiwania,
- automatyczne podpowiedzenie/prefill ID umowy w formularzu kolejnego etapu.

## Zakres wersji 0.7

- profile frontend CRM:
  - profil Umowy,
  - profil Klienta,
  - profil Nieruchomosci,
  - profil Poszukiwania,
- profile zawieraja dane glowne, powiazania miedzy rekordami oraz szybka nawigacje do powiazanych profili,
- profil Umowy zawiera:
  - aktualny etap,
  - historie etapow,
  - formularz aktualizacji etapu (z zapisem do historii etapow),
- dodane komunikaty sukcesu/bledu dla aktualizacji etapu umowy,
- rozszerzone style frontendowe dla widokow profilowych.

## Zakres wersji 0.8-0.9

- domkniety pelny CRUD w frontend CRM dla:
  - Klientow,
  - Umow,
  - Nieruchomosci,
  - Poszukiwan,
- profile wszystkich encji maja aktywne akcje:
  - `Edytuj`,
  - `Usun` (tylko administrator),
  - `Powrot do listy`,
- edycja poszukiwania rozszerzona do pelnego formularza kryteriow (budynek, media, udogodnienia, wyposazenie, powierzchnie dodatkowe),
- edycja nieruchomosci rozszerzona o obsluge mapy Google i galerii/rzutow (WordPress Media),
- backend edycji nieruchomosci zapisuje i podmienia galerie/rzuty w tabeli `eocrm_property_media`,
- wdrozony eksport oferty na WWW jako osobna strona:
  - automatyczne tworzenie/aktualizacja strony oferty przy `export_www = 1`,
  - automatyczne wylaczenie publikacji strony oferty przy odznaczeniu `export_www` lub usunieciu rekordu,
  - publiczne listy ofert zawieraja przycisk `Zobacz oferte`,
  - dodany shortcode strony pojedynczej oferty: `[eocrm_offer_single property_id="ID"]`.

## Zakres wersji 0.94

- rozbudowa modulu `Agenci` o sekcje `Biura`:
  - dodawanie i edycja biur,
  - aktywacja/dezaktywacja biura,
  - pola kontaktowe i opis biura,
  - logo biura przez WordPress Media.
- przypisywanie agentow do biura:
  - pole `Biuro` w formularzu dodawania i edycji agenta,
  - walidacja przypisania tylko do aktywnego biura.
- profil agenta:
  - dodane pole zdjecia agenta (`photo_id`) przez WordPress Media.
- ustawienia:
  - dodana konfiguracja `Nazwa biura`,
  - zachowana konfiguracja globalnego `Logo biura`.
- strona publiczna:
  - nowy shortcode: `[eocrm_offices_agents]`,
  - automatycznie tworzona strona `Biura i Agenci` (`/biura-i-agenci`),
  - widok publiczny prezentuje biura oraz profile przypisanych agentow.
- migracje:
  - dodana tabela `eocrm_offices`,
  - dodane pole `office_id` w `eocrm_agent_profiles`,
  - automatyczny mechanizm `maybe_upgrade()` aktualizuje schemat i strony po podniesieniu wersji.

## Zakres wersji 0.95

- przebudowa strony publicznej `Biura i Agenci`:
  - nowy layout premium (sekcje biur + duze karty agentow),
  - agenci bez przypisanego biura sa ukryci na stronie publicznej.
- przebudowa stron `Oferty na Sprzedaz` i `Oferty na Wynajem`:
  - nowy layout listy ofert (widok kart z duzym zdjeciem i panelem lokalizacji),
  - dodane podgladowe zdjecie oferty (`preview_photo_url`) pobierane z galerii.
- przebudowa strony pojedynczej oferty:
  - nowy layout hero + sekcje szczegolow,
  - dedykowana sekcja `Wirtualny spacer` (embed lub przycisk linku),
  - brak sekcji listing agents, zgodnie z zalozeniem.
- widocznosc menu:
  - dla niezalogowanych ukrywane sa pozycje `CRM` i podstrony CRM (`Pulpit`, `Nieruchomosci`, `Poszukiwania`, `Umowy`, `Klienci`),
  - strony publiczne (`Biura i Agenci`, `Oferty na Sprzedaz`, `Oferty na Wynajem`) pozostaja dostepne.

## Zakres wersji 0.951

- przebudowa publicznej strony pojedynczej oferty:
  - slider galerii na gorze strony (zamiast pojedynczego zdjecia),
  - usunieta dolna sekcja galerii,
  - dodana sekcja `Agent - Opiekun oferty` pod hero i informacjami podstawowymi.
- media oferty osadzane bezposrednio w stronie:
  - `Wirtualny spacer` jako embed (oEmbed/iframe),
  - `Film oferty` jako embed (oEmbed/iframe),
  - `Rzut 2D` i `Rzut 3D` wyswietlane bezposrednio (obraz/iframe).
- pod sekcja rzutow dodana mapa lokalizacji oferty na podstawie wspolrzednych GPS.
- rozszerzenie backendu strony publicznej oferty:
  - dolaczanie danych opiekuna na podstawie `owner_user_id`,
  - pobieranie i wyswietlanie profilu opiekuna (zdjecie, kontakt, biuro, bio).

## Zakres wersji 0.952

- instalator stron pluginu:
  - strony systemowe CRM sa identyfikowane metadanymi (`_eocrm_system_page`, `_eocrm_system_page_key`),
  - podczas aktualizacji strony sa aktualizowane/nadpisywane zamiast tworzenia duplikatow,
  - duplikaty stron systemowych sa automatycznie wygaszane (status `draft`), bez usuwania.
- widocznosc CRM dla gosci:
  - rozszerzono ukrywanie pozycji CRM dla niezalogowanych na:
    - klasyczne menu (`wp_nav_menu_objects`),
    - automatyczne listy stron (`wp_list_pages_excludes`),
    - menu blokowe/FSE (`render_block` dla `core/navigation-link`).
- efekt:
  - pozycja `CRM` i podstrony CRM sa calkowicie niewidoczne dla niezalogowanych, niezaleznie od typu menu.

## Zakres wersji 0.953 (hotfix krytyczny)

- naprawa bledu krytycznego (`Fatal error: Too few arguments`) w hooku `wp_list_pages_excludes`,
- metoda `filter_public_page_list_excludes` przyjmuje teraz bezpiecznie 1 argument (drugi jest opcjonalny),
- rejestracja filtra `wp_list_pages_excludes` ustawiona na `accepted_args = 1` dla pelnej zgodnosci z motywami (np. Kadence fallback menu).

## Zakres wersji 0.954

- strony CRM sa tworzone/aktualizowane domyslnie jako `private`:
  - `CRM`,
  - `Nieruchomosci CRM`,
  - `Poszukiwania CRM`,
  - `Umowy CRM`,
  - `Klienci CRM`.
- usunieto tworzenie podstrony `Pulpit CRM` (`crm-pulpit`),
- starsza podstrona `crm-pulpit` jest automatycznie wygaszana do `draft` podczas aktualizacji,
- rozbudowano instalator tak, aby dla kazdej strony systemowej obslugiwal docelowy `post_status` (publish/private),
- po backupie przygotowywany jest aktualny pakiet `estate-office-crm.zip` (nadpisanie poprzedniego pliku).

## Zakres wersji 0.956

- przebudowa stron `Oferty na Sprzedaz` i `Oferty na Wynajem`:
  - mapa ofert po lewej stronie (polowa szerokosci),
  - oferty po prawej stronie jako siatka 2 kolumn,
  - maksymalnie 6 ofert na stronie.
- dodana paginacja listy ofert (parametr `eocrm_page`).
- poprawiony sposob wyswietlania znacznikow:
  - bez prefiksu `#`,
  - czytelne etykiety (`Nowa oferta`, `Wylacznosc`, `Oferta MLS`, itp.).
- zmieniony uklad danych oferty w karcie:
  - adres przeniesiony w miejsce numeru oferty i wyswietlany jako glowny naglowek,
  - numer oferty przeniesiony pod linie `Miasto / Dzielnica` jako mniejszy tekst:
    - `Numer oferty: ...`.

## Zakres wersji 0.957

- usunieto z list ofert (sprzedaz/wynajem) zbedna gorna komorke informacyjna:
  - `Estate Office CRM / Oferty ... / Wyswietlane sa tylko aktywne oferty...`,
- strona od razu zaczyna sie od filtrow i glownego ukladu `mapa + oferty`.

## Zakres wersji 0.958

- strona pojedynczej oferty:
  - numer lokalu nie jest wyswietlany w adresie,
  - klikniecie w zdjecie (galeria, rzut jako obraz) otwiera powiekszenie lightbox,
  - sekcje `Wirtualny spacer` i `Film oferty` sa pokazywane tylko wtedy, gdy link istnieje.
- formatowanie cen:
  - cena glowna: format `XXX XXX XXX PLN`,
  - cena za `m2`: waluta na koncu (`... /m2 PLN`).

## Zakres wersji 0.959

- strona pojedynczej oferty (sekcja podstawowych informacji obok galerii):
  - znaczniki oferty wyswietlane sa jako normalne etykiety (bez prefiksu `#`),
  - metryczki (`metraz`, `pokoje`, `lazienki`) wzbogacono o ikonki.

## Zakres wersji 0.960

- sekcja `Rzuty` na stronie pojedynczej oferty:
  - `Rzut 2D` i `Rzut 3D` wyswietlane sa obok siebie w ukladzie 2 kolumn,
  - na mniejszych ekranach uklad automatycznie przechodzi do 1 kolumny.
- powiekszanie rzutow:
  - obrazy rzutow maja powiekszenie w lightbox po kliknieciu,
  - dla rzutow osadzanych jako iframe (np. PDF) dodano przycisk `Powieksz` i otwieranie w lightboxie iframe.

## Zakres wersji 0.961

- hotfix krytyczny strony pojedynczej oferty:
  - poprawiono domyslne ukrywanie lightboxa, aby nie renderowal sie automatycznie po wejsciu na oferte,
  - dodano twarde reguly CSS dla elementow z `hidden` w lightboxie (`display: none !important`),
  - uszczelniono stan poczatkowy lightboxa po stronie JS (reset i wymuszenie zamkniecia na starcie).

## Zakres wersji 0.962

- galeria w powiekszeniu na stronie oferty:
  - dodano strzalki `poprzednie/nastepne` w lightboxie dla zdjec galerii,
  - dodano obsluge klawiszy `ArrowLeft/ArrowRight` dla nawigacji po galerii.
- sekcja `Rzuty` na stronie oferty:
  - rzuty 2D i 3D sa wyswietlane obok siebie we wspolnej ramce,
  - usunieto widoczne tytuly `Rzut 2D` i `Rzut 3D`,
  - zachowano mozliwosc powiekszania (obrazy oraz pliki osadzane).
- metryki i ikonografia:
  - poprawiono przypisanie ikon (zamiana metraz/pokoje),
  - dodano ikony i metryki: `Sypialnie`, `Lazienki`, `Toalety`,
  - ten sam zestaw metryk i ikon dodano do list ofert.
- znaczniki i opisy ofert:
  - znaczniki na stronie oferty sa formatowane czytelnie bez `_` (np. `Nowa Oferta`),
  - znaczniki listy ofert i strony oferty sa spĂ„â€šÄąâ€šjne.
- lista ofert:
  - cena ma ten sam format co na stronie oferty (`XXX XXX XXX PLN`),
  - `Rodzaj` przeniesiono pod adres i nad `Numer oferty`.

## Zakres wersji 0.963

- podstawowe informacje (lista ofert + pojedyncza oferta):
  - metryki z ikonami (`metraz`, `pokoje`, `sypialnie`, `lazienki`, `toalety`) nie wyswietlaja sie, gdy wartosc jest pusta lub rowna `0`,
  - caly blok metryk jest ukrywany, jesli po odfiltrowaniu nie ma zadnej pozycji.
- podstawowe informacje tekstowe:
  - `Rodzaj` nie wyswietla placeholdera (`-`) i jest ukrywany, gdy brak wartosci.

## Zakres wersji 0.964

- pojedyncza oferta:
  - dodano odstep miedzy blokiem podstawowych informacji/metryk a znacznikami oferty (spojny z lista ofert),
  - odstep jest widoczny tylko gdy znaczniki sa wyswietlane.
- kalkulator kredytowy (tylko dla ofert `SPRZEDAZ`):
  - cena nieruchomosci podstawia sie automatycznie z oferty,
  - dynamiczne przeliczanie po zmianie wkladu wlasnego (PLN i %), okresu kredytu, marzy i prowizji,
  - wybor WIBOR: `1M`, `3M`, `6M`,
  - obsluga rat `rownych` i `malejacych`,
  - uwzglednienie dodatkowych oplat miesiecznych i opcji doliczenia prowizji do kwoty kredytu.
- integracja WIBOR:
  - dodano endpoint AJAX `eocrm_get_wibor_rates`,
  - plugin pobiera stawki online (z fallbackiem i cache transient), a wyniki sa automatycznie odswiezane w kalkulatorze.

## Zakres wersji 0.965

- strona pojedynczej oferty:
  - modul kalkulatora kredytowego przeniesiony na sam dol strony, pod sekcje mapy lokalizacji,
  - dopracowano odstep miedzy podstawowymi informacjami a znacznikami w bloku podsumowania.
- WIBOR:
  - poprawiono mechanike przelaczania `1M/3M/6M` po stronie JS,
  - dodano dzienny refresh stawek przez WP-Cron (`eocrm_daily_wibor_refresh`),
  - glownym zrodlem stawek jest polska strona `Bankier.pl` (dane publikowane ze zrodla `GPW Benchmark`),
  - dodano dodatkowe zrodlo `PAP Biznes` (tabele GPW Benchmark) oraz fallback na Stooq,
  - dodano bezpieczny fallback cache ostatniego prawidlowego odczytu.
- meta danych WIBOR:
  - kalkulator pokazuje zrodlo danych, date notowania (jesli dostepna) oraz czas aktualizacji cache.

## Zakres wersji 0.966

- kalkulator kredytowy (strona oferty):
  - przebudowano uklad: wyniki obliczen sa po lewej, a ustawienia po prawej,
  - pole `% wkladu wlasnego` ma krok `1` przy zmianie strzalkami,
  - wybor WIBOR zmieniono z listy rozwijanej na 3 przyciski (`1M`, `3M`, `6M`),
  - aktywny przycisk WIBOR jest podswietlany i przelacza obliczenia.
- podstawowe informacje oferty:
  - zamieniono prezentacje adresu i numeru oferty jak na listach:
    - adres jest duzy i pogrubiony,
    - numer oferty jest mniejszy (`Numer oferty: ...`).
- tytul strony oferty:
  - tytul generowany jest jako `Ulica Numer, Miasto (Numer oferty)`,
  - bez numeru mieszkania,
  - dodano aktualizacje tytulu przy synchronizacji strony eksportowej i filtr tytulu dokumentu.
  - dodano tez filtr `the_title`, aby naglowek strony byl zgodny z tym samym formatem.

## Zakres wersji 0.967

- kalkulator kredytowy:
  - dopracowano proporcje kolumn (lewa troche wieksza, prawa troche mniejsza, bardziej kompaktowy blok),
  - ustawiono mniejsza szerokosc maksymalna calego bloku kalkulatora,
  - zachowano podobna wielkosc obu kolumn.
- WIBOR:
  - uszczelniono pobieranie z `Bankier.pl` (naglowki requestu, parser tekstu + fallback parser HTML),
  - priorytetowo wykorzystywane jest zrodlo Bankier.pl.
- szablon oferty nieruchomosci:
  - usunieto ramki (border/box-shadow) w widoku pojedynczej oferty: karty, sekcje podsumowania, media i elementy ramkowe.

## Zakres wersji 0.968

- kalkulator kredytowy:
  - usunieto ograniczenie szerokosci bloku i przywrocono pelna szerokosc sekcji,
  - ustawiono staly podzial kolumn `30% / 70%` (lewa strona wyniki, prawa strona ustawienia),
  - kolumny sa rozciagane na pelna wysokosc sekcji kalkulatora.
- prawa kolumna ustawien:
  - pola formularza wyswietlane sa w 2 kolumnach,
  - pole checkbox (`Dolicz prowizje do kwoty kredytu`) zajmuje cala szerokosc,
  - na mniejszych ekranach formularz automatycznie wraca do 1 kolumny.

## Zakres wersji 0.969

- edycja `Nieruchomosci`, `Umowy` i `Klienta`:
  - dodano pole zmiany `Opiekuna` dostepne tylko dla administratora,
  - zmiana opiekuna jest zapisywana bezposrednio do `owner_user_id`.
- bezpieczenstwo:
  - backend przyjmuje zmiane opiekuna tylko dla `manage_options`,
  - walidacja opiekuna dopuszcza tylko istniejacych uzytkownikow z rola:
    - `administrator`,
    - `agent` (`estate_agent`).
- frontend CRM:
  - lista opiekunow ladowana centralnie z backendu dla trybow edycji:
    - `edit-property`,
    - `edit-agreement`,
    - `edit-client`.

## Zakres wersji 0.970 (0.97 beta)

- uruchomiono modul licencyjny w menu `Estate Office CRM -> Licencja`:
  - obsluga wpisania klucza licencji,
  - status licencji, data wygasniecia i tryb read-only,
  - integracja update-check (`pre_set_site_transient_update_plugins`) przez SDK.
- tryb `tylko odczyt` przy braku aktywnej licencji:
  - globalna blokada wszystkich mutacji CRM opartych o `eocrm_action`,
  - dotyczy CRUD frontend (`Klienci`, `Umowy`, `Nieruchomosci`, `Poszukiwania`) oraz zapisow backend (`Agenci`, `Biura`, `Ustawienia`).
- integracja SDK:
  - dodano `includes/licensing/class-eolm-client.php`,
  - dodano klase integracyjna `includes/class-estate-office-crm-license.php`.
- konfiguracja serwera licencji:
  - domyslnie: `home_url('/')`,
  - mozliwosc nadpisania przez stale:
    - `EOCRM_LICENSE_SERVER_URL`,
    - `EOCRM_LICENSE_PRODUCT_SLUG`.

## Zakres wersji 0.971 (hotfix)

- poprawa modulu licencyjnego:
  - formularz wpisania/zmiany klucza licencji nie jest juz blokowany przez read-only,
  - usunieto dodatkowa blokade `maybe_block_writes_on_expiry` z SDK po stronie admin-init.
- zachowanie trybu tylko odczytu:
  - blokada CRUD nadal dziala globalnie przez wewnetrzny guard `eocrm_action`,
  - brak aktywnej licencji dalej blokuje zapisy CRM (frontend i backend).

## Zakres wersji 0.972 (hotfix)

- konfiguracja serwera licencji:
  - domyslny `license_server_url` ustawiono na `https://estateofficecrm.pl`,
  - nadal mozliwe nadpisanie stalej `EOCRM_LICENSE_SERVER_URL`.
- cache walidacji licencji:
  - klucz cache zawiera teraz rowniez `license_server_url`,
  - zmiana serwera licencji nie uzywa juz stalego cache ze starej konfiguracji.

## Zakres wersji 0.973 (hotfix)

- podmieniono plik SDK klienta licencji w produkcie:
  - `includes/licensing/class-eolm-client.php` (zrodlo: `estate-office-licensja/sdk/class-eolm-client.php`).
- konfiguracja serwera licencji:
  - domyslny `license_server_url` ustawiono na `http://estateofficecrm.pl`,
  - przy poprawnym certyfikacie mozna/zaleca sie nadpisac na `https://estateofficecrm.pl` przez `EOCRM_LICENSE_SERVER_URL`.

## Zakres wersji 0.974 (hotfix)

- publiczna karta oferty:
  - adres oferty jest wiekszy, a cena wyraznie wieksza,
  - zmniejszono odstepy miedzy: cena, lokalizacja, rodzaj i numer oferty,
  - metryki (ikony + wartosci) zostaly powiekszone,
  - znaczniki (`Nowa Oferta`, `Wylacznosc`, itd.) sa lekko wieksze,
  - w sekcji `Szczegoly nieruchomosci` zmniejszono odstepy miedzy pozycjami,
  - `Wirtualny spacer` i `Film oferty` maja wymuszony format `16:9`.
- listy ofert (sprzedaz/wynajem):
  - cena jest wyraznie wieksza na karcie listy,
  - metryki (ikony + wartosci) sa wieksze i bardziej czytelne,
  - opis oferty ograniczono do pierwszej linii, maksymalnie `100` znakow (z `...` po przekroczeniu limitu).

## Zakres wersji 0.975 (hotfix)

- menu publiczne:
  - domyslnie plugin nie filtruje juz pozycji menu dla gosci,
  - strony publiczne (`Oferty na Sprzedaz`, `Oferty na Wynajem`, `Biura i Agenci`) pozostaja widoczne dla niezalogowanych,
  - CRM dalej pozostaje chroniony na poziomie tresci (shortcode CRM wymaga logowania/uprawnien).
- karta oferty:
  - metryki i ikony w glownej sekcji informacji zostaly dodatkowo powiekszone.
- lista ofert:
  - cena zostala przeniesiona pod adres.

## Zakres wersji 0.976 (hotfix)

- Ustawienia:
  - dodano zakladki: `Ogolne`, `Szablony Ofert`, `Jednostki Miary`,
  - wszystkie dotychczasowe opcje pozostaja w zakladce `Ogolne`,
  - do `Ogolne` dodano pole `Jezyk` (`pl_PL`, `en_US`, `de_DE`, `uk_UA`).
- nowe sekcje:
  - `Szablony Ofert`: bazowe etykiety i naglowki ofert (`sprzedaz`, `wynajem`, etykieta CTA, widocznosc numeru oferty),
  - `Jednostki Miary`: waluta, jednostki powierzchni i odleglosci.
- bezpieczenstwo zapisu:
  - zapisuje sie tylko aktywna zakladka ustawien (bez nadpisywania danych z pozostalych zakladek).

## Zakres wersji 0.991 (duzy hotfix CRM + oferty + biura)

- Nieruchomosci - formularz i karta:
  - dodano pole `Gmina` dla typow `DOM` i `DZIALKA` (dodawanie/edycja),
  - w profilu nieruchomosci dodano osobne pozycje `Powiat` i `Gmina`,
  - rozbudowano builder rzutow: szersze pola nazw, uklad obok siebie i zmiana kolejnosci (`W gore` / `W dol`).

- Poszukiwania:
  - dodano domyslny format numeru poszukiwania (prefiks + licznik z Ustawien) do formularza dodawania,
  - dodano pola `Pietro od` / `Pietro do` (dodawanie + edycja + walidacja),
  - karta poszukiwania wyswietla teraz zakres pieter,
  - poprawiono prezentacje `Pow. dodatkowe` (czytelny format zamiast surowego JSON).

- Umowy (frontend CRM):
  - naprawiono fallback `Rodzaj nieruchomosci` dla umow powiazanych z poszukiwaniami (odczyt z poszukiwania/kryteriow, gdy brak nieruchomosci).

- Widocznosc danych dla Agenta:
  - Agent widzi tylko swoje `Umowy`, `Klientow` i `Nieruchomosci`,
  - `Poszukiwania` pozostaja globalne (widoczne dla wszystkich Agentow),
  - Administrator nadal widzi wszystkie rekordy.

- Strony CRM:
  - strony systemowe CRM sa utrzymywane jako `Opublikowane` (przy zachowaniu kontroli dostepu shortcode i uprawnieniami).

- Oferty publiczne:
  - dla `DOM` i `DZIALKA` adres wyswietla tylko ulice (bez numeru budynku) na liscie i karcie oferty,
  - dla `DOM` i `DZIALKA` dodano informacje `Powiat/Gmina` na kartach ofert i stronie oferty.

- Biura i Agenci:
  - poprawiono wyswietlanie opisu biura (z fallbackiem, gdy opis pusty),
  - dodano pelna obsluge widocznosci publicznej biur/agentow (backend + frontend),
  - poprawiono layout kart agentow: 3 kolumny na desktop, brak przycinania zdjec (object-fit: contain).

## Zakres wersji 0.992 (hotfix: rzuty + listy + mapa + numeracja umow)

- Formularz nieruchomosci - rzuty:
  - poprawiono obsluge klikniec w przyciski zmiany kolejnosci (`W gore` / `W dol`) i uszczelniono eventy,
  - przebudowano layout listy rzutow, aby akcje byly zawsze klikalne i czytelne.

- Biura i Agenci:
  - karty agentow sa wezsze i bardziej responsywne (`auto-fit`),
  - zdjecia maja lagodne zaokraglenia,
  - zmniejszono odstepy w kartach i utrzymano format jednolinijkowy informacji na telefonach.

- Lista ofert:
  - zageszczono odstepy miedzy adresem/cena/lokalizacja/rodzajem/numerem oferty,
  - doprecyzowano style marginesow (z priorytetem nad stylem motywu), by uniknac zbyt duzych przerw.

- Ustawienia - numeracja umow:
  - dodano pelna konfiguracje wzorca numeru umowy:
    - liczba czesci (`1-4`),
    - separator (`-`, `/`, `_`, `.`),
    - typ czesci (`Licznik`, `Data`, `Tekst`) i wartosc kazdej czesci,
  - generowanie domyslnego i kolejnego numeru umowy korzysta z tego samego wzorca.

- Mapa na liscie ofert:
  - mapa renderuje markery dla wszystkich ofert na aktualnej stronie (max 6),
  - marker otwiera skrocone podsumowanie + link do oferty,
  - dodano numerowanie markerow i offset dla duplikatow wspolrzednych, aby nie nakladaly sie w jednym punkcie.

## Zakres wersji 0.993 (hotfix: mapa + agenci + nowa numeracja)

- Mapa listy ofert:
  - popup markera pokazuje miniature zdjecia glownego oferty (`preview_photo_url`),
  - popup ma nowoczesny uklad (zaokraglona karta, cienie, CTA do oferty),
  - dopracowano styl kontenera Google InfoWindow (zaokraglenie, padding i wyglad przycisku zamykania).

- Biura i Agenci:
  - przebudowano karty agentow:
    - sa wyzsze i czytelniejsze,
    - zdjecie agenta jest lepiej eksponowane i ma zaokraglenie,
    - utrzymano tylko delikatna, pojedyncza ramke calej karty,
  - siatka kart jest responsywna (`3` kolumny desktop, `2` tablet, `1` mobile),
  - dane kontaktowe zachowuja format jednolinijkowy (bez zawijania na mniejszych ekranach).

- Ustawienia - Numeracja Umow:
  - przebudowano konfiguracje numeracji do osobnej zakladki `Numeracja Umow`,
  - dodano 3 niezalezne panele: `Umowy`, `Oferty`, `Poszukiwania`,
  - w kazdym panelu:
    - `Prefiks`,
    - liczba czlonow (bez prefiksu) `1-3`,
    - separator (`/`, `-`, `.`),
    - dla kazdego czlonu typ: `Data`, `Cyfry`, `Wlasne`,
    - dynamiczne pola zalezne od typu (format daty / poczatek i przyrost licznika),
  - tryb `Wlasne` pozostawia puste pole numeru przy tworzeniu rekordu,
  - generator numerow backendu korzysta z tych samych ustawien dla umow/ofert/poszukiwan
    (np. `UM/20260331/0001`).

## Zakres wersji 0.994 (hotfix: odstepy + numeracja Umow per typ)

- Lista ofert + popup mapy:
  - zmniejszono odstepy miedzy liniami informacji w popupie markera mapy (czytelniejsza, bardziej zwarta chmurka),
  - na kartach listy ofert dodano mala przerwe:
    - miedzy `Numer oferty` a metrykami (ikonami),
    - miedzy metrykami a znacznikami.

- Ustawienia - Numeracja Umow:
  - sekcja `Umowy` ma 4 przyciski/warianty:
    - `SPRZEDAZ`,
    - `KUPNO`,
    - `WYNAJEM`,
    - `NAJEM`,
  - kazdy wariant ma niezalezny format numeracji (prefiks, liczba czlonow, separator, typy czlonow, daty/liczniki),
  - `Oferty` i `Poszukiwania` pozostaja w dotychczasowym modelu numeracji.

- Tworzenie Umowy (frontend CRM):
  - domyslny numer umowy zmienia sie dynamicznie po wyborze typu transakcji,
  - przy zapisie umowy automatyczne numerowanie korzysta z konfiguracji odpowiadajacej wybranemu typowi transakcji.

## Bezpieczenstwo i dane

- wszystkie zapytania SQL sa parametryzowane,
- shortcode CRM jest dostepny tylko dla zalogowanych z odpowiednimi uprawnieniami,
- role i capabilities sa rozdzielone (Agent bez uprawnien administracyjnych i bez usuwania rekordow),
- plugin korzysta z dedykowanych tabel `wp_eocrm_*`.




