 # Training Planning Refactor Handoff

Date: 2026-04-28

## Purpose

This document is a handoff for a new agent with no prior chat context.

It describes:

- what problem space this refactor is addressing
- what has already been changed
- which phases exist
- which steps are complete vs still open
- which files and tests matter most

## Problem Summary

The training planning system had become hard to reason about because the same exercise intent was being represented and resolved in too many ways:

- editable config / DTO data
- override data
- preview-time derived data
- compiled / materialized athlete-session data
- plan-grid rendering logic
- athlete-dashboard rendering logic

This led to repeated bugs around:

- mixed weeks where one session is historical and another is future
- week-level read/derive fallbacks hiding session-specific differences
- automatic strategy behavior differing between plan preview and athlete view
- string/int casting drift between preview, compile, and render paths
- historical freeze behavior mutating current override state

The refactor direction is:

- keep `week-wide apply` as an editing feature
- remove accidental `week-wide read/derive` behavior where session truth should win
- make automatic strategy behavior share a cleaner common seam
- move toward a system that is easier to reason about, safer to test, and eventually better for analytics/statistics

## High-Level Phase Plan

### Phase 1

Make mixed weeks reliable while preserving week-wide apply.

Goal:

- keep collapsed editing that applies to all sessions in a week
- make save, preview, colors, highlights, and locking resolve by session when sessions differ

### Phase 2

Unify automatic strategy behavior behind shared seams.

Goal:

- auto reps, auto weight, and auto heart rate should not be wired and derived independently in separate places
- preview orchestration and compilation should be able to align around shared automatic strategy logic

### Phase 3

Normalize value typing and storage for consistency and future analytics.

Goal:

- keep one clear canonical meaning for values
- reduce fragile string/int drift
- support future stats without parsing display strings

### Phase 4

Replace freeze-history-via-overrides with explicit historical snapshot semantics.

Goal:

- past sessions should be preserved as resolved historical truth
- future plan changes should affect future sessions only

### Phase 5

Separate shared program definition from athlete-specific adaptation layers.

Goal:

- make rebuild scope and data ownership obvious
- reduce accidental fan-out from athlete-specific edits

### Phase 6

Improve performance and rebuild flow.

Goal:

- reduce synchronous work
- keep queue/job boundaries clear
- preserve correctness while improving perceived speed

### Phase 7

Thin the rendering layers.

Goal:

- Blade / Livewire views should render prepared view data
- business logic should live in shared resolvers/domain services

### Phase 8

Consolidate canonical resolved read models.

Goal:

- remove duplicate read-time seams that still derive the same truth in multiple places
- make session-count, override-resolution, and prepared view data flow from one canonical source
- leave later automatic-strategy/value-contract work with cleaner inputs and fewer UI-specific mutations

## Current Status

Current phase: **Phase 8**

Phases 1 through 7 have landed enough implementation that the next highest-value work is consolidation rather than more broad surface-area expansion.

Phase 2 is still not fully complete conceptually, because preview and compilation still need a stronger shared resolved automatic-output contract, but the codebase has also advanced into Phase 7/8 style rendering and read-model cleanup work.

## Completed Work

## Phase 1 Completed Work

### 1. Mixed-session save and freeze behavior

Past sessions in a mixed week are now treated as session-specific locked history instead of being accidentally overwritten by future-session edits.

Important changes:

- `app/Livewire/Training/View/PlanExerciseGrid.php`
  - locked-session freezing was changed to work per session
  - locked historical sessions are frozen from their own session-specific values
  - future sessions in the same week remain editable

Protected by:

- `tests/Feature/Livewire/Training/PlanExerciseGridMixedSessionSaveTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridExpandedSessionDisplayTest.php`

### 2. Highlight behavior fixed for mixed weeks

Changed-state / highlight logic no longer incorrectly lights up an entire week when only one session changed.

Important changes:

- `app/Livewire/Training/View/PlanExerciseGrid.php`
- `app/Data/Exercise/Preview/ExercisePreviewBuilder.php`
- `app/Data/Exercise/Preview/PreviewGridRow.php`

