# Unified Training Runtime Refactor

Date: 2026-05-15

## Goal

Complete the next architectural step after the session-first planning work:

1. Make scheduled training resolve from a single runtime truth.
2. Reduce the number of places that recompute planning logic independently.
3. Preserve historical snapshots without relying on fresh recomputation.
4. Keep actual performance separate from planned prescription.
5. Move most business verification to pure DTO transformations with thin persistence/integration coverage.

This document treats the existing codebase and test suite as migration constraints, not just implementation details.

## Current Problem

The system still has two overlapping runtime truths for scheduled training:

1. Plan-time computed values built from:
   - config defaults
   - override layers
   - block context
   - metric baseline lookups
   - preview/session grouping logic

2. Scheduled slot materialized values stored in:
   - `training_program_slots`
   - `training_program_slot_exercises`
   - `training_program_slot_sets`
   - `training_program_slot_set_values`

This creates fragile duplication:

- the plan screen can render from fresh computation
- the athlete dashboard renders from persisted planned snapshots
- historical views partially rely on historical overrides instead of just reading frozen slot truth
- schedule mutations can change progression context in ways that require sibling slot rebuilds

The Front Squat mismatch investigated on 2026-05-15 is a direct example of this divergence.

## Non-Negotiable Invariants

These are the invariants the refactor must preserve.

### Runtime Truth

1. A scheduled session has exactly one canonical planned snapshot.
2. Athlete and coach scheduled-session views must read the same planned snapshot.
3. Rendering scheduled training must not require recomputing planning logic from authoring config.

### History

4. Locked/past sessions must remain frozen unless an explicit repair/rematerialization tool is used.
5. Historical rendering must prefer frozen materialized planned snapshots over fresh recomputation.
6. Actual recordings must not mutate the meaning of the planned snapshot.

### Future Rebuilds

7. Future scheduled sessions must rebuild when relevant authoring inputs change.
8. Schedule-shape mutations must rebuild all affected future sessions in scope, not just the touched slot.
9. Progression must compile against the full relevant scheduled sequence, not a narrow local subset.

### Metrics / Blocks

10. Metric baseline selection must use the effective block context and cutoff date.
11. Athlete-specific block overrides must influence effective planning context consistently across preview, compilation, and rendering.
12. Missing required metrics must suppress metric-dependent exercises consistently in both preview and compiled runtime.

### Audit

13. `training_plan_value_revisions` records authoring input changes, not runtime display state.
14. `training_state_revisions` records workflow/status/block state changes, not planning reconstruction.
15. `training_actual_value_revisions` records actual performance changes only.
16. Revision tables are audit-only and must never be required to reconstruct runtime display state.

### Testing

17. Most business rules must be verifiable as pure DTO transformations.
18. Persistence tests should prove projection shape and rebuild orchestration, not carry the full business burden.

## Current Touchpoint Inventory

### Authoring / Preview

- `app/Livewire/Training/View/PlanExerciseGrid.php`
- `app/Livewire/Training/View/ProgramEditor.php`
- `app/Livewire/Training/View/PlanExerciseSettingsForm.php`
- `app/Livewire/Training/CalendarExerciseSettingsForm.php`
- `app/Support/Training/ProgramPreviewBuilder.php`
- `app/Support/Athlete/PlannedProgramDetailsExerciseViewBuilder.php`
- `app/Data/Exercise/Preview/ExercisePreviewBuilder.php`
- `app/Training/Planning/ResolvedPlannedSessionBuilder.php`

### Scheduled Compilation / Projection

- `app/Training/TrainingSessionCompiler.php`
- `app/Training/TrainingSessionMaterializer.php`
- `app/Training/TrainingSessionRebuildService.php`
- `app/Training/ScheduledTrainingSnapshotResetService.php`
- `app/Observers/TrainingProgramSlotObserver.php`

### Scheduled Runtime Reads

- `app/Livewire/Athlete/ProgramDetails.php`
- `app/Support/Athlete/ProgramDetailsExerciseViewBuilder.php`
- `app/Training/TrainingSessionPlannedValueService.php`
- `app/Training/AthleteExerciseValueService.php`

### Planning Context / Blocks / Metrics

- `app/Livewire/Training/Concerns/WithCalendarPlan.php`
- `app/Training/CalendarBlockService.php`
- `app/Support/Training/SlotSessionNumberResolver.php`
- `app/Training/ProjectedOneRepMaxService.php`
- `app/Support/Training/ExerciseMetricAvailability.php`

