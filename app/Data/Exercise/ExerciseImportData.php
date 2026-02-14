<?php

namespace App\Data\Exercise;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;
use App\Models\Exercise\Exercise;

class ExerciseImportData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id = null,
        public ?int $externalId = null,
        public string $name = '',
        public ?int $category = null,
        public array $equipment = [],
        public array $modifiers = [],
        public ?string $videoUrl = null,
        public ?string $instructions = null,
        public ExerciseConfig $config = new ExerciseConfig,
    ) {}

    public function persist(): void
    {
        $exercise = Exercise::create([
            'name' => $this->name,
            'video_url' => $this->videoUrl,
            'instructions' => $this->instructions,
            'config' => $this->config?->toArray() ?? [],
            'category_id' => $this->category,
            'external_id' => $this->externalId,
        ]);

        $this->id = $exercise->id;

        $tagIds = collect([
            ...$this->equipment,
            ...$this->modifiers,
        ])->filter()->all();

        $exercise->tags()->sync($tagIds);
    }

    public static function getForm(): Form
    {
        return ExerciseData::getForm();
    }
}
