<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 *
 * @package PhpMyAdmin
 */

/**
 *
 */
require_once './libraries/common.inc.php';

/**
 * Does the common work
 */
$GLOBALS['js_include'][] = 'server_privileges.js';
$GLOBALS['js_include'][] = 'replication.js';

require './libraries/server_common.inc.php';
require './libraries/replication.inc.php';
require './libraries/replication_gui.lib.php';
require_once './libraries/server_synchronize.lib.php';

/**
 * Checks if the user is allowed to do what he tries to...
 */
if (! $is_superuser) {
    include './libraries/server_links.inc.php';
    echo '<h2>' . "\n"
        . PMA_getIcon('s_replication.png')
        . __('Replication') . "\n"
        . '</h2>' . "\n";
    PMA_Message::error(__('No Privileges'))->display();
    include './libraries/footer.inc.php';
}

/**
 * Handling control requests
 */
//slave reply until position
$slave_status=isset($server_slave_replication[0]) ? $server_slave_replication[0] : null;
if(isset($slave_status['Until_Condition']) && isset($slave_status['Until_Log_File'])){
        $slave_Until_Condition=$slave_status['Until_Condition'];
        $slave_Until_Log_File=$slave_status['Until_Log_File'];
        $slave_Until_Log_Pos=$slave_status['Until_Log_Pos'];
}else{
    $slave_Until_Condition=null;
    $slave_Until_Log_File=null;
    $slave_Until_Log_Pos=null;
}

