<?php
/**
 * Content Notes – CLI-Selbsttest (Kit testing.md §4): DB-Schicht, Prefs-Auswertung, Rechte,
 * Formular-Eingabe, Speichern/Aufräumen über die Datahandler-Hook-Funktionen und Parser.
 *
 * Voraussetzung: Plugin installiert und aktiviert (tests/cli-plugin.php install).
 *
 *   php tests/cli-selftest.php
 */
if (PHP_SAPI !== 'cli') {
    die('CLI only.');
}
define('IN_MYBB', 1);
chdir(dirname(__DIR__));
require_once './inc/init.php';

if (!defined('MYBB_ADMIN_DIR')) {
    $admin_dir = isset($config['admin_dir']) ? $config['admin_dir'] : 'admin';
    define('MYBB_ADMIN_DIR', MYBB_ROOT . $admin_dir . '/');
}
require_once MYBB_ROOT . 'inc/class_parser.php';

if (!function_exists('contentnotes_is_installed') || !contentnotes_is_installed()) {
    fwrite(STDERR, "Plugin nicht installiert/aktiv: erst php tests/cli-plugin.php install\n");
    exit(2);
}

// Account-/Theme-Kontext, den init.php nicht liefert
if (!isset($mybb->user) || !is_array($mybb->user)) {
    $mybb->user = [];
}
$mybb->user += ['uid' => 0, 'usergroup' => 1, 'additionalgroups' => '', 'timezone' => 0, 'dst' => 0, 'dateformat' => '', 'timeformat' => ''];
$theme = ['templateset' => 1, 'imgdir' => 'images', 'borderwidth' => 1, 'tablespace' => 4];
$lang->set_language('english');
$lang->load('contentnotes');

$pass = 0;
$fail = 0;

/**
 * @param string $name     Name des Tests
 * @param mixed  $expected Erwartung
 * @param mixed  $actual   Ergebnis
 * @return void
 */
function check(string $name, $expected, $actual): void
{
    global $pass, $fail;

    if ($expected === $actual) {
        $pass++;
        echo "  ok   {$name}\n";
    } else {
        $fail++;
        echo "  FAIL {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
    }
}

/**
 * @param string $name   Name des Tests
 * @param bool   $result Muss true sein
 * @return void
 */
function check_true(string $name, bool $result): void
{
    check($name, true, $result);
}

/**
 * Setzt den angemeldeten Account für die folgenden Tests.
 *
 * @param int    $uid   Mitglieds-ID (0 = Gast)
 * @param string $group Primäre Gruppe
 * @return void
 */
function as_user(int $uid, string $group = '2'): void
{
    global $mybb;

    $mybb->user['uid']              = $uid;
    $mybb->user['usergroup']        = $group;
    $mybb->user['additionalgroups'] = '';
}

/**
 * Setzt Request-Eingaben (get_input liest $mybb->input).
 *
 * @param array $input Eingabewerte
 * @return void
 */
function set_input(array $input): void
{
    global $mybb;

    $mybb->input = $input;
}

// Saubere Ausgangslage
$db->delete_query('contentnotes_notes');
$db->delete_query('contentnotes_userprefs');
$db->delete_query('contentnotes_categories');
contentnotes_db_rebuild_cache();

$mybb->settings['contentnotes_enabled']       = 1;
$mybb->settings['contentnotes_groups']        = '4';
$mybb->settings['contentnotes_forums']        = '-1';
$mybb->settings['contentnotes_editor_button'] = 1;

// ---------------------------------------------------------------------------------------
echo "Kategorien\n";
// ---------------------------------------------------------------------------------------
check('slug valid: gewalt', true, contentnotes_valid_slug('gewalt'));
check('slug valid: mit - und _', true, contentnotes_valid_slug('a-b_c9'));
check('slug invalid: Großbuchstabe', false, contentnotes_valid_slug('Gewalt'));
check('slug invalid: Leerzeichen', false, contentnotes_valid_slug('a b'));
check('slug invalid: leer', false, contentnotes_valid_slug(''));
check('slug invalid: 51 Zeichen', false, contentnotes_valid_slug(str_repeat('a', 51)));
check('slug invalid: ]', false, contentnotes_valid_slug('a]b'));

