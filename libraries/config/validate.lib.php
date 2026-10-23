<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Various validation functions
 *
 * Validation function takes two argument: id for which it is called
 * and array of fields' values (usually values for entire formset, as defined
 * in forms.inc.php).
 * The function must always return an array with an error (or error array)
 * assigned to a form element (formset name or field path). Even if there are
 * no errors, key must be set with an empty value.
 *
 * Valdiation functions are assigned in $cfg_db['_validators'] (config.values.php).
 *
 * @package PhpMyAdmin
 */

/**
 * Returns validator list
 *
 * @return array
 */
function PMA_config_get_validators()
{
    static $validators = null;

    if ($validators === null) {
        $cf = ConfigFile::getInstance();
        $validators = $cf->getDbEntry('_validators', array());
        if (!defined('PMA_SETUP')) {
            // not in setup script: load additional validators for user preferences
            // we need oryginal config values not overwritten by user preferences, creating a new PMA_Config
            // instance is a better idea than hacking into its code
            $org_cfg = $cf->getOrgConfigObj();
            $uvs = $cf->getDbEntry('_userValidators', array());
            foreach ($uvs as $field => $uv_list) {
                $uv_list = (array)$uv_list;
                foreach ($uv_list as &$uv) {
                    if (!is_array($uv)) {
                        continue;
                    }
                    for ($i = 1; $i < count($uv); $i++) {
                        if (substr($uv[$i], 0, 6) == 'value:') {
                            $uv[$i] = PMA_array_read(substr($uv[$i], 6), $org_cfg->settings);
                        }
                    }
                }
                $validators[$field] = isset($validators[$field])
                    ? array_merge((array)$validators[$field], $uv_list)
                    : $uv_list;
            }
        }
    }
    return $validators;
}

/**
 * Runs validation $validator_id on values $values and returns error list.
 *
 * Return values:
 * o array, keys - field path or formset id, values - array of errors
 *   when $isPostSource is true values is an empty array to allow for error list
 *   cleanup in HTML documen
 * o false - when no validators match name(s) given by $validator_id
 *
 * @param string|array  $validator_id
 * @param array         $values
 * @param bool          $isPostSource  tells whether $values are directly from POST request
 * @return bool|array
 */
function PMA_config_validate($validator_id, &$values, $isPostSource)
{
    // find validators
    $validator_id = (array) $validator_id;
    $validators = PMA_config_get_validators();
    $vids = array();
    $cf = ConfigFile::getInstance();
    foreach ($validator_id as &$vid) {
        $vid = $cf->getCanonicalPath($vid);
        if (isset($validators[$vid])) {
            $vids[] = $vid;
        }
    }
    if (empty($vids)) {
        return false;
    }

    // create argument list with canonical paths and remember path mapping
    $arguments = array();
    $key_map = array();
    foreach ($values as $k => $v) {
        $k2 = $isPostSource ? str_replace('-', '/', $k) : $k;
        $k2 = strpos($k2, '/') ? $cf->getCanonicalPath($k2) : $k2;
        $key_map[$k2] = $k;
        $arguments[$k2] = $v;
    }

    // validate
    $result = array();
    foreach ($vids as $vid) {
        // call appropriate validation functions
        foreach ((array)$validators[$vid] as $validator) {
            $vdef = (array) $validator;
            $vname = array_shift($vdef);
            $args = array_merge(array($vid, &$arguments), $vdef);
            $r = call_user_func_array($vname, $args);

            // merge results
            if (is_array($r)) {
                foreach ($r as $key => $error_list) {
                    // skip empty values if $isPostSource is false
                    if (!$isPostSource && empty($error_list)) {
                        continue;
                    }
                    if (!isset($result[$key])) {
                        $result[$key] = array();
                    }
                    $result[$key] = array_merge($result[$key], (array)$error_list);
                }
            }
        }
    }

    // restore original paths
    $new_result = array();
    foreach ($result as $k => $v) {
        $k2 = isset($key_map[$k]) ? $key_map[$k] : $k;
        $new_result[$k2] = $v;
    }
    return empty($new_result) ? true : $new_result;
}

