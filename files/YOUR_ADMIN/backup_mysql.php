<?php
//
//
//
//
// +----------------------------------------------------------------------+
// |zen-cart Open Source E-commerce                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 2003 The zen-cart developers                           |
// |                                                                      |
// | http://www.zen-cart.com/index.php                                    |
// |                                                                      |
// | Portions Copyright (c) 2003 osCommerce                               |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the GPL license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at the following url:           |
// | http://www.zen-cart.com/license/2_0.txt.                             |
// | If you did not receive a copy of the zen-cart license and are unable |
// | to obtain it through the world-wide-web, please send a note to       |
// | license@zen-cart.com so we can mail you a copy immediately.          |
// +----------------------------------------------------------------------+
// $Id: backup_mysql.php modified 2019-05-15 torvista $
//
//TODO steve messages are delayed by one refresh
//Windows System Error Codes https://msdn.microsoft.com/en-us/library/windows/desktop/ms681381(v=vs.85).aspx
//UNIX Exit codes http://www.faqs.org/docs/abs/HTML/exitcodes.html
require('includes/application_top.php');

define('LINK_ERROR_CODES_WIN', 'https://msdn.microsoft.com/en-us/library/windows/desktop/ms681381(v=vs.85).aspx');
define('LINK_ERROR_CODES_NIX', 'http://www.faqs.org/docs/abs/HTML/exitcodes.html');

if (stristr(PHP_OS, "win")) { //Windows
    $os='win';
    define('OS_DELIM', '"');
} else {//Unix
    $os='nix';
    define('OS_DELIM', "'");
    }

$debug = true; //anything except 0 will force debug info
$possibles = array();
$dump_params = '';

$tables_to_export = (isset($_GET['tables']) && $_GET['tables'] != '') ? str_replace(',', ' ', $_GET['tables']) : '';
$redirect = (isset($_GET['returnto']) && $_GET['returnto'] != '') ? $_GET['returnto'] : '';
$resultcodes = '';
$_POST['compress'] = (isset($_REQUEST['compress'])) ? $_REQUEST['compress'] : false;
$strA = '';
$strB = '';

$compress_override = ((isset($_GET['comp']) && $_GET['comp'] > 0) || COMPRESS_OVERRIDE == 'true') ? true : false;

$debug = (isset ($_POST['debug']) || isset ($_GET['debug']));



if ($debug) {
    $messageStack->add(TEXT_DEBUG_ON, 'info');
    $messageStack->add('$_POST[\'debug\'] =' . $_POST['debug'] . ', $debug=' . $debug, 'info');
    $messageStack->add('$_GET[\'debug\'] =' . $_GET['debug'] . ', $debug=' . $debug, 'info');
}
$skip_locks_requested = (isset($_REQUEST['skiplocks']) && $_REQUEST['skiplocks'] == 'yes');


// check to see if open_basedir restrictions in effect -- if so, likely won't be able to use this tool.
$flag_basedir = false;
$open_basedir = @ini_get('open_basedir');
if ($open_basedir != '') {
    $basedir_check_array = explode(':', $open_basedir);
    foreach ($basedir_check_array as $basedir_check) {
        if (!strstr(DIR_FS_ADMIN, $basedir_check)) $flag_basedir = true;
    }
    if ($flag_basedir) $messageStack->add(ERROR_CANT_BACKUP_IN_SAFE_MODE, 'error');
}
// check to see if "exec()" is disabled in PHP -- if so, won't be able to use this tool.
$exec_disabled = false;
$php_disabled_functions = @ini_get("disable_functions");

    if (in_array('exec', preg_split('/,/', str_replace(' ', '', $php_disabled_functions)))) {
        $messageStack->add(ERROR_EXEC_DISABLED, 'error');
        $exec_disabled = true;
    }
    if ( in_array('shell_exec', preg_split('/,/', str_replace(' ', '', $php_disabled_functions))) && $os == 'nix') {
        //$messageStack->add(ERROR_SHELL_EXEC_DISABLED, 'error');//shell_exec only used on Unix to find mysql: show error later
        $shell_exec_disabled = true;
    }
if ( $exec_disabled || ($shell_exec_disabled) ) {
    $messageStack->add(ERROR_PHP_DISABLED_FUNCTIONS . $php_disabled_functions, 'warning');
}
// WHERE ARE THE MYSQL EXECUTABLES?

// Note that the locations of the executables are also defined in admin/includes/extra_datafiles/backup_mysql.php file
// MYSQL_EXE and MYSQLDUMP_EXE (and MYSQL_EXE_LOCAL and MYSQLDUMP_EXE_LOCAL for a secondary development server).
// These can occasionally be overridden in the URL by specifying &tool=/path/to/foo/bar/plus/utilname, depending on server support
// This section will check those settings and other common paths to confirm the correct location of the two executables required.
// Do NOT change these test paths here ... edit/correct the paths in the extra_datafiles/backup_mysql.php file instead.

// Try and get some paths automatically

if ($os=="win") { //Windows
    $basedir_result = $db->Execute("SHOW VARIABLES LIKE 'basedir'");
    while (!$basedir_result->EOF) {
        $path = $basedir_result->fields['Value'];
        //check the path
        if (preg_match('/^[A-Z]:/i', $path)) {//path has a drive letter
            $windows_mysql_path = $path . '/bin/';
        } else {//path has no drive letter: portable installation Xampp?
            $possibles = array_merge(range('A', 'Z'), range('a', 'z'));
            array_walk($possibles, function (&$value, $key, $path) {
                $value = $value . $path . '/bin/';
            }, ':' . $path);//make an array of all the possible drives+path
        }
        $basedir_result->MoveNext();
    }

    if ($debug)
        $messageStack->add('Auto-Detected path to check: ' . PHP_OS . ', $windows_mysql_path=' . $windows_mysql_path, 'success');

} else { //Unix
    if (!$shell_exec_disabled) {//steve: Unix "which" command finds the executable.
        $unix_mysql_path = str_replace('mysql', '', trim(shell_exec('which mysql')));
        if ($debug)
            $messageStack->add('Auto-Detected path to check: ' . PHP_OS . ', $unix_mysql_path=' . $unix_mysql_path, 'success');
    } else {
        $messageStack->add(ERROR_SHELL_EXEC_DISABLED, 'warning');
    }

}

