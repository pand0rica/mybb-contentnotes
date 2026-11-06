<?php
/**
 * Content Notes – Formulare: Rechte, Formular-Rendering, Validierung, Speichern, Aufräumen.
 *
 * @package ContentNotes
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

/**
 * Ist das Plugin im Frontend eingeschaltet?
 *
 * @return bool
 */
function contentnotes_is_enabled(): bool
{
    global $mybb;

    return !empty($mybb->settings['contentnotes_enabled']);
}

/**
 * Gehört das angemeldete Mitglied zu den Gruppen, die Warnungen setzen dürfen?
 *
 * @return bool
 */
function contentnotes_user_in_allowed_group(): bool
{
    global $mybb;

    if (!contentnotes_is_enabled() || empty($mybb->user['uid'])) {
        return false;
    }

    $groups = (string)($mybb->settings['contentnotes_groups'] ?? '');
    if ($groups === '') {
        return false;
    }

    return (bool)is_member($groups);
}

/**
 * Ist das Forum für Thread-/Post-Warnungen freigegeben?
 *
 * @param int $fid Forum-ID
 * @return bool
 */
function contentnotes_forum_allowed(int $fid): bool
{
    global $mybb;

    $forums = (string)($mybb->settings['contentnotes_forums'] ?? '');
    if ($forums === '-1') {
        return true;
    }
    if ($forums === '') {
        return false;
    }

    return in_array((string)$fid, array_map('trim', explode(',', $forums)), true);
}

/**
 * Darf das angemeldete Mitglied in diesem Forum Thread-/Post-Warnungen setzen?
 *
 * @param int $fid Forum-ID
 * @return bool
 */
function contentnotes_user_can_set(int $fid): bool
{
    return contentnotes_user_in_allowed_group() && contentnotes_forum_allowed($fid);
}

/**
 * Wurde das Formular mit den Warnungsfeldern mitgesendet?
 *
 * @return bool
 */
function contentnotes_form_submitted(): bool
{
    global $mybb;

    return $mybb->get_input('contentnotes_form', MyBB::INPUT_INT) === 1;
}

/**
 * Liest die gewählten Warnungen aus dem Request.
 *
 * @return array cid => Freitext (nur angehakte Kategorien, Freitext auf 255 Zeichen geprüft separat)
 */
function contentnotes_read_input(): array
{
    global $mybb;

    $raw    = $mybb->get_input('contentnotes', MyBB::INPUT_ARRAY);
    $result = [];
    foreach ($raw as $cid => $fields) {
        $cid = (int)$cid;
        if ($cid <= 0 || !is_array($fields) || empty($fields['on'])) {
            continue;
        }
        $note = isset($fields['note']) && is_string($fields['note']) ? trim($fields['note']) : '';
        // Zeilenumbrüche und Steuerzeichen raus: der Hinweis ist eine einzelne Zeile
        $note = trim((string)preg_replace('/[\x00-\x1F\x7F]+/', ' ', $note));
        $result[$cid] = $note;
    }

    return $result;
}

/**
 * Ist irgendein Freitext länger als 255 Zeichen?
 *
 * @param array $cid_to_note cid => Freitext
 * @return bool
 */
function contentnotes_note_too_long(array $cid_to_note): bool
{
    foreach ($cid_to_note as $note) {
        if (my_strlen((string)$note) > 255) {
            return true;
        }
    }

    return false;
}

/**
 * Filtert die Eingabe auf zulässige Kategorien: aktive oder bereits gesetzte.
 *
 * @param array $cid_to_note cid => Freitext
 * @param array $existing    Bereits gesetzte Kategorien (cid => Freitext)
 * @return array
 */
function contentnotes_filter_input(array $cid_to_note, array $existing = []): array
{
    $categories = contentnotes_db_get_categories();
    $result     = [];
    foreach ($cid_to_note as $cid => $note) {
        $cid = (int)$cid;
        if (!isset($categories[$cid])) {
            continue;
        }
        if (empty($categories[$cid]['active']) && !isset($existing[$cid])) {
            continue;
        }
        $result[$cid] = my_substr((string)$note, 0, 255);
    }

    return $result;
}

