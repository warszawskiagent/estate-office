=== Estate Office CRM ===
Contributors: estateoffice
Tags: real estate, crm, properties, agency, offers
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.1725
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Estate Office CRM is an advanced CRM plugin for real estate agencies. It manages clients, agreements, properties and searches, and publishes offers on the website.

== Description ==

Estate Office CRM provides:

* Front-end CRM for agents and administrators.
* Property, client, agreement and search management (custom plugin tables).
* Public offer pages and listings (sale/rent).
* Offices and agents public directory.
* XML export tools and portal export integrations.
* PDF generation for property cards.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/estate-office-crm`, or install through the WordPress plugins screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Configure settings in `Estate Office CRM -> Ustawienia`.

== Frequently Asked Questions ==

= Does it work with agents and role separation? =

Yes. The plugin includes dedicated capabilities for administrators and agent users.

= Does it create plugin-specific database tables? =

Yes. CRM data is stored in plugin tables created on activation.

== Changelog ==

= 1.1724 =
* Searches: house searches no longer hide the multi-level criterion.
* Searches: garage and parking-space count fields are displayed only after selecting the related amenity.

= 1.1723 =
* Properties/Searches: balcony, basement and storage area fields are no longer required when the feature is selected.
* Searches: added multi-select property types and optional garage/parking-space counts in amenities.
* Agreements: agreement forms now support assigning multiple existing clients and adding multiple new clients in one flow.
* Agreements: administrators can edit or delete completed agreement stage history entries.
* CRM lists: agreements, properties and searches are ordered by agreement signing date, newest first.

= 1.1722 =
* Agreements: ending an agreement no longer asks for a transaction when all commission split stages are already settled.
* Agreements: ending an agreement with an existing partial transaction now opens that partial transaction for completion instead of creating a duplicate.
* Transactions: commission payment timeline marks covered stages as completed, including the final covered stage.
= 1.1721 =
* Property profile: quick edit card now supports updating offer price and currency together with flags and export markers.
* Properties: quick price save recalculates price per square meter and refreshes website/portal export status.
= 1.1720 =
* Agreement flow: after creating a related property/search, CRM now returns to the created agreement profile instead of opening the transaction form automatically.
* Agreements: added success notices on the agreement profile after related property/search creation, including a clear partial-commission hint when needed.
* Transaction form: commission-stage hint now explains that commission is the sum of currently due stage amounts.
* Agreement profile: commission split display is now compact and readable for long stage names.

= 1.1719 =
* Agreement flow: initial partial commission transaction is now offered after the related property/search is saved, not immediately after agreement creation.
* Agreements: commission-stage updates now open the existing partial transaction for extension instead of creating duplicate partial transactions.
* Transactions: creating a partial commission-stage transaction automatically updates the existing partial transaction when one already exists.
* Agreement profile: "Dodaj transakcje" asks whether to extend an existing partial transaction or create a new transaction.

= 1.1718 =
* Transactions: partial commission-stage transactions with fixed commission amounts no longer require a transaction price.
* Transactions: transaction price remains required for percentage commissions and settlement/final transactions.
* Dashboard: fixed-amount partial transactions are included in remuneration totals even when transaction price is empty.

= 1.1717 =
* Agreements: if the initial "Umowa posrednictwa" stage has a commission split payment, CRM opens the partial transaction form immediately after agreement creation.
* Transactions: added a Status column showing the current stage/status of the linked agreement.

= 1.1716 =
* Transactions: added an explicit schema compatibility migration so partial commission transactions can be saved without a settlement date.
* Transactions: non-settlement commission-stage records are displayed as partial transactions.
* Transaction profile: payment stages now render as a full timeline with completed, current and upcoming stages.
* Transaction form: the submit button label switches to partial transaction wording for intermediate commission stages.

= 1.1715 =
* Agreements: commission split rows in edit mode now show only stages valid for the selected agreement transaction type.
* Transactions: percentage commission stages are calculated as a percentage of the full agreement commission, not directly from the transaction price.
* Transactions: skipped unpaid commission stages are summed into the next created commission-stage transaction.
* Transactions: settlement date is required only for "Umowa przyrzeczona" / "Umowa najmu" or regular transactions without a commission stage.
* Transaction profile: added payment-stage visualization for commission-stage transactions.

= 1.1714 =
* Agreements: added "Podzial prowizji na etapy" with configurable agreement stages and commission values.
* Agreement stage update: CRM can now prompt for a commission-stage transaction when a configured commission stage is reached.
* Transactions: transactions can be linked to a commission stage and inherit that stage commission amount/unit.
* Transaction UI: added commission stage selector, profile display and list hints.
* Database: extended agreements and transactions tables with commission stage metadata.

= 1.1710 =
* Agent login panel: replaced the default WordPress login form with a controlled CRM form and an explicit "Zaloguj" button.
* Agent login panel: logged-in CRM users now see a clear "Panel CRM" button.
* Front-end CRM: added a bottom "Wyloguj" button.
* UX: WordPress admin bar is hidden on the front-end for Agent and Manager roles while remaining visible for administrators.

= 1.1709 =
* Onboarding: new Agent and Manager users now receive an onboarding e-mail with the links enabled for their role.
* Onboarding: added an HTML e-mail template with CRM links, login button and password reset link.
* Settings -> Onboarding: added manual on-demand onboarding e-mail sending to a selected Agent or Manager.

= 1.1708 =
* Branding: replaced the About CRM square logo and added the horizontal Estate Office CRM logo to the administrator dashboard.
* Admin dashboard: logo sizes are constrained for a cleaner layout.
* Settings: added a new Onboarding tab between General settings and Agreement numbering.
* Onboarding: added Agent/Manager TAK/NIE switches for core starter links and a repeater for custom onboarding links.

= 1.1707 =
* Property PDF export: restored the options popup before generating a property offer PDF.
* Fixed frontend JS lookup so the PDF button in the property profile header correctly opens the modal even when the modal markup is rendered in a separate profile container.
* Direct PDF URL remains as a safe fallback when JavaScript is unavailable.

= 1.1706 =
* CRM frontend style selector moved out of the developer-access card to the main General settings panel Ă˘â‚¬â€ť placed directly between "Google Maps API Key" and "Znak wodny".
* The selector is now always visible and savable, no developer access required.
* Default style switched to "Minimalistyczny" (was "Modern"). New installs and any unset value resolve to minimal.
* get_active_crm_style() no longer gates on developer_access_enabled; reads crm_style directly and falls back to minimal for empty / invalid values.
* Save handler simplified: always persists the chosen crm_style; no forced overwrite when dev access is off.

= 1.1705 =
* Minimal style: the actual emoji glyphs (cena, metraz, galeria, etc.) are now replaced with clean monochrome SVG icons (Feather/Lucide style). The original emoji text inside icon containers is hidden via font-size:0 and a `::before` pseudo-element with mask-image renders the SVG, picking up the parent's pastel color.
* Themed detail-cards: map-pin (Lokalizacja), ruler (Powierzchnia), home (Stan), bolt (Media), check-circle (Cechy), globe (Eksport), file-text (Opis/Identyfikacja), clock (Historia), image (Galeria), flag (Tagi), clipboard (Aktualizacja etapu / Szczegoly), user (Opiekun w sidebarze), bar-chart (Podsumowanie), phone (Kontakt klienta).
* Dashboard KPIs: home, file, search, users (instead of 4 random emoji).
* Quick stats Ă˘â‚¬â€ť context aware (data-eocrm-...-profile): Property=coin/ruler/bed/user, Search=coin/ruler/bed/user, Agreement=calendar/bar-chart/coin/user, Client=file/home/search/user.
* Empty state icons: home/search/file/users depending on which listing is empty.
* Hero gallery overlay: camera icon. Quick action buttons: plus / home / user / search.
* Related-list small avatars: user / home / search depending on context.

= 1.1704 =
* Minimal CRM style: refined icon palette to match a modern real-estate office aesthetic. All flat single-grey icons replaced with a coherent set of soft pastels mapped by domain:
* Sage (homes/growth) for Nieruchomosci and Properties thumbnails, dimensions and features cards, "Opublikowane" status, completed stages.
* Sky (trust/calm) for Klienci avatars, location card, primary actions, hero eyebrow, "W trakcie" pills.
* Sand (paper/legal) for Umowy thumbnails, agreement-card icons, condition card, tags.
* Lilac (discovery) for Poszukiwania thumbnails, media card, export card, mini portal pills, "Bezterminowa" badge.
* Clay (warm) for owner avatars and history-timeline icon.
* Rose (alerts) for "Sprzedane" status pill.
* Stage timeline, status pills, side-summary progress, dashboard bars and rank positions now share the same pastel system. Buttons in form footers and form section accents tuned to the sky pastel.

= 1.1703 =
* Settings -> Developer access: new "Styl CRM (frontend)" selector visible only when developer access is active. Two options: "Domyslny (Modern)" Ă˘â‚¬â€ť the existing rich style, and "Minimalistyczny" Ă˘â‚¬â€ť a new clean/flat style.
* New file: assets/css/frontend-minimal.css. Layered on top of frontend.css; neutralizes gradients/colored accents/shadows, single neutral accent (--eocrm-primary), flat cards, refined tables and pills.
* The selected style is auto-injected globally via wp_head priority 999 and wp_footer priority 1 (covers shortcode-driven late enqueues). Reverts to default when developer access is disabled.
* New helper EstateOfficeCRM_Plugin::get_active_crm_style() returns "default" or "minimal" depending on dev access + saved preference.

= 1.1702 =
* UI: replaced the old transaction emoji icon with a modern inline SVG icon.
* Transaction icon updated in the transactions list, empty state, transaction profile, agreement related transactions card and the agreement-close transaction modal.
* Added dedicated CSS sizing and color treatments so the icon matches the CRM visual style.

= 1.1701 =
* Transaction profile: fixed an undefined users table variable used when reading cooperating agent data.
* Transaction profile: fixed the "Nie znaleziono profilu transakcji" state after creating a transaction.
* Transaction redirects now prefer the main CRM page with the Transakcje tab instead of the separate "Transakcje CRM" page, so the page title stays unchanged.

= 1.1700 =
* Transactions: added editing for saved transactions.
* Transactions: administrators can now delete transactions.
* Transactions: cooperation with another agent now supports "Biuro wlasne" with CRM agent selection and "Inne biuro" with external office/agent fields.
* CRM Dashboard: administrators and managers now see per-agent gross remuneration breakdowns while Agent users continue to see only their own remuneration.
* Database: extended the transactions table with cooperation metadata fields.

= 1.1699 =
* Transactions: the transaction form now allows users to adjust the commission amount and commission unit before saving.
* CRM Dashboard: added a "Wynagrodzenie brutto" panel with tabs for the current month, previous month and two months ago.
* Gross remuneration is calculated from saved transactions by transaction date and respects Agent, Manager and Administrator visibility scopes.

= 1.1698 =
* Agreement profile: added a local "Dodaj transakcje" button in the related transactions card.
* Agreement stage update: replaced the browser confirm with a CRM-styled modal when choosing "Umowa zakonczona".
* The modal lets users either create a transaction or save only the agreement stage.

= 1.1697 =
* Added a new CRM "Transakcje" section with transaction list, transaction profile and an agreement-linked transaction form.
* Agreement profiles now include a "Dodaj transakcje" button and a related transactions panel.
* Changing an agreement stage to "Umowa zakonczona" can redirect to the transaction form after user confirmation.
* Transaction profiles calculate remuneration from the transaction price and agreement commission.

= 1.1696 =
* CRM Dashboard: regular Agent users now see the "Najaktywniejsi w biurze" panel.
* CRM Dashboard: Agent and Manager rankings include all users assigned to the same office, including users with 0 active agreements.
* CRM lists: the "Opiekun" column remains hidden for regular Agent users and visible only for Administrators and Managers.

= 1.1695 =
* CRM lists and record profiles now use the Agent profile photo from Estate Office CRM instead of the default WordPress avatar.
* The "Opiekun" column is hidden for regular Agent users and remains visible for Administrators and Managers.

= 1.1694 =
* CRM property form: gallery photos and floor plans are preserved after validation errors.
* CRM property form: draft media previews are rebuilt after a failed save, instead of clearing hidden media fields.

= 1.1693 =
* CRM Properties list: swapped the location display order so street/building/apartment is the primary line and city/district is the secondary line.

= 1.1692 =
* CRM Properties list: location now always includes street with building number and apartment/unit number when available.
* CRM Properties, Searches and Agreements lists: added an "Aktywne" filter.
* The "Aktywne" filter means an active, not-finished related agreement for properties/searches and a not-finished agreement for agreements.

= 1.1691 =
* Agreement form (new and edit): the "Dodaj nowego klienta do tej umowy" checkbox now renders as a pretty pill-toggle button (matching the "Adres korespondencyjny taki sam" toggle in the client edit form). Achieved by switching the wrapping `<p class="eocrm-form-field">` to `<p class="eocrm-form-field-checkbox">` so the existing pill-toggle CSS applies.

= 1.1690 =
* Clients listing: added new "Nieruch." column showing the count of properties linked to a client (via active agreements). list_clients() now computes properties_count via DISTINCT subquery on agreement_clients -> agreements -> properties.
* Modern styling for all CRUD forms (Add/Edit Umowy, Klienci, Nieruchomosci, Poszukiwania) without template changes (CSS-only via :has() targeting).
* Form card hero header: gradient background, blue/purple accent stripe on top, clean H3 typography.
* Section headings (H4) styled as uppercase labels with vertical accent bar and soft underline.
* Refined form-field labels (uppercase, letter-spacing, color), readonly inputs with muted background.
* Submit area separated by top border with bigger primary button (44px, gradient, lift on hover).
* Helper paragraphs styled as info blocks with left accent.
* Nested "new client" card inside agreement form gets soft blue gradient background.

= 1.1689 =
* Modern Clients (Klienci) listing matching Properties/Searches/Agreements: toolbar with icon-prefixed search and 2 type filters (Os. fizyczna / Firma), results meta with breakdown counts, modern 7-column table.
* Listing rows: client avatar (initial for individuals, building icon for companies), name + subtitle (Osoba fizyczna/Firma), address with postal code, clickable phone (tel:) and email (mailto:), agreements count, searches count, owner avatar, client-type pill.
* Client profile rebuilt with hero (large 80px avatar, status pill, hero meta with clickable phone+email+address, actions Powrot/Edytuj/Usun).
* Four KPI quick stats: Umowy (with active count), Nieruchomosci, Poszukiwania, Opiekun (avatar + email).
* Themed sub-cards: Dane podstawowe (PESEL/dokument or NIP/KRS/REGON depending on type), Kontakt (telefon/email/WWW), Identyfikacja (opiekun/utworzono/aktualizacja).
* Adresy klienta Ă˘â‚¬â€ť 2-card layout (gl&oacute;wny + korespondencyjny).
* Powiazane umowy Ă˘â‚¬â€ť agreement cards with document icon, number, transaction badge, stage pill, signing date.
* Powiazane nieruchomosci + Powiazane poszukiwania Ă˘â‚¬â€ť 2-column related cards with colored icons.
* list_clients() SQL extended with website, postal_code, created_at, updated_at, agreements_count, searches_count via subqueries.
* New client filter bound in JS (data-eocrm-client-filter) for live type filtering.

= 1.1688 =
* Fix: "Szczegoly umowy" and "Historia etapow" now sit side-by-side in the 2/3 + 1/3 ratio (previously they stacked vertically because the .eocrm-prop-detail-card--full modifier was forcing grid-column: 1 / -1). Explicit grid-column overrides added for cards inside .eocrm-agreement-details-row.

= 1.1687 =
* Agreement profile layout reshuffle: "Szczegoly umowy" and "Historia etapow" moved directly under the "Etapy umowy" timeline.
* Two-column 2/3 + 1/3 row: details (2/3) and history (1/3) sit side-by-side; history scrolls internally if long, max-height ~520px, capped to keep symmetry with details.
* Below the new row: KPI quick stats + main 2/3+1/3 row (Klienci/Nieruchomosci/Poszukiwania | Stage update form / Owner / Podsumowanie) unchanged.

= 1.1686 =
* Modern Agreements (Umowy) listing matching Properties/Searches: toolbar with icon-prefixed search and 4 transaction filters (Sprzedaz / Kupno / Wynajem / Najem), results meta, modern 7-column table.
* Listing rows: agreement number + transaction subtitle (with document icon), linked property address (clickable), date signed, valid-until (or "Bezterminowa" badge), current stage pill, owner avatar + name, status icon (active/finished).
* Agreement profile rebuilt with hero (status pill "W trakcie realizacji" / "Zakonczona", actions Powrot/Edytuj/Kopiuj/Usun), stage timeline pulled from agreement settings, four KPI quick stats (Daty / Postep / Prowizja / Opiekun).
* Two-column main row (2/3 + 1/3): main col with Klienci grid (avatar cards), Powiazane nieruchomosci (cards with thumbnails), Powiazane poszukiwania, Szczegoly umowy (2-col defs), Historia etapow vertical timeline; sidebar with Stage update form, Owner card, Podsumowanie summary panel with progress and counts.
* list_agreements() SQL extended with is_indefinite, is_exclusive, commission_amount, commission_unit, created_at, updated_at; agreement_profile_properties enriched with primary_photo_url for thumbnails.

= 1.1685 =
* Modern Searches (Poszukiwania) listing matching the Properties listing pattern: unified toolbar with icon-prefixed search + KUPNO / NAJEM pill filters, results meta, modern 8-column table.
* Rows include: search number + property type/transaction subtitle (with magnifier icon), location, formatted budget range, area range (m2), rooms range, owner avatar + name, status icon (linked-agreement active/inactive), updated_at date.
* Search profile rebuilt with hero header (status pill + actions), stage timeline pulled from the linked agreement, four KPI quick-stat tiles (Budzet / Metraz / Pokoje / Opiekun).
* Themed sub-cards: Kryteria podstawowe / Lokalizacja / Stan i wykonczenie / Media i instalacje / Cechy i atuty / Powierzchnie dodatkowe / Identyfikacja, plus full-width Opis card and Related (Klienci / Umowa) cards.
* list_searches() SQL extended with area, rooms, floor ranges, agreement stage join, owner display name, created_at, updated_at.

= 1.1684 =
* Property profile layout reshuffle: hero photo now takes 2/3 of width, with Szybkie znaczniki promoted to a 1/3 sidebar next to it.
* Thumbnail strip moved from below the hero photo to a dedicated "Galeria zdjec" card placed directly above Rzuty.
* Old standalone Quick flags card at the bottom removed (no duplication).
* Side flags card scrolls internally if many flags, with the submit button pinned to the bottom.

= 1.1683 =
* Property profile: fixed cropping of the current stage indicator (added top padding so the pulsing ring is no longer clipped).
* Property profile detail card replaced with hero gallery + 6 themed sub-cards (Lokalizacja / Powierzchnia i pomieszczenia / Stan i wykonczenie / Media i instalacje / Cechy i atuty / Eksport i kanaly).
* Hero gallery shows the primary photo large with overlay counter "N zdjec" + thumbnail strip below.
* Description, Floor plans, Related (Klienci / Umowa / Tagi), and Quick flags each get their own card.
* Each themed card has a colored top accent bar, an icon, uppercase labels and bigger value typography for clearer scanning.
* Empty states for missing photos / clients / agreement.

= 1.1682 =
* Modern property profile page with hero header, agreement-pulled stage timeline, and quick-stat KPI tiles.
* Hero shows offer number + status pill, property type, transaction type, address, owner; action buttons (Powrot, Edytuj, Kopiuj, PDF, Usun) moved to hero top-right.
* Stage timeline reads stages from the linked agreement transaction type (per-tenant settings respected) and marks stages as Completed (green tick) / Current (blue, pulsing) / Upcoming (gray); shows progress percentage and bar.
* Stage dates pulled from agreement_stages history table when present.
* Quick stats row: Cena (with cena/m2), Metraz (with floor sub-info), Pokoje (with bedrooms sub), Opiekun (avatar + export status).
* Existing detail dl-defs, description, gallery, floor plans, and quick-flag toggles preserved underneath the new hero/timeline.
* Empty-state shown when property has no linked agreement.

= 1.1681 =
* Properties listing: removed dedicated WWW column.
* Status column now contains 3 globe-style circular icons matching the previous logic: Eksport WWW, Eksport na Portale, Umowa (aktywna/nieaktywna).
* Inactive states use the same diagonal-slash treatment for visual consistency across all three indicators.
* Removed legacy colored status pill and U/P mini-pills; the 3 icons replace them entirely.

= 1.1680 =
* Modern Properties listing: unified toolbar with icon-prefixed search + pill filter group on a single card.
* Property rows now show a 64x48 thumbnail (primary photo) with subtle hover lift.
* Two-line main column: offer number + property type / transaction type subtitle.
* Dedicated Location column (city + district), Price column with price/m2 sub-line, separate metric columns for area and rooms (with floor sub-info when available).
* Owner column displays avatar (Gravatar/initial fallback) next to display name.
* Status pill: Aktywna / Sprzedane / Wynajete / Premium / Wylacznosc with color coding; mini pills for active agreement and portal export.
* Dedicated WWW publication indicator (green globe = on, gray = off).
* Aktualizacja column with date and time of last update.
* "Znaleziono N nieruchomosci" results meta line; refined empty state.
* Existing JS hooks preserved (live search, transaction/export filters).
* Role scoping unchanged: Agent sees own, Manager sees office, Admin sees all.

= 1.1679 =
* Modern role-aware frontend dashboard: separate views and copy for Agent, Manager, and Administrator.
* Hero panel with personalized greeting, role badge, and quick actions (new agreement, property, client, search).
* Four KPI tiles with icons, accent stripe, and contextual sub-labels per role.
* New "Najnowsze umowy" panel with stage pill and signing date.
* New "Najnowsze nieruchomosci" panel with price and publication status.
* Transaction breakdown bars (sale/purchase/rent) and offer export breakdown (WWW / portals / both / none).
* Agent ranking with progress bars (Manager/Admin only); Agent dashboard shows recent searches instead.
* Fully responsive layout, role-scoped data via existing owner-scope clause.

= 1.1678 =
* Modernized CSS for both frontend CRM and WordPress admin: refined design tokens, typography, shadows, focus rings.
* Forms: unified input height/padding, modern focus state, custom-styled selects, better labels and helper text spacing.
* Tables: subtle row hover, refined headers with uppercase labels, soft separators.
* Buttons: refined primary gradient, lift on hover, accessible focus rings, segmented choice toggle as pill group.
* Cards, tabs, modals, login panel and offer cards: softer radii, layered shadows, smooth hover transitions.
* Respects prefers-reduced-motion and prevents iOS zoom on small screens.

= 1.1677 =
* Added the `[eocrm_agent_login]` shortcode with a public login panel for agents, managers and administrators.
* The installer now creates the "Panel logowania Agenta" page and keeps its shortcode up to date.
* Added an Estate Office CRM admin submenu shortcut for the agent login panel.
* Added a quick link to the public login panel in the CRM admin dashboard.

= 1.1676 =
* License screen: added domain unregister action for moving a license to another site.
* License screen: clears the local key after successful unregister to prevent automatic reactivation.
* License screen: refreshed admin styling to match the CRM admin panels.

= 1.1675 =
* Settings: removed the explanatory developer-access text under the developer-access toggle.
* About CRM: replaced the empty planned-features placeholder with planned items for portal export and MLS WSPON import.

= 1.1674 =
* Settings: added a developer-access toggle in General settings with server-side password validation.
* Settings: added the developer-only Import MLS tab.
* Portal export: OtoDom/OLX settings, callbacks and queued exports are available only when developer access is enabled.

= 1.1673 =
* Front-end CRM Searches: added transaction filters for KUPNO and NAJEM.
* Front-end CRM Agreements: added transaction filters for SPRZEDAZ, KUPNO, WYNAJEM and NAJEM.
* The new transaction filters work together with the existing live text search and do not affect property filters.

= 1.1672 =
* Rollback: removed the failed central development-features integration.
* About CRM planned features are back to the local placeholder/filter mechanism.
* License SDK no longer calls the development-features endpoint.

= 1.1670 =
* CRM Dashboard - Szybkie Przejscia (front-end CRM): hotfix for broken links under WordPress "Plain" permalinks.
* Front-end URLs are now resolved via `get_permalink()` against the page IDs stored in the `eocrm_page_ids` option, with a `get_page_by_path()` fallback by slug (`crm`, `crm-nieruchomosci`, `crm-poszukiwania`, `crm-klienci`, `crm-umowy`).
* Result: links work correctly regardless of the site's Permalinks setting (Plain, Day name, Month name, Numeric, Post name, Custom).
* If a CRM page does not exist (manually deleted by the user), the corresponding button is silently omitted instead of generating a dead URL.
* Added private helper `EstateOfficeCRM_Admin::resolve_crm_frontend_url()` reused for all 5 front-end quick links.

= 1.1669 =
* CRM Dashboard (admin Pulpit) - Szybkie Przejscia: added 5 new buttons at the very top linking to the public CRM front-end (Panel CRM, Nieruchomosci, Poszukiwania, Klienci, Umowy).
* Front-end buttons open in a new tab (`target="_blank"`) so the admin context in wp-admin is preserved, and use the `dashicons-external` chevron to make the new-tab behavior visible.
* Buttons are split into two visual groups via modifier classes `eocrm-dashboard-quick-link--frontend` and `eocrm-dashboard-quick-link--admin`, ready for further per-group styling without touching the markup.

= 1.1668 =
* Plugins screen (Wtyczki): EULA and Subscription Terms (Regulamin abonamentu) plugin-row links now point to the canonical pages on estateofficecrm.pl (`?page_id=205` for EULA, `?page_id=204` for Regulamin).
* Plugins screen: removed the duplicate WordPress-generated "Visit plugin site" link, since the custom "Estate Office CRM" link already opens the plugin website.
* About CRM screen: replaced the EULA pill URL with the canonical EULA page and added a new Regulamin abonamentu pill linking to `?page_id=204`.
* CRM Dashboard (admin Pulpit): the "Szybkie przejscia" block has moved to the very top of the dashboard and is now rendered as a modern button grid (icon + label + description + chevron, hover/focus states).
* CRM Dashboard: the agent KPI section keeps the dashboard hint, the old links list has been removed in favor of the top buttons.

= 1.1667 =
* Removed the current prerelease label from the CRM about/status screen.
* Updated the working features list and removed licensing from that list.
* Added a planned features section prepared for data from the licensing plugin.
* Added EULA link in the CRM about screen.
* Updated plugin author metadata and added plugin-row links to Estate Office CRM, subscription terms, and EULA.

= 1.1666 =
* Added button-style filters to the CRM Properties table.
* Filters include sale, rent, WWW export, portal export, and sold/rented status.
* CRM property filters work client-side and combine with the existing live text search.

= 1.1665 =
* Added an MLS ribbon for public property listings and single offer pages.
* Moved Premium and MLS ribbons to the left side of property photos.
* Added a configurable New Offer duration setting in General settings.
* Expired New Offer status now clears both the active flag and the `nowa_oferta` tag during daily cleanup.
* Restyled CRM form checkboxes and radio choices as button-like toggles while preserving existing save logic.

= 1.1664 =
* Added diagonal public-offer ribbons for `wynajete`, `nowa_oferta`, and `premium` tags.
* Ribbons now use separate colors and stack safely when multiple status tags are active.
* Added quick CRM property marker controls on the property profile page, under gallery and floor plans.
* Quick controls allow updating offer tags plus WWW and portal export flags without opening the full edit form.

= 1.1663 =
* Added a sold status ribbon for public property listings and single offer pages.
* When the `sprzedane` tag is selected, the main offer photo displays a diagonal red `SPRZEDANE` ribbon in the upper-right corner.
* The ribbon is supported by all current single offer templates.

= 1.1662 =
* Mobile offer layout emergency hotfix:
* restored visible gallery width on phones after the previous ordering fix,
* forced mobile offer sections to stretch to full available width,
* kept the intended order: gallery, price/basic information, agent, remaining content, contact form,
* fixed remaining Polish diacritics in offer contact notices.

= 1.1661 =
* Mobile offer layout correction:
* forced the gallery to stay above the price/basic information block on phones,
* moved the agent card directly under the price/basic information block for `v2`, `v3`, `v5`, and `v6`,
* added an explicit mobile ordering safeguard for the default and thumbnail offer templates,
* fixed Polish diacritics in offer and mortgage contact messages.

= 1.1660 =
* Mobile offer layout hotfix:
* adjusted responsive order for property offer templates `v2`, `v3`, `v5`, and `v6`,
* on phones the gallery is now followed immediately by the summary block (price + key info) and the agent card,
* the remaining offer content is pushed below these priority blocks for a clearer first-screen mobile layout.

= 1.1659 =
* OtoDom/OLX compliance update:
* added `Tryb testowego konta OtoDom` option that auto-applies the required `[qatest-mercury]` title prefix and test description,
* simplified publishing flow to the primary Polish `site_urn` (`urn:site:otodompl`) and repurposed the OLX toggle for additional OLX content validation rules,
* clarified in settings that OtoDom test account credentials are entered on the OtoDom login screen during OAuth, not inside CRM settings,
* added OtoDom location mapping with `city_id`, optional `district_id`, and `street_name` in `location.custom_fields`,
* after successful publish/update CRM now fetches advert metadata (`GET /advert/v1/<uuid>/meta`) and stores advert state locally,
* webhook notifications now update the stored advert state by matching `object_id` to the property reference,
* failed `POST/PUT/DELETE` requests now store the last sync error for later diagnostics,
* successful `DELETE` keeps sync history while clearing `advert_uuid` to avoid repeated delete attempts.

= 1.1658 =
* OtoDom + OLX.pl settings hotfix:
* added dedicated `Partner URN` field for values like `urn:partner:...`,
* clarified that `Site URN` should remain site-specific values such as `urn:site:otodompl` and `urn:site:olxpl`,
* authorization button now requires saved `Client ID`, `Client Secret`, and `API Key`,
* improved OAuth/token error message to list exactly which API credentials are missing.

= 1.1657 =
* Added new portal export provider: `OtoDom + OLX.pl (OLX Group API)` in `Ustawienia -> Eksport na portale`.
* Added provider-specific settings: Client ID, Client Secret, API Key, Notification Secret, auth host/locale, site URN for OtoDom and optional OLX parallel export.
* Added generated callback endpoints visible in settings:
* `Authentication callback URL` => `?eocrm_portal_auth=otodom_olx`
* `Notification callback URL` => `?eocrm_portal_notify=otodom_olx`
* Implemented OAuth callback flow with token persistence and queue export processing to `https://api.olxgroup.com/advert/v1`.
* Implemented webhook endpoint with `x-signature` HMAC validation (`sha1(object_id,transaction_id,notification_secret)`).
* Added per-property external advert UUID tracking for update/delete sync on each configured `site_urn`.
* Security/stability hardening:
* strict `state` validation before token exchange,
* safer warning suppression helper signature,
* normalized mapping for property/transaction enums (including accented variants).

