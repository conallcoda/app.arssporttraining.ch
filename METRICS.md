# Metrics System Documentation

## Overview

The metrics system uses **Single Table Inheritance (STI)** and **Polymorphic Relationships** to store various types of measurements related to users, exercises, and training periods.

## Architecture

### Database Schema

**Table:** `metrics`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `type` | string | STI type (e.g., 'one_rep_max', 'body_weight', 'reps') |
| `metricable_type` | string | Polymorphic parent type (User, Exercise, TrainingExercise) |
| `metricable_id` | bigint | Polymorphic parent ID |
| `value` | decimal(10,2) | The numeric value |
| `unit` | string | Optional unit (kg, lbs, reps, seconds) |
| `recorded_at` | date | Optional date for time-series tracking |
| `notes` | text | Optional notes |
| `extra` | json | Schemaless attributes for metric-specific data |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp | Soft deletes |

### Metric Types

#### User Metrics
Performance and biometric data specific to a user:
- `OneRepMax` - User's one-rep max for specific exercises
- `BodyWeight` - User's body weight over time

#### Exercise Metrics
Training variables for exercises in a workout:
- `Reps` - Number of repetitions
- `Sets` - Number of sets
- `TimeUnderTension` - Time under tension duration

## Usage Examples

### 1. Tracking User's One Rep Max

```php
use App\Models\Users\User;
use App\Models\Exercise\Exercise;
use App\Models\Metrics\User\OneRepMax;

// Find the athlete and exercise
$athlete = User::find(1);
$benchPress = Exercise::where('name', 'Bench Press')->first();

// Record a 1RM test
$oneRepMax = new OneRepMax([
    'value' => 100,
    'unit' => 'kg',
    'recorded_at' => now(),
    'notes' => 'Personal best!'
]);

// Option 1: Attach to user (for body-weight relative strength)
$athlete->metrics()->save($oneRepMax);

// Option 2: Store exercise-specific 1RM using extra attributes
$oneRepMax = new OneRepMax([
    'value' => 100,
    'unit' => 'kg',
    'recorded_at' => now(),
]);
$oneRepMax->extra->exercise_id = $benchPress->id;
$athlete->metrics()->save($oneRepMax);

// Retrieve athlete's 1RM for bench press
$benchPress1RM = $athlete->metrics()
    ->where('type', 'one_rep_max')
    ->get()
    ->first(fn($metric) => $metric->extra->exercise_id == $benchPress->id);

// Get latest 1RM
$latest1RM = $athlete->metrics()
    ->where('type', 'one_rep_max')
    ->latest('recorded_at')
    ->first();
```

### 2. Tracking Body Weight Over Time

```php
use App\Models\Users\User;
use App\Models\Metrics\User\BodyWeight;

$athlete = User::find(1);

// Record body weight measurements
$bodyWeight = new BodyWeight([
    'value' => 75.5,
    'unit' => 'kg',
    'recorded_at' => now(),
]);
$athlete->metrics()->save($bodyWeight);

// Get weight trend (last 30 days)
$weightTrend = $athlete->metrics()
    ->where('type', 'body_weight')
    ->where('recorded_at', '>=', now()->subDays(30))
    ->orderBy('recorded_at')
    ->get()
    ->pluck('value', 'recorded_at');
```

### 3. Assigning Exercise Metrics to Training Plans

```php
use App\Models\Training\Periods\TrainingExercise;
use App\Models\Metrics\Exercise\Reps;
use App\Models\Metrics\Exercise\Sets;

// Get a training exercise from a workout plan
$trainingExercise = TrainingExercise::find(1);

// Add prescribed sets and reps
$sets = new Sets(['value' => 4, 'unit' => 'sets']);
$reps = new Reps(['value' => 8, 'unit' => 'reps']);

$trainingExercise->metrics()->saveMany([$sets, $reps]);

// Retrieve metrics for the exercise
$prescribedSets = $trainingExercise->metrics()
    ->where('type', 'sets')
    ->first()
    ->value; // 4

$prescribedReps = $trainingExercise->metrics()
    ->where('type', 'reps')
    ->first()
    ->value; // 8
```

