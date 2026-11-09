<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown;

/**
 * Markdown parser for the [initial markdown spec](http://daringfireball.net/projects/markdown/syntax).
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class Markdown extends Parser
{
    // include block element parsing using traits
    use block\Code_Trait;
    use block\Headline_Trait;
    use block\Html_Trait {
        parse_inline_html as private;
    }
    use block\List_Trait {
        // Check Ul List before headline
        identify_ul as protected identify_b_ul;
        consume_ul as protected consume_b_ul;
    }
    use block\Quote_Trait;
    use block\Rule_Trait {
        // Check Hr before checking lists
        identify_hr as protected identify_a_hr;
        consume_hr as protected consume_a_hr;
    }

    // include inline element parsing using traits
    use inline\Code_Trait;
    use inline\Emph_Strong_Trait;
    use inline\Link_Trait;

    /**
     * @var boolean whether to format markup according to HTML5 spec.
     * Defaults to `false` which means that markup is formatted as HTML4.
     */
    public $html5 = false;

    /**
     * @var array these are "escapeable" characters. When using one of these prefixed with a
     * backslash, the character will be outputted without the backslash and is not interpreted
     * as markdown.
     */
    protected $escape_characters = [
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
    ];

    /**
     * @inheritDoc
     */
    protected function prepare()
    {
        // reset references
        $this->references = [];
    }

    /**
     * Consume lines for a paragraph
     *
     * Allow headlines and code to break paragraphs
     */
    protected function consume_paragraph($lines, $current)
    {
        // consume until newline
        $content = [];
        for ($i = $current, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];

            // a list may break a paragraph when it is inside of a list
            if (isset($this->context[1]) && $this->context[1] === 'list' && !ctype_alpha($line[0]) && (
                $this->identify_ul($line, $lines, $i) || $this->identify_ol($line, $lines, $i)
            )) {
                break;
            }

            if ($line === '' || ltrim($line) === '' || $this->identify_headline($line, $lines, $i)) {
                break;
            } elseif ($line[0] === "\t" || $line[0] === ' ' && strncmp($line, '    ', 4) === 0) {
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
        return str_replace("  \n", $this->html5 ? "<br>\n" : "<br />\n", $text[1]);
    }
}
