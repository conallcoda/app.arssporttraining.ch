# AGENTS.md

Local context for `cms/src/`.

## Golden References

- `Area.php`
- `Module.php`
- `PageDefinition.php`
- `DetailsPageDefinition.php`
- `Registry.php`
- `Layout/Layout.php`
- `Navigation/BreadcrumbResolver.php`

## Checklist

1. Keep public contracts generic and chainable.
2. Prefer extending existing abstractions over adding parallel APIs.
3. When scoping changes affect routing or context resolution, review `Area.php`, `Registry.php`, and `Livewire/Concerns/InteractsWithEntityDefinition.php` together.
4. When layout changes affect details pages, review `Layout/*`, `Contracts/HasDetailsLayout.php`, and the details Blade views together.
5. Back behavior changes with package tests when the package already has coverage nearby.

## Anti-Patterns

- Importing consumer classes or helpers into shared source.
- Splitting one concept across `cms` and `form-kit` without a clear ownership boundary.
- Changing route/context behavior without checking breadcrumb and default-scope consequences.
