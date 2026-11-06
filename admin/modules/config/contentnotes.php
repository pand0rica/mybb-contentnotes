<?php
/**
 * Content Notes – ACP-Modul (Konfiguration): Kategorien für Inhaltswarnungen verwalten.
 *
 * @package ContentNotes
 */

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.');
}

// MyBB lädt Plugin-Sprachdateien im ACP nicht automatisch: als erste Anweisung laden.
$lang->load('contentnotes');

$page->add_breadcrumb_item($lang->contentnotes, 'index.php?module=config-contentnotes');

/**
 * Gibt die Tab-Navigation aus (nach output_header()).
 *
 * @param string $active Aktiver Tab: list, add oder edit
 * @return void
 */
function contentnotes_acp_nav_tabs(string $active): void
{
    global $page, $lang;

    $tabs = [
        'list' => [
            'title'       => $lang->contentnotes_tab_list,
            'link'        => 'index.php?module=config-contentnotes',
            'description' => $lang->contentnotes_tab_desc_list,
        ],
        'add' => [
            'title'       => $lang->contentnotes_tab_add,
            'link'        => 'index.php?module=config-contentnotes&amp;action=add',
            'description' => $lang->contentnotes_tab_desc_add,
        ],
    ];
    if ($active === 'edit') {
        $tabs['edit'] = [
            'title'       => $lang->contentnotes_tab_edit,
            'link'        => 'index.php?module=config-contentnotes',
            'description' => $lang->contentnotes_tab_desc_edit,
        ];
    }

    $page->output_nav_tabs($tabs, $active);
}

/**
 * Liest die Formularfelder einer Kategorie.
 *
 * @return array
 */
function contentnotes_acp_read_form(): array
{
    global $mybb;

    return [
        'name'        => trim($mybb->get_input('name')),
        'slug'        => strtolower(trim($mybb->get_input('slug'))),
        'description' => trim($mybb->get_input('description')),
        'active'      => $mybb->get_input('active', MyBB::INPUT_INT) === 1 ? 1 : 0,
        'disporder'   => $mybb->get_input('disporder', MyBB::INPUT_INT),
    ];
}

/**
 * Prüft die Formularfelder einer Kategorie.
 *
 * @param array $data        Formularwerte
 * @param int   $exclude_cid Beim Bearbeiten: diese Kategorie bei der Kürzel-Prüfung ignorieren
 * @return array Fehlermeldungen
 */
function contentnotes_acp_validate(array $data, int $exclude_cid = 0): array
{
    global $lang;

    $errors = [];

    if ($data['name'] === '') {
        $errors[] = $lang->contentnotes_error_name_missing;
    } elseif (my_strlen($data['name']) > 100) {
        $errors[] = $lang->contentnotes_error_name_too_long;
    }

    if ($data['slug'] === '' || !contentnotes_valid_slug($data['slug'])) {
        $errors[] = $lang->contentnotes_error_slug_invalid;
    } elseif (contentnotes_db_slug_exists($data['slug'], $exclude_cid)) {
        $errors[] = $lang->contentnotes_error_slug_exists;
    }

    return $errors;
}

/**
 * Gibt das Kategorie-Formular aus (Anlegen und Bearbeiten).
 *
 * @param string $action_url Ziel-URL (ohne my_post_key; den ergänzt Form selbst)
 * @param array  $data      Formularwerte
 * @param string $title     Titel des Formular-Containers
 * @param bool   $editing   true = Bearbeiten
 * @return void
 */
function contentnotes_acp_output_form(string $action_url, array $data, string $title, bool $editing): void
{
    global $lang;

    $form           = new Form($action_url, 'post');
    $form_container = new FormContainer($title);

    $form_container->output_row(
        $lang->contentnotes_field_name . ' <em>*</em>',
        $lang->contentnotes_field_name_desc,
        $form->generate_text_box('name', $data['name'], ['id' => 'name', 'maxlength' => 100]),
        'name'
    );
    $form_container->output_row(
        $lang->contentnotes_field_slug . ' <em>*</em>',
        $editing ? $lang->contentnotes_field_slug_desc_edit : $lang->contentnotes_field_slug_desc,
        $form->generate_text_box('slug', $data['slug'], ['id' => 'slug', 'maxlength' => 50]),
        'slug'
    );
    $form_container->output_row(
        $lang->contentnotes_field_description,
        $lang->contentnotes_field_description_desc,
        $form->generate_text_area('description', $data['description'], ['id' => 'description', 'rows' => 3]),
        'description'
    );
    $form_container->output_row(
        $lang->contentnotes_field_active,
        $lang->contentnotes_field_active_desc,
        $form->generate_yes_no_radio('active', (int)$data['active']),
        'active'
    );
    $form_container->output_row(
        $lang->contentnotes_field_disporder,
        $lang->contentnotes_field_disporder_desc,
        $form->generate_numeric_field('disporder', (int)$data['disporder'], ['id' => 'disporder', 'min' => 0]),
        'disporder'
    );

    $form_container->end();

    $buttons = [$form->generate_submit_button($lang->contentnotes_save)];
    $form->output_submit_wrapper($buttons);
    $form->end();
}

