<?php

/**
 * DocxTidy - Docx Zip manipulation utility methods
 *
 * Copyright (c) 2017 gyselroth™  (http://www.gyselroth.com)
 *
 * @package DocxTidy
 * @version 0.2.0
 * @link    https://github.com/gyselroth/docx-tidy
 * @author  gyselroth™  (http://www.gyselroth.com)
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache 2.0 license
 */

namespace DocxTidy\Util;

use Comodojo\Zip\Zip;

class DocxZip
{

    /**
     * @param string $docxPath
     * @return array
     * @throws \Comodojo\Exception\ZipException
     */
    public static function unzipDocx($docxPath)
    {
        $extractedFiles = self::filenameWithoutExtension($docxPath);
        $zipPath        = $extractedFiles . '.zip';
        copy($docxPath, $zipPath);

        $zip = Zip::open($zipPath);
        $zip->extract($extractedFiles);
        $zip->close();

        $xmlLocation   = $extractedFiles . '/word/';
        $folderContent = scandir($xmlLocation);

        $xmlFiles = [];

        foreach ($folderContent as $file) {
            if (strrpos($file, '.xml') === 0) {
                $xmlFiles[] = $xmlLocation . $file;
            }
        }

        return $xmlFiles;
    }

    /**
     * @param string      $docxPath
     * @param string|null $outputPath
     * @throws \Comodojo\Exception\ZipException
     */
    public static function zipFilesToDocx($docxPath, $outputPath = null)
    {
        $outputDocx = empty($outputPath) ? $docxPath : $outputPath;
        $outputZip  = str_replace('.docx', '.zip', $outputDocx);

        if (file_exists($outputZip)) {
            unlink($outputZip);
        }

        $extractedFiles = self::filenameWithoutExtension($docxPath);

        $zip = Zip::create($outputZip, true);
        $zip->add($extractedFiles);
        $zip->close();

        self::deleteDirectory($extractedFiles);

        if (file_exists($outputDocx)) {
            unlink($outputDocx);
        }

        rename($outputZip, $outputDocx);
    }

    /**
     * @param string $filename
     * @return string
     */
    public static function filenameWithoutExtension($filename)
    {
        return substr($filename, 0, strrpos($filename, '.'));
    }

    /**
     * @param string $directory
     * @return bool
     */
    public static function deleteDirectory($directory)
    {
        if (!file_exists($directory)) {
            return true;
        }

        if (!is_dir($directory)) {
            return unlink($directory);
        }

        foreach (scandir($directory) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!self::deleteDirectory($directory . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }

        }

        return rmdir($directory);
    }
}