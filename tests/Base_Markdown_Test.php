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
    protected $output_File_Extension = '.html';

    abstract public function get_Data_Paths();

    /**
     * @return Parser
     */
    abstract public function create_Markdown();

    /**
     * @dataProvider data_Files
     */
    public function testParse($path, $file)
    {
        list($markdown, $html) = $this->get_Test_Data($path, $file);
        // Different OS line endings should not affect test
        $html = str_replace(["\r\n", "\n\r", "\r"], "\n", $html);

        $m = $this->create_Markdown();
        $this->assertEquals($html, $m->parse($markdown));
    }

    public function testUtf8()
    {
        $this->assertSame("<p>абвгдеёжзийклмнопрстуфхцчшщъыьэюя</p>\n", $this->create_Markdown()->parse('абвгдеёжзийклмнопрстуфхцчшщъыьэюя'));
        $this->assertSame("<p>there is a charater, 配</p>\n", $this->create_Markdown()->parse('there is a charater, 配'));
        $this->assertSame("<p>Arabic Latter \"م (M)\"</p>\n", $this->create_Markdown()->parse('Arabic Latter "م (M)"'));
        $this->assertSame("<p>電腦</p>\n", $this->create_Markdown()->parse('電腦'));

        $this->assertSame('абвгдеёжзийклмнопрстуфхцчшщъыьэюя', $this->create_Markdown()->parse_Paragraph('абвгдеёжзийклмнопрстуфхцчшщъыьэюя'));
        $this->assertSame('there is a charater, 配', $this->create_Markdown()->parse_Paragraph('there is a charater, 配'));
        $this->assertSame('Arabic Latter "م (M)"', $this->create_Markdown()->parse_Paragraph('Arabic Latter "م (M)"'));
        $this->assertSame('電腦', $this->create_Markdown()->parse_Paragraph('電腦'));
    }

    public function test_Invalid_Utf8()
    {
        $m = $this->create_Markdown();
        $this->assertEquals("<p><code>�</code></p>\n", $m->parse("`\x80`"));
        $this->assertEquals('<code>�</code>', $m->parse_Paragraph("`\x80`"));
    }

    public function preg_Data()
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
     * @dataProvider preg_Data
     */
    public function test_Preg_Replace_R($input, $exptected, $pexpect = null)
    {
        $this->assertSame($exptected, $this->create_Markdown()->parse_Paragraph($input));
        $this->assertSame($pexpect === null ? "<p>$exptected</p>\n" : "<p>$pexpect</p>\n", $this->create_Markdown()->parse($input));
    }

    public function get_Test_Data($path, $file)
    {
        return [
            file_get_contents($this->get_Data_Paths()[$path] . '/' . $file . '.md'),
            file_get_contents($this->get_Data_Paths()[$path] . '/' . $file . $this->output_File_Extension),
        ];
    }

    public function data_Files()
    {
        $files = [];
        foreach ($this->get_Data_Paths() as $name => $src) {
            $handle = opendir($src);
            if ($handle === false) {
                throw new \Exception('Unable to open directory: ' . $src);
            }
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                if (substr($file, -3, 3) === '.md' && file_exists($src . '/' . substr($file, 0, -3) .  $this->output_File_Extension)) {
                    $files[] = [$name, substr($file, 0, -3)];
                }
            }
            closedir($handle);
        }
        return $files;
    }
}
