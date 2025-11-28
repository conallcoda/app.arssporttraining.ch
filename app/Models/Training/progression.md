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
| **Target Improvement** | Percentage strength gain targeted over the block | 7.5% |
| **Starting Reps** | Highest rep count in the block | 14 |
| **Step-Down Interval** | Number of weeks before dropping to the next rep tier | 2 |
| **Rep Decrement** | How much reps drop per tier | 2 |
| **Minimum Reps** | Floor for the rep ladder (reps never go below this) | 6 |

---

## Calculation Process

Both progression strategies share the same initial steps (1-2) and final steps (4-5). They differ only in Step 3: how weekly anchor weights are calculated.

### Step 1: Calculate Derived 1RM from Athlete Test

The athlete performs a submaximal test on the back squat. The system converts this to a Derived 1RM using a standard reps-to-percentage table.

**Formula:**
```
Derived 1RM = Test Weight ÷ Rep Percentage
```

**Reps-to-Percentage Conversion Table:**

| Reps | Percentage |
|------|------------|
| 1 | 1.00 |
| 2 | 0.96 |
| 3 | 0.94 |
| 4 | 0.91 |
| 5 | 0.88 |
| 6 | 0.86 |
| 7 | 0.83 |
| 8 | 0.80 |
| 9 | 0.77 |
| 10 | 0.74 |
| 11 | 0.71 |
| 12 | 0.67 |
| 13 | 0.65 |
| 14 | 0.63 |
| 15 | 0.62 |

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

| Exercise | 1RM Modifier |
|----------|--------------|
| Back Squat | 1.00 |
| Front Squat | 0.85 |
| Deadlift Wide | 0.95 |
| Deadlift Narrow | 1.05 |
| Bent-over Row | 0.60 |

**Example:**
- Back Squat Derived 1RM: 70kg
- Front Squat modifier: 0.85
- Front Squat Derived 1RM = 70 × 0.85 = **59.5kg**

*Note: Coaches can override this per athlete per exercise based on their knowledge.*

---

### Step 3: Determine the Rep Ladder (Anchor Reps)

Before calculating weights, the system determines the anchor rep count for each week. This determines the rep percentage used for anchor weight calculations and is shared by both rep strategies.

#### Anchor Rep Rules

1. Anchor reps start at the **second tier** (starting reps minus one decrement)
2. Every *n* weeks (the step-down interval), anchor reps drop by one tier
3. Anchor reps never fall below the configured minimum (default: 6)

#### Set Count Per Week

- **Odd weeks** (1, 3, 5): 3 sets (removes the final set)
- **Even weeks** (2, 4): 4 sets (full volume)

#### Anchor Reps Example

**Settings:** Starting reps = 12, Step-down interval = 2, Rep decrement = 2, Minimum = 6

| Week | Anchor Reps | Rep Percentage |
|------|-------------|----------------|
| 1 | 10 | 0.74 |
| 2 | 10 | 0.74 |
| 3 | 8 | 0.80 |
| 4 | 8 | 0.80 |
| 5 | 6 | 0.86 |

---

### Step 4: Calculate Weekly Anchor Weights

This is where the two strategies differ. Both use the rep ladder from Step 3 to determine the anchor reps for each week.

---

## Strategy A: Fixed Step Progression

Fixed Step Progression calculates the final week's anchor weight, then works **backwards** using fixed weight decrements based on weight brackets.

### 4a: Calculate Target 1RM

**Formula:**
```
Target 1RM = Derived 1RM × (1 + Target Improvement %)
```

**Example:**
- Derived 1RM: 70kg
- Target improvement: 12.5%
- Target 1RM = 70 × 1.125 = **78.75kg**

### 4b: Calculate Final Week's Anchor Weight

The final anchor weight is derived from the target 1RM using the rep percentage for the final week's anchor reps.

**Formula:**
```
Final Anchor = Target 1RM × Rep Percentage (for final week's anchor reps)
```

**Example:**
- Target 1RM: 78.75kg
- Final week anchor reps: 6
- Rep percentage for 6 reps: 0.86
- Final Anchor = 78.75 × 0.86 = **67.7kg** (rounded to 67.5kg)

### 4c: Work Backwards for Each Week

Starting from the final week's anchor, calculate each previous week's anchor by subtracting a fixed amount based on the current weight bracket:

