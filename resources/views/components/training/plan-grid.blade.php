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
    'resetMenuOptions' => [],
    'showPreview' => false,
    'showPlannedActualSetColumns' => false,
    'showPlannedActualToggle' => false,
    'plannedActualToggleActive' => false,
    'plannedActualToggleAction' => null,
    'actualCellValues' => [],
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
    :resetMenuOptions="$resetMenuOptions"
    :showPreview="$showPreview"
    :showPlannedActualSetColumns="$showPlannedActualSetColumns"
    :showPlannedActualToggle="$showPlannedActualToggle"
    :plannedActualToggleActive="$plannedActualToggleActive"
    :plannedActualToggleAction="$plannedActualToggleAction"
    :actualCellValues="$actualCellValues"
    valueDisplayMode="planned"
/>
