<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * display list of server engines and additonal information about them
 *
 * @package PhpMyAdmin
 */

/**
 * no need for variables importing
 * @ignore
 */
if (! defined('PMA_NO_VARIABLES_IMPORT')) {
    define('PMA_NO_VARIABLES_IMPORT', true);
}

/**
 * requirements
 */
require_once './libraries/common.inc.php';

/**
 * Does the common work
 */
require './libraries/server_common.inc.php';
require './libraries/StorageEngine.class.php';


/**
 * Displays the links
 */
require './libraries/server_links.inc.php';

$fsfx_tabs=array(
    'engine'=>array('name'=>'Storage Engines',
        'sql_1'=>'SHOW ENGINES',
        'sql_2'=>'SELECT * FROM `ENGINES`',
    ),
    'plugin'=>array('name'=>'Plugins',
        'sql_1'=>'SHOW PLUGINS',
        'sql_2'=>'SELECT * FROM `PLUGINS`',
    ),
    'all_plugin'=>array('name'=>'All Plugin',
        'sql_1'=>'SHOW PLUGINS SONAME',
        'sql_2'=>'SELECT * FROM `ALL_PLUGINS`',
    ),
);
// $optab + $engine/plugin/soname (operate tab, detail name)
if(isset($_REQUEST['optab']) && array_key_exists( $_REQUEST['optab'], $fsfx_tabs)){
    $optab=$_REQUEST['optab'];
}else{
    $optab='engine';    // engine for default
}
//pma 3.5.8 links in server_engine.php
$engine=isset($_REQUEST['engine']) ? $_REQUEST['engine'] : null;
$plugin=isset($_REQUEST['plugin']) ? $_REQUEST['plugin'] : null;
$soname=isset($_REQUEST['soname']) ? $_REQUEST['soname'] : null;

$dtname=$engine || $plugin || $soname;

$fsfx_buff='<ul id="topmenu2">'."\n";
foreach($fsfx_tabs as $key => $value){
    $fsfx_link='./server_engines.php'. PMA_generate_common_url(array('optab' => $key));
    if($key==$optab){
         $fsfx_act1=' class="active"';
         $fsfx_act2=' class="tabactive"';
    }else{
         $fsfx_act1='';
         $fsfx_act2=' class="tab"';
    }

    $this_url_params = array_merge(
        $url_params,
        array(
            'db'        => 'information_schema',
            'sql_query' => $value['sql_2'],
            'reload'    => 0,
            'display_text' => 'F',
        )
    );
    $fsfx_tabs[$key]['alink']='sql.php' .PMA_generate_common_url($this_url_params);

    $fsfx_buff .= '  <li '.$fsfx_act1.'><a '.$fsfx_act2.' href="'.$fsfx_link.'">' . __($value['name']) . '</a></li>'."\n";
}
$fsfx_buff.='</ul>' . "\n";

echo $fsfx_buff;
unset($fsfx_buff,$fsfx_link,$fsfx_act1,$fsfx_act2);


//install/uninstall plugin ...
if(isset($_REQUEST['action'])){
    if($_REQUEST['action']=='install' && isset($_REQUEST['plugin_name']) && isset($_REQUEST['library'])){
        $act_plugin=$_REQUEST['plugin_name'];
        $act_lib=$_REQUEST['library'];
        $sql="INSTALL PLUGIN $act_plugin SONAME '$act_lib';";
        PMA_DBI_query($sql);
    }elseif($_REQUEST['action']=='uninstall' && isset($_REQUEST['plugin_name'])){
        $act_plugin=$_REQUEST['plugin_name'];
        $sql="UNINSTALL PLUGIN $act_plugin ;";
        PMA_DBI_query($sql);
    }
}


/**
 * Did the user request information about a certain storage engine?
 */