Protected by:

- `tests/Feature/Livewire/Training/PlanExerciseGridHighlightTest.php`

### 3. Session-specific preview values and colors

Expanded weeks now carry session-specific values, colors, and override flags through the preview pipeline instead of collapsing everything to shared week-level cells.

Important changes:

- `app/Data/Exercise/Preview/GridState.php`
- `app/Data/Exercise/Preview/PreviewGridRow.php`
- `app/Data/Exercise/Preview/ExercisePreviewBuilder.php`
- `resources/views/components/training/exercise-grid.blade.php`

Protected by:

- `tests/Feature/Livewire/Training/PlanExerciseGridExpandedSessionDisplayTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridAutomaticHeartRateSessionTest.php`

### 4. Automatic heart rate derivation and color behavior fixed in mixed weeks

Changing `heartRateZone` for a future session in a mixed week now updates only that session’s derived `heartRate` value and color.

Important changes:

- `app/Data/Exercise/Strategies/HeartRate/NorwegianIntensityStrategy.php`
- `app/Data/Exercise/Preview/GridState.php`
- `app/Data/Exercise/Preview/ExercisePreviewBuilder.php`

Protected by:

- `tests/Feature/Livewire/Training/PlanExerciseGridAutomaticHeartRateSessionTest.php`
- `tests/Feature/Data/Exercise/Strategies/HeartRate/NorwegianIntensityStrategyTest.php`

### 5. Athlete dashboard automatic heart rate fixes

The athlete dashboard now:

- materializes automatic HR ranges as strings instead of truncating to ints
- uses HRZ-derived color for HR when automatic HR is range-based

Important changes:

- `app/Training/TrainingSessionCompiler.php`
- `app/Livewire/Athlete/ProgramDetails.php`

Protected by:

- `tests/Feature/Training/AutomaticHeartRateCompilationTest.php`
- `tests/Feature/Livewire/Athlete/ProgramDetailsTest.php`

### 6. Typed value handling improvements

Important changes:

- duration is compiled as canonical int seconds
- string-shaped values such as `12_12` and HR ranges are preserved where appropriate
- compiler type resolution is now driven more consistently by setting metadata

Important changes:

- `app/Training/TrainingSessionCompiler.php`
- `app/Livewire/Athlete/ProgramDetails.php`

Protected by:

- `tests/Feature/Training/CompiledSettingTypePreservationTest.php`

### 7. Rebuild scope and materialization reliability improvements

Important changes:

- slot materialization now uses transaction retry + `lockForUpdate()`
- athlete-specific plan-grid edits rebuild only that athlete’s future slots for that program
- rebuild work is now routed through job classes and a dispatcher, but still sync-dispatched for now

Important changes:

- `app/Training/TrainingSessionMaterializer.php`
- `app/Training/TrainingSessionRebuildDispatcher.php`
- `app/Jobs/RebuildFutureSlotsForExerciseProgramJob.php`
- `app/Jobs/RebuildFutureSlotsForAthleteExerciseProgramJob.php`
- `app/Jobs/RebuildFutureSlotsForAthleteJob.php`

Protected by:

- `tests/Feature/Livewire/Training/PlanExerciseGridScopedRebuildTest.php`
- `tests/Feature/Training/PastSlotFreezeTest.php`

### 8. Week-wide apply kept, but read/derive paths made session-safe

This is a core conceptual rule of the refactor:

- editing can still apply to all sessions in a week
- but once sessions differ, rendering and derivation should not pretend there is one shared value

Important changes:

- `app/Data/Exercise/Preview/OverrideManager.php`
  - `applyToAll` uses real week session counts
  - `copyWeek` copies session-specific values instead of flattening the week
  - stale out-of-range session overrides are removed
- `app/Data/Exercise/Preview/PreviewGrid.php`
  - carries `weekSessionCounts`
- `app/Livewire/Training/View/PlanExerciseGrid.php`
  - computes explicit per-week session counts
  - auto-expands weeks when sessions diverge
