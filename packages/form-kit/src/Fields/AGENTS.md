# AGENTS.md

Local context for `form-kit/src/Fields/`.

## Golden References

- `Text.php`
- `Select.php`
- `Repeater.php`
- `RelationshipSelector.php`

## Checklist

1. Before adding a field, inspect existing `Fields/*`, `Concerns/*`, and fieldset `view` patterns.
2. Use the smallest field class that can work. `Text.php` is the normal shape; `RelationshipSelector.php` is the exception case.
3. Declare a stable `type` and keep chainable methods returning `static`.
4. Keep field properties serializable and reusable across consumers.
5. If the field needs CMS models/config, move it to `cms/src/Form/Fields`.

## Anti-Patterns

- New field types for presentation-only changes.
- Database lookups or consumer service calls in generic field classes.
- Skipping tests for nested, conditional, or repeated usage.
