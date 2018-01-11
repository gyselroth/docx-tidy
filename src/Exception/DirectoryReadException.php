<?php

/**
 * This file is part of the DocxTidy package.
 *
 * Directory read exception
 *
 * Copyright (c) 2017 gyselroth™  (http://www.gyselroth.com)
 *
 * @package DocxTidy
 * @link    https://github.com/gyselroth/docx-tidy
 * @author  gyselroth™  (http://www.gyselroth.com)
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache 2.0 license
 */

namespace DocxTidy\Exception;

class DirectoryReadException extends \Exception {

    /**
     * Constructor - redefine so $path is mandatory
     *
     * @param string            $path
     * @param \Exception|null   $previous
     */
    public function __construct(string $path, \Exception $previous = null)
    {
        parent::__construct('Failed reading directory: ' . $path, 0, $previous);
    }
}