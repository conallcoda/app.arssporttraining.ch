<?php

use Coda\Cms\Models\Enums\GenderEnum;
use Coda\Cms\Models\Enums\UserTypeEnum;
use Coda\Cms\Models\Media;
use Coda\Cms\Models\Tag;
use Coda\Cms\Models\User;
use Coda\Cms\Models\UserGroup;

return [
    'name' => null,

    // Accepts either a single relative asset path string, or an array:
    // ['light' => 'path/to/light-logo.png', 'dark' => 'path/to/dark-logo.png']
    'logo' => null,

    'home' => '/admin/dashboard',

    'default_layout' => 'cms::components.layouts.admin',

    'area_navigation' => [
        'enabled' => false,
    ],

    'breadcrumbs' => [
        'enabled' => false,
    ],

    'auth' => [
        'enabled' => true,
        'realm' => env('CMS_AUTH_REALM'),

        'passkeys' => [
            'enabled' => (bool) env('CMS_PASSKEYS_ENABLED', false),
            'allow_password_login' => (bool) env('CMS_PASSKEYS_ALLOW_PASSWORD_LOGIN', true),
        ],
    ],

    'site_title' => null,

    'title_format' => ':page_name',

    'display' => [
        'date_format' => 'd.m.Y',
        'datetime_format' => 'd.m.Y H:i:s',
    ],

    'models' => [
        'user' => User::class,
        'user_group' => UserGroup::class,
        'tag' => Tag::class,
        'media' => Media::class,
    ],

    'tables' => [
        'user' => 'users',
        'user_group' => 'user_groups',
        'user_group_membership' => 'user_group_memberships',
        'password_reset_token' => 'password_reset_tokens',
        'session' => 'sessions',
    ],

    'columns' => [
        'owner_foreign_key' => 'owner_id',
        'user_foreign_key' => 'user_id',
        'user_group_foreign_key' => 'user_group_id',
    ],

    'enums' => [
        'user_type' => UserTypeEnum::class,
        'gender' => GenderEnum::class,
    ],

    'query_builder_namespace' => 'App\\QueryBuilders',

    'admin_user_types' => null,

    'home_by_type' => null,

    'user_switching' => (bool) env('USER_SWITCHING_ADMIN', true),

    // Dev-only: when set to an email and APP_ENV is not production, every
    // request is auto-authenticated as the matching user. Set false to disable.
    // Lookup happens via config (not env()) so config:cache still works.
    'auto_login' => env('ADMIN_AUTO_LOGIN', false),

    'profile_menu' => [
        'settings' => null,
    ],
];
