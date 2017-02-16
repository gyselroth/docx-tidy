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
     * @param $docxPath
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

        self::deleteDirectory($extractedFiles);

        return $xmlFiles;
    }

    /**
     * @param $docxPath
     * @param $outputName
     * @throws \Comodojo\Exception\ZipException
     */
    public static function zipFilesToDocx($docxPath, $outputName)
    {
        $directory = dirname($docxPath) . '/';
        $outputZip = $directory . str_replace('.docx', '.zip', empty($outputName) ? $docxPath : $outputName);

        if (file_exists($outputZip)) {
            unlink($outputZip);
        }

        $zip = Zip::create($outputZip, true);
        $zip->add(self::filenameWithoutExtension($docxPath));
        $zip->close();

        $outputDocx = str_replace('.zip', '.docx', $outputZip);

        if (file_exists($outputDocx)) {
            unlink($outputDocx);
        }

        rename($outputZip, $outputDocx);
    }

    /**
     * @param $filename
     * @return string
     */
    public static function filenameWithoutExtension($filename)
    {
        return substr($filename, 0, strrpos($filename, '.'));
    }

    /**
     * @param $directory
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