$violence = contentnotes_db_insert_category(['name' => 'Gewalt <b>', 'slug' => 'gewalt', 'description' => 'Darstellung von Gewalt', 'active' => 1, 'disporder' => 2]);
$addiction = contentnotes_db_insert_category(['name' => 'Sucht', 'slug' => 'sucht', 'description' => '', 'active' => 1, 'disporder' => 1]);
$retired   = contentnotes_db_insert_category(['name' => 'Altlast', 'slug' => 'alt', 'description' => '', 'active' => 0, 'disporder' => 3]);

check_true('insert liefert IDs', $violence > 0 && $addiction > 0 && $retired > 0);
check('Sortierung nach disporder', [$addiction, $violence, $retired], array_keys(contentnotes_db_get_categories()));
check('nur aktive', [$addiction, $violence], array_keys(contentnotes_db_get_categories(true)));
check('Kategorie per Slug', $violence, contentnotes_db_get_category_by_slug('gewalt')['cid'] ?? null);
check('unbekannter Slug', null, contentnotes_db_get_category_by_slug('nope'));
check('slug_exists: vergeben', true, contentnotes_db_slug_exists('gewalt'));
check('slug_exists: eigene cid ausgenommen', false, contentnotes_db_slug_exists('gewalt', $violence));
check('slug_exists: frei', false, contentnotes_db_slug_exists('frei'));
check('Datacache enthält 3 Kategorien', 3, count((array)$cache->read('contentnotes_categories')));

contentnotes_db_update_category($addiction, ['name' => 'Sucht & Abhängigkeit', 'active' => 1]);
check('update_category wirkt im Cache', 'Sucht & Abhängigkeit', contentnotes_db_get_category($addiction)['name'] ?? null);

// ---------------------------------------------------------------------------------------
echo "Markierungen\n";
// ---------------------------------------------------------------------------------------
contentnotes_db_replace_notes('thread', 901, [$violence => 'Folter', $addiction => '']);
$notes = contentnotes_db_get_notes('thread', [901, 902]);
check('thread notes gelesen', [$violence => 'Folter', $addiction => ''], $notes[901] ?? null);
check('thread ohne Notes', false, isset($notes[902]));

contentnotes_db_replace_notes('thread', 901, [$addiction => 'Alkohol']);
check('replace ersetzt', [$addiction => 'Alkohol'], contentnotes_db_get_notes('thread', [901])[901] ?? null);

contentnotes_db_add_notes('thread', 901, [$addiction => '', $violence => 'Krieg']);
check('add ergänzt, Freitext bleibt wenn leer', [$addiction => 'Alkohol', $violence => 'Krieg'], contentnotes_db_get_notes('thread', [901])[901] ?? null);
contentnotes_db_add_notes('thread', 901, [$addiction => 'Rauchen']);
check('add überschreibt mit neuem Freitext', 'Rauchen', contentnotes_db_get_notes('thread', [901])[901][$addiction] ?? null);

contentnotes_db_replace_notes('post', 901, [$violence => 'post-901']);
check('Typen getrennt: post ≠ thread', [$violence => 'post-901'], contentnotes_db_get_notes('post', [901])[901] ?? null);

contentnotes_db_replace_notes('bogus', 901, [$violence => 'x']);
check('ungültiger Typ wird ignoriert', [], contentnotes_db_get_notes('bogus', [901]));

check('Beschreibung sortiert nach Kategorie-Reihenfolge', [$addiction, $violence], array_column(contentnotes_get_notes('thread', 901), 'cid'));

contentnotes_db_delete_notes('thread', [901]);
check('delete_notes (thread)', [], contentnotes_db_get_notes('thread', [901]));
check('delete_notes lässt post unberührt', true, isset(contentnotes_db_get_notes('post', [901])[901]));
contentnotes_db_delete_notes('post', [901]);

// ---------------------------------------------------------------------------------------
echo "Vorlieben (Standard = Warnung)\n";
// ---------------------------------------------------------------------------------------
as_user(0, '1');
check('Gast: immer Warnung', true, contentnotes_viewer_wants_warning([$violence]));

