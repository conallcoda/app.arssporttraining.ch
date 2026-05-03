<?php

namespace App\Support\Training;

use App\Models\Tag;
use Illuminate\Support\Collection;

class BlockModalPayloadBuilder
{
    /**
     * @param  Collection<int|string, array{category:mixed}>  $groupedPrograms
     * @return array<int, array{name:string,slug:string}>
     */
    public function categoryOptions(Collection $groupedPrograms): array
    {
        return $groupedPrograms
            ->filter(fn (array $group, int $categoryId) => $categoryId > 0 && $group['category'] !== null)
            ->mapWithKeys(fn (array $group, int $categoryId) => [
                $categoryId => [
                    'name' => $group['category']->name,
                    'slug' => $group['category']->slug,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<int, array{name:string,slug:string}>  $categoryOptions
     * @return array<string, mixed>
     */
    public function forAdd(int $groupId, ?int $userId, array $categoryOptions): array
    {
        return [
            'groupId' => $groupId,
            'userId' => $userId,
            'categoryOptions' => $categoryOptions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forDate(string $date, int $groupId, ?int $userId): array
    {
        return [
            'date' => $date,
            'groupId' => $groupId,
            'userId' => $userId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forDateRange(string $startDate, string $endDate, int $groupId, ?int $userId): array
    {
        return [
            'date' => $startDate,
            'endDate' => $endDate,
            'groupId' => $groupId,
            'userId' => $userId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forCategoryDate(string $date, int $groupId, ?int $userId, int $categoryId, ?Tag $tag): array
    {
        return [
            'date' => $date,
            'groupId' => $groupId,
            'userId' => $userId,
            'categoryId' => $categoryId,
            'categorySlug' => $tag?->slug,
            'categoryName' => $tag?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forCategoryDateRange(string $startDate, string $endDate, int $groupId, ?int $userId, int $categoryId, ?Tag $tag): array
    {
        return [
            'date' => $startDate,
            'endDate' => $endDate,
            'groupId' => $groupId,
            'userId' => $userId,
            'categoryId' => $categoryId,
            'categorySlug' => $tag?->slug,
            'categoryName' => $tag?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forEdit(?int $blockId, int $groupId, ?int $userId, ?int $parentId = null): array
    {
        $payload = [
            'blockId' => $blockId,
            'groupId' => $groupId,
            'userId' => $userId,
        ];

        if ($parentId !== null) {
            $payload['parentId'] = $parentId;
        }

        return $payload;
    }
}
