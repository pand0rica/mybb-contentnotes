<?php
// Content Notes – admin language file (English)

$l['contentnotes'] = "Content Notes";
$l['contentnotes_can_manage'] = "Can manage content note categories?";

// Tabs
$l['contentnotes_tab_list'] = "Categories";
$l['contentnotes_tab_desc_list'] = "All content note categories. Members choose in their User CP how each category is shown to them.";
$l['contentnotes_tab_add'] = "Add Category";
$l['contentnotes_tab_desc_add'] = "Create a new content note category.";
$l['contentnotes_tab_edit'] = "Edit Category";
$l['contentnotes_tab_desc_edit'] = "Change the details of this category.";

// Form fields
$l['contentnotes_field_name'] = "Name";
$l['contentnotes_field_name_desc'] = "Shown in warnings, badges and in the User CP (max. 100 characters).";
$l['contentnotes_field_slug'] = "Short code";
$l['contentnotes_field_slug_desc'] = "Unique short code used in the MyCode, for example <code>[cw=violence]text[/cw]</code>. Lowercase letters, digits, hyphen and underscore only (max. 50 characters).";
$l['contentnotes_field_slug_desc_edit'] = "Unique short code used in the MyCode. <strong>Careful:</strong> if you change it, existing <code>[cw=old-code]</code> markings in posts will show a generic content note instead of this category.";
$l['contentnotes_field_description'] = "Description";
$l['contentnotes_field_description_desc'] = "Optional. Shown next to the category in the User CP.";
$l['contentnotes_field_active'] = "Active";
$l['contentnotes_field_active_desc'] = "Inactive categories can no longer be chosen when writing. Existing markings keep working.";
$l['contentnotes_field_require_note'] = "Free text required";
$l['contentnotes_field_require_note_desc'] = "If enabled, a free text (e.g. what exactly happens) must be entered whenever this category is chosen.";
$l['contentnotes_field_disporder'] = "Display order";
$l['contentnotes_field_disporder_desc'] = "Categories are sorted by this number, lowest first.";
$l['contentnotes_save'] = "Save Category";
$l['contentnotes_save_order'] = "Save Display Order";

// Errors
$l['contentnotes_error_name_missing'] = "Please enter a name.";
$l['contentnotes_error_name_too_long'] = "The name is longer than 100 characters.";
$l['contentnotes_error_slug_invalid'] = "The short code must be 1 to 50 characters long and may only contain lowercase letters, digits, hyphen and underscore.";
$l['contentnotes_error_slug_exists'] = "This short code is already used by another category.";
$l['contentnotes_error_invalid_category'] = "The category does not exist.";

// Success messages
$l['contentnotes_success_added'] = "The category has been created.";
$l['contentnotes_success_edited'] = "The category has been saved.";
$l['contentnotes_success_activated'] = "The category has been activated.";
$l['contentnotes_success_deactivated'] = "The category has been deactivated.";
$l['contentnotes_success_deleted'] = "The category and all its markings and member settings have been deleted.";
$l['contentnotes_success_order'] = "The display order has been saved.";
$l['contentnotes_confirm_activate'] = "Do you really want to activate the category \"{1}\"? It can then be chosen again when writing.";
$l['contentnotes_confirm_deactivate'] = "Do you really want to deactivate the category \"{1}\"? It can no longer be chosen when writing; existing markings keep working.";
$l['contentnotes_confirm_delete'] = "Do you really want to delete the category \"{1}\"? All markings on threads and posts and all member settings for this category will be deleted as well.";

// Overview table
$l['contentnotes_col_name'] = "Name";
$l['contentnotes_col_slug'] = "Short code";
$l['contentnotes_col_description'] = "Description";
$l['contentnotes_col_status'] = "Status";
$l['contentnotes_col_order'] = "Order";
$l['contentnotes_col_uses'] = "Markings";
$l['contentnotes_col_controls'] = "Controls";
$l['contentnotes_status_active'] = "Active";
$l['contentnotes_status_inactive'] = "Inactive";
$l['contentnotes_none'] = "There are no categories yet. Create the first one with \"Add Category\".";
$l['contentnotes_edit'] = "Edit";
$l['contentnotes_activate'] = "Activate";
$l['contentnotes_deactivate'] = "Deactivate";
$l['contentnotes_delete'] = "Delete";

// Settings (title and description of the settings page)
$l['setting_group_contentnotes'] = "Content Notes";
$l['setting_group_contentnotes_desc'] = "Settings for content notes on threads, posts and text passages.";
$l['setting_contentnotes_enabled'] = "Enable Content Notes";
$l['setting_contentnotes_enabled_desc'] = "Turns the content note features in the forum on or off.";
$l['setting_contentnotes_groups'] = "Groups allowed to set content notes";
$l['setting_contentnotes_groups_desc'] = "Members of these groups may set, change and remove content notes on threads and posts and see the editor button.";
$l['setting_contentnotes_forums'] = "Forums with thread and post notes";
$l['setting_contentnotes_forums_desc'] = "In these forums the content note fields are offered when writing and editing threads and posts.";
$l['setting_contentnotes_editor_button'] = "Show editor button";
$l['setting_contentnotes_editor_button_desc'] = "Adds a content note button to the message editor for the allowed groups.";

// Admin log
$l['admin_log_config_contentnotes_add'] = "Added content note category #{1} ({2})";
$l['admin_log_config_contentnotes_edit'] = "Edited content note category #{1} ({2})";
$l['admin_log_config_contentnotes_toggle'] = "Changed active status of content note category #{1} ({2})";
$l['admin_log_config_contentnotes_delete'] = "Deleted content note category #{1} ({2})";
$l['admin_log_config_contentnotes_update_order'] = "Updated the display order of the content note categories";
