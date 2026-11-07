# H4W-Anpassungen (nur auf Branch `h4w`)

Dieser Branch ist `main` plus die folgenden Anpassungen für das H4W-Theme. Die Datei gibt es nur auf `h4w`
(Dev-Kit `standards/conventions.md` §12). Von `h4w` wird ins Test- und Live-Forum deployt.

## Anpassungen

| Datei | Zweck |
|---|---|
| `inc/plugins/contentnotes/templates/contentnotes_form.tpl` | Das H4W-Theme baut die Schreibformulare (`newthread`, `newreply`, `editpost`) als Flex-Container `.h4w-post-container` mit Kind-`<div class="h4w-post-item">` (Label und Feld im Wechsel, 20 % / 80 %, `styles/post.css` im Stylekit). Das Formular ist deshalb **genau zwei direkte `<div>`** statt einer Tabellenzeile (`<tr>` in einem `<div>` ist ungültig und zerlegt den Editor, Dev-Kit `debugging.md` Punkt 30). |
| `inc/plugins/contentnotes/assets/contentnotes.css` (Block am Ende, `.h4w-post-item …`) | Notiz-Felder füllen die Spalte des Flex-Items (`flex: 1 1 200px; min-width: 0`). |

Nicht angepasst (im H4W-Theme gleich wie im Standard-Theme): Schnellantwort (Tabelle), Badge-/Banner-Anker, UserCP-Menü.

## Regeln für diesen Branch

- Änderungen immer auf `main` machen und `main` → `h4w` mergen (nie umgekehrt). Bei einem Konflikt in `contentnotes_form.tpl` bzw. im h4w-CSS-Block die
  h4w-Variante behalten.
- **Editor-Änderungen** (`jscripts/contentnotes.js`, Regel `.sceditor-button-contentnotes` in `contentnotes.css`) werden zwischen `main` und `h4w`
  synchron gehalten, außer die Inhaberin sagt etwas anderes. Zurzeit sind sie identisch.
- Test-Forum prüfen (`test.h4w-rpg.de`), nicht das Live-Forum, bevor auf `live` deployt wird; Live nur auf ausdrückliche Anweisung.
