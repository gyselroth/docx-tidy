<?php

/**
 * DocxTidy - File read exception
 *
 * Copyright (c) 2017 gyselroth™  (http://www.gyselroth.com)
 *
 * @package DocxTidy
 * @version 0.2.0
 * @link    https://github.com/gyselroth/docx-tidy
 * @author  gyselroth™  (http://www.gyselroth.com)
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache 2.0 license
 */

namespace DocxTidy\Exception;

class FileReadException extends \Exception {

    /**
     * Constructor - redefine so $filename is mandatory
     *
     * @param string            $filename
     * @param \Exception|null   $previous
     */
    public function __construct($filename, \Exception $previous = null)
    {
        parent::__construct('Failed reading file: ' . $filename, 0, $previous);
    }
}