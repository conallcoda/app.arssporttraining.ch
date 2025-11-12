<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Livewire\Wireable;
use Spatie\LaravelData\Concerns\WireableData;

abstract class AbstractData extends Data implements Wireable
{
    use WireableData;
}
