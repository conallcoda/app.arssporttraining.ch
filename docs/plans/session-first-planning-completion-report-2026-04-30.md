# Session-First Planning Completion Report

Date: 2026-04-30

## Goal

Finish the remaining session-first planning work autonomously, with these specific outcomes:

1. Fanout/edit behavior should follow runtime grouping policy instead of week-only assumptions.
2. Planning/default resolution should keep converging on the shared resolved planned-session model instead of bespoke preview/editor logic.
3. Persisted planning data should move closer to explicit session/cell rows, with JSON used more for defaults and strategy inputs than as the full nested planning truth.

## What Was Completed

### 1. Group-aware fanout now works in the editor flows

`applyToAll` no longer behaves like a dead flag.

Implemented:
- `PlanExerciseGrid` now fans cell edits across all sessions in the same runtime group.
- `CalendarExerciseSettingsForm` now does the same for scheduled preview editing.
- `InteractsWithPreview` now does the same for generic preview editing.

Behavior:
- `groupingMode = week` fans across sessions in the same week bucket.
- `groupingMode = groups` fans across fixed-size session groups, including groups that span week boundaries.
- locked historical sessions are skipped.
- target defaults are resolved per target session before deciding whether an override should be stored or removed.

This means fanout now follows the same derived grouping policy that already drives:
- display grouping
- deload behavior
- automatic progression

Key files:
- `app/Livewire/Training/View/PlanExerciseGrid.php`
- `app/Livewire/Training/CalendarExerciseSettingsForm.php`
- `app/Livewire/Concerns/InteractsWithPreview.php`

### 2. Editor default resolution now goes through the shared preview/session path

Previously, several editor paths still recomputed “default” values with local strategy logic or ad hoc orchestration.

Changed:
- cell/session default resolution for editing now reads from the shared defaults grid built by `ExercisePreviewBuilder`
- scheduled sets default resolution no longer needs separate deload logic in the form component
- this keeps preview, edit-default comparison, grouped strategy behavior, and override removal rules aligned

Result:
- fewer divergent code paths
- fewer places still directly rebuilding week-based strategy behavior
- more of planning UI behavior now flows through the same resolved runtime shape

### 3. Persisted override storage was flattened into normalized `overrideValues`

`ExercisePlanConfig` now supports a flattened persisted override representation:

```php
[
    'programExerciseId' => 123,
    'userId' => null,
    'scope' => 'current|historical|baseline',
    'target' => 'session|cell',
    'week' => 0,
    'session' => 1,
    'set' => 0, // cell only
    'settingKey' => 'reps',
    'value' => 12,
]
```

What changed:
- runtime API stays the same: callers still use `defaultExerciseOverrides()`, `userExerciseOverrides()`, `resolveExercise()`, etc.
- persisted config now stores session/cell/historical/baseline override rows in a normalized flat list under `overrideValues`
- nested `gridOverrides`, `historicalGridOverrides`, and `baselineGridOverrides` are stripped from persisted `exercises` / `userExercises` payloads
- on read, `ExercisePlanConfig` hydrates those flat rows back into runtime `ExerciseOverrides` objects
- legacy raw `weeks` payloads are normalized before flattening, so older writes still survive the new persistence shape

This is not the final “real tables instead of JSON” end state, but it is a meaningful convergence step:
- persisted planning truth is much closer to explicit session/cell rows
- nested override bags are no longer the primary persisted shape
- defaults/strategy config remains in the config object, while per-session planning data is flatter and more canonical

Key files:
- `app/Data/Training/Config/ExercisePlanConfig.php`
- `app/Models/Exercise/ExerciseProgram.php`
- `app/Models/Exercise/ExercisePlan.php`

## Verification

Targeted verification:
- `php artisan test tests/Unit/Training/Config/ExercisePlanConfigTest.php tests/Feature/Models/TrainingProgramTest.php tests/Feature/Livewire/Training/PlanExerciseGridMixedSessionSaveTest.php tests/Feature/Livewire/Training/PlanExerciseGridHighlightTest.php`
- `php artisan test tests/Feature/Training/TrainingSessionCompilerTest.php tests/Feature/Livewire/Training/CalendarExerciseSettingsFormTest.php`

Broader verification:
- `php artisan test tests/Feature/Training tests/Feature/Models/TrainingProgramTest.php tests/Unit/Training/Config/ExercisePlanConfigTest.php`
- `php artisan test tests/Feature/Livewire/Training tests/Feature/Livewire/Database/ExerciseFormTest.php tests/Unit/Data/Exercise/Preview tests/Feature/Data/Exercise/Preview`

Relevant new/updated coverage:
- `tests/Feature/Livewire/Training/PlanExerciseGridMixedSessionSaveTest.php`
- `tests/Feature/Models/TrainingProgramTest.php`
- `tests/Unit/Training/Config/ExercisePlanConfigTest.php`

## Files Changed In This Final Pass

- `app/Data/Training/Config/ExercisePlanConfig.php`
- `app/Livewire/Concerns/InteractsWithPreview.php`
- `app/Livewire/Training/CalendarExerciseSettingsForm.php`
- `app/Livewire/Training/View/PlanExerciseGrid.php`
- `app/Models/Exercise/ExercisePlan.php`
- `app/Models/Exercise/ExerciseProgram.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridMixedSessionSaveTest.php`
- `tests/Feature/Models/TrainingProgramTest.php`
- `tests/Unit/Training/Config/ExercisePlanConfigTest.php`

## Important Nuances

### 1. Persistence is flatter, but still lives in the config JSON column

This pass intentionally stopped at a safer intermediate state:
- override rows are now normalized within JSON
- settings/defaults/strategy inputs still live in the config object

That means the system is significantly closer to the desired model, but not yet at:
- dedicated relational planning tables
- full parity with the compiled/materialized storage model

### 2. Runtime still uses `weekIndex/sessionIndex` as an ordering axis

That is still present in:
- compiler/runtime ordering
- preview grouping internals
- persisted override row coordinates

At this point those fields are serving as ordered-session addressing, not week-shaped storage truth. That distinction is important.

### 3. `preview.weeks` still exists as horizon/authoring metadata

This pass did not remove planning horizon concepts.

Current role:
- preview/editor horizon
- automatic strategy horizon
- calendar/program authoring context

It is less central than before, but it still exists.

### 4. The final architectural end state is still one step beyond this

The remaining “true final” move, if pursued later, is:
- replace JSON-backed flattened override persistence with dedicated normalized planning tables
- let config objects hold defaults/strategies only
- let planned session/exercise/set records be the primary persisted planning model

This report should be read as:
- session-first runtime convergence: done
- group-aware fanout: done
- normalized persisted override rows: done
- full database-normalized planned-training persistence: not yet done

## Bottom Line

The remaining implementation steps from the previous handover were completed in the practical sense needed for this codebase:

1. fanout now respects grouping
2. editor/runtime default resolution now leans on the shared planned-session path
3. persisted override truth is now flattened into explicit session/cell rows instead of nested override bags

The system is now materially more coherent:
- grouping is derived runtime behavior
- session-first planning behavior is shared across preview, editing, and compilation
- persisted planning override data is flatter and much closer to the actual domain shape
