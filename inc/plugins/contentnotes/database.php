<?php
/**
 * Content Notes – Datenbank-Schicht: Kategorien, Markierungen (Notes), Vorlieben (Prefs).
 *
 * @package ContentNotes
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

/**
 * Prüft, ob der Markierungstyp erlaubt ist.
 *
 * @param string $type Typ
 * @return bool
 */
function contentnotes_valid_type(string $type): bool
{
    return $type === 'thread' || $type === 'post';
}

/**
 * Prüft ein Kategorie-Kürzel (a-z, 0-9, Unterstrich, Bindestrich; 1–50 Zeichen).
 *
 * @param string $slug Kürzel
 * @return bool
 */
function contentnotes_valid_slug(string $slug): bool
{
    return (bool)preg_match('/^[a-z0-9_-]{1,50}$/', $slug);
}

/**
 * Prozessweiter Zwischenspeicher für die Kategorien.
 *
 * @param array|null $set Neue Werte setzen (null = nur lesen)
 * @return array|null
 */
function contentnotes_db_categories_store(?array $set = null)
{
    static $store = null;

    if ($set !== null) {
        $store = $set;
    }

    return $store;
}

/**
 * Baut den Datacache der Kategorien neu auf (cid => Zeile, sortiert nach Sortierung/Name).
 *
 * @return array
 */
function contentnotes_db_rebuild_cache(): array
{
    global $db, $cache;

    $categories = [];
    $query      = $db->simple_select('contentnotes_categories', '*', '', [
        'order_by'  => 'disporder ASC, name',
        'order_dir' => 'ASC',
    ]);
    while ($row = $db->fetch_array($query)) {
        $cid              = (int)$row['cid'];
        $categories[$cid] = [
            'cid'         => $cid,
            'name'        => (string)$row['name'],
            'slug'        => (string)$row['slug'],
            'description' => (string)($row['description'] ?? ''),
            'active'      => (int)$row['active'],
            'disporder'   => (int)$row['disporder'],
        ];
    }

    $cache->update('contentnotes_categories', $categories);
    contentnotes_db_categories_store($categories);

    return $categories;
}

/**
 * Liefert die Kategorien (cid => Zeile) aus dem Datacache.
 *
 * @param bool $active_only Nur aktive Kategorien
 * @return array
 */
function contentnotes_db_get_categories(bool $active_only = false): array
{
    global $cache;

    $all = contentnotes_db_categories_store();
    if ($all === null) {
        $data = $cache->read('contentnotes_categories');
        $all  = is_array($data) ? $data : contentnotes_db_rebuild_cache();
        contentnotes_db_categories_store($all);
    }

    if (!$active_only) {
        return $all;
    }

    $active = [];
    foreach ($all as $cid => $category) {
        if (!empty($category['active'])) {
            $active[$cid] = $category;
        }
    }

    return $active;
}

/**
 * Liefert eine Kategorie oder null.
 *
 * @param int $cid Kategorie-ID
 * @return array|null
 */
function contentnotes_db_get_category(int $cid): ?array
{
    $all = contentnotes_db_get_categories();

    return $all[$cid] ?? null;
}

/**
 * Liefert eine Kategorie anhand des Kürzels oder null.
 *
 * @param string $slug Kürzel
 * @return array|null
 */
function contentnotes_db_get_category_by_slug(string $slug): ?array
{
    foreach (contentnotes_db_get_categories() as $category) {
        if ($category['slug'] === $slug) {
            return $category;
        }
    }

    return null;
}

/**
 * Prüft, ob ein Kürzel bereits vergeben ist.
 *
 * @param string $slug        Kürzel
 * @param int    $exclude_cid Diese Kategorie ignorieren (beim Bearbeiten)
 * @return bool
 */
function contentnotes_db_slug_exists(string $slug, int $exclude_cid = 0): bool
{
    global $db;

    $query = $db->simple_select(
        'contentnotes_categories',
        'cid',
        "slug = '" . $db->escape_string($slug) . "' AND cid != " . (int)$exclude_cid,
        ['limit' => 1]
    );

    return (bool)$db->fetch_array($query);
}

/**
 * Legt eine Kategorie an und baut den Cache neu auf.
 *
 * @param array $data name, slug, description, active, disporder
 * @return int Neue Kategorie-ID
 */