### 4. Calculating Training Load Based on 1RM

```php
use App\Models\Users\User;
use App\Models\Exercise\Exercise;
use App\Models\Training\Periods\TrainingExercise;

$athlete = User::find(1);
$benchPress = Exercise::where('name', 'Bench Press')->first();

// Get athlete's 1RM for bench press
$oneRepMax = $athlete->metrics()
    ->where('type', 'one_rep_max')
    ->get()
    ->first(fn($metric) => $metric->extra->exercise_id == $benchPress->id);

// Calculate training weight at 75% 1RM
$trainingWeight = $oneRepMax->value * 0.75; // 75kg (if 1RM is 100kg)

// Create training exercise with calculated load
$trainingExercise = new TrainingExercise(['name' => 'Bench Press']);
$trainingExercise->extra->exercise_id = $benchPress->id;
$trainingExercise->extra->weight = $trainingWeight;
$trainingExercise->extra->intensity = 75; // percentage
$trainingExercise->save();

// Add reps/sets
$trainingExercise->metrics()->create([
    'type' => 'sets',
    'value' => 4,
]);
$trainingExercise->metrics()->create([
    'type' => 'reps',
    'value' => 6,
]);
```

## Adding Relationships to Models

To use metrics with your models, add the polymorphic relationship:

### User Model

```php
// app/Models/Users/User.php

use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Metrics\Metric;

public function metrics(): MorphMany
{
    return $this->morphMany(Metric::class, 'metricable');
}
```

### Exercise Model

```php
// app/Models/Exercise/Exercise.php

use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Metrics\Metric;

public function metrics(): MorphMany
{
    return $this->morphMany(Metric::class, 'metricable');
}
```

### TrainingExercise Model

```php
// app/Models/Training/Periods/TrainingExercise.php

use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Metrics\Metric;

public function metrics(): MorphMany
{
    return $this->morphMany(Metric::class, 'metricable');
}
```

## Helper Methods

You can add scopes to the Metric model for easier querying:

```php
// app/Models/Metrics/Metric.php

public function scopeOfType(Builder $query, string $type): Builder
{
    return $query->where('type', $type);
}

public function scopeForExercise(Builder $query, int $exerciseId): Builder
{
    return $query->whereRaw("JSON_EXTRACT(extra, '$.exercise_id') = ?", [$exerciseId]);
}

public function scopeRecordedBetween(Builder $query, $start, $end): Builder
{
    return $query->whereBetween('recorded_at', [$start, $end]);
}
```

## Progression Logic Example

```php
// Service class for calculating progressive overload
class ProgressionService
{
    public function calculateNextWorkout(User $athlete, Exercise $exercise): array
    {
        // Get latest 1RM
        $oneRepMax = $athlete->metrics()
            ->where('type', 'one_rep_max')
            ->get()
            ->first(fn($m) => $m->extra->exercise_id == $exercise->id);

        if (!$oneRepMax) {
            throw new \Exception('No 1RM recorded for this exercise');
        }

        // Progressive overload: 75% 1RM for 4x8
        $weight = round($oneRepMax->value * 0.75, 2);

        return [
            'weight' => $weight,
            'sets' => 4,
            'reps' => 8,
            'intensity' => 75,
        ];
    }
}
```

## Best Practices

1. **Always set `recorded_at`** for time-series metrics (OneRepMax, BodyWeight)
2. **Use `unit`** consistently (standardize on kg vs lbs, etc.)
3. **Store exercise_id in `extra`** when tracking exercise-specific user metrics
4. **Use soft deletes** to maintain historical data
5. **Add indexes** for common query patterns (already added in migration)

## Next Steps

1. Add the `metrics()` relationship to User, Exercise, and TrainingExercise models
2. Create Filament resources for managing user metrics (1RM testing, body weight tracking)
3. Build progression calculators based on user metrics
4. Create workout logging interface to track actual vs prescribed metrics
