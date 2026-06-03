# Auth-Kit / CMS Authorization Handover

Date: 2026-06-03

## Context

The athlete-training app needs to support different kinds of coaches. A normal Coach should be able to work independently with their own athletes, exercises, programs, schedules, and manual blocks, but should not be able to permanently remove important data or access administrative settings. An Admin Coach should be able to manage broader administrative concerns.

The current app already has useful pieces:

- `UserTypeEnum` has `admin`, `coach`, and `athlete`.
- `config/cms.php` has `admin_user_types`, currently allowing both `coach` and `admin` into the CMS/admin area.
- Many domain models already have `owner_id`.
- CMS lists for athletes, exercises, and programs already use `OwnershipTabs`.
- Coaches are currently treated mostly as "can access CMS", not as a rich record-aware permission role.

The desired direction is a reusable authorization layer that can live in generic packages, not only this app.

## Core Decision

Do not use `spatie/laravel-permission` directly in the first version.

Instead, build a config-backed authorization system in `auth-kit` with Spatie-like ergonomics and Laravel Gate integration. In the future, Spatie can be introduced behind the auth-kit wrapper as a persistence/backend implementation.

This keeps the first step simple:

- One config file defines roles, role assignment rules, and role permissions.
- CMS modules expose their available capabilities/actions.
- A generator/discovery command compiles module action definitions into a readable action map.
- Laravel `can()` / Gate-style checks still work.
- Templates and common CMS list components do not need manual `can` / `hasRole` checks.

## Package Boundaries

### Auth-Kit

Auth-kit should be generic. It must not know about coaches, athletes, exercises, programs, or this app's domain.

Auth-kit owns:

- Role definitions.
- Permission/rule definitions.
- Role assignment from config.
- Permission matching, including wildcards such as `*` and `programs.*`.
- Laravel Gate integration.
- Generic policy/access helpers such as "own".
- A future backend boundary where config-backed permissions can later be swapped for database-backed permissions.

Auth-kit may provide package-owned permissions only for its own UI/features, such as:

- `auth.roles.view`
- `auth.roles.update`
- `auth.permissions.view`
- `auth.permissions.assign`

Those should remain optional and package-owned.

### CMS

CMS should not know domain roles either. It owns CMS-shaped concepts:

- Modules.
- Module capabilities.
- Generic actions such as `viewAny`, `view`, `create`, `update`, `delete`, `use`.
- Automatic action filtering before rendering.
- Automatic action enforcement before execution.
- Query scoping hooks for visible records.

CMS modules declare what can be done. They do not decide which domain role receives those abilities.

### App

The athlete-training app owns:

- Domain roles such as `admin_coach`, `coach`, and `athlete`.
- Role assignment rules from app models/fields.
- Which permissions each role receives.
- Domain-specific scopes such as `global`, `granted`, and `accessible_athlete`.
- Domain-specific record access strategies.

## Config Shape

Target source-of-truth config:

```php
// config/auth-kit.php

return [
    'roles' => [
        'admin_coach' => [
            'label' => 'Admin Coach',
            'permissions' => [
                '*',
            ],
        ],

        'coach' => [
            'label' => 'Coach',
            'permissions' => [
                'cms.access' => true,

                'athletes.viewAny' => true,
                'athletes.view' => ['own', 'granted'],
                'athletes.create' => true,
                'athletes.update' => ['own'],
                'athletes.delete' => false,

                'exercises.viewAny' => true,
                'exercises.view' => ['own', 'global'],
                'exercises.use' => ['own', 'global'],
                'exercises.create' => true,
                'exercises.update' => ['own'],
                'exercises.delete' => false,

                'programs.*' => ['own'],
                'programs.view' => ['own', 'global'],
                'programs.use' => ['own', 'global'],
                'programs.delete' => false,

                'calendar.view' => ['accessible_athlete'],
                'calendar.create' => ['accessible_athlete'],
                'calendar.update' => ['accessible_athlete'],
                'calendar.delete' => ['accessible_athlete'],

                'manual-blocks.view' => ['accessible_athlete'],
                'manual-blocks.create' => ['accessible_athlete'],
                'manual-blocks.update' => ['accessible_athlete'],
                'manual-blocks.delete' => ['accessible_athlete'],
            ],
        ],

        'athlete' => [
            'label' => 'Athlete',
            'permissions' => [
                'dashboard.access' => true,
            ],
        ],
    ],

    'role_assignments' => [
        [
            'model' => App\Models\Users\User::class,
            'role' => 'admin_coach',
            'when' => [
                'type' => 'admin',
            ],
        ],

        [
            'model' => App\Models\Users\User::class,
            'role' => 'coach',
            'when' => [
                'type' => 'coach',
            ],
        ],

        [
            'model' => App\Models\Users\User::class,
            'role' => 'athlete',
            'when' => [
                'type' => 'athlete',
            ],
        ],
    ],

    'ownership' => [
        'default_owner_column' => 'owner_id',
        'models' => [
            App\Models\Exercise\Exercise::class => 'owner_id',
            App\Models\Exercise\ExerciseProgram::class => 'owner_id',
            App\Models\Users\User::class => 'owner_id',
        ],
    ],
];
```

