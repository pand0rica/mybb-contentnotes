# Content Notes – Agent Instructions

Dieses Repo wird mit dem **MyBB Plugin Dev Kit** entwickelt. Bevor du hier etwas tust:

1. Lies zuerst das Dev-Kit: **`<Dev-Kit>/AGENTS.md`** und halte dich an alle dort
   verlinkten Standards. Das Kit ist die kanonische Quelle für Konventionen, Boilerplate,
   Debugging und Testing.
2. Lies dann in diesem Repo: `PROJECT_CONTEXT.md` (Kontext) und – in der Umsetzungsphase –
   `IMPLEMENTATION_PLAN.md` (die Arbeitsschritte).

Das Dev-Kit wird **nicht** in dieses Repo kopiert, sondern referenziert. `<Dev-Kit>` ist der
Ort, an dem das Kit in dieser Umgebung liegt (in Phase 1 eingesetzt). Liegt es nicht vor,
frag danach, statt zu raten.

## Kurz-Kontext
Inhaltswarnungen (Content Notes) für Threads, Posts und Textstellen (`[cw=kuerzel|Hinweis]…[/cw]`),
Kategorien im ACP (Config-Modul), Einstellung pro Kategorie im Benutzer-CP (Warnung / einblenden).
Architektur prozedural-modular, drei Tabellen. Details: `PROJECT_CONTEXT.md`.

**Kit-Ort:** In Cloud-Sitzungen liegt das Kit unter `<Dev-Kit>`
(Repo `pand0rica/mybb-devkit`). Lokal den Pfad entsprechend anpassen.

## Sprache
Antworten Deutsch, Code/Bezeichner Englisch. PHP 7.4 + 8.x. Keine externen Libraries.
Alle Texte (Begleitdateien, Sprachdateien, Templates, Settings) geschlechtergerecht:
genderneutral, sonst Gender-Sternchen; Englisch neutral (Kit `standards/conventions.md` §10).

## Phasen (werden einzeln ausgelöst)

| Eingabe | Phase | Folge dieser Kit-Datei |
|---|---|---|
| „umsetzen / implementieren" | Umsetzung | `<Dev-Kit>/workflows/2-implement.md` + `IMPLEMENTATION_PLAN.md` |
| „reviewe das Projekt" | Review | `<Dev-Kit>/workflows/3-review.md` |
| „release das Projekt" | Release | `<Dev-Kit>/workflows/4-release.md` |

## Die 5 goldenen Regeln (Kurzform, Details im Kit)
Keine Core-Edits · `TABLE_PREFIX` · `$mybb->get_input()` · POST + `verify_post_check()` ·
alles mit Präfix `contentnotes`. Ausgabe `htmlspecialchars_uni()`, SQL `escape_string()`/`(int)`.