//list of paths to search
$pathsearch = array_merge($possibles, array(
    str_replace('mysql', '', MYSQL_EXE) . '/',
    str_replace('mysql.exe', '', MYSQL_EXE) . '/',
    str_replace('mysql', '', MYSQL_EXE_LOCAL) . '/',
    str_replace('mysql.exe', '', MYSQL_EXE_LOCAL) . '/',
    $unix_mysql_path,
    $windows_mysql_path,
    '/usr/bin/',
    '/usr/local/bin/',
    '/usr/local/mysql/bin/',
    'c:/mysql/bin/',
    'd:/mysql/bin/',
    'e:/mysql/bin/',
    'c:/server/mysql/bin/',
    '\'c:/Program Files/MySQL/MySQL Server 5.0/bin/\'',
    '\'d:\\Program Files\\MySQL\\MySQL Server 5.0\\bin\\\''
));

$pathsearch = array_merge($pathsearch, explode(':', $open_basedir));
$mysql_exe = 'unknown'; //used to store the complete path and executable name
$mysqldump_exe = 'unknown'; //used to store the complete path and executable name

foreach ($pathsearch as $path) {

    // $path = str_replace('\\','/',$path); // convert backslashes
    $path = str_replace('//', '/', $path); // convert double slashes to singles
    $path = str_replace("'", "", $path); // remove ' marks if any
    $path = (substr($path, -1) != '/' && substr($path, -1) != '\\') ? $path . '/' : $path; // add a '/' to the end if missing
    if ($mysql_exe == 'unknown') {
        if (@file_exists($path . 'mysql'))
            $mysql_exe = $path . 'mysql';
        if (@file_exists($path . 'mysql.exe'))
            $mysql_exe = $path . 'mysql.exe';
    }

    if ($mysqldump_exe == 'unknown') {
        if (@file_exists($path . 'mysqldump'))
            $mysqldump_exe = $path . 'mysqldump';
        if (@file_exists($path . 'mysqldump.exe'))
            $mysqldump_exe = $path . 'mysqldump.exe';
    }

    //if ($debug)
    //$messageStack->add(TEXT_CHECK_PATH . $path . '<br />', 'caution');
    if ($mysql_exe != 'unknown' && $mysqldump_exe != 'unknown') {
        $message = TEXT_EXECUTABLES_FOUND;
        $message_type = 'success';
        break; //exit when executables are found
    } else {
        $message = TEXT_EXECUTABLES_NOT_FOUND;
        $message_type = 'error';
    }
}

//$mysql_exe = '"' . $mysql_exe . '"';
//$mysqldump_exe = '"' . $mysqldump_exe . '"';

if ($message == TEXT_EXECUTABLES_NOT_FOUND)
    $messageStack->add($message, $message_type);

if (($shell_exec_disabled || $debug) && $message == TEXT_EXECUTABLES_FOUND) {
    $messageStack->add($message, $message_type);
    $messageStack->add('mysqlexe=' . $mysql_exe . '<br />', $message_type);
    $messageStack->add('mysqldumpexe=' . $mysqldump_exe . '<br />', $message_type);
}

// check if the backup directory exists
$dir_ok = false;
if (is_dir(DIR_FS_BACKUP)) {
    if (is_writable(DIR_FS_BACKUP)) {
        $dir_ok = true;
    } else {
        $messageStack->add(ERROR_BACKUP_DIRECTORY_NOT_WRITEABLE . ': ' . DIR_FS_BACKUP, 'error');
    }
} else {
    $messageStack->add(ERROR_BACKUP_DIRECTORY_DOES_NOT_EXIST . ': ' . DIR_FS_BACKUP, 'error');
}
/*steve debug
$messageStack->add(
'<br />CONFIGURE_REALPATH='.CONFIGURE_REALPATH.
'<br />DIR_FS_ADMIN='.DIR_FS_ADMIN.
'<br />DIR_FS_BACKUP='.DIR_FS_BACKUP.
'<br />realpath (here)='.realpath(dirname(__FILE__) . '/../')
, 'error');*/

// -------------------------------------------

$action = (isset($_GET['action']) ? $_GET['action'] : '');

