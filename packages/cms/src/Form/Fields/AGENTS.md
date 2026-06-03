# AGENTS.md

Local context for `cms/src/Form/Fields/`.

## Own This Layer For

- Fields that depend on CMS config, CMS models, CMS scopes, or CMS display conventions.

## Golden References

- `Tags.php`
- `Color.php`

## Checklist

1. If the field could work outside CMS, move it to `form-kit` instead.
2. Keep option loading explicit and cacheable when it touches the database.
3. Match shared field conventions for labels, help text, required state, spacing, and errors.
4. Update the shared rendering path only when the behavior is truly reusable.

## Anti-Patterns

- App-specific queries or labels in CMS field classes.
- Creating a CMS field type when an existing `form-kit` field plus configuration is enough.
