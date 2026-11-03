# Content Notes – Project Context

## Identität
- **Name:** Content Notes
- **Codename / Dateiname:** `contentnotes` (Präfix: `contentnotes`)
- **Version:** 1.0.0
- **Entwickelt von:** pand0rica
- **Kurzbeschreibung:** Inhaltswarnungen (Content Notes) für ganze Threads, einzelne Posts und
  einzelne Textstellen – mit frei definierbaren Kategorien und persönlicher Einstellung pro
  Kategorie.
- **MyBB:** 1.8.x · **PHP:** 7.4 + 8.x

## Zweck & Zielgruppe
Rollenspiel- und Community-Foren, in denen belastende Inhalte (Gewalt, Sucht, Tod …)
gekennzeichnet werden sollen. Das Team legt im ACP Kategorien an. Wer schreibt (und das Recht
dazu hat), markiert Threads, Posts oder einzelne Textstellen mit einer oder mehreren Kategorien
und einem optionalen Freitext. Mitglieder entscheiden im Benutzer-CP pro Kategorie, ob sie eine
anklickbare Warnung sehen oder den Inhalt direkt ohne Warnung angezeigt bekommen.

## Funktionsumfang (verbindliche Spezifikation)

### Kategorien (ACP)
- Felder: **Name**, **Kürzel** (eindeutig, nur `[a-z0-9_-]`, max. 50 Zeichen – steht im MyCode),
  **Beschreibung** (erscheint im UserCP), **aktiv/inaktiv**, **Sortierung** (`disporder`).
- Inaktive Kategorien sind in Formularen und im Editor nicht mehr wählbar, bestehende
  Markierungen damit wirken aber weiter (Anzeige + UserCP-Einstellung bleiben).
- Löschen einer Kategorie entfernt alle Markierungen (`contentnotes_notes`) und Vorlieben
  (`contentnotes_userprefs`) dieser Kategorie. Inline-MyCode mit dem gelöschten Kürzel wird
  danach als generische „Inhaltswarnung" angezeigt.

### Persönliche Einstellung (UserCP)
- Eigene Seite `usercp.php?action=contentnotes` mit Menüpunkt in der UserCP-Sidebar.
- Pro Kategorie: **Warnung anzeigen** (Modus 0) oder **ohne Warnung einblenden** (Modus 1).
- **Standard = Warnung**: kein Eintrag in `contentnotes_userprefs` ⇒ Warnung. Gäste sehen
  immer Warnungen.

### Drei Markierungsorte
1. **Thread** (beim Erstellen bzw. beim Bearbeiten des ersten Posts):
   - Badge mit den Kategorienamen hinter dem Betreff in `forumdisplay_thread` und
     `search_results_threads_thread`. Das Badge wird **immer** angezeigt (Info, kein Verdecken).
   - Im Thread: Warnkasten oberhalb von `<div id="posts">` mit allen Kategorien + Freitexten.
     Steht mind. eine Kategorie beim Viewer auf „Warnung", sind **alle Posts verdeckt**, bis der
     Button „Inhalt anzeigen" geklickt wird. Gilt pro Seitenaufruf (nicht gespeichert).
   - Stehen alle Kategorien auf „einblenden": weder Kasten noch Verdecken (Badge bleibt).
2. **Post** (Antwort, Quick-Reply, Bearbeiten eines Nicht-Erst-Posts):
   - Die Nachricht wird im Postbit durch einen Warnkopf (Kategorien + Freitexte + Button)
     ersetzt/verdeckt; Klick zeigt die Nachricht. Gleiche „mind. eine Kategorie"-Regel.
