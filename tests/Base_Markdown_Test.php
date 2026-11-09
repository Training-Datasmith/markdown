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
 * Base class for all Test cases.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
abstract class Base_Markdown_Test extends \PHPUnit\Framework\TestCase
{
    protected $output_file_extension = '.html';

    abstract public function get_data_paths();

    /**
     * @return Parser
     */
    abstract public function create_markdown();

    /**
     * @dataProvider data_files
     */
    public function test_parse($path, $file)
    {
        list($markdown, $html) = $this->get_test_data($path, $file);
        // Different OS line endings should not affect test
        $html = str_replace(["\r\n", "\n\r", "\r"], "\n", $html);

        $m = $this->create_markdown();
        $this->assertEquals($html, $m->parse($markdown));
    }

    public function test_utf8()
    {
        $this->assertSame("<p>абвгдеёжзийклмнопрстуфхцчшщъыьэюя</p>\n", $this->create_markdown()->parse('абвгдеёжзийклмнопрстуфхцчшщъыьэюя'));
        $this->assertSame("<p>there is a charater, 配</p>\n", $this->create_markdown()->parse('there is a charater, 配'));
        $this->assertSame("<p>Arabic Latter \"م (M)\"</p>\n", $this->create_markdown()->parse('Arabic Latter "م (M)"'));
        $this->assertSame("<p>電腦</p>\n", $this->create_markdown()->parse('電腦'));

        $this->assertSame('абвгдеёжзийклмнопрстуфхцчшщъыьэюя', $this->create_markdown()->parse_paragraph('абвгдеёжзийклмнопрстуфхцчшщъыьэюя'));
        $this->assertSame('there is a charater, 配', $this->create_markdown()->parse_paragraph('there is a charater, 配'));
        $this->assertSame('Arabic Latter "م (M)"', $this->create_markdown()->parse_paragraph('Arabic Latter "م (M)"'));
        $this->assertSame('電腦', $this->create_markdown()->parse_paragraph('電腦'));
    }

    public function test_invalid_utf8()
    {
        $m = $this->create_markdown();
        $this->assertEquals("<p><code>�</code></p>\n", $m->parse("`\x80`"));
        $this->assertEquals('<code>�</code>', $m->parse_paragraph("`\x80`"));
    }

    public function preg_data()
    {
        // http://en.wikipedia.org/wiki/Newline#Representations
        return [
            ["a\r\nb", "a\nb"],
            ["a\n\rb", "a\nb"], // Acorn BBC and RISC OS spooled text output :)
            ["a\nb", "a\nb"],
            ["a\rb", "a\nb"],

            ["a\n\nb", "a\n\nb", "a</p>\n<p>b"],
            ["a\r\rb", "a\n\nb", "a</p>\n<p>b"],
            ["a\n\r\n\rb", "a\n\nb", "a</p>\n<p>b"], // Acorn BBC and RISC OS spooled text output :)
            ["a\r\n\r\nb", "a\n\nb", "a</p>\n<p>b"],
        ];
    }

    /**
     * @dataProvider preg_data
     */
    public function test_preg_replace_r($input, $exptected, $pexpect = null)
    {
        $this->assertSame($exptected, $this->create_markdown()->parse_paragraph($input));
        $this->assertSame($pexpect === null ? "<p>$exptected</p>\n" : "<p>$pexpect</p>\n", $this->create_markdown()->parse($input));
    }

    public function get_test_data($path, $file)
    {
        return [
            file_get_contents($this->get_data_paths()[$path] . '/' . $file . '.md'),
            file_get_contents($this->get_data_paths()[$path] . '/' . $file . $this->output_file_extension),
        ];
    }

    public function data_files()
    {
        $files = [];
        foreach ($this->get_data_paths() as $name => $src) {
            $handle = opendir($src);
            if ($handle === false) {
                throw new \Exception('Unable to open directory: ' . $src);
            }
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                if (substr($file, -3, 3) === '.md' && file_exists($src . '/' . substr($file, 0, -3) .  $this->output_file_extension)) {
                    $files[] = [$name, substr($file, 0, -3)];
                }
            }
            closedir($handle);
        }
        return $files;
    }
}
