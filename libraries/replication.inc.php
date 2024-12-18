<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 *
 * @package PhpMyAdmin
 */

if (! defined('PHPMYADMIN')) {
    exit;
}

/**
 * get master replication from server
 */
$server_master_replication = PMA_fx_show_master_status();
/**
 * get slave replication from server
 */
$server_slave_replication = PMA_fx_show_slave_status();

/**
 * replication types
 */
$replication_types = array('master', 'slave');


/**
 * define variables for master status
 */
$master_variables = array(
    'File',
    'Position',
    'Binlog_Do_DB',
    'Binlog_Ignore_DB',
);

/**
 * Define variables for slave status
 *   24/12/09   maybe it is useless already, but keep it still
 */
$slave_variables  = array(
    'Slave_IO_State',
    'Master_Host',
    'Master_User',
    'Master_Port',
    'Connect_Retry',
    'Master_Log_File',
    'Read_Master_Log_Pos',
    'Relay_Log_File',
    'Relay_Log_Pos',
    'Relay_Master_Log_File',
    'Slave_IO_Running',
    'Slave_SQL_Running',
    'Replicate_Do_DB',
    'Replicate_Ignore_DB',
    'Replicate_Do_Table',
    'Replicate_Ignore_Table',
    'Replicate_Wild_Do_Table',
    'Replicate_Wild_Ignore_Table',
    'Last_Errno',
    'Last_Error',
    'Skip_Counter',
    'Exec_Master_Log_Pos',
    'Relay_Log_Space',
    'Until_Condition',
    'Until_Log_File',
    'Until_Log_Pos',
    'Master_SSL_Allowed',
    'Master_SSL_CA_File',
    'Master_SSL_CA_Path',
    'Master_SSL_Cert',
    'Master_SSL_Cipher',
    'Master_SSL_Key',
    'Seconds_Behind_Master',
);
/**
 * define important variables, which need to be watched for correct running of replication in slave mode
 *
 * @usedby PMA_replication_print_status_table()
 */
// TODO change to regexp or something, to allow for negative match. To e.g. highlight 'Last_Error'
//
$slave_variables_alerts = array(
    'Slave_IO_Running' => 'No',
    'Slave_SQL_Running' => 'No',
    'Replica_IO_Running' => 'No',
    'Replica_SQL_Running' => 'No',
);
$slave_variables_oks = array(
    'Slave_IO_Running' => 'Yes',
    'Slave_SQL_Running' => 'Yes',
    'Replica_IO_Running' => 'Yes',
    'Replica_SQL_Running' => 'Yes',
);

// check which replication is available and set $server_{master/slave}_status and assign values

// replication info is more easily passed to functions
/*
 * @todo use $replication_info everywhere instead of the generated variable names
 */
$replication_info = array();

if(count($server_master_replication) > 0){
    $server_master_status       = true;
    $replication_info['master'] = array(
        'status'   => true,
        'Do_DB'    => explode(",", $server_master_replication[0]["Binlog_Do_DB"]),
        'Ignore_DB'    => explode(",", $server_master_replication[0]["Binlog_Ignore_DB"]),
    );
    // vars below seem never used
    $server_master_Do_DB        = explode(",", $server_master_replication[0]["Binlog_Do_DB"]);
    $server_master_Ignore_DB    = explode(",", $server_master_replication[0]["Binlog_Ignore_DB"]);
}else{
    $server_master_status = false;
}
if(count($server_slave_replication) > 0){
    $server_slave_status        = true;
    $replication_info['slave']  = array(
        'status'            => true,
        'Do_DB'             => explode(",", $server_slave_replication[0]["Replicate_Do_DB"]),
        'Ignore_DB'         => explode(",", $server_slave_replication[0]["Replicate_Ignore_DB"]),
        'Do_Table'          => explode(",", $server_slave_replication[0]["Replicate_Do_Table"]),
        'Ignore_Table'      => explode(",", $server_slave_replication[0]["Replicate_Ignore_Table"]),
        'Wild_Do_Table'     => explode(",", $server_slave_replication[0]["Replicate_Wild_Do_Table"]),
        'Wild_Ignore_Table' => explode(",", $server_slave_replication[0]["Replicate_Wild_Ignore_Table"]),
    );
    // vars below seem never used
    $server_slave_Do_DB        = explode(",", $server_slave_replication[0]["Replicate_Do_DB"]);
    $server_slave_Ignore_DB    = explode(",", $server_slave_replication[0]["Replicate_Ignore_DB"]);
    $server_slave_Do_Table     = explode(",", $server_slave_replication[0]["Replicate_Do_Table"]);
    $server_slave_Ignore_Table = explode(",", $server_slave_replication[0]["Replicate_Ignore_Table"]);
    $server_slave_Wild_Do_Table        = explode(",", $server_slave_replication[0]["Replicate_Wild_Do_Table"]);
    $server_slave_Wild_Ignore_Table    = explode(",", $server_slave_replication[0]["Replicate_Wild_Ignore_Table"]);

}else{
    $server_slave_status = false;
}