| Current Weight | Decrease Per Week |
|----------------|-------------------|
| Above 100kg | 7.5kg |
| 50kg – 100kg | 5.0kg |
| Below 50kg | 2.5kg |

**Example — 5-Week Block with 67.5kg Final Anchor:**

| Week | Calculation | Anchor Weight |
|------|-------------|---------------|
| 5 | Final target | **67.5kg** |
| 4 | 67.5 - 5.0 | **62.5kg** |
| 3 | 62.5 - 5.0 | **57.5kg** |
| 2 | 57.5 - 5.0 | **52.5kg** |
| 1 | 52.5 - 5.0 | **47.5kg** |

*Note: The decrease amount is determined by which bracket the current weight falls into.*

---

## Strategy B: Compounded Progression

Compounded Progression calculates each week's Projected 1RM using compound growth, then derives that week's anchor weight using that week's anchor rep percentage.

### 4a: Calculate Weekly Growth Rate

**Formula:**
```
Weekly Growth Rate = (1 + Target Improvement %)^(1 / Number of Weeks) - 1
```

**Example:**
- Target improvement: 12.5%
- Block length: 5 weeks
- Weekly Growth Rate = (1.125)^(1/5) - 1 = 0.0239 = **2.39% per week**

### 4b: Calculate Each Week's Projected 1RM

**Formula:**
```
Projected 1RM (Week N) = Derived 1RM × (1 + Weekly Growth Rate)^(N-1)
```

**Example — 5-Week Block with 70kg Derived 1RM:**

| Week | Calculation | Projected 1RM |
|------|-------------|---------------|
| 1 | 70.0 × 1.0239^0 | **70.0kg** |
| 2 | 70.0 × 1.0239^1 | **71.7kg** |
| 3 | 70.0 × 1.0239^2 | **73.4kg** |
| 4 | 70.0 × 1.0239^3 | **75.1kg** |
| 5 | 70.0 × 1.0239^4 | **78.75kg** |

### 4c: Calculate Each Week's Anchor Weight

Each week's anchor weight is derived from that week's projected 1RM and that week's anchor rep percentage.

**Formula:**
```
Anchor Weight = Projected 1RM × Rep Percentage (for that week's anchor reps)
```

**Example:**

| Week | Projected 1RM | Anchor Reps | Rep % | Anchor Weight |
|------|---------------|-------------|-------|---------------|
| 1 | 70.0kg | 10 | 0.74 | **51.8kg** |
| 2 | 71.7kg | 10 | 0.74 | **53.1kg** |
| 3 | 73.4kg | 8 | 0.80 | **58.7kg** |
| 4 | 75.1kg | 8 | 0.80 | **60.1kg** |
| 5 | 78.75kg | 6 | 0.86 | **67.7kg** |

*Note: The jump between Week 2 and Week 3 is larger because the rep percentage shifts from 0.74 to 0.80, not just because of 1RM growth.*

---

## Strategy Comparison

Using the same inputs:
- Derived 1RM: 70kg
- Target improvement: 12.5%
- Target 1RM: 78.75kg
- Block length: 5 weeks
- Rep ladder: 12/12/10 → 12/12/10/10 → 10/10/8 → 10/10/8/8 → 8/8/6

| Week | Anchor Reps | Rep % | Fixed Step | Compounded | Difference |
|------|-------------|-------|------------|------------|------------|
| 1 | 10 | 0.74 | 47.5kg | 51.8kg | -4.3kg |
| 2 | 10 | 0.74 | 52.5kg | 53.1kg | -0.6kg |
| 3 | 8 | 0.80 | 57.5kg | 58.7kg | -1.2kg |
| 4 | 8 | 0.80 | 62.5kg | 60.1kg | +2.4kg |
| 5 | 6 | 0.86 | 67.5kg | 67.7kg | -0.2kg |

**Key differences:**

| Aspect | Fixed Step Progression | Compounded Progression |
|--------|------------------------|------------------------|
| **Starting point** | Lighter than current capacity | Matches current capacity |
| **Growth pattern** | Consistent kg jumps | Percentage-based growth with rep % shifts |
| **Week 4** | Heavier | Lighter |
| **Final week** | Same | Same |
| **Best for** | Peaking, aggressive progression | Longer periods, conservative progression |

---

### Step 5: Calculate Per-Set Weights Within Each Week

Once the anchor weight is known for each week, calculate the weight for every other set **working backwards from the anchor**.

