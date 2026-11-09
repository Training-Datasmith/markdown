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
    public $code_Attributes_On_Pre = false;

    /**
     * @inheritDoc
     */
    protected $escape_Characters = [
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

    private $_special_Attributes_Regex = '\{(([#\.][A-z0-9-_]+\s*)+)\}';

    // TODO allow HTML intended 3 spaces

    // TODO add markdown inside HTML blocks

    // TODO implement definition lists

    // TODO implement footnotes

    // TODO implement Abbreviations

    // block parsing

    protected function identify_Reference($line)
    {
        return ($line[0] === ' ' || $line[0] === '[') && preg_match('/^ {0,3}\[[^\[](.*?)\]:\s*([^\s]+?)(?:\s+[\'"](.+?)[\'"])?\s*('.$this->_special_Attributes_Regex.')?\s*$/', $line);
    }

    /**
     * Consume link references
     */
    protected function consume_Reference($lines, $current)
    {
        while (isset($lines[$current]) && preg_match('/^ {0,3}\[(.+?)\]:\s*(.+?)(?:\s+[\(\'"](.+?)[\)\'"])?\s*('.$this->_special_Attributes_Regex.')?\s*$/', $lines[$current], $matches)) {
            $label = strtolower($matches[1]);

            $this->references[$label] = [
                'url' => $this->replace_Escape($matches[2]),
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
    protected function consume_Fenced_Code(array $lines, $current)
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

    protected function render_Code($block)
    {
        $attributes = $this->render_Attributes($block);
        return ($this->code_Attributes_On_Pre ? "<pre$attributes><code>" : "<pre><code$attributes>")
            . htmlspecialchars($block['content'] . "\n", ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . "</code></pre>\n";
    }

    /**
     * Renders a headline
     */
    protected function render_Headline($block)
    {
        foreach ($block['content'] as $i => $element) {
            if ($element[0] === 'special_Attributes') {
                unset($block['content'][$i]);
                $block['attributes'] = $element[1];
            }
        }
        $tag = 'h' . $block['level'];
        $attributes = $this->render_Attributes($block);
        return "<$tag$attributes>" . rtrim($this->render_Absy($block['content']), "# \t") . "</$tag>\n";
    }

    protected function render_Attributes(array $block)
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
    protected function parse_Special_Attributes($text)
    {
        if (preg_match("~$this->_special_Attributes_Regex~", $text, $matches)) {
            return [['special_Attributes', $matches[1]], strlen($matches[0])];
        }
        return [['text', '{'], 1];
    }

    protected function render_Special_Attributes($block)
    {
        return '{' . $block[1] . '}';
    }

    protected function parse_Inline($text)
    {
        $elements = parent::parse_Inline($text);
        // merge special attribute elements to links and images as they are not part of the final absy later
        $relatedElement = null;
        foreach ($elements as $i => $element) {
            if ($element[0] === 'link' || $element[0] === 'image') {
                $relatedElement = $i;
            } elseif ($element[0] === 'special_Attributes') {
                if ($relatedElement !== null) {
                    $elements[$relatedElement]['attributes'] = $element[1];
                    unset($elements[$i]);
                }
                $relatedElement = null;
            } else {
                $relatedElement = null;
            }
        }
        return $elements;
    }

    protected function render_Link($block)
    {
        if (isset($block['refkey'])) {
            if (($ref = $this->lookup_Reference($block['refkey'])) !== false) {
                $block = array_merge($block, $ref);
            } else {
                if (strncmp($block['orig'], '[', 1) === 0) {
                    return '[' . $this->render_Absy($this->parse_Inline(substr($block['orig'], 1)));
                }
                return $block['orig'];
            }
        }
        $attributes = $this->render_Attributes($block);
        return '<a href="' . htmlspecialchars($block['url'], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
            . (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
            . $attributes . '>' . $this->render_Absy($block['text']) . '</a>';
    }

    protected function render_Image($block)
    {
        if (isset($block['refkey'])) {
            if (($ref = $this->lookup_Reference($block['refkey'])) !== false) {
                $block = array_merge($block, $ref);
            } else {
                if (strncmp($block['orig'], '![', 2) === 0) {
                    return '![' . $this->render_Absy($this->parse_Inline(substr($block['orig'], 2)));
                }
                return $block['orig'];
            }
        }
        $attributes = $this->render_Attributes($block);
        return '<img src="' . htmlspecialchars($block['url'], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
            . ' alt="' . htmlspecialchars($block['text'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
            . $attributes . ($this->html5 ? '>' : ' />');
    }
}
