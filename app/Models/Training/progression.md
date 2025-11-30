# Training Plan Progression System

A complete specification for automatically generating progressive strength training plans.

---

## Overview

This system generates personalised training plans for athletes based on their tested strength levels. It automates the calculation of weights, reps, and sets across a training block while following established periodisation principles.

The system is designed for coaches working with groups of athletes (e.g., school classes) who need to efficiently create individualised plans without manual calculation for each student.

**The system supports two weight progression strategies:**

- **Fixed Step Progression** — calculates the final target, then works backwards using fixed weight decrements
- **Compounded Progression** — grows the athlete's projected 1RM each week using compound interest, then derives working weights from that

**The system also supports two rep calculation strategies:**

- **Paired Ladder** — reps follow a fixed pattern based on starting reps, step-down interval, and decrement
- **Proportional Reps** — reps are calculated from each set's weight as a percentage of a Reference 1RM

The coach selects which strategies to use. All combinations are supported.

---

## Terminology

| Term | Definition |
|------|------------|
| **Athlete Test** | The submaximal test performed by the athlete, recorded as reps × weight (e.g., 8 reps × 56kg) |
| **Derived 1RM** | The theoretical one-rep maximum calculated from the Athlete Test using the reps-to-percentage conversion table. This value is never actually lifted—it serves as the baseline for all calculations. |
| **Projected 1RM** | The expected 1RM for a given week, based on progression from the Derived 1RM toward the Target 1RM |
| **Target 1RM** | The goal 1RM for the final week of the block (Derived 1RM × Target Improvement %) |
| **Reference 1RM** | The 1RM value used for Proportional Reps calculations. Calculated using linear interpolation between Derived 1RM and Target 1RM for each week. |
| **Training Block** | A multi-week training period (typically 5 weeks) designed to achieve a specific strength goal |
| **Week** | A single week within a training block |
| **Session** | A training day within a week; each week contains two identical sessions per exercise |
| **Set** | A single bout of exercise repetitions before resting |
| **Anchor Set** | The final (heaviest) set of an exercise — the reference point for calculating all other set weights |
| **Anchor Weight** | The weight assigned to the anchor set, derived from the projected 1RM multiplied by the rep percentage for that set's rep count |
| **Anchor Reps** | The rep count for the anchor set in a given week, used to determine the rep percentage for weight calculations |
| **Rep Tier** | A pair of sets performed at the same rep count (e.g., two sets of 12 reps) |
| **Rep Ladder** | The descending sequence of rep tiers across a block (e.g., 14 → 12 → 10 → 8 → 6) |

---

## System Inputs

### Athlete Inputs

| Input | Description | Example |
|-------|-------------|---------|
| **Athlete Test** | Submaximal test result recorded as reps × weight | 8 reps × 56kg |

### Global Inputs (Coach Configurable)

| Input | Description | Default |
|-------|-------------|---------|
| **Block Length** | Number of weeks in the training block | 5 |
| **Increment Step** | Smallest weight increment available | 0.5kg |
| **Weight Progression Strategy** | Fixed Step Progression or Compounded Progression | Fixed Step |
| **Rep Calculation Strategy** | Paired Ladder or Proportional Reps | Paired Ladder |

### Per-Exercise Inputs (Coach Configurable)

| Input | Description | Default |
|-------|-------------|---------|
| **1RM Modifier** | Multiplier to derive exercise 1RM from back squat 1RM | 1.0 |
| **Target Improvement** | Percentage strength gain targeted over the block | 12.5% |
| **Starting Reps** | Highest rep count in the block | 14 |
| **Step-Down Interval** | Number of weeks before dropping to the next rep tier | 2 |
| **Rep Decrement** | How much reps drop per tier | 2 |
| **Minimum Reps** | Floor for the rep ladder (reps never go below this) | 6 |

---

## Weight Calculation

This section covers how the system calculates weights for each set across the training block.

### Step 1: Calculate Derived 1RM from Athlete Test

The athlete performs a submaximal test on the back squat. The system converts this to a Derived 1RM using a standard reps-to-percentage table.

**Formula:**
```
Derived 1RM = Test Weight ÷ Rep Percentage
```

**Reps-to-Percentage Conversion Table:**

