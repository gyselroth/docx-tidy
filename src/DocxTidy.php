<?php

/**
 * DocxTidy
 *
 * Simplify markup of DOCX XML by tidying successive elements w/ redundant properties / types
 *
 * 1. Merge successive runs having identical run-properties
 * 2. Merge successive elements of same type (<w:t>, <w:instrText>) within each run
 *
 * Copyright (c) 2017 gyselroth™  (http://www.gyselroth.com)
 *
 * @package DocxTidy
 * @version 0.4.1
 * @link    https://github.com/gyselroth/docx-tidy
 * @author  gyselroth™  (http://www.gyselroth.com)
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache 2.0 license
 */

namespace DocxTidy;

use DocxTidy\Exception\FileReadException;
use DocxTidy\Exception\FileWriteException;
use DocxTidy\Util\DocxXml;
use DocxTidy\Util\DocxZip;

class DocxTidy
{
    // Regular expressions matching (word) XML elements
    const PATTERN_PARAGRAPH_OPEN       = '/<w:p .*?>/i';
    const PATTERN_RUN_OPEN             = '/<w:r(\w){0}( .*?)?>/i';
    const PATTERN_RUN_CLOSE            = '/<\/w:r>/i';
    const PATTERN_RUN_PROPERTIES       = '/<w:rPr>.*?(<\/w:rPr>)/i';
    const PATTERN_ELEMENT_TAG_UNCLOSED = '/<(\/)?w:[a-z]+/i';

    // Items which will be removed by default from the whole XML
    const PATTERN_LANG       = '<w:lang w:val="[a-z|-]{2,5}"\/>';
    const PATTERN_NO_PROOF   = '<w:noProof\/>';
    const PATTERN_PROOF_ERR  = '<w:proofErr w:type="\w+"\/>';
    const PATTERN_FONTS_HINT = '<w:rFonts w:hint="\w+"\/>';
    const PATTERN_W_HINT     = '\sw:hint="\w+"';
    const PATTERN_EMPTY_TAG  = '<w:([a-z]+)><\/w:([a-z]+)>';

    const STRING_TAG_RUN_CLOSE         = '</w:r>';
    const STRING_TAG_W_TEXT_OPEN       = '<w:t>';
    const STRING_TAG_W_INSTR_TEXT_OPEN = '<w:instrText>';

    const STRING_SPACE_PRESERVE        = ' xml:space="preserve"';
    const STRING_FLDCHAR_TYPE_BEGIN    = 'fldCharType="begin"';
    const STRING_FLDCHAR_TYPE_END      = 'fldCharType="end"';

    /** @var array */
    private $mergeableTagTypes = ['w:t', 'w:instrText'];

    /** @var array  Array of content of runs (w/o run-opening tag) */
    private $runsInCurrentParagraph;

    /** @var array  Opening tags of runs (which the current paragraph was exploded by) */
    private $runOpenTagsInCurrentParagraph;

    /** @var bool   Flag whether parser iteration is currently within (tags contained inside) a fieldCharacter scope (begin...end) */
    private $isWithinFieldCharScope = false;

    /** @var bool   Flag whether fldChar-scope ends within current run */
    private $isFieldCharScopeEndingInCurrentRun = false;

    /** @var bool|string Properties to be used in (to ensure merge-ability of) all runs inside fieldsCharacter scope */
    private $runPropertiesInFieldCharScope = false;

