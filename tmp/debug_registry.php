<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$registry = app(Coda\Cms\Registry::class);

echo 'modules='.$registry->modules()->count().PHP_EOL;
echo 'pages='.$registry->pages()->count().PHP_EOL;
echo 'components='.$registry->components()->count().PHP_EOL;

$navigation = $registry->navigation();

echo 'nav_groups='.count($navigation).PHP_EOL;

foreach ($navigation as $group) {
    echo 'group '.$group->key.' items='.count($group->items).PHP_EOL;

    foreach ($group->items as $item) {
        echo '  item '.$item->label
            .' route='.($item->route ?? 'null')
            .' children='.count($item->items)
            .' tabs='.count($item->tabs)
            .PHP_EOL;
    }
}

$page = $registry->page('exercise-program-index');
echo 'page='.json_encode($page->toArray(), JSON_PRETTY_PRINT).PHP_EOL;

$tabs = $registry->tabsForRoute('exercise-program-index');
echo 'tabs='.json_encode(array_map(fn ($tab) => $tab->toArray(), $tabs), JSON_PRETTY_PRINT).PHP_EOL;

echo PHP_EOL.'--- exercise program probe ---'.PHP_EOL;

Illuminate\Support\Facades\DB::flushQueryLog();
Illuminate\Support\Facades\DB::enableQueryLog();

$component = new App\Livewire\Training\ExerciseProgramList;
$component->mount();
$items = $component->items();

echo 'items_count='.$items->count().PHP_EOL;
echo 'query_count_after_items='.count(Illuminate\Support\Facades\DB::getQueryLog()).PHP_EOL;

$first = $items->first();
if ($first) {
    echo 'first_model_class='.get_class($first).PHP_EOL;

    $before = count(Illuminate\Support\Facades\DB::getQueryLog());
    $data = App\Data\Training\ExerciseProgramData::fromModel($first);
    echo 'query_count_after_data_from_model='.(count(Illuminate\Support\Facades\DB::getQueryLog()) - $before).PHP_EOL;

    $before = count(Illuminate\Support\Facades\DB::getQueryLog());
    $array = $data->toArray();
    echo 'query_count_after_data_to_array='.(count(Illuminate\Support\Facades\DB::getQueryLog()) - $before).PHP_EOL;
    echo 'data_keys='.implode(',', array_keys($array)).PHP_EOL;

    $before = count(Illuminate\Support\Facades\DB::getQueryLog());
    $config = $first->config;
    echo 'query_count_after_model_config='.(count(Illuminate\Support\Facades\DB::getQueryLog()) - $before).PHP_EOL;
    echo 'config_class='.get_class($config).PHP_EOL;
    echo 'override_values_count='.count($config->overrideValues).PHP_EOL;
}

$queries = Illuminate\Support\Facades\DB::getQueryLog();
foreach (array_slice($queries, 0, 20) as $index => $query) {
    echo 'query['.$index.']='.$query['query'].PHP_EOL;
}
