# AGENTS.md

Local context for `cms/`.

## Own This Layer For

- `Area`, `Module`, `Registry`, page definitions, navigation, CRUD Livewire, CMS display/layout, CMS-only fields, media/auth integration.

## Golden References

- `src/Area.php`
- `src/Module.php`
- `src/Registry.php`
- `src/Livewire/AbstractModelList.php`
- `src/Livewire/AbstractModelDetailsPage.php`
- `resources/views/model-details-page.blade.php`
- `/Users/conalloreilly/Development/cfc/cfc-admin/app/Cms/Areas/ConferenceArea.php`
- `/Users/conalloreilly/Development/cfc/cfc-admin/app/Cms/Areas/Conference/EditionModule.php`

## Local Rules

1. Keep CMS contracts consumer-agnostic. No route names, services, models, or debug helpers from `App\\...`.
2. Scoped areas should derive context through `Area::scope()`, `Registry`, and Livewire concerns. Do not reintroduce manual scope selectors when context is already implied.
3. Details pages should pair form fieldsets with layout nodes deliberately. In two-column details layouts: main content left, media right, media first in the right column.
4. CMS-specific fields belong here only when they depend on CMS config, CMS models, or registry behavior. Otherwise move them to `form-kit`.
5. Before editing package views, check whether a consumer override already exists under `resources/views/vendor/cms`.

## Anti-Patterns

- Treating existing app leakage as precedent. `src/Livewire/AbstractModelDetailsPage.php` currently imports `App\\Support\\TopicFieldDebugLogger`; do not repeat that pattern.
- Exposing legacy/internal IDs by default in shared CMS UI.
- Hardcoding scoped parent selectors instead of deriving them from route context.
- Fixing a generic CMS problem only in a consumer override.
