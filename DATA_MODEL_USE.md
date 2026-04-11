# How Data Works in ARS Athlete Training

This document explains every feature in the application, how data flows through it, and why it works the way it does. It's written so anyone — developer, product person, or new team member — can understand the full picture.

---

## Table of Contents

1. [The Two Worlds: Coach and Athlete](#1-the-two-worlds-coach-and-athlete)
2. [People and Teams](#2-people-and-teams)
3. [The Exercise Library](#3-the-exercise-library)
4. [Labeling and Organizing (Tags)](#4-labeling-and-organizing-tags)
5. [Exercise Settings: What Gets Tracked](#5-exercise-settings-what-gets-tracked)
6. [Smart Calculations: When the System Does the Math](#6-smart-calculations-when-the-system-does-the-math)
7. [Programs: Grouping Exercises Into Sessions](#7-programs-grouping-exercises-into-sessions)
8. [Plans: The Blueprint](#8-plans-the-blueprint)
9. [Assigning Training to a Team](#9-assigning-training-to-a-team)
10. [The Calendar: Scheduling and Viewing](#10-the-calendar-scheduling-and-viewing)
11. [Training Blocks: Marking Phases on the Calendar](#11-training-blocks-marking-phases-on-the-calendar)
12. [Customization: The Override Chain](#12-customization-the-override-chain)
13. [The Training Grid: The Heart of the UI](#13-the-training-grid-the-heart-of-the-ui)
14. [Athlete Metrics: Measuring Progress](#14-athlete-metrics-measuring-progress)
15. [The Athlete Experience](#15-the-athlete-experience)
16. [Data Import and External Exercises](#16-data-import-and-external-exercises)
17. [Ownership and Multi-Coach Support](#17-ownership-and-multi-coach-support)
18. [API Endpoints: Powering the Calendar](#18-api-endpoints-powering-the-calendar)
19. [End-to-End: A Complete Training Cycle](#19-end-to-end-a-complete-training-cycle)

---

## 1. The Two Worlds: Coach and Athlete

The app has two completely separate experiences depending on who logs in.

### The Coach Side (`/admin/...`)

Coaches see a full management interface. They build exercises, assemble programs, schedule training, track metrics, and customize plans for individual athletes. The coach side is where all the planning and configuration happens.

**What coaches can do:**
- Build and manage an exercise library with videos, instructions, and photos
- Create programs (workout sessions) from those exercises
- Design multi-week plans with weekly schedules
- Assign plans to groups of athletes
- Customize training down to individual cells in a grid (e.g. "Athlete A does 6 reps in week 3, set 2")
- Record athlete measurements (1RM, heart rate)
- View a calendar overview of who is training what and when
- Mark training phases on the calendar (e.g. "Hypertrophy block, Jan-Feb")

### The Athlete Side (`/dashboard/...`)

Athletes see a simple, focused mobile-friendly interface. They check in, see what's on their schedule, and view the details of each session — exercises, sets, reps, weights, rest times, videos, and photos.

**What athletes can do:**
- Submit a daily readiness check (Ready / Train Smart / Recovery / Rest)
- Browse their schedule by day or week
- View program details with every exercise laid out in a table
- Watch instructional videos and browse exercise photos
- See their personalized training numbers (reps, weight, pace, etc.)

---

## 2. People and Teams

### Users

Every person in the system is a User with one of three roles:

| Role     | What they do                                              | Where they go after login |
|----------|-----------------------------------------------------------|---------------------------|
| Coach    | Builds training, manages athletes, records metrics        | `/admin/programs`         |
| Athlete  | Views their schedule and training details                 | `/dashboard`              |
| Admin    | Full access to everything                                 | `/admin/programs`         |

Each user has a profile: name, email, phone, gender, date of birth, and a color. The color is used throughout the UI to visually identify coaches and their content.

**Where this shows up:** The coach and athlete lists in the admin panel (`/admin/coaches`, `/admin/athletes`). Coaches can search, filter, add, edit, and delete users. Password management is also handled here.

### Groups

Athletes are organized into named groups (teams). A group is an ordered list of athletes.

**Why groups matter:** Groups are the unit of assignment. When a coach wants to give training to athletes, they assign it to a group. Everyone in that group gets the same base program, but individual athletes can then receive customized versions.

**Where this shows up:** The group list (`/admin/athletes/groups`), and critically, the sidebar on the calendar page. The coach selects a group in the sidebar, then everything on the calendar — programs, blocks, metrics — is scoped to that group.

---

## 3. The Exercise Library

The exercise library is where coaches build and manage their collection of movements.

### Exercises

An exercise represents a single movement or activity: "Back Squat", "5km Run", "Plank Hold", etc.

**What an exercise stores:**
- **Name** — what to call it
- **Category** — where it fits in a hierarchy (e.g. Strength > Legs > Quad Dominant)
- **Equipment tags** — what gear is needed (barbell, dumbbells, resistance band)
- **Modifier tags** — variations (paused, banded, tempo, single-leg)
- **Video URL** — an instructional YouTube video
- **Instructions** — free-text coaching cues
- **Photos** — image gallery (stored via Spatie Media Library)
- **Config** — the detailed settings for this exercise (covered in section 5)

**Where this shows up:** The exercise list page (`/admin/exercises`) is a searchable, filterable table. Coaches can filter by category, equipment, modifiers, and internal tags. Each exercise can be clicked to open a full edit form. The form has a two-column layout: settings on the left, a live preview grid on the right.

### Exercise Templates

Templates are reusable presets for exercise configuration. A coach might create a "Standard Strength Template" with 4 sets, reps starting at 10, weight in automatic mode. Then when creating new exercises, they pick the template and the config fills in automatically.

**The benefit:** Saves time and enforces consistency. Instead of configuring every exercise from scratch, coaches apply a template and tweak from there.

**Where this shows up:** The templates tab on the exercise page (`/admin/exercises/templates`). Templates can be duplicated to create variations. When editing an exercise, a template dropdown is available — selecting one pre-fills the config.

### Categories

Categories are a hierarchical tree: top-level categories like "Strength" and "Conditioning", with children like "Upper Body > Chest", "Lower Body > Glutes", etc. Each category has a name, short name, color, and sort order.

**The benefit:** Categories drive color-coding throughout the entire app. When a program has a category of "Strength", it shows as a colored badge on the calendar, in program lists, and in the athlete's view. This gives everyone an instant visual indicator of what type of training is happening.

**Where this shows up:** The categories tab on the exercise page (`/admin/exercises/categories`) shows the full tree. Coaches can add children, reorder, edit colors, and delete (with an impact analysis showing how many exercises would be affected).

---

## 4. Labeling and Organizing (Tags)

Tags are a general-purpose labeling system. They're used in six different contexts:

| What gets tagged | Tag type               | Example tags                     | Benefit                                           |
|------------------|------------------------|----------------------------------|---------------------------------------------------|
| Exercises        | Equipment              | Barbell, Dumbbell, Cable Machine | Athletes know what gear they need                 |
| Exercises        | Modifiers              | Paused, Banded, Tempo, Single-Leg | Coaches can find exercise variations quickly      |
| Exercises        | Internal tags          | "Needs Review", "Favourite"      | Private coach-only organization                   |
| Programs         | Internal tags          | "Competition Prep", "Off-Season" | Private coach-only organization                   |
| Athletes         | Internal tags          | "Injury: Shoulder", "U18"       | Filter and group athletes by any criteria         |
| Groups           | Internal tags          | "Active", "Pre-Season Roster"   | Filter and organize groups                        |

**Where this shows up:** Equipment and modifiers are managed under their own tabs on the exercise page. Internal tags appear on all list pages and can be used as filters. On the athlete's view, equipment and modifiers show as colored badges on each exercise.

---

## 5. Exercise Settings: What Gets Tracked

This is where exercises get interesting. The "config" on an exercise determines what numbers the coach wants to track and how they behave.

### Available Settings

A coach enables whichever settings are relevant to the exercise. A squat might track sets, reps, weight, tempo, and rest. A run might track distance, duration, pace, and heart rate.

| Setting          | What it means to the athlete                                  |
|------------------|---------------------------------------------------------------|
| **Sets**         | How many rounds of the exercise to do (always active)         |
| **Reps**         | How many repetitions per set                                  |
| **Weight**       | How much to lift (kg)                                         |
| **Tempo**        | The speed pattern for the movement (e.g. "3010" = 3s down, 0 pause, 1s up, 0 pause) |
| **Rest**         | How long to rest between sets (seconds)                       |
| **Distance**     | How far to go (meters or kilometers)                          |
| **Duration**     | How long to go (seconds, minutes, or mm:ss format)            |
| **Pace**         | How fast to go (mm:ss per km)                                 |
| **Watts**        | Power output for cycling/rowing                               |
| **Heart Rate**   | Target beats per minute                                       |
| **HR Zone**      | Target heart rate zone (0-4)                                  |

### Per-Session vs. Per-Week

Each setting can apply at two levels of detail:

- **Per session** (default): Every cell in the training grid can have its own value. Week 1 Set 1 might be 10 reps, Week 3 Set 2 might be 6 reps.
- **Per week**: One value applies to the whole week. For example, rest might be 90s all of week 1, then 120s all of week 2.

**The benefit:** Per-session gives full control for things like reps and weight that vary across sets. Per-week keeps things simple for values that don't change within a session, like rest periods or tempo.

### Deload Weeks

Sets have a special "deload" option. When enabled, the system automatically reduces the number of sets on alternating weeks (odd or even). This gives athletes built-in recovery weeks without the coach having to manually edit every grid cell.

**The benefit:** The coach configures it once and every relevant week automatically drops to fewer sets.

---

## 6. Smart Calculations: When the System Does the Math

Three settings have "automatic" modes where the system calculates values instead of requiring manual entry.

### Automatic Reps

When reps are set to automatic, the system generates a progressive rep scheme across weeks. It starts at a given number and decrements over time. It also uses a "paired" pattern: the first half of sets get higher reps, the second half get lower reps.

**Example:** A 4-set exercise might show 10, 10, 8, 8 reps in week 1, then 8, 8, 6, 6 in a later week.

**The benefit:** The coach sets one number and the system builds an intelligent progression that naturally reduces volume over the training block.

### Automatic Weight (1RM-Based)

This is the most powerful automatic feature. When weight is set to automatic:

1. The coach records the athlete's current strength measurement (e.g. "Athlete did 5 reps at 100kg")
2. The system estimates the athlete's one-rep max (1RM) from that data
3. The coach sets a target goal (e.g. "+10%")
4. The system calculates progressive weights across all weeks, working backward from the target

The weight steps are rounded to practical increments: 2.5kg steps below 55kg, 5kg steps up to 107.5kg, and 7.5kg steps above that.

**The benefit:** The entire weight progression is generated from a single measurement and a goal. If the athlete gets retested, changing one number updates every weight in every week.

### Automatic Heart Rate (Norwegian Model)

When heart rate is set to automatic, the system uses the athlete's max heart rate and anaerobic threshold percentage to calculate target heart rates. It supports two activity-specific zone tables: biking and jogging.

Each zone gets a color in the training grid so athletes can instantly see the intensity level.

**The benefit:** Zone-based training with precise BPM targets calculated from actual test data, not guesswork.

---

## 7. Programs: Grouping Exercises Into Sessions

A program represents a single training session — a collection of exercises that an athlete does together.

### What a program contains

- **Name** — "Monday Upper Body", "Thursday Intervals"
- **Category** — determines the color badge (e.g. Strength = blue, Conditioning = green)
- **Exercises** — an ordered list of exercises, each with a sort position and optional superset group
- **Warm-up program** — an optional reference to another program to do first
- **Cool-down program** — an optional reference to another program to do after
- **Config** — program-level overrides and schedule configuration

### Supersets

Exercises within a program can be grouped into supersets using a letter label (A, B, C...). Exercises with the same letter are performed together in alternating fashion.

```
Program "Monday Upper Body"
  1. Bench Press
  2. Incline DB Press    [A]  ← superset
  3. Cable Fly           [A]  ← superset
  4. Tricep Pushdown
```

**The benefit:** Athletes see which exercises to pair together. The UI groups them visually.

**Where this shows up:** The program editor on the calendar's Plan view. Coaches drag exercises to reorder them and assign group letters. The program list (`/admin/programs`) shows all programs with search, category filtering, and ownership tabs (My / All / Other Coaches).

---

## 8. Plans: The Blueprint

A plan is a complete training blueprint that a coach designs before assigning it to anyone. It's a template — it defines what programs happen on which days of the week, across how many weeks.

### Schedule

The plan contains a weekly schedule with 7 days and 2 slots per day (AM and PM). Each slot can hold one or more programs.

```
Week Template:
  Monday AM:     Strength Upper
  Wednesday AM:  Strength Lower
  Friday AM:     Conditioning
  Friday PM:     Mobility
```

### Linked Weeks

Plans typically run for multiple weeks (default is 5). The coach defines one "template week" and then links the other weeks to it. Linked weeks automatically follow the same program layout.

If a coach needs week 4 to be different (say, a deload week with fewer sessions), they unlink that week and edit it independently.

**The benefit:** Define the pattern once, repeat it across the block. Only customize the weeks that differ.

### Targets

The plan stores global target values for automatic weight calculation:

- **Measured reps** — how many reps the baseline test was done at
- **Measured weight** — what weight was used for that test
- **Target goal** — the percentage improvement to aim for

These feed into the automatic weight calculation for every exercise that uses automatic weight mode.

---

## 9. Assigning Training to a Team

When a coach is ready to give a plan to a group of athletes, they assign it. This is where the plan stops being a template and becomes live training.

### What Happens During Assignment

1. Each program in the plan is **duplicated**. The original stays in the plan library untouched. The copy becomes the group's working version.
2. Any grid overrides the coach made at the plan level are saved as a "baseline" on the copy. This lets the UI later show what's changed vs. the original.
3. The duplicate is linked to a new **Training Program** record that connects it to the group.

**Why duplication matters:** The coach can keep refining the original plan and assign it to other groups later. Each group gets its own independent copy that can be customized without affecting anyone else.

### Bypassing Plans

Coaches don't have to use plans. They can also:
- Import a single program directly into a group
- Import a single exercise (which auto-wraps it in a new program)

**Where this shows up:** On the calendar page, the "Add Program" button lets coaches search and import programs or exercises directly.

### Scheduling (Slots)

Once a group has programs assigned, the coach schedules them. A **slot** is a specific date, time, and set of athletes for a program.

The calendar's Schedule view shows a week grid. Coaches click cells to create slots, choosing the program, time, and which athletes in the group should attend. In Edit mode, they can select a program and click cells to quickly fill in the schedule. In Remove mode, clicking a cell deletes the slot.

**The benefit:** The coach builds the full week schedule visually, assigning specific athletes to specific sessions.

---

## 10. The Calendar: Scheduling and Viewing

The calendar (`/admin/calendar`) is the coach's command center. It has three views that serve different purposes.

### Overview View

A bird's-eye grid showing all groups and athletes with color-coded cells. Each cell represents one day for one athlete, filled with gradient colors showing what categories of training are scheduled.

**The benefit:** At a glance, the coach sees training distribution across the entire roster. Too much blue (strength) for one athlete? Too many empty cells? This view reveals it.

Coaches can expand a group to see individual athletes, and click on any cell to drill into the details.

### Schedule View

A week grid focused on one group or one athlete. Days are columns, AM/PM are rows. Each cell shows the scheduled programs with their times and names.

**Modes:**
- **View mode** — see what's scheduled
- **Edit mode** — select a program from a dropdown, then click cells to assign it
- **Remove mode** — click cells to delete slots

**The benefit:** Fast, visual weekly scheduling. The coach can fill in a whole week in seconds by clicking.

### Plan View

The detailed training programming view. This is where the coach works with the exercise grids — seeing and editing every set, rep, and weight across weeks.

**What the Plan view shows:**
- A dropdown to select the program to work on
- A dropdown to select the relevant training block (phase)
- The program editor: exercises with drag-drop reordering, warm-up/cool-down selection
- The exercise grids: multi-week tables showing calculated and overridden values
- Athlete metrics: current 1RM and heart rate measurements displayed as badges
- The block goal: what percentage improvement this phase targets

**The benefit:** Everything the coach needs to fine-tune training in one place. They can see the numbers, edit individual cells, change settings, and the grids update live.

### Sidebar

All calendar views share a left sidebar for selecting groups and athletes. Coaches can search, filter by ownership (my groups / all), and switch between group view and individual athlete view.

When viewing an individual athlete, the Plan view shows that athlete's personalized grid — their specific overrides and measurements applied to the base program.

---

## 11. Training Blocks: Marking Phases on the Calendar

Blocks are date-range markers on the calendar that define training phases.

### Block Types

| Type       | What it does                                                    | Visual                |
|------------|-----------------------------------------------------------------|-----------------------|
| Category   | Marks a training focus period for a category (e.g. "Hypertrophy for Chest, Jan 1 - Feb 15") | Colored background band |
| Note       | A free-text note spanning dates                                 | Text on calendar      |
| Focus      | A general training focus period                                 | Amber background      |

### Category Blocks

These are the most important type. A category block says "during this period, this category of exercise is the focus." It carries:

- **Goal** — a target percentage improvement (e.g. 10%)
- **Auto-record 1RM** — whether to automatically generate projected 1RM targets for athletes

**The benefit:** The block goal drives the automatic weight calculation for every exercise in that category during that period. The coach sets "10% improvement" on the block, and every exercise grid within it calculates weights aiming for that target.

### Session Numbering

Within a category block, the calendar tracks session numbers. If the block runs for 6 weeks with 3 sessions per week, each program cell shows "Session 1", "Session 2", up to "Session 18". This tells coaches and athletes where they are in the progression.

### Per-Athlete Block Overrides

Blocks apply to the whole group by default, but any block can be overridden for a specific athlete:

- Different start/end dates (an athlete who was injured might start the block later)
- Different notes
- Disabled entirely (`active: false` — the block doesn't apply to this athlete)

**The benefit:** One block covers the group, but exceptions for individuals are handled without creating separate blocks.

**Where this shows up:** On the Programs View of the calendar, blocks appear as colored background bands behind the program rows. Coaches right-click or use buttons to create/edit blocks. The Block Form modal lets them set dates, notes, categories, goals, and optionally assign to specific athletes.

---

## 12. Customization: The Override Chain

This is the core concept that makes the whole system flexible. Exercise settings flow through three layers, and each layer can override the one above it.

### The Three Layers

```
Layer 1: EXERCISE (the library default)
   "Back Squat: 3 sets, 10 reps, 50kg manual"
         │
         ▼
Layer 2: PROGRAM (the group default)
   "In Monday Strength: 4 sets, 8 reps, automatic weight"
         │
         ▼
Layer 3: ATHLETE (the individual exception)
   "For Athlete A: 6 reps (everything else from program)"
         │
         ▼
   WHAT THE ATHLETE SEES: 4 sets, 6 reps, automatic weight
```

### Layer 1: Exercise Default

When a coach creates an exercise in the library, they set up its default configuration: which settings are active, what the defaults are, whether weight is manual or automatic, etc.

**This is the starting point.** If nothing overrides it, every program that uses this exercise gets these defaults.

### Layer 2: Program Override

When an exercise is inside a program, the coach can override any aspect of its config for that program specifically. Common overrides:

- Changing the number of reps or sets
- Switching weight from manual to automatic (or vice versa)
- Enabling or disabling settings (e.g. adding tempo tracking for a strength program)
- Editing specific cells in the grid (e.g. changing week 3, set 2 from 80kg to 85kg)
- Disabling the exercise entirely (it stays in the program but is hidden from athletes)

**This applies to all athletes in the group.** Everyone sees these overrides unless they have their own.

### Layer 3: Athlete Override

When a coach selects a specific athlete in the sidebar and edits the exercise grid, those changes only apply to that one athlete. Everything they don't change falls through to the program override (layer 2) or the exercise default (layer 1).

**This is the most specific level.** It lets coaches personalize training for individuals while keeping the group program as the baseline.

### How It Resolves

The system merges layers top-to-bottom:
1. Start with the exercise's base config
2. Apply program overrides on top (any non-null setting replaces the base)
3. Apply athlete overrides on top (any non-null setting replaces the program level)

For individual grid cells, the merge is additive: if the program overrides week 1 and the athlete overrides week 3, both overrides apply. If they both override the same cell, the athlete's value wins.

**Where this shows up:** The Plan view on the calendar. When the coach has a group selected, they see and edit Layer 2 (program overrides). When they select an individual athlete, they see and edit Layer 3 (athlete overrides). Overridden cells are highlighted in green so the coach can see what's been customized.

---

## 13. The Training Grid: The Heart of the UI

The training grid is the central UI element that coaches and athletes interact with. It's a table with weeks as columns and rows for each tracked setting.

### Grid Structure

```
           │ Week 1      │ Week 2      │ Week 3      │
           │ S1  S2  S3  │ S1  S2  S3  │ S1  S2  S3  │
───────────┼─────────────┼─────────────┼─────────────┤
Sets       │  4   4   4  │  3   3   3  │  4   4   4  │  ← deload in week 2
Reps       │ 10  10   8  │ 10  10   8  │  8   8   6  │  ← paired pattern
Weight(kg) │ 60  60  65  │ 65  65  70  │ 70  70  75  │  ← progressive
Rest(s)    │     90      │     90      │    120      │  ← per-week
```

### How Grid Values Are Calculated

The system runs calculation strategies in a specific order:

1. **Sets** — applies deload logic (reduces sets on odd/even weeks if configured)
2. **Reps** — applies the paired rep strategy if in automatic mode
3. **Weight** — applies 1RM-based progression if in automatic mode
4. **Heart Rate Zones** — fills zone values
5. **Heart Rate** — applies Norwegian intensity model if in automatic mode

After strategies run, any grid overrides are applied on top.

### Editing Grid Cells

Coaches click a cell to edit it inline. The input type, validation, and step size adapt to the setting type (numbers for weight, text patterns for tempo, etc.). When a coach edits a cell, it creates a grid override at the appropriate layer (program or athlete).

Each cell has a color based on its setting type, and overridden cells get a green highlight. The coach can visually scan the grid and see exactly what's been customized.

### Grid Actions

- **Copy week** — copy all override values from one week to another
- **Reset overrides** — clear all customizations and return to calculated values
- **Disable exercise** — hide it from athletes without removing it from the program

**Where this shows up:** On the coach side, the Plan view shows grids for every exercise in the program. On the athlete side, the Program Details page shows the same grid but read-only, displaying only the athlete's personalized effective values.

### Summary Row

When automatic weight is active, the grid shows a summary above it:

- **Starting 1RM** — the athlete's current estimated one-rep max (orange badge)
- **Target 1RM** — what they're working toward (green badge)
- **Goal %** — the improvement target (e.g. "+10%")
- **1RM Modifier** — the offset from baseline (e.g. "+20%")

---

## 14. Athlete Metrics: Measuring Progress

Metrics are real-world measurements recorded by coaches. They feed back into the automatic calculations that drive the training grids.

### Metric Types

| Metric     | What gets recorded                    | What gets calculated               | How it's used                    |
|------------|---------------------------------------|-------------------------------------|----------------------------------|
| One-Rep Max (1RM) | Reps performed + weight lifted | Estimated 1RM (using conversion table) | Drives automatic weight mode    |
| Heart Rate | Max heart rate + anaerobic threshold % | HR zone boundaries                  | Drives automatic HR mode         |

### Recording Metrics

On the athlete list (`/admin/athletes`), each athlete shows their latest metrics as badges. Clicking opens a form to record new measurements.

On the calendar's Plan view, metrics are displayed as editable badges next to each exercise. The coach can see the athlete's current 1RM and heart rate values and update them without leaving the page.

In the calendar's Programs view, a collapsible metrics section shows metric values for each program over time. Coaches can click cells to add or edit metrics for specific dates.

**The benefit:** Metrics are visible everywhere they're relevant. Updating one measurement immediately recalculates every grid that depends on it.

### Manual vs. Projected

- **Manual metrics** are recorded by a coach from a real-world test. They're tied to the athlete directly.
- **Projected metrics** are auto-generated targets tied to training blocks. When a category block has "auto-record 1RM" enabled, the system generates projected 1RM values for each athlete based on the block's goal percentage.

### Metric History

The metric index page (`/admin/athletes/{id}/metrics`) shows the full history of submissions for an athlete, organized by tabs for each metric type and sorted by date.

---

## 15. The Athlete Experience

Athletes see a completely different, simplified interface designed for mobile use.

### Daily Readiness

When an athlete opens the app, they see a readiness check. This is a simple 1-4 scale:

| Score | Label       | Color  | Meaning                          |
|-------|-------------|--------|----------------------------------|
| 4     | Ready       | Green  | Good to train as programmed      |
| 3     | Train Smart | Amber  | Can train but should be mindful  |
| 2     | Recovery    | Orange | Light activity only              |
| 1     | Rest        | Red    | Take the day off                 |

The readiness score shows as a circular gauge throughout the athlete's dashboard. Optionally, the app can require readiness submission before showing the day's training (controlled by the `athlete.require_readiness` config).

### Day and Week Views

The athlete's calendar (`/dashboard/calendar/day/{date}` and `/dashboard/calendar/week/{date}`) shows scheduled sessions organized by AM and PM. Each session is a card showing:

- The time
- The program name
- A colored badge for the category
- The number of exercises
- A tap target to view full details

Week view shows multiple days at once with navigation arrows. Day view shows a single day with a date picker.

### Program Details

When an athlete taps on a program (`/programs/{date}/{trainingProgram}`), they see every exercise laid out:

- Exercise name and category badge
- Equipment and modifier badges
- A data table with rows for each tracked setting and columns for each set
- Week-level values (rest, tempo) shown above the set table
- Notes in gray boxes
- Instructions text
- A video link that opens a YouTube player modal
- A photo link that opens a swipeable image gallery

The numbers shown are the **effective values** after all three override layers have been resolved. The athlete never sees the layering — they just see their personalized training.

### Exercise Gallery

Exercise photos are displayed in a full-screen gallery modal with:
- Swipe gestures for navigation (touch-friendly)
- Thumbnail carousel at the bottom
- Image counter ("1 / 5")
- Previous/Next buttons

---

## 16. Data Import and External Exercises

### External Exercise Library

The app connects to an external exercise database. Coaches can browse, search, and import exercises from it.

**Where this shows up:** The Import tab on the exercise page (`/admin/exercises/import`). Coaches see a searchable list of external exercises with categories, equipment, modifiers, and YouTube video previews. Clicking "Import" opens the exercise form pre-filled with the external data, ready to be saved as a local exercise.

### Bulk Import Commands

Several console commands support data migration:

- `exercise:import` — imports exercises from JSON files
- `exercises:export` — exports exercises to files
- `kilo:parse` — parses exercise data from Kilo PDF exports (with YouTube links)
- `db:live-import` — imports from a remote database via SSH
- `db:export` — exports business data to PHP files

---

## 17. Ownership and Multi-Coach Support

Every record in the system has an owner — the coach who created it. This enables multi-coach use:

- **Data filtering:** Lists show "My" / "All" / "Other Coaches" tabs. By default, coaches see their own content.
- **Visual identification:** Each coach has a color. Exercises, programs, athletes, and groups show the owner's name and color badge so it's clear who created what.
- **Independence:** Multiple coaches can use the system simultaneously. Their exercises and programs don't interfere with each other.

**Where this shows up:** Every list page (exercises, programs, athletes, groups) has ownership tabs at the top and a coach filter. The sidebar on the calendar also has a group ownership filter.

---

## 18. API Endpoints: Powering the Calendar

The calendar's interactive features are driven by a set of JSON API endpoints. These power the popovers, cell data, and color indicators that make the calendar responsive without full page reloads.

| Endpoint | What it returns | Used for |
|----------|----------------|----------|
| `/admin/api/slot-details` | Time-grouped slot details for a day | Popover when hovering a calendar cell — shows who's training at what time |
| `/admin/api/slot-week-page` | Full week schedule with AM/PM grouping | The Schedule view's week grid data |
| `/admin/api/slot-member-colors` | Activity count by color per athlete per day | The Overview grid's color gradient cells |
| `/admin/api/program-grid-cells` | Cell data with session counts and numbers | The Programs view's grid cells — shows how many sessions and session numbering |
| `/admin/api/user-day-slots` | A single athlete's programs for a day | Popover when hovering an athlete's cell — shows their specific schedule |

These endpoints are fetched asynchronously by Alpine.js components (`calendar_slot_popover`, `metric_cell_popover`) to show contextual information on hover.

---

## 19. End-to-End: A Complete Training Cycle

Here's how all the pieces fit together in a real coaching workflow:

### 1. Set Up the Exercise Library

The coach creates exercises in the library, assigning categories, equipment, modifiers, videos, and photos. They configure each exercise with the appropriate settings — reps and weight for strength, distance and pace for running, etc. They may use templates to speed this up.

### 2. Build Programs

The coach groups exercises into programs representing individual sessions. They order the exercises, set up supersets, and optionally link warm-up and cool-down programs.

### 3. Assign to a Group

The coach imports programs into a group. The system duplicates each program so the group gets its own copy. The coach can also import entire plans.

### 4. Schedule the Week

On the calendar's Schedule view, the coach fills in the week: which program on which day, at what time, for which athletes.

### 5. Set Up Training Blocks

The coach creates category blocks on the calendar marking the training phase. They set the block's goal (e.g. 10% improvement) and optionally enable auto-record 1RM.

### 6. Record Measurements

The coach records each athlete's 1RM and/or heart rate measurements. These feed into the automatic calculations.

### 7. Customize the Grids

On the Plan view, the coach reviews the auto-generated grids. They might:
- Override specific cells for the whole group (program level)
- Switch to an individual athlete and adjust their numbers (athlete level)
- Enable or disable specific exercises for specific athletes

### 8. Athletes Train

Athletes open the app, check their readiness, see their schedule for the day, and tap into each program to see their personalized exercises, weights, reps, and everything else. They can watch instructional videos and browse exercise photos.

### 9. Measure and Iterate

After the training block, the coach retests athletes and records new measurements. The new data flows into the next block's calculations, driving the progression forward.

```
┌─────────────┐     ┌──────────────┐     ┌──────────────┐
│  Build       │────▶│  Assign &    │────▶│  Customize   │
│  Library     │     │  Schedule    │     │  Grids       │
└─────────────┘     └──────────────┘     └──────┬───────┘
                                                 │
                                                 ▼
┌─────────────┐     ┌──────────────┐     ┌──────────────┐
│  Record New  │◀────│  Athletes    │◀────│  Record      │
│  Metrics     │     │  Train       │     │  Metrics     │
└──────┬──────┘     └──────────────┘     └──────────────┘
       │
       └──────▶ Next training block (repeat)
```

The cycle is: **Build → Assign → Customize → Measure → Train → Re-Measure → Repeat.**
