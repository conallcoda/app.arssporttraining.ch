<?php

namespace App\Training;

use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramBlockTypeEnum;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarBlockService
{
    public function findCategoryOverlap(
        int $groupId,
        int $categoryId,
        Carbon $start,
        ?Carbon $end = null,
        ?int $userId = null,
        ?int $parentId = null,
        ?int $excludeBlockId = null,
    ): ?TrainingProgramBlock {
        $end ??= $start->copy();

        $query = TrainingProgramBlock::query()
            ->where('group_id', $groupId)
            ->where('category_id', $categoryId)
            ->where('type', TrainingProgramBlockTypeEnum::Category)
            ->where('active', true)
            ->where('start', '<=', $end->format('Y-m-d'))
            ->where(function ($q) use ($start) {
                $q->where('end', '>=', $start->format('Y-m-d'))
                    ->orWhere(function ($q2) use ($start) {
                        $q2->whereNull('end')
                            ->where('start', '>=', $start->format('Y-m-d'));
                    });
            });

        if ($excludeBlockId !== null) {
            $query->whereKeyNot($excludeBlockId);
        }

        if ($parentId !== null) {
            $query->whereKeyNot($parentId);
        }

        if ($userId === null && $parentId === null) {
            $query->whereNull('user_id')
                ->whereNull('parent_id');
        } else {
            $query->where(function ($q) use ($userId) {
                $q->where(function ($q2) {
                    $q2->whereNull('user_id')
                        ->whereNull('parent_id');
                });

                if ($userId !== null) {
                    $q->orWhere('user_id', $userId);
                }
            });
        }

        return $query
            ->orderBy('start')
            ->orderBy('id')
            ->first();
    }

    public function getEffectiveCategoryBlocks(int $groupId, ?int $userId, int $categoryId): Collection
    {
        $blocks = TrainingProgramBlock::query()
            ->where('group_id', $groupId)
            ->where('category_id', $categoryId)
            ->where('type', TrainingProgramBlockTypeEnum::Category)
            ->whereNull('parent_id')
            ->where(function ($q) use ($userId) {
                if ($userId !== null) {
                    $q->whereNull('user_id')
                        ->orWhere('user_id', $userId);
                } else {
                    $q->whereNull('user_id');
                }
            })
            ->orderBy('start')
            ->get();

        return $this->resolveOverrides($blocks, $userId);
    }

    public function findOverlappingBlock(int $groupId, ?int $userId, int $categoryId, ?Carbon $date = null): ?TrainingProgramBlock
    {
        $date ??= Carbon::today();
        $effective = $this->getEffectiveCategoryBlocks($groupId, $userId, $categoryId);

        if ($effective->isEmpty()) {
            return null;
        }

        return $effective->first(function ($block) use ($date) {
            $end = $block->end ?? $block->start;

            return $block->start->lte($date) && $end->gte($date);
        }) ?? $effective->first();
    }

    public function getBlockDateRanges(int $groupId, ?int $userId, int $categoryId): array
    {
        $blocks = TrainingProgramBlock::query()
            ->where('group_id', $groupId)
            ->where('category_id', $categoryId)
            ->where('type', TrainingProgramBlockTypeEnum::Category)
            ->whereNull('parent_id')
            ->get();

        $overridesByParent = $this->loadOverridesByParent($blocks, $userId);

        $ranges = [];
        foreach ($blocks as $block) {
            $override = $overridesByParent->get($block->id);
            if ($override && ! $override->active) {
                continue;
            }
            $effective = $override ?? $block;
            $ranges[] = [
                $effective->start->startOfDay(),
                ($effective->end ?? $effective->start)->endOfDay(),
            ];
        }

        return $ranges;
    }

    public function getCategoryBlocksForDateRange(int $groupId, ?int $userId, Carbon $start, Carbon $end): Collection
    {
        $blocks = TrainingProgramBlock::query()
            ->where('group_id', $groupId)
            ->whereNotNull('category_id')
            ->whereNull('parent_id')
            ->where('type', TrainingProgramBlockTypeEnum::Category)
            ->where(function ($q) use ($userId) {
                if ($userId !== null) {
                    $q->whereNull('user_id')
                        ->orWhere('user_id', $userId);
                } else {
                    $q->whereNull('user_id');
                }
            })
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($q2) use ($start, $end) {
                    $q2->where('start', '<=', $end->format('Y-m-d'))
                        ->where(function ($q3) use ($start) {
                            $q3->where('end', '>=', $start->format('Y-m-d'))
                                ->orWhereNull('end');
                        });
                })->orWhereBetween('start', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
            })
            ->get();

        $overridesByParent = $this->loadOverridesByParent($blocks, $userId);

        return collect(['blocks' => $blocks, 'overridesByParent' => $overridesByParent]);
    }

    public function buildBlockOptions(int $groupId, ?int $userId, int $categoryId): array
    {
        $effective = $this->getEffectiveCategoryBlocks($groupId, $userId, $categoryId);
        $options = [];

        foreach ($effective as $block) {
            $label = $block->note ?: $block->start->format('M d').($block->end ? ' - '.$block->end->format('M d') : '');
            $options[$block->id] = $label;
        }

        return $options;
    }

    protected function resolveOverrides(Collection $blocks, ?int $userId): Collection
    {
        if ($blocks->isEmpty()) {
            return collect();
        }

        $overridesByParent = $this->loadOverridesByParent($blocks, $userId);

        return $blocks->map(function ($block) use ($overridesByParent) {
            if ($block->user_id !== null) {
                return $block;
            }

            $override = $overridesByParent->get($block->id);
            if ($override && ! $override->active) {
                return null;
            }

            return $override ?? $block;
        })->filter()->values();
    }

    protected function loadOverridesByParent(Collection $blocks, ?int $userId): Collection
    {
        if ($userId === null) {
            return collect();
        }

        $groupBlockIds = $blocks->whereNull('user_id')->pluck('id');
        if ($groupBlockIds->isEmpty()) {
            return collect();
        }

        return TrainingProgramBlock::query()
            ->whereNotNull('parent_id')
            ->where('user_id', $userId)
            ->whereIn('parent_id', $groupBlockIds)
            ->get()
            ->keyBy('parent_id');
    }
}
