<?php
/**
 * Content Notes – Anzeige: Header-Script, Thread-Badge, Thread-Banner, Post-Veil.
 *
 * @package ContentNotes
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

/**
 * Lädt die Frontend-Sprachdatei genau einmal pro Anfrage.
 *
 * @return void
 */
function contentnotes_load_lang(): void
{
    global $lang;

    static $loaded = false;

    if (!$loaded) {
        $lang->load('contentnotes');
        $loaded = true;
    }
}

/**
 * Hook global_intermediate: bindet das Script ein und stellt dem Editor die Kategorien bereit.
 *
 * @return void
 */
function contentnotes_handle_headerinclude(): void
{
    global $mybb, $headerinclude, $lang;

    if (!contentnotes_is_enabled()) {
        return;
    }

    contentnotes_load_lang();

    $base    = !empty($mybb->asset_url) ? $mybb->asset_url : $mybb->settings['bburl'];
    $version = str_replace('.', '', CONTENTNOTES_VERSION);
    $config  = '';

    if (!empty($mybb->settings['contentnotes_editor_button']) && contentnotes_user_in_allowed_group()) {
        $categories = [];
        foreach (contentnotes_db_get_categories(true) as $category) {
            $categories[] = ['slug' => $category['slug'], 'name' => $category['name']];
        }

        $data = [
            'canSet'     => true,
            'categories' => $categories,
            'lang'       => [
                'button'   => (string)$lang->contentnotes_editor_button,
                'category' => (string)$lang->contentnotes_editor_category,
                'note'     => (string)$lang->contentnotes_editor_note,
                'insert'   => (string)$lang->contentnotes_editor_insert,
                'none'     => (string)$lang->contentnotes_editor_none,
            ],
        ];

        $config = '<script type="text/javascript">var contentnotes_config = '
            . json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            . ";</script>\n";
    }

    $headerinclude .= "\n" . $config
        . '<script type="text/javascript" src="' . $base . '/jscripts/contentnotes.js?ver=' . $version . '"></script>' . "\n";
}

/**
 * Rendert die Liste der Kategorien (mit Freitext) als <li>-Einträge.
 *
 * @param array $described Liste aus ['cid', 'name', 'slug', 'note']
 * @return string
 */
function contentnotes_render_note_items(array $described): string
{
    global $templates, $lang, $mybb, $theme;

    $items = '';
    foreach ($described as $entry) {
        $name      = htmlspecialchars_uni($entry['name']);
        $note_html = $entry['note'] !== '' ? ': ' . htmlspecialchars_uni($entry['note']) : '';
        eval('$items .= "' . $templates->get('contentnotes_note_item') . '";');
    }

    return $items;
}

/**
 * Hook forumdisplay_thread / search_results_thread: Badge an der Thread-Zeile.
 *
 * @return void
 */
function contentnotes_handle_thread_badge(): void
{
    global $thread, $threadcache, $thread_cache, $templates, $lang, $mybb, $theme;

    static $notes = [];

    if (!is_array($thread)) {
        return;
    }
    $thread['contentnotes_badge'] = '';

    if (!contentnotes_is_enabled()) {
        return;
    }

    $tid = (int)($thread['tid'] ?? 0);
    if ($tid <= 0) {
        return;
    }

    if (!array_key_exists($tid, $notes)) {
        // Alle Threads der Seite in einem Query laden
        $batch = [$tid];
        foreach ([$threadcache ?? null, $thread_cache ?? null] as $page_threads) {
            if (is_array($page_threads)) {
                foreach (array_keys($page_threads) as $page_tid) {
                    $batch[] = (int)$page_tid;
                }
            }
        }
        $batch = array_values(array_diff(array_unique($batch), array_keys($notes)));
        $found = contentnotes_db_get_notes('thread', $batch);
        foreach ($batch as $batch_tid) {
            $notes[$batch_tid] = $found[$batch_tid] ?? [];
        }
    }

    $described = contentnotes_describe_notes($notes[$tid] ?? []);
    if (!$described) {
        return;
    }

    contentnotes_load_lang();

    $names  = [];
    $titles = [];
    foreach ($described as $entry) {
        $names[]  = htmlspecialchars_uni($entry['name']);
        $titles[] = htmlspecialchars_uni($entry['name'] . ($entry['note'] !== '' ? ': ' . $entry['note'] : ''));
    }
    $names  = implode(', ', $names);
    $titles = implode('; ', $titles);

    $badge = '';
    eval('$badge = "' . $templates->get('contentnotes_badge') . '";');
    $thread['contentnotes_badge'] = $badge;
}

