# Alpine x-for Grid Cells Design

## Problem

The calendar programs grid renders 38 programs × 42 days = 1,596 `<td>` elements via Blade `@foreach`, producing ~3.6MB of HTML. Livewire JSON-encodes this into ~24MB wire responses. The browser chokes on morphing 2M+ DOM nodes.

## Solution

Move per-day cell rendering from Blade `@foreach` to Alpine `x-for`. Server renders the table skeleton (headers, row labels, block structures). Alpine renders day cells client-side using data already fetched via the `gridCells` API.

## What converts to Alpine x-for

1. **Program slot cells** (biggest win — 1,596 cells removed from server HTML)
2. **Category indicator cells** (per-category × 42 days)
3. **Metric day cells** (2 metrics × 42 days)
4. **Overview group/member day cells** (per-group × 42 days)

## What stays as Blade @foreach

- Table headers (months, weeks, day labels) — structural, small
- Notes row (absolute-positioned blocks with lane layout)
- Block label rows (colspan skip logic)

## Data flow

- `days` array → passed once as JSON to top-level Alpine `x-data`
- `blockDayMap` → passed per-category as JSON for cell background styling
- Cell slot data → `gridCells` API (already used by `calendar_slot_popover`)
- Metric cell data → passed per-metric-row as JSON from Livewire computed
- Overview colors → `memberColors` API (already used)

## API changes

- `gridCells` endpoint extended to return `{count, time}` when `user_id` is provided (already done)

## Estimated impact

- HTML: ~3.6MB → ~200-300KB
- Wire response: ~24MB → ~2-3MB
- DOM nodes on initial render: ~80% reduction
- Browser morph time: proportional reduction

## Files to modify

- `resources/views/livewire/training/calendar-programs-view.blade.php` — program cells, category indicators, metric cells
- `resources/views/livewire/training/calendar-overview-grid.blade.php` — group/member day cells
- `resources/js/alpine/calendar-slot-popover.js` — already updated with `handleCellClick`, `getCellTime`
- `app/Livewire/Training/CalendarProgramsView.php` — remove unused server-side computeds
