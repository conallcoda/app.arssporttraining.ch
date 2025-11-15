<?php

namespace App\Data\MetricTypes;

interface MetricType
{
    public static function defaults(): array;

    public static function unit($short = true): ?string;

    public static function rules(): array;
}
