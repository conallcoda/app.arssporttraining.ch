<?php

namespace App\Support\Training;

use App\Models\Training\TrainingProgramSlot;
use App\Training\CalendarBlockService;
use Carbon\Carbon;

class SlotSessionNumberResolver
{
    public function __construct(
        private readonly CalendarBlockService $calendarBlockService,
    ) {}

    public function resolve(array $rows, int $groupId, ?int $userId, Carbon $start, Carbon $end): array
    {
        $data = $this->calendarBlockService->getCategoryBlocksForDateRange($groupId, $userId, $start, $end);
        $blocks = $data['blocks'];
        $overridesByParent = $data['overridesByParent'];

        $blockRanges = [];
        foreach ($blocks as $block) {
            if ($block->user_id !== null) {
                $effective = $block;
            } else {
                $override = $overridesByParent->get($block->id);
                if ($override && ! $override->active) {
                    continue;
                }
                $effective = $override ?? $block;
            }

            $blockRanges[$effective->category_id][] = [
                'start' => $effective->start->format('Y-m-d'),
                'end' => ($effective->end ?? $effective->start)->format('Y-m-d'),
            ];
        }

        $categoriesByProgram = [];
        foreach ($rows as $row) {
            $categoriesByProgram[(int) $row->program_id] = (int) $row->category_id;
        }

        $visibleKeys = [];
        foreach ($rows as $row) {
            $visibleKeys[$row->program_id.'-'.$row->slot_date] = true;
        }

        $sessionNumbers = [];
        $datesByProgram = $this->loadProgramDates(array_keys($categoriesByProgram), $userId, $blockRanges);

        foreach ($categoriesByProgram as $programId => $categoryId) {
            $categoryBlocks = $blockRanges[$categoryId] ?? [];
            $dates = $datesByProgram[$programId] ?? [];
            $dates = array_values(array_unique($dates));
            sort($dates);

            foreach ($categoryBlocks as $block) {
                $counter = 0;
                foreach ($dates as $date) {
                    if ($date >= $block['start'] && $date <= $block['end']) {
                        $counter++;
                        $key = $programId.'-'.$date;

                        if (isset($visibleKeys[$key])) {
                            $sessionNumbers[$key] = $counter;
                        }
                    }
                }
            }
        }

        return $sessionNumbers;
    }

    /**
     * @param  int[]  $programIds
     * @param  array<int, array<int, array{start: string, end: string}>>  $blockRanges
     * @return array<int, array<int, string>>
     */
    protected function loadProgramDates(array $programIds, ?int $userId, array $blockRanges): array
    {
        if ($programIds === [] || $blockRanges === []) {
            return [];
        }

        $allRanges = [];
        foreach ($blockRanges as $ranges) {
            foreach ($ranges as $range) {
                $allRanges[] = $range;
            }
        }

        if ($allRanges === []) {
            return [];
        }

        $start = collect($allRanges)->min('start');
        $end = collect($allRanges)->max('end');

        if (! is_string($start) || ! is_string($end)) {
            return [];
        }

        $rows = TrainingProgramSlot::query()
            ->selectRaw('training_program_id as program_id, DATE(datetime) as slot_date')
            ->whereIn('training_program_id', $programIds)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->whereBetween('datetime', [
                Carbon::parse($start)->startOfDay(),
                Carbon::parse($end)->endOfDay(),
            ])
            ->distinct()
            ->orderBy('datetime')
            ->get();

        $datesByProgram = [];
        foreach ($rows as $row) {
            $datesByProgram[(int) $row->program_id][] = (string) $row->slot_date;
        }

        return $datesByProgram;
    }
}
