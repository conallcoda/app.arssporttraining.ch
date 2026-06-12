# AGENTS.md

Local context for `cms/src/Livewire/`.

## Golden References

- `AbstractModelList.php`
- `AbstractModelDetailsPage.php`
- `AbstractModelTree.php`
- `Concerns/InteractsWithEntityDefinition.php`
- `Concerns/InteractsWithFormData.php`
- `Concerns/InteractsWithMediaUploads.php`

## Checklist

1. Keep base Livewire classes consumer-neutral.
2. Load context through registry, page definitions, and concerns before adding new component state.
3. For form flows, keep fieldsets, defaults, validation, and media persistence aligned.
4. Scoped components should derive current context through `InteractsWithEntityDefinition`, not duplicate selector logic.
5. If a change affects package Blade hooks, inspect the matching view and Alpine module in the same pass.

## Anti-Patterns

- New `App\\...` imports in shared Livewire classes.
- Component branches that only make sense for one consuming app.
- Changing save/load flows without checking hidden-field, file-upload, or details-layout behavior.
