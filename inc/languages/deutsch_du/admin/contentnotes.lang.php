<?php
// Content Notes – Admin-Sprachdatei (Deutsch, du-Form)

$l['contentnotes'] = "Inhaltswarnungen";
$l['contentnotes_description'] = "Verwaltung der Kategorien für Inhaltswarnungen.";
$l['contentnotes_can_manage'] = "Darf Kategorien für Inhaltswarnungen verwalten?";
$l['contentnotes_invalid_key'] = "Der Sicherheitsschlüssel war ungültig oder fehlte. Bitte versuche es noch einmal.";

// Tabs
$l['contentnotes_tab_list'] = "Kategorien";
$l['contentnotes_tab_desc_list'] = "Alle Kategorien für Inhaltswarnungen. Mitglieder stellen im Benutzer-CP ein, wie jede Kategorie für sie angezeigt wird.";
$l['contentnotes_tab_add'] = "Kategorie hinzufügen";
$l['contentnotes_tab_desc_add'] = "Lege eine neue Kategorie für Inhaltswarnungen an.";
$l['contentnotes_tab_edit'] = "Kategorie bearbeiten";
$l['contentnotes_tab_desc_edit'] = "Ändere die Angaben dieser Kategorie.";

// Formularfelder
$l['contentnotes_field_name'] = "Name";
$l['contentnotes_field_name_desc'] = "Wird in Warnungen, Hinweisen und im Benutzer-CP angezeigt (max. 100 Zeichen).";
$l['contentnotes_field_slug'] = "Kürzel";
$l['contentnotes_field_slug_desc'] = "Eindeutiges Kürzel für den MyCode, zum Beispiel <code>[cw=gewalt]Text[/cw]</code>. Nur Kleinbuchstaben, Ziffern, Bindestrich und Unterstrich (max. 50 Zeichen).";
$l['contentnotes_field_slug_desc_edit'] = "Eindeutiges Kürzel für den MyCode. <strong>Achtung:</strong> Wenn du es änderst, zeigen vorhandene Markierungen mit <code>[cw=altes-kuerzel]</code> in Beiträgen nur noch eine allgemeine Inhaltswarnung statt dieser Kategorie.";
$l['contentnotes_field_description'] = "Beschreibung";
$l['contentnotes_field_description_desc'] = "Optional. Wird im Benutzer-CP neben der Kategorie angezeigt.";
$l['contentnotes_field_active'] = "Aktiv";
$l['contentnotes_field_active_desc'] = "Inaktive Kategorien lassen sich beim Schreiben nicht mehr auswählen. Bestehende Markierungen wirken weiter.";
$l['contentnotes_field_disporder'] = "Sortierung";
$l['contentnotes_field_disporder_desc'] = "Die Kategorien werden nach dieser Zahl sortiert, die kleinste zuerst.";
$l['contentnotes_save'] = "Kategorie speichern";
$l['contentnotes_save_order'] = "Sortierung speichern";

// Fehler
$l['contentnotes_error_name_missing'] = "Bitte gib einen Namen an.";
$l['contentnotes_error_name_too_long'] = "Der Name ist länger als 100 Zeichen.";
$l['contentnotes_error_slug_invalid'] = "Das Kürzel muss 1 bis 50 Zeichen lang sein und darf nur Kleinbuchstaben, Ziffern, Bindestrich und Unterstrich enthalten.";
$l['contentnotes_error_slug_exists'] = "Dieses Kürzel wird bereits von einer anderen Kategorie verwendet.";
$l['contentnotes_error_invalid_category'] = "Die Kategorie existiert nicht.";

// Erfolgsmeldungen
$l['contentnotes_success_added'] = "Die Kategorie wurde angelegt.";
$l['contentnotes_success_edited'] = "Die Kategorie wurde gespeichert.";
$l['contentnotes_success_activated'] = "Die Kategorie wurde aktiviert.";
$l['contentnotes_success_deactivated'] = "Die Kategorie wurde deaktiviert.";
$l['contentnotes_success_deleted'] = "Die Kategorie samt allen Markierungen und Einstellungen von Mitgliedern wurde gelöscht.";
$l['contentnotes_success_order'] = "Die Sortierung wurde gespeichert.";
$l['contentnotes_confirm_delete'] = "Möchtest du die Kategorie \"{1}\" wirklich löschen? Alle Markierungen an Threads und Beiträgen und alle Einstellungen von Mitgliedern zu dieser Kategorie werden ebenfalls gelöscht.";

// Übersichtstabelle
$l['contentnotes_col_name'] = "Name";
$l['contentnotes_col_slug'] = "Kürzel";
$l['contentnotes_col_description'] = "Beschreibung";
$l['contentnotes_col_status'] = "Status";
$l['contentnotes_col_order'] = "Sortierung";
$l['contentnotes_col_uses'] = "Markierungen";
$l['contentnotes_col_controls'] = "Aktionen";
$l['contentnotes_status_active'] = "Aktiv";
$l['contentnotes_status_inactive'] = "Inaktiv";
$l['contentnotes_none'] = "Es gibt noch keine Kategorien. Lege mit \"Kategorie hinzufügen\" die erste an.";
$l['contentnotes_edit'] = "Bearbeiten";
$l['contentnotes_activate'] = "Aktivieren";
$l['contentnotes_deactivate'] = "Deaktivieren";
$l['contentnotes_delete'] = "Löschen";

// Einstellungen (Titel und Beschreibung auf der Einstellungsseite)
$l['setting_group_contentnotes'] = "Inhaltswarnungen";
$l['setting_group_contentnotes_desc'] = "Einstellungen für Inhaltswarnungen an Threads, Beiträgen und Textstellen.";
$l['setting_contentnotes_enabled'] = "Inhaltswarnungen aktivieren";
$l['setting_contentnotes_enabled_desc'] = "Schaltet die Funktionen für Inhaltswarnungen im Forum ein oder aus.";
$l['setting_contentnotes_groups'] = "Gruppen, die Inhaltswarnungen setzen dürfen";
$l['setting_contentnotes_groups_desc'] = "Mitglieder dieser Gruppen dürfen Inhaltswarnungen an Threads und Beiträgen setzen, ändern und entfernen und sehen den Editor-Button.";
$l['setting_contentnotes_forums'] = "Foren mit Thread- und Beitragswarnungen";
$l['setting_contentnotes_forums_desc'] = "In diesen Foren werden die Felder für Inhaltswarnungen beim Schreiben und Bearbeiten von Threads und Beiträgen angeboten.";
$l['setting_contentnotes_editor_button'] = "Editor-Button anzeigen";
$l['setting_contentnotes_editor_button_desc'] = "Fügt den berechtigten Gruppen einen Button für Inhaltswarnungen im Editor hinzu.";

// Admin-Log
$l['admin_log_config_contentnotes_add'] = "Kategorie für Inhaltswarnungen #{1} ({2}) angelegt";
$l['admin_log_config_contentnotes_edit'] = "Kategorie für Inhaltswarnungen #{1} ({2}) bearbeitet";
$l['admin_log_config_contentnotes_toggle'] = "Aktiv-Status der Kategorie für Inhaltswarnungen #{1} ({2}) geändert";
$l['admin_log_config_contentnotes_delete'] = "Kategorie für Inhaltswarnungen #{1} ({2}) gelöscht";
$l['admin_log_config_contentnotes_update_order'] = "Sortierung der Kategorien für Inhaltswarnungen aktualisiert";
