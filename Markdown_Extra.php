<?php
namespace cebe\markdown;

// work around https://github.com/facebook/hhvm/issues/1120
defined('ENT_HTML401') || define('ENT_HTML401', 0);

/**
 * Markdown parser for the [markdown extra](http://michelf.ca/projects/php-markdown/extra/) flavor.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */
class Markdown_Extra extends Markdown
{
    // include block element parsing using traits
    use block\Table_Trait;
    use block\Fenced_Code_Trait;

    // include inline element parsing using traits
    // TODO

    /**
     * @var bool whether special attributes on code blocks should be applied on the `<pre>` element.
     * The default behavior is to put them on the `<code>` element.
     */
    public $code_attributes_on_pre = false;

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
        // added by Markdown_Extra
        ':', // colon
        '|', // pipe
    ];

    private $_special_attributes_regex = '\{(([#\.][A-z0-9-_]+\s*)+)\}';

    // TODO allow HTML intended 3 spaces

    // TODO add markdown inside HTML blocks

    // TODO implement definition lists

    // TODO implement footnotes

    // TODO implement Abbreviations

    // block parsing

    protected function identify_reference($line)
    {
        return ($line[0] === ' ' || $line[0] === '[') && preg_match('/^ {0,3}\[[^\[](.*?)\]:\s*([^\s]+?)(?:\s+[\'"](.+?)[\'"])?\s*('.$this->_special_attributes_regex.')?\s*$/', $line);
    }

    /**
     * Consume link references
     */
    protected function consume_reference($lines, $current)
    {
        while (isset($lines[$current]) && preg_match('/^ {0,3}\[(.+?)\]:\s*(.+?)(?:\s+[\(\'"](.+?)[\)\'"])?\s*('.$this->_special_attributes_regex.')?\s*$/', $lines[$current], $matches)) {
            $label = strtolower($matches[1]);

            $this->references[$label] = [
                'url' => $this->replace_escape($matches[2]),
            ];
            if (isset($matches[3])) {
                $this->references[$label]['title'] = $matches[3];
            } else {
                // title may be on the next line
                if (isset($lines[$current + 1]) && preg_match('/^\s+[\(\'"](.+?)[\)\'"]\s*$/', $lines[$current + 1], $matches)) {
                    $this->references[$label]['title'] = $matches[1];
                    $current++;
                }
            }
            if (isset($matches[5])) {
                $this->references[$label]['attributes'] = $matches[5];
            }
            $current++;
        }
        return [false, --$current];
    }

    /**
     * Consume lines for a fenced code block
     */
    protected function consume_fenced_code(array $lines, $current)
    {
        // consume until ```
        $block = [
            'code',
        ];
        $line = trim($lines[$current]);
        if (($pos = strrpos($line, '`')) === false) {
            $pos = strrpos($line, '~');
        }
        $fence = substr($line, 0, $pos + 1);
        $block['attributes'] = substr($line, $pos);
        $content = [];
        for ($i = $current + 1, $count = count($lines); $i < $count; $i++) {
            if (($pos = strpos($line = $lines[$i], $fence)) === false || $pos > 3) {
                $content[] = $line;
            } else {
                break;
            }
        }
        $block['content'] = implode("\n", $content);
        return [$block, $i];
    }

    protected function render_code($block)
    {
        $attributes = $this->render_attributes($block);
        return ($this->code_attributes_on_pre ? "<pre$attributes><code>" : "<pre><code$attributes>")
            . htmlspecialchars($block['content'] . "\n", ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . "</code></pre>\n";
    }

    /**
     * Renders a headline
     */
    protected function render_headline($block)
    {
        foreach ($block['content'] as $i => $element) {
            if ($element[0] === 'special_attributes') {
                unset($block['content'][$i]);
                $block['attributes'] = $element[1];
            }
        }
        $tag = 'h' . $block['level'];
        $attributes = $this->render_attributes($block);
        return "<$tag$attributes>" . rtrim($this->render_absy($block['content']), "# \t") . "</$tag>\n";
    }

    protected function render_attributes(array $block)
    {
        $html = [];
        if (isset($block['attributes'])) {
            $attributes = preg_split('/\s+/', $block['attributes'], -1, PREG_SPLIT_NO_EMPTY);
            foreach ($attributes as $attribute) {
                if ($attribute[0] === '#') {
                    $html['id'] = substr($attribute, 1);
                } else {
                    $html['class'][] = substr($attribute, 1);
                }
            }
        }
        $result = '';
        foreach ($html as $attr => $value) {
            if (is_array($value)) {
                $value = trim(implode(' ', $value));
            }
            if (!empty($value)) {
                $result .= " $attr=\"$value\"";
            }
        }
        return $result;
    }

    // inline parsing

    /**
     * @marker {
     */
    protected function parse_special_attributes($text)
    {
        if (preg_match("~$this->_special_attributes_regex~", $text, $matches)) {
            return [['special_attributes', $matches[1]], strlen($matches[0])];
        }
        return [['text', '{'], 1];
    }

    protected function render_special_attributes($block)
    {
        return '{' . $block[1] . '}';
    }

    protected function parse_inline($text)
    {
        $elements = parent::parse_inline($text);
        // merge special attribute elements to links and images as they are not part of the final absy later
        $related_element = null;
        foreach ($elements as $i => $element) {
            if ($element[0] === 'link' || $element[0] === 'image') {
                $related_element = $i;
            } elseif ($element[0] === 'special_attributes') {
                if ($related_element !== null) {
                    $elements[$related_element]['attributes'] = $element[1];
                    unset($elements[$i]);
                }
                $related_element = null;
            } else {
                $related_element = null;
            }
        }
        return $elements;
    }

    protected function render_link($block)
    {
        if (isset($block['refkey'])) {
            if (($ref = $this->lookup_reference($block['refkey'])) !== false) {
                $block = array_merge($block, $ref);
            } else {
                if (strncmp($block['orig'], '[', 1) === 0) {
                    return '[' . $this->render_absy($this->parse_inline(substr($block['orig'], 1)));
                }
                return $block['orig'];
            }
        }
        $attributes = $this->render_attributes($block);
        return '<a href="' . htmlspecialchars($block['url'], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
            . (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
            . $attributes . '>' . $this->render_absy($block['text']) . '</a>';
    }

    protected function render_image($block)
    {
        if (isset($block['refkey'])) {
            if (($ref = $this->lookup_reference($block['refkey'])) !== false) {
                $block = array_merge($block, $ref);
            } else {
                if (strncmp($block['orig'], '![', 2) === 0) {
                    return '![' . $this->render_absy($this->parse_inline(substr($block['orig'], 2)));
                }
                return $block['orig'];
            }
        }
        $attributes = $this->render_attributes($block);
        return '<img src="' . htmlspecialchars($block['url'], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
            . ' alt="' . htmlspecialchars($block['text'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
            . $attributes . ($this->html5 ? '>' : ' />');
    }
}
