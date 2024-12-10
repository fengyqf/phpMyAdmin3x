<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * display the binary logs and the content of the selected
 *
 * @package PhpMyAdmin
 */

/**
 *
 */
require_once './libraries/common.inc.php';

/**
 * Does the common work, provides $binary_logs
 */
require_once './libraries/server_common.inc.php';

/**
 * Displays the links
 */
require_once './libraries/server_links.inc.php';



// -----------------------------------------------------------------------------
$fx_curr_log=(isset($_REQUEST['log'])) ? trim($_REQUEST['log']) : null;
$fxop_binlog=isset($_REQUEST['fxop_binlog']) ? $_REQUEST['fxop_binlog'] : '';

if(isset($_REQUEST['bl_type']) && $_REQUEST['bl_type']=='RELAYLOG'){
    $bl_type='RELAYLOG';
}else{
    $bl_type='BINLOG';
}

$fx_reload_binlog=$fx_redirect_binlog=0;
$fx_sql='';
if ($fxop_binlog=='do_flush') {
    if (PMA_MYSQL_INT_VERSION < 50503) {
        $fx_sql="FLUSH LOGS;";
    }else{
        $fx_sql="FLUSH BINARY LOGS;";
    }
    PMA_DBI_query($fx_sql);
    sleep(1);
    $fx_reload_binlog=1;
    $fx_redirect_binlog=1;
}elseif ($fxop_binlog=='do_purge') {
    $fx_sql="PURGE BINARY LOGS TO '".PMA_sqlAddSlashes($_REQUEST['log'])."';";
    PMA_DBI_query($fx_sql);
    sleep(1);
    $fx_reload_binlog=1;
}


if($fx_reload_binlog){
    // reload $binary_logs, ref libraries\server_common.inc.php:53
    $binary_logs = fx_get_binary_logs_list();
}

//move $fx_curr_log to the last
if($fx_redirect_binlog){
    foreach($binary_logs as $fx_value){
        $fx_curr_log=$fx_value['Log_name'];
    }
    unset($fx_value);
}

// -----------------------------------------------------------------------------






$url_params = array();

/**
 * Need to find the real end of rows?
 */
$pos = isset($_REQUEST['pos']) ? (int) $_REQUEST['pos'] : 0;
$fromPos = isset($_REQUEST['fromPos']) ? (int) $_REQUEST['fromPos'] : 0;

if (! $fx_curr_log || ! array_key_exists($fx_curr_log, $binary_logs)) {
    $_REQUEST['log'] = '';
} else {
    $url_params['log'] = $fx_curr_log;
}

$sql_query = 'SHOW BINLOG EVENTS';
if($bl_type=='RELAYLOG'){
    $sql_query = 'SHOW RELAYLOG EVENTS';
    $url_params['bl_type']=$bl_type;
}
if ($fx_curr_log) {
    $sql_query .= ' IN \'' . PMA_sqlAddSlashes($fx_curr_log) . '\'';
}
if ($fromPos > 0) {
    $sql_query .= ' FROM ' . $fromPos . '';
    $url_params['fromPos'] = $fromPos;
}
if ($GLOBALS['cfg']['MaxRows'] !== 'all') {
    $sql_query .= ' LIMIT ' . $pos . ', ' . (int) $GLOBALS['cfg']['MaxRows'];
}

/**
 * Sends the query
 */
$result = PMA_DBI_query($sql_query);

/**
 * prepare some vars for displaying the result table
 */
// Gets the list of fields properties
if (isset($result) && $result) {
    $num_rows = PMA_DBI_num_rows($result);
} else {
    $num_rows = 0;
}

if (empty($_REQUEST['dontlimitchars'])) {
    $dontlimitchars = false;
} else {
    $dontlimitchars = true;
    $url_params['dontlimitchars'] = 1;
}

/**
 * Displays the sub-page heading
 */
echo '<h2>' . "\n"
   . ($GLOBALS['cfg']['MainPageIconic'] ? PMA_getImage('s_tbl.png') : '')
   . '    ' . __('Binary log') . "\n"
   . '</h2>' . "\n";

/**
 * Display log selector.
 */
