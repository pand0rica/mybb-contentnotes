<?php
/**
 * Content Notes – legt die Standard-Kategorien an (CLI, idempotent).
 *
 * Aufruf aus dem MyBB-Root:  php inc/plugins/contentnotes/seed-categories.php
 *
 * Legt nur Kategorien an, deren Kürzel noch nicht existiert. Bestehende Kategorien
 * (auch nachträglich im ACP geänderte) werden nie überschrieben oder gelöscht.
 * Das Plugin muss installiert sein. Exit-Code: 0 ok, 1 Fehler.
 */
if (PHP_SAPI !== 'cli') {
    die('CLI only.');
}

define('IN_MYBB', 1);
define('NO_ONLINE', 1);
chdir(dirname(__DIR__, 3));
require_once './inc/init.php';

if (!defined('MYBB_ADMIN_DIR')) {
    $admin_dir = isset($config['admin_dir']) ? $config['admin_dir'] : 'admin';
    define('MYBB_ADMIN_DIR', MYBB_ROOT . $admin_dir . '/');
}
require_once MYBB_ROOT . 'inc/plugins/contentnotes.php';

if (!$db->table_exists('contentnotes_categories')) {
    fwrite(STDERR, "contentnotes is not installed - install it first\n");
    exit(1);
}
contentnotes_upgrade_schema();

// slug => [Name, Beschreibung, Freitext Pflicht]
$defaults = [
    'gewalt'                          => ['Gewalt', 'Kampf, Folter, Blut, schwere Verletzungen, Gore', 0],
    'sexuelle-inhalte'                => ['Sexuelle Inhalte', 'Von angedeutet bis explizit', 0],
    'sexualisierte-gewalt'            => ['Sexualisierte Gewalt', 'Übergriffe, Vergewaltigung, Belästigung. Bewusst getrennt von „Sexuelle Inhalte“, denn es sind völlig unterschiedliche Trigger.', 0],
    'tod-trauer'                      => ['Tod & Trauer', 'Tod von Figuren, Sterben, Verlust, Beerdigungen', 0],
    'suizid-selbstverletzung'         => ['Suizid & Selbstverletzung', 'Eigene Kategorie, weil hier besonders hohe Sensibilität nötig ist', 0],
    'psychische-belastung'            => ['Psychische Belastung', 'Depression, Angst, Panikattacken, Trauma, Dissoziation, Wahnvorstellungen', 0],
    'missbrauch-toxische-beziehungen' => ['Missbrauch & toxische Beziehungen', 'Häusliche Gewalt, emotionaler Missbrauch, Manipulation, Stalking', 0],
    'gewalt-kinder-tiere'             => ['Gewalt gegen Kinder oder Tiere', 'Wird oft ausdrücklich separat gewünscht', 0],
    'diskriminierung'                 => ['Diskriminierung', 'Rassismus, Queerfeindlichkeit, Sexismus, Ableismus, Blutstatus-Hetze', 0],
    'sucht-substanzen'                => ['Sucht & Substanzen', 'Alkohol, Drogen, Entzug', 0],
    'essstoerungen-koerperbild'       => ['Essstörungen & Körperbild', 'Essstörungen, Körperbild, Gewicht', 0],
    'medizinisches'                   => ['Medizinisches', 'Krankheit, Krankenhaus, Nadeln, Operationen, Schwangerschaftskomplikationen, Fehlgeburt', 0],
    'horror-phobien'                  => ['Horror & Phobien', 'Body Horror, Spinnen/Insekten, Enge, Ertrinken, Dunkelheit', 0],
    'sonstiges'                       => ['Sonstiges', 'Alles, was in keine andere Kategorie passt. Hier ist ein Freitext Pflicht.', 1],
];

$order   = 0;
$created = 0;
foreach ($defaults as $slug => $row) {
    $order += 10;
    if (contentnotes_db_slug_exists($slug)) {
        echo "exists:  $slug\n";
        continue;
    }
    contentnotes_db_insert_category([
        'name'         => $row[0],
        'slug'         => $slug,
        'description'  => $row[1],
        'active'       => 1,
        'require_note' => $row[2],
        'disporder'    => $order,
    ]);
    $created++;
    echo "created: $slug\n";
}

echo "done: $created created, " . (count($defaults) - $created) . " already present\n";