/**
 * @param $string contains "dbname.tablename"
 * @param $what   what to extract (db|table)
 * @return $string the extracted part
 */
function PMA_extract_db_or_table($string, $what = 'db')
{
    $list = explode(".", $string);
    if ('db' == $what) {
        return $list[0];
    } else {
        return isset($list[1]) ? $list[1] : '';
    }
}
/**
 * @param string $action  possible values: START or STOP
 * @param string $control default: null, possible values: SQL_THREAD or IO_THREAD or null. If it is set to null, it controls both SQL_THREAD and IO_THREAD
 * @param mixed  $link    mysql link
 *
 * @return mixed output of PMA_DBI_try_query
 */
function PMA_replication_slave_control($action, $control = '', $link = null,$until=null,&$log_sql=null)
{
    if(PMA_MYSQL_SERVER_TYPE == 'MariaDB' && PMA_MYSQL_INT_VERSION >= 100501){
        $slvtx='REPLICA';
        $mstx='MASTER';
    }elseif(PMA_MYSQL_SERVER_TYPE == 'MySQL' && PMA_MYSQL_INT_VERSION >= 80022){
        $slvtx='REPLICA';
        $mstx='SOURCE';
    }else{
        $slvtx='SLAVE';
        $mstx='MASTER';
    }
    $action = strtoupper((string)$action);
    $control = strtoupper((string)$control);

    if ($action != "START" && $action != "STOP") {
        return -1;
    }
    if ($control != "SQL_THREAD" && $control != "IO_THREAD" && $control != '') {
        return -1;
    }
    // reply to a defined position: START SLAVE UNTIL...
    if($action == "START" && $control == "SQL_THREAD" && isset($until['Until_Log_File']) && $until['Until_Log_File']){
        $log_file=$until['Until_Log_File'] ;
        $pos=isset($until['RELAY_LOG_POS']) ? (int)($until['RELAY_LOG_POS']) : 0 ;
        if($until['Until_Condition']=='Relay'){
            $query="START $slvtx SQL_THREAD UNTIL RELAY_LOG_FILE='".PMA_sqlAddSlashes($log_file)."', RELAY_LOG_POS=".$pos.";";
        }else{
            $query="START $slvtx SQL_THREAD UNTIL {$mstx}_LOG_FILE='".PMA_sqlAddSlashes($log_file)."', {$mstx}_LOG_POS=".$pos.";";
        }
    }else{
        $query=$action . " $slvtx " . $control . ";";
    }
    $log_sql .= $query."\r\n";
    return PMA_DBI_try_query($query, $link);
}
/**
 * @param string $user     replication user on master
 * @param string $password password for the user
 * @param string $host     master's hostname or IP
 * @param int    $port     port, where mysql is running
 * @param array  $pos      position of mysql replication, array should contain fields File and Position
 * @param bool   $stop     shall we stop slave?
 * @param bool   $start    shall we start slave?
 * @param mixed  $link     mysql link
 *
 * @return output of CHANGE MASTER mysql command
 * ref https://dev.mysql.com/doc/refman/8.0/en/change-replication-source-to.html
 *  In MySQL 8.0.23 and later, use CHANGE REPLICATION SOURCE TO in place of the deprecated CHANGE MASTER TO statement. 
 * ref https://mariadb.com/kb/en/change-master-to/
    The terms master and slave have historically been used in replication, 
    and MariaDB has begun the process of adding primary and replica synonyms. 
    The old terms will continue to be used to maintain backward compatibility
     - see MDEV-18777 to follow progress on this effort.
 */