function contentnotes_db_insert_category(array $data): int
{
    global $db;

    $cid = (int)$db->insert_query('contentnotes_categories', [
        'name'        => $db->escape_string((string)($data['name'] ?? '')),
        'slug'        => $db->escape_string((string)($data['slug'] ?? '')),
        'description' => $db->escape_string((string)($data['description'] ?? '')),
        'active'      => empty($data['active']) ? 0 : 1,
        'disporder'   => (int)($data['disporder'] ?? 0),
    ]);

    contentnotes_db_rebuild_cache();

    return $cid;
}

/**
 * Aktualisiert eine Kategorie und baut den Cache neu auf.
 *
 * @param int   $cid  Kategorie-ID
 * @param array $data Zu ändernde Felder (name, slug, description, active, disporder)
 * @return void
 */
function contentnotes_db_update_category(int $cid, array $data): void
{
    global $db;

    $update = [];
    if (array_key_exists('name', $data)) {
        $update['name'] = $db->escape_string((string)$data['name']);
    }
    if (array_key_exists('slug', $data)) {
        $update['slug'] = $db->escape_string((string)$data['slug']);
    }
    if (array_key_exists('description', $data)) {
        $update['description'] = $db->escape_string((string)$data['description']);
    }
    if (array_key_exists('active', $data)) {
        $update['active'] = empty($data['active']) ? 0 : 1;
    }
    if (array_key_exists('disporder', $data)) {
        $update['disporder'] = (int)$data['disporder'];
    }

    if ($update) {
        $db->update_query('contentnotes_categories', $update, 'cid = ' . (int)$cid);
        contentnotes_db_rebuild_cache();
    }
}

/**
 * Löscht eine Kategorie samt Markierungen und Vorlieben und baut den Cache neu auf.
 *
 * @param int $cid Kategorie-ID
 * @return void
 */
function contentnotes_db_delete_category(int $cid): void
{
    global $db;

    $cid = (int)$cid;
    $db->delete_query('contentnotes_notes', 'cid = ' . $cid);
    $db->delete_query('contentnotes_userprefs', 'cid = ' . $cid);
    $db->delete_query('contentnotes_categories', 'cid = ' . $cid);

    contentnotes_db_rebuild_cache();
}

/**
 * Zählt die Markierungen pro Kategorie.
 *
 * @return array cid => Anzahl
 */
function contentnotes_db_count_notes_per_category(): array
{
    global $db;

    $counts = [];
    $query  = $db->query('SELECT cid, COUNT(*) AS total FROM ' . TABLE_PREFIX . 'contentnotes_notes GROUP BY cid');
    while ($row = $db->fetch_array($query)) {
        $counts[(int)$row['cid']] = (int)$row['total'];
    }

    return $counts;
}

/**
 * Liest die Markierungen für mehrere Threads bzw. Posts.
 *
 * @param string $type   'thread' oder 'post'
 * @param array  $refids tids bzw. pids
 * @return array refid => [cid => Freitext]
 */
function contentnotes_db_get_notes(string $type, array $refids): array
{
    global $db;

    if (!contentnotes_valid_type($type)) {
        return [];
    }

    $refids = array_values(array_unique(array_filter(array_map('intval', $refids))));
    if (!$refids) {
        return [];
    }

    $result = [];
    $query  = $db->simple_select(
        'contentnotes_notes',
        'refid, cid, note',
        "type = '" . $db->escape_string($type) . "' AND refid IN (" . implode(',', $refids) . ')',
        ['order_by' => 'nid', 'order_dir' => 'ASC']
    );
    while ($row = $db->fetch_array($query)) {
        $result[(int)$row['refid']][(int)$row['cid']] = (string)$row['note'];
    }

    return $result;
}

/**
 * Liest alle Post-Markierungen eines Threads (ein Query für die ganze Seite).
 *
 * @param int $tid Thread-ID
 * @return array pid => [cid => Freitext]
 */
function contentnotes_db_get_post_notes_for_thread(int $tid): array
{
    global $db;

    $result = [];
    $query  = $db->query(
        'SELECT n.refid, n.cid, n.note FROM ' . TABLE_PREFIX . 'contentnotes_notes n '
        . 'INNER JOIN ' . TABLE_PREFIX . "posts p ON (p.pid = n.refid) WHERE n.type = 'post' AND p.tid = " . (int)$tid
        . ' ORDER BY n.nid ASC'
    );
    while ($row = $db->fetch_array($query)) {
        $result[(int)$row['refid']][(int)$row['cid']] = (string)$row['note'];
    }

    return $result;
}

/**
 * Ersetzt alle Markierungen eines Threads bzw. Posts.
 *
 * @param string $type          'thread' oder 'post'
 * @param int    $refid         tid bzw. pid
 * @param array  $cid_to_note   cid => Freitext
 * @return void
 */