$action = $mybb->get_input('action');
$cid    = $mybb->get_input('cid', MyBB::INPUT_INT);

$list_url = 'index.php?module=config-contentnotes';

// ---------------------------------------------------------------------------------------
// Kategorie anlegen
// ---------------------------------------------------------------------------------------
if ($action === 'add') {
    $errors = [];
    $data   = ['name' => '', 'slug' => '', 'description' => '', 'active' => 1, 'disporder' => 0];

    // Schreibt nur unter POST: admin/index.php prüft den my_post_key selbst und stuft bei Fehler auf GET herunter
    if ($mybb->request_method === 'post') {
        $data   = contentnotes_acp_read_form();
        $errors = contentnotes_acp_validate($data);

        if (!$errors) {
            $new_cid = contentnotes_db_insert_category($data);
            log_admin_action($new_cid, $data['name']);

            flash_message($lang->contentnotes_success_added, 'success');
            admin_redirect($list_url);
        }
    }

    $page->add_breadcrumb_item($lang->contentnotes_tab_add, $list_url . '&amp;action=add');
    $page->output_header($lang->contentnotes);
    contentnotes_acp_nav_tabs('add');

    if ($errors) {
        $page->output_inline_error($errors);
    }

    contentnotes_acp_output_form($list_url . '&amp;action=add', $data, $lang->contentnotes_tab_add, false);
    $page->output_footer();
    exit;
}

// ---------------------------------------------------------------------------------------
// Kategorie bearbeiten
// ---------------------------------------------------------------------------------------
if ($action === 'edit') {
    $category = contentnotes_db_get_category($cid);
    if ($category === null) {
        flash_message($lang->contentnotes_error_invalid_category, 'error');
        admin_redirect($list_url);
    }

    $errors = [];
    $data   = $category;

    // Schreibt nur unter POST: admin/index.php prüft den my_post_key selbst und stuft bei Fehler auf GET herunter
    if ($mybb->request_method === 'post') {
        $data   = contentnotes_acp_read_form();
        $errors = contentnotes_acp_validate($data, $cid);

        if (!$errors) {
            contentnotes_db_update_category($cid, $data);
            log_admin_action($cid, $data['name']);

            flash_message($lang->contentnotes_success_edited, 'success');
            admin_redirect($list_url);
        }
    }

    $page->add_breadcrumb_item($lang->contentnotes_tab_edit, $list_url . '&amp;action=edit&amp;cid=' . $cid);
    $page->output_header($lang->contentnotes);
    contentnotes_acp_nav_tabs('edit');

    if ($errors) {
        $page->output_inline_error($errors);
    }

    contentnotes_acp_output_form($list_url . '&amp;action=edit&amp;cid=' . $cid, $data, $lang->contentnotes_tab_edit, true);
    $page->output_footer();
    exit;
}

// ---------------------------------------------------------------------------------------
// Aktiv/Inaktiv umschalten (Bestätigungsseite, geschrieben wird nur unter POST)
// ---------------------------------------------------------------------------------------
if ($action === 'toggle') {
    $category = contentnotes_db_get_category($cid);
    if ($category === null) {
        flash_message($lang->contentnotes_error_invalid_category, 'error');
        admin_redirect($list_url);
    }

    if ($mybb->get_input('no')) {
        admin_redirect($list_url);
    }

    $is_active = !empty($category['active']);

    if ($mybb->request_method === 'post') {
        contentnotes_db_update_category($cid, ['active' => $is_active ? 0 : 1]);
        log_admin_action($cid, $category['name']);

        flash_message($is_active ? $lang->contentnotes_success_deactivated : $lang->contentnotes_success_activated, 'success');
        admin_redirect($list_url);
    }

    $page->output_confirm_action(
        $list_url . '&amp;action=toggle&amp;cid=' . $cid,
        $lang->sprintf(
            $is_active ? $lang->contentnotes_confirm_deactivate : $lang->contentnotes_confirm_activate,
            htmlspecialchars_uni($category['name'])
        )
    );
    exit;
}

