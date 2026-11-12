<?php
/**
 * Content Notes – Inhaltswarnungen für Threads, Posts und einzelne Textstellen.
 *
 * Hauptdatei: info(), Lifecycle (install/uninstall/activate/deactivate) und Hook-Registrierung.
 * Die eigentliche Logik liegt in inc/plugins/contentnotes/*.php.
 *
 * @package ContentNotes
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

define('CONTENTNOTES_VERSION', '1.1.0');
define('CONTENTNOTES_DIR', MYBB_ROOT . 'inc/plugins/contentnotes/');

// Die Module enthalten ausschließlich Funktionsdefinitionen (keine Seiteneffekte) und werden
// auch von install()/activate() gebraucht, deshalb immer geladen.
require_once CONTENTNOTES_DIR . 'database.php';
require_once CONTENTNOTES_DIR . 'forms.php';
require_once CONTENTNOTES_DIR . 'display.php';
require_once CONTENTNOTES_DIR . 'parser.php';
require_once CONTENTNOTES_DIR . 'usercp.php';

if (defined('IN_ADMINCP')) {
    // ACP: Config-Modul „Inhaltswarnungen“ registrieren
    $plugins->add_hook('admin_config_menu', 'contentnotes_admin_menu');
    $plugins->add_hook('admin_config_action_handler', 'contentnotes_admin_action');
    $plugins->add_hook('admin_config_permissions', 'contentnotes_admin_permissions');
    // Sprachdatei für settinggroup-/Setting-Titel auf der Einstellungsseite
    $plugins->add_hook('admin_config_settings_begin', 'contentnotes_admin_load_lang');
    // Sprachdatei für die Admin-Log-Zeilen (admin_log_config_contentnotes_*)
    $plugins->add_hook('admin_tools_adminlog_begin', 'contentnotes_admin_load_lang');
} else {
    // Seitenausgabe: Script + Editor-Konfiguration vor </head> einfügen
    $plugins->add_hook('pre_output_page', 'contentnotes_handle_output');

    // Formulare (Thread / Antwort / Bearbeiten)
    $plugins->add_hook('newthread_start', 'contentnotes_handle_newthread_form');
    $plugins->add_hook('newreply_start', 'contentnotes_handle_newreply_form');
    $plugins->add_hook('editpost_end', 'contentnotes_handle_editpost_form');

    // Validieren und Speichern
    $plugins->add_hook('datahandler_post_validate_thread', 'contentnotes_handle_validate');
    $plugins->add_hook('datahandler_post_validate_post', 'contentnotes_handle_validate');
    $plugins->add_hook('datahandler_post_insert_thread_end', 'contentnotes_handle_insert_thread');
    $plugins->add_hook('datahandler_post_insert_post_end', 'contentnotes_handle_insert_post');
    $plugins->add_hook('datahandler_post_insert_merge', 'contentnotes_handle_insert_merge');
    $plugins->add_hook('datahandler_post_update_end', 'contentnotes_handle_update_post');

    // Aufräumen
    $plugins->add_hook('class_moderation_delete_thread_start', 'contentnotes_handle_delete_thread_start');
    $plugins->add_hook('class_moderation_delete_thread', 'contentnotes_handle_delete_thread');
    $plugins->add_hook('class_moderation_delete_post', 'contentnotes_handle_delete_post');
    $plugins->add_hook('datahandler_user_delete_start', 'contentnotes_handle_user_delete');

    // Anzeige
    $plugins->add_hook('forumdisplay_thread', 'contentnotes_handle_thread_badge');
    $plugins->add_hook('search_results_thread', 'contentnotes_handle_thread_badge');
    $plugins->add_hook('showthread_start', 'contentnotes_handle_showthread_start');
    $plugins->add_hook('postbit', 'contentnotes_handle_postbit');
    $plugins->add_hook('postbit_prev', 'contentnotes_handle_postbit_preview');

    // Inline-MyCode [cw]
    $plugins->add_hook('parse_message', 'contentnotes_parse_inline');
    $plugins->add_hook('text_parse_message', 'contentnotes_parse_inline_text');

    // Benutzer-CP
    $plugins->add_hook('usercp_menu', 'contentnotes_usercp_menu');
    $plugins->add_hook('usercp_start', 'contentnotes_usercp_page');
}

/**
 * Plugin-Informationen für den ACP-Plugin-Manager.
 *
 * @return array
 */
