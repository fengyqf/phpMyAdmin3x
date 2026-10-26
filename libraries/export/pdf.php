<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Produce a PDF report (export) from a query
 *
 * @package PhpMyAdmin-Export
 * @subpackage PDF
 */
if (! defined('PHPMYADMIN')) {
    exit;
}

/**
 *
 */
if (isset($plugin_list)) {
    $plugin_list['pdf'] = array(
        'text' => __('PDF'),
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'force_file' => true,
        'options' => array(
            array('type' => 'begin_group', 'name' => 'general_opts'),
            array('type' => 'message_only', 'name' => 'explanation', 'text' => __('(Generates a report containing the data of a single table)')),
            array('type' => 'text', 'name' => 'report_title', 'text' => __('Report title:')),
            array(
                'type' => 'select',
                'name' => 'font',
                'text' => __('Font:'),
                'values' => array(
                    'auto' => __('Auto-detect'),
                    'stsongstdlight' => __('Simplified Chinese') . ' (STSong)',
                    'cid0cs' => __('Simplified Chinese') . ' (Arial Unicode MS)',
                    'msungstdlight' => __('Traditional Chinese') . ' (MSung)',
                    'cid0ct' => __('Traditional Chinese') . ' (Arial Unicode MS)',
                    'kozminproregular' => __('Japanese') . ' (KozMinPro)',
                    'kozgopromedium' => __('Japanese') . ' (KozGoPro)',
                    'cid0jp' => __('Japanese') . ' (Arial Unicode MS)',
                    'hysmyeongjostdmedium' => __('Korean') . ' (HYSMyeongJo)',
                    'cid0kr' => __('Korean') . ' (Arial Unicode MS)',
                    'dejavusans' => __('International (DejaVu Sans)'),
                ),
            ),
            array('type' => 'hidden', 'name' => 'structure_or_data'),
            array('type' => 'end_group')
            ),
        'options_text' => __('Options'),
        );
} else {

    include_once './libraries/PDF.class.php';

    /**
     * Adapted from a LGPL script by Philip Clarke
     * @package PhpMyAdmin-Export
     * @subpackage PDF
     */
    class PMA_Export_PDF extends PMA_PDF
    {
        var $tablewidths;
        var $headerset;
        var $dataY;
        var $cellFontSize;
        var $titleFontSize;
        var $titleText;
        var $currentDb;
        var $currentTable;
        var $colTitles;
        var $colAlign;
        var $results;
        var $numFields;
        var $fields;
        var $sColWidth;
        var $display_column;
        var $titleWidth;
        var $colFits;
        var $userFontChoice;

        function checkPageBreak($h = 0, $y = '', $addpage = true)
        {
            if ($this->empty_string($y)) {
                $y = $this->y;
            }
            $current_page = $this->page;
            if ((($y + $h) > $this->PageBreakTrigger) AND (! $this->InFooter) AND (! $this->InHeader) AND ($this->AcceptPageBreak())) {
                if ($addpage) {
                    //Automatic page break
                    $x = $this->x;
                    $this->AddPage($this->CurOrientation);
                    $this->y = $this->dataY;
                    $oldpage = $this->page - 1;
                    if ($this->rtl) {
                        if ($this->pagedim[$this->page]['orm'] != $this->pagedim[$oldpage]['orm']) {
                            $this->x = $x - ($this->pagedim[$this->page]['orm'] - $this->pagedim[$oldpage]['orm']);
                        } else {
                            $this->x = $x;
                        }
                    } else {
                        if ($this->pagedim[$this->page]['olm'] != $this->pagedim[$oldpage]['olm']) {
                            $this->x = $x + ($this->pagedim[$this->page]['olm'] - $this->pagedim[$oldpage]['olm']);
                        } else {
                            $this->x = $x;
                        }
                    }
                }
                return true;
            }
            if ($current_page != $this->page) {
                // account for columns mode
                return true;
            }
            return false;
        }

        function Header()
        {
            global $maxY;
            // Check if header for this page already exists
            if (! isset($this->headerset[$this->page])) {
                $fullwidth = 0;
                foreach ($this->tablewidths as $width) {
                    $fullwidth += $width;
                }
                $font = $this->getPmaFont();
                $yPos = ($this->tMargin) - ($this->FontSizePt / $this->k) * 5;
                if ($yPos < 10) {
                    $yPos = 10;
                }
                $this->SetY($yPos);
                $this->cellFontSize = $this->FontSizePt ;
                $this->SetFont($font, '', ($this->titleFontSize ? $this->titleFontSize : $this->FontSizePt));
                $this->Cell(0, $this->FontSizePt, $this->titleText, 0, 1, 'C');
                $this->SetFont($font, '', $this->cellFontSize);
                $yPos2 = ($this->tMargin) - ($this->FontSizePt / $this->k) * 2.5;
                if ($yPos2 < 25) {
                    $yPos2 = 25;
                }
                $this->SetY($yPos2);
                $this->Cell(
                    0, $this->FontSizePt,
                    __('Database') . ': ' . $this->currentDb . ',  ' . __('Table') . ': ' . $this->currentTable,
                    0, 1, 'L'
                );
                $l = ($this->lMargin);
                foreach ($this->colTitles as $col => $txt) {
                    $this->SetXY($l, ($this->tMargin));
                    $this->MultiCell($this->tablewidths[$col], $this->FontSizePt, $txt);
                    $l += $this->tablewidths[$col] ;
                    $maxY = ($maxY < $this->getY()) ? $this->getY() : $maxY ;
                }
                $this->SetXY($this->lMargin, $this->tMargin);
                $this->setFillColor(200, 200, 200);
                $l = ($this->lMargin);
                foreach ($this->colTitles as $col => $txt) {
                    $this->SetXY($l, $this->tMargin);
                    $this->cell($this->tablewidths[$col], $maxY-($this->tMargin), '', 1, 0, 'L', 1);
                    $this->SetXY($l, $this->tMargin);
                    $this->MultiCell($this->tablewidths[$col], $this->FontSizePt, $txt, 0, 'C');
                    $l += $this->tablewidths[$col];
                }
                $this->setFillColor(255, 255, 255);
                // set headerset
                $this->headerset[$this->page] = 1;
            }

            $this->dataY = $maxY;
        }

        function morepagestable($lineheight=8)
        {
            // some things to set and 'remember'
            $l = $this->lMargin;
            $startheight = $h = $this->dataY;
            $startpage = $currpage = $this->page;

            // calculate the whole width
            $fullwidth = 0;
            foreach ($this->tablewidths as $width) {
                $fullwidth += $width;
            }

            // Now let's start to write the table
            $row = 0;
            $tmpheight = array();
            $maxpage = $this->page;

            while ($data = PMA_DBI_fetch_row($this->results)) {
                $this->page = $currpage;
                // write the horizontal borders
                $this->Line($l, $h, $fullwidth+$l, $h);
                // write the content and remember the height of the highest col
                foreach ($data as $col => $txt) {
                    $this->page = $currpage;
                    $this->SetXY($l, $h);
                    if ($this->tablewidths[$col] > 0) {
                        $this->MultiCell($this->tablewidths[$col], $lineheight, $txt, 0, $this->colAlign[$col]);
                        $l += $this->tablewidths[$col];
                    }

                    if (!isset($tmpheight[$row.'-'.$this->page])) {
                        $tmpheight[$row.'-'.$this->page] = 0;
                    }
                    if ($tmpheight[$row.'-'.$this->page] < $this->GetY()) {
                        $tmpheight[$row.'-'.$this->page] = $this->GetY();
                    }
                    if ($this->page > $maxpage) {
                        $maxpage = $this->page;
                    }
                    unset($data[$col]);
                }

                // get the height we were in the last used page
                $h = $tmpheight[$row.'-'.$maxpage];
                // set the "pointer" to the left margin
                $l = $this->lMargin;
                // set the $currpage to the last page
                $currpage = $maxpage;
                unset($data[$row]);
                $row++;
            }
            // draw the borders
            // we start adding a horizontal line on the last page
            $this->page = $maxpage;
            $this->Line($l, $h, $fullwidth+$l, $h);
            // now we start at the top of the document and walk down
            for ($i = $startpage; $i <= $maxpage; $i++) {
                $this->page = $i;
                $l = $this->lMargin;
                $t = ($i == $startpage) ? $startheight : $this->tMargin;
                $lh = ($i == $maxpage) ? $h : $this->h-$this->bMargin;
                $this->Line($l, $t, $l, $lh);
                foreach ($this->tablewidths as $width) {
                    $l += $width;
                    $this->Line($l, $t, $l, $lh);
                }
            }
            // set it to the last page, if not it'll cause some problems
            $this->page = $maxpage;
        }

        function setAttributes($attr = array())
        {
            foreach ($attr as $key => $val) {
                $this->$key = $val ;
            }
        }

        function setTopMargin($topMargin)
        {
            $this->tMargin = $topMargin;
        }

        function resolveFontForData()
        {
            if (!empty($this->userFontChoice) && $this->userFontChoice !== 'auto') {
                $chosen = strtolower(trim($this->userFontChoice));
                if (in_array($chosen, self::getValidFonts())) {
                    $this->setPmaFont($chosen);
                    return;
                }
            }

            // If already resolved to a CJK font, keep it
            if (in_array($this->getPmaFont(), array(
                'stsongstdlight', 'cid0cs', 'msungstdlight', 'cid0ct',
                'kozminproregular', 'kozgopromedium', 'cid0jp',
                'hysmyeongjostdmedium', 'cid0kr'
            ))) {
                return;
            }

            // 1. Check UI language
            $lang = isset($GLOBALS['lang']) ? strtolower($GLOBALS['lang']) : '';
            if (strpos($lang, 'ja') !== false) {
                $this->setPmaFont('kozminproregular');
                return;
            } elseif (strpos($lang, 'ko') !== false) {
                $this->setPmaFont('hysmyeongjostdmedium');
                return;
            } elseif (strpos($lang, 'zh_tw') !== false || strpos($lang, 'zh_hk') !== false) {
                $chineseFont = 'msungstdlight';
            } else {
                $chineseFont = 'stsongstdlight';
            }

            // Patterns for specific scripts
            $jaPattern = '/[\x{3040}-\x{30ff}]/u'; // Hiragana & Katakana (uniquely Japanese)
            $koPattern = '/[\x{ac00}-\x{d7af}\x{1100}-\x{11ff}\x{3130}-\x{318f}]/u'; // Hangul (uniquely Korean)
            $cjkPattern = '/[\x{4e00}-\x{9fa5}\x{3000}-\x{303f}\x{ff00}-\x{ffef}]/u'; // CJK Ideographs & Symbols

            $hasHanzi = false;

            // 2. Check title, db name, table name for CJK characters
            $headerText = $this->titleText . ' ' . $this->currentDb . ' ' . $this->currentTable;
            if (preg_match($jaPattern, $headerText)) {
                $this->setPmaFont('kozminproregular');
                return;
            }
            if (preg_match($koPattern, $headerText)) {
                $this->setPmaFont('hysmyeongjostdmedium');
                return;
            }
            if (preg_match($cjkPattern, $headerText)) {
                $hasHanzi = true;
            }

            // 3. Check table column names/comments and sample rows for CJK
            if (!empty($this->currentDb) && !empty($this->currentTable)) {
                // Check column names & comments
                $colResult = PMA_DBI_try_query(
                    'SHOW FULL COLUMNS FROM ' . PMA_backquote($this->currentDb) . '.' . PMA_backquote($this->currentTable)
                );
                if ($colResult) {
                    while ($col = PMA_DBI_fetch_assoc($colResult)) {
                        $colCheck = (isset($col['Field']) ? $col['Field'] : '') . ' ' . (isset($col['Comment']) ? $col['Comment'] : '');
                        if (preg_match($jaPattern, $colCheck)) {
                            PMA_DBI_free_result($colResult);
                            $this->setPmaFont('kozminproregular');
                            return;
                        }
                        if (preg_match($koPattern, $colCheck)) {
                            PMA_DBI_free_result($colResult);
                            $this->setPmaFont('hysmyeongjostdmedium');
                            return;
                        }
                        if (!$hasHanzi && preg_match($cjkPattern, $colCheck)) {
                            $hasHanzi = true;
                        }
                    }
                    PMA_DBI_free_result($colResult);
                }

                // Check sample data (first 50 rows)
                $sampleResult = PMA_DBI_try_query(
                    'SELECT * FROM ' . PMA_backquote($this->currentDb) . '.' . PMA_backquote($this->currentTable) . ' LIMIT 50'
                );
                if ($sampleResult) {
                    while ($srow = PMA_DBI_fetch_row($sampleResult)) {
                        foreach ($srow as $val) {
                            if (is_string($val)) {
                                if (preg_match($jaPattern, $val)) {
                                    PMA_DBI_free_result($sampleResult);
                                    $this->setPmaFont('kozminproregular');
                                    return;
                                }
                                if (preg_match($koPattern, $val)) {
                                    PMA_DBI_free_result($sampleResult);
                                    $this->setPmaFont('hysmyeongjostdmedium');
                                    return;
                                }
                                if (!$hasHanzi && preg_match($cjkPattern, $val)) {
                                    $hasHanzi = true;
                                }
                            }
                        }
                    }
                    PMA_DBI_free_result($sampleResult);
                }
            }

            if ($hasHanzi) {
                $this->setPmaFont($chineseFont);
                return;
            }

            // Default to Western font
            $this->setPmaFont('dejavusans');
        }

        function mysql_report($query)
        {
            unset($this->tablewidths);
            unset($this->colTitles);
            unset($this->titleWidth);
            unset($this->colFits);
            unset($this->display_column);
            unset($this->colAlign);

            $this->resolveFontForData();

            // Set font before measuring column widths
            $font = $this->getPmaFont();
            $this->SetFont($font, '', 9);

            /**
             * Pass 1 for column widths
             */
            $this->results = PMA_DBI_query($query, null, PMA_DBI_QUERY_UNBUFFERED);
            $this->numFields  = PMA_DBI_num_fields($this->results);
            $this->fields = PMA_DBI_get_fields_meta($this->results);

            // sColWidth = starting col width (an average size width)
            $availableWidth = $this->w - $this->lMargin - $this->rMargin;
            $this->sColWidth = $availableWidth / $this->numFields;
            $totalTitleWidth = 0;

            // loop through results header and set initial col widths/ titles/ alignment
            // if a col title is less than the starting col width, reduce that column size
            $colFits = array();
            for ($i = 0; $i < $this->numFields; $i++) {
                $stringWidth = $this->getstringwidth($this->fields[$i]->name) + 6 ;
                // save the real title's width
                $titleWidth[$i] = $stringWidth;
                $totalTitleWidth += $stringWidth;

                // set any column titles less than the start width to the column title width
                if ($stringWidth < $this->sColWidth) {
                    $colFits[$i] = $stringWidth ;
                }
                $this->colTitles[$i] = $this->fields[$i]->name;
                $this->display_column[$i] = true;

                switch ($this->fields[$i]->type) {
                case 'int':
                    $this->colAlign[$i] = 'R';
                    break;
                case 'blob':
                case 'tinyblob':
                case 'mediumblob':
                case 'longblob':
                    /**
                     * @todo do not deactivate completely the display
                     * but show the field's name and [BLOB]
                     */
                    if (stristr($this->fields[$i]->flags, 'BINARY')) {
                        $this->display_column[$i] = false;
                        unset($this->colTitles[$i]);
                    }
                    $this->colAlign[$i] = 'L';
                    break;
                default:
                    $this->colAlign[$i] = 'L';
                }
            }

            // title width verification
            if ($totalTitleWidth > $availableWidth) {
                $adjustingMode = true;
            } else {
                $adjustingMode = false;
                // we have enough space for all the titles at their
                // original width so use the true title's width
                foreach ($titleWidth as $key => $val) {
                    $colFits[$key] = $val;
                }
            }

            // loop through the data; any column whose contents
            // is greater than the column size is resized
            /**
              * @todo force here a LIMIT to avoid reading all rows
              */
            while ($row = PMA_DBI_fetch_row($this->results)) {
                foreach ($colFits as $key => $val) {
                    $stringWidth = $this->getstringwidth($row[$key]) + 6 ;
                    if ($adjustingMode && ($stringWidth > $this->sColWidth)) {
                        // any column whose data's width is bigger than
                        // the start width is now discarded
                        unset($colFits[$key]);
                    } else {
                        // if data's width is bigger than the current column width,
                        // enlarge the column (but avoid enlarging it if the
                        // data's width is very big)
                        if ($stringWidth > $val && $stringWidth < ($this->sColWidth * 3)) {
                            $colFits[$key] = $stringWidth ;
                        }
                    }
                }
            }

            $totAlreadyFitted = 0;
            foreach ($colFits as $key => $val) {
                // set fitted columns to smallest size
                $this->tablewidths[$key] = $val;
                // to work out how much (if any) space has been freed up
                $totAlreadyFitted += $val;
            }

            if ($adjustingMode) {
                $surplus = (sizeof($colFits) * $this->sColWidth) - $totAlreadyFitted;
                $surplusToAdd = $surplus / ($this->numFields - sizeof($colFits));
            } else {
                $surplusToAdd = 0;
            }

            for ($i=0; $i < $this->numFields; $i++) {
                if (!in_array($i, array_keys($colFits))) {
                    $this->tablewidths[$i] = $this->sColWidth + $surplusToAdd;
                }
                if ($this->display_column[$i] == false) {
                    $this->tablewidths[$i] = 0;
                }
            }

            ksort($this->tablewidths);

            PMA_DBI_free_result($this->results);

            // Pass 2

            $this->results = PMA_DBI_query($query, null, PMA_DBI_QUERY_UNBUFFERED);
            $this->setY($this->tMargin);
            $this->AddPage();
            $this->SetFont($this->getPmaFont(), '', 9);
            $this->morepagestable($this->FontSizePt);
            PMA_DBI_free_result($this->results);

        } // end of mysql_report function

    } // end of PMA_Export_PDF class

    $pdf = new PMA_Export_PDF('L', 'pt', 'A3');

    /**
     * Finalize the pdf.
     *
     * @return  bool        Whether it succeeded
     *
     * @access  public
     */
    function PMA_exportFooter()
    {
        global $pdf;

        // instead of $pdf->Output():
        if (!PMA_exportOutputHandler($pdf->getPDFData())) {
            return false;
        }

        return true;
    }

    /**
     * Initialize the pdf to export data.
     *
     * @return  bool        Whether it succeeded
     *
     * @access  public
     */
    function PMA_exportHeader()
    {
        global $pdf_report_title;
        global $pdf_font;
        global $pdf;

        $pdf->Open();

        $font_candidate = !empty($pdf_font) ? strtolower(trim($pdf_font)) : 'auto';
        $chosen_font = ($font_candidate === 'auto' || in_array($font_candidate, PMA_PDF::getValidFonts()))
            ? $font_candidate
            : 'auto';
        $attr = array(
            'titleFontSize' => 18,
            'titleText' => $pdf_report_title,
            'userFontChoice' => $chosen_font
        );
        $pdf->setAttributes($attr);
        $pdf->setTopMargin(80);

        return true;
    }

    /**
     * Outputs database header
     *
     * @param string $db Database name
     *
     * @return bool Whether it succeeded
     *
     * @access public
     */
    function PMA_exportDBHeader($db)
    {
        return true;
    }

    /**
     * Outputs database footer
     *
     * @param string $db Database name
     *
     * @return bool Whether it succeeded
     *
     * @access public
     */
    function PMA_exportDBFooter($db)
    {
        return true;
    }

    /**
     * Outputs CREATE DATABASE statement
     *
     * @param string $db Database name
     *
     * @return bool Whether it succeeded
     *
     * @access public
     */
    function PMA_exportDBCreate($db)
    {
        return true;
    }

    /**
     * Outputs the content of a table in PDF format
     *
     * @param string $db        database name
     * @param string $table     table name
     * @param string $crlf      the end of line sequence
     * @param string $error_url the url to go back in case of error
     * @param string $sql_query SQL query for obtaining data
     *
     * @return bool Whether it succeeded
     *
     * @access public
     */
    function PMA_exportData($db, $table, $crlf, $error_url, $sql_query)
    {
        global $pdf;

        $attr=array('currentDb' => $db, 'currentTable' => $table);
        $pdf->setAttributes($attr);
        $pdf->mysql_report($sql_query);

        return true;
    } // end of the 'PMA_exportData()' function
}
?>