Both strategies use the same tiered decrease rules for within-session set calculations:

| Current Weight | Decrease Per Set |
|----------------|------------------|
| Above 100kg | 7.5kg |
| 50kg – 100kg | 5.0kg |
| Below 50kg | 2.5kg |

**Example — Week 5 with 3 Sets (Anchor = 67.5kg):**

| Set | Calculation | Weight |
|-----|-------------|--------|
| 3 (Anchor) | Anchor weight | **67.5kg** |
| 2 | 67.5 - 5.0 | **62.5kg** |
| 1 | 62.5 - 5.0 | **57.5kg** |

---

### Step 6: Calculate Per-Set Reps

This is where the two rep strategies differ.

---

## Rep Strategy A: Paired Ladder

Paired Ladder assigns reps based on a fixed pattern. Reps come in pairs and follow a predictable descent across the block.

### Paired Ladder Rules

1. Reps always come in **pairs** within a session (two sets at the same rep count)
2. Within a session, the **second pair** is always one tier lower than the first
3. Every *n* weeks (the step-down interval), the entire ladder shifts down by one tier
4. A "tier" drops by the rep decrement value (default: 2 reps)
5. Reps never fall below the configured minimum (default: 6)
6. If the ladder hits the floor, repeat the last valid pattern

### Paired Ladder Example

**Settings:** Starting reps = 12, Step-down interval = 2, Rep decrement = 2, Minimum = 6

| Week | Set 1 | Set 2 | Set 3 | Set 4 |
|------|-------|-------|-------|-------|
| 1 | 12 | 12 | 10 | — |
| 2 | 12 | 12 | 10 | 10 |
| 3 | 10 | 10 | 8 | — |
| 4 | 10 | 10 | 8 | 8 |
| 5 | 8 | 8 | 6 | — |

---

## Rep Strategy B: Proportional Reps

Proportional Reps calculates the rep count for each set based on that set's weight as a percentage of a Reference 1RM. Lighter sets get more reps, heavier sets get fewer reps.

### Reference 1RM Calculation

To ensure smooth, predictable rep calculations across both weight progression strategies, Proportional Reps uses a **Reference 1RM** calculated via linear interpolation:

**Formula:**
```
Reference 1RM (Week N) = Derived 1RM + ((Target 1RM - Derived 1RM) × (N - 1) / (Total Weeks - 1))
```

**Example — 5-Week Block (Derived 1RM = 70kg, Target 1RM = 78.75kg):**

| Week | Reference 1RM |
|------|---------------|
| 1 | 70.0kg |
| 2 | 72.2kg |
| 3 | 74.4kg |
| 4 | 76.6kg |
| 5 | 78.75kg |

This linear interpolation ensures:
- Week 1 starts at the athlete's current capacity
- Week 5 ends at the target capacity
- Intermediate weeks progress smoothly
- Works consistently with both Fixed Step and Compounded weight progressions

### Proportional Reps Calculation

**Formula:**
```
Rep Percentage = Set Weight ÷ Reference 1RM
Reps = Lookup from reps-to-percentage table (rounded to nearest 2)
```

**Reps-to-Percentage Lookup (for Proportional Reps):**

| % of Reference 1RM | Reps |
|--------------------|------|
| 60-64% | 14 |
| 65-69% | 12 |
| 70-75% | 10 |
| 76-81% | 8 |
| 82-87% | 6 |

### Proportional Reps Rules

1. Anchor reps are **fixed** (determined by the anchor rep ladder, same as Paired Ladder)
2. Earlier sets calculate reps from their weight as a percentage of Reference 1RM
3. Reps are rounded to the nearest 2 for simplicity
4. Minimum reps floor still applies (default: 6)

### Proportional Reps Example

**Week 3 — Reference 1RM: 74.4kg, Anchor: 8 reps @ 58.5kg**

| Set | Weight | % of Reference 1RM | Reps |
|-----|--------|-------------------|------|
| 1 | 48.5kg | 65% | 12 |
| 2 | 53.5kg | 72% | 10 |
| 3 (Anchor) | 58.5kg | 79% | **8** |

---

## Rep Strategy Comparison

Using the same inputs with Compounded Progression:
- Derived 1RM: 70kg
- Target 1RM: 78.75kg
- Block length: 5 weeks

### Side-by-Side: Paired Ladder vs Proportional Reps

