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
 * Position des letzten öffnenden Tags `<name …>` vor $pos (oder false).
 *
 * @param string $html Seite
 * @param int    $pos  Suche endet vor dieser Position
 * @param string $name Tag-Name, z.B. 'tr' oder 'div'
 * @return int|false
 */
function contentnotes_find_open_tag_before(string $html, int $pos, string $name)
{
    $needle = '<' . $name;
    $head   = substr($html, 0, $pos);

    while (($start = strripos($head, $needle)) !== false) {
        $next = substr($head, $start + strlen($needle), 1);
        if ($next === ' ' || $next === '>' || $next === "\t" || $next === "\n" || $next === "\r") {
            return $start;
        }
        $head = substr($head, 0, $start);
    }

    return false;
}

/**
 * Entfernt Elemente, die für den Betrachtenden komplett ausgeblendet sind:
 * Thread-Zeilen (Marker steht in der `<tr>`) und Posts (Marker steht im Post-Container).
 * Lässt sich ein Element nicht sicher abgrenzen, wird nur der Marker entfernt (bei Posts bleibt dann
 * die kurze Hinweiszeile statt der Nachricht stehen, es wird also nie Inhalt ausgeliefert).
 *
 * @param string $html Fertige Seite
 * @return string
 */
function contentnotes_strip_hidden(string $html): string
{
    // Thread-Zeilen
    $guard = 0;
    while ($guard++ < 500 && preg_match('/<!--contentnotes-hide-thread:(\d+)-->/', $html, $m, PREG_OFFSET_CAPTURE)) {
        $pos   = $m[0][1];
        $len   = strlen($m[0][0]);
        $start = contentnotes_find_open_tag_before($html, $pos, 'tr');
        $end   = stripos($html, '</tr>', $pos);

        if ($start === false || $end === false) {
            $html = substr_replace($html, '', $pos, $len);
            continue;
        }
        $html = substr_replace($html, '', $start, $end + 5 - $start);
    }

    // Posts
    $guard = 0;
    while ($guard++ < 500 && preg_match('/<!--contentnotes-hide-post:(\d+)-->/', $html, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1];
        $len = strlen($m[0][0]);
        $pid = (int)$m[1][0];

        $removed = false;
        $id_pos  = strpos($html, 'id="post_' . $pid . '"');
        if ($id_pos !== false && $id_pos < $pos) {
            $start = contentnotes_find_open_tag_before($html, $id_pos, 'div');
            if ($start !== false && $start < $pos) {
                // Ende des Post-Containers per Div-Balance suchen
                $depth  = 0;
                $offset = $start;
                $end    = false;
                while (preg_match('#<(/?)div\b[^>]*>#i', $html, $t, PREG_OFFSET_CAPTURE, $offset)) {
                    $depth  += ($t[1][0] === '/') ? -1 : 1;
                    $offset  = $t[0][1] + strlen($t[0][0]);
                    if ($depth === 0) {
                        $end = $offset;
                        break;
                    }
                }

                if ($end !== false && $end > $pos) {
                    // Davorstehenden Anker <a name="pidN" id="pidN"></a> mitnehmen
                    $before = substr($html, max(0, $start - 120), min(120, $start));
                    if (preg_match('#<a name="pid' . $pid . '" id="pid' . $pid . '"></a>\s*$#', $before, $a)) {
                        $start -= strlen($a[0]);
                    }
                    $html    = substr_replace($html, '', $start, $end - $start);
                    $removed = true;
                }
            }
        }

        if (!$removed) {
            $html = substr_replace($html, '', $pos, $len);
        }
    }

    return $html;
}

/**
 * Hook pre_output_page: bindet das Script ein und stellt dem Editor die Kategorien bereit.
 *
 * Bewusst am Ausgabe-Hook statt an global_intermediate: $headerinclude wird in global.php erst
 * nach global_intermediate aus dem Template gebaut, ein Anhängen dort ginge verloren.
 *
 * @param string $contents Fertige HTML-Seite
 * @return string
 */
function contentnotes_handle_output($contents)
{
    global $mybb, $lang;

    if (!is_string($contents)) {
        return $contents;
    }

    // Komplett ausgeblendete Threads/Posts aus der fertigen Seite entfernen
    if (strpos($contents, '<!--contentnotes-hide-') !== false) {
        $contents = contentnotes_strip_hidden($contents);
    }

    if (!contentnotes_is_enabled() || strpos($contents, '</head>') === false) {
        return $contents;
    }
    if (strpos($contents, '/jscripts/contentnotes.js') !== false) {
        return $contents; // schon eingebunden
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

    $block = $config
        . '<script type="text/javascript" src="' . $base . '/jscripts/contentnotes.js?ver=' . $version . '"></script>' . "\n";

    // Vor dem ersten </head> einfügen (hinter jQuery und den Core-Scripts)
    return substr_replace($contents, $block, (int)stripos($contents, '</head>'), 0);
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

    // „Komplett ausblenden“: Zeile später aus der fertigen Seite entfernen (Marker in der Zeile)
    if (contentnotes_viewer_hides(array_column($described, 'cid'))) {
        $thread['contentnotes_badge'] = '<!--contentnotes-hide-thread:' . $tid . '-->';

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

    $action = contentnotes_viewer_action(array_column($described, 'cid'));

    // Komplett ausblenden: direkter Aufruf zeigt nur einen Hinweis
    if ($action === 'hide') {
        error($lang->contentnotes_thread_hidden_message, $lang->contentnotes_thread_hidden_title);
    }

    // Alle Kategorien auf „einblenden“: weder Kasten noch Verdecken
    if ($action !== 'warn') {
        return;
    }

    $items = contentnotes_render_note_items($described);
    eval('$contentnotes_thread_banner = "' . $templates->get('contentnotes_thread_banner') . '";');
    $contentnotes_posts_attr = ' class="contentnotes_veiled" data-contentnotes-veil="thread"';
}

/**
 * Wendet die Einstellung des Betrachtenden auf einen Post an: einblenden (unverändert), Warnkopf mit
 * verdeckter Nachricht oder komplett ausblenden (Marker; der Post wird aus der fertigen Seite entfernt).
 *
 * @param array $post        Post-Daten (message wird ersetzt)
 * @param array $cid_to_note cid => Freitext
 * @param bool  $allow_hide  false = „ausblenden“ wie „Warnung“ behandeln (Vorschau: Autor*innen sehen den Text)
 * @return void
 */
function contentnotes_veil_post(array &$post, array $cid_to_note, bool $allow_hide = true): void
{
    global $templates, $lang, $mybb, $theme;

    $described = contentnotes_describe_notes($cid_to_note);
    if (!$described) {
        return;
    }

    $action = contentnotes_viewer_action(array_column($described, 'cid'));
    if ($action === 'show') {
        return;
    }

    contentnotes_load_lang();

    if ($action === 'hide' && $allow_hide) {
        // Die Nachricht wird nie ausgeliefert. Der Marker lässt den ganzen Post entfernen; klappt das
        // nicht (unausgeglichenes HTML), bleibt nur diese Hinweiszeile stehen.
        $post['message'] = '<!--contentnotes-hide-post:' . (int)($post['pid'] ?? 0) . '-->'
            . '<div class="contentnotes_hidden_note smalltext">' . htmlspecialchars_uni((string)$lang->contentnotes_post_hidden_fallback) . '</div>';

        return;
    }

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
        contentnotes_veil_post($post, $input, false);
    }
}
