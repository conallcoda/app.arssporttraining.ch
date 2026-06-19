# Plan / Materialized Parity Handover

## Goal

Make it impossible for the admin plan view and the athlete dashboard to show different planned values for the same scheduled session.

Current product rule to move toward:

```text
Recorded sessions are frozen.
Unrecorded sessions are open.
Date alone should not decide whether a planned session can be edited, rebuilt, or repaired.
```

This came out of the carry-over athlete values work. The athlete dashboard can show the updated materialized planned snapshot while the admin plan grid can still show the older plan value when the session is in the past and therefore treated as historical/locked.

That mismatch must not be allowed.

## Current Problem

There are two related concepts that are currently mixed together:

1. Past by date.
2. Recorded/immutable because the athlete has actual data, skipped/completed state, or modifications.

The plan grid uses `lockedSessionsByWeek` to decide when to preserve historical values and when to pull from materialized snapshots. Today that lookup is populated by `TrainingSessionEditGuard::applyPlanEditLockConstraints()`, which locks:

```php
datetime <= now()
OR recorded outcome exists
```

That means an unrecorded past session is treated as locked in the admin plan grid, even though the athlete dashboard can still render its materialized planned snapshot.

Example from the issue:

- Admin edits or carry-over updates 15.06.
- 17.06 is after the source and unrecorded, but is now in the past.
- Athlete dashboard for 17.06 shows the materialized planned values `10, 10, 8`.
- Admin plan grid still shows `7.5, 7.5, 7.5` because it is using the historical locked path.

## Invariant

For a scheduled slot/exercise/set/field:

```text
admin planned value == materialized planned snapshot value shown to athlete
```

The only acceptable exception is when a session has recorded athlete data. In that case the planned snapshot is intentionally frozen and should not be rewritten by normal plan edits or carry-over.

## Main Decision

Change the general plan edit lock rule from date-based to recorded-state-based.

Recommended behavior:

- Unrecorded past sessions are editable/open for planned-value updates.
- Recorded sessions are locked, regardless of whether they are past or future.
- Carry-over should update target sessions after the source session if the target has no recorded data, even if the target date is before `now()`.
- Backfill/repair tools should be able to repair unrecorded past drift without needing a special historical repair path.

## Affected Production Files

### 1. `app/Training/TrainingSessionEditGuard.php`

Current:

```php
public function applyPlanEditLockConstraints(Builder $query): Builder
{
    return $query->where(function (Builder $query): void {
        $query
            ->where('datetime', '<=', now())
            ->orWhere(fn (Builder $query) => $this->applyRecordedOutcomeConstraints($query));
    });
}
```

Suggested:

```php
public function applyPlanEditLockConstraints(Builder $query): Builder
{
    return $this->applyRecordedOutcomeConstraints($query);
}
```

Also update the docblock from "future-only" wording to "recorded sessions are locked".

### 2. `app/Livewire/Training/Concerns/WithCalendarPlan.php`

This builds `lockedSessionsByWeek` from `TrainingSessionEditGuard::planEditLockedDateTimeLookup()`.

Expected impact:

- Once the guard changes, `lockedSessionsByWeek` should only mark sessions with recorded outcomes.
- Verify plan schedule info no longer marks unrecorded past sessions locked.

### 3. `app/Livewire/Training/View/PlanExerciseGrid.php`

Important paths:

- `getHistoricalGridOverrides()`
- `materializedHistoricalGridOverrides()`
- `snapshotLockedWeeks()`
- `syncLockedHistoricalSessionSnapshots()`
- `shouldRestrictPlannedEditForSession()`
- `saveOverrides()`

With the new rule, "historical" should mean recorded/frozen, not past by date.

Expected impact:

- Unrecorded past sessions should use normal `gridOverrides`.
- Recorded sessions should continue to use frozen historical/materialized snapshot values.
- Saving plan grid changes must update the materialized planned snapshot for every affected unrecorded scheduled session, not only future sessions.

Watch this method:

```php
private/protected function isSessionInPast(...)
```

There is a direct date check around:

```php
$slot->datetime->lessThanOrEqualTo(now())
```

Audit whether that still has a legitimate UI-only use. It should not drive planned/materialized divergence.

### 4. `app/Training/TrainingSessionRebuildService.php`

Current rebuild methods are future/date-based:

