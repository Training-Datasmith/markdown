<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\block;

/**
 * Adds the list blocks
 */
trait List_Trait
{
    /**
     * @var bool enable support `start` attribute of ordered lists. This means that lists
     * will start with the number you actually type in markdown and not the HTML generated one.
     * Defaults to `false` which means that numeration of all ordered lists(<ol>) starts with 1.
     */
    public $keep_List_Start_Number = false;

    /**
     * identify a line as the beginning of an ordered list.
     */
    protected function identify_Ol($line)
    {
        return (($l = $line[0]) > '0' && $l <= '9' || $l === ' ') && preg_match('/^ {0,3}\d+\.[ \t]/', $line);
    }

    /**
     * identify a line as the beginning of an unordered list.
     */
    protected function identify_Ul($line)
    {
        $l = $line[0];
        return ($l === '-' || $l === '+' || $l === '*') && (isset($line[1]) && (($l1 = $line[1]) === ' ' || $l1 === "\t")) ||
               ($l === ' ' && preg_match('/^ {0,3}[\-\+\*][ \t]/', $line));
    }

    /**
     * Consume lines for an ordered list
     */
    protected function consume_Ol($lines, $current)
    {
        // consume until newline

        $block = [
            'list',
            'list' => 'ol',
            'attr' => [],
            'items' => [],
        ];
        return $this->consume_List($lines, $current, $block, 'ol');
    }

    /**
     * Consume lines for an unordered list
     */
    protected function consume_Ul($lines, $current)
    {
        // consume until newline

        $block = [
            'list',
            'list' => 'ul',
            'items' => [],
        ];
        return $this->consume_List($lines, $current, $block, 'ul');
    }

    private function consume_List(array $lines, $current, array $block, $type)
    {
        $item = 0;
        $indent = '';
        $len = 0;
        $last_Line_Empty = false;
        // track the indentation of list markers, if indented more than previous element
        // a list marker is considered to be long to a lower level
        $lead_Space = 3;
        $marker = $type === 'ul' ? ltrim($lines[$current])[0] : '';
        for ($i = $current, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            // match list marker on the beginning of the line
            $pattern = ($type === 'ol') ? '/^( {0,'.$lead_Space.'})(\d+)\.[ \t]+/' : '/^( {0,'.$lead_Space.'})\\'.$marker.'[ \t]+/';
            if (preg_match($pattern, $line, $matches)) {
                if (($len = substr_count($matches[0], "\t")) > 0) {
                    $indent = str_repeat("\t", $len);
                    $line = substr($line, strlen($matches[0]));
                } else {
                    $len = strlen($matches[0]);
                    $indent = str_repeat(' ', $len);
                    $line = substr($line, $len);
                }
                if ($i === $current) {
                    $lead_Space = strlen($matches[1]) + 1;
                }

                if ($type === 'ol' && $this->keep_List_Start_Number) {
                    // attr `start` for ol
                    if (!isset($block['attr']['start']) && isset($matches[2])) {
                        $block['attr']['start'] = $matches[2];
                    }
                }

                $block['items'][++$item][] = $line;
                $block['lazy_Items'][$item] = $last_Line_Empty;
                $last_Line_Empty = false;
            } elseif (ltrim($line) === '') {
                // line is empty, may be a lazy list
                $last_Line_Empty = true;

                // two empty lines will end the list
                if (!isset($lines[$i + 1][0])) {
                    break;

                    // next item is the continuation of this list -> lazy list
                } elseif (preg_match($pattern, $lines[$i + 1])) {
                    $block['items'][$item][] = $line;
                    $block['lazy_Items'][$item] = true;

                    // next item is indented as much as this list -> lazy list if it is not a reference
                } elseif (strncmp($lines[$i + 1], $indent, $len) === 0 || !empty($lines[$i + 1]) && $lines[$i + 1][0] == "\t") {
                    $block['items'][$item][] = $line;
                    $next_Line = $lines[$i + 1][0] === "\t" ? substr($lines[$i + 1], 1) : substr($lines[$i + 1], $len);
                    $block['lazy_Items'][$item] = empty($next_Line) || !method_exists($this, 'identify_Reference') || !$this->identify_Reference($next_Line);

                    // everything else ends the list
                } else {
                    break;
                }
            } else {
                if ($line[0] === "\t") {
                    $line = substr($line, 1);
                } elseif (strncmp($line, $indent, $len) === 0) {
                    $line = substr($line, $len);
                }
                $block['items'][$item][] = $line;
                $last_Line_Empty = false;
            }

            // if next line is <hr>, end the list
            if (!empty($lines[$i + 1]) && method_exists($this, 'identify_Hr') && $this->identify_Hr($lines[$i + 1])) {
                break;
            }
        }

        foreach ($block['items'] as $item_Id => $item_Lines) {
            $content = [];
            if (!$block['lazy_Items'][$item_Id]) {
                $first_Par = [];
                while (!empty($item_Lines) && rtrim($item_Lines[0]) !== '' && $this->detect_Line_Type($item_Lines, 0) === 'paragraph') {
                    $first_Par[] = array_shift($item_Lines);
                }
                $content = $this->parse_Inline(implode("\n", $first_Par));
            }
            if (!empty($item_Lines)) {
                $content = array_merge($content, $this->parse_Blocks($item_Lines));
            }
            $block['items'][$item_Id] = $content;
        }

        return [$block, $i];
    }

    /**
     * Renders a list
     */
    protected function render_List(array $block)
    {
        $type = $block['list'];

        if (!empty($block['attr'])) {
            $output = "<$type " . $this->generate_Html_Attributes($block['attr']) . ">\n";
        } else {
            $output = "<$type>\n";
        }

        foreach ($block['items'] as $item_Lines) {
            $output .= '<li>' . $this->render_Absy($item_Lines). "</li>\n";
        }
        return $output . "</$type>\n";
    }

    /**
     * Return html attributes string from [attr_Name => attr_Value] list
     * @param array $attributes the attribute name-value pairs.
     * @return string
     */
    private function generate_Html_Attributes(array $attributes)
    {
        foreach ($attributes as $name => $value) {
            $attributes[$name] = "$name=\"$value\"";
        }
        return implode(' ', $attributes);
    }

    abstract protected function parse_Blocks($lines);
    abstract protected function parse_Inline($text);
    abstract protected function render_Absy($absy);
    abstract protected function detect_Line_Type($lines, $current);
}
