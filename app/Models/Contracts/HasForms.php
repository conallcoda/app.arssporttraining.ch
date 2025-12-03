<?php

namespace App\Models\Contracts;

interface HasForms
{
    public function getFields(): array;
}