- `app/Livewire/Training/CalendarExerciseSettingsForm.php`
  - same divergence/session-count rules as the main plan grid
- `app/Livewire/Concerns/InteractsWithPreview.php`
  - generic preview/editor path now follows the same divergence rule

Protected by:

- `tests/Feature/Data/Exercise/Preview/OverrideManagerCellTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridMixedSessionSaveTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridExpandedSessionDisplayTest.php`
- `tests/Feature/Livewire/Training/CalendarExerciseSettingsFormTest.php`
- `tests/Feature/Livewire/Database/ExerciseFormTest.php`

## Phase 2 Completed Work So Far

### 1. Shared automatic derivation classes were introduced

These now centralize automatic calculation logic for the strategy family:

- `app/Training/Derivation/AutomaticRepsResolver.php`
- `app/Training/Derivation/AutomaticWeightResolver.php`
- `app/Training/Derivation/AutomaticWeightResolution.php`
- `app/Training/Derivation/AutomaticHeartRateResolver.php`

### 2. Existing automatic strategy classes now delegate to shared resolvers

Refactored classes:

- `app/Data/Exercise/Strategies/Reps/PairedRepStrategy.php`
- `app/Data/Exercise/Strategies/Weight/OneRepMaxFixedStrategy.php`
- `app/Data/Exercise/Strategies/HeartRate/NorwegianIntensityStrategy.php`

### 3. Shared automatic strategy factory introduced and wired into the orchestrator

There is now one shared construction path for the automatic strategy family.

Important changes:

- `app/Data/Exercise/Strategies/AutomaticStrategyFactory.php`
- `app/Data/Exercise/Preview/StrategyOrchestrator.php`

The orchestrator now builds automatic reps/weight/HR strategies through the factory instead of directly instantiating each one in separate branches.

### 4. Shared automatic seam has direct tests

Important tests:

- `tests/Unit/Training/Derivation/AutomaticStrategyResolverTest.php`
- `tests/Feature/Data/Exercise/Preview/StrategyOrchestratorTest.php`

The orchestrator test now includes a seam-specific assertion proving an injected automatic factory is actually respected.

## Remaining Work

## Phase 2 Remaining Steps

There are roughly **3 substantial steps left** in Phase 2.

### Step 2.1

Define a shared resolved output contract for automatic strategies.

Current issue:

- shared logic exists, but most consumption still happens through `GridState` side effects and ad hoc lookup patterns

Goal:

- define a cleaner output shape for automatic strategy results
- likely include resolved values, color semantics where relevant, and summary/provenance metadata where relevant

Suggested first target:

- start with heart rate / heart rate zone because it has both value and color behavior

### Step 2.2

Make preview and compilation consume that resolved output more directly.

Current issue:

- preview and compile are still aligned largely through the strategy/orchestrator pipeline, not through a shared resolved result contract

Goal:

- reduce ad hoc `GridState` lookups
- move toward preview builder and compiler consuming the same resolved meaning more directly

### Step 2.3

Add equivalence coverage across preview vs compiled output for automatic strategies.

Goal:

- prove that the same automatic input resolves the same way in the plan-preview path and the compiled/materialized athlete-session path

Priority areas:

- automatic heart rate
- automatic weight
- automatic reps

## Later Phase Intent

## Phase 3

Introduce a canonical value contract that is consistent and analytics-friendly.

Important direction:

- duration should stay canonical int seconds
- values that have analytical structure should not rely only on display strings
- eventual storage should support future statistics without reverse-parsing UI strings

Examples:

- HR ranges should eventually be analytically representable as bounds, not only `"176-184"`
- compound reps like `12_12` should preserve full structure, not collapse to `12`

## Phase 4

Historical sessions should become explicit resolved snapshots rather than being preserved through synthetic override mutation.

Important direction:

- past sessions are historical truth
- future sessions remain derived from current config + overlays

## Phase 5

Separate shared program definition from athlete-specific adaptation more clearly.