= 1.1656 =
* CRM -> Nieruchomosci (lista): added new `Status` column with compact icons:
* `W` - Eksport WWW (green/red),
* `P` - Eksport na Portale (green/red),
* `U` - Umowa (green active, red finished/no linked agreement).
* Extended properties listing query with export flags and agreement stage/activity status.
* Added frontend CSS for status icon pills and spacing in table cell.

= 1.1655 =
* CRM Dashboard: `Aktywne umowy` now excludes agreements in final stage (`Umowa zakonczona` / `Umowa zakoĂ„Ä…Ă˘â‚¬Ĺľczona`).
* CRM Dashboard: property metric changed to `Opublikowane nieruchomosci` and now counts only `export_www = 1` excluding `Sprzedane/Wynajete`.
* CRM Dashboard: `Poszukiwania` counter now excludes searches linked to agreements with final stage.
* Settings -> Ogolne: added retention settings for sold/rented exports:
* `Eksport WWW - liczba dni po oznaczeniu Sprzedane/Wynajete`
* `Eksport na Portale - liczba dni po oznaczeniu Sprzedane/Wynajete`
* Daily cleanup now auto-disables `export_www` / `export_portals` for sold/rented properties after configured retention period; portal cleanup queues delete events.
* Public offers queries now respect WWW retention for sold/rented properties.