/**
 * Baut das Formular-Fragment (Tabellenzeile bzw. Quick-Reply-Zeile).
 *
 * @param array $selected cid => Freitext der aktuell gewählten Kategorien
 * @param bool  $quick    true = kompakte Variante für die Schnellantwort
 * @return string
 */
function contentnotes_build_form(array $selected, bool $quick = false): string
{
    global $templates, $lang, $mybb, $theme;

    contentnotes_load_lang();

    $categories = contentnotes_db_get_categories();
    $rows       = '';
    foreach ($categories as $cid => $category) {
        // Inaktive Kategorien nur zeigen, wenn sie bereits gesetzt sind (damit man sie entfernen kann)
        if (empty($category['active']) && !isset($selected[$cid])) {
            continue;
        }
        $cid_value = (int)$cid;
        $name      = htmlspecialchars_uni($category['name']);
        $checked   = isset($selected[$cid]) ? ' checked="checked"' : '';
        $note      = htmlspecialchars_uni(isset($selected[$cid]) ? (string)$selected[$cid] : '');
        $placeholder = !empty($category['require_note'])
            ? $lang->contentnotes_note_placeholder_required
            : $lang->contentnotes_note_placeholder;
        eval('$rows .= "' . $templates->get('contentnotes_form_row') . '";');
    }

    if ($rows === '') {
        return '';
    }

    $open_attr = $selected ? ' open="open"' : '';
    $form      = '';
    $template  = $quick ? 'contentnotes_quickreply' : 'contentnotes_form';
    eval('$form = "' . $templates->get($template) . '";');

    return $form;
}

/**
 * Gemeinsame Vorbereitung: Formular für neuen Thread bzw. neue Antwort (inkl. Entwurf bearbeiten).
 *
 * @param int    $fid   Forum-ID
 * @param string $type  'thread' oder 'post' (für das Vorbelegen beim Bearbeiten eines Entwurfs)
 * @param int    $refid tid bzw. pid des Entwurfs (0 = kein Entwurf)
 * @return string
 */
function contentnotes_render_new_form(int $fid, string $type, int $refid = 0): string
{
    global $mybb;

    if (!contentnotes_user_can_set($fid)) {
        return '';
    }

    if (contentnotes_form_submitted()) {
        $selected = contentnotes_read_input();
    } elseif ($refid > 0 && $mybb->get_input('action') === 'editdraft') {
        $notes    = contentnotes_db_get_notes($type, [$refid]);
        $selected = $notes[$refid] ?? [];
    } else {
        $selected = [];
    }

    return contentnotes_build_form($selected);
}

/**
 * Hook newthread_start: Formular für Thread-Warnungen.
 *
 * @return void
 */
function contentnotes_handle_newthread_form(): void
{
    global $contentnotes_form, $fid, $mybb;

    $contentnotes_form = contentnotes_render_new_form((int)$fid, 'thread', $mybb->get_input('tid', MyBB::INPUT_INT));
}

/**
 * Hook newreply_start: Formular für Post-Warnungen.
 *
 * @return void
 */
function contentnotes_handle_newreply_form(): void
{
    global $contentnotes_form, $fid, $thread, $mybb;

    $forum_id          = (int)($fid ?: ($thread['fid'] ?? 0));
    $contentnotes_form = contentnotes_render_new_form($forum_id, 'post', $mybb->get_input('pid', MyBB::INPUT_INT));
}

/**
 * Hook editpost_end: Formular beim Bearbeiten (erster Post = Thread-Warnungen, sonst Post-Warnungen).
 *
 * @return void
 */
function contentnotes_handle_editpost_form(): void
{
    global $contentnotes_form, $fid, $thread, $post, $pid;

    $contentnotes_form = '';

    $forum_id = (int)($fid ?: ($thread['fid'] ?? 0));
    if (!contentnotes_user_can_set($forum_id)) {
        return;
    }

    if (contentnotes_form_submitted()) {
        $selected = contentnotes_read_input();
    } else {
        $post_id  = (int)($pid ?: ($post['pid'] ?? 0));
        $is_first = isset($thread['firstpost']) && (int)$thread['firstpost'] === $post_id;
        $type     = $is_first ? 'thread' : 'post';
        $refid    = $is_first ? (int)$thread['tid'] : $post_id;
        $notes    = contentnotes_db_get_notes($type, [$refid]);
        $selected = $notes[$refid] ?? [];
    }

    $contentnotes_form = contentnotes_build_form($selected);
}