| Reps | % | Reps | % | Reps | % |
|------|---|------|---|------|---|
| 1 | 1.00 | 6 | 0.86 | 11 | 0.71 |
| 2 | 0.96 | 7 | 0.83 | 12 | 0.67 |
| 3 | 0.94 | 8 | 0.80 | 13 | 0.65 |
| 4 | 0.91 | 9 | 0.77 | 14 | 0.63 |
| 5 | 0.88 | 10 | 0.74 | 15 | 0.62 |

**Example:**
- Athlete Test: 8 reps × 56kg
- Rep percentage for 8 reps: 0.80
- Derived 1RM = 56 ÷ 0.80 = **70kg**

---

### Step 2: Calculate Per-Exercise Derived 1RM

Each exercise has a modifier that estimates its Derived 1RM relative to the back squat.

**Formula:**
```
Exercise Derived 1RM = Back Squat Derived 1RM × Exercise Modifier
```

**Example Modifier Table:**

| Exercise | Modifier | Exercise | Modifier |
|----------|----------|----------|----------|
| Back Squat | 1.00 | Deadlift Narrow | 1.05 |
| Front Squat | 0.85 | Bent-over Row | 0.60 |
| Deadlift Wide | 0.95 | | |

**Example:**
- Back Squat Derived 1RM: 70kg
- Front Squat modifier: 0.85
- Front Squat Derived 1RM = 70 × 0.85 = **59.5kg**

*Note: Coaches can override this per athlete per exercise based on their knowledge.*

---

### Step 3: Calculate Anchor Reps Per Week

The anchor rep count determines which rep percentage is used for anchor weight calculations. **This is shared by both weight strategies and both rep strategies.**

**Formula:**
```
Anchor reps start at (Starting Reps - Rep Decrement)
Every (Step-Down Interval) weeks, anchor reps decrease by (Rep Decrement)
Anchor reps never fall below (Minimum Reps)
```

**Example — Settings:** Starting reps = 12, Step-down interval = 2, Rep decrement = 2, Minimum = 6

| Week | 1 | 2 | 3 | 4 | 5 |
|------|---|---|---|---|---|
| **Anchor Reps** | 10 | 10 | 8 | 8 | 6 |
| **Rep %** | 0.74 | 0.74 | 0.80 | 0.80 | 0.86 |

**Set count per week:**
- Odd weeks (1, 3, 5): 3 sets
- Even weeks (2, 4): 4 sets

---

### Step 4: Calculate Weekly Anchor Weights

This is where the two weight strategies differ. Both use the anchor reps from Step 3.

---

### Weight Strategy A: Fixed Step Progression

Fixed Step Progression calculates the final week's anchor weight, then works **backwards** using fixed weight decrements based on weight brackets.

#### 4a: Calculate Target 1RM

**Formula:**
```
Target 1RM = Derived 1RM × (1 + Target Improvement %)
```

**Example:**
- Derived 1RM: 70kg
- Target improvement: 12.5%
- Target 1RM = 70 × 1.125 = **78.75kg**

#### 4b: Calculate Final Week's Anchor Weight

**Formula:**
```
Final Anchor = Target 1RM × Rep Percentage (for final week's anchor reps)
```

**Example:**
- Target 1RM: 78.75kg
- Final week anchor reps: 6
- Rep percentage for 6 reps: 0.86
- Final Anchor = 78.75 × 0.86 = **67.7kg** (rounded to 67.5kg)

#### 4c: Work Backwards for Each Week

| Current Weight | Decrease Per Week |
|----------------|-------------------|
| Above 100kg | 7.5kg |
| 50kg – 100kg | 5.0kg |
| Below 50kg | 2.5kg |

**Example — 5-Week Block with 67.5kg Final Anchor:**

| Week | 5 | 4 | 3 | 2 | 1 |
|------|---|---|---|---|---|
| **Anchor** | 67.5 | 62.5 | 57.5 | 52.5 | 47.5 |

---

### Weight Strategy B: Compounded Progression

Compounded Progression calculates each week's Projected 1RM using compound growth, then derives that week's anchor weight.

#### 4a: Calculate Weekly Growth Rate

**Formula:**
```
Weekly Growth Rate = (1 + Target Improvement %)^(1 / Number of Weeks) - 1
```

**Example:**
- Target improvement: 12.5%
- Block length: 5 weeks
- Weekly Growth Rate = (1.125)^(1/5) - 1 = 0.0239 = **2.39% per week**

