<?php

/**
 * This file is part of the DocxTidy package.
 *
 * Docx XML manipulation utility methods
 *
 * Copyright (c) 2017 gyselroth™  (http://www.gyselroth.com)
 *
 * @package DocxTidy
 * @link    https://github.com/gyselroth/docx-tidy
 * @author  gyselroth™  (http://www.gyselroth.com)
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache 2.0 license
 */

namespace DocxTidy\Util;

class DocxXml {

    // "Limiting" type of tag: opening / close / neither of those
    public const TYPE_TAG_LIMITATION_OPEN  = 1;
    public const TYPE_TAG_LIMITATION_CLOSE = 2;
    private const TYPE_TAG_LIMITATION_NONE = 0;

    private const PATTERN_ELEMENT_TAG_UNCLOSED = '/<(\/)?(w:[a-z]+)/i';

    /**
     * @param  string   $pattern
     * @param  string   $subject
     * @param  array    &$matches
     * @return array
     * @throws \InvalidArgumentException
     * @note   First item of result array wasn't necessarily prefixed w/ given pattern before split
     */
    public static function preg_split_with_matches(string $pattern, string $subject, &$matches): array
    {
        $amountMatches = preg_match_all($pattern, $subject, $matches);
        if (false === $amountMatches) {
            throw new \InvalidArgumentException('preg_match_all failed - ' . 'pattern: ' . $pattern . ', subject: ' . $subject);
        }

        if ([] !== $matches) {
            /** @noinspection ReturnNullInspection */
            $matches = array_shift($matches);
        }

        return preg_split($pattern, $subject);
    }

    /**
     * Non by-reference preg_match_all wrapper, allowing for easier error detection
     *
     * @param  string $pattern
     * @param  string $subject
     * @param  bool   $returnOnlyFullMatches Default: returns all matches, including those of sub-expressions
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function getAllPregMatches(string $pattern, string $subject, bool $returnOnlyFullMatches = false): array
    {
        $amountMatches = preg_match_all($pattern, $subject, $matches);
        if (false === $amountMatches) {
            throw new \InvalidArgumentException('Error in regular expression. - ' . 'pattern: ' . $pattern . ', subject: ' . $subject);
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
    public static function implodeWithGlues(array $pieces, array $glues): string
    {
        if (0 === \count($glues)) {
            return implode('', $pieces);
        }
        if (0 === \count($pieces)) {
            return '';
        }

        $result = '';
        foreach ($pieces as $index => $piece) {
            if (\is_array($piece)) {
                throw new \InvalidArgumentException('Pieces must be an array of strings');
            }

            $result .= $pieces[$index];
            $result .= $glues[$index] ?? '';
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
    public static function preg_match_array(array $subjects, string $pattern): array
    {
        $matches = [];
        foreach ($subjects as $index => $item) {
            preg_match($pattern, $item, $itemMatches);
            $matches[] = \count($itemMatches) > 1 ? $itemMatches[0] : null;
        }

        return $matches;
    }

    /**
     * @param  string $tag1
     * @param  string $tag2
     * @return bool
     * @throws \InvalidArgumentException
     */
    public static function areTagsOfSameType(string $tag1, string $tag2): bool
    {
        return self::getTypeOfTag($tag1) === self::getTypeOfTag($tag2);
    }

    /**
     * Determine "limiting" type of given tag- open / close / neither of those
     *
     * @param  string $tag
     * @return int
     */
    public static function getTagLimitingType(string $tag): int
    {
        /** @noinspection ReturnFalseInspection */
        if (0 === strpos($tag, '<w:')) {
            return self::TYPE_TAG_LIMITATION_OPEN;
        }
        /** @noinspection ReturnFalseInspection */
        if (0 === strpos($tag, '</w:')) {
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
        if (!\is_string($tag)) {
            throw new \InvalidArgumentException('Tag type identification failed. Tag must be string. Given: ' . $tag);
        }

        $amountMatches = preg_match(self::PATTERN_ELEMENT_TAG_UNCLOSED, $tag, $matches);
        if (null === $amountMatches || empty($matches[2])) {
            throw new \InvalidArgumentException('Tag type identification failed. Argument: ' . $tag);
        }

        return $matches[2];
    }

}