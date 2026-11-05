<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 *
 * @package PhpMyAdmin
 */
if (! defined('PHPMYADMIN')) {
    exit;
}

/*
for window functions in sql, such as row_number()
*/
function fsfx__is_ignore_sql_parse($sql_query){
    //$sql_query=strtolower($sql_query);
    //window function ...() over()
    if( preg_match('/\bover\s*\(/i', $sql_query) ){
        return true;
    }
    // Ignore CTE for table extraction (too complex for legacy logic)
    if (preg_match('/^\s*WITH\b/i', $sql_query)) {
        return true;
    }
    return false;
}

/**
 * Detects the main query type (SELECT, UPDATE, DELETE, INSERT) of a CTE (WITH ...) statement
 * by iterating through lexer tokens and tracking parenthesis depth.
 */
function fsfx__get_cte_main_query_start($parsed_sql)
{
    if (!isset($parsed_sql['len']) || $parsed_sql['len'] == 0) {
        return false;
    }

    $is_cte = false;
    $seen_as = false;
    $in_cte_body = false;
    $cte_body_finished = false;
    $depth = 0;

    for ($i = 0; $i < $parsed_sql['len']; $i++) {
        $type = $parsed_sql[$i]['type'];
        if (strpos($type, 'comment') !== false || strpos($type, 'white_') === 0) {
            continue;
        }

        if (!$is_cte) {
            if (strtoupper((string)$parsed_sql[$i]['data']) !== 'WITH') {
                return false;
            }
            $is_cte = true;
            continue;
        }

        if (!$in_cte_body && !$cte_body_finished
            && strtoupper((string)$parsed_sql[$i]['data']) === 'AS'
        ) {
            $seen_as = true;
            continue;
        }

        if ($type === 'punct_bracket_open_round') {
            $depth++;
            if ($seen_as && !$in_cte_body && !$cte_body_finished) {
                $in_cte_body = true;
            }
        } elseif ($type === 'punct_bracket_close_round') {
            $depth--;
            if ($in_cte_body && $depth === 0) {
                $in_cte_body = false;
                $cte_body_finished = true;
            }
        } elseif ($cte_body_finished && $depth === 0
            && $type === 'punct_listsep'
        ) {
            $seen_as = false;
            $cte_body_finished = false;
        } elseif ($cte_body_finished && !$in_cte_body
            && ($type === 'alpha_reservedWord' || $type === 'alpha_identifier')
        ) {
            $word = strtoupper((string)$parsed_sql[$i]['data']);
            if (in_array($word, array('SELECT', 'UPDATE', 'DELETE', 'INSERT'))) {
                return $i;
            }
        }
    }
    return false;
}

/**
 * Detects the main query type (SELECT, UPDATE, DELETE, INSERT) of a CTE.
 *
 * @param array $parsed_sql
 * @return string|bool
 */
function fsfx__detect_cte_main_query_type($parsed_sql)
{
    $main_query_start = fsfx__get_cte_main_query_start($parsed_sql);
    if ($main_query_start === false) {
        return false;
    }
    return strtoupper((string)$parsed_sql[$main_query_start]['data']);
}

/**
 * Detect aggregate functions after the CTE definitions, ignoring their use
 * inside the CTE bodies themselves.
 *
 * @param array $parsed_sql
 * @return bool
 */
function fsfx__cte_main_query_has_aggregate($parsed_sql)
{
    $main_query_start = fsfx__get_cte_main_query_start($parsed_sql);
    if ($main_query_start === false) {
        return false;
    }

    $aggregate_functions = array('SUM', 'AVG', 'STD', 'STDDEV', 'MIN', 'MAX', 'BIT_OR', 'BIT_AND');
    for ($i = $main_query_start; $i < $parsed_sql['len']; $i++) {
        $word = strtoupper((string)$parsed_sql[$i]['data']);
        if (in_array($word, $aggregate_functions)) {
            for ($j = $i + 1; $j < $parsed_sql['len']; $j++) {
                $type = $parsed_sql[$j]['type'];
                if (strpos($type, 'comment') !== false || strpos($type, 'white_') === 0) {
                    continue;
                }
                if ($type === 'punct_bracket_open_round') {
                    return true;
                }
                break;
            }
        }
    }
    return false;
}

/**
 * Remove a final delimiter and trailing comments from a CTE query.
 *
 * @param string  $sql_query
 * @param string  $trailing_comments
 * @param boolean $has_semicolon
 * @return string
 */