3. **Textstelle** per MyCode `[cw=kuerzel]Text[/cw]` oder `[cw=kuerzel|Hinweis]Text[/cw]`:
   - Darstellung **inline**, ohne Block/Zeilenumbruch: kleines Label-Chip („⚠ Name: Hinweis")
     direkt vor dem verschwommenen Text (`filter: blur()`), der im Lesefluss seine Breite behält.
     Klick oder Enter/Leertaste auf Chip bzw. Text deckt auf (`aria-expanded`).
   - Viewer hat die Kategorie auf „einblenden": nur der Text, ohne Chip/Blur.
   - Unbekanntes Kürzel ⇒ generisches Label „Inhaltswarnung" (immer Warnung).
   - Plaintext-Kontexte (`text_parse_message`: RSS, E-Mail-Benachrichtigungen): Inhalt wird
     durch „[Inhaltswarnung: Name]" ersetzt.
   - `[cw]` innerhalb von `[code]`/`[php]` bleibt unangetastet (siehe Core-Fakten).

### Mehrfach-Markierung
Thread und Post können **mehrere Kategorien** tragen, **jede mit eigenem optionalen Freitext**
(max. 255 Zeichen). Formular: aufklappbares Fieldset, pro aktiver Kategorie eine Checkbox + ein
kleines Textfeld. Feldnamen `contentnotes[<cid>][on]` und `contentnotes[<cid>][note]`.

### Editor
SCEditor-Button „Inhaltswarnung" (nur für berechtigte Gruppen und wenn Setting
`contentnotes_editor_button` an): Dropdown mit Kategorie-Auswahl (aktive Kategorien) +
optionalem Freitext; umschließt die Markierung mit `[cw=kuerzel|Hinweis]…[/cw]`. Zusätzlich ein
BBCode-Format `cw`, damit der MyCode im WYSIWYG-Modus erhalten bleibt (Roundtrip).

## Architektur
- **Variante:** prozedural-modular (Begründung: mehrere getrennte Bereiche – Formulare, Anzeige,
  Parser, UserCP, ACP – mit mittlerer Codemenge; eine Datei würde unübersichtlich, Klassen sind
  unnötig).
- **Dateilayout:**
  ```
  inc/plugins/contentnotes.php              info, Lifecycle, Hook-Registrierung, require der Module
  inc/plugins/contentnotes/database.php     contentnotes_db_* (Kategorien, Notes, Prefs, Datacache)
  inc/plugins/contentnotes/forms.php        Formular-Render, Validierung, Speichern, Aufräum-Hooks
  inc/plugins/contentnotes/display.php      Badge, Thread-Banner/-Veil, Postbit-Veil, Prefs-Auswertung, headerinclude
  inc/plugins/contentnotes/parser.php       [cw]-MyCode (HTML + Plaintext)
  inc/plugins/contentnotes/usercp.php       UserCP-Seite + Menüpunkt
  inc/plugins/contentnotes/templates/*.tpl  eigene Templates (Präfix contentnotes_)
  inc/plugins/contentnotes/assets/contentnotes.css
  jscripts/contentnotes.js                  Aufdecken (Thread/Post/Inline) + SCEditor-Command/-Format
  admin/modules/config/contentnotes.php     Kategorienverwaltung
  inc/languages/english/contentnotes.lang.php       (+ admin/contentnotes.lang.php)
  inc/languages/deutsch_du/contentnotes.lang.php    (+ admin/contentnotes.lang.php)
  tests/cli-plugin.php, tests/cli-selftest.php
  ```

## Datenbank-Tabellen
Alle `ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci` (Kit `conventions.md` §4).

| Tabelle (`TABLE_PREFIX .`) | Zweck | Spalten |
|---|---|---|
| `contentnotes_categories` | Kategorien | `cid` INT(11) AI PK, `name` VARCHAR(100) NOT NULL, `slug` VARCHAR(50) NOT NULL UNIQUE, `description` TEXT, `active` TINYINT(1) DEFAULT 1, `disporder` INT(11) DEFAULT 0 |
| `contentnotes_notes` | Markierungen an Threads/Posts | `nid` INT(11) AI PK, `type` VARCHAR(10) NOT NULL (`thread` \| `post`), `refid` INT(11) NOT NULL (tid bzw. pid), `cid` INT(11) NOT NULL, `note` VARCHAR(255) NOT NULL DEFAULT ''; UNIQUE KEY (`type`,`refid`,`cid`), KEY (`type`,`refid`) |
| `contentnotes_userprefs` | Vorlieben pro Mitglied & Kategorie | `uid` INT(11) NOT NULL, `cid` INT(11) NOT NULL, `mode` TINYINT(1) NOT NULL DEFAULT 0 (0 = Warnung, 1 = einblenden); PRIMARY KEY (`uid`,`cid`) |

