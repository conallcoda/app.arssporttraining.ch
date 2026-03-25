# Alpine x-for Grid Cells Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace Blade `@foreach` day-cell rendering with Alpine `x-for` to reduce HTML from ~3.6MB to ~200KB, cutting Livewire wire responses from ~24MB to ~2MB.

**Architecture:** Pass the `days` array and per-row context (blockDayMap, metric data) as JSON to Alpine `x-data`. Each row uses `<template x-for>` to render `<td>` elements client-side. Cell content comes from Alpine data already fetched via the `gridCells` API. Metric cell data is passed as JSON per-metric-row.

**Tech Stack:** Alpine.js x-for, existing `calendar_slot_popover` Alpine component, existing `gridCells` API endpoint.

---

### Task 1: Pass days array to Alpine scope in CalendarProgramsView

**Files:**
- Modify: `resources/views/livewire/training/calendar-programs-view.blade.php:7`

**Step 1: Add days JSON to the calendar_slot_popover x-data init**

The `x-data="calendar_slot_popover({...})"` on line 7 already receives config. Add the `days` array to it. The Alpine component will expose this for child `x-for` loops.

In `calendar-slot-popover.js`, add `days` to the component data:

```js
days: config.days || [],
```

In the blade, add `days` to the config:

```blade
x-data="calendar_slot_popover({ ..., days: {{ \Illuminate\Support\Js::from($this->days) }} })"
```

**Step 2: Run `npm run build`**

**Step 3: Verify** — Check browser console, confirm `days` array is accessible in Alpine.

---

### Task 2: Convert program slot cells to Alpine x-for

**Files:**
- Modify: `resources/views/livewire/training/calendar-programs-view.blade.php` (lines 362-384)

**Step 1: Replace the Blade @foreach day loop inside each program row**

Current (lines 362-384): Blade `@foreach ($this->days as $dayIdx => $day)` renders 42 `<td>` elements per program row with `handleCellClick`, `getCellCount` etc.

Replace with Alpine `x-for`. Pass the `blockDayMap` for this category as JSON to the category `<tbody>` Alpine scope. Each program row uses `<template x-for>` to render cells:

```blade
<template x-for="(day, dayIdx) in days" :key="day.date">
    <td @click="handleCellClick($event.currentTarget, {{ $entry->id }}, day.date, '{{ $categoryColor }}')"
        class="border-r border-b border-zinc-300 dark:border-zinc-600 p-1 cursor-pointer hover:brightness-95 dark:hover:brightness-125"
        :class="[
            blockDayMap[dayIdx] ? '{{ $categoryBlockBgClass }}' : (day.oddWeek ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : ''),
            (endIdx !== null ? (dayIdx >= Math.min(anchorIdx, endIdx) && dayIdx <= Math.max(anchorIdx, endIdx)) : anchorIdx === dayIdx) && 'ring ring-inset ring-black dark:ring-white'
        ]"
        :title="blockDayMap[dayIdx]?.note || ''">
        <button x-show="getCellCount({{ $entry->id }}, day.date) > 0"
            x-cloak
            type="button"
            @click.stop="handleCellClick($event.currentTarget, {{ $entry->id }}, day.date, '{{ $categoryColor }}')"
            class="w-full aspect-square flex items-center justify-center text-[10px] font-medium text-white rounded-sm cursor-pointer {{ $colorClass ?: 'bg-emerald-400/80 dark:bg-emerald-500/60' }}"
            x-text="getCellCount({{ $entry->id }}, day.date)">
        </button>
        <div x-show="cellDataLoaded && getCellCount({{ $entry->id }}, day.date) === 0"
            x-cloak
            class="aspect-square flex items-center justify-center cursor-pointer group/empty">
            <svg class="size-3 text-zinc-400 dark:text-zinc-500 opacity-0 group-hover/empty:opacity-100 transition-opacity" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
        </div>
    </td>
</template>
```

**Step 2: Pass blockDayMap as JSON to the category tbody**

The category `<tbody>` already has `x-data="calendar_cell_select({...})"`. Merge blockDayMap into it:

```blade
<tbody x-data="{ ...calendar_cell_select({ type: 'category', categoryId: {{ $categoryId }} }), blockDayMap: {{ \Illuminate\Support\Js::from($blockDayMap) }} }"
```

**Step 3: Verify** — Load calendar, click into Performance Test group, confirm program cells render correctly with slot counts.

---

### Task 3: Convert category indicator cells to Alpine x-for

**Files:**
- Modify: `resources/views/livewire/training/calendar-programs-view.blade.php` (lines 289-312)

**Step 1: Replace the category indicator row's Blade @foreach**

Current: Blade `@foreach` renders per-day indicator squares with conditional `hasCategoryData()` Alpine calls and block background detection.

Replace with Alpine `x-for`:

