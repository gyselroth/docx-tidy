<?php

/**
 * This file is part of the DocxTidy package.
 *
 * Copyright (c) 2017-2018 gyselroth™  (http://www.gyselroth.com)
 *
 * @package DocxTidy
 * @link    https://github.com/gyselroth/docx-tidy
 * @author  gyselroth™  (http://www.gyselroth.com)
 * @license https://www.apache.org/licenses/LICENSE-2.0 Apache 2.0 license
 */

namespace DocxTidy\Mergeable;

use DocxTidy\Util\DocxXml;

class TagTupleMergeable
{
    /** @var array */
    private const MERGEABLE_TAG_TYPES = ['w:t', 'w:instrText'];

    /** @var string|null */
    private $tag1;

    /** @var string|null */
    private $tag2;

    /**
     * Constructor
     *
     * @param string|null $tag1
     * @param string|null $tag2
     */
    public function __construct($tag1, $tag2)
    {
        $this->tag1 = $tag1;
        $this->tag2 = $tag2;
    }

    /**
     * Assert merge-abiliy of given tags
     *
     * 1. Must be not null
     * 2. Must be of same type
     * 3. Must be of supported merge-element types (<w:t> or <w:instrText>)
     * 4. 1st element must be a closing-tag, 2nd element must be an opening tag
     *
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function isMergeable(): bool
    {
        return null !== $this->tag2
            && DocxXml::areTagsOfSameType($this->tag1, $this->tag2)
            && $this->areTagLimitingTypesInMergeableOrder()
            && $this->areTagTypesMergeable();
    }

    /**
     * @return bool
     * @throws \InvalidArgumentException
     */
    private function areTagTypesMergeable(): bool
    {
        return \in_array(DocxXml::getTypeOfTag($this->tag1), self::MERGEABLE_TAG_TYPES, true)
            && \in_array(DocxXml::getTypeOfTag($this->tag2), self::MERGEABLE_TAG_TYPES, true);
    }

    /**
     * Are limiting-types of tags in merge-able order?
     *
     * @return bool
     */
    private function areTagLimitingTypesInMergeableOrder(): bool
    {
        return DocxXml::TYPE_TAG_LIMITATION_CLOSE === DocxXml::getTagLimitingType($this->tag1)
            && DocxXml::TYPE_TAG_LIMITATION_OPEN === DocxXml::getTagLimitingType($this->tag2);
    }
}
