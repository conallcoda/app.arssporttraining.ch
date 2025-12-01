# Training System: Code Review & Improvement Recommendations

## Overview

This document outlines areas for improvement in the training planning system, covering actions, modals, components, and Alpine.js files.

---

## 🔴 Critical Issues

### 1. Logic Error in AddBlock.php

**File:** `app/Models/Training/Actions/AddBlock.php:18`

```php
// BUG: This assigns then checks if falsy - always passes
if (!$this->parentId = $tree->root->uuid)

// SHOULD BE:
if ($this->parentId !== $tree->root->uuid)
```

**Impact:** May cause unexpected behavior or bypass intended validation.

---

## 🟠 High Priority

### 2. Duplicated Index-Finding Logic

**Files:**
- `app/Models/Training/Actions/DuplicateNode.php` (lines 24-28)
- `app/Models/Training/Actions/MoveNode.php` (lines 24-33)
- `app/Models/Training/Events/NodeMovedEvent.php` (lines 28-32, 63-68)

**Problem:** Same pattern repeated 4+ times:

```php
foreach ($parent->children as $i => $child) {
    if ($child->uuid === $this->nodeId) {
        $index = $i;
        break;
    }
}
```

**Recommendation:** Add helper method to `TrainingTree`:

```php
public function findChildIndex(TrainingNode $parent, string $childUuid): ?int
{
    foreach ($parent->children as $index => $child) {
        if ($child->uuid === $childUuid) {
            return $index;
        }
    }
    return null;
}
```

---

### 3. Event Classes Have Identical Redo/Undo

**Files:**
- `app/Models/Training/Events/SessionAddedEvent.php`
- `app/Models/Training/Events/BlockAddedEvent.php`
- `app/Models/Training/Events/SetAddedEvent.php`
- `app/Models/Training/Events/ExerciseAddedEvent.php`

**Problem:** All have identical implementations:

```php
public function redo(TrainingTree $tree) {
    $parent = $tree->getNode($this->parent->uuid);
    $tree->addChild($parent, $this->child->data);
}
```

**Recommendation:** Create a trait:

```php
trait HasAddEventBehavior
{
    public function redo(TrainingTree $tree): void
    {
        $parent = $tree->getNode($this->parent->uuid);
        $tree->addChild($parent, $this->child->data);
    }

    public function undo(TrainingTree $tree): void
    {
        $tree->removeChild(
            $tree->getNode($this->parent->uuid),
            $this->child->uuid
        );
    }
}
```

---

### 4. schedule-grid.blade.php is Too Large (~500 Lines)

**File:** `resources/views/livewire/planner/schedule-grid.blade.php`

**Problem:** Single component handles:
- Session CRUD operations
- Week CRUD operations
- Modal interactions
- Drag/drop logic
- Event dispatching
- Cell content calculation

**Recommendation:** Split into smaller components:

| Component | Responsibility |
|-----------|---------------|
| `ScheduleGridManager` | State management + actions |
| `ScheduleGridView` | Presentation only |
| `DragDropHandler` | Alpine component for drag/drop |
| `SessionCell` | Individual cell rendering |

---

## 🟡 Medium Priority

### 5. Inconsistent Factory Methods in Actions

| Action Class | Factory Method | Consistent? |
|-------------|----------------|-------------|
| AddBlock | `fromParentId()` | ✓ |
| AddWeek | `fromParentId()` | ✓ |
| AddSession | `fromParentId()` | ✓ |
| DeleteNode | `fromNodeId()` | Different |
| DeleteSession | `fromIds()` | Different |
| SwapSessions | `fromSessionIds()` | Different |
| AddExercise | ❌ None | Missing |
| UpdateExercise | ❌ None | Missing |
| AddSet | ❌ None | Missing |
| UpdateSet | ❌ None | Missing |
| LinkSession | ❌ None | Missing |
| LinkWeek | ❌ None | Missing |

**Recommendation:** Standardize all actions to use `from()` static factory.

---

### 6. No Input Validation in Actions

**Files:**
- `app/Models/Training/Actions/AddExercise.php`
- `app/Models/Training/Actions/UpdateExercise.php`
- `app/Models/Training/Actions/AddSet.php`
- `app/Models/Training/Actions/UpdateSet.php`

**Problem:** No validation for:
- Negative weights/reps
- Null exercise IDs
- Invalid ranges

**Recommendation:** Add validation method to base Action class:

```php
abstract class Action extends AbstractData
{
    abstract protected function validate(): void;

    public function execute(TrainingTree $tree): Event
    {
        $this->validate();
        return $this->doExecute($tree);
    }
}
```

---

### 7. Complex Nested Loops in Progression Table

**File:** `resources/views/livewire/planner/training-progression.blade.php:144-210`

**Problem:** `getExerciseRows()` has 4 nested loops and is called per exercise on every refresh.

**Recommendation:**
- Memoize results
- Calculate all exercises at once instead of per-exercise
- Consider caching at the block level

---

### 8. Memory Leak in data-grid.js

**File:** `resources/js/alpine/data-grid.js:12`

```javascript
// Global listener never cleaned up
document.addEventListener('mouseup', () => { ... })
```

**Recommendation:**