### Audit / Repair

- `app/Training/TrainingPlanRevisionService.php`
- `app/Training/TrainingStateRevisionService.php`
- `app/Models/Training/TrainingRevisionBatch.php`
- `app/Models/Training/TrainingPlanValueRevision.php`
- `app/Models/Training/TrainingStateRevision.php`
- `app/Models/Training/TrainingActualValueRevision.php`
- `app/Console/Commands/ResetScheduledTrainingSnapshotsCommand.php`

## Existing Test Harness To Preserve

These tests already encode important behavior and should remain green throughout the migration.

### High-Signal Core Runtime Tests

- `tests/Feature/Training/TrainingSessionCompilerTest.php`
- `tests/Feature/Training/TrainingSessionMaterializerTest.php`
- `tests/Feature/Training/PastSlotFreezeTest.php`
- `tests/Feature/Training/ScheduledTrainingSnapshotResetServiceTest.php`
- `tests/Feature/Training/ResolvedPlannedSessionBuilderTest.php`
- `tests/Feature/Training/CompiledSettingTypePreservationTest.php`
- `tests/Feature/Training/AutomaticHeartRateCompilationTest.php`

### High-Signal Plan / Scheduled View Tests

- `tests/Feature/Livewire/Training/PlanExerciseGridActualValuesTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridMixedSessionSaveTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridExpandedSessionDisplayTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridFutureBoundaryTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridMetricAvailabilityTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridAutomaticHeartRateSessionTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridScopedRebuildTest.php`

### Athlete Runtime Tests

- `tests/Feature/Livewire/Athlete/ProgramDetailsTest.php`
- `tests/Feature/Livewire/Athlete/TodayScheduleTest.php`
- `tests/Feature/Livewire/Athlete/CalendarRoutingTest.php`

### Metrics / Block Context Tests

- `tests/Feature/Livewire/CalendarPlanMetricsTest.php`
- `tests/Unit/Training/SlotSessionNumberResolverTest.php`

## Target Architecture

### 1. Separate Authoring Inputs From Runtime Truth

Authoring inputs remain:

- exercise defaults
- strategy config
- program-level overrides
- athlete-level overrides
- block settings
- metric baseline lookups

But those inputs are not runtime truth for scheduled sessions.

### 2. Canonical Scheduled Snapshot DTOs

Introduce canonical DTOs for scheduled runtime:

```php
ScheduledSessionSnapshotData
ScheduledExerciseSnapshotData
ScheduledSetSnapshotData
ScheduledValueSnapshotData
RecordedValueSnapshotData
SessionStatusSnapshotData
```

These DTOs represent:

- frozen planned prescription
- optional actual performance
- workflow/status state

### 3. One Pure Compiler

Introduce a pure compiler boundary:

```php
PlanCompiler::compile(AuthoringProgramData $program, PlanningContextData $context): list<PlannedSessionData>
```

Rules:

- no Eloquent writes
- no view concerns
- full relevant scheduled sequence passed explicitly
- grouping affects derived behavior, not storage identity

### 4. One Projection Layer

Projection boundary:

```php
PlannedSessionProjector::project(list<PlannedSessionData> $sessions): void
```

This is the only layer that writes planned snapshots for scheduled slots.

### 5. One Scheduled Read Model

Introduce:

```php
ScheduledSessionSnapshotBuilder
```

This builder reads materialized slot rows and returns canonical runtime DTOs for:

- athlete session details
- coach scheduled plan display
- planned-vs-actual comparison screens
- export/reporting

### 6. History Policy

- future scheduled sessions compile from current authoring inputs
- locked/past sessions read frozen snapshots
- actuals merge at read time, never by reinterpreting plan inputs

## Refactor Slices

The safest delivery is phased.

### Slice 1 — Lock Existing Invariants And Fix Known Drift Bugs

Scope:

- block-aware compiler session context
- sibling future rebuilds after schedule shape changes
- planned-mode locked sessions read frozen slot snapshots

Verification:

- `TrainingSessionCompilerTest`
- `TrainingSessionMaterializerTest`
- `PlanExerciseGridActualValuesTest`

Status:

- completed on 2026-05-15 in code

### Slice 2 — Introduce Unified Scheduled Snapshot Read DTOs

Scope:

- add canonical scheduled snapshot DTOs
- add builder from `TrainingProgramSlot` materialized rows
- use the builder in athlete runtime first

