<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\inline;

// work around https://github.com/facebook/hhvm/issues/1120
defined('ENT_HTML401') || define('ENT_HTML401', 0);

/**
 * Addes links and images as well as url markers.
 *
 * This trait conflicts with the Html_Trait. If both are used together,
 * you have to define a resolution, by defining the Html_Trait::parse_Inline_Html
 * as private so it is not used directly:
 *
 * ```php
 * use block\Html_Trait {
 *     parse_Inline_Html as private parse_Inline_Html;
 * }
 * ```
 *
 * If the method exists it is called internally by this trait.
 *
 * Also make sure to reset references on prepare():
 *
 * ```php
 * protected function prepare()
 * {
 *     // reset references
 *     $this->references = [];
 * }
 * ```
 */
trait Link_Trait
{
    /**
     * @var array a list of defined references in this document.
     */
    protected $references = [];

    /**
     * Remove backslash from escaped characters
     * @param $text
     * @return string
     */
    protected function replace_Escape($text)
    {
        $strtr = [];
        foreach ($this->escape_Characters as $char) {
            $strtr["\\$char"] = $char;
        }
        return strtr($text, $strtr);
    }

    /**
     * Parses a link indicated by `[`.
     * @marker [
     */
    protected function parse_Link($markdown)
    {
        if (!in_array('parse_Link', array_slice($this->context, 1)) && ($parts = $this->parse_Link_Or_Image($markdown)) !== false) {
            list($text, $url, $title, $offset, $key) = $parts;
            return [
                [
                    'link',
                    'text' => $this->parse_Inline($text),
                    'url' => $url,
                    'title' => $title,
                    'refkey' => $key,
                    'orig' => substr($markdown, 0, $offset),
                ],
                $offset,
            ];
        }
        // remove all starting [ markers to avoid next one to be parsed as link
        $result = '[';
        $i = 1;
        while (isset($markdown[$i]) && $markdown[$i] === '[') {
            $result .= '[';
            $i++;
        }
        return [['text', $result], $i];
    }

    /**
     * Parses an image indicated by `![`.
     * @marker ![
     */
    protected function parse_Image($markdown)
    {
        if (($parts = $this->parse_Link_Or_Image(substr($markdown, 1))) !== false) {
            list($text, $url, $title, $offset, $key) = $parts;

            return [
                [
                    'image',
                    'text' => $text,
                    'url' => $url,
                    'title' => $title,
                    'refkey' => $key,
                    'orig' => substr($markdown, 0, $offset + 1),
                ],
                $offset + 1,
            ];
        }
        // remove all starting [ markers to avoid next one to be parsed as link
        $result = '!';
        $i = 1;
        while (isset($markdown[$i]) && $markdown[$i] === '[') {
            $result .= '[';
            $i++;
        }
        return [['text', $result], $i];
    }

    protected function parse_Link_Or_Image($markdown)
    {
        if (strpos($markdown, ']') !== false && preg_match('/\[((?>[^\]\[]+|(?R))*)\]/', $markdown, $text_Matches)) { // TODO improve bracket regex
            $text = $text_Matches[1];
            $offset = strlen($text_Matches[0]);
            $markdown = substr($markdown, $offset);

            $pattern = <<<REGEXP
				/(?(R) # in case of recursion match parentheses
					 \(((?>[^\s()]+)|(?R))*\)
				|      # else match a link with title
					^\(\s*(((?>[^\s()]+)|(?R))*)(\s+"(.*?)")?\s*\)
				)/x
REGEXP;
            if (preg_match($pattern, $markdown, $ref_Matches)) {
                // inline link
                return [
                    $text,
                    isset($ref_Matches[2]) ? $this->replace_Escape($ref_Matches[2]) : '', // url
                    empty($ref_Matches[5]) ? null : $ref_Matches[5], // title
                    $offset + strlen($ref_Matches[0]), // offset
                    null, // reference key
                ];
            }
            if (preg_match('/^([ \n]?\[(.*?)\])?/s', $markdown, $ref_Matches)) {
                // reference style link
                if (empty($ref_Matches[2])) {
                    $key = strtolower($text);
                } else {
                    $key = strtolower($ref_Matches[2]);
                }
                return [
                    $text,
                    null, // url
                    null, // title
                    $offset + strlen($ref_Matches[0]), // offset
                    $key,
                ];
            }
        }
        return false;
    }

    /**
     * Parses inline HTML.
     * @marker <
     */
    protected function parse_Lt($text)
    {
        if (strpos($text, '>') !== false) {
            if (!in_array('parse_Link', $this->context)) { // do not allow links in links
                if (preg_match('/^<([^\s>]*?@[^\s]*?\.\w+?)>/', $text, $matches)) {
                    // email address
                    return [
                        ['email', $this->replace_Escape($matches[1])],
                        strlen($matches[0]),
                    ];
                }
                // do not allow links in links
                if (preg_match('/^<([a-z]{3,}:\/\/[^\s]+?)>/', $text, $matches)) {
                    // URL
                    return [
                        ['url', $this->replace_Escape($matches[1])],
                        strlen($matches[0]),
                    ];
                }
            }
            // try inline HTML if it was neither a URL nor email if Html_Trait is included.
            if (method_exists($this, 'parse_Inline_Html')) {
                return $this->parse_Inline_Html($text);
            }
        }
        return [['text', '&lt;'], 1];
    }

    protected function render_Email($block)
    {
        $email = htmlspecialchars($block[1], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return "<a href=\"mailto:$email\">$email</a>";
    }

    protected function render_Url($block)
    {
        $url = htmlspecialchars($block[1], ENT_COMPAT | ENT_HTML401, 'UTF-8');
        $decodedUrl = urldecode($block[1]);
        $secure_Url_Text = preg_match('//u', $decodedUrl) ? $decodedUrl : $block[1];
        $text = htmlspecialchars($secure_Url_Text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return "<a href=\"$url\">$text</a>";
    }

    protected function lookup_Reference($key)
    {
        $normalized_Key = preg_replace('/\s+/', ' ', $key);
        if (isset($this->references[$key]) || isset($this->references[$key = $normalized_Key])) {
            return $this->references[$key];
        }
        return false;
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
        return '<a href="' . htmlspecialchars($block['url'], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
            . (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
            . '>' . $this->render_Absy($block['text']) . '</a>';
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
        return '<img src="' . htmlspecialchars($block['url'], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
            . ' alt="' . htmlspecialchars($block['text'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
            . ($this->html5 ? '>' : ' />');
    }

    // references

    protected function identify_Reference($line)
    {
        return isset($line[0]) && ($line[0] === ' ' || $line[0] === '[') && preg_match('/^ {0,3}\[[^\[](.*?)\]:\s*([^\s]+?)(?:\s+[\'"](.+?)[\'"])?\s*$/', $line);
    }

    /**
     * Consume link references
     */
    protected function consume_Reference(array $lines, $current)
    {
        while (isset($lines[$current]) && preg_match('/^ {0,3}\[(.+?)\]:\s*(.+?)(?:\s+[\(\'"](.+?)[\)\'"])?\s*$/', $lines[$current], $matches)) {
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
            $current++;
        }
        return [false, --$current];
    }

    abstract protected function parse_Inline($text);
    abstract protected function render_Absy($blocks);
}
