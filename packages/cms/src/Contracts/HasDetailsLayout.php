<?php

namespace Coda\Cms\Contracts;

use Coda\Cms\Layout\Layout;

interface HasDetailsLayout
{
    public static function getDetailsLayout(): Layout;
}