| Week | Set | Weight | Paired Ladder | Proportional Reps |
|------|-----|--------|---------------|-------------------|
| 1 | 1 | 47.0kg | 12 | 12 |
| 1 | 2 | 49.5kg | 12 | 10 |
| 1 | 3 | 52.0kg | 10 | 10 |
| 2 | 1 | 45.5kg | 12 | 14 |
| 2 | 2 | 48.0kg | 12 | 12 |
| 2 | 3 | 50.5kg | 10 | 10 |
| 2 | 4 | 53.0kg | 10 | 10 |
| 3 | 1 | 48.5kg | 10 | 12 |
| 3 | 2 | 53.5kg | 10 | 10 |
| 3 | 3 | 58.5kg | 8 | 8 |
| 4 | 1 | 47.5kg | 10 | 14 |
| 4 | 2 | 52.5kg | 10 | 10 |
| 4 | 3 | 57.5kg | 8 | 8 |
| 4 | 4 | 60.0kg | 8 | 8 |
| 5 | 1 | 57.5kg | 8 | 10 |
| 5 | 2 | 62.5kg | 8 | 8 |
| 5 | 3 | 67.5kg | 6 | 6 |

### Key Observations

**Anchor sets match:** Both strategies produce identical reps for the anchor set (10/10/8/8/6).

**Earlier sets differ:**
- **Paired Ladder:** Reps stay paired (12/12, 10/10, 8/8) regardless of weight differences
- **Proportional Reps:** Lighter sets get more reps (14 instead of 12), creating more variation

**Practical differences:**

| Aspect | Paired Ladder | Proportional Reps |
|--------|---------------|-------------------|
| **Predictability** | Easy to memorise pattern | Varies by weight |
| **Volume on light sets** | Lower | Higher |
| **Effort calibration** | Fixed pattern | Matched to weight |
| **Best for** | Simplicity, consistency | Optimised volume distribution |

---

## Proportional Reps with Fixed Step Progression

When using Proportional Reps with Fixed Step Progression, the Reference 1RM (linear interpolation) ensures consistent rep calculations despite Fixed Step's irregular implied 1RM values.

### Why Linear Interpolation Matters

Fixed Step Progression produces anchor weights via fixed decrements, which implies erratic weekly 1RM values:

| Week | Fixed Step Anchor | Implied 1RM (from anchor) | Reference 1RM (interpolated) |
|------|-------------------|---------------------------|------------------------------|
| 1 | 47.5kg | 64.2kg | 70.0kg |
| 2 | 52.5kg | 70.9kg | 72.2kg |
| 3 | 57.5kg | 71.9kg | 74.4kg |
| 4 | 62.5kg | 78.1kg | 76.6kg |
| 5 | 67.5kg | 78.5kg | 78.75kg |

