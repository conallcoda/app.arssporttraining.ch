<?php

namespace App\Http\Controllers\Training;

use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Support\Training\ExerciseProgramSelectorPreviewService;
use App\Training\TrainingSessionRebuildDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgramSectionExerciseController
{
    public function destroy(
        Request $request,
        ExerciseProgram $exerciseProgram,
        string $section,
        ExerciseProgramExercise $programExercise,
    ): RedirectResponse {
        abort_unless(in_array($section, ['main', 'warm_up', 'warm_down'], true), 404);
        abort_unless((int) $programExercise->exercise_program_id === (int) $exerciseProgram->id, 404);
        abort_unless(($programExercise->type ?? 'main') === $section, 404);

        $isReferenced = TrainingProgramSlotExercise::query()
            ->where('exercise_program_exercise_id', $programExercise->id)
            ->exists();

        if ($isReferenced) {
            return redirect()->to($this->redirectUrl($request, $section));
        }

        DB::transaction(function () use ($exerciseProgram, $programExercise, $section): void {
            $config = $exerciseProgram->config;
            $config->removeExerciseOverrides((int) $programExercise->id);
            $config->removeExerciseOverridesForAllUsers((int) $programExercise->id);
            $exerciseProgram->config = $config;
            $exerciseProgram->saveQuietly();

            ExerciseProgramExercise::withoutEvents(function () use ($exerciseProgram, $programExercise, $section): void {
                $programExercise->delete();

                ExerciseProgramExercise::query()
                    ->where('exercise_program_id', $exerciseProgram->id)
                    ->where('type', $section)
                    ->orderBy('sort')
                    ->orderBy('id')
                    ->get(['id'])
                    ->values()
                    ->each(fn (ExerciseProgramExercise $row, int $index) => $row->updateQuietly(['sort' => $index]));
            });
        });

        app(ExerciseProgramSelectorPreviewService::class)->syncProgram($exerciseProgram->id);
        app(TrainingSessionRebuildDispatcher::class)->dispatchFutureSlotsForExerciseProgramChange($exerciseProgram->id);

        return redirect()->to($this->redirectUrl($request, $section));
    }

    private function redirectUrl(Request $request, string $section): string
    {
        $redirect = $request->input('redirect');

        if (! is_string($redirect) || $redirect === '') {
            $redirect = url()->previous();
        }

        $parts = parse_url($redirect);
        if (! is_array($parts)) {
            $parts = ['path' => '/'];
        }

        $query = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['section'] = $section;

        $path = ($parts['path'] ?? '/');
        $url = $path.'?'.http_build_query($query);

        if (isset($parts['fragment'])) {
            $url .= '#'.$parts['fragment'];
        }

        return $url;
    }
}