function PMA_replication_slave_change_master($user, $password, $host, $port, $pos, $stop = true, $start = false, $link = null, &$log_sql=null)
{
    if ($stop) {
        PMA_replication_slave_control("STOP", null, $link);
    }

    if(PMA_MYSQL_SERVER_TYPE == 'MySQL' && PMA_MYSQL_INT_VERSION >= 80023){
        $tpl_chg='CHANGE REPLICATION SOURCE TO ';
        $tpl_src="SOURCE_HOST='%s', SOURCE_PORT=%d, SOURCE_USER='%s', SOURCE_PASSWORD='%s' ";
        $tpl_pos="SOURCE_LOG_FILE='%s', SOURCE_LOG_POS=%d ";
    }elseif(PMA_MYSQL_SERVER_TYPE == 'MariaDB' && PMA_MYSQL_INT_VERSION >= 900901){
        $tpl_chg ='CHANGE PRIMARY TO ';
        $tpl_src="PRIMARY_HOST='%s', PRIMARY_PORT=%d, PRIMARY_USER='%s', PRIMARY_PASSWORD='%s' ";
        $tpl_pos="PRIMARY_LOG_FILE='%s', PRIMARY_LOG_POS=%d ";
    }else{
        $tpl_chg ='CHANGE MASTER TO ';
        $tpl_src="MASTER_HOST='%s', MASTER_PORT=%d, MASTER_USER='%s', MASTER_PASSWORD='%s' ";
        $tpl_pos="MASTER_LOG_FILE='%s', MASTER_LOG_POS=%d ";
    }


    $sql_s=$sql_g=array();
    if($host){
        $sql_s[] = sprintf($tpl_src, $host, (int)$port, $user, $password);
        $sql_g[] = sprintf($tpl_src, $host, (int)$port, $user, '***');
    }
    if(isset($pos["File"]) && isset($pos["Position"])){
        $tmp = sprintf($tpl_pos, $pos["File"], (int)$pos["Position"] );
        $sql_s[]=$tmp;
        $sql_g[]=$tmp;
    }
    if(!$sql_s){
        return null;
    }
    $sql    =$tpl_chg.implode(', ',$sql_s);
    $log_sql=$tpl_chg.implode(', ',$sql_g);

    $out = PMA_DBI_try_query($sql, $link);

    if ($start) {
        PMA_replication_slave_control("START", null, $link);
    }

    return $out;
}

/**
 * This function provides connection to remote mysql server
 *
 * @param string $user     mysql username
 * @param string $password password for the user
 * @param string $host     mysql server's hostname or IP
 * @param int    $port     mysql remote port
 * @param string $socket   path to unix socket
 *
 * @return mixed $link mysql link on success
 */
function PMA_replication_connect_to_master($user, $password, $host = null, $port = null, $socket = null)
{
    $server = array();
    $server["host"] = $host;
    $server["port"] = $port;
    $server["socket"] = $socket;

    // 5th parameter set to true means that it's an auxiliary connection
    // and we must not go back to login page if it fails
    return PMA_DBI_connect($user, $password, false, $server, true);
}
/**
 * @param mixed $link mysql link
 *
 * @return array - containing File and Position in MySQL replication on master server, useful for PMA_replication_slave_change_master
 */