// ---------------------------------------------------------------------------------------
// Kategorie löschen (Bestätigungsseite, dann POST)
// ---------------------------------------------------------------------------------------
if ($action === 'delete') {
    $category = contentnotes_db_get_category($cid);
    if ($category === null) {
        flash_message($lang->contentnotes_error_invalid_category, 'error');
        admin_redirect($list_url);
    }

    if ($mybb->get_input('no')) {
        admin_redirect($list_url);
    }

    // Schreibt nur unter POST: admin/index.php prüft den my_post_key selbst und stuft bei Fehler auf GET herunter
    if ($mybb->request_method === 'post') {
        contentnotes_db_delete_category($cid);
        log_admin_action($cid, $category['name']);

        flash_message($lang->contentnotes_success_deleted, 'success');
        admin_redirect($list_url);
    }

    $page->output_confirm_action(
        $list_url . '&amp;action=delete&amp;cid=' . $cid,
        $lang->sprintf($lang->contentnotes_confirm_delete, htmlspecialchars_uni($category['name']))
    );
    exit;
}

// ---------------------------------------------------------------------------------------
// Sortierung speichern (POST)
// ---------------------------------------------------------------------------------------
if ($action === 'update_order') {
    if ($mybb->request_method !== 'post') {
        admin_redirect($list_url);
    }

    $orders = $mybb->get_input('disporder', MyBB::INPUT_ARRAY);
    foreach (contentnotes_db_get_categories() as $category_id => $category) {
        if (isset($orders[$category_id])) {
            contentnotes_db_update_category((int)$category_id, ['disporder' => (int)$orders[$category_id]]);
        }
    }
    log_admin_action();

    flash_message($lang->contentnotes_success_order, 'success');
    admin_redirect($list_url);
}

// ---------------------------------------------------------------------------------------
// Übersicht
// ---------------------------------------------------------------------------------------
$page->output_header($lang->contentnotes);
contentnotes_acp_nav_tabs('list');

$form  = new Form($list_url . '&amp;action=update_order', 'post');
$table = new Table();
$table->construct_header($lang->contentnotes_col_name);
$table->construct_header($lang->contentnotes_col_slug);
$table->construct_header($lang->contentnotes_col_description);
$table->construct_header($lang->contentnotes_col_status, ['class' => 'align_center']);
$table->construct_header($lang->contentnotes_col_order, ['class' => 'align_center', 'width' => '8%']);
$table->construct_header($lang->contentnotes_col_uses, ['class' => 'align_center']);
$table->construct_header($lang->contentnotes_col_controls, ['class' => 'align_center', 'colspan' => 3]);

$categories = contentnotes_db_get_categories();
$counts     = contentnotes_db_count_notes_per_category();

if (!$categories) {
    $table->construct_cell($lang->contentnotes_none, ['colspan' => 9, 'class' => 'align_center']);
    $table->construct_row();
}

foreach ($categories as $category_id => $category) {
    $active = !empty($category['active']);

    $table->construct_cell('<strong>' . htmlspecialchars_uni($category['name']) . '</strong>');
    $table->construct_cell('<code>' . htmlspecialchars_uni($category['slug']) . '</code>');
    $table->construct_cell(htmlspecialchars_uni($category['description']));
    $table->construct_cell($active ? $lang->contentnotes_status_active : $lang->contentnotes_status_inactive, ['class' => 'align_center']);
    $table->construct_cell(
        $form->generate_numeric_field('disporder[' . (int)$category_id . ']', (int)$category['disporder'], ['style' => 'width: 50px;', 'min' => 0]),
        ['class' => 'align_center']
    );
    $table->construct_cell((int)($counts[$category_id] ?? 0), ['class' => 'align_center']);
    $table->construct_cell(
        '<a href="' . $list_url . '&amp;action=edit&amp;cid=' . (int)$category_id . '">' . $lang->contentnotes_edit . '</a>',
        ['class' => 'align_center']
    );
    $table->construct_cell(
        '<a href="' . $list_url . '&amp;action=toggle&amp;cid=' . (int)$category_id . '">'
        . ($active ? $lang->contentnotes_deactivate : $lang->contentnotes_activate) . '</a>',
        ['class' => 'align_center']
    );
    $table->construct_cell(
        '<a href="' . $list_url . '&amp;action=delete&amp;cid=' . (int)$category_id . '">' . $lang->contentnotes_delete . '</a>',
        ['class' => 'align_center']
    );
    $table->construct_row();
}

$table->output($lang->contentnotes);

if ($categories) {
    $buttons = [$form->generate_submit_button($lang->contentnotes_save_order)];
    $form->output_submit_wrapper($buttons);
}
$form->end();

$page->output_footer();
