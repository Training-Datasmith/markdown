<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown;

/**
 * Markdown parser for github flavored markdown.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class Github_Markdown extends Markdown
{
    // include block element parsing using traits
    use block\Table_Trait;
    use block\Fenced_Code_Trait;

    // include inline element parsing using traits
    use inline\Strikeout_Trait;
    use inline\Url_Link_Trait;

    /**
     * @var boolean whether to interpret newlines as `<br />`-tags.
     * This feature is useful for comments where newlines are often meant to be real new lines.
     */
    public $enable_newlines = false;

    /**
     * @inheritDoc
     */
    protected $escape_characters = [
        // from Markdown
        '\\', // backslash
        '`', // backtick
        '*', // asterisk
        '_', // underscore
        '{', '}', // curly braces
        '[', ']', // square brackets
        '(', ')', // parentheses
        '#', // hash mark
        '+', // plus sign
        '-', // minus sign (hyphen)
        '.', // dot
        '!', // exclamation mark
        '<', '>',
        // added by Github_Markdown
        ':', // colon
        '|', // pipe
    ];

    /**
     * Consume lines for a paragraph
     *
     * Allow headlines, lists and code to break paragraphs
     */
    protected function consume_paragraph($lines, $current)
    {
        // consume until newline
        $content = [];
        for ($i = $current, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if ($line === ''
                || ltrim($line) === ''
                || !ctype_alpha($line[0]) && (
                    $this->identify_quote($line, $lines, $i) ||
                    $this->identify_fenced_code($line, $lines, $i) ||
                    $this->identify_ul($line, $lines, $i) ||
                    $this->identify_ol($line, $lines, $i) ||
                    $this->identify_hr($line, $lines, $i)
                )
                || $this->identify_headline($line, $lines, $i)) {
                break;
            } elseif ($this->identify_code($line, $lines, $i)) {
                // possible beginning of a code block
                // but check for continued inline HTML
                // e.g. <img src="file.jpg"
                //           alt="some alt aligned with src attribute" title="some text" />
                if (preg_match('~<\w+([^>]+)$~s', implode("\n", $content))) {
                    $content[] = $line;
                } else {
                    break;
                }
            } else {
                $content[] = $line;
            }
        }
        $block = [
            'paragraph',
            'content' => $this->parse_inline(implode("\n", $content)),
        ];
        return [$block, --$i];
    }

    /**
     * @inheritdocs
     *
     * Parses a newline indicated by two spaces on the end of a markdown line.
     */
    protected function render_text($text)
    {
        if ($this->enable_newlines) {
            $br = $this->html5 ? "<br>\n" : "<br />\n";
            return strtr($text[1], ["  \n" => $br, "\n" => $br]);
        }
        return parent::render_text($text);
    }
}
