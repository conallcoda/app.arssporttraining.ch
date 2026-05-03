<?php

namespace App\Support\Training;

class SlotWeekPagePresenter
{
    public function __construct(
        private readonly SlotStatusPresenter $statusPresenter,
    ) {}

    public function presentGrouped(iterable $slots): array
    {
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
                $entry['statusColor'] = $this->statusPresenter->aggregateColor($statuses);

                return $entry;
            }, $entries));

            $result[$date] = $this->splitMeridiem($dayEntries);
        }

        return $result;
    }

    public function presentUser(iterable $slots): array
    {
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
                'statusColor' => $this->statusPresenter->color($slot->slot_status),
            ];

            if ($time < '12:00') {
                $result[$date]['am'][] = $entry;
            } else {
                $result[$date]['pm'][] = $entry;
            }
        }

        foreach ($result as $date => $entries) {
            $result[$date] = $this->splitMeridiem(array_merge($entries['am'] ?? [], $entries['pm'] ?? []));
        }

        return $result;
    }

    private function splitMeridiem(array $entries): array
    {
        $am = array_values(array_filter($entries, fn (array $entry): bool => $entry['time'] < '12:00'));
        $pm = array_values(array_filter($entries, fn (array $entry): bool => $entry['time'] >= '12:00'));

        usort($am, fn (array $a, array $b): int => $a['time'] <=> $b['time'] ?: $a['name'] <=> $b['name']);
        usort($pm, fn (array $a, array $b): int => $a['time'] <=> $b['time'] ?: $a['name'] <=> $b['name']);

        return ['am' => $am, 'pm' => $pm];
    }
}
