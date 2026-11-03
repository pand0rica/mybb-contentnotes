# Content Notes – Implementation Plan

**Umsetzung:** folge `<Dev-Kit>/workflows/2-implement.md`. Schritte der Reihe
nach. Bei Unklarheit gezielt nachfragen, aber nicht umplanen. Alle Details (Tabellen, Hooks,
Template-Anker, Settings) stehen in `PROJECT_CONTEXT.md` – das ist die verbindliche Spezifikation.

Kit-Pfade unten sind relativ zu `<Dev-Kit>/`.

---

## Schritt 1 – Grundgerüst & Zugriffsschutz
- [ ] **Ziel:** Hauptdatei mit Zugriffsschutz, `contentnotes_info()` (Codename `contentnotes`,
      Version 1.0.0, Autor\*in pand0rica, `compatibility` `18*`), `require_once` der fünf Module aus
      `inc/plugins/contentnotes/`, Registrierung **aller** Hooks aus `PROJECT_CONTEXT.md` § Hooks
      (Handler zunächst als leere Stubs in den jeweiligen Modulen anlegen, damit nichts fatal endet).
      Frontend-Hooks nur registrieren, wenn nicht `IN_ADMINCP`; ACP-Hooks nur im ACP.
- **Dateien:** `inc/plugins/contentnotes.php`, `inc/plugins/contentnotes/{database,forms,display,parser,usercp}.php`
- **Vorlage:** `standards/boilerplate.md` §1, §2 · Naming `standards/conventions.md` §3
- **Abnahme:** `php -l` auf alle Dateien sauber; Plugin erscheint in der ACP-Pluginliste.

## Schritt 2 – Lifecycle: install / is_installed / uninstall
- [ ] **Ziel:** Die drei Tabellen exakt nach `PROJECT_CONTEXT.md` § Datenbank-Tabellen anlegen
      (inkl. UNIQUE-/Index-Keys), settinggroup `contentnotes` + 4 Settings anlegen,
      leeren Datacache `contentnotes_categories` aufbauen. `is_installed()` prüft die Kategorien-
      Tabelle. `uninstall()` droppt alle drei Tabellen, löscht Settings + settinggroup,
      ruft `rebuild_settings()`, löscht den Datacache.
- **Dateien:** `inc/plugins/contentnotes.php`
- **Vorlage:** `standards/boilerplate.md` §3, §4 · `standards/conventions.md` §4 ·
  `standards/debugging.md` Punkte 9, 21
- **Abnahme:** CLI-Lifecycle `install` legt Tabellen + Settings an (`standards/testing.md` §3).

## Schritt 3 – activate / deactivate: Templates, Stylesheet, Core-Template-Injection
- [ ] **Ziel:**
  - Alle `.tpl` aus `inc/plugins/contentnotes/templates/` als `contentnotes_*` (sid -2) laden
    (Template-Liste: `PROJECT_CONTEXT.md` § Eigene Templates; Inhalte zunächst minimal, werden in
    Schritt 5–8 ausgebaut).
  - Stylesheet `contentnotes.css` aus `inc/plugins/contentnotes/assets/` in alle Themes
    (Basis-Styles: `.contentnotes_badge`, `.contentnotes_banner`, `.contentnotes_veil`,
    `.contentnotes_js .contentnotes_veiled{display:none}`, `.contentnotes_inline`,
    `.contentnotes_inline_label`, `.contentnotes_inline_text{filter:blur(4px)}`,
    `.contentnotes_inline.is-revealed .contentnotes_inline_text{filter:none}`,
    Formular-Fieldset). Farben neutral, nur Theme-Variablen/`inherit`; das Inline-Chip mit
    `display:inline`, `white-space:normal`, kleiner Schrift – kein Block, kein Umbruch.
  - Core-Templates per `find_replace_templatesets()` exakt nach `PROJECT_CONTEXT.md`
    § Template-Injection anpassen (Guard gegen Doppel-Injection).
  - `deactivate()`: exaktes Spiegelbild (Templates `contentnotes_%` löschen, Stylesheet aus allen
    Themes + Cache-Datei entfernen, alle Injektionen per Gegen-Regex zurückbauen).
    **Keine** Settings hier anfassen.
