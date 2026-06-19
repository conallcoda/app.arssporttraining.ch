# Carry Over Athlete Values Handover

## Goal

Add a toggle that lets athlete-entered manual values carry forward through the rest of the plan.

Working name:

```text
Carry over athlete values
```

Default:

```text
true
```

Important default rule:

Existing configs that do not store this option must behave as if it is `true`, and the edit form should show it as enabled.

## Product Behavior

When an athlete records actual values for a manual training exercise and carry-over is enabled, copy the recorded athlete values into future planned sessions for the same program exercise.

Example:

```text
Session 1 recorded:
Set 1: 7.5
Set 2: 7.5
Set 3: 10

Future 3-set sessions become:
7.5 - 7.5 - 10

Future 4-set sessions become:
7.5 - 7.5 - 10 - 10
```

The source values are actual athlete entries. The destination should be future planned snapshot values, not future actual values. Future actual fields must remain empty until the athlete records those sessions.

## Scope

Apply this only to manual training exercises, but across all exercise categories: strength, conditioning, etc.

Initial fields:

- `weight`
- `reps`

Carry reps and weight independently:

- if the athlete records weight only, carry weight only
- if the athlete records reps only, carry reps only
- if both are recorded, carry both

Do not carry unrelated settings such as tempo, rest, notes, heart rate, duration, distance, pace, or watts unless a later product decision explicitly expands the feature.

## Identity Prerequisite

The first step has been completed: materialized slot exercises now store `exercise_program_exercise_id`.

This feature should rely on that id. Do not match future sessions using:

```text
exercise_id + sort + group + type
```

The reason is that sort/group/type can drift after sessions have been materialized, and the same exercise can appear more than once in a program. Carry-over must target the exact source program exercise pivot.

Relevant completed identity work:

- `training_program_slot_exercises.exercise_program_exercise_id`
- `TrainingProgramSlotExercise::programExercise()`
- compiler/materializer writes the pivot id
- snapshots include `programExerciseId`
- plan actual grid matching uses pivot id
- effective config resolver uses pivot id
- snapshot audit/reset use pivot id
- editor deletion keeps referenced pivots and deletes only unused removed pivots

Local audit after the identity work:

```text
slot_exercises_audited: 1640
direct_identity_present: 1640
direct_identity_valid: 1640
direct_identity_missing_pivot: 0
direct_identity_program_mismatch: 0
```

## Suggested Config Shape

The simplest place for the toggle is the manual weight setting config, because this feature is about manual load progression.

Suggested config key:

```php
'weight' => [
    'mode' => 'manual',
    'carryOverAthleteValues' => true,
]
```

Runtime rule:

```php
$carryOver = (bool) data_get($effectiveConfig, 'weight.carryOverAthleteValues', true);
```

Only honor it when the effective weight mode is manual. If there is no weight setting or weight is automatic, do not carry values.

The UI label can be:

```text
Carry over athlete values
```

Avoid making older saved config arrays noisy. It is fine for the default `true` to be implicit, as long as form hydration displays true.

## Main Entry Point

The athlete actual save path is:

```php
app/Training/AthleteExerciseValueService.php
```

Specifically:

```php
AthleteExerciseValueService::saveExerciseValues()
```

This is where actual values are normalized, saved, revisions are recorded, and exercise/session status is refreshed.

Recommended flow:

1. Save actual values as it does today.
2. If there were changes, inspect the changed source exercise.
3. If carry-over is enabled and the exercise qualifies, apply carry-over inside the same transaction or immediately after it in a dedicated service.
4. Refresh affected future exercise/session state if planned values changed.

Prefer a dedicated service, for example:

```php
App\Training\CarryOverAthleteValuesService
```

This keeps `AthleteExerciseValueService` from becoming too large.

## Carry-Over Algorithm

Input:

- source `TrainingProgramSlotExercise`
- recorded fields from that exercise, restricted to `weight` and `reps`

Source requirements:

- `exercise_program_exercise_id` is present
- source slot has `training_program_id`, `user_id`, and scheduled date/datetime
- effective config has manual weight and `carryOverAthleteValues !== false`
- at least one explicit actual `weight` or `reps` value exists

Target future exercises:

- same `training_program_id`
- same `user_id`
- slot date/datetime after the source slot
- same `exercise_program_exercise_id`
- not cancelled
- not already recorded/immutable

Suggested target query:

```php
TrainingProgramSlotExercise::query()
    ->where('exercise_program_exercise_id', $source->exercise_program_exercise_id)
    ->whereHas('slot', fn ($query) => $query
        ->where('training_program_id', $source->slot->training_program_id)
        ->where('user_id', $source->slot->user_id)
        ->whereNull('cancelled_at')
        ->where('datetime', '>', $source->slot->datetime)
    )
    ->with(['slot', 'sets.values'])
```

For each target set:

- set index 0 gets source set index 0
- set index 1 gets source set index 1
- if the target has more sets than the source, use the last source set value
- if the source lacks that field for all sets, do not change that field

Example:

```text
source sets: [7.5, 7.5, 10]
target sets: 4
target values: [7.5, 7.5, 10, 10]
```

## Planned Value Writes