function PMA_replication_slave_bin_log_master($link = null)
{
    $data = PMA_fx_show_master_status($link);
    $output = array();

    if (! empty($data)) {
        $output["File"] = $data[0]["File"];
        $output["Position"] = $data[0]["Position"];
    }
    return $output;
}

function PMA_replication_slave_get_status($link = null)
{
    $data = PMA_fx_show_slave_status($link);
    $output = array();

    if (! empty($data)) {
        $output=$data[0];
    }
    return $output;
}

function PMA_replication_slave_get_master_pos($link = null)
{
    $keys=array('');
    $output = array();
    $data=PMA_replication_slave_get_status($link);
    if($data){
        $output["Master_Log_File"]      = isset($data["Source_Log_File"]) ? $data["Source_Log_File"] :
                 (isset($data["Master_Log_File"]) ? $data["Master_Log_File"] : null);
        $output["Read_Master_Log_Pos"]  = isset($data["Read_Source_Log_Pos"]) ? $data["Read_Source_Log_Pos"] :
                 (isset($data["Read_Master_Log_Pos"]) ? $data["Read_Master_Log_Pos"] : null);
        $output["Relay_Log_File"]       = $data["Relay_Log_File"];
        $output["Relay_Log_Pos"]        = $data["Relay_Log_Pos"];
        $output["Relay_Master_Log_File"]= isset($data["Relay_Source_Log_File"]) ? $data["Relay_Source_Log_File"] :
                 (isset($data["Relay_Master_Log_File"]) ? $data["Relay_Master_Log_File"] : null);
        $output["Master_Log_Pos"]       = $output["Read_Master_Log_Pos"];  // use Relay_Master_Log_File
        if( (isset($data["Slave_IO_Running"]) && $data["Slave_IO_Running"]=='Yes')
            || (isset($data["Slave_SQL_Running"]) && $data["Slave_SQL_Running"]=='Yes') 
            || (isset($data["Replica_IO_Running"]) && $data["Replica_IO_Running"]=='Yes') 
            || (isset($data["Replica_SQL_Running"]) && $data["Replica_SQL_Running"]=='Yes') 
        ){
            $output["Running"] = 1 ;
        }
    }
    return $output;
}

function PMA_replication_slave_get_until($link = null)
{
    $output = array();
    $data=PMA_replication_slave_get_status($link);
    if($data){
        $output["Until_Condition"]      = $data["Until_Condition"];
        $output["Until_Log_File"]       = $data["Until_Log_File"];
        $output["Until_Log_Pos"]        = $data["Until_Log_Pos"];
    }
    return $output;
}

/**
 * Get list of replicated databases on master server
 *
 * @param mixed $link mysql link
 *
 * @return array array of replicated databases
 */

function PMA_replication_master_replicated_dbs($link = null)
{
    $data = PMA_fx_show_slave_status($link);

    $do_db     = array();
    $ignore_db = array();

    if (! empty($data[0]['Binlog_Do_DB'])) {
        $do_db     = explode(',', $data[0]['Binlog_Do_DB']);
    }
    if (! empty($data[0]['Binlog_Ignore_DB'])) {
        $ignore_db = explode(',', $data[0]['Binlog_Ignore_DB']);
    }

    $tmp_alldbs = PMA_DBI_query('SHOW DATABASES;', $link);
    while ($tmp_row = PMA_DBI_fetch_row($tmp_alldbs)) {
        if (PMA_is_system_schema($tmp_row[0]))
            continue;
        if (count($do_db) == 0) {
            if (array_search($tmp_row[0], $ignore_db) !== false) {
                continue;
            }
            $dblist[] = $tmp_row[0];

        } else {
            if (array_search($tmp_row[0], $do_db) !== false) {
                $dblist[] = $tmp_row[0];
            }
        }
    } // end while

    return $link;
}
/**
 * This function provides synchronization of structure and data between two mysql servers.
 *
 * @todo improve code sharing between the function and synchronization
 *
 * @param string $db       name of database, which should be synchronized
 * @param mixed  $src_link link of source server, note: if the server is current PMA server, use null
 * @param mixed  $trg_link link of target server, note: if the server is current PMA server, use null
 * @param bool   $data     if true, then data will be copied as well
 */

