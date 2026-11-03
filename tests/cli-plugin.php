<?php
/**
 * Content Notes – CLI-Lifecycle-Helper (Kit testing.md §3).
 *
 * Installiert/deinstalliert das Plugin ohne ACP-Login und beweist den vollständigen Cleanup.
 * Das Skript liegt in <MyBB-Root>/tests/ und wird per CLI gestartet:
 *
 *   php tests/cli-plugin.php install|uninstall|status|fingerprint
 */
if (PHP_SAPI !== 'cli') {
    die('CLI only.');
}
define('IN_MYBB', 1);
chdir(dirname(__DIR__));
require_once './inc/init.php';

// activate() braucht Admin-Funktionen (Stylesheets) – Konstante selbst setzen
if (!defined('MYBB_ADMIN_DIR')) {
    $admin_dir = isset($config['admin_dir']) ? $config['admin_dir'] : 'admin';
    define('MYBB_ADMIN_DIR', MYBB_ROOT . $admin_dir . '/');
}
require_once MYBB_ROOT . 'inc/plugins/contentnotes.php';

/**
 * Pflegt den plugins-Datacache wie der ACP-Plugin-Manager.
 *
 * @param bool $active Aktiv setzen oder entfernen
 * @return void
 */
function contentnotes_cli_set_active(bool $active): void
{
    global $cache;

    $c = $cache->read('plugins');
    if (!is_array($c)) {
        $c = [];
    }
    if (!isset($c['active']) || !is_array($c['active'])) {
        $c['active'] = [];
    }
    if ($active) {
        $c['active']['contentnotes'] = 'contentnotes';
    } else {
        unset($c['active']['contentnotes']);
    }
    $cache->update('plugins', $c);
}

/**
 * Titel der Core-Templates, in die das Plugin Variablen injiziert.
 *
 * @return array
 */
function contentnotes_cli_core_titles(): array
{
    $titles = [];
    foreach (contentnotes_template_injections() as $injection) {
        $titles[$injection[0]] = true;
    }

    return array_keys($titles);
}

/**
 * Fingerprint: pro Core-Template die Menge der unterschiedlichen Inhalte (SHA-1) über alle Sets.
 * Nach install→uninstall muss er dem Ausgangszustand entsprechen.
 *
 * @return void
 */
function contentnotes_cli_fingerprint(): void
{
    global $db;

    foreach (contentnotes_cli_core_titles() as $title) {
        $hashes = [];
        $query  = $db->simple_select('templates', 'template', "title = '" . $db->escape_string($title) . "'");
        while ($row = $db->fetch_array($query)) {
            $hashes[sha1($row['template'])] = true;
        }
        $keys = array_keys($hashes);
        sort($keys);
        echo $title . ': ' . implode(',', $keys) . "\n";
    }
}

/**
 * Status: Tabellen, Settings, Templates, Stylesheets, Injektionen, Datacache.
 *
 * @return void
 */
function contentnotes_cli_status(): void
{
    global $db, $cache;

    foreach (['contentnotes_categories', 'contentnotes_notes', 'contentnotes_userprefs'] as $table) {
        echo 'table ' . $table . ': ' . ($db->table_exists($table) ? 'EXISTS' : 'MISSING') . "\n";
    }

    $count = static function (string $table, string $where) use ($db): int {
        return (int)$db->fetch_field($db->simple_select($table, 'COUNT(*) AS n', $where), 'n');
    };

    echo 'settings: ' . $count('settings', "name LIKE 'contentnotes\\_%'") . "\n";
    echo 'settinggroups: ' . $count('settinggroups', "name = 'contentnotes'") . "\n";
    echo 'own templates: ' . $count('templates', "title LIKE 'contentnotes%'") . "\n";
    echo 'stylesheets: ' . $count('themestylesheets', "name = 'contentnotes.css'") . "\n";
    echo 'core templates with contentnotes_ variables: ' . $count('templates', "template LIKE '%contentnotes\\_%' AND title NOT LIKE 'contentnotes%'") . "\n";
    // Direkt in der DB prüfen: der In-Memory-Cache bleibt im selben Prozess nach delete() stehen
    echo 'datacache contentnotes_categories: ' . ($count('datacache', "title = 'contentnotes_categories'") > 0 ? 'EXISTS' : 'MISSING') . "\n";

    $plugins = $cache->read('plugins');
    echo 'plugins cache active: ' . (isset($plugins['active']['contentnotes']) ? 'yes' : 'no') . "\n";
}

$cmd = isset($argv[1]) ? $argv[1] : 'status';

if ($cmd === 'install') {
    contentnotes_install();
    contentnotes_activate();
    contentnotes_cli_set_active(true);
    echo "installed + activated\n";
} elseif ($cmd === 'deactivate') {
    contentnotes_deactivate();
    contentnotes_cli_set_active(false);
    echo "deactivated\n";
} elseif ($cmd === 'activate') {
    contentnotes_activate();
    contentnotes_cli_set_active(true);
    echo "activated\n";
} elseif ($cmd === 'uninstall') {
    contentnotes_deactivate();
    contentnotes_uninstall();
    contentnotes_cli_set_active(false);
    echo "deactivated + uninstalled\n";
} elseif ($cmd === 'fingerprint') {
    contentnotes_cli_fingerprint();
    exit(0);
}

contentnotes_cli_status();
