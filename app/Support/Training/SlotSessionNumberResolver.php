<?php

namespace App\Support\Training;

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

        $datesByProgram = [];
        foreach ($rows as $row) {
            $datesByProgram[$row->program_id]['category_id'] = $row->category_id;
            $datesByProgram[$row->program_id]['dates'][] = $row->slot_date;
        }

        $sessionNumbers = [];
        foreach ($datesByProgram as $programId => $info) {
            $categoryBlocks = $blockRanges[$info['category_id']] ?? [];
            $dates = $info['dates'];
            sort($dates);

            foreach ($categoryBlocks as $block) {
                $counter = 0;
                foreach ($dates as $date) {
                    if ($date >= $block['start'] && $date <= $block['end']) {
                        $counter++;
                        $sessionNumbers[$programId.'-'.$date] = $counter;
                    }
                }
            }
        }

        return $sessionNumbers;
    }
}
