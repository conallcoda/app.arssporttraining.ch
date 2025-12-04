# Training Plan Calculator

This guide explains how athlete training plans are generated, from test data through to a complete multi-week programme.

---

## Core Concepts

- **One Rep Max (1RM)**: The maximum weight an athlete can lift for a single repetition. This is the foundation for all training calculations.
- **Training Block**: A complete training cycle spanning multiple weeks (e.g., 5 weeks).
- **Week**: A unit within a block containing multiple training sessions.
- **Session**: A single workout day within a week.
- **Set**: An individual effort within a session, defined by repetitions and weight.

---

## From Test Data to Training Weights

Athletes are tested on the **Back Squat** to establish their baseline strength. From this test (weight lifted × repetitions), the system calculates the athlete's 1RM.

### Exercise Modifiers

Each exercise has a **modifier** that expresses its relationship to the Back Squat:

| Exercise | Modifier |
|----------|----------|
| Back Squat | 100% |
| Front Squat | 85% |

When creating a plan for a specific exercise, the system derives that exercise's 1RM by applying the modifier to the athlete's Back Squat 1RM. This means a single test provides the foundation for training plans across multiple exercises.

---

## How Plans Are Built

Every training plan starts with two values:

1. **Starting 1RM**: The athlete's current derived strength for the chosen exercise
2. **Target 1RM**: The goal strength (e.g., a 10% increase)

