<?php

namespace App\Actions;

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseExternal;
use App\Models\Tag;

class DeleteCategoryTree
{
    public function getAffectedExerciseCount(Tag $tag): int
    {
        $tagIds = $tag->descendantsAndSelf()->pluck('id');

        $exerciseCount = Exercise::whereIn('category_id', $tagIds)->count();
        $externalCount = ExerciseExternal::whereIn('category_id', $tagIds)->count();

        return $exerciseCount + $externalCount;
    }

    public function getDescendantCount(Tag $tag): int
    {
        return $tag->descendants()->count();
    }

    public function execute(Tag $tag): void
    {
        $tagIds = $tag->descendantsAndSelf()->pluck('id');

        Exercise::whereIn('category_id', $tagIds)->update(['category_id' => null]);
        ExerciseExternal::whereIn('category_id', $tagIds)->update(['category_id' => null]);

        $tag->descendants()->orderByDesc('depth')->each(function (Tag $descendant) {
            $descendant->delete();
        });

        $tag->delete();
    }
}
