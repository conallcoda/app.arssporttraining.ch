# Session-First Planning — Table-Backed Override Persistence

Date: 2026-04-30

## Goal

Continue from the previous completion report's "Important Nuances" and complete the final architectural step:

> Replace JSON-backed flattened override persistence with dedicated normalized planning tables. Let config objects hold defaults/strategies only.

The user explicitly chose Option A (full normalized tables) and requested autonomous execution.

## What Was Done

### 1. New `exercise_plan_config_overrides` table

Created [database/migrations/2026_04_30_120000_create_exercise_plan_config_overrides_table.php](database/migrations/2026_04_30_120000_create_exercise_plan_config_overrides_table.php).

Schema:

```
id
owner_type, owner_id            -- morph to ExerciseProgram or ExercisePlan
program_exercise_id             -- FK to exercise_program_exercises.id (cascadeOnDelete)
user_id                         -- nullable FK to users.id (cascadeOnDelete); null for default overrides
scope                           -- current | historical | baseline
target                          -- session | cell
week_index, session_index       -- override coordinates
set_index                       -- nullable; only set for cell-target rows
setting_key                     -- e.g. reps, weight, rest, ...
value                           -- JSON; can hold scalar or structured value
created_at, updated_at
```

Indexes:
- `epco_owner_pivot_user_idx` on `(owner_type, owner_id, program_exercise_id, user_id)`
- `epco_pivot_user_idx` on `(program_exercise_id, user_id)`
- Implicit owner index from `morphs('owner')`

The migration also performs an in-place backfill:
1. Walks every row in `exercise_programs` and `exercise_plans`.
2. Reads `config.overrideValues` (the flat shape introduced by the prior pass) plus any legacy nested `gridOverrides` / `historicalGridOverrides` / `baselineGridOverrides` still sitting on `exercises[*]` and `userExercises[*][*]`.
3. Filters orphan rows whose `program_exercise_id` no longer corresponds to a real pivot.
4. Inserts override rows.
5. Strips `overrideValues` and the nested override bags from the JSON config and writes it back.

### 2. New `ExercisePlanConfigOverride` Eloquent model

Created [app/Models/Exercise/ExercisePlanConfigOverride.php](app/Models/Exercise/ExercisePlanConfigOverride.php).

- `owner()` morph to `ExerciseProgram` or `ExercisePlan`.
- `programExercise()` belongsTo `ExerciseProgramExercise`.
- `user()` belongsTo `User`.
- `getDecodedValue()` decodes the JSON `value` column to its native scalar/array form.
- `toFlatArray()` produces the runtime row shape (`programExerciseId`, `userId`, `scope`, `target`, `week`, `session`, `set`, `settingKey`, `value`) consumed by `ExercisePlanConfig`.

### 3. Owner-side trait `HasPlanConfigOverrides`

Created [app/Models/Concerns/HasPlanConfigOverrides.php](app/Models/Concerns/HasPlanConfigOverrides.php).

- `planConfigOverrides()` morph relation.
- Overrides `save()` so override row sync runs after `parent::save()` for both `save()` and `saveQuietly()` paths.
- `stashPendingPlanConfigOverrideRows()` / `flushPendingPlanConfigOverrideRows()` couple the cast `set()` write to the post-save sync.
- `syncPlanConfigOverrideRows()` does a keyed diff of existing rows vs. desired rows and applies only the deletes / inserts / updates that actually changed — it doesn't rewrite the whole set.
- A `deleting` listener cleans up override rows on hard delete (soft delete leaves them for restore).

### 4. Custom cast `ExercisePlanConfigCast`

Created [app/Casts/ExercisePlanConfigCast.php](app/Casts/ExercisePlanConfigCast.php).

- `get()`: decodes the JSON config column, then queries the owner's `planConfigOverrides` relation and injects the rows as `overrideValues` on the way into `ExercisePlanConfig::from()`. The runtime hydration path inside `ExercisePlanConfig::hydrateOverrideValues()` is unchanged, so every consumer that reads `defaultExerciseOverrides()` / `userExerciseOverrides()` / `resolveExercise()` sees the same shape it did before.
- `set()`: accepts either an `ExercisePlanConfig` or array, extracts the flat override rows via `flatOverrideRows()`, stashes them on the owner, and writes the JSON back through `toPersistedArray()` (which no longer contains `overrideValues`).

### 5. `ExercisePlanConfig` reduced to defaults/strategies

Updated [app/Data/Training/Config/ExercisePlanConfig.php](app/Data/Training/Config/ExercisePlanConfig.php).

