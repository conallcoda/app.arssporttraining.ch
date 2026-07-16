<?php

namespace Coda\SchemaKit;

use Illuminate\Support\ServiceProvider;

class SchemaKitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $migrationsPath = __DIR__.'/../database/migrations';

        $this->loadMigrationsFrom($migrationsPath);

        $this->publishes([
            $migrationsPath => database_path('migrations'),
        ], 'schema-kit-migrations');
    }
}