$sql_query='';
$refresh = false;
if (isset($GLOBALS['sr_take_action'])) {
    if (isset($GLOBALS['slave_changemaster'])) {
        $_SESSION['replication']['m_username'] = $sr['username'] = PMA_sqlAddSlashes($GLOBALS['username']);
        $_SESSION['replication']['m_password'] = $sr['pma_pw']   = PMA_sqlAddSlashes($GLOBALS['pma_pw']);
        $_SESSION['replication']['m_hostname'] = $sr['hostname'] = PMA_sqlAddSlashes($GLOBALS['hostname']);
        $_SESSION['replication']['m_port']     = $sr['port']     = PMA_sqlAddSlashes($GLOBALS['port']);
        $_SESSION['replication']['m_correct']  = '';
        $_SESSION['replication']['sr_action_status'] = 'error';
        $_SESSION['replication']['sr_action_info'] = __('Unknown error');

        // Attempt to connect to the new master server
        $link_to_master = null;
        if ($sr['username'] && $sr['hostname']){
            $link_to_master = PMA_replication_connect_to_master($sr['username'], $sr['pma_pw'], $sr['hostname'], $sr['port']);
            if ($link_to_master) {
                $_SESSION['replication']['sr_action_status'] = 'error';
                $_SESSION['replication']['sr_action_info'] =
                        sprintf(__('Unable to connect to master %s.'), htmlspecialchars($sr['hostname']));
            }
        }

        if ($sr['username'] && $sr['hostname'] && ! $link_to_master) {
            // seems never run to here, just keep below else-block
            $_SESSION['replication']['sr_action_status'] = 'error';
            $_SESSION['replication']['sr_action_info'] = sprintf(__('Unable to connect to master %s.'), htmlspecialchars($sr['hostname']));
        } else {
            // Read the current master position, or prefer position form form if exists
            $position = array();
            if(isset($GLOBALS['try_sync_master_position']) && $GLOBALS['try_sync_master_position'] && $link_to_master){
                $position = PMA_replication_slave_bin_log_master($link_to_master);
                if (empty($position)) {
                    $fx_error=1;
                    $_SESSION['replication']['sr_action_status'] = 'error';
                    $_SESSION['replication']['sr_action_info'] = __('Unable to read master log position. Possible privilege problem on master.');
                }
            }elseif(isset($GLOBALS['Master_Log_File']) && $GLOBALS['Master_Log_File']){
                $position = array(  "File"  =>    PMA_sqlAddSlashes($GLOBALS['Master_Log_File']),
                                    "Position" => (int)($GLOBALS['Master_Log_Pos']),
                                );
            }else{
                $position = array(  "File"  => '',"Position" => 0 );
            }

            if(!isset($fx_error)) {
                $_SESSION['replication']['m_correct']  = true;
                if (! PMA_replication_slave_change_master($sr['username'], $sr['pma_pw'], $sr['hostname'], $sr['port'], $position, true, false,null,$sql_query)) {
                    $_SESSION['replication']['sr_action_status'] = 'error';
                    $_SESSION['replication']['sr_action_info'] = __('Unable to change master');
                } else {
                    $_SESSION['replication']['sr_action_status'] = 'success';
                    $_SESSION['replication']['sr_action_info'] = sprintf(__('Master server changed successfully to %s'), htmlspecialchars($sr['hostname']));
                }
                unset($fx_error);
            }
        }
    } elseif (isset($GLOBALS['sr_slave_server_control'])) {
        if ($GLOBALS['sr_slave_action'] == 'reset' || $GLOBALS['sr_slave_action'] == 'reset_start') {
            PMA_replication_slave_control("STOP",null,null,null,$sql_query);
            PMA_DBI_try_query("RESET SLAVE;");              $sql_query .= "RESET SLAVE;\r\n";
            if($GLOBALS['sr_slave_action'] == 'reset_start'){
                PMA_replication_slave_control("START",null,null,null,$sql_query);
            }
        } else {
            $tmp_parm=isset($GLOBALS['sr_slave_control_parm']) ? $GLOBALS['sr_slave_control_parm'] : '';
            $slave_until=array(
                'Until_Condition' => isset($GLOBALS['Until_Condition']) ? $GLOBALS['Until_Condition'] : '',
                'Until_Log_File' => isset($GLOBALS['Until_Log_File']) ? $GLOBALS['Until_Log_File'] : '',
                'Until_Log_Pos' => isset($GLOBALS['Until_Log_Pos']) ? (int)($GLOBALS['Until_Log_Pos']) : 0,
                );
            PMA_replication_slave_control($GLOBALS['sr_slave_action'], $tmp_parm,null,$slave_until,$sql_query);
        }
        $refresh = true;

    } elseif (isset($GLOBALS['sr_slave_skip_error'])) {
        $count = 1;
        if (isset($GLOBALS['sr_skip_errors_count'])) {
            $count = $GLOBALS['sr_skip_errors_count'] * 1;
        }
        //PMA_replication_slave_control("STOP",null,null,null,$sql_query);
        $tmp_sql="SET GLOBAL SQL_SLAVE_SKIP_COUNTER = ".$count.";" ;
        PMA_DBI_try_query($tmp_sql);                    $sql_query .= "$tmp_sql\r\n";
        $fx_start_slave=1;
        if(isset($GLOBALS['sr_skip_errors_until'])){
            $slave_until=array(
                'Until_Condition' => $slave_Until_Condition,
                'Until_Log_File' => $slave_Until_Log_File,
                'Until_Log_Pos' => $slave_Until_Log_Pos,
                );
        }elseif(isset($GLOBALS['sr_skip_errors_continue'])){
            $slave_until=array();
        }else{
            $fx_start_slave=0;
        }
        if($fx_start_slave==1){
            PMA_replication_slave_control("START","SQL_THREAD",null,$slave_until,$sql_query);
        }

    } elseif (isset($GLOBALS['sl_sync'])) {
        // TODO username, host and port could be read from 'show slave status',
        // when asked for a password this might work in more situations then just after changing master (where the master password is stored in session)
        $src_link = PMA_replication_connect_to_master($_SESSION['replication']['m_username'], $_SESSION['replication']['m_password'], $_SESSION['replication']['m_hostname'], $_SESSION['replication']['m_port']);
        $trg_link = null; // using null to indicate the current PMA server

        $data = PMA_DBI_fetch_result(
        ((PMA_MYSQL_INT_VERSION < 50700) ? 'SHOW MASTER STATUS' : 'SHOW BINARY LOG STATUS'), 
        null, null, $src_link); // let's find out, which databases are replicated

        $do_db     = array();
        $ignore_db = array();
        $dblist    = array();

        if (! empty($data[0]['Binlog_Do_DB'])) {
            $do_db     = explode(',', $data[0]['Binlog_Do_DB']);
        }
        if (! empty($data[0]['Binlog_Ignore_DB'])) {
            $ignore_db = explode(',', $data[0]['Binlog_Ignore_DB']);
        }

        $tmp_alldbs = PMA_DBI_query('SHOW DATABASES;', $src_link);
        while ($tmp_row = PMA_DBI_fetch_row($tmp_alldbs)) {
            if (PMA_is_system_schema($tmp_row[0])) {
                continue;
            }
            if (count($do_db) == 0) {
                if (array_search($tmp_row[0], $ignore_db) !== false) {
                    continue;
                }
                $dblist[] = $tmp_row[0];

                PMA_DBI_query('CREATE DATABASE IF NOT EXISTS '.PMA_backquote($tmp_row[0]), $trg_link);
            } else {
                if (array_search($tmp_row[0], $do_db) !== false) {
                    $dblist[] = $tmp_row[0];
                    PMA_DBI_query('CREATE DATABASE IF NOT EXISTS '.PMA_backquote($tmp_row[0]), $trg_link);
                }
            }
        } // end while

        unset($do_db, $ignore_db, $data);

        if (isset($GLOBALS['repl_data'])) {
            $include_data = true;
        } else {
            $include_data = false;
        }
        foreach ($dblist as $db) {
            PMA_replication_synchronize_db($db, $src_link, $trg_link, $include_data);
        }
        // TODO some form of user feedback error/success would be nice
        //  What happens if $dblist is empty?
        //  or sync failed?
    }

    if ($refresh) {
        //Header("Location: ". PMA_generate_common_url($GLOBALS['url_params']));
    }
}
/**
 * Displays the links
 */