- `toPersistedArray()` no longer emits `overrideValues`. The persisted JSON shape now contains only `target`, `weeks`, `schedule`, `exercises[*]`, and `userExercises[*][*]`, with override-grid bags stripped from each exercise entry.
- New public method `flatOverrideRows()` exposes the flattened rows for the cast.
- The constructor still accepts `overrideValues` as input — that's how `get()` rehydrates from the table on read.

### 6. Models wired to the new cast

- [app/Models/Exercise/ExerciseProgram.php](app/Models/Exercise/ExerciseProgram.php) — adds `HasPlanConfigOverrides` trait, casts `config` via `ExercisePlanConfigCast`, drops the legacy `Attribute` accessor.
- [app/Models/Exercise/ExercisePlan.php](app/Models/Exercise/ExercisePlan.php) — same.

The legacy `Attribute::make(get: ..., set: ...)` for `config` is gone from both models.

### 7. Export / import round-trip preserved

Updated [app/Console/Commands/ExportDatabaseCommand.php](app/Console/Commands/ExportDatabaseCommand.php).

Both `exportExercisePrograms()` and `exportExercisePlans()` now route through a new `configWithOverrides()` helper that merges the persisted JSON config with `flatOverrideRows()` from the model. So the exported `exercise_programs.php` / `exercise_plans.php` blobs still carry override information in the same `config.overrideValues` form, even though it's no longer in the live JSON column.

Updated [database/seeders/DatabaseImportSeeder.php](database/seeders/DatabaseImportSeeder.php).

`seedExercisePrograms()` now creates the program model first, inserts its `exercise_program_exercises` pivot rows, and only then assigns `config`. This is required because `program_exercise_id` is now an enforced FK on the override table — the pivot rows have to exist before the override-sync fires. Without this reordering, every imported program with overrides would have triggered an FK constraint violation.

### 8. Tests updated to reflect table-backed persistence

- [tests/Unit/Training/Config/ExercisePlanConfigTest.php](tests/Unit/Training/Config/ExercisePlanConfigTest.php) — the test that previously asserted `toPersistedArray()['overrideValues']` was a 3-row array now asserts that `toPersistedArray()` has no `overrideValues` key, that `flatOverrideRows()` returns 3 rows, and that rehydration via `ExercisePlanConfig::from($persisted + ['overrideValues' => $rows])` reproduces the runtime grid shape.
- [tests/Feature/Models/TrainingProgramTest.php](tests/Feature/Models/TrainingProgramTest.php) — the test that previously asserted the raw JSON column contained `overrideValues[0].programExerciseId` now asserts the JSON column has no `overrideValues` key, the model's `planConfigOverrides()` relation contains the row, and the runtime `gridOverrides` shape is reproduced on reload. Also added an explicit assertion on the override row's columns (`program_exercise_id`, `setting_key`, `target`, `scope`, `getDecodedValue()`).
- [tests/Feature/Livewire/Training/PlanExerciseGridScopedRebuildTest.php](tests/Feature/Livewire/Training/PlanExerciseGridScopedRebuildTest.php) — was using a literal `userId => 20` placeholder. With the new `users` FK on the override table, this hit a constraint violation. Replaced with `User::factory()->create()` and threaded the real `$athlete->id` through the test.

## Verification

Final targeted sweep (matches the suites the previous report verified, plus database-related Livewire):

```
php artisan test \
  tests/Unit/Training/Config/ExercisePlanConfigTest.php \
  tests/Feature/Models/TrainingProgramTest.php \
  tests/Feature/Livewire/Training \
  tests/Feature/Training \
  tests/Feature/Data/Exercise/Preview \
  tests/Unit/Data/Exercise/Preview \
  tests/Feature/Livewire/Database
```

Result: **Tests: 135 passed (413 assertions)** — all green.

Full suite (`php artisan test`) before and after this work:
- Before: 16 failed, 499 passed (1283 assertions)
- After:  16 failed, 499 passed (1289 assertions)

The 16 pre-existing failures (in `AutomaticStrategyResolverTest`, `OneRepMaxFixedStrategyTest`, `CalendarRoutingTest`, `TodayScheduleTest`, `CalendarIndexOverrideTest`) are unrelated to override persistence and were already red on `main` before this pass — verified by stashing changes and re-running. No new regressions.

Pint clean: `vendor/bin/pint --dirty` was run; only formatting tweaks were required.

## What This Closes vs. The Previous Report's Nuances

Mapping back to the four nuances called out in [session-first-planning-completion-report-2026-04-30.md](docs/plans/session-first-planning-completion-report-2026-04-30.md):

### Nuance 1 — "Persistence is flatter, but still lives in the config JSON column"