if (zen_not_null($action)) {
    switch ($action) {

        case 'forget':
            $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'DB_LAST_RESTORE'");
            $messageStack->add(SUCCESS_LAST_RESTORE_CLEARED, 'success');
            zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL));
            break;

        case 'backupnow':
            unset($output);
            zen_set_time_limit(250);  // not sure if this is needed anymore?
            if (isset($_POST['suffix']) && $_POST['suffix'] != '') {
                $suffix = '_' . zen_output_string_protected($_POST['suffix']); //sanitise input
                $suffix = preg_replace('/\s+/', "_", $suffix, -1); //swap whitespace for an underscore
                $suffix = preg_replace("/[^-a-zA-Z0-9_]+/", "", $suffix); //strip to simple ascii only
            } else {
                $suffix = '';
            }

            $backup_file = 'db_' . DB_DATABASE . '-' . ($tables_to_export != '' ? 'limited-' : '') . date('Y-m-d_H-i-s') . $suffix . '.sql';
            $dump_params .= ' --host=' . DB_SERVER;
            $dump_params .= ' --user=' . DB_SERVER_USERNAME;
            //$dump_params .= ' --password="' . DB_SERVER_PASSWORD . '"';//WIN DEFINITELY needs double quotes around the filename when shell metacharacters *%&$& etc. are in the password
            $dump_params .= ' --password=' . OS_DELIM . DB_SERVER_PASSWORD . OS_DELIM;//NIX DEFINITELY needs single quotes around the filename when shell metacharacters *%&$& etc. are in the password
            $dump_params .= ' --opt';   //"optimized" -- turns on all "fast" and optimized export methods
            $dump_params .= ' --complete-insert';  // undo optimization slightly and do "complete inserts"--lists all column names for benefit of restore of diff systems
            if ($skip_locks_requested) {
                $dump_params .= ' --skip-lock-tables --skip-add-locks';     //use this if your host prevents you from locking tables for backup
            }
//        $dump_params .= ' --skip-comments'; // mysqldump inserts '--' as comment delimiters, which is invalid on import (only for mysql v4.01+)
//        $dump_params .= ' --skip-quote-names';
//        $dump_params .= ' --force';  // ignore SQL errors if they occur
//        $dump_params .= ' --compatible=postgresql'; // other options are: ,mysql323, mysql40
            $dump_params .= ' --result-file=' . OS_DELIM . DIR_FS_BACKUP . $backup_file . OS_DELIM;//WIN DEFINITELY needs double quote around the filename
            //$dump_params .= ' --databases ' . DB_DATABASE;//this option will restore only to the same-named database
            $dump_params .= ' ' . DB_DATABASE;

            // if using the "--tables" parameter, this should be the last parameter, and tables should be space-delimited
            // fill $tables_to_export with list of tables, separated by spaces, if wanna just export certain tables
            $dump_params .= (($tables_to_export == '') ? '' : ' --tables ' . $tables_to_export);
            $dump_params .= " 2>&1";// ensures console output is sent to the $output array

            $toolfilename = (isset($_GET['tool']) && $_GET['tool'] != '') ? $_GET['tool'] : $mysqldump_exe;

            // remove " marks in parameters for friendlier IIS support