as_user(777, '2');
check('Mitglied ohne Eintrag: Warnung', true, contentnotes_viewer_wants_warning([$violence]));
contentnotes_db_save_user_prefs(777, [$violence => 1]);
// Prefs werden pro Prozess gemerkt: neuer uid-Wert für frischen Stand
as_user(778, '2');
contentnotes_db_save_user_prefs(778, [$violence => 1, $addiction => 0]);
check('Modus 1 (einblenden) ⇒ keine Warnung', false, contentnotes_viewer_wants_warning([$violence]));
check('Modus 0 ⇒ Warnung', true, contentnotes_viewer_wants_warning([$addiction]));
check('gemischt ⇒ Warnung (mind. eine Kategorie)', true, contentnotes_viewer_wants_warning([$violence, $addiction]));
check('unbekannte cid ⇒ Warnung', true, contentnotes_viewer_wants_warning([$violence, 999999]));
check('keine Kategorien ⇒ keine Warnung', false, contentnotes_viewer_wants_warning([]));
check('Prefs lesbar', [$violence => 1, $addiction => 0], contentnotes_db_get_user_prefs(778));
contentnotes_db_delete_user_prefs([777, 778]);
check('Prefs gelöscht', 0, (int)$db->fetch_field($db->simple_select('contentnotes_userprefs', 'COUNT(*) AS n', 'uid IN (777,778)'), 'n'));

// ---------------------------------------------------------------------------------------
echo "Rechte\n";
// ---------------------------------------------------------------------------------------
as_user(0, '1');
check('Gast darf nicht', false, contentnotes_user_can_set(2));
as_user(5, '2');
check('Gruppe 2 nicht berechtigt', false, contentnotes_user_can_set(2));
as_user(1, '4');
check('Gruppe 4 berechtigt (alle Foren)', true, contentnotes_user_can_set(2));
$mybb->settings['contentnotes_forums'] = '3,5';
check('Forum nicht in Liste', false, contentnotes_user_can_set(2));
check('Forum in Liste', true, contentnotes_user_can_set(5));
$mybb->settings['contentnotes_forums'] = '';
check('keine Foren ⇒ nie', false, contentnotes_user_can_set(2));
$mybb->settings['contentnotes_forums'] = '-1';
as_user(6, '2');
$mybb->user['additionalgroups'] = '4';
check('Zusatzgruppe zählt', true, contentnotes_user_can_set(2));
$mybb->settings['contentnotes_groups'] = '-1';
as_user(7, '2');
check('Gruppen -1 = alle Mitglieder', true, contentnotes_user_can_set(2));
$mybb->settings['contentnotes_groups'] = '';
check('Gruppen leer = niemand', false, contentnotes_user_can_set(2));
$mybb->settings['contentnotes_groups'] = '4';
$mybb->settings['contentnotes_enabled'] = 0;
as_user(1, '4');
check('Plugin aus ⇒ niemand', false, contentnotes_user_can_set(2));
$mybb->settings['contentnotes_enabled'] = 1;

// ---------------------------------------------------------------------------------------
echo "Formular-Eingabe\n";
// ---------------------------------------------------------------------------------------
set_input([]);
check('Marker fehlt', false, contentnotes_form_submitted());
set_input(['contentnotes_form' => '1']);
check('Marker da', true, contentnotes_form_submitted());

set_input(['contentnotes' => [
    $violence  => ['on' => '1', 'note' => "  Folter\r\nund mehr  "],
    $addiction => ['note' => 'ohne Haken'],
    'abc'      => ['on' => '1'],
    0          => ['on' => '1'],
    $retired   => ['on' => '1', 'note' => 'alt'],
    777777     => ['on' => '1', 'note' => 'gibt es nicht'],
]]);
$read = contentnotes_read_input();
check('nur angehakte Kategorien, Zeilenumbruch → Leerzeichen', [$violence => 'Folter und mehr', $retired => 'alt', 777777 => 'gibt es nicht'], $read);
check('Filter: inaktive/unbekannte raus', [$violence => 'Folter und mehr'], contentnotes_filter_input($read));
check('Filter: bereits gesetzte inaktive bleibt', [$violence => 'Folter und mehr', $retired => 'alt'], contentnotes_filter_input($read, [$retired => 'x']));
check('Länge ≤ 255 erlaubt', false, contentnotes_note_too_long([1 => str_repeat('ä', 255)]));
check('Länge 256 zu lang (Zeichen, nicht Bytes)', true, contentnotes_note_too_long([1 => str_repeat('ä', 256)]));
check('Filter kürzt auf 255', 255, my_strlen(contentnotes_filter_input([$violence => str_repeat('x', 400)])[$violence]));