Important direction:

- athlete-specific changes should live in a clearly distinct layer
- rebuild scope should map directly to ownership

## Phase 6

Performance and rebuild work.

Important direction:

- preserve correctness first
- reduce synchronous rebuild cost
- keep existing dispatcher/job boundary so async queueing can be introduced later without changing callers

## Phase 7

Thin rendering layers.

Important direction:

- views should consume prepared resolved view data
- rendering should not be the place where prescription logic is re-derived

## Phase 8

Consolidate canonical read models and remove duplicate read-time seams.

Important direction:

- session counts should be computed once and carried through the preview/view model path
- resolved override data should be read through one aggregate seam instead of piecemeal helper calls
- Livewire components should orchestrate prepared data, not own formatting/color/label policy that can live in dedicated builders

## Files Most Important To Understand Next

### Planning / preview path

- `app/Livewire/Training/View/PlanExerciseGrid.php`
- `app/Livewire/Training/CalendarExerciseSettingsForm.php`
- `app/Livewire/Concerns/InteractsWithPreview.php`
- `app/Data/Exercise/Preview/ExercisePreviewBuilder.php`
- `app/Data/Exercise/Preview/GridState.php`
- `app/Data/Exercise/Preview/PreviewGrid.php`
- `app/Data/Exercise/Preview/PreviewGridRow.php`
- `app/Data/Exercise/Preview/OverrideManager.php`
- `app/Data/Exercise/Preview/StrategyOrchestrator.php`

### Automatic strategy path

- `app/Data/Exercise/Strategies/AutomaticStrategyFactory.php`
- `app/Data/Exercise/Strategies/Reps/PairedRepStrategy.php`
- `app/Data/Exercise/Strategies/Weight/OneRepMaxFixedStrategy.php`
- `app/Data/Exercise/Strategies/HeartRate/NorwegianIntensityStrategy.php`
- `app/Training/Derivation/AutomaticRepsResolver.php`
- `app/Training/Derivation/AutomaticWeightResolver.php`
- `app/Training/Derivation/AutomaticWeightResolution.php`
- `app/Training/Derivation/AutomaticHeartRateResolver.php`

### Compile / athlete-session path

- `app/Training/TrainingSessionCompiler.php`
- `app/Training/TrainingSessionMaterializer.php`
- `app/Training/TrainingSessionRebuildDispatcher.php`
- `app/Livewire/Athlete/ProgramDetails.php`

## Tests To Keep Green

These are the highest-signal safety tests for this refactor:

- `tests/Feature/Livewire/Training/PlanExerciseGridMixedSessionSaveTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridExpandedSessionDisplayTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridHighlightTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridAutomaticHeartRateSessionTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridScopedRebuildTest.php`
- `tests/Feature/Livewire/Training/CalendarExerciseSettingsFormTest.php`
- `tests/Feature/Livewire/Database/ExerciseFormTest.php`
- `tests/Feature/Data/Exercise/Preview/OverrideManagerCellTest.php`
- `tests/Feature/Data/Exercise/Preview/StrategyOrchestratorTest.php`
- `tests/Feature/Data/Exercise/Strategies/HeartRate/NorwegianIntensityStrategyTest.php`
- `tests/Unit/Training/Derivation/AutomaticStrategyResolverTest.php`
- `tests/Feature/Training/AutomaticHeartRateCompilationTest.php`
- `tests/Feature/Training/CompiledSettingTypePreservationTest.php`
- `tests/Feature/Training/PastSlotFreezeTest.php`
- `tests/Feature/Training/TrainingSessionCompilerTest.php`
- `tests/Feature/Livewire/Athlete/ProgramDetailsTest.php`

## Recommended Next Move

For the next session, the best immediate Phase 2 task is:

1. define a shared resolved output contract for automatic strategies
2. apply it first to automatic heart rate
3. prove equivalence between preview and compiled output
4. then repeat the pattern for automatic weight and automatic reps

This keeps the refactor incremental while moving toward a cleaner, more coherent system.
