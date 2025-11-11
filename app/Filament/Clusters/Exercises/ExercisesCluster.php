<?php

namespace App\Filament\Clusters\Exercises;

use BackedEnum;
use Filament\Clusters\Cluster;

class ExercisesCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'lucide-dumbbell';
}
