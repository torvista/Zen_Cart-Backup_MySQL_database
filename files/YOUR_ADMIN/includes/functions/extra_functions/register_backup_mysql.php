<?php
/**
 * Backup MySQL Admin Page Registration.
 *
 * Attempts to create a link to the Backup MySQL tool in the Zen Cart admin menu in Zen Cart 1.5+.
 * After running successfully once, this file deletes itself as it is never needed again!
 * @torvista 2015-01-13, based on Conor's code
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$this_mod_name = "Backup MySQL";
$this_mod_box_tools_define = 'BOX_TOOLS_BACKUP_MYSQL';
$this_mod_filename_define = 'FILENAME_BACKUP_MYSQL';
$this_mod_page_key = "backup_mysql";
$this_mod_file_list = array(
    "backup_mysql.php",
    "includes/extra_datafiles/backup_mysql.php",
    "includes/languages/english/backup_mysql.php",
    "includes/languages/english/extra_definitions/backup_mysql.php"
);
$this_file = "includes/functions/extra_functions/register_backup_mysql.php";

$style_error = "background: pink; border: 1px solid red; margin: 1em; padding: 0.4em;";
$style_success = "background: palegreen; border: 1px solid black; margin: 1em; padding: 0.4em;";

if (function_exists('zen_register_admin_page') && !zen_page_key_exists($this_mod_page_key)) {
    print '<p style="' . $style_success . '">Processing ' . $this_mod_name . ' Admin Page Registration' . "</p>\n";

    //check the existence of the files required for this mod
    $error_messages = array();
    foreach ($this_mod_file_list as $file) {
        if (@!file_exists($file)) {
            $error_messages[] = $this_mod_name . " file: $file NOT found";
        } else { //debug
            //$error_messages[] = $this_mod_name . " file: $file WAS found";
        }
    }

    if (sizeof($error_messages) > 0) {//an installation file is missing
        foreach ($error_messages as $error_message) {
            print '<p style="' . $style_error . '">' . $error_message . "</p>\n";
        }
    } else {//all required files are in place
        //get the position of the next entry in the tools menu
        $max_sort_order = $db->Execute("SELECT MAX(sort_order) +1 AS next_menu_item FROM " . TABLE_ADMIN_PAGES . " WHERE menu_key = 'tools'");
        $next_menu_item = $max_sort_order->fields['next_menu_item'];

        //register the admin page and create the menu item
        zen_register_admin_page($this_mod_page_key, $this_mod_box_tools_define, $this_mod_filename_define, '', 'tools', 'Y', $next_menu_item);
        print '<p style="' . $style_success . '">All installation files found: ' . $this_mod_name . " has been added to the Tools menu</p>\n";
    }
}
