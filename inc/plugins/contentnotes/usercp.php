<?php
/**
 * Content Notes – Benutzer-CP: Menüpunkt und Seite „Inhaltswarnungen“.
 *
 * @package ContentNotes
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

/**
 * Anker-Zeile im Core-Template usercp_nav_misc (Entwürfe), hinter die der Menüpunkt kommt.
 *
 * @return string
 */
function contentnotes_usercp_nav_anchor(): string
{
    return '<tr><td class="trow1 smalltext"><a href="usercp.php?action=drafts" class="usercp_nav_item usercp_nav_drafts">{$draftcount}</a></td></tr>';
}

/**
 * Fertig gebackene Menüzeile (statisch, nur {$lang->...} wird zur Laufzeit aufgelöst, siehe
 * Kit debugging.md Punkt 20). Das Icon steckt als Inline-SVG direkt am Link.
 *
 * @return string
 */
function contentnotes_usercp_nav_row(): string
{
    $icon = "url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22 viewBox=%220 0 16 16%22%3E"
        . "%3Cpath d=%22M8 1.5L15 14H1z%22 fill=%22none%22 stroke=%22%23555%22 stroke-width=%221.4%22 stroke-linejoin=%22round%22/%3E"
        . "%3Cpath d=%22M8 6v4%22 stroke=%22%23555%22 stroke-width=%221.4%22 stroke-linecap=%22round%22/%3E"
        . "%3Ccircle cx=%228%22 cy=%2212%22 r=%220.9%22 fill=%22%23555%22/%3E%3C/svg%3E')";

    return '<tr><td class="trow1 smalltext"><a href="usercp.php?action=contentnotes" class="usercp_nav_item usercp_nav_contentnotes" '
        . 'style="background-image: ' . $icon . ';">{$lang->contentnotes_usercp_menu_item}</a></td></tr>';
}

/**
 * Hook usercp_menu: lädt nur die Sprachdatei, damit {$lang->contentnotes_usercp_menu_item} im
 * Core-Template etwas findet (die Zeile selbst steckt statisch im Template).
 *
 * @return void
 */
function contentnotes_usercp_menu(): void
{
    contentnotes_load_lang();
}

/**
 * Hook usercp_start: eigene Seite (Anzeige) und Speichern der Vorlieben.
 *
 * @return void
 */
function contentnotes_usercp_page(): void
{
    global $mybb, $lang, $templates, $theme, $headerinclude, $header, $footer, $usercpnav;

    $action = $mybb->get_input('action');
    if ($action !== 'contentnotes' && $action !== 'do_contentnotes') {
        return;
    }

    contentnotes_load_lang();

    // Der Menüpunkt bleibt immer sichtbar; die Seite prüft das Setting selbst
    if (!contentnotes_is_enabled()) {
        error($lang->contentnotes_disabled);
    }

    $uid = (int)$mybb->user['uid'];
    if ($uid <= 0) {
        error_no_permission();
    }

    $categories = contentnotes_db_get_categories();

    if ($action === 'do_contentnotes') {
        if ($mybb->request_method !== 'post') {
            error_no_permission();
        }
        verify_post_check($mybb->get_input('my_post_key'));

        $submitted = $mybb->get_input('contentnotes_mode', MyBB::INPUT_ARRAY);
        $modes     = [];
        foreach ($categories as $cid => $category) {
            if (isset($submitted[$cid])) {
                $modes[$cid] = contentnotes_normalize_mode($submitted[$cid]);
            }
        }
        contentnotes_db_save_user_prefs($uid, $modes);

        redirect('usercp.php?action=contentnotes', $lang->contentnotes_usercp_saved);
    }

    add_breadcrumb($lang->nav_usercp, 'usercp.php');
    add_breadcrumb($lang->contentnotes_usercp_title, 'usercp.php?action=contentnotes');

    // Aktive Kategorien und inaktive, die noch an Threads/Posts hängen
    $counts = contentnotes_db_count_notes_per_category();
    $prefs  = contentnotes_db_get_user_prefs($uid);
    $rows   = '';
    foreach ($categories as $cid => $category) {
        if (empty($category['active']) && empty($counts[$cid])) {
            continue;
        }

        $trow         = alt_trow();
        $cid_value    = (int)$cid;
        $name         = htmlspecialchars_uni($category['name']);
        $description  = trim((string)$category['description']);
        $description_html = $description !== ''
            ? '<br /><span class="smalltext">' . htmlspecialchars_uni($description) . '</span>'
            : '';
        $mode         = isset($prefs[$cid]) ? contentnotes_normalize_mode($prefs[$cid]) : 0;
        $checked_warn = $mode === 0 ? ' checked="checked"' : '';
        $checked_show = $mode === 1 ? ' checked="checked"' : '';
        $checked_hide = $mode === 2 ? ' checked="checked"' : '';
        eval('$rows .= "' . $templates->get('contentnotes_usercp_row') . '";');
    }

    if ($rows === '') {
        $rows = '<tr><td class="trow1" colspan="2">' . htmlspecialchars_uni((string)$lang->contentnotes_usercp_none) . '</td></tr>';
    }

    $contentnotes_usercp = '';
    eval('$contentnotes_usercp = "' . $templates->get('contentnotes_usercp') . '";');
    output_page($contentnotes_usercp);
    exit;
}
