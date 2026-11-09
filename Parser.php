<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown;

use ReflectionMethod;

/**
 * A generic parser for markdown-like languages.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
abstract class Parser
{
    /**
     * @var integer the maximum nesting level for language elements.
     */
    public $maximum_nesting_level = 32;

    /**
     * @var array the current context the parser is in.
     * TODO remove in favor of absy
     */
    protected $context = [];
    /**
     * @var array these are "escapeable" characters. When using one of these prefixed with a
     * backslash, the character will be outputted without the backslash and is not interpreted
     * as markdown.
     */
    protected $escape_Characters = [
        '\\', // backslash
    ];

    private $_depth = 0;

    /**
     * Parses the given text considering the full language.
     *
     * This includes parsing block elements as well as inline elements.
     *
     * @param string $text the text to parse
     * @return string parsed markup
     */
    public function parse($text)
    {
        $this->prepare();

        if (ltrim($text) === '') {
            return '';
        }

        $text = str_replace(["\r\n", "\n\r", "\r"], "\n", $text);

        $this->prepare_Markers($text);

        $absy = $this->parse_Blocks(explode("\n", $text));
        $markup = $this->render_Absy($absy);

        $this->cleanup();
        return $markup;
    }

    /**
     * Parses a paragraph without block elements (block elements are ignored).
     *
     * @param string $text the text to parse
     * @return string parsed markup
     */
    public function parse_Paragraph($text)
    {
        $this->prepare();

        if (ltrim($text) === '') {
            return '';
        }

        $text = str_replace(["\r\n", "\n\r", "\r"], "\n", $text);

        $this->prepare_Markers($text);

        $absy = $this->parse_Inline($text);
        $markup = $this->render_Absy($absy);

        $this->cleanup();
        return $markup;
    }

    /**
     * This method will be called before `parse()` and `parse_Paragraph()`.
     * You can override it to do some initialization work.
     */
    protected function prepare()
    {
    }

    /**
     * This method will be called after `parse()` and `parse_Paragraph()`.
     * You can override it to do cleanup.
     */
    protected function cleanup()
    {
    }

    // block parsing

    private $_block_types;

    /**
     * @return array a list of block element types available.
     */
    protected function block_Types()
    {
        if ($this->_block_types === null) {
            // detect block types via "identify" functions
            $reflection = new \ReflectionClass($this);
            $this->_block_types = array_filter(array_map(function ($method) {
                $name = $method->getName();
                return strncmp($name, 'identify_', 9) === 0 ? strtolower(substr($name, 9)) : false;
            }, $reflection->getMethods(ReflectionMethod::IS_PROTECTED)));

            sort($this->_block_types);
        }
        return $this->_block_types;
    }

    /**
     * Given a set of lines and an index of a current line it uses the registed block types to
     * detect the type of this line.
     * @param integer $current
     * @return string name of the block type in lower case
     */
    protected function detect_Line_Type($lines, $current)
    {
        $line = $lines[$current];
        $block_Types = $this->block_Types();
        foreach ($block_Types as $blockType) {
            if ($this->{'identify_' . $blockType}($line, $lines, $current)) {
                return $blockType;
            }
        }
        // consider the line a normal paragraph if no other block type matches
        return 'paragraph';
    }

    /**
     * Parse block elements by calling `detect_Line_Type()` to identify them
     * and call consume function afterwards.
     */
    protected function parse_Blocks($lines)
    {
        if ($this->_depth >= $this->maximum_nesting_level) {
            // maximum depth is reached, do not parse input
            return [['text', implode("\n", $lines)]];
        }
        $this->_depth++;

        $blocks = [];

        // convert lines to blocks
        for ($i = 0, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if ($line !== '' && rtrim($line) !== '') { // skip empty lines
                // identify a blocks beginning and parse the content
                list($block, $i) = $this->parse_Block($lines, $i);
                if ($block !== false) {
                    $blocks[] = $block;
                }
            }
        }

        $this->_depth--;

        return $blocks;
    }

    /**
     * Parses the block at current line by identifying the block type and parsing the content
     * @param $lines
     * @param $current
     * @return array Array of two elements, the first element contains the block,
     * the second contains the next line index to be parsed.
     */
    protected function parse_Block($lines, $current)
    {
        // identify block type for this line
        $blockType = $this->detect_Line_Type($lines, $current);

        // call consume method for the detected block type to consume further lines
        return $this->{'consume_' . $blockType}($lines, $current);
    }

    protected function render_Absy($blocks)
    {
        $output = '';
        foreach ($blocks as $block) {
            array_unshift($this->context, $block[0]);
            $output .= $this->{'render_' . $block[0]}($block);
            array_shift($this->context);
        }
        return $output;
    }

    /**
     * Consume lines for a paragraph
     *
     * @param $lines
     * @param $current
     * @return array
     */
    protected function consume_Paragraph($lines, $current)
    {
        // consume until newline
        $content = [];
        for ($i = $current, $count = count($lines); $i < $count; $i++) {
            if (ltrim($lines[$i]) !== '') {
                $content[] = $lines[$i];
            } else {
                break;
            }
        }
        $block = [
            'paragraph',
            'content' => $this->parse_Inline(implode("\n", $content)),
        ];
        return [$block, --$i];
    }

    /**
     * Render a paragraph block
     *
     * @param $block
     * @return string
     */
    protected function render_Paragraph($block)
    {
        return '<p>' . $this->render_Absy($block['content']) . "</p>\n";
    }

    // inline parsing

    /**
     * @var array the set of inline markers to use in different contexts.
     */
    private $_inline_Markers = [];

    /**
     * Returns a map of inline markers to the corresponding parser methods.
     *
     * This array defines handler methods for inline markdown markers.
     * When a marker is found in the text, the handler method is called with the text
     * starting at the position of the marker.
     *
     * Note that markers starting with whitespace may slow down the parser,
     * you may want to use [[render_Text]] to deal with them.
     *
     * You may override this method to define a set of markers and parsing methods.
     * The default implementation looks for protected methods starting with `parse` that
     * also have an `@marker` annotation in PHPDoc.
     *
     * @return array a map of markers to parser methods
     */
    protected function inline_Markers()
    {
        $markers = [];
        // detect "parse" functions
        $reflection = new \ReflectionClass($this);
        foreach ($reflection->getMethods(ReflectionMethod::IS_PROTECTED) as $method) {
            $methodName = $method->getName();
            if (strncmp($methodName, 'parse', 5) === 0) {
                $docComment = $method->getDocComment();
                if ($docComment === false) {
                    continue;
                }
                preg_match_all('/@marker ([^\s]+)/', $docComment, $matches);
                foreach ($matches[1] as $match) {
                    $markers[$match] = $methodName;
                }
            }
        }
        return $markers;
    }

    /**
     * Prepare markers that are used in the text to parse
     *
     * Add all markers that are present in markdown.
     * Check is done to avoid iterations in parse_Inline(), good for huge markdown files
     * @param string $text
     */
    protected function prepare_Markers($text)
    {
        $this->_inline_Markers = [];
        foreach ($this->inline_Markers() as $marker => $method) {
            if (strpos($text, $marker) !== false) {
                $m = $marker[0];
                // put the longest marker first
                if (isset($this->_inline_Markers[$m])) {
                    reset($this->_inline_Markers[$m]);
                    if (strlen($marker) > strlen(key($this->_inline_Markers[$m]))) {
                        $this->_inline_Markers[$m] = array_merge([$marker => $method], $this->_inline_Markers[$m]);
                        continue;
                    }
                }
                $this->_inline_Markers[$m][$marker] = $method;
            }
        }
    }

    /**
     * Parses inline elements of the language.
     *
     * @param string $text the inline text to parse.
     * @return array
     */
    protected function parse_Inline($text)
    {
        if ($this->_depth >= $this->maximum_nesting_level) {
            // maximum depth is reached, do not parse input
            return [['text', $text]];
        }
        $this->_depth++;

        $markers = implode('', array_keys($this->_inline_Markers));

        $paragraph = [];

        while (!empty($markers) && ($found = strpbrk($text, $markers)) !== false) {

            $pos = strpos($text, $found);

            // add the text up to next marker to the paragraph
            if ($pos !== 0) {
                $paragraph[] = ['text', substr($text, 0, $pos)];
            }
            $text = $found;

            $parsed = false;
            foreach ($this->_inline_Markers[$text[0]] as $marker => $method) {
                if (strncmp($text, $marker, strlen($marker)) === 0) {
                    // parse the marker
                    array_unshift($this->context, $method);
                    list($output, $offset) = $this->$method($text);
                    array_shift($this->context);

                    $paragraph[] = $output;
                    $text = substr($text, $offset);
                    $parsed = true;
                    break;
                }
            }
            if (!$parsed) {
                $paragraph[] = ['text', substr($text, 0, 1)];
                $text = substr($text, 1);
            }
        }

        $paragraph[] = ['text', $text];

        $this->_depth--;

        return $paragraph;
    }

    /**
     * Parses escaped special characters.
     * @marker \
     */
    protected function parse_Escape($text)
    {
        if (isset($text[1]) && in_array($text[1], $this->escape_Characters)) {
            return [['text', $text[1]], 2];
        }
        return [['text', $text[0]], 1];
    }

    /**
     * This function renders plain text sections in the markdown text.
     * It can be used to work on normal text sections for example to highlight keywords or
     * do special escaping.
     */
    protected function render_Text($block)
    {
        return $block[1];
    }
}