Using the Implied 1RM would produce inconsistent rep calculations (e.g., Week 1's low implied 1RM would give fewer reps than expected). The linear interpolation provides smooth, predictable progression.

### Fixed Step + Proportional Reps Example

| Week | Set | Weight | Reference 1RM | % of Ref | Reps |
|------|-----|--------|---------------|----------|------|
| 1 | 1 | 42.5kg | 70.0kg | 61% | 14 |
| 1 | 2 | 45.0kg | 70.0kg | 64% | 14 |
| 1 | 3 | 47.5kg | 70.0kg | 68% | **10** |
| 3 | 1 | 47.5kg | 74.4kg | 64% | 14 |
| 3 | 2 | 52.5kg | 74.4kg | 71% | 10 |
| 3 | 3 | 57.5kg | 74.4kg | 77% | **8** |
| 5 | 1 | 57.5kg | 78.75kg | 73% | 10 |
| 5 | 2 | 62.5kg | 78.75kg | 79% | 8 |
| 5 | 3 | 67.5kg | 78.75kg | 86% | **6** |

---

## Complete Example: Fixed Step Progression + Paired Ladder

**Athlete Test:** 8 reps × 56kg on back squat

**Derived values:**
- Back Squat Derived 1RM: 56 ÷ 0.80 = 70kg
- Target 1RM: 70 × 1.125 = 78.75kg
- Final Anchor: 78.75 × 0.86 = 67.5kg

**Settings:**
- Block length: 5 weeks
- Increment step: 0.5kg
- Target improvement: 12.5%
- Starting reps: 12
- Step-down interval: 2
- Rep decrement: 2
- Minimum reps: 6
- Weight Strategy: Fixed Step
- Rep Strategy: Paired Ladder

### Weekly Anchors (Fixed Step)

| Week | Anchor Weight |
|------|---------------|
| 1 | 47.5kg |
| 2 | 52.5kg |
| 3 | 57.5kg |
| 4 | 62.5kg |
| 5 | 67.5kg |

### Generated Plan

#### Week 1 (3 sets × 2 sessions)

| Set | Reps | Weight |
|-----|------|--------|
| 1 | 12 | 42.5kg |
| 2 | 12 | 45.0kg |
| 3 | 10 | 47.5kg |

#### Week 2 (4 sets × 2 sessions)

| Set | Reps | Weight |
|-----|------|--------|
| 1 | 12 | 42.5kg |
| 2 | 12 | 45.0kg |
| 3 | 10 | 47.5kg |
| 4 | 10 | 52.5kg |

#### Week 3 (3 sets × 2 sessions)

| Set | Reps | Weight |
|-----|------|--------|
| 1 | 10 | 47.5kg |
| 2 | 10 | 52.5kg |
| 3 | 8 | 57.5kg |

#### Week 4 (4 sets × 2 sessions)

| Set | Reps | Weight |
|-----|------|--------|
| 1 | 10 | 47.5kg |
| 2 | 10 | 52.5kg |
| 3 | 8 | 57.5kg |
| 4 | 8 | 62.5kg |

#### Week 5 (3 sets × 2 sessions)

| Set | Reps | Weight |
|-----|------|--------|
| 1 | 8 | 57.5kg |
| 2 | 8 | 62.5kg |
| 3 | 6 | 67.5kg |

---

## Complete Example: Fixed Step Progression + Proportional Reps

**Same athlete and settings as above, but with Proportional Reps**

### Reference 1RM (Linear Interpolation)

| Week | Reference 1RM |
|------|---------------|
| 1 | 70.0kg |
| 2 | 72.2kg |
| 3 | 74.4kg |
| 4 | 76.6kg |
| 5 | 78.75kg |

### Generated Plan

#### Week 1 (3 sets × 2 sessions) — Reference 1RM: 70.0kg

| Set | Weight | % of Ref | Reps |
|-----|--------|----------|------|
| 1 | 42.5kg | 61% | 14 |
| 2 | 45.0kg | 64% | 14 |
| 3 | 47.5kg | 68% | 10 |

#### Week 2 (4 sets × 2 sessions) — Reference 1RM: 72.2kg

| Set | Weight | % of Ref | Reps |
|-----|--------|----------|------|
| 1 | 42.5kg | 59% | 14 |
| 2 | 45.0kg | 62% | 14 |
| 3 | 47.5kg | 66% | 12 |
| 4 | 52.5kg | 73% | 10 |

#### Week 3 (3 sets × 2 sessions) — Reference 1RM: 74.4kg

| Set | Weight | % of Ref | Reps |
|-----|--------|----------|------|
| 1 | 47.5kg | 64% | 14 |
| 2 | 52.5kg | 71% | 10 |
| 3 | 57.5kg | 77% | 8 |

#### Week 4 (4 sets × 2 sessions) — Reference 1RM: 76.6kg

| Set | Weight | % of Ref | Reps |
|-----|--------|----------|------|
| 1 | 47.5kg | 62% | 14 |
| 2 | 52.5kg | 69% | 12 |
| 3 | 57.5kg | 75% | 10 |
| 4 | 62.5kg | 82% | 8 |

#### Week 5 (3 sets × 2 sessions) — Reference 1RM: 78.75kg

| Set | Weight | % of Ref | Reps |
|-----|--------|----------|------|
| 1 | 57.5kg | 73% | 10 |
| 2 | 62.5kg | 79% | 8 |
| 3 | 67.5kg | 86% | 6 |

---

## Complete Example: Compounded Progression + Paired Ladder

**Athlete Test:** 8 reps × 56kg on back squat (same as above)

**Derived values:**
- Derived 1RM: 70kg
- Weekly growth rate: (1.125)^(1/5) - 1 = 2.39%
- Week 5 Projected 1RM: 78.75kg

**Settings:** Same as above, but Weight Strategy: Compounded

### Weekly Anchors (Compounded)

| Week | Projected 1RM | Anchor Reps | Rep % | Anchor Weight |
|------|---------------|-------------|-------|---------------|
| 1 | 70.0kg | 10 | 0.74 | 52.0kg |
| 2 | 71.7kg | 10 | 0.74 | 53.0kg |
| 3 | 73.4kg | 8 | 0.80 | 58.5kg |
| 4 | 75.1kg | 8 | 0.80 | 60.0kg |
| 5 | 78.75kg | 6 | 0.86 | 67.5kg |

### Generated Plan

#### Week 1 (3 sets × 2 sessions)

| Set | Reps | Weight |
|-----|------|--------|
| 1 | 12 | 47.0kg |
| 2 | 12 | 49.5kg |
| 3 | 10 | 52.0kg |

#### Week 2 (4 sets × 2 sessions)

| Set | Reps | Weight |
|-----|------|--------|
| 1 | 12 | 45.5kg |
| 2 | 12 | 48.0kg |
| 3 | 10 | 50.5kg |
| 4 | 10 | 53.0kg |

#### Week 3 (3 sets × 2 sessions)

| Set | Reps | Weight |
|-----|------|--------|
| 1 | 10 | 48.5kg |
| 2 | 10 | 53.5kg |
| 3 | 8 | 58.5kg |

#### Week 4 (4 sets × 2 sessions)

| Set | Reps | Weight |
|-----|------|--------|
| 1 | 10 | 47.5kg |
| 2 | 10 | 52.5kg |
| 3 | 8 | 57.5kg |
| 4 | 8 | 60.0kg |

#### Week 5 (3 sets × 2 sessions)

| Set | Reps | Weight |
|-----|------|--------|
| 1 | 8 | 57.5kg |
| 2 | 8 | 62.5kg |
| 3 | 6 | 67.5kg |

---

## Complete Example: Compounded Progression + Proportional Reps

**Same athlete and settings as above, but with Proportional Reps**

### Reference 1RM (Linear Interpolation)

| Week | Reference 1RM |
|------|---------------|
| 1 | 70.0kg |
| 2 | 72.2kg |
| 3 | 74.4kg |
| 4 | 76.6kg |
| 5 | 78.75kg |

### Generated Plan

#### Week 1 (3 sets × 2 sessions) — Reference 1RM: 70.0kg

| Set | Weight | % of Ref | Reps |
|-----|--------|----------|------|
| 1 | 47.0kg | 67% | 12 |
| 2 | 49.5kg | 71% | 10 |
| 3 | 52.0kg | 74% | 10 |

#### Week 2 (4 sets × 2 sessions) — Reference 1RM: 72.2kg

| Set | Weight | % of Ref | Reps |
|-----|--------|----------|------|
| 1 | 45.5kg | 63% | 14 |
| 2 | 48.0kg | 66% | 12 |
| 3 | 50.5kg | 70% | 10 |
| 4 | 53.0kg | 73% | 10 |

#### Week 3 (3 sets × 2 sessions) — Reference 1RM: 74.4kg

| Set | Weight | % of Ref | Reps |
|-----|--------|----------|------|
| 1 | 48.5kg | 65% | 12 |
| 2 | 53.5kg | 72% | 10 |
| 3 | 58.5kg | 79% | 8 |

#### Week 4 (4 sets × 2 sessions) — Reference 1RM: 76.6kg

| Set | Weight | % of Ref | Reps |
|-----|--------|----------|------|
| 1 | 47.5kg | 62% | 14 |
| 2 | 52.5kg | 69% | 12 |
| 3 | 57.5kg | 75% | 10 |
| 4 | 60.0kg | 78% | 8 |

#### Week 5 (3 sets × 2 sessions) — Reference 1RM: 78.75kg

| Set | Weight | % of Ref | Reps |
|-----|--------|----------|------|
| 1 | 57.5kg | 73% | 10 |
| 2 | 62.5kg | 79% | 8 |
| 3 | 67.5kg | 86% | 6 |

---

## Training Principles Embedded in the System

### Progressive Overload
Weights increase both within sessions (ramping sets) and across weeks. The final week targets the specified percentage improvement over the Derived 1RM.

### Linear Periodisation
The rep ladder moves from high reps/moderate weight to low reps/high weight over the block. This is a proven approach for building strength in novice-to-intermediate athletes.

### Built-in Recovery
Odd weeks have one fewer set, creating natural deload opportunities without interrupting the progression. This undulating volume pattern manages fatigue accumulation.

### Ramping Sets
Within each session, weights increase from the first set to the last. This serves as progressive warm-up toward the heaviest working set and reduces injury risk.

### Relative Loading
The tiered weight jumps (2.5/5/7.5kg) account for the fact that a 5kg jump is much harder at 40kg than at 120kg. This keeps relative intensity consistent across different strength levels.

### Frequency
Two identical sessions per week per exercise provides sufficient practice and stimulus for skill acquisition and strength development.

---

## Mid-Block Adjustments

Coaches may need to adjust a training plan mid-block—for example, if an athlete is progressing faster or slower than expected, or returning from injury. The system must handle these adjustments while protecting historical data.

### Core Requirement: Protect Completed Sessions

**Once an athlete has completed a session, those values are historical and must never change.**

The system must:

1. Mark sessions as "completed" once done
2. Lock all values (weights, reps, sets) for completed sessions
3. Only recalculate future (uncompleted) sessions when changes are made
4. Clearly indicate which sessions are locked vs editable

### Adjustment Scenarios

#### Scenario 1: Change the Target 1RM

The coach decides the original target was too aggressive or too conservative.

| Strategy | Behaviour |
|----------|-----------|
| **Fixed Step** | Final anchor recalculates, then all future weeks recalculate backwards from new target. Completed weeks remain locked. May create a visible "jump" between last completed week and first recalculated week. |
| **Compounded** | Weekly growth rate recalculates for remaining weeks only. Future weeks get new Projected 1RMs. Completed weeks remain locked. Transition is smoother because growth rate adjusts to cover remaining weeks. |

**Example — Target 1RM changed after Week 2 is completed:**

*Fixed Step:* Week 3-5 recalculate backwards from new target. If new target is higher, Week 3 might jump significantly from Week 2.

*Compounded:* New growth rate = ((New Target / Week 2 Projected 1RM) ^ (1/3)) - 1. Weeks 3-5 progress smoothly from where Week 2 ended.

**Compounded handles this better** because it can recalculate from any midpoint.

---

#### Scenario 2: Change a Single Week's Anchor (One-Time)

The coach wants to override Week 3's anchor weight for that week only—future weeks continue from the original plan.

| Strategy | Behaviour |
|----------|-----------|
| **Fixed Step** | Override Week 3's anchor. Weeks 4-5 stay as originally calculated. Creates a visible "gap" in the step pattern (Week 3 doesn't follow the 2.5/5/7.5kg decrement from Week 4). |
| **Compounded** | Override Week 3's anchor. Weeks 4-5 continue from their original Projected 1RMs. Clean separation—each week is independent. |
| **Paired Ladder** | Reps stay as originally calculated (follow the fixed pattern). |
| **Proportional Reps** | Coach chooses: keep original reps OR recalculate reps based on new weight and Reference 1RM. |