if($bl_type=='RELAYLOG'){
    echo '<form action="server_binlog.php" method="get">';
    echo PMA_generate_common_hidden_inputs($url_params);
    echo '<fieldset><legend>input RelayLog filename to show</legend>';
    echo '<input type="text" name="log" value="'.$fx_curr_log.'" style="width: 260px" >';
    echo '<input type="text" name="fromPos" id="fromPos" value="'.$fromPos.'" style="width: 60px" title="'.__('Position').'" />';
    echo '<input type="submit" value="' . __('Go') . '" />';
    echo '&nbsp;<span>';
    $this_url_params = $url_params;
    $this_url_params['bl_type']='';
    echo ' &nbsp; | &nbsp;<span><a href="./server_binlog.php' . PMA_generate_common_url($this_url_params) . '">Binlog</a>';
    echo '</span>';
    echo '</fieldset>';
    echo '</form>';
}elseif (count($binary_logs) >= 1) {
    echo '<form action="server_binlog.php" method="get">';
    // keep status 'try_purge' in selector
    if($fxop_binlog=='try_purge'){
        $url_params['fxop_binlog']='try_purge';
    }
    echo PMA_generate_common_hidden_inputs($url_params);
    echo '<fieldset><legend>';
    echo __('Select binary log to view');
    echo '</legend><select name="log" id="sel_log">';
    $full_size = 0;
    $fx_next_log=$fx_last_log='';
    $fx_lognames=array();
    foreach ($binary_logs as $each_log) {
        echo '<option value="' . $each_log['Log_name'] . '"';
        if(!$fx_curr_log){
            $fx_curr_log=$each_log['Log_name'];
        }
        if($fx_next_log=='Yes'){
            $fx_next_log=$each_log['Log_name'];
        }
        if ($each_log['Log_name'] == $fx_curr_log) {
            echo ' selected="selected"';
            $fx_next_log='Yes';
        }
        echo '>' . $each_log['Log_name'];
        if (isset($each_log['File_size'])) {
            $full_size += $each_log['File_size'];
            echo ' (' . implode(' ', PMA_formatByteDown($each_log['File_size'], 3, 2)) . ')';
        }
        echo '</option>';
        $fx_last_log=$each_log['Log_name'];
        $fx_lognames[]=$each_log['Log_name'];
    }
    if($fx_next_log=='Yes'){
        $fx_next_log='';
    }
    echo '</select> ';
    echo '<input type="text" name="fromPos" id="fromPos" value="'.$fromPos.'" style="width: 60px" title="'.__('Position').'" />';
    echo '<input type="submit" value="' . __('Go') . '" />';
    echo '&nbsp;<span>';
    echo count($binary_logs) . ' ' . __('Files') . ', ';
    if ($full_size > 0) {
        echo implode(' ', PMA_formatByteDown($full_size));
    }
    echo '</span>';
    $this_url_params = $url_params;
    //$this_url_params['log']=$fx_last_log;
    if($fx_next_log){
        $this_url_params['log']=$fx_next_log;
        echo ' &nbsp;&nbsp;<span><a href="./server_binlog.php' . PMA_generate_common_url($this_url_params) . '">Next</a></span>';
    }else{
        echo ' &nbsp;&nbsp;<span style="color:#999999;">Next</a></span>';
    }
    if($fxop_binlog=='try_purge'){
        $this_url_params['fxop_binlog']='do_purge';
        $this_url_params['log']=$fx_curr_log;
        echo ' &nbsp;&nbsp;<span><a href="./server_binlog.php' . PMA_generate_common_url($this_url_params) . '">PURGE BINARY LOGS TO '.$fx_curr_log.'</a></span>';
    }elseif($fx_curr_log){
        $this_url_params['log']=$fx_curr_log;
        $this_url_params['fxop_binlog']='try_purge';
        echo ' &nbsp;&nbsp;<span><a href="./server_binlog.php' . PMA_generate_common_url($this_url_params) . '" title="purge binary log">Purge...</a></span>';
        $this_url_params['fxop_binlog']='do_flush';
        echo ' &nbsp;&nbsp;<span><a href="./server_binlog.php' . PMA_generate_common_url($this_url_params) . '" title="flush binary log">Flush</a></span>';
    }
    $this_url_params = $url_params;
    $this_url_params['log']=null;
    $this_url_params['bl_type']='RELAYLOG';
    echo ' &nbsp; | &nbsp;<span><a href="./server_binlog.php' . PMA_generate_common_url($this_url_params) . '">RelayLog</a></span>';

    echo '</fieldset>';
    echo '</form>';
}

if(isset($fx_sql) && $fx_sql){
    $sql_query=$fx_sql."\r\n".$sql_query;
}
PMA_showMessage(PMA_Message::success(),$sql_query);