function contentnotes_info(): array
{
    return [
        'name'          => 'Content Notes',
        'description'   => 'Inhaltswarnungen für Threads, Posts und einzelne Textstellen mit frei definierbaren Kategorien. Mitglieder entscheiden pro Kategorie, ob sie eine klickbare Warnung sehen oder den Inhalt direkt angezeigt bekommen.',
        'website'       => 'https://github.com/pand0rica/mybb-contentnotes',
        'author'        => 'pand0rica',
        'authorsite'    => 'https://github.com/pand0rica',
        'version'       => CONTENTNOTES_VERSION,
        'guid'          => '',
        'codename'      => 'contentnotes',
        'compatibility' => '18*',
    ];
}

/**
 * Prüft, ob das Plugin installiert ist.
 *
 * @return bool
 */
function contentnotes_is_installed(): bool
{
    global $db;

    return $db->table_exists('contentnotes_categories');
}

/**
 * Legt Tabellen, Settings und den Kategorien-Cache an.
 *
 * @return void
 */
function contentnotes_install(): void
{
    global $db;

    $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "contentnotes_categories` (
        `cid`         INT(11) NOT NULL AUTO_INCREMENT,
        `name`        VARCHAR(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `slug`        VARCHAR(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `description` TEXT COLLATE utf8_unicode_ci,
        `active`      TINYINT(1) NOT NULL DEFAULT 1,
        `require_note` TINYINT(1) NOT NULL DEFAULT 0,
        `disporder`   INT(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`cid`),
        UNIQUE KEY `slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "contentnotes_notes` (
        `nid`    INT(11) NOT NULL AUTO_INCREMENT,
        `type`   VARCHAR(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        `refid`  INT(11) NOT NULL DEFAULT 0,
        `cid`    INT(11) NOT NULL DEFAULT 0,
        `note`   VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        PRIMARY KEY (`nid`),
        UNIQUE KEY `item_category` (`type`, `refid`, `cid`),
        KEY `item` (`type`, `refid`),
        KEY `cid` (`cid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    $db->query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "contentnotes_userprefs` (
        `uid`  INT(11) NOT NULL DEFAULT 0,
        `cid`  INT(11) NOT NULL DEFAULT 0,
        `mode` TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`uid`, `cid`),
        KEY `cid` (`cid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    contentnotes_install_settings();
    rebuild_settings();

    contentnotes_db_rebuild_cache();
}

/**
 * Entfernt Tabellen, Settings und den Kategorien-Cache vollständig.
 *
 * @return void
 */
function contentnotes_uninstall(): void
{
    global $db, $cache;

    $db->drop_table('contentnotes_userprefs');
    $db->drop_table('contentnotes_notes');
    $db->drop_table('contentnotes_categories');

    // Spiegelbild zu install(): Settings + Gruppe erst hier entfernen, nicht in deactivate().
    $db->delete_query('settings', "name LIKE 'contentnotes\_%'");
    $db->delete_query('settinggroups', "name = 'contentnotes'");
    rebuild_settings();

    $cache->delete('contentnotes_categories');
}

/**
 * Legt die Settings idempotent an (Erstinstallation und Upgrade-Pfad), ohne vorhandene
 * Werte zu überschreiben.
 *
 * @return void
 */
function contentnotes_install_settings(): void
{
    global $db;

    $existing = $db->fetch_array($db->simple_select('settinggroups', 'gid', "name = 'contentnotes'"));
    if ($existing) {
        $gid = (int)$existing['gid'];
    } else {
        $gid = (int)$db->insert_query('settinggroups', [
            'name'        => 'contentnotes',
            'title'       => 'Content Notes',
            'description' => 'Settings for the Content Notes plugin.',
            'disporder'   => 50,
            'isdefault'   => 0,
        ]);
    }

    $settings = [
        [
            'name'        => 'contentnotes_enabled',
            'title'       => 'Enable Content Notes',
            'description' => 'Turns the content note features in the forum on or off.',
            'optionscode' => 'yesno',
            'value'       => '1',
            'disporder'   => 1,
        ],
        [
            'name'        => 'contentnotes_groups',
            'title'       => 'Groups allowed to set content notes',
            'description' => 'Members of these groups may set, change and remove content notes on threads and posts and see the editor button.',
            'optionscode' => 'groupselect',
            'value'       => '4',
            'disporder'   => 2,
        ],
        [
            'name'        => 'contentnotes_forums',
            'title'       => 'Forums with thread and post notes',
            'description' => 'In these forums the content note fields are offered when writing and editing threads and posts.',
            'optionscode' => 'forumselect',
            'value'       => '-1',
            'disporder'   => 3,
        ],
        [
            'name'        => 'contentnotes_editor_button',
            'title'       => 'Show editor button',
            'description' => 'Adds a content note button to the message editor for the allowed groups.',
            'optionscode' => 'yesno',
            'value'       => '1',
            'disporder'   => 4,
        ],
    ];

    foreach ($settings as $setting) {
        $name = $db->escape_string($setting['name']);
        if ($db->fetch_array($db->simple_select('settings', 'sid', "name = '{$name}'"))) {
            continue; // idempotent: bereits vorhanden
        }
        $setting['gid'] = $gid;
        foreach (['name', 'title', 'description', 'optionscode', 'value'] as $field) {
            $setting[$field] = $db->escape_string($setting[$field]);
        }
        $db->insert_query('settings', $setting);
    }
}

/**
 * Liefert die Core-Template-Injektionen als Liste von [Template, Anker, Ersatz].
 * Der Ersatz enthält den Anker, damit deactivate() exakt zurückbauen kann.
 *
 * @return array
 */
function contentnotes_template_injections(): array
{
    return [
        ['newthread', '{$posticons}', '{$posticons}{$contentnotes_form}'],
        ['newreply', '{$posticons}', '{$posticons}{$contentnotes_form}'],
        ['editpost', '{$posticons}', '{$posticons}{$contentnotes_form}'],
        ['showthread_quickreply', '{$captcha}', '{$contentnotes_quickreply}{$captcha}'],
        ['showthread', '<div id="posts">', '{$contentnotes_thread_banner}<div id="posts"{$contentnotes_posts_attr}>'],
        ['forumdisplay_thread', '<span>{$prefix} {$gotounread}', '{$thread[\'contentnotes_badge\']}<span>{$prefix} {$gotounread}'],
        ['search_results_threads_thread', '<span>{$prefix} {$gotounread}', '{$thread[\'contentnotes_badge\']}<span>{$prefix} {$gotounread}'],
    ];
}

/**
 * Frühere Injektionen (Label hinter dem Threadtitel, 1.0.0/1.1.0 bis zur Umstellung): werden beim
 * Zurückbauen mit entfernt, damit nach einem Update nichts doppelt im Template steht.
 *
 * @return array
 */
function contentnotes_legacy_template_injections(): array
{
    return [
        ['forumdisplay_thread', '{$thread[\'multipage\']}', '{$thread[\'contentnotes_badge\']}{$thread[\'multipage\']}'],
        ['search_results_threads_thread', '{$thread[\'multipage\']}', '{$thread[\'contentnotes_badge\']}{$thread[\'multipage\']}'],
        // h4w: der Menüpunkt steht nicht mehr in „Sonstiges“, sondern in der RPG-Kategorie (siehe contentnotes_h4w_*)
        ['usercp_nav_misc', contentnotes_usercp_nav_anchor(), contentnotes_usercp_nav_anchor() . contentnotes_usercp_nav_row()],
    ];
}

/**
 * h4w: Menüzeile für die RPG-Kategorie im UserCP (Master-Template usercp_nav_rpg_user, nur User-Accounts).
 * Byte-gleich mit der Zeile in mybb-stylekit/templates-master/usercp_nav_rpg_user.html; endet mit der Markierung
 * <!--contentnotes_nav-->, damit sie beim Aktivieren erst entfernt und dann neu gesetzt wird (kein Duplikat).
 *
 * @return string
 */
function contentnotes_h4w_nav_row(): string
{
    return '<tr><td class="trow1 smalltext"><a href="usercp.php?action=contentnotes" class="usercp_nav_item usercp_nav_contentnotes" style="padding-left: 0;">'
        . '<svg class="usercp_nav_icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" aria-hidden="true" style="vertical-align: -3px; margin-right: 6px;">'
        . '<path d="M8 1.8 14.6 13.4H1.4z" stroke-width="1.3" stroke-linejoin="round"/><path d="M8 6.2v3.4" stroke-width="1.3" stroke-linecap="round"/>'
        . '<circle cx="8" cy="11.4" r=".7" fill="currentColor" stroke="none"/></svg>'
        . '{$lang->contentnotes_usercp_menu_item}</a></td></tr><!--contentnotes_nav-->';
}

/**
 * h4w: Zeile aus dem Master-Template usercp_nav_rpg_user entfernen (inkl. Einrückung und Zeilenumbruch).
 *
 * @return void
 */
function contentnotes_h4w_remove_nav_row(): void
{
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    find_replace_templatesets(
        'usercp_nav_rpg_user',
        '#[ \t]*<tr><td class="trow1 smalltext"><a href="usercp\.php\?action=contentnotes".*?</tr><!--contentnotes_nav-->\r?\n?#s',
        '',
        0
    );
}

/**
 * h4w: Zeile vor {$usercp_nav_rpg_custom} in usercp_nav_rpg_user einsetzen (erst entfernen, dann setzen).
 *
 * @return void
 */
function contentnotes_h4w_install_nav_row(): void
{
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    contentnotes_h4w_remove_nav_row();
    find_replace_templatesets(
        'usercp_nav_rpg_user',
        '#\{\$usercp_nav_rpg_custom\}#',
        contentnotes_h4w_nav_row() . "\n    {\$usercp_nav_rpg_custom}",
        0
    );
}

/**
 * Hängt die Injektionen in die Core-Templates (alle Template-Sets, idempotent).
 *
 * @return void
 */
function contentnotes_install_template_injections(): void
{
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    // Idempotent: erst zurückbauen, dann neu einhängen.
    contentnotes_uninstall_template_injections();

    foreach (contentnotes_template_injections() as $injection) {
        list($title, $find, $replace) = $injection;
        find_replace_templatesets($title, '#' . preg_quote($find, '#') . '#', $replace);
    }

    // h4w: Menüzeile in der RPG-Kategorie (nur User-Accounts)
    contentnotes_h4w_install_nav_row();
}

/**
 * Baut die Injektionen aus den Core-Templates wieder aus.
 *
 * @return void
 */
function contentnotes_uninstall_template_injections(): void
{
    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    foreach (array_merge(contentnotes_template_injections(), contentnotes_legacy_template_injections()) as $injection) {
        list($title, $find, $replace) = $injection;
        // Autocreate aus: es werden nur bereits veränderte Templates zurückgebaut.
        find_replace_templatesets($title, '#' . preg_quote($replace, '#') . '#', $find, 0);
    }

    contentnotes_h4w_remove_nav_row();
}

/**
 * Lädt die eigenen Templates aus inc/plugins/contentnotes/templates/*.tpl (Template-Set -1).
 *
 * @return void
 */
function contentnotes_install_templates(): void
{
    global $db;

    $template_dir = CONTENTNOTES_DIR . 'templates/';
    if (!is_dir($template_dir)) {
        return;
    }

    $handle = opendir($template_dir);
    if (!$handle) {
        return;
    }

    while (($file = readdir($handle)) !== false) {
        if (substr($file, -4) !== '.tpl') {
            continue;
        }
        $title   = substr($file, 0, -4);
        $content = (string)file_get_contents($template_dir . $file);

        // Idempotent: vorhandene globale Fassung ersetzen
        $db->delete_query('templates', "title = '" . $db->escape_string($title) . "' AND sid = -1");
        $db->insert_query('templates', [
            'title'    => $db->escape_string($title),
            'template' => $db->escape_string($content),
            'sid'      => -1,
            'version'  => '',
            'dateline' => TIME_NOW,
        ]);
    }
    closedir($handle);
}

/**
 * Legt das Stylesheet in allen Themes an bzw. aktualisiert es.
 *
 * @return void
 */
function contentnotes_install_stylesheet(): void
{
    global $db;

    require_once MYBB_ADMIN_DIR . 'inc/functions_themes.php';

    $css_path = CONTENTNOTES_DIR . 'assets/contentnotes.css';
    if (!file_exists($css_path)) {
        return;
    }

    $css    = $db->escape_string((string)file_get_contents($css_path));
    $themes = $db->simple_select('themes', 'tid');
    while ($theme = $db->fetch_array($themes)) {
        $tid      = (int)$theme['tid'];
        $existing = $db->fetch_array($db->simple_select(
            'themestylesheets',
            'sid',
            "name = 'contentnotes.css' AND tid = '{$tid}'"
        ));
        if ($existing) {
            $db->update_query('themestylesheets', [
                'stylesheet'   => $css,
                'lastmodified' => TIME_NOW,
            ], "sid='" . (int)$existing['sid'] . "'", 1);
        } else {
            $sid = (int)$db->insert_query('themestylesheets', [
                'name'         => 'contentnotes.css',
                'tid'          => $tid,
                'attachedto'   => '',
                'stylesheet'   => $css,
                'lastmodified' => TIME_NOW,
                'cachefile'    => 'contentnotes.css',
            ]);
            $db->update_query('themestylesheets', ['cachefile' => "css.php?stylesheet={$sid}"], "sid='{$sid}'", 1);
        }
        update_theme_stylesheet_list($tid);
    }
}

/**
 * Upgrade-Schritte für das Schema (idempotent, läuft bei jeder Aktivierung).
 *
 * @return void
 */
function contentnotes_upgrade_schema(): void
{
    global $db;

    // 1.1.0: Freitext pro Kategorie als Pflicht
    if ($db->table_exists('contentnotes_categories') && !$db->field_exists('require_note', 'contentnotes_categories')) {
        $db->add_column('contentnotes_categories', 'require_note', 'TINYINT(1) NOT NULL DEFAULT 0');
        contentnotes_db_rebuild_cache();
    }
}

/**
 * Aktiviert das Plugin: Upgrade-Schritte, Settings, Templates, Stylesheet und Template-Einbindungen.
 *
 * @return void
 */
function contentnotes_activate(): void
{
    // Upgrade-Pfad: neue Spalten/Settings späterer Versionen nachrüsten, vorhandene Werte bleiben.
    contentnotes_upgrade_schema();
    contentnotes_install_settings();
    rebuild_settings();

    contentnotes_install_templates();
    contentnotes_install_stylesheet();
    contentnotes_install_template_injections();
}

/**
 * Deaktiviert das Plugin: exaktes Spiegelbild zu activate(). Settings und Daten bleiben erhalten.
 *
 * @return void
 */
function contentnotes_deactivate(): void
{
    global $db;

    require_once MYBB_ADMIN_DIR . 'inc/functions_themes.php';

    contentnotes_uninstall_template_injections();

    $db->delete_query('templates', "title LIKE 'contentnotes%'");

    $db->delete_query('themestylesheets', "name = 'contentnotes.css'");
    $themes = $db->simple_select('themes', 'tid');
    while ($theme = $db->fetch_array($themes)) {
        update_theme_stylesheet_list((int)$theme['tid']);
    }
}

/**
 * ACP: Menüeintrag unter „Konfiguration“.
 *
 * @param array $sub_menu Untermenü der Konfiguration
 * @return void
 */
function contentnotes_admin_menu(&$sub_menu): void
{
    global $lang;

    $lang->load('contentnotes');

    $sub_menu[] = [
        'id'    => 'contentnotes',
        'title' => $lang->contentnotes,
        'link'  => 'index.php?module=config-contentnotes',
    ];
}

/**
 * ACP: Action-Handler für das Config-Modul.
 *
 * @param array $actions Action-Liste
 * @return void
 */
function contentnotes_admin_action(&$actions): void
{
    $actions['contentnotes'] = ['active' => 'contentnotes', 'file' => 'contentnotes.php'];
}

/**
 * ACP: Berechtigung für das Config-Modul.
 *
 * @param array $admin_permissions Berechtigungsliste
 * @return void
 */
function contentnotes_admin_permissions(&$admin_permissions): void
{
    global $lang;

    $lang->load('contentnotes');

    $admin_permissions['contentnotes'] = $lang->contentnotes_can_manage;
}

/**
 * ACP: Lädt die Admin-Sprachdatei für Titel/Beschreibungen der Einstellungen.
 *
 * @return void
 */
function contentnotes_admin_load_lang(): void
{
    global $lang;

    $lang->load('contentnotes');
}
