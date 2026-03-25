<?php

namespace Database\Seeders;

use App\Data\Athlete\Metric\MetricEnum;
use App\Models\Athlete\MetricSubmission;
use App\Models\Athlete\MetricValue;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class PerformanceTestSeeder extends Seeder
{
    public function run(): void
    {
        $coach = User::query()->where('type', 'coach')->first();

        $group = UserGroup::create([
            'name' => 'Performance Test',
            'owner_id' => $coach?->id,
        ]);

        $athletes = User::factory()
            ->athlete()
            ->count(50)
            ->create(['owner_id' => $coach?->id]);

        $group->members()->attach(
            $athletes->mapWithKeys(fn (User $user, int $index) => [
                $user->id => ['sort' => $index],
            ])
        );

        $startDate = Carbon::now();
        $endDate = Carbon::now()->addYear();

        $trainingPrograms = $this->seedTrainingPrograms($group);
        $this->seedFocusBlocks($group, $coach, $startDate, $endDate);
        $categoryBlocks = $this->seedCategoryBlocks($group, $coach, $trainingPrograms, $startDate, $endDate);

        $this->seedSlots($athletes, $categoryBlocks, $coach);
        $this->seedAllMetrics($athletes, $coach, $startDate, $endDate);

        $this->command->info("Created 50 athletes in 'Performance Test' group.");
        $this->command->info("Imported {$trainingPrograms->count()} training programs.");
        $this->command->info('Created category blocks with scheduled slots for all athletes.');
    }

    private function seedTrainingPrograms(UserGroup $group): Collection
    {
        $exercisePrograms = ExerciseProgram::all();
        $trainingPrograms = collect();

        foreach ($exercisePrograms as $program) {
            $trainingPrograms->push(
                TrainingProgram::importProgram($program, $group->id)
            );
        }

        return $trainingPrograms;
    }

    private function seedFocusBlocks(UserGroup $group, ?User $coach, Carbon $startDate, Carbon $endDate): Collection
    {
        $blocks = collect();
        $cursor = $startDate->copy();
        $blockNumber = 1;

        while ($cursor->lt($endDate)) {
            $weeks = rand(2, 5);
            $blockEnd = $cursor->copy()->addWeeks($weeks)->subDay();

            if ($blockEnd->gt($endDate)) {
                $blockEnd = $endDate->copy();
            }

            $blocks->push(TrainingProgramBlock::create([
                'group_id' => $group->id,
                'owner_id' => $coach?->id,
                'type' => 'focus',
                'color' => 'amber',
                'start' => $cursor->toDateString(),
                'end' => $blockEnd->toDateString(),
                'note' => "Block {$blockNumber}",
                'active' => true,
            ]));

            $gap = rand(0, 7);
            $cursor = $blockEnd->copy()->addDays(1 + $gap);
            $blockNumber++;
        }

        return $blocks;
    }

    private function seedCategoryBlocks(UserGroup $group, ?User $coach, Collection $trainingPrograms, Carbon $startDate, Carbon $endDate): Collection
    {
        $ids = $trainingPrograms->pluck('id');
        $loaded = TrainingProgram::with('program.exerciseCategory')->whereIn('id', $ids)->get();

        $programsByCategory = $loaded
            ->groupBy(fn (TrainingProgram $tp) => $tp->program->exercise_category_id);

        $allCategoryBlocks = collect();

        foreach ($programsByCategory as $categoryId => $programs) {
            if (! $categoryId) {
                continue;
            }

            $category = $programs->first()->program->exerciseCategory;
            $categoryName = $category->name;
            $categoryColor = $category->color;
            $cursor = $startDate->copy();
            $sequenceNumber = 1;
            $blocks = collect();

            while ($cursor->lt($endDate)) {
                $weeks = rand(2, 5);
                $blockEnd = $cursor->copy()->addWeeks($weeks)->subDay();

                if ($blockEnd->gt($endDate)) {
                    $blockEnd = $endDate->copy();
                }

                $blocks->push(TrainingProgramBlock::create([
                    'group_id' => $group->id,
                    'owner_id' => $coach?->id,
                    'category_id' => $categoryId,
                    'type' => 'category',
                    'color' => $categoryColor,
                    'start' => $cursor->toDateString(),
                    'end' => $blockEnd->toDateString(),
                    'note' => "{$categoryName} {$sequenceNumber}",
                    'active' => true,
                ]));

                $gap = rand(0, 7);
                $cursor = $blockEnd->copy()->addDays(1 + $gap);
                $sequenceNumber++;
            }

            foreach ($programs as $program) {
                $allCategoryBlocks->push([
                    'training_program' => $program,
                    'blocks' => $blocks,
                ]);
            }
        }

        return $allCategoryBlocks;
    }

    private function seedSlots(Collection $athletes, Collection $categoryBlocks, ?User $coach): void
    {
        $now = now();
        $rows = [];

        foreach ($athletes as $athlete) {
            foreach ($categoryBlocks as $entry) {
                $trainingProgramId = $entry['training_program']->id;

                foreach ($entry['blocks'] as $block) {
                    $date = Carbon::parse($block->start);
                    $blockEnd = Carbon::parse($block->end);

                    while ($date->lte($blockEnd)) {
                        $time = rand(0, 1) === 0 ? '09:00:00' : '14:00:00';

                        $rows[] = [
                            'training_program_id' => $trainingProgramId,
                            'user_id' => $athlete->id,
                            'owner_id' => $coach?->id,
                            'datetime' => $date->format('Y-m-d').' '.$time,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $date->addDays(rand(1, 3));
                    }
                }
            }
        }

        foreach (array_chunk($rows, 5000) as $chunk) {
            TrainingProgramSlot::insert($chunk);
        }
    }

    private function seedAllMetrics(Collection $athletes, ?User $coach, Carbon $startDate, Carbon $endDate): void
    {
        $now = now();
        $submissionRows = [];
        $athleteMetricDates = [];

        foreach ($athletes as $athlete) {
            foreach ([MetricEnum::OneRepMax, MetricEnum::HeartRate] as $metric) {
                $date = $startDate->copy();

                while ($date->lte($endDate)) {
                    $submissionRows[] = [
                        'user_id' => $athlete->id,
                        'metric' => $metric->value,
                        'recorded_by' => $coach?->id,
                        'recorded_at' => $date->toDateString(),
                        'owner_type' => User::class,
                        'owner_id' => $athlete->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $athleteMetricDates[] = $metric;

                    $date->addDays(rand(7, 10));
                }
            }
        }

        foreach (array_chunk($submissionRows, 5000) as $chunk) {
            MetricSubmission::insert($chunk);
        }

        $firstSubmissionId = MetricSubmission::query()
            ->where('created_at', $now)
            ->orderBy('id')
            ->value('id');

        $valueRows = [];

        foreach ($athleteMetricDates as $index => $metric) {
            $submissionId = $firstSubmissionId + $index;

            if ($metric === MetricEnum::OneRepMax) {
                $valueRows[] = [
                    'submission_id' => $submissionId,
                    'field' => 'measuredReps',
                    'value' => (string) rand(1, 5),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $valueRows[] = [
                    'submission_id' => $submissionId,
                    'field' => 'measuredWeight',
                    'value' => (string) rand(40, 150),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($metric === MetricEnum::HeartRate) {
                $valueRows[] = [
                    'submission_id' => $submissionId,
                    'field' => 'heartRate',
                    'value' => (string) rand(55, 200),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $valueRows[] = [
                    'submission_id' => $submissionId,
                    'field' => 'anaerobicThreshold',
                    'value' => (string) rand(75, 95),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($valueRows, 5000) as $chunk) {
            MetricValue::insert($chunk);
        }
    }
}
