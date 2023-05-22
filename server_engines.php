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
/**
 * Did the user request information about a certain storage engine?
 */
if (( empty($_REQUEST['engine']) || ! PMA_StorageEngine::isValid($_REQUEST['engine']) )
        && !isset($_GET['plugin']) ) {

    /**
     * Displays the sub-page heading
     */
    echo '<h2>' . "\n"
       . ($GLOBALS['cfg']['MainPageIconic'] ? PMA_getImage('b_engine.png') : '')
       . "\n" . __('Storage Engines') . "\n"
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


    // --------- plugins list
    echo '<h2>' . "\n"
       . ($GLOBALS['cfg']['MainPageIconic'] ? PMA_getImage('b_plugin.png') : '')
       . "\n" . __('Plugins') . "\n"
       . '</h2>' . "\n";

    $tbheader= '<table class="noclick">' . "\n"
       . '<thead>' . "\n"
       . '<tr><th>' . __('Plugins') . '</th>' . "\n"
       . '    <th>' . __('Description') . '</th>' . "\n"
       . '    <th>' . __('Version') . '</th>' . "\n"
       . '    <th>' . __('Author') . '</th>' . "\n"
       . '    <th>' . __('License') . '</th>' . "\n"
       . '</tr>' . "\n"
       . '</thead>' . "\n"
       . '<tbody>' . "\n";
    $tbfooter= '</tbody>' . "\n"  . '</table>' . "\n" . '</div>' . "\n";
    $odd_row = true;
    $lasttype='';
    foreach (PMA_StorageEngine::getPlugins() as $plugin => $details) {
        if($lasttype!=$details['PLUGIN_TYPE']){
            if($lasttype!=''){
                echo $tbfooter;
                echo '</div>'."\n";
            }
            echo '<div class="group"><h2>'.$details['PLUGIN_TYPE'].'</h2>'."\n";
            echo $tbheader;
        }
        echo '<tr class="'
           . ($odd_row ? 'odd' : 'even')
           . ($details['PLUGIN_STATUS'] == 'DISABLED'
                ? ' disabled'
                : '')
           . '">' . "\n"
           . '    <td><a href="./server_engines.php'
           . PMA_generate_common_url(array('plugin' => $details['PLUGIN_NAME'])) . '">' . "\n"
           . '            ' . htmlspecialchars($details['PLUGIN_NAME']) . "\n"
           . '        </a></td>' . "\n"
           . '    <td>' . htmlspecialchars($details['PLUGIN_DESCRIPTION']) . '</td>' . "\n"
           . '    <td>' . htmlspecialchars($details['PLUGIN_VERSION']) . '</td>' . "\n"
           . '    <td>' . htmlspecialchars($details['PLUGIN_AUTHOR']) . '</td>' . "\n"
           . '    <td>' . htmlspecialchars($details['PLUGIN_LICENSE']) . '</td>' . "\n"
           . '</tr>' . "\n";
        $odd_row = !$odd_row;
        $lasttype=$details['PLUGIN_TYPE'];
    }
   unset($odd_row, $plugin, $details);
   echo $tbfooter;
    // --------- plugins list end
}elseif(isset($_GET['plugin'])){
    // display plugin details, infact all columns in the SHOW PLUGINS
    $plugin_name=$_REQUEST['plugin'];
    $plugin_details=PMA_StorageEngine::getPlugins($plugin_name);
    echo '<h2>' . "\n"
       . ($GLOBALS['cfg']['MainPageIconic'] ? PMA_getImage('b_plugin.png') : '')
       . "\n" . __('Plugins') . "\n"
       . '</h2>' . "\n";
    echo '<div class="group"><h2>'.$plugin_name.'</h2>'."\n";

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

} else {

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
}

/**
 * Sends the footer
 */
require './libraries/footer.inc.php';

?>