set_input(['contentnotes' => 'kein-array']);
check('kaputte Eingabe (String) ⇒ leer', [], contentnotes_read_input());

// ---------------------------------------------------------------------------------------
echo "Speichern über Datahandler-Hook-Funktionen\n";
// ---------------------------------------------------------------------------------------
as_user(1, '4');
$handler               = new stdClass();
$handler->return_values = ['tid' => 4001, 'pid' => 5001];
$handler->data          = ['fid' => 2];

set_input(['contentnotes_form' => '1', 'contentnotes' => [$violence => ['on' => '1', 'note' => 'Blut'], $addiction => ['on' => '1']]]);
contentnotes_handle_insert_thread($handler);
check('Thread-Notes gespeichert', [$violence => 'Blut', $addiction => ''], contentnotes_db_get_notes('thread', [4001])[4001] ?? null);

contentnotes_handle_insert_post($handler);
check('Post-Notes gespeichert', [$violence => 'Blut', $addiction => ''], contentnotes_db_get_notes('post', [5001])[5001] ?? null);

// Merge ergänzt nur
set_input(['contentnotes_form' => '1', 'contentnotes' => [$violence => ['on' => '1', 'note' => '']]]);
$merge_handler               = new stdClass();
$merge_handler->return_values = ['pid' => 5001, 'merge' => true];
$merge_handler->data          = ['fid' => 2];
contentnotes_handle_insert_merge($merge_handler);
check('Merge: vorhandene bleiben, Freitext bleibt', [$violence => 'Blut', $addiction => ''], contentnotes_db_get_notes('post', [5001])[5001] ?? null);

// Ohne Marker ändert sich nichts (z.B. andere Plugins, API, Quick-Edit)
set_input([]);
contentnotes_handle_insert_thread($handler);
check('ohne Marker: unverändert', [$violence => 'Blut', $addiction => ''], contentnotes_db_get_notes('thread', [4001])[4001] ?? null);

// Marker ohne Haken entfernt alles (Berechtigte nehmen alle Warnungen zurück)
set_input(['contentnotes_form' => '1']);
contentnotes_handle_insert_thread($handler);
check('Marker ohne Haken: Warnungen entfernt', [], contentnotes_db_get_notes('thread', [4001]));

// Ohne Recht ändert sich nichts, auch mit Marker
contentnotes_db_replace_notes('thread', 4001, [$violence => 'bleibt']);
as_user(5, '2');
set_input(['contentnotes_form' => '1', 'contentnotes' => [$addiction => ['on' => '1']]]);
contentnotes_handle_insert_thread($handler);
check('ohne Recht: unverändert', [$violence => 'bleibt'], contentnotes_db_get_notes('thread', [4001])[4001] ?? null);
as_user(1, '4');

// Validierung
$errors_handler = new class {
    public $errors = [];

    public function set_error($code)
    {
        $this->errors[] = $code;
    }
};
set_input(['contentnotes_form' => '1', 'contentnotes' => [$violence => ['on' => '1', 'note' => str_repeat('x', 256)]]]);
contentnotes_handle_validate($errors_handler);
check('Validierung: zu langer Freitext ⇒ Fehlercode', ['contentnotes_note_too_long'], $errors_handler->errors);
check('Fehlertext in Sprachdatei', true, isset($lang->postdata_contentnotes_note_too_long));

$ok_handler = new class {
    public $errors = [];

    public function set_error($code)
    {
        $this->errors[] = $code;
    }
};
set_input(['contentnotes_form' => '1', 'contentnotes' => [$violence => ['on' => '1', 'note' => str_repeat('x', 255)]]]);
contentnotes_handle_validate($ok_handler);
check('Validierung: 255 Zeichen ok', [], $ok_handler->errors);

