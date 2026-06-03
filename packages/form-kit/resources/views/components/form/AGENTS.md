# AGENTS.md

Local context for `form-kit/resources/views/components/form/`.

## Golden References

- `field.blade.php`
- `field-shell.blade.php`
- `field-header.blade.php`
- `fieldset.blade.php`
- `fieldset-tabs.blade.php`

## Checklist

1. Treat this directory as the shared rendering contract for labels, help text, required badges, spacing, and errors.
2. Precompute state and class names at the top of the file.
3. Preserve Livewire semantics:
   - `wire:model.live` for immediate syncing
   - `wire:model.live.blur` for blur-triggered syncing
4. When touching nested UI, verify repeaters, relationship selectors, tabs, and prefixed fieldsets.
5. Check for consumer overrides before assuming a package view change will show up.

## Anti-Patterns

- Blade directives inside attribute lists.
- One-off consumer markup in shared field rendering.
- Renaming `data-*` hooks without updating shared Alpine code.
- Testing only simple text/select fields after editing `field.blade.php`.
