@props([
    'grid',
    'name' => 'Untitled',
    'showHeader' => true,
    'settingClickable' => false,
    'collapseWeeks' => true,
    'copyMenuOptions' => [],
])

<x-training.exercise-grid
    :grid="$grid"
    :name="$name"
    :showHeader="$showHeader"
    :settingClickable="$settingClickable"
    :collapseWeeks="$collapseWeeks"
    :copyMenuOptions="$copyMenuOptions"
    :showActualValues="false"
    valueDisplayMode="planned"
/>
