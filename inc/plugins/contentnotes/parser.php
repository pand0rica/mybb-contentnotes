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
 * Reguläre Ausdrücke für innerste [cw]-Blöcke (kein weiteres [cw] im Inhalt).
 *
 * @return string
 */
function contentnotes_inline_pattern(): string
{
    return '#\[cw(?:=([a-z0-9_-]{1,50})(?:\|([^\]]*))?)?\]((?:(?!\[cw[=\]]|\[/cw\]).)*)\[/cw\]#is';
}

/**
 * Bereinigt einen Hinweistext: Entities auflösen, Tags entfernen, neu escapen.
 * Ist sicher, egal ob HTML im Beitrag erlaubt ist oder nicht.
 *
 * @param string $raw Hinweistext aus dem MyCode
 * @return string
 */
function contentnotes_clean_note(string $raw): string
{
    $raw = trim(strip_tags(htmlspecialchars_decode($raw)));
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
            return $m[3];
        });
    }

    contentnotes_load_lang();

    return contentnotes_replace_inline($message, static function (array $m): string {
        global $lang;

        $slug     = strtolower((string)($m[1] ?? ''));
        $note     = contentnotes_clean_note((string)($m[2] ?? ''));
        $content  = $m[3];
        $category = $slug !== '' ? contentnotes_db_get_category_by_slug($slug) : null;

        // Vorlieben: bekannte Kategorie auf „einblenden“ => nur der Text
        if ($category !== null && !contentnotes_viewer_wants_warning([(int)$category['cid']])) {
            return $content;
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

        $slug     = strtolower((string)($m[1] ?? ''));
        $category = $slug !== '' ? contentnotes_db_get_category_by_slug($slug) : null;
        $name     = $category !== null ? $category['name'] : (string)$lang->contentnotes_generic;

        return $lang->sprintf((string)$lang->contentnotes_text_replacement, $name);
    });
}
