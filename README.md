# August 2024: Most of this has been ported to the plugin version: https://github.com/torvista/Zen_Cart-Backup_MYSQL_Plugin

# Backup MySQL for Zen Cart

Plugin: BACKUP_MYSQL Admin Tool 
Designed for: Zen Cart v1.5.x series (tested up to Zen Cart 1.58)
Created by: DrByte

This essential tool provides an Admin-based interface to 
- backup your database (eg: prior to an upgrade or addition or new code) 
- restore backups (eg. while testing a code mod repeatedly).

Donations:  Please support Zen Cart!  paypal@zen-cart.com  - Thank you!

Support thread for the ORIGINAL mod
https://www.zen-cart.com/showthread.php?35714-Backup-MySQL-Database

This fileset has been extensively modified from the version in the Plugins...see the changelog below.

INSTALLATION

1) Upload all files from the "YOUR_ADMIN" folder into your admin directory, retaining the folder structures.

2) When you first refresh an admin page, the file
YOUR_ADMIN/includes/functions/extra_functions/register_backup_mysql.php
should add the Database Backup-MySQL menu item under the Tools Section.

3) Click on "Tools"->"Database Backup-MySQL".
The tool tries various paths looking for the mysql executables files required for the back/restore functions.
If the mod does not detect the paths automatically, configure the correct paths in 
YOUR_ADMIN/includes/extra_datafiles/backup_mysql.php
and please report it in the support thread so the code can be improved.


Zen Cart versions PRIOR to 1.55 ONLY

4) If you are using a CATALOG /local/configure.php PRIOR TO Zen Cart 1.55 the mod will complain about not being able to locate the backup directory.
Comment out the original line and add this changed line.
//define('DIR_FS_ADMIN', realpath(dirname(__FILE__) . '/../') . '/');//standard configure.php
  define('DIR_FS_ADMIN', realpath(dirname(__FILE__) . '/../../') . '/');//use for /local/configure.php ONLY (fixed in 1.55)
  

===========================================================

UNINSTALL

Remove the files.
Run the uninstall_Backup_Mysql.sql in the Admin->Tools->SQL patch or phpMyadmin.

===========================================================

USE

In the admin area, click on "Tools"->"Database Backup-MySQL".

Backup

You have the choice to create the backup file in the YOUR_ADMIN/backups directory or download it directly to your local computer.
If the server does not support GZIP compression the option will not be displayed.

You can download a backup file that already exists in the YOUR_ADMIN/includes/backups folder by clicking on the small down-arrow icon to the left of the backup filename.

NOTE: If you intend to download files, we STRONGLY recommend you do it only via a secure SSL / HTTPS: connection. Otherwise you put all 
your customers' data at risk of somebody tracking your download.

Restore

Select a file to restore.

Note that from ZC 1.53 the value of DIR_FS_SQL_CACHE (which is stored in the database) is checked on every admin page load and corrected if necessary.
This causes the first page-load after restoring a backup, to auto redirect to the admin index page. 

=========================================================

POSSIBLE PROBLEMS

1) Backup folder permissions

Note that to perform backups, your YOUR_ADMIN/backups folder must be writable by the webserver user.

2) Cannot find "mysql" and "mysqldump"

The second time the Backup MySQL page is refreshed, the paths defined in 
/extra_datafiles/backup_mysql.php 
will be checked along with other pre-defined common paths to try and confirm the existence of the two MySQL utilities required.
If the tools are not found, an error message is displayed.
Extra information can be displayed by setting DEBUG to 'ON' at the start of the admin/backup_mysql.php
file.

Example error on a local windows server
"Warning Result code: 1
Warning 0 => '"mysqldump"' is not recognized as an internal or external command,
Warning 1 => operable program or batch file."

This tool requires/looks for the mysql binary tools "mysql" and "mysqldump".
If for some reason it is not finding the tools properly, you will need to edit the filepaths defined for LOCAL_EXE_MYSQL and LOCAL_EXE_MYSQLDUMP in

