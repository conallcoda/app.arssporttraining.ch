<?php

namespace App\Cms\Form;

enum ActionBehavior: string
{
    case FormModal = 'form_modal';
    case Confirm = 'confirm';
    case Direct = 'direct';
    case AlpineEvent = 'alpine_event';
}
