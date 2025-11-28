# Anchored Progression: A Coach's Guide

This guide explains how the Anchored Progression system automatically calculates weights for your athletes across a training block.

---

## Definition of Terms

Before diving into the calculations, let's define the key terms:

| Term | Definition |
|------|------------|
| **Training Block** | A multi-week training period (e.g., 4 weeks) designed to achieve a specific goal |
| **Week** | A single week within a training block |
| **Session** | A training day within a week (e.g., Monday Upper Body, Wednesday Legs) |
| **Set** | A single bout of exercise repetitions before resting |
| **Anchor Set** | The final (heaviest) set of an exercise - this is the reference point for calculating all other set weights |
| **Anchor Weight** | The weight assigned to the anchor set |
| **Starting Weight** | The baseline weight used to calculate the block's progression (default: 50kg/lbs) |
| **Increase Percentage** | How much heavier the final week's anchor should be compared to the starting weight (default: 7.5%) |
| **Increase Step** | The smallest weight increment available (default: 0.5kg/lbs) |

---

## How It Works: The Three-Step Process

The Anchored Progression system follows three sequential steps:

### Step 1: Reduce Sets on Odd Weeks (Volume Management)

To manage training volume and allow for recovery, the system automatically reduces the number of sets on **odd-numbered weeks** (Week 1, Week 3, etc.).

**What happens:**
- On odd weeks, the **last set** of each exercise is removed
- This means Week 1 and Week 3 have fewer sets than Week 2 and Week 4

**Example - 4 Week Block:**

| Week | Number of Sets |
|------|---------------|
| Week 1 | 3 sets |
| Week 2 | 4 sets |
| Week 3 | 3 sets |
| Week 4 | 4 sets |

**Why this matters:** This creates a wave-like pattern of training stress, giving the athlete lighter weeks built in for recovery.

---

### Step 2: Calculate Weekly Anchor Weights

The system calculates the anchor weight (heaviest set) for each week, working **backwards from the final week**.

#### Calculating the Final Week's Target Weight

First, the system determines what the anchor weight should be in the **last week** of the block:

```
Final Anchor Weight = Starting Weight + (Starting Weight × Increase Percentage)
```

The result is then rounded down to the nearest increment step.

**Example:**
- Starting Weight: 50kg
- Increase Percentage: 7.5%
- Increase Step: 0.5kg

Calculation:
```
Target = 50 + (50 × 0.075) = 50 + 3.75 = 53.75kg
Rounded to nearest 0.5kg = 53.5kg
```

#### Working Backwards for Each Week

Once the final week's anchor is set, the system works **backwards** to calculate each previous week. The weight decreases as you go back in time using these rules:

| Current Weight | Decrease Per Week |
|---------------|-------------------|
| Above 100kg | 7.5kg |
| 50kg - 100kg | 5.0kg |
| Below 50kg | 2.5kg |

**Example - 4 Week Block with 53.5kg Final Anchor:**

| Week | Calculation | Anchor Weight |
|------|-------------|---------------|
| Week 4 | Final target | **53.5kg** |
| Week 3 | 53.5 - 5.0 | **48.5kg** |
| Week 2 | 48.5 - 2.5 | **46.0kg** |
| Week 1 | 46.0 - 2.5 | **43.5kg** |

**Note:** The decrease amounts change based on the weight bracket you're in. In this example, Week 4 to Week 3 uses a 5kg decrease (because 53.5 is between 50-100), but Week 3 to Week 2 uses 2.5kg (because 48.5 is below 50).

---

### Step 3: Calculate All Set Weights Within Each Week

Once the anchor weight is known for each week, the system calculates the weight for every other set **working backwards from the anchor**.

The same decrease rules apply:

| Current Weight | Decrease Per Set |
|---------------|------------------|
| Above 100kg | 7.5kg |
| 50kg - 100kg | 5.0kg |
| Below 50kg | 2.5kg |

**Example - Week 4 with 4 Sets (Anchor = 53.5kg):**

| Set | Calculation | Weight |
|-----|-------------|--------|
| Set 4 (Anchor) | Anchor weight | **53.5kg** |
| Set 3 | 53.5 - 5.0 | **48.5kg** |
| Set 2 | 48.5 - 2.5 | **46.0kg** |
| Set 1 | 46.0 - 2.5 | **43.5kg** |

**Example - Week 1 with 3 Sets (Anchor = 43.5kg):**

| Set | Calculation | Weight |
|-----|-------------|--------|
| Set 3 (Anchor) | Anchor weight | **43.5kg** |
| Set 2 | 43.5 - 2.5 | **41.0kg** |
| Set 1 | 41.0 - 2.5 | **38.5kg** |

---

## Complete Example: 4-Week Training Block

Let's put it all together with a complete example.

**Settings:**
- Starting Weight: 50kg
- Increase: 7.5%
- Block Length: 4 weeks
- Default Sets: 4

### Week-by-Week Breakdown

#### Week 1 (3 sets - odd week reduction)
| Set | Weight |
|-----|--------|
| Set 1 | 38.5kg |
| Set 2 | 41.0kg |
| Set 3 | 43.5kg |

#### Week 2 (4 sets - full volume)
| Set | Weight |
|-----|--------|
| Set 1 | 38.5kg |
| Set 2 | 41.0kg |
| Set 3 | 43.5kg |
| Set 4 | 46.0kg |

#### Week 3 (3 sets - odd week reduction)
| Set | Weight |
|-----|--------|
| Set 1 | 41.0kg |
| Set 2 | 43.5kg |
| Set 3 | 48.5kg |

#### Week 4 (4 sets - full volume)
| Set | Weight |
|-----|--------|
| Set 1 | 43.5kg |
| Set 2 | 46.0kg |
| Set 3 | 48.5kg |
| Set 4 | 53.5kg |

---

## Key Takeaways for Coaches

1. **Progressive Overload**: The system ensures athletes lift heavier weights each week, with the final week being approximately 7.5% heavier than the starting point.

2. **Built-in Deloads**: Odd weeks have one fewer set, creating natural recovery opportunities within the training block.

3. **Ramping Sets**: Within each session, weights increase from the first set to the last, allowing athletes to warm up progressively toward their heaviest working weight.

4. **Tiered Weight Jumps**: The weight jumps between sets/weeks are larger for heavier loads (7.5kg when above 100kg) and smaller for lighter loads (2.5kg when below 50kg). This accounts for the fact that 5kg is a much bigger relative jump at 40kg than at 120kg.

5. **The Anchor Point**: All calculations flow from the final week's anchor weight. This "goal weight" determines everything else in the block by working backwards.

---

## Adjusting the System

You can customise the progression by changing three values:

| Setting | What It Controls | Default |
|---------|-----------------|---------|
| **Start** | The baseline weight for calculations | 50 |
| **Increase** | Percentage gain over the block | 7.5% |
| **Increase Step** | Smallest weight increment | 0.5 |

**Example adjustments:**
- For a more aggressive block, increase the percentage to 10%
- For a newer athlete, reduce the starting weight to 30kg
- If your gym only has 2.5kg plates, set the increase step to 2.5