/**
 * Temporary error handler to capture regex compilation error and suppress propagation.
 *
 * 1. Replaces the legacy test_php_errormsg() mechanism, completely removing track_errors,
 *    html_errors, and ob_start() to avoid deprecation notices on PHP 7.2+/8.x.
 * 2. Returning true indicates the error was handled, which suppresses screen output
 *    and prevents bubbling up to the global PMA_Error_Handler (no Session pollution).
 *    Compatible with PHP 5.2 through PHP 8.5+.
 *
 * @param int    $errno
 * @param string $errstr
 * @param string $errfile
 * @param int    $errline
 * @return bool
 */
function PMA_regex_error_catcher($errno = 0, $errstr = '', $errfile = '', $errline = 0)
{
    $GLOBALS['PMA_temp_regex_error'] = $errstr;
    return true; // Suppress output and prevent bubbling to PMA_Error_Handler
}

/**
 * Test database connection
 *
 * @param string $extension     'drizzle', 'mysql' or 'mysqli'
 * @param string $connect_type  'tcp' or 'socket'
 * @param string $host
 * @param string $port
 * @param string $socket
 * @param string $user
 * @param string $pass
 * @param string $error_key
 * @return bool|array
 */
function test_db_connection($extension, $connect_type, $host, $port, $socket, $user, $pass = null, $error_key = 'Server')
{
    $socket = empty($socket) || $connect_type == 'tcp' ? null : $socket;
    $port = empty($port) || $connect_type == 'socket' ? null : ':' . $port;
    $error = null;

    if ($extension == 'drizzle' && !function_exists('drizzle_create')) {
        return array($error_key => __('Could not initialize Drizzle connection library'));
    } elseif ($extension == 'mysql' && !function_exists('mysql_connect')) {
        return array($error_key => __('Could not connect to MySQL server'));
    } elseif ($extension == 'mysqli' && !function_exists('mysqli_connect')) {
        return array($error_key => __('Could not connect to MySQL server'));
    } elseif ($extension != 'drizzle' && $extension != 'mysql' && $extension != 'mysqli') {
        return array($error_key => __('Unknown extension: ') . $extension);
    }

    if ($extension == 'drizzle') {
        while (1) {
            $drizzle = @drizzle_create();
            if (!$drizzle) {
                $error = __('Could not initialize Drizzle connection library');
                break;
            }
            $conn = $socket
                ? @drizzle_con_add_uds($socket, $user, $pass, null, 0)
                : @drizzle_con_add_tcp($drizzle, $host, $port, $user, $pass, null, 0);
            if (!$conn) {
                $error = __('Could not connect to Drizzle server');
                drizzle_free($drizzle);
                break;
            }
            // connection object is set up but we have to send some query to actually connect
            $res = @drizzle_query($conn, 'SELECT 1');
            if (!$res) {
                $error = __('Could not connect to Drizzle server');
            } else {
                drizzle_result_free($res);
            }
            drizzle_con_free($conn);
            drizzle_free($drizzle);
            break;
        }
    } else if ($extension == 'mysql') {
        $conn = @mysql_connect($host . $socket . $port, $user, $pass);
        if (!$conn) {
            $error = __('Could not connect to MySQL server');
        } else {
            mysql_close($conn);
        }
    } else if ($extension == 'mysqli') {
        $conn = @mysqli_connect($host, $user, $pass, null, $port, $socket);
        if (!$conn) {
            $error = __('Could not connect to MySQL server');
        } else {
            mysqli_close($conn);
        }
    }
    // Retrieve driver-specific native error message on connection failure
    if (! is_null($error)) {
        $server_error = '';
        if ($extension == 'mysqli' && function_exists('mysqli_connect_error')) {
            $server_error = mysqli_connect_error();
        } elseif ($extension == 'mysql' && function_exists('mysql_error')) {
            $server_error = mysql_error();
        } elseif ($extension == 'drizzle' && isset($conn) && function_exists('drizzle_con_error')) {
            $server_error = drizzle_con_error($conn);
        }

        if (! empty($server_error)) {
            $error .= " - $server_error";
        }
    }
    return is_null($error) ? true : array($error_key => $error);
}