- **Dateien:** `inc/plugins/contentnotes.php`, `inc/plugins/contentnotes/templates/*.tpl`,
  `inc/plugins/contentnotes/assets/contentnotes.css`
- **Vorlage:** `standards/boilerplate.md` §5, §6, §8, §9 · `standards/debugging.md` Punkte 8, 10,
  17, 18, 21
- **Abnahme:** CLI `install`→`activate`→`deactivate`→`uninstall`: alle Zähler 0, Core-Templates
  byte-gleich zum Ausgangszustand (Diff des `templates`-Inhalts vorher/nachher).

## Schritt 4 – DB-Layer & Kategorie-Cache
- [ ] **Ziel:** CRUD-Funktionen mit Präfix `contentnotes_db_`:
  - Kategorien: `get_categories(bool $active_only)` (aus Datacache), `get_category(int)`,
    `get_category_by_slug(string)`, `insert_category`, `update_category`, `delete_category`
    (löscht zugehörige Notes + Prefs), `slug_exists(string, int $exclude_cid)`,
    `rebuild_cache()`.
  - Notes: `get_notes(string $type, array $refids)` ⇒ `[refid => [cid => note]]`,
    `get_post_notes_for_thread(int $tid)` (Join über `posts.tid`), `replace_notes(string $type,
    int $refid, array $cid_to_note)` (delete + insert), `add_notes(...)` (für Merge, ohne
    Löschen, Duplikate per UNIQUE ignorieren), `delete_notes(string $type, array $refids)`.
  - Prefs: `get_user_prefs(int $uid)` ⇒ `[cid => mode]`, `save_user_prefs(int $uid, array)`,
    `delete_user_prefs(array $uids)`.
  - Öffentliche API `contentnotes_get_notes()` und `contentnotes_viewer_wants_warning(array $cids)`
    (Gast ⇒ immer true; Mitglied ⇒ true, sobald eine cid keinen Eintrag oder Modus 0 hat;
    Prefs pro Request statisch cachen).
  - `datahandler_user_delete_start`-Handler: Prefs der uids löschen.
  - Typ-Whitelist `type ∈ {thread, post}` in jeder Funktion; alle IDs `(int)`, Strings
    `escape_string`; `simple_select` mit mehreren Zeilen immer per `while`.
- **Dateien:** `inc/plugins/contentnotes/database.php`
- **Vorlage:** `standards/conventions.md` §3, §4, §6 · `standards/debugging.md` Punkte 6, 7, 14
- **Abnahme:** CLI-Selbsttest (`standards/testing.md` §4): Kategorie anlegen/ändern/löschen,
  Notes ersetzen/hinzufügen/löschen, Prefs-Auswertung inkl. Default „Warnung".

## Schritt 5 – Formulare, Validierung, Speichern, Aufräumen
- [ ] **Ziel:**
  - Rechte-Helper `contentnotes_user_can_set(int $fid): bool` (Setting enabled, `is_member()`
    gegen `contentnotes_groups`, Forum in `contentnotes_forums` oder `-1`).
  - Formular-Builder `contentnotes_build_form(string $type, array $selected, string $tpl)`:
    aufklappbares Fieldset, je **aktive** Kategorie eine Zeile (Checkbox + Textfeld
    `maxlength="255"`), plus verstecktes Marker-Feld `contentnotes_form=1`. Bereits gesetzte, aber
    inzwischen inaktive Kategorien beim Bearbeiten trotzdem als Zeile anzeigen (damit sie
    entfernt werden können).
  - Vorbelegung: bei Vorschau/Fehler aus `get_input('contentnotes', MyBB::INPUT_ARRAY)`, beim
    Bearbeiten aus der DB.
  - Hooks `newthread_start` (type thread), `newreply_start` (post), `editpost_end` (erster
    Post ⇒ thread mit `tid`, sonst post mit `pid`), `showthread_start` ⇒
    `$contentnotes_quickreply` (post, kompakte Variante).
  - Validierung (`datahandler_post_validate_thread/_post`): nur wenn Marker gesetzt und Recht
    vorhanden; cids müssen existieren (aktiv – oder beim Bearbeiten bereits gesetzt), Freitext
    `my_strlen() ≤ 255`, sonst `$handler->set_error('contentnotes_note_too_long')`.
  - Speichern über die Datahandler-Hooks laut `PROJECT_CONTEXT.md` § Hooks
    (`insert_thread_end`, `insert_post_end`, `insert_merge`, `update_end`). Ohne Marker/Recht
    **nichts** ändern. Nach jedem Speichern `$plugins->run_hooks('contentnotes_notes_saved', $args)`.
  - Aufräum-Hooks: Thread-Löschung (pids in `_start` statisch merken), Post-Löschung.