Verification:

- `ProgramDetailsTest`
- new slot-model vs snapshot parity test for `ProgramDetailsExerciseViewBuilder`

Risk:

- row ordering, set numbering, note handling, skipped-set rendering

Status:

- completed on 2026-05-15 in code

### Slice 3 — Route Coach Scheduled Views To The Same Snapshot Builder

Scope:

- scheduled plan/actual views stop using ad hoc slot/model extraction
- plan scheduled mode and athlete mode share runtime read path

Verification:

- `PlanExerciseGridActualValuesTest`
- `PlanExerciseGridMixedSessionSaveTest`
- new “coach scheduled view equals athlete scheduled view” test

Risk:

- grouped display metadata
- session-scoped vs set-scoped repetition

Status:

- completed on 2026-05-15 in code

### Slice 4 — Introduce Explicit Pure Compiler DTO Boundary

Scope:

- formal `AuthoringProgramData`
- formal `PlanningContextData`
- pure `PlanCompiler`
- preserve current `TrainingSessionCompiler` as orchestration shell calling the pure compiler

Verification:

- a new DTO-first compiler test suite
- old compiler feature tests remain green as parity tests

Risk:

- metric/block context parity
- hidden reliance on Eloquent relations in strategy resolution

Status:

- completed on 2026-05-15 in code

### Slice 5 — Replace Remaining Preview/Runtime Divergence

Scope:

- preview/editor grids consume the same pure compiler DTOs
- reduce bespoke preview logic
- unscheduled preview remains compile-time only
- scheduled views always read persisted snapshots

Verification:

- grid feature tests
- new DTO parity tests between preview compile output and scheduled compile output for future sessions

Risk:

- edit fanout semantics
- override highlighting
- session grouping display state

Status:

- in progress on 2026-05-15
- `ProgramPreviewBuilder` now routes preview session compilation through `PlanCompiler` instead of bespoke per-exercise `ResolvedPlannedSessionBuilder` calls
- `ExercisePreviewBuilder` now routes preview session value/provenance compilation through `PlanCompiler` instead of instantiating `ResolvedPlannedSessionBuilder` directly
- added `ProgramPreviewBuilderTest` coverage for:
  - exact concrete preview values from fixed authoring + planning inputs
  - omission of metric-dependent preview exercises when required inputs are missing
- added a stronger preview-vs-scheduled oracle for automatic progression:
  - the same session timeline and metric inputs now assert exact preview weights and exact materialized slot weights together
- existing preview/grid coverage remains green after the swap:
  - `ExercisePreviewBuilderTest`
  - `ProgramEditorPreviewTest`
  - `PlanExerciseGridMixedSessionSaveTest`
- targeted verification is green:
  - `ProgramPreviewBuilderTest`
  - `PlanCompilerTest`
  - `TrainingSessionCompilerTest`
  - `ProgramDetailsTest`
- most remaining Slice 5 work is now about trimming redundant preview-only helper logic and tightening oracle coverage rather than replacing major parallel compiler branches

### Slice 6 — Canonical Storage Contract And Migration Design

Scope:

- decide whether the existing materialized slot tables remain the canonical planned snapshot store or whether dedicated `planned_*` tables are introduced
- document the exact mapping from current rows/tables into the target runtime contract
- define identity preservation rules for sessions, exercises, sets, and values
- define how revisions remain audit-only under the target model

Status:

- started on 2026-05-15 with read-only parity tooling
- added `ScheduledTrainingSnapshotAuditService` to compare stored materialized slot rows against a fresh compile at the persisted snapshot-column level
- added `training:snapshot-audit` as the first migration/audit command surface
- added verification for both:
  - matching stored snapshots
  - intentional planned-value drift detection

Verification:

- schema/contract tests
- mapping fixtures proving current slot rows translate cleanly to canonical snapshot DTOs

Risk:

- introducing a target schema that forces unnecessary rewrites or identity churn

### Slice 7 — Backfill And Parity Audit Tooling

Scope:

- build deterministic backfill commands/services
- build parity audit commands/services
- classify mismatches instead of silently rewriting ambiguous history
- produce actionable reports for remediation

Verification:

- backfill command tests
- parity audit tests
- fixture-driven classification tests for historical/future/ambiguous sessions

Risk:

- accidental recomputation of historical truth
- under-reporting mismatches between old and new reads

Status:

- started on 2026-05-15 and partially completed
- added slot classification through `ScheduledTrainingSnapshotClassifier`:
  - `locked_past`
  - `future_open`
  - `ambiguous_boundary`