YOUR_ADMIN/includes/extra_datafiles/backup_mysql.php

Windows servers may require this.... but some detection is already built-in for standard paths on Windows configurations, so give it a try as-is first. 
Otherwise, edit the settings in the file, and it should be fine (do NOT put a trailing / in the end of the define).

In many cases, Windows 2003 servers will prevent the use of exec() commands by virtue of the fact that Windows 2003 restricts the Internet Guest Account from being allowed to run cmd.exe.  To override this, you would need to alter the security permissions on cmd.exe and grant the Internet Guest Account read/execute as "Special Access" permissions.
NOTE: This may be a security risk to your server, so it's best to consult with your security expert before making such a change.

3) php safe_mode, open.basedir

This tool will not work on servers running in strict safe_mode, or with open.basedir restrictions in effect, or with restrictions against using "exec()" commands. There is no way to work around these limitations without turning off the restrictions on your server. Contact your host for more information on such possibilities.
          
4) After a restore, the browser is redirected to the admin in index page.
If the restored database came from another server, the value of SESSION_WRITE_DIRECTORY will be incorrect. This is detected automatically (from ZC 1.5.3), corrected and the browser redirected to the index. If, when returning to the backup_mysql menu item, it still redirects to the index page, this is due to cacheing of this constant by the browser session (I think...), just refresh the page manually to fix it.

===========================================================

CHANGELOG:
2020-11-23 - torvista: added defines for ICON_FILE_DOWNLOAD, IMAGE_BACKUP, IMAGE_RESTORE (removed from core in ZC157) and 'backup' directory (removed in ZC158). Many changes based on IDE inspections/recommendations.

2017-02-15 - torvista:more stuff for php 7.3

2017-02-15 - torvista: 
- added option to add a suffix to backup filename
- changed mysql to mysqli
- added support for gzip and zip restores in windows
- added extra tool paths to configures file to enable the same file to be used in a production and local development environment
- moved mysql tool path defines from language files to /extra_datafiles
- added automatic path detection to the paths checked
- added error checks to page registration of admin tools menu item to detect incomplete installations
- changed quote marks to single for Nix and double for Win around passwords to cope with metachars ($%<&* etc.) in passwords
- added debug info option tickbox (at foot of file)
- changed to html5, removed obsolete formatting tags

2012-07-03 - v1.5a - to better detect and avoid display of files that aren't related to backups.

2011-12-09 - v1.5 - with additional file to register page for 1.5.0.

2010-06-01 - v1.4 - includes PHP 5.3 fixes and smarter detection of whether exec() is disabled.

2008-01-04 - 1.3.5, compression improvements.

2007-04-28 - Updated contrib to new version number: 1.3 -- auto-handles lock-tables limitations and various bugfixes.

2006-02-28 - Completed support for individual table export (&tables=xxx,xxxx,xxxx) and added more tweaks for IIS support.

2006-01-10 - Small typo fixed related to open_basedir detection.

2005-12-30 - Updated to allow more overrides in compression options, to detect failures due to Win2003 limitations, etc.

2005-11-12 - Updated to default to typical binary names if none found -- attempted safe-mode workaround.

2005-11-10 - Now accommodates path-names containing spaces (Windows hosts).

2005-07-21 - Tiny update to predeclare some vars for Windows hosts.

2005-07-21 - Updated to allow option to "skip locks" in case your host has not given you the "LOCK TABLES" permission.

2005-07-20 - Set GZIP on by default for new backups, and fixed logic bug on path detection (thanks to masterblaster).

2005-03-21 - Added exclusion for "index.php" in listing of backup archives.

2004-09-25 - Added additional search paths for finding binaries, as well as more error-checking.

2004-08-18 - Modified script to work on servers where database is hosted remotely.

2004-08-17 - Added additional error-checking output for better indication of causes of failures.

2004-08-04 - Added additional search paths for finding binaries to be executed.

2004-08-01 - Initial Release.