**Datacache** `contentnotes_categories`: Array aller Kategorien (cid ⇒ Zeile), sortiert nach
`disporder, name`. Neuaufbau über `contentnotes_db_rebuild_cache()` nach jeder ACP-Änderung und in
`install()`; Löschen in `uninstall()` (`$cache->delete('contentnotes_categories')` bzw.
`DELETE FROM datacache WHERE title='contentnotes_categories'`).

## Settings (settinggroup `contentnotes`, angelegt in `install()`, entfernt in `uninstall()`)
| Setting | optionscode | Standard | Zweck |
|---|---|---|---|
| `contentnotes_enabled` | yesno | 1 | Plugin-Funktionen im Frontend an/aus |
| `contentnotes_groups` | groupselect | `4` (Admins) | Gruppen, die Thread-/Post-Warnungen setzen/ändern/entfernen dürfen und den Editor-Button sehen (Primär- **und** Zusatzgruppen prüfen: `is_member()`) |
| `contentnotes_forums` | forumselect | `-1` (alle) | Foren, in denen Thread-/Post-Formulare angeboten und gespeichert werden |
| `contentnotes_editor_button` | yesno | 1 | SCEditor-Button anzeigen |

## Hooks
| Hook | Handler | Modul | Zweck |
|---|---|---|---|
| `global_intermediate` | `contentnotes_handle_headerinclude` | display | auf newthread/newreply/editpost/showthread: Kategorien-JSON (aktive) + Rechte-Flag + `jscripts/contentnotes.js` an `$headerinclude` anhängen |
| `newthread_start` | `contentnotes_handle_newthread_form` | forms | `$contentnotes_form` (Thread) bauen |
| `newreply_start` | `contentnotes_handle_newreply_form` | forms | `$contentnotes_form` (Post) bauen |
| `editpost_end` | `contentnotes_handle_editpost_form` | forms | Formular, vorbelegt aus DB; erster Post ⇒ Thread-Notes |
| `showthread_start` | `contentnotes_handle_showthread_start` | display | Thread-Notes laden, `$contentnotes_thread_banner`, `$contentnotes_posts_attr`, Quick-Reply-Formular `$contentnotes_quickreply` |
| `datahandler_post_validate_thread` / `_post` | `contentnotes_handle_validate` | forms | Input prüfen (cid aktiv, Freitext ≤ 255), Fehler via `$this->set_error()` |
| `datahandler_post_insert_thread_end` | `contentnotes_handle_insert_thread` | forms | Thread-Notes speichern (`return_values['tid']`) |
| `datahandler_post_insert_post_end` | `contentnotes_handle_insert_post` | forms | Post-Notes speichern (`return_values['pid']`) |
| `datahandler_post_insert_merge` | `contentnotes_handle_insert_post` | forms | Doppelpost-Merge: Notes an `return_values['pid']` (zusammengeführter Post) ergänzen, nicht ersetzen |
| `datahandler_post_update_end` | `contentnotes_handle_update_post` | forms | `return_values['first_post']` ⇒ Thread-Notes, sonst Post-Notes ersetzen – **nur wenn das Formular mitgesendet wurde** (Marker-Feld `contentnotes_form=1`) und Recht vorhanden |
| `class_moderation_delete_thread_start` | `contentnotes_handle_delete_thread_start` | forms | pids des Threads merken (statisch) |
| `class_moderation_delete_thread` | `contentnotes_handle_delete_thread` | forms | Thread-Notes + gemerkte Post-Notes löschen |
| `class_moderation_delete_post` | `contentnotes_handle_delete_post` | forms | Post-Notes löschen |
| `datahandler_user_delete_start` | `contentnotes_handle_user_delete` | database | Prefs der gelöschten uids löschen |
| `forumdisplay_thread` | `contentnotes_handle_thread_badge` | display | `$thread['contentnotes_badge']` (Notes aller tids der Seite einmalig per `IN()` laden) |
| `search_results_thread` | `contentnotes_handle_thread_badge` | display | dito für Suchergebnisse |
| `postbit` | `contentnotes_handle_postbit` | display | Post-Veil um `$post['message']`; Post-Notes der Thread-Seite einmalig per Join `posts.tid` laden |
| `postbit_prev` | `contentnotes_handle_postbit_preview` | display | Vorschau: Veil aus Input statt DB |
| `parse_message` | `contentnotes_parse_inline` | parser | `[cw]` ⇒ HTML |
| `text_parse_message` | `contentnotes_parse_inline_text` | parser | `[cw]` ⇒ „[Inhaltswarnung: Name]" |
| `usercp_menu` | `contentnotes_usercp_menu` | usercp | Menüpunkt (Kit `boilerplate.md` §14) |
| `usercp_start` | `contentnotes_usercp_page` | usercp | `action=contentnotes` (GET Anzeige) / `do_contentnotes` (POST Speichern) |
| `admin_config_menu` / `admin_config_action_handler` / `admin_config_permissions` | `contentnotes_admin_*` | Hauptdatei | ACP-Config-Modul registrieren |