- extended `training:snapshot-audit` to report classification and classification counts
- added `training:snapshot-backfill`:
  - dry-run by default
  - scoped by training program, athlete, date range, or explicit slot ids
  - rematerializes only eligible `future_open` mismatches when `--force` is used
- added command and service coverage for:
  - matching vs mismatched audits
  - dry-run backfill
  - forced future-open backfill
  - locked-past protection during backfill

### Slice 8 — Read/Write Cutover

Scope:

- move scheduled reads onto the canonical runtime path everywhere
- move scheduled writes/projection onto the canonical runtime contract
- preserve fallback/repair pathways during the cutover window

Verification:

- focused end-to-end tests around coach scheduled view, athlete view, rebuilds, and actual recording
- shadow-read parity checks during rollout

Risk:

- partial cutover leaving some screens on the old path
- writes hitting old and new pathways inconsistently

Status:

- started on 2026-05-15 with shadow-compare tooling
- added `training:snapshot-compare`:
  - future-open cutover parity by default
  - optional inclusion of locked/ambiguous sessions
  - scoped comparison over program, athlete, date range, or explicit slot ids
- scheduled reads are already unified on the snapshot runtime in the athlete dashboard and coach scheduled grid paths landed in earlier slices
- the remaining cutover work is now primarily operational:
  - use compare/audit output against real data
  - decide when to retire any remaining legacy-only scheduled-runtime branches

### Slice 9 — Audit / Repair Simplification And Legacy Cleanup

Scope:

- formalize revision service roles
- ensure revision tables are audit only
- provide intentional repair/rematerialize tools where needed
- remove obsolete scheduled-runtime recomputation paths
- deprecate redundant tables/columns only after cutover is stable

Verification:

- revision service tests
- reset/rematerialization command tests
- legacy-path removal regression tests

Risk:

- accidental regression in provenance expectations
- removing a legacy dependency before all reads/writes are off it

Status:

- started on 2026-05-15 with explicit repair tooling
- added `training:snapshot-repair`:
  - requires explicit `--slot-id`
  - requires `--force`
  - can intentionally rematerialize locked or ambiguous sessions
- split materializer controls so we can independently:
  - force normal future rebuilds
  - ignore the compiled-version shortcut for drift repair
  - bypass the historical immutability guard only for explicit repair flows
- verified targeted locked-past repair without weakening the default historical protections used by normal rebuild paths

## Data Migration Strategy

The refactor needs a formal migration track. We should not rely on ad hoc data fixes after the runtime is simplified.

### Migration Principles

1. Do not recompute history into correctness.
2. Preserve the frozen planned snapshot for locked/past sessions unless a deliberate repair tool is invoked.
3. Recompile future sessions from current authoring truth.
4. Treat revisions as audit evidence, not runtime reconstruction inputs.
5. Prefer identity preservation over table churn wherever practical.

### Recommended Storage Contract

The preferred near-term approach is:

- keep the existing materialized slot tables as the canonical scheduled planned snapshot store
- treat them as the runtime contract, not just a cache
- keep actual values and statuses alongside them as they exist today
- move toward dedicated `planned_*` tables only if the current slot shape proves structurally insufficient after the runtime unification is complete

This is safer because it:

- avoids a full historical table migration up front
- preserves existing foreign keys and edit flows
- lets us unify reads/writes before introducing schema churn

### Data Classes To Preserve

We need explicit preservation rules for:

- planned scheduled snapshot rows
  - `training_program_slots`
  - `training_program_slot_exercises`
  - `training_program_slot_sets`
  - `training_program_slot_set_values`
- actual recorded values and modification flags
- exercise/set/session statuses
- block associations and scheduling dates
- revision batches and revision rows

### Session Classification Rules

Every scheduled session should be classified before migration or backfill behavior is chosen.

1. `locked_past`
   - read frozen planned snapshot as truth
   - do not rewrite automatically

2. `future_open`
   - eligible for recompilation from current authoring truth
   - canonical target should match current compiler output

3. `ambiguous_boundary`
   - near-present or partially edited sessions where lock state, actuals, or timing make automatic rewrite risky
   - report for review or require explicit repair mode

### Backfill / Translation Rules

The backfill layer should support two deterministic translations:

1. current materialized slot rows -> canonical scheduled snapshot DTOs
2. current authoring inputs + planning context -> canonical compiled planned DTOs

