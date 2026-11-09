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
 * you have to define a resolution, by defining the Html_Trait::parse_inline_html
 * as private so it is not used directly:
 *
 * ```php
 * use block\Html_Trait {
 *     parse_inline_html as private parse_inline_html;
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
    protected function replace_escape($text)
    {
        $strtr = [];
        foreach ($this->escape_characters as $char) {
            $strtr["\\$char"] = $char;
        }
        return strtr($text, $strtr);
    }

    /**
     * Parses a link indicated by `[`.
     * @marker [
     */
    protected function parse_link($markdown)
    {
        if (!in_array('parse_link', array_slice($this->context, 1)) && ($parts = $this->parse_link_or_image($markdown)) !== false) {
            list($text, $url, $title, $offset, $key) = $parts;
            return [
                [
                    'link',
                    'text' => $this->parse_inline($text),
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
    protected function parse_image($markdown)
    {
        if (($parts = $this->parse_link_or_image(substr($markdown, 1))) !== false) {
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

    protected function parse_link_or_image($markdown)
    {
        if (strpos($markdown, ']') !== false && preg_match('/\[((?>[^\]\[]+|(?R))*)\]/', $markdown, $text_matches)) { // TODO improve bracket regex
            $text = $text_matches[1];
            $offset = strlen($text_matches[0]);
            $markdown = substr($markdown, $offset);

            $pattern = <<<REGEXP
				/(?(R) # in case of recursion match parentheses
					 \(((?>[^\s()]+)|(?R))*\)
				|      # else match a link with title
					^\(\s*(((?>[^\s()]+)|(?R))*)(\s+"(.*?)")?\s*\)
				)/x
REGEXP;
            if (preg_match($pattern, $markdown, $ref_matches)) {
                // inline link
                return [
                    $text,
                    isset($ref_matches[2]) ? $this->replace_escape($ref_matches[2]) : '', // url
                    empty($ref_matches[5]) ? null : $ref_matches[5], // title
                    $offset + strlen($ref_matches[0]), // offset
                    null, // reference key
                ];
            }
            if (preg_match('/^([ \n]?\[(.*?)\])?/s', $markdown, $ref_matches)) {
                // reference style link
                if (empty($ref_matches[2])) {
                    $key = strtolower($text);
                } else {
                    $key = strtolower($ref_matches[2]);
                }
                return [
                    $text,
                    null, // url
                    null, // title
                    $offset + strlen($ref_matches[0]), // offset
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
    protected function parse_lt($text)
    {
        if (strpos($text, '>') !== false) {
            if (!in_array('parse_link', $this->context)) { // do not allow links in links
                if (preg_match('/^<([^\s>]*?@[^\s]*?\.\w+?)>/', $text, $matches)) {
                    // email address
                    return [
                        ['email', $this->replace_escape($matches[1])],
                        strlen($matches[0]),
                    ];
                }
                // do not allow links in links
                if (preg_match('/^<([a-z]{3,}:\/\/[^\s]+?)>/', $text, $matches)) {
                    // URL
                    return [
                        ['url', $this->replace_escape($matches[1])],
                        strlen($matches[0]),
                    ];
                }
            }
            // try inline HTML if it was neither a URL nor email if Html_Trait is included.
            if (method_exists($this, 'parse_inline_html')) {
                return $this->parse_inline_html($text);
            }
        }
        return [['text', '&lt;'], 1];
    }

    protected function render_email($block)
    {
        $email = htmlspecialchars($block[1], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return "<a href=\"mailto:$email\">$email</a>";
    }

    protected function render_url($block)
    {
        $url = htmlspecialchars($block[1], ENT_COMPAT | ENT_HTML401, 'UTF-8');
        $decoded_url = urldecode($block[1]);
        $secure_url_text = preg_match('//u', $decoded_url) ? $decoded_url : $block[1];
        $text = htmlspecialchars($secure_url_text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return "<a href=\"$url\">$text</a>";
    }

    protected function lookup_reference($key)
    {
        $normalized_key = preg_replace('/\s+/', ' ', $key);
        if (isset($this->references[$key]) || isset($this->references[$key = $normalized_key])) {
            return $this->references[$key];
        }
        return false;
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
        return '<a href="' . htmlspecialchars($block['url'], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
            . (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
            . '>' . $this->render_absy($block['text']) . '</a>';
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
        return '<img src="' . htmlspecialchars($block['url'], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
            . ' alt="' . htmlspecialchars($block['text'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"'
            . (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
            . ($this->html5 ? '>' : ' />');
    }

    // references

    protected function identify_reference($line)
    {
        return isset($line[0]) && ($line[0] === ' ' || $line[0] === '[') && preg_match('/^ {0,3}\[[^\[](.*?)\]:\s*([^\s]+?)(?:\s+[\'"](.+?)[\'"])?\s*$/', $line);
    }

    /**
     * Consume link references
     */
    protected function consume_reference(array $lines, $current)
    {
        while (isset($lines[$current]) && preg_match('/^ {0,3}\[(.+?)\]:\s*(.+?)(?:\s+[\(\'"](.+?)[\)\'"])?\s*$/', $lines[$current], $matches)) {
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
            $current++;
        }
        return [false, --$current];
    }

    abstract protected function parse_inline($text);
    abstract protected function render_absy($blocks);
}
