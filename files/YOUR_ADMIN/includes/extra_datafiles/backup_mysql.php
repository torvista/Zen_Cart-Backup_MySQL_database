<?php
// $Id: backup_mysql.php modified 2020-11-23 torvista $

if (!defined('FILENAME_BACKUP')) {//was removed from core ZC158
    define('FILENAME_BACKUP', 'backup');
}
define('FILENAME_BACKUP_MYSQL', 'backup_mysql');

// Set this to 'true' if the zip options aren't appearing while doing a backup, and you are certain that gzip support exists on your server
define('COMPRESS_OVERRIDE','false');
//define('COMPRESS_OVERRIDE','true');

// define the locations of the mysql utilities.  Use FORWARD slashes /.

// Typical hosting Unix/Linux location is in '/usr/bin/' ... but not on Windows servers.
// try 'c:/mysql/bin/mysql.exe' and 'c:/mysql/bin/mysqldump.exe' on Windows hosts ... change drive letter and path as needed
define('MYSQL_EXE',     '/usr/bin/mysql');  // used for restores
define('MYSQLDUMP_EXE', '/usr/bin/mysqldump');  // used for backups

// If you use a local development server such as WAMP or XAMPP with a different MYSQL path to your production hosting, put the full paths here too.
// eg C:/wamp/bin/mysql/mysql5.6.12/bin/mysql.exe' or C:/xampp/mysql/bin/mysql.exe'
define('MYSQL_EXE_LOCAL',     'C:/wamp/bin/mysql/mysql5.6.12/bin/mysql.exe');  // used for restores
define('MYSQLDUMP_EXE_LOCAL', 'C:/wamp/bin/mysql/mysql5.6.12/bin/mysqldump.exe');  // used for backups