function fsfx__get_cte_query_body($sql_query, &$trailing_comments, &$has_semicolon)
{
    $query = rtrim($sql_query);
    $trailing_comments = '';
    $parsed_query = PMA_SQP_parse($query);
    if (isset($parsed_query['len'])) {
        for ($i = $parsed_query['len'] - 1; $i >= 0; $i--) {
            if (strpos($parsed_query[$i]['type'], 'comment') === false) {
                break;
            }
            $comment = $parsed_query[$i]['data'];
            if (substr($query, -strlen($comment)) !== $comment) {
                break;
            }
            $query = rtrim(substr($query, 0, -strlen($comment)));
            $trailing_comments = $comment . $trailing_comments;
        }
    }

    $has_semicolon = false;
    if (substr($query, -1) === ';') {
        $query = rtrim(substr($query, 0, -1));
        $has_semicolon = true;
    }

    return $query;
}

/**
 * Append a LIMIT clause to a CTE query without relying on the legacy analyzer
 * to split the query into sections.
 *
 * @param string $sql_query
 * @param string $limit_clause
 * @return string
 */
function fsfx__append_cte_limit($sql_query, $limit_clause)
{
    $query = fsfx__get_cte_query_body($sql_query, $trailing_comments, $has_semicolon);
    $semicolon = $has_semicolon ? ';' : '';

    // A LIMIT clause belongs before the optional locking clause.
    $lock_pattern = '/\s+(FOR\s+(?:UPDATE|SHARE)(?:\s+OF\s+.+?)?'
        . '(?:\s+(?:NOWAIT|SKIP\s+LOCKED))?'
        . '|LOCK\s+IN\s+SHARE\s+MODE)\s*$/i';
    if (preg_match($lock_pattern, $query, $matches, PREG_OFFSET_CAPTURE)) {
        $lock_position = $matches[0][1];
        return rtrim(substr($query, 0, $lock_position)) . $limit_clause
            . substr($query, $lock_position) . $semicolon . $trailing_comments;
    }

    return $query . $limit_clause . $semicolon . $trailing_comments;
}


/**
 *
 */
$GLOBALS['unparsed_sql'] = $sql_query;
$parsed_sql = PMA_SQP_parse($sql_query);
$analyzed_sql = PMA_SQP_analyze($parsed_sql);

// for bug 780516: now that we use case insensitive preg_match
// or flags from the analyser, do not put back the reformatted query
// into $sql_query, to make this kind of query work without
// capitalizing keywords:
//
// CREATE TABLE SG_Persons (
//  id int(10) unsigned NOT NULL auto_increment,
//  first varchar(64) NOT NULL default '',
//  PRIMARY KEY  (`id`)
// )

// check for a real SELECT ... FROM
$is_select = isset($analyzed_sql[0]['queryflags']['select_from']);

// ========== CTE Pagination Fix ==========
// If the old parser didn't mark it as a SELECT, we verify if it's a WITH ... SELECT
$is_cte_select = false;
$is_cte_main_query_aggregate = false;
if (!$is_select && isset($parsed_sql)) {
    $cte_type = fsfx__detect_cte_main_query_type($parsed_sql);
    if ($cte_type === 'SELECT') {
        $is_select = true;
        $is_cte_select = true;
        $is_cte_main_query_aggregate = fsfx__cte_main_query_has_aggregate($parsed_sql);
    }
}
// ========================================

// If the query is a Select, extract the db and table names and modify
// $db and $table, to have correct page headers, links and left frame.
// db and table name may be enclosed with backquotes, db is optionnal,
// query may contain aliases.

/**
 * @todo if there are more than one table name in the Select:
 * - do not extract the first table name
 * - do not show a table name in the page header
 * - do not display the sub-pages links)
 */
if ($is_select && !$is_cte_select && !fsfx__is_ignore_sql_parse($sql_query)) {
    $prev_db = $db;
    if (isset($analyzed_sql[0]['table_ref'][0]['table_true_name'])) {
        $table = $analyzed_sql[0]['table_ref'][0]['table_true_name'];
    }
    if (isset($analyzed_sql[0]['table_ref'][0]['db'])
      && strlen($analyzed_sql[0]['table_ref'][0]['db'])) {
        $db    = $analyzed_sql[0]['table_ref'][0]['db'];
    } else {
        $db = $prev_db;
    }
    // Nijel: don't change reload, if we already decided to reload in import
    if (empty($reload) && empty($GLOBALS['is_ajax_request'])) {
        $reload  = ($db == $prev_db) ? 0 : 1;
    }
}
?>
