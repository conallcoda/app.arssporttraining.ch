# Performance Improvements Plan

Date: 2026-03-25

## Context

Full-stack performance audit of the athlete-training application. The codebase is Laravel 12 / Livewire 4 / Flux UI Pro / Alpine.js. The heaviest page is the calendar (`CalendarIndex` → `CalendarProgramsView` at 1,194 lines with 12+ computed properties).

Key findings from the audit:
- Zero caching anywhere in the application
- Database-backed sessions and cache (competing with app queries)
- N+1 query patterns in metric computed properties
- Large JSON payloads serialized inline into Alpine data attributes
- Missing composite database indexes on frequently queried columns
- Single monolithic JS bundle (no code splitting)
- No config/route/view caching in deployment
- No background jobs — all processing is synchronous

---

## Tier 1: Quick wins

### 1. Add missing composite database indexes

**Problem:** `CalendarProgramsView` (lines 175-190) queries `training_program_blocks` by `(group_id, category_id)` and `(group_id, user_id)` on every render. No composite indexes exist — queries fall back to individual foreign key indexes.

**Fix:** One migration adding composite indexes:
- `training_program_blocks`: `(group_id, category_id)` and `(group_id, user_id)`
- `exercise_programs`: `(owner_id, exercise_category_id)`

**Files:** New migration

**Risk:** None. Additive change, no schema modification.

**Effort:** 10 minutes

---

### 2. Collapse N+1 in `currentMetricValues()`

**Problem:** `CalendarProgramsView::currentMetricValues()` (lines 491-510) loops `MetricEnum::cases()` (2 cases: OneRepMax, HeartRate) and fires a separate query per case. That's 4 queries minimum per render (2 queries + 2 eager loads for `values`).

**Fix:** Single query using `whereIn('metric', [...])` with a `ROW_NUMBER()` window function or a latest-per-group pattern to fetch the most recent submission per metric type in one hit, then partition in PHP.

**Files:** `app/Livewire/Training/CalendarProgramsView.php`

**Risk:** Low. Same data, different query strategy. Testable via existing metric tests.

**Effort:** 20 minutes

---

### 3. Trim `MetricSubmissionData` payloads in cell data

**Problem:** `metricCellData` (line 475) and `currentMetricValues` (line 507) serialize the full `MetricSubmissionData` DTO into every metric cell's Alpine data attribute. This includes relationship data, timestamps, and fields the frontend never reads. `groupMetricCellData` is worse — it does this for every athlete in the group.

**Fix:** Return only the fields Alpine actually uses (likely `id`, `summary`, `recorded_at`, `user_id`). The full DTO can be fetched on-demand when the metric popover opens.

**Files:** `app/Livewire/Training/CalendarProgramsView.php` (methods `metricCellData`, `groupMetricCellData`, `currentMetricValues`)

**Risk:** Low. Must verify the popover and metric form don't read from the inline data. If they do, fetch the full DTO via the existing Alpine fetch endpoint instead.

**Effort:** 30 minutes

---

### 4. Add debounce to number inputs in exercise-creator

**Problem:** `wire:model.live` on number inputs (`defaultWeeks`, `maxWidth`) sends a Livewire request on every keystroke/spin. Typing "12" fires two requests.

**Fix:** Change to `wire:model.live.debounce.300ms` on those inputs.

**Files:** Exercise creator blade template(s)

**Risk:** None.

**Effort:** 5 minutes

---

### 5. Remove `console.log` statements from Alpine components

**Problem:** `calendar-cell-select.js` has ~6 `console.log` calls in production code paths (`_clearOtherInstance`, click handlers).

**Fix:** Delete them.

**Files:** `resources/js/alpine/calendar-cell-select.js`

**Risk:** None.

**Effort:** 5 minutes

---

### 6. Guard metric computed properties behind `metricsLoaded`

**Problem:** `metricsLoaded` flag exists (line 48) but metric computed properties (`metricCellData`, `groupMetricCellData`, `currentMetricValues`, `metricSummaryDates`) may not all short-circuit when `metricsLoaded === false`. If they don't, the queries still run on initial render before the user requests them.

