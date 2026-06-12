<?php

namespace Coda\Cms;

enum ComponentType: string
{
    case List = 'list';
    case Form = 'form';
    case Tree = 'tree';
    case Custom = 'custom';
}
