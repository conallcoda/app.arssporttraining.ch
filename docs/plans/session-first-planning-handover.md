# Session-First Planning Refactor Handover

## User Goal

The user wants to continue a larger architectural refactor with these outcomes:

1. Remove week overrides entirely from persisted exercise/program override data.
2. Move planning toward the same normalized session/exercise/set shape as the materialized athlete/performance model.
3. Keep all override layers:
   - exercise
   - program
   - scheduled program
   - group
   - athlete
4. Keep grouping as a derived abstraction, not a storage primitive.
5. Support future grouping modes:
   - group by week
   - group by fixed session-count buckets
6. Coach setting idea:
   - `Session Grouping: Week | Groups`
   - if `Groups`, also specify group size
7. Keep session storage explicit regardless of grouping mode.
8. Let grouping affect:
   - display
   - strategy behavior like deload / auto sets / progression
   - fanout of edits across sessions in the same group
9. Bring planning JSON config arrays much closer to the normalized materialized/performance model.

## What Has Already Been Implemented

### 1. Week override removal and session-first override model

Override storage now normalizes to:
- `sessions`
- `cells`

Legacy `weeks` override bags are normalized away.

Key files changed:
- `app/Support/Training/GridOverrideNormalizer.php`
- `app/Data/Exercise/ExerciseConfig.php`
- `app/Data/Training/Config/ExerciseOverrides.php`
- `app/Data/Exercise/Preview/GridOverrides.php`
- `app/Data/Training/Config/EffectiveExerciseConfig.php`
- `app/Data/Exercise/Preview/GridState.php`
- `app/Data/Exercise/Preview/StrategyOrchestrator.php`
- `app/Data/Exercise/Preview/OverrideManager.php`
- `app/Data/Exercise/Preview/ExercisePreviewBuilder.php`
- `app/Training/TrainingSessionCompiler.php`

Behavioral changes:
- no persisted `weeks` override bag
- session-level override APIs replace week-level ones
- no legacy session-0 inheritance in runtime resolution
- compiler resolves normalized session-first overrides
- import seeding normalizes legacy data before materialization

### 2. Grid/editor UI simplification

Implemented:
- shared exercise grid always renders session rows
- removed week copy UI and collapsed week edit behavior
- week-column editable cells now route to session override updates

Key files changed:
- `resources/views/components/training/exercise-grid.blade.php`
- `packages/cms/resources/js/alpine/editable-cell.js`
- `app/Livewire/Concerns/InteractsWithPreview.php`
- `app/Livewire/Training/CalendarExerciseSettingsForm.php`
- `app/Livewire/Training/View/PlanExerciseGrid.php`

Important note:
- Alpine source changed, but frontend assets were not rebuilt in this session.

### 3. Seeder/import safety for production pull + local seed

Implemented:
- imported legacy configs are normalized into session/cell form before materialization

Key file:
- `database/seeders/DatabaseImportSeeder.php`

### 4. Additional regression fix discovered during wider pass

Implemented:
- recorded slots with `completed_at` are no longer deleted via quick delete in calendar views

Files changed:
- `app/Livewire/Training/CalendarScheduleView.php`
- `app/Livewire/Training/CalendarProgramsView.php`

## Dead Schedule-Week Subsystem Removal

The user suspected the old schedule-week planning subsystem was dead and asked to remove it if unused.

### Findings

Investigated:
- `app/Data/Training/Config/ExercisePlanConfig.php`
- `app/Data/Training/Config/Schedule/DefaultScheduleConfig.php`
- `app/Data/Training/Config/Schedule/ScheduleWeek.php`
- `app/Livewire/Training/View/ScheduleHandler.php`

Conclusions:
- `ScheduleHandler` had no callers and referenced config methods that no longer exist. It was effectively dead/stale.
- Old plan schedule-week DTOs were only still referenced through an orphaned plan/program listing/import chain, not any active route that could be traced.
- `TrainingProgram::importFromPlan()` had no callers outside tests.

### Removed

Deleted:
- `app/Livewire/Training/View/ScheduleHandler.php`
- `app/Livewire/Training/View/ProgramList.php`
- `app/Livewire/Training/View/Programs.php`
- `resources/views/livewire/training/view/programs.blade.php`
- `app/Data/Training/Config/Schedule/DefaultScheduleConfig.php`
- `app/Data/Training/Config/Schedule/ScheduleWeek.php`
- `app/Data/Training/Config/Schedule/ScheduleWeekSlot.php`