#### 4b: Calculate Each Week's Projected 1RM

**Formula:**
```
Projected 1RM (Week N) = Derived 1RM × (1 + Weekly Growth Rate)^(N-1)
```

#### 4c: Calculate Each Week's Anchor Weight

**Formula:**
```
Anchor Weight = Projected 1RM × Rep Percentage (for that week's anchor reps)
```

**Example — 5-Week Block with 70kg Derived 1RM:**

| Week | 1 | 2 | 3 | 4 | 5 |
|------|---|---|---|---|---|
| **Proj 1RM** | 70.0 | 71.7 | 73.4 | 75.1 | 78.75 |
| **Anchor Reps** | 10 | 10 | 8 | 8 | 6 |
| **Anchor** | 51.8 | 53.1 | 58.7 | 60.1 | 67.7 |

*Note: The jump between Week 2 and Week 3 is larger because the rep percentage shifts from 0.74 to 0.80.*

---

### Weight Strategy Comparison

| Week | Anchor Reps | Fixed Step | Compounded | Difference |
|------|-------------|------------|------------|------------|
| 1 | 10 | 47.5kg | 51.8kg | -4.3kg |
| 2 | 10 | 52.5kg | 53.1kg | -0.6kg |
| 3 | 8 | 57.5kg | 58.7kg | -1.2kg |
| 4 | 8 | 62.5kg | 60.1kg | +2.4kg |
| 5 | 6 | 67.5kg | 67.7kg | -0.2kg |

| Aspect | Fixed Step | Compounded |
|--------|------------|------------|
| **Starting point** | Lighter than current capacity | Matches current capacity |
| **Growth pattern** | Consistent kg jumps | Percentage-based with rep % shifts |
| **Best for** | Peaking, aggressive progression | Longer periods, conservative progression |

---

### Step 5: Calculate Per-Set Weights Within Each Week

Once the anchor weight is known, calculate other set weights **working backwards from the anchor**.

| Current Weight | Decrease Per Set |
|----------------|------------------|
| Above 100kg | 7.5kg |
| 50kg – 100kg | 5.0kg |
| Below 50kg | 2.5kg |

**Example — Week 5 with 3 Sets (Anchor = 67.5kg):**

| Set | 3 (Anchor) | 2 | 1 |
|-----|------------|---|---|
| **Weight** | 67.5 | 62.5 | 57.5 |

---

## Rep Calculation

This section covers how the system calculates reps for each set.

### Rep Strategy A: Paired Ladder

Paired Ladder assigns reps based on a fixed pattern. Reps come in pairs and follow a predictable descent across the block.

#### Paired Ladder Rules

1. Reps come in **pairs** within a session (two sets at the same rep count)
2. Within a session, the **second pair** is one tier lower than the first
3. Every *n* weeks (step-down interval), the entire ladder shifts down by one tier
4. Reps never fall below the minimum (default: 6)

#### Paired Ladder Example

**Settings:** Starting reps = 12, Step-down interval = 2, Rep decrement = 2, Minimum = 6

| Week | Set 1 | Set 2 | Set 3 | Set 4 |
|------|-------|-------|-------|-------|
| 1 | 12 | 12 | 10 | — |
| 2 | 12 | 12 | 10 | 10 |
| 3 | 10 | 10 | 8 | — |
| 4 | 10 | 10 | 8 | 8 |
| 5 | 8 | 8 | 6 | — |

---

### Rep Strategy B: Proportional Reps

Proportional Reps calculates the rep count for each set based on that set's weight as a percentage of a Reference 1RM. Lighter sets get more reps, heavier sets get fewer reps.

#### Reference 1RM Calculation

To ensure smooth, predictable rep calculations across both weight strategies, Proportional Reps uses a **Reference 1RM** calculated via linear interpolation:

**Formula:**
```
Reference 1RM (Week N) = Derived 1RM + ((Target 1RM - Derived 1RM) × (N - 1) / (Total Weeks - 1))
```

**Example — 5-Week Block (Derived 1RM = 70kg, Target 1RM = 78.75kg):**

| Week | 1 | 2 | 3 | 4 | 5 |
|------|---|---|---|---|---|
| **Ref 1RM** | 70.0 | 72.2 | 74.4 | 76.6 | 78.75 |