/**
 * Validate server config
 *
 * @param string $path
 * @param array  $values
 * @return array
 */
function validate_server($path, $values)
{
    $result = array('Server' => '', 'Servers/1/user' => '', 'Servers/1/SignonSession' => '', 'Servers/1/SignonURL' => '');
    $error = false;
    if ($values['Servers/1/auth_type'] == 'config' && empty($values['Servers/1/user'])) {
        $result['Servers/1/user'] = __('Empty username while using config authentication method');
        $error = true;
    }
    if ($values['Servers/1/auth_type'] == 'signon' && empty($values['Servers/1/SignonSession'])) {
        $result['Servers/1/SignonSession'] = __('Empty signon session name while using signon authentication method');
        $error = true;
    }
    if ($values['Servers/1/auth_type'] == 'signon' && empty($values['Servers/1/SignonURL'])) {
        $result['Servers/1/SignonURL'] = __('Empty signon URL while using signon authentication method');
        $error = true;
    }

    if (!$error && $values['Servers/1/auth_type'] == 'config') {
        $password = $values['Servers/1/nopassword'] ? null : $values['Servers/1/password'];
        $test = test_db_connection($values['Servers/1/extension'], $values['Servers/1/connect_type'], $values['Servers/1/host'], $values['Servers/1/port'], $values['Servers/1/socket'], $values['Servers/1/user'], $password, 'Server');
        if ($test !== true) {
            $result = array_merge($result, $test);
        }
    }
    return $result;
}

/**
 * Validate pmadb config
 *
 * @param string $path
 * @param array  $values
 * @return array
 */
function validate_pmadb($path, $values)
{
    //$tables = array('Servers/1/bookmarktable', 'Servers/1/relation', 'Servers/1/table_info', 'Servers/1/table_coords', 'Servers/1/pdf_pages', 'Servers/1/column_info', 'Servers/1/history', 'Servers/1/designer_coords');
    $result = array('Server_pmadb' => '', 'Servers/1/controluser' => '', 'Servers/1/controlpass' => '');
    $error = false;

    if ($values['Servers/1/pmadb'] == '') {
        return $result;
    }

    $result = array();
    if ($values['Servers/1/controluser'] == '') {
        $result['Servers/1/controluser'] = __('Empty phpMyAdmin control user while using pmadb');
        $error = true;
    }
    if ($values['Servers/1/controlpass'] == '') {
        $result['Servers/1/controlpass'] = __('Empty phpMyAdmin control user password while using pmadb');
        $error = true;
    }
    if (!$error) {
        $test = test_db_connection($values['Servers/1/extension'], $values['Servers/1/connect_type'],
            $values['Servers/1/host'], $values['Servers/1/port'], $values['Servers/1/socket'],
            $values['Servers/1/controluser'], $values['Servers/1/controlpass'], 'Server_pmadb');
        if ($test !== true) {
            $result = array_merge($result, $test);
        }
    }
    return $result;
}


/**
 * Validates regular expression
 *
 * @param string $path
 * @param array  $values
 * @return array
 */
function validate_regex($path, $values)
{
    $result = array($path => '');

    if ($values[$path] == '') {
        return $result;
    }

    // 3. Self-contained execution: initialize temporary state, attach dedicated handler,
    // and restore immediately. The captured error is unset right after use,
    // ensuring zero global residue and no cross-function contamination.
    $GLOBALS['PMA_temp_regex_error'] = null;
    set_error_handler('PMA_regex_error_catcher');

    $matches = array();
    // in libraries/List_Database.class.php _checkHideDatabase(),
    // a '/' is used as the delimiter for hide_db
    preg_match('/' . $values[$path] . '/', '', $matches);

    restore_error_handler();

    if (! empty($GLOBALS['PMA_temp_regex_error'])) {
        $error = preg_replace('/^preg_match\(\): /', '', $GLOBALS['PMA_temp_regex_error']);
        unset($GLOBALS['PMA_temp_regex_error']);
        return array($path => $error);
    }
    unset($GLOBALS['PMA_temp_regex_error']);

    return $result;
}

