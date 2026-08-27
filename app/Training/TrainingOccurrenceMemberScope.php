<?php

namespace App\Training;

use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\UserGroup;

class TrainingOccurrenceMemberScope
{
    /**
     * @param  array<int, mixed>  $selectedMembers
     * @param  array<int, mixed>  $deselectedMembers
     * @return array{
     *     selected_members: list<int>,
     *     deselected_members: list<int>,
     *     affected_user_ids: list<int>,
     *     requested_removal_user_ids: list<int>,
     *     removal_confirmation_missing: bool
     * }
     */
    public function resolve(
        ?int $filteredUserId,
        ?int $groupId,
        ?int $originalTrainingProgramId,
        ?string $originalDatetime,
        array $selectedMembers,
        array $deselectedMembers,
        bool $removalsConfirmed,
    ): array {
        if ($filteredUserId !== null) {
            return [
                'selected_members' => [],
                'deselected_members' => [],
                'affected_user_ids' => [$filteredUserId],
                'requested_removal_user_ids' => [],
                'removal_confirmation_missing' => false,
            ];
        }

        $groupMemberIds = $groupId === null
            ? []
            : UserGroup::query()
                ->find($groupId)?->members()
                ->pluck('users.id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all() ?? [];
        $selected = $this->withinGroup($selectedMembers, $groupMemberIds);
        $requestedRemovals = $this->withinGroup($deselectedMembers, $groupMemberIds);

        if ($originalTrainingProgramId === null || $originalDatetime === null) {
            return [
                'selected_members' => $selected,
                'deselected_members' => [],
                'affected_user_ids' => $selected,
                'requested_removal_user_ids' => [],
                'removal_confirmation_missing' => false,
            ];
        }

        if ($requestedRemovals !== [] && ! $removalsConfirmed) {
            return [
                'selected_members' => $selected,
                'deselected_members' => [],
                'affected_user_ids' => array_values(array_unique([...$selected, ...$requestedRemovals])),
                'requested_removal_user_ids' => $requestedRemovals,
                'removal_confirmation_missing' => true,
            ];
        }

        $originalScheduledMemberIds = TrainingProgramSlot::query()
            ->where('training_program_id', $originalTrainingProgramId)
            ->where('datetime', $originalDatetime)
            ->whereIn('user_id', $groupMemberIds)
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $confirmedRemovals = array_values(array_intersect($requestedRemovals, $originalScheduledMemberIds));

        return [
            'selected_members' => $selected,
            'deselected_members' => $confirmedRemovals,
            'affected_user_ids' => array_values(array_unique([...$selected, ...$confirmedRemovals])),
            'requested_removal_user_ids' => $requestedRemovals,
            'removal_confirmation_missing' => false,
        ];
    }

    /**
     * @param  array<int, mixed>  $candidateIds
     * @param  list<int>  $groupMemberIds
     * @return list<int>
     */
    private function withinGroup(array $candidateIds, array $groupMemberIds): array
    {
        $normalized = array_values(array_unique(array_map('intval', $candidateIds)));

        return array_values(array_intersect($normalized, $groupMemberIds));
    }
}
