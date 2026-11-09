<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\block;

/**
 * Adds inline and block HTML support
 */
trait Html_Trait
{
    /**
     * @var array HTML elements considered as inline elements.
     * @see http://www.w3.org/wiki/HTML/Elements#Text-level_semantics
     */
    protected $inline_html_elements = [
        'a', 'abbr', 'acronym',
        'b', 'basefont', 'bdo', 'big', 'br', 'button', 'blink',
        'cite', 'code',
        'del', 'dfn',
        'em',
        'font',
        'i', 'img', 'ins', 'input', 'iframe',
        'kbd',
        'label', 'listing',
        'map', 'mark',
        'nobr',
        'object',
        'q',
        'rp', 'rt', 'ruby',
        's', 'samp', 'script', 'select', 'small', 'spacer', 'span', 'strong', 'sub', 'sup',
        'tt', 'var',
        'u',
        'wbr',
        'time',
    ];
    /**
     * @var array HTML elements known to be self-closing.
     */
    protected $self_closing_html_elements = [
        'br', 'hr', 'img', 'input', 'nobr',
    ];

    /**
     * identify a line as the beginning of a HTML block.
     */
    protected function identify_html($line, array $lines, $current)
    {
        if ($line[0] !== '<' || isset($line[1]) && $line[1] == ' ') {
            return false; // no html tag
        }

        if (strncmp($line, '<!--', 4) === 0) {
            return true; // a html comment
        }

        $gt_pos = strpos($lines[$current], '>');
        $space_pos = strpos($lines[$current], ' ');
        if ($gt_pos === false && $space_pos === false) {
            return false; // no html tag
        } elseif ($space_pos === false) {
            $tag = rtrim(substr($line, 1, $gt_pos - 1), '/');
        } else {
            $tag = rtrim(substr($line, 1, min($gt_pos, $space_pos) - 1), '/');
        }

        if (!ctype_alnum($tag) || in_array(strtolower($tag), $this->inline_html_elements)) {
            return false; // no html tag or inline html tag
        }
        return true;
    }

    /**
     * Consume lines for an HTML block
     */
    protected function consume_html(array $lines, $current)
    {
        $content = [];
        if (strncmp($lines[$current], '<!--', 4) === 0) { // html comment
            for ($i = $current, $count = count($lines); $i < $count; $i++) {
                $line = $lines[$i];
                $content[] = $line;
                if (strpos($line, '-->') !== false) {
                    break;
                }
            }
        } else {
            $tag = rtrim(substr($lines[$current], 1, min(strpos($lines[$current], '>'), strpos($lines[$current] . ' ', ' ')) - 1), '/');
            $level = 0;
            if (in_array($tag, $this->self_closing_html_elements)) {
                $level--;
            }
            for ($i = $current, $count = count($lines); $i < $count; $i++) {
                $line = $lines[$i];
                $content[] = $line;
                $level += substr_count($line, "<$tag") - substr_count($line, "</$tag>") - substr_count($line, '/>');
                if ($level <= 0) {
                    break;
                }
            }
        }
        $block = [
            'html',
            'content' => implode("\n", $content),
        ];
        return [$block, $i];
    }

    /**
     * Renders an HTML block
     */
    protected function render_html(array $block)
    {
        return $block['content'] . "\n";
    }

    /**
     * Parses an & or a html entity definition.
     * @marker &
     */
    protected function parse_entity($text)
    {
        // html entities e.g. &copy; &#169; &#x00A9;
        if (preg_match('/^&#?[\w\d]+;/', $text, $matches)) {
            return [['inline_html', $matches[0]], strlen($matches[0])];
        }
        return [['text', '&amp;'], 1];
    }

    /**
     * renders a html entity.
     */
    protected function render_inline_html($block)
    {
        return $block[1];
    }

    /**
     * Parses inline HTML.
     * @marker <
     */
    protected function parse_inline_html($text)
    {
        if (strpos($text, '>') === false) {
            return [['text', '&lt;'], 1];
        }
        if (preg_match('~^</?(\w+\d?)( .*?)?>~s', $text, $matches)) {
            // HTML tags
            return [['inline_html', $matches[0]], strlen($matches[0])];
        }
        if (preg_match('~^<!--.*?-->~s', $text, $matches)) {
            // HTML comments
            return [['inline_html', $matches[0]], strlen($matches[0])];
        }
        return [['text', '&lt;'], 1];
    }

    /**
     * Escapes `>` characters.
     * @marker >
     */
    protected function parse_gt($text)
    {
        return [['text', '&gt;'], 1];
    }
}