This ensures:
- Week 1 starts at the athlete's current capacity
- Week 5 ends at the target capacity
- Works consistently with both weight strategies

#### Proportional Reps Calculation

**Formula:**
```
Rep Percentage = Set Weight ÷ Reference 1RM
Reps = Lookup from table (rounded to nearest 2)
```

**Reps-to-Percentage Lookup:**

| % of Reference 1RM | Reps |
|--------------------|------|
| 60-64% | 14 |
| 65-69% | 12 |
| 70-75% | 10 |
| 76-81% | 8 |
| 82-87% | 6 |

#### Proportional Reps Rules

1. Anchor reps are **fixed** (from anchor rep ladder—same as Paired Ladder)
2. Earlier sets calculate reps from their weight as % of Reference 1RM
3. Reps rounded to nearest 2
4. Minimum reps floor applies

---

### Rep Strategy Comparison

| Week | Set | Weight | Paired Ladder | Proportional |
|------|-----|--------|---------------|--------------|
| 1 | 1 | 47.0 | 12 | 12 |
| 1 | 2 | 49.5 | 12 | 10 |
| 1 | 3 | 52.0 | 10 | 10 |
| 3 | 1 | 48.5 | 10 | 12 |
| 3 | 2 | 53.5 | 10 | 10 |
| 3 | 3 | 58.5 | 8 | 8 |
| 5 | 1 | 57.5 | 8 | 10 |
| 5 | 2 | 62.5 | 8 | 8 |
| 5 | 3 | 67.5 | 6 | 6 |

| Aspect | Paired Ladder | Proportional Reps |
|--------|---------------|-------------------|
| **Predictability** | Easy to memorise | Varies by weight |
| **Volume on light sets** | Lower | Higher |
| **Best for** | Simplicity | Optimised volume |

---

## Complete Examples

The following four examples show all combinations of weight and rep strategies.

**Shared inputs:**
- Athlete Test: 8 reps × 56kg → Derived 1RM: 70kg
- Target improvement: 12.5% → Target 1RM: 78.75kg
- Block: 5 weeks | Starting reps: 12 | Step-down: 2 | Decrement: 2 | Min: 6

---

### Example 1: Fixed Step + Paired Ladder

| Week | 1 | 2 | 3 | 4 | 5 |
|------|---|---|---|---|---|
| **Anchor** | 47.5 | 52.5 | 57.5 | 62.5 | 67.5 |

| W.S | Set 1 | Set 2 | Set 3 | Set 4 |
|-----|-------|-------|-------|-------|
| **1.1** | 12 | 12 | 10 | — |
| | 42.5 | 45.0 | 47.5 | — |
| **1.2** | 12 | 12 | 10 | — |
| | 42.5 | 45.0 | 47.5 | — |
| **2.1** | 12 | 12 | 10 | 10 |
| | 42.5 | 45.0 | 47.5 | 52.5 |
| **2.2** | 12 | 12 | 10 | 10 |
| | 42.5 | 45.0 | 47.5 | 52.5 |
| **3.1** | 10 | 10 | 8 | — |
| | 47.5 | 52.5 | 57.5 | — |
| **3.2** | 10 | 10 | 8 | — |
| | 47.5 | 52.5 | 57.5 | — |
| **4.1** | 10 | 10 | 8 | 8 |
| | 47.5 | 52.5 | 57.5 | 62.5 |
| **4.2** | 10 | 10 | 8 | 8 |
| | 47.5 | 52.5 | 57.5 | 62.5 |
| **5.1** | 8 | 8 | 6 | — |
| | 57.5 | 62.5 | 67.5 | — |
| **5.2** | 8 | 8 | 6 | — |
| | 57.5 | 62.5 | 67.5 | — |

---

### Example 2: Fixed Step + Proportional Reps

| Week | 1 | 2 | 3 | 4 | 5 |
|------|---|---|---|---|---|
| **Anchor** | 47.5 | 52.5 | 57.5 | 62.5 | 67.5 |
| **Ref 1RM** | 70.0 | 72.2 | 74.4 | 76.6 | 78.75 |

