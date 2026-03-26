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
];