require './libraries/server_links.inc.php';

if($sql_query){
    PMA_showMessage(null, $sql_query, 'success');
    if ($refresh) {
        require './libraries/footer.inc.php';
        die();
    }
}
unset($refresh);

echo '<div id="replication">';
echo ' <h2>';
echo '   ' . PMA_getImage('s_replication.png');
echo     __('Replication');
echo ' </h2>';

// Display error messages
if (isset($_SESSION['replication']['sr_action_status']) && isset($_SESSION['replication']['sr_action_info'])) {
    if ($_SESSION['replication']['sr_action_status'] == 'error') {
        PMA_Message::error($_SESSION['replication']['sr_action_info'])->display();
        $_SESSION['replication']['sr_action_status'] = 'unknown';
    } elseif ($_SESSION['replication']['sr_action_status'] == 'success') {
        PMA_Message::success($_SESSION['replication']['sr_action_info'])->display();
        $_SESSION['replication']['sr_action_status'] = 'unknown';
    }
}

if ($server_master_status) {
    if (! isset($GLOBALS['repl_clear_scr']) && !isset($GLOBALS['sr_take_action'])) {
        echo '<fieldset>';
        echo '<legend>' . __('Master replication') . '</legend>';
        echo __('This server is configured as master in a replication process.');
        echo '<ul>';
        echo '  <li><a href="#" id="master_status_href">' . __('Show master status') . '</a></li>';
        PMA_replication_print_status_table('master', true, false);

        echo '  <li><a href="#" id="master_slaves_href">' . __('Show connected slaves') . '</a></li>';
        PMA_replication_print_slaves_table(true);

        $_url_params = $GLOBALS['url_params'];
        $_url_params['mr_adduser'] = true;
        $_url_params['repl_clear_scr'] = true;

        echo '  <li><a href="' . PMA_generate_common_url($_url_params) . '" id="master_addslaveuser_href">' . __('Add slave replication user') . '</a></li>';
    }

    // Display 'Add replication slave user' form
    if (isset($GLOBALS['mr_adduser'])) {
        PMA_replication_gui_master_addslaveuser();
    } elseif (! isset($GLOBALS['repl_clear_scr'])) {
        echo "</ul>";
        echo "</fieldset>";
    }
} elseif (! isset($GLOBALS['mr_configure']) && ! isset($GLOBALS['repl_clear_scr'])) {
    $_url_params = $GLOBALS['url_params'];
    $_url_params['mr_configure'] = true;

    echo '<fieldset>';
    echo '<legend>' . __('Master replication') . '</legend>';
    echo sprintf(__('This server is not configured as master in a replication process. Would you like to <a href="%s">configure</a> it?'), PMA_generate_common_url($_url_params));
    echo '</fieldset>';
}

