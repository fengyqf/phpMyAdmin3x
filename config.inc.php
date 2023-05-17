<?php
/*
 * Generated configuration file
 * Generated by: phpMyAdmin 3.5.0 setup script
 * Date: Sun, 08 Apr 2012 14:35:10 +0800
 */

/* Servers configuration */
$i = 0;

/* Server: My Local MySQL server [1] */
$i++;
$cfg['Servers'][$i]['verbose'] = 'My Local MySQL server';
$cfg['Servers'][$i]['host'] = 'localhost';
$cfg['Servers'][$i]['port'] = '';
$cfg['Servers'][$i]['socket'] = '';
$cfg['Servers'][$i]['connect_type'] = 'tcp';
$cfg['Servers'][$i]['extension'] = 'mysqli';
$cfg['Servers'][$i]['auth_type'] = 'cookie';
$cfg['Servers'][$i]['user'] = '';
$cfg['Servers'][$i]['password'] = '';
$cfg['Servers'][$i]['compress'] = true;
$cfg['Servers'][$i]['pmadb'] = 'phpmyadmin';
$cfg['Servers'][$i]['controluser'] = 'phpmyadmin';
$cfg['Servers'][$i]['controlpass'] = 'phpmyadmin';
$cfg['Servers'][$i]['bookmarktable'] = 'pma_bookmark';
$cfg['Servers'][$i]['relation'] = 'pma_relation';
$cfg['Servers'][$i]['table_info'] = 'pma_table_info';
$cfg['Servers'][$i]['table_coords'] = 'pma_table_coords';
$cfg['Servers'][$i]['pdf_pages'] = 'pma_pdf_pages';
$cfg['Servers'][$i]['column_info'] = 'pma_column_info';
$cfg['Servers'][$i]['history'] = 'pma_history';
$cfg['Servers'][$i]['designer_coords'] = 'pma_designer_coords';
$cfg['Servers'][$i]['userconfig'] = 'pma_userconfig';
$cfg['Servers'][$i]['recent'] = 'pma_recent';
$cfg['Servers'][$i]['table_uiprefs'] = 'pma_table_uiprefs';
$cfg['Servers'][$i]['tracking'] = 'pma_tracking';
$cfg['Servers'][$i]['MaxTableUiprefs'] = 1000;
$cfg['Servers'][$i]['tracking_version_auto_create'] = false;


/* End of servers configuration */

$cfg['DefaultLang'] = 'zh_CN';
$cfg['ThemeDefault'] = 'original';
$cfg['UploadDir'] = '';
$cfg['SaveDir'] = '';
$cfg['AjaxEnable'] = false;
$cfg['ServerDefault'] = 1;
$cfg['QueryHistoryDB'] = true;
$cfg['MaxCharactersInDisplayedSQL'] = 5000;
$cfg['LeftFrameDBTree'] = false;
$cfg['PersistentConnections'] = true;
$cfg['ExecTimeLimit'] = 0;
$cfg['PropertiesIconic'] = true;
$cfg['ShowPhpInfo'] = true;
$cfg['blowfish_secret'] = 'fsctoken';
$cfg['Export']['sql_procedure_function'] = false;
$cfg['pinned_collations'] = array(
    'utf8mb4_general_ci','utf8mb4_unicode_ci','utf8mb4_bin',
    'utf8_general_ci','utf8_unicode_ci','utf8_bin',
);

//forece QueryWindowsSize
$cfg['QueryWindowSize']['Width'] = 1200;
$cfg['QueryWindowSize']['Height'] = 400;

//display errors, useful for debug
//$cfg['Error_Handler']['display'] = 20;  // display count or true(default 20)


/*  ** put your addition configure into  ./config_patch.inc.php, such as:

 *   user/password for the default mysql-server

$cfg['Servers'][1]['auth_type'] = 'config';
$cfg['Servers'][1]['user'] = 'root';
$cfg['Servers'][1]['password'] = 'you-password';


 *   And some more mysql-server configure
$i++;
$cfg['Servers'][$i]['verbose'] = 'the-2nd-server';
$cfg['Servers'][$i]['host'] = '10.0.0.123';
...

 *   And other configure as your mind

*/

include('./config_patch.inc.php');

?>
