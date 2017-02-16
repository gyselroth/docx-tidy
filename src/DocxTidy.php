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
 * @version 0.2.0
 * @link    https://github.com/gyselroth/docx-tidy
 * @author  gyselroth™  (http://www.gyselroth.com)
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache 2.0 license
 */

namespace DocxTidy;

use DocxTidy\Util\DocxXml;

class DocxTidy
{
    // Regular expressions matching (word) XML elements
    const PATTERN_PARAGRAPH_OPEN       = '/<w:p .*?>/i';
    const PATTERN_RUN_OPEN             = '/<w:r(\w){0}( .*?)?>/i';
    const PATTERN_RUN_CLOSE            = '/<\/w:r>/i';
    const PATTERN_RUN_PROPERTIES       = '/<w:rPr>.*?(<\/w:rPr>)/i';
    const PATTERN_ELEMENT_TAG_UNCLOSED = '/<(\/)?w:[a-z]+/i';

    // Items which will be removed by default from the whole XML
    const PATTERN_LANG            = '<w:lang w:val="[a-z|-]{2,5}"\/>';
    const PATTERN_NO_PROOF        = '<w:noProof\/>';
    const PATTERN_PROOF_ERR       = '<w:proofErr w:type="\w+"\/>';

    const STRING_SPACE_PRESERVE  = ' xml:space="preserve"';

    /** @var array  Array of content of runs (w/o run-opening tag) */
    private $runsInCurrentParagraph;

    /** @var array  Opening tags of runs (which the current paragraph was exploded by) */
    private $runOpenTagsInCurrentParagraph;

    private $mergeableTagTypes = ['w:t', 'w:instrText'];

    /**
     * Constructor
     */
    public function __construct()
    {

    }

    /**
     * Find and merge successive runs w/ identical runProperties within paragraphs.
     * Within each run: merge successive run elements (<w:t> or <w:instrText>)
     *
     * @param  string       $xml
     * @param  array|string $removePattern
     * @return string
     * @throws \InvalidArgumentException
     */
    public function tidy($xml, $removePattern = null)
    {
        if ($removePattern !== false) {
            if ($removePattern === null) {
                // Default: remove spell-check flags
                $removePattern = '/' . self::PATTERN_NO_PROOF . '|' . self::PATTERN_PROOF_ERR . '|' . self::PATTERN_LANG . '/i';
            }
            $xml = preg_replace($removePattern, '', $xml);
        }

        // Remove all space preserve occurrences as they will be added into the paragraph later
        $xml = str_replace(self::STRING_SPACE_PRESERVE, '', $xml);

        // 1. Collect paragraphs, tidy each paragraph:
        $paragraphs       = DocxXml::preg_split_with_matches(self::PATTERN_PARAGRAPH_OPEN, $xml, $paragraphOpenTags);
        $amountParagraphs = count($paragraphs);
        for ($indexParagraph = 1; $indexParagraph < $amountParagraphs; $indexParagraph++) {
            // First item is XML and document meta data, NOT a paragraph
            do {
                $amountRunsMerged     = 0;
                $amountElementsMerged = 0;

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

                    // Update runs in current paragraph w/ merged runs
                    $paragraphs[$indexParagraph] = DocxXml::implodeWithGlues($this->runsInCurrentParagraph, $this->runOpenTagsInCurrentParagraph);
                }
            } while ($amountRunsMerged > 0 || $amountElementsMerged > 0);
        }

        $xml = DocxXml::implodeWithGlues($paragraphs, $paragraphOpenTags);

        // Runs of leading and trailing spaces get stripped if xml:space isn’t set to preserve, and are preserved otherwise
        return str_replace(
            ['<w:t>',                  '<w:instrText>'],
            ['<w:t space="preserve">', '<w:instrText space="preserve">'],
            $xml);
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
            if(!$this->areTagsMergeable($elementsInRun[$index], $elementsInRun[$index + 1])) {
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
     */
    protected function mergeCurrentRunWithNext($indexRun)
    {
        // Extract run properties
        $runProperties = DocxXml::preg_match_array($this->runsInCurrentParagraph, self::PATTERN_RUN_PROPERTIES);

        if (count($runProperties) <= 1) {
            return false;
        }

        $runPropertiesCurrent = $runProperties[$indexRun];
        $runPropertiesNext    = $runProperties[$indexRun + 1];

        if (!$runPropertiesCurrent || $runPropertiesCurrent !== $runPropertiesNext) {
            return false;
        }

        // Following run's Run-properties are identical to current
        // Remove: 1. close-tag of current run, 2. open-tag of next run, 3. run-properties of next run
        $this->runsInCurrentParagraph[$indexRun]     = preg_replace(self::PATTERN_RUN_CLOSE, '', $this->runsInCurrentParagraph[$indexRun]);
        $this->runsInCurrentParagraph[$indexRun + 1] = preg_replace(self::PATTERN_RUN_OPEN, '', $this->runsInCurrentParagraph[$indexRun + 1]);
        $this->runsInCurrentParagraph[$indexRun + 1] = preg_replace(self::PATTERN_RUN_PROPERTIES, '', $this->runsInCurrentParagraph[$indexRun + 1]);

        // Move the two merged runs into the 2nd of them, so it can be compared/merged w/ its successor
        $this->runsInCurrentParagraph[$indexRun + 1] = $this->runsInCurrentParagraph[$indexRun] . $this->runsInCurrentParagraph[$indexRun + 1];
        $this->runsInCurrentParagraph[$indexRun]     = '';

        // Remove run open-tag of merged run
        $this->runOpenTagsInCurrentParagraph[$indexRun] = '';

        return true;
    }
}