# Code Pattern Analysis: Livewire/Flux vs Your CMS Package & App

## Context
You asked for an honest comparison of how Livewire and Flux organise their source code versus how you've built your CMS package and app — to surface patterns you could adopt and things you may not fully understand yet.

This is a research/analysis document, not an implementation plan. The refactoring suggestions at the end are optional and prioritised.

---

## What You're Doing Well

### 1. Trait composition on Form fields is excellent
Your `Field` base class uses `HasCondition`, `HasDefault`, `HasLabel`, `HasValidation`, and individual field types layer on `HasOptions`, `HasNumericConstraints`, `HasSelectVariants`, etc. (25+ concern traits total). This directly mirrors Livewire's own `Component` class, which uses 10+ `Handles*` traits instead of deep inheritance. Your granularity is appropriate — each concern is genuinely independent.

### 2. Builder/fluent APIs are clean
`Action::make('edit', 'Edit')->row()->icon('pencil')->formModal(...)` and `Form::make()->fieldset(...)` are readable, composable APIs. This aligns with how Flux uses `ClassBuilder` for fluent CSS composition. Your `Table::make()->columns()->sortable()->filters()` follows the same discipline.

### 3. Good use of modern Livewire attributes in app code
`CalendarIndex` correctly uses `#[Url]`, `#[On]`, `#[Computed]`, `#[Renderless]`. The `#[Url(except: '', history: true)]` usage shows thoughtful URL state management.

### 4. Data DTOs with `persist()` are a sound choice
Your Spatie Data classes serve as both form data carriers and persistence gateways. The `HasForms` contract that exposes `getForm()` keeps form definitions co-located with their data — a well-designed convention.

### 5. Event-driven component communication
`CalendarIndex` dispatches and listens through events (`sidebar-selection-changed`, `calendar-range.submitted`, `block.submitted`). This is exactly how Livewire intends cross-component communication to work.

### 6. You understand Livewire's extension model
`CalendarProfilerHook` correctly extends `ComponentHook` and uses `boot()` and `dehydrate()` lifecycle methods — showing awareness of the pluggable hook architecture.

---

## Patterns You Should Adopt from Livewire/Flux

### 1. Split `field.blade.php` (751 lines) into per-type Blade components

**What Livewire/Flux does:** Each Flux component type has its own Blade template file (`button/index.blade.php`, `input/index.blade.php`, `select/index.blade.php`). They never use a monolithic switch.

**What you do:** `packages/cms/resources/views/components/form/field.blade.php` is a 751-line file with 18+ `instanceof` branches. The `Relationship` branch alone is ~100 lines, `FileUpload` is ~120 lines.

**What to do:** Extract each field type into its own Blade component:
```
packages/cms/resources/views/components/form/fields/
├── select.blade.php
├── number.blade.php
├── relationship.blade.php
├── file-upload.blade.php
├── repeater.blade.php
└── ... (18 total)
```

Then `field.blade.php` becomes a thin dispatcher:
```blade
<x-dynamic-component :component="'cms::form.fields.' . $field->bladeComponent()" :field="$field" :prefix="$prefix" />
```

**Why this matters:** Navigability, testability, and new field types can be added without touching the dispatcher.

### 2. Extract shared logic from AbstractModelList (913 lines) and AbstractModelTree (601 lines)

**What Livewire/Flux does:** Shared behaviour lives in focused traits. The `Component` class itself is relatively thin — all feature logic is in `Handles*` traits and `ComponentHook` extensions.

**What you have:** Both abstract classes share ~200+ lines of nearly identical code: action methods (`getActions`, `getAddAction`, `getEditAction`, `getDeleteAction`), computed action filters (`headerActions`, `rowActions`, `rowMenuActions`, `formModals`), form config methods (`formConfig`, `fields`, `fieldsets`), entity slug/name helpers, mount/edit logic, and event listeners.

**What to do:** Extract into:
- `InteractsWithCrudActions` — actions, modals, confirmation, listeners
- `InteractsWithEntityDefinition` — slug, name, options, form config, mount

Then `AbstractModelList` adds only pagination/sorting/filtering, and `AbstractModelTree` adds only tree queries and reordering.

### 3. Eliminate Alpine JS file duplication

**The situation:** 7 Alpine files exist identically in both `packages/cms/resources/js/alpine/` and `resources/js/alpine/`:
- `form-modal.js`, `editable-cell.js`, `masked-input.js`, `sortable-items.js`, `tree-select.js`, `model-tree.js`, `youtube-player.js`

Your `resources/js/app.js` imports the local copies. The CMS package copies are dead code.

**What Livewire/Flux does:** Flux compiles its JS into `dist/flux.min.js` and serves it through `AssetManager` with route-based delivery. There's one copy, one source of truth.

**What to do:** Either:
- Import directly from the package: `import '../../packages/cms/resources/js/alpine/form-modal'`
- Or have the CMS package provide a JS entry point that the app imports

The 4 app-specific files (`schedule-grid.js`, `calendar-cell-select.js`, `calendar-slot-popover.js`, `metric-cell-popover.js`) stay in `resources/js/alpine/`.

### 4. Two-phase service provider boot

