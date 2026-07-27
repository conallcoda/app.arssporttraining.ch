@php
    $inputAttributes = new \Illuminate\View\ComponentAttributeBag([
        $field->wireModelDirective() => $wireModel,
    ]);
@endphp

<flux:input :attributes="$inputAttributes->merge($attributes)" :placeholder="$explicitPlaceholder($field)" :size="$field->size" clearable>
    <x-slot:icon>
        <flux:icon.magnifying-glass variant="micro" />
    </x-slot:icon>
</flux:input>
