<?php

/**
 * This file is part of the DocxTidy package.
 *
 * File write exception
 *
 * Copyright (c) 2017-2018 gyselroth™  (http://www.gyselroth.com)
 *
 * @package DocxTidy
 * @link    https://github.com/gyselroth/docx-tidy
 * @author  gyselroth™  (http://www.gyselroth.com)
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache 2.0 license
 */

namespace DocxTidy\Exception;

class FileWriteException extends \Exception {

    /**
     * Constructor - redefine so $filename is mandatory
     *
     * @param string            $filename
     * @param \Exception|null   $previous
     */
    public function __construct(string $filename, \Exception $previous = null)
    {
        parent::__construct('Failed writing to file: ' . $filename, 0, $previous);
    }
}