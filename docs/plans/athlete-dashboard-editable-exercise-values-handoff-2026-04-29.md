# Athlete Dashboard Editable Exercise Values Handoff

Date: 2026-04-29

## Purpose

This document is a handoff for a new agent with no prior chat context.

It explains exactly what feature needs to be implemented for the athlete dashboard, how the current system works, which parts of the codebase are relevant, and what implementation shape is recommended.

The goal is to let an athlete edit the values for each exercise set before marking the exercise done, and also continue editing those values after the exercise has been marked done.

## Feature Summary

On the athlete program details page, athletes can currently:

- open a program for a specific day
- view each exercise
- view the planned set grid
- mark an exercise done
- mark an exercise skipped
- open video and gallery media when available

We now need to add athlete-editable exercise values.

Specifically:

- athletes should be able to edit set values before clicking `Mark Done`
- athletes should still be able to edit after clicking `Mark Done`
- post-completion edits should autosave
- edited values should be visually highlighted in the grid
- this must be controlled by a new feature flag in `config/athlete.php` named `allow_athlete_edits`
- the athlete-facing edit controls must reuse the same validation, labels, units, and field behavior defined by the setting classes as much as possible
- setting-specific logic should stay centralized in the setting classes as the single source of truth

## Exact Product Requirements

### Feature Flag

Add a new config entry in `config/athlete.php`:

- `allow_athlete_edits`

Recommended default:

- `false`

This flag controls whether the athlete edit pencil is shown and whether the edit flow is available.

### Toolbar Layout

On each exercise card in the athlete program details view:

- video and gallery buttons remain left aligned
- the edit pencil is right aligned on the same row
- if there is no video and no gallery, the edit pencil still shows
- this row should behave like a small toolbar

This means the toolbar should render whenever at least one of these is true:

- video exists
- gallery exists
- athlete editing is enabled

### Edit Modal

The pencil opens a modal.

The modal should:

- show tabs for each set
- use the exercise’s configured set label, not hardcoded `Set`
- show a form field for each editable setting in that set
- prefill fields from the current resolved values
- validate using the same rules the setting system already defines
- reuse unit suffixes, masks, labels, and other field behavior from the setting system

Examples:

- if the set label is `Interval`, the tabs should read `Interval 1`, `Interval 2`, etc.
- if weight uses `kg`, the field should show `kg`
- if reps uses a custom field behavior, athlete editing should reuse that logic rather than duplicate it

### Save Behavior

When the athlete saves the modal:

- if no values changed, nothing should be marked modified
- if values changed, the changed values should be persisted and flagged as modified
- the exercise grid should visually highlight changed values before `Mark Done`

### Completion Behavior

When the athlete clicks `Mark Done`:

- the exercise should be marked completed as it is today
- edited values must be preserved
- modification flags must not be cleared

After the athlete has clicked `Mark Done`:

- they can still reopen the modal
- they can still edit values
- changes should automatically save

## Relevant Existing Flow

### Athlete Page

The athlete program details page is:

- `app/Livewire/Athlete/ProgramDetails.php`
- `resources/views/livewire/athlete/program-details.blade.php`

This page:

- loads the athlete’s `TrainingProgramSlot`
- loads materialized `TrainingProgramSlotExercise` rows
- renders one exercise card per exercise
- shows the grid of materialized set values
- supports `markExerciseCompleted()` and `markExerciseSkipped()`

### Athlete View Data

The athlete exercise card view data is built through:

- `app/Support/Athlete/ProgramDetailsExerciseViewBuilder.php`
- `app/Data/Athlete/ProgramDetailsExerciseData.php`
- `app/Data/Athlete/ProgramDetailsSessionRowData.php`

Important detail:

- the current view builder renders planned values only
- it does not yet prefer actual athlete-entered values
- it does not yet highlight modified cells

### Materialized Training Session Data

The compiled session tables already support the data model needed for this feature.

Important models:

- `app/Models/Training/TrainingProgramSlot.php`
- `app/Models/Training/TrainingProgramSlotExercise.php`
- `app/Models/Training/TrainingProgramSlotSet.php`
- `app/Models/Training/TrainingProgramSlotSetValue.php`

Important existing columns in `training_program_slot_set_values`:

- `planned_value_type`
- `planned_int_value`
- `planned_decimal_value`
- `planned_string_value`
- `planned_json_value`
- `actual_value_type`
- `actual_int_value`
- `actual_decimal_value`
- `actual_string_value`
- `actual_json_value`
- `unit`
- `is_modified`

Important existing modification/status fields higher up the tree:

- `training_program_slots.has_any_modification`
- `training_program_slot_exercises.has_any_modification`
- `training_program_slot_sets.has_any_modification`
- `training_program_slot_exercises.modified_set_count`

Important existing set status enum:

- `app/Models/Training/TrainingProgramSlotSetStatusEnum.php`

This already includes:

- `Pending`
- `Completed`
- `CompletedWithModification`
- `Skipped`

This strongly suggests the intended architecture is to store athlete-edited values as `actual_*` values on the materialized session rows, not as a second override system.

## Recommended Architecture

## Core Principle

Implement this as athlete-entered actual values on top of the materialized session tables.

Do not build this as:

- exercise-config overrides
- program overrides
- athlete program overrides
- an ad hoc JSON blob on the Livewire component
- a parallel table just for athlete edits

Instead:

- planned values remain the coach-defined compiled prescription
- actual values become the athlete’s edited or performed values
- `is_modified` indicates divergence from plan
- the slot/set/exercise modification flags summarize that state upward

This matches the schema that already exists and will be much easier to reason about later.

## Single Source of Truth for Setting Logic

The setting system currently centralizes several important pieces of logic:

- labels
- short labels
- unit labels
- input metadata
- form fields for coach config

Relevant files:

- `app/Data/Exercise/Settings/AbstractSetting.php`
- `app/Data/Exercise/ExerciseSetting.php`
- `app/Data/Exercise/ExerciseConfig.php`
- the concrete setting classes under `app/Data/Exercise/Settings`

This feature should extend that system so athlete editing also comes from the setting layer.

Recommended direction:

- add athlete-entry-specific methods to `AbstractSetting`
- let each concrete setting define how athlete input should render, validate, normalize, and display

Do not try to force the current coach config `fields()` API to do everything.

Reason:

- the current `fields()` methods define coach-facing exercise configuration
- athlete editing needs set-value entry fields, not config fields like `mode`, `applyPer`, or progression settings

So the right shape is:

- preserve current coach config methods
- add new athlete entry methods beside them

## Proposed Setting-Class Additions

Add a small athlete-edit contract to `AbstractSetting` and implement it per setting.

Recommended capabilities:

- build the athlete form field for one set value
- provide validation rules for athlete-entered values
- normalize submitted input into canonical value form
- resolve display formatting for actual/planned values
- expose unit suffixes and label behavior
- resolve storage type for `actual_value_type`

Potential method responsibilities:

- `athleteField(...)`
- `athleteRules(...)`
- `normalizeAthleteValue(...)`
- `formatAthleteValue(...)`
- `storeActualValue(...)` or equivalent helper/service integration

The exact method names can differ, but the idea should hold:

- setting-specific behavior lives in the setting class
- the modal and save service consume that behavior

## UI Implementation Plan

### 1. Add the Feature Flag

File:

- `config/athlete.php`

Add:

- `allow_athlete_edits` with a default of `false`

### 2. Add an Athlete Edit Modal Component

Create a dedicated Livewire component for athlete editing.

Recommended location:

- `app/Livewire/Athlete/ExerciseValueEditor.php`

Recommended view:

- `resources/views/livewire/athlete/exercise-value-editor.blade.php`

Recommended responsibilities:

- open for a specific `TrainingProgramSlotExercise`
- load its sets and set values
- build tabs per set
- build form fields per setting using the setting classes
- prefill from actual value when present, otherwise planned value
- validate submitted values
- save into `actual_*`
- update modification/status summary state
- dispatch an event back to `ProgramDetails` so the card/grid refreshes

The modal can be mounted globally in the athlete layout, similar to the existing gallery modal.

Relevant existing modal/layout files:

- `resources/views/components/layouts/athlete.blade.php`
- `resources/views/components/athlete/exercise-gallery-modal.blade.php`

### 3. Add Pencil Button to Athlete Exercise Toolbar

Update:

- `resources/views/livewire/athlete/program-details.blade.php`

Current state:

- the media buttons only render when there is video or gallery
- there is no right-aligned edit control