    /** @var  int */
    private $lengthTagRunClose;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->lengthTagRunClose = strlen(self::STRING_TAG_RUN_CLOSE);
    }

    /**
     * Tidy given word XML string
     *
     * Find and merge successive runs w/ identical runProperties within paragraphs.
     * Within each run: merge successive run elements (<w:t> or <w:instrText>)
     *
     * @param  string       $xml
     * @param  array|string $removePattern
     * @return string
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function tidyXml($xml, $removePattern = null)
    {
        if ($removePattern !== false) {
            if ($removePattern === null) {
                // Default: remove spell-check flags
                $removePattern = '/' . self::PATTERN_NO_PROOF . '|' . self::PATTERN_PROOF_ERR . '|' . self::PATTERN_LANG . '|' . self::PATTERN_FONTS_HINT . '|' . self::PATTERN_EMPTY_TAG . '|' . self::PATTERN_W_HINT . '/i';
            }
            do {
                $xml = preg_replace($removePattern, '', $xml, -1, $count);
            } while ($count > 0);
        }

        // Remove all space preserve occurrences as they will be added into the paragraph later
        $xml = str_replace(self::STRING_SPACE_PRESERVE, '', $xml);

        // 1. Collect paragraphs, tidy each paragraph:
        $paragraphs       = DocxXml::preg_split_with_matches(self::PATTERN_PARAGRAPH_OPEN, $xml, $paragraphOpenTags);
        $amountParagraphs = count($paragraphs);
        for ($indexParagraph = 1; $indexParagraph < $amountParagraphs; $indexParagraph++) {
            // First item is XML and document meta data, NOT a paragraph
            do {
                $amountRunsMerged             = $amountElementsMerged                = 0;
                $this->isWithinFieldCharScope = $this->runPropertiesInFieldCharScope = false;

                // Collect all runs into array
                $this->runsInCurrentParagraph = DocxXml::preg_split_with_matches(self::PATTERN_RUN_OPEN, $paragraphs[$indexParagraph], $this->runOpenTagsInCurrentParagraph);
                $amountRunsInCurrentParagraph = count($this->runsInCurrentParagraph);

                if ($amountRunsInCurrentParagraph > 1) {
                    // Iterate over possibly merge-able runs
                    // First item is opening of paragraph, NOT a run. Last item doesn't have any successor to merge w/
                    for ($indexRun = 1; $indexRun < $amountRunsInCurrentParagraph - 1; $indexRun++) {
                        $amountRunsMerged += $this->mergeCurrentRunWithNext($indexRun) ? 1 : 0;
                    }

                    $amountElementsMerged = $this->mergeRunElements($amountRunsInCurrentParagraph);
                }

                // Update runs in current paragraph w/ merged runs
                $paragraphs[$indexParagraph] = DocxXml::implodeWithGlues($this->runsInCurrentParagraph, $this->runOpenTagsInCurrentParagraph);
            } while ($amountRunsMerged > 0 || $amountElementsMerged > 0);
        }

        $xml = DocxXml::implodeWithGlues($paragraphs, $paragraphOpenTags);

        // Runs of leading and trailing spaces get stripped if xml:space isn’t set to preserve, and are preserved otherwise
        return str_replace(
            ['<w:t>',                                    '<w:instrText>'],
            ['<w:t' . self::STRING_SPACE_PRESERVE . '>', '<w:instrText' . self::STRING_SPACE_PRESERVE . '>'],
            $xml);
    }

    /**
     * Tidy all XML files inside given word DOCX file, save resulting DOCX file overwriting original or given output file
     *
     * @param  string       $docxPath
     * @param  string|null  $outputPath
     * @param  string|null  $removePattern
     * @return bool
     * @throws \DocxTidy\Exception\DirectoryRealPathException
     * @throws \DocxTidy\Exception\DirectoryReadException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \DocxTidy\Exception\FileReadException
     * @throws \DocxTidy\Exception\FileWriteException
     */
    public function tidyDocx($docxPath, $outputPath = null, $removePattern = null)
    {
        $xmlFiles = DocxZip::unzipDocx($docxPath);

        foreach ($xmlFiles as $xmlFile) {
            $xmlContent = file_get_contents($xmlFile);
            if (false === $xmlContent) {
                throw new FileReadException($xmlFile);
            }

            $tidyXml = $this->tidyXml($xmlContent, $removePattern);

            if (false === file_put_contents($xmlFile, $tidyXml)) {
                throw new FileWriteException($xmlFile);
            }
        }

        return DocxZip::zipFilesToDocx($docxPath, $outputPath);
    }

    /**
     * Merge successive run elements (<w:t> or <w:instrText>) within current run (of current paragraph)
     *
     * @param  int $amountRunsInCurrentParagraph
     * @return int
     * @throws \InvalidArgumentException
     */
    protected function mergeRunElements($amountRunsInCurrentParagraph)
    {
        $amountMergedTotal  = 0;
        // Iterate over runs in current paragraph
        for ($indexRun = 0; $indexRun < $amountRunsInCurrentParagraph; $indexRun++) {
            if ($this->runsInCurrentParagraph[$indexRun] !== '') {
                // Non-empty run:
                $elementTagsClosing = DocxXml::preg_split_with_matches(self::PATTERN_ELEMENT_TAG_UNCLOSED, $this->runsInCurrentParagraph[$indexRun], $elementTagsUnclosed);
                /** @var array $elementTagsUnclosed   ex: [<w:pPr,<w:pStyle,                      <w:t,          </w:pPr ] */
                /** @var array $elementTagsClosing    ex: [      >,         w:ascii="Helvetica"/>,    >some text,       >] */

                // Skip if types-list is empty or contains no merge-able types
                if (!self::containsMergeableElements(implode(',', $elementTagsUnclosed))) {
                    continue;
                }

                // Generate array of full element tags
                $elementsInRun = [];
                foreach($elementTagsUnclosed as $index => $lhs) {
                    $elementsInRun []= $lhs . $elementTagsClosing[$index + 1];
                }

                do {
                    $amountMerged = $this->joinElementsUsingUnclosedTagsReference($elementsInRun, $elementTagsUnclosed);
                    $amountMergedTotal += $amountMerged;
                } while($amountMerged > 0 && self::containsMergeableElements(implode(',', $elementTagsUnclosed)));

                $this->runsInCurrentParagraph[$indexRun] = implode('', $elementsInRun);
            }
        }

        return $amountMergedTotal;
    }

    /**
     * Check whether given list of elementTypes might contain any tags that can be merged.
     * The elements of these types are not merge-able if:
     *
     * 1. The list is empty
     * 2. There are contained none or less than two consecutive of any of the handled merge-types
     *
     * @param  array $elementTagsList     comma-separated list of element-tag openings, ex: <w:pPr,<w:pStyle,<w:spacing,...
     * @return bool
     */
    protected static function containsMergeableElements($elementTagsList)
    {
        if ('' === $elementTagsList) {
            return false;
        }

        return strpos($elementTagsList, '</w:t,<w:t') !== false || strpos($elementTagsList, '</w:instrText,<w:instrText') !== false;
    }

    /**
     * Join (<w:t> or <w:instrText> of) given elements that are of the same type in direct succession
     *
     * @param  array &$elementsInRun
     * @param  array &$elementTagsUnclosed
     * @return int
     * @throws \InvalidArgumentException
     */
    protected function joinElementsUsingUnclosedTagsReference(&$elementsInRun, &$elementTagsUnclosed)
    {
        // Iterate over element-types (skip 1st (being rPr) and last (does not have a following element that it could be joined w/)
        $amountElementsInRun = count($elementsInRun);
        $amountMerged        = 0;
        for ($index = 1; $index < $amountElementsInRun; $index++) {
            if(!isset($elementsInRun[$index + 1]) || !$this->areTagsMergeable($elementsInRun[$index], $elementsInRun[$index + 1])) {
                continue;
            }
            $elementsInRun[$index - 1] .= str_replace(['<w:t>', '<w:instrText>'], '', $elementsInRun[$index + 1]);

            $elementsInRun[$index]           = '';
            $elementTagsUnclosed[$index]     = '';

            $elementsInRun[$index + 1]       = '';
            $elementTagsUnclosed[$index + 1] = '';

            $amountMerged++;
            break;
        }

        // Remove empty items (if using php < 5.3, use create_function() instead), keep indexes enumerated
        $elementsInRun       = array_values(array_filter($elementsInRun,       function($value) { return $value !== ''; }));
        $elementTagsUnclosed = array_values(array_filter($elementTagsUnclosed, function($value) { return $value !== ''; }));

        return $amountMerged;
    }

    /**
     * Assert merge-abiliy of given tags
     *
     * 1. Must be not null
     * 2. Must be of same type
     * 3. Must be of supported merge-element types (<w:t> or <w:instrText>)
     * 4. 1st element must be a closing-tag, 2nd element must be an opening tag
     *
     * @param  string $tag1
     * @param  string $tag2
     * @return bool
     * @throws \InvalidArgumentException
     */
    protected function areTagsMergeable($tag1, $tag2)
    {
        if (null === $tag2) {
            return false;
        }
        if (!DocxXml::areTagsOfSameType($tag1, $tag2)) {
            return false;
        }
        if (! (DocxXml::getTagLimitingType($tag1) === DocxXml::TYPE_TAG_LIMITATION_CLOSE
            && DocxXml::getTagLimitingType($tag2) === DocxXml::TYPE_TAG_LIMITATION_OPEN)) {
            // Tags are not in merge-able limiting types order
            return false;
        }
        if (!( in_array(DocxXml::getTypeOfTag($tag1), $this->mergeableTagTypes, true)
            && in_array(DocxXml::getTypeOfTag($tag2), $this->mergeableTagTypes, true))) {
            // Tags aren't merge-able tag types
            return false;
        }

        return true;
    }

    /**
     * If current and next run have identical run-properties (and not none): merge them into only one run
     *
     * @param  int $indexRun
     * @return bool
     * @throws \UnexpectedValueException
     */
    protected function mergeCurrentRunWithNext($indexRun)
    {
        if (!$this->areRunsMergeable($indexRun)) {
            return false;
        }

        // Extract run properties
        $runProperties = DocxXml::preg_match_array($this->runsInCurrentParagraph, self::PATTERN_RUN_PROPERTIES);

        if (count($runProperties) <= 1) {
            return false;
        }

        $runPropertiesCurrent = $runProperties[$indexRun];
        $runPropertiesNext    = $runProperties[$indexRun + 1];

        // 1. Check: within fldChar-scope?
        // 2. Update run-properties of scope (if within scope: fetch / else: set to false)
        if ($this->updateRunPropertiesInFieldCharScope($indexRun)) {
            // Inherit run-properties (from 1st w:t or w:instrText inside current fieldChar-scope)
            $this->runsInCurrentParagraph[$indexRun] = preg_replace(self::PATTERN_RUN_PROPERTIES, $this->runPropertiesInFieldCharScope, $this->runsInCurrentParagraph[$indexRun]);
            $runPropertiesCurrent = $runPropertiesNext = $this->runPropertiesInFieldCharScope;
        }

        if ($runPropertiesCurrent !== $runPropertiesNext && $runPropertiesNext !== null) {
            return false;
        }

        // Following run's run-properties are identical (or inherited while inside fldChar-scope) to current

        // 1. Remove close-tag (</w:r>) of current run
        $this->runsInCurrentParagraph[$indexRun]     = preg_replace(self::PATTERN_RUN_CLOSE,       '', $this->runsInCurrentParagraph[$indexRun]);

        // 2. Remove run-open of next run
        $this->runsInCurrentParagraph[$indexRun + 1] = preg_replace(self::PATTERN_RUN_OPEN,       '', $this->runsInCurrentParagraph[$indexRun + 1]);
        // 3. Remove run-properties of next run
        $this->runsInCurrentParagraph[$indexRun + 1] = preg_replace(self::PATTERN_RUN_PROPERTIES, '', $this->runsInCurrentParagraph[$indexRun + 1]);

        // 4. Move the two merged runs into the 2nd one of them, so it can be compared/merged w/ its successor
        $this->runsInCurrentParagraph[$indexRun + 1] = $this->runsInCurrentParagraph[$indexRun] . $this->runsInCurrentParagraph[$indexRun + 1];
        $this->runsInCurrentParagraph[$indexRun]     = '';

        // 5. Remove open-tag of next run(openTag @note index is 1 less than inside runs-array)
        $this->runOpenTagsInCurrentParagraph[$indexRun] = '';

        return true;
    }

    /**
     * Assert merge-ability of runs (at given index and following one)
     *
     * @param  int  $index
     * @return bool
     */
    private function areRunsMergeable($index)
    {
        // Ensure 1st item ends w/ closing tag of run
        if (substr($this->runsInCurrentParagraph[$index], -$this->lengthTagRunClose) !== self::STRING_TAG_RUN_CLOSE) {
            return false;
        }

        // Keep fldChar-scope runs (=from fldCharType="begin" to fldCharType="end") in one exclusive run
        $nextRunStartsFieldCharScope  = strpos($this->runsInCurrentParagraph[$index + 1], self::STRING_FLDCHAR_TYPE_BEGIN) !== false;
        $currentRunEndsFieldCharScope = strpos($this->runsInCurrentParagraph[$index],     self::STRING_FLDCHAR_TYPE_END) !== false;

        return !$nextRunStartsFieldCharScope && !$currentRunEndsFieldCharScope;
    }

    /**
     * Fetch and store run-properties to be used on all elements of current fldChar scope (all elements bordered by fldCharacterType="begin" / fldCharacterType="end")
     *
     * @param  int $index
     * @return bool         Is within fldChar-scope (and did update run-properties at given index)?
     * @throws \UnexpectedValueException
     */
    protected function updateRunPropertiesInFieldCharScope($index)
    {
        if (!$this->updateIsWithinFieldCharScope($index)) {
            return false;
        }

        // Inherit run-properties of fldChar-scope (unless scope spans only this sole run)
        if ($this->isWithinFieldCharScope && !$this->isFieldCharScopeEndingInCurrentRun) {
            if ($this->runPropertiesInFieldCharScope === false) {
                $this->runPropertiesInFieldCharScope = $this->getRunPropertiesOfFieldCharScope($index);
            }
            if ($this->runPropertiesInFieldCharScope === false) {
                throw new \UnexpectedValueException('No w:t or w:instrText tag found in paragraph after fldCharType="begin"');
            }

            // Inherit run-properties (from 1st w:t or w:instrText inside current fieldChar-scope)
            if (null === $this->runsInCurrentParagraph[$index]) {
                throw new \UnexpectedValueException('Failed replace run-properties in: ' . $this->runsInCurrentParagraph[$index]);
            }

            return true;
        }

        return false;
    }

    /**
     * Detect whether parsing-offset enters/leaves scope of fldChar elements (all elements bordered by fldCharacterType="begin" / fldCharacterType="end")
     *
     * @param  int  $indexRun
     * @return bool             Is $this->runsInCurrentParagraph[$indexRun] within a w:fldChar-scope (fldCharType="begin" until fldCharType="end")?
     */
    public function updateIsWithinFieldCharScope($indexRun)
    {
        if (!$this->isWithinFieldCharScope) {
            // Detect entering field character scope
            $this->isWithinFieldCharScope = strpos($this->runsInCurrentParagraph[$indexRun], self::STRING_FLDCHAR_TYPE_BEGIN) !== false;
        }
        if (!$this->isWithinFieldCharScope) {
            $this->runPropertiesInFieldCharScope = false;

            return false;
        }

        // While inside: detect end of fldChar-scope
        $this->isFieldCharScopeEndingInCurrentRun = strpos($this->runsInCurrentParagraph[$indexRun], self::STRING_FLDCHAR_TYPE_END) !== false;

        if ($this->isFieldCharScopeEndingInCurrentRun) {
            $this->isWithinFieldCharScope        = false;
            $this->runPropertiesInFieldCharScope = false;
        }

        return $this->isWithinFieldCharScope;
    }

    /**
     * Get run-properties to be used on all elements of current fldChar scope (all elements bordered by fldCharacterType="begin" / fldCharacterType="end")
     *
     * @param  int      $indexStart
     * @param  string   $patternTagRunPropertiesSource
     * @return bool|string
     */
    protected function getRunPropertiesOfFieldCharScope($indexStart, $patternTagRunPropertiesSource = null)
    {
        if (null === $patternTagRunPropertiesSource) {
            // Default cycle: look for <w:t> to take run-properties from
            $patternTagRunPropertiesSource = self::STRING_TAG_W_TEXT_OPEN;
        }

        $amountRunsInParagraph = count($this->runsInCurrentParagraph);
        for ($index = $indexStart; $index < $amountRunsInParagraph; $index++) {
            // Seek next <w:t> Tag
            if (strpos($this->runsInCurrentParagraph[$index], $patternTagRunPropertiesSource) !== false) {
                // Set runPropertiesCurrent = <w:rPr> of <w:t>
                preg_match(self::PATTERN_RUN_PROPERTIES, $this->runsInCurrentParagraph[$index], $runProperties);
                return empty($runProperties) ? '' : $runProperties[0];
            }

            if (strpos($this->runsInCurrentParagraph[$index], self::STRING_FLDCHAR_TYPE_END) !== false) {
                break;
            }
        }

        // If default cycle failed: look for <w:instrText> to take run-properties from
        return $this->runPropertiesInFieldCharScope === false && $patternTagRunPropertiesSource === self::STRING_TAG_W_TEXT_OPEN
            ? $this->getRunPropertiesOfFieldCharScope($indexStart, self::STRING_TAG_W_INSTR_TEXT_OPEN)
            : false;
    }
}