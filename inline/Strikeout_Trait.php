<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\inline;

/**
 * Adds strikeout inline elements
 */
trait Strikeout_Trait
{
    /**
     * Parses the strikethrough feature.
     * @marker ~~
     */
    protected function parse_strike($markdown)
    {
        if (preg_match('/^~~(.+?)~~/', $markdown, $matches)) {
            return [
                [
                    'strike',
                    $this->parse_inline($matches[1]),
                ],
                strlen($matches[0]),
            ];
        }
        return [['text', $markdown[0] . $markdown[1]], 2];
    }

    protected function render_strike($block)
    {
        return '<del>' . $this->render_absy($block[1]) . '</del>';
    }

    abstract protected function parse_inline($text);
    abstract protected function render_absy($blocks);
}
