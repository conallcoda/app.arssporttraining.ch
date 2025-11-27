<?php

use App\Filament\Forms\Components\ColorPicker;
use App\Models\Training\TrainingSessionCategory;
use function Livewire\Volt\{state, computed, on};

state([
    'categories' => [],
    'activeCategory' => null,
]);

$allCategories = computed(fn() => TrainingSessionCategory::all()->keyBy('id'));

$activeCategoryModel = computed(function () {
    if (!$this->activeCategory) {
        return null;
    }
    return $this->allCategories->get($this->activeCategory);
});

$getColorValue = function (?string $colorName, int $shade = 500): string {
    if (!$colorName) {
        return '#000000';
    }
    return ColorPicker::getColorValue($colorName, $shade);
};

$setActiveCategory = function (int $categoryId) {
    $this->activeCategory = $categoryId;
    $this->dispatch('progression-category-changed', categoryId: $categoryId);
};

on([
    'progression-categories-updated' => function ($categories, $activeCategory) {
        $this->categories = $categories;
        $this->activeCategory = $activeCategory;
    },
    'progression-category-changed' => function ($categoryId) {
        $this->activeCategory = $categoryId;
    },
]);

?>

<div>
    @if (count($categories) > 0)
        @php
            $activeCat = $this->activeCategoryModel;
            $bgColor = $activeCat ? $this->getColorValue($activeCat->background_color) : '#3b82f6';
            $textColor = $activeCat ? $this->getColorValue($activeCat->text_color) : '#ffffff';
        @endphp
        <flux:dropdown>
            <flux:button size="sm" style="background-color: {{ $bgColor }}; color: {{ $textColor }}; border: none;">
                {{ $activeCat?->name ?? 'Select Category' }}
                <x-lucide-chevron-down class="w-3 h-3 ml-1" />
            </flux:button>
            <flux:menu>
                @foreach ($categories as $category)
                    @php
                        $cat = $this->allCategories->get($category['id']);
                        $catBgColor = $cat ? $this->getColorValue($cat->background_color) : '#3b82f6';
                    @endphp
                    <flux:menu.item
                        wire:click="setActiveCategory({{ $category['id'] }})"
                        :class="$activeCategory === $category['id'] ? 'bg-zinc-100 dark:bg-zinc-800' : ''"
                    >
                        <span class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full" style="background-color: {{ $catBgColor }};"></span>
                            {{ $category['name'] }}
                        </span>
                    </flux:menu.item>
                @endforeach
            </flux:menu>
        </flux:dropdown>
    @endif
</div>