Required update:

- render a toolbar row when athlete edits are enabled or media exists
- keep video/gallery on the left
- place the pencil button on the right
- the pencil must still render when there is no video/gallery

### 4. Wire ProgramDetails to Open the Modal

Update:

- `app/Livewire/Athlete/ProgramDetails.php`

Add logic to:

- expose whether editing is enabled
- open the modal for a selected exercise
- refresh computed state after edits save

Likely behavior:

- dispatch an event such as `open-athlete-exercise-value-editor`
- listen for a saved event and unset computed caches

### 5. Render Actual Values and Modified Highlights

Update:

- `app/Support/Athlete/ProgramDetailsExerciseViewBuilder.php`
- `app/Data/Athlete/ProgramDetailsExerciseData.php`
- `app/Data/Athlete/ProgramDetailsSessionRowData.php`

Required behavior:

- if an actual value exists, display it instead of the planned value
- if a value is modified, add a distinct cell background class
- if a row mixes modified and unmodified cells, each cell should reflect its own state

Important:

- the current builder uses `extractPlannedValue(...)`
- this should evolve into planned-vs-actual resolution
- the builder should stay a read-model builder, not become a write layer

## Domain/Service Implementation Plan

### 6. Add a Dedicated Athlete Edit Persistence Service

Create a domain service for the modal save path.

Recommended location:

- `app/Training/AthleteExerciseValueService.php`

Responsibilities:

- accept a slot exercise id and submitted set-value payload
- load the full materialized exercise with sets and values
- validate ownership and permissions
- normalize submitted values via the relevant setting classes
- write correct `actual_*` columns
- set `is_modified`
- clear actual values if the input matches the planned value exactly
- update set/exercise/slot modification summaries and statuses

This service should be the only place that mutates actual athlete value data for this feature.

### 7. Add Planned-vs-Actual Comparison Logic

The service should compare submitted normalized values against planned normalized values.

Expected rules:

- if actual equals planned, treat as not modified
- if actual differs from planned, set `is_modified = true`
- if the athlete reverts a field back to the planned value, remove the modification and clear the stored actual value if possible

Recommended result:

- preserve clean semantics where actual values only exist when needed or when helpful
- avoid permanently marking something modified after the athlete reverts to plan

### 8. Roll Up Modification State

When saving one or more values:

- recompute `training_program_slot_sets.has_any_modification`
- recompute `training_program_slot_exercises.has_any_modification`
- recompute `training_program_slot_exercises.modified_set_count`
- recompute `training_program_slot_exercises.completed_set_count`
- recompute `training_program_slot_exercises.pending_set_count`
- recompute `training_program_slot_exercises.skipped_set_count`
- recompute `training_program_slots.has_any_modification`
- recompute slot summary counts/status if needed

This logic likely belongs either:

- in the new athlete value service
- or in a shared session-status recalculation service used by both the new service and `TrainingSessionProgressService`

Shared recalculation is preferable if it keeps status logic from diverging.

## Status Logic Changes Required

### Current Problem

`app/Training/TrainingSessionProgressService.php` currently clears modification state when marking complete or skipped.

Today it does things like:

- sets `has_any_modification` to `false`
- sets `modified_set_count` to `0`

That conflicts directly with the requested feature.

### Required Change

Refactor `TrainingSessionProgressService` so that completion does not wipe out athlete modifications.

Desired behavior:

- marking done preserves actual values
- marking done preserves modified flags
- sets with modified values can become `CompletedWithModification`
- an exercise with at least one modified completed set should remain visibly modified

### Recommended Semantics

For `Mark Done`:

- unmodified pending set -> `Completed`
- modified pending set -> `CompletedWithModification`

For exercise status:

- if all sets are completed and none are skipped:
  - exercise should likely still be `Completed`
  - but `has_any_modification = true` if any set is modified

Alternative:

- consider whether exercise-level status should remain `Completed` and use `has_any_modification` purely for edit state

This is the cleanest path unless product explicitly wants a new exercise-level modified status.

For skipped exercises:

- clarify whether editing should remain available after `Skip`

Recommended default:

- do not allow post-skip editing unless specifically requested

Reason:

- the product request explicitly mentions continued editing after `Mark Done`
- it does not mention continued editing after `Skip`