## Template-Injection (activate/deactivate, Kit `boilerplate.md` §8/§9)
| Core-Template | Anker | Einfügung |
|---|---|---|
| `newthread`, `newreply`, `editpost` | `{$posticons}` | `{$posticons}{$contentnotes_form}` |
| `showthread_quickreply` | `{$option_signature}` | `{$option_signature}{$contentnotes_quickreply}` (innerhalb der Optionen-Zelle; Feldset kompakt) |
| `showthread` | `<div id="posts">` (liegt in `<td id="posts_container">`) | `{$contentnotes_thread_banner}<div id="posts"{$contentnotes_posts_attr}>` – Banner steht in derselben Tabellenzelle direkt über den Posts; `$contentnotes_posts_attr` ist leer oder ` class="contentnotes_veiled" data-contentnotes-veil="thread"` |
| `forumdisplay_thread`, `search_results_threads_thread` | `{$thread['multipage']}` (steht direkt hinter dem Betreff-Link) | `{$thread['contentnotes_badge']}{$thread['multipage']}` |

Guard-String/Entfernen per `find_replace_templatesets()` mit exaktem Gegen-Regex in
`deactivate()`. Debugging-Punkte 17 und 18 beachten.

## Eigene Templates (`contentnotes_*`, aus `.tpl`)
`contentnotes_form`, `contentnotes_form_row`, `contentnotes_quickreply`, `contentnotes_badge`,
`contentnotes_thread_banner`, `contentnotes_post_veil`, `contentnotes_note_item`,
`contentnotes_usercp`, `contentnotes_usercp_row`, `contentnotes_usercp_menu`.
(Inline-Markup des Parsers ist bewusst **kein** Template – Performance pro Vorkommen; das Markup
steht als Konstante in `parser.php`, Texte aus der Sprachdatei.)

## Eigene Hooks / API
- `contentnotes_get_notes(string $type, int $refid): array` – öffentliche Abfrage (für andere
  Plugins), liefert `[['cid'=>…, 'name'=>…, 'slug'=>…, 'note'=>…], …]`.
- `contentnotes_viewer_wants_warning(array $cids): bool` – Prefs-Auswertung für den aktuellen
  Viewer.
- Eigener Hook `contentnotes_notes_saved` (`['type'=>…, 'refid'=>…, 'cids'=>[…]]`) nach jedem
  Speichern. In README dokumentieren (Phase 3).

## Abhängigkeiten
Keine. Kein anderes Plugin wird benötigt.

## Berechtigungen
- Thread-/Post-Warnungen setzen/ändern/entfernen: nur Mitglieder in `contentnotes_groups`
  (`is_member()`), nur in Foren aus `contentnotes_forums`.