Also removed dependent logic:
- `ExercisePlan::programIds()`
- `ExercisePlan::programs()`
- `TrainingProgram::importFromPlan()`
- schedule-specific helpers from `ExercisePlanConfig`

### Current `ExercisePlanConfig` status

`ExercisePlanConfig` still has a `schedule` property, but now as:
- `array|Optional`

It is treated as inert legacy data rather than an active typed schedule subsystem.

## Current Route / Usage Findings

Active admin route:
- `/admin/programs`
- `/admin/programs/{exerciseProgram}`

These are provided via:
- `app/Cms/Modules/ExerciseProgramModule.php`

They use:
- `ExerciseProgramView`
- `ProgramEditor`

Could not trace any active route into:
- removed `ScheduleHandler`
- removed `Training\View\Programs`
- removed `Training\View\ProgramList`

## Remaining Week Concepts Still Live

The removal above did **not** remove all week concepts.

Still live:

### 1. Grid UI still groups by week
- `resources/views/components/training/exercise-grid.blade.php`
- `app/Data/Exercise/Preview/PreviewGrid.php`
- `app/Data/Exercise/Preview/PreviewGridWeek.php`
- `app/Data/Exercise/Preview/ExercisePreviewBuilder.php`

### 2. Program editor still has `Weeks` input
- `app/Livewire/Training/View/ProgramEditor.php`
- `resources/views/livewire/training/view/program-editor.blade.php`
- `resources/views/livewire/training/exercise-program-view.blade.php`

### 3. Exercise preview config still uses `preview.weeks`
- `app/Data/Exercise/Settings/PreviewSetting.php`
- `app/Livewire/Concerns/InteractsWithPreview.php`
- `app/Livewire/Database/ExerciseForm.php`

### 4. Sets/deload logic is explicitly week-based
- `app/Data/Exercise/Settings/SetsSetting.php`
- `app/Data/Exercise/Strategies/Sets/DeloadSetsStrategy.php`
- `app/Data/Exercise/Preview/GridState.php`

### 5. Automatic progression/derivation still runs over weeks
- `app/Data/Exercise/Preview/StrategyOrchestrator.php`
- `app/Training/Derivation/AutomaticRepsResolver.php`
- `app/Training/Derivation/AutomaticWeightResolver.php`
- `app/Training/Derivation/AutomaticHeartRateResolver.php`

### 6. Compiler/materializer still resolves sessions by `weekIndex/sessionIndex`
- `app/Training/TrainingSessionCompiler.php`

### 7. Calendar screens still use real calendar weeks
These are legitimate calendar/schedule concepts and should not be conflated with old week override behavior.

## Important Architectural Discussion Already Settled With User

The user does **not** just want week overrides gone.

They want:
- planning JSON config arrays and normalized materialized/performance model brought much closer together
- ideally one or almost one storage concept for both planning and recording
- grouping abstracted away from storage
- override layers preserved

Important conclusion:

If the system keeps current JSON config arrays as the primary truth, it does **not** solve the planning-vs-materialized mismatch.

To really solve it:
- canonical truth needs to move toward normalized session/exercise/set planned data
- config arrays should become:
  - defaults
  - strategy settings
  - patches/layer input

## Recommended Next Work

The next session should not jump straight into code deletion of all week concepts.

It should first design the canonical normalized model.

### 1. Design the canonical shared model

Need a concrete proposal for:
- canonical planned session DTO
- canonical recorded/materialized session DTO
- planned vs actual structure
- identity fields for unscheduled vs scheduled sessions
- session-level vs set-level values
- how provenance / layer source is tracked

Likely target:
- session
  - identity / slot/date/sequence
  - exercises
    - planned
    - actual
    - status

### 2. Design layer-aware patch model

Need exact model for:
- exercise defaults/strategies
- program patch
- scheduled program patch
- group patch
- athlete patch

Important distinction already discussed with user:
- actuals should **not** just be another override layer
- actuals are a parallel axis attached to resolved planned data

Need explicit resolution order and precedence rules.

### 3. Design grouping abstraction

Need a clean interface for grouping policy:
- `week`
- `groups` with group size

Need to define:
- how `group_key` is derived
- how deload / sets / auto progression consume groups
- how `apply per group` fanout works without group-shaped storage
- how coach settings persist this preference