if ( $optab=='engine' && !$dtname ) {

    /**
     * Displays the sub-page heading
     */
    echo '<h2>' . "\n"
       . ($GLOBALS['cfg']['MainPageIconic'] ? PMA_getImage('b_engine.png') : '')
       . "\n" . __('Storage Engines') . "\n"
       . (isset($fsfx_tabs['engine']['alink']) ? '<a href="'.$fsfx_tabs[$key]['alink'].'" title="view full table of information_schema">raw</a>'."\n" : '')
       . '</h2>' . "\n";


    /**
     * Displays the table header
     */
    echo '<table class="noclick">' . "\n"
       . '<thead>' . "\n"
       . '<tr><th>' . __('Storage Engine') . '</th>' . "\n"
       . '    <th>' . __('Description') . '</th>' . "\n"
       . '</tr>' . "\n"
       . '</thead>' . "\n"
       . '<tbody>' . "\n";


    /**
     * Listing the storage engines
     */
    $odd_row = true;
    foreach (PMA_StorageEngine::getStorageEngines() as $engine => $details) {
        echo '<tr class="'
           . ($odd_row ? 'odd' : 'even')
           . ($details['Support'] == 'NO' || $details['Support'] == 'DISABLED'
                ? ' disabled'
                : '')
           . '">' . "\n"
           . '    <td><a href="./server_engines.php'
           . PMA_generate_common_url(array('engine' => $engine)) . '">' . "\n"
           . '            ' . htmlspecialchars($details['Engine']) . "\n"
           . '        </a></td>' . "\n"
           . '    <td>' . htmlspecialchars($details['Comment']) . '</td>' . "\n"
           . '</tr>' . "\n";
        $odd_row = !$odd_row;
    }

    $PMA_Config = $GLOBALS['PMA_Config'];
    if ($PMA_Config->get('BLOBSTREAMING_PLUGINS_EXIST')) {
        // Special case for PBMS daemon which is not listed as an engine
        echo '<tr class="'
            . ($odd_row ? 'odd' : 'even')
            .  '">' . "\n"
            . '    <td><a href="./server_engines.php'
            . PMA_generate_common_url(array('engine' => "PBMS")) . '">' . "\n"
            . '            '  . "PBMS\n"
            . '        </a></td>' . "\n"
            . '    <td>' . htmlspecialchars("PrimeBase MediaStream (PBMS) daemon") . '</td>' . "\n"
            . '</tr>' . "\n";
    }

   unset($odd_row, $engine, $details);
    echo '</tbody>' . "\n"
       . '</table>' . "\n";

}elseif($optab=='engine' && isset($_REQUEST['engine'])) {

    /**
     * Displays details about a given Storage Engine
     */

    $engine_plugin = PMA_StorageEngine::getEngine($_REQUEST['engine']);
    echo '<h2>' . "\n"
       . ($GLOBALS['cfg']['MainPageIconic'] ? PMA_getImage('b_engine.png') : '')
       . '    ' . htmlspecialchars($engine_plugin->getTitle()) . "\n"
       . '    ' . PMA_showMySQLDocu('', $engine_plugin->getMysqlHelpPage()) . "\n"
       . '</h2>' . "\n\n";
    echo '<p>' . "\n"
       . '    <em>' . "\n"
       . '        ' . htmlspecialchars($engine_plugin->getComment()) . "\n"
       . '    </em>' . "\n"
       . '</p>' . "\n\n";
    $infoPages = $engine_plugin->getInfoPages();
    if (!empty($infoPages) && is_array($infoPages)) {
        echo '<p>' . "\n"
           . '    <strong>[</strong>' . "\n";
        if (empty($_REQUEST['page'])) {
            echo '    <strong>' . __('Variables') . '</strong>' . "\n";
        } else {
            echo '    <a href="./server_engines.php'
                . PMA_generate_common_url(array('engine' => $_REQUEST['engine'])) . '">'
                . __('Variables') . '</a>' . "\n";
        }
        foreach ($infoPages as $current => $label) {
            echo '    <strong>|</strong>' . "\n";
            if (isset($_REQUEST['page']) && $_REQUEST['page'] == $current) {
                echo '    <strong>' . $label . '</strong>' . "\n";
            } else {
                echo '    <a href="./server_engines.php'
                    . PMA_generate_common_url(
                        array('engine' => $_REQUEST['engine'], 'page' => $current))
                    . '">' . htmlspecialchars($label) . '</a>' . "\n";
            }
        }
        unset($current, $label);
        echo '    <strong>]</strong>' . "\n"
           . '</p>' . "\n\n";
    }
    unset($infoPages, $page_output);
    if (!empty($_REQUEST['page'])) {
        $page_output = $engine_plugin->getPage($_REQUEST['page']);
    }
    if (!empty($page_output)) {
        echo $page_output;
    } else {
        echo '<p> ' . $engine_plugin->getSupportInformationMessage() . "\n"
           . '</p>' . "\n"
           . $engine_plugin->getHtmlVariables();
    }

}elseif( ($optab=='plugin' || $optab=='all_plugin') && !$dtname ){
    // display plugin details, infact all columns in the SHOW PLUGINS
    $all_plugin=($optab=='all_plugin') ? true : false;
    $plugin_name=$dtname;
    //$plugin_details=PMA_StorageEngine::getPlugins($plugin_name=$plugin_name,$all_plugin=$all_plugin);
    // --------- plugins list
    echo '<h2>' . "\n"
       . ($GLOBALS['cfg']['MainPageIconic'] ? PMA_getImage('b_plugin.png') : '')
       . "\n" . __('Plugins') . "\n"
       . (isset($fsfx_tabs['engine']['alink']) ? '<a href="'.$fsfx_tabs[$key]['alink'].'" title="view full table of information_schema">raw</a>'."\n" : '')
       . '</h2>' . "\n";

    $tbheader= '<table class="noclick">' . "\n"
       . '<thead>' . "\n"
       . '<tr><th>' . __('Name') . '</th>' . "\n"
       . '    <th>' . __('Status') . '</th>' . "\n"
       . '    <th>' . __('Type') . '</th>' . "\n"
       . '    <th>' . __('Library') . '</th>' . "\n"
       . '    <th>' . __('License') . '</th>' . "\n"
       . '    <th>' . __('Action') . '</th>' . "\n"
       . '</tr>' . "\n"
       . '</thead>' . "\n"
       . '<tbody>' . "\n";
    $tbfooter= '</tbody>' . "\n"  . '</table>' . "\n" . '</div>' . "\n";
    $odd_row = true;
    $lasttype='';
    foreach (PMA_StorageEngine::getPlugins($plugin_name=$plugin_name,$all_plugin=$all_plugin) as $plugin => $details) {
        if($lasttype!=$details['PLUGIN_TYPE']){
            if($lasttype!=''){
                echo $tbfooter;
                echo '</div>'."\n";
            }
            echo '<div class="group"><h2>'.$details['PLUGIN_TYPE'].'</h2>'."\n";
            echo $tbheader;
        }
        $fsfx_plg_act='';
        if($details['PLUGIN_LIBRARY']){
            if($details['PLUGIN_STATUS']=='ACTIVE'){
                $fsfx_plg_act.='<a href="./server_engines.php'.PMA_generate_common_url(
                    array('optab'=>$optab,'action' => 'uninstall'
                        ,'plugin_name'=> $details['PLUGIN_NAME'])
                ).'">uninstall</a>';
            }elseif($details['PLUGIN_STATUS']=='NOT INSTALLED'){
                $fsfx_plg_act.='<a href="./server_engines.php'.PMA_generate_common_url(
                    array('optab'=>$optab,'action' => 'install'
                        ,'plugin_name'=> $details['PLUGIN_NAME'],'library'=>$details['PLUGIN_LIBRARY'])
                ).'">install</a>';
            }
        }
        echo '<tr class="'
           . ($odd_row ? 'odd' : 'even')
           . ($details['PLUGIN_STATUS'] == 'DISABLED'
                ? ' disabled'
                : '')
           . '">' . "\n"
           . '    <td><a href="./server_engines.php'
           . PMA_generate_common_url(array('optab'=>$optab,'plugin' => $details['PLUGIN_NAME'])) . '">' . "\n"
           . '            ' . htmlspecialchars($details['PLUGIN_NAME']) . "\n"
           . '        </a></td>' . "\n"
           . '    <td>' . htmlspecialchars((string)$details['PLUGIN_STATUS']) . '</td>' . "\n"
           . '    <td>' . htmlspecialchars((string)$details['PLUGIN_TYPE']) . '</td>' . "\n"
           . '    <td>' . htmlspecialchars((string)$details['PLUGIN_LIBRARY']) . '</td>' . "\n"
           . '    <td>' . htmlspecialchars((string)$details['PLUGIN_LICENSE']) . '</td>' . "\n"
           . '    <td> ' . $fsfx_plg_act . '</td>' . "\n"
           . '</tr>' . "\n";
        $odd_row = !$odd_row;
        $lasttype=$details['PLUGIN_TYPE'];
    }
   unset($odd_row, $plugin, $details);
   echo $tbfooter;
    // --------- plugins list end

}elseif( ($optab=='plugin' || $optab=='all_plugin') && $dtname ){
    // display plugin details, infact all columns in the SHOW PLUGINS
    $all_plugin=($optab=='all_plugin') ? true : false;
    $plugin_details=PMA_StorageEngine::getPlugins($plugin_name=$plugin,$all_plugin=$all_plugin);


    echo '<h2>' . "\n"
       . ($GLOBALS['cfg']['MainPageIconic'] ? PMA_getImage('b_plugin.png') : '')
       . "\n" . __('Plugins') . "\n"
       . (isset($fsfx_tabs['engine']['alink']) ? '<a href="'.$fsfx_tabs[$key]['alink'].'" title="view full table of information_schema">raw</a>'."\n" : '')
       . '</h2>' . "\n";
    echo '<div class="group"><h2>'.$plugin.'</h2>'."\n";

    $tbheader= '<table class="noclick">' . "\n"
       . '<thead>' . "\n"
       . '<tr><th>' . __('Column') . '</th>' . "\n"
       . '    <th>' . __('Value') . '</th>' . "\n"
       . '</tr>' . "\n"
       . '</thead>' . "\n"
       . '<tbody>' . "\n";
    $tbfooter= '</tbody>' . "\n"  . '</table>' . "\n" . '</div>' . "\n";
    $odd_row = true;
    echo $tbheader;
    if(!$plugin_details){
        //not exists
            echo '<tr>' . "\n"
               . '    <td>Not Found</td>' . "\n"
               . '    <td>-</td>' . "\n"
               . '</tr>' . "\n";
    }else{
        //output
        foreach ($plugin_details as $key => $value) {
            echo '<tr class="'. ($odd_row ? 'odd' : 'even') . '">' . "\n"
               . '    <td>' . htmlspecialchars((string)$key) . '</td>' . "\n"
               . '    <td>' . htmlspecialchars((string)$value) . '</td>' . "\n"
               . '</tr>' . "\n";
            $odd_row = !$odd_row;
        }
    }
    unset($odd_row, $plugin_details, $key,$value);
    echo $tbfooter;

}

/**
 * Sends the footer
 */
require './libraries/footer.inc.php';

?>
