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

use DocxTidy\Exception\DirectoryReadException;
use DocxTidy\Exception\DirectoryRealPathException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class DocxZip
{
    /**
     * @param  string   $docxPath
     * @return array
     * @throws \DocxTidy\Exception\DirectoryReadException
     */
    public static function unzipDocx($docxPath)
    {
        $extractedFiles = pathinfo($docxPath, PATHINFO_FILENAME);
        $zipPath        = $extractedFiles . '.zip';
        copy($docxPath, $zipPath);
        
        self::rmdirRecursive($extractedFiles);

        $zipArchive = new ZipArchive();
        $zipArchive->open($zipPath);
        chmod($extractedFiles, 0777);
        $zipArchive->extractTo($extractedFiles);
        $zipArchive->close();

        $xmlLocation   = $extractedFiles . '/word/';
        $folderContent = scandir($xmlLocation);
        if (false === $folderContent) {
            throw new DirectoryReadException($xmlLocation);
        }

        $xmlFiles = [];

        foreach ($folderContent as $file) {
            if (strrpos($file, '.xml') === 0) {
                $xmlFiles[] = $xmlLocation . $file;
            }
        }

        unlink($zipPath);

        return $xmlFiles;
    }

    /**
     * @param  string       $docxPath
     * @param  string|null  $outputPath
     * @return bool
     * @throws \DocxTidy\Exception\DirectoryRealPathException
     * @throws \DocxTidy\Exception\DirectoryReadException
     */
    public static function zipFilesToDocx($docxPath, $outputPath = null)
    {
        $outputDocx = empty($outputPath) ? $docxPath : $outputPath;
        $outputZip  = str_replace('.docx', '.zip', $outputDocx);

        if (file_exists($outputZip)) {
            unlink($outputZip);
        }

        $extractedFiles = pathinfo($docxPath, PATHINFO_FILENAME);

        $zipArchive = new ZipArchive();
        $zipArchive->open($outputZip, ZipArchive::CREATE);

        $extractedFiles = str_replace('\\', DIRECTORY_SEPARATOR, realpath($extractedFiles));

        if (is_dir($extractedFiles) === true) {
            $files    = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractedFiles), RecursiveIteratorIterator::SELF_FIRST);
            $pathDots = ['.', '..'];
            foreach ($files as $file) {
                $file = str_replace('\\', DIRECTORY_SEPARATOR, $file);

                // ignore "." and ".." folders
                if (in_array(substr($file, strrpos($file, DIRECTORY_SEPARATOR) + 1), $pathDots, true)) {
                    continue;
                }

                $file = realpath($file);
                if (false === $file) {
                    throw new DirectoryRealPathException($file);
                }

                if (is_dir($file) === true) {
                    $zipArchive->addEmptyDir(str_replace($extractedFiles . DIRECTORY_SEPARATOR, '', $file . DIRECTORY_SEPARATOR));
                } elseif (is_file($file) === true) {
                    $zipArchive->addFile($file, str_replace($extractedFiles . DIRECTORY_SEPARATOR, '', $file));
                }
            }
        } elseif (is_file($extractedFiles) === true) {
            $zipArchive->addFile($extractedFiles, basename($extractedFiles));
        }
        
        $zipArchive->close();

        self::rmdirRecursive($extractedFiles);

        if (file_exists($outputDocx)) {
            unlink($outputDocx);
        }

        return rename($outputZip, $outputDocx);
    }

    /**
     * @param  string $directory
     * @return bool
     * @throws \DocxTidy\Exception\DirectoryReadException
     */
    public static function rmdirRecursive($directory)
    {
        if (!file_exists($directory)) {
            return true;
        }

        if (!is_dir($directory)) {
            return unlink($directory);
        }

        $items = scandir($directory);
        if (false === $items) {
            throw new DirectoryReadException($directory);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (!self::rmdirRecursive($directory . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($directory);
    }
}