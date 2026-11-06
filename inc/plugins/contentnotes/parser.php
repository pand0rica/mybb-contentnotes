<?php
/**
 * Content Notes – Inline-MyCode [cw=kuerzel|Hinweis]Text[/cw].
 *
 * @package ContentNotes
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

/**
 * Regulärer Ausdruck für innerste [cw]-Blöcke (kein weiteres [cw] im Inhalt).
 * Das Attribut ist entweder in Anführungszeichen (so schreibt es der SCEditor zurück, sobald
 * der Hinweis Leerzeichen enthält) oder roh bis zur schließenden Klammer.
 *
 * @return string
 */
function contentnotes_inline_pattern(): string
{
    return '#\[cw(?:=("(?:[^"\\\\]|\\\\.)*"|[^\]]*))?\]((?:(?!\[cw[=\]]|\[/cw\]).)*)\[/cw\]#is';
}

/**
 * Zerlegt das [cw]-Attribut in Kürzel und Hinweistext.
 *
 * Erlaubt sind `kuerzel`, `kuerzel|Hinweis` und beides in Anführungszeichen (`"kuerzel|Hinweis"`,
 * Anführungszeichen im Text als `\"`). Ist das Kürzel ungültig, bleibt es leer (allgemeine
 * Inhaltswarnung) und der ganze Text wird zum Hinweis – es geht nichts verloren.
 *
 * Das Attribut kann HTML-escaped ankommen (ohne erlaubtes HTML läuft der Parser vorher
 * htmlspecialchars): deshalb zuerst einmal zurückwandeln.
 *
 * @param string $attr Attribut nach dem "="
 * @return array [Kürzel, Hinweis als Rohtext]
 */
function contentnotes_split_attr(string $attr): array
{
    $attr = trim(htmlspecialchars_decode($attr, ENT_QUOTES));

    if (strlen($attr) >= 2 && $attr[0] === '"' && substr($attr, -1) === '"') {
        $attr = (string)preg_replace('/\\\\(["\\\\])/', '$1', substr($attr, 1, -1));
    }
    if ($attr === '') {
        return ['', ''];
    }

    $parts = explode('|', $attr, 2);
    $slug  = strtolower(trim($parts[0]));
    if (!contentnotes_valid_slug($slug)) {
        return ['', $attr];
    }

    return [$slug, $parts[1] ?? ''];
}

/**
 * Bereinigt einen Hinweistext (Rohtext): Tags entfernen, Whitespace glätten, kürzen, escapen.
 * Ist sicher, egal ob HTML im Beitrag erlaubt ist oder nicht.
 *
 * @param string $raw Hinweistext als Rohtext
 * @return string
 */
function contentnotes_clean_note(string $raw): string
{
    $raw = trim(strip_tags($raw));
    $raw = trim((string)preg_replace('/\s+/', ' ', $raw));
    if (my_strlen($raw) > 255) {
        $raw = my_substr($raw, 0, 255);
    }

    return htmlspecialchars_uni($raw);
}

/**
 * Wendet eine Ersetzungsfunktion auf alle [cw]-Blöcke an (verschachtelt, von innen nach außen).
 *
 * @param string   $message  Nachricht
 * @param callable $callback function(array $matches): string
 * @return string
 */
function contentnotes_replace_inline(string $message, callable $callback): string
{
    $pattern = contentnotes_inline_pattern();

    // Mehrere Durchläufe für verschachtelte Blöcke, mit Obergrenze gegen Endlosschleifen
    for ($pass = 0; $pass < 5; $pass++) {
        $replaced = preg_replace_callback($pattern, $callback, $message, -1, $count);
        if ($replaced === null) {
            break; // PCRE-Fehler (z.B. Backtrack-Limit): Nachricht unverändert lassen
        }
        $message = $replaced;
        if ($count === 0) {
            break;
        }
    }

    return $message;
}

/**
 * Hook parse_message: [cw] in HTML umwandeln.
 *
 * @param string $message Bereits geparste Nachricht
 * @return string
 */
function contentnotes_parse_inline($message)
{
    if (!is_string($message) || stripos($message, '[cw') === false) {
        return $message;
    }

    // Plugin im Frontend ausgeschaltet: Markierungen entfernen, Text bleibt stehen
    if (!contentnotes_is_enabled()) {
        return contentnotes_replace_inline($message, static function (array $m): string {
            return $m[2];
        });
    }

    contentnotes_load_lang();

    return contentnotes_replace_inline($message, static function (array $m): string {
        global $lang;

        list($slug, $raw_note) = contentnotes_split_attr((string)($m[1] ?? ''));
        $note     = contentnotes_clean_note($raw_note);
        $content  = $m[2];
        $category = $slug !== '' ? contentnotes_db_get_category_by_slug($slug) : null;

        // Vorlieben: bekannte Kategorie auf „komplett ausblenden“ => spurlos weg, auf „einblenden“ => nur der Text
        if ($category !== null) {
            $action = contentnotes_viewer_action([(int)$category['cid']]);
            if ($action === 'hide') {
                return '';
            }
            if ($action === 'show') {
                return $content;
            }
        }

        $name  = $category !== null ? htmlspecialchars_uni($category['name']) : htmlspecialchars_uni((string)$lang->contentnotes_generic);
        $label = '&#9888; ' . $name . ($note !== '' ? ': ' . $note : '');
        $attr  = $slug !== '' ? ' data-cw="' . htmlspecialchars_uni($slug) . '"' : '';

        return '<span class="contentnotes_inline"' . $attr . ' role="button" tabindex="0" aria-expanded="false" title="'
            . htmlspecialchars_uni((string)$lang->contentnotes_inline_title) . '">'
            . '<span class="contentnotes_inline_label">' . $label . '</span> '
            . '<span class="contentnotes_inline_text">' . $content . '</span></span>';
    });
}

/**
 * Hook text_parse_message: [cw] in Klartext-Kontexten (RSS, E-Mail) durch einen Hinweis ersetzen.
 *
 * @param string $message Nachricht
 * @return string
 */
function contentnotes_parse_inline_text($message)
{
    if (!is_string($message) || stripos($message, '[cw') === false) {
        return $message;
    }

    contentnotes_load_lang();

    return contentnotes_replace_inline($message, static function (array $m): string {
        global $lang;

        list($slug) = contentnotes_split_attr((string)($m[1] ?? ''));
        $category   = $slug !== '' ? contentnotes_db_get_category_by_slug($slug) : null;
        $name       = $category !== null ? $category['name'] : (string)$lang->contentnotes_generic;

        return $lang->sprintf((string)$lang->contentnotes_text_replacement, $name);
    });
}
