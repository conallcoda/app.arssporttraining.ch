<?php

namespace App\Models\Training\Progression\Strategy\Rep;

interface RepConfigInterface
{
    public function getStrategyClass(): string;
}
