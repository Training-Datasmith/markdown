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
 * Adds auto linking for URLs
 */
trait Url_Link_Trait
{
    /**
     * Parses urls and adds auto linking feature.
     * @marker http
     * @marker ftp
     */
    protected function parse_url($markdown)
    {
        $pattern = <<<REGEXP
			/(?(R) # in case of recursion match parentheses
				 \(((?>[^\s()]+)|(?R))*\)
			|      # else match a link with title
				^(https?|ftp):\/\/(([^\s<>()]+)|(?R))+(?<![\.,:;\'"!\?\s])
			)/x
REGEXP;

        if (!in_array('parse_link', $this->context) && preg_match($pattern, $markdown, $matches)) {
            return [
                ['auto_url', $matches[0]],
                strlen($matches[0]),
            ];
        }
        return [['text', substr($markdown, 0, 4)], 4];
    }

    protected function render_auto_url($block)
    {
        $href = htmlspecialchars($block[1], ENT_COMPAT | ENT_HTML401, 'UTF-8');
        $decoded_url = urldecode($block[1]);
        $secure_url_text = preg_match('//u', $decoded_url) ? $decoded_url : $block[1];
        $text = htmlspecialchars($secure_url_text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return "<a href=\"$href\">$text</a>";
    }
}