= 1.1534 =
* CRM Property profile: media fields now read from the correct source (`media` JSON), including heating, water, sewage, gas and electricity.
* CRM Property profile: added visibility for filled media attributes (furnished state, attic, multi-level), while still hiding empty values.
* CRM Property copy mode: ensured full editor/media/maps assets are loaded in `copy-property` mode (same UX as create/edit form).

= 1.1533 =
* CRM Properties list: address column now includes apartment number.
* CRM Properties list search now includes apartment number field.
* Property profile card now shows only filled fields (empty values are hidden).
* Property profile card now includes missing fields like floor number and total floors when available.
* Added admin-only copy action for Property profile (`Kopiuj`) with prefilled form and default offer number.
* Added admin-only copy action for Agreement profile (`Kopiuj`) with prefilled form and default agreement number by transaction type.

= 1.1532 =
* Security hotfix after Plugin Check report:
* Refactored portal export property query to remove dynamic WHERE fragment and use explicit prepared variants.
* Added targeted PHPCS `UnescapedDBParameter` ignore annotations only for internally controlled SQL (prepared statements and plugin-owned table maps).
* Hardened SQL-related scan findings in:
* `includes/class-estate-office-crm-admin.php`
* `includes/class-estate-office-crm-agreements.php`
* `includes/class-estate-office-crm-pages.php`
* `includes/class-estate-office-crm-portal-export.php`
* `includes/class-estate-office-crm-searches.php`