if (isset($GLOBALS['mr_configure'])) {
    // Render the 'Master configuration' section
    echo '<fieldset>';
    echo '<legend>' . __('Master configuration') . '</legend>';
    echo __('This server is not configured as master server in a replication process. You can choose from either replicating all databases and ignoring certain (useful if you want to replicate majority of databases) or you can choose to ignore all databases by default and allow only certain databases to be replicated. Please select the mode:') . '<br /><br />';

    echo '<select name="db_type" id="db_type">';
    echo '<option value="all">' . __('Replicate all databases; Ignore:') . '</option>';
    echo '<option value="ign">' . __('Ignore all databases; Replicate:') . '</option>';
    echo '</select>';
    echo '<br /><br />';
    echo __('Please select databases:') . '<br />';
    echo PMA_replication_db_multibox();
    echo '<br /><br />';
    echo __('Now, add the following lines at the end of [mysqld] section in your my.cnf and please restart the MySQL server afterwards.') . '<br />';
    echo '<pre id="rep"></pre>';
    echo __('Once you restarted MySQL server, please click on Go button. Afterwards, you should see a message informing you, that this server <b>is</b> configured as master');
    echo '</fieldset>';
    echo '<fieldset class="tblFooters">';
    echo ' <form method="post" action="server_replication.php" >';
    echo PMA_generate_common_hidden_inputs('', '');
    echo '  <input type="submit" value="' . __('Go') . '" id="goButton" />';
    echo ' </form>';
    echo '</fieldset>';

    include './libraries/footer.inc.php';
    exit;
}

echo '</div>';

