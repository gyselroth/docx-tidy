<?php

/**
 * DocxTidy - Docx XML manipulation utility methods
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

class DocxXml {

    // "Limiting" type of tag: opening / close / neither of those
    const TYPE_TAG_LIMITATION_NONE    = 0;
    const TYPE_TAG_LIMITATION_OPEN = 1;
    const TYPE_TAG_LIMITATION_CLOSE = 2;

    const PATTERN_ELEMENT_TAG_UNCLOSED = '/<(\/)?(w:[a-z]+)/i';

    /**
     * @param  string $pattern
     * @param  string $subject
     * @param  array  &$matches
     * @return array
     * @note   First item of result array wasn't necessarily prefixed w/ given pattern before split
     */
    public static function preg_split_with_matches($pattern, $subject, &$matches)
    {
        preg_match_all($pattern, $subject, $matches);
        $matches = array_shift($matches);

        return preg_split($pattern, $subject);
    }

    /**
     * Non by-reference preg_match_all wrapper, allowing for easier error detection
     *
     * @param  string $pattern
     * @param  string $subject
     * @param  bool   $returnOnlyFullMatches    Default: returns all matches, including those of sub-expressions
     * @return array
     */
    public static function getAllPregMatches($pattern, $subject, $returnOnlyFullMatches = false)
    {
        $amountMatches = preg_match_all($pattern, $subject, $matches);
        if ($amountMatches === false) {
            die('Error in regular expression.');
        }

        return $returnOnlyFullMatches ? $matches[0] : $matches;
    }

    /**
     * Implode given array with multiple given glues
     *
     * @param  array $pieces i.e. ex1: [w:p, w:rPr, w:rPr, ...] or ex2: [w:p]
     * @param  array $glues  i.e. ex1: [w:r, w:r]               or ex2: []
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function implodeWithGlues($pieces, $glues)
    {
        if (count($glues) === 0) {
            return implode('', $pieces);
        }

        if (count($pieces) === 0) {
            return '';
        }

        $result = '';
        foreach ($pieces as $index => $piece) {
            if (is_array($piece)) {
                throw new \InvalidArgumentException('Pieces must be an array of strings');
            }

            $result .= $pieces[$index];
            $result .= isset($glues[$index]) ? $glues[$index] : '';
        }

        return $result;
    }

    /**
     * Perform preg_match on all items of given subjects(-array) using given preg_ex pattern, return collected full-matches (no sub-pattern matches)
     *
     * @param  array  $subjects
     * @param  string $pattern
     * @return array
     */
    public static function preg_match_array(array $subjects, $pattern)
    {
        $matches = [];
        foreach ($subjects as $index => $item) {
            preg_match($pattern, $item, $itemMatches);
            $matches[] = count($itemMatches) > 1 ? $itemMatches[0] : null;
        }

        return $matches;
    }

    /**
     * @param  string $tag1
     * @param  string $tag2
     * @return bool
     * @throws \InvalidArgumentException
     */
    public static function areTagsOfSameType($tag1, $tag2)
    {
        return self::getTypeOfTag($tag1) === self::getTypeOfTag($tag2);
    }

    /**
     * Determine "limiting" type of given tag- open / close / neither of those
     *
     * @param  string $tag
     * @return int
     */
    public static function getTagLimitingType($tag)
    {
        if (strpos($tag, '<w:') === 0) {
            return self::TYPE_TAG_LIMITATION_OPEN;
        }
        if (strpos($tag, '</w:') === 0) {
            return self::TYPE_TAG_LIMITATION_CLOSE;
        }

        return self::TYPE_TAG_LIMITATION_NONE;
    }

    /**
     * @param  string       $tag
     * @return string|bool  Tag type e.g. 'w:t' or 'w:instrText', no matter whether it is a '<w:...' or '</w:...' tag
     * @throws \InvalidArgumentException
     */
    public static function getTypeOfTag($tag)
    {
        if (!is_string($tag)) {
            throw new \InvalidArgumentException('Tag type identification failed. Tag must be string. Given: ' . $tag);
        }

        $amountMatches = preg_match(self::PATTERN_ELEMENT_TAG_UNCLOSED, $tag, $matches);

        if (null === $amountMatches || empty($matches[2])) {
            throw new \InvalidArgumentException('Tag type identification failed. Argument: ' . $tag);
        }

        return $matches[2];
    }

}