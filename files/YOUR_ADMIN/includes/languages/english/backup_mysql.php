<?php
// 
// +----------------------------------------------------------------------+
// |zen-cart Open Source E-commerce                                       |
// +----------------------------------------------------------------------+
// | Copyright (c) 2003-2010 The zen-cart developers                      |
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
// $Id: backup_mysql.php
//

// the following are the english language definitions
define('HEADING_TITLE', 'MySQL Database Backup/Restore');
define('ERROR_BACKUP_DIRECTORY_DOES_NOT_EXIST', 'Error: Backup directory<br />"'.DIR_FS_BACKUP.'"<br />does not exist (slash orientation is not significant).<br />Please check configure.php (or /local/configure.php if used).');
define('ERROR_BACKUP_DIRECTORY_NOT_WRITEABLE', 'Error: Backup directory is not writeable.');
define('ERROR_CANT_BACKUP_IN_SAFE_MODE','ERROR: This backup script seldom works when safe_mode is enabled or open_basedir restrictions are in effect.<br />If you get no errors doing a backup, check to see whether the file is less than 200kb. If so, then the backup is likely unreliable.');
define('ERROR_DOWNLOAD_LINK_NOT_ACCEPTABLE', 'Error: Download link not acceptable.');
define('ERROR_EXEC_DISABLED','ERROR: Your server\'s "exec()" command has been disabled. This script cannot run. Ask your host if they are willing to re-enable PHP exec().');
define('ERROR_FILE_NOT_REMOVEABLE', 'Error: Could not remove the file specified. You may have to use FTP to remove the file, due to a server-permissions configuration limitation.');
define('ERROR_NOT_FOUND', 'not found');
define('ERROR_PHP_DISABLED_FUNCTIONS', 'PHP-Disabled Functions: ');
define('FAILURE_BACKUP_FAILED_CHECK_PERMISSIONS','The backup failed because there was an error starting the backup program (mysqldump or mysqldump.exe).<br />If running on Windows 2003 server, you may need to alter permissions on cmd.exe to allow Special Access to the Internet Guest Account to read/execute.<br />You should talk to your webhost about why exec() commands are failing when attempting to run the mysqldump binary/program.');
define('FAILURE_DATABASE_NOT_RESTORED', 'Failure: The database may NOT have been restored properly. Please check it carefully.');
define('FAILURE_DATABASE_NOT_RESTORED_FILE_NOT_FOUND', 'Failure: The database was NOT restored.  ERROR: FILE NOT FOUND: %s. Note that compressed files must be named *.sql.gz or *.sql.zip.');
define('FAILURE_DATABASE_NOT_RESTORED_UTIL_NOT_FOUND', 'ERROR: Could not locate the MYSQL restore utility. RESTORE FAILED.');
define('FAILURE_DATABASE_NOT_SAVED', 'Failure: The database has NOT been saved.');
define('FAILURE_DATABASE_NOT_SAVED_UTIL_NOT_FOUND', 'ERROR: Could not locate the MYSQLDUMP backup utility. BACKUP FAILED.');
define('SUCCESS_BACKUP_DELETED', 'Success: The backup has been removed.');
define('SUCCESS_DATABASE_RESTORED', 'Success: The database has been restored.');
define('SUCCESS_DATABASE_SAVED', 'Success: The database has been saved.');
define('SUCCESS_LAST_RESTORE_CLEARED', 'Success: The last restoration date has been cleared.');
define('TABLE_HEADING_ACTION', 'Action');
define('TABLE_HEADING_FILE_DATE', 'Date');
define('TABLE_HEADING_FILE_SIZE', 'Size');
define('TABLE_HEADING_TITLE', 'Title');
define('TEXT_ADD_SUFFIX', 'Here you can add an optional suffix to the filename (ascii characters only):');
define('TEXT_BACKUP_DIRECTORY', 'Backup Directory:');
define('TEXT_CHECK_PATH', 'Checking Path: ');
define('TEXT_COMMAND', 'Command: ');
define('TEXT_COMMAND_RUN', '<br />The command being run is: ');
define('TEXT_DEBUG_ON', 'Backup MySQL <strong>Debug ON</strong>');
define('TEXT_DELETE_INTRO', 'Are you sure you want to delete this backup?');
define('TEXT_EXECUTABLES_FOUND', 'MySQL tools found:');
define('TEXT_EXECUTABLES_NOT_FOUND', 'MySQL tools (mysql, mysqldump) not found.');
define('TEXT_FIX_CACHE_KEY', 'Run fix_cache_key.php');
define('TEXT_FORGET', '(forget)');
define('TEXT_INFO_BEST_THROUGH_HTTPS', '(Safer via a secured HTTPS connection)');
define('TEXT_INFO_COMPRESSION', 'Compression:');
define('TEXT_INFO_DATE', 'Date:');
define('TEXT_INFO_DOWNLOAD_ONLY', 'Download without storing on server');
define('TEXT_INFO_HEADING_NEW_BACKUP', 'New Backup');
define('TEXT_INFO_HEADING_RESTORE_LOCAL', 'Restore Local');
define('TEXT_INFO_NEW_BACKUP', 'Do not interrupt the backup process which might take a couple of minutes.');
define('TEXT_INFO_RESTORE', 'Do not interrupt the restoration process.<br /><br />The larger the backup, the longer this process takes!<br /><br />If possible, use the mysql client.<br /><br />For example:<br /><br /><b>mysql -h' . DB_SERVER . ' -u' . DB_SERVER_USERNAME . ' -p ' . DB_DATABASE . ' < %s </b> %s');
define('TEXT_INFO_RESTORE_LOCAL', 'Do not interrupt the restoration process.<br /><br />The larger the backup, the longer this process takes!');
define('TEXT_INFO_RESTORE_LOCAL_RAW_FILE', 'The file uploaded must be a raw sql (text) file.');
define('TEXT_INFO_SIZE', 'Size:');
define('TEXT_INFO_SKIP_LOCKS', 'Skip Lock option (check this if you get a LOCK TABLES permissions error)');
define('TEXT_INFO_UNPACK', '<br /><br />(after unpacking the file from the archive)');
define('TEXT_INFO_USE_GZIP', 'Use GZIP');
define('TEXT_INFO_USE_NO_COMPRESSION', 'No Compression (Pure SQL)');
define('TEXT_INFO_USE_ZIP', 'Use ZIP');
define('TEXT_LAST_RESTORATION', 'Last Restoration:');
define('TEXT_NO_EXTENSION', 'None');
define('TEXT_RESULT_CODE', 'Result code: ');
define('TEXT_SELECTED_EXECUTABLES', 'Command Files Selected: ');
define('TEXT_ZC_PLUGIN_DOWNLOAD', 'Zen Cart website - Plugin download page');
define('TEXT_ZC_SUPPORT', 'Zen Cart Forum - Plugin support thread');
define('WARNING_NOT_SECURE_FOR_DOWNLOADS','<span class="errorText">NOTE: You do not have SSL enabled. Any downloads you do from this page will not be encrypted. Doing backups and restores will be fine, but download/upload of files from/to the server presents a security risk.</span>');
define('WARNING_MYSQL_NOT_FOUND','WARNING: "<strong>mysql</strong>" binary not found. <strong>Restores</strong> may not work.<br />Please set full path to MYSQL binary in extra_datafiles/backup_mysql.php');
define('WARNING_MYSQLDUMP_NOT_FOUND','WARNING: "<strong>mysqldump</strong>" binary not found. <strong>Backups</strong> may not work.<br />Please set full path to MYSQLDUMP binary in extra_datafiles/backup_mysql.php');
define('TEXT_TEMP_SQL_DELETED','Temporary .sql file deleted');
define('TEXT_TEMP_SQL_NOT_DELETED','Temporary .sql file NOT deleted');