/**
 * Validates TrustedProxies field
 *
 * @param string $path
 * @param array  $values
 * @return array
 */
function validate_trusted_proxies($path, $values)
{
    $result = array($path => array());

    if (empty($values[$path])) {
        return $result;
    }

    if (is_array($values[$path])) {
        // value already processed by FormDisplay::save
        $lines = array();
        foreach ($values[$path] as $ip => $v) {
            $lines[] = preg_match('/^-\d+$/', $ip)
                ? $v
                : $ip . ': ' . $v;
        }
    } else {
        // AJAX validation
        $lines = explode("\n", $values[$path]);
    }
    foreach ($lines as $line) {
        $line = trim($line);
        $matches = array();
        // we catch anything that may (or may not) be an IP
        if (!preg_match("/^(.+):(?:[ ]?)\\w+$/", $line, $matches)) {
            $result[$path][] = __('Incorrect value') . ': ' . htmlspecialchars($line);
            continue;
        }
        // now let's check whether we really have an IP address
        if (filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            && filter_var($matches[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            $ip = htmlspecialchars(trim($matches[1]));
            $result[$path][] = sprintf(__('Incorrect IP address: %s'), $ip);
            continue;
        }
    }

    return $result;
}

/**
 * Tests integer value
 *
 * @param string $path
 * @param array  $values
 * @param bool   $allow_neg       allow negative values
 * @param bool   $allow_zero      allow zero
 * @param int    $max_value       max allowed value
 * @param string $error_string    error message key: $GLOBALS["strConfig$error_lang_key"]
 * @return string  empty string if test is successful
 */
function test_number($path, $values, $allow_neg, $allow_zero, $max_value, $error_string)
{
    if ($values[$path] === '') {
        return '';
    }

    if (intval($values[$path]) != $values[$path] || (!$allow_neg && $values[$path] < 0) || (!$allow_zero && $values[$path] == 0) || $values[$path] > $max_value) {
        return $error_string;
    }

    return '';
}

/**
 * Validates port number
 *
 * @param string $path
 * @param array  $values
 * @return array
 */
function validate_port_number($path, $values)
{
    return array($path => test_number($path, $values, false, false, 65535, __('Not a valid port number')));
}

/**
 * Validates positive number
 *
 * @param string $path
 * @param array  $values
 * @return array
 */
function validate_positive_number($path, $values)
{
    return array($path => test_number($path, $values, false, false, PHP_INT_MAX, __('Not a positive number')));
}

/**
 * Validates non-negative number
 *
 * @param string $path
 * @param array  $values
 * @return array
 */
function validate_non_negative_number($path, $values)
{
    return array($path => test_number($path, $values, false, true, PHP_INT_MAX, __('Not a non-negative number')));
}

/**
 * Validates value according to given regular expression
 * Pattern and modifiers must be a valid for PCRE <b>and</b> JavaScript RegExp
 *
 * @param string $path
 * @param array  $values
 * @param string $regex
 * @return void
 */
function validate_by_regex($path, $values, $regex)
{
    $result = preg_match($regex, $values[$path]);
    return array($path => ($result ? '' : __('Incorrect value')));
}

/**
 * Validates upper bound for numeric inputs
 *
 * @param string $path
 * @param array  $values
 * @param int    $max_value
 * @return array
 */
function validate_upper_bound($path, $values, $max_value)
{
    $result = $values[$path] <= $max_value;
    return array($path => ($result ? '' : sprintf(__('Value must be equal or lower than %s'), $max_value)));
}
?>