//REQUIRES TESTING:        if (strstr($toolfilename,'.exe')) $dump_params = str_replace('"','',$dump_params);

            if ($debug) $messageStack->add(TEXT_COMMAND . $toolfilename . ' ' . $dump_params, 'caution');

            //- In PHP/5.2 and older you have to surround the full command plus arguments in double quotes
            //- In PHP/5.3 and greater you don't have to (if you do, your script will break)

            //this is the actual mysqldump. Steve removed @:why hide errors?
            $resultcodes = exec($toolfilename . $dump_params, $output, $dump_results);//$dump_results is number returned by operating system: anything other than 0 is a fail
            //Windows System Error Codes https://msdn.microsoft.com/en-us/library/windows/desktop/ms681381(v=vs.85).aspx
            //UNIX Exit codes http://www.faqs.org/docs/abs/HTML/exitcodes.html

            exec("exit(0)");//terminates the current script successfully

            //Exit code = -1? Cannot find any reference to -1
            if ($dump_results == -1) $messageStack->add(FAILURE_BACKUP_FAILED_CHECK_PERMISSIONS . TEXT_COMMAND_RUN . $toolfilename . str_replace('--password=' . DB_SERVER_PASSWORD, '--password=*****', str_replace('2>&1', '', $dump_params)), 'error');//hide password

            if ((zen_not_null($dump_results) && $dump_results != '0')) $messageStack->add(TEXT_RESULT_CODE . ' ($dump_results) ' . $dump_results, 'error');

            // parse the value that comes back from the script

            if (zen_not_null($resultcodes)) {
                list($strA, $strB) = preg_split('/[|]/', $resultcodes);
                //$array = print_r($resultcodes, true);if ($debug) $messageStack->add('$resultcodes: ' . $array, 'error');
                if ($debug) $messageStack->add('$resultcodes valueA: ' . $strA, 'error');
                if ($debug) $messageStack->add('$resultcodes valueB: ' . $strB, 'error');
            }

            // $output contains response strings from execution. This displays if anything is generated.
            //$output= array(1,2,3);//to test, as nothing ever seems to come out of $output!
            if (zen_not_null($output)) {
                foreach ($output as $key => $value) {
                    $messageStack->add('console $output:' . "$key => $value<br />", 'error');
                }
            }

            if (file_exists(DIR_FS_BACKUP . $backup_file) && ($dump_results == '0' || $dump_results == '')) { // display success message noting that MYSQLDUMP was used
                $messageStack->add('<a href="' . ((ENABLE_SSL_ADMIN == 'true') ? DIR_WS_HTTPS_ADMIN : DIR_WS_ADMIN) . 'backups/' . $backup_file . '">' . SUCCESS_DATABASE_SAVED . '</a>', 'success');

            } elseif ($dump_results == '127') {//127 = command not found
                $messageStack->add(FAILURE_DATABASE_NOT_SAVED_UTIL_NOT_FOUND, 'error');

            } elseif (stristr($strA, 'Access denied') && stristr($strA, 'LOCK TABLES')) {
                unlink(DIR_FS_BACKUP . $backup_file);
                zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL, 'action=backupnow' . ($debug ? '&debug=1' : '') . (($_POST['compress'] != false) ? '&compress=' . $_POST['compress'] : '') . (($tables_to_export != '') ? '&tables=' . str_replace(' ', ',', $tables_to_export) : '') . '&skiplocks=yes'));

            } else {
                $messageStack->add(FAILURE_DATABASE_NOT_SAVED, 'error');
            }

            //compress the file as requested & optionally download
            if (isset($_POST['download']) && ($_POST['download'] == 'yes') && file_exists(DIR_FS_BACKUP . $backup_file)) {
                switch ($_POST['compress']) {
                    case 'gzip':
                        @exec(LOCAL_EXE_GZIP . ' ' . DIR_FS_BACKUP . $backup_file);
                        $backup_file .= '.gz';
                        break;
                    case 'zip':
                        @exec(LOCAL_EXE_ZIP . ' -j ' . DIR_FS_BACKUP . $backup_file . '.zip ' . DIR_FS_BACKUP . $backup_file);
                        if (file_exists(DIR_FS_BACKUP . $backup_file) && file_exists(DIR_FS_BACKUP . $backup_file . 'zip'))
                            unlink(DIR_FS_BACKUP . $backup_file);
                        $backup_file .= '.zip';
                }

                if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT'])) {
                    header('Content-Type: application/octetstream');
//            header('Content-Disposition: inline; filename="' . $backup_file . '"');
                    header('Content-Disposition: attachment; filename=' . $backup_file);
                    header("Expires: Mon, 26 Jul 2001 05:00:00 GMT");
                    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
                    header("Cache-Control: must_revalidate, post-check=0, pre-check=0");
                    header("Pragma: public");
                    header("Cache-control: private");
                } else {
                    header('Content-Type: application/x-octet-stream');
                    header('Content-Disposition: attachment; filename=' . $backup_file);
                    header("Expires: Mon, 26 Jul 2001 05:00:00 GMT");
                    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
                    header("Pragma: no-cache");
                }

                readfile(DIR_FS_BACKUP . $backup_file);
                unlink(DIR_FS_BACKUP . $backup_file);

                exit;
            } else {
                switch ($_POST['compress'] && file_exists(DIR_FS_BACKUP . $backup_file)) {

                    case 'gzip':
                        @exec(LOCAL_EXE_GZIP . ' ' . DIR_FS_BACKUP . $backup_file);
                        if (file_exists(DIR_FS_BACKUP . $backup_file))
                            @exec('gzip ' . DIR_FS_BACKUP . $backup_file);
                        break;

                    case 'zip':
                        @exec(LOCAL_EXE_ZIP . ' -j ' . DIR_FS_BACKUP . $backup_file . '.zip ' . DIR_FS_BACKUP . $backup_file);
                        if (file_exists(DIR_FS_BACKUP . $backup_file) && file_exists(DIR_FS_BACKUP . $backup_file . 'zip'))
                            unlink(DIR_FS_BACKUP . $backup_file);
                }
            }
            zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL));
            break;

        case 'restorenow':

        case 'restorelocalnow'://restore a file using the Browse dialog/not in the /backups directory
            unset($output);
            zen_set_time_limit(300);
            $specified_restore_file = (isset($_GET['file'])) ? $_GET['file'] : '';

            if ($specified_restore_file != '' && file_exists(DIR_FS_BACKUP . $specified_restore_file)) {
                $restore_file = DIR_FS_BACKUP . $specified_restore_file;
                //TODO better way to get extension to further flag warning if missing .sql. for compressed files.
                $extension = substr($specified_restore_file, -3);

                // determine file format and unzip if needed. Note that *.sql.gz and *.sql.zip are first extracted to *.sql and will overwrite any pre-existing *.sql with the same name.
                if (($extension == 'sql') || ($extension == '.gz') || ($extension == 'zip')) {
                    switch ($extension) {

                        case 'sql':
                            $restore_from = $restore_file;
                            $remove_raw = false;
                            break;

                        case '.gz':
                            //if ($debug) $messageStack->add('filetype=.gz', 'success');
                            $restore_from = substr($restore_file, 0, -3);//filename.sql
                            if ($os == 'nix') exec(LOCAL_EXE_GUNZIP . ' ' . $restore_file . ' -c > ' . $restore_from);
                            if ($os == 'win') {
                                //if ($debug) $messageStack->add('os='.$os, 'success');
                                $sfp = gzopen($restore_file, "rb");
                                $fp = fopen($restore_from, "w");
                                while (!gzeof($sfp)) {
                                    $string = gzread($sfp, 4096);
                                    fwrite($fp, $string, strlen($string));
                                }
                                gzclose($sfp);
                                fclose($fp);
                            }
                            $remove_raw = true;
                            break;

                        case 'zip'://remember it has to be .sql.zip NOT just .zip
                            $restore_from = substr($restore_file, 0, -4);
                            //echo '$restore_file='.$restore_file.'<br />$restore_from='.$restore_from;die;
                            if ($os == 'nix') exec(LOCAL_EXE_UNZIP . ' ' . $restore_file . ' -d ' . DIR_FS_BACKUP);
                            if ($os == 'win') {
                                $path = pathinfo(realpath($restore_file), PATHINFO_DIRNAME);
                                $zip = new ZipArchive;
                                $res = $zip->open($restore_file);
                                if ($res === TRUE) {
                                    // extract it to the path we determined above
                                    $zip->extractTo($path);
                                    $zip->close();
                                }
                            }
                            $remove_raw = true;
                    }
                }
            } elseif ($action == 'restorelocalnow') {
                $sql_file = new upload('sql_file', DIR_FS_BACKUP);
                $specified_restore_file = $sql_file->filename;
                $restore_from = DIR_FS_BACKUP . $specified_restore_file;
            }

            //Restore using "mysql"
            //$load_params = ' "--database=' . DB_DATABASE . '"';
            $load_params = ' --database=' . DB_DATABASE;

            $load_params .= ' --host=' . DB_SERVER;
            $load_params .= ' --user=' . DB_SERVER_USERNAME;
            $load_params .= ((DB_SERVER_PASSWORD == '') ? '' : ' --password=' . OS_DELIM . DB_SERVER_PASSWORD . OS_DELIM);
            $load_params .= ' ' . DB_DATABASE; // this needs to be the 2nd-last parameter
            $load_params .= ' < ' . OS_DELIM . $restore_from . OS_DELIM; // this needs to be the LAST parameter
            $load_params .= " 2>&1";
            //DEBUG echo $mysql_exe . ' ' . $load_params;

            if (file_exists($restore_from) && $specified_restore_file != '') {
                $toolfilename = (isset($_GET['tool']) && $_GET['tool'] != '') ? $_GET['tool'] : $mysql_exe;

                // remove " marks in parameters for friendlier IIS support
//REQUIRES TESTING:          if (strstr($toolfilename,'.exe')) $load_params = str_replace('"','',$load_params);

                if ($debug)
                    $messageStack->add(TEXT_COMMAND . $toolfilename . ' ' . $load_params, 'caution');
                $resultcodes = exec($toolfilename . $load_params, $output, $load_results);//$output gets filled with an array oall the normally displayed dialogue that comes back from the command, $load_results
                exec("exit(0)");

                // parse the value that comes back from the script

                if (zen_not_null($resultcodes)) {
                    list($strA, $strB) = preg_split('/[|]/', $resultcodes);
                    $messageStack->add("valueA: " . $strA, 'error');
                    $messageStack->add("valueB: " . $strB, 'error');
                }

                if (zen_not_null($load_results) && $load_results != '0') {
                    $messageStack->add(TEXT_RESULT_CODE . $load_results, 'caution');
                }

                // $output contains response strings from execution. This displays if anything is generated.
                //$output= array(1,2,3);//to test, as nothing ever seems to come out of $output!
                if (zen_not_null($output)) {
                    foreach ($output as $key => $value) {
                        $messageStack->add('console $output:' . "$key => $value<br />", 'error');
                    }
                }

                if ($load_results == '0') {
                    // store the last-restore-date, if successful. Update key if exists rather than delete and insert.
                    $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
                    (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
                     ('Last Database Restore', 'DB_LAST_RESTORE', '" . $specified_restore_file . "', 'Last database restore file', '6', '0', now(), now(), NULL, NULL)
                     ON DUPLICATE KEY UPDATE
                     configuration_value    = '" . $specified_restore_file . "',
                     last_modified          = now()");

                    $messageStack->add('<a href="' . ((ENABLE_SSL_ADMIN == 'true') ? DIR_WS_HTTPS_ADMIN : DIR_WS_ADMIN) . 'backups/' . $specified_restore_file . '">' . SUCCESS_DATABASE_RESTORED . '</a>', 'success');
                    if ($remove_raw == true) {//if .sql came from a compressed (.zip, .gz) file, delete it now
                        $delete_sql = unlink($restore_from);
                        $messageStack->add($delete_sql ? TEXT_TEMP_SQL_DELETED : TEXT_TEMP_SQL_NOT_DELETED, $delete_sql ? 'success' : 'error');
                    }
                } elseif ($load_results == '127') {//127 = command not found
                    $messageStack->add(FAILURE_DATABASE_NOT_RESTORED_UTIL_NOT_FOUND, 'error');
                } else {
                    $messageStack->add(FAILURE_DATABASE_NOT_RESTORED, 'error');
                } // endif $load_results
            } else {
                $messageStack->add(sprintf(FAILURE_DATABASE_NOT_RESTORED_FILE_NOT_FOUND, '[' . $restore_from . ']'), 'error');
            } // endif file_exists

            zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL));
            break;

        case 'download':
            $extension = substr($_GET['file'], -3);

            if (($extension == 'zip') || ($extension == '.gz') || ($extension == 'sql')) {
                if ($fp = fopen(DIR_FS_BACKUP . $_GET['file'], 'rb')) {
                    $buffer = fread($fp, filesize(DIR_FS_BACKUP . $_GET['file']));
                    fclose($fp);

                    header('Content-type: application/x-octet-stream');
                    header('Content-disposition: attachment; filename=' . $_GET['file']);

                    echo $buffer;

                    exit;
                }
            } else {
                $messageStack->add(ERROR_DOWNLOAD_LINK_NOT_ACCEPTABLE, 'error');
            }
            break;

        case 'deleteconfirm':
            if (strstr($_GET['file'], '..')) zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL));

            zen_remove(DIR_FS_BACKUP . '/' . $_GET['file']);//deletes the file
            // backwards compatibility:
            if (isset($zen_remove_error) && $zen_remove_error == true) $zremove_error = $zen_remove_error;

            if (!$zremove_error) {
                $messageStack->add(SUCCESS_BACKUP_DELETED, 'success');

                zen_redirect(zen_href_link(FILENAME_BACKUP_MYSQL));
            }
            break;
    }
}