```php
private function futureSlotsQuery(): Builder
{
    return TrainingProgramSlot::query()
        ->whereNull('cancelled_at')
        ->where('datetime', '>', now())
        ->orderBy('datetime')
        ->orderBy('id');
}
```

This is not enough if plan edits can affect unrecorded past sessions.

Recommended change:

- Introduce an "editable/open scheduled slots" rebuild path.
- It should exclude cancelled and recorded/immutable slots.
- It should not exclude by date unless a caller explicitly asks for future-only.

Possible naming:

```php
rebuildOpenSlotsForExerciseProgram(...)
rebuildOpenSlotsForTrainingProgramAthlete(...)
openSlotsQuery()
```

Keep existing future-specific methods if other callers genuinely need future-only semantics, but plan-grid saves should use the open/unrecorded path.

### 5. `app/Training/TrainingSessionMaterializer.php`

Current skip logic blocks forced materialization for past slots unless `allowImmutableRewrite` is true:

```php
if ($slot->datetime->lte(now()) && ! $allowImmutableRewrite) {
    return true;
}
```

This is another date-based mismatch source.

Recommended change:

- Skip materialization when the slot has recorded data, not merely when it is past.
- Reuse or inject `TrainingSessionEditGuard` if practical.
- Be careful not to delete actual rows for recorded sessions.

The materializer currently deletes and recreates slot exercises when rebuilding, so the recorded-data guard is critical.

### 6. `app/Training/CarryOverAthleteValuesService.php`

Current in-progress carry-over implementation chooses override layer by date:

```php
$layer = $target->slot->datetime->lte(now()) ? 'historical' : 'current';
```

With the new rule, carry-over should write normal/current overrides for unrecorded target sessions. Recorded target sessions are skipped already and should remain skipped.

Recommended:

- Remove the date-based layer selection.
- Use current `gridOverrides` for every eligible target.
- Keep target eligibility based on recorded actuals/status, not date.

### 7. `app/Training/ScheduledTrainingSnapshotClassifier.php`

Current classifier adds `datetime_in_past` and returns `locked_past` when date is in the past.

This affects audit/backfill/repair tooling:

- `ScheduledTrainingSnapshotBackfillService`
- `ScheduledTrainingSnapshotCompareService`
- `FixTrainingSnapshotDriftCommand`
- `BackfillTrainingSnapshotsCommand`

Recommended:

- Do not classify a slot as locked solely because `datetime <= now()`.
- Classify recorded/completed/skipped/modified slots as locked or ambiguous.
- Classify unrecorded past slots with drift as repairable/open.

Names like `locked_past` may become misleading. Either keep the existing names temporarily for compatibility or introduce clearer classifications:

```text
open_unrecorded
locked_recorded
ambiguous_boundary
```

## Other Mismatch Sources To Audit

### Plan grid saves

`PlanExerciseGrid::saveOverrides()` persists config overrides and dispatches rebuild jobs when future-affecting overrides changed.

Risk:

```text
config changes, but materialized planned snapshots are not rebuilt for unrecorded past sessions
```

Fix:

- Dispatch/rebuild open unrecorded sessions, not only future sessions.

### Rebuild jobs

Files:

- `app/Jobs/RebuildFutureSlotsForAthleteJob.php`
- `app/Jobs/RebuildFutureSlotsForExerciseProgramJob.php`
- `app/Jobs/RebuildFutureSlotsForTrainingProgramJob.php`
- `app/Jobs/RebuildFutureSlotsForAthleteExerciseProgramJob.php`
- `app/Jobs/RebuildFutureSlotsForTrainingProgramAthleteJob.php`
- `app/Training/TrainingSessionRebuildDispatcher.php`

Risk:

The naming and implementation are future-only. After this change, plan-grid rebuild dispatch may need either new jobs or renamed/generalized jobs.

### Metric-driven rebuilds

`app/Observers/MetricSubmissionObserver.php` dispatches future slot rebuilds for 1RM/heart-rate changes.

This may intentionally remain future-only, because metric submissions are date-based training inputs. Do not change without checking product intent.

### Slot observer rebuilds

`app/Observers/TrainingProgramSlotObserver.php` rebuilds sibling future slots when scheduled slots are created/updated/deleted.

This probably remains future-oriented for schedule edits, but audit carefully if open past slots should also reflect changed schedule structure.

### Reset/repair commands

`ScheduledTrainingSnapshotResetService` can reset broadly and preserves actual state more carefully than the materializer. Confirm its behavior still matches the new invariant.