If this is implemented as disallowed:

- hide or disable the pencil for skipped exercises

If product later wants skip-editing too, it can be added.

## Modal UX Details

### Tabs

Use a tab per set.

Label format:

- `{setLabel} 1`
- `{setLabel} 2`
- etc.

Where `setLabel` should come from the exercise configuration already used by the athlete grid.

Current source used in the athlete view builder:

- `exercise->config->sets->label ?? 'Set'`

### Fields per Tab

For each set:

- show one field per editable setting that exists on that set

Ignore settings that are not meant for direct athlete entry if needed.

For example:

- reps
- weight
- distance
- duration
- pace
- watts
- heartRate
- heartRateZone
- tempo
- rest
- note

The exact final list should be driven by the setting enum / setting classes, not hardcoded in the view.

### Prefill Rules

For each set value field:

- if actual value exists, prefill that
- otherwise prefill planned value

### Save Rules

Before exercise completion:

- use an explicit save button in the modal

After exercise completion:

- changes should autosave

Recommended implementation:

- keep the save button for consistency
- add autosave on change after completion

Or:

- autosave for all states if that is simpler and stable

The requirement only explicitly mandates autosave after completion, but autosave everywhere may be acceptable if the UX remains safe.

If implementing autosave everywhere:

- debounce requests
- show lightweight saving feedback

If implementing mixed behavior:

- before completion use explicit save
- after completion switch to autosave

That mixed behavior is slightly more complex but matches the request more literally.

## Validation and Field Reuse

### Existing Reusable Pieces

Relevant existing sources of field logic:

- `app/Data/Exercise/Settings/AbstractSetting.php`
- concrete setting classes under `app/Data/Exercise/Settings`
- `app/Form/Fields/*`
- `app/Data/Exercise/Preview/CellInputMeta.php`
- `resources/views/components/training/exercise-grid-input.blade.php`

Examples already in code:

- `RepsSetting::inputMeta()`
- `WeightSetting::inputMeta()`
- `AbstractSetting::resolveUnitLabel()`
- custom field classes like `App\Form\Fields\Reps` and `App\Form\Fields\Weight`

### Recommendation

Do not duplicate:

- suffixes
- masks
- min/max rules
- numeric step logic
- setting labels

Instead:

- expose reusable athlete-field metadata from the setting classes
- build the modal fields from that metadata

The setting class should know:

- how to render athlete input
- how to validate athlete input
- how to normalize athlete input
- how to format athlete output

## File-Level Implementation Checklist

### Config

- `config/athlete.php`
  - add `allow_athlete_edits`

### Athlete Livewire

- `app/Livewire/Athlete/ProgramDetails.php`
  - expose feature flag
  - open modal
  - refresh after save
  - optionally enforce no editing for future sessions and skipped sessions

- create `app/Livewire/Athlete/ExerciseValueEditor.php`
  - modal state
  - set tabs
  - dynamic fields
  - validation
  - save/autosave

### Athlete Views

- `resources/views/livewire/athlete/program-details.blade.php`
  - toolbar layout
  - edit button
  - modified grid classes if needed

- create `resources/views/livewire/athlete/exercise-value-editor.blade.php`
  - modal markup
  - per-set tabs
  - fields
  - save/autosave UX

- `resources/views/components/layouts/athlete.blade.php`
  - mount the new modal component globally if that is the chosen pattern

### View Builders / DTOs

- `app/Support/Athlete/ProgramDetailsExerciseViewBuilder.php`
  - prefer actual values over planned values
  - add modified highlighting
  - resolve labels/units consistently

- `app/Data/Athlete/ProgramDetailsExerciseData.php`
  - extend with editability/toolbar metadata if useful

- `app/Data/Athlete/ProgramDetailsSessionRowData.php`
  - extend with cell-level modified metadata if useful beyond CSS class strings

### Setting System

- `app/Data/Exercise/Settings/AbstractSetting.php`
  - add athlete-entry extension points

- concrete setting classes in `app/Data/Exercise/Settings/*`
  - implement athlete field/validation/normalization/display behavior

- `app/Data/Exercise/ExerciseSetting.php`
  - help map from setting key to setting class where needed

### Domain Services

- create `app/Training/AthleteExerciseValueService.php`
  - persist actual values
  - mark modifications
  - roll up summary state