**Fix:** Add `if (!$this->metricsLoaded) return [];` at the top of each metric computed property.

**Files:** `app/Livewire/Training/CalendarProgramsView.php`

**Risk:** None. Metrics section already lazy-loads via user interaction.

**Effort:** 10 minutes

---

## Tier 2: Moderate effort, high impact

### 7. Cache tag queries per request

**Problem:** Tag queries (`Tag::query()->forScope(...)`) run on every table render in `ExerciseProgramList` and `ExerciseList` — same immutable data fetched repeatedly within the same request and across Livewire updates.

**Fix:** Use `Cache::remember()` with a short TTL (60 seconds) or `once()` (Laravel's request-scoped memoization) for tag lookups. Invalidate on tag create/update/delete if using a longer TTL.

**Files:** `app/Livewire/Database/ExerciseProgramList.php`, `app/Livewire/Database/ExerciseList.php`

**Risk:** Low. Tags change rarely. Short TTL eliminates stale-data concerns.

**Effort:** 30 minutes

---

### 8. Reduce computed property cascade on events

**Problem:** `onCalendarRangeChanged` (lines 90-103) invalidates 10+ computed properties at once. Many depend on each other (`categoryBlocks` depends on `days`, `groupedPrograms` depends on `programs`), so unsetting all of them forces full recalculation even for properties that weren't accessed in the current render.

**Fix:** Only unset root properties (`days`, `programs`, `allBlocks`). Dependent properties recompute naturally when their upstream dependencies change via Livewire's computed property caching.

**Files:** `app/Livewire/Training/CalendarProgramsView.php` (event handlers)

**Risk:** Medium. Must verify Livewire 4's computed caching behaviour — if dependents don't auto-invalidate when a root is unset, this won't work. Test thoroughly.

**Effort:** 30 minutes

---

### 9. Deduplicate `allBlocks` and `categoryBlocks` query logic

**Problem:** Both methods (lines 162-251 and 253+) build nearly identical date-range overlap queries with the same clamping and lane-assignment logic. Both run on every render.

**Fix:** Extract shared query + lane-assignment into `CalendarBlockService` (which already exists). Optionally fetch both block types in a single query differentiated by `category_id IS NULL` vs not, then split in PHP.

**Files:** `app/Livewire/Training/CalendarProgramsView.php`, `app/Training/CalendarBlockService.php`

**Risk:** Low. Refactor with identical output. Testable by comparing before/after results.

**Effort:** 1 hour

---

### 10. Switch sessions and cache driver away from database

**Problem:** Both `SESSION_DRIVER` and `CACHE_STORE` are `database`. Every session read/write and every cache operation is a DB query competing with application queries.

**Fix:**
- **If Redis is available** (Herd supports it via `herd enable-redis`): switch both to `redis`.
- **If not:** switch sessions to `file` (Laravel default, zero-config). Consider `file` for cache too.

**Files:** `.env`, `.env.production`

**Risk:** Low. Standard Laravel configuration change. Test session persistence after switching.

**Effort:** 5 minutes (config change) + testing

---

### 11. Throttle Alpine fetch calls in `calendar-slot-popover.js`

**Problem:** The popover makes two `fetch()` calls (`/admin/api/program-grid-cells` and `/admin/api/slot-details`) without debouncing or cancellation. Rapidly hovering/clicking slots queues multiple concurrent requests.

**Fix:** Add an `AbortController` pattern — cancel the previous request when a new one starts. Optionally add a small delay (100-150ms) before firing.

**Files:** `resources/js/alpine/calendar-slot-popover.js`

**Risk:** None. Strictly improves behaviour.

**Effort:** 20 minutes

---

## Tier 3: Larger effort, strategic wins

### 12. Vite code splitting

**Problem:** `vite.config.js` builds a single monolithic JS bundle. All 11 Alpine components, the calendar library (`@event-calendar/core`), `treeselectjs`, etc. load on every page including login.

**Fix:** Split into at least 2 entry points:
- `app.js` — core (Livewire, Alpine, Flux)
- `calendar.js` — calendar-specific Alpine components + event-calendar library

Load `calendar.js` only on calendar routes via `@vite('resources/js/calendar.js')` in the calendar layout.

**Files:** `vite.config.js`, `resources/js/app.js`, new `resources/js/calendar.js`, calendar layout blade

**Risk:** Medium. Must verify Alpine components register correctly when split. Test all pages after splitting.

**Effort:** 1-2 hours

---

### 13. Add config/route/view caching to deployment

**Problem:** No caching commands in deployment. Every request parses all config files, route definitions, and Blade templates from disk.

**Fix:** Add to deployment script (or post-deploy hook):
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Files:** Deployment script / CI pipeline

**Risk:** Low. Standard Laravel production practice. Remember to re-run after config changes. Note: `config:cache` means `env()` calls outside config files will return `null`.

**Effort:** 5 minutes

---

### 14. Offload heavy metric calculations to background jobs

**Problem:** Metric cell data computation (projected 1RM, heart rate calculations) runs synchronously during Livewire renders. As athlete count grows, this becomes the primary bottleneck.

**Fix:** When a `MetricSubmission` is created/updated, dispatch a job that pre-computes the cell data and stores it in a cache key or dedicated table. The Livewire component reads the pre-computed result instead of calculating live.

**Files:** New job class, `MetricSubmission` model observer, `CalendarProgramsView.php` metric methods

**Risk:** Medium. Introduces eventual consistency — metric data may be slightly stale between submission and job completion. Requires the queue worker to be running (`php artisan queue:work`).

**Effort:** 2-3 hours

---

### 15. Extract CalendarProgramsView into smaller Livewire components

**Problem:** At 1,194 lines with 12+ computed properties, this component does too much. Every Livewire interaction re-evaluates the full property graph. The HTML payload includes the entire component's state on every round-trip.

**Fix:** Split into focused child components:
- `CalendarBlocksRow` — owns `allBlocks`, `categoryBlocks`
- `CalendarMetricsSection` — owns all metric computed properties
- `CalendarProgramGrid` — owns slot/program display

Parent coordinates via events. Each child only re-renders when its own data changes.

**Files:** `app/Livewire/Training/CalendarProgramsView.php` (split), new component classes, updated blade templates

**Risk:** Medium-high. Largest refactor. Requires careful event wiring between parent and children. Must preserve all existing functionality.

**Effort:** 4-6 hours

---

## Implementation priority

| Priority | Item | Effort | Impact | Risk |
|----------|------|--------|--------|------|
| 1 | #1 Database indexes | 10 min | High | None |
| 2 | #6 Lazy-load metrics guard | 10 min | High | None |
| 3 | #2 Collapse N+1 currentMetricValues | 20 min | Medium | Low |
| 4 | #3 Trim metric payloads | 30 min | Medium | Low |
| 5 | #13 Config/route/view cache | 5 min | Medium | Low |
| 6 | #10 Session/cache driver | 5 min | Medium | Low |
| 7 | #5 Remove console.logs | 5 min | Low | None |
| 8 | #4 Debounce number inputs | 5 min | Low | None |
| 9 | #7 Cache tag queries | 30 min | Medium | Low |
| 10 | #8 Reduce computed cascade | 30 min | Medium | Medium |
| 11 | #9 Deduplicate block queries | 1 hr | Medium | Low |
| 12 | #11 Throttle Alpine fetches | 20 min | Low-Med | None |
| 13 | #12 Vite code splitting | 1-2 hr | Medium | Medium |
| 14 | #14 Background metric jobs | 2-3 hr | High | Medium |
| 15 | #15 Split CalendarProgramsView | 4-6 hr | High | Med-High |

Items 1-8 can likely be done in a single session. Items 9-12 are a second pass. Items 13-15 are standalone pieces of work.