// ---------------------------------------------------------------------------------------
echo "Aufräumen\n";
// ---------------------------------------------------------------------------------------
contentnotes_db_replace_notes('thread', 4002, [$violence => 'a']);
contentnotes_db_replace_notes('post', 5002, [$violence => 'b']);
contentnotes_db_replace_notes('post', 5003, [$violence => 'c']);
contentnotes_pending_deleted_pids(4002, [5002, 5003]);
contentnotes_handle_delete_thread(4002);
check('Thread löschen: Thread-Notes weg', [], contentnotes_db_get_notes('thread', [4002]));
check('Thread löschen: Post-Notes der Posts weg', [], contentnotes_db_get_notes('post', [5002, 5003]));
check('pending pids vergessen', [], contentnotes_pending_deleted_pids(4002));

contentnotes_db_replace_notes('post', 5004, [$violence => 'd']);
contentnotes_handle_delete_post(5004);
check('Post löschen: Notes weg', [], contentnotes_db_get_notes('post', [5004]));

contentnotes_db_save_user_prefs(790, [$violence => 1]);
$user_handler              = new stdClass();
$user_handler->delete_uids = [790];
contentnotes_handle_user_delete($user_handler);
check('Mitglied löschen: Prefs weg', 0, (int)$db->fetch_field($db->simple_select('contentnotes_userprefs', 'COUNT(*) AS n', 'uid = 790'), 'n'));

// ---------------------------------------------------------------------------------------
echo "Inline-MyCode [cw]\n";
// ---------------------------------------------------------------------------------------
as_user(0, '1');
$out = contentnotes_parse_inline('Vorher [cw=gewalt|Folter]geheimer Text[/cw] nachher');
check_true('Warnung: Wrapper mit data-cw', strpos($out, '<span class="contentnotes_inline" data-cw="gewalt" role="button" tabindex="0" aria-expanded="false"') === 0 || strpos($out, 'data-cw="gewalt"') !== false);
check_true('Warnung: Text bleibt im Fluss (inline spans, kein div)', strpos($out, '<div') === false && strpos($out, 'contentnotes_inline_text">geheimer Text</span>') !== false);
check_true('Warnung: Label mit Kategorie und Hinweis (Name escaped)', strpos($out, '&#9888; Gewalt &lt;b&gt;: Folter') !== false);
check_true('Warnung: Umgebungstext unverändert', strpos($out, 'Vorher ') === 0 && substr($out, -8) === ' nachher');

$out = contentnotes_parse_inline('[cw=gewalt]ohne Hinweis[/cw]');
check_true('ohne Hinweis: kein Doppelpunkt im Label', strpos($out, '&#9888; Gewalt &lt;b&gt;</span>') !== false);

$out = contentnotes_parse_inline('[cw=unbekannt]x[/cw]');
check_true('unbekanntes Kürzel ⇒ generisch', strpos($out, '&#9888; Content note') !== false);
$out = contentnotes_parse_inline('[cw]x[/cw]');
check_true('ohne Kürzel ⇒ generisch', strpos($out, '&#9888; Content note') !== false && strpos($out, 'data-cw') === false);

$out = contentnotes_parse_inline('[CW=GEWALT]groß[/CW]');
check_true('Groß-/Kleinschreibung egal', strpos($out, 'data-cw="gewalt"') !== false && strpos($out, '&#9888; Gewalt') !== false);

$out = contentnotes_parse_inline('[cw=gewalt|<script>alert(1)</script>x" onmouseover="y]t[/cw]');
check_true('Hinweis: kein <script>, kein ausbrechendes Attribut', strpos($out, '<script') === false && strpos($out, '" onmouseover') === false);

$out = contentnotes_parse_inline('[cw=gewalt|&lt;b&gt;fett&lt;/b&gt; &amp; mehr]t[/cw]');
check_true('Hinweis: bereits escapte Tags werden entfernt, & bleibt', strpos($out, '<b>') === false && strpos($out, 'fett &amp; mehr') !== false);

$out = contentnotes_parse_inline('[cw=gewalt]außen [cw=sucht]innen[/cw] weiter[/cw]');
check('verschachtelt: beide aufgelöst', 2, substr_count($out, 'class="contentnotes_inline"'));
check_true('verschachtelt: kein übrig gebliebenes [cw]', strpos($out, '[cw') === false && strpos($out, '[/cw]') === false);

