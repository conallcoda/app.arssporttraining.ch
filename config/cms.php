<?php

use App\Models\Tag;
use App\Models\Users\GenderEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Models\Users\UserTypeEnum;

return [
    'name' => 'Athlete Training',

    'logo' => 'images/logo.webp',

    'site_title' => 'ARS Athlete Training',

    'home' => '/admin/programs',

    'models' => [
        'user' => User::class,
        'user_group' => UserGroup::class,
        'tag' => Tag::class,
    ],

    'enums' => [
        'user_type' => UserTypeEnum::class,
        'gender' => GenderEnum::class,
    ],

    'query_builder_namespace' => 'App\\QueryBuilders',

    'admin_user_types' => ['coach', 'admin'],

    'home_by_type' => [
        'athlete' => '/dashboard',
        '*' => '/admin/programs',
    ],

    'user_switching' => (bool) env('USER_SWITCHING_ENABLED', false),

    'mobile_preview' => (bool) env('MOBILE_PREVIEW_ENABLED', false),

    'profile_menu' => [
        'settings' => [
            'label' => 'Settings',
            'icon' => 'settings',
            'modal' => 'coach-settings',
            'user_types' => ['coach', 'admin'],
        ],
    ],
];
