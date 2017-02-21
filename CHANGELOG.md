DocxTidy - Changelog
====================


Version 0.4.1 - not released yet
--------------------------------
* Bugfix: Mergig runs ending w/ bookmarks was invalidating bookmark XML


Version 0.4.0 - released 2017/02/21
-----------------------------------
* Changed: All runs in sequences of runs from fldCharType="begin" until fildCharType="end" are merged into 1 run, but not merged w/ other runs


Version 0.3.0 - released 2017/02/21
-----------------------------------

* Added: All tags within scope bordered by fldCharType="begin" to fldCharType="end" inherit run-properties of first w:t tag inside that paragraph   
* Added: Specific exceptions   


Version 0.2.0 - released 2017/02/20
-----------------------------------

* Added: Tidy full DOCX including unpack and re-archive
* Added: Usage examples
* Added: Verified PSR-4 autoloading
* Added: Verified and documented installation using composer


Version 0.1.0 - released 2017/02/15
-----------------------------------

* Initial release
