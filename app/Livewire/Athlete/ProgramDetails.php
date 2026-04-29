<?php

namespace App\Livewire\Athlete;

use App\Data\Athlete\ProgramDetailsExerciseData;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Support\AthleteDashboardDate;
use App\Support\Athlete\ProgramDetailsExerciseViewBuilder;
use App\Training\ExerciseGroupLabeler;
use App\Training\TrainingSessionMaterializer;
use App\Training\TrainingSessionProgressService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ProgramDetails extends Component
{
    private const SECTION_ORDER = [
        'warm_up',
        'main',
        'warm_down',
    ];

    private const SECTION_LABELS = [
        'warm_up' => 'Warm Up',
        'main' => 'Program',
        'warm_down' => 'Warm Down',
    ];

    public string $date;

    public int $trainingProgramId;

    public ?string $from = null;

    public string $activeSection = 'main';

    public function mount(string $date, TrainingProgram $trainingProgram): void
    {
        $this->date = CarbonImmutable::parse($date)->format('Y-m-d');
        $this->trainingProgramId = $trainingProgram->id;
        $this->from = $this->sanitizeReturnUrl(request()->query('from'));
        $this->activeSection = 'main';

        abort_unless(
            TrainingProgramSlot::query()
                ->where('training_program_id', $this->trainingProgramId)
                ->where('user_id', auth()->id())
                ->whereDate('datetime', $this->date)
                ->exists(),
            404
        );
    }

    #[Computed]
    public function trainingProgram(): TrainingProgram
    {
        return $this->currentSlot->trainingProgram;
    }

    #[Computed]
    public function currentSlot(): TrainingProgramSlot
    {
        $slot = TrainingProgramSlot::query()
            ->with([
                'trainingProgram.program.exerciseCategory',
                'trainingProgram.program.exercises',
                'exercises.exercise.category',
                'exercises.exercise.equipment',
                'exercises.exercise.modifiers',
                'exercises.exercise.media',
                'exercises.sets.values',
            ])
            ->where('training_program_id', $this->trainingProgramId)
            ->where('user_id', auth()->id())
            ->whereDate('datetime', $this->date)
            ->orderBy('datetime')
            ->orderBy('id')
            ->firstOrFail();

        if ($slot->compiled_at === null) {
            app(TrainingSessionMaterializer::class)->materialize($slot, force: true);

            $slot = $slot->fresh([
                'trainingProgram.program.exerciseCategory',
                'trainingProgram.program.exercises',
                'exercises.exercise.category',
                'exercises.exercise.equipment',
                'exercises.exercise.modifiers',
                'exercises.exercise.media',
                'exercises.sets.values',
            ]);
        } elseif ($this->shouldRefreshAuxiliarySections($slot)) {
            app(TrainingSessionMaterializer::class)->materialize($slot, force: true);

            $slot = $slot->fresh([
                'trainingProgram.program.exerciseCategory',
                'trainingProgram.program.exercises',
                'exercises.exercise.category',
                'exercises.exercise.equipment',
                'exercises.exercise.modifiers',
                'exercises.exercise.media',
                'exercises.sets.values',
            ]);
        }

        return $slot;
    }

    #[Computed]
    public function programExercises(): array
    {
        $sorted = $this->currentSlot->exercises
            ->where('type', $this->activeSection)
            ->sortBy('sort')
            ->values();

        $materializedGroupLabels = ExerciseGroupLabeler::label(
            $sorted,
            fn (TrainingProgramSlotExercise $exercise): ?string => $exercise->group,
            fn (TrainingProgramSlotExercise $exercise): int => $exercise->id,
        );

        $sourceExercises = $this->trainingProgram->program->exercises()
            ->wherePivot('type', $this->activeSection)
            ->orderByPivot('sort')
            ->orderByPivot('id')
            ->get()
            ->values();

        $sourceGroupLabelsByIndex = ExerciseGroupLabeler::label(
            $sourceExercises,
            fn ($exercise): ?string => $exercise->pivot->group,
            fn ($exercise): int => $sourceExercises->search($exercise),
        );

        return $sorted
            ->map(fn (TrainingProgramSlotExercise $exercise, int $index) => app(ProgramDetailsExerciseViewBuilder::class)->build(
                $exercise,
                $index,
                $materializedGroupLabels[$exercise->id] ?? $sourceGroupLabelsByIndex[$index] ?? null,
            ))
            ->values()
            ->all();
    }

    #[Computed]
    public function sectionTabs(): array
    {
        $tabs = collect(self::SECTION_ORDER)
            ->map(function (string $section): ?array {
                $count = $this->currentSlot->exercises
                    ->where('type', $section)
                    ->count();

                if ($count === 0) {
                    return null;
                }

                return [
                    'key' => $section,
                    'label' => self::SECTION_LABELS[$section] ?? Str::headline($section),
                    'count' => $count,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if (! collect($tabs)->pluck('key')->contains($this->activeSection)) {
            $this->activeSection = collect($tabs)->pluck('key')->contains('main')
                ? 'main'
                : (collect($tabs)->first()['key'] ?? 'main');
        }

        return $tabs;
    }

    #[Computed]
    public function showsSectionTabs(): bool
    {
        return collect($this->sectionTabs)
            ->pluck('key')
            ->contains(fn (string $key): bool => $key !== 'main');
    }

    /**
     * @return array<int, array{submitted: bool, color: array{light: string, dark: string}}>
     */
    #[Computed]
    public function progressSegments(): array
    {
        return $this->currentSlot->exercises
            ->sortBy('sort')
            ->values()
            ->map(fn (TrainingProgramSlotExercise $exercise) => [
                'submitted' => $exercise->status->isSubmitted(),
                'color' => $exercise->status->barColor(),
            ])
            ->all();
    }

    #[Computed]
    public function completionPercent(): int
    {
        $segments = $this->progressSegments;
        $total = count($segments);

        if ($total === 0) {
            return 0;
        }

        $submitted = array_filter($segments, fn (array $segment): bool => $segment['submitted']);

        return (int) round((count($submitted) / $total) * 100);
    }

    #[Computed]
    public function backUrl(): string
    {
        return $this->from ?: route('athlete.dashboard.calendar', ['date' => $this->date]);
    }

    #[Computed]
    public function backLabel(): string
    {
        $path = parse_url($this->backUrl, PHP_URL_PATH) ?: '';

        return Str::startsWith($path, '/dashboard/calendar')
            ? 'Back to Calendar'
            : 'Back to Dashboard';
    }

    public function markExerciseCompleted(int $slotExerciseId): void
    {
        abort_if($this->isFutureSession, 403);

        $exercise = $this->currentSlot->exercises->firstWhere('id', $slotExerciseId);
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);

        app(TrainingSessionProgressService::class)->markExerciseCompleted($exercise);

        unset($this->currentSlot, $this->programExercises, $this->progressSegments, $this->sectionTabs, $this->showsSectionTabs);
    }

    public function markExerciseSkipped(int $slotExerciseId): void
    {
        abort_if($this->isFutureSession, 403);

        $exercise = $this->currentSlot->exercises->firstWhere('id', $slotExerciseId);
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);

        app(TrainingSessionProgressService::class)->markExerciseSkipped($exercise);

        unset($this->currentSlot, $this->programExercises, $this->progressSegments, $this->sectionTabs, $this->showsSectionTabs);
    }

    protected function shouldRefreshAuxiliarySections(TrainingProgramSlot $slot): bool
    {
        $sourceHasAuxiliarySections = $slot->trainingProgram->program->exercises
            ->contains(fn ($exercise): bool => ($exercise->pivot->type ?? 'main') !== 'main');

        if (! $sourceHasAuxiliarySections) {
            return false;
        }

        $slotHasAuxiliarySections = $slot->exercises
            ->contains(fn (TrainingProgramSlotExercise $exercise): bool => in_array($exercise->type ?? 'main', ['warm_up', 'warm_down'], true));

        if ($slotHasAuxiliarySections) {
            return false;
        }

        return (int) $slot->completed_exercise_count === 0
            && (int) $slot->partial_exercise_count === 0
            && (int) $slot->skipped_exercise_count === 0
            && ! $slot->has_any_modification;
    }

    #[Computed]
    public function isFutureSession(): bool
    {
        return AthleteDashboardDate::isFutureDate($this->date);
    }

    protected function sanitizeReturnUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (Str::startsWith($url, '/')) {
            return $url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $appUrl = parse_url(url('/'));
        $returnUrl = parse_url($url);

        if (($appUrl['host'] ?? null) !== ($returnUrl['host'] ?? null)) {
            return null;
        }

        $path = $returnUrl['path'] ?? '/';
        $query = isset($returnUrl['query']) ? '?'.$returnUrl['query'] : '';
        $fragment = isset($returnUrl['fragment']) ? '#'.$returnUrl['fragment'] : '';

        return $path.$query.$fragment;
    }

    public function categoryBadgeStyle(?string $color): ?string
    {
        if (! is_string($color) || trim($color) === '') {
            return null;
        }

        $rawColor = trim($color);
        $normalized = ltrim($rawColor, '#');

        if (preg_match('/^[0-9a-fA-F]{3}$/', $normalized)) {
            $normalized = implode('', array_map(
                fn (string $char): string => $char.$char,
                str_split($normalized)
            ));
        }

        $textColor = '#ffffff';

        if (preg_match('/^[0-9a-fA-F]{6}$/', $normalized)) {
            $red = hexdec(substr($normalized, 0, 2));
            $green = hexdec(substr($normalized, 2, 2));
            $blue = hexdec(substr($normalized, 4, 2));
            $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;
            $textColor = $luminance > 160 ? '#111827' : '#ffffff';
            $rawColor = '#'.$normalized;
        } elseif (! preg_match('/^[#(),.%\-\sa-zA-Z0-9]+$/', $rawColor)) {
            return null;
        }

        return sprintf('background-color: %s; color: %s;', $rawColor, $textColor);
    }

    public function render(): View
    {
        return view('livewire.athlete.program-details', [
            'trainingProgram' => $this->trainingProgram,
            'currentSlot' => $this->currentSlot,
            'programExercises' => $this->programExercises,
            'sectionTabs' => $this->sectionTabs,
            'showsSectionTabs' => $this->showsSectionTabs,
            'isFutureSession' => $this->isFutureSession,
        ])->layout('components.layouts.athlete', ['title' => $this->trainingProgram->program->name]);
    }
}
