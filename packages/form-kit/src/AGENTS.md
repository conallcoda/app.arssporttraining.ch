# AGENTS.md

Local context for `form-kit/src/`.

## Golden References

- `Field.php`
- `Form.php`
- `Concerns/HasCondition.php`
- `Concerns/HasValidation.php`
- `Fields/Text.php`
- `Fields/Select.php`
- `Fields/RelationshipSelector.php`
- `../tests/FormConditionalFieldsTest.php`

## Checklist

1. Keep field classes as data/config objects with chainable APIs.
2. Reuse concerns before adding repeated properties or methods to many fields.
3. Keep `Form::resolveFieldsets()` behavior aligned with defaults, prefixes, hidden fields, and conditional visibility.
4. Add or extend tests whenever conditions, defaults, nested schema, or validation behavior changes.

## Anti-Patterns

- Rendering logic in PHP field classes.
- Conditions that ignore fieldset prefix scoping.
- Validation rules for fields that are hidden by conditions.
