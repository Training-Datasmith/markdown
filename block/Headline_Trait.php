<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\block;

/**
 * Adds the headline blocks
 */
trait Headline_Trait
{
    /**
     * identify a line as a headline
     */
    protected function identify_Headline($line, array $lines, $current)
    {
        return (
            // heading with #
            $line[0] === '#' && !preg_match('/^#\d+/', $line)
            ||
            // underlined headline
            !empty($lines[$current + 1]) &&
            (($l = $lines[$current + 1][0]) === '=' || $l === '-') &&
            preg_match('/^(\-+|=+)\s*$/', $lines[$current + 1])
        );
    }

    /**
     * Consume lines for a headline
     */
    protected function consume_Headline(array $lines, $current)
    {
        if ($lines[$current][0] === '#') {
            // ATX headline
            $level = 1;
            while (isset($lines[$current][$level]) && $lines[$current][$level] === '#' && $level < 6) {
                $level++;
            }
            $block = [
                'headline',
                'content' => $this->parse_Inline(trim($lines[$current], "# \t")),
                'level' => $level,
            ];
            return [$block, $current];
        }
        // underlined headline
        $block = [
                'headline',
                'content' => $this->parse_Inline($lines[$current]),
                'level' => $lines[$current + 1][0] === '=' ? 1 : 2,
            ];
        return [$block, $current + 1];
    }

    /**
     * Renders a headline
     */
    protected function render_Headline(array $block)
    {
        $tag = 'h' . $block['level'];
        return "<$tag>" . $this->render_Absy($block['content']) . "</$tag>\n";
    }

    abstract protected function parse_Inline($text);
    abstract protected function render_Absy($absy);
}
