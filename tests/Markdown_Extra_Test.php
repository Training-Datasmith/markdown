<?php

declare(strict_types=1);

namespace cebe\markdown\tests;

use cebe\markdown\Markdown_Extra;

/**
 * @author Carsten Brandt <mail@cebe.cc>
 * @group extra
 */
class Markdown_Extra_Test extends Base_Markdown_Test
{
    public function create_markdown()
    {
        return new Markdown_Extra();
    }

    public function get_data_paths()
    {
        return [
            'markdown-data' => __DIR__ . '/markdown-data',
            'extra-data' => __DIR__ . '/extra-data',
        ];
    }
}
