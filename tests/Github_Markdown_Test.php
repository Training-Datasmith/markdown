<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\tests;

use cebe\markdown\Github_Markdown;

/**
 * Test case for the github flavored markdown.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @group github
 */
class Github_Markdown_Test extends Base_Markdown_Test
{
    public function create_markdown()
    {
        return new Github_Markdown();
    }

    public function get_data_paths()
    {
        return [
            'markdown-data' => __DIR__ . '/markdown-data',
            'github-data' => __DIR__ . '/github-data',
        ];
    }

    public function test_newlines()
    {
        $markdown = $this->create_markdown();
        $this->assertEquals("This is text<br />\nnewline\nnewline.", $markdown->parse_paragraph("This is text  \nnewline\nnewline."));
        $markdown->enable_newlines = true;
        $this->assertEquals("This is text<br />\nnewline<br />\nnewline.", $markdown->parse_paragraph("This is text  \nnewline\nnewline."));

        $this->assertEquals("<p>This is text</p>\n<p>newline<br />\nnewline.</p>\n", $markdown->parse("This is text\n\nnewline\nnewline."));
    }

    public function data_files()
    {
        $files = parent::data_files();
        foreach ($files as $i => $f) {
            // skip files that are different in github MD
            if ($f[0] === 'markdown-data' && (
                $f[1] === 'list-marker-in-paragraph' ||
                    $f[1] === 'dense-block-markers'
            )) {
                unset($files[$i]);
            }
        }
        return $files;
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

        $this->assertStringEndsWith(">{$utf_natural_url}</a>", $parser->parse_paragraph($utf_natural_url), 'Natural UTF-8 URL needs no conversion.');
        $this->assertStringEndsWith(">{$utf_natural_url}</a>", $parser->parse_paragraph($utf_encoded_url), 'Encoded UTF-8 URL will be converted to readable format.');
        $this->assertStringEndsWith(">{$euc_encoded_url}</a>", $parser->parse_paragraph($euc_encoded_url), 'Non UTF-8 URL should never be converted.');
        // See: \cebe\markdown\inline\Url_Link_Trait::render_auto_url
    }
}