- Ohne Recht wird beim Bearbeiten **kein** Formular gezeigt und die vorhandenen Notes bleiben
  unverändert (Marker-Feld fehlt ⇒ kein Update).
- Inline-MyCode `[cw]`: wird für alle Beiträge geparst (Tippen lässt sich nicht verhindern);
  nur der Editor-Button ist an die Gruppe gebunden.
- UserCP-Seite: Login-Pflicht (`error_no_permission()` für Gäste).
- ACP: eigene Admin-Permission `contentnotes` über `admin_config_permissions`.

## Verifizierte MyBB-Core-Fakten (Stand 1.8.41, Branch `feature` von mybb/mybb)
- **Achtung:** Branch `master` von mybb/mybb ist **1.6.18**. Für 1.8 gegen `feature` oder einen
  `mybb_18xx`-Branch prüfen.
- `class_parser.php`: `parse_message` läuft nach `parse_mycode()`; `[code]`/`[php]` sind zu dem
  Zeitpunkt noch als `<mybb-code>`-Platzhalter ausgelagert ⇒ Codeblöcke werden vom `[cw]`-Regex
  nicht erfasst. Ist `allow_html` aus, ist der Text bereits HTML-escaped; ist es an, muss der
  Freitext im `[cw=…|Hinweis]` selbst mit `htmlspecialchars_uni(strip_tags(...))` behandelt werden.
- `datahandlers/post.php`: `insert_thread_end` ⇒ `return_values` `pid`, `tid`, `visible`;
  `insert_post_end` ⇒ `pid`, `visible`, `closed`; `insert_merge` ⇒ `pid` (des bestehenden
  Posts), `merge => true`; `update_end` ⇒ `visible`, `first_post` (pid über `$this->data['pid']`).
- `codebuttons`-Template definiert `opt_editor` (mit `toolbar`-String) global und ruft
  `$("#…").sceditor(opt_editor)` in `$(function(){…})` auf. jQuery-Ready-Handler laufen in
  Registrierungsreihenfolge ⇒ ein Handler aus `headerinclude` kann `opt_editor.toolbar` vorher
  ergänzen (`quote|` ⇒ `quote,contentnotes|`, Fallback: `|contentnotes` anhängen). Das
  `codebuttons`-Template wird **nicht** verändert.

## Bekannte Grenzen / bewusste Annahmen
- Quick-Edit (AJAX, `xmlhttp.php`) ändert keine Thread-/Post-Warnungen – nur der Volleditor.
- Threads zusammenführen: Thread-Warnungen des aufgelösten Threads entfallen.
- „Inhalt anzeigen" gilt pro Seitenaufruf; nichts wird in Cookie/Storage gemerkt.
- Entwürfe (`savedraft`): Notes hängen am Draft-pid und bleiben beim Veröffentlichen erhalten.

## What NOT to change
- `jscripts/contentnotes.js` liegt bewusst unter `jscripts/` (web-erreichbar, MyBB-üblich) –
  nicht nach `inc/plugins/contentnotes/assets/` verschieben (`inc/` kann per Webserver gesperrt
  sein).
- Das Core-Template `codebuttons` wird **nicht** editiert; die Toolbar wird per JS erweitert.
- Standard ohne Eintrag = **Warnung**. Beide Modi werden gespeichert; ein fehlender Eintrag
  bedeutet immer Warnung – diese Default-Regel nicht umdrehen.
- Verdecken nur mit JS: `contentnotes.js` setzt die Klasse `contentnotes_js` auf `<html>`, erst
  dann greift `.contentnotes_js .contentnotes_veiled { display:none }`. Ohne JS bleibt alles
  sichtbar (Warnkasten steht trotzdem darüber). Nicht auf serverseitiges Weglassen umbauen.
- Settings gehören in `install()`/`uninstall()`, nicht in `activate()`/`deactivate()`
  (Kit `debugging.md` Punkt 21).
- ACP-Modul ist ein **Config**-Modul (`admin/modules/config/`), kein Tools-Modul.
- Inline-Markup des Parsers bleibt im PHP (kein Template pro Vorkommen).
