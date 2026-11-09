<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\tests;

use cebe\markdown\Markdown;

/**
 * Test support ordered lists at arbitrary number(`start` html attribute)
 * @author Maxim Hodyrew <maximkou@gmail.com>
 * @group default
 */
class Markdown_OL_Start_Num_Test extends Base_Markdown_Test
{
    public function create_Markdown()
    {
        $markdown = new Markdown();
        $markdown->keep_List_Start_Number = true;
        return $markdown;
    }

    public function get_Data_Paths()
    {
        return [
            'markdown-data' => __DIR__ . '/markdown-ol-start-num-data',
        ];
    }
}
