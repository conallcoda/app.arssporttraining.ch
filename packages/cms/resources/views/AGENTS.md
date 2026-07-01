# AGENTS.md

Local context for `cms/resources/views/`.

## Golden References

- `model-list.blade.php`
- `model-details-page.blade.php`
- `details-layout/node.blade.php`
- `/Users/conalloreilly/Development/cfc/cfc-admin/resources/views/vendor/cms/details-layout/node.blade.php`
- `/Users/conalloreilly/Development/cfc/cfc-admin/resources/views/vendor/cms/partials/layout-page-content.blade.php`

## Checklist

1. Search for published overrides before editing package views.
2. Precompute classes, branches, and state with `@php`.
3. Keep repeated branches in sync. `model-details-page.blade.php` has `none`, `section`, and `card` containers.
4. Preserve `wire:model.live`, `wire:model.live.blur`, and `data-*` hooks expected by Alpine and Livewire.
5. Keep details-layout fieldset names aligned with form fieldset keys.

## Anti-Patterns

- Blade directives inside component or HTML attribute lists.
- Editing only one repeated container branch.
- Assuming a package view change will be visible when a consumer override shadows it.
- Adding consumer-only copy or layout rules to shared views.