**Both weight strategies handle one-time overrides similarly.** The override is stored as an exception.

---

#### Scenario 3: Change a Single Week's Anchor (Carry Forward)

The coach overrides Week 3's anchor weight and wants this change to affect all future weeks, effectively resetting the progression from this point.

| Strategy | Behaviour | Complexity |
|----------|-----------|------------|
| **Fixed Step** | Override Week 3, then recalculate Weeks 4-5 by stepping *forward* from Week 3 (adding 2.5/5/7.5kg per week instead of subtracting). Target 1RM must be recalculated by reversing from the new Week 5 anchor. | High—requires reversing calculation direction |
| **Compounded** | Override Week 3, derive an implied Projected 1RM from the new anchor, recalculate weekly growth rate from Week 3 to Week 5. Weeks 4-5 get new Projected 1RMs. Target 1RM updates automatically. | Moderate—recalculate growth rate from midpoint |

**Fixed Step Carry-Forward Calculation:**

```
New Week 4 Anchor = Week 3 Override + step increment (based on weight bracket)
New Week 5 Anchor = Week 4 Anchor + step increment
New Target 1RM = Week 5 Anchor ÷ Rep Percentage (for anchor reps)
```

**Compounded Carry-Forward Calculation:**

```
Implied Week 3 1RM = Week 3 Override ÷ Rep Percentage (for Week 3 anchor reps)
New Growth Rate = ((Target 1RM / Implied Week 3 1RM) ^ (1 / remaining weeks)) - 1
Week 4 Projected 1RM = Implied Week 3 1RM × (1 + New Growth Rate)
Week 5 Projected 1RM = Week 4 Projected 1RM × (1 + New Growth Rate)
```