Likely concepts:
- `SessionGroupingPolicy`
- `GroupResolver`
- strategies operate on ordered groups rather than weeks

### 4. Inventory still-live week concepts

Need to classify remaining week concepts into:
- delete soon
- replace with grouping abstraction
- keep because they are true calendar/schedule structure

### 5. Be careful with `ExercisePlanConfig`

It now accepts legacy `schedule` payload as inert data.

Need to inspect for fallout outside tested slices:
- admin CRUD for `ExercisePlan`
- import/export assumptions
- serialization expectations
- data docs

### 6. Asset build still pending

`packages/cms/resources/js/alpine/editable-cell.js` was changed.

No frontend asset build was run in this session.

## Tests Run and Current Green State

Broader passes already completed and green.

Earlier pass:
- `php artisan test tests/Feature/Livewire/Training tests/Feature/Training tests/Feature/Data/Exercise/Preview tests/Unit/Data/Exercise/Preview tests/Unit/Training/Config`
- result at that point: `107 passed (301 assertions)`

After dead schedule-week chain removal:
- `php artisan test tests/Feature/Models/TrainingProgramTest.php tests/Unit/Training/Config/ExercisePlanConfigTest.php tests/Feature/Livewire/Training tests/Feature/Training`
- result: `62 passed (216 assertions)`

## Key Files Changed In This Overall Refactor

Core refactor:
- `app/Support/Training/GridOverrideNormalizer.php`
- `app/Data/Exercise/ExerciseConfig.php`
- `app/Data/Exercise/Preview/ExercisePreviewBuilder.php`
- `app/Data/Exercise/Preview/GridOverrides.php`
- `app/Data/Exercise/Preview/GridState.php`
- `app/Data/Exercise/Preview/OverrideManager.php`
- `app/Data/Exercise/Preview/StrategyOrchestrator.php`
- `app/Data/Training/Config/EffectiveExerciseConfig.php`
- `app/Data/Training/Config/ExerciseOverrides.php`
- `app/Livewire/Concerns/InteractsWithPreview.php`
- `app/Livewire/Training/CalendarExerciseSettingsForm.php`
- `app/Livewire/Training/View/PlanExerciseGrid.php`
- `app/Training/TrainingSessionCompiler.php`
- `database/seeders/DatabaseImportSeeder.php`
- `packages/cms/resources/js/alpine/editable-cell.js`
- `resources/views/components/training/exercise-grid.blade.php`

Calendar deletion guard:
- `app/Livewire/Training/CalendarScheduleView.php`
- `app/Livewire/Training/CalendarProgramsView.php`

Dead schedule-week chain removal:
- `app/Data/Training/Config/ExercisePlanConfig.php`
- `app/Models/Exercise/ExercisePlan.php`
- `app/Models/Training/TrainingProgram.php`

Deleted:
- `app/Livewire/Training/View/ScheduleHandler.php`
- `app/Livewire/Training/View/ProgramList.php`
- `app/Livewire/Training/View/Programs.php`
- `app/Data/Training/Config/Schedule/DefaultScheduleConfig.php`
- `app/Data/Training/Config/Schedule/ScheduleWeek.php`
- `app/Data/Training/Config/Schedule/ScheduleWeekSlot.php`
- `resources/views/livewire/training/view/programs.blade.php`

## Open Questions For Next Agent

1. What is the exact canonical normalized DTO for both planning and recording?
2. Should planned and actual live side-by-side in one persisted structure, or share DTO shape but use different persistence tables?
3. How should override provenance be tracked in the resolved canonical model?
4. What is the identity for unscheduled sessions?
   - sequence index?
   - synthetic key?
5. Should group-aware strategies be applied at resolution time only, or partially persisted as explicit session patches?
6. How should `preview.weeks` evolve?
   - remain as authoring horizon only?
   - become derived session count?
7. How should current odd/even week deload be represented in group terms?
   - every Nth group?
   - alternating group pattern?
8. Do we ultimately want to remove `weekIndex` from compiler/session identity too, or keep it as a derived runtime ordering axis?

## Notes For Continuity

- The user is supportive of large cleanup and wants autonomous implementation.
- The user prefers storage and DTOs as close as possible to the normalized/materialized base format.
- The user is explicitly okay with deeper churn if it leads to a cleaner long-term model.
- The user strongly cares that override layers remain supported.
- The next session should likely spend time designing the canonical/layer/grouping model before making another broad code pass.
