<?php

namespace App\Http\Controllers\Training;

use App\Models\Training\TrainingProgramSlot;
use App\Support\Training\SlotStatusPresenter;
use App\Training\CalendarBlockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SlotDetailsController
{
    public function memberColors(Request $request): JsonResponse
    {
        $request->validate([
            'group_id' => 'required|integer',
            'start' => 'required|date',
            'end' => 'required|date',
        ]);

        $start = Carbon::parse($request->start);
        $end = Carbon::parse($request->end);

        $slots = TrainingProgramSlot::query()
            ->join('training_programs', 'training_program_slots.training_program_id', '=', 'training_programs.id')
            ->join('exercise_programs', 'training_programs.exercise_program_id', '=', 'exercise_programs.id')
            ->leftJoin('tags', 'exercise_programs.exercise_category_id', '=', 'tags.id')
            ->whereNull('training_programs.deleted_at')
            ->where('training_programs.group_id', $request->integer('group_id'))
            ->whereBetween('training_program_slots.datetime', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->selectRaw('training_program_slots.user_id, DATE(training_program_slots.datetime) as slot_date, COALESCE(tags.color, \'_none\') as category_color, COUNT(*) as cnt')
            ->groupByRaw('training_program_slots.user_id, DATE(training_program_slots.datetime), COALESCE(tags.color, \'_none\')')
            ->get();

        $result = [];
        foreach ($slots as $row) {
            $result[$row->user_id][$row->slot_date][$row->category_color] = $row->cnt;
        }

        return response()->json($result);
    }

    protected function statusPresenter(): SlotStatusPresenter
    {
        return app(SlotStatusPresenter::class);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'training_program_id' => 'required|integer',
            'date' => 'required|date',
            'user_id' => 'nullable|integer',
        ]);

        $date = Carbon::parse($request->date);

        $query = TrainingProgramSlot::query()
            ->where('training_program_id', $request->training_program_id)
            ->whereBetween('datetime', [$date->startOfDay()->toDateTimeString(), $date->copy()->endOfDay()->toDateTimeString()])
            ->join('users', 'training_program_slots.user_id', '=', 'users.id')
            ->select('training_program_slots.user_id', 'datetime', 'training_program_slots.status', 'users.forename', 'users.surname')
            ->orderBy('datetime');

        if ($request->filled('user_id')) {
            $query->where('training_program_slots.user_id', $request->integer('user_id'));
        }

        $slots = $query->get();

        if ($request->filled('user_id')) {
            $result = $slots->map(fn ($row) => [
                'time' => $row->datetime->format('H:i'),
                'name' => trim("{$row->forename} {$row->surname}"),
                'userId' => $row->user_id,
                'statusColor' => $this->statusPresenter()->color($row->status),
            ]);
        } else {
            $grouped = [];
            foreach ($slots as $row) {
                $time = $row->datetime->format('H:i');
                $grouped[$time]['time'] = $time;
                $grouped[$time]['names'][] = trim("{$row->forename} {$row->surname}");
                $grouped[$time]['_statuses'][] = $row->status;
            }
            $result = [];
            foreach ($grouped as $entry) {
                $statuses = $entry['_statuses'] ?? [];
                unset($entry['_statuses']);
                $entry['statusColor'] = $this->statusPresenter()->aggregateColor($statuses);
                $result[] = $entry;
            }
        }

        return response()->json($result);
    }

    public function weekPage(Request $request): JsonResponse
    {
        $request->validate([
            'group_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'start' => 'required|date',
            'end' => 'required|date',
        ]);

        $start = Carbon::parse($request->start);
        $end = Carbon::parse($request->end);

        if ($request->filled('group_id') && ! $request->filled('user_id')) {
            $result = $this->athleteSlots($request->integer('group_id'), $start, $end);
        } else {
            $result = $this->programSlots($request->integer('user_id'), $start, $end);
        }

        return response()->json($result);
    }

    private function athleteSlots(int $groupId, Carbon $start, Carbon $end): array
    {
        $slots = TrainingProgramSlot::query()
            ->join('training_programs', 'training_program_slots.training_program_id', '=', 'training_programs.id')
            ->join('exercise_programs', 'training_programs.exercise_program_id', '=', 'exercise_programs.id')
            ->leftJoin('tags', 'exercise_programs.exercise_category_id', '=', 'tags.id')
            ->join('users', 'training_program_slots.user_id', '=', 'users.id')
            ->whereNull('training_programs.deleted_at')
            ->where('training_programs.group_id', $groupId)
            ->whereBetween('training_program_slots.datetime', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->selectRaw("training_programs.id as training_program_id, training_programs.exercise_program_id, DATE(training_program_slots.datetime) as slot_date, TIME(training_program_slots.datetime) as slot_time, exercise_programs.name as program_name, tags.color as category_color, training_program_slots.status as slot_status, TRIM(CONCAT(users.forename, ' ', users.surname)) as user_name")
            ->get();

        $rawByDate = [];
        foreach ($slots as $slot) {
            $date = $slot->slot_date;
            $time = substr($slot->slot_time, 0, 5);
            $key = $slot->exercise_program_id.'-'.$time;

            $rawByDate[$date][$key]['trainingProgramId'] = $slot->training_program_id;
            $rawByDate[$date][$key]['name'] = $slot->program_name;
            $rawByDate[$date][$key]['color'] = $slot->category_color;
            $rawByDate[$date][$key]['time'] = $time;
            $rawByDate[$date][$key]['userNames'][] = $slot->user_name;
            $rawByDate[$date][$key]['_statuses'][] = $slot->slot_status;
        }

        $result = [];
        foreach ($rawByDate as $date => $entries) {
            $dayEntries = array_values(array_map(function (array $entry): array {
                $statuses = $entry['_statuses'] ?? [];
                unset($entry['_statuses']);
                $entry['statusColor'] = $this->statusPresenter()->aggregateColor($statuses);

                return $entry;
            }, $entries));
            $am = array_values(array_filter($dayEntries, fn ($e) => $e['time'] < '12:00'));
            $pm = array_values(array_filter($dayEntries, fn ($e) => $e['time'] >= '12:00'));
            usort($am, fn ($a, $b) => $a['time'] <=> $b['time'] ?: $a['name'] <=> $b['name']);
            usort($pm, fn ($a, $b) => $a['time'] <=> $b['time'] ?: $a['name'] <=> $b['name']);
            $result[$date] = ['am' => $am, 'pm' => $pm];
        }

        return $result;
    }

    private function programSlots(int $userId, Carbon $start, Carbon $end): array
    {
        $slots = TrainingProgramSlot::query()
            ->join('training_programs', 'training_program_slots.training_program_id', '=', 'training_programs.id')
            ->join('exercise_programs', 'training_programs.exercise_program_id', '=', 'exercise_programs.id')
            ->leftJoin('tags', 'exercise_programs.exercise_category_id', '=', 'tags.id')
            ->whereNull('training_programs.deleted_at')
            ->where('training_program_slots.user_id', $userId)
            ->whereBetween('training_program_slots.datetime', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->selectRaw('training_programs.id as training_program_id, DATE(training_program_slots.datetime) as slot_date, TIME(training_program_slots.datetime) as slot_time, exercise_programs.name as program_name, tags.color as category_color, training_program_slots.status as slot_status')
            ->get();

        $result = [];
        foreach ($slots as $slot) {
            $date = $slot->slot_date;
            $time = substr($slot->slot_time, 0, 5);

            $entry = [
                'trainingProgramId' => $slot->training_program_id,
                'name' => $slot->program_name,
                'color' => $slot->category_color,
                'time' => $time,
                'userNames' => [],
                'statusColor' => $this->statusPresenter()->color($slot->slot_status),
            ];

            if ($time < '12:00') {
                $result[$date]['am'][] = $entry;
            } else {
                $result[$date]['pm'][] = $entry;
            }
        }

        return $result;
    }

    public function gridCells(Request $request): JsonResponse
    {
        $request->validate([
            'group_id' => 'required|integer',
            'start' => 'required|date',
            'end' => 'required|date',
            'user_id' => 'nullable|integer',
        ]);

        $groupId = $request->integer('group_id');
        $start = Carbon::parse($request->start)->startOfDay();
        $end = Carbon::parse($request->end)->endOfDay();
        $userId = $request->filled('user_id') ? $request->integer('user_id') : null;

        $userFilter = $userId ? 'AND tps.user_id = ?' : '';
        $bindings = $userId
            ? [$groupId, $start, $end, $userId]
            : [$groupId, $start, $end];

        $timeSelect = $userId ? ', MIN(TIME(tps.datetime)) as first_time' : '';

        $rows = DB::select("
            SELECT tp.id as program_id,
                   ep.exercise_category_id as category_id,
                   DATE(tps.datetime) as slot_date,
                   COUNT(DISTINCT tps.user_id) as user_count,
                   SUM(CASE WHEN tps.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                   SUM(CASE WHEN tps.status = 'partially_completed' THEN 1 ELSE 0 END) as partial_count,
                   SUM(CASE WHEN tps.status = 'skipped' THEN 1 ELSE 0 END) as skipped_count,
                   SUM(CASE WHEN tps.status = 'pending' OR tps.status IS NULL THEN 1 ELSE 0 END) as pending_count
                   {$timeSelect}
            FROM training_program_slots tps
            JOIN training_programs tp ON tps.training_program_id = tp.id
            JOIN exercise_programs ep ON tp.exercise_program_id = ep.id
            WHERE tp.group_id = ?
              AND tp.deleted_at IS NULL
              AND tps.datetime BETWEEN ? AND ?
              {$userFilter}
            GROUP BY tp.id, ep.exercise_category_id, DATE(tps.datetime)
        ", $bindings);

        $sessionNumbers = $this->computeSessionNumbers($rows, $groupId, $userId, $start, $end);

        $cells = [];
        foreach ($rows as $row) {
            $key = $row->program_id.'-'.$row->slot_date;
            $session = $sessionNumbers[$key] ?? null;
            $statusCounts = [
                'completed' => (int) $row->completed_count,
                'partial' => (int) $row->partial_count,
                'skipped' => (int) $row->skipped_count,
                'pending' => (int) $row->pending_count,
            ];

            if ($userId) {
                $cell = [
                    'count' => (int) $row->user_count,
                    'time' => substr($row->first_time, 0, 5),
                    'status' => $this->statusPresenter()->aggregateStatus($statusCounts),
                ];
            } else {
                $cell = [
                    'count' => (int) $row->user_count,
                    'completedCount' => $statusCounts['completed'],
                    'partialCount' => $statusCounts['partial'],
                    'skippedCount' => $statusCounts['skipped'],
                    'pendingCount' => $statusCounts['pending'],
                    'status' => $this->statusPresenter()->aggregateStatus($statusCounts),
                ];
            }

            if ($session !== null) {
                $cell['session'] = $session;
            }

            $cells[$key] = $cell;
        }

        return response()->json($cells);
    }

    private function computeSessionNumbers(array $rows, int $groupId, ?int $userId, Carbon $start, Carbon $end): array
    {
        $blockService = app(CalendarBlockService::class);
        $data = $blockService->getCategoryBlocksForDateRange($groupId, $userId, $start, $end);
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
            $catBlocks = $blockRanges[$info['category_id']] ?? [];
            $dates = $info['dates'];
            sort($dates);

            foreach ($catBlocks as $block) {
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

    public function userDaySlots(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'date' => 'required|date',
        ]);

        $date = Carbon::parse($request->date);

        $slots = DB::select('
            SELECT tps.datetime,
                   tps.status,
                   ep.name as program_name,
                   t.color as category_color
            FROM training_program_slots tps
            JOIN training_programs tp ON tps.training_program_id = tp.id
            JOIN exercise_programs ep ON tp.exercise_program_id = ep.id
            LEFT JOIN tags t ON ep.exercise_category_id = t.id
            WHERE tps.user_id = ?
              AND tp.deleted_at IS NULL
              AND tps.datetime BETWEEN ? AND ?
            ORDER BY tps.datetime
        ', [$request->integer('user_id'), $date->startOfDay(), $date->copy()->endOfDay()]);

        $result = array_map(fn ($row) => [
            'time' => Carbon::parse($row->datetime)->format('H:i'),
            'name' => $row->program_name,
            'color' => $row->category_color,
            'statusColor' => $this->statusPresenter()->color($row->status),
        ], $slots);

        return response()->json($result);
    }
}