= 1.1531 =
* Fixed agreement client live search in forms (missing JS normalization function).
* Improved agreement client search index (name/company/phone/e-mail/address fields).
* Updated "Offices and Agents" ordering: manager is now pinned to top within each office.
* Added manager badge label in agent card: "MenedĂ„Ä…Ă„Ëťer Biura".

= 1.1530 =
* Added office-scoped manager access foundation (`Menedzer`) with role support in user/agent management and runtime capabilities.
* Added manager-aware data scope for CRM ownership checks and dashboard aggregates (office-level scope).
* Improved agreements form UX: client list now appears only when searching (instead of rendering full list by default).
* Added offer list pagination summary (`Strona X z Y`) and direct link to the last page.
* Added setting to open property offers from list/map in a new browser tab (`Ustawienia -> Szablony Ofert`).
* Added frontend CSS polish for new pagination elements.
* Fixed client ownership when adding a new client during agreement edit, so ownership follows agreement owner (important for manager editing office records).

= 1.1529 =
* Hotfix: fixed missing Polish diacritics in the mortgage calculator and advisor contact form.
* Updated calculator labels/messages (including WIBOR source line) across all public offer templates.
* Updated mortgage advisor form validation and e-mail texts to proper Polish characters.

= 1.1528 =
* Removed visible frame around mortgage advisor logo under calculator (no border, no shadow).