**Compounded handles carry-forward more naturally** because the growth rate can be recalculated from any point.

---

#### Scenario 4: Change a Single Set's Weight or Reps

The coach wants to override one specific set within a session.

| Strategy | Behaviour |
|----------|-----------|
| **All combinations** | Manual override only. No ripple effect to other sets or weeks. The override is stored as an exception. |

Set-level overrides are handled identically across all strategy combinations.

---

### Rep Strategy Considerations for Overrides

When using **Proportional Reps**, weight overrides have an additional consideration:

| Override Type | Options |
|---------------|---------|
| **Weight override (anchor or set)** | 1. Keep original reps (weight changes, reps stay) <br> 2. Recalculate reps (reps adjust to match new weight vs Reference 1RM) |
| **Rep override** | Manual override only—stored as exception |

**Recommendation:** Default to "Keep original reps" for one-time overrides, "Recalculate reps" for carry-forward overrides.

When using **Paired Ladder**, weight overrides do not affect reps—the ladder pattern is fixed regardless of weight changes.

---

### Reference 1RM Handling for Overrides

When using **Proportional Reps** with carry-forward overrides, the Reference 1RM for future weeks must also update:

**Original Reference 1RM formula:**
```
Reference 1RM (Week N) = Derived 1RM + ((Target 1RM - Derived 1RM) × (N - 1) / (Total Weeks - 1))
```