?>
    <!DOCTYPE html>
    <html <?php echo HTML_PARAMS; ?>>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>"/>
        <title><?php echo TITLE . ' - Admin - ' . HEADING_TITLE; ?></title>
        <link rel="stylesheet" type="text/css" href="includes/stylesheet.css"/>
        <link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS"/>
        <script src="includes/menu.js"></script>
        <script src="includes/general.js"></script>
        <script>
            <!--
            function init() {
                cssjsmenu('navbar');
                if (document.getElementById) {
                    var kill = document.getElementById('hoverJS');
                    kill.disabled = true;
                }
            }
            // -->
        </script>
    </head>
    <body onload="init()">
    <!-- header //-->
    <?php require(DIR_WS_INCLUDES . 'header.php'); ?>
    <!-- header_eof //-->

    <!-- body //-->
    <table style="width:100%">
        <tr>
            <!-- body_text //-->
            <td>
                <table style="width:100%">
                    <tr>
                        <td class="pageHeading"><?php
                            echo HEADING_TITLE;
                            ?></td>
                        <td class="smallText right">
                            <a href="http://www.zen-cart.com/showthread.php?35714-Backup-MySQL-Database"
                               target="_blank"><?php
                                echo TEXT_ZC_SUPPORT;
                                ?></a><br/><a
                                    href="http://www.zen-cart.com/downloads.php?do=file&amp;id=7"
                                    target="_blank"><?php
                                echo TEXT_ZC_PLUGIN_DOWNLOAD;
                                ?></a></td>
                    </tr>
                    <?php
                    if (substr(HTTP_SERVER, 0, 5) != 'https') { // display security warning about downloads if not SSL
                        ?>
                        <tr>
                            <td class="main"><?php
                                echo WARNING_NOT_SECURE_FOR_DOWNLOADS;
                                ?></td>
                            <td class="main right"><?php
                                echo zen_draw_separator('pixel_trans.gif', HEADING_IMAGE_WIDTH, HEADING_IMAGE_HEIGHT);
                                ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <table style="width: 100%">
                    <tr style="vertical-align: top">
                        <td>
                            <?php if ($dir_ok == true) { //otherwise generates debug logs ?>
                                <table style="width:100%">
                                    <tr class="dataTableHeadingRow">
                                        <td class="dataTableHeadingContent"><?php
                                            echo TABLE_HEADING_TITLE;
                                            ?></td>
                                        <td class="dataTableHeadingContent left"><?php
                                            echo TABLE_HEADING_FILE_DATE;
                                            ?></td>
                                        <td class="dataTableHeadingContent left"><?php
                                            echo TABLE_HEADING_FILE_SIZE;
                                            ?></td>
                                        <td class="dataTableHeadingContent center"><?php
                                            echo TABLE_HEADING_ACTION;
                                            ?>&nbsp;</td>
                                    </tr>
                                    <?php

                                    //  if (!get_cfg_var('safe_mode') && $dir_ok == true) {

                                    $dir = dir(DIR_FS_BACKUP);
                                    $contents = array();
                                    while ($file = $dir->read()) {
                                        if (!is_dir(DIR_FS_BACKUP . $file)) {
                                            if (substr($file, 0, 1) != '.' && !in_array($file, array('empty.txt', 'index.php', 'index.htm', 'index.html'))) {
                                                $contents[] = $file;
                                            }
                                        }
                                    }
                                    sort($contents);
                                    for ($i = 0, $n = sizeof($contents); $i < $n; $i++) {
                                        $entry = $contents[$i];
                                        $check = 0;

                                        if ((!isset($_GET['file']) || (isset($_GET['file']) && ($_GET['file'] == $entry))) && !isset($buInfo) && ($action != 'backup') && ($action != 'restorelocal')) {
                                            $file_array['file'] = $entry;
                                            $file_array['date'] = date(PHP_DATE_TIME_FORMAT, filemtime(DIR_FS_BACKUP . $entry));
                                            $file_array['size'] = number_format(filesize(DIR_FS_BACKUP . $entry)) . ' bytes';
                                            switch (substr($entry, -3)) {
                                                case 'zip':
                                                    $file_array['compression'] = 'ZIP';
                                                    break;
                                                case '.gz':
                                                    $file_array['compression'] = 'GZIP';
                                                    break;
                                                default:
                                                    $file_array['compression'] = TEXT_NO_EXTENSION;
                                                    break;
                                            }

                                            $buInfo = new objectInfo($file_array);
                                        }

                                        if (isset($buInfo) && is_object($buInfo) && ($entry == $buInfo->file)) {
                                            echo '              <tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)">' . "\n";
                                            $onclick_link = 'file=' . $buInfo->file . '&amp;action=restore';
                                        } else {
                                            echo '              <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)">' . "\n";
                                            $onclick_link = 'file=' . $entry;
                                        }

                                        ?>
                                        <!--                 <td class="dataTableContent" onclick="document.location.href='<?php //echo 'here'.zen_href_link(FILENAME_BACKUP_MYSQL, $onclick_link);
                                        ?>'"><?php //echo '<a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'action=download&amp;file=' . $entry) . '">' . zen_image(DIR_WS_ICONS . 'file_download.gif', ICON_FILE_DOWNLOAD) . '</a>&nbsp;' . $entry;
                                        ?></td> -->

                                        <td class="dataTableContent" onClick="document.location.href='<?php
                                        echo zen_href_link(FILENAME_BACKUP_MYSQL, $onclick_link);
                                        ?>'"><?php
                                            echo '<a href="' . ((ENABLE_SSL_ADMIN == 'true') ? DIR_WS_HTTPS_ADMIN : DIR_WS_ADMIN) . 'backups/' . $entry . '">' . zen_image(DIR_WS_ICONS . 'file_download.gif', ICON_FILE_DOWNLOAD) . '</a>&nbsp;' . $entry;
                                            ?></td>
                                        <td class="dataTableContent left"
                                            onClick="document.location.href='<?php
                                            echo zen_href_link(FILENAME_BACKUP_MYSQL, $onclick_link);
                                            ?>'"><?php
                                            echo date(PHP_DATE_TIME_FORMAT, filemtime(DIR_FS_BACKUP . $entry));
                                            ?></td>
                                        <td class="dataTableContent left"
                                            onClick="document.location.href='<?php
                                            echo zen_href_link(FILENAME_BACKUP_MYSQL, $onclick_link);
                                            ?>'"><?php
                                            echo number_format(filesize(DIR_FS_BACKUP . $entry));
                                            ?> bytes
                                        </td>
                                        <td class="dataTableContent center"><?php
                                            if (isset($buInfo) && is_object($buInfo) && ($entry == $buInfo->file)) {
                                                echo zen_image(DIR_WS_IMAGES . 'icon_arrow_right.gif', '');
                                            } else {
                                                echo '<a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'file=' . $entry) . '">' . zen_image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO) . '</a>';
                                            }
                                            ?>
                                            &nbsp;</td>
                                        </tr>
                                        <?php
                                    }
                                    $dir->close();
                                    //  } // endif safe-mode & dir_ok

                                    // now let's display the backup/restore buttons below filelist
                                    ?>
                                    <tr>
                                        <td class="smallText" colspan="3"><?php
                                            echo TEXT_BACKUP_DIRECTORY . ' ' . DIR_FS_BACKUP;
                                            ?></td>
                                        <td class="smallText right"><?php

                                            if (($action != 'backup') && (isset($dir)) && !ini_get('safe_mode') && $dir_ok == true) {
                                                echo '<a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'action=backup' . (($debug) ? '&debug=1' : '')) . (($tables_to_export != '') ? '&tables=' . str_replace(' ', ',', $tables_to_export) : '') . '">' . zen_image_button('button_backup.gif', IMAGE_BACKUP) . '</a>&nbsp;&nbsp;';
                                            }

                                            if (($action != 'restorelocal') && isset($dir)) {
                                                echo '<a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'action=restorelocal' . (($debug) ? '&debug=1' : '')) . '">' . zen_image_button('button_restore.gif', IMAGE_RESTORE) . '</a>';
                                            }
                                            ?></td>
                                    </tr>
                                    <?php

                                    if (defined('DB_LAST_RESTORE')) {
                                        ?>
                                        <tr>
                                            <td class="smallText" colspan="4"><?php
                                                echo TEXT_LAST_RESTORATION . ' ' . DB_LAST_RESTORE . ' <a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'action=forget') . '">' . TEXT_FORGET . '</a>';
                                                ?></td>
                                        </tr>
                                        <?php
                                    } ?>

                                    <?php if ($debug) { ?>
                                        <tr>
                                            <td colspan="4" class="smallText">Comparison Info:<br/>
                                                <?php echo SESSION_WRITE_DIRECTORY ?>
                                                =SESSION_WRITE_DIRECTORY<br/>
                                                <?php echo DIR_FS_SQL_CACHE ?>
                                                =DIR_FS_SQL_CACHE
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <tr>
                                        <td colspan="4">
                                            <?php echo zen_draw_form('set_debug', FILENAME_BACKUP_MYSQL, zen_get_all_get_params(array('debug'), 'get')); ?>
                                            <label for="debug_checkbox">Debug</label>
                                            <?php echo zen_draw_checkbox_field('debug', 'on', $debug, '', 'id="debug_checkbox" onchange="this.form.submit();"'); ?>
                                            </form>
                                        </td>
                                    </tr>
                                </table>
                            <?php } ?>
                        </td>
                        <?php
                        $heading = array();
                        $contents = array();
                        switch ($action) {
                            case 'backup':
                                $heading[] = array(
                                    'text' => '<strong>' . TEXT_INFO_HEADING_NEW_BACKUP . '</strong>'
                                );
                                $contents = array(
                                    'form' => zen_draw_form('backup', FILENAME_BACKUP_MYSQL, 'action=backupnow' . ($debug ? '&debug=1' : '') . (($tables_to_export != '') ? '&tables=' . str_replace(' ', ',', $tables_to_export) : ''))
                                );
                                $contents[] = array(
                                    'text' => TEXT_INFO_NEW_BACKUP
                                );
                                $contents[] = array(
                                    'text' => '<br />' . zen_draw_radio_field('compress', 'no', (!@file_exists(LOCAL_EXE_GZIP) && !$compress_override ? true : false)) . ' ' . TEXT_INFO_USE_NO_COMPRESSION
                                );
                                if (@file_exists(LOCAL_EXE_GZIP) || $compress_override)
                                    $contents[] = array(
                                        'text' => '<br />' . zen_draw_radio_field('compress', 'gzip', true) . ' ' . TEXT_INFO_USE_GZIP
                                    );
                                if (@file_exists(LOCAL_EXE_ZIP))
                                    $contents[] = array(
                                        'text' => zen_draw_radio_field('compress', 'zip', (!@file_exists(LOCAL_EXE_GZIP) ? true : false)) . ' ' . TEXT_INFO_USE_ZIP
                                    );
                                $contents[] = array(
                                    'text' => '<br />' . zen_draw_radio_field('skiplocks', 'yes', false) . ' ' . TEXT_INFO_SKIP_LOCKS
                                );

                                // Download to file --- Should only be done if SSL is active, otherwise database is exposed as clear text

                                if ($dir_ok == true) {
                                    $contents[] = array(
                                        'text' => '<br />' . zen_draw_checkbox_field('download', 'yes') . ' ' . TEXT_INFO_DOWNLOAD_ONLY . '*<br /><span class="errorText">*' . TEXT_INFO_BEST_THROUGH_HTTPS . '</span>'
                                    );
                                } else {
                                    $contents[] = array(
                                        'text' => '<br />' . zen_draw_radio_field('download', 'yes', true) . ' ' . TEXT_INFO_DOWNLOAD_ONLY . '*<br /><span class="errorText">*' . TEXT_INFO_BEST_THROUGH_HTTPS . '</span>'
                                    );
                                }

                                // bof add suffix to filename

                                $contents[] = array(
                                    'text' => '<br />' . TEXT_ADD_SUFFIX . '<br />' . zen_draw_input_field('suffix', '', 'size="31" maxlength="30"')
                                );

                                // eof add suffix to filename
                                // display backup button

                                $contents[] = array(
                                    'align' => 'center',
                                    'text' => '<br />' . zen_image_submit('button_backup.gif', IMAGE_BACKUP) . '&nbsp;<a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, (($debug == 'ON') ? 'debug=ON' : '')) . (($tables_to_export != '') ? '&tables=' . str_replace(' ', ',', $tables_to_export) : '') . '">' . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>'
                                );
                                break;

                            case 'restore':
                                $heading[] = array(
                                    'text' => '<strong>' . $buInfo->date . '</strong>'
                                );
                                $contents[] = array(
                                    'text' => zen_break_string(sprintf(TEXT_INFO_RESTORE, DIR_FS_BACKUP . (($buInfo->compression != TEXT_NO_EXTENSION) ? substr($buInfo->file, 0, strrpos($buInfo->file, '.')) : $buInfo->file), ($buInfo->compression != TEXT_NO_EXTENSION) ? TEXT_INFO_UNPACK : ''), 35, ' ')
                                );
                                $contents[] = array(
                                    'align' => 'center',
                                    'text' => '<br /><a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'file=' . $buInfo->file . '&amp;action=restorenow' . ($debug ? '&debug=1' : '')) . '">' . zen_image_button('button_restore.gif', IMAGE_RESTORE) . '</a>&nbsp;<a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'file=' . $buInfo->file . ($debug ? '&debug=1' : '')) . '">' . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>'
                                );
                                break;

                            case 'restorelocal':
                                $heading[] = array(
                                    'text' => '<strong>' . TEXT_INFO_HEADING_RESTORE_LOCAL . '</strong>'
                                );
                                $contents = array(
                                    'form' => zen_draw_form('restore', FILENAME_BACKUP_MYSQL, 'action=restorelocalnow' . ($debug ? '&debug=1' : ''), 'post', 'enctype="multipart/form-data"')
                                );
                                $contents[] = array(
                                    'text' => TEXT_INFO_RESTORE_LOCAL . '<br /><br />' . TEXT_INFO_BEST_THROUGH_HTTPS
                                );
                                $contents[] = array(
                                    'text' => '<br />' . zen_draw_file_field('sql_file')//browser decides button language (cannot be forced by external means)
                                );
                                $contents[] = array(
                                    'text' => TEXT_INFO_RESTORE_LOCAL_RAW_FILE
                                );
                                $contents[] = array(
                                    'align' => 'center',
                                    'text' => '<br />' . zen_image_submit('button_restore.gif', IMAGE_RESTORE) . '&nbsp;<a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, ($debug ? '&debug=1' : '')) . '">' . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>'
                                );
                                break;

                            case 'delete':
                                if ($dir_ok == false) break;//directory is not writeable
                                $heading[] = array(
                                    'text' => '<strong>' . $buInfo->date . '</strong>'
                                );
                                $contents = array(
                                    'form' => zen_draw_form('delete', FILENAME_BACKUP_MYSQL, 'file=' . $buInfo->file . '&amp;action=deleteconfirm')
                                );
                                $contents[] = array(
                                    'text' => TEXT_DELETE_INTRO
                                );
                                $contents[] = array(
                                    'text' => '<br /><strong>' . $buInfo->file . '</strong>'
                                );
                                $contents[] = array(
                                    'align' => 'center',
                                    'text' => '<br />' . zen_image_submit('button_delete.gif', IMAGE_DELETE) . ' <a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'file=' . $buInfo->file) . '">' . zen_image_button('button_cancel.gif', IMAGE_CANCEL) . '</a>'
                                );
                                break;

                            default:
                                if (isset($buInfo) && is_object($buInfo)) {
                                    $heading[] = array(
                                        'text' => '<strong>' . $buInfo->date . '</strong>'
                                    );
                                    $contents[] = array(
                                        'align' => 'center',
                                        'text' => '<a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'file=' . $buInfo->file . '&amp;action=restore' . ($debug ? '&debug=1' : '')) . '">' . zen_image_button('button_restore.gif', IMAGE_RESTORE) . '</a> ' . (($dir_ok == true && $exec_disabled == false) ? '<a href="' . zen_href_link(FILENAME_BACKUP_MYSQL, 'file=' . $buInfo->file . '&amp;action=delete') . '">' . zen_image_button('button_delete.gif', IMAGE_DELETE) . '</a>' : '')
                                    );
                                    $contents[] = array(
                                        'text' => '<br />' . TEXT_INFO_DATE . ' ' . $buInfo->date
                                    );
                                    $contents[] = array(
                                        'text' => TEXT_INFO_SIZE . ' ' . $buInfo->size
                                    );
                                    $contents[] = array(
                                        'text' => '<br />' . TEXT_INFO_COMPRESSION . ' ' . $buInfo->compression
                                    );
                                }
                                break;
                        }

                        if ((zen_not_null($heading)) && (zen_not_null($contents))) {
                            echo '            <td style="width:25%">' . "\n";

                            $box = new box;
                            echo $box->infoBox($heading, $contents);

                            echo '            </td>' . "\n";
                        }
                        ?>
                    </tr>
                </table>

            </td>
            <!-- body_text_eof //-->
        </tr>
    </table>
    <!-- body_eof //-->

    <!-- footer //-->
    <?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
    <!-- footer_eof //-->
    <br/>
    </body>
    </html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>