/**
 * Hook datahandler_post_validate_thread / _post: Freitext-Länge prüfen.
 *
 * @param object $handler PostDataHandler
 * @return void
 */
function contentnotes_handle_validate(&$handler): void
{
    global $lang;

    static $done = [];

    $key = spl_object_hash($handler);
    if (isset($done[$key])) {
        return;
    }
    $done[$key] = true;

    // Ohne abgeschicktes Formular zählt nur die [cw]-Prüfung der Nachricht
    $input = contentnotes_form_submitted() ? contentnotes_read_input() : [];
    if (contentnotes_note_too_long($input)) {
        contentnotes_load_lang();
        $handler->set_error('contentnotes_note_too_long');
    }

    // Kategorien mit Pflicht-Freitext: Formular (Thread/Beitrag) und [cw]-Passagen prüfen
    $missing = contentnotes_missing_required_notes($input, (string)($handler->data['message'] ?? ''));
    if ($missing) {
        contentnotes_load_lang();
        foreach ($missing as $name) {
            $handler->set_error('contentnotes_note_required', [$name]);
        }
    }
}

/**
 * Welche Kategorien mit Pflicht-Freitext haben keinen Text?
 *
 * Geprüft werden angehakte Kategorien des Formulars (nur aktive oder gesetzte; unbekannte werden
 * ohnehin verworfen) und [cw=kuerzel]…[/cw]-Passagen in der Nachricht.
 *
 * @param array  $cid_to_note Formulareingabe (cid => Freitext)
 * @param string $message     Nachrichtentext (Rohtext)
 * @return array Namen der betroffenen Kategorien (ohne Duplikate)
 */
function contentnotes_missing_required_notes(array $cid_to_note, string $message): array
{
    $categories = contentnotes_db_get_categories();
    $missing    = [];

    foreach ($cid_to_note as $cid => $note) {
        $cid = (int)$cid;
        if (isset($categories[$cid]) && !empty($categories[$cid]['require_note']) && trim((string)$note) === '') {
            $missing[$cid] = $categories[$cid]['name'];
        }
    }

    if ($message !== '' && stripos($message, '[cw') !== false) {
        preg_match_all(contentnotes_inline_pattern(), $message, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            list($slug, $raw_note) = contentnotes_split_attr((string)($m[1] ?? ''));
            $category = $slug !== '' ? contentnotes_db_get_category_by_slug($slug) : null;
            if ($category !== null && !empty($category['require_note']) && trim(strip_tags($raw_note)) === '') {
                $missing[(int)$category['cid']] = $category['name'];
            }
        }
    }

    return array_values($missing);
}

/**
 * Speichert Warnungen für ein Element, wenn Formular und Recht vorhanden sind.
 *
 * @param string $type   'thread' oder 'post'
 * @param int    $refid  tid bzw. pid
 * @param int    $fid    Forum-ID
 * @param bool   $merge  true = nur ergänzen (Doppelpost-Zusammenführung)
 * @return void
 */
function contentnotes_save_for(string $type, int $refid, int $fid, bool $merge = false): void
{
    global $plugins;

    if ($refid <= 0 || !contentnotes_form_submitted() || !contentnotes_user_can_set($fid)) {
        return;
    }

    $existing = contentnotes_db_get_notes($type, [$refid]);
    $existing = $existing[$refid] ?? [];
    $input    = contentnotes_filter_input(contentnotes_read_input(), $existing);

    if ($merge) {
        contentnotes_db_add_notes($type, $refid, $input);
    } else {
        contentnotes_db_replace_notes($type, $refid, $input);
    }

    $args = ['type' => $type, 'refid' => $refid, 'cids' => array_keys($input)];
    $plugins->run_hooks('contentnotes_notes_saved', $args);
}

/**
 * Hook datahandler_post_insert_thread_end: Thread-Warnungen speichern.
 *
 * @param object $handler PostDataHandler
 * @return void
 */