function PMA_replication_synchronize_db($db, $src_link, $trg_link, $data = true)
{
    $src_db = $trg_db = $db;

    $src_tables = PMA_DBI_get_tables($src_db, $src_link);

    $trg_tables = PMA_DBI_get_tables($trg_db, $trg_link);

    /**
     * initializing arrays to save table names
     */
    $source_tables_uncommon = array();
    $target_tables_uncommon = array();
    $matching_tables = array();
    $matching_tables_num = 0;

    /**
     * Criterion for matching tables is just their names.
     * Finding the uncommon tables for the source database
     * BY comparing the matching tables with all the tables in the source database
     */
    PMA_getMatchingTables($trg_tables, $src_tables, $matching_tables, $source_tables_uncommon);

    /**
     * Finding the uncommon tables for the target database
     * BY comparing the matching tables with all the tables in the target database
     */
    PMA_getNonMatchingTargetTables($trg_tables, $matching_tables, $target_tables_uncommon);

    /**
     *
     * Comparing Data In the Matching Tables
     * It is assumed that the matching tables are structurally
     * and typely exactly the same
     */
    $fields_num = array();
    $matching_tables_fields = array();
    $matching_tables_keys   = array();
    $insert_array = array(array(array()));
    $update_array = array(array(array()));
    $delete_array = array();
    $row_count = array();
    $uncommon_tables_fields = array();
    $matching_tables_num = sizeof($matching_tables);

    for ($i = 0; $i < sizeof($matching_tables); $i++) {
        PMA_dataDiffInTables($src_db, $trg_db, $src_link, $trg_link, $matching_tables, $matching_tables_fields, $update_array, $insert_array,
            $delete_array, $fields_num, $i, $matching_tables_keys);
    }
    for ($j = 0; $j < sizeof($source_tables_uncommon); $j++) {
        PMA_dataDiffInUncommonTables($source_tables_uncommon, $src_db, $src_link, $j, $row_count);
    }

    /**
     * INTEGRATION OF STRUCTURE DIFFERENCE CODE
     *
     */
    $source_columns = array();
    $target_columns = array();
    $alter_str_array = array(array());
    $add_column_array = array(array());
    $uncommon_columns = array();
    $target_tables_keys = array();
    $source_indexes = array();
    $target_indexes = array();
    $add_indexes_array = array();
    $alter_indexes_array = array();
    $remove_indexes_array = array();
    $criteria = array('Field', 'Type', 'Null', 'Collation', 'Key', 'Default', 'Comment');

    for ($counter = 0; $counter < $matching_tables_num; $counter++) {
        PMA_structureDiffInTables($src_db, $trg_db, $src_link, $trg_link, $matching_tables, $source_columns,
            $target_columns, $alter_str_array, $add_column_array, $uncommon_columns, $criteria, $target_tables_keys, $counter);

        PMA_indexesDiffInTables($src_db, $trg_db, $src_link, $trg_link, $matching_tables, $source_indexes, $target_indexes,
            $add_indexes_array, $alter_indexes_array, $remove_indexes_array, $counter);
    }

    /**
     * Generating Create Table query for all the non-matching tables present in Source but not in Target and populating tables.
     */
    for ($q = 0; $q < sizeof($source_tables_uncommon); $q++) {
        if (isset($source_tables_uncommon[$q])) {
            PMA_createTargetTables($src_db, $trg_db, $src_link, $trg_link, $source_tables_uncommon, $q, $uncommon_tables_fields, false);
        }
        if (isset($row_count[$q]) && $data) {
            PMA_populateTargetTables($src_db, $trg_db, $src_link, $trg_link, $source_tables_uncommon, $q, $uncommon_tables_fields, false);
        }
    }
}
?>