```blade
<template x-for="(day, dayIdx) in days" :key="'ind-' + day.date">
    <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-1 cursor-pointer hover:brightness-95 dark:hover:brightness-125"
        :class="[
            blockDayMap[dayIdx] ? '{{ $categoryBlockBgClass }}' : (day.oddWeek ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : ''),
            (endIdx !== null ? (dayIdx >= Math.min(anchorIdx, endIdx) && dayIdx <= Math.max(anchorIdx, endIdx)) : anchorIdx === dayIdx) && 'ring ring-inset ring-black dark:ring-white'
        ]"
        @mousedown.stop="!blockDayMap[dayIdx] && startDrag(dayIdx, day.date, $event)"
        @mouseover="!blockDayMap[dayIdx] && dragOver(dayIdx, day.date)"
        @contextmenu="!blockDayMap[dayIdx] && showContextMenu($event, dayIdx, day.date)"
        @click="blockDayMap[dayIdx] && $wire.editBlock(blockDayMap[dayIdx].id)">
        <div class="aspect-square" :class="hasCategoryData({{ json_encode($categoryProgramIds) }}, day.date) && 'rounded-sm {{ $categoryColorClass }}'"></div>
    </td>
</template>
```

**Step 2: Verify** — Confirm indicator dots appear correctly, click on block cells opens edit.

---

### Task 4: Convert metrics summary and detail cells to Alpine x-for

**Files:**
- Modify: `resources/views/livewire/training/calendar-programs-view.blade.php` (lines 100-167)
- Modify: `app/Livewire/Training/CalendarProgramsView.php` — add method to return metric data as JSON

**Step 1: Convert the metrics summary header row (line 100)**

Pass `metricSummaryDates` as JSON. Replace Blade loop:

```blade
<template x-for="day in days" :key="'ms-' + day.date">
    <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-1"
        :class="day.oddWeek ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : ''">
        <div class="aspect-square" :class="metricSummaryDates[day.date] !== undefined && 'rounded-sm bg-zinc-300 dark:bg-zinc-700'"></div>
    </td>
</template>
```

Pass data: add `metricSummaryDates: {{ \Illuminate\Support\Js::from($this->metricSummaryDates) }}` to the metrics tbody `x-data`.

**Step 2: Convert the per-metric detail rows (line 124)**

For each metric row, pass the metric cell data as JSON. For the user view, pass `metricCellData` filtered for that metric. For the group view, pass `groupMetricCellData` filtered for that metric.

Add a helper to CalendarProgramsView that returns metric data for a specific metric type:

```php
public function getMetricRowData(string $metricValue): array
{
    $data = $this->userId !== null ? $this->metricCellData : $this->groupMetricCellData;
    $filtered = [];
    foreach ($data as $key => $value) {
        if (str_starts_with($key, $metricValue . '-')) {
            $date = substr($key, strlen($metricValue) + 1);
            $filtered[$date] = $value;
        }
    }
    return $filtered;
}
```

Then in blade, per metric row:

```blade
@php $metricRowData = $this->getMetricRowData($metricCase->value); @endphp
<tr ... x-data="{ metricData: {{ \Illuminate\Support\Js::from($metricRowData) }} }">
    <td>{{ label }}</td>
    <template x-for="day in days" :key="'m-{{ $metricCase->value }}-' + day.date">
        <!-- Alpine-rendered metric cell -->
    </template>
</tr>
```

The metric cell template renders differently for user vs group view. Pass `isUserView` flag:

For user view: click dispatches `$wire.openMetricCell(metricValue, day.date)`, shows label or + icon.
For group view: click opens metric popover with `openPopover()`, shows count or + icon.

**Step 3: Verify** — Expand metrics section, confirm cells render for both group and athlete views.

---

### Task 5: Convert overview grid day cells to Alpine x-for

**Files:**
- Modify: `resources/views/livewire/training/calendar-overview-grid.blade.php` (lines 113-145)

**Step 1: Pass days and group date colors to Alpine**

The overview grid's per-group `<tbody>` already has `x-data` with `members`, `days` (a simplified version). The group summary row renders color gradients per day.

Pass `dateColors` and `dates` from `$groupRow` to the Alpine scope. Replace the Blade `@foreach` on the group summary row with `x-for`:

```blade
<template x-for="(day, dayIdx) in days" :key="'g-' + day.date">
    <td class="border-r border-b border-zinc-300 dark:border-zinc-600 p-0"
        :class="!dates[day.date] && (day.isToday ? 'bg-blue-50/50 dark:bg-blue-900/10' : (day.oddWeek ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : ''))"
        :style="dates[day.date] ? buildGradient(dateColors[day.date]) : ''">
        <div class="aspect-square"></div>
    </td>
</template>
```

Add a `buildGradient(colorCounts)` method to the Alpine scope that computes the CSS gradient (moving the current PHP logic to JS):