Destination should update planned value columns on future `training_program_slot_set_values` rows:

- `planned_value_type`
- `planned_int_value`
- `planned_decimal_value`
- `planned_string_value`
- `planned_json_value`
- `unit`

Do not write:

- `actual_value_type`
- `actual_int_value`
- `actual_decimal_value`
- `actual_string_value`
- `actual_json_value`
- `actual_recorded_by`
- `actual_recorded_at`
- `actual_is_explicit`

Existing helper to consider:

```php
app/Training/TrainingSessionPlannedValueService.php
```

It already knows how to encode planned values from submitted values. It currently works per exercise and submitted set id, so the carry-over service can either reuse it or share/extract its planned encoding logic.

Important: avoid treating carry-over as a coach manual plan edit from the grid unless that is a deliberate product choice. It is an automatic propagation caused by athlete recording.

## Locked/Recorded Future Sessions

Do not overwrite future sessions that already have real recorded data.

Relevant existing guard concepts:

- `TrainingSessionEditGuard`
- `TrainingProgramSlotSetValue.actual_value_type`
- `actual_is_explicit`
- slot/exercise/set status counters

At minimum, skip a target exercise if any of its values has an explicit actual value. A stricter rule is to skip the whole target slot if it has recorded data.

Recommended first version:

- skip target slot exercises where any target value has `actual_value_type !== null` or `actual_is_explicit = true`
- do not delete or recreate target rows
- update planned values in place

## Revision/Audit Considerations

Actual value revisions already exist:

```php
TrainingActualValueRevision
TrainingRevisionBatch
```

Planned grid edits have revision support through:

```php
TrainingPlanRevisionService
```

Decide whether carry-over planned writes need their own revision batch. Recommended:

- record a revision batch with source/action such as `carry_over_athlete_values`
- include the source slot exercise id in metadata if the revision model supports it

If revision support is not easy to reuse, add tests first and keep the planned writes explicit and discoverable.

## Form/UI Changes

Likely files to inspect:

- `app/Data/Exercise/Settings/WeightSetting.php`
- `app/Livewire/Training/View/PlanExerciseSettingsForm.php`
- `app/Livewire/Training/CalendarExerciseSettingsForm.php`
- `resources/views/livewire/training/view/plan-exercise-settings-form.blade.php`
- `resources/views/livewire/training/calendar-exercise-settings-form.blade.php`

Expected behavior:

- show toggle only when weight mode is manual, or keep it disabled/hidden otherwise
- default to checked when config key is missing
- persist false only when the user turns it off
- editing an existing form with no saved value displays checked

## Tests To Add

Add focused tests before broad UI coverage.

Recommended feature tests:

1. Carries weight and reps from actual source to future planned values.
2. Copies the last source set into extra future sets.
3. Does not write future actual values.
4. Does not affect another occurrence of the same `exercise_id` with a different `exercise_program_exercise_id`.
5. Does not affect warm-up/main/cool-down duplicates unless they share the same pivot id, which they should not.
6. Does not run when `carryOverAthleteValues` is false.
7. Missing config behaves as true.
8. Skips future target sessions that already have recorded actuals.
9. Handles partial actuals:
   - weight only carries weight
   - reps only carries reps
   - blank actuals do not erase future planned values
10. Does not rely on sort/group:
    - change source or target sort/group after materialization and ensure carry-over still works by pivot id.

Good existing test areas:

- `tests/Feature/Training/TrainingSessionMaterializerTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridActualValuesTest.php`
- add a new file if the service is large, for example:

```text
tests/Feature/Training/CarryOverAthleteValuesServiceTest.php
```

## Regressions To Watch

The dangerous regressions are:

- writing future actual values instead of future planned values
- carrying values to the wrong duplicate exercise
- matching by sort/group/type again
- overwriting sessions that already have recorded data
- clearing future values when the source has blanks
- copying automatic weight calculations where manual carry-over should not apply
- causing compiled version drift without updating materialized planned snapshots intentionally
- failing when a future session has more sets than the source
- silently doing nothing because the toggle defaults to false when absent

## Recommended Implementation Order

1. Add config/default handling for `weight.carryOverAthleteValues`.
2. Add the toggle to relevant settings forms.
3. Create `CarryOverAthleteValuesService`.
4. Call it from `AthleteExerciseValueService` after successful actual saves.
5. Implement future target lookup by `exercise_program_exercise_id`.
6. Update planned values only, using existing normalization/encoding where possible.
7. Add focused service tests.
8. Add one UI/form hydration test for missing config showing checked.
9. Run the focused actual-value/materializer/grid tests.

## Verification Baseline From Identity Step

Before starting this feature in a new session, useful commands:

```bash
php artisan training:audit-slot-exercise-identity --show=3
php artisan test tests/Feature/Training/TrainingSessionMaterializerTest.php tests/Feature/Livewire/Training/PlanExerciseGridActualValuesTest.php tests/Feature/Livewire/Training/ProgramEditorRebuildTest.php tests/Feature/Training/ResolvedPlannedSessionBuilderTest.php tests/Unit/Training/PlanCompilerTest.php
```

The identity-focused suite passed with:

```text
52 passed
```