/**
 * Displays the page
 */
?>
<script type="text/javascript">
$(document).ready(function() {
    $("#sel_log").change(function() {
        $("#fromPos").val("0");
    })
});
</script>
<table border="0" cellpadding="2" cellspacing="1">
<thead>
<tr>
    <td colspan="6" align="center">
<?php
// we do not now how much rows are in the binlog
// so we can just force 'NEXT' button
if ($pos > 0) {
    $this_url_params = $url_params;
    if ($pos > $GLOBALS['cfg']['MaxRows']) {
        $this_url_params['pos'] = $pos - $GLOBALS['cfg']['MaxRows'];
    }

    echo '<a href="./server_binlog.php' . PMA_generate_common_url($this_url_params) . '"';
    if ($GLOBALS['cfg']['NavigationBarIconic']) {
        echo ' title="' . _pgettext('Previous page', 'Previous') . '">';
    } else {
        echo '>' . _pgettext('Previous page', 'Previous');
    } // end if... else...
    echo ' &lt; </a> - ';
}

$this_url_params = $url_params;
if ($pos > 0) {
    $this_url_params['pos'] = $pos;
}
if ($dontlimitchars) {
    unset($this_url_params['dontlimitchars']);
    ?>
        <a href="./server_binlog.php<?php echo PMA_generate_common_url($this_url_params); ?>"
            title="<?php __('Truncate Shown Queries'); ?>">
                <img src="<?php echo $pmaThemeImage; ?>s_partialtext.png"
                    alt="<?php echo __('Truncate Shown Queries'); ?>" /></a>
    <?php
} else {
    $this_url_params['dontlimitchars'] = 1;
    ?>
        <a href="./server_binlog.php<?php echo PMA_generate_common_url($this_url_params); ?>"
            title="<?php __('Show Full Queries'); ?>">
                <img src="<?php echo $pmaThemeImage; ?>s_fulltext.png"
                    alt="<?php echo __('Show Full Queries'); ?>" /></a>
    <?php
}
// we do not now how much rows are in the binlog
// so we can just force 'NEXT' button
if ($num_rows >= $GLOBALS['cfg']['MaxRows']) {
    $this_url_params = $url_params;
    $this_url_params['pos'] = $pos + $GLOBALS['cfg']['MaxRows'];
    echo ' - <a href="./server_binlog.php' . PMA_generate_common_url($this_url_params) . '"';
    if ($GLOBALS['cfg']['NavigationBarIconic']) {
        echo ' title="' . _pgettext('Next page', 'Next') . '">';
    } else {
        echo '>' . _pgettext('Next page', 'Next');
    } // end if... else...
    echo ' &gt; </a>';
}
?>
    </td>
</tr>
<tr>
    <th><?php echo __('Log name'); ?></th>
    <th><?php echo __('Position'); ?></th>
    <th><?php echo __('Event type'); ?></th>
    <th><?php echo __('Server ID'); ?></th>
    <th><?php echo __('Original position'); ?></th>
    <th><?php echo __('Information'); ?></th>
</tr>
</thead>
<tbody>
<?php
$odd_row = true;
while ($value = PMA_DBI_fetch_assoc($result)) {
    if (! $dontlimitchars && PMA_strlen($value['Info']) > $GLOBALS['cfg']['LimitChars']) {
        $value['Info'] = PMA_substr($value['Info'], 0, $GLOBALS['cfg']['LimitChars']) . '...';
    }
    ?>
<tr class="noclick <?php echo $odd_row ? 'odd' : 'even'; ?>">
    <td><?php echo $value['Log_name']; ?></td>
    <td align="right">&nbsp;<?php echo $value['Pos']; ?>&nbsp;</td>
    <td>&nbsp;<?php echo $value['Event_type']; ?>&nbsp;</td>
    <td align="right">&nbsp;<?php echo $value['Server_id']; ?>&nbsp;</td>
    <td align="right">&nbsp;<?php echo isset($value['Orig_log_pos']) ? $value['Orig_log_pos'] : $value['End_log_pos']; ?>&nbsp;</td>
    <td>&nbsp;<?php echo htmlspecialchars($value['Info']); ?>&nbsp;</td>
</tr>
    <?php
    $odd_row = !$odd_row;
}
?>
</tbody>
</table>
<?php


/**
 * Sends the footer
 */
require './libraries/footer.inc.php';

?>