`ScheduledTrainingSnapshotRepairService` intentionally allows immutable rewrite. Keep it as an explicit repair tool, but tests should prove normal paths do not rewrite recorded sessions.

## Test Plan

### Update existing tests

Likely failing tests after the guard change:

- `tests/Feature/Training/TrainingSessionEditGuardTest.php`
  - Rename/update "locks all non-future and recorded slots for plan editing".
  - New expectation: unrecorded past slot is not locked; recorded future slot is locked.

- `tests/Feature/Livewire/CalendarIndexOverrideTest.php`
  - Current expectation may be `[[true, true]]` for one past unrecorded and one recorded session.
  - New expectation should be `[[false, true]]`.

- `tests/Feature/Training/CarryOverAthleteValuesServiceTest.php`
  - Replace the historical override expectation for past unrecorded targets.
  - New expectation: past-but-unrecorded target after source gets normal `gridOverrides`, not `historicalGridOverrides`.

### Add parity tests

Add an end-to-end style test proving:

1. Create scheduled sessions on 15.06 and 17.06.
2. Set `now()` after 17.06.
3. Leave 17.06 unrecorded.
4. Change/carry-over planned values from 15.06 into 17.06.
5. Assert admin plan grid value for 17.06 equals `ScheduledSessionSnapshotBuilder::build($slot17)` planned value.
6. Assert athlete dashboard/program details renders the same planned value.

Add a companion test:

1. Same setup.
2. Mark 17.06 as recorded or add explicit actual values.
3. Change/carry-over from 15.06.
4. Assert 17.06 is not changed in config overrides or materialized planned snapshot.

### Audit/backfill tests

Update/add tests for:

- `ScheduledTrainingSnapshotClassifierTest` or existing `ScheduledTrainingSnapshotAuditServiceTest`.
- `ScheduledTrainingSnapshotBackfillServiceTest`.

Expected:

- Unrecorded past drift is repairable/open.
- Recorded past drift is locked/skipped unless explicit repair is used.

### Focused command/tests to run

```bash
php artisan test tests/Feature/Training/TrainingSessionEditGuardTest.php
php artisan test tests/Feature/Livewire/CalendarIndexOverrideTest.php
php artisan test tests/Feature/Livewire/Training/PlanExerciseGridActualValuesTest.php
php artisan test tests/Feature/Livewire/Training/PlanExerciseGridMixedSessionSaveTest.php
php artisan test tests/Feature/Livewire/Training/PlanExerciseGridFutureBoundaryTest.php
php artisan test tests/Feature/Training/CarryOverAthleteValuesServiceTest.php
php artisan test tests/Feature/Training/TrainingSessionMaterializerTest.php
php artisan test tests/Feature/Training/TrainingSessionRebuildServiceTest.php
php artisan test tests/Feature/Training/ScheduledTrainingSnapshotAuditServiceTest.php
php artisan test tests/Feature/Console/BackfillTrainingSnapshotsCommandTest.php
php artisan test tests/Feature/Console/FixTrainingSnapshotDriftCommandTest.php
php artisan test tests/Feature/Livewire/Athlete/ProgramDetailsTest.php
```

## Implementation Order

1. Add or update tests for the desired lock rule.
2. Change `TrainingSessionEditGuard` to lock only recorded sessions.
3. Update plan schedule expectations and plan grid historical/current expectations.
4. Change carry-over to persist unrecorded target overrides in the current layer.
5. Generalize rebuild/materializer paths from future-only to open/unrecorded where used by plan edits.
6. Update snapshot classifier/backfill semantics.
7. Add parity tests proving admin plan grid and athlete dashboard read the same planned values.
8. Run focused tests above.

## Notes From Current Session

The exercise pivot identity audit was healthy after migration:

```text
slot_exercises_audited: 1583
direct_identity_column_exists: true
direct_identity_present: 1583
direct_identity_valid: 1583
direct_identity_missing_pivot: 0
direct_identity_program_mismatch: 0
direct_identity_loose_signature_mismatch: 0
```

Strict signature mismatches existed, but direct `exercise_program_exercise_id` identity was valid. Continue using the direct pivot id for carry-over and parity checks.

Current carry-over implementation is in progress in this working tree. Before starting this parity work in a new session, inspect current diffs carefully and do not assume this document reflects committed code.
