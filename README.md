DocxTidy
========

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
* DocxTidy removes all space="preserve" flags and re-inserts space="preserve" into all &lt;w:t&gt; tags

This library is distributed on an “AS IS” BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, 
either express or implied.


Installation
------------

Following soon...


Changelog
---------

https://github.com/gyselroth/docx-tidy/blob/master/CHANGELOG.md


Third party acknowledgements
----------------------------

Microsoft Office and Word are registered trademarks of Microsoft Corporation.


License
-------

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