DocxTidy - Changelog
====================

Version 0.5.0 - not released yet
--------------------------------

* Changed: Extracted tag-tuple merge-ability detection into new TagTupleMergeable class
* Changed: Language level to PHP7.1 (constant visibility, nullables, catch multiple exceptions, etc.)


Version 0.4.5 - released 2017/10/09
-----------------------------------

* Changed: Commented-out exclude null next-runProperties during successive run merge-ability detection


Version 0.4.4 - released 2017/05/08
-----------------------------------

* BugFix: Use correct directory paths when unzipping and zipping a docx


Version 0.4.3 - released 2017/03/23
-----------------------------------
* Improved: Prevent possible "undefined offset" warning during elements joining


Version 0.4.2 - released 2017/02/22
-----------------------------------
* Bugfix: Undefined index error was thrown if run in field char scope had no run properties


Version 0.4.1 - released 2017/02/22
-----------------------------------
* Bugfix: Merging of runs was ignoring (+invalidating) other elements between closing of one and opening of next run
* Bugfix: Merging runs ending w/ bookmarks was invalidating bookmark XML


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