```javascript
Alpine.data('data_grid', () => ({
    _mouseupHandler: null,

    init() {
        this._mouseupHandler = () => this.handleMouseup();
        document.addEventListener('mouseup', this._mouseupHandler);
    },

    destroy() {
        document.removeEventListener('mouseup', this._mouseupHandler);
    }
}))
```

---

### 9. Magic Numbers and Strings

**File:** `resources/views/livewire/planner/training-progression.blade.php:14-15`

```php
$defaultWeight = 50;
$defaultSets = '14-14-12-12';
```

**Recommendation:** Move to configuration:

```php
// config/training.php
return [
    'defaults' => [
        'weight' => 50,
        'sets' => '14-14-12-12',
    ],
];

// Usage
$defaultWeight = config('training.defaults.weight');
```

---

### 10. Duplicated Session Finding Logic

**Files:**
- `schedule-grid.blade.php` → `getSessionAtPosition()`
- `training-progression.blade.php` → `findSessionByUuid()`
- `training-progression.blade.php` → `findSessionInWeek()`

**Recommendation:** Create `SessionFinder` service class:

```php
class SessionFinder
{
    public function __construct(private TrainingNode $block) {}

    public function atPosition(TrainingNode $week, int $day, int $slot): ?TrainingNode
    public function byUuid(string $uuid): ?TrainingNode
    public function inWeek(string $weekUuid, int $day, int $slot): ?TrainingNode
}
```

---

## 🟢 Low Priority

### 11. Console.log Left in Production

**File:** `resources/js/alpine/data-grid.js:101`

```javascript
console.log('copy')  // Remove this
```

---

### 12. Modal Reset Logic Duplication

**Files:**
- `resources/views/livewire/planner/week-modal.blade.php:26-33`
- `resources/views/livewire/planner/session-modal.blade.php:47-59`

**Problem:** Both manually reset all state properties in close function.

**Recommendation:** Use state objects that can be reset atomically:

```php
state([
    'form' => [
        'mode' => 'new',
        'weekIndex' => null,
        'linkedTo' => null,
        // ...
    ],
]);

$close = function () {
    $this->form = [...defaultFormState];
    $this->dispatch('close-modal', name: 'week-modal');
};
```

---

### 13. Duplicated Move Exercise Logic

**File:** `resources/views/livewire/planner/session-modal.blade.php:70-84`

**Problem:** `moveExerciseUp` and `moveExerciseDown` duplicate swap logic.

**Recommendation:**

```php
$moveExercise = function (int $index, int $direction) {
    $newIndex = $index + $direction;
    if ($newIndex < 0 || $newIndex >= count($this->exercises)) {
        return;
    }
    [$this->exercises[$index], $this->exercises[$newIndex]] =
        [$this->exercises[$newIndex], $this->exercises[$index]];
};

$moveExerciseUp = fn($i) => $this->moveExercise($i, -1);
$moveExerciseDown = fn($i) => $this->moveExercise($i, 1);
```

---

### 14. LinkWeek Orphaned Sessions Risk

**File:** `app/Models/Training/Actions/LinkWeek.php:26-42`

**Problem:** When unlinking, creates linked clones of sessions but doesn't validate the source week exists. If deleted, creates orphaned sessions.

**Recommendation:** Add validation:

```php
if ($sourceWeek && !$tree->getNode($sourceWeek->uuid)) {
    throw new InvalidArgumentException('Source week no longer exists');
}
```

---

## Summary Table

| Priority | Issue | Effort | Impact |
|----------|-------|--------|--------|
| 🔴 Critical | AddBlock logic bug | 5 min | High |
| 🟠 High | Duplicate index-finding | 2 hr | Medium |
| 🟠 High | Event class duplication | 1 hr | Medium |
| 🟠 High | schedule-grid too large | 4 hr | High |
| 🟡 Medium | Missing validation | 3 hr | Medium |
| 🟡 Medium | Inconsistent factories | 2 hr | Low |
| 🟡 Medium | Progression performance | 2 hr | Medium |
| 🟡 Medium | Memory leak | 30 min | Low |
| 🟡 Medium | Magic numbers | 30 min | Low |
| 🟡 Medium | Session finder duplication | 1 hr | Low |
| 🟢 Low | Console.log | 5 min | None |
| 🟢 Low | Modal reset duplication | 1 hr | Low |
| 🟢 Low | Move exercise duplication | 15 min | Low |
| 🟢 Low | LinkWeek orphan risk | 30 min | Low |

---

## Recommended Action Plan

### Phase 1: Critical Fixes (30 minutes)
1. Fix AddBlock.php logic error
2. Remove console.log from data-grid.js

### Phase 2: Quick Wins (2 hours)
1. Add `findChildIndex()` to TrainingTree
2. Create `HasAddEventBehavior` trait
3. Fix memory leak in data-grid.js
4. Extract magic numbers to config

### Phase 3: Refactoring (8 hours)
1. Create `SessionFinder` service
2. Split schedule-grid.blade.php into smaller components
3. Add validation layer to actions
4. Standardize factory methods

### Phase 4: Polish (4 hours)
1. Consolidate modal reset logic
2. Improve progression table performance
3. Add missing factory methods to all actions
