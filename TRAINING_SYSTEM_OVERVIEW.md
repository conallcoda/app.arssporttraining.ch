# Training Planning System Overview

## Architecture Summary

The system uses a **hierarchical tree structure** with this relationship:

```
Block (Training Period)
  └── Week 1 (Primary/Source Week)
      ├── Session 1 (day 0, slot 0)
      │   ├── Exercise 1
      │   │   ├── Set 1 (reps/weight)
      │   │   └── Set 2
      │   └── Exercise 2
      └── Session 2 (can be linked to Session 1)
  └── Week 2 (can be linked to Week 1)
  └── Week 3...
```

---

## Key Components

### 1. BlockCreator.php (`app/Livewire/BlockCreator.php`)

The **main orchestrator** that:
- Manages the `TrainingTree` instance
- Handles events from both schedule grid and progression table
- Coordinates `refreshGrid()` which recalculates progressions and dispatches updates

**Key Event Handlers:**

| Event | Actions Handled |
|-------|-----------------|
| `schedule-action` | `session.add`, `session.update`, `session.delete`, `session.link`, `session.move`, `session.swap`, `week.add`, `week.link`, `week.delete` |
| `progression-action` | `set.update` |

---

### 2. TrainingTree.php (`app/Models/Training/TrainingTree.php`)

The **action dispatcher** with a registry mapping paths to action classes:

```
block: [add, delete, duplicate, move]
week: [add, link, delete, duplicate, move]
session: [add, update, link, move, swap, delete]
exercise: [add, update, delete, move]
set: [add, update, delete, move]
```

Key methods: `executeAction()`, `getNode()`, `addChild()`, `removeChild()`, `save()`

---

### 3. TrainingNode.php (`app/Models/Training/TrainingNode.php`)

Represents each node in the tree with **linking support**:

| Type | Data Class | Key Data |
|------|-----------|----------|
| `block` | BlockData | Container |
| `week` | WeekData | Container |
| `session` | SessionData | `day`, `slot`, `category`, `exercises[]` |
| `exercise` | ExerciseData | `exercise` (ID) |
| `set` | SetData | `reps`, `weight` |

**Linking behavior:** When `linked_to` is set, `getChildren()` returns the source node's children

---

## Week & Progression Schedule Interaction

### Schedule Grid (`resources/views/livewire/planner/schedule-grid.blade.php`)

- Displays weeks × days × slots (AM/PM)
- Handles session creation, moving, swapping, linking
- Dispatches `schedule-action` events → BlockCreator

### Progression Table (`resources/views/livewire/planner/training-progression.blade.php`)

- Shows exercises by category across weeks
- Displays sets with reps/weight using default values
- Dispatches `progression-action` events → BlockCreator

---

## Data Flow

```
User Action (click/drag)
    ↓
schedule-action / progression-action event
    ↓
BlockCreator executes via TrainingTree
    ↓
refreshGrid()
    ↓
grid-refresh event dispatched
    ↓
Both grids update with new data
```

---

## Linking Mechanics

| Link Type | Effect |
|-----------|--------|
| **Week Link** | Week 2 → Week 1: Week 2 inherits all of Week 1's sessions |
| **Session Link** | Session A → Session B: Session A uses Session B's exercises/category but keeps its own day/slot |

**Constraints:**
- First week cannot be deleted or linked (it's the source)
- Linked sessions cannot be swapped with each other
- Unlinking copies source data to target

---

## Persistence

- **Frontend:** localStorage via `resources/js/alpine/block-creator-storage.js`
- **Backend:** `TrainingTree.save()` persists to `TrainingPeriod` model

---

## Important Files Summary

| File | Purpose |
|------|---------|
| `app/Livewire/BlockCreator.php` | Main orchestrator, handles all events |
| `app/Models/Training/TrainingTree.php` | Tree manager, action dispatcher |
| `app/Models/Training/TrainingNode.php` | Tree node, supports linking |
| `app/Models/Training/Data/TrainingData.php` | Base for all data types |
| `app/Models/Training/Data/WeekData.php` | Week-specific data |
| `app/Models/Training/Data/SessionData.php` | Session data (day, slot, category, exercises) |
| `resources/views/livewire/planner/schedule-grid.blade.php` | Week × day × slot display and management |
| `resources/views/livewire/planner/training-progression.blade.php` | Exercise progression table |
| `resources/js/alpine/block-creator-storage.js` | localStorage persistence |

---

## Action Classes (`app/Models/Training/Actions/`)

All actions extend `Action` and implement `execute(TrainingTree $tree): Event`

### Session Actions
- **AddSession** - Creates new session or linked session clone
- **UpdateSession** - Updates session properties (name, category, exercises)
- **LinkSession** - Links/unlinks session to another
- **MoveSession** - Moves session to new day/slot
- **SwapSessions** - Swaps position of two sessions
- **DeleteSession** - Removes session from week

### Week Actions
- **AddWeek** - Creates new week
- **LinkWeek** - Links week to another week
- **DeleteNode** - Generic delete (works for weeks, blocks, etc.)

### Other Actions
- **AddBlock**, **DuplicateNode**, **MoveNode**
- **AddExercise**, **UpdateExercise**
- **AddSet**, **UpdateSet**