function contentnotes_db_replace_notes(string $type, int $refid, array $cid_to_note): void
{
    global $db;

    if (!contentnotes_valid_type($type) || $refid <= 0) {
        return;
    }

    contentnotes_db_delete_notes($type, [$refid]);
    contentnotes_db_add_notes($type, $refid, $cid_to_note);
}

/**
 * Ergänzt Markierungen, ohne vorhandene zu löschen (bei vorhandener Kategorie wird der
 * Freitext nur überschrieben, wenn ein neuer angegeben ist).
 *
 * @param string $type        'thread' oder 'post'
 * @param int    $refid       tid bzw. pid
 * @param array  $cid_to_note cid => Freitext
 * @return void
 */
function contentnotes_db_add_notes(string $type, int $refid, array $cid_to_note): void
{
    global $db;

    if (!contentnotes_valid_type($type) || $refid <= 0) {
        return;
    }

    $type_sql = $db->escape_string($type);
    foreach ($cid_to_note as $cid => $note) {
        $cid  = (int)$cid;
        $note = (string)$note;
        if ($cid <= 0) {
            continue;
        }

        $existing = $db->fetch_array($db->simple_select(
            'contentnotes_notes',
            'nid',
            "type = '{$type_sql}' AND refid = " . (int)$refid . " AND cid = {$cid}",
            ['limit' => 1]
        ));

        if ($existing) {
            if ($note !== '') {
                $db->update_query('contentnotes_notes', ['note' => $db->escape_string($note)], 'nid = ' . (int)$existing['nid']);
            }
            continue;
        }

        $db->insert_query('contentnotes_notes', [
            'type'  => $type_sql,
            'refid' => (int)$refid,
            'cid'   => $cid,
            'note'  => $db->escape_string($note),
        ]);
    }
}

/**
 * Löscht alle Markierungen der angegebenen Threads bzw. Posts.
 *
 * @param string $type   'thread' oder 'post'
 * @param array  $refids tids bzw. pids
 * @return void
 */
function contentnotes_db_delete_notes(string $type, array $refids): void
{
    global $db;

    if (!contentnotes_valid_type($type)) {
        return;
    }

    $refids = array_values(array_unique(array_filter(array_map('intval', $refids))));
    if (!$refids) {
        return;
    }

    $db->delete_query(
        'contentnotes_notes',
        "type = '" . $db->escape_string($type) . "' AND refid IN (" . implode(',', $refids) . ')'
    );
}

/**
 * Liest die Vorlieben eines Mitglieds.
 *
 * @param int  $uid     Mitglieds-ID
 * @param bool $refresh true = den im Request gemerkten Stand verwerfen und neu lesen
 * @return array cid => Modus (0 = Warnung, 1 = einblenden, 2 = komplett ausblenden)
 */
function contentnotes_db_get_user_prefs(int $uid, bool $refresh = false): array
{
    global $db;

    static $memo = [];

    $uid = (int)$uid;
    if ($uid <= 0) {
        return [];
    }
    if (!$refresh && isset($memo[$uid])) {
        return $memo[$uid];
    }

    $prefs = [];
    $query = $db->simple_select('contentnotes_userprefs', 'cid, mode', 'uid = ' . $uid);
    while ($row = $db->fetch_array($query)) {
        $prefs[(int)$row['cid']] = (int)$row['mode'];
    }
    $memo[$uid] = $prefs;

    return $prefs;
}

/**
 * Speichert die Vorlieben eines Mitglieds (ersetzt alle bisherigen).
 *
 * @param int   $uid          Mitglieds-ID
 * @param array $cid_to_mode  cid => Modus (0 = Warnung, 1 = einblenden, 2 = komplett ausblenden)
 * @return void
 */
function contentnotes_db_save_user_prefs(int $uid, array $cid_to_mode): void
{
    global $db;

    $uid = (int)$uid;
    if ($uid <= 0) {
        return;
    }

    $db->delete_query('contentnotes_userprefs', 'uid = ' . $uid);
    foreach ($cid_to_mode as $cid => $mode) {
        $cid = (int)$cid;
        if ($cid <= 0) {
            continue;
        }
        $db->insert_query('contentnotes_userprefs', [
            'uid'  => $uid,
            'cid'  => $cid,
            'mode' => contentnotes_normalize_mode($mode),
        ]);
    }

    contentnotes_db_get_user_prefs($uid, true); // gemerkten Stand aktualisieren
}

/**
 * Löscht die Vorlieben der angegebenen Mitglieder.
 *
 * @param array $uids Mitglieds-IDs
 * @return void
 */