The exact syntax can evolve, but the key idea is:

- Action names stay normal: `view`, `update`, `delete`, `use`.
- Ownership is not encoded into permission names.
- Record-aware scopes are attached as rule values: `['own']`, `['own', 'global']`, `['accessible_athlete']`.

## Generated Action Map

CMS modules should expose available capabilities. A command should discover them and generate a file for review/reference.

Example command:

```bash
php artisan auth-kit:discover-actions
```

Possible generated file:

```php
// config/auth-kit-actions.php
// Generated from CMS modules. Do not assign roles here.

return [
    'cms' => [
        'cms.access',
    ],

    'athletes' => [
        'athletes.viewAny',
        'athletes.view',
        'athletes.create',
        'athletes.update',
        'athletes.delete',
    ],

    'exercises' => [
        'exercises.viewAny',
        'exercises.view',
        'exercises.create',
        'exercises.update',
        'exercises.delete',
        'exercises.use',
    ],

    'programs' => [
        'programs.viewAny',
        'programs.view',
        'programs.create',
        'programs.update',
        'programs.delete',
        'programs.use',
    ],
];
```

The generated file is not the role source of truth. It is a discoverability and validation artifact.

The command should warn about:

- Permissions assigned to roles that are not discovered.
- Discovered actions that no role receives.
- Invalid wildcard prefixes.
- Duplicate action definitions.

## Module Capability Convention

Modules should declare capabilities/actions, not role assignments.

Example:

```php
class ExerciseModule extends Module
{
    public function capabilities(): array
    {
        return [
            Capability::viewAny(),
            Capability::view(),
            Capability::create(),
            Capability::update(),
            Capability::delete(),
            Capability::make('use'),
        ];
    }
}
```

The CMS derives permission names dynamically:

```php
$permission = "{$module->name()}.{$action->ability()}";
```

So a generic edit action:

```php
Action::make('edit')->ability('update')->recordAware();
```

resolves to:

- `exercises.update` in `ExerciseModule`
- `athletes.update` in `AthleteModule`
- `programs.update` in `ExerciseProgramModule`

No template or common list component should hard-code `exercises.update`.

## Authorization Flow

CMS should check authorization in three places:

1. Query building: lists only include records visible to the current user.
2. Action rendering: buttons/menu items are hidden when not allowed.
3. Action execution: submit/delete/modal handlers abort if not allowed.

Execution blocking is the security layer. UI hiding is convenience.

Target flow:

```php
$authorizer->allows($user, 'exercises.update', $exercise);
```

Internally:

1. Resolve roles assigned to the user from config.
2. Match the requested permission against role permissions, including wildcards.
3. If the permission rule is boolean, return it.
4. If the permission rule has scopes, evaluate scopes against the user and record.

## Own / ViewOwn / DeleteOwn Decision

Do not create separate permission names like:

- `exercises.viewOwn`
- `exercises.updateOwn`
- `exercises.deleteOwn`

Those combine two questions that should stay separate:

1. Can this role perform this kind of action?
2. Does this specific record match the user's access scope?

Preferred model:

```php
'exercises.update' => ['own'],
'exercises.view' => ['own', 'global'],
'exercises.delete' => false,
```

This keeps action names stable and lets scopes grow naturally:

- `own`
- `any`
- `global`
- `granted`
- `accessible_athlete`
- `same_organization`

Auth-kit should ship generic scopes:

- `any`
- `own`
- `none`

The app should register domain scopes:

```php
'global' => fn ($user, $record) => $record?->owner_id === null,

'granted' => fn ($user, $record) => CoachAthleteAccess::query()
    ->where('coach_id', $user->id)
    ->where('athlete_id', $record->id)
    ->exists(),
```

## Auth-Kit API Targets

Auth-kit should provide APIs with familiar ergonomics:

```php
$user->authRoles();
$user->hasAuthRole('coach');
$user->hasAuthPermission('exercises.update');
$user->can('exercises.update');
$user->can('exercises.update', $exercise);
```

