<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */

/**
 * Interface for the zip extension
 * @package    phpMyAdmin
 */



/*
    use chatGPT to converted the functions in zip_extension.lib.php
    seems it works

*/
























function PMA_getZipContents($file, $specific_entry = null)
{
    $error_message = '';
    $file_data = '';
    
    $zip = new ZipArchive();
    $open_res = $zip->open($file);
    if ($open_res === true) {
        if ($zip->numFiles === 0) {
            $error_message = __('No files found inside ZIP archive!');
        } else {
            if ($specific_entry === null) {
                // 读取第一个文件，检查是否是 ODS 文件
                $first_entry = $zip->getNameIndex(0);
                if ($first_entry === false) {
                    $error_message = __('Error in ZIP archive: Could not read first entry');
                } else {
                    // 打开文件流
                    $stream = $zip->getStream($first_entry);
                    if ($stream) {
                        $read = stream_get_contents($stream);
                        fclose($stream);

                        if ($read === false) {
                            $error_message = __('Error in ZIP archive: Could not read first entry');
                        } else {
                            $ods_mime = 'application/vnd.oasis.opendocument.spreadsheet';
                            if (!strcmp($ods_mime, $read)) {
                                $specific_entry = '/^content\.xml$/';
                            } else {
                                $file_data = $read;
                            }
                        }
                    } else {
                        $error_message = __('Error in ZIP archive: Could not open stream for first entry');
                    }
                }
            }

            if ($specific_entry !== null && $error_message === '') {
                // 遍历所有文件，查找符合条件的文件
                $entry_found = false;
                $entry_read_error = false;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry_name = $zip->getNameIndex($i);
                    if ($entry_name !== false && preg_match($specific_entry, $entry_name)) {
                        $stream = $zip->getStream($entry_name);
                        if ($stream) {
                            $file_data = stream_get_contents($stream);
                            fclose($stream);
                            if ($file_data !== false) {
                                $entry_found = true;
                                break;
                            }
                            $entry_read_error = true;
                        } else {
                            $entry_read_error = true;
                        }
                    }
                }

                if (! $entry_found) {
                    if ($entry_read_error) {
                        $error_message = __('Error in ZIP archive: Could not read matching entry');
                    } else {
                        $error_message = __('Error in ZIP archive: Could not find "') . $specific_entry . '"';
                    }
                }
            }
        }
        $zip->close();
    } else {
        $error_message = __('Error in ZIP archive:') . ' ' . PMA_getZipError($open_res);
    }
    
    return array('error' => $error_message, 'data' => $file_data);
}



function PMA_findFileFromZipArchive($file_regexp, $file)
{
    $zip = new ZipArchive();
    if ($zip->open($file) === true) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry_name = $zip->getNameIndex($i);
            if ($entry_name !== false && preg_match($file_regexp, $entry_name)) {
                $zip->close();
                return $entry_name;
            }
        }
        $zip->close();
    }
    return false;
}


function PMA_getNoOfFilesInZip($file)
{
    $count = 0;
    $zip = new ZipArchive();
    
    if ($zip->open($file) === true) {
        $count = $zip->numFiles;
        $zip->close();
    }
    
    return $count;
}


function PMA_zipExtract($zip_path, $destination, $entries)
{
    $zip = new ZipArchive;
    if ($zip->open($zip_path) === true) {
        $result = $zip->extractTo($destination, $entries);
        $zip->close();
        return $result;
    }
    return false;
}









function PMA_getZipError($code)
{
    // I don't think this needs translation
    $errors = array(
        ZipArchive::ER_MULTIDISK => 'Multi-disk zip archives not supported',
        ZipArchive::ER_READ      => 'Read error',
        ZipArchive::ER_CRC       => 'CRC error',
        ZipArchive::ER_NOZIP     => 'Not a zip archive',
        ZipArchive::ER_INCONS    => 'Zip archive inconsistent',
    );

    return isset($errors[$code]) ? $errors[$code] : $code;
}

