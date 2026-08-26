<?php

namespace App\Providers;

use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Observers\TrainingDefinitionAuditObserver;
use App\Training\TrainingAuditContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TrainingAuditContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ExerciseProgram::observe(TrainingDefinitionAuditObserver::class);
        ExerciseProgramExercise::observe(TrainingDefinitionAuditObserver::class);
        TrainingProgram::observe(TrainingDefinitionAuditObserver::class);
        TrainingProgramBlock::observe(TrainingDefinitionAuditObserver::class);

        RateLimiter::for('client-js-log', function ($request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        if ($this->app->environment('local')) {
            Mail::alwaysTo('conall+test@coda.works');
        }
    }
}
