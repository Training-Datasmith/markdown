<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\tests;

use cebe\markdown\Parser;

/**
 * Test case for the parser base class.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @group default
 */
class Parser_Test extends \PHPUnit\Framework\TestCase
{
    public function test_marker_order()
    {
        $parser = new Test_Parser();
        $parser->markers = [
            '[' => 'parse_marker_a',
            '[[' => 'parse_marker_b',
        ];

        $this->assertEquals("<p>Result is A</p>\n", $parser->parse('Result is [abc]'));
        $this->assertEquals("<p>Result is B</p>\n", $parser->parse('Result is [[abc]]'));
        $this->assertEquals('Result is A', $parser->parse_paragraph('Result is [abc]'));
        $this->assertEquals('Result is B', $parser->parse_paragraph('Result is [[abc]]'));

        $parser = new Test_Parser();
        $parser->markers = [
            '[[' => 'parse_marker_b',
            '[' => 'parse_marker_a',
        ];

        $this->assertEquals("<p>Result is A</p>\n", $parser->parse('Result is [abc]'));
        $this->assertEquals("<p>Result is B</p>\n", $parser->parse('Result is [[abc]]'));
        $this->assertEquals('Result is A', $parser->parse_paragraph('Result is [abc]'));
        $this->assertEquals('Result is B', $parser->parse_paragraph('Result is [[abc]]'));
    }

    public function test_max_nesting_level()
    {
        $parser = new Test_Parser();
        $parser->markers = [
            '[' => 'parse_marker_c',
        ];

        $parser->maximum_nesting_level = 3;
        $this->assertEquals('(C-a(C-b(C-c)))', $parser->parse_paragraph('[a[b[c]]]'));
        $parser->maximum_nesting_level = 2;
        $this->assertEquals('(C-a(C-b[c]))', $parser->parse_paragraph('[a[b[c]]]'));
        $parser->maximum_nesting_level = 1;
        $this->assertEquals('(C-a[b[c]])', $parser->parse_paragraph('[a[b[c]]]'));
    }

    public function test_keep_zero_alive()
    {
        $parser = new Test_Parser();

        $this->assertEquals('0', $parser->parse_paragraph('0'));
        $this->assertEquals("<p>0</p>\n", $parser->parse('0'));
    }
}

class Test_Parser extends Parser
{
    public $markers = [];

    protected function inline_markers()
    {
        return $this->markers;
    }

    protected function parse_marker_a($text)
    {
        return [['text', 'A'], strrpos($text, ']') + 1];
    }

    protected function parse_marker_b($text)
    {
        return [['text', 'B'], strrpos($text, ']') + 1];
    }

    protected function parse_marker_c($text)
    {
        $terminating_marker_pos = strrpos($text, ']');
        $inside = $this->parse_inline(substr($text, 1, $terminating_marker_pos - 1));
        return [['text', '(C-' . $this->render_absy($inside) . ')'], $terminating_marker_pos + 1];
    }
}
