# Data Model

This document describes every data concept in the application, how they relate, and how configuration flows and gets overridden at multiple levels.

---

## Table of Contents

1. [Users & Roles](#1-users--roles)
2. [Tags](#2-tags)
3. [Exercises](#3-exercises)
4. [Exercise Settings](#4-exercise-settings)
5. [Exercise Programs](#5-exercise-programs)
6. [Exercise Plans](#6-exercise-plans)
7. [Training Programs (Assigning to Groups)](#7-training-programs-assigning-to-groups)
8. [Training Blocks (Calendar Periods)](#8-training-blocks-calendar-periods)
9. [The Override Chain](#9-the-override-chain)
10. [Grid Overrides](#10-grid-overrides)
11. [Athlete Metrics](#11-athlete-metrics)
12. [Ownership](#12-ownership)
13. [Full Data Flow Summary](#13-full-data-flow-summary)

---

## 1. Users & Roles

Every person in the system is a **User**. Each user has a `type` that determines their role:

| Type       | Description                                      |
|------------|--------------------------------------------------|
| `coach`    | Creates exercises, programs, and manages athletes |
| `athlete`  | Receives assigned programs and records metrics    |
| `admin`    | Full system access                                |

Users have standard profile fields: forename, surname, email, phone, gender, date of birth, and a display color.

### User Groups

Athletes are organized into **User Groups**. A group is simply a named collection of users with a sort order for each member.

```
UserGroup "Team Alpha"
  ├── Athlete A  (sort: 0)
  ├── Athlete B  (sort: 1)
  └── Athlete C  (sort: 2)
```

Groups are the primary unit for assigning training programs. When a coach builds a training plan, they assign it to a group. Every athlete in that group receives the program.

---

## 2. Tags

Tags are a flexible, polymorphic labeling system used throughout the app. Every tag has a **scope** that defines what kind of thing it labels. Tags can also be hierarchical (parent/child).

| Scope                    | Used On          | Purpose                            |
|--------------------------|------------------|------------------------------------|
| `exercise_equipment`     | Exercises        | Equipment needed (barbell, band)   |
| `exercise_modifiers`     | Exercises        | Variations (tempo, paused, banded) |
| `exercise_internal`      | Exercises        | Internal coach-only labels         |
| `program_internal`       | Programs         | Internal coach-only labels         |
| `athlete_internal`       | Athletes         | Internal coach-only labels         |
| `athlete_group_internal` | Groups           | Internal coach-only labels         |

Tags are also used as **exercise categories**. The category is a hierarchical tag tree. An exercise belongs to one category (e.g. "Strength > Upper Body > Chest"). Categories have a name, short name, color, and sort order within their parent.

---

## 3. Exercises

An **Exercise** is the fundamental building block. It represents a single movement or activity (e.g. "Back Squat", "5km Run").

### What an exercise contains

| Field          | Description                                           |
|----------------|-------------------------------------------------------|
| `name`         | Display name                                          |
| `category`     | Tag reference (e.g. "Strength > Legs")                |
| `equipment`    | Tags (e.g. barbell, dumbbells)                        |
| `modifiers`    | Tags (e.g. paused, banded)                            |
| `video_url`    | Optional instructional video                          |
| `instructions` | Free-text instructions                                |
| `config`       | The exercise configuration (see next section)         |
| `template`     | Optional link to an Exercise Template                 |
| `external`     | Optional link to an External Exercise source          |
| `photos`       | Media attachments                                     |

### Exercise Templates

An **Exercise Template** is a reusable configuration preset. It stores the same `config` structure as an exercise. When an exercise is linked to a template, the template's config serves as its starting point.

### External Exercises

An **Exercise External** represents an exercise imported from an outside source. It stores the source identifier, name, category, video, and tag references. When imported, it becomes a full Exercise in the system.

---

## 4. Exercise Settings

The `config` on an exercise is where the real complexity lives. It defines what values a coach wants to track for that exercise and how those values behave.

### The settings list

The `settings` array on a config determines which settings are active. Each active setting gets its own configuration object:

| Setting         | What it tracks                  | Example default |
|-----------------|---------------------------------|-----------------|
| `sets`          | Number of sets (always active)  | 3 sets          |
| `reps`          | Repetitions per set             | 10 reps         |
| `weight`        | Load per set                    | 50 kg           |
| `tempo`         | Strength tempo (e.g. "3010")    | "3010"          |
| `rest`          | Rest between sets (seconds)     | 90s             |
| `distance`      | Distance (meters or km)         | 1000m           |
| `duration`      | Time (seconds, minutes, mm:ss)  | 60s             |
| `pace`          | Speed (mm:ss format)            | "5:00"          |
| `watts`         | Power output                    | 200w            |
| `heartRate`     | Heart rate target               | 140 bpm         |
| `heartRateZone` | HR zone (0-4)                   | Zone 2          |

### How settings apply

Each setting has an `applyPer` mode that controls its granularity:

- **`session`** (default): The value can be different for every cell in the grid (week x set). For example, reps might be 10 in week 1 set 1, but 8 in week 3 set 2.
- **`week`**: The value applies to an entire week. For example, rest might be 90s for all of week 1, then 120s for week 2.

### Automatic modes

Some settings have an `automatic` mode where the system calculates values instead of the coach entering them manually:

- **Reps (automatic)**: Starts at a default and decrements across weeks. Uses a "paired" pattern where the first half of sets gets higher reps than the second half.
- **Weight (automatic)**: Uses the athlete's 1RM measurement and a target goal percentage to calculate appropriate weights for each week, progressing toward the target.
- **Heart Rate (automatic)**: Uses the athlete's max heart rate and anaerobic threshold to calculate target heart rates based on training zones (Norwegian model for biking or jogging).

### Sets and deload

Sets has a special `deload` option. When enabled, the system automatically reduces sets on either odd or even weeks to give athletes a lighter week.

### The preview grid

Every exercise config also has a `preview` setting that controls how the exercise grid is rendered: how many weeks to show and how many sessions per week. This determines the dimensions of the preview grid that coaches interact with.

---

## 5. Exercise Programs

An **Exercise Program** is a collection of exercises grouped together for a training session (e.g. "Monday Upper Body", "Thursday Intervals").

### What a program contains

| Field               | Description                                       |
|---------------------|---------------------------------------------------|
| `name`              | Display name                                      |
| `exerciseCategory`  | Tag for the program's category (e.g. "Strength")  |
| `exercises`         | Ordered list of exercises in this program          |
| `config`            | Program-level configuration and overrides          |
| `warmUpProgram`     | Optional link to another program used as warm-up   |
| `warmDownProgram`   | Optional link to another program used as cool-down |
| `parent`            | Polymorphic link to either an ExercisePlan or TrainingProgram |

### The exercise list (pivot)

Exercises are attached to a program through a pivot table. Each entry has:

- **sort**: The display order within the program
- **group**: A single character (A, B, C...) for superset grouping. Exercises with the same group letter are performed together.

```
Program "Monday Upper Body"
  ├── Bench Press        (sort: 0, group: null)
  ├── Incline DB Press   (sort: 1, group: A)
  ├── Cable Fly          (sort: 2, group: A)    ← superset with Incline DB Press
  └── Tricep Pushdown    (sort: 3, group: null)
```

### Program config

A program carries its own `ExercisePlanConfig` — this is where overrides live. More on this in the [Override Chain](#9-the-override-chain) section.

---

## 6. Exercise Plans

An **Exercise Plan** is a template that a coach designs before assigning it to any group. Think of it as a blueprint.

### What a plan contains

| Field      | Description                                         |
|------------|-----------------------------------------------------|
| `name`     | Display name                                        |
| `config`   | Schedule, targets, and exercise overrides            |
| `programs` | The Exercise Programs that belong to this plan       |

### Schedule config

The plan's config includes a `schedule` that defines how programs are laid out across weeks:

```
Schedule (5 weeks)
  Week 0 (template week)
    ├── Monday AM:  [Program 1]
    ├── Wednesday AM: [Program 2]
    └── Friday AM:  [Program 1, Program 3]
  Week 1 → linked to Week 0 (same layout)
  Week 2 → linked to Week 0
  Week 3 → linked to Week 0
  Week 4 → linked to Week 0
```

Each week has up to 7 days with 2 slots each (AM/PM). Each slot can hold one or more program IDs.

Weeks can be **linked** to another week, meaning they follow the same program layout. This lets coaches define a pattern once and repeat it, while still being able to unlink specific weeks for variation.

### Target config

The plan also stores a `target` that drives automatic weight calculations:

| Field            | Description                                      |
|------------------|--------------------------------------------------|
| `measuredReps`   | How many reps the athlete's measurement was at   |
| `measuredWeight` | The weight they lifted for those reps            |
| `targetGoal`     | Percentage increase to aim for                   |

---

## 7. Training Programs (Assigning to Groups)

When a coach assigns a plan to a group of athletes, the system creates **Training Programs**. This is where the plan stops being a template and becomes a live, assigned piece of training.

### The import process

```
ExercisePlan (template)
  └── contains Programs A, B, C
        │
        ▼  importFromPlan()
TrainingProgram (group: "Team Alpha")
  └── links to ExerciseProgram A' (duplicate of A)
TrainingProgram (group: "Team Alpha")
  └── links to ExerciseProgram B' (duplicate of B)
TrainingProgram (group: "Team Alpha")
  └── links to ExerciseProgram C' (duplicate of C)
```

Key points:

1. Each Exercise Program is **duplicated**. The original stays untouched in the plan. The copy is what the group actually uses.
2. When duplicating, any existing grid overrides are saved as `baselineGridOverrides` on the copy. This preserves what the coach set at the plan level as a reference point.
3. The duplicate's `parent` is set to the new TrainingProgram, severing its link to the original plan.
4. Each TrainingProgram links one group to one program, with a sort order.

Coaches can also import a single program or even a single exercise directly into a group, bypassing the plan entirely.

### Training Program Slots

A **Training Program Slot** is a specific scheduled instance of a program for a specific athlete on a specific date/time.

```
TrainingProgram (group: "Team Alpha", program: "Monday Upper Body")
  ├── Slot: Athlete A, Monday 2024-01-08 09:00
  ├── Slot: Athlete B, Monday 2024-01-08 09:00
  └── Slot: Athlete C, Monday 2024-01-08 09:00
```

Slots are unique per (training_program, user, datetime). They represent the actual calendar entries that athletes see.

---

## 8. Training Blocks (Calendar Periods)

**Training Program Blocks** are date-range annotations on the calendar. They mark periods of time with specific training focuses, notes, or category targets.

### Block types

| Type       | Purpose                                    | Has Config |
|------------|--------------------------------------------|------------|
| `category` | Marks a training category focus period     | Yes        |
| `note`     | Free-text note on the calendar             | No         |
| `focus`    | General training focus period              | No         |

### Category blocks

Category blocks are the most important type. They mark a period where a specific exercise category is the focus (e.g. "Hypertrophy phase for Chest, Jan 1 - Feb 15"). They carry a `config` with:

- **goal**: A target percentage (e.g. 10% improvement)
- **autoRecord1rm**: Whether to automatically record 1RM measurements during this block

### Athlete overrides for blocks

Blocks support a parent/child override pattern for individual athletes:

```
Block (group-level, no user_id, no parent_id)
  "Hypertrophy Block, Jan 1 - Feb 15"
    │
    └── Override (user_id: Athlete A, parent_id: ↑)
        "Hypertrophy Block, Jan 8 - Feb 22"   ← different dates for this athlete
```

- A block with no `user_id` and no `parent_id` is a **group-level block** that applies to everyone.
- A block with a `user_id` and a `parent_id` pointing to a group block is an **athlete override**. It can change the dates, note, active status, or any other field for just that one athlete.
- An override with `active: false` means the block is disabled for that athlete.

The system resolves which block to show by checking for an athlete-specific override first, falling back to the group block if none exists.

---

## 9. The Override Chain

This is the central concept of the data model. Exercise configuration flows through multiple layers, and each layer can override the one before it.

### The three layers

```
Layer 1: Exercise Config (base)
    ↓
Layer 2: Program Overrides (plan-level defaults)
    ↓
Layer 3: User Overrides (athlete-specific)
    ↓
  = Effective Config (what the athlete actually sees)
```

### Layer 1: Exercise Config

This is the configuration stored directly on the Exercise model. It defines the default settings, their modes, and their default values. Every exercise has this.

### Layer 2: Program Overrides (ExerciseOverrides)

When an exercise lives inside a program, the program can override any aspect of that exercise's config. These overrides are stored in the program's `config.exercises` map, keyed by exercise ID.

An override can change:
- Which settings are enabled (add or remove settings)
- Any setting's configuration (change reps from 10 to 8, switch weight mode from manual to automatic)
- Grid cell values (change week 2, set 3 from 80kg to 85kg)
- Whether the exercise is disabled entirely

If no override exists for an exercise, the base config is used as-is.

### Layer 3: User Overrides (ExerciseOverrides)

When a coach wants to customize an exercise for a specific athlete within a program, they create a user-level override. These are stored in the program's `config.userExercises` map, keyed by user ID then exercise ID.

User overrides have the exact same shape as program overrides. They can change anything the program override can.

### How resolution works

The system merges these layers top-to-bottom:

1. Start with the Exercise's base config
2. If the program has overrides for this exercise, apply them on top (replacing any matching settings)
3. If there are user-specific overrides for this athlete + exercise, apply those on top

For settings (reps, weight, etc.): a non-null override **replaces** the layer below entirely.
For grid overrides: cell-level data is **merged** (see next section).
For the disabled flag: the most specific non-null value wins (user > program).

### Example

```
Exercise "Back Squat"
  Base config: reps=10, weight=manual(50kg), settings=[reps, weight, tempo]

Program "Monday Strength" overrides for Back Squat:
  reps=8, weight=automatic(1RM-based)
  → Now: reps=8, weight=automatic, settings=[reps, weight, tempo]

User override for Athlete A on Back Squat:
  reps=6
  → Athlete A sees: reps=6, weight=automatic, settings=[reps, weight, tempo]
  → Athlete B sees: reps=8, weight=automatic, settings=[reps, weight, tempo]
  → (Athlete B has no user override, so they get the program defaults)
```

### Where overrides are stored

All overrides live in the Exercise Program's `config` JSON column as an `ExercisePlanConfig`:

```
ExerciseProgram.config = {
    "schedule": { ... },
    "target": { ... },
    "weeks": 5,
    "exercises": {
        "42": { <-- exercise ID 42, program-level overrides
            "settings": ["reps", "weight"],
            "reps": { "mode": "manual", "default": 8 },
            "gridOverrides": { "cells": [...], "weeks": [...] }
        }
    },
    "userExercises": {
        "7": {  <-- user ID 7
            "42": { <-- exercise ID 42, athlete-level overrides
                "reps": { "mode": "manual", "default": 6 },
                "gridOverrides": { "cells": [...], "weeks": [...] }
            }
        }
    }
}
```

---

## 10. Grid Overrides

The exercise preview grid is a 2D table: weeks across the top, sets down the side. Each cell holds the values for that specific week + set combination.

Settings with `applyPer: session` have values at every cell. Settings with `applyPer: week` have one value per week column.

### How the grid gets its values

1. **Strategy phase**: The system runs calculation strategies in order (sets → reps → weight → heart rate zones → heart rate). Each strategy fills in its portion of the grid based on the setting's configuration and mode.
2. **Override phase**: Any grid overrides are applied on top of the calculated values.

### Cell overrides

A cell override targets a specific (week, session, set) coordinate and replaces field values:

```json
{
    "week": 2,
    "session": 0,
    "set": 1,
    "data": { "reps": 6, "weight": 85 }
}
```

This means: "In week 2, session 0 (AM), set 1, the reps should be 6 and weight should be 85, regardless of what the strategy calculated."

### Week overrides

A week override targets an entire week and replaces week-level field values:

```json
{
    "week": 3,
    "data": { "rest": 120 }
}
```

This means: "In week 3, rest should be 120s for all sets."

### How overrides merge across layers

When the system resolves the effective config, grid overrides from all three layers are merged:

1. Start with the exercise's base `overrides` (cells + weeks)
2. Merge in the program-level `gridOverrides`
3. Merge in the user-level `gridOverrides`

For cells, the merge key is `"week-session-set"`. If two layers override the same cell, their `data` maps are merged with the later layer winning on conflicts.

For weeks, the merge key is `"week"`. Same merge behavior.

### Baseline grid overrides

When a program is duplicated (during import to a group), any existing grid overrides are copied into `baselineGridOverrides`. This preserves the original plan-level overrides as a reference, so the UI can show what has changed since the program was assigned.

---

## 11. Athlete Metrics

Athletes have measurable data points that feed back into the training system.

### Metric types

| Metric      | What it measures                        | Derived values        |
|-------------|----------------------------------------|-----------------------|
| `oneRepMax` | Measured reps + weight for an exercise | Estimated 1RM         |
| `heartRate` | Max heart rate + anaerobic threshold   | HR zones, thresholds  |

### How metrics are stored

A **Metric Submission** is a single recording event. It has:

- The **athlete** being measured
- The **metric type** (1RM or heart rate)
- Who **recorded** it (the coach)
- When it was **recorded**
- A polymorphic **owner** that links it to either a User (manual entry) or a TrainingProgramBlock (projected/planned target)

Each submission has one or more **Metric Values** — key/value pairs that store both the raw inputs and any derived calculations.

For a 1RM submission:
- Raw: `measuredReps=5`, `measuredWeight=100`
- Derived: `estimated1RM=116.7` (calculated using a formula)

For a heart rate submission:
- Raw: `heartRate=190`, `anaerobicThreshold=82`

### How metrics feed back into training

- **1RM metrics** are used by the automatic weight mode. When weight is set to automatic, the system looks up the athlete's latest 1RM for the relevant exercise and uses it with the target goal to calculate progressive weights across weeks.
- **Heart rate metrics** are used by the automatic heart rate mode. The system uses the athlete's max HR and anaerobic threshold percentage to calculate zone-based intensity targets.
- **Category blocks** with `autoRecord1rm: true` can automatically create projected 1RM submissions tied to the block, setting targets for athletes to work toward.

### Manual vs. Projected

Metric submissions are scoped as either:
- **Manual**: Recorded by a coach against a User (real-world measurement)
- **Projected**: Created against a TrainingProgramBlock (planned target for a training period)

---

## 12. Ownership

Every model in the system uses the `HasOwner` trait, which adds an `owner_id` foreign key pointing to a User. This represents the coach who created or manages that record.

Ownership is used for:
- **Data scoping**: Coaches only see their own exercises, programs, athletes, etc.
- **Multi-tenancy**: Multiple coaches can use the system independently.
- **Audit trail**: Knowing who created what.

---

## 13. Full Data Flow Summary

Here is how everything connects, from bottom to top:

```
                         ┌─────────────┐
                         │    USERS    │
                         ├─────────────┤
                         │ Coach       │──── owns everything below
                         │ Athlete     │──── receives training
                         │ Admin       │──── full access
                         └──────┬──────┘
                                │
                    ┌───────────┴───────────┐
                    │                       │
             ┌──────┴──────┐         ┌──────┴──────┐
             │ User Groups │         │   Metrics   │
             │             │         │ (1RM, HR)   │
             └──────┬──────┘         └──────┬──────┘
                    │                       │
                    │              feeds into│automatic
                    │              weight/HR│calculations
                    │                       │
        ┌───────────┴───────────┐           │
        │                       │           │
 ┌──────┴──────┐  ┌─────────────┴─┐        │
 │  Training   │  │   Training    │        │
 │  Programs   │  │   Blocks      │        │
 │ (assigned)  │  │ (calendar)    │        │
 └──────┬──────┘  └───────────────┘        │
        │                                   │
        │ each links to a                   │
        │ duplicated program                │
        │                                   │
 ┌──────┴──────────────────────────┐        │
 │  Exercise Program (duplicate)   │        │
 │  ┌────────────────────────────┐ │        │
 │  │ config.exercises           │◄├────────┘
 │  │   (program-level overrides)│ │
 │  │ config.userExercises       │ │
 │  │   (athlete-level overrides)│ │
 │  └────────────────────────────┘ │
 └──────┬──────────────────────────┘
        │ contains
        │
 ┌──────┴──────┐
 │  Exercises  │
 │  (base      │
 │   config)   │
 └─────────────┘
```

### The lifecycle of training data

1. **Coach creates exercises** with base configurations (settings, defaults, modes).
2. **Coach groups exercises into programs** (ordered, with superset groupings).
3. **Coach creates a plan** arranging programs into a weekly schedule.
4. **Coach assigns the plan to a group**. The system duplicates each program so the group gets its own copy.
5. **Coach customizes at the program level** — overriding settings or grid values for specific exercises. These apply to all athletes in the group.
6. **Coach customizes at the athlete level** — overriding settings or grid values for a specific athlete on a specific exercise.
7. **Training slots are created** — putting specific athletes on specific dates/times for each program.
8. **Blocks are added to the calendar** — marking training phases, focus periods, and category targets.
9. **Athletes view their training** — the system resolves the effective config by merging base → program overrides → athlete overrides.
10. **Metrics are recorded** — 1RM and heart rate measurements that feed back into automatic calculations for the next training cycle.