- `app/Training/TrainingSessionProgressService.php`
  - stop clearing modifications on completion
  - preserve modified state after `Mark Done`
  - use `CompletedWithModification` where appropriate

## Testing Plan

Add or expand tests in:

- `tests/Feature/Livewire/Athlete/ProgramDetailsTest.php`

Potentially add focused tests for the new modal/service:

- `tests/Feature/Livewire/Athlete/ExerciseValueEditorTest.php`
- `tests/Unit/Training/AthleteExerciseValueServiceTest.php`
- `tests/Unit/Data/Exercise/Settings/*` for athlete-entry normalization/validation where needed

### Required Test Cases

1. Feature flag off

- pencil is hidden
- edit actions are unavailable

2. Feature flag on with no media

- toolbar still renders
- pencil shows even when no video/gallery exists

3. Feature flag on with media

- video/gallery remain left aligned
- pencil is present on the right

4. Modal set tabs

- correct number of tabs
- correct set label text

5. Field prefills

- planned values appear by default
- saved actual values re-open correctly

6. Validation reuse

- invalid athlete input fails according to the setting rules
- valid athlete input saves correctly

7. Save with no changes

- no values marked modified
- no highlight shown

8. Save with changes before completion

- actual values saved
- `is_modified` set
- grid highlights changed cells
- exercise remains uncompleted

9. Mark done after edits

- edited values remain
- modification state remains
- completion succeeds

10. Edit after completion

- athlete can reopen modal
- changes autosave
- grid refreshes

11. Revert back to planned value

- modification flag is removed
- highlight disappears

12. Skip behavior

- verify chosen product behavior
- if skipping disables editing, ensure pencil is hidden or blocked

13. Future session safety

- future sessions should likely still be non-editable, matching current completion restrictions
- verify the modal cannot be used for future sessions unless product explicitly wants that changed

## Recommended Implementation Sequence

1. Add the feature flag.
2. Build the setting-layer athlete-entry abstraction.
3. Build the persistence service for actual values and modification rollups.
4. Refactor `TrainingSessionProgressService` to preserve modifications.
5. Build the athlete edit modal Livewire component.
6. Add the toolbar pencil and wire modal opening from `ProgramDetails`.
7. Update the athlete view builder to render actual values and modified highlights.
8. Add feature and unit tests.
9. Manually verify the full flow:
   - open exercise
   - edit values
   - save
   - see highlighted cells
   - mark done
   - reopen and edit again
   - confirm autosave and preserved highlights

## Important Design Decisions

### Decision 1: Use Materialized Session Actual Values

This is the most important design choice.

Use:

- `TrainingProgramSlotSetValue.actual_*`

Do not create:

- another override layer
- another source of truth

### Decision 2: Keep Setting Logic Centralized

Use setting classes as the single source of truth for:

- validation
- units
- labels
- field behavior
- normalization

### Decision 3: Do Not Clear Modification Flags on Completion

The product explicitly wants athletes to still see what they changed and continue editing after completion.

That means:

- completion and modification are orthogonal concepts
- completion should not reset athlete edits

### Decision 4: Prefer Shared Status Recalculation

If status/modification rollup logic starts spreading between multiple services, bugs will follow.

Prefer one shared recalculation path that both:

- athlete value saving
- completion/skipping

can rely on.

## Open Product Questions

These are the only notable ambiguities still present in the request.

### 1. Should skipped exercises remain editable?

Not specified.

Recommended default:

- no

### 2. Should autosave apply only after completion or for all edits?

The request explicitly requires autosave after completion.

Recommended implementation approach:

- explicit save before completion
- autosave after completion

Alternative:

- autosave everywhere if this materially simplifies the implementation and UX stays solid

### 3. Which settings are athlete-editable?

The request says:

- show a form field for each setting per set

That suggests all materialized per-set settings should be editable.

If a setting should not be editable in practice, the setting class should be able to opt out explicitly.

## Final Guidance for the Implementing Agent

If there is one thing to preserve, it is this separation:

- coach plan = planned values
- athlete-performed adjustment = actual values

And if there is one thing not to do, it is this:

- do not implement athlete edits as another preview/config override system

The existing schema is already pointing to the correct design. The implementation should lean into that and make the setting classes the central place where athlete input behavior is defined.