These translators let us compare:

- what is frozen in storage
- what the compiler would produce now
- whether the session should be preserved, rebuilt, or flagged

### Required Migration Commands

We should add explicit commands/services for:

- `training:snapshot-audit`
  - scan scheduled sessions and classify them
  - report missing rows, inconsistent status state, malformed value typing, and render parity mismatches

- `training:snapshot-backfill`
  - backfill canonical snapshot shape where missing
  - support `--future-only`, `--program=`, `--user=`, `--from=`, `--to=`

- `training:snapshot-compare`
  - compare old scheduled rendering vs new canonical snapshot rendering
  - compare compiler output vs stored future snapshots

- `training:snapshot-repair`
  - intentionally rematerialize only scoped sessions
  - require explicit targeting for locked/past sessions

### Parity Audit Requirements

Before cutover, the audit tooling should verify:

1. athlete scheduled render old path vs canonical snapshot path
2. coach scheduled render old path vs canonical snapshot path
3. stored future snapshot vs current compiler output
4. actual values and modification flags survive unchanged
5. revision writes still occur with equivalent semantics

Any mismatch should be:

- classified
- counted
- exported with concrete identifiers
- never silently repaired during the audit phase

### Cutover Strategy

The safest cutover is staged:

1. shadow reads
   - build canonical snapshot runtime output in parallel
   - log/report mismatches without changing user-visible behavior

2. read cutover
   - athlete and coach scheduled views read the canonical runtime path
   - old path remains as fallback for scoped repair/debugging

3. write cutover
   - rebuild/materialization services project only to the canonical runtime contract
   - actual recording remains unchanged except for using the same canonical read semantics

4. cleanup
   - remove obsolete scheduled rendering paths
   - retire redundant storage only after parity has been stable

### Rollback Strategy

Every cutover phase should have a rollback path.

- If shadow-read parity fails, stay on old reads and fix the mapper/compiler.
- If read cutover fails, switch scheduled rendering back to the legacy path while preserving newly collected audit data.
- If write cutover fails, pause rebuild/backfill jobs and restore the previous projector path before attempting cleanup.

### Migration Test Strategy

Migration work needs its own tests, separate from runtime feature tests.

1. fixture tests for session classification
2. translator tests for current rows -> canonical snapshot DTOs
3. backfill command tests
4. parity audit tests
5. cutover smoke tests
6. rollback-path tests for scoped failures

## DTO-First Test Strategy

### Primary New Test Layers

1. `tests/Unit/Training/Compiler/*`
   - authoring inputs + planning context -> planned sessions

2. `tests/Unit/Training/Snapshots/*`
   - slot rows -> canonical scheduled snapshot DTO

3. `tests/Unit/Training/Merge/*`
   - planned snapshot + actuals -> merged runtime DTO

4. `tests/Unit/Training/Patches/*`
   - defaults + program patch + athlete patch -> effective authoring input

### What Stays As Feature / Integration Tests

- rebuild triggers
- projection persistence shape
- Livewire wiring
- audit table writes

## Test Hardening And Pruning Strategy

The goal is not to keep every existing test forever. The goal is to end up with a smaller, more trustworthy suite.

### Keep

These tests should stay because they validate externally meaningful behavior or system boundaries:

- oracle-style state transition tests with explicit expected values
- DTO compiler tests with fixed inputs and handwritten outputs
- materialization/projection tests that prove persistence shape
- rebuild trigger tests
- audit/revision write tests
- a thin set of end-to-end Livewire wiring tests

### Downgrade In Importance

These tests are still useful during migration, but should stop being the primary source of confidence:

- parity tests that only prove two runtime paths agree
- tests that compare one builder against another builder that shares the same assumptions
- tests that assert a UI shape without proving the underlying planned values are correct

### Candidate Tests To Remove Or Collapse Later

After stronger oracle coverage exists, we should review and potentially remove or merge:

- duplicate Livewire tests that differ only in minor presentation details
- repeated parity tests once a single shared runtime path exists
- tests that reconstruct their expected values by calling the same preview/compiler chain as the code under test
- overly broad mixed-session tests that overlap heavily with smaller, clearer oracle tests

### Retirement Rules

We should only remove an existing test if all of the following are true:

1. its business intent is covered by a stronger oracle or DTO-level test
2. it is not the only test exercising a system boundary or integration trigger
3. removing it makes the suite easier to understand, not just shorter

### Recommended Cleanup Sequence

