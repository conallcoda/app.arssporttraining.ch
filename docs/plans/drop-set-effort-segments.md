# Drop Set Effort Segments

## Context

Coaches want to prescribe and record drop sets without expanding them into many normal sets in the main program grid.

Example:

```text
Set 1
Reps: 12, 12, 12
Weight: 10, 8, 6 kg
```

This represents one set with multiple immediate efforts inside it: the athlete performs 12 reps, reduces the weight, performs another 12 reps, reduces again, and performs a final 12 reps.

Tempo also needs to support letters such as `X`, for example `30X0`, where `X` means the phase is performed explosively.

## Main Concern

This should not become free-form text in numeric fields.

Allowing values such as `12,12,12` directly in the existing reps field would make storage, validation, previews, athlete entry, and future statistics harder. Every statistics/reporting path would have to parse human-entered mini-language and handle malformed combinations such as:

```text
Reps: 12, 12, 12
Weight: 10, 8
```

The feature introduces set-like things inside a set, so it needs a small structured model even if the UI stays simple.

## Preferred Direction

Keep normal sets simple by default.

Most sets should continue to behave like this:

```text
Set 1
Reps: 12
Weight: 10
Tempo: 30X0
```

Only drop sets need extra structure:

```json
{
  "reps": 12,
  "weight": 10,
  "tempo": "30X0",
  "segments": [
    { "reps": 12, "weight": 10 },
    { "reps": 12, "weight": 8 },
    { "reps": 12, "weight": 6 }
  ]
}
```

For non-drop sets, `segments` can be absent or null.

Application code should expose a helper such as:

```php
$set->efforts();
```

That helper returns one virtual effort for normal sets:

```php
[
    ['reps' => 12, 'weight' => 10],
]
```

And multiple efforts for drop sets:

```php
[
    ['reps' => 12, 'weight' => 10],
    ['reps' => 12, 'weight' => 8],
    ['reps' => 12, 'weight' => 6],
]
```

This gives stats code one consistent concept, while avoiding a migration that forces every existing set to physically store a segment.

## UI Direction

Do not show a segment repeater for every set.

Default UI remains simple:

```text
Reps: 12
Weight: 10
```

Only when a coach or athlete explicitly enables drop-set mode for a set should the UI show segment-style entry.

Compact display:

```text
Reps: 12 / 12 / 12
Weight: 10 / 8 / 6 kg
```

Editing could happen in a small modal/popover for that one set:

```text
Drop set
[12 reps] [10 kg]
[12 reps] [8 kg]
[12 reps] [6 kg]
+ Add drop
```

The main preview grid should stay readable and avoid becoming a spreadsheet inside a spreadsheet.

## Statistics Direction

Stats should use the normalized effort helper rather than raw reps/weight fields.

For a drop set:

```text
12 x 10 kg
12 x 8 kg
12 x 6 kg
```

Possible calculations:

```text
Total reps = 36
Volume = (12 * 10) + (12 * 8) + (12 * 6) = 288 kg
Max weight = 10 kg
Drop set count = 1 set with more than one effort
```

This lets the system calculate volume and other exercise statistics without pretending the drop set is three normal rested sets.

## Migration Strategy

Avoid a large migration initially.

Phase 1:

- Support tempo as a string, including letters such as `X`.
- Add optional segment/effort data only for drop sets.
- Keep existing simple set values valid.
- Add helper methods that normalize old simple values into one virtual effort.
- Update future stats/reporting code to use the helper.

Phase 2:

- If drop sets become important for reporting or performance, consider a more formal normalized table or JSON schema.
- Backfill existing data only if there is a strong reason.

## Key Product Rule

A set can have one or more efforts.

Normal set:

```text
1 effort
```

Drop set:

```text
2+ efforts
```

The UI should keep normal sets simple, while the backend has enough structure to support drop sets and future statistics cleanly.
