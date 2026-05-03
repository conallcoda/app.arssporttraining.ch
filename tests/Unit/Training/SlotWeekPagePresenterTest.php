<?php

use App\Support\Training\SlotStatusPresenter;
use App\Support\Training\SlotWeekPagePresenter;

it('groups athlete slots by program and splits them into am and pm buckets', function () {
    $presenter = new SlotWeekPagePresenter(new SlotStatusPresenter);

    $result = $presenter->presentGrouped([
        fakeWeekSlot(
            trainingProgramId: 10,
            exerciseProgramId: 50,
            slotDate: '2030-04-02',
            slotTime: '09:15:00',
            name: 'Tempo Run',
            color: '#f00',
            status: 'completed',
            userName: 'Ada Lovelace',
        ),
        fakeWeekSlot(
            trainingProgramId: 10,
            exerciseProgramId: 50,
            slotDate: '2030-04-02',
            slotTime: '09:15:00',
            name: 'Tempo Run',
            color: '#f00',
            status: 'pending',
            userName: 'Grace Hopper',
        ),
        fakeWeekSlot(
            trainingProgramId: 11,
            exerciseProgramId: 51,
            slotDate: '2030-04-02',
            slotTime: '14:00:00',
            name: 'Strength',
            color: '#0f0',
            status: 'skipped',
            userName: 'Ada Lovelace',
        ),
    ]);

    expect($result)->toBe([
        '2030-04-02' => [
            'am' => [[
                'trainingProgramId' => 10,
                'name' => 'Tempo Run',
                'color' => '#f00',
                'time' => '09:15',
                'userNames' => ['Ada Lovelace', 'Grace Hopper'],
                'statusColor' => ['light' => '252 211 77', 'dark' => '251 191 36'],
            ]],
            'pm' => [[
                'trainingProgramId' => 11,
                'name' => 'Strength',
                'color' => '#0f0',
                'time' => '14:00',
                'userNames' => ['Ada Lovelace'],
                'statusColor' => ['light' => '125 211 252', 'dark' => '56 189 248'],
            ]],
        ],
    ]);
});

it('formats user slots with per-slot status colors and sorted day sections', function () {
    $presenter = new SlotWeekPagePresenter(new SlotStatusPresenter);

    $result = $presenter->presentUser([
        fakeWeekSlot(
            trainingProgramId: 20,
            exerciseProgramId: 60,
            slotDate: '2030-04-03',
            slotTime: '15:00:00',
            name: 'Bike',
            color: '#00f',
            status: 'completed',
        ),
        fakeWeekSlot(
            trainingProgramId: 21,
            exerciseProgramId: 61,
            slotDate: '2030-04-03',
            slotTime: '08:30:00',
            name: 'Core',
            color: '#999',
            status: 'pending',
        ),
    ]);

    expect($result)->toBe([
        '2030-04-03' => [
            'am' => [[
                'trainingProgramId' => 21,
                'name' => 'Core',
                'color' => '#999',
                'time' => '08:30',
                'userNames' => [],
                'statusColor' => ['light' => '228 228 231', 'dark' => '161 161 170'],
            ]],
            'pm' => [[
                'trainingProgramId' => 20,
                'name' => 'Bike',
                'color' => '#00f',
                'time' => '15:00',
                'userNames' => [],
                'statusColor' => ['light' => '110 231 183', 'dark' => '52 211 153'],
            ]],
        ],
    ]);
});

function fakeWeekSlot(
    int $trainingProgramId,
    int $exerciseProgramId,
    string $slotDate,
    string $slotTime,
    string $name,
    ?string $color,
    string $status,
    ?string $userName = null,
): object {
    return (object) [
        'training_program_id' => $trainingProgramId,
        'exercise_program_id' => $exerciseProgramId,
        'slot_date' => $slotDate,
        'slot_time' => $slotTime,
        'program_name' => $name,
        'category_color' => $color,
        'slot_status' => $status,
        'user_name' => $userName,
    ];
}