if (! isset($GLOBALS['repl_clear_scr'])) {
    // Render the 'Slave configuration' section
    echo '<fieldset>';
    echo '<legend>' . __('Slave replication') . '</legend>';
    if ($server_slave_status) {
        echo '<div id="slave_configuration_gui">';

        $_url_params = $GLOBALS['url_params'];
        $_url_params['sr_take_action'] = true;
        $_url_params['sr_slave_server_control'] = true;

        if ($server_slave_replication[0]['Slave_IO_Running'] == 'No') {
            $_url_params['sr_slave_action'] = 'start';
        } else {
            $_url_params['sr_slave_action'] = 'stop';
        }

        $_url_params['sr_slave_control_parm'] = 'IO_THREAD';
        $slave_control_io_link = PMA_generate_common_url($_url_params);

        if ($server_slave_replication[0]['Slave_SQL_Running'] == 'No') {
            $_url_params['sr_slave_action'] = 'start';
        } else {
            $_url_params['sr_slave_action'] = 'stop';
        }

        $_url_params['sr_slave_control_parm'] = 'SQL_THREAD';
        $slave_control_sql_link = PMA_generate_common_url($_url_params);

        if ($server_slave_replication[0]['Slave_IO_Running'] == 'No'
            || $server_slave_replication[0]['Slave_SQL_Running'] == 'No'
        ) {
            $_url_params['sr_slave_action'] = 'start';
        } else {
            $_url_params['sr_slave_action'] = 'stop';
        }

        $_url_params['sr_slave_control_parm'] = null;
        $slave_control_full_link = PMA_generate_common_url($_url_params);

        $_url_params['sr_slave_action'] = 'reset';
        $slave_control_reset_link = PMA_generate_common_url($_url_params);

        $_url_params['sr_slave_action'] = 'reset_start';
        $slave_control_reset_start_link = PMA_generate_common_url($_url_params);

        $_url_params = $GLOBALS['url_params'];
        $_url_params['sr_take_action'] = true;
        $_url_params['sr_slave_skip_error'] = true;
        $_url_params['sr_skip_errors_count'] = 1;
        $slave_skip_error_link = PMA_generate_common_url($_url_params);

        $_url_params = $GLOBALS['url_params'];
        $_url_params['sl_configure'] = true;
        $_url_params['repl_clear_scr'] = true;

        $reconfiguremaster_link = PMA_generate_common_url($_url_params);

        //echo __('Server is configured as slave in a replication process. Would you like to:');
        //echo '<br />';
        echo '<ul>';
        echo ' <li>IO Thread: ';
        if ($server_slave_replication[0]['Slave_IO_Running'] == 'No') {
            echo PMA_getImage('s_error2.png','Slave IO Thread Stoped');
        }else{
            echo PMA_getImage('s_success.png','Slave IO Thread is Running');
        }
        echo '  &nbsp;&nbsp; SQL Thread: ';
        if ($server_slave_replication[0]['Slave_SQL_Running'] == 'No') {
            echo PMA_getImage('s_error2.png','SQL Thread Stoped');
        }else{
            echo PMA_getImage('s_success.png','SQL Thread is Running');
        }
        echo '</li>';
        echo ' <li><a href="#" id="slave_status_href">' . __('See slave status table') . '...</a></li>';
        echo PMA_replication_print_status_table('slave', true, false);
        if (isset($_SESSION['replication']['m_correct']) && $_SESSION['replication']['m_correct'] == true) {
            echo ' <li><a href="#" id="slave_synchronization_href">' . __('Synchronize databases with master') . '</a></li>';
            echo ' <div id="slave_synchronization_gui" style="display: none;">';
            echo '  <form method="post" action="server_replication.php">';
            echo PMA_generate_common_hidden_inputs('', '');
            echo '   replication use account is used here, maybe not authorized. <br />';
            echo '   <input type="checkbox" name="repl_struc" value="1" checked="checked" disabled="disabled" /> ' . __('Structure') . '<br />'; // this is just for vizualization, it has no other purpose
            echo '   <input type="checkbox" name="repl_data"  value="1" checked="checked" /> ' . __('Data') .' <br />';
            echo '   <input type="hidden" name="sr_take_action" value="1" />';
            echo '   <input type="submit" name="sl_sync" value="' . __('Go') . '" />';
            echo '  </form>';
            echo ' </div>';
        }
        echo ' <li><a href="#" id="slave_control_href">' . __('Control slave:') . '</a>';
        echo ' <div id="slave_control_gui" style="display: block;">';
        echo '  <ul>';
        echo '   <li><a href="'. $slave_control_full_link . '">' . (($server_slave_replication[0]['Slave_IO_Running'] == 'No' || $server_slave_replication[0]['Slave_SQL_Running'] == 'No') ? __('Full start') : __('Full stop')) . ' </a></li>';
        echo '   <li><a href="'. $slave_control_reset_link . '">' . __('Reset slave') . '</a></li>';
        echo '   <li><a href="'. $slave_control_reset_start_link . '">' . __('Reset slave') .','. __('Full start') .'</a></li>';
        if ($server_slave_replication[0]['Slave_IO_Running'] == 'No') {
            echo '   <li><a href="' . $slave_control_io_link . '">' . __('Start IO Thread only') . '</a></li>';
        } else {
            echo '   <li><a href="' . $slave_control_io_link . '">' . __('Stop IO Thread only') . '</a></li>';
        }
        if ($server_slave_replication[0]['Slave_SQL_Running'] == 'No') {
            echo '   <li><a href="' . $slave_control_sql_link . '">' . __('Start SQL Thread only') . '</a></li>';
            echo '   <li>';
            echo '    <form method="post" action="server_replication.php"><span>' . __('START SLAVE UNTIL ').'</span>';
            echo PMA_generate_common_hidden_inputs('', '');
            //PMA_display_html_radio('Until_Condition', array('Master'=>'Master','Relay'=>'Relay'), $slave_Until_Condition, false, true, "");
            echo PMA_generate_html_dropdown('Until_Condition', array('Master'=>'Master_Log_File','Relay'=>'Relay_Log_File'), $slave_Until_Condition,'Until_Condition');
            echo '      Log_File <input type="text" name="Until_Log_File" value="'.$slave_Until_Log_File.'" style="width: 180px" />';
            echo '      Log_Pos <input type="text" name="Until_Log_Pos" value="'.$slave_Until_Log_Pos.'" style="width: 60px" />';
            echo '      <input type="hidden" name="sr_slave_action" value="start" />';
            echo '      <input type="hidden" name="sr_slave_control_parm" value="SQL_THREAD" />';
            echo '              <input type="submit" name="sr_slave_action_start_until" value="' . __('Go') . '" />';
            echo '      <input type="hidden" name="sr_take_action" value="1" />';
            echo '      <input type="hidden" name="sr_slave_server_control" value="1" />';
            echo '    </form></li>';
        } else {
            echo '   <li><a href="' . $slave_control_sql_link . '">' . __('Stop SQL Thread only') . '</a></li>';
        }
        echo '  </ul>';
        echo ' </div>';
        echo ' </li>';
        echo ' <li><a href="#" id="slave_errormanagement_href">' . __('Error management:') . '</a>(skip next N binlog events)';
        if ($server_slave_replication[0]['Last_Errno'] == 0 ) {
            echo ' <div id="slave_errormanagement_gui" style="display: none;">';
        }
        PMA_Message::notice(__('Skipping errors might lead into unsynchronized master and slave!'))->display();
        echo "   <div>Last Error: [{$server_slave_replication[0]['Last_Errno']}] @{$server_slave_replication[0]['Relay_Master_Log_File']}:{$server_slave_replication[0]['Exec_Master_Log_Pos']}</dv><div>{$server_slave_replication[0]['Last_Error']}</div>";
        echo '  <ul>';
        echo '   <li><a href="' . $slave_skip_error_link . '" title="SQL_SLAVE_SKIP_COUNTER=1">' . __('Skip current error') . '</a></li>';
        echo '   <li>' . __('Skip next');
        echo '    <form method="post" action="server_replication.php">';
        echo PMA_generate_common_hidden_inputs('', '');
        echo '      <input type="text" name="sr_skip_errors_count" value="1" style="width: 30px" />' . __('events, ');
        $fx_tmp_checked=(isset($GLOBALS['sr_skip_errors_continue'])) ? " checked" : '';
        echo '      <label><input type="checkbox" name="sr_skip_errors_continue" value="1"'.$fx_tmp_checked.' />Start Slave</label>';
        if($slave_Until_Log_File){
            $fx_tmp_checked=(isset($GLOBALS['sr_skip_errors_until'])) ? " checked" : '';
            echo '      <label><input type="checkbox" name="sr_skip_errors_until" value="1"'.$fx_tmp_checked.' />Start Slave until'.' '.$slave_Until_Log_File.':'.$slave_Until_Log_Pos.' </label>';
        }
        unset($fx_tmp_checked);
        echo '              <input type="submit" name="sr_slave_skip_error" value="' . __('Go') . '" />';
        echo '      <input type="hidden" name="sr_take_action" value="1" />';
        echo '    </form></li>';
        echo '  </ul>';
        echo ' </div>';
        echo ' </li>';
        echo ' <li><a href="' . $reconfiguremaster_link . '">' . __('Change or reconfigure master server') . '</a></li>';
        echo '</ul>';

    } elseif (! isset($GLOBALS['sl_configure'])) {
        $_url_params = $GLOBALS['url_params'];
        $_url_params['sl_configure'] = true;
        $_url_params['repl_clear_scr'] = true;

        echo sprintf(__('This server is not configured as slave in a replication process. Would you like to <a href="%s">configure</a> it?'), PMA_generate_common_url($_url_params));
    }
    echo '</div>';
    echo '</fieldset>';
}
if (isset($GLOBALS['sl_configure'])) {
    $fx_data=PMA_replication_slave_get_master_pos();
    PMA_replication_gui_changemaster("slave_changemaster",$fx_data);
}
require './libraries/footer.inc.php';
?>