1. add oracle coverage for defaults, overrides, historical freeze, and future rebuild boundaries
2. tag or group existing tests by purpose:
   - oracle
   - parity
   - integration
   - UI wiring
3. identify duplicated assertions across `PlanExerciseGrid*`, `ProgramDetails*`, and materialization tests
4. collapse duplicate parity tests once scheduled reads share one runtime path everywhere
5. keep one representative end-to-end test per boundary and delete the rest only after repeated green runs

## Risk Register

### Risk 1 — Schedule mutations alter progression for earlier future sessions

Example:
- adding the second or third session in a week can change the first session's progression anchor

Mitigation:
- rebuild full future athlete/program scope
- DTO test around block session sequence

### Risk 2 — Historical snapshots silently recompute

Mitigation:
- locked session views read persisted planned snapshot first
- explicit past-slot freeze tests

### Risk 3 — Coach scheduled plan and athlete view drift again

Mitigation:
- one shared scheduled snapshot builder
- one parity test asserting equality across both screens

### Risk 4 — Revision tables become implicit runtime dependencies

Mitigation:
- ban reconstruction from revision tables in runtime code
- use revisions only for audit assertions

## Delivered On 2026-05-15

The following concrete fixes landed as Slice 1:

1. `TrainingSessionCompiler` now resolves progression session context from the effective category block timeline instead of a narrow per-week guess.
2. `TrainingProgramSlotObserver` + `TrainingSessionRebuildService` now rebuild sibling future slots when schedule shape changes.
3. `PlanExerciseGrid` now prefers frozen materialized planned snapshots for locked sessions in planned mode.

The following unification work landed as Slice 2:

4. Added canonical scheduled snapshot DTOs:
   - `ScheduledSessionSnapshotData`
   - `ScheduledExerciseSnapshotData`
   - `ScheduledSetSnapshotData`
   - `ScheduledValueSnapshotData`
5. Added `ScheduledSessionSnapshotBuilder` as the canonical scheduled runtime read-model builder from materialized slot rows.
6. Switched athlete `ProgramDetails` scheduled rendering to read from the snapshot builder.
7. Simplified `ProgramDetailsExerciseViewBuilder` so both the legacy slot-model entrypoint and the new scheduled snapshot entrypoint render through the same snapshot-based implementation.
8. Carried setting config metadata into the snapshot layer so formatting and labels remain consistent without reaching back into Eloquent-only config objects.

The following unification work landed as Slice 3:

9. Routed coach scheduled plan/actual reads in `PlanExerciseGrid` onto `ScheduledSessionSnapshotBuilder` for:
   - locked planned snapshot reads
   - actual-value table reads
   - coach/athlete scheduled-value parity checks
10. Kept coach scheduled writes on the existing slot-model path for now, so read unification happened without broad write-path churn.

The following compiler boundary work landed as Slice 4:

11. Added DTO compiler inputs:
   - `AuthoringExerciseData`
   - `AuthoringProgramData`
   - `PlanningContextData`
12. Added pure `PlanCompiler` to transform authoring DTOs + planning context DTOs into `ResolvedPlannedSession`.
13. Converted `TrainingSessionCompiler` into an orchestration shell that gathers DB-backed context, builds DTO inputs, delegates to `PlanCompiler`, and maps the result into compiled slot values.
14. Added direct DTO-first compiler tests to verify the new boundary independently of slot/materialization flows.

Verification completed:

- `tests/Unit/Training/PlanCompilerTest.php`
- `tests/Feature/Livewire/Athlete/ProgramDetailsTest.php`
- `tests/Feature/Training/TrainingSessionCompilerTest.php`
- `tests/Feature/Training/TrainingSessionMaterializerTest.php`
- `tests/Feature/Livewire/Training/PlanExerciseGridActualValuesTest.php`

Note:

- a later rerun of the full `ProgramDetailsTest` edit-path subset in this local workspace was blocked by an unrelated Blade syntax error in `coda-packages/form-kit/resources/views/components/form/field.blade.php`
- the scheduled rendering parity path itself remained verified via `ProgramDetailsExerciseViewBuilder` parity coverage and the green coach scheduled view tests

## Recommended Next Implementation Step

Implement Slice 5 next:

- route remaining preview/editor compilation through the DTO compiler boundary where sensible
- continue narrowing the distinction between unscheduled preview logic and scheduled planning logic
- keep migration work explicit in Slices 6-9 rather than folding it into feature refactors
