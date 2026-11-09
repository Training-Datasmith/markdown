<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\inline;

/**
 * Adds inline code elements
 */
trait Code_Trait
{
    /**
     * Parses an inline code span `` ` ``.
     * @marker `
     */
    protected function parse_inline_code($text)
    {
        if (preg_match('/^(``+)\s(.+?)\s\1/s', $text, $matches)) {
            // code with enclosed backtick
            return [
                [
                    'inline_code',
                    $matches[2],
                ],
                strlen($matches[0]),
            ];
        }
        if (preg_match('/^`(.+?)`/s', $text, $matches)) {
            return [
                [
                    'inline_code',
                    $matches[1],
                ],
                strlen($matches[0]),
            ];
        }
        return [['text', $text[0]], 1];
    }

    protected function render_inline_code($block)
    {
        return '<code>' . htmlspecialchars($block[1], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
    }
}