function contentnotes_db_delete_user_prefs(array $uids): void
{
    global $db;

    $uids = array_values(array_unique(array_filter(array_map('intval', $uids))));
    if ($uids) {
        $db->delete_query('contentnotes_userprefs', 'uid IN (' . implode(',', $uids) . ')');
        foreach ($uids as $uid) {
            contentnotes_db_get_user_prefs($uid, true); // gemerkten Stand aktualisieren
        }
    }
}

/**
 * Hook datahandler_user_delete_start: Vorlieben der zu löschenden Mitglieder entfernen.
 *
 * @param object $userhandler UserDataHandler
 * @return void
 */
function contentnotes_handle_user_delete(&$userhandler): void
{
    if (isset($userhandler->delete_uids) && is_array($userhandler->delete_uids)) {
        contentnotes_db_delete_user_prefs($userhandler->delete_uids);
    }
}

/**
 * Öffentliche API: Markierungen eines Threads bzw. Posts mit Kategorie-Daten.
 *
 * @param string $type  'thread' oder 'post'
 * @param int    $refid tid bzw. pid
 * @return array Liste aus ['cid', 'name', 'slug', 'note']
 */
function contentnotes_get_notes(string $type, int $refid): array
{
    $notes = contentnotes_db_get_notes($type, [$refid]);

    return contentnotes_describe_notes($notes[$refid] ?? []);
}

/**
 * Ergänzt cid => Freitext um Kategorie-Daten und sortiert nach Kategorie-Sortierung.
 * Unbekannte Kategorien (bereits gelöscht) werden verworfen.
 *
 * @param array $cid_to_note cid => Freitext
 * @return array Liste aus ['cid', 'name', 'slug', 'note']
 */
function contentnotes_describe_notes(array $cid_to_note): array
{
    $list = [];
    foreach (contentnotes_db_get_categories() as $cid => $category) {
        if (!isset($cid_to_note[$cid])) {
            continue;
        }
        $list[] = [
            'cid'  => (int)$cid,
            'name' => $category['name'],
            'slug' => $category['slug'],
            'note' => (string)$cid_to_note[$cid],
        ];
    }

    return $list;
}

/**
 * Begrenzt einen Modus auf die erlaubten Werte: 0 = Warnung, 1 = einblenden, 2 = komplett ausblenden.
 *
 * @param mixed $mode Rohwert
 * @return int
 */
function contentnotes_normalize_mode($mode): int
{
    $mode = (int)$mode;

    return ($mode === 1 || $mode === 2) ? $mode : 0;
}

/**
 * Was soll der aktuelle Betrachtende bei diesen Kategorien sehen?
 *
 * Die strengste Stufe gewinnt: hide > warn > show. Gäste bekommen immer die Warnung (nie
 * „ausblenden“); eine Kategorie ohne Eintrag zählt als Warnung. Ohne Kategorien: show.
 *
 * @param array $cids Kategorie-IDs
 * @return string 'hide', 'warn' oder 'show'
 */
function contentnotes_viewer_action(array $cids): string
{
    global $mybb;

    if (!$cids) {
        return 'show';
    }

    $uid = (int)($mybb->user['uid'] ?? 0);
    if ($uid <= 0) {
        return 'warn';
    }

    $prefs  = contentnotes_db_get_user_prefs($uid);
    $result = 'show';
    foreach ($cids as $cid) {
        $mode = isset($prefs[(int)$cid]) ? contentnotes_normalize_mode($prefs[(int)$cid]) : 0;
        if ($mode === 2) {
            return 'hide';
        }
        if ($mode === 0) {
            $result = 'warn';
        }
    }

    return $result;
}

/**
 * Öffentliche API: Soll der aktuelle Betrachtende bei diesen Kategorien eine (klickbare) Warnung sehen?
 * Gäste immer; Mitglieder, wenn die strengste Stufe „Warnung“ ist (kein Eintrag zählt als Warnung).
 *
 * @param array $cids Kategorie-IDs
 * @return bool
 */
function contentnotes_viewer_wants_warning(array $cids): bool
{
    return contentnotes_viewer_action($cids) === 'warn';
}

/**
 * Öffentliche API: Soll der aktuelle Betrachtende diese Kategorien komplett ausgeblendet bekommen?
 * Gäste nie.
 *
 * @param array $cids Kategorie-IDs
 * @return bool
 */
function contentnotes_viewer_hides(array $cids): bool
{
    return contentnotes_viewer_action($cids) === 'hide';
}
