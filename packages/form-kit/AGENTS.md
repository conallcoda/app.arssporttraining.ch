# AGENTS.md

Local context for `form-kit/`.

## Own This Layer For

- Reusable field definitions, concerns, fieldset resolution, conditional visibility, defaults, validation metadata, shared Flux form rendering.

## Golden References

- `src/Field.php`
- `src/Form.php`
- `src/Fields/Text.php`
- `src/Fields/RelationshipSelector.php`
- `tests/FormConditionalFieldsTest.php`

## Local Rules

1. Prefer extending concerns, existing fields, or fieldset `view` composition before creating a new field type.
2. Keep `form-kit` generic. If logic depends on CMS registry, tags, media, or admin routing, move it up to `cms`.
3. Rendering belongs in shared Blade views; field classes should stay small and serializable.
4. Changes to defaults, hidden fields, prefixes, or conditions need tests.
5. Do not deepen the existing `form-kit` to `cms` coupling unless there is no cleaner boundary.

## Anti-Patterns

- New hard dependencies on a consumer app.
- Hidden fields still contributing defaults or validation.
- Solving one consumer workflow by inflating a core field API.