/**
 * Hook showthread_start: Thread-Banner, Verdecken der Posts und Zeile in der Schnellantwort.
 *
 * @return void
 */
function contentnotes_handle_showthread_start(): void
{
    global $contentnotes_thread_banner, $contentnotes_posts_attr, $contentnotes_quickreply;
    global $thread, $fid, $templates, $lang, $mybb, $theme;

    $contentnotes_thread_banner = '';
    $contentnotes_posts_attr    = '';
    $contentnotes_quickreply    = '';

    if (!contentnotes_is_enabled()) {
        return;
    }

    contentnotes_load_lang();

    $forum_id = (int)($fid ?: ($thread['fid'] ?? 0));
    if (contentnotes_user_can_set($forum_id)) {
        $contentnotes_quickreply = contentnotes_build_form([], true);
    }

    $tid = (int)($thread['tid'] ?? 0);
    if ($tid <= 0) {
        return;
    }

    $notes     = contentnotes_db_get_notes('thread', [$tid]);
    $described = contentnotes_describe_notes($notes[$tid] ?? []);
    if (!$described) {
        return;
    }

    // Alle Kategorien auf „einblenden“: weder Kasten noch Verdecken
    if (!contentnotes_viewer_wants_warning(array_column($described, 'cid'))) {
        return;
    }

    $items = contentnotes_render_note_items($described);
    eval('$contentnotes_thread_banner = "' . $templates->get('contentnotes_thread_banner') . '";');
    $contentnotes_posts_attr = ' class="contentnotes_veiled" data-contentnotes-veil="thread"';
}

/**
 * Verdeckt die Nachricht eines Posts hinter einem Warnkopf (wenn der Betrachtende das möchte).
 *
 * @param array $post       Post-Daten (message wird ersetzt)
 * @param array $cid_to_note cid => Freitext
 * @return void
 */
function contentnotes_veil_post(array &$post, array $cid_to_note): void
{
    global $templates, $lang, $mybb, $theme;

    $described = contentnotes_describe_notes($cid_to_note);
    if (!$described || !contentnotes_viewer_wants_warning(array_column($described, 'cid'))) {
        return;
    }

    contentnotes_load_lang();

    $items   = contentnotes_render_note_items($described);
    $message = (string)($post['message'] ?? '');
    $veiled  = '';
    eval('$veiled = "' . $templates->get('contentnotes_post_veil') . '";');
    $post['message'] = $veiled;
}

/**
 * Hook postbit: Post-Warnung anwenden.
 *
 * @param array $post Post-Daten
 * @return void
 */
function contentnotes_handle_postbit(&$post): void
{
    static $by_thread = [];

    if (!contentnotes_is_enabled() || !is_array($post)) {
        return;
    }

    $tid = (int)($post['tid'] ?? 0);
    $pid = (int)($post['pid'] ?? 0);
    if ($tid <= 0 || $pid <= 0) {
        return;
    }

    if (!isset($by_thread[$tid])) {
        $by_thread[$tid] = contentnotes_db_get_post_notes_for_thread($tid);
    }
    if (empty($by_thread[$tid][$pid])) {
        return;
    }

    contentnotes_veil_post($post, $by_thread[$tid][$pid]);
}

/**
 * Hook postbit_prev: Vorschau mit den im Formular gewählten Warnungen.
 *
 * @param array $post Post-Daten
 * @return void
 */
function contentnotes_handle_postbit_preview(&$post): void
{
    if (!contentnotes_is_enabled() || !is_array($post) || !contentnotes_form_submitted()) {
        return;
    }

    $input = contentnotes_filter_input(contentnotes_read_input(), contentnotes_db_get_categories());
    if ($input) {
        contentnotes_veil_post($post, $input);
    }
}
