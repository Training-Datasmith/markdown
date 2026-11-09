<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\tests;

use cebe\markdown\Markdown;

/**
 * Test case for traditional markdown.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @group default
 */
class Markdown_Test extends Base_Markdown_Test
{
    public function create_Markdown()
    {
        return new Markdown();
    }

    public function get_Data_Paths()
    {
        return [
            'markdown-data' => __DIR__ . '/markdown-data',
        ];
    }

    public function test_Edge_Cases()
    {
        $this->assertEquals("<p>&amp;</p>\n", $this->create_Markdown()->parse('&'));
        $this->assertEquals("<p>&lt;</p>\n", $this->create_Markdown()->parse('<'));
    }

    public function test_Keep_Zero_Alive()
    {
        $parser = $this->create_Markdown();

        $this->assertEquals('0', $parser->parse_Paragraph('0'));
        $this->assertEquals("<p>0</p>\n", $parser->parse('0'));
    }

    public function test_Auto_Link_Labeling_With_Encoded_Url()
    {
        $parser = $this->create_Markdown();

        $utf_Text = "\xe3\x81\x82\xe3\x81\x84\xe3\x81\x86\xe3\x81\x88\xe3\x81\x8a";
        $utf_Natural_Url = 'http://example.com/' . $utf_Text;
        $utf_Encoded_Url = 'http://example.com/' . urlencode($utf_Text);
        $euc_Encoded_Url = 'http://example.com/' . urlencode(mb_convert_encoding($utf_Text, 'EUC-JP', 'UTF-8'));

        $this->assertStringEndsWith(">{$utf_Natural_Url}</a>", $parser->parse_Paragraph("<{$utf_Natural_Url}>"), 'Natural UTF-8 URL needs no conversion.');
        $this->assertStringEndsWith(">{$utf_Natural_Url}</a>", $parser->parse_Paragraph("<{$utf_Encoded_Url}>"), 'Encoded UTF-8 URL will be converted to readable format.');
        $this->assertStringEndsWith(">{$euc_Encoded_Url}</a>", $parser->parse_Paragraph("<{$euc_Encoded_Url}>"), 'Non UTF-8 URL should never be converted.');
        // See: \cebe\markdown\inline\Link_Trait::render_Url
    }
}