**Closed.** Override rows now live in `exercise_plan_config_overrides`, a real relational table with FKs to `exercise_program_exercises` and `users`. The `config` JSON column on `exercise_programs` and `exercise_plans` no longer contains override data of any kind — `overrideValues`, `gridOverrides`, `historicalGridOverrides`, and `baselineGridOverrides` are all stripped at write time.

### Nuance 4 — "The final architectural end state is one step beyond this"

**Closed.** That step was: replace JSON-backed flat override persistence with dedicated normalized planning tables; let config objects hold defaults/strategies only. Both halves are done. `ExercisePlanConfig` now persists only `target`, `weeks`, `schedule`, and per-exercise/per-user defaults/strategies. Override rows are first-class relational records.

### Nuance 2 — "Runtime still uses `weekIndex/sessionIndex` as an ordering axis"

**Unchanged, deliberately.** Removing this is a separate refactor (compiler, preview, calendar block service all key on it). The new override columns are named `week_index` / `session_index` to make the intent explicit — they're ordered-session addressing, not week-shaped storage truth. A future grouping-policy abstraction can rename or replace these without re-shaping the table.

### Nuance 3 — "`preview.weeks` still exists as horizon/authoring metadata"

**Unchanged, deliberately.** This is legitimate authoring-horizon UI state — preview range, calendar/program authoring context, automatic strategy horizon. It is not override or planning truth.

## Important Nuances Of This Pass

### 1. The override table is the source of truth at rest

There is no longer a second copy of override data in the JSON column. The cast strips it on write. Reads always pull from the table. If you query `getRawOriginal('config')` directly you will see no override data — anything that needs it must go through `$model->config` (which loads via the cast) or `$model->planConfigOverrides` (the relation directly).

### 2. Save ordering matters for new programs

Anything that creates an `ExerciseProgram` with overrides in its config must ensure the `exercise_program_exercises` pivot rows exist before the override sync fires. The seeder was updated to reflect this; `ExerciseProgram::duplicate()` already had the right ordering (it creates pivots and only then re-assigns config). Any new code that constructs a program from a config blob should follow the same pattern: create the program shell, insert pivots, then assign config.

### 3. `saveQuietly()` still works

The trait overrides `save()`, not the model's saved event, so `saveQuietly()` (which wraps `save()` in `withoutEvents`) still triggers the override sync. The Livewire grid uses `saveQuietly()` on the athlete-scoped path; that path is covered by `PlanExerciseGridScopedRebuildTest`.

### 4. FK constraints are enforced

`program_exercise_id` cascades on pivot delete (so removing an exercise from a program drops its overrides automatically). `user_id` cascades on user delete (so removing an athlete drops their per-athlete overrides automatically). No more orphan override rows, ever.

### 5. The export format intentionally still contains `overrideValues`

The export-and-reimport seeding flow still round-trips override data via a `config.overrideValues` blob in the exported `.php` files. That keeps the seed format stable for production-pull-into-local. The runtime persistence is fully relational — only the export wire-format keeps the flat shape, since the import explicitly hydrates it through the cast on the way back in.

## Files Changed In This Pass

Created:
- `database/migrations/2026_04_30_120000_create_exercise_plan_config_overrides_table.php`
- `app/Models/Exercise/ExercisePlanConfigOverride.php`
- `app/Models/Concerns/HasPlanConfigOverrides.php`
- `app/Casts/ExercisePlanConfigCast.php`

Modified:
- `app/Data/Training/Config/ExercisePlanConfig.php`
- `app/Models/Exercise/ExerciseProgram.php`
- `app/Models/Exercise/ExercisePlan.php`
- `app/Console/Commands/ExportDatabaseCommand.php`
- `database/seeders/DatabaseImportSeeder.php`
- `tests/Unit/Training/Config/ExercisePlanConfigTest.php`
- `tests/Feature/Models/TrainingProgramTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridScopedRebuildTest.php`

## Bottom Line

Reading the prior report's "session-first runtime convergence: done / group-aware fanout: done / normalized persisted override rows: done / full database-normalized planned-training persistence: not yet done" status block:

- session-first runtime convergence: still done
- group-aware fanout: still done
- normalized persisted override rows: now actually relational, not a JSON-blob substitute
- full database-normalized planned-training persistence: **done for the override layer**; planned per-session/exercise/set data still lives in the existing `training_program_slot_*` materialization tables (which were already normalized) plus the runtime `defaults + strategy + override-table` resolution. There is no separate "planned program template sessions" table because the program template doesn't enumerate sessions — sessions only exist as concrete instantiated slots, which already have their own normalized tables.

The system now has a single canonical relational source of truth for every override layer (default, per-user, current/historical/baseline scope, session/cell target). `ExercisePlanConfig`'s persisted JSON is finally just defaults and strategies.
