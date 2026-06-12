@props([
    'chart' => null,
    'chartExpression' => null,
    'heightClass' => 'h-64',
])

@php
    $resolvedChartExpression = is_string($chartExpression) && $chartExpression !== ''
        ? $chartExpression
        : \Illuminate\Support\Js::from($chart);
    $chartDataExpression = "cmsChart({$resolvedChartExpression})";
    $chartEffectExpression = "setDefinition({$resolvedChartExpression})";
@endphp

<div
    x-data="{{ $chartDataExpression }}"
    x-init="init()"
    x-effect="{{ $chartEffectExpression }}"
    {{ $attributes->class(['relative w-full', $heightClass]) }}
>
    <canvas x-ref="canvas"></canvas>
</div>
