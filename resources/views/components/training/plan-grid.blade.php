@props([
    'grid',
    'name' => 'Untitled',
    'showHeader' => true,
    'showMenu' => true,
    'editable' => true,
    'settingClickable' => false,
    'collapseWeeks' => true,
    'copyMenuOptions' => [],
    'previewMenuOptions' => [],
    'showPreview' => false,
])

<x-training.exercise-grid
    :grid="$grid"
    :name="$name"
    :showHeader="$showHeader"
    :showMenu="$showMenu"
    :editable="$editable"
    :settingClickable="$settingClickable"
    :collapseWeeks="$collapseWeeks"
    :copyMenuOptions="$copyMenuOptions"
    :previewMenuOptions="$previewMenuOptions"
    :showPreview="$showPreview"
    :showActualValues="false"
    valueDisplayMode="planned"
/>
