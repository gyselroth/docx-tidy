DocxTidy
========

[![Total Downloads](https://img.shields.io/packagist/dt/gyselroth/docx-tidy.svg)](https://packagist.org/packages/gyselroth/docx-tidy)
[![Latest Stable Version](https://img.shields.io/packagist/v/gyselroth/docx-tidy.svg)](https://packagist.org/packages/gyselroth/docx-tidy)


Description
-----------

A PHP library to tidy DOCX XML files: merges successive elements of same type and properties.

By simplifying the markup of DOCX XML, DocxTidy alleviates the efforts needed for custom modifications (for instance DOCX-based templating).

* Merges successive runs having identical run-properties
* Merges successive elements of same type (&lt;w:t&gt;, &lt;w:instrText&gt;) within each run


DocxTidy supports two tidying modes:

1. DOCX files (includes unpacking and re-archiving of contained XML files)
2. XML string


Please Note
-----------

* By merging segmented tags, DocxTidy removes versioning/editing history information
* When run with default settings, DocxTidy removes spellchecking flags ("noProof", "proofErr", "lang")
* DocxTidy removes all xml:space="preserve" flags and re-inserts xml:space="preserve" into all &lt;w:t&gt; and &lt;w:instrText&gt; tags
* To standardize run properties, DocxTidy deletes any font hints
* After unifying consecutive runs w/ identical properties and removing any spellchecking flags, DocxTidy removes any resulting empty tags

This library is distributed on an “AS IS” BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, 
either express or implied.


Installation
------------

The recommended way to install is via Composer:

```bash
$ composer require gyselroth/docx-tidy
```

PHP 7.1 is required since DocxTidy version 0.5.0 (PHP 5.6 until version 0.4.5)


Usage
-----

### Example 1: Tidy a whole DOCX file

```php
<?php

use DocxTidy\DocxTidy;

// tidy a DOCX file
(new DocxTidy())->tidyDocx('path/to/your.docx');
```

### Example 2: Tidy an XML (string)

```php
<?php

use DocxTidy\DocxTidy;

// read DOCX XML, e.g. "document.xml" / "header1.xml" / etc
$xml = file_get_contents('path/to/your_unzipped_docx/word/document.xml');

// tidy DOCX XML string
$xml = (new DocxTidy())->tidyXml($xml);
```


Changelog
---------

https://github.com/gyselroth/docx-tidy/blob/master/CHANGELOG.md


Submitting bugs and feature requests
------------------------------------

Bugs and feature request are tracked on [GitHub](https://github.com/gyselroth/docx-tidy/issues)


Third party acknowledgements
----------------------------

Microsoft Office and Word are registered trademarks of Microsoft Corporation.


Author and License
------------------

Copyright 2017 gyselroth™ (http://www.gyselroth.com)

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0":http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