$out = contentnotes_parse_inline("[cw=gewalt]zwei\nZeilen[/cw]");
check_true('mehrzeilig', strpos($out, "zwei\nZeilen") !== false);

check('kein [cw] ⇒ String unverändert', 'nur Text', contentnotes_parse_inline('nur Text'));
check('offenes [cw] ohne Ende unverändert', '[cw=gewalt]offen', contentnotes_parse_inline('[cw=gewalt]offen'));

// Vorlieben: einblenden ⇒ nur Text
as_user(791, '2');
contentnotes_db_save_user_prefs(791, [$violence => 1]);
$out = contentnotes_parse_inline('a [cw=gewalt|x]sichtbar[/cw] b');
check('Pref einblenden: nur der Text', 'a sichtbar b', $out);
$out = contentnotes_parse_inline('a [cw=sucht]warn[/cw] b');
check_true('Pref einblenden gilt nur für diese Kategorie', strpos($out, 'contentnotes_inline') !== false);
$out = contentnotes_parse_inline('a [cw=unbekannt]warn[/cw] b');
check_true('unbekannte Kategorie bleibt Warnung', strpos($out, 'contentnotes_inline') !== false);
contentnotes_db_delete_user_prefs([791]);
as_user(0, '1');

// Plugin aus: Markierung entfernen, Text bleibt
$mybb->settings['contentnotes_enabled'] = 0;
check('Plugin aus: nur Text', 'a x b', contentnotes_parse_inline('a [cw=gewalt|h]x[/cw] b'));
$mybb->settings['contentnotes_enabled'] = 1;

// Klartext (RSS, E-Mail)
check('Klartext-Ersatz', 'a [Content note: Gewalt <b>] b', contentnotes_parse_inline_text('a [cw=gewalt|h]geheim[/cw] b'));
check('Klartext unbekannt', '[Content note: Content note]', contentnotes_parse_inline_text('[cw=zzz]geheim[/cw]'));

// ---------------------------------------------------------------------------------------
echo "Parser komplett (postParser mit Hooks)\n";
// ---------------------------------------------------------------------------------------
$parser  = new postParser();
$options = ['allow_html' => 0, 'allow_mycode' => 1, 'allow_smilies' => 0, 'allow_imgcode' => 0, 'allow_videocode' => 0, 'filter_badwords' => 0, 'nl2br' => 1];
$html    = $parser->parse_message('Text [cw=gewalt|Folter]geheim[/cw] Ende', $options);
check_true('postParser: [cw] ersetzt', strpos($html, 'contentnotes_inline_text">geheim</span>') !== false && strpos($html, '[cw') === false);

$html = $parser->parse_message("[code]\n[cw=gewalt]im Code[/cw]\n[/code]", $options);
check_true('postParser: [cw] im [code] bleibt Rohtext', strpos($html, '[cw=gewalt]im Code[/cw]') !== false && strpos($html, 'contentnotes_inline') === false);

$html = $parser->parse_message('[b][cw=sucht]fett[/cw][/b] <img src=x onerror=alert(1)>', $options);
check_true('postParser: HTML bleibt escaped', strpos($html, '<img') === false && strpos($html, 'contentnotes_inline') !== false);

$options['allow_html'] = 1;
$html = $parser->parse_message('[cw=gewalt|<i>x</i>]t[/cw]', $options);
check_true('postParser mit allow_html: Tags im Hinweis entfernt', strpos($html, '<i>x</i>') === false);

