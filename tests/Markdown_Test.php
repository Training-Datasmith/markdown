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
    public function create_markdown()
    {
        return new Markdown();
    }

    public function get_data_paths()
    {
        return [
            'markdown-data' => __DIR__ . '/markdown-data',
        ];
    }

    public function test_edge_cases()
    {
        $this->assertEquals("<p>&amp;</p>\n", $this->create_markdown()->parse('&'));
        $this->assertEquals("<p>&lt;</p>\n", $this->create_markdown()->parse('<'));
    }

    public function test_keep_zero_alive()
    {
        $parser = $this->create_markdown();

        $this->assertEquals('0', $parser->parse_paragraph('0'));
        $this->assertEquals("<p>0</p>\n", $parser->parse('0'));
    }

    public function test_auto_link_labeling_with_encoded_url()
    {
        $parser = $this->create_markdown();

        $utf_text = "\xe3\x81\x82\xe3\x81\x84\xe3\x81\x86\xe3\x81\x88\xe3\x81\x8a";
        $utf_natural_url = 'http://example.com/' . $utf_text;
        $utf_encoded_url = 'http://example.com/' . urlencode($utf_text);
        $euc_encoded_url = 'http://example.com/' . urlencode(mb_convert_encoding($utf_text, 'EUC-JP', 'UTF-8'));

        $this->assertStringEndsWith(">{$utf_natural_url}</a>", $parser->parse_paragraph("<{$utf_natural_url}>"), 'Natural UTF-8 URL needs no conversion.');
        $this->assertStringEndsWith(">{$utf_natural_url}</a>", $parser->parse_paragraph("<{$utf_encoded_url}>"), 'Encoded UTF-8 URL will be converted to readable format.');
        $this->assertStringEndsWith(">{$euc_encoded_url}</a>", $parser->parse_paragraph("<{$euc_encoded_url}>"), 'Non UTF-8 URL should never be converted.');
        // See: \cebe\markdown\inline\Link_Trait::render_url
    }
}