| W.S | Set 1 | Set 2 | Set 3 | Set 4 |
|-----|-------|-------|-------|-------|
| **1.1** | 14 | 14 | 10 | — |
| | 42.5 | 45.0 | 47.5 | — |
| **1.2** | 14 | 14 | 10 | — |
| | 42.5 | 45.0 | 47.5 | — |
| **2.1** | 14 | 14 | 12 | 10 |
| | 42.5 | 45.0 | 47.5 | 52.5 |
| **2.2** | 14 | 14 | 12 | 10 |
| | 42.5 | 45.0 | 47.5 | 52.5 |
| **3.1** | 14 | 10 | 8 | — |
| | 47.5 | 52.5 | 57.5 | — |
| **3.2** | 14 | 10 | 8 | — |
| | 47.5 | 52.5 | 57.5 | — |
| **4.1** | 14 | 12 | 10 | 8 |
| | 47.5 | 52.5 | 57.5 | 62.5 |
| **4.2** | 14 | 12 | 10 | 8 |
| | 47.5 | 52.5 | 57.5 | 62.5 |
| **5.1** | 10 | 8 | 6 | — |
| | 57.5 | 62.5 | 67.5 | — |
| **5.2** | 10 | 8 | 6 | — |
| | 57.5 | 62.5 | 67.5 | — |

---

### Example 3: Compounded + Paired Ladder

| Week | 1 | 2 | 3 | 4 | 5 |
|------|---|---|---|---|---|
| **Proj 1RM** | 70.0 | 71.7 | 73.4 | 75.1 | 78.75 |
| **Anchor** | 52.0 | 53.0 | 58.5 | 60.0 | 67.5 |

| W.S | Set 1 | Set 2 | Set 3 | Set 4 |
|-----|-------|-------|-------|-------|
| **1.1** | 12 | 12 | 10 | — |
| | 47.0 | 49.5 | 52.0 | — |
| **1.2** | 12 | 12 | 10 | — |
| | 47.0 | 49.5 | 52.0 | — |
| **2.1** | 12 | 12 | 10 | 10 |
| | 45.5 | 48.0 | 50.5 | 53.0 |
| **2.2** | 12 | 12 | 10 | 10 |
| | 45.5 | 48.0 | 50.5 | 53.0 |
| **3.1** | 10 | 10 | 8 | — |
| | 48.5 | 53.5 | 58.5 | — |
| **3.2** | 10 | 10 | 8 | — |
| | 48.5 | 53.5 | 58.5 | — |
| **4.1** | 10 | 10 | 8 | 8 |
| | 47.5 | 52.5 | 57.5 | 60.0 |
| **4.2** | 10 | 10 | 8 | 8 |
| | 47.5 | 52.5 | 57.5 | 60.0 |
| **5.1** | 8 | 8 | 6 | — |
| | 57.5 | 62.5 | 67.5 | — |
| **5.2** | 8 | 8 | 6 | — |
| | 57.5 | 62.5 | 67.5 | — |

---

### Example 4: Compounded + Proportional Reps

| Week | 1 | 2 | 3 | 4 | 5 |
|------|---|---|---|---|---|
| **Proj 1RM** | 70.0 | 71.7 | 73.4 | 75.1 | 78.75 |
| **Anchor** | 52.0 | 53.0 | 58.5 | 60.0 | 67.5 |
| **Ref 1RM** | 70.0 | 72.2 | 74.4 | 76.6 | 78.75 |

| W.S | Set 1 | Set 2 | Set 3 | Set 4 |
|-----|-------|-------|-------|-------|
| **1.1** | 12 | 10 | 10 | — |
| | 47.0 | 49.5 | 52.0 | — |
| **1.2** | 12 | 10 | 10 | — |
| | 47.0 | 49.5 | 52.0 | — |
| **2.1** | 14 | 12 | 10 | 10 |
| | 45.5 | 48.0 | 50.5 | 53.0 |
| **2.2** | 14 | 12 | 10 | 10 |
| | 45.5 | 48.0 | 50.5 | 53.0 |
| **3.1** | 12 | 10 | 8 | — |
| | 48.5 | 53.5 | 58.5 | — |
| **3.2** | 12 | 10 | 8 | — |
| | 48.5 | 53.5 | 58.5 | — |
| **4.1** | 14 | 12 | 10 | 8 |
| | 47.5 | 52.5 | 57.5 | 60.0 |
| **4.2** | 14 | 12 | 10 | 8 |
| | 47.5 | 52.5 | 57.5 | 60.0 |
| **5.1** | 10 | 8 | 6 | — |
| | 57.5 | 62.5 | 67.5 | — |
| **5.2** | 10 | 8 | 6 | — |
| | 57.5 | 62.5 | 67.5 | — |