// ---------------------------------------------------------------------------------------
echo "Formular rendern und Escaping\n";
// ---------------------------------------------------------------------------------------
as_user(1, '4');
$form = contentnotes_build_form([$violence => 'a"><script>x</script>']);
check_true('Formular: Checkbox je aktive Kategorie', substr_count($form, 'type="checkbox"') === 2);
check_true('Formular: inaktive Kategorie nicht wählbar', strpos($form, 'contentnotes[' . $retired . ']') === false);
check_true('Formular: gesetzte Kategorie angehakt, Freitext escaped', strpos($form, 'name="contentnotes[' . $violence . '][on]" value="1" checked="checked"') !== false && strpos($form, '<script>') === false);
check_true('Formular: Marker-Feld', strpos($form, 'name="contentnotes_form" value="1"') !== false);
check_true('Formular: aufgeklappt wenn etwas gesetzt', strpos($form, '<details class="contentnotes_details" open="open">') !== false);
$form = contentnotes_build_form([]);
check_true('Formular: zugeklappt wenn nichts gesetzt', strpos($form, ' open=') === false);
$form = contentnotes_build_form([$retired => 'x']);
check_true('Formular: bereits gesetzte inaktive wird angeboten (zum Entfernen)', strpos($form, 'contentnotes[' . $retired . '][on]') !== false);
$form = contentnotes_build_form([], true);
check_true('Schnellantwort-Variante', strpos($form, 'width="22%"') !== false);

// ---------------------------------------------------------------------------------------
echo "Veil und Badge\n";
// ---------------------------------------------------------------------------------------
as_user(0, '1');
$post = ['pid' => 1, 'message' => '<p>Inhalt</p>'];
contentnotes_veil_post($post, [$violence => 'Folter']);
check_true('Post-Veil: Inhalt eingebettet und verdeckt', strpos($post['message'], 'contentnotes_veiled" data-contentnotes-veil="post"><p>Inhalt</p>') !== false);
check_true('Post-Veil: Kategorie und Freitext im Warnkopf (escaped)', strpos($post['message'], '<strong>Gewalt &lt;b&gt;</strong>: Folter') !== false);
check_true('Post-Veil: Button', strpos($post['message'], 'type="button" class="button contentnotes_reveal"') !== false);

as_user(792, '2');
contentnotes_db_save_user_prefs(792, [$violence => 1]);
$post = ['pid' => 1, 'message' => '<p>Inhalt</p>'];
contentnotes_veil_post($post, [$violence => 'Folter']);
check('Post-Veil: Pref einblenden ⇒ Nachricht unverändert', '<p>Inhalt</p>', $post['message']);
contentnotes_db_delete_user_prefs([792]);

// Badge (forumdisplay): globale $thread/$threadcache wie im Core
as_user(0, '1');
contentnotes_db_replace_notes('thread', 6001, [$violence => 'Folter', $addiction => '']);
$thread       = ['tid' => 6001, 'subject' => 'x'];
$threadcache  = [6001 => $thread, 6002 => ['tid' => 6002]];
contentnotes_handle_thread_badge();
check_true('Badge: Kategorienamen', strpos($thread['contentnotes_badge'], 'Sucht &amp; Abhängigkeit, Gewalt &lt;b&gt;') !== false);
check_true('Badge: title mit Freitext', strpos($thread['contentnotes_badge'], 'Gewalt &lt;b&gt;: Folter') !== false);
$thread = ['tid' => 6002];
contentnotes_handle_thread_badge();
check('Badge: Thread ohne Notes ⇒ leer, aber Schlüssel gesetzt', '', $thread['contentnotes_badge'] ?? null);
contentnotes_db_delete_notes('thread', [6001]);

// ---------------------------------------------------------------------------------------
echo "Kategorie löschen räumt auf\n";
// ---------------------------------------------------------------------------------------
contentnotes_db_replace_notes('thread', 7001, [$retired => 'x', $violence => 'y']);
contentnotes_db_save_user_prefs(793, [$retired => 1, $violence => 0]);
contentnotes_db_delete_category($retired);
check('Notes der Kategorie weg, andere bleiben', [$violence => 'y'], contentnotes_db_get_notes('thread', [7001])[7001] ?? null);
check('Prefs der Kategorie weg, andere bleiben', [$violence => 0], contentnotes_db_get_user_prefs(793) === [] ? [] : array_diff_key(contentnotes_db_get_user_prefs(793), [$retired => 1]));
check('Kategorie nicht mehr im Cache', null, contentnotes_db_get_category($retired));
contentnotes_db_delete_notes('thread', [7001]);
contentnotes_db_delete_user_prefs([793]);

// Aufräumen: Fixtures entfernen
$db->delete_query('contentnotes_notes');
$db->delete_query('contentnotes_userprefs');
$db->delete_query('contentnotes_categories');
contentnotes_db_rebuild_cache();

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