The system then uses **[Actions](#actions)** to construct the plan step-by-step, transforming an empty block into a complete programme. **[Rules](#rules)** apply automatic adjustments throughout this process.

---

## Training Strategies

A strategy determines the overall philosophy for how intensity progresses across the training block.

### Fixed Decrement

Intensity increases using discrete weight steps that vary based on the athlete's strength level. Stronger athletes use larger jumps. This creates natural plateaus where multiple sets or weeks share the same intensity before stepping up. See [Fixed Decrement Strategy](#fixed-decrement-strategy) for details.

### Linear Progression

Intensity increases smoothly and evenly from start to finish. Each week (or set) receives a slightly higher target than the last, creating a gradual ramp toward the goal. See [Linear Progression Strategy](#linear-progression-strategy) for details.

---

## Customising Your Plan

### Actions

Actions are the building blocks that shape your plan. Each action performs one specific transformation:

- [Create Empty Block](#create-empty-block) - Establishes the block structure
- [Set Block Start](#set-block-start) - Sets the starting 1RM baseline
- [Set Block Target](#set-block-target) - Sets the end goal
- [Set Week Targets](#set-week-targets-fixed-decrement) - Distributes intensity across weeks (Fixed Decrement or Linear)
- [Set Week Progression](#set-week-progression-fixed-decrement) - Distributes intensity within each week (Fixed Decrement or Linear)
- [Set Paired Reps](#set-paired-reps) - Determines repetition counts
- [Calculate Weights](#calculate-weights) - Converts 1RM values to actual weights

### Rules

Rules apply automatic adjustments after each action:

- [Reduce Sets for Odd Weeks](#reduce-sets-for-odd-weeks) - Built-in deload pattern
- [Round Weights to Nearest Step](#round-weights-to-nearest-step) - Matches available plates
- [Duplicate Sessions Per Week](#duplicate-sessions-per-week) - Ensures consistent training days

---

# Glossary

## Strategies

### Fixed Decrement Strategy

The Fixed Decrement strategy uses discrete weight steps to progress intensity. Rather than smooth increments, it groups sets and weeks at the same intensity level before stepping up.

**How it works:**

The system works backwards from your target 1RM using fixed weight increments:
- **0-50kg**: 2.5kg steps
- **50-100kg**: 5.0kg steps
- **100kg+**: 7.5kg steps

This creates plateaus where the athlete consolidates at one intensity before advancing. The step size automatically scales with the athlete's strength level.

---

### Linear Progression Strategy

The Linear Progression strategy distributes intensity evenly across the training block, creating a smooth ramp from starting 1RM to target 1RM.

**How it works:**

The total intensity increase is divided equally across weeks (and sets within weeks). Each training unit receives a slightly higher target than the previous one.

---

## Actions

### Create Empty Block

Creates the initial block structure with the specified number of weeks, sessions per week, and sets per session. All values (reps, weights, 1RM) start as empty and are populated by subsequent actions.

| Option | Description | Default | Range |
|--------|-------------|---------|-------|
| Sets per session | Number of sets in each training session | 4 | 1-6 |

**Example:** With 4 sets per session across a 5-week block with 2 sessions per week:

```
Week 1: Session 1 [_, _, _, _]  Session 2 [_, _, _, _]
Week 2: Session 1 [_, _, _, _]  Session 2 [_, _, _, _]
Week 3: Session 1 [_, _, _, _]  Session 2 [_, _, _, _]
Week 4: Session 1 [_, _, _, _]  Session 2 [_, _, _, _]
Week 5: Session 1 [_, _, _, _]  Session 2 [_, _, _, _]
```

---

### Set Block Start

Sets the starting 1RM value on the first set of the last session of week one. This establishes the athlete's baseline strength for the training block.

| Option | Description | Default |
|--------|-------------|---------|
| Starting 1RM | Derived from athlete's test data | — |

**Example:** Athlete has starting 1RM of 100kg:

```
Week 1: Session 2 → Set 1: 1RM = 100kg
                    Sets 2-4: 1RM = _
```

---

### Set Block Target

Sets the target 1RM on the final set of the final session of the final week. This establishes the end goal that the entire block works toward.

| Option | Description | Default |
|--------|-------------|---------|
| Target 1RM | Calculated from starting 1RM + target goal % | — |

**Example:** Athlete has target 1RM of 110kg in a 5-week block:

```
Week 5: Session 2 → Sets 1-3: 1RM = _
                    Set 4: 1RM = 110kg
```

---

### Set Week Targets (Fixed Decrement)

Works backwards from the target using fixed weight steps. Multiple weeks may share the same target before stepping down.

| Option | Description | Default | Range |
|--------|-------------|---------|-------|
| Step down interval | How many weeks stay at each intensity level | 2 | 1-10 |

**Example:** Target 110kg, step down interval of 2 across 5 weeks:

```
Week 5: 110.0kg (target)
Week 4: 110.0kg (same - interval not reached)
Week 3: 102.5kg (stepped down 7.5kg)
Week 2: 102.5kg (same - interval not reached)
Week 1: 95.0kg  (stepped down 7.5kg)
```

*Note: Step sizes are 7.5kg for weights over 100kg, 5kg for 50-100kg, 2.5kg under 50kg*

---

### Set Week Targets (Linear)

Distributes targets evenly from starting 1RM to target 1RM with smooth progression.

| Option | Description | Default | Range |
|--------|-------------|---------|-------|
| Step up interval | How many weeks between 1RM target increases | 1 | 1-10 |

**Example:** Start 100kg, target 110kg, step up interval of 1 across 5 weeks:

```
Week 1: 100.0kg
Week 2: 102.5kg (+2.5kg)
Week 3: 105.0kg (+2.5kg)
Week 4: 107.5kg (+2.5kg)
Week 5: 110.0kg (+2.5kg)
```

---

### Set Week Progression (Fixed Decrement)

Distributes 1RM values across sets within each week using fixed weight steps. Sets are grouped at the same intensity before stepping up.

| Option | Description | Default | Range |
|--------|-------------|---------|-------|
| Step down interval | How many sets share each intensity level | 2 | 1-10 |

**Example:** Week target 110kg with 4 sets and step down interval of 2:

```
Set 1: 102.5kg  ┐ Group 1 (lowest intensity)
Set 2: 102.5kg  ┘
Set 3: 110.0kg  ┐ Group 2 (target intensity)
Set 4: 110.0kg  ┘
```

---

### Set Week Progression (Linear)

Distributes 1RM values evenly across sets within each week, creating a smooth progression from the week's start to its target.

*No configuration options.*

**Example:** Week starts at 100kg, target 110kg with 4 sets:

```
Set 1: 100.0kg (start)
Set 2: 103.3kg
Set 3: 106.7kg
Set 4: 110.0kg (target)
```

---

### Set Paired Reps

Determines the repetition count for each set across the block. Reps typically start higher (volume emphasis) and decrease over time (intensity emphasis). Sets are "paired" meaning they're grouped at the same rep count.

| Option | Description | Default | Range |
|--------|-------------|---------|-------|
| Starting reps | Initial rep count for the first set of week one | 12 | 1-20 |
| Step down interval | How many weeks before reps decrease | 2 | 1-4 |
| Rep decrement | How much reps drop at each interval | 2 | 1-4 |
| Minimum reps | Floor value — reps never go below this | 1 | 1-6 |

**Example:** Starting reps 12, step down interval 2, rep decrement 2, 4 sets:

```
Week 1: [12, 12, 10, 10] reps
Week 2: [12, 12, 10, 10] reps (same - interval not reached)
Week 3: [10, 10, 8, 8] reps   (stepped down after 2 weeks)
Week 4: [10, 10, 8, 8] reps   (same - interval not reached)
Week 5: [8, 8, 6, 6] reps     (stepped down after 2 weeks)
```

---

### Calculate Weights

Converts 1RM values and rep counts into actual training weights using standard strength formulas (Brzycki formula).

*No configuration options.*

**Example:** Set with 1RM of 100kg and 10 reps prescribed:

```
Input:  1RM = 100kg, Reps = 10
Output: Weight = 75kg (approximately 75% of 1RM for 10 reps)
```

The formula accounts for the relationship between weight and reps — lower reps allow heavier weights, higher reps require lighter weights to achieve the same training effect.

---

## Rules

### Reduce Sets for Odd Weeks

Automatically reduces the number of sets on odd-numbered weeks (1, 3, 5, etc.) to create a built-in deload pattern. Sets are removed from the end of each session.

| Option | Description | Default | Range |
|--------|-------------|---------|-------|
| Sets to reduce by | How many sets to remove on odd weeks | 1 | 1-10 |

**Example:** 4 sets per session, reduce by 1:

```
Week 1: [Set 1, Set 2, Set 3] (odd - reduced)
Week 2: [Set 1, Set 2, Set 3, Set 4] (even - full)
Week 3: [Set 1, Set 2, Set 3] (odd - reduced)
Week 4: [Set 1, Set 2, Set 3, Set 4] (even - full)
Week 5: [Set 1, Set 2, Set 3] (odd - reduced)
```

---

### Round Weights to Nearest Step

Rounds all calculated weights to the nearest practical increment, matching the plates available in the gym.

| Option | Description | Default | Options |
|--------|-------------|---------|---------|
| Step | Smallest weight increment available | 0.5kg | 0.25, 0.5, 1.0, 2.5, 5.0kg |

**Example:** Step of 2.5kg:

```
Before: 67.3kg → After: 67.5kg
Before: 71.1kg → After: 70.0kg
Before: 82.8kg → After: 82.5kg
```

---

### Duplicate Sessions Per Week

Copies the structure from the last session to all other sessions within each week. This ensures all training days in a week have identical programming.

*No configuration options.*

**Example:** Week with 3 sessions where Session 3 has been populated:

```
Before:
  Session 1: [_, _, _, _]
  Session 2: [_, _, _, _]
  Session 3: [80kg×10, 85kg×8, 90kg×6, 95kg×4]

After:
  Session 1: [80kg×10, 85kg×8, 90kg×6, 95kg×4]
  Session 2: [80kg×10, 85kg×8, 90kg×6, 95kg×4]
  Session 3: [80kg×10, 85kg×8, 90kg×6, 95kg×4]
```