**After carry-forward override at Week 3:**
```
Reference 1RM (Week N, where N >= 3) = Implied Week 3 1RM + ((New Target 1RM - Implied Week 3 1RM) × (N - 3) / (Total Weeks - 3))
```

This ensures reps continue to progress smoothly from the override point.

---

### Strategy Comparison for Mid-Block Adjustments

| Scenario | Fixed Step | Compounded | Winner |
|----------|------------|------------|--------|
| Protect completed sessions | Requires explicit locking to prevent backwards recalc | Natural—weeks are independent | Compounded |
| Change Target 1RM | Recalculates backwards, may create jumps | Recalculates growth rate, smooth transition | Compounded |
| One-time weekly override | Creates gap in step pattern | Clean separation | Tie |
| Carry-forward override | Complex—must reverse calculation direction | Moderate—recalculate from midpoint | Compounded |
| Set-level override | Simple exception | Simple exception | Tie |

| Scenario | Paired Ladder | Proportional Reps | Winner |
|----------|---------------|-------------------|--------|
| Weight override | No rep impact | Must decide: keep or recalculate reps | Paired Ladder (simpler) |
| Carry-forward | No additional complexity | Must update Reference 1RM | Paired Ladder (simpler) |
| Flexibility | Fixed pattern | Reps adapt to weight changes | Proportional (more precise) |

---

### Implementation Requirements

The system must provide these controls when a coach makes an override:

**For all overrides:**

1. **Session lock status** — Completed sessions are always locked (enforced, not optional)

**For weekly anchor overrides:**

2. **Scope selection** — "This week only" vs "Carry forward to future weeks"

**For Proportional Reps weight overrides:**

3. **Rep handling** — "Keep original reps" vs "Recalculate reps from new weight"

**UI indicators:**

4. Clearly show which values are:
   - Calculated (from strategy)
   - Manually overridden
   - Locked (completed session)

---

## Edge Cases and Constraints

### Minimum Reps Floor
When the rep ladder reaches the minimum (default 6), it stops descending and repeats the last valid pattern for the remainder of the block.

### Hard Constraint
The rep decrement must never result in reps below 1. This is mathematically impossible and the system should reject such configurations.

### Coach Overrides
All per-exercise inputs can be overridden on a per-athlete basis. The system provides sensible defaults, but the coach's knowledge of individual athletes takes precedence.

---

## Summary of Defaults

| Parameter | Default Value |
|-----------|---------------|
| Block Length | 5 weeks |
| Increment Step | 0.5kg |
| Weight Progression Strategy | Fixed Step |
| Rep Calculation Strategy | Paired Ladder |
| 1RM Modifier | 1.0 |
| Target Improvement | 7.5% |
| Starting Reps | 14 |
| Step-Down Interval | 2 weeks |
| Rep Decrement | 2 |
| Minimum Reps | 6 |