---

## Mid-Block Adjustments

Coaches may need to adjust a training plan mid-block. The system must handle these adjustments while protecting historical data.

### Core Requirement: Protect Completed Sessions

**Once an athlete has completed a session, those values are historical and must never change.**

The system must:

1. Mark sessions as "completed" once done
2. Lock all values (weights, reps, sets) for completed sessions
3. Only recalculate future (uncompleted) sessions when changes are made
4. Clearly indicate which sessions are locked vs editable

---

### Scenario 1: Change the Target 1RM

| Strategy | Behaviour |
|----------|-----------|
| **Fixed Step** | Final anchor recalculates, then all future weeks recalculate backwards. May create a visible "jump" between last completed week and first recalculated week. |
| **Compounded** | Weekly growth rate recalculates for remaining weeks only. Transition is smoother because growth rate adjusts to cover remaining weeks. |

**Compounded handles this better** because it can recalculate from any midpoint.

---

### Scenario 2: Change a Single Week's Anchor (One-Time)

| Strategy | Behaviour |
|----------|-----------|
| **Fixed Step** | Override stored as exception. Creates visible "gap" in step pattern. |
| **Compounded** | Override stored as exception. Clean separation—each week is independent. |
| **Paired Ladder** | Reps stay as originally calculated. |
| **Proportional Reps** | Coach chooses: keep original reps OR recalculate reps. |

---

### Scenario 3: Change a Single Week's Anchor (Carry Forward)

| Strategy | Behaviour | Complexity |
|----------|-----------|------------|
| **Fixed Step** | Recalculate future weeks by stepping forward from override. Target 1RM must be recalculated. | High |
| **Compounded** | Derive implied 1RM from override, recalculate growth rate for remaining weeks. | Moderate |

**Compounded handles carry-forward more naturally.**

---

### Scenario 4: Change a Single Set's Weight or Reps

| Strategy | Behaviour |
|----------|-----------|
| **All combinations** | Manual override only. No ripple effect to other sets or weeks. |

---

### Rep Strategy Considerations for Overrides

| Override Type | Options |
|---------------|---------|
| **Weight override** | Keep original reps OR recalculate reps |
| **Rep override** | Manual override only |

**Recommendation:** Default to "Keep original reps" for one-time overrides, "Recalculate reps" for carry-forward overrides.

---

### Strategy Comparison for Mid-Block Adjustments

| Scenario | Fixed Step | Compounded | Winner |
|----------|------------|------------|--------|
| Protect completed sessions | Requires explicit locking | Natural—weeks independent | Compounded |
| Change Target 1RM | May create jumps | Smooth transition | Compounded |
| Carry-forward override | Complex | Moderate | Compounded |

| Scenario | Paired Ladder | Proportional Reps | Winner |
|----------|---------------|-------------------|--------|
| Weight override | No rep impact | Must decide reps | Paired Ladder (simpler) |
| Flexibility | Fixed pattern | Reps adapt to weight | Proportional (precise) |

---

### Implementation Requirements

**For all overrides:**
1. **Session lock status** — Completed sessions always locked

**For weekly anchor overrides:**
2. **Scope selection** — "This week only" vs "Carry forward"

**For Proportional Reps weight overrides:**
3. **Rep handling** — "Keep original reps" vs "Recalculate reps"

**UI indicators:**
4. Clearly show which values are: Calculated | Manually overridden | Locked

---

## Edge Cases and Constraints

### Minimum Reps Floor
When the rep ladder reaches the minimum (default 6), it stops descending and repeats the last valid pattern.

### Hard Constraint
The rep decrement must never result in reps below 1. The system should reject such configurations.

### Coach Overrides
All per-exercise inputs can be overridden on a per-athlete basis.

---

## Summary of Defaults

| Parameter | Default Value |
|-----------|---------------|
| Block Length | 5 weeks |
| Increment Step | 0.5kg |
| Weight Progression Strategy | Fixed Step |
| Rep Calculation Strategy | Paired Ladder |
| 1RM Modifier | 1.0 |
| Target Improvement | 12.5% |
| Starting Reps | 14 |
| Step-Down Interval | 2 weeks |
| Rep Decrement | 2 |
| Minimum Reps | 6 |
