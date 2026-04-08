<?php

use Coda\Cms\Models\Enums\GenderEnum;
use Coda\Cms\Models\Enums\UserTypeEnum;
use Coda\Cms\Models\Tag;
use Coda\Cms\Models\User;
use Coda\Cms\Models\UserGroup;

return [
    'name' => null,

    'logo' => null,

    'home' => '/admin/dashboard',

    'default_layout' => 'cms::components.layouts.admin',

    'auth' => [
        'enabled' => true,
    ],

    'site_title' => null,

    'title_format' => ':page_name // :site_title',

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

    'admin_user_types' => null,

    'home_by_type' => null,

    'user_switching' => (bool) env('USER_SWITCHING_ENABLED', false),

    'mobile_preview' => (bool) env('MOBILE_PREVIEW_ENABLED', false),
];
