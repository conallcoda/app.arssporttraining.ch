<?php

namespace App\Models\Contracts;

use Filament\Schemas\Schema;

interface HasForms
{
    public function getFields(): array;

    public function getForm(Schema $schema): array;
}