- **Dateien:** `inc/plugins/contentnotes/forms.php`, `templates/contentnotes_form.tpl`,
  `templates/contentnotes_form_row.tpl`, `templates/contentnotes_quickreply.tpl`
- **Vorlage:** `standards/conventions.md` §5, §6 · `standards/debugging.md` Punkte 2, 16
- **Abnahme:** curl (`standards/testing.md` §5): neuer Thread mit 2 Kategorien + Freitext ⇒ 2 Zeilen
  `type=thread`; Antwort ⇒ `type=post`; Bearbeiten ohne Recht lässt Notes unverändert;
  Thread löschen ⇒ alle zugehörigen Notes weg. Freitext mit `<script>` wird beim Rendern escaped.

## Schritt 6 – Anzeige: Badge, Thread-Banner, Post-Veil, JS
- [ ] **Ziel:**
  - `contentnotes_handle_headerinclude` (`global_intermediate`, nur auf `newthread.php`,
    `newreply.php`, `editpost.php`, `showthread.php`, `forumdisplay.php`, `search.php`):
    `<script>` mit `var contentnotes_config = {categories:[{slug,name}], canSet:bool,
    editorButton:bool, lang:{…}}` (per `json_encode`, nur aktive Kategorien) +
    `<script src="{$mybb->asset_url}/jscripts/contentnotes.js?ver=100">`.
  - Badge (`forumdisplay_thread`, `search_results_thread`): Notes aller tids der Seite einmalig
    laden (beim ersten Aufruf tids aus `$threadcache` bzw. dem Suchergebnis sammeln), Template
    `contentnotes_badge` mit Kategorienamen (escaped).
  - Thread (`showthread_start`): Notes laden; wenn vorhanden ⇒ `$contentnotes_thread_banner`
    (Template `contentnotes_thread_banner` mit `contentnotes_note_item`-Liste; Button
    „Inhalt anzeigen" nur bei Warnung); wenn `contentnotes_viewer_wants_warning()` ⇒
    `$contentnotes_posts_attr = ' class="contentnotes_veiled" data-contentnotes-veil="thread"'`.
    Alle Kategorien „einblenden" ⇒ Banner und Attribut leer.
  - Post (`postbit`): Post-Notes der Thread-Seite einmalig laden; bei Warnung
    `$post['message']` in Template `contentnotes_post_veil` einbetten (Warnkopf + Button +
    `<div class="contentnotes_veiled" data-contentnotes-veil="post">…</div>`).
    `postbit_prev`: dasselbe aus dem Input.
  - `jscripts/contentnotes.js` (jQuery, kein Build): setzt `contentnotes_js` auf `<html>`;
    Klick auf `.contentnotes_reveal` entfernt `contentnotes_veiled` am zugehörigen Ziel, setzt
    `aria-expanded="true"`, blendet den Button aus; Inline-Aufdecken (Schritt 7) per Klick und
    Enter/Leertaste auf `.contentnotes_inline`.
  - Buttons sind `<button type="button" class="button">` (reine JS-Buttons).
- **Dateien:** `inc/plugins/contentnotes/display.php`, `jscripts/contentnotes.js`,
  `templates/contentnotes_{badge,thread_banner,post_veil,note_item}.tpl`, CSS ergänzen
- **Vorlage:** `standards/conventions.md` §5, §9 · `standards/boilerplate.md` §15 ·
  `standards/debugging.md` Punkte 16, 22
- **Abnahme:** curl auf `forumdisplay.php` / `showthread.php` als Gast: Badge, Banner,
  `contentnotes_veiled` vorhanden; als Mitglied mit allen Kategorien „einblenden": kein Banner,
  kein Veil. **Manueller Browsertest** fürs Aufdecken (JS) an übergeben.

## Schritt 7 – Inline-MyCode `[cw]` & Editor-Button
- [ ] **Ziel:**
  - `contentnotes_parse_inline` (`parse_message`): Regex
    `#\[cw=([a-z0-9_-]{1,50})(?:\|([^\]]{0,255}))?\](.*?)\[/cw\]#si`, bis zu 3 Durchläufe
    (Verschachtelung). Hinweis-Text: `htmlspecialchars_uni(strip_tags(htmlspecialchars_decode(...)))`
    (sicher für `allow_html` an und aus). Ausgabe bei Warnung:
    `<span class="contentnotes_inline" data-cw="slug" role="button" tabindex="0" aria-expanded="false"><span class="contentnotes_inline_label">⚠ Name: Hinweis</span> <span class="contentnotes_inline_text">Text</span></span>`;
    bei „einblenden" nur `Text`. Unbekanntes Kürzel ⇒ Label aus `$lang->contentnotes_generic`,
    immer Warnung. Sprachdatei im Parser-Kontext lazy laden (nur einmal).
  - `contentnotes_parse_inline_text` (`text_parse_message`): `[cw…]…[/cw]` ⇒
    `[Inhaltswarnung: Name]`.
  - JS: SCEditor-Command `contentnotes` (Dropdown: Kategorie-Select aus
    `contentnotes_config.categories` + Textfeld + Einfügen-Button; umschließt die Auswahl per
    `editor.insert('[cw=slug|hinweis]', '[/cw]')`), BBCode-Format `cw` für WYSIWYG
    (`tags: {span: {'data-cw': null}}`, `format`/`html` mit Erhalt von slug + Hinweis),
    Locale-Eintrag. Toolbar: in einem `$(function(){})`, der vor dem `codebuttons`-Ready-Handler
    läuft, `opt_editor.toolbar` um `contentnotes` ergänzen (`quote|` ⇒ `quote,contentnotes|`,
    sonst `|contentnotes` anhängen) – nur wenn `canSet && editorButton` und `opt_editor` existiert.
    `codebuttons`-Template **nicht** anfassen.
- **Dateien:** `inc/plugins/contentnotes/parser.php`, `jscripts/contentnotes.js`, CSS
- **Vorlage:** `standards/conventions.md` §5, §6 · Core-Fakten in `PROJECT_CONTEXT.md`
- **Abnahme:** CLI-Selbsttest: Parser mit Fixture-Strings (bekanntes/unbekanntes Kürzel, mit/ohne
  Hinweis, `<script>` im Hinweis, `[cw]` in `[code]` bleibt roh, verschachtelt, Pref
  „einblenden"). Editor-Button + WYSIWYG-Roundtrip: **manueller Browsertest**.

## Schritt 8 – UserCP-Seite
- [ ] **Ziel:** Menüpunkt in der UserCP-Sidebar (eigene Zeile mit Icon, Kit §14),
      `usercp.php?action=contentnotes` (Login-Pflicht): Tabelle aller Kategorien (aktive +
      solche, für die schon eine Pref existiert), Name + Beschreibung, je zwei Radio-Buttons
      „Warnung anzeigen" / „ohne Warnung einblenden" (Default Warnung). Speichern per POST
      `action=do_contentnotes` + `verify_post_check()`, danach `redirect()` mit Erfolgsmeldung.
      Seite mit `$usercpnav`, `$headerinclude`, `$header`, `$footer` (Sidebar-Styling).
- **Dateien:** `inc/plugins/contentnotes/usercp.php`,
  `templates/contentnotes_{usercp,usercp_row,usercp_menu}.tpl`
- **Vorlage:** `standards/boilerplate.md` §14, §15 · `standards/debugging.md` Punkte 3, 4, 20, 22
- **Abnahme:** curl: GET als Gast ⇒ no-permission; POST ohne `my_post_key` ⇒ Fehler; POST mit
  Key speichert Modi; Anzeige danach vorbelegt.

## Schritt 9 – ACP-Config-Modul „Inhaltswarnungen"
- [ ] **Ziel:** Modul `admin/modules/config/contentnotes.php`, registriert über
      `admin_config_menu`, `admin_config_action_handler`, `admin_config_permissions`
      (Permission `contentnotes`). `$lang->load('contentnotes')` als erste Anweisung.
      Aktionen:
  - `list` (Standard): Tabelle Name · Kürzel · Beschreibung · aktiv · Sortierung (editierbar
    per Inline-Input + „Sortierung speichern" POST) · Anzahl Markierungen · Optionen
    (Bearbeiten, Aktiv/Inaktiv umschalten, Löschen).
  - `add` / `edit`: Formular (Name Pflicht, Kürzel Pflicht + Regex `^[a-z0-9_-]{1,50}$` + eindeutig,
    Beschreibung, aktiv, Sortierung). Hinweis beim Kürzel ändern: bestehende `[cw=alt]` im Text
    passen dann nicht mehr (Warnung im Formular).
  - `delete`: Bestätigungsseite (`$page->output_confirm_action`), dann Kategorie + Notes + Prefs
    löschen.
  - Nach jeder Änderung `contentnotes_db_rebuild_cache()` + `log_admin_action()`.
  - Jede schreibende Aktion: `contentnotes_acp_verify_key()` mit Rückgabeprüfung.
- **Dateien:** `admin/modules/config/contentnotes.php`, Registrierungs-Handler in
  `inc/plugins/contentnotes.php`
- **Vorlage:** `standards/boilerplate.md` §12 (als **Config**-Modul: `admin_config_*` statt
  `admin_tools_*`, URL `index.php?module=config-contentnotes`) · `standards/debugging.md` Punkt 23
- **Abnahme:** `php -l`; Methoden-Abgleich gegen ein Core-Config-Modul (z.B.
  `admin/modules/config/thread_prefixes.php`); **manueller Klicktest an übergeben**
  (`standards/testing.md` §8).

## Schritt 10 – Sprachdateien
- [ ] **Ziel:** Alle genutzten Keys `contentnotes_*` definieren – Frontend (Formular, Banner,
      Badge, Veil, Inline, UserCP, Fehler, JS-Strings) und Admin (Modul, Settings-Titel/-Beschreibungen
      nur falls per Sprachdatei, Formular, Meldungen, `contentnotes_invalid_key`). english +
      deutsch_du, Frontend/Admin strikt getrennt, geschlechtergerecht (neutral, sonst Sternchen;
      Englisch neutral).
- **Dateien:** `inc/languages/english/contentnotes.lang.php`,
  `inc/languages/english/admin/contentnotes.lang.php`,
  `inc/languages/deutsch_du/contentnotes.lang.php`,
  `inc/languages/deutsch_du/admin/contentnotes.lang.php`
- **Vorlage:** `standards/boilerplate.md` §13 · `standards/conventions.md` §10 ·
  `standards/debugging.md` Punkt 5
- **Abnahme:** `python3 <Dev-Kit>/scripts/lang_check.py` ohne fehlende Keys;
  Sprachdatei-Grep (`standards/testing.md` §6).

## Schritt 11 – Selbsttests finalisieren
- [ ] **Ziel:** `tests/cli-plugin.php` (Lifecycle inkl. Template-Diff aus Schritt 3) und
      `tests/cli-selftest.php` (DB-Layer aus Schritt 4, Prefs-Auswertung, Parser-Fixtures aus
      Schritt 7, Rechte-Helper aus Schritt 5).
- **Vorlage:** `standards/testing.md` §3, §4, §7
- **Abnahme:** Lifecycle install→uninstall→install grün, Selbsttest 0 failed.
  Pre-Release-Checkliste (`standards/debugging.md`) einmal durchgehen und Ergebnis notieren.

---

Begleitdateien (README, CHANGELOG, PLUGIN_POST.html, LICENSE, FUNDING.yml, .gitignore,
.claudeignore, docs/screenshots) macht **Phase 3** („reviewe das Projekt").