```js
buildGradient(colorCounts) {
    if (!colorCounts) return ''
    let entries = Object.entries(colorCounts)
    let total = entries.reduce((s, [, c]) => s + c, 0)
    if (entries.length === 1) {
        let c = entries[0][0]
        return 'background-color: ' + (c === '_none' ? 'var(--color-zinc-300)' : 'var(--color-' + c + '-600)') + ';'
    }
    let pos = 0
    let stops = entries.map(([c, cnt]) => {
        let pct = (cnt / total * 100).toFixed(2)
        let css = c === '_none' ? 'var(--color-zinc-300)' : 'var(--color-' + c + '-600)'
        let s = css + ' ' + pos + '% ' + (parseFloat(pos) + parseFloat(pct)) + '%'
        pos = parseFloat(pos) + parseFloat(pct)
        return s
    })
    return 'background: linear-gradient(to bottom, ' + stops.join(', ') + ');'
}
```

Note: this gradient logic already exists in the Alpine member row rendering (`buildMemberRows`). Refactor to share.

**Step 2: Pass full days array with all properties**

The overview grid currently passes a simplified `days` array to Alpine. Change to pass the full `$this->days` with `isToday`, `oddWeek`, etc.

**Step 3: Also pass `dates` and `dateColors`**

```blade
dates: {{ \Illuminate\Support\Js::from($groupRow['dates']) }},
dateColors: {{ \Illuminate\Support\Js::from($groupRow['dateColors']) }},
```

**Step 4: Verify** — Load calendar without selection, confirm overview grid renders with color gradients.

---

### Task 6: Convert notes section drag zone cells to Alpine x-for

**Files:**
- Modify: `resources/views/livewire/training/calendar-programs-view.blade.php` (lines 49-57)

**Step 1: Replace the notes row background cells**

The notes row renders transparent drag-target cells over the absolute-positioned blocks. Replace Blade `@foreach` with Alpine `x-for`:

```blade
<template x-for="(day, dayIdx) in days" :key="'note-' + day.date">
    <div @mousedown.stop="startDrag(dayIdx, day.date, $event)"
         @mouseover="dragOver(dayIdx, day.date)"
         @contextmenu="showContextMenu($event, dayIdx, day.date)"
         class="flex-1 cursor-pointer h-full border-r border-zinc-300 dark:border-zinc-600 last:border-r-0 select-none"
         :class="[
             day.oddWeek ? 'bg-zinc-50/50 dark:bg-zinc-700/10' : '',
             (endIdx !== null ? (dayIdx >= Math.min(anchorIdx, endIdx) && dayIdx <= Math.max(anchorIdx, endIdx)) : anchorIdx === dayIdx) && 'ring ring-inset ring-black dark:ring-white'
         ]">
    </div>
</template>
```

Note: these are `<div>` elements inside an absolute flex container, not `<td>`. The `x-for` `<template>` works here.

**Step 2: Verify** — Confirm note drag selection still works.

---

### Task 7: Remove unused server-side computed properties

**Files:**
- Modify: `app/Livewire/Training/CalendarProgramsView.php`

**Step 1: Remove `metricSummaryDates` computed if metric summary is now Alpine-driven**

If the metric summary header is now rendered via Alpine using data from the `x-data`, the `metricSummaryDates` computed is only used to pass JSON to Alpine — it can stay as-is for the initial render, but no longer needs to be in the Livewire HTML response on re-renders.

Actually: keep `metricSummaryDates` — it's small data passed once as JSON. The big savings come from not rendering the cells server-side.

**Step 2: Verify unused computeds removed from CalendarIndex `unset()` calls**

Grep for any remaining `unset` of `programCellSlots`, `athleteSlotOrder`, `groupSlotOrder` across all files and remove.

---

### Task 8: Build, clear caches, and measure

**Step 1:** Run `npm run build`

**Step 2:** Run `php artisan view:clear && php artisan config:clear`

**Step 3:** Clear profile log: `> storage/logs/livewire-profile-performance25mar.log`

**Step 4:** Test the flow: calendar overview → Performance Test group → switch users → expand metrics

**Step 5:** Check profile log for `response_kb` — should be dramatically smaller

**Step 6:** Check Chrome DevTools Performance tab — DOM nodes should stay well under 100K

**Step 7:** Measure HTML size with tinker test:
```bash
php artisan tinker --execute="..."
```
Target: < 500 KB HTML (was 3.6 MB)

---

### Task 9: Commit

```bash
git add -A
git commit -m "perf: render grid day cells with Alpine x-for instead of Blade

Reduces HTML payload from ~3.6MB to ~300KB for the Performance Test
group (38 programs × 42 days). Livewire wire responses drop from
~24MB to ~2MB. Eliminates DOM explosion (2M+ nodes → <100K)."
```
