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
    public $keep_list_start_number = false;

    /**
     * identify a line as the beginning of an ordered list.
     */
    protected function identify_ol($line)
    {
        return (($l = $line[0]) > '0' && $l <= '9' || $l === ' ') && preg_match('/^ {0,3}\d+\.[ \t]/', $line);
    }

    /**
     * identify a line as the beginning of an unordered list.
     */
    protected function identify_ul($line)
    {
        $l = $line[0];
        return ($l === '-' || $l === '+' || $l === '*') && (isset($line[1]) && (($l1 = $line[1]) === ' ' || $l1 === "\t")) ||
               ($l === ' ' && preg_match('/^ {0,3}[\-\+\*][ \t]/', $line));
    }

    /**
     * Consume lines for an ordered list
     */
    protected function consume_ol($lines, $current)
    {
        // consume until newline

        $block = [
            'list',
            'list' => 'ol',
            'attr' => [],
            'items' => [],
        ];
        return $this->consume_list($lines, $current, $block, 'ol');
    }

    /**
     * Consume lines for an unordered list
     */
    protected function consume_ul($lines, $current)
    {
        // consume until newline

        $block = [
            'list',
            'list' => 'ul',
            'items' => [],
        ];
        return $this->consume_list($lines, $current, $block, 'ul');
    }

    private function consume_list(array $lines, $current, array $block, $type)
    {
        $item = 0;
        $indent = '';
        $len = 0;
        $last_line_empty = false;
        // track the indentation of list markers, if indented more than previous element
        // a list marker is considered to be long to a lower level
        $lead_space = 3;
        $marker = $type === 'ul' ? ltrim($lines[$current])[0] : '';
        for ($i = $current, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            // match list marker on the beginning of the line
            $pattern = ($type === 'ol') ? '/^( {0,'.$lead_space.'})(\d+)\.[ \t]+/' : '/^( {0,'.$lead_space.'})\\'.$marker.'[ \t]+/';
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
                    $lead_space = strlen($matches[1]) + 1;
                }

                if ($type === 'ol' && $this->keep_list_start_number) {
                    // attr `start` for ol
                    if (!isset($block['attr']['start']) && isset($matches[2])) {
                        $block['attr']['start'] = $matches[2];
                    }
                }

                $block['items'][++$item][] = $line;
                $block['lazy_items'][$item] = $last_line_empty;
                $last_line_empty = false;
            } elseif (ltrim($line) === '') {
                // line is empty, may be a lazy list
                $last_line_empty = true;

                // two empty lines will end the list
                if (!isset($lines[$i + 1][0])) {
                    break;

                    // next item is the continuation of this list -> lazy list
                } elseif (preg_match($pattern, $lines[$i + 1])) {
                    $block['items'][$item][] = $line;
                    $block['lazy_items'][$item] = true;

                    // next item is indented as much as this list -> lazy list if it is not a reference
                } elseif (strncmp($lines[$i + 1], $indent, $len) === 0 || !empty($lines[$i + 1]) && $lines[$i + 1][0] == "\t") {
                    $block['items'][$item][] = $line;
                    $next_line = $lines[$i + 1][0] === "\t" ? substr($lines[$i + 1], 1) : substr($lines[$i + 1], $len);
                    $block['lazy_items'][$item] = empty($next_line) || !method_exists($this, 'identify_reference') || !$this->identify_reference($next_line);

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
                $last_line_empty = false;
            }

            // if next line is <hr>, end the list
            if (!empty($lines[$i + 1]) && method_exists($this, 'identify_hr') && $this->identify_hr($lines[$i + 1])) {
                break;
            }
        }

        foreach ($block['items'] as $item_id => $item_lines) {
            $content = [];
            if (!$block['lazy_items'][$item_id]) {
                $first_par = [];
                while (!empty($item_lines) && rtrim($item_lines[0]) !== '' && $this->detect_line_type($item_lines, 0) === 'paragraph') {
                    $first_par[] = array_shift($item_lines);
                }
                $content = $this->parse_inline(implode("\n", $first_par));
            }
            if (!empty($item_lines)) {
                $content = array_merge($content, $this->parse_blocks($item_lines));
            }
            $block['items'][$item_id] = $content;
        }

        return [$block, $i];
    }

    /**
     * Renders a list
     */
    protected function render_list(array $block)
    {
        $type = $block['list'];

        if (!empty($block['attr'])) {
            $output = "<$type " . $this->generate_html_attributes($block['attr']) . ">\n";
        } else {
            $output = "<$type>\n";
        }

        foreach ($block['items'] as $item_lines) {
            $output .= '<li>' . $this->render_absy($item_lines). "</li>\n";
        }
        return $output . "</$type>\n";
    }

    /**
     * Return html attributes string from [attr_name => attr_value] list
     * @param array $attributes the attribute name-value pairs.
     * @return string
     */
    private function generate_html_attributes(array $attributes)
    {
        foreach ($attributes as $name => $value) {
            $attributes[$name] = "$name=\"$value\"";
        }
        return implode(' ', $attributes);
    }

    abstract protected function parse_blocks($lines);
    abstract protected function parse_inline($text);
    abstract protected function render_absy($absy);
    abstract protected function detect_line_type($lines, $current);
}