= 1.1527 =
* Added mortgage advisor logo formatting settings: max logo height (%) and horizontal alignment (left/center/right).
* Applied new settings on public offer page under mortgage calculator, with fixed vertical centering.
* Improved advisor logo rendering so it no longer scales too large relative to the contact form.

= 1.1526 =
* Fixed mortgage advisor logo rendering under calculator: full logo is now visible (contain), without square crop.
* Adjusted advisor section vertical alignment so logo height follows form section height instead of overflowing it.

= 1.1525 =
* Updated mortgage advisor block layout ratio to 60/40 (logo/form).
* Removed strict logo max-width cap to respect the new column proportion.

= 1.1524 =
* Fixed advisor block placement: moved it from multimedia section to render directly under mortgage calculator.
* Ensured advisor section appears in all public offer templates (v1-v6) when enabled.

= 1.1523 =
* Hotfix: fixed rendering of the mortgage advisor block under calculator (image + heading + form) on sale offers.
* Added settings toggle (YES/NO) to enable or disable advisor block under mortgage calculator.
* Refactored advisor block rendering to backend-generated HTML for better reliability.

= 1.1522 =
* Added a new mortgage advisor contact block under the mortgage calculator on sale offers.
* Added a dedicated settings tab for mortgage calculator contact (advisor image/logo + notification e-mail).
* Added calculator value export to contact form e-mail payload (offer number, URL, and calculated values).
* Improved mortgage calculator responsive typography to reduce wrapping on narrower screens.

= 1.1521 =
* Added 3 new public offer templates: Modern v4, Modern v5, Modern v6.
* Added 3 gallery slider styles for the new templates (thumbnails, vertical numbered controls, autoplay progress).
* Extended template settings selector with v4-v6 previews and labels.
* Extended frontend template resolver to support new variants.

= 1.1520 =
* Check Plugin hardening pass:
* Unified text domain in licensing SDK.
* Improved sanitization of request method and nonce handling in SDK/admin.
* Replaced direct unlink cleanup paths with a safe wrapper using `wp_delete_file`.
* Added WordPress.org-compatible `readme.txt`.