function contentnotes_handle_insert_thread(&$handler): void
{
    $tid = (int)($handler->return_values['tid'] ?? $handler->tid ?? 0);
    $fid = (int)($handler->data['fid'] ?? 0);

    contentnotes_save_for('thread', $tid, $fid);
}

/**
 * Hook datahandler_post_insert_post_end: Post-Warnungen speichern.
 *
 * @param object $handler PostDataHandler
 * @return void
 */
function contentnotes_handle_insert_post(&$handler): void
{
    $pid = (int)($handler->return_values['pid'] ?? $handler->pid ?? 0);
    $fid = (int)($handler->data['fid'] ?? 0);

    contentnotes_save_for('post', $pid, $fid);
}

/**
 * Hook datahandler_post_insert_merge: Warnungen an den zusammengeführten Post ergänzen.
 *
 * @param object $handler PostDataHandler
 * @return void
 */
function contentnotes_handle_insert_merge(&$handler): void
{
    $pid = (int)($handler->return_values['pid'] ?? 0);
    $fid = (int)($handler->data['fid'] ?? 0);

    contentnotes_save_for('post', $pid, $fid, true);
}

/**
 * Hook datahandler_post_update_end: Warnungen beim Bearbeiten speichern
 * (erster Post des Threads = Thread-Warnungen, sonst Post-Warnungen).
 *
 * @param object $handler PostDataHandler
 * @return void
 */
function contentnotes_handle_update_post(&$handler): void
{
    global $db;

    $pid = (int)($handler->data['pid'] ?? 0);
    if ($pid <= 0 || !contentnotes_form_submitted()) {
        return;
    }

    $row = $db->fetch_array($db->query(
        'SELECT p.tid, p.fid, t.firstpost FROM ' . TABLE_PREFIX . 'posts p '
        . 'LEFT JOIN ' . TABLE_PREFIX . 'threads t ON (t.tid = p.tid) WHERE p.pid = ' . $pid . ' LIMIT 1'
    ));
    if (!$row) {
        return;
    }

    if ((int)$row['firstpost'] === $pid) {
        contentnotes_save_for('thread', (int)$row['tid'], (int)$row['fid']);
    } else {
        contentnotes_save_for('post', $pid, (int)$row['fid']);
    }
}

/**
 * Hook class_moderation_delete_thread_start: Post-IDs des Threads vor dem Löschen merken.
 *
 * @param int $tid Thread-ID
 * @return int
 */
function contentnotes_handle_delete_thread_start($tid)
{
    global $db;

    $pids  = [];
    $query = $db->simple_select('posts', 'pid', 'tid = ' . (int)$tid);
    while ($row = $db->fetch_array($query)) {
        $pids[] = (int)$row['pid'];
    }
    contentnotes_pending_deleted_pids((int)$tid, $pids);

    return $tid;
}

/**
 * Merkt Post-IDs zu gelöschten Threads zwischen _start und dem eigentlichen Löschen.
 *
 * @param int        $tid  Thread-ID
 * @param array|null $pids Zu merkende pids (null = lesen und vergessen)
 * @return array
 */
function contentnotes_pending_deleted_pids(int $tid, ?array $pids = null): array
{
    static $pending = [];

    if ($pids !== null) {
        $pending[$tid] = $pids;

        return $pids;
    }

    $result = $pending[$tid] ?? [];
    unset($pending[$tid]);

    return $result;
}

/**
 * Hook class_moderation_delete_thread: Thread- und Post-Warnungen entfernen.
 *
 * @param int $tid Thread-ID
 * @return int
 */
function contentnotes_handle_delete_thread($tid)
{
    contentnotes_db_delete_notes('thread', [(int)$tid]);
    contentnotes_db_delete_notes('post', contentnotes_pending_deleted_pids((int)$tid));

    return $tid;
}

/**
 * Hook class_moderation_delete_post: Post-Warnungen entfernen.
 *
 * @param int $pid Post-ID
 * @return int
 */
function contentnotes_handle_delete_post($pid)
{
    contentnotes_db_delete_notes('post', [(int)$pid]);

    return $pid;
}