Possible internal contracts:

```php
interface RoleResolver
{
    public function rolesFor(object $actor): array;
}

interface PermissionAuthorizer
{
    public function allows(object $actor, string $permission, mixed $target = null): bool;
}

interface ScopeEvaluator
{
    public function matches(object $actor, mixed $target, array $context = []): bool;
}
```

Gate integration should allow Laravel-native checks:

```php
Gate::before(function ($user, string $ability, mixed $target = null) {
    return app(PermissionAuthorizer::class)->allows($user, $ability, $target) ?: null;
});
```

Exact Gate argument handling needs care because Laravel passes policy arguments as an array in some call paths.

## Generic Access Helpers

Auth-kit can expose generic helpers that CMS/app strategies can use:

```php
Access::own($user, $record);
Access::ownedBy($record, $user);
Access::permission($user, 'exercises.update', $record);
Access::role($user, 'admin_coach');
```

Ownership should be configurable:

```php
'ownership' => [
    'default_owner_column' => 'owner_id',
    'models' => [
        Exercise::class => 'owner_id',
        ExerciseProgram::class => 'owner_id',
    ],
],
```

## CMS Access Strategies

CMS should have reusable resource strategies:

- `PermissionOnlyAccess`
- `OwnedResourceAccess`
- `OwnedOrGlobalReadableAccess`
- `NoDeleteAccess`
- `CompositeAccess`

The app can add domain strategies:

- `AthleteAccessStrategy`
- `ExerciseLibraryAccessStrategy`
- `ProgramLibraryAccessStrategy`
- `AccessibleAthleteScheduleAccessStrategy`
- `ManualBlockAccessStrategy`

Example shape:

```php
final class ExerciseLibraryAccessStrategy
{
    public function allows($user, string $ability, ?Model $record = null): bool
    {
        return app(PermissionAuthorizer::class)
            ->allows($user, "exercises.{$ability}", $record);
    }
}
```

The app config decides that `exercises.view` means `own|global`, while `exercises.update` means `own`.

## Initial Athlete-Training Role Intent

Normal Coach:

- Can create athletes.
- Can view/edit athletes they own.
- Can view athletes granted by admin.
- Cannot view or edit other coaches' athletes unless granted.
- Cannot delete athletes.
- Can create exercises.
- Can edit own exercises.
- Can view/use admin-created/global exercises.
- Cannot edit global/admin-created exercises.
- Cannot edit other coaches' exercises.
- Cannot delete exercises.
- Can create programs.
- Can edit own programs.
- Can view/use admin-created/global programs.
- Cannot edit global/admin-created programs.
- Cannot edit other coaches' programs.
- Cannot delete programs.
- Can create/edit/delete schedules for athletes they can access.
- Can create/edit/delete manual blocks for athletes they can access.
- Cannot manage coaches.
- Cannot access system settings.
- Cannot change permissions.

Admin Coach:

- Can access CMS.
- Can manage coaches.
- Can manage settings/permissions.
- Can grant athlete access.
- Can manage global/admin-created library data.
- Can delete where the app decides deletion is allowed.

Athlete:

- No CMS access.
- Accesses athlete dashboard/training surfaces only.

## First Implementation Slice

1. Add auth-kit config shape and a config-backed `PermissionAuthorizer`.
2. Add role resolution from app-configured assignment rules.
3. Add wildcard permission matching.
4. Add scope evaluation for boolean, `own`, and `any`.
5. Register Laravel Gate integration.
6. Add `capabilities()` convention to CMS modules.
7. Add `ability()` / `recordAware()` metadata to CMS actions if not already present.
8. Make common CMS list actions ask the module/authorizer dynamically.
9. Add `auth-kit:discover-actions` command.
10. Add tests proving:
    - A coach gets the configured coach role from `User::type`.
    - `programs.*` matches `programs.update`.
    - `exercises.update => ['own']` allows own records and blocks other records.
    - `exercises.view => ['own', 'global']` allows own/global and blocks other coach-owned records.
    - Generic CMS edit/delete actions are hidden and blocked based on dynamic module/action permissions.

## Future Spatie Path

If a UI for editing roles/permissions is needed later, introduce Spatie behind auth-kit rather than directly in app/CMS code.

The future backend swap should preserve:

```php
$user->can('exercises.update', $exercise);
app(PermissionAuthorizer::class)->allows($user, 'exercises.update', $exercise);
```

Possible future implementations:

- `ConfigPermissionAuthorizer`
- `SpatiePermissionAuthorizer`
- `HybridPermissionAuthorizer`

The CMS and app modules should not need to know which backend is active.

