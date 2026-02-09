<?php

namespace App\Cms\Form;

enum ActionPlacement: string
{
    case Header = 'header';
    case Row = 'row';
    case RowMenu = 'row_menu';
}