**What Livewire does:** `LivewireServiceProvider` separates `register()` (bind singletons) from `boot()` which calls discrete named methods: `registerLivewireServices()`, `bootMechanisms()`, `bootFeatures()`.

**What you do:** Your `CmsPackageServiceProvider::boot()` is one flat method mixing view loading, asset publishing, Fortify config, and Livewire component registration.

**What to do:** Split into named methods:
```php
public function boot(): void
{
    $this->bootViews();
    $this->bootPublishing();
    $this->bootAuth();
    $this->bootLivewireComponents();
}
```

This isn't cosmetic — when the CMS grows, each concern becomes independently readable and modifiable.

### 5. Consider Livewire component auto-discovery for the package

**What Livewire supports:** Namespace-based auto-discovery so any Livewire component in `Coda\Cms\Livewire\*` is automatically available without manual registration.

**What you do:** Manually register 6 components. Each new component requires editing the service provider.

---

## Anti-Patterns & Code Smells

### 1. `WithCalendarPlan` trait is 922 lines — it's a relocated monolith, not decomposition

Combined with `CalendarIndex.php` (528 lines), the effective component is **1,450 lines**. The trait contains plan navigation, block options, program options, 1RM metric fetching, heart rate metric fetching, group member metric fetching, block submission/deletion handlers, and schedule info computation — all unrelated concerns in one trait.

**Specific smell:** Three nearly identical methods (`openPlan1rmEdit`, `openPlanHeartRateEdit`, `openPlanGroupMemberMetricEdit`) share the same pattern: resolve block → compute cutoff date → query MetricSubmission → dispatch event. This is a clear extraction into a service class.

**Specific smell:** `planGroupMemberMetrics` computed property iterates each group member and runs 2 queries per member. For 20 athletes = 40 queries in one property. Should be batch-queried with `whereIn('user_id', $memberIds)`.

### 2. No tests in the CMS package

The package's core abstractions (`AbstractModelList`, `AbstractModelTree`, `FormModal`, `InteractsWithFormData`, `Field::buildValidationRules()`) are **untested**. A few tests exist in the app's `tests/Feature/Cms/` directory, but they don't travel with the package. If the package is ever extracted or reused, these critical components have zero test coverage.

At minimum, the package needs:
- Unit tests for `Field::buildValidationRules()` (called on every form submission)
- Unit tests for `InteractsWithFormData::syncConditionalFieldData()` (mutates form state based on visibility)
- Feature tests for `AbstractModelList` lifecycle (mount, paginate, sort, filter, submit, delete)
- Feature tests for `FormModal` (open, submit, cancel, media upload)

### 3. `getListeners()` legacy pattern in CMS base classes

Both `AbstractModelList` and `FormModal` use the old `getListeners()` method for dynamic event names. While necessary when listener names depend on runtime values (entity slugs), Livewire 3/4's `#[On]` attribute with dynamic segments is the preferred approach. Worth reviewing whether the dynamic names can be refactored to use `#[On('entity.{slug}.submitted')]` patterns.

---

## Concepts You May Not Have Fully Explored

### 1. Livewire's ComponentHook system as a plugin architecture

You used `ComponentHook` for profiling, but the real power is using hooks as **feature modules**. Livewire itself implements validation, computed properties, URL binding, lazy loading, etc. all as ComponentHook extensions — not as base class methods. If you have cross-cutting concerns in your CMS (audit logging, permission checking, automatic caching), they could be ComponentHook extensions rather than traits or middleware.

### 2. Flux's `ClassBuilder` for dynamic CSS composition

Flux avoids string concatenation for CSS classes. Instead:
```php
Flux::classes()
    ->add('base-classes')
    ->add(match($variant) { 'primary' => '...', 'danger' => '...' })
```

If your CMS generates dynamic classes (badge colors, field widths), this pattern is cleaner than inline ternaries.

### 3. Livewire's Property Synthesizer pattern

Livewire uses dedicated "Synth" classes to handle serialisation/deserialisation of complex types (Carbon, Collection, Enum). If your Data DTOs ever have serialisation edge cases, you can register custom synthesizers via `Livewire::propertySynthesizer()`.

### 4. WeakMap for component state

Livewire's `DataStore` uses PHP's `WeakMap` to associate state with components without preventing garbage collection. If your CMS tracks any per-component state globally (e.g., the Registry), WeakMap prevents memory leaks in long-running processes.

---

## Summary of Refactoring Priorities

| Priority | What | Why |
|----------|------|-----|
| **High** | Split `field.blade.php` into per-type components | 751-line switch is unmaintainable |
| **High** | Extract shared AbstractModelList/Tree logic into traits | ~200 lines duplicated between 2 core classes |
| **High** | Eliminate 7 duplicated Alpine JS files | Dead code in CMS package, single source of truth needed |
| **Medium** | Extract CalendarIndex metric logic into a service + fix N+1 | 3 identical methods + 40 queries for 20 athletes |
| **Medium** | Add CMS package tests | Core abstractions have zero test coverage |
| **Medium** | Split service provider boot into named methods | Readability and maintainability as package grows |
| **Low** | Split `WithCalendarPlan` (922 lines) into focused traits | Relocated monolith, not true decomposition |
| **Low** | Guard `CalendarProfilerHook` behind config/env check | Disk I/O on every request if left enabled |
