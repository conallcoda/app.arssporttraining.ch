<?php

use App\Support\Training\SlotSessionNumberResolver;
use App\Training\CalendarBlockService;
use Carbon\Carbon;

it('numbers sessions within category block ranges', function () {
    $service = Mockery::mock(CalendarBlockService::class);
    $service->shouldReceive('getCategoryBlocksForDateRange')
        ->once()
        ->andReturn(collect([
            'blocks' => collect([
                fakeBlock(id: 10, categoryId: 7, start: '2030-04-01', end: '2030-04-10'),
            ]),
            'overridesByParent' => collect(),
        ]));

    $resolver = new class($service) extends SlotSessionNumberResolver
    {
        protected function loadProgramDates(array $programIds, ?int $userId, array $blockRanges): array
        {
            return [
                100 => ['2030-04-02', '2030-04-04', '2030-04-11'],
            ];
        }
    };

    expect($resolver->resolve(
        rows: [
            fakeSlotRow(programId: 100, categoryId: 7, slotDate: '2030-04-02'),
            fakeSlotRow(programId: 100, categoryId: 7, slotDate: '2030-04-04'),
            fakeSlotRow(programId: 100, categoryId: 7, slotDate: '2030-04-11'),
        ],
        groupId: 1,
        userId: null,
        start: Carbon::parse('2030-04-01'),
        end: Carbon::parse('2030-04-30'),
    ))->toBe([
        '100-2030-04-02' => 1,
        '100-2030-04-04' => 2,
    ]);
});

it('applies active overrides and skips disabled parent blocks', function () {
    $service = Mockery::mock(CalendarBlockService::class);
    $service->shouldReceive('getCategoryBlocksForDateRange')
        ->once()
        ->andReturn(collect([
            'blocks' => collect([
                fakeBlock(id: 20, categoryId: 9, start: '2030-05-01', end: '2030-05-10'),
                fakeBlock(id: 21, categoryId: 11, start: '2030-05-01', end: '2030-05-10'),
            ]),
            'overridesByParent' => collect([
                20 => fakeBlock(id: 200, categoryId: 9, start: '2030-05-03', end: '2030-05-04', active: true, userId: 55),
                21 => fakeBlock(id: 201, categoryId: 11, start: '2030-05-01', end: '2030-05-10', active: false, userId: 55),
            ]),
        ]));

    $resolver = new class($service) extends SlotSessionNumberResolver
    {
        protected function loadProgramDates(array $programIds, ?int $userId, array $blockRanges): array
        {
            return [
                300 => ['2030-05-02', '2030-05-03', '2030-05-04'],
                400 => ['2030-05-03'],
            ];
        }
    };

    expect($resolver->resolve(
        rows: [
            fakeSlotRow(programId: 300, categoryId: 9, slotDate: '2030-05-02'),
            fakeSlotRow(programId: 300, categoryId: 9, slotDate: '2030-05-03'),
            fakeSlotRow(programId: 300, categoryId: 9, slotDate: '2030-05-04'),
            fakeSlotRow(programId: 400, categoryId: 11, slotDate: '2030-05-03'),
        ],
        groupId: 2,
        userId: 55,
        start: Carbon::parse('2030-05-01'),
        end: Carbon::parse('2030-05-31'),
    ))->toBe([
        '300-2030-05-03' => 1,
        '300-2030-05-04' => 2,
    ]);
});

it('numbers visible sessions from the full block timeline rather than the filtered window', function () {
    $service = Mockery::mock(CalendarBlockService::class);
    $service->shouldReceive('getCategoryBlocksForDateRange')
        ->once()
        ->andReturn(collect([
            'blocks' => collect([
                fakeBlock(id: 30, categoryId: 7, start: '2030-05-01', end: '2030-05-31'),
            ]),
            'overridesByParent' => collect(),
        ]));

    $resolver = new class($service) extends SlotSessionNumberResolver
    {
        protected function loadProgramDates(array $programIds, ?int $userId, array $blockRanges): array
        {
            return [
                500 => ['2030-05-01', '2030-05-04', '2030-05-07'],
            ];
        }
    };

    expect($resolver->resolve(
        rows: [
            fakeSlotRow(programId: 500, categoryId: 7, slotDate: '2030-05-04'),
            fakeSlotRow(programId: 500, categoryId: 7, slotDate: '2030-05-07'),
        ],
        groupId: 1,
        userId: null,
        start: Carbon::parse('2030-05-04'),
        end: Carbon::parse('2030-05-10'),
    ))->toBe([
        '500-2030-05-04' => 2,
        '500-2030-05-07' => 3,
    ]);
});

function fakeBlock(
    int $id,
    int $categoryId,
    string $start,
    ?string $end,
    bool $active = true,
    ?int $userId = null,
): object {
    return (object) [
        'id' => $id,
        'category_id' => $categoryId,
        'start' => Carbon::parse($start),
        'end' => $end ? Carbon::parse($end) : null,
        'active' => $active,
        'user_id' => $userId,
    ];
}

function fakeSlotRow(int $programId, int $categoryId, string $slotDate): object
{
    return (object) [
        'program_id' => $programId,
        'category_id' => $categoryId,
        'slot_date' => $slotDate,
    ];
}
